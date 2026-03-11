<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCR Housing Repository - Accès aux données des Logements
 * * Gère la lecture (liste, détails) et la suppression des logements en BDD.
 * Pattern Singleton.
 * * @since 2.0.0 (Refactoring)
 */
class PCR_Housing_Repository
{
    /**
     * Instance unique de la classe.
     * @var PCR_Housing_Repository|null
     */
    private static $instance = null;

    /**
     * Constructeur privé (Singleton).
     */
    private function __construct() {}

    /**
     * Récupère l'instance unique.
     * * @return PCR_Housing_Repository
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Retourne une liste légère des logements pour le tableau dashboard.
     * * @param array $args Arguments de requête (pagination, filtres)
     * @return array
     */
    public function get_housing_list($args = [])
    {
        $defaults = [
            'posts_per_page' => 20,
            'paged' => 1,
            'orderby' => 'title',
            'order' => 'ASC',
            's' => '',
            'meta_query' => [],
            'type_filter' => '',
            'status_filter' => '',
            'mode_filter' => '',
        ];

        $args = wp_parse_args($args, $defaults);
        $config = PCR_Housing_Config::get_instance();

        // --- LOGIQUE DE FILTRE PAR TYPE ---
        $post_types = ['villa', 'appartement'];

        if (!empty($args['type_filter']) && in_array($args['type_filter'], ['villa', 'appartement'])) {
            $post_types = [$args['type_filter']];
        }

        $query_args = [
            'post_type' => $post_types,
            'post_status' => ['publish', 'pending', 'draft', 'private', 'future'],
            'posts_per_page' => $args['posts_per_page'],
            'paged' => $args['paged'],
            'orderby' => $args['orderby'],
            'order' => $args['order'],
        ];

        // --- FILTRE STATUS ---
        if (!empty($args['status_filter']) && $args['status_filter'] !== 'all') {
            $query_args['post_status'] = $args['status_filter'];
        }

        // --- FILTRE SEARCH ---
        if (!empty($args['s'])) {
            $query_args['s'] = $args['s'];
        }

        // --- FILTRE META (MODE RESERVATION) ---
        $meta_query = $args['meta_query'];

        if (!empty($args['mode_filter'])) {
            $meta_query[] = [
                'key' => 'mode_reservation',
                'value' => $args['mode_filter'],
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
                    $item['capacite'] = get_field('capacite', $post_id) ?: 0;
                    $item['base_price_from'] = get_field('prix-a-partir-de-e-nuit-prix-de-base', $post_id) ?: 0;
                    $item['identifiant_lodgify'] = get_field('identifiant_lodgify', $post_id) ?: '';
                    $item['mode_reservation'] = get_field('mode_reservation', $post_id) ?: 'log_directe';

                    // Ville (peut venir du champ ACF ou de la taxonomie)
                    $ville_acf = get_field('ville', $post_id);
                    $ville_tax = '';
                    $terms = get_the_terms($post_id, 'destination');
                    if ($terms && !is_wp_error($terms)) {
                        $ville_tax = $terms[0]->name;
                    }
                    $item['ville'] = $ville_acf ?: $ville_tax;
                } else {
                    $item['capacite'] = 0;
                    $item['base_price_from'] = 0;
                    $item['identifiant_lodgify'] = '';
                    $item['mode_reservation'] = 'log_directe';
                    $item['ville'] = '';
                }

                // Statut formaté pour l'affichage (Utilisation de la Config)
                $item['status_label'] = $config->get_status_label($item['status']);
                $item['status_class'] = $config->get_status_class($item['status']);

                // Mode de réservation formaté (Utilisation de la Config)
                $item['mode_label'] = $config->get_mode_label($item['mode_reservation']);
                $item['mode_class'] = $config->get_mode_class($item['mode_reservation']);

                $list[] = $item;
            }
            wp_reset_postdata();
        }

        return [
            'success' => true,
            'items' => $list,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
            'current_page' => $args['paged'],
        ];
    }

    /**
     * Retourne les détails complets d'un logement avec tous les champs.
     * * @param int $post_id ID du post
     * @return array|false
     */
    public function get_housing_details($post_id)
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return false;
        }

        $post = get_post($post_id);
        if (!$post || !in_array($post->post_type, ['villa', 'appartement'])) {
            return false;
        }

        $details = [
            'id' => $post_id,
            'title' => $post->post_title,
            'slug' => $post->post_name,
            'status' => $post->post_status,
            'type' => $post->post_type,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'date_created' => $post->post_date,
            'date_modified' => $post->post_modified,
        ];

        $config = PCR_Housing_Config::get_instance();
        $formatter = PCR_Housing_Formatter::get_instance();

        // Chargement de tous les champs ACF mappés
        if (function_exists('get_field')) {
            $mapped_fields = $config->get_mapped_fields();
            $special_keys = $config->get_special_meta_keys();

            foreach ($mapped_fields as $normalized_key => $meta_key) {
                // Utilise la vraie meta_key si elle a un trait d'union
                $real_meta_key = $special_keys[$normalized_key] ?? $meta_key;

                $value = get_field($real_meta_key, $post_id);

                // Traitement spécifique selon le type de champ via le Formatter
                $details[$normalized_key] = $formatter->process_field_value($normalized_key, $value);
            }
        } else {
            // Fallback sans ACF : on charge ce qu'on peut via get_post_meta
            error_log('[PCR Housing Repository] ACF non disponible, fallback vers get_post_meta');
            $mapped_fields = $config->get_mapped_fields();

            foreach ($mapped_fields as $normalized_key => $meta_key) {
                $details[$normalized_key] = get_post_meta($post_id, $meta_key, true);
            }
        }

        // Image à la une
        $thumbnail_id = get_post_thumbnail_id($post_id);
        $details['featured_image'] = [
            'id' => $thumbnail_id,
            'url' => $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : '',
            'sizes' => $thumbnail_id ? wp_get_attachment_metadata($thumbnail_id)['sizes'] ?? [] : [],
        ];

        // Taxonomies
        $details['taxonomies'] = [];
        $taxonomies = get_object_taxonomies($post->post_type, 'objects');
        foreach ($taxonomies as $taxonomy) {
            $terms = get_the_terms($post_id, $taxonomy->name);
            $details['taxonomies'][$taxonomy->name] = [];
            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $details['taxonomies'][$taxonomy->name][] = [
                        'id' => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                    ];
                }
            }
        }

        return [
            'success' => true,
            'data' => $details,
        ];
    }

    // --- À AJOUTER DANS PCR_Housing_Repository ---

    public static function get_rates($post_id)
    {
        if (!$post_id) return ['seasons' => [], 'promos' => []];

        return [
            'seasons' => self::format_seasons(get_field('pc_season_blocks', $post_id)),
            'promos'  => self::format_promos(get_field('pc_promo_blocks', $post_id)),
        ];
    }

    public static function save_rates($post_id, $json_data)
    {
        if (!$post_id || empty($json_data)) return;

        $data = is_string($json_data) ? json_decode(stripslashes($json_data), true) : $json_data;
        if (!is_array($data)) return;

        if (isset($data['seasons'])) {
            $acf_seasons = array_map(function ($season) {
                return [
                    'season_name'            => sanitize_text_field($season['name']),
                    'season_price'           => floatval($season['price']),
                    'season_note'            => sanitize_textarea_field($season['note'] ?? ''),
                    'season_min_nights'      => intval($season['minNights'] ?? 0),
                    'season_extra_guest_fee' => floatval($season['guestFee'] ?? 0),
                    'season_extra_guest_from' => intval($season['guestFrom'] ?? 0),
                    'season_periods'         => array_map(function ($p) {
                        return ['date_from' => $p['start'], 'date_to' => $p['end']];
                    }, $season['periods'] ?? [])
                ];
            }, $data['seasons']);
            update_field('field_pc_season_blocks_20250826', $acf_seasons, $post_id);
        }

        if (isset($data['promos'])) {
            $acf_promos = array_map(function ($promo) {
                return [
                    'nom_de_la_promotion' => sanitize_text_field($promo['name']),
                    'promo_type'          => sanitize_text_field($promo['promo_type']),
                    'promo_value'         => floatval($promo['value']),
                    'promo_valid_until'   => sanitize_text_field($promo['validUntil'] ?? ''),
                    'promo_periods'       => array_map(function ($p) {
                        return ['date_from' => $p['start'], 'date_to' => $p['end']];
                    }, $promo['periods'] ?? [])
                ];
            }, $data['promos']);
            update_field('field_693425b17049d', $acf_promos, $post_id);
        }
    }

    // Helpers privés
    private static function format_seasons($acf_data)
    {
        if (!is_array($acf_data)) return [];
        return array_map(function ($row) {
            $periods = array_map(function ($p) {
                return ['start' => $p['date_from'], 'end' => $p['date_to']];
            }, $row['season_periods'] ?? []);
            return [
                'name' => $row['season_name'],
                'price' => $row['season_price'],
                'note' => $row['season_note'],
                'minNights' => $row['season_min_nights'],
                'guestFee' => $row['season_extra_guest_fee'],
                'guestFrom' => $row['season_extra_guest_from'],
                'periods' => $periods
            ];
        }, $acf_data);
    }

    private static function format_promos($acf_data)
    {
        if (!is_array($acf_data)) return [];
        return array_map(function ($row) {
            $periods = array_map(function ($p) {
                return ['start' => $p['date_from'], 'end' => $p['date_to']];
            }, $row['promo_periods'] ?? []);
            return [
                'name' => $row['nom_de_la_promotion'],
                'promo_type' => $row['promo_type'],
                'value' => $row['promo_value'],
                'validUntil' => $row['promo_valid_until'],
                'periods' => $periods
            ];
        }, $acf_data);
    }

    /**
     * Supprime définitivement un logement et toutes ses données associées.
     * * @param int $post_id ID du post à supprimer
     * @return array
     */
    public function delete_housing($post_id)
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return [
                'success' => false,
                'message' => 'ID de logement invalide.',
            ];
        }

        $post = get_post($post_id);
        if (!$post || !in_array($post->post_type, ['villa', 'appartement'])) {
            return [
                'success' => false,
                'message' => 'Logement introuvable.',
            ];
        }

        // Vérification des permissions
        if (!current_user_can('delete_post', $post_id)) {
            return [
                'success' => false,
                'message' => 'Permission insuffisante pour supprimer ce logement.',
            ];
        }

        try {
            $housing_title = $post->post_title;

            // Supprimer définitivement le post et toutes ses métadonnées
            $result = wp_delete_post($post_id, true);

            if (!$result) {
                throw new Exception('Échec de la suppression du logement.');
            }

            return [
                'success' => true,
                'message' => sprintf('Le logement "%s" a été supprimé définitivement.', $housing_title),
                'data' => [
                    'deleted_id' => $post_id,
                ],
            ];
        } catch (Exception $e) {
            error_log('[PCR Housing Repository] Erreur suppression : ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la suppression : ' . $e->getMessage(),
            ];
        }
    }
}
