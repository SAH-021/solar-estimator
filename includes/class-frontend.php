<?php
if (!defined('ABSPATH')) exit;

class SolEst_Frontend {
    public static function init() {
        add_shortcode('solar_estimator', [__CLASS__, 'shortcode']);
        // Registered on init (not wp_enqueue_scripts): block themes render the page content before wp_head,
        // and the shortcode needs the handles to exist when it runs.
        add_action('init', [__CLASS__, 'register_assets']);
    }

    public static function register_assets() {
        $u = SOLEST_URL . 'assets/';
        $v = SOLEST_VERSION;
        wp_register_style('solest-neshan', $u . 'vendor/neshan-leaflet/leaflet.css', [], '1.0.8');
        wp_register_script('solest-neshan', $u . 'vendor/neshan-leaflet/leaflet.js', [], '1.0.8', true);
        // The Neshan SDK writes the global L; keep it in SOLEST_L and restore any previous L (other plugins).
        wp_add_inline_script('solest-neshan', 'window.__solestPrevL=window.L;', 'before');
        wp_add_inline_script('solest-neshan', 'window.SOLEST_L=window.L;if(window.__solestPrevL===undefined){try{delete window.L}catch(e){window.L=undefined}}else{window.L=window.__solestPrevL}');
        wp_register_style('solest-vazir', 'https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css', [], null);
        wp_register_style('solest-sheet', $u . 'css/sheet.css', [], $v);
        wp_register_style('solest-app', $u . 'css/estimator.css', ['solest-sheet'], $v);
        wp_register_script('solest-engine', $u . 'js/engine.js', [], $v, true);
        wp_register_script('solest-app', $u . 'js/estimator.js', ['solest-engine', 'solest-neshan'], $v, true);
    }

    private static function img($id) {
        return $id ? (string) wp_get_attachment_image_url((int) $id, 'full') : '';
    }

    /** Every string the JavaScript needs, translated into the language in use. {name} marks a value filled in by the script. */
    private static function i18n() {
        return [
            // money & units
            'billion_toman' => __('billion Toman', 'solar-estimator'), 'million_toman' => __('million Toman', 'solar-estimator'),
            // select lists
            'opt_custom' => __('✍️ Other (I’ll type it myself)', 'solar-estimator'), 'opt_auto' => __('Automatic (recommended)', 'solar-estimator'),
            'hint_auto' => __('In automatic mode the panel, inverter and cables are chosen from the installation type and capacity. The reason for each choice is shown in the results.', 'solar-estimator'),
            'hint_manual' => __('Choose each item from the list. If your model is not listed, pick “Other” and type it yourself. Results update instantly.', 'solar-estimator'),
            'lbl_area' => __('Available area (m²)', 'solar-estimator'), 'lbl_power' => __('Target capacity (kW)', 'solar-estimator'),
            // map
            'map_nokey_title' => __('The Neshan map key has not been entered.', 'solar-estimator'),
            'map_nokey_body' => __('Add the Web key in the {link}. Until then, visitors calculate without a map, using the default location.', 'solar-estimator'),
            'map_settings_link' => __('plugin settings', 'solar-estimator'),
            'map_unavailable' => __('The map is temporarily unavailable; the calculation uses the country’s average irradiation.', 'solar-estimator'),
            'map_load_failed' => __('The map could not be loaded. Check your internet connection; the rest of the calculation still works.', 'solar-estimator'),
            'map_init_failed' => __('The Neshan map failed to start.', 'solar-estimator'),
            'pin_title' => __('Project location', 'solar-estimator'),
            'sat_switched' => __('Satellite source changed: {name}', 'solar-estimator'),
            'sat_blocked' => __('Satellite imagery does not load on your network; it may be filtered and need a VPN', 'solar-estimator'),
            'sat_credit' => __('Imagery: {name}', 'solar-estimator'), 'sat_source' => __('Satellite source: {name}', 'solar-estimator'),
            // status line
            'coord' => __('Lat: {lat}°  Lng: {lng}°', 'solar-estimator'),
            'st_climate_fetch' => __('Fetching climate data…', 'solar-estimator'), 'st_climate_ok' => __('Climate data updated ✓', 'solar-estimator'), 'st_atlas' => __('Irradiation atlas used ✓', 'solar-estimator'),
            'geo_unsupported' => __('Your browser does not support geolocation', 'solar-estimator'), 'geo_denied' => __('Location permission was not granted', 'solar-estimator'),
            'st_searching' => __('Searching…', 'solar-estimator'), 'st_not_found' => __('Place not found', 'solar-estimator'), 'st_search_failed' => __('Search is unavailable; click on the map instead', 'solar-estimator'),
            'draw_start' => __('Draw land or roof boundary', 'solar-estimator'),
            'draw_stop' => __('Drawing: click the corners (press again to finish)', 'solar-estimator'),
            'area_hint' => __('Boundary area: {area} m². When you are done, press the draw button again.', 'solar-estimator'),
            // calculation
            'val_invalid' => __('Enter a positive number.', 'solar-estimator'),
            'why_cables_manual' => __('DC cable: {dc} and AC cable: {ac}', 'solar-estimator'),
            'why_cables_auto' => __('DC cable size {dc} and, for the AC side, the economical cable {ac} were selected.', 'solar-estimator'),
            'spec_inv' => __('{kw} kW | input up to {v} V | efficiency {eff}%', 'solar-estimator'),
            'trans_yes' => __('1 transformer, {kva}', 'solar-estimator'), 'trans_no' => __('Not required (low-voltage connection)', 'solar-estimator'),
            'inverter_label' => __('Inverter: {kw}', 'solar-estimator'), 'annual_gen' => __('Annual generation: {mwh} MWh', 'solar-estimator'),
            'usd_note' => __('{usd} ({rate} Toman per USD)', 'solar-estimator'), 'roi_range' => __('{min} to {max} years', 'solar-estimator'), 'tilt_val' => __('{n} degrees', 'solar-estimator'),
            'panel_line' => __('{n} × {w}', 'solar-estimator'), 'string_line' => __('{n} strings of {ser} | string voltage {v}', 'solar-estimator'),
            'inv_line' => __('{n} × {kw}', 'solar-estimator'), 'inv_sub' => __('{brand} | efficiency {eff}', 'solar-estimator'),
            'brk_line' => __('Total AC current {a} | main breaker {b}', 'solar-estimator'),
            'ok' => __('Allowed', 'solar-estimator'), 'bad' => __('Out of limit ({max})', 'solar-estimator'), 'max_pct' => __('max {pct}%', 'solar-estimator'),
            'dc_detail' => __('Drop: {drop} {status} | {m} m (run per string {len} m)', 'solar-estimator'),
            'ac_detail' => __('Current per inverter {a} | Drop: {drop} {status} | {m} m', 'solar-estimator'),
            // engine (reasons and custom names)
            'custom' => __('Custom', 'solar-estimator'), 'custom_panel' => __('Custom panel {w}W', 'solar-estimator'), 'custom_inv' => __('Custom inverter {kw}kW', 'solar-estimator'),
            'custom_dc' => __('Custom DC cable', 'solar-estimator'), 'custom_ac' => __('Custom AC cable', 'solar-estimator'), 'calc_with' => __('calculated with {label}', 'solar-estimator'),
            'r_panel_custom' => __('You entered the panel {name} yourself (power {w} W).', 'solar-estimator'),
            'r_panel_manual' => __('You selected the panel {name}.', 'solar-estimator'),
            'r_panel_roof' => __('For a shed roof or flat rooftop with a compact factor of {area} m² per kW, the panel {name} was selected.', 'solar-estimator'),
            'r_panel_ground' => __('For a ground-mount plant with a factor of {area} m² per kW, the high-power panel {name} was selected.', 'solar-estimator'),
            'r_inv_custom' => __('You entered the inverter {name} yourself (power {kw} kW).', 'solar-estimator'),
            'r_inv_manual' => __('You selected the inverter {name}.', 'solar-estimator'),
            'r_inv_auto' => __('For a capacity of {kwp} kWp, the inverter {name} with a standard overload ratio was selected.', 'solar-estimator'),
            // quote
            'bom_panel' => __('Solar module', 'solar-estimator'), 'bom_inv' => __('Grid-tied inverter', 'solar-estimator'), 'bom_struct' => __('Mounting structure', 'solar-estimator'),
            'bom_earth' => __('Earthing and lightning protection', 'solar-estimator'), 'bom_eng' => __('Engineering, installation and commissioning', 'solar-estimator'), 'bom_notes' => __('Client notes', 'solar-estimator'),
            'bom_panel_qty' => __('{n} pcs — {name}', 'solar-estimator'), 'bom_inv_qty' => __('{n} unit(s) — {name}', 'solar-estimator'),
            'bom_struct_val' => __('Equivalent to {kwp} kWp, hot-dip galvanized', 'solar-estimator'),
            'bom_earth_val' => __('1 complete standard set', 'solar-estimator'), 'bom_eng_val' => __('Execution, testing and grid hand-over', 'solar-estimator'),
            'client_line' => __('Client: {name} | Company: {company} | Phone: {phone} | Date: {date}', 'solar-estimator'),
            'date_only' => __('Date: {date}', 'solar-estimator'), 'not_given' => __('Not provided', 'solar-estimator'), 'phone_line' => __('Phone: {phone}', 'solar-estimator'),
            'sheet_title' => __('Quote and equipment list', 'solar-estimator'), 'th_no' => __('No.', 'solar-estimator'), 'th_desc' => __('Description', 'solar-estimator'), 'th_spec' => __('Quantity and specifications', 'solar-estimator'),
            'total_line' => __('Estimated total construction cost: {cost} (about {usd})', 'solar-estimator'),
            'letterhead' => __('Letterhead', 'solar-estimator'), 'quote_doc_title' => __('Quote', 'solar-estimator'),
            // lead form
            'lead_name_short' => __('Please enter your full name.', 'solar-estimator'), 'lead_phone_bad' => __('The mobile number is not valid, e.g. 09121234567', 'solar-estimator'),
            'lead_saving' => __('Submitting…', 'solar-estimator'), 'lead_submit' => __('Confirm and issue', 'solar-estimator'),
            'lead_save_failed' => __('Submission failed; please try again.', 'solar-estimator'), 'lead_net_failed' => __('Could not reach the server; check your internet connection.', 'solar-estimator'),
            // admin quick prices
            'saving' => __('Saving…', 'solar-estimator'), 'saved' => __('Saved ✓', 'solar-estimator'), 'save_failed' => __('Could not save.', 'solar-estimator'), 'net_failed' => __('Could not reach the server.', 'solar-estimator'),
        ];
    }

    private static function config($ui) {
        $o = SolEst_Settings::get();
        $eq = solest_equipment();
        $admin = current_user_can('manage_options');
        return [
            'rest' => esc_url_raw(rest_url(SolEst_Rest::NS . '/')),
            'isAdmin' => $admin, 'nonce' => $admin ? wp_create_nonce('wp_rest') : '',
            'ui' => ['lang' => $ui['fa'] ? 'fa' : 'en', 'rtl' => $ui['rtl']],
            'i18n' => self::i18n(),
            'map' => [
                'key' => $o['neshan_web_key'], 'type' => $o['map_type'], 'center' => [32.4279, 53.688], 'zoom' => 5,
                'sats' => SolEst_Settings::sat_sources(), 'hasService' => $o['neshan_service_key'] !== '',
                'canConfigure' => $admin, 'settingsUrl' => admin_url('edit.php?post_type=' . SolEst_Leads::TYPE . '&page=solest-settings'),
            ],
            'engine' => [
                'price' => ['ground' => (float) $o['price_ground'], 'roof' => (float) $o['price_roof'], 'usdRate' => (float) $o['usd_rate'], 'satba' => (float) $o['satba_rate'], 'greenMult' => (float) $o['green_mult']],
                'areaPerKw' => ['ground' => (float) $o['area_ground'], 'roof' => (float) $o['area_roof']],
                'genFactor' => (float) $o['gen_factor'],
                'panels' => $eq['panels'], 'inverters' => $eq['inverters'], 'dc' => $eq['dc'], 'ac' => $eq['ac'],
            ],
            'climateSource' => $o['climate_source'], 'leadRequired' => (bool) $o['lead_required'],
            'doc' => [
                'company' => $o['company_name'], 'phone' => $o['company_phone'], 'disclaimer' => $o['show_disclaimer'] ? $o['disclaimer'] : '', 'showPrice' => (bool) $o['show_price_in_doc'],
                'lhMode' => $o['lh_mode'], 'lhHeader' => self::img($o['lh_header_id']), 'lhFooter' => self::img($o['lh_footer_id']),
                'lhTop' => (float) $o['lh_top_mm'], 'lhBottom' => (float) $o['lh_bottom_mm'],
                'sheetCss' => SOLEST_URL . 'assets/css/sheet.css?ver=' . SOLEST_VERSION, 'ink' => $o['color_ink'], 'accent' => $o['color_accent'],
            ],
        ];
    }

    public static function shortcode($atts) {
        $a = shortcode_atts(['lang' => ''], $atts, 'solar_estimator');
        return SolEst_I18n::with($a['lang'], [__CLASS__, 'render']);
    }

    public static function render() {
        $o = SolEst_Settings::get();
        $ui = SolEst_I18n::ui();
        $uid = 'sd' . wp_unique_id();

        wp_enqueue_style('solest-neshan');
        $vazir = $ui['fa'] && $o['load_font'];
        if ($vazir) wp_enqueue_style('solest-vazir');
        wp_enqueue_style('solest-app');
        wp_enqueue_script('solest-app');
        wp_add_inline_script('solest-app', 'window.SOLEST_CFG=window.SOLEST_CFG||{};window.SOLEST_CFG[' . wp_json_encode($uid) . ']=' . wp_json_encode(self::config($ui)) . ';', 'before');

        $style = sprintf('--ink:%s;--sun:%s;', $o['color_ink'], $o['color_accent']);
        // Persian digits only in the Persian interface.
        $fa = function ($n) use ($ui) { return $ui['fa'] ? str_replace('.', '٫', str_replace(range(0, 9), ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'], (string) $n)) : (string) $n; };
        $num = function ($n) use ($fa) { return $fa(rtrim(rtrim(number_format((float) $n, 1, '.', ''), '0'), '.')); };

        ob_start();
        include SOLEST_DIR . 'includes/template.php';
        return ob_get_clean();
    }
}
