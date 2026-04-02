<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCR Housing Service - Logique métier des Logements
 * * Orchestre la création et la mise à jour des logements, 
 * l'enregistrement des données et les repeaters complexes.
 * Pattern Singleton.
 * * @since 2.0.0 (Refactoring)
 */
class PCR_Housing_Service
{
    /**
     * Instance unique de la classe.
     * @var PCR_Housing_Service|null
     */
    private static $instance = null;

    /**
     * Constructeur privé (Singleton).
     */
    private function __construct() {}

    /**
     * Récupère l'instance unique.
     * * @return PCR_Housing_Service
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Met à jour un logement avec les données fournies ou en crée un nouveau.
     * * @param int $post_id ID du post (0 pour création)
     * @param array $data Données à mettre à jour
     * @return array
     */
    public function update_housing($post_id, $data)
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
                $new_post_data = [
                    'post_type' => sanitize_text_field($data['post_type']),
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
                    throw new Exception('Échec de la création du logement.');
                }
            } else {
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

            // 🚀 SYSTÈME HYBRIDE : Mapping corrigé pour correspondre à la Config et à Vue.js
            if (isset($data['payment_rules'])) {
                $rules = is_string($data['payment_rules']) ? json_decode(wp_unslash($data['payment_rules']), true) : $data['payment_rules'];

                if (is_array($rules)) {
                    // MAPPING CORRIGÉ : On utilise les clés exactes de class-housing-config.php
                    $mapping = [
                        'mode_pay'       => 'pc_pay_mode',
                        'deposit_type'   => 'pc_deposit_type',
                        'deposit_value'  => 'pc_deposit_value',
                        'delay_days'     => 'pc_balance_delay_days',
                        'caution_type'   => 'pc_caution_type',
                        'caution_amount' => 'pc_caution_amount'
                    ];

                    foreach ($mapping as $vue_key => $db_key) {
                        if (isset($rules[$vue_key])) {
                            // On ajoute la bonne clé dans $data pour que la boucle native s'en occupe
                            $data[$db_key] = $rules[$vue_key];

                            // FORCE SAVE : On l'écrit aussi directement pour être sûr à 100%
                            update_post_meta($post_id, $db_key, sanitize_text_field($rules[$vue_key]));
                        }
                    }

                    // On sauvegarde l'objet groupé au cas où une vieille fonction en aurait besoin
                    update_post_meta($post_id, '_pc_payment_rules', $rules);
                }

                unset($data['payment_rules']);
            }

            // 2. Mise à jour des champs (Désormais 100% Natif WordPress !)
            $updated_fields = 0;
            $config = PCR_Housing_Config::get_instance();
            $formatter = PCR_Housing_Formatter::get_instance();

            foreach ($data as $normalized_key => $value) {
                if (in_array($normalized_key, ['title', 'slug', 'status', 'content', 'excerpt', 'featured_image_id', 'post_type'])) {
                    continue;
                }

                if ($normalized_key === 'acf_groupes_images') {
                    $success = $this->update_repeater_field($post_id, 'groupes_images', $value);
                    if ($success) $updated_fields++;
                    continue;
                }

                $clean_key = str_replace('acf_', '', $normalized_key);
                $field_config = $config->get_field_config_by_slug($clean_key);

                if (!$field_config) {
                    continue;
                }

                if (in_array($clean_key, ['hero_desktop_url', 'hero_mobile_url'])) {
                    $value = $formatter->process_image_field($value);
                }

                // 🚀 GESTION DES CHAMPS COMPLEXES (JSON/Tableaux)
                if ($clean_key === 'icals_sync') {
                    $unslashed = wp_unslash($value);
                    $raw_array = is_string($unslashed) ? json_decode($unslashed, true) : $unslashed;

                    $clean_icals = [];
                    if (is_array($raw_array)) {
                        foreach ($raw_array as $item) {
                            if (!is_array($item)) continue;

                            $name = isset($item['name']) ? sanitize_text_field($item['name']) : '';
                            $url = isset($item['url']) ? esc_url_raw($item['url']) : '';

                            if (!empty($name) && !empty($url)) {
                                $clean_icals[] = ['name' => $name, 'url' => $url];
                            }
                        }
                    }

                    update_post_meta($post_id, $clean_key, $clean_icals);
                    $updated_fields++;
                    continue;
                }

                if ($clean_key === 'logement_faq') {
                    $unslashed = wp_unslash($value);

                    // Avec la modif du Store JS, on va recevoir un vrai JSON propre ici.
                    $raw_array = is_string($unslashed) ? json_decode($unslashed, true) : $unslashed;

                    $clean_faq = [];
                    if (is_array($raw_array)) {
                        foreach ($raw_array as $item) {
                            // On ignore TOUTE donnée corrompue
                            if (!is_array($item)) continue;

                            $q = isset($item['question']) ? sanitize_text_field($item['question']) : '';
                            $r = isset($item['reponse']) ? wp_kses_post($item['reponse']) : '';

                            // On ne sauvegarde QUE si l'un des deux champs est rempli (évite les lignes vides en BDD)
                            if (!empty($q) || !empty($r)) {
                                $clean_faq[] = ['question' => $q, 'reponse' => $r];
                            }
                        }
                    }

                    // Sauvegarde du tableau propre.
                    update_post_meta($post_id, $clean_key, $clean_faq);
                    $updated_fields++;
                    continue;
                }

                if (is_array($value)) {
                    $clean_value = array_map('sanitize_text_field', wp_unslash($value));
                } else {
                    $clean_value = $formatter->sanitize_field_value($clean_key, $value);
                }

                $result = update_post_meta($post_id, $clean_key, $clean_value);
                if ($result !== false) {
                    $updated_fields++;
                }
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

            /// 🚀 NETTOYAGE LÉGACY & CACHE (Destruction des fantômes)
            delete_post_meta($post_id, 'ical_url');
            delete_post_meta($post_id, 'field_pc_ical_url');
            delete_post_meta($post_id, '_booked_dates_cache');

            // 🚀 SYNCHRONISATION IMMÉDIATE DU CACHE ICAL
            if (class_exists('PC_Ical_Cache_Provider')) {
                $cache_provider = new PC_Ical_Cache_Provider();
                if (method_exists($cache_provider, 'sync_single_logement')) {
                    $cache_provider->sync_single_logement($post_id);
                }
            }

            // 🚀 SÉCURITÉ : Génération automatique du token iCal s'il n'existe pas
            $existing_token = get_post_meta($post_id, 'ical_export_token', true);
            if (empty($existing_token)) {
                $new_token = wp_generate_password(24, false);
                update_post_meta($post_id, 'ical_export_token', $new_token);
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
            error_log('[PCR Housing Service] Erreur mise à jour : ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la sauvegarde : ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Met à jour un champ Repeater ACF avec les données formatées.
     * * @param int $post_id ID du post
     * @param string $field_name Nom du champ repeater
     * @param array $data Données du repeater
     * @return bool Succès ou échec
     */
    public function update_repeater_field($post_id, $field_name, $data)
    {
        if (!function_exists('update_field')) {
            error_log("[PCR Housing Service] ACF non disponible pour update_repeater_field");
            return false;
        }

        error_log("🔧 Mise à jour du Repeater {$field_name} avec " . count($data) . " éléments");

        switch ($field_name) {
            case 'groupes_images':
                return $this->update_groupes_images_repeater($post_id, $data);

            default:
                error_log("[PCR Housing Service] Repeater {$field_name} non géré");
                return false;
        }
    }

    /**
     * Met à jour spécifiquement le Repeater "groupes_images".
     * * @param int $post_id ID du post
     * @param array $groupes_data Données des groupes d'images
     * @return bool Succès ou échec
     */
    private function update_groupes_images_repeater($post_id, $groupes_data)
    {
        if (!is_array($groupes_data)) {
            return false;
        }

        $formatted_data = [];

        foreach ($groupes_data as $index => $groupe) {
            $categorie_val = $groupe['categorie'] ?? '';

            if (empty($categorie_val)) {
                continue;
            }

            $formatted_groupe = [
                'field_693abf6847b68' => sanitize_text_field($categorie_val),
                'field_693abfe847b69' => sanitize_text_field($groupe['categorie_personnalisee'] ?? ''),
                'field_693ac02f47b6a' => []
            ];

            if (isset($groupe['images_du_groupe'])) {
                $images_data = $groupe['images_du_groupe'];

                if (is_array($images_data)) {
                    foreach ($images_data as $image_id) {
                        if (is_numeric($image_id) && (int)$image_id > 0) {
                            $formatted_groupe['field_693ac02f47b6a'][] = (int) $image_id;
                        }
                    }
                } elseif (is_string($images_data) && !empty($images_data)) {
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

        $success = update_field('field_693abf0447b67', $formatted_data, $post_id);

        if (!$success) {
            error_log("[PCR Housing Service] Échec sauvegarde Repeater via Keys. Tentative fallback via Noms...");
            return update_field('groupes_images', $groupes_data, $post_id);
        }

        return $success !== false;
    }
}
