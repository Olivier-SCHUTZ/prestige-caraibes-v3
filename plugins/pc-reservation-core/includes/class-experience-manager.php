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
            // === GÉNÉRAL & SEO ===
            'exp_exclude_sitemap' => 'field_68db7babdb30a',
            'exp_http_410' => 'field_68db7bf4db30b',
            'exp_meta_titre' => 'field_66dcc831d111b',
            'exp_meta_description' => 'field_66dcc867d111c',
            'exp_meta_canonical' => 'field_68db7c44db30c',
            'exp_meta_robots' => 'field_68db7ca7db30d',
            'exp_logements_recommandes' => 'field_66dcc8a4d111d',
            'exp_availability' => 'field_68d509f885264',

            // === DÉTAILS PRINCIPAUX ===
            'exp_h1_custom' => 'field_68beb1671e633',
            'exp_hero_desktop' => 'field_68beb1cd1e634',
            'exp_hero_mobile' => 'field_68beb2221e635',

            // === DÉTAILS SORTIES ===
            'exp_duree' => 'field_66dcc94cd111f',
            'exp_capacite' => 'field_66dcc9a3d1120',
            'exp_age_minimum' => 'field_66dcc9f9d1121',
            'exp_accessibilite' => 'field_66dcca37d1122',
            'exp_periode' => 'field_68bec10e3bc0f',
            'exp_jour' => 'field_68bf09049c6ae',
            'exp_periodes_fermeture' => 'field_66dccab9d1123',
            'exp_lieux_horaires_depart' => 'field_66dccb67d1126',

            // === INCLUSIONS & PRÉ-REQUIS ===
            'exp_prix_comprend' => 'field_66dcccc2d112c',
            'exp_prix_ne_comprend_pas' => 'field_66dccd1cd112d',
            'exp_a_prevoir' => 'field_66dccd4dd112e',

            // === SERVICES ===
            'exp_delai_de_reservation' => 'field_68dcd02002938',
            'exp_zone_intervention' => 'field_68dcd26402939',
            'exp_type_de_prestation' => 'field_68dcd3f60293a',
            'exp_heure_limite_de_commande' => 'field_68dcd5180293b',
            'exp_le_service_comprend' => 'field_68dce6573eeb6',
            'exp_service_a_prevoir' => 'field_68dce6ca3eeb7',

            // === GALERIE ===
            'photos_experience' => 'field_66dccda9d1130',

            // === FAQ ===
            'exp_faq' => 'field_66dcce25d1132',

            // === TARIFS ===
            'exp_types_de_tarifs' => 'field_66dcceddd1136',

            // === RÈGLES CHANNEL MANAGER & PAIEMENT ===
            'taux_tva' => 'field_692db668fa552',
            'pc_pay_mode' => 'field_6919d4793e90d',
            'pc_deposit_type' => 'field_6919e2d8b01b8',
            'pc_deposit_value' => 'field_6919e38eb01b9',
            'pc_balance_delay_days' => 'field_6919e3e5b01ba',
            'pc_caution_amount' => 'field_6919e424b01bb',
            'pc_caution_mode' => 'field_6919e47bb01bc',
        ];
    }

    /**
     * Traite la valeur d'un champ selon son type (Lecture depuis la BDD vers l'Affichage).
     * * @param string $field_key Clé du champ
     * @param mixed $value Valeur brute
     * @return mixed Valeur traitée
     */
    private static function process_field_value($field_key, $value)
    {
        switch ($field_key) {
            case 'exp_duree':
            case 'exp_capacite':
            case 'exp_age_minimum':
            case 'taux_tva':
            case 'pc_deposit_value':
            case 'pc_balance_delay_days':
            case 'pc_caution_amount':
            case 'exp_heure_limite_de_commande':
                return is_numeric($value) ? (float) $value : 0;

            case 'exp_exclude_sitemap':
            case 'exp_http_410':
            case 'exp_availability':
                if ($value === "1" || $value === 1 || $value === true || $value === 'true') return true;
                if ($value === "0" || $value === 0 || $value === false || $value === 'false' || $value === '') return false;
                return (bool) $value;

            case 'exp_accessibilite':
            case 'exp_periode':
            case 'exp_jour':
            case 'exp_a_prevoir':
            case 'exp_delai_de_reservation':
            case 'exp_zone_intervention':
            case 'exp_logements_recommandes':
            case 'exp_lieux_horaires_depart':
            case 'exp_periodes_fermeture':
            case 'exp_types_de_tarifs':
            case 'exp_faq':
            case 'photos_experience':
                return is_array($value) ? $value : [];

            default:
                if (is_string($value)) {
                    return trim($value);
                }
                return $value;
        }
    }

    /**
     * Sanitise la valeur d'un champ avant sauvegarde.
     * * @param string $field_key Clé du champ
     * @param mixed $value Valeur à sanitiser
     * @return mixed Valeur sanitisée
     */
    private static function sanitize_field_value($field_key, $value)
    {
        switch ($field_key) {
            case 'exp_prix_comprend':
            case 'exp_prix_ne_comprend_pas':
            case 'exp_le_service_comprend':
            case 'exp_service_a_prevoir':
                // Champs HTML longs / WYSIWYG
                return wp_kses_post($value);

            case 'exp_h1_custom':
            case 'exp_meta_titre':
            case 'exp_meta_description':
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
                if ($value === "1" || $value === 1 || $value === true || $value === 'true') return true;
                if ($value === "0" || $value === 0 || $value === false || $value === 'false' || $value === '') return false;
                return (bool) $value;

            case 'exp_meta_canonical':
                return esc_url_raw($value);

            case 'exp_logements_recommandes':
                if (is_array($value)) return array_map('intval', array_filter($value));
                return [];

            case 'exp_accessibilite':
            case 'exp_periode':
            case 'exp_jour':
            case 'exp_a_prevoir':
            case 'exp_delai_de_reservation':
            case 'exp_zone_intervention':
                // Champs array (checkboxes) ou fallback texte
                if (is_array($value)) return array_map('sanitize_text_field', $value);
                return sanitize_text_field($value);

            case 'exp_lieux_horaires_depart':
            case 'exp_periodes_fermeture':
            case 'exp_types_de_tarifs':
            case 'exp_faq':
                // Champs complexes (Repeaters) : laisser ACF gérer la sanitisation
                return $value;

            case 'exp_hero_desktop':
            case 'exp_hero_mobile':
            case 'photos_experience':
                // Champs images
                return $value;

            default:
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
            'exp_le_service_comprend' => 'exp_le_service_comprend',
            'exp_service_a_prevoir' => 'exp_service_a_prevoir',

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
            'pc_caution_mode' => 'pc_caution_mode',

            'exp_faq' => 'exp_faq',
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
                    // CORRECTION : Clés simplifiées pour matcher le JS (item.duree, item.capacite)
                    $item['duree'] = get_field('exp_duree', $post_id) ?: '';
                    $item['capacite'] = get_field('exp_capacite', $post_id) ?: 0;
                    $item['availability'] = get_field('exp_availability', $post_id) ?: true;
                    $item['type_de_prestation'] = get_field('exp_type_de_prestation', $post_id) ?: '';

                    // Zone d'intervention
                    $item['zone_intervention'] = get_field('exp_zone_intervention', $post_id) ?: '';

                    // Nouveaux champs pour le tableau (Lieu de départ et TVA)
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

                // On passe aussi les champs bruts si besoin de debug, mais les clés ci-dessus sont prioritaires
                $item['exp_availability'] = $item['availability'];

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
     * Version CORRIGÉE pour Rate Manager et Repeaters.
     * * @param int $post_id ID du post
     * @return array|false
     */
    public static function get_experience_details($post_id)
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0) return false;

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'experience') return false;

        // 1. Données de base WP
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

        // 2. Chargement via Mapping ACF (Code existant amélioré)
        if (function_exists('get_field')) {
            $mapped_fields = self::get_mapped_fields();

            foreach ($mapped_fields as $normalized_key => $meta_key) {
                $value = get_field($meta_key, $post_id);
                // On utilise ta fonction de process interne
                $details[$normalized_key] = self::process_field_value($normalized_key, $value);
            }
        } else {
            // Fallback (rare)
            $mapped_fields = self::get_mapped_fields();
            foreach ($mapped_fields as $normalized_key => $meta_key) {
                $details[$normalized_key] = get_post_meta($post_id, $meta_key, true);
            }
        }

        // =========================================================
        // 3. 🔧 TRAITEMENT SPÉCIALISÉ DES IMAGES POUR L'AFFICHAGE
        // =========================================================

        // A. Images Hero : s'assurer qu'on a à la fois l'ID et l'URL
        $exp_hero_desktop = get_field('exp_hero_desktop', $post_id);
        $exp_hero_mobile = get_field('exp_hero_mobile', $post_id);

        $details['exp_hero_desktop'] = self::process_image_for_display($exp_hero_desktop);
        $details['exp_hero_mobile'] = self::process_image_for_display($exp_hero_mobile);

        // B. Galerie : traitement spécialisé pour l'affichage
        $photos_experience = get_field('photos_experience', $post_id);
        $details['photos_experience'] = self::process_gallery_for_display($photos_experience);

        // =========================================================
        // 4. 🔧 SÉCURITÉ ANTI-CRASH : PROTECTION TARIFS & RATE MANAGER
        // =========================================================

        // A. RATE MANAGER (Calendrier) - OBLIGATOIRE pour éviter le crash JS
        // Ces champs stockent le JSON des saisons et promos du calendrier
        $seasons_data = get_field('seasons_data', $post_id);
        $promos_data = get_field('promos_data', $post_id);

        // 🛡️ SÉCURITÉ : Force les données à être des tableaux (jamais null/false)
        $details['seasons_data'] = (is_array($seasons_data)) ? $seasons_data : [];
        $details['promos_data'] = (is_array($promos_data)) ? $promos_data : [];

        // B. TARIFS & TVA - CHAMPS CRITIQUES (Hérités du JSON ACF)
        // 🛡️ Force taux_tva à être défini (requis pour l'onglet Tarifs)
        $taux_tva_raw = get_field('taux_tva', $post_id);
        $details['taux_tva'] = ($taux_tva_raw !== false && $taux_tva_raw !== null) ? $taux_tva_raw : '';

        // 🛡️ CHAMP CRITIQUE : exp_types_de_tarifs (Source de vérité du JSON)
        $exp_types_de_tarifs_raw = get_field('exp_types_de_tarifs', $post_id);

        // 🔧 FIX CRITIQUE : Si le repeater est vide (à cause de min=1), créer une ligne par défaut
        if (!is_array($exp_types_de_tarifs_raw) || empty($exp_types_de_tarifs_raw)) {
            error_log("🔧 [PCR Experience Manager] Création ligne de tarif par défaut pour expérience #{$post_id}");
            $details['exp_types_de_tarifs'] = [
                [
                    'exp_type' => 'unique',
                    'exp_type_custom' => '',
                    // Structure complète pour éviter les erreurs JS
                    'exp_options_tarifaires' => [],
                    'exp-frais-fixes' => [],
                    'exp_tarifs_lignes' => []
                ]
            ];

            // 🔧 BONUS : Sauvegarder automatiquement cette ligne par défaut pour corriger définitivement le problème
            if (function_exists('update_field')) {
                $success = update_field('exp_types_de_tarifs', $details['exp_types_de_tarifs'], $post_id);
                if ($success) {
                    error_log("✅ [PCR Experience Manager] Ligne de tarif par défaut sauvegardée pour expérience #{$post_id}");
                }
            }
        } else {
            $details['exp_types_de_tarifs'] = $exp_types_de_tarifs_raw;
        }

        // C. RÈGLES DE PAIEMENT (Compatibilité avec Housing Manager)
        $details['pc_pay_mode'] = get_field('pc_pay_mode', $post_id) ?: 'acompte_plus_solde';
        $details['pc_deposit_type'] = get_field('pc_deposit_type', $post_id) ?: 'pourcentage';
        $details['pc_deposit_value'] = get_field('pc_deposit_value', $post_id) ?: '';
        $details['pc_balance_delay_days'] = get_field('pc_balance_delay_days', $post_id) ?: '';
        $details['pc_caution_amount'] = get_field('pc_caution_amount', $post_id) ?: '';
        $details['pc_caution_mode'] = get_field('pc_caution_mode', $post_id) ?: 'aucune';

        // D. 🛡️ SÉCURISATION COMPLÈTE DES REPEATERS
        // Liste exhaustive des champs qui doivent être des tableaux pour éviter les crashes JS
        $repeaters_to_secure = [
            'exp_lieux_horaires_depart',
            'exp_periodes_fermeture',
            'exp_faq',
            'exp_types_de_tarifs', // DÉJÀ traité ci-dessus mais on double-check
            'exp_accessibilite',
            'exp_periode',
            'exp_jour',
            'photos_experience', // Galerie
            'exp_a_prevoir', // Checkbox field
            'exp_delai_de_reservation', // Checkbox field
            'exp_zone_intervention', // Checkbox field
            'seasons_data', // DÉJÀ traité ci-dessus mais on double-check
            'promos_data'  // DÉJÀ traité ci-dessus mais on double-check
        ];

        foreach ($repeaters_to_secure as $field_key) {
            if (empty($details[$field_key]) || !is_array($details[$field_key])) {
                $details[$field_key] = [];
            }
        }

        // =========================================================

        // 4. Image à la une (Code existant)
        $thumbnail_id = get_post_thumbnail_id($post_id);
        $details['featured_image'] = [
            'id' => $thumbnail_id,
            'url' => $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : '',
            'sizes' => $thumbnail_id ? wp_get_attachment_metadata($thumbnail_id)['sizes'] ?? [] : [],
        ];

        // 5. Taxonomies (Code existant)
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
            case 'exp_faq':
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
     * 🔧 NOUVELLE MÉTHODE : Traite une image pour l'affichage dans le dashboard.
     * Retourne un objet avec à la fois l'ID et l'URL pour compatibilité maximale.
     * 
     * @param mixed $image_value Valeur retournée par ACF (URL ou ID selon config)
     * @return array|string Structure {id, url} ou string si URL directe
     */
    private static function process_image_for_display($image_value)
    {
        if (empty($image_value)) {
            return null;
        }

        // Si c'est déjà une URL (return_format: url dans ACF)
        if (is_string($image_value) && (strpos($image_value, 'http') === 0 || strpos($image_value, '/wp-content/uploads') !== false)) {
            // Essayer de retrouver l'ID correspondant
            $attachment_id = attachment_url_to_postid($image_value);

            return [
                'id' => $attachment_id > 0 ? $attachment_id : null,
                'url' => $image_value,
                'type' => 'url'
            ];
        }

        // Si c'est un ID numérique
        if (is_numeric($image_value)) {
            $attachment_id = (int) $image_value;
            if ($attachment_id > 0 && get_post($attachment_id) && get_post_type($attachment_id) === 'attachment') {
                $url = wp_get_attachment_url($attachment_id);
                return [
                    'id' => $attachment_id,
                    'url' => $url ?: '',
                    'type' => 'id'
                ];
            }
        }

        // Si c'est un objet ACF complet (array)
        if (is_array($image_value) && isset($image_value['id'])) {
            return [
                'id' => (int) $image_value['id'],
                'url' => $image_value['url'] ?: wp_get_attachment_url($image_value['id']) ?: '',
                'type' => 'array'
            ];
        }

        // Fallback : retourner tel quel
        return $image_value;
    }

    /**
     * 🔧 NOUVELLE MÉTHODE : Traite la galerie pour l'affichage dans le dashboard.
     * Normalise le format pour que JavaScript puisse afficher les miniatures.
     * 
     * @param mixed $gallery_value Valeur retournée par ACF Gallery
     * @return array Tableau d'objets {id, url, thumbnail}
     */
    private static function process_gallery_for_display($gallery_value)
    {
        if (empty($gallery_value) || !is_array($gallery_value)) {
            return [];
        }

        $processed_gallery = [];

        foreach ($gallery_value as $image) {
            $processed_image = null;

            if (is_array($image)) {
                // Format objet ACF standard : {ID, url, sizes, etc.}
                $image_id = isset($image['ID']) ? (int) $image['ID'] : (isset($image['id']) ? (int) $image['id'] : null);

                if ($image_id) {
                    $processed_image = [
                        'id' => $image_id,
                        'url' => $image['url'] ?? wp_get_attachment_url($image_id) ?? '',
                        'thumbnail' => wp_get_attachment_image_src($image_id, 'thumbnail')[0] ?? '',
                        'sizes' => $image['sizes'] ?? []
                    ];
                }
            } elseif (is_numeric($image)) {
                // Format ID simple
                $image_id = (int) $image;
                if ($image_id > 0 && get_post($image_id) && get_post_type($image_id) === 'attachment') {
                    $processed_image = [
                        'id' => $image_id,
                        'url' => wp_get_attachment_url($image_id) ?: '',
                        'thumbnail' => wp_get_attachment_image_src($image_id, 'thumbnail')[0] ?? '',
                        'sizes' => []
                    ];
                }
            } elseif (is_string($image)) {
                // Format URL directe (rare pour galerie mais possible)
                $image_id = attachment_url_to_postid($image);
                $processed_image = [
                    'id' => $image_id > 0 ? $image_id : null,
                    'url' => $image,
                    'thumbnail' => $image_id > 0 ? (wp_get_attachment_image_src($image_id, 'thumbnail')[0] ?? '') : $image,
                    'sizes' => []
                ];
            }

            if ($processed_image) {
                $processed_gallery[] = $processed_image;
            }
        }

        return $processed_gallery;
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
