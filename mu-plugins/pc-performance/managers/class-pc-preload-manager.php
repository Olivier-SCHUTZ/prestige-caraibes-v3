<?php

/**
 * PC Preload Manager
 * Gère l'injection dynamique des balises <link rel="preload"> (Fonts & LCP)
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Preload_Manager
{

    public function __construct()
    {
        add_action('wp_head', [$this, 'emit_preloads'], 1);
    }

    /**
     * Injecte les préchargements vitaux
     */
    public function emit_preloads()
    {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST) || wp_doing_ajax()) return;

        // 1. Préchargement des polices locales
        $fonts = PC_Performance_Core::get_instance()->font_manager->get_font_urls();
        foreach ($fonts as $f) {
            echo '<link rel="preload" as="font" type="font/woff2" href="' . esc_url($f) . '" crossorigin>' . "\n";
        }

        // 2. Préchargement des images LCP (Mobile & Desktop)
        $lcp_urls = PC_Performance_Core::get_instance()->lcp_manager->get_urls();

        if (!empty($lcp_urls['mobile'])) {
            $mime = PC_Resource_Helper::get_image_mime_type($lcp_urls['mobile']);
            echo '<link rel="preload" media="(max-width: 767px)" as="image" href="' . esc_url($lcp_urls['mobile']) . '" fetchpriority="high" type="' . esc_attr($mime) . '">' . "\n";
        }

        if (!empty($lcp_urls['desktop'])) {
            $mime = PC_Resource_Helper::get_image_mime_type($lcp_urls['desktop']);
            echo '<link rel="preload" media="(min-width: 768px)" as="image" href="' . esc_url($lcp_urls['desktop']) . '" fetchpriority="high" type="' . esc_attr($mime) . '">' . "\n";
        }
    }
}
