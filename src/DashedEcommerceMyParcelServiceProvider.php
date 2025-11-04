<?php

namespace Dashed\DashedEcommerceMyParcel;

use Dashed\DashedEcommerceMyParcel\Commands\CreateMyParcelConceptOrders;
use Dashed\DashedEcommerceMyParcel\Commands\CreateMyParcelShipments;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Illuminate\Console\Scheduling\Schedule;
use Dashed\DashedEcommerceCore\Models\Order;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder;
use Dashed\DashedEcommerceMyParcel\Commands\CheckMyParcelOrders;
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
            $schedule->command(CreateMyParcelConceptOrders::class)->everyMinute()->withoutOverlapping();
            $schedule->command(CheckMyParcelOrders::class)->everyFifteenMinutes()->withoutOverlapping();
        });
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
                CreateMyParcelConceptOrders::class,
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
