<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCR Experience Service - Logique métier des Expériences
 * Orchestre la création et la mise à jour des expériences, 
 * l'enregistrement des données et les repeaters complexes.
 * Pattern Singleton.
 * * @since 2.0.0 (Refactoring)
 */
class PCR_Experience_Service
{
    /**
     * Instance unique de la classe.
     * @var PCR_Experience_Service|null
     */
    private static $instance = null;

    /**
     * Constructeur privé (Singleton).
     */
    private function __construct() {}

    /**
     * Récupère l'instance unique.
     * @return PCR_Experience_Service
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Met à jour une expérience avec les données fournies.
     * Accepte ID = 0 pour créer une nouvelle expérience.
     * * @param int $post_id ID du post (0 pour création)
     * @param array $data Données à mettre à jour
     * @return array
     */
    public function update_experience($post_id, $data)
    {
        $post_id = (int) $post_id;
        $is_creation = ($post_id === 0);

        if (!$is_creation) {
            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'experience') {
                return [
                    'success' => false,
                    'message' => 'Expérience introuvable.',
                ];
            }

            if (!current_user_can('edit_post', $post_id)) {
                return [
                    'success' => false,
                    'message' => 'Permission insuffisante.',
                ];
            }
        } else {
            if (empty($data['post_type']) || $data['post_type'] !== 'experience') {
                return [
                    'success' => false,
                    'message' => 'Type de post invalide. Doit être "experience".',
                ];
            }

            if (!current_user_can('publish_posts')) {
                return [
                    'success' => false,
                    'message' => 'Permission insuffisante pour créer une expérience.',
                ];
            }

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
                $config = PCR_Experience_Config::get_instance();
                $formatter = PCR_Experience_Formatter::get_instance();

                foreach ($data as $normalized_key => $value) {
                    if (in_array($normalized_key, ['title', 'slug', 'status', 'content', 'excerpt', 'featured_image_id', 'post_type'])) {
                        continue;
                    }

                    $clean_key = str_replace('acf_', '', $normalized_key);
                    $field_config = $config->get_field_config_by_slug($clean_key);

                    if (!$field_config) {
                        continue;
                    }

                    $clean_value = $formatter->sanitize_field_value($clean_key, $value);
                    $update_key = $field_config['key'] ?? $field_config['meta_key'];

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
            error_log('[PCR Experience Service] Erreur mise à jour : ' . $e->getMessage());
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
            error_log("[PCR Experience Service] ACF non disponible pour update_repeater_field");
            return false;
        }

        error_log("🔧 Mise à jour du Repeater Experience {$field_name} avec " . count($data) . " éléments");

        switch ($field_name) {
            case 'exp_lieux_horaires_depart':
            case 'exp_periodes_fermeture':
            case 'exp_types_de_tarifs':
            case 'exp_faq':
                $result = update_field($field_name, $data, $post_id);
                return $result !== false;

            default:
                error_log("[PCR Experience Service] Repeater {$field_name} non géré");
                return false;
        }
    }
}
