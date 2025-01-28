<?php

namespace Dashed\DashedEcommerceMyParcel\Classes;

use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Http;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceMyParcel\Models\MyParcelShippingMethod;
use Dashed\DashedEcommerceMyParcel\Models\MyParcelShippingMethodService;
use Dashed\DashedEcommerceMyParcel\Models\MyParcelShippingMethodServiceOption;

class MyParcel
{
    public static function isConnected($siteId = null)
    {
        if (! $siteId) {
            $siteId = Sites::getActive();
        }

        $response = Http::get('https://portal.keendelivery.com/api/v2/authorization?api_token=' . Customsetting::get('myparcel_api_key', $siteId));
        $response = json_decode($response->body(), true);
        if (isset($response['authorized']) && $response['authorized']) {
            return true;
        } else {
            return false;
        }
    }

    public static function syncShippingMethods($siteId = null)
    {
        if (! $siteId) {
            $siteId = Sites::getActive();
        }

        if (! self::isConnected($siteId)) {
            return;
        }

        $response = Http::get('https://portal.keendelivery.com/api/v2/shipping_methods?api_token=' . Customsetting::get('myparcel_api_key', $siteId));
        $response = json_decode($response->body(), true);

        foreach ($response['shipping_methods'] as $keenShippingMethod) {
            $shippingMethod = MyParcelShippingMethod::updateOrCreate(
                [
                    'value' => $keenShippingMethod['value'],
                    'site_id' => $siteId,
                ],
                [
                    'name' => $keenShippingMethod['text'],
                ]
            );

            foreach ($keenShippingMethod['services'] as $service) {
                $shippingMethodService = MyParcelShippingMethodService::updateOrCreate(
                    [
                        'myparcel_shipping_method_id' => $shippingMethod->id,
                        'value' => $service['value'],
                    ],
                    [
                        'name' => $service['text'],
                    ]
                );

                foreach ($service['options'] as $option) {
                    $shippingMethodServiceOption = MyParcelShippingMethodServiceOption::updateOrCreate(
                        [
                            'myparcel_shipping_method_service_id' => $shippingMethodService->id,
                            'field' => $option['field'],
                            'name' => $option['text'],
                        ],
                        [
                            'type' => $option['type'],
                            'mandatory' => $option['mandatory'],
                            'choices' => $option['choices'],
                        ]
                    );
                }
            }
        }
    }

    public static function getActiveShippingMethods($siteId)
    {
        $shippingMethods = self::getShippingMethods($siteId);

        foreach ($shippingMethods as $shippingKey => $shippingMethod) {
            foreach ($shippingMethod['services'] as $serviceKey => $service) {
                if (! $shippingMethods[$shippingKey]['services'][$serviceKey]['enabled']) {
                    unset($shippingMethods[$shippingKey]['services'][$serviceKey]);
                }
            }
        }

        return $shippingMethods;
    }

    public static function createShipment(Order $order, $formData)
    {
        $data = [
            'product' => $formData['shipping_method'],
            'service' => $formData['service'],
            'amount' => 1,
            'reference' => 'Order ' . $order->invoice_id,
            'company_name' => $order->company_name,
            'contact_person' => $order->name,
            'street_line_1' => $order->street,
            'number_line_1' => $order->house_nr,
            'number_line_1_addition' => '',
            'zip_code' => $order->zip_code,
            'city' => $order->city,
            'country' => $order->countryIsoCode,
            'phone' => $order->phone_number,
            'email' => $order->email,
        ];

        foreach ($formData as $key => $value) {
            if (str($key)->contains('shipping_method_service_') && str($key)->contains('_option_') && $value) {
                $data[str($key)->explode('_')->last()] = $value;
            }
        }

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post('https://portal.keendelivery.com/api/v2/shipment?api_token=' . Customsetting::get('myparcel_api_key'), $data);
        $response = json_decode($response->body(), true);

        return $response;
    }

    public static function getLabelsFromShipments(array $shipmentIds = [])
    {
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])
            ->post('https://portal.keendelivery.com/api/v2/label?api_token=' . Customsetting::get('myparcel_api_key'), [
                'shipments' => $shipmentIds,
            ])
            ->json();

        return $response;
    }
}
