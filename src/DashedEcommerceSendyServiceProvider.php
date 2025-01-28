<?php

namespace Dashed\DashedEcommerceMyParcel;

use Livewire\Livewire;
use Filament\Actions\Action;
use Spatie\LaravelPackageTools\Package;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Dashed\DashedEcommerceMyParcel\Livewire\Orders\ShowMyParcelOrders;
use Dashed\DashedEcommerceMyParcel\Livewire\Orders\ShowPushToMyParcelOrder;
use Dashed\DashedEcommerceMyParcel\Filament\Pages\Settings\MyParcelSettingsPage;

class DashedEcommerceMyParcelServiceProvider extends PackageServiceProvider
{
    public static string $name = 'dashed-ecommerce-myparcel';

    public function bootingPackage()
    {
        Livewire::component('show-push-to-myparcel-order', ShowPushToMyParcelOrder::class);
        Livewire::component('show-myparcel-orders', ShowMyParcelOrders::class);

        Order::addDynamicRelation('myparcelOrders', function (Order $model) {
            return $model->hasMany(MyParcelOrder::class);
        });

        if (! app()->runningInConsole()) {
            if (MyParcelOrder::where('label_printed', 0)->count()) {
                ecommerce()->buttonActions(
                    'orders',
                    array_merge(ecommerce()->buttonActions('orders'), [
                        Action::make('downloadMyParcelLabels')
                            ->button()
                            ->label('Download MyParcel Labels')
                            ->url(url(config('filament.path', 'dashed') . '/myparcel/download-labels'))
                            ->openUrlInNewTab(),
                    ])
                );
            }
        }
    }

    public function configurePackage(Package $package): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $package
            ->name('dashed-ecommerce-myparcel')
            ->hasRoutes([
                'MyParcelRoutes',
            ])
            ->hasViews();

        cms()->builder(
            'settingPages',
            array_merge(cms()->builder('settingPages'), [
                'myparcel' => [
                    'name' => 'MyParcel',
                    'description' => 'Koppel MyParcel',
                    'icon' => 'archive-box',
                    'page' => MyParcelSettingsPage::class,
                ],
            ])
        );

        ecommerce()->widgets(
            'orders',
            array_merge(ecommerce()->widgets('orders'), [
                'show-push-to-myparcel-order' => [
                    'name' => 'show-push-to-myparcel-order',
                    'width' => 'sidebar',
                ],
                'show-myparcel-orders' => [
                    'name' => 'show-myparcel-orders',
                    'width' => 'sidebar',
                ],
            ])
        );
    }
}
