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
        // Chemins dynamiques pour les MU-Plugins
        $css_path = WPMU_PLUGIN_DIR . '/pc-ui-components/assets/css/components/pc-card.css';
        $css_url  = WPMU_PLUGIN_URL . '/pc-ui-components/assets/css/components/pc-card.css';

        // Cache busting intelligent basé sur la date de modification du fichier
        $version = file_exists($css_path) ? filemtime($css_path) : '2.5.0';

        // Chargement du style des cartes
        wp_enqueue_style(
            'pc-ui-card-style',
            $css_url,
            [],
            $version
        );
    }
}
