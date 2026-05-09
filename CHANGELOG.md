# Changelog

All notable changes to `dashed-ecommerce-myparcel` will be documented in this file.

## v4.3.4 - 2026-05-09

### Fixed
- `MyParcel::createConcepts()` zette een MyParcelOrder zonder carrier elke cron-run (per minuut) opnieuw klaar: de bestaande MyParcelOrder werd gedelete en `connectOrderWithCarrier()` maakte 'm direct opnieuw aan, inclusief OrderLog-regel "Bestelling klaargezet voor MyParcel". Bij een land zonder ingestelde `my_parcel_default_carrier_<land>` (bv. FR voor Etsy-orders) bleef de nieuwe MyParcelOrder ook zonder carrier en herhaalde dit zich elke minuut, met als gevolg honderden duplicate OrderLogs op één order. Nu wordt alleen delete+recreate gedaan als er daadwerkelijk een default carrier voor het land is ingesteld; anders krijgt de MyParcelOrder een duidelijke error en stopt de cron 'm op te pakken (admin-actie "Opnieuw in wachtrij zetten" reset 'm na configuratie).

## v4.3.3 - 2026-05-08

### Fixed
- `MyParcel::createConcepts()` slaat nu MyParcelOrders met een al-gezette `error` over. Voorheen probeerde de cron-job elke minuut dezelfde order opnieuw bij MyParcel aan te bieden, kreeg dezelfde fout terug en stuurde elke keer een notificatie-mail naar de admins. Eén mail per falende order is genoeg; admin's "Opnieuw in wachtrij zetten"-actie clearet de error en triggert een nieuwe poging.

## v4.3.1 - 2026-05-08

### Fixed
- `MyParcel::createConceptAndLabelForOrder()` en `createReturnLabelForOrder()` zetten `label_printed` niet meer automatisch op `1` bij het aanmaken van het label. Voorheen kreeg de admin direct de "Label gedownload" badge te zien terwijl het PDF nog niet daadwerkelijk was opgehaald, waardoor het label ook niet meer in de bulk-download teller stond.
- Per-order download-knop in `show-my-parcel-orders.blade.php` werkt nu via `wire:click="downloadLabel(...)"` op `ShowMyParcelOrders` Livewire-component. De action checkt of het PDF bestaat, markeert `label_printed = 1`, en streamt het PDF terug. Voorheen was de knop een directe `<a href>` naar de public storage URL waardoor de "gedownload"-status niet werd bijgewerkt.

## v4.3.0 - 2026-05-07

### Added
- **Bijdrage aan de admin samenvatting-mails (framework uit dashed-core v4.5.0).** Nieuwe `MyParcelSummaryContributor` onder `Services\Summary\`, automatisch geregistreerd via `cms()->builder('summaryContributors', ...)` in de boot van de package. De contributor levert een sectie aan voor de periodieke samenvatting-mail (dagelijks / wekelijks / maandelijks) met drie stats: aantal aangemaakte verzendlabels, aantal aangemaakte retourlabels en aantal retourlabels die naar de klant zijn gemaild. Filtert op `updated_at` zodat alleen labels meetellen die in de periode daadwerkelijk zijn afgedrukt of gemaild. De sectie wordt overgeslagen als alle drie de stats nul zijn, zodat admins geen muur van nullen ontvangen. Standaard frequentie staat op `weekly`.

## v4.2.1 - 2026-05-06

### Fixed
- **Retourlabel-knop deed niks.** De action in `ShowCreateMyParcelReturnLabelOrder` was geregistreerd als `Action::make('createMyParcelReturnLabel')` terwijl de Livewire-method `action()` heet. Filament's HasActions resolved actie-naam tegen method-naam; de mismatch zorgde dat de mount-flow stilzwijgend faalde. Action-naam hernoemd naar `'action'` zodat hij matcht (zelfde patroon als ShowPushToMyParcelOrder).
- **Verzendlabel aanmaken redirectte naar de PDF**, terwijl de admin gewoon op de detail-pagina wil blijven zodat het label in de lijst eronder verschijnt en via de download-knop opgehaald kan worden. Vervangen door een `$this->dispatch('$refresh')` zodat de pagina ververst en de download-knop in de labels-lijst direct beschikbaar is.

## v4.2.0 - 2026-05-06

### Changed
- **Verzend- en retourlabel-knoppen verplaatst naar de sidebar van de bestel-detailpagina** in plaats van de header-actions bovenaan.
  - De sidebar-knop "Verstuur naar MyParcel" is hernoemd naar **"Verzendlabel aanmaken"** en gebruikt nu de synchrone `MyParcel::createConceptAndLabelForOrder()`-flow (was: dispatchen van een queued bulk-job). Voordeel: het label staat na bevestiging direct klaar om te downloaden, en `MyParcelOrder::label_pdf_path` wordt per order opgeslagen zodat de download-knop in de labels-lijst werkt.
  - **Nieuwe sidebar-Livewire** `show-create-my-parcel-return-label-order` met een eigen knop "Retourlabel aanmaken" die de retourlabel-flow afhandelt (zelfde modal-form als voorheen: vervoerder, pakket-/verzendtype, mail-klant-toggle, persoonlijke notitie). Vervangt de oude header-action.
  - De header-actions `CreateMyParcelLabelAction` en `CreateMyParcelReturnLabelAction` zijn verwijderd.
- De PDF-download in de labels-lijst (`show-my-parcel-orders` blade) toont al een download-icoon wanneer `label_pdf_path` is gezet; alle nieuwe labels die via deze sidebar-flows worden aangemaakt worden per order op disk opgeslagen.

## v4.1.2 - 2026-05-05

### Changed
- "Verzendstatussen ophalen bij MyParcel"-knop in de bestellingen-toolbar is nu een compacte icon-button met tooltip in plaats van een grote primaire knop, zodat hij minder visueel gewicht krijgt naast de andere acties.

## v4.1.1 - 2026-05-05

### Added
- Knop "Verzendstatussen ophalen bij MyParcel" in de toolbar van de bestellingen-lijst. Triggert handmatig de bestaande `dashed:check-my-parcel-orders` command voor alle niet-afgehandelde bestellingen via de queue, zodat de admin niet hoeft te wachten op de volgende kwartier-run van de scheduler. Vraagt om bevestiging voordat de sync start.

## v4.1.0 - 2026-05-05

### Added
- Twee nieuwe header-acties op de ViewOrder pagina van een bestelling. "Verzendlabel aanmaken" zet direct een MyParcel concept klaar voor één bestelling, haalt het label PDF op en biedt het aan als download. "Retourlabel aanmaken" maakt via de unrelated-return endpoint een retourlabel aan, downloadt het PDF en kan optioneel direct een mail aan de klant sturen met het label als bijlage en een persoonlijke notitie.
- Nieuwe `MyParcel::createConceptAndLabelForOrder()` en `MyParcel::createReturnLabelForOrder()` methodes voor per-bestelling label generatie.
- Nieuwe `ReturnLabelMail` mailable die de `dashed-core::emails.layout` gebruikt en het label PDF als bijlage meestuurt. Onderwerp en inhoud zijn configureerbaar via Customsetting keys `myparcel_return_label_email_subject` en `myparcel_return_label_email_content`. Variabelen worden volgens de `:variable:` syntax gesubstitueerd (`:orderId:`, `:customerFirstName:`, `:customerLastName:`, `:siteName:`, `:siteUrl:`).
- Migratie `2026_05_05_090000_add_return_label_fields_to_my_parcel_order_table` voegt `is_return`, `is_label_email_sent`, `personal_note` en `label_pdf_path` toe aan `dashed__order_my_parcel`.
- De bestaande "ShowMyParcelOrders" component toont retourlabels nu met een eigen badge en biedt een directe download-knop voor het opgeslagen label PDF.

## v4.0.15 - 2026-04-26

### Fixed
- `MyParcel::createConcepts()` verstuurde alle bestellingen in één batch-API-call. Eén bestelling met ongeldige data (bv. leeg e-mailadres) liet de hele sync falen. De sync verstuurt nu één bestelling per API-call: bestellingen met fouten worden gemarkeerd (`error`-veld op de MyParcelOrder zichtbaar in de view-order kaart) en de overige bestellingen gaan gewoon door.
- Mislukte bestellingen krijgen na de sync één samenvattende admin-mail via `Mails::sendNotificationToAdmins()` met per-bestelling de invoice-id en foutmelding.
- Successvolle bestellingen krijgen hun `error`-veld nu automatisch leeggemaakt.

## 1.0.0 - 202X-XX-XX

- initial release
