<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCR Experience Repository - Accès aux données des Expériences
 * Pattern Singleton.
 * * @since 2.0.0 (Refactoring)
 */
class PCR_Experience_Repository
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

    public function get_experience_list($args = [])
    {
        // ... (Initialisation des arguments identiques à avant)
        $defaults = [
            'posts_per_page' => 20,
            'paged' => 1,
            'orderby' => 'title',
            'order' => 'ASC',
            's' => '',
            'meta_query' => [],
            'status_filter' => '',
            'availability_filter' => '',
        ];
        $args = wp_parse_args($args, $defaults);
        $config = PCR_Experience_Config::get_instance();

        $query_args = [
            'post_type' => 'experience',
            'post_status' => ['publish', 'pending', 'draft', 'private', 'future'],
            'posts_per_page' => $args['posts_per_page'],
            'paged' => $args['paged'],
            'orderby' => $args['orderby'],
            'order' => $args['order'],
        ];

        if (!empty($args['status_filter']) && $args['status_filter'] !== 'all') $query_args['post_status'] = $args['status_filter'];
        if (!empty($args['s'])) $query_args['s'] = $args['s'];

        $meta_query = $args['meta_query'];
        if (!empty($args['availability_filter'])) {
            $meta_query[] = ['key' => 'exp_availability', 'value' => $args['availability_filter'], 'compare' => '='];
        }
        if (!empty($meta_query)) $query_args['meta_query'] = $meta_query;

        $query = new WP_Query($query_args);
        $list = [];

        if ($query->have_posts()) {
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

                // ✨ NOUVEAU : Récupération via le wrapper sécurisé avec fallback
                $pcr_exists = class_exists('PCR_Fields');
                $has_acf = function_exists('get_field');

                $item['duree'] = $pcr_exists ? PCR_Fields::get('exp_duree', $post_id, '') : ($has_acf ? get_field('exp_duree', $post_id) : '');
                $item['capacite'] = $pcr_exists ? PCR_Fields::get('exp_capacite', $post_id, 0) : ($has_acf ? get_field('exp_capacite', $post_id) : 0);
                $item['availability'] = (bool) ($pcr_exists ? PCR_Fields::get('exp_availability', $post_id, true) : ($has_acf ? get_field('exp_availability', $post_id) : true));
                $item['type_de_prestation'] = $pcr_exists ? PCR_Fields::get('exp_type_de_prestation', $post_id, '') : ($has_acf ? get_field('exp_type_de_prestation', $post_id) : '');
                $item['zone_intervention'] = $pcr_exists ? PCR_Fields::get('exp_zone_intervention', $post_id, '') : ($has_acf ? get_field('exp_zone_intervention', $post_id) : '');
                $item['taux_tva'] = $pcr_exists ? PCR_Fields::get('taux_tva', $post_id, '') : ($has_acf ? get_field('taux_tva', $post_id) : '');

                $lieux = $pcr_exists ? PCR_Fields::get('exp_lieux_horaires_depart', $post_id, []) : ($has_acf ? get_field('exp_lieux_horaires_depart', $post_id) : []);
                // 🛡️ Bouclier Liste
                if (is_numeric($lieux) && $has_acf) {
                    $lieux = get_field('exp_lieux_horaires_depart', $post_id);
                }
                $item['lieu_depart'] = (is_array($lieux) && !empty($lieux) && !empty($lieux[0]['exp_lieu_depart'])) ? $lieux[0]['exp_lieu_depart'] : '';

                $item['exp_availability'] = $item['availability'];
                $item['status_label'] = $config->get_status_label($item['status']);
                $item['status_class'] = $config->get_status_class($item['status']);
                $item['availability_label'] = $item['exp_availability'] ? 'Disponible' : 'Non disponible';
                $item['availability_class'] = $item['exp_availability'] ? 'pc-status--available' : 'pc-status--unavailable';

                $list[] = $item;
            }
            wp_reset_postdata();
        }

        return ['success' => true, 'items' => $list, 'total' => $query->found_posts, 'pages' => $query->max_num_pages, 'current_page' => $args['paged']];
    }

    public function get_experience_details($post_id)
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0) return false;

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'experience') return false;

        $details = [
            'id' => $post_id,
            'title' => $post->post_title,
            'slug' => $post->post_name,
            'status' => $post->post_status,
            'type' => $post->post_type,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
        ];

        $config = PCR_Experience_Config::get_instance();
        $formatter = PCR_Experience_Formatter::get_instance();

        // ✨ NOUVEAU : Chargement unifié via PCR_Fields
        $mapped_fields = $config->get_mapped_fields();
        $complex_fields = ['exp_lieux_horaires_depart', 'exp_periodes_fermeture', 'exp_types_de_tarifs', 'exp_faq', 'exp_a_prevoir', 'exp_accessibilite', 'exp_periode', 'exp_jour', 'exp_zone_intervention'];

        foreach ($mapped_fields as $normalized_key => $meta_key) {
            $value = class_exists('PCR_Fields') ? PCR_Fields::get($meta_key, $post_id) : (function_exists('get_field') ? get_field($meta_key, $post_id) : null);

            // 🛡️ BOUCLIER ANTI-CRASH ACF : Si la donnée native a été écrasée par un chiffre (nombre de lignes) via WP Admin
            if (is_numeric($value) && in_array($meta_key, $complex_fields) && function_exists('get_field')) {
                $value = get_field($meta_key, $post_id);
            }

            $details[$normalized_key] = $formatter->process_field_value($normalized_key, $value);
        }

        // =========================================================
        $pcr_exists = class_exists('PCR_Fields');
        $has_acf = function_exists('get_field');

        $details['exp_hero_desktop'] = $formatter->process_image_for_display($pcr_exists ? PCR_Fields::get('exp_hero_desktop', $post_id) : ($has_acf ? get_field('exp_hero_desktop', $post_id) : null));
        $details['exp_hero_mobile'] = $formatter->process_image_for_display($pcr_exists ? PCR_Fields::get('exp_hero_mobile', $post_id) : ($has_acf ? get_field('exp_hero_mobile', $post_id) : null));
        $details['photos_experience'] = $formatter->process_gallery_for_display($pcr_exists ? PCR_Fields::get('photos_experience', $post_id) : ($has_acf ? get_field('photos_experience', $post_id) : null));

        // SÉCURITÉ ANTI-CRASH TARIFS
        $seasons_data = $pcr_exists ? PCR_Fields::get('seasons_data', $post_id, []) : ($has_acf ? get_field('seasons_data', $post_id) : []);
        $promos_data = $pcr_exists ? PCR_Fields::get('promos_data', $post_id, []) : ($has_acf ? get_field('promos_data', $post_id) : []);
        $details['seasons_data'] = is_array($seasons_data) ? $seasons_data : [];
        $details['promos_data'] = is_array($promos_data) ? $promos_data : [];

        $taux_tva_raw = $pcr_exists ? PCR_Fields::get('taux_tva', $post_id) : ($has_acf ? get_field('taux_tva', $post_id) : null);
        // Création d'une ligne de tarif par défaut si vide
        if (!is_array($details['exp_types_de_tarifs']) || empty($details['exp_types_de_tarifs'])) {
            $details['exp_types_de_tarifs'] = [[
                'exp_type' => 'unique',
                'exp_type_custom' => '',
                'exp_options_tarifaires' => [],
                'exp-frais-fixes' => [],
                'exp_tarifs_lignes' => []
            ]];
        }

        // RÈGLES DE PAIEMENT
        $details['pc_pay_mode'] = $pcr_exists ? PCR_Fields::get('pc_pay_mode', $post_id, 'acompte_plus_solde') : ($has_acf ? get_field('pc_pay_mode', $post_id) : 'acompte_plus_solde');
        $details['pc_deposit_type'] = $pcr_exists ? PCR_Fields::get('pc_deposit_type', $post_id, 'pourcentage') : ($has_acf ? get_field('pc_deposit_type', $post_id) : 'pourcentage');
        $details['pc_deposit_value'] = $pcr_exists ? PCR_Fields::get('pc_deposit_value', $post_id, '') : ($has_acf ? get_field('pc_deposit_value', $post_id) : '');
        $details['pc_balance_delay_days'] = $pcr_exists ? PCR_Fields::get('pc_balance_delay_days', $post_id, '') : ($has_acf ? get_field('pc_balance_delay_days', $post_id) : '');
        $details['pc_caution_amount'] = $pcr_exists ? PCR_Fields::get('pc_caution_amount', $post_id, '') : ($has_acf ? get_field('pc_caution_amount', $post_id) : '');
        $details['pc_caution_mode'] = $pcr_exists ? PCR_Fields::get('pc_caution_mode', $post_id, 'aucune') : ($has_acf ? get_field('pc_caution_mode', $post_id) : 'aucune');

        // Initialisation à vide pour les repeaters inexistants
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

    public function delete_experience($post_id)
    {
        // Identique à la version précédente
        $post_id = (int) $post_id;
        if (!current_user_can('delete_post', $post_id)) return ['success' => false];
        wp_delete_post($post_id, true);
        return ['success' => true];
    }
}
