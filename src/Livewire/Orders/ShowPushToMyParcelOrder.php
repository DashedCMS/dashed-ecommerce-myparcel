<?php

namespace Dashed\DashedEcommerceMyParcel\Livewire\Orders;

use Throwable;
use Livewire\Component;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
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
            ->label(__('Verzendlabel aanmaken'))
            ->color('primary')
            ->icon('heroicon-o-document-arrow-down')
            ->fillForm(function () {
                $data = [];

                $myParcelOrder = $this->order->myParcelOrders()->where('label_printed', 0)->first();

                $data['package_type'] = $myParcelOrder->package_type ?? Customsetting::get("my_parcel_default_package_type_{$this->order->countryIsoCode}", null, 1);
                $data['delivery_type'] = $myParcelOrder->delivery_type ?? Customsetting::get("my_parcel_default_delivery_type_{$this->order->countryIsoCode}", null, 2);
                $data['carrier'] = $myParcelOrder->carrier ?? Customsetting::get("my_parcel_default_carrier_{$this->order->countryIsoCode}", null, CarrierPostNL::class);

                $existingOptions = $myParcelOrder->options ?? [];
                foreach (MyParcel::extraLabelOptions($this->order) as $field) {
                    if (array_key_exists($field['name'], $existingOptions)) {
                        $data[$field['name']] = $field['type'] === 'amount'
                            ? ((int) $existingOptions[$field['name']]) / 100
                            : (bool) $existingOptions[$field['name']];
                    } else {
                        $data[$field['name']] = $field['default'];
                    }
                }

                return $data;
            })
            ->schema(function () {
                $fields = [
                    Select::make("carrier")
                        ->label(__('Carrier'))
                        ->required()
                        ->options(MyParcel::getCarriers()),
                    Select::make("package_type")
                        ->label(__('Pakket type'))
                        ->required()
                        ->options(MyParcel::getPackageTypes())
                        ->helperText(__('Let op: niet alle opties zijn altijd beschikbaar voor alle adressen')),
                    Select::make("delivery_type")
                        ->label(__('Verzend type'))
                        ->required()
                        ->options(MyParcel::getDeliveryTypes())
                        ->helperText(__('Let op: niet alle opties zijn altijd beschikbaar voor alle adressen')),
                ];

                foreach (MyParcel::extraLabelOptions($this->order) as $extraOption) {
                    $fields[] = match ($extraOption['type']) {
                        'amount' => TextInput::make($extraOption['name'])
                            ->label($extraOption['label'])
                            ->numeric()
                            ->prefix('€'),
                        default => Toggle::make($extraOption['name'])
                            ->label($extraOption['label']),
                    };
                }

                return $fields;
            })
            ->action(function ($data) {
                $this->validate();

                $options = MyParcel::sanitizeExtraOptions($data);

                $myParcelOrder = $this->order->myParcelOrders()
                    ->where('label_printed', 0)
                    ->where('is_return', false)
                    ->first();

                if (! $myParcelOrder) {
                    $myParcelOrder = $this->order->myParcelOrders()->create([
                        'carrier' => $data['carrier'],
                        'package_type' => $data['package_type'],
                        'delivery_type' => $data['delivery_type'],
                        'is_return' => false,
                        'options' => $options,
                    ]);
                } else {
                    $myParcelOrder->update([
                        'carrier' => $data['carrier'],
                        'package_type' => $data['package_type'],
                        'delivery_type' => $data['delivery_type'],
                        'is_return' => false,
                        'options' => $options,
                    ]);
                }

                try {
                    $result = MyParcel::createConceptAndLabelForOrder($myParcelOrder);
                } catch (Throwable $e) {
                    $myParcelOrder->error = $e->getMessage();
                    $myParcelOrder->save();

                    Notification::make()
                        ->title(__('Aanmaken van verzendlabel mislukt'))
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return null;
                }

                Notification::make()
                    ->title(__('Verzendlabel aangemaakt'))
                    ->body(__('Het label staat klaar in de lijst hieronder en kan via de download-knop opgehaald worden.'))
                    ->success()
                    ->send();

                // Refresh de pagina zodat het zojuist aangemaakte label
                // direct in de lijst eronder verschijnt met de download-knop.
                $this->dispatch('$refresh');

                return null;
            });
    }
}
