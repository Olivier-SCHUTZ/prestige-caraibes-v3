<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCR Experience List - Gestion des listes et requêtes
 * 
 * Gère uniquement les requêtes WP_Query pour le tableau dashboard.
 * Filtres, pagination, tri, et formatting des données de liste.
 * 
 * @since 0.2.0
 */
class PCR_Experience_List
{
    /**
     * Retourne une liste légère des expériences pour le tableau dashboard.
     * 
     * @param array $args Arguments de requête (pagination, filtres)
     * @return array
     */
    public static function get_experiences($args = [])
    {
        $defaults = [
            'posts_per_page' => 20,
            'paged' => 1,
            'orderby' => 'title',
            'order' => 'ASC',
            's' => '',
            'meta_query' => [],
            'status_filter' => '',
            'availability_filter' => '',
        ];

        $args = wp_parse_args($args, $defaults);

        $query_args = [
            'post_type' => 'experience',
            'post_status' => ['publish', 'pending', 'draft', 'private', 'future'],
            'posts_per_page' => $args['posts_per_page'],
            'paged' => $args['paged'],
            'orderby' => $args['orderby'],
            'order' => $args['order'],
        ];

        // Filtre par statut
        if (!empty($args['status_filter']) && $args['status_filter'] !== 'all') {
            $query_args['post_status'] = $args['status_filter'];
        }

        // Filtre de recherche
        if (!empty($args['s'])) {
            $query_args['s'] = $args['s'];
        }

        // Filtre meta (disponibilité)
        $meta_query = $args['meta_query'];

        if (!empty($args['availability_filter'])) {
            $meta_query[] = [
                'key' => 'exp_availability',
                'value' => $args['availability_filter'],
                'compare' => '='
            ];
        }

        if (!empty($meta_query)) {
            $query_args['meta_query'] = $meta_query;
        }

        $query = new WP_Query($query_args);

        $list = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                $item = self::format_experience_item($post_id);
                if ($item) {
                    $list[] = $item;
                }
            }
            wp_reset_postdata();
        }

        return [
            'success' => true,
            'data' => [
                'experiences' => $list,
                'pagination' => [
                    'total' => $query->found_posts,
                    'total_pages' => $query->max_num_pages,
                    'current_page' => $args['paged'],
                    'per_page' => $args['posts_per_page'],
                ]
            ]
        ];
    }

    /**
     * Formate un élément d'expérience pour l'affichage dans le tableau.
     * 
     * @param int $post_id ID du post
     * @return array|null Item formaté ou null en cas d'erreur
     */
    private static function format_experience_item($post_id)
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return null;
        }

        // Données de base
        $item = [
            'id' => $post_id,
            'title' => get_the_title(),
            'slug' => get_post_field('post_name', $post_id),
            'status' => get_post_status(),
            'type' => get_post_type(),
            'edit_url' => admin_url('post.php?post=' . $post_id . '&action=edit'),
            'view_url' => get_permalink($post_id),
        ];

        // Image à la une
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if ($thumbnail_id) {
            $item['image'] = [
                'id' => $thumbnail_id,
                'url' => wp_get_attachment_image_src($thumbnail_id, 'medium')[0] ?? '',
                'thumbnail' => wp_get_attachment_image_src($thumbnail_id, 'thumbnail')[0] ?? '',
            ];
        } else {
            $item['image'] = [
                'id' => 0,
                'url' => '',
                'thumbnail' => '',
            ];
        }

        // Champs critiques via ACF
        if (function_exists('get_field')) {
            $item['capacite'] = get_field('exp_capacite', $post_id) ?: 0;
            $item['duree'] = get_field('exp_duree', $post_id) ?: 0;
            $item['availability'] = get_field('exp_availability', $post_id) ?: 'InStock';

            // Récupération du premier tarif si disponible
            $tarifs = get_field('exp_types_de_tarifs', $post_id);
            $item['prix_base'] = 0;
            if (is_array($tarifs) && !empty($tarifs)) {
                $premier_tarif = $tarifs[0];
                if (isset($premier_tarif['exp_tarifs_lignes']) && is_array($premier_tarif['exp_tarifs_lignes'])) {
                    foreach ($premier_tarif['exp_tarifs_lignes'] as $ligne) {
                        if (isset($ligne['tarif_valeur']) && $ligne['tarif_valeur'] > 0) {
                            $item['prix_base'] = (float) $ligne['tarif_valeur'];
                            break;
                        }
                    }
                }
            }

            // Catégories (si taxonomie existe)
            $categories = get_the_terms($post_id, 'experience-category');
            if ($categories && !is_wp_error($categories)) {
                $item['categories'] = array_map(function ($term) {
                    return [
                        'id' => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                    ];
                }, $categories);
            } else {
                $item['categories'] = [];
            }
        } else {
            // Fallback sans ACF
            $item['capacite'] = 0;
            $item['duree'] = 0;
            $item['availability'] = 'InStock';
            $item['prix_base'] = 0;
            $item['categories'] = [];
        }

        // Labels formatés pour l'affichage
        $item['status_label'] = self::get_status_label($item['status']);
        $item['status_class'] = self::get_status_class($item['status']);
        $item['availability_label'] = self::get_availability_label($item['availability']);
        $item['availability_class'] = self::get_availability_class($item['availability']);

        // Formatage des données numériques
        $item['duree_formatted'] = self::format_duration($item['duree']);
        $item['capacite_formatted'] = self::format_capacity($item['capacite']);
        $item['prix_formatted'] = self::format_price($item['prix_base']);

        return $item;
    }

    /**
     * Retourne le label d'affichage pour un statut de post.
     * 
     * @param string $status
     * @return string
     */
    private static function get_status_label($status)
    {
        $labels = [
            'publish' => 'Publié',
            'pending' => 'En attente',
            'draft' => 'Brouillon',
            'private' => 'Privé',
            'future' => 'Programmé',
            'trash' => 'Corbeille',
        ];

        return $labels[$status] ?? ucfirst($status);
    }

    /**
     * Retourne la classe CSS pour un statut de post.
     * 
     * @param string $status
     * @return string
     */
    private static function get_status_class($status)
    {
        $classes = [
            'publish' => 'pc-status--published',
            'pending' => 'pc-status--pending',
            'draft' => 'pc-status--draft',
            'private' => 'pc-status--private',
            'future' => 'pc-status--future',
            'trash' => 'pc-status--trash',
        ];

        return $classes[$status] ?? 'pc-status--unknown';
    }

    /**
     * Retourne le label d'affichage pour la disponibilité.
     * 
     * @param string $availability
     * @return string
     */
    private static function get_availability_label($availability)
    {
        $choices = PCR_Experience_Data_Mapper::get_field_choices();
        $availability_choices = $choices['exp_availability'] ?? [];

        return $availability_choices[$availability] ?? 'Réservable actuellement';
    }

    /**
     * Retourne la classe CSS pour la disponibilité.
     * 
     * @param string $availability
     * @return string
     */
    private static function get_availability_class($availability)
    {
        $classes = [
            'InStock' => 'pc-availability--in-stock',
            'SoldOut' => 'pc-availability--sold-out',
            'PreOrder' => 'pc-availability--pre-order',
        ];

        return $classes[$availability] ?? 'pc-availability--in-stock';
    }

    /**
     * Formate la durée pour l'affichage.
     * 
     * @param float $duree Durée en heures
     * @return string Durée formatée
     */
    private static function format_duration($duree)
    {
        if ($duree <= 0) {
            return '-';
        }

        if ($duree == (int) $duree) {
            // Nombre entier
            return $duree . 'h';
        } else {
            // Nombre décimal (ex: 2.5h)
            return number_format($duree, 1) . 'h';
        }
    }

    /**
     * Formate la capacité pour l'affichage.
     * 
     * @param int $capacite Nombre de personnes
     * @return string Capacité formatée
     */
    private static function format_capacity($capacite)
    {
        if ($capacite <= 0) {
            return '-';
        }

        return $capacite . ' pers' . ($capacite > 1 ? '.' : '');
    }

    /**
     * Formate le prix pour l'affichage.
     * 
     * @param float $prix Prix en euros
     * @return string Prix formaté
     */
    private static function format_price($prix)
    {
        if ($prix <= 0) {
            return 'Sur devis';
        }

        return number_format($prix, 0, ',', ' ') . ' €';
    }

    /**
     * Retourne les options de tri disponibles.
     * 
     * @return array Options de tri
     */
    public static function get_sort_options()
    {
        return [
            'title_asc' => [
                'label' => 'Nom A-Z',
                'orderby' => 'title',
                'order' => 'ASC',
            ],
            'title_desc' => [
                'label' => 'Nom Z-A',
                'orderby' => 'title',
                'order' => 'DESC',
            ],
            'date_desc' => [
                'label' => 'Plus récent',
                'orderby' => 'date',
                'order' => 'DESC',
            ],
            'date_asc' => [
                'label' => 'Plus ancien',
                'orderby' => 'date',
                'order' => 'ASC',
            ],
            'modified_desc' => [
                'label' => 'Dernière modification',
                'orderby' => 'modified',
                'order' => 'DESC',
            ],
        ];
    }

    /**
     * Retourne les options de filtre par statut.
     * 
     * @return array Options de filtre
     */
    public static function get_status_filter_options()
    {
        return [
            'all' => 'Tous les statuts',
            'publish' => 'Publié',
            'pending' => 'En attente',
            'draft' => 'Brouillon',
            'private' => 'Privé',
            'future' => 'Programmé',
        ];
    }

    /**
     * Retourne les options de filtre par disponibilité.
     * 
     * @return array Options de filtre
     */
    public static function get_availability_filter_options()
    {
        $choices = PCR_Experience_Data_Mapper::get_field_choices();
        $availability_choices = $choices['exp_availability'] ?? [];

        return array_merge(['all' => 'Toutes disponibilités'], $availability_choices);
    }

    /**
     * Effectue une recherche rapide dans les expériences.
     * 
     * @param string $search_term Terme de recherche
     * @param int $limit Limite de résultats
     * @return array Résultats de recherche
     */
    public static function quick_search($search_term, $limit = 10)
    {
        if (empty($search_term) || strlen($search_term) < 2) {
            return [
                'success' => true,
                'items' => [],
            ];
        }

        $query_args = [
            'post_type' => 'experience',
            'post_status' => ['publish', 'pending', 'draft', 'private'],
            'posts_per_page' => $limit,
            's' => sanitize_text_field($search_term),
            'orderby' => 'relevance',
        ];

        $query = new WP_Query($query_args);
        $results = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                $results[] = [
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'status' => get_post_status(),
                    'edit_url' => admin_url('post.php?post=' . $post_id . '&action=edit'),
                ];
            }
            wp_reset_postdata();
        }

        return [
            'success' => true,
            'items' => $results,
        ];
    }
}
