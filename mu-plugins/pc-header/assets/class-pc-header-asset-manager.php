<?php

/**
 * Gestionnaire des Assets (CSS / JS) pour le Header
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Header_Asset_Manager
{
    /**
     * Enregistre les hooks d'assets
     */
    public function register()
    {
        // On utilise désormais le hook standard global (plus de wp_head)
        add_action('wp_enqueue_scripts', [$this, 'enqueue_global_assets'], 20);
    }

    /**
     * Charge les scripts JS et le CSS principal.
     */
    public function enqueue_global_assets()
    {
        // Le Header est présent sur toutes les pages, aucune condition d'exclusion requise.

        // =========================================================================
        // 1. COMPOSANTS CSS
        // =========================================================================

        $css_files = [
            'pc-header-critical' => 'components/pc-header-critical.css', // Notre ancien CSS inline
            'pc-header-main'     => 'header-main.css',
            'pc-header-dropdown' => 'components/header-dropdown.css'
        ];

        foreach ($css_files as $handle => $filename) {
            $css_path = PC_HEADER_PATH . 'assets/css/' . $filename;
            if (file_exists($css_path)) {
                wp_enqueue_style($handle, PC_HEADER_URL . 'assets/css/' . $filename, [], filemtime($css_path));
            }
        }

        // =========================================================================
        // 2. COMPOSANTS JS
        // =========================================================================

        $js_files = [
            'pc-header-nav-js'       => 'components/header-navigation.js',
            'pc-header-search-js'    => 'components/header-search.js',
            'pc-header-offcanvas-js' => 'components/header-offcanvas.js',
            'pc-header-smart-js'     => 'components/header-smart.js',
            'pc-header-dropdown-js'  => 'components/header-dropdown.js'
        ];

        $main_js_deps = [];

        foreach ($js_files as $handle => $filename) {
            $js_path = PC_HEADER_PATH . 'assets/js/' . $filename;
            if (file_exists($js_path)) {
                wp_enqueue_script($handle, PC_HEADER_URL . 'assets/js/' . $filename, [], filemtime($js_path), true);
                $main_js_deps[] = $handle; // On collecte les handles pour les dépendances du main.js
            }
        }

        // =========================================================================
        // 3. JS PRINCIPAL ET CONFIGURATION
        // =========================================================================

        $main_js_path = PC_HEADER_PATH . 'assets/js/header-main.js';

        if (file_exists($main_js_path)) {
            // Le main.js attend que tous les composants soient chargés
            wp_enqueue_script('pc-header-main-js', PC_HEADER_URL . 'assets/js/header-main.js', $main_js_deps, filemtime($main_js_path), true);
            wp_script_add_data('pc-header-main-js', 'defer', true);

            // Récupération de la configuration
            $cfg = class_exists('PC_Header_Config') ? PC_Header_Config::get() : [];

            wp_localize_script('pc-header-main-js', 'PCHeaderGlobal', [
                'bpDesktop'      => 1025,
                'restUrl'        => esc_url_raw(rest_url('pc/v1/search-suggest')),
                'minChars'       => (int)($cfg['search_min_chars'] ?? 2),
                'maxResults'     => (int)($cfg['search_max_results'] ?? 8),
            ]);
        }
    }

    /**
     * Rétrocompatibilité : Méthode laissée vide intentionnellement.
     * Si l'ancien shortcode du header l'appelle manuellement, cela ne créera pas d'erreur fatale.
     */
    public static function enqueue_assets() {}
}
