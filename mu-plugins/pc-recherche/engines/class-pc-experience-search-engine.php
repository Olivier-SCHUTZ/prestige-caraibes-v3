<?php

/**
 * Moteur de recherche spécifique pour les expériences
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Experience_Search_Engine extends PC_Search_Engine_Base
{
    /**
     * Retourne les filtres disponibles pour ce moteur
     */
    public function get_available_filters(): array
    {
        return [
            'category',
            'ville',
            'keyword',
            'participants',
            'prix_min',
            'prix_max',
            'page'
        ];
    }

    /**
     * Exécute la recherche principale avec filtres
     */
    public function search(array $filters): array
    {
        // 1. Assainissement et préparation des filtres
        $category     = isset($filters['category']) ? sanitize_text_field($filters['category']) : '';
        $ville        = isset($filters['ville']) ? sanitize_text_field($filters['ville']) : '';
        $keyword      = isset($filters['keyword']) ? sanitize_text_field($filters['keyword']) : '';
        $participants = isset($filters['participants']) ? intval($filters['participants']) : 1;
        $prix_min     = isset($filters['prix_min']) && is_numeric($filters['prix_min']) ? floatval($filters['prix_min']) : 0;
        $prix_max     = isset($filters['prix_max']) && is_numeric($filters['prix_max']) ? floatval($filters['prix_max']) : 99999;
        $page         = isset($filters['page']) ? intval($filters['page']) : 1;

        // 2. Construction de la WP_Query (Filtres SQL natifs)
        $args = [
            'post_type'      => 'experience',
            'post_status'    => 'publish',
            'posts_per_page' => -1, // On récupère tout pour filtrer en PHP ensuite (villes/tarifs complexes)
            's'              => $keyword
        ];

        if (!empty($category)) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'categorie_experience',
                    'field'    => 'slug',
                    'terms'    => $category
                ]
            ];
        }

        $meta_query = ['relation' => 'AND'];
        if ($participants > 1) {
            $meta_query[] = [
                'key'     => 'exp_capacite',
                'value'   => $participants,
                'compare' => '>=',
                'type'    => 'NUMERIC'
            ];
        }

        if (count($meta_query) > 1) {
            $args['meta_query'] = $meta_query;
        }

        $query = new WP_Query($args);
        $final_posts = [];

        // 3. Filtrage côté PHP (Villes spécifiques et Grilles de Tarifs ACF)
        if ($query->have_posts()) {
            foreach ($query->posts as $post) {
                $post_id = $post->ID;

                // Filtre Ville (sous-champ 'exp_lieux_horaires_depart')
                if (!empty($ville)) {
                    $ville_trouvee = false;
                    $lieux = class_exists('PCR_Fields') ? PCR_Fields::get('exp_lieux_horaires_depart', $post_id) : [];
                    if (is_array($lieux)) {
                        foreach ($lieux as $lieu) {
                            if (isset($lieu['exp_lieu_depart']) && $lieu['exp_lieu_depart'] === $ville) {
                                $ville_trouvee = true;
                                break;
                            }
                        }
                    }
                    if (!$ville_trouvee) {
                        continue; // Passe au post suivant
                    }
                }

                // Filtre Prix (Logique complexe sur les grilles 'exp_types_de_tarifs')
                $tarifs = class_exists('PCR_Fields') ? PCR_Fields::get('exp_types_de_tarifs', $post_id) : [];
                $base_price = null;

                if ($tarifs) {
                    foreach ($tarifs as $tarif) {
                        $prix_adulte = !empty($tarif['exp_tarif_adulte']) ? floatval($tarif['exp_tarif_adulte']) : null;
                        if ($prix_adulte !== null && ($base_price === null || $prix_adulte < $base_price)) {
                            if ($prix_adulte > 0) {
                                $base_price = $prix_adulte;
                            }
                        }
                    }
                }

                if (($prix_min > 0 || $prix_max < 99999) && ($base_price === null || $base_price < $prix_min || $base_price > $prix_max)) {
                    continue; // Hors budget
                }

                $final_posts[] = $post;
            }
        }

        // 4. Pagination et formatage via la classe parente
        // Attention: Les expériences s'affichent par groupe de 6, pas 9 !
        return $this->paginate_and_format_results($final_posts, $page, 6);
    }

    /**
     * Formate les vignettes spécifiques aux expériences
     */
    protected function format_items(array $posts): array
    {
        $vignettes_data = [];
        foreach ($posts as $post) {
            $post_id = $post->ID;

            // Appel à l'ancien Helper (temporairement) pour le label de prix
            $tarifs      = class_exists('PCR_Fields') ? PCR_Fields::get('exp_types_de_tarifs', $post_id) : [];
            $price_label = function_exists('pc_exp_get_vignette_price_label') ? pc_exp_get_vignette_price_label($tarifs) : '';

            // Récupération de la ville principale
            $lieux     = class_exists('PCR_Fields') ? PCR_Fields::get('exp_lieux_horaires_depart', $post_id) : [];
            $city_name = (!empty($lieux) && !empty($lieux[0])) ? ($lieux[0]['exp_lieu_depart'] ?? '') : '';

            $vignettes_data[] = [
                'id'    => $post_id,
                'title' => get_the_title($post_id),
                'link'  => get_permalink($post_id),
                'thumb' => get_the_post_thumbnail_url($post_id, 'medium_large'),
                'price' => $price_label,
                'city'  => $city_name,
            ];
        }
        return $vignettes_data;
    }

    /**
     * Formate les données pour la carte (uniquement si coordonnées présentes)
     */
    protected function format_map_data(array $posts): array
    {
        $map_data = [];
        foreach ($posts as $post) {
            $post_id = $post->ID;
            $lieux = class_exists('PCR_Fields') ? PCR_Fields::get('exp_lieux_horaires_depart', $post_id) : [];

            if (!empty($lieux) && !empty($lieux[0])) {
                $lat = isset($lieux[0]['lat_exp']) ? (float) $lieux[0]['lat_exp'] : null;
                $lng = isset($lieux[0]['longitude']) ? (float) $lieux[0]['longitude'] : null;

                if ($lat !== null && $lng !== null) {
                    $tarifs      = class_exists('PCR_Fields') ? PCR_Fields::get('exp_types_de_tarifs', $post_id) : [];
                    $price_label = function_exists('pc_exp_get_vignette_price_label') ? pc_exp_get_vignette_price_label($tarifs) : '';

                    $map_data[] = [
                        'id'    => $post_id,
                        'title' => get_the_title($post_id),
                        'link'  => get_permalink($post_id),
                        'thumb' => get_the_post_thumbnail_url($post_id, 'medium_large'),
                        'price' => $price_label,
                        'city'  => $lieux[0]['exp_lieu_depart'] ?? '',
                        'lat'   => $lat,
                        'lng'   => $lng,
                    ];
                }
            }
        }
        return $map_data;
    }
}
