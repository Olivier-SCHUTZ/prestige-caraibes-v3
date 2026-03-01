<?php

/**
 * Module : Shortcode Logements [pc_destination_logements]
 * Affiche la grille des logements rattachés à une destination.
 */

if (!defined('ABSPATH')) {
    exit; // Sécurité
}

class PC_Destination_Logements_Shortcode
{
    /**
     * Enregistre le shortcode
     */
    public function register()
    {
        add_shortcode('pc_destination_logements', [$this, 'render']);
    }

    /**
     * Rendu du shortcode
     */
    public function render($atts)
    {
        $a = shortcode_atts([
            'id'       => get_queried_object_id(),
            'per_page' => 12,
            'orderby'  => 'date',
            'order'    => 'DESC',
        ], $atts, 'pc_destination_logements');

        // Utilisation de notre nouveau Query Helper
        $id = PC_Destination_Query_Helper::safe_int($a['id'], get_queried_object_id());
        if (!$id) {
            return '';
        }

        // Requête via le Query Helper
        $q = PC_Destination_Query_Helper::get_posts_by_rel_destination('logement', $id, [
            'posts_per_page' => PC_Destination_Query_Helper::safe_int($a['per_page'], 12),
            'orderby'        => sanitize_key($a['orderby']),
            'order'          => in_array(strtoupper($a['order']), ['ASC', 'DESC']) ? strtoupper($a['order']) : 'DESC',
        ]);

        // Affichage via le Render Helper
        return '<section class="pc-section pc-dest-logements">' . PC_Destination_Render_Helper::render_cards($q, 'logement') . '</section>';
    }
}
