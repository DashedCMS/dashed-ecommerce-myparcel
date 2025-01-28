<?php

namespace Dashed\DashedEcommerceMyParcel\Filament\Pages\Settings;

use Filament\Pages\Page;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Tabs;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceMyParcel\Classes\MyParcel;
use Dashed\DashedEcommerceMyParcel\Models\MyParcelShippingMethod;

class MyParcelSettingsPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $title = 'MyParcel';

    protected static string $view = 'dashed-core::settings.pages.default-settings';
    public array $data = [];

    public function mount(): void
    {
        $formData = [];
        $sites = Sites::getSites();
        foreach ($sites as $site) {
            $formData["myparcel_api_key_{$site['id']}"] = Customsetting::get('myparcel_api_key', $site['id']);
            $formData["myparcel_connected_{$site['id']}"] = Customsetting::get('myparcel_connected', $site['id'], 0) ? true : false;

            foreach (MyParcelShippingMethod::get() as $shippingMethod) {
                $formData["shipping_method_{$shippingMethod->id}_enabled"] = $shippingMethod->enabled;
                foreach ($shippingMethod->myparcelShippingMethodServices as $service) {
                    $formData["shipping_method_service_{$service->id}_enabled"] = $service->enabled;
                    foreach ($service->myparcelShippingMethodServiceOptions as $option) {
                        $formData["shipping_method_service_option_{$option->id}_default"] = $option->default;
                    }
                }
            }
        }
        $this->form->fill($formData);
    }

    protected function getFormSchema(): array
    {
        $sites = Sites::getSites();
        $tabGroups = [];

        $tabs = [];
        foreach ($sites as $site) {
            $schema = [
                Placeholder::make('label')
                    ->label("MyParcel voor {$site['name']}")
                    ->content('Activeer MyParcel.')
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),
                Placeholder::make('label')
                    ->label("MyParcel is " . (! Customsetting::get('myparcel_connected', $site['id'], 0) ? 'niet' : '') . ' geconnect')
                    ->content(Customsetting::get('myparcel_connection_error', $site['id'], ''))
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),
                TextInput::make("myparcel_api_key_{$site['id']}")
                    ->label('MyParcel API key')
                    ->maxLength(255)
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),
            ];

            foreach (MyParcelShippingMethod::get() as $shippingMethod) {
                $schema[] = Toggle::make("shipping_method_{$shippingMethod->id}_enabled")
                    ->label("Verzendmethod {$shippingMethod->name} activeren")
                    ->reactive();
            }

            foreach (MyParcelShippingMethod::get() as $shippingMethod) {
                foreach ($shippingMethod->myparcelShippingMethodServices as $service) {
                    $serviceSchema = [];

                    $serviceSchema[] = Toggle::make("shipping_method_service_{$service->id}_enabled")
                        ->reactive();

                    $optionsSchema = [];
                    foreach ($service->myparcelShippingMethodServiceOptions as $option) {
                        if ($option->type == 'textbox') {
                            $optionsSchema[] = TextInput::make("shipping_method_service_option_{$option->id}_default")
                                ->label($option->name)
                                ->maxLength(255);
                        } elseif ($option->type == 'checkbox') {
                            $optionsSchema[] = Toggle::make("shipping_method_service_option_{$option->id}_default")
                                ->label($option->name);
                        } elseif ($option->type == 'email') {
                            $optionsSchema[] = TextInput::make("shipping_method_service_option_{$option->id}_default")
                                ->type('email')
                                ->label($option->name)
                                ->email()
                                ->maxLength(255);
                        } elseif ($option->type == 'date') {
                            $optionsSchema[] = DatePicker::make("shipping_method_service_option_{$option->id}_default")
                                ->label($option->name);
                        } elseif ($option->type == 'selectbox') {
                            $choices = [];
                            foreach ($option->choices as $choice) {
                                $choices[$choice['value']] = $choice['text'];
                            }
                            $optionsSchema[] = Select::make("shipping_method_service_option_{$option->id}_default")
                                ->label($option->name)
                                ->options($choices);
                        } else {
                            dump('Contacteer je beheerder om dit in te bouwen');
                        }
                    }

                    $serviceSchema[] = Card::make()
                        ->schema($optionsSchema)
                        ->hidden(fn ($get) => ! $get("shipping_method_service_{$service->id}_enabled"));

                    $schema[] = Section::make($service->name)
                        ->label($service->name)
                        ->schema($serviceSchema)
                        ->hidden(fn ($get) => ! $get("shipping_method_{$shippingMethod->id}_enabled"));
                }
            }

            $tabs[] = Tab::make($site['id'])
                ->label(ucfirst($site['name']))
                ->schema($schema)
                ->columns([
                    'default' => 1,
                    'lg' => 2,
                ]);
        }
        $tabGroups[] = Tabs::make('Sites')
            ->tabs($tabs);

        return $tabGroups;
    }

    public function getFormStatePath(): ?string
    {
        return 'data';
    }

    public function submit()
    {
        $sites = Sites::getSites();

        foreach ($sites as $site) {
            Customsetting::set('myparcel_api_key', $this->form->getState()["myparcel_api_key_{$site['id']}"], $site['id']);
            Customsetting::set('myparcel_connected', MyParcel::isConnected($site['id']), $site['id']);

            foreach (MyParcelShippingMethod::get() as $shippingMethod) {
                if (isset($this->form->getState()["shipping_method_{$shippingMethod->id}_enabled"])) {
                    $shippingMethod->enabled = $this->form->getState()["shipping_method_{$shippingMethod->id}_enabled"];
                    $shippingMethod->save();
                }

                foreach ($shippingMethod->myparcelShippingMethodServices as $service) {
                    if (isset($this->form->getState()["shipping_method_service_{$service->id}_enabled"])) {
                        $service->enabled = $this->form->getState()["shipping_method_service_{$service->id}_enabled"];
                        $service->save();
                    }

                    foreach ($service->myparcelShippingMethodServiceOptions as $option) {
                        if (isset($this->form->getState()["shipping_method_service_option_{$option->id}_default"])) {
                            $option->default = $this->form->getState()["shipping_method_service_option_{$option->id}_default"];
                            $option->save();
                        }
                    }
                }
            }

            if (Customsetting::get('myparcel_connected', $site['id'], 0)) {
                MyParcel::syncShippingMethods($site['id']);
            }
        }

        Notification::make()
            ->title('De MyParcel instellingen zijn opgeslagen')
            ->success()
            ->send();

        return redirect(MyParcelSettingsPage::getUrl());
    }
}
