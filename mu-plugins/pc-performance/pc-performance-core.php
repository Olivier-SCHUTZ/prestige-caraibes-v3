<?php

/**
 * PC Performance Core
 * Pattern: Singleton & Autoloader Dynamique
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Performance_Core
{
    private static $instance = null;

    // Instances publiques accessibles par les autres classes du module
    public $lcp_manager;
    public $font_manager;
    public $preconnect_manager;
    public $preload_manager;

    private function __construct()
    {
        $this->register_autoloader();
        // On initialise sur le hook 'wp' pour s'assurer que les conditionnels (is_singular, is_page) fonctionnent
        add_action('wp', [$this, 'init_modules'], 5);
    }

    public static function get_instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function register_autoloader()
    {
        spl_autoload_register([$this, 'autoload']);
    }

    private function autoload($class_name)
    {
        if (
            strpos($class_name, 'PC_Performance_') !== 0 &&
            strpos($class_name, 'PC_Preload_') !== 0 &&
            strpos($class_name, 'PC_Preconnect_') !== 0 &&
            strpos($class_name, 'PC_LCP_') !== 0 &&
            strpos($class_name, 'PC_Font_') !== 0 &&
            strpos($class_name, 'PC_Url_') !== 0 &&
            strpos($class_name, 'PC_Context_') !== 0 &&
            strpos($class_name, 'PC_Resource_') !== 0
        ) {
            return;
        }

        $directories = [
            '/config/',
            '/helpers/',
            '/managers/'
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
     * Initialisation ordonnée des Managers
     */
    public function init_modules()
    {
        // 1. Les gestionnaires de données (doivent démarrer en premier)
        if (class_exists('PC_LCP_Manager')) {
            $this->lcp_manager = new PC_LCP_Manager();
        }
        if (class_exists('PC_Font_Manager')) {
            $this->font_manager = new PC_Font_Manager();
        }

        // 2. Les émetteurs (ont besoin des données générées au-dessus)
        if (class_exists('PC_Preconnect_Manager')) {
            $this->preconnect_manager = new PC_Preconnect_Manager();
        }
        if (class_exists('PC_Preload_Manager')) {
            $this->preload_manager = new PC_Preload_Manager();
        }
    }
}

// ==========================================
// ALLUMAGE DU MODULE
// ==========================================
// Cette ligne réactive tes optimisations de performance !
PC_Performance_Core::get_instance();
