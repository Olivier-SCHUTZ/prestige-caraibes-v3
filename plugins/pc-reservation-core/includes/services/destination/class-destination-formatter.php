<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCR Destination Formatter - Formatage et Nettoyage des données
 * Gère le typage strict et le formatage des images pour Vue.js.
 * Pattern Singleton.
 */
class PCR_Destination_Formatter
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

    /**
     * Traite la valeur d'un champ selon son type (Lecture depuis BDD vers Vue.js).
     */
    public function process_field_value($field_key, $value)
    {
        switch ($field_key) {
            // Champs numériques (Floats / Décimales pour le GPS)
            case 'dest_geo_lat':
            case 'dest_geo_lng':
                return is_numeric($value) ? (float) $value : 0;

                // Champs numériques (Entiers)
            case 'dest_population':
            case 'dest_surface_km2':
            case 'dest_airport_distance_km':
            case 'dest_order':
                return is_numeric($value) ? (int) $value : 0;

                // Champs booléens
            case 'dest_featured':
            case 'dest_exclude_sitemap':
            case 'dest_http_410':
                if ($value === "1" || $value === 1 || $value === true || $value === 'true') return true;
                if ($value === "0" || $value === 0 || $value === false || $value === 'false' || $value === '') return false;
                return (bool) $value;

                // Champs Tableaux / JSON
            case 'dest_infos':
            case 'dest_faq':
            case 'dest_exp_featured':
            case 'dest_logements_recommandes':
                return is_array($value) ? $value : [];

                // Nettoyage par défaut des chaînes
            default:
                if (is_string($value)) {
                    return trim($value);
                }
                return $value;
        }
    }

    /**
     * Traite une image pour l'affichage dans le dashboard Vue.js.
     * Retourne un objet {id, url, type}
     */
    public function process_image_for_display($image_value)
    {
        if (empty($image_value)) {
            return null;
        }

        // Si c'est un ID numérique
        if (is_numeric($image_value)) {
            $attachment_id = (int) $image_value;
            if ($attachment_id > 0 && get_post($attachment_id) && get_post_type($attachment_id) === 'attachment') {
                $url = wp_get_attachment_url($attachment_id);
                return [
                    'id' => $attachment_id,
                    'url' => $url ?: '',
                    'type' => 'id'
                ];
            }
        }

        // Si c'est déjà une URL (retournée par ACF parfois)
        if (is_string($image_value) && (strpos($image_value, 'http') === 0 || strpos($image_value, '/wp-content/uploads') !== false)) {
            $attachment_id = attachment_url_to_postid($image_value);
            return [
                'id' => $attachment_id > 0 ? $attachment_id : null,
                'url' => $image_value,
                'type' => 'url'
            ];
        }

        // Si c'est un objet ACF complet (array)
        if (is_array($image_value) && isset($image_value['id'])) {
            return [
                'id' => (int) $image_value['id'],
                'url' => $image_value['url'] ?: wp_get_attachment_url($image_value['id']) ?: '',
                'type' => 'array'
            ];
        }

        return $image_value;
    }
}
