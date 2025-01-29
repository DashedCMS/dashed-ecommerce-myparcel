<?php

namespace Dashed\DashedEcommerceMyParcel\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceMyParcel\Classes\MyParcel;
use Dashed\DashedEcommerceCore\Jobs\ExportSpecificPackingSlipsJob;

class CreateShippingLabelsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 5;
    public $timeout = 1200;

    public User $user;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $response = MyParcel::createShipments();

        Notification::make()
            ->body('Labels zijn aangemaakt (' . count($response['orders']) . ' bestellingen)')
            ->persistent()
            ->actions([
                \Filament\Notifications\Actions\Action::make('download')
                    ->label('Download labels')
                    ->button()
                    ->url(Storage::disk('public')->url($response['filePath']))
                    ->openUrlInNewTab(),
            ])
            ->success()
            ->sendToDatabase($this->user)
            ->send();

        ExportSpecificPackingSlipsJob::dispatch($response['orders'], $this->user);
    }
}
