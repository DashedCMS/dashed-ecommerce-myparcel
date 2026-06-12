<?php

namespace Dashed\DashedEcommerceMyParcel\Listeners;

use Illuminate\Support\Facades\Mail;
use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use MyParcelNL\Sdk\src\Model\Carrier\CarrierPostNL;
use Dashed\DashedEcommerceMyParcel\Classes\MyParcel;
use Dashed\DashedEcommerceMyParcel\Mail\ReturnLabelMail;
use Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder;
use Dashed\DashedEcommerceCore\Contracts\ReturnLabelProvider;
use Dashed\DashedEcommerceCore\Events\Orders\OrderReturnApprovedEvent;

/**
 * Genereert en mailt automatisch een MyParcel-retourlabel zodra een
 * OrderReturn is goedgekeurd (handmatig of automatisch).
 *
 * De logica spiegelt de handmatige flow in
 * ShowCreateMyParcelReturnLabelOrder: er wordt een nieuwe return-type
 * MyParcelOrder aangemaakt met de standaard vervoerder/pakkettype/verzendtype
 * van de regio, MyParcel::createReturnLabelForOrder() gooit bij een fout en
 * geeft bij succes een array met 'filePath' terug, waarna ReturnLabelMail
 * met (Order, filePath, personalNote) wordt verstuurd.
 */
class CreateMyParcelReturnLabelListener implements ReturnLabelProvider, ShouldQueue
{
    public function appliesTo(Order $order): bool
    {
        return MyParcelOrder::query()->where('order_id', $order->id)->exists();
    }

    public function createAndSendReturnLabel(OrderReturn $orderReturn): bool
    {
        $order = $orderReturn->order;
        if (! $order) {
            return false;
        }

        if (! MyParcel::isConnected($order->site_id)) {
            return false;
        }

        $iso = $order->countryIsoCode;

        // Nieuwe return-type MyParcelOrder aanmaken, gelijk aan de handmatige
        // flow. We hergebruiken bewust NIET de bestaande verzend-order, zodat
        // de oorspronkelijke verzending niet wordt overschreven.
        $myParcelOrder = $order->myParcelOrders()->create([
            'carrier' => Customsetting::get('my_parcel_default_carrier_' . $iso, $order->site_id, CarrierPostNL::class),
            'package_type' => Customsetting::get('my_parcel_default_package_type_' . $iso, $order->site_id, 1),
            'delivery_type' => Customsetting::get('my_parcel_default_delivery_type_' . $iso, $order->site_id, 2),
            'is_return' => true,
        ]);

        $result = MyParcel::createReturnLabelForOrder($myParcelOrder);

        $filePath = $result['filePath'] ?? null;
        if (! $filePath) {
            return false;
        }

        $orderReturn->return_label_provider = 'myparcel';
        $orderReturn->return_label_path = $filePath;
        $orderReturn->save();

        $email = $orderReturn->email ?: $order->email;
        if ($email) {
            Mail::to($email)->send(new ReturnLabelMail($order, $filePath, null));

            $myParcelOrder->is_label_email_sent = true;
            $myParcelOrder->save();
        }

        return true;
    }

    public function handle(OrderReturnApprovedEvent $event): void
    {
        try {
            $order = $event->orderReturn->order;
            if (! $order || ! $this->appliesTo($order)) {
                return;
            }
            $this->createAndSendReturnLabel($event->orderReturn);
        } catch (\Throwable $e) {
            report($e);
            $log = new OrderLog();
            $log->order_id = $event->orderReturn->order_id;
            $log->tag = 'order.return-label-failed';
            $log->save();
        }
    }
}
