<?php

/**
 * Plugin Name: PC Stripe Caution (Provisoire)
 * Description: Génération de lien Stripe (setup), caution off-session (manual capture), rotation silencieuse, libération J+7.
 * Version: 0.1.0
 * Author: Prestige Caraïbes
 */

if (!defined('ABSPATH')) exit;

define('PCSC_VERSION', '0.1.0');
define('PCSC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PCSC_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once PCSC_PLUGIN_DIR . 'includes/class-pcsc-db.php';
require_once PCSC_PLUGIN_DIR . 'includes/class-pcsc-stripe.php';
require_once PCSC_PLUGIN_DIR . 'includes/class-pcsc-shortcodes.php';
require_once PCSC_PLUGIN_DIR . 'includes/class-pcsc-webhooks.php';
require_once PCSC_PLUGIN_DIR . 'includes/class-pcsc-mailer.php';

register_activation_hook(__FILE__, ['PCSC_DB', 'activate']);

add_action('init', function () {
    PCSC_Shortcodes::init();
    PCSC_Webhooks::init();
});

add_action('pcsc_cron_daily', ['PCSC_DB', 'cron_daily']);
add_action('pcsc_cron_release', ['PCSC_DB', 'cron_release_single'], 10, 1);

add_action('wp', function () {
    // Planifie un cron quotidien si absent
    if (!wp_next_scheduled('pcsc_cron_daily')) {
        wp_schedule_event(time() + 300, 'daily', 'pcsc_cron_daily');
    }
});
