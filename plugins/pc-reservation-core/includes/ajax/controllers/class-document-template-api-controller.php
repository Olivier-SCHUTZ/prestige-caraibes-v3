<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCR Document Template API Controller
 * Gère le CRUD natif (Vue.js) pour le CPT 'pc_pdf_template' sans ACF.
 * Pattern Singleton.
 * @since 3.0.0
 */
class PCR_Document_Template_API_Controller
{
    private static $instance = null;

    /**
     * Liste des clés meta (anciens champs ACF)
     */
    private $meta_keys = [
        'pc_model_context', // global, location, experience
        'pc_doc_type'       // devis, facture, contrat, voucher, document...
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
        add_action('wp_ajax_pc_get_pdf_templates', [$this, 'ajax_get_templates']);
        add_action('wp_ajax_pc_get_pdf_template_details', [$this, 'ajax_get_template_details']);
        add_action('wp_ajax_pc_save_pdf_template', [$this, 'ajax_save_template']);
        add_action('wp_ajax_pc_delete_pdf_template', [$this, 'ajax_delete_template']);
    }

    /**
     * Liste tous les modèles PDF existants
     */
    public function ajax_get_templates()
    {
        check_ajax_referer('pc_resa_manual_create', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Accès refusé.']);
        }

        $args = [
            'post_type'      => 'pc_pdf_template',
            'posts_per_page' => -1,
            'post_status'    => ['publish', 'draft'],
            'orderby'        => 'title',
            'order'          => 'ASC'
        ];

        $posts = get_posts($args);
        $templates = [];

        foreach ($posts as $post) {
            $context = get_post_meta($post->ID, 'pc_model_context', true);
            $type    = get_post_meta($post->ID, 'pc_doc_type', true);

            $templates[] = [
                'id'       => $post->ID,
                'title'    => $post->post_title,
                'status'   => $post->post_status,
                'context'  => $context ?: 'global',
                'type'     => $type ?: 'document'
            ];
        }

        wp_send_json_success(['items' => $templates]);
    }

    /**
     * Récupère tous les détails d'un modèle PDF spécifique
     */
    public function ajax_get_template_details()
    {
        check_ajax_referer('pc_resa_manual_create', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Accès refusé.']);
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'pc_pdf_template') {
            wp_send_json_error(['message' => 'Modèle PDF introuvable.']);
        }

        $data = [
            'id'      => $post->ID,
            'title'   => $post->post_title,
            'content' => $post->post_content,
            'status'  => $post->post_status,
        ];

        foreach ($this->meta_keys as $key) {
            $data[$key] = get_post_meta($post->ID, $key, true);
        }

        wp_send_json_success(['data' => $data]);
    }

    /**
     * Crée ou met à jour un modèle PDF
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
        $title   = sanitize_text_field($data['title'] ?? 'Nouveau Modèle PDF');
        $content = wp_kses_post($data['content'] ?? ''); // Autorise le HTML pour le design du PDF
        $status  = sanitize_text_field($data['status'] ?? 'publish');

        $post_data = [
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => $status,
            'post_type'    => 'pc_pdf_template'
        ];

        if ($post_id > 0) {
            $post_data['ID'] = $post_id;
            $updated_id = wp_update_post($post_data);
        } else {
            $updated_id = wp_insert_post($post_data);
        }

        if (is_wp_error($updated_id) || $updated_id === 0) {
            wp_send_json_error(['message' => 'Erreur lors de la sauvegarde du modèle PDF.']);
        }

        // Sauvegarde des métadonnées avec rétrocompatibilité ACF
        foreach ($this->meta_keys as $key) {
            if (isset($data[$key])) {
                $value = sanitize_text_field($data[$key]);
                update_post_meta($updated_id, $key, $value);
                update_post_meta($updated_id, '_' . $key, 'field_' . $key);
            }
        }

        wp_send_json_success([
            'message' => 'Modèle PDF sauvegardé avec succès !',
            'id'      => $updated_id
        ]);
    }

    /**
     * Supprime un modèle PDF
     */
    public function ajax_delete_template()
    {
        check_ajax_referer('pc_resa_manual_create', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Accès refusé.']);
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if ($post_id > 0) {
            $result = wp_delete_post($post_id, false);
            if ($result) {
                wp_send_json_success(['message' => 'Modèle PDF supprimé avec succès.']);
            }
        }

        wp_send_json_error(['message' => 'Erreur lors de la suppression.']);
    }
}
