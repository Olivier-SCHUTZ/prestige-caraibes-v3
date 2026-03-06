<?php

/**
 * PC Cache Core
 * Pattern: Singleton & Autoloader
 * Orchestrateur ultra-sécurisé pour la synchronisation iCal et caches API
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Cache_Core
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
     * Enregistre l'autoloader dynamique
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
        if (strpos($class_name, 'PC_Cache_') !== 0 && strpos($class_name, 'PC_Ical_') !== 0) {
            return;
        }

        $directories = [
            '/providers/',
            '/handlers/',
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
     * Instanciation des gestionnaires et planificateurs
     */
    private function init_modules()
    {
        // Initialisation du système de planification (CRON)
        if (class_exists('PC_Cache_Scheduler')) {
            new PC_Cache_Scheduler();
        }
    }
}
