<?php

/**
 * Module : Shortcode Hub Destinations [pc_destinations_hub]
 * Affiche la grille globale de toutes les destinations (page mère).
 */

if (!defined('ABSPATH')) {
    exit; // Sécurité
}

class PC_Destination_Hub_Shortcode
{
    /**
     * Enregistre le shortcode
     */
    public function register()
    {
        add_shortcode('pc_destinations_hub', [$this, 'render']);
    }

    /**
     * Rendu du shortcode
     */
    public function render($atts)
    {
        $a = shortcode_atts([
            'region'        => 'all', // all | grande-terre | basse-terre | iles-voisines
            'featured_only' => 'false',
            'orderby'       => 'meta_value_num', // dest_order
            'order'         => 'ASC',
        ], $atts, 'pc_destinations_hub');

        $meta = ['relation' => 'AND', ['key' => 'dest_order', 'compare' => 'EXISTS']];

        if ($a['featured_only'] === 'true') {
            $meta[] = ['key' => 'dest_featured', 'value' => '1', 'compare' => '='];
        }
        if ($a['region'] !== 'all') {
            $meta[] = ['key' => 'dest_region', 'value' => sanitize_text_field($a['region']), 'compare' => '='];
        }

        $q = new WP_Query([
            'post_type'      => 'destination',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => $meta,
            'meta_key'       => 'dest_order',
            'orderby'        => sanitize_key($a['orderby']),
            'order'          => in_array(strtoupper($a['order']), ['ASC', 'DESC']) ? strtoupper($a['order']) : 'ASC',
        ]);

        ob_start();
        if ($q->have_posts()) {
            echo '<div class="pc-grid pc-grid--destinations">';
            while ($q->have_posts()) {
                $q->the_post();
                $pid = get_the_ID();
                $permalink = get_permalink($pid);
                $title = get_the_title($pid);
                $slogan = function_exists('get_field') ? (string) get_field('dest_slogan', $pid) : '';
                $img_id = function_exists('get_field') ? (int) get_field('dest_hero_desktop', $pid) : 0;

                $thumb = $img_id
                    ? wp_get_attachment_image($img_id, 'large', false, ['loading' => 'lazy', 'class' => 'pc-card__img'])
                    : get_the_post_thumbnail($pid, 'large', ['loading' => 'lazy', 'class' => 'pc-card__img']);

                echo '<article class="pc-card pc-card--destination">';
                echo $thumb ?: '';
                echo '<div class="pc-card__body">';
                echo '<h3 class="pc-card__title"><a href="' . esc_url($permalink) . '">' . esc_html($title) . '</a></h3>';

                if ($slogan) {
                    echo '<p class="pc-card__excerpt">' . esc_html($slogan) . '</p>';
                }

                echo '<p><a class="pc-btn" href="' . esc_url($permalink) . '">Découvrir</a></p>';
                echo '</div></article>';
            }
            echo '</div>';
        }
        wp_reset_postdata();
        return ob_get_clean();
    }
}
