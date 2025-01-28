<?php

namespace Dashed\DashedEcommerceMyParcel\Livewire\Orders;

use Dashed\DashedCore\Models\Customsetting;
use Filament\Forms\Get;
use Livewire\Component;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Actions\Contracts\HasActions;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Actions\Concerns\InteractsWithActions;
use Dashed\DashedEcommerceMyParcel\Classes\MyParcel;
use Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder;
use Dashed\DashedEcommerceMyParcel\Mail\TrackandTraceMail;
use Dashed\DashedEcommerceMyParcel\Models\MyParcelShippingMethod;
use MyParcelNL\Sdk\src\Model\Carrier\CarrierPostNL;

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

                $data['package_type'] = Customsetting::get('my_parcel_default_package_type', null, 1);
                $data['delivery_type'] = Customsetting::get('my_parcel_default_delivery_type', null, 2);
                $data['carrier'] = Customsetting::get('my_parcel_default_carrier', null, CarrierPostNL::class);

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

                $response = MyParcel::createShipment($this->order, $data);
                dd($response);
                if (isset($response['shipment_id'])) {
                    $myparcelOrder = new MyParcelOrder();
                    $myparcelOrder->order_id = $this->order->id;
                    $myparcelOrder->shipment_id = $response['shipment_id'];
                    $myparcelOrder->label = $response['label'];
                    $time = uniqid();
                    Storage::disk('public')->put('/dashed/orders/myparcel/labels/label-' . $this->order->invoice_id . '-' . $time . '.pdf', base64_decode($response['label']));
                    $myparcelOrder->label_url = '/myparcel/labels/label-' . $this->order->invoice_id . '-' . $time . '.pdf';
                    $myparcelOrder->track_and_trace = $response['track_and_trace'];
                    $myparcelOrder->save();

                    foreach ($response['track_and_trace'] as $code => $link) {
                        $this->order->addTrackAndTrace('myparcel', $data['service'], $code, $link);
                    }

                    $orderLog = new OrderLog();
                    $orderLog->order_id = $this->order->id;
                    $orderLog->user_id = Auth::user()->id;
                    $orderLog->tag = 'order.pushed-to-myparcel';
                    $orderLog->save();

                    //                    try {
                    //                        Mail::to($this->order->email)->send(new TrackandTraceMail($myparcelOrder));
                    //
                    //                        $orderLog = new OrderLog();
                    //                        $orderLog->order_id = $this->order->id;
                    //                        $orderLog->user_id = Auth::user()->id;
                    //                        $orderLog->tag = 'order.t&t.send';
                    //                        $orderLog->save();
                    //                    } catch (\Exception $e) {
                    //                        $orderLog = new OrderLog();
                    //                        $orderLog->order_id = $this->order->id;
                    //                        $orderLog->user_id = Auth::user()->id;
                    //                        $orderLog->tag = 'order.t&t.not-send';
                    //                        $orderLog->save();
                    //                    }


                    $this->dispatch('refreshPage');
                    Notification::make()
                        ->title('De bestelling is naar MyParcel gepushed.')
                        ->success()
                        ->send();
                } else {
                    foreach ($response as $error) {
                        if (is_array($error)) {
                            foreach ($error as $errorItem) {
                                Notification::make()
                                    ->title($errorItem)
                                    ->danger()
                                    ->send();
                            }
                        } else {
                            Notification::make()
                                ->title($error)
                                ->danger()
                                ->send();
                        }
                    }
                }
            });
    }
}
