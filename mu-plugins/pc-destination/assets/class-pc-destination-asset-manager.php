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
        // 🛡️ Condition stricte : On ne charge QUE sur la page de type 'destination'
        if (!is_singular('destination')) {
            return;
        }

        // =========================================================================
        // 1. COMPOSANTS CSS
        // =========================================================================

        $components_css = [];

        foreach ($components_css as $handle => $filename) {
            $css_path = PC_DEST_DIR . 'assets/css/components/' . $filename;
            if (file_exists($css_path)) {
                wp_enqueue_style($handle, PC_DEST_URL . 'assets/css/components/' . $filename, [], filemtime($css_path));
            }
        }

        // Aucun JS ou composant externe n'est requis pour les destinations !
    }
}
