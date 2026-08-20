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
            // Retourlabels tellen niet mee: die zeggen niets over of de
            // bestelling de deur uit is, en een retourlabel dat nooit gebruikt
            // wordt blijft eeuwig op concept staan. Retouren hebben hun eigen
            // retour_status. Zelfde afbakening als MyParcel::labelsToPrint().
            $shipments = $order->myParcelOrders()
                ->whereNotNull('shipment_id')
                ->where('is_return', false)
                ->get();

            // Beide beginnen op waar en worden onwaar zodra een zending
            // achterblijft. Ze stonden op onwaar en werden waar zodra er een
            // zending meezat, terwijl ze "all" heten: een bestelling met twee
            // pakketten waarvan er een bezorgd was ging daardoor naar
            // afgehandeld terwijl het tweede nog op de plank lag.
            $allShipped = true;
            $allDelivered = true;

            foreach ($shipments as $myParcelOrder) {
                $shipment = MyParcel::getShipment($myParcelOrder->shipment_id, $order->site_id);
                $statusCode = $shipment['data']['shipments'][0]['status'] ?? 0;

                if (in_array($statusCode, [7, 8, 9, 10, 11, 19])) {
                    continue;
                }

                $allDelivered = false;

                if (! in_array($statusCode, [3, 4, 5, 6])) {
                    // Nog niet onderweg (concept, aangemeld) of een status die
                    // hier niet bekend is. In beide gevallen is dit pakket geen
                    // bewijs dat de bestelling verzonden is.
                    $allShipped = false;
                }
            }

            // Zonder zendingen valt er niets af te leiden: een bestelling die
            // nooit een label kreeg zou anders met lege handen als afgehandeld
            // gelden, want een lus over niets laat beide vlaggen op waar staan.
            if ($shipments->isEmpty()) {
                continue;
            }

            if ($allDelivered) {
                $order->changeFulfillmentStatus('handled');
            } elseif ($allShipped) {
                $order->changeFulfillmentStatus('shipped');
            }
        }
    }
}
