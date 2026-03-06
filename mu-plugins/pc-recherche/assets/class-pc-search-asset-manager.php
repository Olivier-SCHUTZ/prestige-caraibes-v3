<?php

/**
 * Gestionnaire des Assets (CSS / JS) pour le module de recherche
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Search_Asset_Manager
{
    public function register()
    {
        // On utilise désormais le hook standard (plus de wp_head inline)
        add_action('wp_enqueue_scripts', [$this, 'enqueue_global_assets'], 20);
    }

    public function enqueue_global_assets()
    {
        // 1. Définition des besoins par page (Granularité fine)
        $needs_map = is_page(['recherche-de-logements', 'recherche-dexperiences']);

        $needs_calendar = is_page([
            'recherche-de-logements', // <-- Logements a besoin du calendrier, pas Expériences !
            'location-villa-en-guadeloupe',
            'location-villa-de-luxe-en-guadeloupe',
            'location-grande-villa-en-guadeloupe',
            'location-appartement-en-guadeloupe',
            'promotion-villa-en-guadeloupe',
            'comment-ca-marche',
            'accueil'
        ]) || is_front_page();

        // 🛡️ Condition stricte : Si on n'est sur aucune de ces pages, on coupe tout !
        if (!$needs_map && !$needs_calendar) {
            return;
        }

        // =========================================================================
        // 2. LIBRAIRIES EXTERNES
        // =========================================================================

        // Flatpickr (Calendrier) - Chargé UNIQUEMENT si la page en a besoin
        if ($needs_calendar) {
            wp_enqueue_style('flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], '4.6.13');
            wp_enqueue_script('flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js', [], '4.6.13', true);
            wp_enqueue_script('flatpickr-fr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js', ['flatpickr-js'], '4.6.13', true);
            wp_enqueue_script('flatpickr-range', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/rangePlugin.js', ['flatpickr-js'], '4.6.13', true);
        }

        // Leaflet (Carte) - Chargé UNIQUEMENT si la page a une carte
        if ($needs_map) {
            wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4');
            wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true);
        }

        // =========================================================================
        // 3. COMPOSANTS CSS
        // =========================================================================

        $components_css = [
            'pc-search-critical'   => 'pc-search-critical.css',
            'pc-search-form'       => 'pc-search-form.css',
            'pc-search-filters'    => 'pc-search-filters.css',
            'pc-search-results'    => 'pc-search-results.css',
            'pc-search-pagination' => 'pc-search-pagination.css',
            'pc-search-map'        => 'pc-search-map.css'
        ];

        foreach ($components_css as $handle => $filename) {
            $css_path = PC_RECHERCHE_PATH . 'assets/css/components/' . $filename;
            if (file_exists($css_path)) {
                wp_enqueue_style($handle, PC_RECHERCHE_URL . 'assets/css/components/' . $filename, [], filemtime($css_path));
            }
        }

        // =========================================================================
        // 4. COMPOSANTS JS
        // =========================================================================

        wp_enqueue_script('pc-search-form-js', PC_RECHERCHE_URL . 'assets/js/components/pc-search-form.js', ['jquery'], PC_RECHERCHE_VERSION, true);

        // La logique Map et Ajax dépend de la présence de la carte
        if ($needs_map) {
            wp_enqueue_script('pc-search-map-js', PC_RECHERCHE_URL . 'assets/js/components/pc-search-map.js', ['jquery', 'leaflet-js'], PC_RECHERCHE_VERSION, true);
            wp_enqueue_script('pc-search-ajax-js', PC_RECHERCHE_URL . 'assets/js/components/pc-search-ajax.js', ['jquery', 'pc-search-form-js', 'pc-search-map-js'], PC_RECHERCHE_VERSION, true);
        } else {
            // Pas de dépendance map si pas de carte
            wp_enqueue_script('pc-search-ajax-js', PC_RECHERCHE_URL . 'assets/js/components/pc-search-ajax.js', ['jquery', 'pc-search-form-js'], PC_RECHERCHE_VERSION, true);
        }

        // Sécurité : On injecte les deux objets JS (Logements & Expériences) pour éviter toute erreur JS existante
        wp_localize_script('pc-search-ajax-js', 'pc_search_params', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('pc_search_nonce')
        ]);

        wp_localize_script('pc-search-ajax-js', 'pc_exp_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('pc_experience_search_nonce')
        ]);
    }

    /**
     * Ces méthodes sont laissées vides INTENTIONNELLEMENT (Sécurité Anti-Régression).
     */
    public static function enqueue_assets() {}
    public static function enqueue_experience_assets() {}
}
