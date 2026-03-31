<?php

/**
 * PC Resource Helper
 * Manipulation des assets et requêtes ACF pour la performance
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Resource_Helper
{

    /**
     * Devine le type MIME d'une image depuis son extension
     * @param string $url
     * @return string
     */
    public static function get_image_mime_type($url)
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!$path) return 'image/*';

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'webp') return 'image/webp';
        if ($ext === 'avif') return 'image/avif';
        if ($ext === 'jpg' || $ext === 'jpeg') return 'image/jpeg';
        if ($ext === 'png') return 'image/png';

        return 'image/*';
    }

    /**
     * Récupère de façon sécurisée une URL d'image (compatible PCR_Fields/ACF)
     * Gère les retours en Array, ID ou String
     * @param string $acf_key
     * @return string L'URL résolue ou une chaîne vide
     */
    public static function get_acf_image_url($acf_key)
    {
        if (!class_exists('PCR_Fields')) return '';

        $v = PCR_Fields::get($acf_key);

        if (is_array($v)) {
            if (!empty($v['url'])) {
                $v = $v['url'];
            } elseif (!empty($v['ID'])) {
                $v = wp_get_attachment_url($v['ID']);
            } else {
                $v = '';
            }
        } elseif (is_numeric($v)) {
            $v = wp_get_attachment_url(intval($v));
        }

        $u = trim((string)$v);
        if (!$u) return '';

        $u = PC_Url_Helper::resolve_url($u); // Utilisation de notre nouveau Helper URL
        return filter_var($u, FILTER_VALIDATE_URL) ? $u : '';
    }
}
