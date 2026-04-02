<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCR Housing Formatter - Formatage et Nettoyage des données Logement
 * * Gère la sanitisation, le typage des champs ACF et la conversion des images.
 * Pattern Singleton.
 * * @since 2.0.0 (Refactoring)
 */
class PCR_Housing_Formatter
{
    /**
     * Instance unique de la classe.
     * @var PCR_Housing_Formatter|null
     */
    private static $instance = null;

    /**
     * Constructeur privé (Singleton).
     */
    private function __construct() {}

    /**
     * Récupère l'instance unique.
     * * @return PCR_Housing_Formatter
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Traite la valeur d'un champ selon son type.
     * * @param string $field_key Clé du champ
     * @param mixed $value Valeur brute
     * @return mixed Valeur traitée
     */
    public function process_field_value($field_key, $value)
    {
        switch ($field_key) {
            case 'groupes_images':
            case 'pc_season_blocks':
            case 'pc_promo_blocks':
            case 'regles_de_paiement':
            case 'information_contrat_location':
                return $value;

            case 'highlights':
            case 'eq_piscine_spa':
            case 'eq_parking_installations':
            case 'eq_politiques':
            case 'eq_divertissements':
            case 'eq_cuisine_salle_a_manger':
            case 'eq_caracteristiques_emplacement':
            case 'eq_salle_de_bain_blanchisserie':
            case 'eq_chauffage_climatisation':
            case 'eq_internet_bureautique':
            case 'eq_securite_maison':
            case 'taxe_sejour':
            case 'google_vr_amenities':
                return is_array($value) ? $value : [];

            case 'capacite':
            case 'superficie':
            case 'nombre_de_chambres':
            case 'nombre_sdb':
            case 'nombre_lits':
            case 'geo_radius_m':
            case 'prox_airport_km':
            case 'prox_bus_km':
            case 'prox_port_km':
            case 'prox_beach_km':
            case 'base_price_from':
            case 'min_nights':
            case 'max_nights':
            case 'extra_guest_fee':
            case 'caution':
            case 'frais_menage':
            case 'autres_frais':
            case 'taux_tva':
            case 'taux_tva_menage':
                return is_numeric($value) ? (float) $value : 0;

            case 'extra_guest_from':
            case 'season_extra_guest_from':
                if (empty($value) || !is_numeric($value)) {
                    return '';
                }
                $numeric_value = (float) $value;
                return $numeric_value >= 1 ? $numeric_value : '';

            case 'pc_promo_log':
            case 'log_exclude_sitemap':
            case 'log_http_410':
                if ($value === "1" || $value === 1 || $value === true || $value === 'true') {
                    return true;
                }
                if ($value === "0" || $value === 0 || $value === false || $value === 'false' || $value === '') {
                    return false;
                }
                return (bool) $value;

            case 'logement_experiences_recommandees':
                if (is_array($value)) {
                    return array_map('intval', $value);
                }
                return [];

            default:
                if (is_string($value)) {
                    return trim($value);
                }
                return $value;
        }
    }

    /**
     * Sanitise la valeur d'un champ avant sauvegarde.
     * * @param string $field_key Clé du champ
     * @param mixed $value Valeur à sanitiser
     * @return mixed Valeur sanitisée
     */
    public function sanitize_field_value($field_key, $value)
    {
        switch ($field_key) {
            case 'seo_long_html':
            case 'politique_dannulation':
            case 'regles_maison':
            case 'hote_description':
                return wp_kses_post($value);

            case 'gallery_urls':
            case 'video_urls':
            case 'highlights_custom':
            case 'lodgify_widget_embed':
            case 'seo_gallery_urls':
                return sanitize_textarea_field($value);

            case 'capacite':
            case 'superficie':
            case 'nombre_de_chambres':
            case 'nombre_sdb':
            case 'nombre_lits':
            case 'geo_radius_m':
            case 'prox_airport_km':
            case 'prox_bus_km':
            case 'prox_port_km':
            case 'prox_beach_km':
            case 'base_price_from':
            case 'min_nights':
            case 'max_nights':
            case 'extra_guest_fee':
            case 'caution':
            case 'frais_menage':
            case 'autres_frais':
            case 'taux_tva':
            case 'taux_tva_menage':
                return is_numeric($value) ? (float) $value : 0;

            case 'extra_guest_from':
            case 'season_extra_guest_from':
                if (empty($value) || !is_numeric($value)) {
                    return '';
                }
                $numeric_value = (float) $value;
                return $numeric_value >= 1 ? $numeric_value : '';

            case 'url_canonique':
                return esc_url_raw($value);

            case 'highlights':
            case 'eq_piscine_spa':
            case 'eq_parking_installations':
            case 'eq_politiques':
            case 'eq_divertissements':
            case 'eq_cuisine_salle_a_manger':
            case 'eq_caracteristiques_emplacement':
            case 'eq_salle_de_bain_blanchisserie':
            case 'eq_chauffage_climatisation':
            case 'eq_internet_bureautique':
            case 'eq_securite_maison':
            case 'taxe_sejour':
            case 'google_vr_amenities':
                if (is_array($value)) {
                    return array_map('sanitize_text_field', $value);
                }
                return [];

            case 'logement_experiences_recommandees':
                if (is_array($value)) {
                    return array_map('intval', array_filter($value));
                }
                return [];

            case 'groupes_images':
            case 'pc_season_blocks':
            case 'pc_promo_blocks':
            case 'regles_de_paiement':
            case 'information_contrat_location':
                return $value;

            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * Traite un champ Image (conversion ID au lieu d'URL si nécessaire).
     * * @param mixed $value Valeur du champ image (URL, ID, ou array)
     * @return mixed Valeur traitée
     */
    public function process_image_field($value)
    {
        if (empty($value)) {
            return '';
        }

        if (is_numeric($value)) {
            $id = (int) $value;
            if ($id > 0 && get_post($id) && get_post_type($id) === 'attachment') {
                return $id;
            }
            return '';
        }

        if (is_string($value) && preg_match('/^\d+$/', trim($value))) {
            $id = (int) trim($value);
            if ($id > 0 && get_post($id) && get_post_type($id) === 'attachment') {
                return $id;
            }
            return '';
        }

        if (is_string($value) && (strpos($value, 'http') === 0 || strpos($value, '/wp-content/uploads') !== false)) {
            error_log("[PCR Housing Formatter] 🔧 Conversion URL vers ID pour: {$value}");

            $attachment_id = attachment_url_to_postid($value);

            if ($attachment_id > 0) {
                error_log("[PCR Housing Formatter] ✅ Conversion réussie: URL -> ID #{$attachment_id}");
                return (int) $attachment_id;
            } else {
                error_log("[PCR Housing Formatter] ❌ Échec conversion, URL gardée: {$value}");

                $attachment_id = $this->manual_url_to_attachment_id($value);
                if ($attachment_id > 0) {
                    error_log("[PCR Housing Formatter] ✅ Conversion manuelle réussie: URL -> ID #{$attachment_id}");
                    return (int) $attachment_id;
                }

                return $value;
            }
        }

        if (is_array($value)) {
            return array_map(function ($item) {
                return $this->process_image_field($item);
            }, $value);
        }

        return $value;
    }

    /**
     * Méthode manuelle pour convertir une URL d'image vers son ID d'attachment.
     * Utilisée quand attachment_url_to_postid() échoue.
     * * @param string $url URL de l'image
     * @return int ID de l'attachment ou 0 si non trouvé
     */
    private function manual_url_to_attachment_id($url)
    {
        global $wpdb;

        if (empty($url)) {
            return 0;
        }

        $filename = basename(parse_url($url, PHP_URL_PATH));

        if (empty($filename)) {
            return 0;
        }

        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = '_wp_attached_file' 
             AND meta_value LIKE %s
             LIMIT 1",
            '%' . $wpdb->esc_like($filename)
        ));

        if ($attachment_id && is_numeric($attachment_id)) {
            $attachment_id = (int) $attachment_id;
            if (get_post($attachment_id) && get_post_type($attachment_id) === 'attachment') {
                return $attachment_id;
            }
        }

        return 0;
    }
}
