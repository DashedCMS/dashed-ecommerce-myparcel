<?php

namespace Dashed\DashedEcommerceMyParcel;

use Filament\Panel;
use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Dashed\DashedEcommerceMyParcel\Classes\MyParcel;
use Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder;
use Dashed\DashedEcommerceMyParcel\Jobs\CreateShippingLabelsJob;
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
                    ->label('MyParcel pakket type')
                    ->options(MyParcel::getPackageTypes()),
            ]);
    }

    public function boot(Panel $panel): void
    {
        cms()->builder('builderBlockClasses', [
            self::class => 'builderBlocks',
        ]);

        if (MyParcelOrder::where('label_printed', 0)->whereNotNull('shipment_id')->count()) {
            ecommerce()->buttonActions(
                'orders',
                array_merge(ecommerce()->buttonActions('orders'), [
                    Action::make('downloadMyParcelLabels')
                        ->button()
                        ->label('Download MyParcel Labels (' . MyParcelOrder::where('label_printed', 0)->whereNotNull('shipment_id')->count() . ')')
                        ->openUrlInNewTab()
                        ->action(function () {
                            CreateShippingLabelsJob::dispatch(auth()->user())->onQueue('ecommerce');

                            Notification::make()
                                ->body('Labels worden aangemaakt, ze staan over een paar minuten klaar om te downloaden')
                                ->success()
                                ->send();
                        }),
                ])
            );
        }

        // Handmatig de periodieke MyParcel-status-sync triggeren voor alle
        // niet-afgehandelde bestellingen. De command draait normaal elk
        // kwartier via de scheduler; deze knop dispatcht hem direct naar
        // de queue zodat de admin niet hoeft te wachten op de volgende run.
        ecommerce()->buttonActions(
            'orders',
            array_merge(ecommerce()->buttonActions('orders'), [
                Action::make('syncMyParcelStatuses')
                    ->iconButton()
                    ->color('gray')
                    ->icon('heroicon-o-arrow-path')
                    ->label('Verzendstatussen ophalen bij MyParcel')
                    ->tooltip('Verzendstatussen ophalen bij MyParcel')
                    ->requiresConfirmation()
                    ->modalHeading('Verzendstatussen synchroniseren')
                    ->modalDescription('Hiermee wordt voor elke niet-afgehandelde bestelling de huidige status bij MyParcel opgehaald en bijgewerkt. De sync draait in de achtergrond.')
                    ->modalSubmitActionLabel('Sync starten')
                    ->action(function () {
                        Artisan::queue('dashed:check-my-parcel-orders')->onQueue('ecommerce');

                        Notification::make()
                            ->title('Sync gestart')
                            ->body('De verzendstatussen worden in de achtergrond opgehaald bij MyParcel.')
                            ->success()
                            ->send();
                    }),
            ])
        );

    }
}
