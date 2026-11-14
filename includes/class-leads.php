<?php
if (!defined('ABSPATH')) exit;

class SolEst_Leads {
    const TYPE = 'solest_lead';
    const NUM = ['lat', 'lng', 'value', 'areaM2', 'dcKwp', 'acKw', 'panels', 'inverters', 'capexUsd', 'capexMillion', 'genMwh', 'paybackMin', 'paybackMax'];
    const STR = ['address' => 200, 'panelName' => 120, 'invName' => 120, 'notes' => 600];

    public static function init() {
        add_action('init', [__CLASS__, 'register_type']);
        add_filter('manage_' . self::TYPE . '_posts_columns', [__CLASS__, 'columns']);
        add_action('manage_' . self::TYPE . '_posts_custom_column', [__CLASS__, 'column'], 10, 2);
        add_action('add_meta_boxes', function () {
            add_meta_box('solest_snapshot', __('Estimate details', 'solar-estimator'), [__CLASS__, 'box'], self::TYPE, 'normal', 'high');
        });
    }

    public static function labels() {
        return [
            'address' => __('Approximate address', 'solar-estimator'), 'coords' => __('Coordinates', 'solar-estimator'),
            'installType' => __('Installation type', 'solar-estimator'), 'mode' => __('Input basis', 'solar-estimator'),
            'value' => __('Input value', 'solar-estimator'), 'areaM2' => __('Area (m²)', 'solar-estimator'),
            'dcKwp' => __('Panel capacity (kWp)', 'solar-estimator'), 'acKw' => __('Inverter capacity (kW)', 'solar-estimator'),
            'panels' => __('Number of panels', 'solar-estimator'), 'panelName' => __('Panel model', 'solar-estimator'),
            'inverters' => __('Number of inverters', 'solar-estimator'), 'invName' => __('Inverter model', 'solar-estimator'),
            'notes' => __('Applicant’s notes', 'solar-estimator'),
            'capexMillion' => __('Construction cost (million Toman)', 'solar-estimator'), 'capexUsd' => __('Construction cost (USD)', 'solar-estimator'),
            'genMwh' => __('Annual generation (MWh)', 'solar-estimator'), 'payback' => __('Payback (years)', 'solar-estimator'),
        ];
    }

    public static function types() {
        return [
            'ground' => __('Ground-mount', 'solar-estimator'),
            'shed' => __('Shed roof', 'solar-estimator'),
            'rooftop' => __('Flat rooftop', 'solar-estimator'),
        ];
    }

    public static function register_type() {
        // Requests contain personal data (name, phone): only administrators may see or manage them.
        $admin = 'manage_options';
        register_post_type(self::TYPE, [
            'labels' => [
                'name' => __('Solar plant requests', 'solar-estimator'), 'singular_name' => __('Request', 'solar-estimator'),
                'menu_name' => __('Solar Estimator', 'solar-estimator'), 'all_items' => __('Requests', 'solar-estimator'),
                'edit_item' => __('View request', 'solar-estimator'), 'search_items' => __('Search requests', 'solar-estimator'),
                'not_found' => __('No requests yet.', 'solar-estimator'),
            ],
            'public' => false, 'show_ui' => true, 'menu_icon' => 'dashicons-lightbulb', 'menu_position' => 58,
            'supports' => ['title'], 'map_meta_cap' => true,
            'capability_type' => 'post',
            'capabilities' => [
                'create_posts' => 'do_not_allow',
                'edit_posts' => $admin, 'edit_others_posts' => $admin, 'edit_published_posts' => $admin, 'edit_private_posts' => $admin,
                'publish_posts' => $admin, 'read_private_posts' => $admin,
                'delete_posts' => $admin, 'delete_others_posts' => $admin, 'delete_published_posts' => $admin, 'delete_private_posts' => $admin,
            ],
        ]);
    }

    public static function normalize_phone($p) {
        $p = strtr((string) $p, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
        $p = preg_replace('/\D+/', '', $p);
        if (strpos($p, '0098') === 0) $p = '0' . substr($p, 4);
        elseif (strpos($p, '98') === 0 && strlen($p) === 12) $p = '0' . substr($p, 2);
        elseif (strlen($p) === 10 && $p[0] === '9') $p = '0' . $p;
        return preg_match('/^09\d{9}$/', $p) ? $p : '';
    }

    public static function clean_estimate($e) {
        $e = is_array($e) ? $e : [];
        $o = [];
        foreach (self::NUM as $k) if (isset($e[$k]) && is_numeric($e[$k])) $o[$k] = round((float) $e[$k], 3);
        foreach (self::STR as $k => $max) if (isset($e[$k])) $o[$k] = mb_substr(sanitize_text_field((string) $e[$k]), 0, $max);
        $o['installType'] = isset(self::types()[$e['installType'] ?? '']) ? $e['installType'] : 'ground';
        $o['mode'] = ($e['mode'] ?? '') === 'power' ? 'power' : 'area';
        return $o;
    }

    /** @return int|WP_Error */
    public static function create($name, $company, $phone, $estimate) {
        $id = wp_insert_post(['post_type' => self::TYPE, 'post_status' => 'publish', 'post_title' => $name . ($company ? ' — ' . $company : '')], true);
        if (is_wp_error($id)) return $id;
        update_post_meta($id, '_solest_phone', $phone);
        update_post_meta($id, '_solest_company', $company);
        update_post_meta($id, '_solest_estimate', wp_json_encode($estimate, JSON_UNESCAPED_UNICODE));
        self::notify($id, $name, $company, $phone, $estimate);
        return $id;
    }

    private static function notify($id, $name, $company, $phone, $e) {
        $to = SolEst_Settings::get('notify_email');
        if (!$to) return;
        $dash = '—';
        $types = self::types();
        $lines = [
            /* translators: %s: applicant name */
            sprintf(__('Name: %s', 'solar-estimator'), $name),
            /* translators: %s: company name */
            sprintf(__('Company: %s', 'solar-estimator'), $company ?: $dash),
            /* translators: %s: mobile number */
            sprintf(__('Mobile: %s', 'solar-estimator'), $phone),
            '',
            /* translators: %s: installation type */
            sprintf(__('Installation type: %s', 'solar-estimator'), $types[$e['installType']] ?? ''),
            /* translators: %s: capacity in kWp */
            sprintf(__('Capacity: %s kWp', 'solar-estimator'), $e['dcKwp'] ?? $dash),
            /* translators: %s: cost */
            sprintf(__('Construction cost: %s', 'solar-estimator'), isset($e['capexMillion']) ? sprintf(__('%s million Toman', 'solar-estimator'), number_format($e['capexMillion'])) : $dash),
            /* translators: %s: address */
            sprintf(__('Address: %s', 'solar-estimator'), $e['address'] ?? $dash),
            isset($e['lat'], $e['lng']) ? sprintf(
                /* translators: %s: map link */
                __('Map: %s', 'solar-estimator'),
                'https://www.openstreetmap.org/?mlat=' . $e['lat'] . '&mlon=' . $e['lng'] . '#map=17/' . $e['lat'] . '/' . $e['lng']
            ) : '',
            '',
            /* translators: %s: admin link */
            sprintf(__('View in admin: %s', 'solar-estimator'), admin_url('post.php?post=' . $id . '&action=edit')),
        ];
        wp_mail(
            $to,
            /* translators: %s: site name */
            sprintf(__('New solar plant estimate request — %s', 'solar-estimator'), wp_specialchars_decode(get_bloginfo('name'))),
            implode("\n", $lines)
        );
    }

    public static function columns($c) {
        return [
            'cb' => $c['cb'], 'title' => __('Applicant', 'solar-estimator'), 'solest_phone' => __('Mobile', 'solar-estimator'),
            'solest_cap' => __('Capacity', 'solar-estimator'), 'solest_capex' => __('Construction cost', 'solar-estimator'), 'date' => __('Date', 'solar-estimator'),
        ];
    }

    public static function column($col, $id) {
        $e = json_decode((string) get_post_meta($id, '_solest_estimate', true), true) ?: [];
        if ($col === 'solest_phone') echo '<span dir="ltr">' . esc_html(get_post_meta($id, '_solest_phone', true)) . '</span>';
        if ($col === 'solest_cap') echo isset($e['dcKwp']) ? '<span dir="ltr">' . esc_html(number_format_i18n($e['dcKwp'], 1)) . ' kWp</span>' : '—';
        if ($col === 'solest_capex') echo isset($e['capexMillion']) ? esc_html(sprintf(__('%s million Toman', 'solar-estimator'), number_format_i18n($e['capexMillion']))) : '—';
    }

    public static function box($post) {
        $e = json_decode((string) get_post_meta($post->ID, '_solest_estimate', true), true) ?: [];
        $types = self::types();
        $rows = [
            __('Applicant', 'solar-estimator') => esc_html($post->post_title),
            __('Mobile', 'solar-estimator') => '<span dir="ltr">' . esc_html(get_post_meta($post->ID, '_solest_phone', true)) . '</span>',
        ];
        $vals = [];
        foreach (self::labels() as $k => $label) {
            if ($k === 'coords') {
                if (isset($e['lat'], $e['lng'])) $vals[$label] = '<a dir="ltr" target="_blank" rel="noopener" href="' . esc_url('https://www.openstreetmap.org/?mlat=' . $e['lat'] . '&mlon=' . $e['lng'] . '#map=17/' . $e['lat'] . '/' . $e['lng']) . '">' . esc_html($e['lat'] . ', ' . $e['lng']) . '</a>';
                continue;
            }
            if ($k === 'payback') {
                if (isset($e['paybackMin'], $e['paybackMax'])) {
                    /* translators: 1: minimum years, 2: maximum years */
                    $vals[$label] = esc_html(sprintf(__('%1$s to %2$s', 'solar-estimator'), $e['paybackMin'], $e['paybackMax']));
                }
                continue;
            }
            if (!isset($e[$k])) continue;
            $v = $e[$k];
            if ($k === 'installType') $v = $types[$v] ?? $v;
            if ($k === 'mode') $v = $v === 'power' ? __('Capacity (kW)', 'solar-estimator') : __('Area (m²)', 'solar-estimator');
            $vals[$label] = '<span dir="auto">' . esc_html(is_numeric($v) ? number_format_i18n((float) $v, 1) : $v) . '</span>';
        }
        echo '<table class="widefat striped"><tbody>';
        foreach ($rows + $vals as $l => $v) echo '<tr><th style="width:200px">' . esc_html($l) . '</th><td>' . $v . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput -- every value is escaped when it is built above
        echo '</tbody></table><p class="description">' . esc_html__('The numbers were calculated in the customer’s browser and sent over; they are useful for sales follow-up, not as a binding document.', 'solar-estimator') . '</p>';
    }
}
