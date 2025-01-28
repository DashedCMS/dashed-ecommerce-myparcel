<?php

namespace Dashed\DashedEcommerceMyParcel;

use Livewire\Livewire;
use Filament\Actions\Action;
use Spatie\LaravelPackageTools\Package;
use Dashed\DashedEcommerceCore\Models\Order;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder;
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

        if (cms()->isCMSRoute()) {
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
    }
}
