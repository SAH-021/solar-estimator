# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [2.1.0] — 2026-11-13

### Added
- **Bilingual interface (Persian / English).** English source strings with a complete Persian translation in `languages/` (`.pot`, `.po`, `.mo`). Applies to the tool, the quote, the admin screens and the messages returned by the server.
- **Interface language** setting (automatic / Persian / English) and a `lang` attribute for the shortcode: `[solar_estimator lang="en"]`, `[solar_estimator lang="fa"]`.
- Left-to-right layout (the stylesheets now follow the `dir` attribute) and language-aware numbers, dates and the Vazirmatn font (Persian only).
- **Show disclaimer** option in the settings; free-text defaults (title, subtitle, disclaimer) now follow the interface language when left empty.
- `solest_client_ip` filter for sites behind a CDN or reverse proxy.
- README in English and Persian, GPL-2.0-or-later `LICENSE`, third-party notices, `.gitignore`, `.gitattributes`.
- The uninstall routine also removes the plugin's cached search results and rate-limit counters.

### Fixed
- **Saving the four daily prices from the top of the tool could silently turn off** "ask for name and mobile", the notification email, the disclaimer and the font when the settings page had never been saved. The quick save now starts from the full effective settings.
- **Blank tool on block themes.** The scripts and styles are now registered on `init`; block themes render the page content before `wp_head`, so the shortcode ran before the handles existed and the configuration was never printed.
- Settings values were un-slashed twice, which removed backslashes from saved text.
- Customer requests could be seen by editors. They are now restricted to administrators (`manage_options`).
- The rate-limit address is sanitised before it is used.

### Changed
- Plugin header now has an author, licence and text-domain / domain-path headers.
- Removed unused code (`title` shortcode attribute, unused label table).
- Calculation engine returns translatable keys for its explanatory sentences; the numeric results are unchanged (verified against the 2.0.1 engine on 4,000 random inputs).

## [2.0.1]

- Previous release (Persian only): Neshan map, automatic / manual equipment selection, quote with letterhead, lead capture, daily price settings.
