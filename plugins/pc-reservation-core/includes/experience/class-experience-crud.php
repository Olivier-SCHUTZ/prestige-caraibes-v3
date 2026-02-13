<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCR Experience CRUD - Logique de base de données
 * 
 * Gère la logique pure CRUD avec stratégie "Flatten" pour les repeaters.
 * Support ID=0 pour création via wp_insert_post().
 * 
 * @since 0.2.0
 */
class PCR_Experience_CRUD
{
    /**
     * Retourne les détails complets d'une expérience avec tous les champs.
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
            $mapped_fields = PCR_Experience_Data_Mapper::get_mapped_fields();

            foreach ($mapped_fields as $normalized_key => $meta_key) {
                $value = get_field($meta_key, $post_id);
                $details[$normalized_key] = self::process_field_value($normalized_key, $value);
            }
        } else {
            error_log('[PCR Experience CRUD] ACF non disponible, fallback vers get_post_meta');
            $mapped_fields = PCR_Experience_Data_Mapper::get_mapped_fields();

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
     * Met à jour ou crée une expérience avec les données fournies.
     * Support ID = 0 pour création.
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
            // Mode édition : validation
            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'experience') {
                return [
                    'success' => false,
                    'message' => 'Expérience introuvable.',
                ];
            }
        } else {
            // Mode création : validation du titre obligatoire
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
                $new_post_data = [
                    'post_type' => 'experience',
                    'post_title' => sanitize_text_field($data['title']),
                    'post_content' => wp_kses_post($data['content'] ?? ''),
                    'post_excerpt' => wp_kses_post($data['excerpt'] ?? ''),
                    'post_status' => in_array($data['status'] ?? 'draft', ['publish', 'pending', 'draft', 'private'])
                        ? sanitize_text_field($data['status']) : 'draft',
                    'post_author' => get_current_user_id(),
                ];

                if (!empty($data['slug'])) {
                    $new_post_data['post_name'] = sanitize_title($data['slug']);
                }

                $post_id = wp_insert_post($new_post_data);

                if (is_wp_error($post_id)) {
                    throw new Exception('Erreur lors de la création du post : ' . $post_id->get_error_message());
                }

                if (!$post_id) {
                    throw new Exception('Échec de la création de l\'expérience.');
                }
            } else {
                // Mode édition : mise à jour du post
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

            // 2. Mise à jour des champs ACF avec stratégie Flatten
            $updated_fields = self::update_acf_fields($post_id, $data);

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
                    'updated_fields' => $updated_fields,
                ],
            ];
        } catch (Exception $e) {
            error_log('[PCR Experience CRUD] Erreur mise à jour : ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la sauvegarde : ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Supprime définitivement une expérience.
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
            error_log('[PCR Experience CRUD] Erreur suppression : ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la suppression : ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Met à jour tous les champs ACF avec stratégie Flatten.
     * 
     * @param int $post_id ID du post
     * @param array $data Données du formulaire
     * @return int Nombre de champs mis à jour
     */
    private static function update_acf_fields($post_id, $data)
    {
        if (!function_exists('update_field')) {
            return 0;
        }

        $updated_fields = 0;

        foreach ($data as $key => $value) {
            // Skip les champs de base du post
            if (in_array($key, ['title', 'slug', 'status', 'content', 'excerpt', 'featured_image_id'])) {
                continue;
            }

            // Nettoyer la clé (enlever le préfixe acf_ si présent)
            $clean_key = str_replace('acf_', '', $key);

            // Obtenir la configuration du champ
            $field_config = PCR_Experience_Data_Mapper::get_field_config_by_slug($clean_key);
            if (!$field_config) {
                continue;
            }

            // Traitement spécial selon le type de champ
            $field_types = PCR_Experience_Data_Mapper::get_field_types();
            $field_type = $field_types[$clean_key] ?? 'text';

            // Traitement spécial pour les images
            if ($field_type === 'image') {
                $value = self::process_image_field($value);
            } elseif ($field_type === 'gallery') {
                $value = self::process_gallery_field($value);
            } elseif ($field_type === 'repeater') {
                $value = self::process_repeater_field($clean_key, $value);
            }

            // Sanitisation
            $clean_value = self::sanitize_field_value($clean_key, $value, $field_type);

            // Mise à jour via ACF
            $update_key = $field_config['key'] ?? $field_config['meta_key'];
            $result = update_field($update_key, $clean_value, $post_id);

            if ($result !== false) {
                $updated_fields++;
            }
        }

        return $updated_fields;
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
        $field_types = PCR_Experience_Data_Mapper::get_field_types();
        $field_type = $field_types[$field_key] ?? 'text';

        switch ($field_type) {
            case 'boolean':
                return (bool) $value;

            case 'number':
                return is_numeric($value) ? (float) $value : 0;

            case 'checkbox':
                return is_array($value) ? $value : [];

            case 'relationship':
                if (is_array($value)) {
                    return array_map('intval', $value);
                }
                return [];

            case 'repeater':
            case 'group':
            case 'gallery':
                return $value; // Laisser tel quel pour les champs complexes

            default:
                return is_string($value) ? trim($value) : $value;
        }
    }

    /**
     * Sanitise la valeur d'un champ avant sauvegarde.
     * 
     * @param string $field_key Clé du champ
     * @param mixed $value Valeur à sanitiser
     * @param string $field_type Type du champ
     * @return mixed Valeur sanitisée
     */
    private static function sanitize_field_value($field_key, $value, $field_type)
    {
        switch ($field_type) {
            case 'boolean':
                return (bool) $value;

            case 'number':
                return is_numeric($value) ? (float) $value : 0;

            case 'text':
                return sanitize_text_field($value);

            case 'textarea':
                return sanitize_textarea_field($value);

            case 'url':
                return esc_url_raw($value);

            case 'wysiwyg':
                return wp_kses_post($value);

            case 'checkbox':
                if (is_array($value)) {
                    return array_map('sanitize_text_field', $value);
                }
                return [];

            case 'relationship':
                if (is_array($value)) {
                    return array_map('intval', array_filter($value));
                }
                return [];

            case 'repeater':
            case 'group':
            case 'gallery':
            case 'image':
                return $value; // Laisser ACF gérer la sanitisation

            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * Traite un champ Image (conversion URL ↔ ID).
     * 
     * @param mixed $value Valeur du champ image
     * @return mixed Valeur traitée
     */
    private static function process_image_field($value)
    {
        if (empty($value)) {
            return '';
        }

        // Si c'est déjà un ID numérique
        if (is_numeric($value)) {
            $id = (int) $value;
            if ($id > 0 && get_post($id) && get_post_type($id) === 'attachment') {
                return $id;
            }
            return '';
        }

        // Conversion URL vers ID
        if (is_string($value) && (strpos($value, 'http') === 0 || strpos($value, '/wp-content/uploads') !== false)) {
            $attachment_id = attachment_url_to_postid($value);
            return $attachment_id > 0 ? (int) $attachment_id : $value;
        }

        return $value;
    }

    /**
     * Traite un champ Gallery (array d'IDs).
     * 
     * @param mixed $value Valeur du champ gallery
     * @return array Array d'IDs
     */
    private static function process_gallery_field($value)
    {
        if (!is_array($value)) {
            return [];
        }

        $processed = [];
        foreach ($value as $item) {
            $processed_item = self::process_image_field($item);
            if (!empty($processed_item)) {
                $processed[] = $processed_item;
            }
        }

        return $processed;
    }

    /**
     * Traite un champ Repeater selon sa structure.
     * 
     * @param string $field_key Clé du champ repeater
     * @param mixed $value Valeur du repeater
     * @return array Valeur traitée
     */
    private static function process_repeater_field($field_key, $value)
    {
        if (!is_array($value)) {
            return [];
        }

        // Traitement spécifique selon le type de repeater
        switch ($field_key) {
            case 'faq':
                return self::process_faq_repeater($value);
            case 'tarifs':
                return self::process_tarifs_repeater($value);
            default:
                return $value;
        }
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
     * Met à jour les champs Repeater d'une expérience avec la stratégie Flatten.
     * 
     * @param int $post_id ID du post
     * @param array $repeater_data Données des repeaters à mettre à jour
     * @return int Nombre de repeaters mis à jour avec succès
     */
    private static function update_experience_repeaters($post_id, $repeater_data)
    {
        if (!function_exists('update_field') || empty($repeater_data)) {
            return 0;
        }

        $updated_count = 0;

        foreach ($repeater_data as $field_key => $data) {
            // Nettoyage de la clé (enlever préfixe acf_ si présent)
            $clean_key = str_replace('acf_', '', $field_key);

            // Traiter selon le type de repeater
            switch ($clean_key) {
                case 'faq':
                    if (self::update_faq_repeater($post_id, $data)) {
                        $updated_count++;
                    }
                    break;

                case 'tarifs':
                    if (self::update_tarifs_repeater($post_id, $data)) {
                        $updated_count++;
                    }
                    break;

                default:
                    // Repeater générique : utiliser la logique standard
                    $field_config = PCR_Experience_Data_Mapper::get_field_config_by_slug($clean_key);
                    if ($field_config) {
                        $update_key = $field_config['key'] ?? $field_config['meta_key'];
                        if (update_field($update_key, $data, $post_id) !== false) {
                            $updated_count++;
                        }
                    }
                    break;
            }
        }

        return $updated_count;
    }

    /**
     * Traite spécifiquement les données du repeater FAQ.
     * 
     * @param array $faq_data Données du repeater FAQ
     * @return array Données traitées
     */
    private static function process_faq_repeater($faq_data)
    {
        if (!is_array($faq_data)) {
            return [];
        }

        $processed = [];
        foreach ($faq_data as $faq_item) {
            if (!is_array($faq_item)) {
                continue;
            }

            $processed_item = [];

            // Question (obligatoire)
            if (isset($faq_item['question'])) {
                $processed_item['question'] = sanitize_text_field($faq_item['question']);
            }

            // Réponse (obligatoire)
            if (isset($faq_item['reponse'])) {
                $processed_item['reponse'] = wp_kses_post($faq_item['reponse']);
            }

            // Ordre d'affichage (optionnel)
            if (isset($faq_item['ordre'])) {
                $processed_item['ordre'] = (int) $faq_item['ordre'];
            }

            // N'ajouter que si question et réponse sont présentes
            if (!empty($processed_item['question']) && !empty($processed_item['reponse'])) {
                $processed[] = $processed_item;
            }
        }

        return $processed;
    }

    /**
     * Traite spécifiquement les données du repeater Tarifs.
     * 
     * @param array $tarifs_data Données du repeater Tarifs
     * @return array Données traitées
     */
    private static function process_tarifs_repeater($tarifs_data)
    {
        if (!is_array($tarifs_data)) {
            return [];
        }

        $processed = [];
        foreach ($tarifs_data as $tarif_item) {
            if (!is_array($tarif_item)) {
                continue;
            }

            $processed_item = [];

            // Nom/titre du tarif (obligatoire)
            if (isset($tarif_item['nom'])) {
                $processed_item['nom'] = sanitize_text_field($tarif_item['nom']);
            }

            // Prix (obligatoire)
            if (isset($tarif_item['prix'])) {
                $processed_item['prix'] = is_numeric($tarif_item['prix']) ? (float) $tarif_item['prix'] : 0;
            }

            // Unité de prix (par personne, par groupe, etc.)
            if (isset($tarif_item['unite'])) {
                $processed_item['unite'] = sanitize_text_field($tarif_item['unite']);
            }

            // Description du tarif (optionnel)
            if (isset($tarif_item['description'])) {
                $processed_item['description'] = wp_kses_post($tarif_item['description']);
            }

            // Conditions particulières (optionnel)
            if (isset($tarif_item['conditions'])) {
                $processed_item['conditions'] = sanitize_textarea_field($tarif_item['conditions']);
            }

            // N'ajouter que si nom et prix sont présents
            if (!empty($processed_item['nom']) && isset($processed_item['prix'])) {
                $processed[] = $processed_item;
            }
        }

        return $processed;
    }

    /**
     * Met à jour spécifiquement le repeater FAQ d'une expérience.
     * 
     * @param int $post_id ID du post
     * @param array $faq_data Données du repeater FAQ
     * @return bool Succès ou échec
     */
    private static function update_faq_repeater($post_id, $faq_data)
    {
        if (!function_exists('update_field')) {
            return false;
        }

        // Traiter les données FAQ
        $processed_data = self::process_faq_repeater($faq_data);

        // Obtenir la configuration du champ FAQ
        $field_config = PCR_Experience_Data_Mapper::get_field_config_by_slug('faq');
        if (!$field_config) {
            error_log('[PCR Experience CRUD] Configuration du champ FAQ non trouvée');
            return false;
        }

        $update_key = $field_config['key'] ?? $field_config['meta_key'];

        // Mise à jour via ACF
        $result = update_field($update_key, $processed_data, $post_id);

        if ($result === false) {
            error_log("[PCR Experience CRUD] Échec sauvegarde FAQ pour le post {$post_id}");
            return false;
        }

        return true;
    }

    /**
     * Met à jour spécifiquement le repeater Tarifs d'une expérience.
     * 
     * @param int $post_id ID du post
     * @param array $tarifs_data Données du repeater Tarifs
     * @return bool Succès ou échec
     */
    private static function update_tarifs_repeater($post_id, $tarifs_data)
    {
        if (!function_exists('update_field')) {
            return false;
        }

        // Traiter les données Tarifs
        $processed_data = self::process_tarifs_repeater($tarifs_data);

        // Obtenir la configuration du champ Tarifs
        $field_config = PCR_Experience_Data_Mapper::get_field_config_by_slug('tarifs');
        if (!$field_config) {
            error_log('[PCR Experience CRUD] Configuration du champ Tarifs non trouvée');
            return false;
        }

        $update_key = $field_config['key'] ?? $field_config['meta_key'];

        // Mise à jour via ACF
        $result = update_field($update_key, $processed_data, $post_id);

        if ($result === false) {
            error_log("[PCR Experience CRUD] Échec sauvegarde Tarifs pour le post {$post_id}");
            return false;
        }

        return true;
    }
}
