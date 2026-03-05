<?php

/**
 * PC Rating Helper
 * Gère le calcul des notes et l'affichage des étoiles pour les composants UI
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Rating_Helper
{

    /**
     * Calcule la note moyenne d'un post (logement ou expérience)
     * Basé sur le CPT 'pc_review' et la source 'internal'
     *
     * @param int $post_id L'ID du post concerné
     * @return float La note moyenne (0 si aucun avis)
     */
    public static function get_average_rating($post_id)
    {
        $rating = 0;
        $review_args = [
            'post_type'      => 'pc_review',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => 'pc_post_id', 'value' => $post_id],
                ['key' => 'pc_source', 'value' => 'internal']
            ],
            'fields'         => 'ids',
        ];

        $review_query = new WP_Query($review_args);

        if (!empty($review_query->posts)) {
            $total_rating = 0;
            foreach ($review_query->posts as $review_id) {
                $total_rating += (float) get_post_meta($review_id, 'pc_rating', true);
            }
            if ($review_query->post_count > 0) {
                $rating = $total_rating / $review_query->post_count;
            }
        }

        return $rating;
    }

    /**
     * Génère le HTML des étoiles en fonction d'une note
     *
     * @param float $rating La note à afficher
     * @return string Le code HTML
     */
    public static function render_stars_html($rating)
    {
        if ($rating > 0) {
            $rating_html = '<div class="pc-vignette__rating"><div class="pc-vignette__stars">';
            for ($i = 1; $i <= 5; $i++) {
                $class = ($i <= round($rating)) ? 'star filled' : 'star';
                $rating_html .= '<span class="' . esc_attr($class) . '"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg></span>';
            }
            $rating_html .= '</div></div>';
        } else {
            $rating_html = '<div class="pc-vignette__rating">N.C.</div>';
        }

        return $rating_html;
    }
}
