<?php

namespace Dashed\DashedEcommerceMyParcel\Classes;

use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Http;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use MyParcelNL\Sdk\src\Model\Carrier\CarrierDPD;
use MyParcelNL\Sdk\src\Factory\ConsignmentFactory;
use MyParcelNL\Sdk\src\Model\Carrier\CarrierBpost;
use MyParcelNL\Sdk\src\Model\Carrier\CarrierPostNL;

class MyParcel
{
    public static function getUserAgent(): string
    {
        return 'DashedCMS/2.0 PHP/8.2';
    }

    public static function apiKey($siteId): string
    {
        return base64_encode(Customsetting::get('my_parcel_api_key', $siteId));
    }

    public static function baseUrl(): string
    {
        return 'https://api.myparcel.nl';
    }

    public static function isConnected($siteId = null)
    {
        if (! $siteId) {
            $siteId = Sites::getActive();
        }

        $response = Http::withToken(self::apiKey($siteId))
            ->get(self::baseUrl())
            ->json();
        if (($response['status'] ?? false) == 'OK') {
            Customsetting::set('my_parcel_connection_error', null, $siteId);

            return true;
        } else {
            Customsetting::set('my_parcel_connection_error', $response['message'] ?? 'kan niet connecten', $siteId);

            return false;
        }
    }

    public static function createShipment(Order $order, $formData)
    {
        $carrier = $formData['carrier'] ?? Customsetting::get('my_parcel_default_carrier', $order->site_id, CarrierPostNL::class);
        dd(app(CarrierPostNL::class));
        dd(app($carrier)::CONSIGNMENT->getCarrierId());
        $consignment = (ConsignmentFactory::createByCarrierId(app(Customsetting::get('my_parcel_default_carrier', $order->site_id, CarrierPostNL::class))::CONSIGNMENT->getCarrierId()));
        dd($consignment);

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
            'Accept' => 'application/vnd.shipment_label+json;charset=utf-8',
            'Content-Type' => 'application/vnd.shipment+json;charset=utf-8;version=1.1',
        ])
            ->withToken(self::apiKey($order->site_id))
            ->post(self::baseUrl() . '/shipments?paper_size=a6', [$data])
            ->json();

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

    public static function getPackageTypes(): array
    {
        return [
            1 => 'Pakket',
            2 => 'Brievenbus post',
            3 => 'Brief',
            4 => 'Digitale stamp',
            5 => 'Pallet',
            6 => 'Klein pakket',
        ];
    }

    public static function getDeliveryTypes(): array
    {
        return [
            1 => 'Sochtends',
            2 => 'Standaard',
            3 => 'Savonds',
            4 => 'Pakket punt',
            5 => 'Pakket punt express',
        ];
    }

    public static function getCarriers(): array
    {
        return [
            CarrierPostNL::class => 'PostNL',
            CarrierBpost::class => 'Bpost',
            CarrierDPD::class => 'DPD',
        ];
    }
}
