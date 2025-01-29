<?php

namespace Dashed\DashedEcommerceMyParcel;

use Livewire\Livewire;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPackageTools\Package;
use Filament\Notifications\Notification;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceMyParcel\Classes\MyParcel;
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
                            ->label('Download MyParcel Labels (' . MyParcelOrder::where('label_printed', 0)->count() . ')')
                            ->openUrlInNewTab()
                            ->action(function () {
                                $response = MyParcel::createShipments();

                                $pdf = $response->getLabelPdf();

                                Storage::disk('public')->put('dashed/orders/my-parcel/labels.pdf', $pdf);

                                Notification::make()
                                    ->body('Labels zijn aangemaakt')
                                    ->persistent()
                                    ->actions([
                                        \Filament\Notifications\Actions\Action::make('download')
                                            ->label('Download')
                                            ->button()
                                            ->url(Storage::disk('public')->url('dashed/orders/my-parcel/labels.pdf'))
                                            ->openUrlInNewTab(),
                                    ])
                                    ->success()
                                    ->send();
                            }),
//                            ->url(url(config('filament.path', 'dashed') . '/myparcel/download-labels'))
//                            ->openUrlInNewTab(),
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

        cms()->builder('plugins', [
            new DashedEcommerceMyParcelPlugin(),
        ]);
    }
}
