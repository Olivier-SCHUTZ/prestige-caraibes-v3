<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCR Destination Repository - Accès aux données des Destinations
 * Pattern Singleton. Lecture hybride (Native prioritaire, fallback ACF).
 */
class PCR_Destination_Repository
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

    public function get_destination_list($args = [])
    {
        $defaults = [
            'posts_per_page' => 20,
            'paged' => 1,
            'orderby' => 'title',
            'order' => 'ASC',
            's' => '',
            'status_filter' => '',
        ];
        $args = wp_parse_args($args, $defaults);

        $query_args = [
            'post_type' => 'destination', // Utilisation du CPT destination
            'post_status' => ['publish', 'pending', 'draft', 'private', 'future'],
            'posts_per_page' => $args['posts_per_page'],
            'paged' => $args['paged'],
            'orderby' => $args['orderby'],
            'order' => $args['order'],
        ];

        if (!empty($args['status_filter']) && $args['status_filter'] !== 'all') {
            $query_args['post_status'] = $args['status_filter'];
        }
        if (!empty($args['s'])) {
            $query_args['s'] = $args['s'];
        }

        $query = new WP_Query($query_args);
        $list = [];

        if ($query->have_posts()) {
            // Instanciation de la config pour les labels de statut
            $config = PCR_Destination_Config::get_instance();

            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                $item = [
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'slug' => get_post_field('post_name', $post_id),
                    'status' => get_post_status(),
                    'type' => get_post_type(),
                    'edit_url' => admin_url('post.php?post=' . $post_id . '&action=edit'),
                    'view_url' => get_permalink($post_id),
                ];

                $thumbnail_id = get_post_thumbnail_id($post_id);
                $item['image'] = $thumbnail_id ? [
                    'id' => $thumbnail_id,
                    'url' => wp_get_attachment_image_src($thumbnail_id, 'medium')[0] ?? '',
                    'thumbnail' => wp_get_attachment_image_src($thumbnail_id, 'thumbnail')[0] ?? '',
                ] : ['id' => 0, 'url' => '', 'thumbnail' => ''];

                // Récupération des données clés via le pont hybride avec fallback sécurisé
                $item['dest_region'] = class_exists('PCR_Fields') ? PCR_Fields::get('dest_region', $post_id, '') : (function_exists('get_field') ? get_field('dest_region', $post_id) : '');
                $item['dest_featured'] = (bool) (class_exists('PCR_Fields') ? PCR_Fields::get('dest_featured', $post_id, false) : (function_exists('get_field') ? get_field('dest_featured', $post_id) : false));

                $item['status_label'] = $config->get_status_label($item['status']);
                $item['status_class'] = $config->get_status_class($item['status']);

                $list[] = $item;
            }
            wp_reset_postdata();
        }

        return [
            'success' => true,
            'items' => $list,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
            'current_page' => $args['paged']
        ];
    }

    public function get_destination_details($post_id)
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0) return false;

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'destination') return false;

        $details = [
            'id' => $post_id,
            'title' => $post->post_title,
            'slug' => $post->post_name,
            'status' => $post->post_status,
            'type' => $post->post_type,
            'content' => $post->post_content,
        ];

        $config = PCR_Destination_Config::get_instance();
        $formatter = PCR_Destination_Formatter::get_instance();

        $mapped_fields = $config->get_mapped_fields();
        $complex_fields = ['dest_infos', 'dest_faq'];

        foreach ($mapped_fields as $normalized_key => $meta_key) {
            // Sécurité stricte avant d'appeler PCR_Fields
            $value = class_exists('PCR_Fields') ? PCR_Fields::get($meta_key, $post_id) : (function_exists('get_field') ? get_field($meta_key, $post_id) : null);

            // BOUCLIER ANTI-CRASH ACF (comme pour les expériences) : 
            // Si la donnée native a été écrasée par un entier (nombre de lignes) par WP Admin
            if (is_numeric($value) && in_array($meta_key, $complex_fields) && function_exists('get_field')) {
                $value = get_field($meta_key, $post_id);
            }

            // Traitement strict des types de données
            $details[$normalized_key] = $formatter->process_field_value($normalized_key, $value);
        }

        // =========================================================
        // Traitement spécifique des images pour Vue.js avec fallback
        // =========================================================
        $hero_desktop = class_exists('PCR_Fields') ? PCR_Fields::get('dest_hero_desktop', $post_id) : (function_exists('get_field') ? get_field('dest_hero_desktop', $post_id) : null);
        $details['dest_hero_desktop'] = $formatter->process_image_for_display($hero_desktop);

        $hero_mobile = class_exists('PCR_Fields') ? PCR_Fields::get('dest_hero_mobile', $post_id) : (function_exists('get_field') ? get_field('dest_hero_mobile', $post_id) : null);
        $details['dest_hero_mobile'] = $formatter->process_image_for_display($hero_mobile);

        // Initialisation sécurisée pour les repeaters inexistants
        foreach ($complex_fields as $field_key) {
            if (empty($details[$field_key]) || !is_array($details[$field_key])) {
                $details[$field_key] = [];
            }
        }

        $thumbnail_id = get_post_thumbnail_id($post_id);
        $details['featured_image'] = [
            'id' => $thumbnail_id,
            'url' => $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : '',
        ];

        return ['success' => true, 'data' => $details];
    }

    public function delete_destination($post_id)
    {
        $post_id = (int) $post_id;
        if (!current_user_can('delete_post', $post_id)) return ['success' => false];
        wp_delete_post($post_id, true);
        return ['success' => true];
    }
}
