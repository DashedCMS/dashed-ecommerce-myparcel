<?php

namespace Dashed\DashedEcommerceMyParcel;

use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Illuminate\Console\Scheduling\Schedule;
use Dashed\DashedEcommerceCore\Models\Order;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder;
use Dashed\DashedEcommerceMyParcel\Commands\CheckMyParcelOrders;
use Dashed\DashedEcommerceMyParcel\Livewire\Orders\ShowMyParcelOrders;
use Dashed\DashedEcommerceMyParcel\Commands\CreateMyParcelConceptOrders;
use Dashed\DashedEcommerceMyParcel\Livewire\Orders\ShowPushToMyParcelOrder;
use Dashed\DashedEcommerceMyParcel\Livewire\Orders\ShowCreateMyParcelReturnLabelOrder;
use Dashed\DashedEcommerceMyParcel\Filament\Pages\Settings\MyParcelSettingsPage;

class DashedEcommerceMyParcelServiceProvider extends PackageServiceProvider
{
    public static string $name = 'dashed-ecommerce-myparcel';

    public function bootingPackage()
    {
        Livewire::component('show-push-to-my-parcel-order', ShowPushToMyParcelOrder::class);
        Livewire::component('show-create-my-parcel-return-label-order', ShowCreateMyParcelReturnLabelOrder::class);
        Livewire::component('show-my-parcel-orders', ShowMyParcelOrders::class);

        Order::addDynamicRelation('myParcelOrders', function (Order $model) {
            return $model->hasMany(MyParcelOrder::class);
        });

        $this->app->booted(function () {
            $schedule = app(Schedule::class);
            $schedule->command(CreateMyParcelConceptOrders::class)->everyMinute()->withoutOverlapping();
            $schedule->command(CheckMyParcelOrders::class)->everyFifteenMinutes()->withoutOverlapping();
        });

        cms()->registerSettingsDocs(
            page: \Dashed\DashedEcommerceMyParcel\Filament\Pages\Settings\MyParcelSettingsPage::class,
            title: 'MyParcel instellingen',
            intro: 'Koppel de webshop met MyParcel zodat verzendlabels automatisch aangemaakt kunnen worden. Per site leg je de API key vast en bepaal je per regio welke vervoerder en welk pakkettype standaard gebruikt worden. Heb je meerdere sites? Dan stel je dit per site apart in.',
            sections: [
                [
                    'heading' => 'Wat kun je hier instellen?',
                    'body' => <<<MARKDOWN
Op deze pagina regel je drie dingen:

1. De koppeling met je MyParcel account via een API key.
2. Of nieuwe bestellingen automatisch naar MyParcel worden doorgezet.
3. Per regio (land of regio waar je naar verstuurt) welke vervoerder, welk pakkettype en welk verzendtype standaard gekozen worden.

De regio velden zie je terug per land waar je naartoe verzendt. De keuzes die je daar maakt worden voorgevuld zodra je een label aanmaakt, je kunt ze per bestelling nog aanpassen.
MARKDOWN,
                ],
                [
                    'heading' => 'Hoe zet je dit op?',
                    'body' => <<<MARKDOWN
1. Log in op je MyParcel account.
2. Ga naar je accountinstellingen en open het onderdeel voor de API.
3. Kopieer de API key en plak deze in het veld hieronder.
4. Bepaal of je bestellingen automatisch wilt doorzetten.
5. Loop de regio\'s langs en kies per regio de standaard vervoerder, het pakkettype en het verzendtype.
6. Sla de instellingen op.
MARKDOWN,
                ],
            ],
            fields: [
                'API sleutel' => 'De API key uit je MyParcel account. Zonder deze sleutel kan de webshop geen labels aanmaken of bestellingen doorzetten.',
                'Automatisch bestellingen pushen' => 'Zet je dit aan, dan worden nieuwe bestellingen direct in MyParcel klaargezet zodra ze betaald zijn. Staat dit uit, dan stuur je bestellingen handmatig door vanuit het bestellingenoverzicht.',
                'Standaard vervoerder (per regio)' => 'De vervoerder die standaard wordt voorgesteld voor verzendingen naar deze regio, bijvoorbeeld PostNL of DHL. Dit veld stel je per regio apart in.',
                'Standaard pakkettype (per regio)' => 'Het type pakket dat standaard wordt gekozen, bijvoorbeeld een gewoon pakket of een brievenbuspakje. Per regio in te stellen.',
                'Standaard verzendtype (per regio)' => 'Hoe het pakket bij de klant moet komen: standaard bezorgen, avondbezorging of zelf ophalen bij een afhaalpunt. Per regio in te stellen.',
                'Drempel aantal producten' => 'Vanaf dit aantal producten in een bestelling wordt automatisch een ander pakkettype gekozen. Handig als kleine bestellingen in een brievenbuspakje passen, maar grotere bestellingen niet.',
                'Pakkettype boven drempel' => 'Het pakkettype dat gebruikt wordt zodra de drempel bij het aantal producten is bereikt. Per regio in te stellen.',
            ],
            tips: [
                'Test de koppeling eerst met een proefbestelling. Zo zie je meteen of de API key werkt en of de standaardkeuzes per regio kloppen.',
                'Heb je meerdere landen waar je naartoe verzendt? Loop ze allemaal even langs, anders pakt de webshop voor die regio geen voorkeur en moet je het per bestelling met de hand kiezen.',
                'Zet automatisch doorzetten pas aan als je zeker weet dat de standaardinstellingen per regio kloppen, anders kunnen er labels worden aangemaakt met het verkeerde pakkettype.',
            ],
        );
    }

    public function configurePackage(Package $package): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $package
            ->name('dashed-ecommerce-myparcel')
            ->hasRoutes([
                'MyParcelRoutes',
            ])
            ->hasCommands([
                CheckMyParcelOrders::class,
                CreateMyParcelConceptOrders::class,
            ])
            ->hasViews();

        cms()->registerSettingsPage(MyParcelSettingsPage::class, 'MyParcel', 'archive-box', 'Koppel MyParcel');

        ecommerce()->widgets(
            'orders',
            array_merge(ecommerce()->widgets('orders'), [
                'show-push-to-my-parcel-order' => [
                    'name' => 'show-push-to-my-parcel-order',
                    'width' => 'sidebar',
                ],
                'show-create-my-parcel-return-label-order' => [
                    'name' => 'show-create-my-parcel-return-label-order',
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

        cms()->builder('summaryContributors', array_merge(
            cms()->builder('summaryContributors') ?? [],
            [\Dashed\DashedEcommerceMyParcel\Services\Summary\MyParcelSummaryContributor::class],
        ));
    }
}
