<?php

namespace Dashed\DashedEcommerceMyParcel\Livewire\Orders;

use Livewire\Component;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Filament\Forms\Components\Select;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Filament\Actions\Contracts\HasActions;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Actions\Concerns\InteractsWithActions;
use MyParcelNL\Sdk\src\Model\Carrier\CarrierPostNL;
use Dashed\DashedEcommerceMyParcel\Classes\MyParcel;
use Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder;
use Dashed\DashedEcommerceMyParcel\Mail\TrackandTraceMail;

class ShowPushToMyParcelOrder extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
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

                $data['package_type'] = $myParcelOrder->package_type ?? Customsetting::get('my_parcel_default_package_type', null, 1);
                $data['delivery_type'] = $myParcelOrder->delivery_type ?? Customsetting::get('my_parcel_default_delivery_type', null, 2);
                $data['carrier'] = $myParcelOrder->carrier ?? Customsetting::get('my_parcel_default_carrier', null, CarrierPostNL::class);

                return $data;
            })
            ->form(function () {
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
                if (!$myParcelOrder) {
                    $this->order->myParcelOrders()->create([
                        'carrier' => $data['carrier'],
                        'package_type' => $data['package_type'],
                        'delivery_type' => $data['delivery_type'],
                    ]);
                }else{
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
