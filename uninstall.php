<?php
// Uninstalling removes the settings and the temporary caches only.
// Customer requests (the "Solar Estimator → Requests" list) are business data and are kept.
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

delete_option('solest_settings');

global $wpdb;
// Search-result cache and per-IP rate-limit counters (transients and their timeout rows).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_solest\_%' OR option_name LIKE '\_transient\_timeout\_solest\_%'");
