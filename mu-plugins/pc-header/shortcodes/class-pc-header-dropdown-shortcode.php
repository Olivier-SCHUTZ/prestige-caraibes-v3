<?php

/**
 * PC Header Dropdown Shortcode
 * Remplace l'ancien système monolithique pour le menu des logements
 * Shortcode: [liste_logements_dropdown]
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Header_Dropdown_Shortcode
{

    /**
     * Enregistre le shortcode (Utilisation de la méthode register() standard)
     */
    public function register()
    {
        add_shortcode('liste_logements_dropdown', [$this, 'render']);
    }

    /**
     * Rendu du shortcode
     */
    public function render($atts = [], $content = null)
    {
        // Le chargement des assets est maintenant géré dynamiquement
        // par PC_Header_Asset_Manager::enqueue_assets(); appelé par le header
        return PC_Header_Dropdown_Helper::render_dropdown($atts);
    }
}

/**
 * --- FILET DE SÉCURITÉ : RÉTROCOMPATIBILITÉ ---
 * Si le header appelle directement la fonction PHP au lieu du shortcode
 */
if (!function_exists('pc_sll_render_dropdown')) {
    function pc_sll_render_dropdown($atts = [])
    {
        if (class_exists('PC_Header_Dropdown_Helper')) {
            $shortcode_class = new PC_Header_Dropdown_Shortcode();
            return $shortcode_class->render($atts);
        }
        return '';
    }
}
