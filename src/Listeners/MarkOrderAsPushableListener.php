<?php

namespace Dashed\DashedEcommerceMyParcel\Listeners;

use Dashed\DashedCore\Models\Customsetting;
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
            if (MyParcel::isConnected($event->order->site_id)) {
                $event->order->myParcelOrders()->create([]);
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
