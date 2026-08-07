<?php

namespace Dashed\DashedEcommerceMyParcel;

use Filament\Panel;
use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Dashed\DashedEcommerceMyParcel\Classes\MyParcel;
use Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder;
use Dashed\DashedEcommerceMyParcel\Jobs\CreateShippingLabelsJob;
use Dashed\DashedEcommerceMyParcel\Support\MyParcelShippingProvider;
use Dashed\DashedEcommerceMyParcel\Filament\Pages\Settings\MyParcelSettingsPage;

class DashedEcommerceMyParcelPlugin implements Plugin
{
    public function getId(): string
    {
        return 'dashed-ecommerce-myparcel';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->pages([
                MyParcelSettingsPage::class,
            ]);
    }

    public static function builderBlocks(): void
    {
        cms()
            ->builder('productGroupBlocks', [
                Select::make('my-parcel-package-type')
                    ->label(__('MyParcel pakket type'))
                    ->options(MyParcel::getPackageTypes()),
            ]);
    }

    public function boot(Panel $panel): void
    {
        cms()->builder('builderBlockClasses', [
            self::class => 'builderBlocks',
        ]);

        ecommerce()->registerShippingLabelProvider(new MyParcelShippingProvider());

        if (MyParcelOrder::where('label_printed', 0)->whereNotNull('shipment_id')->count()) {
            ecommerce()->buttonActions(
                'orders',
                array_merge(ecommerce()->buttonActions('orders'), [
                    Action::make('downloadMyParcelLabels')
                        ->button()
                        ->label(__('Download MyParcel Labels (:aantal)', ['aantal' => MyParcelOrder::where('label_printed', 0)->whereNotNull('shipment_id')->count()]))
                        ->openUrlInNewTab()
                        ->action(function () {
                            CreateShippingLabelsJob::dispatch(auth()->user())->onQueue('ecommerce');

                            Notification::make()
                                ->body(__('Labels worden aangemaakt, ze staan over een paar minuten klaar om te downloaden'))
                                ->success()
                                ->send();
                        }),
                ])
            );
        }

        ecommerce()->registerShippingStatusCommand('dashed:check-my-parcel-orders');
    }
}
