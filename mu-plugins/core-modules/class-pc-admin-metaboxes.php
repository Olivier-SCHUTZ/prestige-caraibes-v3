<?php

/**
 * Hub central pour les Metaboxes propulsées par Vue.js dans le WP Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Admin_Metaboxes_Manager
{
    /**
     * Clés meta pour l'app SEO
     */
    private $seo_keys = [
        'pc_exclude_sitemap',
        'pc_http_410',
        'pc_meta_title',
        'pc_meta_description',
        'pc_meta_canonical',
        'pc_meta_robots',
        'pc_qcm_enabled',
        'pc_qcm_shortcode',
        'pc_schema_kind',
        'pc_search_type',
        'pc_cat_intro',
        'pc_cat_mode',
        'pc_cat_manual_items',
        'pc_search_emit_itemlist',
        'serv_desktop_url',
        'serv_mobile_url'
    ];

    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'register_vue_metaboxes']);
        add_action('save_post', [$this, 'save_vue_metaboxes']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_vue_apps']);
    }

    /**
     * Déclaration de toutes tes metaboxes Vue.js
     */
    public function register_vue_metaboxes()
    {
        // 1. Metabox SEO & Structure (Affichée uniquement sur les pages)
        add_meta_box(
            'pc_page_seo_app',
            'Prestige Caraïbes — SEO & Structure',
            [$this, 'render_seo_app'],
            'page',
            'normal',
            'high'
        );

        // Tu pourras ajouter tes futurs JSON orphelins ici...
    }

    /**
     * Rendu HTML du conteneur Vue 3 pour le SEO
     */
    public function render_seo_app($post)
    {
        // 1. Récupération des données existantes (sauvegardes natives WP)
        $data = [];
        foreach ($this->seo_keys as $key) {
            $data[$key] = get_post_meta($post->ID, $key, true);
        }

        // Cas particulier : Le répéteur FAQ (désérialisation / JSON Vue.js)
        $faq_raw = get_post_meta($post->ID, 'pc_faq_items', true);
        $data['pc_faq_items'] = is_string($faq_raw) ? json_decode(stripslashes($faq_raw), true) : (maybe_unserialize($faq_raw) ?: []);

        // 2. Transmission sécurisée à Vue.js
        wp_nonce_field('pc_save_vue_apps', 'pc_vue_apps_nonce');
        echo '<script>window.PC_SEO_INITIAL_STATE = ' . json_encode($data) . ';</script>';

        // 3. Le point de montage Vue.js
        echo '<div id="pc-seo-vue-app">Chargement de l\'interface...</div>';
    }

    /**
     * Sauvegarde globale interceptée lors du clic sur "Mettre à jour"
     */
    public function save_vue_metaboxes($post_id)
    {
        if (!isset($_POST['pc_vue_apps_nonce']) || !wp_verify_nonce($_POST['pc_vue_apps_nonce'], 'pc_save_vue_apps')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        // Écoute du payload de l'App SEO
        if (isset($_POST['pc_seo_payload'])) {
            $payload = json_decode(stripslashes($_POST['pc_seo_payload']), true);
            if (is_array($payload)) {
                // Sauvegarde des champs simples
                foreach ($this->seo_keys as $key) {
                    if (isset($payload[$key])) {
                        update_post_meta($post_id, $key, wp_kses_post($payload[$key]));
                    }
                }
                // Sauvegarde du répéteur FAQ en JSON natif
                if (isset($payload['pc_faq_items']) && is_array($payload['pc_faq_items'])) {
                    update_post_meta($post_id, 'pc_faq_items', wp_slash(json_encode($payload['pc_faq_items'])));
                }
            }
        }
    }

    /**
     * Chargement des scripts compilés
     */
    public function enqueue_vue_apps($hook)
    {
        global $post;
        if ($hook !== 'post.php' && $hook !== 'post-new.php') return;

        // On ne charge l'app SEO que si on est sur une page
        if ($post && $post->post_type === 'page') {
            wp_enqueue_script(
                'pc-seo-app',
                plugin_dir_url(__FILE__) . '../assets/js/admin/pc-seo-app.min.js',
                [],
                filemtime(plugin_dir_path(__FILE__) . '../assets/js/admin/pc-seo-app.min.js'),
                true // En footer
            );
        }
    }
}

new PC_Admin_Metaboxes_Manager();
