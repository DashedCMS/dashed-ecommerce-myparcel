<?php

namespace Dashed\DashedEcommerceMyParcel\Livewire\Orders;

use Livewire\Component;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use MyParcelNL\Sdk\src\Model\Carrier\CarrierPostNL;
use Dashed\DashedEcommerceMyParcel\Classes\MyParcel;

class ShowPushToMyParcelOrder extends Component implements HasSchemas, HasActions
{
    use InteractsWithSchemas;
    use InteractsWithActions;

    public Order $order;

    public function mount(Order $order)
    {
        $this->order = $order;
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.plain-action');
    }

    public function action(): Action
    {
        return Action::make('action')
            ->label('Verstuur naar MyParcel')
            ->color('primary')
            ->fillForm(function () {
                $data = [];

                $myParcelOrder = $this->order->myParcelOrders()->where('label_printed', 0)->first();

                $data['package_type'] = $myParcelOrder->package_type ?? Customsetting::get("my_parcel_default_package_type_{$this->order->countryIsoCode}", null, 1);
                $data['delivery_type'] = $myParcelOrder->delivery_type ?? Customsetting::get("my_parcel_default_delivery_type_{$this->order->countryIsoCode}", null, 2);
                $data['carrier'] = $myParcelOrder->carrier ?? Customsetting::get("my_parcel_default_carrier_{$this->order->countryIsoCode}", null, CarrierPostNL::class);

                return $data;
            })
            ->schema(function () {
                return [
                    Select::make("carrier")
                        ->label('Carrier')
                        ->required()
                        ->options(MyParcel::getCarriers()),
                    Select::make("package_type")
                        ->label('Pakket type')
                        ->required()
                        ->options(MyParcel::getPackageTypes())
                        ->helperText('Let op: niet alle opties zijn altijd beschikbaar voor alle adressen'),
                    Select::make("delivery_type")
                        ->label('Verzend type')
                        ->required()
                        ->options(MyParcel::getDeliveryTypes())
                        ->helperText('Let op: niet alle opties zijn altijd beschikbaar voor alle adressen'),
                ];
            })
            ->action(function ($data) {
                $this->validate();

                $myParcelOrder = $this->order->myParcelOrders()->where('label_printed', 0)->first();
                if (! $myParcelOrder) {
                    $this->order->myParcelOrders()->create([
                        'carrier' => $data['carrier'],
                        'package_type' => $data['package_type'],
                        'delivery_type' => $data['delivery_type'],
                    ]);
                } else {
                    $myParcelOrder->update([
                        'carrier' => $data['carrier'],
                        'package_type' => $data['package_type'],
                        'delivery_type' => $data['delivery_type'],
                    ]);
                }

                Notification::make()
                    ->title('De bestelling is klaargezet voor MyParcel.')
                    ->success()
                    ->send();
            });
    }
}
