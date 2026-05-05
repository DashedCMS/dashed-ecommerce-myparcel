<?php

namespace Dashed\DashedEcommerceMyParcel\Filament\Actions;

use Throwable;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceMyParcel\Classes\MyParcel;
use Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder;
use MyParcelNL\Sdk\src\Model\Carrier\CarrierPostNL;

/**
 * Header-action op de ViewOrder pagina: maakt een verzendlabel aan voor één
 * bestelling. De action opent een modal met carrier, pakket type en
 * verzend type. Bij bevestigen wordt het concept aangemaakt en het label
 * direct gedownload als PDF.
 */
class CreateMyParcelLabelAction
{
    public static function make(): Action
    {
        return Action::make('createMyParcelLabel')
            ->label('Verzendlabel aanmaken')
            ->icon('heroicon-o-printer')
            ->button()
            ->color('primary')
            ->fillForm(function (Order $record) {
                $myParcelOrder = $record->myParcelOrders()
                    ->where('label_printed', 0)
                    ->where('is_return', false)
                    ->first();

                return [
                    'carrier' => $myParcelOrder->carrier ?? Customsetting::get(
                        'my_parcel_default_carrier_' . $record->countryIsoCode,
                        $record->site_id,
                        CarrierPostNL::class
                    ),
                    'package_type' => $myParcelOrder->package_type ?? Customsetting::get(
                        'my_parcel_default_package_type_' . $record->countryIsoCode,
                        $record->site_id,
                        1
                    ),
                    'delivery_type' => $myParcelOrder->delivery_type ?? Customsetting::get(
                        'my_parcel_default_delivery_type_' . $record->countryIsoCode,
                        $record->site_id,
                        2
                    ),
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
            ])
            ->modalSubmitActionLabel('Label aanmaken en downloaden')
            ->modalHeading('Verzendlabel aanmaken')
            ->modalDescription('Maak direct een MyParcel verzendlabel aan voor deze bestelling. Het label wordt na bevestigen gedownload.')
            ->action(function (array $data, Order $record) {
                if (! MyParcel::isConnected($record->site_id)) {
                    Notification::make()
                        ->title('MyParcel niet geconnect')
                        ->body('Controleer de API sleutel in de MyParcel instellingen.')
                        ->danger()
                        ->send();

                    return null;
                }

                $myParcelOrder = $record->myParcelOrders()
                    ->where('label_printed', 0)
                    ->where('is_return', false)
                    ->first();

                if (! $myParcelOrder) {
                    $myParcelOrder = $record->myParcelOrders()->create([
                        'carrier' => $data['carrier'],
                        'package_type' => $data['package_type'],
                        'delivery_type' => $data['delivery_type'],
                        'is_return' => false,
                    ]);
                } else {
                    $myParcelOrder->update([
                        'carrier' => $data['carrier'],
                        'package_type' => $data['package_type'],
                        'delivery_type' => $data['delivery_type'],
                        'is_return' => false,
                    ]);
                }

                try {
                    $result = MyParcel::createConceptAndLabelForOrder($myParcelOrder);
                } catch (Throwable $e) {
                    $myParcelOrder->error = $e->getMessage();
                    $myParcelOrder->save();

                    Notification::make()
                        ->title('Aanmaken van verzendlabel mislukt')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return null;
                }

                Notification::make()
                    ->title('Verzendlabel aangemaakt')
                    ->body('Het label staat klaar om te downloaden.')
                    ->success()
                    ->send();

                return redirect()->away(Storage::disk('public')->url($result['filePath']));
            });
    }
}
