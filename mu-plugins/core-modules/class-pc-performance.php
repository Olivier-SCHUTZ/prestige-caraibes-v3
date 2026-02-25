<?php
// Fichier : mu-plugins/core-modules/class-pc-performance.php

if (!defined('ABSPATH')) {
    exit;
}

class PC_Performance
{

    // === PROPRIÉTÉS ===
    private static $instance = null;

    // === INITIALISATION (Singleton) ===
    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->init_hooks();
    }

    public function init_hooks()
    {
        // 1. Définition des constantes de polices
        $this->define_font_constants();

        // 2. Chargement conditionnel des assets de destination (priorité 30)
        add_action('wp_enqueue_scripts', [$this, 'enqueue_destination_assets'], 30);

        // 3. Nettoyage du CSS Gutenberg inutile (priorité 100)
        add_action('wp_enqueue_scripts', [$this, 'dequeue_gutenberg_css'], 100);
    }

    // ==========================================
    // 1. CONSTANTES & PRÉCHARGEMENT
    // ==========================================
    private function define_font_constants()
    {
        if (!defined('PC_PERF_FONT_POPPINS_600_PATH')) {
            define('PC_PERF_FONT_POPPINS_600_PATH', '/wp-content/uploads/2025/08/Poppins-SemiBold.woff2');
        }
        if (!defined('PC_PERF_FONT_LORA_REGULAR_PATH')) {
            define('PC_PERF_FONT_LORA_REGULAR_PATH', '/wp-content/uploads/2025/08/Lora-Regular.woff2');
        }
    }

    // ==========================================
    // 2. CSS CONDITIONNEL (DESTINATIONS)
    // ==========================================
    public function enqueue_destination_assets()
    {
        if (is_admin()) return;

        $should = (is_singular('destination')
            || is_post_type_archive('destination')
            || is_tax('destination_cat'));

        if (!$should && is_singular()) {
            $post = get_queried_object();
            if ($post && !empty($post->post_content)) {
                $should = (
                    has_shortcode($post->post_content, 'pc_destination_hub') ||
                    has_shortcode($post->post_content, 'pc_destination_grid') ||
                    has_shortcode($post->post_content, 'destination_logements_recommandes') ||
                    has_shortcode($post->post_content, 'destination_infos') ||
                    has_shortcode($post->post_content, 'destination_experiences_recommandees') ||
                    has_shortcode($post->post_content, 'pc_destination_logements') ||
                    has_shortcode($post->post_content, 'pc_destination_experiences')
                );
            }
        }

        if ($should) {
            $path = WPMU_PLUGIN_DIR . '/assets/pc-destination.css';
            $url  = WPMU_PLUGIN_URL . '/assets/pc-destination.css';
            if (file_exists($path)) {
                wp_enqueue_style('pc-destination', $url, [], filemtime($path), 'all');
            }
        }
    }

    // ==========================================
    // 3. NETTOYAGE GUTENBERG
    // ==========================================
    public function dequeue_gutenberg_css()
    {
        // Cible les pages, articles, et CPTs. On exclut les archives.
        if (is_singular()) {
            global $post;
            // Si la page n'a pas de blocs Gutenberg, on retire les CSS inutiles.
            if ($post && !has_blocks($post->post_content)) {
                wp_dequeue_style('wp-block-library');
                wp_dequeue_style('wp-block-library-theme');
                wp_dequeue_style('classic-theme-styles'); // Pour les thèmes plus anciens
            }
        }
        // Pour les archives (comme la page blog), on les retire systématiquement.
        elseif (is_home() || is_archive() || is_search()) {
            wp_dequeue_style('wp-block-library');
            wp_dequeue_style('wp-block-library-theme');
            wp_dequeue_style('classic-theme-styles');
        }
    }
}
