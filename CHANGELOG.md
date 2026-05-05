# Changelog

All notable changes to `dashed-ecommerce-myparcel` will be documented in this file.

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
