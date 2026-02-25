<?php
// Fichier : mu-plugins/core-modules/class-pc-assets.php
if (!defined('ABSPATH')) exit;

class PC_Assets_Manager
{

    public static function init()
    {
        // Fondations CSS globales
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_base_css'], 5);

        // Librairies externes (Leaflet, Flatpickr...)
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_external_libraries']);

        // Composants pour les grilles Elementor
        add_action('wp_enqueue_scripts', [__CLASS__, 'load_loop_grid_components']);

        // Autoriser les SVG
        add_filter('upload_mimes', [__CLASS__, 'allow_svg_uploads']);
    }

    public static function enqueue_base_css()
    {
        wp_enqueue_style('pc-base', content_url('mu-plugins/pc-base.css'), [], '1.0');
    }

    public static function enqueue_external_libraries()
    {
        // On a enlevé le add_action, on garde juste la logique pure :
        if (! is_singular(['villa', 'appartement', 'logement', 'experience']) && ! is_page(['reserver', 'demande-sejour', 'recherche-de-logements', 'recherche-dexperiences'])) {
            return;
        }
        wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
        wp_enqueue_style('flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css');
        wp_enqueue_style('glightbox-css', 'https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css');
        wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], null, true);
        wp_enqueue_script('flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js', [], null, true);
        wp_enqueue_script('flatpickr-fr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js', ['flatpickr-js'], null, true);
        wp_enqueue_script('glightbox-js', 'https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js', [], null, true);
        wp_enqueue_script('jspdf', 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js', [], null, true);
    }

    public static function load_loop_grid_components()
    {
        // Liste des slugs des pages où charger les composants.
        $target_pages = [
            'location-villa-en-guadeloupe',
            'location-villa-de-luxe-en-guadeloupe',
            'location-grande-villa-en-guadeloupe',
            'promotion-villa-en-guadeloupe',
            'location-appartement-en-guadeloupe'
        ];

        // Condition : charger uniquement sur la page d'accueil OU sur l'une des pages cibles.
        if (is_front_page() || is_page($target_pages)) {
            // 1. Charger le fichier PHP qui définit le shortcode [pc_loop_lodging_card]
            $php_path = WPMU_PLUGIN_DIR . '/pc-loop-components.php'; // Remplacement de __DIR__ par WPMU_PLUGIN_DIR pour plus de fiabilité
            if (file_exists($php_path)) {
                require_once $php_path;
            }

            // 2. Charger le fichier CSS pour la vignette
            $style_path = WPMU_PLUGIN_DIR . '/assets/pc-loop-card.css';
            if (file_exists($style_path)) {
                $style_url = WPMU_PLUGIN_URL . '/assets/pc-loop-card.css';
                $version = filemtime($style_path);
                wp_enqueue_style('pc-loop-card-style', $style_url, ['pc-base'], $version);
            }
        }
    }

    public static function allow_svg_uploads($m)
    {
        $m['svg'] = 'image/svg+xml';
        return $m;
    }
}
