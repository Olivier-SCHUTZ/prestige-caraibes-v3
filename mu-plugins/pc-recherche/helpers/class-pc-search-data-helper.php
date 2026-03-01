<?php

/**
 * Helper pour récupérer les données transversales (villes, équipements, catégories, etc.)
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Search_Data_Helper
{
    /**
     * LOGEMENTS : Récupère la liste des villes
     */
    public static function get_villes(): array
    {
        $out = [];
        $terms = get_terms(['taxonomy' => 'ville', 'hide_empty' => true]);
        if (!is_wp_error($terms) && !empty($terms)) {
            foreach ($terms as $t) {
                $out[$t->slug] = $t->name;
            }
        }
        if (empty($out)) {
            global $wpdb;
            $rows = $wpdb->get_col("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'ville' AND meta_value <> '' ORDER BY meta_value ASC");
            if ($rows) {
                foreach ($rows as $val) {
                    $out[sanitize_title($val)] = $val;
                }
            }
        }
        return $out;
    }

    /**
     * LOGEMENTS : Récupère la liste des équipements
     */
    public static function get_equipements(): array
    {
        $out = [];
        foreach (['equipement', 'amenity', 'amenities'] as $tax) {
            if (!taxonomy_exists($tax)) continue;
            $terms = get_terms(['taxonomy' => $tax, 'hide_empty' => true]);
            if (!is_wp_error($terms) && !empty($terms)) {
                foreach ($terms as $t) {
                    $out[] = ['slug' => $t->slug, 'name' => $t->name, 'tax'  => $tax];
                }
                break;
            }
        }
        return $out;
    }

    /**
     * EXPÉRIENCES : Récupère la liste des catégories
     */
    public static function get_experience_categories(): array
    {
        $categories = [];
        $terms = get_terms(['taxonomy' => 'categorie_experience', 'hide_empty' => true]);
        if (!is_wp_error($terms) && !empty($terms)) {
            foreach ($terms as $term) {
                $categories[$term->slug] = $term->name;
            }
        }
        return $categories;
    }

    /**
     * EXPÉRIENCES : Récupère la liste des villes de départ (optimisé avec Transient)
     */
    public static function get_experience_villes(): array
    {
        $villes_cache = get_transient('pc_exp_villes_list');
        if ($villes_cache !== false) {
            return $villes_cache;
        }

        $villes = [];
        $experience_ids = new WP_Query([
            'post_type'      => 'experience',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids'
        ]);

        if ($experience_ids->have_posts()) {
            foreach ($experience_ids->posts as $post_id) {
                if (have_rows('exp_lieux_horaires_depart', $post_id)) {
                    while (have_rows('exp_lieux_horaires_depart', $post_id)) {
                        the_row();
                        $lieu = get_sub_field('exp_lieu_depart');
                        if (!empty($lieu) && !in_array($lieu, $villes)) {
                            $villes[] = trim($lieu);
                        }
                    }
                }
            }
        }

        $villes = array_unique($villes);
        sort($villes);
        set_transient('pc_exp_villes_list', $villes, 12 * HOUR_IN_SECONDS);
        return $villes;
    }
}
