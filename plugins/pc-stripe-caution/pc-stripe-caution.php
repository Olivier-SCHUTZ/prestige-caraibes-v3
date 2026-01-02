<?php

/**
 * Plugin Name: PC Stripe Caution
 * Description: Génération de lien Stripe (setup), caution off-session et gestion modulaire.
 * Version: 0.1.0
 * Author: Prestige Caraïbes
 * Text Domain: pc-stripe-caution
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

// define('PCSC_FORCE_LITE', true);

// 1. CONSTANTES
define('PCSC_VERSION', '0.1.0');
define('PCSC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PCSC_PLUGIN_URL', plugin_dir_url(__FILE__));
// Détection automatique du dossier PRO
// Détection : Si le dossier PRO existe ET qu'on ne force pas le mode LITE via une constante
if (defined('PCSC_FORCE_LITE') && PCSC_FORCE_LITE) {
    define('PCSC_IS_PRO', false);
} else {
    define('PCSC_IS_PRO', file_exists(PCSC_PLUGIN_DIR . 'pro/class-pcsc-pro-loader.php'));
}

// 2. INCLUDES (CORE - Toujours chargés)
require_once PCSC_PLUGIN_DIR . 'includes/class-pcsc-db.php';
require_once PCSC_PLUGIN_DIR . 'includes/class-pcsc-stripe.php';
require_once PCSC_PLUGIN_DIR . 'includes/class-pcsc-webhooks.php';
require_once PCSC_PLUGIN_DIR . 'includes/class-pcsc-mailer.php';

// 3. INCLUDES (MODULES)
// Admin
if (is_admin()) {
    require_once PCSC_PLUGIN_DIR . 'admin/class-pcsc-settings.php';
    require_once PCSC_PLUGIN_DIR . 'admin/class-pcsc-admin.php';
}
// Public (Shortcodes, etc.)
require_once PCSC_PLUGIN_DIR . 'public/class-pcsc-public.php';

// 4. CHARGEMENT PRO (Si détecté)
if (PCSC_IS_PRO) {
    require_once PCSC_PLUGIN_DIR . 'pro/class-pcsc-pro-loader.php';
}

// 5. INITIALISATION
register_activation_hook(__FILE__, ['PCSC_DB', 'activate']);

// A. Charger les traductions DÈS que les plugins sont chargés (Best Practice)
add_action('plugins_loaded', 'pcsc_load_textdomain');

function pcsc_load_textdomain()
{
    load_plugin_textdomain(
        'pc-stripe-caution',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}

// B. Initialiser le reste du plugin sur init
add_action('init', 'pcsc_init_plugin');

function pcsc_init_plugin()
{
    // Init Core
    PCSC_Webhooks::init();

    // Init Admin
    if (is_admin()) {
        PCSC_Settings::init();
        PCSC_Admin::init();
    }

    // Init Public
    PCSC_Public::init();

    // Init Pro
    if (PCSC_IS_PRO && class_exists('PCSC_Pro_Loader')) {
        PCSC_Pro_Loader::init();
    }
}

// Planificateur Cron (Basic pour Lite / Advanced géré dans Pro)
add_action('wp', function () {
    if (!wp_next_scheduled('pcsc_cron_daily')) {
        wp_schedule_event(time() + 300, 'daily', 'pcsc_cron_daily');
    }
});
