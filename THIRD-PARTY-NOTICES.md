# Third-party notices

## Neshan Maps Platform — Leaflet SDK

- Files: `assets/vendor/neshan-leaflet/` (`leaflet.js`, `leaflet.css` and the marker images)
- Package: [`@neshan-maps-platform/leaflet`](https://www.npmjs.com/package/@neshan-maps-platform/leaflet) version 1.0.8
- Author: Neshan Maps Org.
- License: ISC
- Bundled unmodified (byte-identical to the npm package) so that the map does not depend on a third-party CDN. The SDK is built on [Leaflet](https://leafletjs.com) (BSD-2-Clause).
- Documentation and keys: https://platform.neshan.org

## Vazirmatn font (optional, loaded from a CDN)

- Loaded from jsDelivr only in the Persian interface and only if enabled in the settings; it is not bundled.
- Font by Saber Rastikerdar, SIL Open Font License 1.1 — https://github.com/rastikerdar/vazirmatn

## Services contacted at run time

Neshan, Open-Meteo, OpenStreetMap Nominatim and the configured satellite tile servers are contacted by the visitor's browser. See the "External services and privacy" section of the README.
