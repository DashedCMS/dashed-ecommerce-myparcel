<?php

namespace Dashed\DashedEcommerceMyParcel\Filament\Actions;

use Throwable;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Mail;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceMyParcel\Classes\MyParcel;
use Dashed\DashedEcommerceMyParcel\Mail\ReturnLabelMail;
use MyParcelNL\Sdk\src\Model\Carrier\CarrierPostNL;

/**
 * Header-action op de ViewOrder pagina: maakt een retourlabel aan voor één
 * bestelling. Naast carrier en pakket-/verzendtype kan de admin kiezen om
 * de klant direct te mailen met het label als bijlage en optioneel een
 * persoonlijke notitie mee te sturen.
 */
class CreateMyParcelReturnLabelAction
{
    public static function make(): Action
    {
        return Action::make('createMyParcelReturnLabel')
            ->label('Retourlabel aanmaken')
            ->icon('heroicon-o-arrow-uturn-left')
            ->button()
            ->color('warning')
            ->fillForm(function (Order $record) {
                return [
                    'carrier' => Customsetting::get(
                        'my_parcel_default_carrier_' . $record->countryIsoCode,
                        $record->site_id,
                        CarrierPostNL::class
                    ),
                    'package_type' => Customsetting::get(
                        'my_parcel_default_package_type_' . $record->countryIsoCode,
                        $record->site_id,
                        1
                    ),
                    'delivery_type' => Customsetting::get(
                        'my_parcel_default_delivery_type_' . $record->countryIsoCode,
                        $record->site_id,
                        2
                    ),
                    'send_email_to_customer' => true,
                    'personal_note' => null,
                ];
            })
            ->schema([
                Select::make('carrier')
                    ->label('Vervoerder')
                    ->required()
                    ->options(MyParcel::getCarriers()),
                Select::make('package_type')
                    ->label('Pakket type')
                    ->required()
                    ->options(MyParcel::getPackageTypes())
                    ->helperText('Let op: niet alle opties zijn altijd beschikbaar voor alle adressen.'),
                Select::make('delivery_type')
                    ->label('Verzend type')
                    ->required()
                    ->options(MyParcel::getDeliveryTypes())
                    ->helperText('Let op: niet alle opties zijn altijd beschikbaar voor alle adressen.'),
                Toggle::make('send_email_to_customer')
                    ->label('Mail klant met label als bijlage')
                    ->default(true),
                Textarea::make('personal_note')
                    ->label('Persoonlijke notitie aan klant')
                    ->rows(4)
                    ->nullable()
                    ->helperText('Optioneel. Wordt onder de standaardtekst toegevoegd in de mail.'),
            ])
            ->modalSubmitActionLabel('Retourlabel aanmaken')
            ->modalHeading('Retourlabel aanmaken')
            ->modalDescription('Maak een retourlabel aan voor deze bestelling. Het label wordt na bevestigen gedownload, en eventueel direct naar de klant gemaild.')
            ->action(function (array $data, Order $record) {
                if (! MyParcel::isConnected($record->site_id)) {
                    Notification::make()
                        ->title('MyParcel niet geconnect')
                        ->body('Controleer de API sleutel in de MyParcel instellingen.')
                        ->danger()
                        ->send();

                    return null;
                }

                $personalNote = ! empty($data['personal_note']) ? trim((string) $data['personal_note']) : null;

                $myParcelOrder = $record->myParcelOrders()->create([
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
                        ->title('Aanmaken van retourlabel mislukt')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return null;
                }

                if (! empty($data['send_email_to_customer']) && $record->email) {
                    try {
                        Mail::to($record->email)->send(new ReturnLabelMail(
                            $record,
                            $result['filePath'],
                            $personalNote
                        ));

                        $myParcelOrder->is_label_email_sent = true;
                        $myParcelOrder->save();

                        Notification::make()
                            ->title('Retourlabel verstuurd naar klant')
                            ->body('De mail is verzonden naar ' . $record->email . '.')
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Mail naar klant mislukt')
                            ->body('Het label is wel aangemaakt, maar de mail kon niet verstuurd worden: ' . $e->getMessage())
                            ->warning()
                            ->send();
                    }
                } else {
                    Notification::make()
                        ->title('Retourlabel aangemaakt')
                        ->body('Het label staat klaar om te downloaden.')
                        ->success()
                        ->send();
                }

                return redirect()->away(Storage::disk('public')->url($result['filePath']));
            });
    }
}
