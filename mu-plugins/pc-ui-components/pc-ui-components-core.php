<?php

/**
 * PC UI Components Core
 * Pattern: Singleton & Autoloader Dynamique
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_UI_Components_Core
{
    private static $instance = null;

    private function __construct()
    {
        $this->register_autoloader();
        $this->init_modules();
    }

    public static function get_instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Enregistre l'autoloader pour ce module spécifique
     */
    private function register_autoloader()
    {
        spl_autoload_register([$this, 'autoload']);
    }

    /**
     * Autoloader dynamique basé sur le nom des classes
     */
    private function autoload($class_name)
    {
        if (strpos($class_name, 'PC_UI_') !== 0 && strpos($class_name, 'PC_Loop_') !== 0 && strpos($class_name, 'PC_Card_') !== 0 && strpos($class_name, 'PC_Rating_') !== 0) {
            return;
        }

        $directories = [
            '/assets/',
            '/shortcodes/',
            '/helpers/'
        ];

        $file_name = 'class-' . strtolower(str_replace('_', '-', $class_name)) . '.php';

        foreach ($directories as $directory) {
            $file_path = __DIR__ . $directory . $file_name;
            if (file_exists($file_path)) {
                require_once $file_path;
                return;
            }
        }
    }

    /**
     * Instanciation des sous-modules et shortcodes
     */
    private function init_modules()
    {
        // Initialise le gestionnaire d'assets s'il existe
        if (class_exists('PC_UI_Asset_Manager')) {
            PC_UI_Asset_Manager::get_instance();
        }

        // Initialise les shortcodes du module
        if (class_exists('PC_Loop_Card_Shortcode')) {
            new PC_Loop_Card_Shortcode();
        }
    }
}

// ==========================================
// ALLUMAGE DU MODULE
// ==========================================
// Cette ligne est cruciale : elle lance la classe, qui lance l'autoloader, qui déclare les shortcodes !
PC_UI_Components_Core::get_instance();
