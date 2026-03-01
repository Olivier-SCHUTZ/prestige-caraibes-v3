<?php

/**
 * Moteur de recherche spécifique pour les logements (Villas, Appartements)
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Logement_Search_Engine extends PC_Search_Engine_Base
{
    /**
     * Retourne les filtres disponibles pour ce moteur
     */
    public function get_available_filters(): array
    {
        return [
            'page',
            'ville',
            'date_arrivee',
            'date_depart',
            'invites',
            'chambres',
            'sdb',
            'prix_min',
            'prix_max',
            'theme'
        ];
    }

    /**
     * Exécute la recherche principale avec filtres
     */
    public function search(array $filters): array
    {
        // 1. Assainissement et préparation des filtres
        $paged        = isset($filters['page']) ? max(1, intval($filters['page'])) : 1;
        $ville        = isset($filters['ville']) ? sanitize_text_field($filters['ville']) : '';
        $date_arrivee = isset($filters['date_arrivee']) ? sanitize_text_field($filters['date_arrivee']) : '';
        $date_depart  = isset($filters['date_depart'])  ? sanitize_text_field($filters['date_depart'])  : '';
        $invites      = isset($filters['invites']) ? max(1, intval($filters['invites'])) : 1;
        $chambres     = isset($filters['chambres']) ? max(0, intval($filters['chambres'])) : 0;
        $sdb          = isset($filters['sdb'])      ? max(0, intval($filters['sdb']))      : 0;
        $prix_min     = isset($filters['prix_min']) && $filters['prix_min'] !== '' ? max(0, intval($filters['prix_min'])) : 0;
        $prix_max     = isset($filters['prix_max']) && $filters['prix_max'] !== '' ? max(0, intval($filters['prix_max'])) : 1000;
        $theme        = isset($filters['theme']) ? sanitize_text_field($filters['theme']) : '';

        // 2. Construction de la WP_Query (Filtres SQL natifs)
        $meta_query = ['relation' => 'AND'];
        $meta_query[] = ['key' => 'capacite', 'value' => $invites, 'compare' => '>=', 'type' => 'NUMERIC'];

        if ($chambres > 0) {
            $meta_query[] = ['key' => 'nombre_de_chambres', 'value' => $chambres, 'compare' => '>=', 'type' => 'NUMERIC'];
        }
        if ($sdb > 0) {
            $meta_query[] = ['key' => 'nombre_sdb', 'value' => $sdb, 'compare' => '>=', 'type' => 'NUMERIC'];
        }
        if ($prix_min > 0 || $prix_max < 1000) {
            $meta_query[] = ['key' => 'base_price_from', 'value' => [$prix_min, $prix_max], 'compare' => 'BETWEEN', 'type' => 'NUMERIC'];
        }
        if (!empty($theme)) {
            $meta_query[] = ['key' => 'highlights', 'value' => '"' . $theme . '"', 'compare' => 'LIKE'];
        }

        $args = [
            'post_type'      => ['logement', 'villa', 'appartement'],
            'posts_per_page' => -1, // On récupère tout pour filtrer en PHP ensuite (villes/dates)
            'meta_query'     => $meta_query,
        ];

        $query = new WP_Query($args);
        $final_posts = [];

        // 3. Filtrage côté PHP (Villes et Dates)
        if ($query->have_posts()) {
            $ville_recherchee_sanitized = $ville ? sanitize_title($ville) : '';

            // Préparation des dates demandées si présentes
            $dates_demandees = [];
            if ($date_arrivee && $date_depart) {
                try {
                    $period = new DatePeriod(new DateTime($date_arrivee), new DateInterval('P1D'), new DateTime($date_depart));
                    foreach ($period as $date) {
                        $dates_demandees[] = $date->format('Y-m-d');
                    }
                } catch (Exception $e) {
                    // Date invalide, on ignore
                    $dates_demandees = [];
                }
            }

            foreach ($query->posts as $post) {
                $post_id = $post->ID;

                // Filtre Ville
                if ($ville) {
                    $ville_du_logement = get_field('ville', $post_id);
                    if (empty($ville_du_logement) || sanitize_title(remove_accents($ville_du_logement)) !== $ville_recherchee_sanitized) {
                        continue;
                    }
                }

                // Filtre Dates (Disponibilité)
                if (!empty($dates_demandees)) {
                    $dates_reservees = get_post_meta($post_id, '_booked_dates_cache', true);
                    if (!empty($dates_reservees) && is_array($dates_reservees) && !empty(array_intersect($dates_demandees, $dates_reservees))) {
                        continue; // Indisponible
                    }
                }

                $final_posts[] = $post;
            }
        }

        // 4. Pagination et formatage via la classe parente
        return $this->paginate_and_format_results($final_posts, $paged, 9);
    }

    /**
     * Formate les vignettes
     */
    protected function format_items(array $posts): array
    {
        $vignettes_data = [];
        foreach ($posts as $post) {
            // Récupération des statistiques d'avis
            $stats = function_exists('pc_rev_get_internal_stats')
                ? pc_rev_get_internal_stats($post->ID)
                : ['avg' => 0, 'count' => 0];

            $vignettes_data[] = [
                'id'           => $post->ID,
                'title'        => get_the_title($post->ID),
                'link'         => get_permalink($post->ID),
                'thumb'        => get_the_post_thumbnail_url($post->ID, 'medium_large') ?: '',
                'price'        => get_field('base_price_from', $post->ID),
                'city'         => get_field('ville', $post->ID),
                'rating_avg'   => $stats['avg'] ?? 0,
                'rating_count' => $stats['count'] ?? 0,
            ];
        }
        return $vignettes_data;
    }

    /**
     * Formate les données pour la carte interactive
     */
    protected function format_map_data(array $posts): array
    {
        $map_data = [];
        foreach ($posts as $post) {
            $map_data[] = [
                'title'     => get_the_title($post->ID),
                'price'     => get_field('base_price_from', $post->ID),
                'latitude'  => (float) get_field('latitude', $post->ID),
                'longitude' => (float) get_field('longitude', $post->ID),
                'link'      => get_permalink($post->ID),
            ];
        }
        return $map_data;
    }
}
