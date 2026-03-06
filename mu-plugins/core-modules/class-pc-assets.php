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

        // Autoriser les SVG
        add_filter('upload_mimes', [__CLASS__, 'allow_svg_uploads']);
    }

    public static function enqueue_base_css()
    {
        wp_enqueue_style('pc-base', content_url('mu-plugins/pc-base.css'), [], '1.0');
    }

    public static function enqueue_external_libraries()
    {
        // Chargement uniquement sur les pages qui en ont besoin
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

    public static function allow_svg_uploads($m)
    {
        $m['svg'] = 'image/svg+xml';
        return $m;
    }
}
