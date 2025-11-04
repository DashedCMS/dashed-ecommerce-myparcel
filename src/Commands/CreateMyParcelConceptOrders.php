<?php

namespace Dashed\DashedEcommerceMyParcel\Commands;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceMyParcel\Classes\MyParcel;

class CreateMyParcelConceptOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dashed:create-myparcel-concept-orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create MyParcel concept orders for paid orders without fulfillment status handled';

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
       MyParcel::createConcepts();
    }
}
