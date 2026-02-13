<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCR Experience Controller - Point d'entrée principal
 * 
 * Gère les permissions, l'initialisation et la coordination entre les modules.
 * Fonctionnellement identique au Housing Manager mais avec architecture modulaire.
 * 
 * @since 0.2.0
 */
class PCR_Experience_Controller
{
    /**
     * Initialisation des hooks.
     */
    public static function init()
    {
        // Hook d'initialisation si nécessaire
        add_action('wp_ajax_get_experiences_list', [__CLASS__, 'ajax_get_list']);
        add_action('wp_ajax_get_experience_details', [__CLASS__, 'ajax_get_details']);
        add_action('wp_ajax_save_experience', [__CLASS__, 'ajax_save']);
        add_action('wp_ajax_delete_experience', [__CLASS__, 'ajax_delete']);

        // Hooks AJAX avec préfixe pc_ (utilisés par dashboard-experience.js)
        self::init_ajax_hooks();
    }

    /**
     * Initialise les hooks AJAX spécifiques au dashboard des expériences.
     */
    private static function init_ajax_hooks()
    {
        // 1. Récupère détails d'une expérience
        add_action('wp_ajax_pc_get_experience_details', [__CLASS__, 'ajax_pc_get_experience_details']);

        // 2. Crée une nouvelle expérience
        add_action('wp_ajax_pc_create_experience', [__CLASS__, 'ajax_pc_create_experience']);

        // 3. Met à jour une expérience
        add_action('wp_ajax_pc_update_experience', [__CLASS__, 'ajax_pc_update_experience']);

        // 4. Supprime une expérience
        add_action('wp_ajax_pc_delete_experience', [__CLASS__, 'ajax_pc_delete_experience']);

        // 5. Liste les expériences avec filtres
        add_action('wp_ajax_pc_get_experiences_list', [__CLASS__, 'ajax_pc_get_experiences_list']);
    }

    /**
     * Retourne une liste légère des expériences pour le tableau dashboard.
     * 
     * @param array $args Arguments de requête (pagination, filtres)
     * @return array
     */
    public static function get_list($args = [])
    {
        // Validation des permissions
        if (!current_user_can('edit_posts')) {
            return [
                'success' => false,
                'message' => 'Permission insuffisante.',
            ];
        }

        // Déléguer à la classe List
        return PCR_Experience_List::get_experiences($args);
    }

    /**
     * Retourne les détails complets d'une expérience avec tous les champs.
     * 
     * @param int $post_id ID du post
     * @return array|false
     */
    public static function get_details($post_id)
    {
        $post_id = (int) $post_id;

        // Validation des permissions
        if ($post_id > 0 && !current_user_can('edit_post', $post_id)) {
            return [
                'success' => false,
                'message' => 'Permission insuffisante.',
            ];
        }

        // Déléguer à la classe CRUD
        return PCR_Experience_CRUD::get_experience_details($post_id);
    }

    /**
     * Met à jour ou crée une expérience avec les données fournies.
     * Support ID = 0 pour création.
     * 
     * @param int $post_id ID du post (0 pour création)
     * @param array $data Données à mettre à jour
     * @return array
     */
    public static function update($post_id, $data)
    {
        $post_id = (int) $post_id;
        $is_creation = ($post_id === 0);

        // Validation des permissions
        if ($is_creation) {
            if (!current_user_can('publish_posts')) {
                return [
                    'success' => false,
                    'message' => 'Permission insuffisante pour créer une expérience.',
                ];
            }
        } else {
            if (!current_user_can('edit_post', $post_id)) {
                return [
                    'success' => false,
                    'message' => 'Permission insuffisante pour modifier cette expérience.',
                ];
            }
        }

        // Déléguer à la classe CRUD
        return PCR_Experience_CRUD::update_experience($post_id, $data);
    }

    /**
     * Supprime définitivement une expérience.
     * 
     * @param int $post_id ID du post à supprimer
     * @return array
     */
    public static function delete($post_id)
    {
        $post_id = (int) $post_id;

        // Validation des permissions
        if (!current_user_can('delete_post', $post_id)) {
            return [
                'success' => false,
                'message' => 'Permission insuffisante pour supprimer cette expérience.',
            ];
        }

        // Déléguer à la classe CRUD
        return PCR_Experience_CRUD::delete_experience($post_id);
    }

    /**
     * Endpoint AJAX pour récupérer la liste des expériences.
     */
    public static function ajax_get_list()
    {
        // Vérification nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'pc_resa_manual_create')) {
            wp_die('Nonce invalide');
        }

        // Paramètres de requête
        $args = [
            'posts_per_page' => (int) ($_POST['per_page'] ?? 20),
            'paged' => (int) ($_POST['page'] ?? 1),
            'orderby' => sanitize_text_field($_POST['orderby'] ?? 'title'),
            'order' => in_array($_POST['order'] ?? 'ASC', ['ASC', 'DESC']) ? $_POST['order'] : 'ASC',
            's' => sanitize_text_field($_POST['search'] ?? ''),
            'status_filter' => sanitize_text_field($_POST['status_filter'] ?? ''),
            'availability_filter' => sanitize_text_field($_POST['availability_filter'] ?? ''),
        ];

        $result = self::get_list($args);
        wp_send_json($result);
    }

    /**
     * Endpoint AJAX pour récupérer les détails d'une expérience.
     */
    public static function ajax_get_details()
    {
        // Vérification nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'pc_dashboard_nonce')) {
            wp_die('Nonce invalide');
        }

        $post_id = (int) ($_POST['post_id'] ?? 0);
        $result = self::get_details($post_id);
        wp_send_json($result);
    }

    /**
     * Endpoint AJAX pour sauvegarder une expérience.
     */
    public static function ajax_save()
    {
        // Vérification nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'pc_dashboard_nonce')) {
            wp_die('Nonce invalide');
        }

        $post_id = (int) ($_POST['post_id'] ?? 0);

        // Récupérer toutes les données du formulaire
        $data = [];
        foreach ($_POST as $key => $value) {
            if (in_array($key, ['action', 'nonce', 'post_id'])) {
                continue;
            }
            $data[$key] = $value;
        }

        $result = self::update($post_id, $data);
        wp_send_json($result);
    }

    /**
     * Endpoint AJAX pour supprimer une expérience.
     */
    public static function ajax_delete()
    {
        // Vérification nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'pc_dashboard_nonce')) {
            wp_die('Nonce invalide');
        }

        $post_id = (int) ($_POST['post_id'] ?? 0);
        $result = self::delete($post_id);
        wp_send_json($result);
    }

    /**
     * Retourne les statistiques rapides des expériences.
     * 
     * @return array
     */
    public static function get_stats()
    {
        if (!current_user_can('edit_posts')) {
            return [
                'success' => false,
                'message' => 'Permission insuffisante.',
            ];
        }

        $stats = [
            'total' => wp_count_posts('experience'),
            'by_availability' => [],
        ];

        // Compter par disponibilité
        $availability_query = new WP_Query([
            'post_type' => 'experience',
            'post_status' => ['publish', 'pending', 'draft', 'private'],
            'posts_per_page' => -1,
            'meta_key' => 'exp_availability',
            'fields' => 'ids',
        ]);

        $availability_counts = [
            'InStock' => 0,
            'SoldOut' => 0,
            'PreOrder' => 0,
            'not_set' => 0,
        ];

        if ($availability_query->have_posts()) {
            foreach ($availability_query->posts as $post_id) {
                $availability = get_field('exp_availability', $post_id);
                if (isset($availability_counts[$availability])) {
                    $availability_counts[$availability]++;
                } else {
                    $availability_counts['not_set']++;
                }
            }
        }

        $stats['by_availability'] = $availability_counts;

        return [
            'success' => true,
            'data' => $stats,
        ];
    }

    // ========================================
    // MÉTHODES AJAX AVEC PRÉFIXE PC_
    // ========================================

    /**
     * AJAX: Récupère les détails d'une expérience (pc_get_experience_details)
     */
    public static function ajax_pc_get_experience_details()
    {
        // Vérification nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'pc_resa_manual_create')) {
            wp_send_json_error(['message' => 'Nonce invalide']);
        }

        $experience_id = (int) ($_POST['experience_id'] ?? 0);
        $result = self::get_details($experience_id);
        wp_send_json($result);
    }

    /**
     * AJAX: Crée une nouvelle expérience (pc_create_experience)
     */
    public static function ajax_pc_create_experience()
    {
        // Vérification nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'pc_resa_manual_create')) {
            wp_send_json_error(['message' => 'Nonce invalide']);
        }

        // Récupérer les données du formulaire
        $data = [];
        foreach ($_POST as $key => $value) {
            if (in_array($key, ['action', 'nonce', 'experience_id'])) {
                continue;
            }
            $data[$key] = $value;
        }

        $result = self::update(0, $data); // 0 pour création
        wp_send_json($result);
    }

    /**
     * AJAX: Met à jour une expérience existante (pc_update_experience)
     */
    public static function ajax_pc_update_experience()
    {
        // Vérification nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'pc_resa_manual_create')) {
            wp_send_json_error(['message' => 'Nonce invalide']);
        }

        $experience_id = (int) ($_POST['experience_id'] ?? 0);

        // Récupérer les données du formulaire
        $data = [];
        foreach ($_POST as $key => $value) {
            if (in_array($key, ['action', 'nonce', 'experience_id'])) {
                continue;
            }
            $data[$key] = $value;
        }

        $result = self::update($experience_id, $data);
        wp_send_json($result);
    }

    /**
     * AJAX: Supprime une expérience (pc_delete_experience)
     */
    public static function ajax_pc_delete_experience()
    {
        // Vérification nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'pc_resa_manual_create')) {
            wp_send_json_error(['message' => 'Nonce invalide']);
        }

        $experience_id = (int) ($_POST['experience_id'] ?? 0);
        $result = self::delete($experience_id);
        wp_send_json($result);
    }

    /**
     * AJAX: Liste les expériences avec filtres (pc_get_experiences_list)
     */
    public static function ajax_pc_get_experiences_list()
    {
        // Vérification nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'pc_resa_manual_create')) {
            wp_send_json_error(['message' => 'Nonce invalide']);
        }

        // Paramètres de requête avec mapping des noms JavaScript
        $args = [
            'posts_per_page' => (int) ($_POST['per_page'] ?? 20),
            'paged' => (int) ($_POST['page'] ?? 1),
            'orderby' => sanitize_text_field($_POST['orderby'] ?? 'title'),
            'order' => in_array($_POST['order'] ?? 'ASC', ['ASC', 'DESC']) ? $_POST['order'] : 'ASC',
            's' => sanitize_text_field($_POST['search'] ?? ''),
            'status_filter' => sanitize_text_field($_POST['status'] ?? ''), // JS envoie 'status'
            'availability_filter' => sanitize_text_field($_POST['availability_filter'] ?? ''),
        ];

        $result = self::get_list($args);
        wp_send_json($result);
    }
}
