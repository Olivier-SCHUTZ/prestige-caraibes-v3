<?php

/**
 * Plugin Name: Prestige Caraïbes - Recherche Core
 * Description: Cœur du système de recherche unifié (Architecture Modulaire).
 * Version: 1.0.0
 * Author: Prestige Caraïbes
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Recherche_Core
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
        define('PC_RECHERCHE_VERSION', '1.0.0');
        define('PC_RECHERCHE_PATH', trailingslashit(__DIR__));

        // Calcul de l'URL dynamique
        $mu_plugin_dir = basename(WPMU_PLUGIN_DIR);
        define('PC_RECHERCHE_URL', trailingslashit(content_url($mu_plugin_dir . '/pc-recherche')));
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
     * Ex: PC_Search_Asset_Manager -> class-pc-search-asset-manager.php
     */
    public function autoloader($class)
    {
        // On s'assure que c'est une classe de notre écosystème
        if (strpos($class, 'PC_') !== 0) {
            return;
        }

        // Formatage du nom de fichier (Standard WordPress)
        $file = 'class-' . strtolower(str_replace('_', '-', $class)) . '.php';

        // Dossiers à parcourir selon l'architecture cible
        $paths = [
            PC_RECHERCHE_PATH . 'assets/',
            PC_RECHERCHE_PATH . 'engines/',
            PC_RECHERCHE_PATH . 'shortcodes/',
            PC_RECHERCHE_PATH . 'components/',
            PC_RECHERCHE_PATH . 'helpers/',
            PC_RECHERCHE_PATH . 'ajax/',
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
        if (class_exists('PC_Search_Asset_Manager')) {
            (new PC_Search_Asset_Manager())->register();
        }

        if (class_exists('PC_Search_Ajax_Handler')) {
            (new PC_Search_Ajax_Handler())->register();
        }

        // On enregistre les shortcodes Logements
        if (class_exists('PC_Logement_Search_Shortcode')) {
            (new PC_Logement_Search_Shortcode())->register();
        }
        if (class_exists('PC_Simple_Search_Shortcode')) {
            (new PC_Simple_Search_Shortcode())->register();
        }

        // On enregistre le shortcode Expériences
        if (class_exists('PC_Experience_Search_Shortcode')) {
            (new PC_Experience_Search_Shortcode())->register();
        }
    }
}

// Lancement du cœur
PC_Recherche_Core::get_instance();
