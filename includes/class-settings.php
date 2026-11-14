<?php
if (!defined('ABSPATH')) exit;

class SolEst_Settings {
    const OPT = 'solest_settings';
    /** Settings whose empty value means "use the (translated) default text". */
    const TEXT_KEYS = ['title', 'subtitle', 'disclaimer'];

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'register']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_assets']);
    }

    public static function map_types() {
        return [
            'standard-day'   => __('Standard (day) — recommended', 'solar-estimator'),
            'neshan'         => __('Neshan classic', 'solar-estimator'),
            'dreamy'         => __('Dreamy', 'solar-estimator'),
            'dreamy-gold'    => __('Dreamy gold', 'solar-estimator'),
            'osm-bright'     => 'OSM Bright',
            'standard-night' => __('Standard (night)', 'solar-estimator'),
        ];
    }

    /** Default value of the free-text settings, in the language currently in use. */
    public static function default_text($key) {
        switch ($key) {
            case 'title':
                return __('Solar Power Plant Design & Quote System', 'solar-estimator');
            case 'subtitle':
                return __('Pin the location on the map, enter an area or a capacity, and the panels, inverters and cables are calculated for you.', 'solar-estimator');
            case 'disclaimer':
                return __('This document is a preliminary estimate and does not replace a final quotation; the final price is set after a technical site visit.', 'solar-estimator');
        }
        return '';
    }

    public static function defaults() {
        return [
            'language' => 'auto',
            'neshan_web_key' => '', 'neshan_service_key' => '', 'map_type' => 'standard-day',
            'sat_sources' => "Google|https://mt{s}.google.com/vt/lyrs=s&x={x}&y={y}&z={z}|0123|20\nEsri|https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}||19",
            'price_ground' => 430, 'price_roof' => 350, 'usd_rate' => 65000, 'satba_rate' => 3900, 'green_mult' => 1.3, 'gen_factor' => 1,
            'area_ground' => 15, 'area_roof' => 7, 'climate_source' => 'current',
            'lead_required' => 1, 'notify_email' => get_option('admin_email'),
            'company_name' => get_bloginfo('name'), 'company_phone' => '',
            'show_price_in_doc' => 0, 'show_disclaimer' => 1, 'disclaimer' => '',
            'lh_header_id' => 0, 'lh_footer_id' => 0, 'lh_mode' => 'banner', 'lh_top_mm' => 45, 'lh_bottom_mm' => 30,
            'title' => '', 'subtitle' => '',
            'color_accent' => '#f0a500', 'color_ink' => '#13233f', 'load_font' => 1,
        ];
    }

    /** Stored values merged with defaults; free-text settings left empty stay empty. */
    public static function raw() {
        return wp_parse_args((array) get_option(self::OPT, []), self::defaults());
    }

    /** Effective settings: empty free-text settings are replaced with their default text. */
    public static function get($key = null) {
        $stored = (array) get_option(self::OPT, []);
        $o = wp_parse_args($stored, self::defaults());
        foreach (self::TEXT_KEYS as $k) {
            if (trim((string) $o[$k]) === '') $o[$k] = self::default_text($k);
        }
        // Settings saved by 2.0.x: an empty disclaimer meant "do not print one".
        if (!isset($stored['show_disclaimer']) && isset($stored['disclaimer']) && trim((string) $stored['disclaimer']) === '') {
            $o['show_disclaimer'] = 0;
        }
        return $key === null ? $o : ($o[$key] ?? null);
    }

    /** Satellite imagery sources: one per line, "name|url|subdomains|max zoom". */
    public static function sat_sources($text = null) {
        $text = $text === null ? self::get('sat_sources') : $text;
        $out = [];
        foreach (preg_split('/\R/u', (string) $text) as $line) {
            $p = array_map('trim', explode('|', $line));
            $url = $p[1] ?? '';
            if (!preg_match('#^https://\S+$#', $url) || strpos($url, '{x}') === false || strpos($url, '{y}') === false || strpos($url, '{z}') === false) continue;
            $out[] = ['name' => sanitize_text_field($p[0] !== '' ? $p[0] : __('Satellite', 'solar-estimator')), 'url' => $url, 'sub' => preg_replace('/[^a-z0-9]/i', '', $p[2] ?? ''), 'maxZoom' => max(10, min(22, (int) ($p[3] ?? 19) ?: 19))];
        }
        return array_slice($out, 0, 4);
    }

    public static function menu() {
        add_submenu_page('edit.php?post_type=' . SolEst_Leads::TYPE, __('Solar Estimator settings', 'solar-estimator'), __('Settings', 'solar-estimator'), 'manage_options', 'solest-settings', [__CLASS__, 'page']);
    }

    public static function register() {
        register_setting('solest_group', self::OPT, ['sanitize_callback' => [__CLASS__, 'sanitize']]);
    }

    public static function admin_assets($hook) {
        if (strpos((string) $hook, 'solest-settings') === false) return;
        wp_enqueue_media();
    }

    private static function image_id($v) {
        $id = absint($v);
        return ($id && wp_attachment_is_image($id)) ? $id : 0;
    }

    private static function number($v, $fallback) {
        $v = (float) str_replace(['٬', ','], '', strtr((string) $v, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٫' => '.']));
        return $v > 0 ? $v : $fallback;
    }

    /** Sanitize callback. WordPress has already unslashed the value (options.php), so it is not unslashed again. */
    public static function sanitize($in) {
        $d = self::defaults();
        $in = is_array($in) ? $in : [];
        $out = [];

        $out['language'] = ($l = SolEst_I18n::normalize($in['language'] ?? '')) !== '' ? $l : 'auto';

        foreach (['neshan_web_key', 'neshan_service_key'] as $k) $out[$k] = preg_replace('/[^A-Za-z0-9._\-]/', '', (string) ($in[$k] ?? ''));
        $out['map_type'] = isset(self::map_types()[$in['map_type'] ?? '']) ? $in['map_type'] : $d['map_type'];

        $src = trim((string) ($in['sat_sources'] ?? ''));
        if ($src === '') {
            $out['sat_sources'] = ''; // empty = no satellite button
        } else {
            $out['sat_sources'] = self::sat_sources($src) ? implode("\n", array_map('trim', preg_split('/\R/u', wp_strip_all_tags($src)))) : $d['sat_sources'];
        }

        foreach (['price_ground', 'price_roof', 'usd_rate', 'satba_rate', 'green_mult', 'gen_factor', 'area_ground', 'area_roof'] as $k) {
            $out[$k] = self::number($in[$k] ?? 0, $d[$k]);
        }
        $out['gen_factor'] = min(1.5, $out['gen_factor']);
        $out['climate_source'] = ($in['climate_source'] ?? '') === 'annual' ? 'annual' : 'current';

        $out['lead_required'] = empty($in['lead_required']) ? 0 : 1;
        $out['show_price_in_doc'] = empty($in['show_price_in_doc']) ? 0 : 1;
        $out['show_disclaimer'] = empty($in['show_disclaimer']) ? 0 : 1;
        $out['load_font'] = empty($in['load_font']) ? 0 : 1;
        $mail = sanitize_email((string) ($in['notify_email'] ?? ''));
        $out['notify_email'] = is_email($mail) ? $mail : '';
        foreach (['company_name', 'company_phone'] as $k) $out[$k] = sanitize_text_field($in[$k] ?? '');
        $out['title'] = sanitize_text_field($in['title'] ?? '');
        $out['subtitle'] = sanitize_text_field($in['subtitle'] ?? '');
        $out['disclaimer'] = sanitize_textarea_field($in['disclaimer'] ?? '');
        $out['color_accent'] = sanitize_hex_color($in['color_accent'] ?? '') ?: $d['color_accent'];
        $out['color_ink'] = sanitize_hex_color($in['color_ink'] ?? '') ?: $d['color_ink'];

        $out['lh_header_id'] = self::image_id($in['lh_header_id'] ?? 0);
        $out['lh_footer_id'] = self::image_id($in['lh_footer_id'] ?? 0);
        $out['lh_mode'] = ($in['lh_mode'] ?? '') === 'page' ? 'page' : 'banner';
        $out['lh_top_mm'] = max(0, min(120, (float) ($in['lh_top_mm'] ?? $d['lh_top_mm'])));
        $out['lh_bottom_mm'] = max(0, min(120, (float) ($in['lh_bottom_mm'] ?? $d['lh_bottom_mm'])));
        return $out;
    }

    private static function row($key, $label, $type = 'text', $help = '', $opts = []) {
        $o = self::raw();
        $id = 'solest_' . $key;
        $name = self::OPT . '[' . $key . ']';
        $v = $o[$key];
        echo '<tr><th scope="row"><label for="' . esc_attr($id) . '">' . esc_html($label) . '</label></th><td>';
        if ($type === 'checkbox') {
            echo '<label><input type="checkbox" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="1" ' . checked((int) $v, 1, false) . '> ' . esc_html__('Enabled', 'solar-estimator') . '</label>';
        } elseif ($type === 'select') {
            echo '<select id="' . esc_attr($id) . '" name="' . esc_attr($name) . '">';
            foreach ($opts as $val => $text) echo '<option value="' . esc_attr($val) . '" ' . selected($v, $val, false) . '>' . esc_html($text) . '</option>';
            echo '</select>';
        } elseif ($type === 'textarea') {
            $ph = in_array($key, self::TEXT_KEYS, true) ? ' placeholder="' . esc_attr(self::default_text($key)) . '"' : '';
            echo '<textarea id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" rows="3" class="large-text"' . $ph . '>' . esc_textarea($v) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput -- $ph is escaped above
        } elseif ($type === 'code') {
            echo '<textarea id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" rows="4" class="large-text code" dir="ltr" spellcheck="false">' . esc_textarea($v) . '</textarea>';
        } elseif ($type === 'image') {
            $url = $v ? wp_get_attachment_image_url((int) $v, 'medium') : '';
            echo '<div class="solest-media" data-title="' . esc_attr($label) . '">';
            echo '<input type="hidden" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr((int) $v) . '">';
            echo '<div class="solest-media-prev" style="margin-bottom:8px">' . ($url ? '<img src="' . esc_url($url) . '" alt="" style="max-width:320px;height:auto;border:1px solid #ccd0d4;background:#fff">' : '') . '</div>';
            echo '<button type="button" class="button solest-media-pick">' . esc_html__('Choose or upload image', 'solar-estimator') . '</button> ';
            echo '<button type="button" class="button-link-delete solest-media-clear"' . ($v ? '' : ' style="display:none"') . '>' . esc_html__('Remove image', 'solar-estimator') . '</button>';
            echo '</div>';
        } else {
            $cls = $type === 'number' ? 'small-text' : 'regular-text';
            $extra = $type === 'number' ? ' step="any" min="0" dir="ltr"' : ($type === 'key' ? ' dir="ltr" autocomplete="off"' : '');
            $ph = in_array($key, self::TEXT_KEYS, true) ? ' placeholder="' . esc_attr(self::default_text($key)) . '"' : '';
            $t = $type === 'number' ? 'number' : ($type === 'color' ? 'color' : 'text');
            echo '<input type="' . esc_attr($t) . '" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($v) . '" class="' . esc_attr($cls) . '"' . $extra . $ph . '>'; // phpcs:ignore WordPress.Security.EscapeOutput -- $extra and $ph are fixed strings or escaped above
        }
        if ($help) echo '<p class="description">' . wp_kses_post($help) . '</p>';
        echo '</td></tr>';
    }

    public static function page() {
        if (!current_user_can('manage_options')) return;
        $code = static function ($s) { return '<code dir="ltr">' . esc_html($s) . '</code>'; };
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Solar Estimator settings', 'solar-estimator'); ?></h1>
            <p><?php esc_html_e('Shortcode:', 'solar-estimator'); ?> <code>[solar_estimator]</code>
               &nbsp;·&nbsp; <?php esc_html_e('Force a language on one page:', 'solar-estimator'); ?> <code>[solar_estimator lang="en"]</code> <code>[solar_estimator lang="fa"]</code></p>
            <?php settings_errors(self::OPT); ?>
            <form method="post" action="options.php">
                <?php settings_fields('solest_group'); ?>

                <h2 class="title"><?php esc_html_e('Language', 'solar-estimator'); ?></h2>
                <table class="form-table" role="presentation">
                    <?php
                    self::row('language', __('Interface language', 'solar-estimator'), 'select',
                        __('Applies to these admin screens and to the tool on your site. “Automatic” follows the site language. A single page can override it with the lang attribute of the shortcode.', 'solar-estimator'),
                        ['auto' => __('Automatic (follow the site language)', 'solar-estimator'), 'fa_IR' => 'فارسی', 'en_US' => 'English']);
                    ?>
                </table>

                <h2 class="title"><?php esc_html_e('Daily prices and revenue', 'solar-estimator'); ?></h2>
                <table class="form-table" role="presentation">
                    <?php
                    self::row('price_ground', __('Ground-mount plant (USD per kW)', 'solar-estimator'), 'number');
                    self::row('price_roof', __('Shed roof and flat rooftop (USD per kW)', 'solar-estimator'), 'number');
                    self::row('usd_rate', __('USD rate (Toman)', 'solar-estimator'), 'number', __('The site admin can also change these four values directly from the top of the tool itself (while logged in).', 'solar-estimator'));
                    self::row('satba_rate', __('SATBA guaranteed purchase (Toman per kWh)', 'solar-estimator'), 'number');
                    self::row('green_mult', __('Energy-exchange green board multiplier relative to SATBA', 'solar-estimator'), 'number', __('Default is 1.3.', 'solar-estimator'));
                    self::row('gen_factor', __('Annual generation adjustment factor', 'solar-estimator'), 'number', __('The default 1 means no change. For a more conservative estimate use, for example, 0.9.', 'solar-estimator'));
                    self::row('area_ground', __('Area needed per kW — ground-mount (m²)', 'solar-estimator'), 'number');
                    self::row('area_roof', __('Area needed per kW — shed roof and rooftop (m²)', 'solar-estimator'), 'number');
                    ?>
                </table>

                <h2 class="title"><?php esc_html_e('Neshan map', 'solar-estimator'); ?></h2>
                <table class="form-table" role="presentation">
                    <?php
                    self::row('neshan_web_key', __('Map key (Web)', 'solar-estimator'), 'key',
                        sprintf(
                            /* translators: 1: link to platform.neshan.org, 2: key prefix */
                            __('Create a Web map key for this site’s domain at %1$s (it starts with %2$s). Without it the map is not shown.', 'solar-estimator'),
                            '<a href="https://platform.neshan.org" target="_blank" rel="noopener">platform.neshan.org</a>', $code('web.')));
                    self::row('neshan_service_key', __('Service key (optional)', 'solar-estimator'), 'key',
                        sprintf(
                            /* translators: %s: key prefix */
                            __('Starts with %s. Address search is done through Neshan (used on the server only). If empty, search uses OpenStreetMap.', 'solar-estimator'),
                            $code('service.')));
                    self::row('map_type', __('Map style', 'solar-estimator'), 'select', '', self::map_types());
                    self::row('sat_sources', __('Satellite imagery sources', 'solar-estimator'), 'code',
                        sprintf(
                            /* translators: %s: line format */
                            __('The Neshan SDK has no satellite layer. One source per line: %s. If the first source does not load, the next one is used automatically. Empty = no satellite button. The terms of use of each source (Google in particular) are your responsibility.', 'solar-estimator'),
                            $code(__('name|url|subdomains|max zoom', 'solar-estimator'))));
                    self::row('climate_source', __('Air temperature source', 'solar-estimator'), 'select',
                        __('“Current” uses the temperature at this moment and changes with the hour; “Annual average” keeps the result stable for a given point.', 'solar-estimator'),
                        ['current' => __('Current temperature', 'solar-estimator'), 'annual' => __('Annual average (more stable)', 'solar-estimator')]);
                    ?>
                </table>

                <h2 class="title"><?php esc_html_e('Quote and letterhead', 'solar-estimator'); ?></h2>
                <table class="form-table" role="presentation">
                    <?php
                    self::row('lead_required', __('Ask for name and mobile number before showing the quote', 'solar-estimator'), 'checkbox');
                    self::row('notify_email', __('Notification email for new requests', 'solar-estimator'), 'text', __('Empty = no email is sent; requests are always saved under “Requests”.', 'solar-estimator'));
                    self::row('lh_header_id', __('Letterhead image', 'solar-estimator'), 'image', __('Your company letterhead image (PNG or JPG). Without an image, the company name and phone are printed at the top of the quote.', 'solar-estimator'));
                    self::row('lh_mode', __('Letterhead type', 'solar-estimator'), 'select',
                        __('“Top banner”: a wide image placed above the quote. “Full A4 image”: an image of a complete letterhead sheet placed behind the text; set the margins below so the text falls inside the blank area.', 'solar-estimator'),
                        ['banner' => __('Top banner', 'solar-estimator'), 'page' => __('Full A4 image (background)', 'solar-estimator')]);
                    self::row('lh_top_mm', __('Text distance from the top of the sheet (mm) — A4 mode only', 'solar-estimator'), 'number');
                    self::row('lh_bottom_mm', __('Text distance from the bottom of the sheet (mm) — A4 mode only', 'solar-estimator'), 'number');
                    self::row('lh_footer_id', __('Footer image (optional) — banner mode only', 'solar-estimator'), 'image');
                    self::row('company_name', __('Company name (used when there is no letterhead)', 'solar-estimator'));
                    self::row('company_phone', __('Company phone (used when there is no letterhead)', 'solar-estimator'));
                    self::row('show_price_in_doc', __('Show the total cost estimate in the quote', 'solar-estimator'), 'checkbox', __('A quote normally lists the equipment only, so this is off by default.', 'solar-estimator'));
                    self::row('show_disclaimer', __('Show a disclaimer at the bottom of the quote', 'solar-estimator'), 'checkbox');
                    self::row('disclaimer', __('Disclaimer text', 'solar-estimator'), 'textarea', __('Leave empty to use the default text.', 'solar-estimator'));
                    ?>
                </table>

                <h2 class="title"><?php esc_html_e('Appearance', 'solar-estimator'); ?></h2>
                <table class="form-table" role="presentation">
                    <?php
                    self::row('title', __('Tool title', 'solar-estimator'));
                    self::row('subtitle', __('Text below the title', 'solar-estimator'), 'textarea', __('Leave the title and this text empty to use the defaults (they follow the interface language).', 'solar-estimator'));
                    self::row('color_ink', __('Main color (headings and text)', 'solar-estimator'), 'color');
                    self::row('color_accent', __('Accent color (buttons)', 'solar-estimator'), 'color');
                    self::row('load_font', __('Load the Vazirmatn font', 'solar-estimator'), 'checkbox', __('Loaded from a CDN, and only for the Persian interface. Turn it off if your theme already has a Persian font.', 'solar-estimator'));
                    ?>
                </table>
                <?php submit_button(__('Save settings', 'solar-estimator')); ?>
            </form>
        </div>
        <script>
        (function () {
            var useImage = <?php echo wp_json_encode(__('Use this image', 'solar-estimator')); ?>;
            document.querySelectorAll('.solest-media').forEach(function (box) {
                var input = box.querySelector('input'), prev = box.querySelector('.solest-media-prev'), clear = box.querySelector('.solest-media-clear'), frame;
                box.querySelector('.solest-media-pick').addEventListener('click', function () {
                    if (!window.wp || !wp.media) return;
                    frame = frame || wp.media({ title: box.getAttribute('data-title'), button: { text: useImage }, library: { type: 'image' }, multiple: false });
                    frame.off('select').on('select', function () {
                        var a = frame.state().get('selection').first().toJSON();
                        var u = (a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url);
                        input.value = a.id; prev.innerHTML = '';
                        var img = document.createElement('img'); img.src = u; img.alt = ''; img.style.cssText = 'max-width:320px;height:auto;border:1px solid #ccd0d4;background:#fff';
                        prev.appendChild(img); clear.style.display = '';
                    });
                    frame.open();
                });
                clear.addEventListener('click', function () { input.value = 0; prev.innerHTML = ''; clear.style.display = 'none'; });
            });
        })();
        </script>
        <?php
    }
}
