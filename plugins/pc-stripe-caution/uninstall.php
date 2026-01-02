<?php

/**
 * Fichier déclenché lors de la suppression du plugin via l'admin WordPress.
 * * @package PC_Stripe_Caution
 */

// Sécurité : on sort si WP ne nous appelle pas
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// 1. Suppression de la table personnalisée
$table_name = $wpdb->prefix . 'pc_cautions';
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// 2. Suppression des options (Réglages)
delete_option('pcsc_settings');

// 3. Suppression du Cron planifié
$timestamp = wp_next_scheduled('pcsc_cron_daily');
if ($timestamp) {
    wp_unschedule_event($timestamp, 'pcsc_cron_daily');
}
