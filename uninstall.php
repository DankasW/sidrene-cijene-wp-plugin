<?php
// Brisanje plugina u WordPressu: miče postavke i zakazani zadatak.
// Objavljeni CSV-ovi u uploads/cjenici ostaju namjerno: to je javna arhiva cjenika, briše se ručno.
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

foreach (['cjenik_auto_feeds', 'cjenik_auto_log', 'cjenik_auto_counters', 'cjenik_auto_status',
          'cjenik_auto_email', 'cjenik_auto_daily', 'cjenik_auto_done', 'cjenik_auto_lock'] as $option) {
    delete_option($option);
}
delete_site_transient('cjenik_auto_release');
wp_clear_scheduled_hook('cjenik_auto_daily_event');
