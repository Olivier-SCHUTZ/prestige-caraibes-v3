<?php

/**
 * PC Loop Card Shortcode
 * Remplace l'ancien pc-loop-components.php
 * Shortcode: [pc_loop_lodging_card]
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Loop_Card_Shortcode extends PC_UI_Shortcode_Base
{

    protected function get_tag()
    {
        // RÉTROCOMPATIBILITÉ ABSOLUE : on garde le tag historique
        return 'pc_loop_lodging_card';
    }

    public function render($atts = [], $content = null)
    {
        global $post;

        // Sécurité : on s'assure qu'on est bien dans une boucle avec un post valide
        if (!$post || empty($post->ID)) {
            return '';
        }

        // Fait appel au Helper pour générer et retourner le HTML de la carte
        return PC_Card_Render_Helper::render_lodging_card($post->ID);
    }
}
