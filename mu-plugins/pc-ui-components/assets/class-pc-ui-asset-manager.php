<?php

/**
 * PC UI Asset Manager
 * Pattern: Singleton
 * Gère le chargement conditionnel des CSS/JS du module UI
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_UI_Asset_Manager
{
    private static $instance = null;

    private function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public static function get_instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function enqueue_assets()
    {
        // 1. Définition des pages cibles exactes
        $target_pages = [
            'location-villa-en-guadeloupe',
            'location-villa-de-luxe-en-guadeloupe',
            'location-grande-villa-en-guadeloupe',
            'location-appartement-en-guadeloupe',
            'promotion-villa-en-guadeloupe',
            'accueil'
        ];

        // 🛡️ Condition stricte : Uniquement sur ces pages ou la page d'accueil native
        if (!is_page($target_pages) && !is_front_page()) {
            return;
        }

        // 2. Chargement du CSS des cartes
        $css_path = WPMU_PLUGIN_DIR . '/pc-ui-components/assets/css/components/pc-card.css';
        $css_url  = WPMU_PLUGIN_URL . '/pc-ui-components/assets/css/components/pc-card.css';

        if (file_exists($css_path)) {
            wp_enqueue_style('pc-ui-card-style', $css_url, [], filemtime($css_path));
        }
    }
}
