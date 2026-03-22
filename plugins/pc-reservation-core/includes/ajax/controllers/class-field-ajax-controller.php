<?php
if (!defined('ABSPATH')) {
    exit; // Sécurité
}

/**
 * Contrôleur AJAX pour la gestion de la nouvelle architecture de champs natifs.
 */
class PCR_Field_Ajax_Controller extends PCR_Base_Ajax_Controller
{
    /**
     * Récupère les valeurs natives des champs pour un post donné
     */
    public static function ajax_get_fields()
    {
        // Utilisation de ta méthode de sécurité parent
        self::verify_access('pc_resa_manual_create', 'nonce', 'manage_options');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$post_id) {
            self::send_error('ID du post manquant.', 400);
        }

        $post_type = get_post_type($post_id);
        $manager = PCR_Field_Manager::init();
        $groups = $manager->get_field_groups();
        $values = [];

        // On parcourt les définitions pour ne renvoyer que les champs liés à ce type de post (ex: villa)
        foreach ($groups as $group_id => $config) {
            if (in_array($post_type, $config['post_types'])) {
                foreach ($config['fields'] as $field_key => $field_config) {
                    $values[$field_key] = $manager->get_native_field($field_key, $post_id);
                }
            }
        }

        self::send_success(['fields' => $values]);
    }

    /**
     * Sauvegarde les valeurs natives envoyées depuis Vue.js
     */
    public static function ajax_save_fields()
    {
        // Sécurité
        self::verify_access('pc_resa_manual_create', 'nonce', 'manage_options');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $fields_data = isset($_POST['fields']) ? (array) $_POST['fields'] : [];

        if (!$post_id) {
            self::send_error('ID du post manquant.', 400);
        }

        $manager = PCR_Field_Manager::init();

        foreach ($fields_data as $key => $value) {
            // Nettoyage de base des slashes ajoutés par l'AJAX
            // (Note : on affinera la sanitization selon le type de champ plus tard)
            $clean_value = is_array($value) ? wp_unslash($value) : wp_unslash(sanitize_text_field($value));
            $manager->save_native_field($key, $post_id, $clean_value);
        }

        self::send_success(['message' => 'Champs sauvegardés avec succès.']);
    }
}
