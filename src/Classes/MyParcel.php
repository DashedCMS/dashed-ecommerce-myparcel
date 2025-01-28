<?php

namespace Dashed\DashedEcommerceMyParcel\Classes;

use Dashed\DashedCore\Classes\Sites;
use Exception;
use Illuminate\Support\Facades\Http;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use MyParcelNL\Sdk\src\Helper\MyParcelCollection;
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

    public static function createShipment(Order $order, $formData)
    {
//        try{
        $carrier = $formData['carrier'] ?? Customsetting::get('my_parcel_default_carrier', $order->site_id, CarrierPostNL::class);
        $consigment = (ConsignmentFactory::createByCarrierId(app(app($carrier)::CONSIGNMENT)->getCarrierId()))
            ->setApiKey(self::apiKey($order->site_id, false))
            ->setReferenceIdentifier('order-' . $order->id)
            ->setCountry($order->countryIsoCode)
            ->setPerson($order->name)
            ->setFullStreet($order->street . ' ' . $order->house_nr)
            ->setPostalCode(trim($order->zip_code))
            ->setCity($order->city)
            ->setEmail($order->email)
            ->setPhone($order->phone_number)
            ->setLabelDescription('Bestelling ' . $order->invoice_id);

        $consigments = (new MyParcelCollection())
            ->setPdfOfLabels('a6');
        $consigments->addConsignment($consigment);
        $consigments->addConsignment($consigment);


        $consigmentId = $consigments->first()->getConsignmentId();

        $response = $consigments->downloadPdfOfLabels();
        dd($response);

//        }catch (Exception $e){
//            return [
//                'success' => false,
//                'message' => $e->getMessage(),
//            ];
//        }

        dd($consigmentId, $response);

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
