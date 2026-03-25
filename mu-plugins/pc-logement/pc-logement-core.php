<?php

/**
 * Plugin Name: Prestige Caraïbes - Logement Core
 * Description: Cœur du système de réservation et d'affichage des logements (Architecture Modulaire).
 * Version: 2.0.0
 * Author: Prestige Caraïbes
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Logement_Core
{

    /**
     * Instance unique de la classe (Singleton)
     */
    private static $instance = null;

    /**
     * Récupère l'instance unique
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructeur privé
     */
    private function __construct()
    {
        $this->define_constants();
        $this->init_autoloader();
        $this->load_dependencies();

        // Initialisation du système au moment opportun
        add_action('plugins_loaded', [$this, 'init_system']);
    }

    /**
     * Définition des constantes globales
     */
    private function define_constants()
    {
        define('PC_LOGEMENT_VERSION', '2.0.0');
        define('PC_LOGEMENT_PATH', trailingslashit(__DIR__));

        // Calcul de l'URL dynamique (pratique pour les mu-plugins)
        $mu_plugin_dir = basename(WPMU_PLUGIN_DIR);
        define('PC_LOGEMENT_URL', trailingslashit(content_url($mu_plugin_dir . '/pc-logement')));
    }

    /**
     * --- NOUVELLE MÉTHODE : Charge les dépendances qui ne passent pas par l'autoloader ---
     */
    private function load_dependencies()
    {
        // COMPOSANT PARTAGÉ : Module Avis Clients
        $reviews_path = WP_CONTENT_DIR . '/mu-plugins/pc-reviews/pc-reviews.php';
        if (file_exists($reviews_path)) {
            require_once $reviews_path;
        }
    }

    /**
     * Enregistre l'autoloader
     */
    private function init_autoloader()
    {
        spl_autoload_register([$this, 'autoloader']);
    }

    /**
     * Charge automatiquement les classes PHP selon leur nom
     * Ex: PC_Shortcode_Base -> class-pc-shortcode-base.php
     */
    public function autoloader($class)
    {
        // On ne gère que les classes de notre plugin
        if (strpos($class, 'PC_') !== 0) {
            return;
        }

        // Formatage du nom de fichier (Standard WordPress)
        $file = str_replace(['PC_', '_'], ['', '-'], $class);
        $file = 'class-pc-' . strtolower($file) . '.php';

        // Dossiers à parcourir
        $paths = [
            PC_LOGEMENT_PATH . 'shortcodes/',
            PC_LOGEMENT_PATH . 'booking/',
            PC_LOGEMENT_PATH . 'assets/',
            PC_LOGEMENT_PATH . 'helpers/',
            PC_LOGEMENT_PATH . 'traits/',
        ];

        foreach ($paths as $path) {
            if (file_exists($path . $file)) {
                require_once $path . $file;
                return;
            }
        }
    }

    /**
     * Instanciation des modules (Sera peuplé au fur et à mesure)
     */
    public function init_system()
    {
        if (class_exists('PC_Gallery_Shortcode')) {
            (new PC_Gallery_Shortcode())->register();
        }
        if (class_exists('PC_Anchor_Menu_Shortcode')) {
            (new PC_Anchor_Menu_Shortcode())->register();
        }
        if (class_exists('PC_Tarifs_Shortcode')) {
            (new PC_Tarifs_Shortcode())->register();
        }
        if (class_exists('PC_Highlights_Shortcode')) {
            (new PC_Highlights_Shortcode())->register();
        }
        if (class_exists('PC_Location_Map_Shortcode')) {
            (new PC_Location_Map_Shortcode())->register();
        }
        if (class_exists('PC_Proximites_Shortcode')) {
            (new PC_Proximites_Shortcode())->register();
        }
        if (class_exists('PC_SEO_Shortcode')) {
            (new PC_SEO_Shortcode())->register();
        }
        if (class_exists('PC_Experiences_Shortcode')) {
            (new PC_Experiences_Shortcode())->register();
        }
        if (class_exists('PC_ICal_Shortcode')) {
            (new PC_ICal_Shortcode())->register();
        }
        if (class_exists('PC_Utils_Shortcodes')) {
            (new PC_Utils_Shortcodes())->register();
        }
        if (class_exists('PC_Devis_Shortcode')) {
            (new PC_Devis_Shortcode())->register();
        }
        if (class_exists('PC_Booking_Bar_Shortcode')) {
            (new PC_Booking_Bar_Shortcode())->register();
        }
        if (class_exists('PC_Booking_Router_Shortcode')) {
            (new PC_Booking_Router_Shortcode())->register();
        }
        if (class_exists('PC_Booking_Handler')) {
            (new PC_Booking_Handler())->register();
        }
        if (class_exists('PC_Equipements_Shortcode')) {
            (new PC_Equipements_Shortcode())->register();
        }
        if (class_exists('PC_Regles_Shortcode')) {
            (new PC_Regles_Shortcode())->register();
        }
        if (class_exists('PC_Politique_Shortcode')) {
            (new PC_Politique_Shortcode())->register();
        }
        if (class_exists('PC_Asset_Manager')) {
            (new PC_Asset_Manager())->register();
        }
        if (class_exists('PC_Hote_Shortcode')) {
            (new PC_Hote_Shortcode())->register();
        }
        if (class_exists('PC_Essentiels_Shortcode')) {
            (new PC_Essentiels_Shortcode())->register();
        }
    }
}

// Lancement du cœur
PC_Logement_Core::get_instance();
