<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCR Destination Ajax Controller
 * Gère les requêtes entre le Dashboard Vue.js et les services PHP pour les Destinations.
 */
class PCR_Destination_Ajax_Controller
{
    public static function init()
    {
        // Lecture : Liste des destinations
        add_action('wp_ajax_pc_get_destinations', [__CLASS__, 'get_destinations']);

        // Lecture : Détails d'une destination
        add_action('wp_ajax_pc_get_destination_details', [__CLASS__, 'get_destination_details']);

        // Écriture : Sauvegarde d'une destination
        add_action('wp_ajax_pc_save_destination', [__CLASS__, 'save_destination']);
    }

    /**
     * Récupère la liste paginée des destinations
     */
    public static function get_destinations()
    {
        check_ajax_referer('pc_resa_manual_create', 'security');

        $args = [
            'paged' => isset($_POST['page']) ? (int) $_POST['page'] : 1,
            'posts_per_page' => isset($_POST['per_page']) ? (int) $_POST['per_page'] : 20,
            'status_filter' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'all',
            's' => isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '',
        ];

        $repo = PCR_Destination_Repository::get_instance();
        $result = $repo->get_destination_list($args);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Récupère les données complètes d'une destination pour le formulaire d'édition
     */
    public static function get_destination_details()
    {
        check_ajax_referer('pc_resa_manual_create', 'security');

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

        if ($post_id <= 0) {
            wp_send_json_error(['message' => 'ID de destination invalide.']);
        }

        $repo = PCR_Destination_Repository::get_instance();
        $result = $repo->get_destination_details($post_id);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Sauvegarde (Création/Mise à jour) d'une destination depuis Vue.js
     */
    public static function save_destination()
    {
        check_ajax_referer('pc_resa_manual_create', 'security');

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

        // Récupération sécurisée du payload envoyé par FormData
        $payload = isset($_POST['payload']) ? wp_unslash($_POST['payload']) : [];

        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }

        if (empty($payload) || !is_array($payload)) {
            wp_send_json_error(['message' => 'Données invalides ou vides.']);
        }

        $service = PCR_Destination_Service::get_instance();
        $result = $service->update_destination($post_id, $payload);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}
