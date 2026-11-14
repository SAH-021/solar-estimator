# Solar Estimator & Quote System

**A WordPress plugin that turns a map pin and a number into a complete solar power plant estimate — equipment, cables, cost, payback and a printable quote on your own letterhead.**

[فارسی](README.fa.md) · English

![Version](https://img.shields.io/badge/version-2.1.0-blue) ![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-21759b) ![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4) ![License](https://img.shields.io/badge/license-GPLv2%2B-green) ![Languages](https://img.shields.io/badge/languages-Persian%20%7C%20English-orange)

Place the shortcode `[solar_estimator]` on any page. Your visitors pin the project location on a [Neshan](https://platform.neshan.org) map (or draw the boundary of their land or roof), choose an installation type, enter an **area** or a **target capacity**, and instantly see which panels, inverter, cables and transformer the project needs, what it will cost, how much energy it will produce and when it pays for itself. They can then request an itemised quote with your letterhead, and you receive their name and mobile number as a lead.

It was built for the Iranian solar market (Neshan maps, Toman currency, SATBA guaranteed purchase tariff, Persian/RTL interface), and ships with a complete **Persian** and **English** interface.

---

## Table of contents

- [What it does](#what-it-does)
- [Features](#features)
- [How the calculation works](#how-the-calculation-works)
- [Installation](#installation)
- [Configuration](#configuration)
- [Shortcode and languages](#shortcode-and-languages)
- [Quote and letterhead](#quote-and-letterhead)
- [Leads (customer requests)](#leads-customer-requests)
- [External services and privacy](#external-services-and-privacy)
- [Security](#security)
- [Developer notes](#developer-notes)
- [FAQ](#faq)
- [Project structure](#project-structure)
- [Translating](#translating)
- [License and credits](#license-and-credits)

---

## What it does

The visitor goes through three steps on one page, and the results update live as anything changes.

**1. Locate the project.** Click the map, drag the pin, search for an address, or use the browser's geolocation. Switch to satellite imagery and draw the boundary of the land or roof — the area in m² is computed and filled in automatically. The location drives the irradiation, the tilt angle and the temperature used in the calculation.

**2. Describe the project.** Choose the installation type — *ground-mount*, *industrial shed roof* or *flat rooftop* — and whether the calculation starts from the **available area (m²)** or the **target capacity (kW)**.

**3. Choose the equipment.** Leave it on **Automatic** and the plugin picks the panel, inverter and cables for you and explains each choice in plain language ("Why this equipment?"). Or switch to **Manual**, pick every item from the equipment bank, or type your own panel, inverter or cable if your model is not listed.

**Results** (all updated live):

| Result | Details |
|---|---|
| Plant size | Panel capacity (kWp), inverter capacity (kW), DC-to-AC ratio |
| Layout | Number of panels, strings and panels per string, string voltage |
| Inverters | Count, model, efficiency |
| Grid connection | Total AC current, main breaker (MCCB) rating, transformer size (kVA) when the plant is 100 kW or more |
| Cables | DC and AC cable cross-section, length, voltage drop and whether it is within the allowed limit |
| Energy | Annual generation (MWh), performance ratio (PR), tilt angle, annual irradiation |
| Money | Total construction cost in Toman and USD, first-year revenue under SATBA guaranteed purchase and under the energy-exchange green board, payback range in years |
| Quote | An itemised equipment list you can print or save as PDF, on your letterhead |

---

## Features

- **Neshan map** with six map styles, marker, address search, geolocation and polygon drawing with live area measurement. The Neshan SDK is bundled, so the map does not depend on a CDN.
- **Satellite imagery** with automatic fallback: if the first imagery source does not load (for example because it is filtered), the next one is tried. Sources are configurable.
- **Automatic or manual equipment selection**, with a short explanation of every automatic choice.
- **Custom equipment**: visitors can type any panel, inverter or cable that is not in the bank; the electrical calculation adapts.
- **Engineering-grade checks**: temperature-corrected string voltage, inverter input limits, DC-to-AC ratio, cable ampacity and voltage-drop limits, transformer and breaker sizing.
- **Financial model** with daily prices that the site administrator can change from the top of the tool itself, for all visitors at once.
- **Lead capture**: name and mobile number before the quote (optional), saved as a request in the dashboard and sent to your email. Spam protection included (honeypot, timing check, per-IP rate limit).
- **Quote on your letterhead**: top-banner or full-A4 background letterhead, optional footer image, company name and phone, optional total-cost line and disclaimer. Print or save as PDF from a clean print view that is independent of your theme.
- **Bilingual**: Persian (RTL) and English (LTR). Automatic from the site language, forced in the settings, or per page with `[solar_estimator lang="en"]`.
- **Fits shared hosting**: no Composer, no Node.js, no external PHP dependencies, no custom database tables.
- **Block themes and page builders**: works in classic and block themes; Elementor's Shortcode widget works too.

---

## How the calculation works

The calculation runs in the visitor's browser (`assets/js/engine.js`, a pure function with no DOM access), so results are instant and the server is never loaded by it. The key assumptions are listed here so you can judge whether they suit your market.

**Irradiation and temperature.** Annual horizontal irradiation comes from a built-in latitude-based atlas (about 2,250 kWh/m² in the south, decreasing northwards, 1,380 on the Caspian coast, clamped between 1,350 and 2,220). Air temperature and wind speed are fetched from [Open-Meteo](https://open-meteo.com) for the pinned point — either the current value or the previous year's average (a setting). If the request fails, defaults are used. Cell temperature = air temperature + 26 − 1.1 × wind speed.

**Panel count.** From area: `area ÷ (m² per kW) ÷ panel kW` (15 m²/kW for ground-mount and 7 m²/kW for rooftops by default — both configurable). From capacity: `kW × 1000 ÷ panel watts`. The panels are then arranged in whole strings.

**Strings.** The minimum ambient temperature is estimated from the latitude. The open-circuit voltage is corrected to that temperature, and the maximum panels in series are limited so the string never exceeds the 1,100 V inverter input limit (with a 35 V margin). The planned string length is about 600 V of operating voltage plus four panels, and at least 14 panels.

**Inverters.** Automatic mode picks a 50 kW unit up to 55 kWp, a 110 kW unit from 500 kWp, and a 100 kW unit in between. The number of units is the larger of what the DC-to-AC ratio allows (maximum 1.25) and what the number of strings needs (MPPT inputs).

**DC cable.** Sized for 1.25 × the panel short-circuit current, with a voltage drop of at most **1.35 %** of the string voltage. Run length is estimated from the site area (clamped between 25 and 140 m).

**AC cable.** Three-phase 400 V, power factor 0.99, sized for 1.25 × the inverter current with a voltage drop of at most **1.5 %**, using resistance and reactance. Copper is excluded automatically above 150 kW of inverter capacity. Run length is estimated from the site area (20–120 m).

**Transformer and breaker.** A transformer is proposed from 100 kW of inverter capacity upwards: the standard size (100 to 2,500 kVA) that covers 115 % of the AC power. The main breaker is 125 % of the plant current, rounded up to the next 50 A.

**Annual generation.** `kWp × irradiation × tilt factor × PR × adjustment factor`, where PR = (1 − temperature loss) × inverter efficiency × 0.93 system losses × bifacial gain. The tilt angle is latitude − 2° for ground-mount, latitude − 3° for flat rooftops and 12° for shed roofs, with tilt factors of 1.05, 1.02 and 0.96.

**Money.** Construction cost = kWp × price per kW (ground-mount and rooftop prices are separate) × USD rate. Revenue = annual kWh × SATBA tariff (× a multiplier for the energy-exchange green board). Payback is shown as a range between the two revenue models.

> **Important:** this is a *preliminary estimate* for sales and planning, not a design document. Equipment specs, prices and rules change; check every default against your own supplier data. Final design needs a site survey and a qualified engineer.

The equipment bank (4 panels, 6 inverters, 4 DC and 8 AC cable sizes) lives in `includes/data.php` and is easy to edit.

---

## Installation

**From a release zip**

1. Download `solar-estimator-x.y.z.zip` from the [Releases](../../releases) page.
2. In WordPress: **Plugins → Add New → Upload Plugin**, choose the zip, then **Activate**.

**From Git**

```bash
cd wp-content/plugins
git clone https://github.com/<your-username>/solar-estimator.git
```

Then activate it under **Plugins**.

**Requirements:** WordPress 5.8+, PHP 7.4+. Tested on the current WordPress development version with a block theme (Twenty Twenty-Five).

---

## Configuration

Go to **Solar Estimator → Settings** in the dashboard.

1. **Map key (Web)** — create a Web key for your site's domain at [platform.neshan.org](https://platform.neshan.org) and paste it. Without it the tool still works, but without a map (visitors get the default location and the calculation uses the country's average irradiation).
2. **Service key** *(optional)* — a Neshan service key enables address search through Neshan. It is used on the server only and is never sent to the browser. Without it, search falls back to OpenStreetMap.
3. Put `[solar_estimator]` on a page.

Everything else has sensible defaults:

| Section | What you can set |
|---|---|
| Language | Interface language: automatic, Persian or English |
| Daily prices and revenue | Price per kW (ground / rooftop), USD rate, SATBA tariff, green-board multiplier, generation adjustment factor, area needed per kW |
| Neshan map | Keys, map style, satellite imagery sources, temperature source |
| Quote and letterhead | Require name and mobile number, notification email, letterhead image and mode, margins, footer image, company name and phone, show total cost, disclaimer |
| Appearance | Title, subtitle, colours, Vazirmatn font |

**Daily price updates.** While you are logged in as an administrator, a **Daily price settings** box appears at the top of the tool itself. Change the four numbers and press save — they apply to all visitors immediately, without opening the settings page.

---

## Shortcode and languages

```text
[solar_estimator]              uses the language set in Settings (default: the site language)
[solar_estimator lang="en"]    English, left-to-right
[solar_estimator lang="fa"]    Persian, right-to-left
```

This lets a bilingual site use the same plugin on its Persian and English pages. The language also applies to the messages the server sends back (for example form validation errors).

Language resolution order: the shortcode's `lang` → the **Interface language** setting → the site/user language. If the site language is neither Persian nor English, the interface is in English.

---

## Quote and letterhead

After the visitor presses **Get the equipment list and quote**, the plugin shows an itemised table:

| Item | Content |
|---|---|
| Solar module | Quantity and model |
| Grid-tied inverter | Quantity and model |
| Mounting structure | kWp equivalent, hot-dip galvanized |
| Earthing and lightning protection | One complete standard set |
| Engineering, installation and commissioning | Execution, testing and grid hand-over |
| Client notes | Whatever the visitor typed (optional) |

The total cost line is **off by default** (the quote lists equipment only) and can be turned on. A disclaimer can be shown at the bottom (default text provided, editable).

**Letterhead.** Upload your letterhead image under **Quote and letterhead**:

- **Top banner** — a wide image above the quote, with an optional footer image.
- **Full A4 image** — a whole letterhead sheet used as the page background; set the top and bottom text margins (mm) so the text falls inside the blank area. In this mode only the first page carries the letterhead.
- **No image** — the company name and phone are printed at the top instead.

**Print / PDF.** The button opens the browser's print dialog from a hidden frame, so it is independent of your theme and is not blocked by pop-up blockers. Choose *Save as PDF* as the printer for a PDF.

---

## Leads (customer requests)

If **Ask for name and mobile number before showing the quote** is on (the default), every customer who requests a quote is saved under **Solar Estimator → Requests** with: name, company, mobile number, the location (with a map link), installation type, area or capacity, panel and inverter details, cost, generation, payback and the customer's notes. A notification email goes to the address you set.

- The mobile number is validated (Iranian mobile format; Persian and Arabic digits are accepted).
- Requests contain personal data, so **only administrators** can view or manage them.
- Uninstalling the plugin removes its settings but **keeps the requests**.
- The figures in a request were calculated in the customer's browser and sent over; treat them as a sales lead, not as a binding document.

---

## External services and privacy

The plugin contacts these services **from the visitor's browser** (not from your server), except where noted. Check that they suit your audience and legal requirements.

| Service | Used for | What is sent |
|---|---|---|
| Neshan map servers | The map | Map requests, with your Web key |
| Neshan API (from your server) | Address search, only if a service key is set | The search text and the map centre |
| [Open-Meteo](https://open-meteo.com) | Temperature and wind at the pinned point | The coordinates of the pin |
| [OpenStreetMap Nominatim](https://nominatim.org) | Address search fallback | The search text |
| Satellite tile servers (default: Google and Esri, configurable) | Satellite imagery | Tile coordinates |
| jsDelivr | The Vazirmatn font, Persian interface only (optional) | A font request |

> **Satellite imagery terms.** Fetching Google or Esri tiles directly is governed by those providers' terms of use, which may restrict this use. The sources are fully configurable (or can be removed) in the settings, and **you are responsible for complying with the terms of the sources you configure**. Consider a licensed tile provider for commercial sites.

The plugin sets no cookies and does not track visitors. Customer details are stored in your own WordPress database only when a visitor submits the quote form. If you operate under data-protection rules, mention this in your privacy policy.

---

## Security

- Public REST endpoints (address search, lead submission) are rate-limited per IP and the lead form has a honeypot field and a minimum-time check. They deliberately use no nonce so the page can be fully cached.
- The daily-price endpoint requires the `manage_options` capability **and** a valid REST nonce.
- All input is sanitised and validated on the server; all output is escaped. Text typed by visitors (custom equipment names, notes) is escaped before it reaches the page or the quote.
- The Neshan service key stays on the server.
- Lead records are not public, not exposed through the REST API, and restricted to administrators.
- No `eval`, no remote code, and no remote JavaScript apart from the services listed above.

If you find a vulnerability, please report it privately (see [Contributing](#contributing)) rather than in a public issue.

---

## Developer notes

**Behind Cloudflare, ArvanCloud or any proxy.** The per-IP rate limit uses `REMOTE_ADDR`, which behind a proxy is the proxy's address — all visitors would then share one limit. Tell the plugin where the real address is:

```php
add_filter( 'solest_client_ip', function ( $ip ) {
    // Only do this if your server is reachable exclusively through the proxy.
    return isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) : $ip;
} );
```

**Changing the equipment bank, cable tables or constants.** Edit `includes/data.php` (equipment, cables) and `assets/js/engine.js` (calculation rules). The engine is a pure function: `SolEstEngine.calculate(input, config)`.

**Adding a language.** See [Translating](#translating).

**Coding notes.** Classes are prefixed `SolEst_`, options live in a single `solest_settings` option, requests are a non-public custom post type `solest_lead`, the REST namespace is `solar-estimator/v1`.

### Contributing

Issues and pull requests are welcome. Please keep the plugin free of Composer and Node dependencies at runtime, keep it PHP 7.4 compatible, add new user-facing strings with the `solar-estimator` text domain (English source strings), and update `languages/` and `CHANGELOG.md`. For security reports, use GitHub's private vulnerability reporting (the repository's *Security* tab) or contact the author through [imsah.ir](https://imsah.ir).

---

## FAQ

**The map does not appear.** Enter the Neshan **Web** key under Settings. The key must be created for your site's exact domain.

**The satellite view stays grey.** Some imagery sources are filtered in some networks. Use the **Other satellite source** button, or change the sources in Settings.

**All my visitors hit "Too many requests".** You are behind a proxy; see [Developer notes](#developer-notes).

**Can I use it without a map?** Yes. Without a key the tool shows a notice (to administrators) and visitors calculate from the default location.

**Can I change prices?** Yes: Settings, or the admin box at the top of the tool.

**Does it work with Elementor / Gutenberg / classic editor?** Yes — put the shortcode in an Elementor *Shortcode* widget, a Gutenberg *Shortcode* block or a classic page.

**Can I translate it to another language?** Yes, see [Translating](#translating). The interface strings are standard WordPress translatable strings.

**Are the results guaranteed?** No. They are preliminary estimates; see the note under [How the calculation works](#how-the-calculation-works).

---

## Project structure

```text
solar-estimator/
├── solar-estimator.php          Plugin header and bootstrap
├── uninstall.php                Removes settings and caches (keeps customer requests)
├── includes/
│   ├── class-i18n.php           Persian / English language handling
│   ├── class-settings.php       Settings page and option sanitising
│   ├── class-frontend.php       Shortcode, assets, front-end configuration
│   ├── class-rest.php           REST routes: address search, lead, daily prices
│   ├── class-leads.php          Customer requests (custom post type, email)
│   ├── data.php                 Equipment bank: panels, inverters, cables
│   └── template.php             The tool's HTML
├── assets/
│   ├── js/engine.js             Calculation engine (no DOM)
│   ├── js/estimator.js          Interface, map, drawing, quote, printing
│   ├── css/estimator.css        Tool styles (RTL and LTR)
│   ├── css/sheet.css            Quote sheet styles
│   └── vendor/neshan-leaflet/   Neshan Maps SDK (ISC), bundled unmodified
└── languages/                   .pot template, Persian .po / .mo
```

---

## Translating

All strings use the `solar-estimator` text domain with English as the source language. The Persian translation is `languages/solar-estimator-fa_IR.po` (compiled to `.mo`).

To add a language, copy `languages/solar-estimator.pot` to `languages/solar-estimator-<locale>.po`, translate it with [Poedit](https://poedit.net) (it compiles the `.mo`), and place both files in `languages/`. To regenerate the template after changing code, run `wp i18n make-pot . languages/solar-estimator.pot` (WP-CLI). Right-to-left languages are supported by the layout.

---

## License and credits

Released under the **GNU General Public License v2 or later** — see [LICENSE](LICENSE).

Bundles the **Neshan Maps Platform Leaflet SDK** (ISC license) — see [THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md).

Author: **SAH** — [imsah.ir](https://imsah.ir)
