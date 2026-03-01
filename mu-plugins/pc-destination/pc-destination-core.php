<?php

/**
 * Point d'entrée principal du système Fiche Destination.
 * Architecture modulaire orientée objet.
 *
 * @package PC_Destination
 */

if (!defined('ABSPATH')) {
    exit; // Sécurité
}

class PC_Destination_Core
{
    /**
     * Instance unique de la classe (Singleton).
     * @var PC_Destination_Core
     */
    private static $instance = null;

    /**
     * Retourne l'instance unique.
     * @return PC_Destination_Core
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
    }

    /**
     * Définit les constantes du module.
     */
    private function define_constants()
    {
        define('PC_DEST_VERSION', '2.0.0');
        define('PC_DEST_DIR', trailingslashit(WP_CONTENT_DIR . '/mu-plugins/pc-destination'));
        define('PC_DEST_URL', trailingslashit(content_url('mu-plugins/pc-destination')));
    }

    /**
     * Charge tous les fichiers requis.
     */
    private function load_dependencies()
    {
        // 1. Chargement des nouveaux modules (Architecture cible)
        require_once PC_DEST_DIR . 'assets/class-pc-destination-asset-manager.php';
        require_once PC_DEST_DIR . 'schema/class-pc-destination-schema-manager.php';
        require_once PC_DEST_DIR . 'shortcodes/class-pc-destination-recommendations-shortcode.php';
        require_once PC_DEST_DIR . 'shortcodes/class-pc-destination-infos-shortcode.php';
        require_once PC_DEST_DIR . 'helpers/class-pc-destination-query-helper.php';
        require_once PC_DEST_DIR . 'helpers/class-pc-destination-render-helper.php';
        require_once PC_DEST_DIR . 'shortcodes/class-pc-destination-logements-shortcode.php';
        require_once PC_DEST_DIR . 'shortcodes/class-pc-destination-experiences-shortcode.php';
        require_once PC_DEST_DIR . 'shortcodes/class-pc-destination-hub-shortcode.php';
    }

    /**
     * Instancie les classes pour enregistrer les hooks.
     */
    private function init_classes()
    {
        // Initialisation du gestionnaire d'Assets
        $asset_manager = new PC_Destination_Asset_Manager();
        $asset_manager->register();

        // Initialisation du Schema Manager
        $schema_manager = new PC_Destination_Schema_Manager();
        $schema_manager->register();

        $reco_shortcode = new PC_Destination_Recommendations_Shortcode();
        $reco_shortcode->register();

        $infos_shortcode = new PC_Destination_Infos_Shortcode();
        $infos_shortcode->register();

        $logements_shortcode = new PC_Destination_Logements_Shortcode();
        $logements_shortcode->register();

        $experiences_shortcode = new PC_Destination_Experiences_Shortcode();
        $experiences_shortcode->register();

        $hub_shortcode = new PC_Destination_Hub_Shortcode();
        $hub_shortcode->register();
    }
}

// Lancement du système Fiche Destination
PC_Destination_Core::get_instance();
