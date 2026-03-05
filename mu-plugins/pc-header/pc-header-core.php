<?php

/**
 * Plugin Name: Prestige Caraïbes - Header Core
 * Description: Cœur du système de Header et Méga-menu (Architecture Modulaire).
 * Version: 2.0.0
 * Author: Prestige Caraïbes
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Header_Core
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

        // Initialisation du système au moment opportun
        add_action('plugins_loaded', [$this, 'init_system']);
    }

    /**
     * Définition des constantes globales
     */
    private function define_constants()
    {
        define('PC_HEADER_VERSION', '2.0.0');
        define('PC_HEADER_PATH', trailingslashit(__DIR__));

        // Calcul de l'URL dynamique (sécurisé pour les mu-plugins)
        $mu_plugin_dir = basename(WPMU_PLUGIN_DIR);
        define('PC_HEADER_URL', trailingslashit(content_url($mu_plugin_dir . '/pc-header')));
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
     */
    public function autoloader($class)
    {
        // On ne gère que les classes de notre plugin
        if (strpos($class, 'PC_Header_') !== 0) {
            return;
        }

        // Formatage du nom de fichier (Standard WordPress)
        $file = str_replace(['PC_', '_'], ['', '-'], $class);
        $file = 'class-pc-' . strtolower($file) . '.php';

        // Dossiers à parcourir
        $paths = [
            PC_HEADER_PATH . 'assets/',
            PC_HEADER_PATH . 'config/',
            PC_HEADER_PATH . 'api/',
            PC_HEADER_PATH . 'helpers/',
            PC_HEADER_PATH . 'shortcodes/',
        ];

        foreach ($paths as $path) {
            if (file_exists($path . $file)) {
                require_once $path . $file;
                return;
            }
        }
    }

    /**
     * Instanciation des modules
     */
    public function init_system()
    {
        if (class_exists('PC_Header_Asset_Manager')) {
            (new PC_Header_Asset_Manager())->register();
        }
        if (class_exists('PC_Header_Search_API')) {
            (new PC_Header_Search_API())->register();
        }
        // NOUVEAU : On active le shortcode !
        if (class_exists('PC_Header_Shortcode')) {
            (new PC_Header_Shortcode())->register();
        }
        // NOUVEAU : On active le composant Dropdown des logements
        if (class_exists('PC_Header_Dropdown_Shortcode')) {
            (new PC_Header_Dropdown_Shortcode())->register();
        }
    }
}

// Lancement du cœur
PC_Header_Core::get_instance();
