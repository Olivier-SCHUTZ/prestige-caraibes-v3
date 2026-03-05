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
        // Injection du CSS critique pour éviter le flash d'icônes
        add_action('wp_head', [$this, 'inject_critical_css'], 1);
    }

    /**
     * Charge les scripts JS et le CSS principal.
     * Cette méthode est appelée dynamiquement depuis le shortcode.
     */
    public static function enqueue_assets()
    {
        static $done = false;
        if ($done || is_admin()) return;
        $done = true;

        // 1. Définition des chemins
        $css_path          = PC_HEADER_PATH . 'assets/css/header-main.css';
        $dropdown_css_path = PC_HEADER_PATH . 'assets/css/components/header-dropdown.css'; // NOUVEAU
        $nav_js_path       = PC_HEADER_PATH . 'assets/js/components/header-navigation.js';
        $search_js_path    = PC_HEADER_PATH . 'assets/js/components/header-search.js';
        $offcanvas_js_path = PC_HEADER_PATH . 'assets/js/components/header-offcanvas.js';
        $smart_js_path     = PC_HEADER_PATH . 'assets/js/components/header-smart.js';
        $dropdown_js_path  = PC_HEADER_PATH . 'assets/js/components/header-dropdown.js'; // NOUVEAU
        $main_js_path      = PC_HEADER_PATH . 'assets/js/header-main.js';

        // 2. URLs correspondantes
        $css_url          = PC_HEADER_URL . 'assets/css/header-main.css';
        $dropdown_css_url = PC_HEADER_URL . 'assets/css/components/header-dropdown.css'; // NOUVEAU
        $nav_js_url       = PC_HEADER_URL . 'assets/js/components/header-navigation.js';
        $search_js_url    = PC_HEADER_URL . 'assets/js/components/header-search.js';
        $offcanvas_js_url = PC_HEADER_URL . 'assets/js/components/header-offcanvas.js';
        $smart_js_url     = PC_HEADER_URL . 'assets/js/components/header-smart.js';
        $dropdown_js_url  = PC_HEADER_URL . 'assets/js/components/header-dropdown.js'; // NOUVEAU
        $main_js_url      = PC_HEADER_URL . 'assets/js/header-main.js';

        // 3. Versions (cache busting)
        $css_ver          = file_exists($css_path) ? filemtime($css_path) : PC_HEADER_VERSION;
        $dropdown_css_ver = file_exists($dropdown_css_path) ? filemtime($dropdown_css_path) : PC_HEADER_VERSION; // NOUVEAU
        $nav_js_ver       = file_exists($nav_js_path) ? filemtime($nav_js_path) : PC_HEADER_VERSION;
        $search_js_ver    = file_exists($search_js_path) ? filemtime($search_js_path) : PC_HEADER_VERSION;
        $offcanvas_js_ver = file_exists($offcanvas_js_path) ? filemtime($offcanvas_js_path) : PC_HEADER_VERSION;
        $smart_js_ver     = file_exists($smart_js_path) ? filemtime($smart_js_path) : PC_HEADER_VERSION;
        $dropdown_js_ver  = file_exists($dropdown_js_path) ? filemtime($dropdown_js_path) : PC_HEADER_VERSION; // NOUVEAU
        $main_js_ver      = file_exists($main_js_path) ? filemtime($main_js_path) : PC_HEADER_VERSION;

        // --- ENQUEUE CSS ---
        $style_deps = [];
        if (wp_style_is('pc-base', 'enqueued')) $style_deps[] = 'pc-base';
        if (wp_style_is('pc-header', 'enqueued')) $style_deps[] = 'pc-header';

        if (file_exists($css_path)) {
            wp_enqueue_style('pc-header-main', $css_url, $style_deps, $css_ver);
        }

        // NOUVEAU : Chargement du style du Dropdown
        if (file_exists($dropdown_css_path)) {
            wp_enqueue_style('pc-header-dropdown', $dropdown_css_url, [], $dropdown_css_ver);
        }

        // --- ENQUEUE JS MODULAIRE ---
        if (file_exists($nav_js_path)) {
            wp_enqueue_script('pc-header-nav-js', $nav_js_url, [], $nav_js_ver, true);
        }

        if (file_exists($search_js_path)) {
            wp_enqueue_script('pc-header-search-js', $search_js_url, [], $search_js_ver, true);
        }

        if (file_exists($offcanvas_js_path)) {
            wp_enqueue_script('pc-header-offcanvas-js', $offcanvas_js_url, [], $offcanvas_js_ver, true);
        }

        if (file_exists($smart_js_path)) {
            wp_enqueue_script('pc-header-smart-js', $smart_js_url, [], $smart_js_ver, true);
        }

        // NOUVEAU : Chargement du JS du Dropdown
        if (file_exists($dropdown_js_path)) {
            wp_enqueue_script('pc-header-dropdown-js', $dropdown_js_url, [], $dropdown_js_ver, true);
        }

        if (file_exists($main_js_path)) {
            // Ajout du dropdown JS dans les dépendances de main-js
            $js_deps = ['pc-header-nav-js', 'pc-header-search-js', 'pc-header-offcanvas-js', 'pc-header-smart-js', 'pc-header-dropdown-js'];
            wp_enqueue_script('pc-header-main-js', $main_js_url, $js_deps, $main_js_ver, true);
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
     * Injection de CSS critique pour éviter le flash d'icônes et de mise en page.
     */
    public function inject_critical_css()
    {
?>
        <style id="pc-critical-header">
            /* On cache le header par défaut pour éviter de voir les icônes géantes ou mal placées */
            .pc-hg {
                opacity: 0;
                visibility: hidden;
            }

            /* On l'affiche dès que le JS dit qu'il est prêt */
            .pc-hg.pc-hg-ready {
                opacity: 1;
                visibility: visible;
                transition: opacity 0.3s ease;
            }

            /* Tailles de secours immédiates pour les éléments sensibles */
            .pc-hg__social-ico svg {
                width: 18px !important;
                height: 18px !important;
                display: block;
            }

            .pc-hg__logo img {
                height: 44px !important;
                width: auto !important;
            }

            .pc-hg__bar {
                min-height: 57px;
            }

            .pc-hg__main {
                min-height: 72px;
            }
        </style>
<?php
    }
}
