<?php

/**
 * PC Preconnect Manager
 * Gère l'injection intelligente des balises <link rel="preconnect">
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Preconnect_Manager
{

    public function __construct()
    {
        add_action('wp_head', [$this, 'emit_preconnects'], 1);
    }

    /**
     * Calcule et injecte les domaines à pré-connecter
     */
    public function emit_preconnects()
    {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST) || wp_doing_ajax()) return;

        $hosts = PC_Performance_Config::get_key('preconnect_domains') ?: [];

        // Origines des images LCP
        $lcp_urls = PC_Performance_Core::get_instance()->lcp_manager->get_urls();
        foreach ($lcp_urls as $url) {
            if ($o = PC_Url_Helper::get_origin($url)) $hosts[] = $o;
        }

        // Origines des Polices
        $fonts = PC_Performance_Core::get_instance()->font_manager->get_font_urls();
        foreach ($fonts as $f) {
            if ($o = PC_Url_Helper::get_origin($f)) $hosts[] = $o;
        }

        // Carte OSM (Conditionnel)
        if (PC_Context_Helper::uses_map()) {
            $hosts[] = 'https://tile.openstreetmap.org';
        }

        // Nettoyage et déduplication
        $hosts = array_values(array_unique(array_filter($hosts)));

        // On retire l'origine du site lui-même (le navigateur y est déjà connecté)
        $site_origin_host = parse_url(PC_Url_Helper::get_site_origin(), PHP_URL_HOST);
        $final_hosts = [];

        foreach ($hosts as $h) {
            $h_host = parse_url($h, PHP_URL_HOST);
            if (!$h_host || strcasecmp($h_host, $site_origin_host) !== 0) {
                $final_hosts[] = $h;
            }
        }

        // Budget maximum de sécurité (évite de surcharger le réseau)
        $final_hosts = array_slice($final_hosts, 0, 6);

        // Rendu HTML
        foreach ($final_hosts as $h) {
            echo '<link rel="preconnect" href="' . esc_url($h) . '" crossorigin>' . "\n";
        }
    }
}
