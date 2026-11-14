<?php
if (!defined('ABSPATH')) exit;

/**
 * Bilingual support (Persian / English).
 *
 * All source strings are English; the Persian translation ships in
 * languages/solar-estimator-fa_IR.mo. The language used by the plugin is, in order of priority:
 *   1. an explicit scope: [solar_estimator lang="fa|en"] or ?lang= on the plugin's REST routes,
 *   2. the "Interface language" setting (if not "auto"),
 *   3. the site / user locale (standard WordPress behaviour).
 *
 * Forcing a language does not depend on the WordPress locale machinery (which only
 * supports one locale per request): the .mo file is read directly and applied through
 * the "gettext_solar-estimator" filter, so it works the same on every WordPress version.
 */
class SolEst_I18n {
    const DOMAIN = 'solar-estimator';
    const FA = 'fa_IR';
    const EN = 'en_US';

    private static $scope = '';
    private static $setting = null;
    private static $mo = [];

    public static function init() {
        add_action('init', [__CLASS__, 'load_textdomain'], 0);
        add_filter('gettext_' . self::DOMAIN, [__CLASS__, 'filter'], 10, 2);
        add_action('update_option_' . SolEst_Settings::OPT, [__CLASS__, 'reset_cache']);
        add_action('add_option_' . SolEst_Settings::OPT, [__CLASS__, 'reset_cache']);
    }

    public static function load_textdomain() {
        load_plugin_textdomain(self::DOMAIN, false, dirname(plugin_basename(SOLEST_FILE)) . '/languages');
    }

    public static function reset_cache() {
        self::$setting = null;
    }

    /** 'fa', 'fa_IR', 'persian' → fa_IR; 'en', 'en_US', 'english' → en_US; anything else → '' */
    public static function normalize($v) {
        $v = strtolower(str_replace('-', '_', trim((string) $v)));
        if ($v === 'fa' || strpos($v, 'fa_') === 0 || $v === 'persian' || $v === 'farsi') return self::FA;
        if ($v === 'en' || strpos($v, 'en_') === 0 || $v === 'english') return self::EN;
        return '';
    }

    private static function setting() {
        if (self::$setting === null) {
            $o = get_option(SolEst_Settings::OPT);
            self::$setting = is_array($o) ? self::normalize($o['language'] ?? '') : '';
        }
        return self::$setting;
    }

    /** Locale forced right now ('' = let WordPress decide). */
    public static function override() {
        if (self::$scope !== '') return self::$scope;
        $s = self::setting();
        if ($s !== '') return $s;
        $site = determine_locale();
        // fa_AF and friends have no .mo of their own: use the Persian one.
        return (strpos($site, 'fa') === 0 && $site !== self::FA) ? self::FA : '';
    }

    /** Use a language for the rest of the request (REST handlers). */
    public static function use_lang($v) {
        $l = self::normalize($v);
        if ($l !== '') self::$scope = $l;
    }

    /** Run $fn with the plugin in $lang (falsy = leave as is). */
    public static function with($lang, $fn) {
        $l = self::normalize($lang);
        if ($l === '') return $fn();
        $prev = self::$scope;
        self::$scope = $l;
        try {
            return $fn();
        } finally {
            self::$scope = $prev;
        }
    }

    /** Language facts for templates and JS: ['locale','lang','rtl','fa'] */
    public static function ui() {
        $o = self::override();
        $site = determine_locale();
        $loc = $o !== '' ? $o : (strpos($site, 'fa') === 0 ? self::FA : self::EN);
        $rtl = $o !== '' ? $o === self::FA : (strpos($site, 'fa') === 0 || is_rtl());
        return [
            'locale' => $loc,
            'lang'   => $o !== '' ? ($o === self::FA ? 'fa' : 'en') : substr($site, 0, 2),
            'rtl'    => $rtl,
            'fa'     => $loc === self::FA,
        ];
    }

    public static function filter($translation, $text) {
        $loc = self::override();
        if ($loc === '') return $translation;
        if ($loc === self::EN) return $text;
        $mo = self::mo($loc);
        return $mo ? $mo->translate($text) : $text;
    }

    private static function mo($loc) {
        if (!isset(self::$mo[$loc])) {
            self::$mo[$loc] = false;
            $file = SOLEST_DIR . 'languages/' . self::DOMAIN . '-' . $loc . '.mo';
            if (is_readable($file)) {
                if (!class_exists('MO')) require_once ABSPATH . WPINC . '/pomo/mo.php';
                $m = new MO();
                if ($m->import_from_file($file)) self::$mo[$loc] = $m;
            }
        }
        return self::$mo[$loc];
    }
}
