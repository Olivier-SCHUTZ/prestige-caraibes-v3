<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PCR_Documents (Façade / Proxy v2)
 * * Ancienne classe monolithique transformée en Façade.
 * Conserve la rétrocompatibilité stricte de l'API publique et des hooks WordPress,
 * mais délègue toute la logique aux nouveaux micro-services (DDD).
 */
class PCR_Documents
{
    public static function init()
    {
        // Chargement du vendor pour DomPDF
        $autoload_path = dirname(dirname(__FILE__)) . '/vendor/autoload.php';
        if (file_exists($autoload_path)) {
            require_once $autoload_path;
        }

        // Hooks WP natifs (Maintenus ici pour le paramétrage)
        add_action('init', [__CLASS__, 'register_cpt']);
        add_action('acf/init', [__CLASS__, 'register_acf_fields']);
        add_filter('acf/load_field/name=pc_linked_cgv', [__CLASS__, 'load_cgv_choices']);
        add_filter('acf/load_field/name=pc_cgv_default_location', [__CLASS__, 'load_cgv_choices']);
        add_filter('acf/load_field/name=pc_cgv_default_experience', [__CLASS__, 'load_cgv_choices']);
        add_action('admin_init', [__CLASS__, 'create_documents_table']);

        // Hooks AJAX natifs
        add_action('wp_ajax_pc_generate_document', [__CLASS__, 'ajax_generate_document']);
        add_action('wp_ajax_pc_get_documents_list', [__CLASS__, 'ajax_get_documents_list']);
    }

    public static function register_cpt()
    {
        register_post_type('pc_pdf_template', [
            'labels' => [
                'name' => 'Modèles PDF',
                'singular_name' => 'Modèle PDF',
                'menu_name' => 'Modèles PDF',
                'add_new_item' => 'Nouveau Modèle PDF',
                'edit_item' => 'Modifier le Modèle PDF',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'pc-reservation-settings',
            'supports' => ['title', 'editor'],
            'menu_icon' => 'dashicons-media-document',
        ]);
    }

    public static function create_documents_table()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pc_documents';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                reservation_id bigint(20) UNSIGNED NOT NULL,
                type_doc varchar(50) NOT NULL,
                numero_doc varchar(50) DEFAULT NULL,
                nom_fichier varchar(191) NOT NULL,
                chemin_fichier text NOT NULL,
                url_fichier text NOT NULL,
                date_creation datetime DEFAULT CURRENT_TIMESTAMP,
                user_id bigint(20) UNSIGNED DEFAULT 0,
                PRIMARY KEY  (id),
                UNIQUE KEY uniq_doc (reservation_id, type_doc),
                KEY reservation_id (reservation_id)
            ) $charset_collate;";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    public static function register_acf_fields()
    {
        if (!function_exists('acf_add_local_field_group')) return;

        acf_add_local_field_group([
            'key' => 'group_pc_pdf_config',
            'title' => 'Paramètres du Modèle',
            'fields' => [
                [
                    'key' => 'field_pc_model_context',
                    'label' => 'Contexte d\'affichage',
                    'name' => 'pc_model_context',
                    'type' => 'select',
                    'instructions' => 'Définit pour quel type de réservation ce modèle sera proposé.',
                    'choices' => [
                        'global' => 'Afficher pour tout (Défaut)',
                        'location' => 'Réservations de type \'logement\' uniquement',
                        'experience' => 'Réservations de type \'experience\' uniquement',
                    ],
                    'default_value' => 'global',
                    'ui' => 1,
                ],
                [
                    'key' => 'field_pc_linked_cgv',
                    'label' => 'Joindre les CGV ? (OPTIONNEL)',
                    'name' => 'pc_linked_cgv',
                    'type' => 'message',
                    'message' => 'Ce document est un modèle libre. Les CGV automatiques ne seront pas ajoutées automatiquement, sauf si vous les copiez-collez dans l\'éditeur.',
                ],
            ],
            'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'pc_pdf_template']]]
        ]);
    }

    public static function load_cgv_choices($field)
    {
        $field['choices'] = [];
        if (have_rows('pc_pdf_cgv_library', 'option')) {
            while (have_rows('pc_pdf_cgv_library', 'option')) {
                the_row();
                $title = get_sub_field('cgv_title');
                if ($title) $field['choices'][$title] = $title;
            }
        }
        return $field;
    }

    // =========================================================================
    // 🚦 METHODES PROXY VERS LE NOUVEAU SERVICE (Zero Regressions)
    // =========================================================================

    public static function generate_native($doc_type, $reservation_id)
    {
        return PCR_Document_Service::get_instance()->generate_native($doc_type, $reservation_id);
    }

    public static function generate($template_id_input, $reservation_id, $force_regenerate = false)
    {
        return PCR_Document_Service::get_instance()->generate($template_id_input, $reservation_id, $force_regenerate);
    }

    public static function preview($template_id)
    {
        PCR_Document_Service::get_instance()->preview($template_id);
    }

    public static function ajax_get_documents_list()
    {
        check_ajax_referer('pc_resa_manual_create', 'nonce');
        $resa_id = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;
        if (!$resa_id) wp_send_json_error(['message' => 'ID manquant']);

        $results = PCR_Document_Repository::get_instance()->get_documents_for_reservation($resa_id);

        $documents = [];
        foreach ($results as $row) {
            $documents[] = [
                'type_doc'      => ucfirst(str_replace('_', ' ', $row->type_doc)),
                'nom_fichier'   => $row->nom_fichier,
                'date_creation' => date('d/m/Y H:i', strtotime($row->date_creation)),
                'url_fichier'   => $row->url_fichier
            ];
        }
        wp_send_json_success($documents);
    }

    public static function ajax_generate_document()
    {
        while (ob_get_level()) ob_end_clean();
        check_ajax_referer('pc_resa_manual_create', 'nonce');

        $template_id = isset($_POST['template_id']) ? sanitize_text_field($_POST['template_id']) : '';
        $resa_id = (int) ($_POST['reservation_id'] ?? 0);
        $force_regen = isset($_POST['force']) && $_POST['force'] === 'true';

        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => 'Non autorisé']);

        if (function_exists('ini_set')) ini_set('memory_limit', '512M');
        set_time_limit(120);

        try {
            $res = self::generate($template_id, $resa_id, $force_regen);
            if ($res['success']) wp_send_json_success($res);
            else wp_send_json_error($res);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}
