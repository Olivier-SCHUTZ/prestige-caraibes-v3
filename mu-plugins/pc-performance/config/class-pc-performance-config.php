<?php

/**
 * PC Performance Config
 * Centralise toutes les règles métier et les constantes du module
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Performance_Config
{

    /**
     * Retourne la configuration complète
     */
    public static function get()
    {
        return [
            // Mapping des champs ACF LCP par type de publication (post_type)
            'lcp_acf_mapping' => [
                'logement'    => ['desktop' => 'hero_desktop_url', 'mobile' => 'hero_mobile_url'],
                'villa'       => ['desktop' => 'hero_desktop_url', 'mobile' => 'hero_mobile_url'],
                'appartement' => ['desktop' => 'hero_desktop_url', 'mobile' => 'hero_mobile_url'],
                'experience'  => ['desktop' => 'exp_hero_desktop', 'mobile' => 'exp_hero_mobile'],
                'destination' => ['desktop' => 'dest_hero_desktop', 'mobile' => 'dest_hero_mobile'],
                'page'        => ['desktop' => 'serv_desktop_url', 'mobile' => 'serv_mobile_url'],
            ],

            // Domaines externes à pré-connecter en priorité
            'preconnect_domains' => [
                'https://www.googletagmanager.com',
                'https://embeds.iubenda.com'
            ],

            // Paramètres de fallback
            'fallback_mobile_to_desktop' => defined('PC_PERF_LCP_FALLBACK_MOBILE_TO_DESKTOP') ? PC_PERF_LCP_FALLBACK_MOBILE_TO_DESKTOP : true,
        ];
    }

    /**
     * Récupère une valeur spécifique de la configuration
     */
    public static function get_key($key)
    {
        $config = self::get();
        return isset($config[$key]) ? $config[$key] : null;
    }
}
