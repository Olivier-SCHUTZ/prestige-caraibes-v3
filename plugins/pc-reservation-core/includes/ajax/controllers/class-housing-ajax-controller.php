<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Contrôleur AJAX pour le Housing Manager (Gestion des Logements).
 */
class PCR_Housing_Ajax_Controller extends PCR_Base_Ajax_Controller
{
    /**
     * Récupère la liste des logements.
     */
    public static function ajax_housing_get_list()
    {
        // Sécurité centralisée
        parent::verify_access('pc_resa_manual_create', 'nonce');

        if (!class_exists('PCR_Housing_Manager')) {
            wp_send_json_error(['message' => 'Module Housing Manager indisponible.']);
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

        $result = PCR_Housing_Manager::get_housing_list($args);

        if (!$result['success']) {
            wp_send_json_error(['message' => 'Erreur lors du chargement des logements.']);
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
     * Récupère les détails d'un logement.
     */
    public static function ajax_housing_get_details()
    {
        // Sécurité centralisée
        parent::verify_access('pc_resa_manual_create', 'nonce');

        if (!class_exists('PCR_Housing_Manager')) {
            wp_send_json_error(['message' => 'Module Housing Manager indisponible.']);
        }

        $post_id = isset($_REQUEST['post_id']) ? (int) $_REQUEST['post_id'] : 0;
        if ($post_id <= 0) {
            wp_send_json_error(['message' => 'ID de logement manquant.']);
        }

        $result = PCR_Housing_Manager::get_housing_details($post_id);

        if (!$result || !$result['success']) {
            wp_send_json_error(['message' => 'Logement introuvable ou erreur de chargement.']);
        }

        // Injection des données de tarifs depuis le Repository
        if (class_exists('PCR_Housing_Repository')) {
            $rates_data = PCR_Housing_Repository::get_rates($post_id);
            $result['data']['seasons_data'] = $rates_data['seasons'];
            $result['data']['promos_data'] = $rates_data['promos'];
        } else {
            error_log('PCR_Housing_Repository introuvable lors de la lecture du logement ID: ' . $post_id);
        }

        wp_send_json_success([
            'housing' => $result['data'],
            'post_id' => $post_id,
            'edit_url' => admin_url('post.php?post=' . $post_id . '&action=edit'),
            'view_url' => get_permalink($post_id)
        ]);
    }

    /**
     * Sauvegarde les modifications d'un logement.
     */
    public static function ajax_housing_save()
    {
        error_log('=== PC HOUSING SAVE DEBUG ===');
        error_log('POST data received: ' . print_r($_POST, true));
        error_log('=============================');

        // Sécurité centralisée
        parent::verify_access('pc_resa_manual_create', 'nonce');

        if (!class_exists('PCR_Housing_Manager')) {
            wp_send_json_error(['message' => 'Module Housing Manager indisponible.']);
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        if ($post_id < 0) {
            wp_send_json_error(['message' => 'ID invalide.']);
        }

        $data = [];

        // 1. Données natives de base du post WordPress
        if (isset($_POST['title'])) $data['title'] = sanitize_text_field($_POST['title']);
        if (isset($_POST['slug'])) $data['slug'] = sanitize_title($_POST['slug']);
        if (isset($_POST['status'])) $data['status'] = sanitize_text_field($_POST['status']);
        if (isset($_POST['content'])) $data['content'] = wp_kses_post($_POST['content']);
        if (isset($_POST['excerpt'])) $data['excerpt'] = wp_kses_post($_POST['excerpt']);
        if (isset($_POST['post_type'])) $data['post_type'] = sanitize_text_field($_POST['post_type']);
        if (isset($_POST['featured_image_id'])) $data['featured_image_id'] = (int) $_POST['featured_image_id'];

        // 1.5 DÉCODAGE VUE.JS : On récupère les champs préfixés par 'acf_' envoyés par le frontend
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'acf_') === 0) {
                $clean_key = substr($key, 4);
                $data[$clean_key] = $value;
            }
        }

        // 3. Appel au manager pour sauvegarder le reste (Titre, Contenu)
        $result = PCR_Housing_Manager::update_housing($post_id, $data);

        if (!$result['success']) {
            wp_send_json_error(['message' => $result['message']]);
        }

        // 4. Sauvegarde des tarifs via le Repository
        if (isset($_POST['rate_manager_data'])) {
            if (class_exists('PCR_Housing_Repository')) {
                PCR_Housing_Repository::save_rates($post_id, $_POST['rate_manager_data']);
            } else {
                error_log('PCR_Housing_Repository introuvable lors de la sauvegarde du logement ID: ' . $post_id);
            }
        }

        wp_send_json_success([
            'message' => $result['message'],
            'post_id' => $post_id,
            'updated_fields' => $result['data']['updated_fields'] ?? 0,
            'edit_url' => admin_url('post.php?post=' . $post_id . '&action=edit'),
        ]);
    }

    /**
     * Supprime un logement.
     */
    public static function ajax_housing_delete()
    {
        // Sécurité centralisée
        parent::verify_access('pc_resa_manual_create', 'nonce');

        if (!class_exists('PCR_Housing_Manager')) {
            wp_send_json_error(['message' => 'Module Housing Manager indisponible.']);
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        if ($post_id <= 0) {
            wp_send_json_error(['message' => 'ID de logement manquant ou invalide.']);
        }

        $result = PCR_Housing_Manager::delete_housing($post_id);

        if (!$result['success']) {
            wp_send_json_error(['message' => $result['message']]);
        }

        wp_send_json_success([
            'message' => $result['message'],
            'deleted_post_id' => $post_id
        ]);
    }
}
