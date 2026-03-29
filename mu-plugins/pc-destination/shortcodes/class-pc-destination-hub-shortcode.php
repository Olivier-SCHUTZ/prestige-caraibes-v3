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

                // --- 1. DÉCODEUR V3 HYBRIDE (Slogan) ---
                $slogan = class_exists('PCR_Fields') ? PCR_Fields::get('dest_slogan', $pid) : null;
                if (empty($slogan)) {
                    $slogan = get_post_meta($pid, 'dest_slogan', true);
                }
                $slogan = is_string($slogan) ? trim(stripslashes($slogan)) : '';

                // --- 2. DÉCODEUR V3 HYBRIDE (Image Hero) ---
                $img_data = class_exists('PCR_Fields') ? PCR_Fields::get('dest_hero_desktop', $pid) : null;
                if (empty($img_data)) {
                    $img_data = get_post_meta($pid, 'dest_hero_desktop', true);
                }

                // Gestion du format (Objet ACF vs ID Natif)
                $img_id = 0;
                if (is_array($img_data) && isset($img_data['ID'])) {
                    $img_id = (int) $img_data['ID'];
                } elseif (is_numeric($img_data)) {
                    $img_id = (int) $img_data;
                }

                $thumb = $img_id
                    ? wp_get_attachment_image($img_id, 'large', false, ['loading' => 'lazy', 'class' => 'pc-card__img'])
                    : get_the_post_thumbnail($pid, 'large', ['loading' => 'lazy', 'class' => 'pc-card__img']);

                echo '<article class="pc-card pc-card--destination">';

                // On englobe l'image dans un lien cliquable avec un wrapper pour l'effet de hover
                echo '<a href="' . esc_url($permalink) . '" class="pc-card__link-wrapper" aria-hidden="true" tabindex="-1">';
                echo $thumb ?: '';
                echo '</a>';

                echo '<div class="pc-card__body">';
                echo '<h3 class="pc-card__title"><a href="' . esc_url($permalink) . '">' . esc_html($title) . '</a></h3>';

                if ($slogan) {
                    echo '<p class="pc-card__excerpt">' . esc_html($slogan) . '</p>';
                }

                echo '<p class="pc-card__action"><a class="pc-btn pc-btn--primary" href="' . esc_url($permalink) . '">Découvrir</a></p>';
                echo '</div></article>';
            }
            echo '</div>';

            // --- 3. CSS EMBARQUÉ V3 ---
?>
            <style>
                .pc-grid--destinations {
                    display: grid;
                    gap: 24px;
                    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                    margin: 2rem 0;
                }

                .pc-card--destination {
                    background: #ffffff;
                    border-radius: var(--pc-border-radius, 12px);
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
                    border: 1px solid #e2e8f0;
                    overflow: hidden;
                    display: flex;
                    flex-direction: column;
                    transition: transform 0.3s ease, box-shadow 0.3s ease;
                    height: 100%;
                }

                .pc-card--destination:hover {
                    transform: translateY(-5px);
                    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
                }

                .pc-card__link-wrapper {
                    display: block;
                    overflow: hidden;
                    aspect-ratio: 4/3;
                }

                .pc-card--destination .pc-card__img {
                    width: 100%;
                    height: 100%;
                    display: block;
                    object-fit: cover;
                    transition: transform 0.5s ease;
                }

                .pc-card--destination:hover .pc-card__img {
                    transform: scale(1.05);
                }

                .pc-card--destination .pc-card__body {
                    padding: 1.5rem;
                    display: flex;
                    flex-direction: column;
                    flex-grow: 1;
                }

                .pc-card--destination .pc-card__title {
                    font-family: var(--pc-font-heading, system-ui);
                    margin: 0 0 0.5rem 0;
                    font-size: 1.25rem;
                    line-height: 1.3;
                    font-weight: 600;
                }

                .pc-card--destination .pc-card__title a {
                    color: var(--pc-color-heading, #1b3b5f);
                    text-decoration: none;
                }

                .pc-card--destination .pc-card__excerpt {
                    color: var(--pc-color-text, #475569);
                    font-size: 1rem;
                    line-height: 1.5;
                    margin: 0 0 1.5rem 0;
                    flex-grow: 1;
                }

                .pc-card--destination .pc-card__action {
                    margin: auto 0 0 0;
                    /* Pousse le bouton toujours en bas */
                }

                .pc-card--destination .pc-btn {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    padding: 0.75rem 1.5rem;
                    border-radius: var(--pc-border-radius, 12px);
                    background-color: var(--pc-color-primary, #007a92);
                    color: #ffffff;
                    text-decoration: none;
                    font-weight: 600;
                    transition: background-color 0.2s ease;
                    width: max-content;
                }

                .pc-card--destination .pc-btn:hover {
                    background-color: var(--pc-color-primary-hover, #005f73);
                    color: #ffffff;
                }
            </style>
<?php
        }
        wp_reset_postdata();
        return ob_get_clean();
    }
}
