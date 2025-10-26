<?php

namespace Dashed\DashedEcommerceMyParcel\Listeners;

use Dashed\DashedCore\Models\Customsetting;
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
        if (Customsetting::get('my_parcel_automatically_push_orders', $event->order->site_id) && $event->order->street && $event->order->order_origin != 'pos') {
            MyParcel::connectOrderWithCarrier($event->order);
        }
    }
}
