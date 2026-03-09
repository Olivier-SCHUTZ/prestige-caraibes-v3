<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCR Experience Repository - Accès aux données des Expériences
 * Gère la lecture (liste, détails) et la suppression des expériences en BDD.
 * Inclut les boucliers anti-crash pour les repeaters et le Rate Manager.
 * Pattern Singleton.
 * * @since 2.0.0 (Refactoring)
 */
class PCR_Experience_Repository
{
    /**
     * Instance unique de la classe.
     * @var PCR_Experience_Repository|null
     */
    private static $instance = null;

    /**
     * Constructeur privé (Singleton).
     */
    private function __construct() {}

    /**
     * Récupère l'instance unique.
     * @return PCR_Experience_Repository
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Retourne une liste légère des expériences pour le tableau dashboard.
     * * @param array $args Arguments de requête (pagination, filtres)
     * @return array
     */
    public function get_experience_list($args = [])
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
        $config = PCR_Experience_Config::get_instance();

        $query_args = [
            'post_type' => 'experience',
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

        // --- FILTRE META (DISPONIBILITÉ) ---
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
                    $item['duree'] = get_field('exp_duree', $post_id) ?: '';
                    $item['capacite'] = get_field('exp_capacite', $post_id) ?: 0;
                    $item['availability'] = get_field('exp_availability', $post_id) ?: true;
                    $item['type_de_prestation'] = get_field('exp_type_de_prestation', $post_id) ?: '';
                    $item['zone_intervention'] = get_field('exp_zone_intervention', $post_id) ?: '';
                    $item['taux_tva'] = get_field('taux_tva', $post_id) ?: '';

                    $lieux = get_field('exp_lieux_horaires_depart', $post_id);
                    $item['lieu_depart'] = (is_array($lieux) && !empty($lieux) && !empty($lieux[0]['exp_lieu_depart'])) ? $lieux[0]['exp_lieu_depart'] : '';
                } else {
                    $item['duree'] = '';
                    $item['capacite'] = 0;
                    $item['availability'] = true;
                    $item['type_de_prestation'] = '';
                    $item['zone_intervention'] = '';
                    $item['taux_tva'] = '';
                    $item['lieu_depart'] = '';
                }

                $item['exp_availability'] = $item['availability'];

                // Statut formaté pour l'affichage (Utilisation de la Config)
                $item['status_label'] = $config->get_status_label($item['status']);
                $item['status_class'] = $config->get_status_class($item['status']);

                // Disponibilité formatée
                $item['availability_label'] = $item['exp_availability'] ? 'Disponible' : 'Non disponible';
                $item['availability_class'] = $item['exp_availability'] ? 'pc-status--available' : 'pc-status--unavailable';

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
     * Retourne les détails complets d'une expérience avec tous les champs mappés.
     * Version CORRIGÉE pour Rate Manager et Repeaters.
     * * @param int $post_id ID du post
     * @return array|false
     */
    public function get_experience_details($post_id)
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0) return false;

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'experience') return false;

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

        $config = PCR_Experience_Config::get_instance();
        $formatter = PCR_Experience_Formatter::get_instance();

        // Chargement via Mapping ACF
        if (function_exists('get_field')) {
            $mapped_fields = $config->get_mapped_fields();

            foreach ($mapped_fields as $normalized_key => $meta_key) {
                $value = get_field($meta_key, $post_id);
                $details[$normalized_key] = $formatter->process_field_value($normalized_key, $value);
            }
        } else {
            $mapped_fields = $config->get_mapped_fields();
            foreach ($mapped_fields as $normalized_key => $meta_key) {
                $details[$normalized_key] = get_post_meta($post_id, $meta_key, true);
            }
        }

        // =========================================================
        // 🔧 TRAITEMENT SPÉCIALISÉ DES IMAGES POUR L'AFFICHAGE
        // =========================================================

        $exp_hero_desktop = get_field('exp_hero_desktop', $post_id);
        $exp_hero_mobile = get_field('exp_hero_mobile', $post_id);
        $details['exp_hero_desktop'] = $formatter->process_image_for_display($exp_hero_desktop);
        $details['exp_hero_mobile'] = $formatter->process_image_for_display($exp_hero_mobile);

        $photos_experience = get_field('photos_experience', $post_id);
        $details['photos_experience'] = $formatter->process_gallery_for_display($photos_experience);

        // =========================================================
        // 🔧 SÉCURITÉ ANTI-CRASH : PROTECTION TARIFS & RATE MANAGER
        // =========================================================

        $seasons_data = get_field('seasons_data', $post_id);
        $promos_data = get_field('promos_data', $post_id);
        $details['seasons_data'] = (is_array($seasons_data)) ? $seasons_data : [];
        $details['promos_data'] = (is_array($promos_data)) ? $promos_data : [];

        $taux_tva_raw = get_field('taux_tva', $post_id);
        $details['taux_tva'] = ($taux_tva_raw !== false && $taux_tva_raw !== null) ? $taux_tva_raw : '';

        // CHAMP CRITIQUE : exp_types_de_tarifs
        $exp_types_de_tarifs_raw = get_field('exp_types_de_tarifs', $post_id);

        if (!is_array($exp_types_de_tarifs_raw) || empty($exp_types_de_tarifs_raw)) {
            error_log("🔧 [PCR Experience Repository] Création ligne de tarif par défaut pour expérience #{$post_id}");
            $details['exp_types_de_tarifs'] = [
                [
                    'exp_type' => 'unique',
                    'exp_type_custom' => '',
                    'exp_options_tarifaires' => [],
                    'exp-frais-fixes' => [],
                    'exp_tarifs_lignes' => []
                ]
            ];

            if (function_exists('update_field')) {
                $success = update_field('exp_types_de_tarifs', $details['exp_types_de_tarifs'], $post_id);
                if ($success) {
                    error_log("✅ [PCR Experience Repository] Ligne de tarif par défaut sauvegardée pour expérience #{$post_id}");
                }
            }
        } else {
            $details['exp_types_de_tarifs'] = $exp_types_de_tarifs_raw;
        }

        // RÈGLES DE PAIEMENT
        $details['pc_pay_mode'] = get_field('pc_pay_mode', $post_id) ?: 'acompte_plus_solde';
        $details['pc_deposit_type'] = get_field('pc_deposit_type', $post_id) ?: 'pourcentage';
        $details['pc_deposit_value'] = get_field('pc_deposit_value', $post_id) ?: '';
        $details['pc_balance_delay_days'] = get_field('pc_balance_delay_days', $post_id) ?: '';
        $details['pc_caution_amount'] = get_field('pc_caution_amount', $post_id) ?: '';
        $details['pc_caution_mode'] = get_field('pc_caution_mode', $post_id) ?: 'aucune';

        // SÉCURISATION COMPLÈTE DES REPEATERS
        $repeaters_to_secure = [
            'exp_lieux_horaires_depart',
            'exp_periodes_fermeture',
            'exp_faq',
            'exp_types_de_tarifs',
            'exp_accessibilite',
            'exp_periode',
            'exp_jour',
            'photos_experience',
            'exp_a_prevoir',
            'exp_delai_de_reservation',
            'exp_zone_intervention',
            'seasons_data',
            'promos_data'
        ];

        foreach ($repeaters_to_secure as $field_key) {
            if (empty($details[$field_key]) || !is_array($details[$field_key])) {
                $details[$field_key] = [];
            }
        }

        // =========================================================

        $thumbnail_id = get_post_thumbnail_id($post_id);
        $details['featured_image'] = [
            'id' => $thumbnail_id,
            'url' => $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : '',
            'sizes' => $thumbnail_id ? wp_get_attachment_metadata($thumbnail_id)['sizes'] ?? [] : [],
        ];

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

    /**
     * Supprime définitivement une expérience et toutes ses données associées.
     * * @param int $post_id ID du post à supprimer
     * @return array
     */
    public function delete_experience($post_id)
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return [
                'success' => false,
                'message' => 'ID d\'expérience invalide.',
            ];
        }

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'experience') {
            return [
                'success' => false,
                'message' => 'Expérience introuvable.',
            ];
        }

        // Vérification des permissions
        if (!current_user_can('delete_post', $post_id)) {
            return [
                'success' => false,
                'message' => 'Permission insuffisante pour supprimer cette expérience.',
            ];
        }

        try {
            $experience_title = $post->post_title;
            $result = wp_delete_post($post_id, true);

            if (!$result) {
                throw new Exception('Échec de la suppression de l\'expérience.');
            }

            return [
                'success' => true,
                'message' => sprintf('L\'expérience "%s" a été supprimée définitivement.', $experience_title),
                'data' => [
                    'deleted_id' => $post_id,
                ],
            ];
        } catch (Exception $e) {
            error_log('[PCR Experience Repository] Erreur suppression : ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la suppression : ' . $e->getMessage(),
            ];
        }
    }
}
