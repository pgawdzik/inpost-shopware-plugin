# Changelog

## 2.0.15 — 2026-10-28

- Allow the full Shopware 6.7 release line for Core and Storefront (`~6.7.0`), removing the upper limit of 6.7.14.0.
- Publish the plugin under the MIT license, matching the public GitHub repository.
- Add illustrated English, Polish and German documentation and a reproducible installation ZIP builder.

## 2.0.14 — 2026-09-14

- Added compatibility with Shopware 6.7.14.0.

## 2.0.13 — 2026-08-27

- Resolved Shopware static-analysis warnings for CLI context creation and DAL entity accessors.
- Removed the media repository update from the shipping-method assignment loop.
- Fixed the missing InPost API exception import.

## 2.0.12 — 2026-08-27

- Added compatibility with Shopware 6.7.13.1.

## 2.0.11 — 2026-08-21

- GeoWidget now follows the current Shopware sales-channel language instead of a language previously remembered by the widget.
- The language is forced through GeoWidget's runtime API before the prepared map is revealed.

## 2.0.10 — 2026-08-21

- Hid GeoWidget's default map while the delivery-address position is being resolved.
- Added a short accessible loading state and reveal the map only after its initial position has been applied.

## 2.0.9 — 2026-08-21

- Fixed delivery-address map synchronization: GeoWidget point search is no longer incorrectly used as an address geocoder.
- The map now uses coordinates of an InPost point matching the delivery street and postcode, with postcode and city fallbacks.
- Preserved the configured default map view when InPost cannot resolve a reliable reference point or its API is unavailable.

## 2.0.8 — 2026-08-21

- Added an opt-out setting that automatically shows Shopware's native address phone field for sales channels with an active InPost integration.
- Added checkout validation that requires a phone number only when an InPost shipping method is selected.
- Kept phone data in Shopware's native customer and order address fields without introducing plugin-specific phone storage.

## 2.0.7 — 2026-08-21

- Switched the production pickup-point selector to InPost's international GeoWidget.
- Added per-sales-channel country selection, with all supported countries enabled by default and Poland retained as the fallback view.
- Added safe delivery-address synchronization using GeoWidget's built-in search, while preserving the fallback view for incomplete, unsupported or unresolved addresses.

## 2.0.6 — 2026-08-19

- Disabled InPost delivery methods until the required credentials are configured for a sales channel.
- Added ShipX connection testing in Administration and per-sales-channel availability synchronization.
- Removed plugin-owned media and sales-channel assignments during a full uninstall.
- Added Shopware translation fallback files and removed development artifacts from the release archive.
