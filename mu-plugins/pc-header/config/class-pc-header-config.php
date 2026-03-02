<?php

/**
 * Configuration centralisée du module PC Header
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Header_Config
{
    /**
     * Retourne la configuration globale du header.
     * Accessible via PC_Header_Config::get()
     *
     * @return array
     */
    public static function get(): array
    {
        $config = [
            'menu_name'          => 'Menu Principal V3',
            'menu_services_name' => 'Menu Services',

            // Page “Recherche” existante (CTA)
            'search_url'         => '/recherche-de-logements/',

            // Logo fallback si aucun “Logo du site” n’est défini dans WP
            'logo_src'           => '/wp-content/uploads/2025/06/Logo-Prestige-Caraibes-bleu.png',

            // Recherche unifiée : post types cibles
            'search_post_types'  => ['villa', 'appartement', 'destination', 'experience'],

            // UI search
            'search_min_chars'   => 2,
            'search_max_results' => 8,
            'search_placeholder' => 'Rechercher une villa, une destination, une expérience…',

            'tel_label'          => '+590 690 63 11 81',
            'tel_href'           => 'tel:+590690631181',

            'social'             => [
                ['key' => 'facebook',  'label' => 'Facebook',  'href' => 'https://facebook.com/prestigecaraibes'],
                ['key' => 'youtube',   'label' => 'YouTube',   'href' => 'https://www.youtube.com/@prestigecaraibes'],
                ['key' => 'instagram', 'label' => 'Instagram', 'href' => 'https://instagram.com/prestigecaraibes'],
                ['key' => 'whatsapp',  'label' => 'WhatsApp',  'href' => 'https://api.whatsapp.com/send?phone=590690631181'],
            ],
        ];

        // On conserve le filtre pour garantir la non-régression si d'autres plugins modifient cette config
        return apply_filters('pc_hg_config', $config);
    }
}
