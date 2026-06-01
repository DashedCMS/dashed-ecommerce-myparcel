<?php

namespace Dashed\DashedEcommerceMyParcel\Listeners;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Dashed\DashedEcommerceMyParcel\Classes\MyParcel;
use Dashed\DashedEcommerceCore\Events\Orders\OrderMarkedAsPaidEvent;

class MarkOrderAsPushableListener implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

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
        if (Customsetting::get('my_parcel_automatically_push_orders', $event->order->site_id) && $event->order->street && $event->order->order_origin != 'pos' && (! $event->order->shippingMethod || $event->order->shippingMethod->sort != 'take_away')) {
            MyParcel::connectOrderWithCarrier($event->order);
        }
    }
}
