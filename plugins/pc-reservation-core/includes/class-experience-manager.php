<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCR Experience Manager - Gestion des Expériences
 * 
 * Bridge Pattern vers les champs ACF existants sans créer de nouvelles tables.
 * Compatible avec l'architecture App Shell et le design Glassmorphisme.
 * 
 * Refonte de PCR_Housing_Manager vers PCR_Experience_Manager basée sur le JSON fourni.
 * 
 * @since 0.2.0
 */
class PCR_Experience_Manager
{
    /**
     * Initialisation des hooks.
     */
    public static function init()
    {
        // Hook d'initialisation si nécessaire
    }

    /**
     * 🔧 SOLUTION CRUCIALE : Retourne les FIELD KEYS ACF réels pour chaque champ d'expérience.
     * Ces clés sont nécessaires pour que update_field() fonctionne correctement.
     * 
     * @return array Mapping [normalized_key => field_key_acf]
     */
    private static function get_acf_field_keys()
    {
        return [
            // === IMAGES & MÉDIAS ===
            'exp_hero_desktop' => 'field_exp_hero_desktop',
            'exp_hero_mobile' => 'field_exp_hero_mobile',
            'photos_experience' => 'field_photos_experience',

            // === GÉNÉRAL ===
            'exp_h1_custom' => 'field_exp_h1_custom',
            'exp_exclude_sitemap' => 'field_exp_exclude_sitemap',
            'exp_http_410' => 'field_exp_http_410',
            'exp_availability' => 'field_exp_availability',

            // === SEO ===
            'exp_meta_titre' => 'field_exp_meta_titre',
            'exp_meta_description' => 'field_exp_meta_description',
            'exp_meta_canonical' => 'field_exp_meta_canonical',
            'exp_meta_robots' => 'field_exp_meta_robots',

            // === RELATIONS ===
            'exp_logements_recommandes' => 'field_exp_logements_recommandes',

            // === DÉTAILS DE L'EXPÉRIENCE ===
            'exp_duree' => 'field_exp_duree',
            'exp_capacite' => 'field_exp_capacite',
            'exp_age_minimum' => 'field_exp_age_minimum',
            'exp_accessibilite' => 'field_exp_accessibilite',
            'exp_periode' => 'field_exp_periode',
            'exp_jour' => 'field_exp_jour',

            // === LIEUX & HORAIRES (Repeater) ===
            'exp_lieux_horaires_depart' => 'field_exp_lieux_horaires_depart',

            // === PÉRIODES DE FERMETURE (Repeater) ===
            'exp_periodes_fermeture' => 'field_exp_periodes_fermeture',

            // === INCLUSIONS & EXCLUSIONS ===
            'exp_prix_comprend' => 'field_exp_prix_comprend',
            'exp_prix_ne_comprend_pas' => 'field_exp_prix_ne_comprend_pas',
            'exp_a_prevoir' => 'field_exp_a_prevoir',

            // === SERVICES ===
            'exp_delai_de_reservation' => 'field_exp_delai_de_reservation',
            'exp_zone_intervention' => 'field_exp_zone_intervention',
            'exp_type_de_prestation' => 'field_exp_type_de_prestation',
            'exp_heure_limite_de_commande' => 'field_exp_heure_limite_de_commande',

            // === TARIFS (Repeater Complexe) ===
            'exp_types_de_tarifs' => 'field_exp_types_de_tarifs',

            // === PAIEMENT (Compatible avec Housing) ===
            'taux_tva' => 'field_taux_tva',
            'pc_pay_mode' => 'field_6919e7994db4b',
            'pc_deposit_type' => 'field_6919e7994db4c',
            'pc_deposit_value' => 'field_6919e7994db4d',
            'pc_balance_delay_days' => 'field_6919e7994db4e',
            'pc_caution_amount' => 'field_6919e7994db4f',
        ];
    }

    /**
     * Sanitise la valeur d'un champ avant sauvegarde.
     * 
     * @param string $field_key Clé du champ
     * @param mixed $value Valeur à sanitiser
     * @return mixed Valeur sanitisée
     */
    private static function sanitize_field_value($field_key, $value)
    {
        // Traitement spécifique selon le champ d'expérience
        switch ($field_key) {
            case 'exp_prix_comprend':
            case 'exp_prix_ne_comprend_pas':
            case 'exp_a_prevoir':
                // Champs HTML longs
                return wp_kses_post($value);

            case 'exp_h1_custom':
            case 'exp_meta_titre':
            case 'exp_meta_description':
            case 'exp_delai_de_reservation':
            case 'exp_zone_intervention':
            case 'exp_type_de_prestation':
            case 'exp_heure_limite_de_commande':
                // Champs texte standards
                return sanitize_text_field($value);

            case 'exp_duree':
            case 'exp_capacite':
            case 'exp_age_minimum':
            case 'taux_tva':
            case 'pc_deposit_value':
            case 'pc_balance_delay_days':
            case 'pc_caution_amount':
                // Champs numériques
                return is_numeric($value) ? (float) $value : 0;

            case 'exp_exclude_sitemap':
            case 'exp_http_410':
            case 'exp_availability':
                // Champs booléens
                if ($value === "1" || $value === 1 || $value === true || $value === 'true') {
                    return true;
                }
                if ($value === "0" || $value === 0 || $value === false || $value === 'false' || $value === '') {
                    return false;
                }
                return (bool) $value;

            case 'exp_meta_canonical':
                // URLs
                return esc_url_raw($value);

            case 'exp_logements_recommandes':
                // Champ relationship
                if (is_array($value)) {
                    return array_map('intval', array_filter($value));
                }
                return [];

            case 'exp_accessibilite':
            case 'exp_periode':
            case 'exp_jour':
                // Champs checkbox/select multiple
                if (is_array($value)) {
                    return array_map('sanitize_text_field', $value);
                }
                return [];

            case 'exp_lieux_horaires_depart':
            case 'exp_periodes_fermeture':
            case 'exp_types_de_tarifs':
                // Champs complexes (Repeaters) : laisser ACF gérer la sanitisation
                return $value;

            case 'exp_hero_desktop':
            case 'exp_hero_mobile':
            case 'photos_experience':
                // Champs images : traitement spécial si nécessaire
                return $value;

            default:
                // Champs texte par défaut
                return sanitize_text_field($value);
        }
    }

    /**
     * Retourne le mapping complet des champs ACF d'expérience vers des clés normalisées.
     * 
     * IMPORTANT: Utilise STRICTEMENT les clés du JSON Experience fourni.
     * 
     * @return array Mapping [cle_normalisee => meta_key_acf]
     */
    public static function get_mapped_fields()
    {
        return [
            // === GÉNÉRAL ===
            'exp_h1_custom' => 'exp_h1_custom',
            'exp_exclude_sitemap' => 'exp_exclude_sitemap',
            'exp_http_410' => 'exp_http_410',
            'exp_availability' => 'exp_availability',

            // === SEO ===
            'exp_meta_titre' => 'exp_meta_titre',
            'exp_meta_description' => 'exp_meta_description',
            'exp_meta_canonical' => 'exp_meta_canonical',
            'exp_meta_robots' => 'exp_meta_robots',

            // === RELATIONS ===
            'exp_logements_recommandes' => 'exp_logements_recommandes',

            // === DÉTAILS DE L'EXPÉRIENCE ===
            'exp_duree' => 'exp_duree',
            'exp_capacite' => 'exp_capacite',
            'exp_age_minimum' => 'exp_age_minimum',
            'exp_accessibilite' => 'exp_accessibilite',
            'exp_periode' => 'exp_periode',
            'exp_jour' => 'exp_jour',

            // === LIEUX & HORAIRES (Repeater) ===
            'exp_lieux_horaires_depart' => 'exp_lieux_horaires_depart',

            // === PÉRIODES DE FERMETURE (Repeater) ===
            'exp_periodes_fermeture' => 'exp_periodes_fermeture',

            // === INCLUSIONS & EXCLUSIONS ===
            'exp_prix_comprend' => 'exp_prix_comprend',
            'exp_prix_ne_comprend_pas' => 'exp_prix_ne_comprend_pas',
            'exp_a_prevoir' => 'exp_a_prevoir',

            // === SERVICES ===
            'exp_delai_de_reservation' => 'exp_delai_de_reservation',
            'exp_zone_intervention' => 'exp_zone_intervention',
            'exp_type_de_prestation' => 'exp_type_de_prestation',
            'exp_heure_limite_de_commande' => 'exp_heure_limite_de_commande',

            // === IMAGES & MÉDIAS ===
            'exp_hero_desktop' => 'exp_hero_desktop',
            'exp_hero_mobile' => 'exp_hero_mobile',
            'photos_experience' => 'photos_experience',

            // === TARIFS (Repeater Complexe) ===
            'exp_types_de_tarifs' => 'exp_types_de_tarifs',

            // === PAIEMENT (Champs compatibles avec Housing) ===
            'taux_tva' => 'taux_tva',
            'pc_pay_mode' => 'pc_pay_mode',
            'pc_deposit_type' => 'pc_deposit_type',
            'pc_deposit_value' => 'pc_deposit_value',
            'pc_balance_delay_days' => 'pc_balance_delay_days',
            'pc_caution_amount' => 'pc_caution_amount',
        ];
    }

    /**
     * Retourne une liste légère des expériences pour le tableau dashboard.
     * 
     * @param array $args Arguments de requête (pagination, filtres)
     * @return array
     */
    public static function get_experience_list($args = [])
    {
        $defaults = [
            'posts_per_page' => 20,
            'paged' => 1,
            'orderby' => 'title',
            'order' => 'ASC',
            's' => '',
            'meta_query' => [],
            // Nouveaux filtres par défaut
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

        // --- FILTRE STATUS ---
        if (!empty($args['status_filter']) && $args['status_filter'] !== 'all') {
            $query_args['post_status'] = $args['status_filter'];
        }

        // --- FILTRE SEARCH ---
        if (!empty($args['s'])) {
            $query_args['s'] = $args['s'];
        }

        // --- FILTRE META (DISPONIBILITÉ) ---
        $meta_query = $args['meta_query']; // Récupère ceux déjà passés

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
                    $item['exp_duree'] = get_field('exp_duree', $post_id) ?: '';
                    $item['exp_capacite'] = get_field('exp_capacite', $post_id) ?: 0;
                    $item['exp_availability'] = get_field('exp_availability', $post_id) ?: true;
                    $item['exp_type_de_prestation'] = get_field('exp_type_de_prestation', $post_id) ?: '';

                    // Zone d'intervention
                    $item['exp_zone_intervention'] = get_field('exp_zone_intervention', $post_id) ?: '';
                } else {
                    $item['exp_duree'] = '';
                    $item['exp_capacite'] = 0;
                    $item['exp_availability'] = true;
                    $item['exp_type_de_prestation'] = '';
                    $item['exp_zone_intervention'] = '';
                }

                // Statut formaté pour l'affichage
                $item['status_label'] = self::get_status_label($item['status']);
                $item['status_class'] = self::get_status_class($item['status']);

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
     * 
     * @param int $post_id ID du post
     * @return array|false
     */
    public static function get_experience_details($post_id)
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return false;
        }

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'experience') {
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

        // Chargement de tous les champs ACF mappés
        if (function_exists('get_field')) {
            $mapped_fields = self::get_mapped_fields();

            foreach ($mapped_fields as $normalized_key => $meta_key) {
                $value = get_field($meta_key, $post_id);

                // Traitement spécifique selon le type de champ
                $details[$normalized_key] = self::process_field_value($normalized_key, $value);
            }
        } else {
            // Fallback sans ACF : on charge ce qu'on peut via get_post_meta
            error_log('[PCR Experience Manager] ACF non disponible, fallback vers get_post_meta');
            $mapped_fields = self::get_mapped_fields();

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

    /**
     * Met à jour une expérience avec les données fournies.
     * NOUVEAU : Accepte ID = 0 pour créer une nouvelle expérience.
     * 
     * @param int $post_id ID du post (0 pour création)
     * @param array $data Données à mettre à jour
     * @return array
     */
    public static function update_experience($post_id, $data)
    {
        $post_id = (int) $post_id;
        $is_creation = ($post_id === 0);

        if (!$is_creation) {
            // Mode édition : validation normale
            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'experience') {
                return [
                    'success' => false,
                    'message' => 'Expérience introuvable.',
                ];
            }

            // Vérification des permissions pour édition
            if (!current_user_can('edit_post', $post_id)) {
                return [
                    'success' => false,
                    'message' => 'Permission insuffisante.',
                ];
            }
        } else {
            // Mode création : validation du type de post
            if (empty($data['post_type']) || $data['post_type'] !== 'experience') {
                return [
                    'success' => false,
                    'message' => 'Type de post invalide. Doit être "experience".',
                ];
            }

            // Vérification des permissions pour création
            if (!current_user_can('publish_posts')) {
                return [
                    'success' => false,
                    'message' => 'Permission insuffisante pour créer une expérience.',
                ];
            }

            // Validation du titre obligatoire
            if (empty($data['title'])) {
                return [
                    'success' => false,
                    'message' => 'Le nom de l\'expérience est obligatoire.',
                ];
            }
        }

        try {
            // 1. Création ou mise à jour des données de base du post
            if ($is_creation) {
                // NOUVELLE LOGIQUE : Créer le post
                $new_post_data = [
                    'post_type' => 'experience',
                    'post_title' => sanitize_text_field($data['title']),
                    'post_content' => wp_kses_post($data['content'] ?? ''),
                    'post_excerpt' => wp_kses_post($data['excerpt'] ?? ''),
                    'post_status' => in_array($data['status'] ?? 'draft', ['publish', 'pending', 'draft', 'private'])
                        ? sanitize_text_field($data['status']) : 'draft',
                    'post_author' => get_current_user_id(),
                ];

                // Générer un slug si nécessaire
                if (!empty($data['slug'])) {
                    $new_post_data['post_name'] = sanitize_title($data['slug']);
                }

                // Créer le nouveau post
                $post_id = wp_insert_post($new_post_data);

                if (is_wp_error($post_id)) {
                    throw new Exception('Erreur lors de la création du post : ' . $post_id->get_error_message());
                }

                if (!$post_id) {
                    throw new Exception('Échec de la création de l\'expérience.');
                }
            } else {
                // LOGIQUE EXISTANTE : Mise à jour du post
                $post_data = [];
                if (isset($data['title'])) {
                    $post_data['post_title'] = sanitize_text_field($data['title']);
                }
                if (isset($data['slug'])) {
                    $post_data['post_name'] = sanitize_title($data['slug']);
                }
                if (isset($data['status'])) {
                    $status = sanitize_text_field($data['status']);
                    if (in_array($status, ['publish', 'pending', 'draft', 'private'])) {
                        $post_data['post_status'] = $status;
                    }
                }
                if (isset($data['content'])) {
                    $post_data['post_content'] = wp_kses_post($data['content']);
                }
                if (isset($data['excerpt'])) {
                    $post_data['post_excerpt'] = wp_kses_post($data['excerpt']);
                }

                if (!empty($post_data)) {
                    $post_data['ID'] = $post_id;
                    $result = wp_update_post($post_data);
                    if (is_wp_error($result)) {
                        throw new Exception('Erreur lors de la mise à jour du post : ' . $result->get_error_message());
                    }
                }
            }

            // 2. Mise à jour des champs ACF
            if (function_exists('update_field')) {
                $updated_fields = 0;

                foreach ($data as $normalized_key => $value) {
                    // Skip les champs de base du post
                    if (in_array($normalized_key, ['title', 'slug', 'status', 'content', 'excerpt', 'featured_image_id', 'post_type'])) {
                        continue;
                    }

                    // Gestion spéciale des champs Repeater
                    if (in_array($normalized_key, ['acf_exp_lieux_horaires_depart', 'acf_exp_periodes_fermeture', 'acf_exp_types_de_tarifs'])) {
                        $clean_key = str_replace('acf_', '', $normalized_key);
                        $success = self::update_repeater_field($post_id, $clean_key, $value);
                        if ($success) $updated_fields++;
                        continue;
                    }

                    // Éliminer le préfixe 'acf_' pour trouver la clé normalisée
                    $clean_key = str_replace('acf_', '', $normalized_key);

                    // Utiliser get_field_config_by_slug() pour trouver la bonne clé
                    $field_config = self::get_field_config_by_slug($clean_key);
                    if (!$field_config) {
                        continue;
                    }

                    // Traitement spécial pour les champs Images (Hero)
                    if (in_array($clean_key, ['exp_hero_desktop', 'exp_hero_mobile'])) {
                        $value = self::process_image_field($value);
                    }

                    // Sanitisation selon le type de champ
                    $clean_value = self::sanitize_field_value($clean_key, $value);

                    // ACTION CRUCIALE : Priorité à la field key ACF, sinon meta_key
                    $update_key = $field_config['key'] ?? $field_config['meta_key'];

                    // Mise à jour via ACF pour garder la compatibilité maximale
                    $result = update_field($update_key, $clean_value, $post_id);
                    if ($result !== false) {
                        $updated_fields++;
                    }
                }
            } else {
                return [
                    'success' => false,
                    'message' => 'ACF non disponible pour la sauvegarde.',
                ];
            }

            // 3. Mise à jour de l'image à la une
            if (isset($data['featured_image_id'])) {
                $image_id = (int) $data['featured_image_id'];
                if ($image_id > 0) {
                    set_post_thumbnail($post_id, $image_id);
                } else {
                    delete_post_thumbnail($post_id);
                }
            }

            return [
                'success' => true,
                'message' => $is_creation ? 'Expérience créée avec succès.' : 'Expérience mise à jour avec succès.',
                'data' => [
                    'id' => $post_id,
                    'updated_fields' => $updated_fields ?? 0,
                ],
            ];
        } catch (Exception $e) {
            error_log('[PCR Experience Manager] Erreur mise à jour : ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la sauvegarde : ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Supprime définitivement une expérience et toutes ses données associées.
     * 
     * @param int $post_id ID du post à supprimer
     * @return array
     */
    public static function delete_experience($post_id)
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
            // Récupérer le titre pour le message de confirmation
            $experience_title = $post->post_title;

            // Supprimer définitivement le post et toutes ses métadonnées
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
            error_log('[PCR Experience Manager] Erreur suppression : ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la suppression : ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Traite la valeur d'un champ selon son type.
     * 
     * @param string $field_key Clé du champ
     * @param mixed $value Valeur brute
     * @return mixed Valeur traitée
     */
    private static function process_field_value($field_key, $value)
    {
        // Traitement spécifique selon le champ d'expérience
        switch ($field_key) {
            case 'exp_lieux_horaires_depart':
            case 'exp_periodes_fermeture':
            case 'exp_types_de_tarifs':
                // Champs complexes (repeater) : garder tel quel
                return $value;

            case 'exp_accessibilite':
            case 'exp_periode':
            case 'exp_jour':
                // Champs checkbox : s'assurer que c'est un array
                return is_array($value) ? $value : [];

            case 'exp_duree':
            case 'exp_capacite':
            case 'exp_age_minimum':
            case 'taux_tva':
            case 'pc_deposit_value':
            case 'pc_balance_delay_days':
            case 'pc_caution_amount':
                // Champs numériques standards
                return is_numeric($value) ? (float) $value : 0;

            case 'exp_exclude_sitemap':
            case 'exp_http_410':
            case 'exp_availability':
                // Champs booléens
                if ($value === "1" || $value === 1 || $value === true || $value === 'true') {
                    return true;
                }
                if ($value === "0" || $value === 0 || $value === false || $value === 'false' || $value === '') {
                    return false;
                }
                return (bool) $value;

            case 'exp_logements_recommandes':
                // Champ relationship : s'assurer que c'est un array d'IDs
                if (is_array($value)) {
                    return array_map('intval', $value);
                }
                return [];

            default:
                // Champs texte : sanitisation de base
                if (is_string($value)) {
                    return trim($value);
                }
                return $value;
        }
    }

    /**
     * 🔧 MÉTHODE HELPER : Trouve la configuration ACF d'un champ par son slug.
     * 
     * @param string $slug Le slug normalisé du champ
     * @return array|false Configuration du champ ou false si non trouvé
     */
    private static function get_field_config_by_slug($slug)
    {
        $field_keys = self::get_acf_field_keys();
        $mapped_fields = self::get_mapped_fields();

        $config = [
            'slug' => $slug,
            'key' => null,
            'meta_key' => null,
        ];

        // 1. Vérifier d'abord les field keys ACF
        if (isset($field_keys[$slug])) {
            $config['key'] = $field_keys[$slug];
            $config['meta_key'] = $field_keys[$slug];
            return $config;
        }

        // 2. Fallback vers le mapping standard
        if (isset($mapped_fields[$slug])) {
            $config['meta_key'] = $mapped_fields[$slug];
            return $config;
        }

        return false;
    }

    /**
     * 🔧 FIX CRITIQUE: Met à jour un champ Repeater ACF avec les données formatées.
     * 
     * @param int $post_id ID du post
     * @param string $field_name Nom du champ repeater
     * @param array $data Données du repeater
     * @return bool Succès ou échec
     */
    private static function update_repeater_field($post_id, $field_name, $data)
    {
        if (!function_exists('update_field')) {
            error_log("[PCR Experience Manager] ACF non disponible pour update_repeater_field");
            return false;
        }

        error_log("🔧 Mise à jour du Repeater Experience {$field_name} avec " . count($data) . " éléments");

        // Traitement spécifique selon le champ Repeater d'expérience
        switch ($field_name) {
            case 'exp_lieux_horaires_depart':
            case 'exp_periodes_fermeture':
            case 'exp_types_de_tarifs':
                // Pour les repeaters d'expérience, on utilise update_field directement
                $result = update_field($field_name, $data, $post_id);
                return $result !== false;

            default:
                error_log("[PCR Experience Manager] Repeater {$field_name} non géré");
                return false;
        }
    }

    /**
     * 🔧 FIX CRITIQUE: Traite un champ Image (conversion ID au lieu d'URL si nécessaire).
     * 
     * @param mixed $value Valeur du champ image (URL, ID, ou array)
     * @return mixed Valeur traitée
     */
    private static function process_image_field($value)
    {
        if (empty($value)) {
            return '';
        }

        // Si c'est déjà un ID numérique, le retourner
        if (is_numeric($value)) {
            $id = (int) $value;
            // Vérifier que l'attachment existe
            if ($id > 0 && get_post($id) && get_post_type($id) === 'attachment') {
                return $id;
            }
            return '';
        }

        // Si c'est une string qui ressemble à un ID
        if (is_string($value) && preg_match('/^\d+$/', trim($value))) {
            $id = (int) trim($value);
            // Vérifier que l'attachment existe
            if ($id > 0 && get_post($id) && get_post_type($id) === 'attachment') {
                return $id;
            }
            return '';
        }

        // 🔧 FIX CRITIQUE: Conversion URL vers ID via attachment_url_to_postid()
        if (is_string($value) && (strpos($value, 'http') === 0 || strpos($value, '/wp-content/uploads') !== false)) {
            error_log("[PCR Experience Manager] 🔧 Conversion URL vers ID pour: {$value}");

            // Utiliser WordPress native function pour convertir URL vers ID
            $attachment_id = attachment_url_to_postid($value);

            if ($attachment_id > 0) {
                error_log("[PCR Experience Manager] ✅ Conversion réussie: URL -> ID #{$attachment_id}");
                return (int) $attachment_id;
            } else {
                error_log("[PCR Experience Manager] ❌ Échec conversion, URL gardée: {$value}");
                return $value;
            }
        }

        // Si c'est un array (cas d'un champ Gallery)
        if (is_array($value)) {
            return array_map(function ($item) {
                return self::process_image_field($item);
            }, $value);
        }

        return $value;
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
            'trash' => 'pc-status--trash',
        ];

        return $classes[$status] ?? 'pc-status--unknown';
    }
} // Fin de la classe PCR_Experience_Manager
