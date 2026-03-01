<?php

/**
 * Gestionnaire des Assets (CSS / JS) pour la fiche Destination
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Destination_Asset_Manager
{
    /**
     * Enregistre les hooks d'assets
     */
    public function register()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_global_assets'], 20);
    }

    /**
     * Charge les scripts JS et le CSS principal
     */
    public function enqueue_global_assets()
    {
        // On ne charge que sur les pages de type 'destination'
        if (!is_singular('destination')) {
            return;
        }

        // =========================================================================
        // 1. COMPOSANTS CSS
        // =========================================================================

        // Composant : Global & Base Cards
        $global_css_path = PC_DEST_DIR . 'assets/css/components/pc-destination-global.css';
        if (file_exists($global_css_path)) {
            wp_enqueue_style('pc-dest-global', PC_DEST_URL . 'assets/css/components/pc-destination-global.css', [], filemtime($global_css_path));
        }

        // Composant : Recommandations (Logements & Expériences)
        $reco_css_path = PC_DEST_DIR . 'assets/css/components/pc-destination-recommendations.css';
        if (file_exists($reco_css_path)) {
            wp_enqueue_style('pc-dest-recommendations', PC_DEST_URL . 'assets/css/components/pc-destination-recommendations.css', [], filemtime($reco_css_path));
        }

        // Composant : Informations Pratiques
        $infos_css_path = PC_DEST_DIR . 'assets/css/components/pc-destination-infos.css';
        if (file_exists($infos_css_path)) {
            wp_enqueue_style('pc-dest-infos', PC_DEST_URL . 'assets/css/components/pc-destination-infos.css', [], filemtime($infos_css_path));
        }

        // Composant : Menu Sticky (Ancres)
        $anchor_css_path = PC_DEST_DIR . 'assets/css/components/pc-destination-anchor-menu.css';
        if (file_exists($anchor_css_path)) {
            wp_enqueue_style('pc-dest-anchor-menu', PC_DEST_URL . 'assets/css/components/pc-destination-anchor-menu.css', [], filemtime($anchor_css_path));
        }
    }
}
