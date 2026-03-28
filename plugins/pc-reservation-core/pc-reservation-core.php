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

// 🚨 CHARGEMENT OBLIGATOIRE DE COMPOSER (Stripe, DomPDF, etc.)
if (file_exists(PC_RES_CORE_PATH . 'vendor/autoload.php')) {
    require_once PC_RES_CORE_PATH . 'vendor/autoload.php';
}

// Chargement des fichiers internes
require_once PC_RES_CORE_PATH . 'db/schema.php';
require_once PC_RES_CORE_PATH . 'includes/class-reservation.php';
require_once PC_RES_CORE_PATH . 'includes/class-payment.php';
require_once PC_RES_CORE_PATH . 'includes/class-booking-engine.php';
require_once PC_RES_CORE_PATH . 'includes/services/booking/class-booking-payload-normalizer.php';
require_once PC_RES_CORE_PATH . 'includes/services/booking/class-booking-pricing-calculator.php';
require_once PC_RES_CORE_PATH . 'includes/services/booking/class-booking-orchestrator.php';
require_once PC_RES_CORE_PATH . 'includes/class-housing-manager.php';
require_once PC_RES_CORE_PATH . 'includes/services/housing/class-housing-config.php';
require_once PC_RES_CORE_PATH . 'includes/services/housing/class-housing-formatter.php';
require_once PC_RES_CORE_PATH . 'includes/services/housing/class-housing-repository.php';
require_once PC_RES_CORE_PATH . 'includes/services/housing/class-housing-service.php';
require_once PC_RES_CORE_PATH . 'includes/services/housing/class-housing-pricing-calculator.php';
// require_once PC_RES_CORE_PATH . 'includes/class-rate-manager.php';
require_once PC_RES_CORE_PATH . 'includes/acf-fields.php';
// ✨ NOUVEAU : Système de champs natifs (Refonte ACF)
require_once PC_RES_CORE_PATH . 'includes/fields/class-field-manager.php';
require_once PC_RES_CORE_PATH . 'includes/fields/class-fields.php';
// Chargement des définitions de champs
require_once PC_RES_CORE_PATH . 'includes/fields/definitions/housing-fields.php';
require_once PC_RES_CORE_PATH . 'includes/fields/definitions/experience-fields.php';
// require_once PC_RES_CORE_PATH . 'shortcodes/shortcode-housing.php';

// Nouveaux Contrôleurs AJAX (Refactoring v2)
require_once PC_RES_CORE_PATH . 'includes/ajax/controllers/class-base-ajax-controller.php';
require_once PC_RES_CORE_PATH . 'includes/ajax/controllers/class-ajax-router.php';
require_once PC_RES_CORE_PATH . 'includes/ajax/controllers/class-calendar-ajax-controller.php';
require_once PC_RES_CORE_PATH . 'includes/ajax/controllers/class-reservation-ajax-controller.php';
require_once PC_RES_CORE_PATH . 'includes/ajax/controllers/class-messaging-ajax-controller.php';
require_once PC_RES_CORE_PATH . 'includes/ajax/controllers/class-document-ajax-controller.php';
require_once PC_RES_CORE_PATH . 'includes/ajax/controllers/class-housing-ajax-controller.php';
require_once PC_RES_CORE_PATH . 'includes/ajax/controllers/class-experience-ajax-controller.php';
require_once PC_RES_CORE_PATH . 'includes/ajax/controllers/class-field-ajax-controller.php';
// ✨ NOUVEAU : API Controller pour l'interface Vue.js (Dashboard)
require_once PC_RES_CORE_PATH . 'includes/ajax/controllers/class-dashboard-api-controller.php';
require_once PC_RES_CORE_PATH . 'includes/ajax/controllers/class-experience-bridge-controller.php';

// Nouveaux Services Métier (Refactoring v2)
require_once PC_RES_CORE_PATH . 'includes/services/reservation/class-reservation-repository.php';
require_once PC_RES_CORE_PATH . 'includes/services/reservation/class-reservation-validator.php';
require_once PC_RES_CORE_PATH . 'includes/services/reservation/class-reservation-service.php';
require_once PC_RES_CORE_PATH . 'includes/services/payment/class-payment-repository.php';
require_once PC_RES_CORE_PATH . 'includes/services/payment/class-payment-service.php';
require_once PC_RES_CORE_PATH . 'includes/services/messaging/class-messaging-repository.php';
require_once PC_RES_CORE_PATH . 'includes/services/messaging/class-notification-dispatcher.php';
require_once PC_RES_CORE_PATH . 'includes/services/messaging/class-template-manager.php';
require_once PC_RES_CORE_PATH . 'includes/services/messaging/class-messaging-service.php';

// ✨ NOUVEAU : Chargement du Vite Loader pour l'architecture V2 Vue.js
require_once PC_RES_CORE_PATH . 'includes/class-vite-loader.php';

// ============================================================
// 🎯 MODULE EXPERIENCE : Chargement des classes PHP
// ============================================================
require_once PC_RES_CORE_PATH . 'includes/class-experience-manager.php';
require_once PC_RES_CORE_PATH . 'includes/services/experience/class-experience-config.php';
require_once PC_RES_CORE_PATH . 'includes/services/experience/class-experience-formatter.php';
require_once PC_RES_CORE_PATH . 'includes/services/experience/class-experience-repository.php';
require_once PC_RES_CORE_PATH . 'includes/services/experience/class-experience-service.php';
// require_once PC_RES_CORE_PATH . 'shortcodes/shortcode-experience.php';

require_once PC_RES_CORE_PATH . 'includes/class-ical-export.php';
require_once PC_RES_CORE_PATH . 'includes/services/calendar/class-ical-exporter.php';
require_once PC_RES_CORE_PATH . 'includes/class-settings.php';
require_once PC_RES_CORE_PATH . 'includes/services/settings/class-settings-config.php';
require_once PC_RES_CORE_PATH . 'includes/services/settings/class-webhook-simulator.php';
require_once PC_RES_CORE_PATH . 'includes/services/settings/class-settings-controller.php';
require_once PC_RES_CORE_PATH . 'includes/gateways/class-stripe-manager.php';
require_once PC_RES_CORE_PATH . 'includes/gateways/class-stripe-ajax.php';
require_once PC_RES_CORE_PATH . 'includes/gateways/class-stripe-webhook.php';
require_once PC_RES_CORE_PATH . 'includes/class-documents.php';
require_once PC_RES_CORE_PATH . 'includes/services/document/class-document-repository.php';
require_once PC_RES_CORE_PATH . 'includes/services/document/class-document-financial-calculator.php';
require_once PC_RES_CORE_PATH . 'includes/services/document/renderers/class-base-renderer.php';
require_once PC_RES_CORE_PATH . 'includes/services/document/renderers/class-invoice-renderer.php';
require_once PC_RES_CORE_PATH . 'includes/services/document/renderers/class-deposit-renderer.php';
require_once PC_RES_CORE_PATH . 'includes/services/document/renderers/class-contract-renderer.php';
require_once PC_RES_CORE_PATH . 'includes/services/document/renderers/class-voucher-renderer.php';
require_once PC_RES_CORE_PATH . 'includes/services/document/renderers/class-custom-renderer.php';
require_once PC_RES_CORE_PATH . 'includes/services/document/class-document-service.php';
require_once PC_RES_CORE_PATH . 'includes/api/class-rest-webhook.php';

require_once PC_RES_CORE_PATH . 'includes/migration-logements.php';
require_once PC_RES_CORE_PATH . 'includes/migration-experience.php';
require_once PC_RES_CORE_PATH . 'includes/class-elementor-pcr-tags.php';
// controller-forms sera branché plus tard quand tu seras prêt
if (file_exists(PC_RES_CORE_PATH . 'includes/controller-forms.php')) {
    require_once PC_RES_CORE_PATH . 'includes/controller-forms.php';
}

// 🧹 SCRIPT DE NETTOYAGE POST-REFACTORING (désactivé pour éviter erreur fatale)
// if (is_admin() && file_exists(PC_RES_CORE_PATH . 'cleanup-legacy-script.php')) {
//     require_once PC_RES_CORE_PATH . 'cleanup-legacy-script.php';
// }

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

    // Initialisation du Field Manager (Nouveau système natif)
    if (class_exists('PCR_Field_Manager')) {
        PCR_Field_Manager::init();
    }

    // Initialisation du contrôleur de formulaires (si présent)
    if (class_exists('PCR_FormController')) {
        PCR_FormController::init();
    }

    // Initialisation du nouveau Routeur AJAX (Refactoring v2)
    if (class_exists('PCR_Ajax_Router')) {
        PCR_Ajax_Router::get_instance()->init();
    }

    // Initialisation du Housing Manager
    if (class_exists('PCR_Housing_Manager')) {
        PCR_Housing_Manager::init();
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
        PCR_Messaging::init(); // L'ancienne classe gère encore les hooks temporairement
    }
    if (class_exists('PCR_Template_Manager')) {
        PCR_Template_Manager::get_instance()->init_hooks(); // Le nouveau Manager est branché !
    }

    // ✨ NOUVEAU : Initialisation des Webhooks REST API
    if (class_exists('PCR_Rest_Webhook')) {
        PCR_Rest_Webhook::init();
    }

    // 🎯 NOUVEAU : Initialisation des champs ACF (onglet Promotions)
    if (class_exists('PCR_ACF_Fields')) {
        PCR_ACF_Fields::init();
    }

    // ============================================================
    // 🎯 MODULE EXPERIENCE : Initialisation du contrôleur
    // ============================================================
    if (class_exists('PCR_Experience_Manager')) {
        PCR_Experience_Manager::init();
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

// ============================================================
// 🎯 MODULE EXPERIENCE : Enregistrement du shortcode
// ============================================================
add_shortcode('pc_experience_dashboard', 'pc_shortcode_experience_dashboard');

// ============================================================
// 🎯 MODULE EXPERIENCE : Assets CSS/JS avec dépendances
// ============================================================
function pcr_enqueue_experience_assets()
{
    // Éviter les enqueues multiples
    static $assets_loaded = false;
    if ($assets_loaded) {
        return;
    }
    $assets_loaded = true;

    /*
    // On déconnecte le CSS
    wp_enqueue_style(
        'pcr-experience-dashboard-css',
        PC_RES_CORE_URL . 'assets/css/dashboard-experience.css',
        array(),
        PC_RES_CORE_VERSION
    );
    */

    /*
    // On déconnecte le JS
    wp_enqueue_script(
        'pcr-exp-core',
        PC_RES_CORE_URL . 'assets/js/dashboard-experience.js',
        array('jquery'),
        PC_RES_CORE_VERSION,
        true
    );
    */

    // ✨ NOUVEAU : Le "pont" pour envoyer les variables à Vue 3 sans charger l'ancien JS
    wp_register_script('pc-vue-bridge', false); // On crée un faux script vide
    wp_enqueue_script('pc-vue-bridge'); // On dit à WP de le charger

    // On attache tes variables cruciales à ce faux script !
    wp_localize_script('pc-vue-bridge', 'pcReservationVars', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pc_resa_manual_create')
    ));


    wp_localize_script('pcr-exp-core', 'pcReservationVars', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pc_resa_manual_create')
    ));

    // Localize Script pour AJAX (CRITIQUE)
    wp_localize_script('pcr-exp-core', 'pcReservationVars', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pc_resa_manual_create')
    ));
}

// Hook pour charger automatiquement les assets si le shortcode est détecté
add_action('wp_enqueue_scripts', function () {
    // Vérifier si on est dans le dashboard ou si la page contient le shortcode
    global $post;

    if (
        get_query_var('pc_app_dashboard') ||
        (is_object($post) && has_shortcode($post->post_content, 'pc_dashboard_experience'))
    ) {
        pcr_enqueue_experience_assets();
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
 * 5. Injection des Scripts (Architecture 100% Vue V2)
 */
add_action('wp_enqueue_scripts', function () {
    // On ne fait rien si ce n'est pas notre page Dashboard
    if (!get_query_var('pc_app_dashboard')) {
        return;
    }

    // 🛡️ NETTOYAGE : On retire Elementor et autres scripts parasites
    wp_dequeue_script('elementor-frontend');
    wp_dequeue_script('elementor-pro-frontend');
    wp_dequeue_style('elementor-frontend');
    wp_dequeue_style('elementor-pro-frontend');

    if (class_exists('PCR_Vite_Loader')) {

        // 1. Création du pont global UNIQUE pour transmettre les variables PHP à Vue
        wp_register_script('pc-vue-global-bridge', false);
        wp_enqueue_script('pc-vue-global-bridge');

        // A. Variables pour Dashboard, Housing et Experience (api-client.js)
        wp_localize_script('pc-vue-global-bridge', 'pcReservationVars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            // Le nonce principal attendu par tes contrôleurs AJAX (ex: pc_resa_manual_create)
            'nonce'    => wp_create_nonce('pc_resa_manual_create'),
            'security' => wp_create_nonce('pc_resa_manual_create'),
        ));

        // B. Variables spécifiques pour le Calendrier (Rétrocompatibilité)
        wp_localize_script('pc-vue-global-bridge', 'pcCalendarData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('pc_dashboard_calendar'),
        ));

        // 🚀 LA PIÈCE MANQUANTE 1 : CHARGEMENT DE LA LIBRAIRIE FLATPICKR
        wp_enqueue_style('pc-flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], '4.6.13');
        wp_enqueue_script('pc-flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js', [], '4.6.13', false);
        wp_enqueue_script('pc-flatpickr-fr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js', ['pc-flatpickr'], '4.6.13', false);

        // 3. Chargement des modules Vue V2
        PCR_Vite_Loader::enqueue_entry('src/modules/dashboard/main.js');
        PCR_Vite_Loader::enqueue_entry('src/modules/calendar/main.js');
        PCR_Vite_Loader::enqueue_entry('src/modules/experience/main.js');
        PCR_Vite_Loader::enqueue_entry('src/modules/housing/main.js');
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
