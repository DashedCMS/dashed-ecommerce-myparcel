<?php

namespace Dashed\DashedEcommerceMyParcel;

use Filament\Panel;
use Filament\Contracts\Plugin;
use Dashed\DashedEcommerceMyParcel\Filament\Pages\Settings\MyParcelSettingsPage;

class DashedEcommerceMyParcelPlugin implements Plugin
{
    public function getId(): string
    {
        return 'dashed-ecommerce-myparcel';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->pages([
                MyParcelSettingsPage::class,
            ]);
    }

    public function boot(Panel $panel): void
    {

    }
}
