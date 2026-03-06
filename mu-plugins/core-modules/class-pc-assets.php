<?php

/**
 * Fichier : mu-plugins/core-modules/class-pc-assets.php
 * Gestionnaire global des assets de base du site.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Assets_Manager
{
    public static function init()
    {
        // 1. Fondations CSS globales (Chargé en priorité 5)
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_base_css'], 5);

        // 2. Autoriser les uploads de SVG dans les médias
        add_filter('upload_mimes', [__CLASS__, 'allow_svg_uploads']);
    }

    public static function enqueue_base_css()
    {
        // Chargement du fichier CSS fondamental de l'architecture
        $base_css_path = WP_CONTENT_DIR . '/mu-plugins/pc-base.css';

        if (file_exists($base_css_path)) {
            wp_enqueue_style('pc-base', content_url('mu-plugins/pc-base.css'), [], filemtime($base_css_path));
        }
    }

    public static function allow_svg_uploads($m)
    {
        $m['svg'] = 'image/svg+xml';
        return $m;
    }
}
