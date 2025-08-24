<?php

namespace Dashed\DashedEcommerceMyParcel\Listeners;

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Classes\Countries;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceMyParcel\Classes\MyParcel;
use Dashed\DashedEcommerceCore\Events\Orders\OrderMarkedAsPaidEvent;

class MarkOrderAsPushableListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param object $event
     * @return void
     */
    public function handle(OrderMarkedAsPaidEvent $event)
    {
        if (Customsetting::get('my_parcel_automatically_push_orders', $event->order->site_id)) {
            if (MyParcel::isConnected($event->order->site_id) && ! $event->order->myParcelOrders()->count()) {
                $packageTypeIds = [];

                foreach ($event->order->orderProducts as $orderProduct) {
                    if ($orderProduct->product) {
                        $packageTypeIds[] = $orderProduct->product->productGroup->contentBlocks['my-parcel-package-type'] ?? Customsetting::get('my_parcel_default_package_type_' . $event->order->countryIsoCode, $event->order->site_id);
                    }
                }

                $event->order->myParcelOrders()->create([
                    'carrier' => Customsetting::get('my_parcel_default_carrier_' . $event->order->countryIsoCode, $event->order->site_id),
                    'package_type' => MyParcel::getBiggestPackageNeededByIds($event->order->countryIsoCode, $packageTypeIds, $event->order->site_id),
                    'delivery_type' => Customsetting::get('my_parcel_default_delivery_type_' . $event->order->countryIsoCode, $event->order->site_id),
                ]);

                $orderLog = new OrderLog();
                $orderLog->order_id = $event->order->id;
                $orderLog->user_id = null;
                $orderLog->tag = 'system.note.created';
                $orderLog->note = 'Bestelling klaargezet voor MyParcel';
                $orderLog->save();
            } elseif (! MyParcel::isConnected($event->order->site_id)) {
                $orderLog = new OrderLog();
                $orderLog->order_id = $event->order->id;
                $orderLog->user_id = null;
                $orderLog->tag = 'system.note.created';
                $orderLog->note = 'MyParcel niet geconnect, bestelling niet klaargezet voor MyParcel';
                $orderLog->save();
            }
        }
    }
}
