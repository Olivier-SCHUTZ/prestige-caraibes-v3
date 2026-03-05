<?php

/**
 * PC URL Helper
 * Gestion avancée des URLs pour le module de performance
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Url_Helper
{

    /**
     * Extrait l'origine (scheme+host) d'une URL absolue ou relative au protocole
     * @param string $url
     * @return string
     */
    public static function get_origin($url)
    {
        if (!$url) return '';
        if (strpos($url, '//') === 0) $url = 'https:' . $url; // support //example.com/...

        $p = @parse_url($url);
        if (empty($p['scheme']) || empty($p['host'])) return '';

        return $p['scheme'] . '://' . $p['host'];
    }

    /**
     * Retourne l'origine absolue du site courant
     * @return string
     */
    public static function get_site_origin()
    {
        $u = home_url();
        $p = @parse_url($u);
        return (!empty($p['scheme']) && !empty($p['host'])) ? $p['scheme'] . '://' . $p['host'] : '';
    }

    /**
     * Convertit un chemin relatif en URL absolue du site courant
     * @param string $u
     * @return string
     */
    public static function resolve_url($u)
    {
        if (!$u) return '';

        // URL absolue
        if (filter_var($u, FILTER_VALIDATE_URL)) return $u;

        // Relative au protocole (//cdn.example.com/...)
        if (strpos($u, '//') === 0) return 'https:' . $u;

        // Chemin absolu à la racine (/wp-content/...)
        if ($u[0] === '/') return rtrim(home_url(), '/') . $u;

        // Chemin relatif classique (wp-content/...)
        if (strpos($u, 'wp-content/') === 0) return rtrim(home_url(), '/') . '/' . $u;

        return $u;
    }
}
