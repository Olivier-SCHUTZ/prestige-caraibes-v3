<?php

/**
 * Plugin Name: Prestige Caraïbes - FAQ Module
 * Description: Gestionnaire modulaire des FAQ (Logements, Expériences, Destinations).
 * Version: 2.5.0
 * Author: Prestige Caraïbes
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_FAQ_Core
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
        define('PC_FAQ_VERSION', '2.5.0');
        define('PC_FAQ_PATH', trailingslashit(__DIR__));

        // Calcul de l'URL dynamique pour les mu-plugins
        $mu_plugin_dir = basename(WPMU_PLUGIN_DIR);
        define('PC_FAQ_URL', trailingslashit(content_url($mu_plugin_dir . '/pc-faq')));
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
     * Ex: PC_Logement_FAQ_Shortcode -> class-pc-logement-faq-shortcode.php
     */
    public function autoloader($class)
    {
        // On gère toutes les classes de notre écosystème PC_
        if (strpos($class, 'PC_') !== 0) {
            return;
        }

        // Formatage du nom de fichier (Standard WordPress)
        // Transforme "PC_Logement_FAQ_Shortcode" en "class-pc-logement-faq-shortcode.php"
        $file = str_replace(['PC_', '_'], ['', '-'], $class);
        $file_name = 'class-pc-' . strtolower($file) . '.php';

        // Dossiers à parcourir par ordre de probabilité
        $paths = [
            PC_FAQ_PATH . 'assets/',
            PC_FAQ_PATH . 'shortcodes/',
            PC_FAQ_PATH . 'helpers/',
        ];

        foreach ($paths as $path) {
            if (file_exists($path . $file_name)) {
                require_once $path . $file_name;
                return;
            }
        }
    }

    /**
     * Instanciation des sous-modules
     */
    public function init_system()
    {
        // 1. Chargement des Assets (CSS/JS)
        if (class_exists('PC_FAQ_Asset_Manager')) {
            (new PC_FAQ_Asset_Manager())->register();
        }

        // 2. Chargement des Shortcodes
        if (class_exists('PC_Logement_FAQ_Shortcode')) {
            (new PC_Logement_FAQ_Shortcode())->register();
        }

        if (class_exists('PC_Experience_FAQ_Shortcode')) {
            (new PC_Experience_FAQ_Shortcode())->register();
        }

        if (class_exists('PC_Destination_FAQ_Shortcode')) {
            (new PC_Destination_FAQ_Shortcode())->register();
        }

        if (class_exists('PC_FAQ_Render_Shortcode')) {
            (new PC_FAQ_Render_Shortcode())->register();
        }
    }
}

// Lancement du cœur
PC_FAQ_Core::get_instance();
