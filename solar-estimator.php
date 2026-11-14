<?php
/**
 * Plugin Name:       Solar Estimator & Quote System
 * Description:       Solar power plant design and quote system with the Neshan map: automatic or manual selection of panels, inverters and cables, cost and payback estimate, customer lead capture and a quote with your company letterhead. Bilingual (Persian / English). Shortcode: [solar_estimator]
 * Version:           2.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            SAH
 * Author URI:        https://imsah.ir
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       solar-estimator
 * Domain Path:       /languages
 */
if (!defined('ABSPATH')) exit;

define('SOLEST_VERSION', '2.1.0');
define('SOLEST_FILE', __FILE__);
define('SOLEST_DIR', plugin_dir_path(__FILE__));
define('SOLEST_URL', plugin_dir_url(__FILE__));

require_once SOLEST_DIR . 'includes/class-i18n.php';
require_once SOLEST_DIR . 'includes/data.php';
require_once SOLEST_DIR . 'includes/class-settings.php';
require_once SOLEST_DIR . 'includes/class-leads.php';
require_once SOLEST_DIR . 'includes/class-rest.php';
require_once SOLEST_DIR . 'includes/class-frontend.php';

SolEst_I18n::init();
SolEst_Settings::init();
SolEst_Leads::init();
SolEst_Rest::init();
SolEst_Frontend::init();

register_activation_hook(__FILE__, function () {
    SolEst_Leads::register_type();
    flush_rewrite_rules();
});
