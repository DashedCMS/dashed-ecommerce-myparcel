# Changelog

All notable changes to `dashed-ecommerce-myparcel` will be documented in this file.

## v4.0.15 - 2026-04-26

### Fixed
- `MyParcel::createConcepts()` verstuurde alle bestellingen in één batch-API-call. Eén bestelling met ongeldige data (bv. leeg e-mailadres) liet de hele sync falen. De sync verstuurt nu één bestelling per API-call: bestellingen met fouten worden gemarkeerd (`error`-veld op de MyParcelOrder zichtbaar in de view-order kaart) en de overige bestellingen gaan gewoon door.
- Mislukte bestellingen krijgen na de sync één samenvattende admin-mail via `Mails::sendNotificationToAdmins()` met per-bestelling de invoice-id en foutmelding.
- Successvolle bestellingen krijgen hun `error`-veld nu automatisch leeggemaakt.

## 1.0.0 - 202X-XX-XX

- initial release
