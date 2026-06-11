<?php

namespace Dashed\DashedEcommerceMyParcel\Commands;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceMyParcel\Classes\MyParcel;

class SyncMyParcelStatuses extends Command
{
    protected $signature = 'dashed:sync-my-parcel-statuses';

    protected $description = 'Synct de bezorgstatus (onderweg/geleverd) van MyParcel-zendingen naar de labelstatus.';

    public function handle(): int
    {
        $count = MyParcel::syncShipmentStatuses();
        $this->info("MyParcel-labelstatussen bijgewerkt: {$count}.");

        return self::SUCCESS;
    }
}
