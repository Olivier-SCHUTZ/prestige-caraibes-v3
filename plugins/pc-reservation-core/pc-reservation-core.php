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
require_once PC_RES_CORE_PATH . 'includes/class-documents.php';
require_once PC_RES_CORE_PATH . 'includes/api/class-rest-webhook.php';
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

// TEMP: Hook pour forcer la mise à jour du schéma BDD (à supprimer une fois terminé)
add_action('init', function () {
    if (is_admin() && current_user_can('manage_options')) {
        $schema_version = get_option('pc_schema_version', '0.0.0');
        if (version_compare($schema_version, '0.1.1', '<')) {
            if (class_exists('PCR_Reservation_Schema')) {
                PCR_Reservation_Schema::install();
                update_option('pc_schema_version', '0.1.1');
                error_log('[PC RESERVATION] Schéma BDD mis à jour vers 0.1.1');
            }
        }
    }
});

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

    // Initialisation Documents
    if (class_exists('PCR_Documents')) {
        PCR_Documents::init();
    }

    // Initialisation Messagerie
    if (file_exists(PC_RES_CORE_PATH . 'includes/class-messaging.php')) {
        require_once PC_RES_CORE_PATH . 'includes/class-messaging.php';
    }
    if (class_exists('PCR_Messaging')) {
        PCR_Messaging::init();
    }

    // ✨ NOUVEAU : Initialisation des Webhooks REST API
    if (class_exists('PCR_Rest_Webhook')) {
        PCR_Rest_Webhook::init();
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

// ============================================================
// 🚦 ROUTEUR WEB APP (Mode DEBUG & FORCE 99)
// ============================================================

/**
 * 1. Création de la règle de réécriture
 */
add_action('init', function () {
    $slug = function_exists('get_field') ? get_field('pc_dashboard_slug', 'option') : 'espace-proprietaire';
    if (empty($slug)) $slug = 'espace-proprietaire';

    add_rewrite_rule(
        '^' . preg_quote($slug, '/') . '/?$',
        'index.php?pc_app_dashboard=1',
        'top'
    );
});

/**
 * 2. Enregistrement de la variable
 */
add_filter('query_vars', function ($vars) {
    $vars[] = 'pc_app_dashboard';
    return $vars;
});

/**
 * 3. DEBUG : Vérification immédiate au chargement
 * Si tu vois ce message, c'est que la variable est bien détectée !
 */
add_action('wp', function () {
    if (get_query_var('pc_app_dashboard')) {
        // Décommenter la ligne ci-dessous SI tu veux vérifier que WP détecte bien l'URL
        // wp_die("<h1>DEBUG 1 :</h1> <p>WordPress a bien détecté la variable 'pc_app_dashboard' !</p>");
    }
});

/**
 * 4. Interception du Template (PRIORITÉ 99)
 */
add_filter('template_include', function ($template) {

    // On vérifie si on est sur le dashboard
    if (get_query_var('pc_app_dashboard')) {

        $new_template = PC_RES_CORE_PATH . 'templates/app-shell.php';

        // --- TEST CRITIQUE : Est-ce que le fichier existe ? ---
        if (!file_exists($new_template)) {
            wp_die("<h1>ERREUR FICHIER</h1><p>Le routeur veut charger le dashboard, mais ne trouve pas le fichier !</p><p>Chemin cherché : <code>" . $new_template . "</code></p><p>Vérifie que le fichier <strong>app-shell.php</strong> est bien dans le dossier <strong>templates</strong> de ton plugin.</p>");
        }

        return $new_template;
    }

    return $template;
}, 99); // <--- LE 99 EST CRUCIAL POUR PASSER APRÈS LE THÈME

/**
 * 5. Injection des Scripts
 */
add_action('wp_enqueue_scripts', function () {
    // On ne fait rien si ce n'est pas notre page Dashboard
    if (!get_query_var('pc_app_dashboard')) {
        return;
    }

    // 🛡️ NETTOYAGE : On retire Elementor et autres scripts parasites qui causent des erreurs
    wp_dequeue_script('elementor-frontend');
    wp_dequeue_script('elementor-pro-frontend');
    wp_dequeue_style('elementor-frontend');
    wp_dequeue_style('elementor-pro-frontend');
    // Si tu as d'autres plugins qui injectent du JS (ex: Pixel, Chatbot...), retire-les ici aussi

    // A. Chargement des assets CALENDRIER
    if (function_exists('pc_dashboard_calendar_enqueue_assets')) {
        pc_dashboard_calendar_enqueue_assets();
    }

    // B. Chargement des assets DASHBOARD
    if (function_exists('pc_resa_dashboard_shortcode')) {
        ob_start();
        pc_resa_dashboard_shortcode([]);
        ob_end_clean();
    }
}, 100);

/**
 * 6. Ajout au menu
 */
add_filter('wp_nav_menu_items', function ($items, $args) {
    if (!function_exists('get_field')) return $items;

    if (get_field('pc_dashboard_menu_item', 'option') && isset($args->theme_location) && $args->theme_location == 'primary') {
        $slug = get_field('pc_dashboard_slug', 'option') ?: 'espace-proprietaire';
        $url = home_url('/' . $slug);
        $label = is_user_logged_in() ? 'Mon Espace' : 'Espace Propriétaire';
        $items .= '<li class="menu-item pc-app-link"><a href="' . esc_url($url) . '">' . esc_html($label) . '</a></li>';
    }
    return $items;
}, 10, 2);
