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

    /**
     * Clés meta pour l'app SEO des Articles de blog
     */
    private $post_seo_keys = [
        'post_exclude_sitemap',
        'post_http_410',
        'post_og_title',
        'post_og_description',
        'post_meta_canonical',
        'post_meta_robots'
    ];

    /**
     * Clés meta pour les Avis
     */
    private $review_keys = [
        'pc_post_id',
        'pc_reviewer_name',
        'pc_reviewer_location',
        'pc_email',
        'pc_rating',
        'pc_stayed_date',
        'pc_title',
        'pc_body',
        'pc_source',
        'pc_source_url'
    ];

    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'register_vue_metaboxes']);
        add_action('save_post', [$this, 'save_vue_metaboxes']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_vue_apps']);

        // Ajout du support type="module" pour les scripts Vite
        add_filter('script_loader_tag', [$this, 'add_module_type_to_vue_scripts'], 10, 3);
    }

    /**
     * Injecte type="module" pour autoriser les "imports" de Vite (Code splitting)
     */
    public function add_module_type_to_vue_scripts($tag, $handle, $src)
    {
        $vue_handles = ['pc-seo-app', 'pc-post-seo-app', 'pc-review-app'];

        if (in_array($handle, $vue_handles)) {
            return '<script type="module" src="' . esc_url($src) . '"></script>' . "\n";
        }

        return $tag;
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

        // 2. Metabox SEO & Structure (Affichée uniquement sur les articles)
        add_meta_box(
            'pc_post_seo_app',
            'Prestige Caraïbes — SEO Articles',
            [$this, 'render_post_seo_app'],
            'post',
            'normal',
            'high'
        );

        // 3. Metabox de gestion des Avis
        add_meta_box(
            'pc_review_app',
            'Détails de l\'avis',
            [$this, 'render_review_app'],
            'pc_review', // Custom Post Type ciblé
            'normal',
            'high'
        );
    }

    /**
     * Rendu HTML du conteneur Vue 3 pour les Avis
     */
    public function render_review_app($post)
    {
        // 1. Récupération des données existantes
        $data = [];
        foreach ($this->review_keys as $key) {
            $val = get_post_meta($post->ID, $key, true);

            // --- CORRECTION RÉTROCOMPATIBILITÉ ACF : Date de séjour ---
            // ACF stocke brut en YYYYMMDD ou YYYYMM (ex: 202508). L'input <month> exige YYYY-MM (ex: 2025-08).
            if ($key === 'pc_stayed_date' && !empty($val)) {
                // Si la chaîne ne contient pas de tiret et commence par 6 ou 8 chiffres
                if (strpos($val, '-') === false && preg_match('/^(\d{4})(\d{2})/', $val, $matches)) {
                    $val = $matches[1] . '-' . $matches[2];
                }
            }

            $data[$key] = $val;
        }

        // 2. Récupération des propriétés (Villas, Appartements, Expériences)
        $properties_query = new WP_Query([
            'post_type'      => ['villa', 'appartement', 'experience'],
            'post_status'    => ['publish', 'draft', 'private'],
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids' // Requête allégée (que les IDs)
        ]);

        $available_properties = [];
        foreach ($properties_query->posts as $pid) {
            $type_label = '';
            $ptype = get_post_type($pid);
            if ($ptype === 'villa') $type_label = 'Villa';
            if ($ptype === 'appartement') $type_label = 'Appartement';
            if ($ptype === 'experience') $type_label = 'Expérience';

            $available_properties[] = [
                'id'    => $pid,
                'title' => get_the_title($pid) . ' (' . $type_label . ')'
            ];
        }

        // 3. Transmission sécurisée à Vue.js
        wp_nonce_field('pc_save_vue_apps', 'pc_vue_apps_nonce');
        echo '<script>
                window.PC_AVAILABLE_PROPERTIES = ' . json_encode($available_properties) . ';
                window.PC_REVIEW_INITIAL_STATE = ' . json_encode($data) . ';
              </script>';

        // 4. Point de montage
        echo '<div id="pc-review-vue-app">Chargement de l\'interface...</div>';
    }

    /**
     * Rendu HTML du conteneur Vue 3 pour le SEO
     */
    public function render_seo_app($post)
    {
        // 1. Récupération des données existantes (sauvegardes natives WP)
        $data = [];
        foreach ($this->seo_keys as $key) {
            $val = get_post_meta($post->ID, $key, true);

            // 🚀 CORRECTION IMAGES : Si la donnée est un ID (format natif ACF), on la convertit en URL pour Vue.js
            if (in_array($key, ['serv_desktop_url', 'serv_mobile_url']) && is_numeric($val) && intval($val) > 0) {
                $url = wp_get_attachment_url(intval($val));
                $val = $url ? $url : ''; // Si l'image n'existe plus, on renvoie vide
            }

            $data[$key] = $val;
        }

        // --- 🚀 MIGRATION CHAMP RELATION : pc_cat_manual_items ---
        $manual_raw = $data['pc_cat_manual_items'];

        // Sécurité : On vérifie que c'est bien du JSON avant de parser, sinon on garde le format natif WP/ACF
        if (is_string($manual_raw) && strpos(trim(stripslashes($manual_raw)), '[') === 0) {
            $manual_raw = json_decode(stripslashes($manual_raw), true);
        } else {
            $manual_raw = maybe_unserialize($manual_raw);
        }

        $manual_items = [];

        if (!empty($manual_raw) && is_array($manual_raw)) {
            foreach ($manual_raw as $item_id) {
                if (get_post_status($item_id)) {
                    $manual_items[] = [
                        'id'    => intval($item_id),
                        'title' => get_the_title($item_id) // On récupère le titre pour Vue.js
                    ];
                }
            }
        }
        $data['pc_cat_manual_items'] = $manual_items;

        // --- 🚀 MIGRATION INTELLIGENTE ACF -> VUE.JS POUR LA FAQ ---
        $faq_raw = get_post_meta($post->ID, 'pc_faq_items', true);
        $faq_items = [];

        // Scénario A : C'est un ancien répéteur ACF (La valeur est un nombre entier de lignes)
        if (is_numeric($faq_raw) && intval($faq_raw) > 0) {
            $count = intval($faq_raw);
            for ($i = 0; $i < $count; $i++) {
                $q = get_post_meta($post->ID, 'pc_faq_items_' . $i . '_question', true);
                $a = get_post_meta($post->ID, 'pc_faq_items_' . $i . '_answer', true);

                if (!empty($q) || !empty($a)) {
                    $faq_items[] = [
                        'question' => $q,
                        'answer'   => $a
                    ];
                }
            }
        }
        // Scénario B : C'est déjà notre nouveau format JSON natif Vue.js
        elseif (is_string($faq_raw) && strpos(trim(stripslashes($faq_raw)), '[') === 0) {
            $decoded = json_decode(stripslashes($faq_raw), true);
            if (is_array($decoded)) {
                $faq_items = $decoded;
            }
        }
        // Scénario C : Fallback WP par défaut (tableau sérialisé)
        else {
            $unserialized = maybe_unserialize($faq_raw);
            if (is_array($unserialized)) {
                $faq_items = $unserialized;
            }
        }

        $data['pc_faq_items'] = $faq_items;
        // -----------------------------------------------------------

        // 2. Transmission sécurisée à Vue.js
        wp_nonce_field('pc_save_vue_apps', 'pc_vue_apps_nonce');
        echo '<script>window.PC_SEO_INITIAL_STATE = ' . json_encode($data) . ';</script>';

        // 3. Le point de montage Vue.js
        echo '<div id="pc-seo-vue-app">Chargement de l\'interface...</div>';
    }

    /**
     * Rendu HTML du conteneur Vue 3 pour le SEO des Articles
     */
    public function render_post_seo_app($post)
    {
        $data = [];
        foreach ($this->post_seo_keys as $key) {
            $data[$key] = get_post_meta($post->ID, $key, true);
        }

        // Transmission sécurisée de l'état initial à Vue.js
        wp_nonce_field('pc_save_vue_apps', 'pc_vue_apps_nonce');
        echo '<script>window.PC_POST_SEO_INITIAL_STATE = ' . json_encode($data) . ';</script>';

        // Le point de montage Vue.js pour les articles
        echo '<div id="pc-post-seo-vue-app">Chargement de l\'interface SEO...</div>';
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
                    // On exclut les champs complexes qui ont un traitement spécial
                    if (in_array($key, ['pc_faq_items', 'pc_cat_manual_items'])) continue;

                    if (isset($payload[$key])) {
                        update_post_meta($post_id, $key, wp_kses_post($payload[$key]));
                    }
                }

                // Sauvegarde du répéteur FAQ en JSON natif
                if (isset($payload['pc_faq_items']) && is_array($payload['pc_faq_items'])) {
                    update_post_meta($post_id, 'pc_faq_items', wp_slash(json_encode($payload['pc_faq_items'])));
                }

                // Sauvegarde de la relation Logements (Tableau d'IDs simple natif)
                if (isset($payload['pc_cat_manual_items']) && is_array($payload['pc_cat_manual_items'])) {
                    $ids = array_map(function ($item) {
                        return intval($item['id']);
                    }, $payload['pc_cat_manual_items']);
                    update_post_meta($post_id, 'pc_cat_manual_items', $ids);
                }
            }
        }

        // Écoute du payload de l'App SEO des Articles
        if (isset($_POST['pc_post_seo_payload'])) {
            $payload = json_decode(stripslashes($_POST['pc_post_seo_payload']), true);
            if (is_array($payload)) {
                foreach ($this->post_seo_keys as $key) {
                    if (isset($payload[$key])) {
                        $val = $payload[$key];
                        // Conversion BDD pour la rétrocompatibilité (ACF stocke les booléens en "0" ou "1")
                        if (is_bool($val)) {
                            $val = $val ? '1' : '0';
                        }
                        update_post_meta($post_id, $key, wp_kses_post($val));
                    }
                }
            }
        }

        // Écoute du payload de l'App Avis
        if (isset($_POST['pc_review_payload'])) {
            $payload = json_decode(stripslashes($_POST['pc_review_payload']), true);
            if (is_array($payload)) {
                foreach ($this->review_keys as $key) {
                    if (isset($payload[$key])) {
                        // Tolérance HTML pour le corps de l'avis, stricte pour le reste
                        $val = ($key === 'pc_body') ? wp_kses_post($payload[$key]) : sanitize_text_field($payload[$key]);
                        update_post_meta($post_id, $key, $val);
                    }
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

        // --- APP SEO : PAGES ---
        if ($post && $post->post_type === 'page') {
            wp_enqueue_media();

            if (file_exists(plugin_dir_path(__FILE__) . '../assets/js/admin/pc-seo-app.min.css')) {
                wp_enqueue_style(
                    'pc-seo-app-style',
                    plugin_dir_url(__FILE__) . '../assets/js/admin/pc-seo-app.min.css',
                    [],
                    filemtime(plugin_dir_path(__FILE__) . '../assets/js/admin/pc-seo-app.min.css')
                );
            }

            wp_enqueue_script(
                'pc-seo-app',
                plugin_dir_url(__FILE__) . '../assets/js/admin/pc-seo-app.min.js',
                [],
                filemtime(plugin_dir_path(__FILE__) . '../assets/js/admin/pc-seo-app.min.js'),
                true
            );
        }

        // --- APP SEO : ARTICLES ---
        if ($post && $post->post_type === 'post') {
            if (file_exists(plugin_dir_path(__FILE__) . '../assets/js/admin/pc-post-seo-app.min.css')) {
                wp_enqueue_style(
                    'pc-post-seo-app-style',
                    plugin_dir_url(__FILE__) . '../assets/js/admin/pc-post-seo-app.min.css',
                    [],
                    filemtime(plugin_dir_path(__FILE__) . '../assets/js/admin/pc-post-seo-app.min.css')
                );
            }

            wp_enqueue_script(
                'pc-post-seo-app',
                plugin_dir_url(__FILE__) . '../assets/js/admin/pc-post-seo-app.min.js',
                [],
                filemtime(plugin_dir_path(__FILE__) . '../assets/js/admin/pc-post-seo-app.min.js'),
                true
            );
        }

        // --- APP AVIS : PC_REVIEW ---
        if ($post && $post->post_type === 'pc_review') {
            if (file_exists(plugin_dir_path(__FILE__) . '../assets/js/admin/pc-review-app.min.css')) {
                wp_enqueue_style(
                    'pc-review-app-style',
                    plugin_dir_url(__FILE__) . '../assets/js/admin/pc-review-app.min.css',
                    [],
                    filemtime(plugin_dir_path(__FILE__) . '../assets/js/admin/pc-review-app.min.css')
                );
            }

            wp_enqueue_script(
                'pc-review-app',
                plugin_dir_url(__FILE__) . '../assets/js/admin/pc-review-app.min.js',
                [],
                filemtime(plugin_dir_path(__FILE__) . '../assets/js/admin/pc-review-app.min.js'),
                true
            );
        }
    }
}

new PC_Admin_Metaboxes_Manager();
