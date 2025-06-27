<?php

namespace Dashed\DashedEcommerceMyParcel\Commands;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceMyParcel\Classes\MyParcel;

class CheckMyParcelOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dashed:check-my-parcel-orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check myparcel orders and update their status';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        foreach (Order::thisSite()->isPaid()->where('fulfillment_status', '!=', 'handled')->get() as $order) {
            $allMyParcelOrdersShipped = true;
            $allMyParcelOrdersDeliverd = true;

            $order->myParcelOrders->each(function ($myParcelOrder) use ($order) {
                $shipment = MyParcel::getShipment($myParcelOrder->shipment_id, $order->site_id);
                $statusCode = $shipment['data']['shipments'][0]['status'] ?? 0;
                if (! in_array($statusCode, [7,8,9,10,11,19])) {
                    $allMyParcelOrdersDeliverd = false;
                } elseif (! in_array($statusCode, [3,4,5,6])) {
                    $allMyParcelOrdersShipped = false;
                }
            });

            if ($allMyParcelOrdersDeliverd) {
                $order->changeFulfillmentStatus('handled');
            } elseif ($allMyParcelOrdersShipped) {
                $order->changeFulfillmentStatus('shipped');
            }
        }
    }
}
