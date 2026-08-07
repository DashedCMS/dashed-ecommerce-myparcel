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
        $response = $this->generateLabels();

        if (($response['processed'] ?? 0) > 0) {
            Notification::make()
                ->body(__('Labels zijn aangemaakt (:aantal bestellingen)', ['aantal' => count($response['orders'])]))
                ->persistent()
                ->actions([
                    Action::make('download')
                        ->label(__('Download labels'))
                        ->button()
                        ->url(Storage::disk('public')->url($response['filePath']))
                        ->openUrlInNewTab(),
                ])
                ->success()
                ->sendToDatabase($this->user)
                ->send();

            ExportSpecificPackingSlipsJob::dispatch($response['orders'], $this->user)->onQueue('ecommerce');
        }

        if (self::shouldChainNextBatch($response)) {
            self::dispatch($this->user)->onQueue('ecommerce');
        }
    }

    /**
     * Haal één batch labels op. Geseparateerd zodat tests dit kunnen
     * vervangen zonder de MyParcel SDK te raken.
     */
    protected function generateLabels(): array
    {
        return MyParcel::createShipments();
    }

    /**
     * Keten alleen door naar een volgende batch als er in deze batch
     * daadwerkelijk iets verwerkt is (voorkomt een oneindige lus bij 0
     * matches) én er nog labels resteren.
     */
    public static function shouldChainNextBatch(array $response): bool
    {
        return ($response['processed'] ?? 0) > 0 && ($response['hasMore'] ?? false);
    }
}
