<?php

namespace Dashed\DashedEcommerceMyParcel\Classes;

use Exception;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedCore\Models\Customsetting;
use MyParcelNL\Sdk\src\Model\Carrier\CarrierDHLEuroplus;
use MyParcelNL\Sdk\src\Model\Carrier\CarrierDHLForYou;
use MyParcelNL\Sdk\src\Model\Carrier\CarrierDPD;
use MyParcelNL\Sdk\src\Helper\MyParcelCollection;
use MyParcelNL\Sdk\src\Factory\ConsignmentFactory;
use MyParcelNL\Sdk\src\Model\Carrier\CarrierBpost;
use MyParcelNL\Sdk\src\Model\Carrier\CarrierPostNL;
use Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder;

class MyParcel
{
    public static function getUserAgent(): string
    {
        return 'DashedCMS/2.0 PHP/8.2';
    }

    public static function apiKey($siteId, $encoded = true): string
    {
        return $encoded ? base64_encode(Customsetting::get('my_parcel_api_key', $siteId)) : Customsetting::get('my_parcel_api_key', $siteId);
    }

    public static function baseUrl(): string
    {
        return 'https://api.myparcel.nl';
    }

    public static function isConnected($siteId = null)
    {
        if (!$siteId) {
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

    public static function createShipments()
    {
        $consignments = (new MyParcelCollection())
            ->setUserAgents(['DashedCMS', '2.0']);

        $myParcelOrders = MyParcelOrder::where('label_printed', 0)->get();
        $orders = [];

        foreach ($myParcelOrders as $key => $myParcelOrder) {
            try {
                $consigment = (ConsignmentFactory::createByCarrierId(app(app($myParcelOrder->carrier)::CONSIGNMENT)->getCarrierId()))
                    ->setApiKey(self::apiKey($myParcelOrder->order->site_id, false))
                    ->setReferenceIdentifier($myParcelOrder->id . '-' . $myParcelOrder->order->id)
                    ->setPackageType($myParcelOrder->package_type)
                    ->setDeliveryType($myParcelOrder->delivery_type)
                    ->setCountry($myParcelOrder->order->countryIsoCode)
                    ->setPerson($myParcelOrder->order->name)
                    ->setFullStreet($myParcelOrder->order->street . ' ' . $myParcelOrder->order->house_nr)
                    ->setPostalCode(trim($myParcelOrder->order->zip_code))
                    ->setCity($myParcelOrder->order->city)
                    ->setEmail($myParcelOrder->order->email)
                    ->setPhone($myParcelOrder->order->phone_number)
                    ->setLabelDescription('Bestelling ' . $myParcelOrder->order->invoice_id);

                $consignments->addConsignment($consigment);
                $orders[] = $myParcelOrder->order;
                $myParcelOrder->label_printed = 1;
                $myParcelOrder->save();
            } catch (Exception $e) {
                $myParcelOrder->error = $e->getMessage();
                $myParcelOrder->save();
            }
        }

        $response = $consignments
            ->setPdfOfLabels('a6');

        foreach ($response->getConsignments() as $shipment) {
            $myParcelOrder = MyParcelOrder::find(str($shipment->getReferenceIdentifier())->explode('-')->first());
            $myParcelOrder->shipment_id = $shipment->getConsignmentId();
            //            $myParcelOrder->label_printed = 1;
            $myParcelOrder->track_and_trace = [
                [
                    $shipment->getBarcode() => $shipment->getBarcodeUrl($shipment->getBarcode(), $myParcelOrder->order->zip_code, $myParcelOrder->order->countryIsoCode),
                ],
            ];
            $myParcelOrder->save();

            $myParcelOrder->order->addTrackAndTrace('my-parcel', $shipment->getCarrierName(), $shipment->getBarcode(), $shipment->getBarcodeUrl($shipment->getBarcode(), $myParcelOrder->order->zip_code, $myParcelOrder->order->countryIsoCode));
        }

        $pdf = $response->getLabelPdf();

        $filePath = 'dashed/orders/my-parcel/labels-' . time() . '.pdf';
        Storage::disk('public')->put($filePath, $pdf);

        return [
            'filePath' => $filePath,
            'orders' => $orders,
        ];
    }

    //    public static function getLabelsFromShipments(array $shipmentIds = [])
    //    {
    //        $response = Http::withHeaders([
    //            'Accept' => 'application/json',
    //            'Content-Type' => 'application/json',
    //        ])
    //            ->post('https://api.myparcel.com/api/v2/label?api_token=' . Customsetting::get('myparcel_api_key'), [
    //                'shipments' => $shipmentIds,
    //            ])
    //            ->json();
    //
    //        return $response;
    //    }

    public static function getShipment(int|string $shipmentId, string $siteId)
    {
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => self::getUserAgent(),
        ])
            ->withToken(self::apiKey($siteId))
            ->get(self::baseUrl() . '/shipments', [
                'id' => $shipmentId,
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

    public static function getBiggestPackageNeededByIds(string $region, array $packageTypeIds, string $siteId): int
    {
        if (count($packageTypeIds) >= Customsetting::get('my_parcel_minimum_product_count_' . $region, $siteId)) {
            return Customsetting::get('my_parcel_minimum_product_count_package_type_' . $region, $siteId);
        }

        if (in_array(5, $packageTypeIds)) {
            return 5;
        } elseif (in_array(4, $packageTypeIds)) {
            return 4;
        } elseif (in_array(1, $packageTypeIds)) {
            return 1;
        } elseif (in_array(6, $packageTypeIds)) {
            return 6;
        } elseif (in_array(2, $packageTypeIds)) {
            return 2;
        } elseif (in_array(3, $packageTypeIds)) {
            return 3;
        } else {
            return 3;
        }
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
            CarrierDHLForYou::class => 'DHL ForYou',
//            CarrierDHLEuroplus::class => 'DHL Europlus',
        ];
    }
}
