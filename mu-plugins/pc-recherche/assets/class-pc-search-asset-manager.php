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
        add_action('wp_head', [$this, 'inject_critical_css'], 2);
    }

    /**
     * Assets Logements
     */
    public static function enqueue_assets()
    {
        $ver = '7.4.2';
        if (!wp_style_is('flatpickr', 'enqueued')) wp_enqueue_style('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], '4.6.13');
        if (!wp_script_is('flatpickr', 'enqueued')) wp_enqueue_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', [], '4.6.13', true);
        if (!wp_script_is('flatpickr-fr', 'enqueued')) wp_enqueue_script('flatpickr-fr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js', ['flatpickr'], '4.6.13', true);
        if (!wp_script_is('flatpickr-range', 'enqueued')) wp_enqueue_script('flatpickr-range', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/rangePlugin.js', ['flatpickr'], '4.6.13', true);

        // NOUVEAUX COMPOSANTS MODULAIRES (Corrigé avec wp_enqueue_style)
        wp_enqueue_style('pc-search-form-css', PC_RECHERCHE_URL . 'assets/css/components/pc-search-form.css', [], PC_RECHERCHE_VERSION);
        wp_enqueue_style('pc-search-filters-css', PC_RECHERCHE_URL . 'assets/css/components/pc-search-filters.css', [], PC_RECHERCHE_VERSION);
        wp_enqueue_style('pc-search-results-css', PC_RECHERCHE_URL . 'assets/css/components/pc-search-results.css', [], PC_RECHERCHE_VERSION);
        wp_enqueue_style('pc-search-pagination-css', PC_RECHERCHE_URL . 'assets/css/components/pc-search-pagination.css', [], PC_RECHERCHE_VERSION);
        wp_enqueue_style('pc-search-map-css', PC_RECHERCHE_URL . 'assets/css/components/pc-search-map.css', [], PC_RECHERCHE_VERSION);

        // JS Logements (En attendant la refonte JS)
        // Nouveaux composants JavaScript
        wp_enqueue_script('pc-search-form-js', PC_RECHERCHE_URL . 'assets/js/components/pc-search-form.js', ['jquery'], PC_RECHERCHE_VERSION, true);
        wp_enqueue_script('pc-search-map-js', PC_RECHERCHE_URL . 'assets/js/components/pc-search-map.js', ['jquery'], PC_RECHERCHE_VERSION, true);
        wp_enqueue_script('pc-search-ajax-js', PC_RECHERCHE_URL . 'assets/js/components/pc-search-ajax.js', ['jquery', 'pc-search-form-js', 'pc-search-map-js'], PC_RECHERCHE_VERSION, true);

        // On injecte les variables (nonce, url AJAX) pour les logements
        if (!wp_script_is('pc-search-ajax-js-local', 'enqueued')) {
            wp_register_script('pc-search-ajax-js-local', '', [], $ver, true);
            wp_enqueue_script('pc-search-ajax-js-local');
            wp_add_inline_script('pc-search-ajax-js-local', 'window.pc_search_params = { ajax_url: "' . esc_js(admin_url('admin-ajax.php')) . '", nonce: "' . esc_js(wp_create_nonce('pc_search_nonce')) . '" };');
        }
    }

    /**
     * Assets Expériences
     */
    public static function enqueue_experience_assets()
    {
        $version = '2.0.0';

        // ON CHARGE LES MÊMES MODULES CSS QUE POUR LES LOGEMENTS (L'ancien est supprimé !)
        wp_enqueue_style('pc-search-form-css', PC_RECHERCHE_URL . 'assets/css/components/pc-search-form.css', [], PC_RECHERCHE_VERSION);
        wp_enqueue_style('pc-search-filters-css', PC_RECHERCHE_URL . 'assets/css/components/pc-search-filters.css', [], PC_RECHERCHE_VERSION);
        wp_enqueue_style('pc-search-results-css', PC_RECHERCHE_URL . 'assets/css/components/pc-search-results.css', [], PC_RECHERCHE_VERSION);
        wp_enqueue_style('pc-search-pagination-css', PC_RECHERCHE_URL . 'assets/css/components/pc-search-pagination.css', [], PC_RECHERCHE_VERSION);
        wp_enqueue_style('pc-search-map-css', PC_RECHERCHE_URL . 'assets/css/components/pc-search-map.css', [], PC_RECHERCHE_VERSION);

        // JS Expériences (En attendant la refonte JS)
        // On charge LES MÊMES composants JavaScript
        wp_enqueue_script('pc-search-form-js', PC_RECHERCHE_URL . 'assets/js/components/pc-search-form.js', ['jquery'], PC_RECHERCHE_VERSION, true);
        wp_enqueue_script('pc-search-map-js', PC_RECHERCHE_URL . 'assets/js/components/pc-search-map.js', ['jquery'], PC_RECHERCHE_VERSION, true);
        wp_enqueue_script('pc-search-ajax-js', PC_RECHERCHE_URL . 'assets/js/components/pc-search-ajax.js', ['jquery', 'pc-search-form-js', 'pc-search-map-js'], PC_RECHERCHE_VERSION, true);

        // On injecte les variables (nonce, url AJAX) spécifiques aux expériences
        wp_localize_script('pc-search-ajax-js', 'pc_exp_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('pc_experience_search_nonce')
        ]);
    }

    public function inject_critical_css()
    {
        if (is_page('recherche-de-logements') || is_page('recherche') || is_page('experiences')) { // J'ai ajouté 'experiences' par sécurité
            echo <<<HTML
<style id="pc-critical-css">.pc-search-shell{background:#fff;border-radius:15px;box-shadow:0 8px 24px rgba(0,0,0,.06);padding:8px;width:100%;}.pc-search-form{display:grid;grid-template-columns:1fr 1fr;grid-template-areas:"loc loc" "arr dep" "gst gst" "btn btn";gap:8px;align-items:stretch;}.pc-area-loc{grid-area:loc}.pc-area-arr{grid-area:arr}.pc-area-dep{grid-area:dep}.pc-area-gst{grid-area:gst}.pc-area-btn{grid-area:btn}.pc-input{width:100%;height:52px;padding:0 14px;border:1px solid #E5E7EB;border-radius:12px;background:#fff;font-size:16px}.pc-search-submit{height:52px;border-radius:12px;white-space:nowrap}.pc-row-adv-toggle{margin-top:8px;display:flex;justify-content:space-between;align-items:center}.pc-results-grid{display:grid;grid-template-columns:1fr;gap:2rem;margin-top:1rem}.pc-vignette{background:#fff;border-radius:12px;overflow:hidden;text-decoration:none;display:flex;flex-direction:column}.pc-vignette__image{aspect-ratio:376/230;background-color:#f0f0f0}.pc-vignette__image img{display:block;width:100%;height:100%;object-fit:cover}.pc-vignette__content{padding:1rem 1.25rem;display:flex;flex-direction:column;gap:0.75rem}.pc-vignette__title{font-size:1.25rem;font-weight:600;margin:0;color:#111827}.pc-vignette__location{font-size:1rem;color:#374151}</style>
HTML;
        }
    }
}
