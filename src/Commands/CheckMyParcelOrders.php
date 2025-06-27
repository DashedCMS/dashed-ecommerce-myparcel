<?php

namespace Dashed\DashedEcommerceMyParcel\Commands;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Models\Order;

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
        foreach (Order::isPaid()->where('fulfillment_status', '!=', 'handled')->get() as $order) {
            $allMyParcelOrdersShipped = false;
            $allMyParcelOrdersDeliverd = false;
            $order->myParcelOrders->each(function ($myParcelOrder) {

            });

            if ($allMyParcelOrdersDeliverd) {
                $this->changeFulfillmentStatus('handled');
            } elseif ($allMyParcelOrdersShipped) {
                $this->changeFulfillmentStatus('shipped');
            }
        }
    }
}
