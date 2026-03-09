<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Contrôleur AJAX pour l'Experience Manager (Gestion des Expériences).
 */
class PCR_Experience_Ajax_Controller extends PCR_Base_Ajax_Controller
{
    /**
     * Récupère la liste des expériences.
     */
    public static function ajax_experience_get_list()
    {
        // Sécurité centralisée
        parent::verify_access('pc_resa_manual_create', 'nonce');

        if (!class_exists('PCR_Experience_Manager')) {
            wp_send_json_error(['message' => 'Module Experience Manager indisponible.']);
        }

        $args = [
            'posts_per_page' => isset($_REQUEST['per_page']) ? (int) $_REQUEST['per_page'] : 20,
            'paged'          => isset($_REQUEST['page']) ? (int) $_REQUEST['page'] : 1,
            'orderby'        => isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'title',
            'order'          => isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'ASC',
            's'              => isset($_REQUEST['search']) ? sanitize_text_field($_REQUEST['search']) : '',
            'type_filter'    => isset($_REQUEST['type_filter']) ? sanitize_text_field($_REQUEST['type_filter']) : '',
            'status_filter'  => isset($_REQUEST['status_filter']) ? sanitize_text_field($_REQUEST['status_filter']) : '',
            'mode_filter'    => isset($_REQUEST['mode_filter']) ? sanitize_text_field($_REQUEST['mode_filter']) : '',
        ];

        $result = PCR_Experience_Manager::get_experience_list($args);

        if (!$result['success']) {
            wp_send_json_error(['message' => 'Erreur lors du chargement des expériences.']);
        }

        wp_send_json_success([
            'items' => $result['items'],
            'total' => $result['total'],
            'pages' => $result['pages'],
            'current_page' => $result['current_page'],
            'per_page' => $args['posts_per_page']
        ]);
    }

    /**
     * Récupère les détails d'une expérience.
     */
    public static function ajax_experience_get_details()
    {
        // Sécurité centralisée
        parent::verify_access('pc_resa_manual_create', 'nonce');

        if (!class_exists('PCR_Experience_Manager')) {
            wp_send_json_error(['message' => 'Module Experience Manager indisponible.']);
        }

        $post_id = isset($_REQUEST['post_id']) ? (int) $_REQUEST['post_id'] : 0;
        if ($post_id <= 0) {
            wp_send_json_error(['message' => 'ID d\'expérience manquant.']);
        }

        $result = PCR_Experience_Manager::get_experience_details($post_id);

        if (!$result || !$result['success']) {
            wp_send_json_error(['message' => 'Expérience introuvable ou erreur de chargement.']);
        }

        wp_send_json_success([
            'experience' => $result['data'],
            'post_id' => $post_id,
            'edit_url' => admin_url('post.php?post=' . $post_id . '&action=edit'),
            'view_url' => get_permalink($post_id)
        ]);
    }

    /**
     * Sauvegarde les modifications d'une expérience.
     */
    public static function ajax_experience_save()
    {
        error_log('=== PC EXPERIENCE SAVE DEBUG ===');
        error_log('POST data received: ' . print_r($_POST, true));
        error_log('=================================');

        // Sécurité centralisée
        parent::verify_access('pc_resa_manual_create', 'nonce');

        if (!class_exists('PCR_Experience_Manager')) {
            wp_send_json_error(['message' => 'Module Experience Manager indisponible.']);
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        if ($post_id < 0) {
            wp_send_json_error(['message' => 'ID invalide.']);
        }

        $data = [];

        if (isset($_POST['title'])) $data['title'] = sanitize_text_field($_POST['title']);
        if (isset($_POST['slug'])) $data['slug'] = sanitize_title($_POST['slug']);
        if (isset($_POST['status'])) $data['status'] = sanitize_text_field($_POST['status']);
        if (isset($_POST['content'])) $data['content'] = wp_kses_post($_POST['content']);
        if (isset($_POST['excerpt'])) $data['excerpt'] = wp_kses_post($_POST['excerpt']);
        if (isset($_POST['post_type'])) $data['post_type'] = sanitize_text_field($_POST['post_type']);
        if (isset($_POST['featured_image_id'])) $data['featured_image_id'] = (int) $_POST['featured_image_id'];

        // Champs ACF
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'acf_') === 0) {
                $field_key = substr($key, 4);
                $data[$field_key] = $value;
            }
        }

        $result = PCR_Experience_Manager::update_experience($post_id, $data);

        if (!$result['success']) {
            wp_send_json_error(['message' => $result['message']]);
        }

        wp_send_json_success([
            'message' => $result['message'],
            'post_id' => $post_id,
            'updated_fields' => $result['data']['updated_fields'] ?? 0,
            'edit_url' => admin_url('post.php?post=' . $post_id . '&action=edit'),
        ]);
    }

    /**
     * Supprime une expérience.
     */
    public static function ajax_experience_delete()
    {
        // Sécurité centralisée
        parent::verify_access('pc_resa_manual_create', 'nonce');

        if (!class_exists('PCR_Experience_Manager')) {
            wp_send_json_error(['message' => 'Module Experience Manager indisponible.']);
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        if ($post_id <= 0) {
            wp_send_json_error(['message' => 'ID d\'expérience manquant ou invalide.']);
        }

        $result = PCR_Experience_Manager::delete_experience($post_id);

        if (!$result['success']) {
            wp_send_json_error(['message' => $result['message']]);
        }

        wp_send_json_success([
            'message' => $result['message'],
            'deleted_post_id' => $post_id
        ]);
    }
}
