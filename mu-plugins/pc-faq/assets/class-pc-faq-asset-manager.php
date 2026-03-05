<?php

/**
 * Gestionnaire des Assets (CSS / JS) pour le module FAQ
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_FAQ_Asset_Manager
{
    /**
     * Enregistre les hooks d'assets
     */
    public function register()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets'], 20);
    }

    /**
     * Charge les styles et scripts
     */
    public function enqueue_assets()
    {
        // Optimisation : On ne charge que sur les contenus susceptibles d'avoir une FAQ
        // Logements, Expériences, Destinations ou Pages standards
        if (!is_singular(['villa', 'appartement', 'logement', 'experience', 'destination', 'page', 'post'])) {
            return;
        }

        // 1. CSS Principal de la FAQ
        // Note : On migre vers le nouveau nom de fichier standardisé
        $css_path = PC_FAQ_PATH . 'assets/css/pc-faq.css';

        if (file_exists($css_path)) {
            wp_enqueue_style(
                'pc-faq-style',
                PC_FAQ_URL . 'assets/css/pc-faq.css',
                [],
                filemtime($css_path)
            );
        }

        // 2. JS Principal (Interactions accordéon)
        // Sera créé dans une étape ultérieure
        $js_path = PC_FAQ_PATH . 'assets/js/pc-faq.js';

        if (file_exists($js_path)) {
            wp_enqueue_script(
                'pc-faq-script',
                PC_FAQ_URL . 'assets/js/pc-faq.js',
                [],
                filemtime($js_path),
                true
            );
        }
    }
}
