<?php

namespace Dashed\DashedEcommerceMyParcel;

use Livewire\Livewire;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Spatie\LaravelPackageTools\Package;
use Filament\Notifications\Notification;
use Illuminate\Console\Scheduling\Schedule;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceMyParcel\Classes\MyParcel;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder;
use Dashed\DashedEcommerceMyParcel\Commands\CheckMyParcelOrders;
use Dashed\DashedEcommerceMyParcel\Jobs\CreateShippingLabelsJob;
use Dashed\DashedEcommerceMyParcel\Livewire\Orders\ShowMyParcelOrders;
use Dashed\DashedEcommerceMyParcel\Livewire\Orders\ShowPushToMyParcelOrder;
use Dashed\DashedEcommerceMyParcel\Filament\Pages\Settings\MyParcelSettingsPage;

class DashedEcommerceMyParcelServiceProvider extends PackageServiceProvider
{
    public static string $name = 'dashed-ecommerce-myparcel';

    public function bootingPackage()
    {
        Livewire::component('show-push-to-my-parcel-order', ShowPushToMyParcelOrder::class);
        Livewire::component('show-my-parcel-orders', ShowMyParcelOrders::class);

        Order::addDynamicRelation('myParcelOrders', function (Order $model) {
            return $model->hasMany(MyParcelOrder::class);
        });

        $this->app->booted(function () {
            $schedule = app(Schedule::class);
            $schedule->command(CheckMyParcelOrders::class)->everyFifteenMinutes();
        });

        if (cms()->isCMSRoute()) {
            if (MyParcelOrder::where('label_printed', 0)->count()) {
                ecommerce()->buttonActions(
                    'orders',
                    array_merge(ecommerce()->buttonActions('orders'), [
                        Action::make('downloadMyParcelLabels')
                            ->button()
                            ->label('Download MyParcel Labels (' . MyParcelOrder::where('label_printed', 0)->count() . ')')
                            ->openUrlInNewTab()
                            ->action(function () {
                                CreateShippingLabelsJob::dispatch(auth()->user());

                                Notification::make()
                                    ->body('Labels worden aangemaakt, ze staan over een paar minuten klaar om te downloaden')
                                    ->success()
                                    ->send();
                            }),
                    ])
                );
            }
        }

        cms()->builder('builderBlockClasses', [
            self::class => 'builderBlocks',
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

    public function configurePackage(Package $package): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $package
            ->name('dashed-ecommerce-myparcel')
            ->hasRoutes([
                'MyParcelRoutes',
            ])
            ->hasCommands([
                CheckMyParcelOrders::class,
            ])
            ->hasViews();

        cms()->registerSettingsPage(MyParcelSettingsPage::class, 'MyParcel', 'archive-box', 'Koppel MyParcel');

        ecommerce()->widgets(
            'orders',
            array_merge(ecommerce()->widgets('orders'), [
                'show-push-to-my-parcel-order' => [
                    'name' => 'show-push-to-my-parcel-order',
                    'width' => 'sidebar',
                ],
                'show-my-parcel-orders' => [
                    'name' => 'show-my-parcel-orders',
                    'width' => 'sidebar',
                ],
            ])
        );

        cms()->builder('plugins', [
            new DashedEcommerceMyParcelPlugin(),
        ]);
    }
}
