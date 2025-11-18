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
require_once PC_RES_CORE_PATH . 'shortcodes/shortcode-dashboard.php';
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
});

// Flag JS : indique au front que le noyau réservation est actif
add_action('wp_head', function () {
    echo '<script>window.pcResaCoreActive = true;</script>';
});
