<?php

/**
 * PC Font Manager
 * Gère les polices locales et nettoie de façon optimisée les requêtes vers Google Fonts
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Font_Manager
{

    public function __construct()
    {
        // Méthodes natives et performantes pour bloquer Google Fonts
        add_filter('wp_resource_hints', [$this, 'remove_google_fonts_hints'], 999, 2);
        add_filter('style_loader_src', [$this, 'block_google_fonts_src'], 10, 1);
        add_action('wp_enqueue_scripts', [$this, 'dequeue_google_fonts'], 20);
    }

    /**
     * Retourne les URLs des polices locales à précharger
     */
    public function get_font_urls()
    {
        $candidates = [];

        // Support des URLs (legacy)
        if (defined('PC_PERF_FONT_POPPINS_600_URL') && PC_PERF_FONT_POPPINS_600_URL) $candidates[] = PC_PERF_FONT_POPPINS_600_URL;
        if (defined('PC_PERF_FONT_LORA_REGULAR_URL') && PC_PERF_FONT_LORA_REGULAR_URL) $candidates[] = PC_PERF_FONT_LORA_REGULAR_URL;

        // Support des PATHs
        if (defined('PC_PERF_FONT_POPPINS_600_PATH') && PC_PERF_FONT_POPPINS_600_PATH) $candidates[] = PC_Url_Helper::resolve_url(PC_PERF_FONT_POPPINS_600_PATH);
        if (defined('PC_PERF_FONT_LORA_REGULAR_PATH') && PC_PERF_FONT_LORA_REGULAR_PATH) $candidates[] = PC_Url_Helper::resolve_url(PC_PERF_FONT_LORA_REGULAR_PATH);

        $candidates = apply_filters('pc_perf_font_urls', $candidates);

        $out = [];
        foreach ($candidates as $u) {
            $u = PC_Url_Helper::resolve_url($u);
            if (filter_var($u, FILTER_VALIDATE_URL)) $out[] = $u;
        }

        return array_slice(array_values(array_unique($out)), 0, 2);
    }

    /**
     * Retire dns-prefetch et preconnect
     */
    public function remove_google_fonts_hints($urls, $relation)
    {
        if (!is_array($urls)) return $urls;
        if ($relation === 'preconnect' || $relation === 'dns-prefetch') {
            return array_filter($urls, function ($u) {
                return stripos($u, 'fonts.googleapis.com') === false && stripos($u, 'fonts.gstatic.com') === false;
            });
        }
        return array_values($urls);
    }

    /**
     * Bloque le chargement du fichier source
     */
    public function block_google_fonts_src($src)
    {
        if (stripos($src, 'fonts.googleapis.com') !== false || stripos($src, 'fonts.gstatic.com') !== false) {
            return false;
        }
        return $src;
    }

    /**
     * Désenregistre proprement les CSS de polices
     */
    public function dequeue_google_fonts()
    {
        global $wp_styles;
        if (empty($wp_styles->registered)) return;

        foreach ($wp_styles->registered as $handle => $obj) {
            $src = isset($obj->src) ? $obj->src : '';
            if ($src && (stripos($src, 'fonts.googleapis.com') !== false || stripos($src, 'fonts.gstatic.com') !== false)) {
                wp_dequeue_style($handle);
                wp_deregister_style($handle);
            }
        }
    }
}
