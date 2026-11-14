<?php
if (!defined('ABSPATH')) exit;

class SolEst_Rest {
    const NS = 'solar-estimator/v1';

    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'routes']);
    }

    public static function routes() {
        $lang = ['lang' => ['sanitize_callback' => 'sanitize_text_field']];
        $geo = $lang + ['lat' => ['sanitize_callback' => 'floatval'], 'lng' => ['sanitize_callback' => 'floatval']];
        register_rest_route(self::NS, '/search', ['methods' => 'GET', 'callback' => [__CLASS__, 'search'], 'permission_callback' => '__return_true',
            'args' => $geo + ['q' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field']]]);
        register_rest_route(self::NS, '/prices', ['methods' => 'POST', 'callback' => [__CLASS__, 'prices'],
            'permission_callback' => function () { return current_user_can('manage_options'); }, 'args' => $lang]);
        register_rest_route(self::NS, '/lead', ['methods' => 'POST', 'callback' => [__CLASS__, 'lead'], 'permission_callback' => '__return_true', 'args' => $lang]);
    }

    /**
     * Per-IP request limit (no nonce, so it works with full-page caching).
     * Behind a CDN or reverse proxy REMOTE_ADDR is the proxy's address: filter `solest_client_ip`
     * to return the real visitor IP (e.g. from CF-Connecting-IP) or all visitors share one limit.
     */
    private static function limited($bucket, $max, $ttl) {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'x';
        $ip = (string) apply_filters('solest_client_ip', $ip);
        $key = 'solest_rl_' . $bucket . '_' . md5($ip);
        $n = (int) get_transient($key);
        if ($n >= $max) return true;
        set_transient($key, $n + 1, $ttl);
        return false;
    }

    private static function neshan($path, $args) {
        $key = SolEst_Settings::get('neshan_service_key');
        if (!$key) return new WP_Error('no_key', __('The Neshan service key is not set.', 'solar-estimator'), ['status' => 404]);
        $res = wp_remote_get(add_query_arg($args, 'https://api.neshan.org' . $path), ['timeout' => 8, 'headers' => ['Api-Key' => $key]]);
        if (is_wp_error($res)) return new WP_Error('neshan_down', __('Could not connect to the Neshan service.', 'solar-estimator'), ['status' => 502]);
        $data = json_decode(wp_remote_retrieve_body($res), true);
        if (wp_remote_retrieve_response_code($res) !== 200 || !is_array($data)) return new WP_Error('neshan_err', __('The Neshan service did not return a valid response.', 'solar-estimator'), ['status' => 502]);
        return $data;
    }

    public static function search(WP_REST_Request $r) {
        SolEst_I18n::use_lang($r->get_param('lang'));
        $q = mb_substr(trim($r['q']), 0, 80);
        if (mb_strlen($q) < 2) return rest_ensure_response([]);
        if (self::limited('s', 40, MINUTE_IN_SECONDS)) return new WP_Error('rate', __('Too many searches; please wait a moment.', 'solar-estimator'), ['status' => 429]);
        $lat = $r['lat'] ? max(24, min(40, (float) $r['lat'])) : 32.43;
        $lng = $r['lng'] ? max(43, min(64, (float) $r['lng'])) : 53.69;
        $ck = 'solest_s_' . md5($q . round($lat, 1) . round($lng, 1));
        if (($c = get_transient($ck)) !== false) return rest_ensure_response($c);
        $d = self::neshan('/v1/search', ['term' => $q, 'lat' => $lat, 'lng' => $lng]);
        if (is_wp_error($d)) return $d;
        $out = [];
        foreach (array_slice((array) ($d['items'] ?? []), 0, 7) as $it) {
            $x = $it['location']['x'] ?? null; $y = $it['location']['y'] ?? null;
            if (!is_numeric($x) || !is_numeric($y)) continue;
            $out[] = ['title' => sanitize_text_field($it['title'] ?? ''), 'address' => sanitize_text_field($it['address'] ?? ''), 'lat' => (float) $y, 'lng' => (float) $x];
        }
        set_transient($ck, $out, 12 * HOUR_IN_SECONDS);
        return rest_ensure_response($out);
    }

    /** Quick save of the daily prices from the top of the tool (site admin only; standard REST nonce). */
    public static function prices(WP_REST_Request $r) {
        SolEst_I18n::use_lang($r->get_param('lang'));
        $b = $r->get_json_params() ?: [];
        $map = ['ground' => 'price_ground', 'roof' => 'price_roof', 'usd' => 'usd_rate', 'satba' => 'satba_rate'];
        // Start from the full settings (stored values + defaults) so that saving four prices
        // never resets the other options (lead form, notification email, font…) to "off".
        $o = SolEst_Settings::raw();
        $saved = [];
        foreach ($map as $k => $opt) {
            $v = isset($b[$k]) ? (float) $b[$k] : 0;
            if ($v <= 0) return new WP_Error('bad_value', __('All prices must be positive numbers.', 'solar-estimator'), ['status' => 400]);
            $o[$opt] = $v; $saved[$k] = $v;
        }
        update_option(SolEst_Settings::OPT, $o);
        return rest_ensure_response(['ok' => true, 'saved' => $saved]);
    }

    public static function lead(WP_REST_Request $r) {
        SolEst_I18n::use_lang($r->get_param('lang'));
        $b = $r->get_json_params() ?: [];
        // Bots: hidden field filled in, or form submitted too fast → pretend success without saving.
        if (!empty($b['website']) || (isset($b['elapsed']) && (int) $b['elapsed'] < 1500)) return rest_ensure_response(['ok' => true]);
        if (self::limited('l', 5, HOUR_IN_SECONDS)) return new WP_Error('rate', __('Too many requests from you; please try again later.', 'solar-estimator'), ['status' => 429]);

        $name = mb_substr(sanitize_text_field($b['name'] ?? ''), 0, 80);
        $company = mb_substr(sanitize_text_field($b['company'] ?? ''), 0, 80);
        $phone = SolEst_Leads::normalize_phone($b['phone'] ?? '');
        if (mb_strlen($name) < 2) return new WP_Error('name', __('Please enter your name.', 'solar-estimator'), ['status' => 400]);
        if (!$phone) return new WP_Error('phone', __('The mobile number is not valid (e.g. 09121234567).', 'solar-estimator'), ['status' => 400]);

        $id = SolEst_Leads::create($name, $company, $phone, SolEst_Leads::clean_estimate($b['estimate'] ?? []));
        if (is_wp_error($id)) return new WP_Error('save', __('Could not save the request; please try again.', 'solar-estimator'), ['status' => 500]);
        return rest_ensure_response(['ok' => true]);
    }
}
