<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCR Experience Service - Logique métier des Expériences
 * Orchestre la création et la mise à jour des expériences.
 * Sauvegarde 100% Native (Les repeaters complexes sont gérés en JSON par Vue.js).
 * Pattern Singleton.
 * * @since 2.0.0 (Refactoring)
 */
class PCR_Experience_Service
{
    private static $instance = null;

    private function __construct() {}

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function update_experience($post_id, $data)
    {
        $post_id = (int) $post_id;
        $is_creation = ($post_id === 0);

        if (!$is_creation) {
            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'experience') {
                return ['success' => false, 'message' => 'Expérience introuvable.'];
            }

            if (!current_user_can('edit_post', $post_id)) {
                return ['success' => false, 'message' => 'Permission insuffisante.'];
            }
        } else {
            if (empty($data['post_type']) || $data['post_type'] !== 'experience') {
                return ['success' => false, 'message' => 'Type de post invalide.'];
            }

            if (!current_user_can('publish_posts')) {
                return ['success' => false, 'message' => 'Permission insuffisante.'];
            }

            if (empty($data['title'])) {
                return ['success' => false, 'message' => 'Le nom de l\'expérience est obligatoire.'];
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
                    throw new Exception('Erreur création : ' . $post_id->get_error_message());
                }
            } else {
                $post_data = [];
                if (isset($data['title'])) $post_data['post_title'] = sanitize_text_field($data['title']);
                if (isset($data['slug'])) $post_data['post_name'] = sanitize_title($data['slug']);
                if (isset($data['content'])) $post_data['post_content'] = wp_kses_post($data['content']);
                if (isset($data['excerpt'])) $post_data['post_excerpt'] = wp_kses_post($data['excerpt']);
                if (isset($data['status'])) {
                    $status = sanitize_text_field($data['status']);
                    if (in_array($status, ['publish', 'pending', 'draft', 'private'])) {
                        $post_data['post_status'] = $status;
                    }
                }

                if (!empty($post_data)) {
                    $post_data['ID'] = $post_id;
                    wp_update_post($post_data);
                }
            }

            // 2. Mise à jour des champs (100% Natif)
            $updated_fields = 0;
            $config = PCR_Experience_Config::get_instance();
            $formatter = PCR_Experience_Formatter::get_instance();

            // Liste des champs qui sont des tableaux ou objets complexes
            $complex_fields = [
                'exp_lieux_horaires_depart',
                'exp_periodes_fermeture',
                'exp_types_de_tarifs',
                'exp_faq',
                'seasons_data',
                'promos_data',
                'exp_a_prevoir',
                'exp_accessibilite',
                'exp_periode',
                'exp_jour',
                'exp_zone_intervention'
            ];

            foreach ($data as $normalized_key => $value) {
                if (in_array($normalized_key, ['title', 'slug', 'status', 'content', 'excerpt', 'featured_image_id', 'post_type'])) {
                    continue;
                }

                $clean_key = str_replace('acf_', '', $normalized_key);

                // 🚀 PROTECTION VITALE : Ne pas détruire les tableaux multidimensionnels
                if (in_array($clean_key, $complex_fields)) {
                    $clean_value = wp_unslash($value); // On garde l'objet JSON/Array intact

                    // SAUVEGARDE NATIVE EXCLUSIVE (Dashboard est le seul maître, on évite ACF)
                    $result = update_post_meta($post_id, $clean_key, $clean_value);
                    if ($result !== false) {
                        $updated_fields++;
                    }
                } else {
                    // Nettoyage classique pour les champs simples et les images
                    if (is_array($value)) {
                        $clean_value = array_map('sanitize_text_field', wp_unslash($value));
                    } else {
                        $clean_value = $formatter->sanitize_field_value($clean_key, $value);
                    }

                    // 1. SAUVEGARDE NATIVE (Pour le Dashboard)
                    $result = update_post_meta($post_id, $clean_key, $clean_value);
                    if ($result !== false) {
                        $updated_fields++;
                    }

                    // 2. SAUVEGARDE ACF (Double Write pour garder les images visibles dans l'admin WP)
                    $field_config = $config->get_field_config_by_slug($clean_key);
                    if (function_exists('update_field') && $field_config && isset($field_config['key'])) {
                        update_field($field_config['key'], $clean_value, $post_id);
                    }
                }
            }

            // 3. Image à la une
            if (isset($data['featured_image_id'])) {
                $image_id = (int) $data['featured_image_id'];
                if ($image_id > 0) set_post_thumbnail($post_id, $image_id);
                else delete_post_thumbnail($post_id);
            }

            return [
                'success' => true,
                'message' => $is_creation ? 'Expérience créée.' : 'Expérience mise à jour.',
                'data' => ['id' => $post_id, 'updated_fields' => $updated_fields],
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
