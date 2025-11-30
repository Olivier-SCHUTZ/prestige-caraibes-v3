<?php

/**
 * Plugin Name: PC Reservation Core
 * Description: Noyau interne de gestion des réservations (logements + expériences).
 * Author: Prestige Caraïbes
 * Version: 0.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Constantes de base
define('PC_RES_CORE_VERSION', '0.1.0');
define('PC_RES_CORE_PATH', plugin_dir_path(__FILE__));
define('PC_RES_CORE_URL', plugin_dir_url(__FILE__));

// Chargement des fichiers internes
require_once PC_RES_CORE_PATH . 'db/schema.php';
require_once PC_RES_CORE_PATH . 'includes/class-reservation.php';
require_once PC_RES_CORE_PATH . 'includes/class-payment.php';
require_once PC_RES_CORE_PATH . 'includes/class-booking-engine.php';
require_once PC_RES_CORE_PATH . 'includes/class-dashboard-ajax.php';
require_once PC_RES_CORE_PATH . 'shortcodes/shortcode-calendar.php';
require_once PC_RES_CORE_PATH . 'shortcodes/shortcode-dashboard.php';
require_once PC_RES_CORE_PATH . 'includes/class-ical-export.php';
require_once PC_RES_CORE_PATH . 'includes/class-settings.php';
require_once PC_RES_CORE_PATH . 'includes/gateways/class-stripe-manager.php';
require_once PC_RES_CORE_PATH . 'includes/gateways/class-stripe-ajax.php';
require_once PC_RES_CORE_PATH . 'includes/gateways/class-stripe-webhook.php';
// controller-forms sera branché plus tard quand tu seras prêt
if (file_exists(PC_RES_CORE_PATH . 'includes/controller-forms.php')) {
    require_once PC_RES_CORE_PATH . 'includes/controller-forms.php';
}


// Activation : création / mise à jour des tables
register_activation_hook(__FILE__, function () {
    if (class_exists('PCR_Reservation_Schema')) {
        PCR_Reservation_Schema::install();
    }
});

// Initialisation du plugin
// Initialisation du plugin
add_action('plugins_loaded', function () {

    // Initialisation du noyau réservation (si besoin plus tard)
    if (class_exists('PCR_Reservation')) {
        PCR_Reservation::init();
    }

    // Initialisation du contrôleur de formulaires (si présent)
    if (class_exists('PCR_FormController')) {
        PCR_FormController::init();
    }

    if (class_exists('PCR_Dashboard_Ajax')) {
        PCR_Dashboard_Ajax::init();
    }

    // Initialisation de l'export iCal
    if (class_exists('PCR_Ical_Export')) {
        PCR_Ical_Export::init();
    }

    // Initialisation des réglages
    if (class_exists('PCR_Settings')) {
        PCR_Settings::init();
    }

    // Initialisation AJAX Stripe
    if (class_exists('PCR_Stripe_Ajax')) {
        PCR_Stripe_Ajax::init();
    }

    // Initialisation Webhook
    if (class_exists('PCR_Stripe_Webhook')) {
        PCR_Stripe_Webhook::init();
    }

    // Initialisation Messagerie
    if (file_exists(PC_RES_CORE_PATH . 'includes/class-messaging.php')) {
        require_once PC_RES_CORE_PATH . 'includes/class-messaging.php';
    }
    if (class_exists('PCR_Messaging')) {
        PCR_Messaging::init();
    }

    // --- AUTOMATISATION : CRON JOB (Vérification quotidienne des cautions) ---
    if (!wp_next_scheduled('pc_cron_daily_caution_check')) {
        wp_schedule_event(time(), 'daily', 'pc_cron_daily_caution_check');
    }
    // 1. Renouvellement (pour ceux qui restent)
    add_action('pc_cron_daily_caution_check', ['PCR_Stripe_Manager', 'process_auto_renewals']);

    // 2. Libération (pour ceux qui sont partis depuis 7 jours)
    add_action('pc_cron_daily_caution_check', ['PCR_Stripe_Manager', 'process_auto_releases']);

    // 3. Messagerie Automatique (Vérification des envois J-7, J-1, etc.)
    if (class_exists('PCR_Messaging')) {
        add_action('pc_cron_daily_caution_check', ['PCR_Messaging', 'process_auto_messages']);
    }
});

// Flag JS : indique au front que le noyau réservation est actif
add_action('wp_head', function () {
    echo '<script>window.pcResaCoreActive = true;</script>';
});
