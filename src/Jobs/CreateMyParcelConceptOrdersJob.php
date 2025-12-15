<?php

namespace Dashed\DashedEcommerceMyParcel\Jobs;

use App\Models\User;
use Filament\Actions\Action;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceMyParcel\Classes\MyParcel;
use Dashed\DashedEcommerceCore\Jobs\ExportSpecificPackingSlipsJob;

class CreateMyParcelConceptOrdersJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 5;
    public $timeout = 1200;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        MyParcel::createConcepts();
    }
}
