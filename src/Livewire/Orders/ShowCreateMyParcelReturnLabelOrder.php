<?php

namespace Dashed\DashedEcommerceMyParcel\Livewire\Orders;

use Throwable;
use Livewire\Component;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Mail;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use MyParcelNL\Sdk\src\Model\Carrier\CarrierPostNL;
use Dashed\DashedEcommerceMyParcel\Classes\MyParcel;
use Dashed\DashedEcommerceMyParcel\Mail\ReturnLabelMail;

/**
 * Sidebar-action op de ViewOrder pagina voor het aanmaken van een retourlabel.
 * Mirrort de Filament header-action die voorheen bovenaan de pagina stond.
 */
class ShowCreateMyParcelReturnLabelOrder extends Component implements HasSchemas, HasActions
{
    use InteractsWithSchemas;
    use InteractsWithActions;

    public Order $order;

    public function mount(Order $order): void
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
            ->label(__('Retourlabel aanmaken'))
            ->color('warning')
            ->icon('heroicon-o-arrow-uturn-left')
            ->fillForm(fn () => [
                'carrier' => Customsetting::get('my_parcel_default_carrier_' . $this->order->countryIsoCode, $this->order->site_id, CarrierPostNL::class),
                'package_type' => Customsetting::get('my_parcel_default_package_type_' . $this->order->countryIsoCode, $this->order->site_id, 1),
                'delivery_type' => Customsetting::get('my_parcel_default_delivery_type_' . $this->order->countryIsoCode, $this->order->site_id, 2),
                'send_email_to_customer' => true,
                'personal_note' => null,
            ])
            ->schema([
                Select::make('carrier')
                    ->label(__('Vervoerder'))
                    ->required()
                    ->options(MyParcel::getCarriers()),
                Select::make('package_type')
                    ->label(__('Pakket type'))
                    ->required()
                    ->options(MyParcel::getPackageTypes())
                    ->helperText(__('Let op: niet alle opties zijn altijd beschikbaar voor alle adressen.')),
                Select::make('delivery_type')
                    ->label(__('Verzend type'))
                    ->required()
                    ->options(MyParcel::getDeliveryTypes())
                    ->helperText(__('Let op: niet alle opties zijn altijd beschikbaar voor alle adressen.')),
                Toggle::make('send_email_to_customer')
                    ->label(__('Mail klant met label als bijlage'))
                    ->default(true),
                Textarea::make('personal_note')
                    ->label(__('Persoonlijke notitie aan klant'))
                    ->rows(4)
                    ->nullable()
                    ->helperText(__('Optioneel. Wordt onder de standaardtekst toegevoegd in de mail.')),
            ])
            ->modalSubmitActionLabel(__('Retourlabel aanmaken'))
            ->modalHeading(__('Retourlabel aanmaken'))
            ->modalDescription(__('Maak een retourlabel aan voor deze bestelling. Het label wordt na bevestigen gedownload, en eventueel direct naar de klant gemaild.'))
            ->action(function (array $data) {
                if (! MyParcel::isConnected($this->order->site_id)) {
                    Notification::make()
                        ->title(__('MyParcel niet geconnect'))
                        ->body(__('Controleer de API sleutel in de MyParcel instellingen.'))
                        ->danger()
                        ->send();

                    return null;
                }

                $personalNote = ! empty($data['personal_note']) ? trim((string) $data['personal_note']) : null;

                $myParcelOrder = $this->order->myParcelOrders()->create([
                    'carrier' => $data['carrier'],
                    'package_type' => $data['package_type'],
                    'delivery_type' => $data['delivery_type'],
                    'is_return' => true,
                    'personal_note' => $personalNote,
                ]);

                try {
                    $result = MyParcel::createReturnLabelForOrder($myParcelOrder);
                } catch (Throwable $e) {
                    $myParcelOrder->error = $e->getMessage();
                    $myParcelOrder->save();

                    Notification::make()
                        ->title(__('Aanmaken van retourlabel mislukt'))
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return null;
                }

                $labelUrl = Storage::disk('public')->url($result['filePath']);

                if (! empty($data['send_email_to_customer']) && $this->order->email) {
                    try {
                        Mail::to($this->order->email)->send(new ReturnLabelMail(
                            $this->order,
                            $result['filePath'],
                            $personalNote
                        ));

                        $myParcelOrder->is_label_email_sent = true;
                        $myParcelOrder->save();

                        Notification::make()
                            ->title(__('Retourlabel verstuurd naar klant'))
                            ->body(__('De mail is verzonden naar :email.', ['email' => $this->order->email]))
                            ->success()
                            ->send();

                        return null;
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title(__('Mail naar klant mislukt'))
                            ->body(__('Het label is wel aangemaakt, maar de mail kon niet verstuurd worden: :fout', ['fout' => $e->getMessage()]))
                            ->warning()
                            ->send();
                    }
                }

                $this->js('window.open(' . json_encode($labelUrl) . ", '_blank');");

                Notification::make()
                    ->title(__('Retourlabel aangemaakt'))
                    ->body(__('Het label is geopend in een nieuw tabblad.'))
                    ->success()
                    ->send();

                return null;
            });
    }
}
