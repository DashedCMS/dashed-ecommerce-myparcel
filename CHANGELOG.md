# Changelog

All notable changes to `dashed-ecommerce-myparcel` will be documented in this file.

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
