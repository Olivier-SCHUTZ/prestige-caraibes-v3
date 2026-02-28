<?php

/**
 * Point d'entrée principal du système Fiche Expériences.
 * Architecture modulaire orientée objet.
 *
 * @package PC_Experiences
 */

if (!defined('ABSPATH')) {
    exit; // Sécurité
}

class PC_Experiences_Core
{

    /**
     * Instance unique de la classe (Singleton).
     * @var PC_Experiences_Core
     */
    private static $instance = null;

    /**
     * Retourne l'instance unique.
     * @return PC_Experiences_Core
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructeur privé (Sécurité Singleton).
     */
    private function __construct()
    {
        $this->define_constants();
        $this->load_dependencies();
        $this->init_classes();
        $this->init_hooks();
    }

    /**
     * Définit les constantes du module.
     */
    private function define_constants()
    {
        define('PC_EXP_VERSION', '2.0.0');
        define('PC_EXP_DIR', trailingslashit(WP_CONTENT_DIR . '/mu-plugins/pc-experiences'));
        define('PC_EXP_URL', trailingslashit(content_url('mu-plugins/pc-experiences')));
    }

    /**
     * Charge tous les fichiers requis (Helpers, Assets, Shortcodes, Handlers).
     */
    private function load_dependencies()
    {
        // Helpers & Assets
        require_once PC_EXP_DIR . 'helpers/class-pc-experience-field-helper.php';
        require_once PC_EXP_DIR . 'assets/class-pc-asset-manager-exp.php';

        // COMPOSANT PARTAGÉ : Module Avis Clients
        $reviews_path = WP_CONTENT_DIR . '/mu-plugins/pc-reviews/pc-reviews.php';
        if (file_exists($reviews_path)) {
            require_once $reviews_path;
        }

        // Base Shortcode
        require_once PC_EXP_DIR . 'shortcodes/class-pc-experience-shortcode-base.php';

        // Shortcodes
        require_once PC_EXP_DIR . 'shortcodes/class-pc-description-shortcode.php';
        require_once PC_EXP_DIR . 'shortcodes/class-pc-gallery-shortcode.php';
        require_once PC_EXP_DIR . 'shortcodes/class-pc-map-shortcode.php';
        require_once PC_EXP_DIR . 'shortcodes/class-pc-summary-shortcode.php';
        require_once PC_EXP_DIR . 'shortcodes/class-pc-pricing-shortcode.php';
        require_once PC_EXP_DIR . 'shortcodes/class-pc-inclusions-shortcode.php';
        require_once PC_EXP_DIR . 'shortcodes/class-pc-recommendations-shortcode.php';
        require_once PC_EXP_DIR . 'shortcodes/class-pc-booking-shortcode.php';

        // Booking
        require_once PC_EXP_DIR . 'booking/class-pc-experience-booking-handler.php';
    }

    /**
     * Instancie les classes pour enregistrer les shortcodes et hooks.
     */
    private function init_classes()
    {
        // Initialisation du gestionnaire d'Assets (Nouveau format)
        $asset_manager = new PC_Asset_Manager_Exp();
        $asset_manager->register();

        new PC_Experience_Description_Shortcode();
        new PC_Experience_Gallery_Shortcode();
        new PC_Experience_Map_Shortcode();
        new PC_Experience_Summary_Shortcode();
        new PC_Experience_Pricing_Shortcode();
        new PC_Experience_Inclusions_Shortcode();
        new PC_Experience_Recommendations_Shortcode();
        new PC_Experience_Booking_Shortcode();

        new PC_Experience_Booking_Handler();
    }

    /**
     * Initialise les hooks généraux de WordPress.
     */
    private function init_hooks() {}
}

// Lancement du système Fiche Expériences
PC_Experiences_Core::get_instance();
