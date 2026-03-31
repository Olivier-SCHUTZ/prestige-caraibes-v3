<?php

/**
 * Module : Helper pour le rendu HTML (Render Helper)
 * Centralise la génération de l'affichage (cartes, grilles, etc.).
 */

if (!defined('ABSPATH')) {
    exit; // Sécurité
}

class PC_Destination_Render_Helper
{
    /**
     * Rendu unifié des grilles de cartes (Logements ou Expériences).
     *
     * @param WP_Query $q Requête contenant les posts.
     * @param string $context Contexte ('logement' ou 'experience').
     * @return string HTML généré.
     */
    public static function render_cards($q, $context = 'logement')
    {
        ob_start();

        if ($q->have_posts()) {
            echo '<div class="pc-grid pc-grid--cards">';
            while ($q->have_posts()) {
                $q->the_post();
                $pid = get_the_ID();
                $permalink = get_permalink($pid);
                $title = get_the_title($pid);
                $thumb = get_the_post_thumbnail($pid, 'medium_large', ['loading' => 'lazy', 'class' => 'pc-card__img']);

                echo '<article class="pc-card pc-card--' . esc_attr($context) . '">';
                echo $thumb ? $thumb : '';
                echo '<div class="pc-card__body">';
                echo '<h3 class="pc-card__title"><a href="' . esc_url($permalink) . '">' . esc_html($title) . '</a></h3>';

                if ($context === 'logement') {
                    $cap = class_exists('PCR_Fields') ? PCR_Fields::get('capacite', $pid) : get_post_meta($pid, 'capacite', true);
                    if ($cap) {
                        echo '<div class="pc-card__meta">' . esc_html($cap) . ' pers.</div>';
                    }
                }

                echo '</div></article>';
            }
            echo '</div>';
        } else {
            echo '<p class="pc-empty">Aucun résultat pour cette destination.</p>';
        }

        wp_reset_postdata();
        return ob_get_clean();
    }
}
