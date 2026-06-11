<?php

namespace Dashed\DashedEcommerceMyParcel\Classes;

use Exception;
use Throwable;
use Illuminate\Support\Facades\Log;
use Dashed\DashedCore\Classes\Mails;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use MyParcelNL\Sdk\src\Model\Recipient;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use MyParcelNL\Sdk\src\Model\Carrier\CarrierDPD;
use MyParcelNL\Sdk\src\Helper\MyParcelCollection;
use MyParcelNL\Sdk\src\Factory\ConsignmentFactory;
use MyParcelNL\Sdk\src\Model\Carrier\CarrierBpost;
use MyParcelNL\Sdk\src\Model\Carrier\CarrierPostNL;
use MyParcelNL\Sdk\src\Model\Carrier\CarrierDHLForYou;
use Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder;
use MyParcelNL\Sdk\src\Model\Carrier\CarrierDHLEuroplus;

class MyParcel
{
    public const LABEL_BATCH_SIZE = 20;

    public static function getUserAgent(): string
    {
        return 'DashedCMS/2.0 PHP/8.2';
    }

    public static function apiKey(?string $siteId = null, $encoded = true): string
    {
        if (! $siteId) {
            $siteId = Sites::getActive();
        }

        $apiKey = Customsetting::get('my_parcel_api_key', $siteId, disableCache: true);

        return $encoded ? base64_encode($apiKey) : $apiKey;
    }

    /**
     * Haalt het label-PDF voor één MyParcel-order op uit MyParcel en slaat het op
     * de public disk op, zodat het geprint kan worden (bv. door de print-daemon).
     *
     * Idempotent: als er al een geldig label_pdf_path is, wordt dat hergebruikt.
     * Markeert bewust NIET als 'gedownload/geprint' — dat blijft aan de
     * daadwerkelijke print/admin-actie. Geeft het pad (relatief op de public
     * disk) terug, of null als er geen shipment is om op te halen.
     */
    public static function downloadLabelForOrder(MyParcelOrder $myParcelOrder): ?string
    {
        if ($myParcelOrder->label_pdf_path && Storage::disk('public')->exists($myParcelOrder->label_pdf_path)) {
            return $myParcelOrder->label_pdf_path;
        }

        if (! $myParcelOrder->shipment_id) {
            return null;
        }

        $apiKey = self::apiKey($myParcelOrder->order->site_id, encoded: false);

        $consignments = (new MyParcelCollection())
            ->setUserAgents(['DashedCMS', '2.0']);
        $consignments = $consignments->addConsignmentByConsignmentIds([(int) $myParcelOrder->shipment_id], $apiKey);
        $response = $consignments->setPdfOfLabels('a6');

        $pdf = $response->getLabelPdf();

        $filePath = 'dashed/orders/my-parcel/label-'
            . ($myParcelOrder->order->invoice_id ?: $myParcelOrder->order_id)
            . '-' . \Illuminate\Support\Str::random(40) . '.pdf';
        Storage::disk('public')->put($filePath, $pdf);

        $myParcelOrder->label_pdf_path = $filePath;
        $myParcelOrder->save();

        return $filePath;
    }

    public static function baseUrl(): string
    {
        return 'https://api.myparcel.nl';
    }

    public static function connectOrderWithCarrier(Order $order)
    {
        if (MyParcel::isConnected($order->site_id) && ! $order->myParcelOrders()->count()) {
            $packageTypeIds = [];

            foreach ($order->orderProducts as $orderProduct) {
                if ($orderProduct->product) {
                    $packageTypeIds[] = $orderProduct->product->productGroup->contentBlocks['my-parcel-package-type'] ?? Customsetting::get('my_parcel_default_package_type_' . $order->countryIsoCode, $order->site_id);
                }
            }

            $order->myParcelOrders()->create([
                'carrier' => Customsetting::get('my_parcel_default_carrier_' . $order->countryIsoCode, $order->site_id),
                'package_type' => MyParcel::getBiggestPackageNeededByIds($order->countryIsoCode, $packageTypeIds, $order->site_id),
                'delivery_type' => Customsetting::get('my_parcel_default_delivery_type_' . $order->countryIsoCode, $order->site_id),
            ]);

            $orderLog = new OrderLog();
            $orderLog->order_id = $order->id;
            $orderLog->user_id = null;
            $orderLog->tag = 'system.note.created';
            $orderLog->note = 'Bestelling klaargezet voor MyParcel';
            $orderLog->save();
        } elseif (! MyParcel::isConnected($order->site_id)) {
            $orderLog = new OrderLog();
            $orderLog->order_id = $order->id;
            $orderLog->user_id = null;
            $orderLog->tag = 'system.note.created';
            $orderLog->note = 'MyParcel niet geconnect, bestelling niet klaargezet voor MyParcel';
            $orderLog->save();
        }
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

    public static function createConcepts()
    {
        $siteId = Sites::getActive();
        $apiKey = self::apiKey($siteId, encoded: false);

        // Sla orders met een al-gezette error over: dezelfde input geeft
        // dezelfde API-fout, dus elke minuut opnieuw proberen levert geen
        // nieuwe info en zorgt voor mail-spam naar de admins. De "Opnieuw
        // in wachtrij zetten"-actie op de Filament-page zet error op null,
        // waarna deze cron-run 'm weer oppakt.
        $myParcelOrders = MyParcelOrder::where('label_printed', 0)
            ->whereNull('shipment_id')
            ->whereNull('error')
            ->get();
        $orders = [];
        $failures = [];

        foreach ($myParcelOrders as $myParcelOrder) {
            if ($myParcelOrder->order->site_id !== $siteId) {
                continue;
            }

            if (! $myParcelOrder->carrier) {
                $countryIsoCode = $myParcelOrder->order->countryIsoCode;
                $defaultCarrier = Customsetting::get('my_parcel_default_carrier_' . $countryIsoCode, $myParcelOrder->order->site_id);

                if ($defaultCarrier) {
                    $order = $myParcelOrder->order;
                    $myParcelOrder->delete();
                    self::connectOrderWithCarrier($order);
                    $myParcelOrder = $order->myParcelOrders()->first();
                }
            }

            if (! $myParcelOrder || ! $myParcelOrder->carrier) {
                if ($myParcelOrder) {
                    $myParcelOrder->error = 'Geen standaard MyParcel-carrier ingesteld voor land ' . ($myParcelOrder->order->countryIsoCode ?? '?') . '. Configureer de carrier en gebruik "Opnieuw in wachtrij zetten".';
                    $myParcelOrder->save();

                    $failures[] = [
                        'invoice_id' => $myParcelOrder->order->invoice_id ?? $myParcelOrder->order_id,
                        'order_id' => $myParcelOrder->order_id,
                        'message' => $myParcelOrder->error,
                    ];
                }

                continue;
            }

            try {
                $consigment = (ConsignmentFactory::createByCarrierId(app(app($myParcelOrder->carrier)::CONSIGNMENT)->getCarrierId()))
                    ->setApiKey($apiKey)
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

                $consignments = (new MyParcelCollection())
                    ->setUserAgents(['DashedCMS', '2.0'])
                    ->addConsignment($consigment)
                    ->createConcepts();

                foreach ($consignments->getConsignments() as $shipment) {
                    $myParcelOrder->shipment_id = $shipment->getConsignmentId();
                    $myParcelOrder->error = null;
                    $myParcelOrder->save();
                }

                $orders[] = $myParcelOrder->order;
            } catch (Throwable $e) {
                $myParcelOrder->error = $e->getMessage();
                $myParcelOrder->save();

                $failures[] = [
                    'invoice_id' => $myParcelOrder->order->invoice_id ?? $myParcelOrder->order_id,
                    'order_id' => $myParcelOrder->order_id,
                    'message' => $e->getMessage(),
                ];

                Log::warning('MyParcel concept creation failed', [
                    'my_parcel_order_id' => $myParcelOrder->id,
                    'order_id' => $myParcelOrder->order_id,
                    'invoice_id' => $myParcelOrder->order->invoice_id ?? null,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if (! empty($failures)) {
            $lines = array_map(function ($failure) {
                $line = 'Bestelling ' . e($failure['invoice_id']) . ': ' . e($failure['message']);

                $url = $failure['order_id']
                    ? rescue(fn () => route('filament.dashed.resources.orders.view', ['record' => $failure['order_id']]), null, false)
                    : null;

                if ($url) {
                    $line .= '<br><a href="' . $url . '" style="display: inline-block; margin-top: 8px; padding: 8px 16px; background-color: #000000; color: #ffffff; border-radius: 6px; text-decoration: none; font-weight: bold;">Bekijk bestelling</a>';
                }

                return $line;
            }, $failures);

            Mails::sendNotificationToAdmins(
                'MyParcel kon de volgende bestellingen niet als concept aanmaken. Corrigeer de gegevens en probeer opnieuw:<br><br>' . implode('<br><br>', $lines),
                count($failures) === 1
                    ? "MyParcel sync mislukt voor bestelling {$failures[0]['invoice_id']}"
                    : 'MyParcel sync mislukt voor ' . count($failures) . ' bestellingen'
            );
        }

        return [
            'orders' => $orders,
            'failures' => $failures,
        ];
    }

    /**
     * Onverwerkte MyParcel-labels voor de actieve site, beperkt tot één batch.
     * Dit is de batch-grens: max LABEL_BATCH_SIZE labels per document.
     */
    public static function unprintedOrdersForBatch(string $siteId, int $limit = self::LABEL_BATCH_SIZE)
    {
        return MyParcelOrder::where('label_printed', 0)
            ->where('is_return', false)
            ->whereNotNull('shipment_id')
            ->whereHas('order', fn ($q) => $q->where('site_id', $siteId))
            ->limit($limit)
            ->get();
    }

    public static function unprintedCount(string $siteId): int
    {
        return MyParcelOrder::where('label_printed', 0)
            ->where('is_return', false)
            ->whereNotNull('shipment_id')
            ->whereHas('order', fn ($q) => $q->where('site_id', $siteId))
            ->count();
    }

    public static function createShipments(int $limit = self::LABEL_BATCH_SIZE): array
    {
        $siteId = Sites::getActive();
        $apiKey = self::apiKey($siteId, encoded: false);

        $myParcelOrders = self::unprintedOrdersForBatch($siteId, $limit);

        $orders = [];
        $filePath = null;
        $printed = 0;

        if ($myParcelOrders->isNotEmpty()) {
            $consignments = (new MyParcelCollection())
                ->setUserAgents(['DashedCMS', '2.0']);

            $shipmentIds = [];
            foreach ($myParcelOrders as $myParcelOrder) {
                $shipmentIds[] = (int) $myParcelOrder->shipment_id;
                $orders[] = $myParcelOrder->order;
            }

            $consignments = $consignments->addConsignmentByConsignmentIds($shipmentIds, $apiKey);
            $response = $consignments->setPdfOfLabels('a6');

            foreach ($response->getConsignments() as $shipment) {
                $myParcelOrder = MyParcelOrder::find(str($shipment->getReferenceIdentifier())->explode('-')->first());
                $myParcelOrder->track_and_trace = [
                    [
                        $shipment->getBarcode() => $shipment->getBarcodeUrl($shipment->getBarcode(), $myParcelOrder->order->zip_code, $myParcelOrder->order->countryIsoCode),
                    ],
                ];
                $myParcelOrder->label_printed = 1;
                $myParcelOrder->save();
                $printed++;

                $myParcelOrder->order->addTrackAndTrace('my-parcel', $shipment->getCarrierName(), $shipment->getBarcode(), $shipment->getBarcodeUrl($shipment->getBarcode(), $myParcelOrder->order->zip_code, $myParcelOrder->order->countryIsoCode));
            }

            $pdf = $response->getLabelPdf();

            $filePath = 'dashed/orders/my-parcel/labels-' . \Illuminate\Support\Str::random(40) . '.pdf';
            Storage::disk('public')->put($filePath, $pdf);
        }

        return [
            'filePath' => $filePath,
            'orders' => $orders,
            'processed' => $printed,
            'hasMore' => self::unprintedCount($siteId) > 0,
        ];
    }

    /**
     * Maakt voor één MyParcelOrder een concept aan bij MyParcel en haalt
     * direct het label PDF op. De PDF wordt opgeslagen in de public disk
     * en het pad wordt teruggegeven samen met track-en-trace data.
     */
    /**
     * Maakt een verzendlabel aan voor een order met de (per-land) standaard
     * carrier/pakkettype/verzendtype, en haalt het label op. Voor de mobiele app:
     * één knop "Verzendlabel aanmaken" zonder formulier.
     */
    public static function createLabelForOrder(Order $order, array $overrides = []): array
    {
        $country = $order->countryIsoCode;

        $attrs = [
            'carrier' => $overrides['carrier'] ?? Customsetting::get("my_parcel_default_carrier_{$country}", null, CarrierPostNL::class),
            'package_type' => $overrides['package_type'] ?? Customsetting::get("my_parcel_default_package_type_{$country}", null, 1),
            'delivery_type' => $overrides['delivery_type'] ?? Customsetting::get("my_parcel_default_delivery_type_{$country}", null, 2),
            'is_return' => false,
        ];

        $myParcelOrder = $order->myParcelOrders()
            ->where('label_printed', 0)
            ->where('is_return', false)
            ->first();

        if ($myParcelOrder) {
            $myParcelOrder->update($attrs);
        } else {
            $myParcelOrder = $order->myParcelOrders()->create($attrs);
        }

        return self::createConceptAndLabelForOrder($myParcelOrder);
    }

    public static function createConceptAndLabelForOrder(MyParcelOrder $myParcelOrder): array
    {
        $apiKey = self::apiKey($myParcelOrder->order->site_id, encoded: false);

        if (! $myParcelOrder->carrier) {
            throw new Exception('Geen vervoerder ingesteld op deze MyParcel order.');
        }

        $consigment = (ConsignmentFactory::createByCarrierId(app(app($myParcelOrder->carrier)::CONSIGNMENT)->getCarrierId()))
            ->setApiKey($apiKey)
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

        $consignments = (new MyParcelCollection())
            ->setUserAgents(['DashedCMS', '2.0'])
            ->addConsignment($consigment)
            ->setPdfOfLabels('a6');

        foreach ($consignments->getConsignments() as $shipment) {
            $myParcelOrder->shipment_id = $shipment->getConsignmentId();
            $myParcelOrder->error = null;
            $myParcelOrder->track_and_trace = [
                [
                    $shipment->getBarcode() => $shipment->getBarcodeUrl(
                        $shipment->getBarcode(),
                        $myParcelOrder->order->zip_code,
                        $myParcelOrder->order->countryIsoCode
                    ),
                ],
            ];
            // label_printed bewust 0 laten: pas wanneer de admin daadwerkelijk
            // op de download-knop klikt (in show-my-parcel-orders.blade.php)
            // zetten we 'm op 1. Dit voorkomt dat de "Label gedownload"-badge
            // verschijnt voordat het PDF echt is opgehaald.
            $myParcelOrder->save();

            $myParcelOrder->order->addTrackAndTrace(
                'my-parcel',
                $shipment->getCarrierName(),
                $shipment->getBarcode(),
                $shipment->getBarcodeUrl(
                    $shipment->getBarcode(),
                    $myParcelOrder->order->zip_code,
                    $myParcelOrder->order->countryIsoCode
                )
            );
        }

        $pdf = $consignments->getLabelPdf();

        $filePath = 'dashed/orders/my-parcel/label-' . $myParcelOrder->order->invoice_id . '-' . \Illuminate\Support\Str::random(40) . '.pdf';
        Storage::disk('public')->put($filePath, $pdf);

        $myParcelOrder->label_pdf_path = $filePath;
        $myParcelOrder->save();

        return [
            'filePath' => $filePath,
            'pdf' => $pdf,
            'myParcelOrder' => $myParcelOrder,
        ];
    }

    /**
     * Maakt een retour-zending aan bij MyParcel voor één order. Dit gebruikt
     * de unrelated-return endpoint via createConcepts(asUnrelatedReturn: true)
     * van de SDK, omdat we geen parent-zending hebben (de oorspronkelijke
     * bestelling is al uitgegaan en de klant moet vanuit huis terugsturen).
     * Het label wordt direct opgehaald, opgeslagen en teruggegeven.
     */
    public static function createReturnLabelForOrder(MyParcelOrder $myParcelOrder): array
    {
        $apiKey = self::apiKey($myParcelOrder->order->site_id, encoded: false);
        $siteId = $myParcelOrder->order->site_id;

        if (! $myParcelOrder->carrier) {
            throw new Exception('Geen vervoerder ingesteld op deze MyParcel order.');
        }

        $shopName = (string) Customsetting::get('site_name', $siteId, '');
        $shopStreet = (string) Customsetting::get('company_street', $siteId, '');
        $shopHouseNr = (string) Customsetting::get('company_street_number', $siteId, '');
        $shopPostalCode = trim((string) Customsetting::get('company_postal_code', $siteId, ''));
        $shopCity = (string) Customsetting::get('company_city', $siteId, '');
        $shopCountry = (string) Customsetting::get('company_country', $siteId, 'NL');
        $shopPhone = (string) Customsetting::get('company_phone_number', $siteId, '');

        if ($shopName === '' || $shopStreet === '' || $shopPostalCode === '' || $shopCity === '') {
            throw new Exception('Retouradres van de winkel is niet volledig ingevuld. Vul site_name, company_street, company_postal_code en company_city in onder Algemene instellingen.');
        }

        $customerSender = (new Recipient())
            ->setCc($myParcelOrder->order->countryIsoCode)
            ->setPerson($myParcelOrder->order->name)
            ->setFullStreet($myParcelOrder->order->street . ' ' . $myParcelOrder->order->house_nr)
            ->setPostalCode(trim((string) $myParcelOrder->order->zip_code))
            ->setCity($myParcelOrder->order->city)
            ->setEmail((string) $myParcelOrder->order->email)
            ->setPhone((string) $myParcelOrder->order->phone_number);

        $consigment = (ConsignmentFactory::createByCarrierId(app(app($myParcelOrder->carrier)::CONSIGNMENT)->getCarrierId()))
            ->setApiKey($apiKey)
            ->setReferenceIdentifier($myParcelOrder->id . '-' . $myParcelOrder->order->id . '-return')
            ->setPackageType($myParcelOrder->package_type)
            ->setDeliveryType($myParcelOrder->delivery_type)
            ->setCountry($shopCountry)
            ->setPerson($shopName)
            ->setFullStreet(trim($shopStreet . ' ' . $shopHouseNr))
            ->setPostalCode($shopPostalCode)
            ->setCity($shopCity)
            ->setPhone($shopPhone)
            ->setLabelDescription('Retour bestelling ' . $myParcelOrder->order->invoice_id)
            ->setSender($customerSender);

        $consignments = (new MyParcelCollection())
            ->setUserAgents(['DashedCMS', '2.0'])
            ->addConsignment($consigment);

        // Forward shipment van klant -> winkel met een expliciete custom-sender.
        // De unrelated-return endpoint zette de winkel als afzender op het
        // gerenderde label; daarom zetten we recipient = winkel, sender = klant
        // en draaien we de richting expliciet om. Vereist de 'custom sender'
        // permissie op het MyParcel account.
        $consignments->createConcepts();
        $consignments->setLabelFormat('a6');
        $consignments->setLatestData();
        $consignments->setPdfOfLabels('a6');

        foreach ($consignments->getConsignments() as $shipment) {
            $myParcelOrder->shipment_id = $shipment->getConsignmentId();
            $myParcelOrder->error = null;
            $myParcelOrder->is_return = true;
            $myParcelOrder->track_and_trace = [
                [
                    $shipment->getBarcode() => $shipment->getBarcodeUrl(
                        $shipment->getBarcode(),
                        $shopPostalCode,
                        $shopCountry,
                    ),
                ],
            ];
            // label_printed bewust 0 laten: download wordt pas geregistreerd
            // wanneer de admin op de download-knop klikt (of de retour-mail
            // wordt verstuurd).
            $myParcelOrder->save();
        }

        $pdf = $consignments->getLabelPdf();

        $filePath = 'dashed/orders/my-parcel/return-label-' . $myParcelOrder->order->invoice_id . '-' . \Illuminate\Support\Str::random(40) . '.pdf';
        Storage::disk('public')->put($filePath, $pdf);

        $myParcelOrder->label_pdf_path = $filePath;
        $myParcelOrder->save();

        return [
            'filePath' => $filePath,
            'pdf' => $pdf,
            'myParcelOrder' => $myParcelOrder,
        ];
    }

    public static function getLabelsFromShipments(array $shipmentIds = [])
    {
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])
            ->post('https://api.myparcel.com/api/v2/label?api_token=' . Customsetting::get('my_parcel_api_key'), [
                'shipments' => $shipmentIds,
            ])
            ->json();

        return $response;
    }

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
        if ((count($packageTypeIds) >= Customsetting::get('my_parcel_minimum_product_count_' . $region, $siteId)) && Customsetting::get('my_parcel_minimum_product_count_package_type_' . $region, $siteId)) {
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
