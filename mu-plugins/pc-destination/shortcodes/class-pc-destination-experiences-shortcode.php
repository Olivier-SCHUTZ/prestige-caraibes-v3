<?php

/**
 * Module : Shortcode Expériences [pc_destination_experiences]
 * Affiche la grille des expériences rattachées à une destination ou mises en avant.
 */

if (!defined('ABSPATH')) {
    exit; // Sécurité
}

class PC_Destination_Experiences_Shortcode
{
    /**
     * Enregistre le shortcode
     */
    public function register()
    {
        add_shortcode('pc_destination_experiences', [$this, 'render']);
    }

    /**
     * Rendu du shortcode
     */
    public function render($atts)
    {
        $a = shortcode_atts([
            'id'    => get_queried_object_id(),
            'limit' => 3,
        ], $atts, 'pc_destination_experiences');

        $id = PC_Destination_Query_Helper::safe_int($a['id'], get_queried_object_id());
        if (!$id) {
            return '';
        }

        $featured = function_exists('get_field') ? (array) get_field('dest_exp_featured', $id) : [];
        $html = '<section class="pc-section pc-dest-experiences">';

        // Si des expériences sont mises en avant via ACF
        if (!empty($featured)) {
            $ids = array_map('intval', is_array($featured) && isset($featured[0]['ID']) ? wp_list_pluck($featured, 'ID') : $featured);
            $q = new WP_Query([
                'post_type'      => 'experience',
                'post_status'    => 'publish',
                'post__in'       => $ids,
                'orderby'        => 'post__in',
                'posts_per_page' => PC_Destination_Query_Helper::safe_int($a['limit'], 3),
            ]);
        } else {
            // Sinon, on charge les expériences liées à la destination
            $q = PC_Destination_Query_Helper::get_posts_by_rel_destination('experience', $id, [
                'posts_per_page' => PC_Destination_Query_Helper::safe_int($a['limit'], 3),
            ]);
        }

        $html .= PC_Destination_Render_Helper::render_cards($q, 'experience') . '</section>';
        return $html;
    }
}
