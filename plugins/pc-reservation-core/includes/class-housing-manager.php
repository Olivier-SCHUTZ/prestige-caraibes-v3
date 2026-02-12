<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCR Housing Manager - Gestion des Logements
 * 
 * Bridge Pattern vers les champs ACF existants sans créer de nouvelles tables.
 * Compatible avec l'architecture App Shell et le design Glassmorphisme.
 * 
 * @since 0.1.4
 */
class PCR_Housing_Manager
{
    /**
     * Initialisation des hooks.
     */
    public static function init()
    {
        // Hook d'initialisation si nécessaire
    }

    /**
     * Retourne le mapping complet des 78 champs ACF vers des clés normalisées.
     * 
     * IMPORTANT: Garde TOUS les champs même ceux marqués "dead" dans l'audit
     * car ils peuvent être utilisés par des mu-plugins ou le thème.
     * 
     * @return array Mapping [cle_normalisee => meta_key_acf]
     */
    private static function get_mapped_fields()
    {
        return [
            // === MÉDIA & GALERIE ===
            'hero_desktop_url' => 'hero_desktop_url',
            'hero_mobile_url' => 'hero_mobile_url',
            'gallery_urls' => 'gallery_urls',
            'video_urls' => 'video_urls',
            'groupes_images' => 'groupes_images',

            // === CONTENU SEO ===
            'contenu_seo_titre_h1' => 'contenu_seo_titre_h1',
            'seo_long_html' => 'seo_long_html',
            'highlights' => 'highlights',
            'highlights_custom' => 'highlights_custom',
            'logement_experiences_recommandees' => 'logement_experiences_recommandees',

            // === DÉTAILS & CAPACITÉS ===
            'identifiant_lodgify' => 'identifiant_lodgify',
            'capacite' => 'capacite',
            'superficie' => 'superficie',
            'nombre_de_chambres' => 'nombre_de_chambres',
            'nombre_sdb' => 'nombre_sdb',
            'nombre_lits' => 'nombre_lits',

            // === ÉQUIPEMENTS (tous les groupes) ===
            'eq_piscine_spa' => 'eq_piscine_spa',
            'eq_parking_installations' => 'eq_parking_installations',
            'eq_politiques' => 'eq_politiques',
            'eq_divertissements' => 'eq_divertissements',
            'eq_cuisine_salle_a_manger' => 'eq_cuisine_salle_a_manger',
            'eq_caracteristiques_emplacement' => 'eq_caracteristiques_emplacement',
            'eq_salle_de_bain_blanchisserie' => 'eq_salle_de_bain_blanchisserie',
            'eq_chauffage_climatisation' => 'eq_chauffage_climatisation',
            'eq_internet_bureautique' => 'eq_internet_bureautique',
            'eq_securite_maison' => 'eq_securite_maison',

            // === EMPLACEMENT & PROXIMITÉS ===
            'geo_coords' => 'geo_coords',
            'geo_radius_m' => 'geo_radius_m',
            'prox_airport_km' => 'prox_airport_km',
            'prox_bus_km' => 'prox_bus_km',
            'prox_port_km' => 'prox_port_km',
            'prox_beach_km' => 'prox_beach_km',
            'adresse_rue' => 'adresse_rue',
            'ville' => 'ville',
            'code_postal' => 'code_postal',
            'latitude' => 'latitude',
            'longitude' => 'longitude',

            // === RÉSERVATION ===
            'politique_dannulation' => 'politique_dannulation',
            'regles_maison' => 'regles_maison',
            'horaire_arrivee' => 'horaire_arrivee',
            'horaire_depart' => 'horaire_depart',
            'ical_url' => 'ical_url',
            'lodgify_widget_embed' => 'lodgify_widget_embed',

            // === TARIFS ===
            'base_price_from' => 'base_price_from', // ATTENTION : meta_key réelle = 'prix-a-partir-de-e-nuit-prix-de-base'
            'pc_promo_log' => 'pc-promo-log',
            'min_nights' => 'min_nights',
            'max_nights' => 'max_nights',
            'unite_de_prix' => 'unite_de_prix', // ATTENTION : meta_key réelle = 'unite-de-prix'
            'extra_guest_fee' => 'extra_guest_fee',
            'extra_guest_from' => 'extra_guest_from',
            'caution' => 'caution', // ATTENTION : meta_key réelle = 'caution-e'
            'frais_menage' => 'frais_menage',
            'autres_frais' => 'autres_frais',
            'autres_frais_type' => 'autres_frais_type',
            'taxe_sejour' => 'taxe_sejour',

            // === TARIFS SAISON ===
            'pc_season_blocks' => 'pc_season_blocks',

            // === PROMOTIONS ===
            'pc_promo_blocks' => 'pc_promo_blocks',

            // === HÔTE ===
            'hote_nom' => 'hote_nom',
            'hote_description' => 'hote_description',

            // === OVERRIDES SEO ===
            'log_exclude_sitemap' => 'log_exclude_sitemap',
            'log_http_410' => 'log_http_410',
            'meta_titre' => 'meta_titre',
            'meta_description' => 'meta_description',
            'url_canonique' => 'url_canonique',
            'log_meta_robots' => 'log_meta_robots',
            'seo_gallery_urls' => 'seo_gallery_urls',

            // === GOOGLE VR ===
            'google_vr_accommodation_type' => 'google_vr_accommodation_type',
            'google_vr_amenities' => 'google_vr_amenities',

            // === CHANNEL MANAGER ===
            'taux_tva' => 'taux_tva',
            'taux_tva_menage' => 'taux_tva_menage',
            'mode_reservation' => 'mode_reservation',

            // === RÈGLES DE PAIEMENT (APLATIES) ===
            'pc_pay_mode' => 'pc_pay_mode',
            'pc_deposit_type' => 'pc_deposit_type',
            'pc_deposit_value' => 'pc_deposit_value',
            'pc_balance_delay_days' => 'pc_balance_delay_days',
            'pc_caution_amount' => 'pc_caution_amount',
            'pc_caution_type' => 'pc_caution_type',

            // === INFOS CONTRAT & PROPRIÉTAIRE (APLATIES) ===
            'log_proprietaire_identite' => 'log_proprietaire_identite',
            'personne_logement' => 'personne_logement',
            'proprietaire_adresse' => 'proprietaire_adresse',
            'description_contrat' => 'description_contrat',
            'equipements_contrat' => 'equipements_contrat',
            'has_piscine' => 'has_piscine',
            'has_jacuzzi' => 'has_jacuzzi',
            'has_guide_numerique' => 'has_guide_numerique',
        ];
    }

    /**
     * Retourne le mapping des meta_key avec traits d'union vers leurs vraies clés ACF.
     * 
     * @return array
     */
    private static function get_special_meta_keys()
    {
        return [
            'base_price_from' => 'prix-a-partir-de-e-nuit-prix-de-base',
            'unite_de_prix' => 'unite-de-prix',
            'caution' => 'caution-e',
        ];
    }

    /**
     * 🔧 SOLUTION CRUCIALE : Retourne les FIELD KEYS ACF réels pour chaque champ.
     * Ces clés sont nécessaires pour que update_field() fonctionne correctement.
     * 
     * @return array Mapping [normalized_key => field_key_acf]
     */
    private static function get_acf_field_keys()
    {
        return [
            // === AJOUT DES CLÉS IMAGES ===
            'hero_desktop_url' => 'field_pc_hero_desktop_url',
            'hero_mobile_url' => 'field_pc_hero_mobile_url',
            'gallery_urls' => 'field_pc_gallery_urls',
            'video_urls' => 'field_pc_video_urls',
            'seo_gallery_urls' => 'field_pc_seo_gallery_urls',

            // === CHAMPS CRITIQUES AVEC FIELD KEYS ===
            'capacite' => 'field_pc_capacite',
            'base_price_from' => 'field_pc_prix_base',
            'prix_nuit' => 'field_pc_prix_nuit', // Si ce champ existe
            'mode_reservation' => 'field_692986ddcf6e3',
            'ical_url' => 'field_pc_ical_url',

            // === CHAMPS DE BASE (souvent sans field key spéciale) ===
            'identifiant_lodgify' => 'identifiant_lodgify',
            'superficie' => 'superficie',
            'nombre_de_chambres' => 'nombre_de_chambres',
            'nombre_sdb' => 'nombre_sdb',
            'nombre_lits' => 'nombre_lits',
            'ville' => 'ville',
            'latitude' => 'latitude',
            'longitude' => 'longitude',
            'min_nights' => 'min_nights',
            'max_nights' => 'max_nights',
            'frais_menage' => 'frais_menage',
            'taux_tva' => 'taux_tva',
            'taux_tva_menage' => 'taux_tva_menage',

            // === CHAMPS TEXTE ===
            'politique_dannulation' => 'politique_dannulation',
            'regles_maison' => 'regles_maison',
            'horaire_arrivee' => 'horaire_arrivee',
            'horaire_depart' => 'horaire_depart',
            'hote_nom' => 'hote_nom',
            'hote_description' => 'hote_description',

            // === OVERRIDES SEO ===
            'meta_titre' => 'meta_titre',
            'meta_description' => 'meta_description',
            'url_canonique' => 'url_canonique',

            // === ÉQUIPEMENTS (checkboxes) ===
            'eq_piscine_spa' => 'eq_piscine_spa',
            'eq_parking_installations' => 'eq_parking_installations',
            'eq_politiques' => 'eq_politiques',
            'eq_divertissements' => 'eq_divertissements',
            'eq_cuisine_salle_a_manger' => 'eq_cuisine_salle_a_manger',
            'eq_caracteristiques_emplacement' => 'eq_caracteristiques_emplacement',
            'eq_salle_de_bain_blanchisserie' => 'eq_salle_de_bain_blanchisserie',
            'eq_chauffage_climatisation' => 'eq_chauffage_climatisation',
            'eq_internet_bureautique' => 'eq_internet_bureautique',
            'eq_securite_maison' => 'eq_securite_maison',

            // === RÈGLES DE PAIEMENT (field keys ACF) ===
            'pc_pay_mode' => 'field_6919e7994db4b',
            'pc_deposit_type' => 'field_6919e7994db4c',
            'pc_deposit_value' => 'field_6919e7994db4d',
            'pc_balance_delay_days' => 'field_6919e7994db4e',
            'pc_caution_amount' => 'field_6919e7994db4f',
            'pc_caution_type' => 'field_6919e7994db50',

            // === INFOS CONTRAT & PROPRIÉTAIRE (field keys ACF) ===
            'log_proprietaire_identite' => 'field_6930b2a1248f7',
            'personne_logement' => 'field_6930b83a248fe',
            'proprietaire_adresse' => 'field_6930b32b248f8',
            'description_contrat' => 'field_6930b751248fd',
            'equipements_contrat' => 'field_6930b54c248fc',
            'has_piscine' => 'field_6930b427248f9',
            'has_jacuzzi' => 'field_6930b4a5248fa',
            'has_guide_numerique' => 'field_6930b4c9248fb',

            // Note : Les champs spéciaux (prix-a-partir-de-e-nuit-prix-de-base, etc.)
            // seront gérés par get_special_meta_keys()
        ];
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
        $special_keys = self::get_special_meta_keys();
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

        // 2. Vérifier les clés spéciales avec traits d'union
        if (isset($special_keys[$slug])) {
            $config['meta_key'] = $special_keys[$slug];
            return $config;
        }

        // 3. Fallback vers le mapping standard
        if (isset($mapped_fields[$slug])) {
            $config['meta_key'] = $mapped_fields[$slug];
            return $config;
        }

        return false;
    }

    /**
     * Retourne une liste légère des logements pour le tableau dashboard.
     * 
     * @param array $args Arguments de requête (pagination, filtres)
     * @return array
     */
    public static function get_housing_list($args = [])
    {
        $defaults = [
            'posts_per_page' => 20,
            'paged' => 1,
            'orderby' => 'title',
            'order' => 'ASC',
            's' => '',
            'meta_query' => [],
            // Nouveaux filtres par défaut
            'type_filter' => '',
            'status_filter' => '',
            'mode_filter' => '',
        ];

        $args = wp_parse_args($args, $defaults);

        // --- LOGIQUE DE FILTRE PAR TYPE ---
        // Par défaut : on prend tout (Villa + Appartement)
        $post_types = ['villa', 'appartement'];

        // Si un filtre spécifique est demandé (et que ce n'est pas "logement" qui veut dire "tous")
        if (!empty($args['type_filter']) && in_array($args['type_filter'], ['villa', 'appartement'])) {
            $post_types = [$args['type_filter']];
        }

        $query_args = [
            'post_type' => $post_types, // C'est ici que le filtre s'applique !
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
        $meta_query = $args['meta_query']; // Récupère ceux déjà passés

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

                // Statut formaté pour l'affichage
                $item['status_label'] = self::get_status_label($item['status']);
                $item['status_class'] = self::get_status_class($item['status']);

                // Mode de réservation formaté
                $item['mode_label'] = self::get_mode_label($item['mode_reservation']);
                $item['mode_class'] = self::get_mode_class($item['mode_reservation']);

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
     * Retourne les détails complets d'un logement avec tous les 78 champs.
     * 
     * @param int $post_id ID du post
     * @return array|false
     */
    public static function get_housing_details($post_id)
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

        // Chargement de tous les champs ACF mappés (SIMPLIFIÉ)
        if (function_exists('get_field')) {
            $mapped_fields = self::get_mapped_fields();
            $special_keys = self::get_special_meta_keys();

            foreach ($mapped_fields as $normalized_key => $meta_key) {
                // Utilise la vraie meta_key si elle a un trait d'union
                $real_meta_key = $special_keys[$normalized_key] ?? $meta_key;

                $value = get_field($real_meta_key, $post_id);

                // Traitement spécifique selon le type de champ
                $details[$normalized_key] = self::process_field_value($normalized_key, $value);
            }
        } else {
            // Fallback sans ACF : on charge ce qu'on peut via get_post_meta
            error_log('[PCR Housing Manager] ACF non disponible, fallback vers get_post_meta');
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
     * Met à jour un logement avec les données fournies.
     * NOUVEAU : Accepte ID = 0 pour créer un nouveau logement.
     * * @param int $post_id ID du post (0 pour création)
     * @param array $data Données à mettre à jour
     * @return array
     */
    public static function update_housing($post_id, $data)
    {
        $post_id = (int) $post_id;
        $is_creation = ($post_id === 0);

        if (!$is_creation) {
            // Mode édition : validation normale
            $post = get_post($post_id);
            if (!$post || !in_array($post->post_type, ['villa', 'appartement'])) {
                return [
                    'success' => false,
                    'message' => 'Logement introuvable.',
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
            if (empty($data['post_type']) || !in_array($data['post_type'], ['villa', 'appartement'])) {
                return [
                    'success' => false,
                    'message' => 'Type de logement invalide. Doit être "villa" ou "appartement".',
                ];
            }

            // Vérification des permissions pour création
            if (!current_user_can('publish_posts')) {
                return [
                    'success' => false,
                    'message' => 'Permission insuffisante pour créer un logement.',
                ];
            }

            // Validation du titre obligatoire
            if (empty($data['title'])) {
                return [
                    'success' => false,
                    'message' => 'Le nom du logement est obligatoire.',
                ];
            }
        }

        try {
            // 1. Création ou mise à jour des données de base du post
            if ($is_creation) {
                // NOUVELLE LOGIQUE : Créer le post
                $new_post_data = [
                    'post_type' => sanitize_text_field($data['post_type']),
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
                    throw new Exception('Échec de la création du logement.');
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

            // 2. Mise à jour des champs ACF (SIMPLIFIÉ - Plus de logique de groupe complexe)
            if (function_exists('update_field')) {
                $updated_fields = 0;

                foreach ($data as $normalized_key => $value) {
                    // Skip les champs de base du post
                    if (in_array($normalized_key, ['title', 'slug', 'status', 'content', 'excerpt', 'featured_image_id', 'post_type'])) {
                        continue;
                    }

                    // Gestion spéciale du champ Repeater "groupes_images"
                    if ($normalized_key === 'acf_groupes_images') {
                        $success = self::update_repeater_field($post_id, 'groupes_images', $value);
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
                    if (in_array($clean_key, ['hero_desktop_url', 'hero_mobile_url'])) {
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
                'message' => $is_creation ? 'Logement créé avec succès.' : 'Logement mis à jour avec succès.',
                'data' => [
                    'id' => $post_id,
                    'updated_fields' => $updated_fields ?? 0,
                ],
            ];
        } catch (Exception $e) {
            error_log('[PCR Housing Manager] Erreur mise à jour : ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la sauvegarde : ' . $e->getMessage(),
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
        // Traitement spécifique selon le champ
        switch ($field_key) {
            case 'groupes_images':
            case 'pc_season_blocks':
            case 'pc_promo_blocks':
            case 'regles_de_paiement':
            case 'information_contrat_location':
                // Champs complexes (repeater/group) : garder tel quel
                return $value;

            case 'highlights':
            case 'eq_piscine_spa':
            case 'eq_parking_installations':
            case 'eq_politiques':
            case 'eq_divertissements':
            case 'eq_cuisine_salle_a_manger':
            case 'eq_caracteristiques_emplacement':
            case 'eq_salle_de_bain_blanchisserie':
            case 'eq_chauffage_climatisation':
            case 'eq_internet_bureautique':
            case 'eq_securite_maison':
            case 'taxe_sejour':
            case 'google_vr_amenities':
                // Champs checkbox : s'assurer que c'est un array
                return is_array($value) ? $value : [];

            case 'capacite':
            case 'superficie':
            case 'nombre_de_chambres':
            case 'nombre_sdb':
            case 'nombre_lits':
            case 'geo_radius_m':
            case 'prox_airport_km':
            case 'prox_bus_km':
            case 'prox_port_km':
            case 'prox_beach_km':
            case 'base_price_from':
            case 'min_nights':
            case 'max_nights':
            case 'extra_guest_fee':
            case 'caution':
            case 'frais_menage':
            case 'autres_frais':
            case 'taux_tva':
            case 'taux_tva_menage':
                // Champs numériques standards
                return is_numeric($value) ? (float) $value : 0;

            case 'extra_guest_from':
            case 'season_extra_guest_from':
                // ⚠️ CHAMPS AVEC CONTRAINTE MIN=1 : Valeurs vides doivent rester vides
                // Sinon ACF rejette avec "Échec de la validation" car 0 < 1
                if (empty($value) || !is_numeric($value)) {
                    return ''; // Garder vide au lieu de forcer à 0
                }
                $numeric_value = (float) $value;
                return $numeric_value >= 1 ? $numeric_value : ''; // Respecter la contrainte min=1

            case 'pc_promo_log':
            case 'log_exclude_sitemap':
            case 'log_http_410':
                // 🔧 FIX CHECKBOX: Champs booléens - conversion correcte des strings "0"/"1"
                if ($value === "1" || $value === 1 || $value === true || $value === 'true') {
                    return true;
                }
                if ($value === "0" || $value === 0 || $value === false || $value === 'false' || $value === '') {
                    return false;
                }
                return (bool) $value;

            case 'logement_experiences_recommandees':
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
     * Sanitise la valeur d'un champ avant sauvegarde.
     * 
     * @param string $field_key Clé du champ
     * @param mixed $value Valeur à sanitiser
     * @return mixed Valeur sanitisée
     */
    private static function sanitize_field_value($field_key, $value)
    {
        // Traitement spécifique selon le champ
        switch ($field_key) {
            case 'seo_long_html':
            case 'politique_dannulation':
            case 'regles_maison':
            case 'hote_description':
                // Champs HTML
                return wp_kses_post($value);

            case 'gallery_urls':
            case 'video_urls':
            case 'highlights_custom':
            case 'lodgify_widget_embed':
            case 'seo_gallery_urls':
                // Champs textarea
                return sanitize_textarea_field($value);

            case 'capacite':
            case 'superficie':
            case 'nombre_de_chambres':
            case 'nombre_sdb':
            case 'nombre_lits':
            case 'geo_radius_m':
            case 'prox_airport_km':
            case 'prox_bus_km':
            case 'prox_port_km':
            case 'prox_beach_km':
            case 'base_price_from':
            case 'min_nights':
            case 'max_nights':
            case 'extra_guest_fee':
            case 'caution':
            case 'frais_menage':
            case 'autres_frais':
            case 'taux_tva':
            case 'taux_tva_menage':
                // Champs numériques standards
                return is_numeric($value) ? (float) $value : 0;

            case 'extra_guest_from':
            case 'season_extra_guest_from':
                // ⚠️ CHAMPS AVEC CONTRAINTE MIN=1 : Valeurs vides doivent rester vides
                // Sinon ACF rejette avec "Échec de la validation" car 0 < 1
                if (empty($value) || !is_numeric($value)) {
                    return ''; // Garder vide au lieu de forcer à 0
                }
                $numeric_value = (float) $value;
                return $numeric_value >= 1 ? $numeric_value : ''; // Respecter la contrainte min=1

            case 'ical_url':
            case 'url_canonique':
                // URLs
                return esc_url_raw($value);

            case 'highlights':
            case 'eq_piscine_spa':
            case 'eq_parking_installations':
            case 'eq_politiques':
            case 'eq_divertissements':
            case 'eq_cuisine_salle_a_manger':
            case 'eq_caracteristiques_emplacement':
            case 'eq_salle_de_bain_blanchisserie':
            case 'eq_chauffage_climatisation':
            case 'eq_internet_bureautique':
            case 'eq_securite_maison':
            case 'taxe_sejour':
            case 'google_vr_amenities':
                // Champs checkbox : valider que c'est un array
                if (is_array($value)) {
                    return array_map('sanitize_text_field', $value);
                }
                return [];

            case 'logement_experiences_recommandees':
                // Champ relationship
                if (is_array($value)) {
                    return array_map('intval', array_filter($value));
                }
                return [];

            case 'groupes_images':
            case 'pc_season_blocks':
            case 'pc_promo_blocks':
            case 'regles_de_paiement':
            case 'information_contrat_location':
                // Champs complexes : laisser ACF gérer la sanitisation
                return $value;

            default:
                // Champs texte par défaut
                return sanitize_text_field($value);
        }
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

    /**
     * Retourne le label d'affichage pour un mode de réservation.
     * 
     * @param string $mode
     * @return string
     */
    private static function get_mode_label($mode)
    {
        $labels = [
            'log_demande' => 'Sur demande',
            'log_directe' => 'Réservation directe',
            'log_channel' => 'Channel Manager',
        ];

        return $labels[$mode] ?? 'Réservation directe';
    }

    /**
     * Retourne la classe CSS pour un mode de réservation.
     * 
     * @param string $mode
     * @return string
     */
    private static function get_mode_class($mode)
    {
        $classes = [
            'log_demande' => 'pc-mode--demand',
            'log_directe' => 'pc-mode--direct',
            'log_channel' => 'pc-mode--channel',
        ];

        return $classes[$mode] ?? 'pc-mode--direct';
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
            error_log("[PCR Housing Manager] ACF non disponible pour update_repeater_field");
            return false;
        }

        error_log("🔧 Mise à jour du Repeater {$field_name} avec " . count($data) . " éléments");

        // Traitement spécifique selon le champ Repeater
        switch ($field_name) {
            case 'groupes_images':
                return self::update_groupes_images_repeater($post_id, $data);

            default:
                error_log("[PCR Housing Manager] Repeater {$field_name} non géré");
                return false;
        }
    }

    /**
     * 🔧 FIX CRITIQUE: Met à jour spécifiquement le Repeater "groupes_images".
     * Utilise les SUB-FIELD KEYS pour garantir la compatibilité ACF.
     * * @param int $post_id ID du post
     * @param array $groupes_data Données des groupes d'images
     * @return bool Succès ou échec
     */
    private static function update_groupes_images_repeater($post_id, $groupes_data)
    {
        if (!is_array($groupes_data)) {
            return false;
        }

        // Préparer les données pour ACF avec les CLÉS (Keys) et non les Noms
        $formatted_data = [];

        foreach ($groupes_data as $index => $groupe) {
            // On accepte 'categorie' ou la clé directe si le JS change
            $categorie_val = $groupe['categorie'] ?? '';

            if (empty($categorie_val)) {
                continue;
            }

            // CONSTRUCTION DE LA ROW AVEC LES CLÉS ACF
            // Ces clés viennent de votre JSON ACF fourni précédemment
            $formatted_groupe = [
                // Clé pour 'categorie'
                'field_693abf6847b68' => sanitize_text_field($categorie_val),

                // Clé pour 'categorie_personnalisee'
                'field_693abfe847b69' => sanitize_text_field($groupe['categorie_personnalisee'] ?? ''),

                // Clé pour 'images_du_groupe' (Gallery)
                'field_693ac02f47b6a' => []
            ];

            // Traitement des images
            if (isset($groupe['images_du_groupe'])) {
                $images_data = $groupe['images_du_groupe'];

                if (is_array($images_data)) {
                    // Si c'est déjà un array, on valide les IDs
                    foreach ($images_data as $image_id) {
                        if (is_numeric($image_id) && (int)$image_id > 0) {
                            $formatted_groupe['field_693ac02f47b6a'][] = (int) $image_id;
                        }
                    }
                } elseif (is_string($images_data) && !empty($images_data)) {
                    // Si c'est une string "12,45,89", on explode
                    $image_ids = explode(',', $images_data);
                    foreach ($image_ids as $id_str) {
                        $id = (int) trim($id_str);
                        if ($id > 0) {
                            $formatted_groupe['field_693ac02f47b6a'][] = $id;
                        }
                    }
                }
            }

            $formatted_data[] = $formatted_groupe;
        }

        // Mise à jour via ACF avec la clé MAÎTRE du Repeater
        // On supprime l'appel préalable à "update_field(..., [])" qui est inutile et risqué
        $success = update_field('field_693abf0447b67', $formatted_data, $post_id);

        if (!$success) {
            error_log("[PCR Housing] Échec sauvegarde Repeater via Keys. Tentative fallback via Noms...");
            // Fallback ultime si les clés échouent (peu probable)
            return update_field('groupes_images', $groupes_data, $post_id);
        }

        return $success !== false;
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
            error_log("[PCR Housing Manager] 🔧 Conversion URL vers ID pour: {$value}");

            // Utiliser WordPress native function pour convertir URL vers ID
            $attachment_id = attachment_url_to_postid($value);

            if ($attachment_id > 0) {
                error_log("[PCR Housing Manager] ✅ Conversion réussie: URL -> ID #{$attachment_id}");
                return (int) $attachment_id;
            } else {
                error_log("[PCR Housing Manager] ❌ Échec conversion, URL gardée: {$value}");

                // 🔧 FALLBACK : Essayer une approche manuelle si attachment_url_to_postid() échoue
                $attachment_id = self::manual_url_to_attachment_id($value);
                if ($attachment_id > 0) {
                    error_log("[PCR Housing Manager] ✅ Conversion manuelle réussie: URL -> ID #{$attachment_id}");
                    return (int) $attachment_id;
                }

                // Si vraiment aucune conversion possible, on garde l'URL (mais ce n'est pas idéal)
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
     * 🔧 FIX FALLBACK : Méthode manuelle pour convertir une URL d'image vers son ID d'attachment.
     * Utilisée quand attachment_url_to_postid() échoue.
     * 
     * @param string $url URL de l'image
     * @return int ID de l'attachment ou 0 si non trouvé
     */
    private static function manual_url_to_attachment_id($url)
    {
        global $wpdb;

        if (empty($url)) {
            return 0;
        }

        // Extraire le nom de fichier de l'URL
        $filename = basename(parse_url($url, PHP_URL_PATH));

        if (empty($filename)) {
            return 0;
        }

        // Rechercher dans la table des attachments
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = '_wp_attached_file' 
             AND meta_value LIKE %s
             LIMIT 1",
            '%' . $wpdb->esc_like($filename)
        ));

        if ($attachment_id && is_numeric($attachment_id)) {
            $attachment_id = (int) $attachment_id;
            // Vérifier que c'est bien un attachment
            if (get_post($attachment_id) && get_post_type($attachment_id) === 'attachment') {
                return $attachment_id;
            }
        }

        return 0;
    }

    /**
     * Supprime définitivement un logement et toutes ses données associées.
     * 
     * @param int $post_id ID du post à supprimer
     * @return array
     */
    public static function delete_housing($post_id)
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
            // Récupérer le titre pour le message de confirmation
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
            error_log('[PCR Housing Manager] Erreur suppression : ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la suppression : ' . $e->getMessage(),
            ];
        }
    }
}
