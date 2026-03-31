<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCR Template API Controller
 * Gère le CRUD natif (Vue.js) pour le CPT 'pc_message' sans dépendre d'ACF.
 * Pattern Singleton.
 * @since 3.0.0
 */
class PCR_Template_API_Controller
{
    private static $instance = null;

    /**
     * Liste des clés meta (anciens champs ACF) à gérer pour un modèle de message.
     */
    private $meta_keys = [
        'pc_message_category',
        'pc_msg_type',
        'pc_trigger_action',
        'pc_trigger_relative',
        'pc_trigger_days',
        'pc_msg_subject',
        'pc_msg_attachment'
    ];

    private function __construct()
    {
        $this->init_hooks();
    }

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function init_hooks()
    {
        add_action('wp_ajax_pc_get_message_templates', [$this, 'ajax_get_templates']);
        add_action('wp_ajax_pc_get_message_template_details', [$this, 'ajax_get_template_details']);
        add_action('wp_ajax_pc_save_message_template', [$this, 'ajax_save_template']);
        add_action('wp_ajax_pc_delete_message_template', [$this, 'ajax_delete_template']);
    }

    /**
     * Liste tous les modèles de messages existants pour le tableau de bord
     */
    public function ajax_get_templates()
    {
        check_ajax_referer('pc_resa_manual_create', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Accès refusé.']);
        }

        $args = [
            'post_type'      => 'pc_message',
            'posts_per_page' => -1,
            'post_status'    => ['publish', 'draft'],
            'orderby'        => 'title',
            'order'          => 'ASC'
        ];

        $posts = get_posts($args);
        $templates = [];

        foreach ($posts as $post) {
            $category = get_post_meta($post->ID, 'pc_message_category', true);
            $type     = get_post_meta($post->ID, 'pc_msg_type', true);

            $templates[] = [
                'id'       => $post->ID,
                'title'    => $post->post_title,
                'status'   => $post->post_status,
                'category' => $category ?: 'email_system', // Fallback par défaut
                'type'     => $type ?: 'libre'
            ];
        }

        wp_send_json_success(['items' => $templates]);
    }

    /**
     * Récupère tous les détails d'un modèle spécifique pour l'édition (la modale)
     */
    public function ajax_get_template_details()
    {
        check_ajax_referer('pc_resa_manual_create', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Accès refusé.']);
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'pc_message') {
            wp_send_json_error(['message' => 'Modèle introuvable.']);
        }

        // On construit l'objet de données
        $data = [
            'id'      => $post->ID,
            'title'   => $post->post_title,
            'content' => $post->post_content,
            'status'  => $post->post_status,
        ];

        // Ajout des post metas (anciens champs ACF)
        foreach ($this->meta_keys as $key) {
            $data[$key] = get_post_meta($post->ID, $key, true);
        }

        // Formatage spécifique pour le nombre de jours (ACF renvoyait parfois une string)
        if (isset($data['pc_trigger_days'])) {
            $data['pc_trigger_days'] = intval($data['pc_trigger_days']);
        }

        wp_send_json_success(['data' => $data]);
    }

    /**
     * Crée ou met à jour un modèle de message
     */
    public function ajax_save_template()
    {
        check_ajax_referer('pc_resa_manual_create', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Accès refusé.']);
        }

        $payload_json = isset($_POST['payload']) ? stripslashes($_POST['payload']) : '';
        $data = json_decode($payload_json, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            wp_send_json_error(['message' => 'Données invalides.']);
        }

        $post_id = isset($data['id']) ? intval($data['id']) : 0;
        $title   = sanitize_text_field($data['title'] ?? 'Nouveau Modèle');

        // Autorise le HTML pour le contenu des emails (TinyMCE)
        $content = wp_kses_post($data['content'] ?? '');
        $status  = sanitize_text_field($data['status'] ?? 'publish');

        $post_data = [
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => $status,
            'post_type'    => 'pc_message'
        ];

        if ($post_id > 0) {
            $post_data['ID'] = $post_id;
            $updated_id = wp_update_post($post_data);
        } else {
            $updated_id = wp_insert_post($post_data);
        }

        if (is_wp_error($updated_id) || $updated_id === 0) {
            wp_send_json_error(['message' => 'Erreur lors de la sauvegarde du post WP.']);
        }

        // Sauvegarde des métadonnées (anciens champs ACF)
        foreach ($this->meta_keys as $key) {
            if (isset($data[$key])) {
                $value = $data[$key];

                // Sanitization basique
                $value = is_string($value) ? sanitize_text_field($value) : $value;

                update_post_meta($updated_id, $key, $value);

                // ✨ ASTUCE ACF Rétrocompatibilité (Optionnel mais recommandé)
                // ACF crée des clés de référence cachées (ex: _pc_message_category). 
                // En ajoutant cette ligne, ACF (s'il est encore là) reconnaîtra la donnée sans broncher.
                // Cela garantit une transition 100% smooth.
                update_post_meta($updated_id, '_' . $key, 'field_' . $key);
            }
        }

        wp_send_json_success([
            'message' => 'Modèle sauvegardé avec succès !',
            'id'      => $updated_id
        ]);
    }

    /**
     * Supprime un modèle de message (Corbeille)
     */
    public function ajax_delete_template()
    {
        check_ajax_referer('pc_resa_manual_create', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Accès refusé.']);
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if ($post_id > 0) {
            // true pour forcer la suppression, false pour mettre à la corbeille
            $result = wp_delete_post($post_id, false);

            if ($result) {
                wp_send_json_success(['message' => 'Modèle supprimé avec succès.']);
            }
        }

        wp_send_json_error(['message' => 'Erreur lors de la suppression.']);
    }
}

// L'initialisation se fera dans pc-reservation-core.php