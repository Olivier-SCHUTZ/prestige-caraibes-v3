<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCR Experience Formatter - Formatage et Nettoyage des données d'Expérience
 * * Gère la sanitisation, le typage des champs ACF et la conversion des images.
 * Pattern Singleton.
 * * @since 2.0.0 (Refactoring)
 */
class PCR_Experience_Formatter
{
    /**
     * Instance unique de la classe.
     * @var PCR_Experience_Formatter|null
     */
    private static $instance = null;

    /**
     * Constructeur privé (Singleton).
     */
    private function __construct() {}

    /**
     * Récupère l'instance unique.
     * * @return PCR_Experience_Formatter
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Traite la valeur d'un champ selon son type (Lecture depuis la BDD vers l'Affichage).
     * * @param string $field_key Clé du champ
     * @param mixed $value Valeur brute
     * @return mixed Valeur traitée
     */
    public function process_field_value($field_key, $value)
    {
        switch ($field_key) {
            case 'exp_duree':
            case 'exp_capacite':
            case 'exp_age_minimum':
            case 'taux_tva':
            case 'pc_deposit_value':
            case 'pc_balance_delay_days':
            case 'pc_caution_amount':
            case 'exp_heure_limite_de_commande':
                return is_numeric($value) ? (float) $value : 0;

            case 'exp_exclude_sitemap':
            case 'exp_http_410':
            case 'exp_availability':
                if ($value === "1" || $value === 1 || $value === true || $value === 'true') return true;
                if ($value === "0" || $value === 0 || $value === false || $value === 'false' || $value === '') return false;
                return (bool) $value;

            case 'exp_accessibilite':
            case 'exp_periode':
            case 'exp_jour':
            case 'exp_a_prevoir':
            case 'exp_delai_de_reservation':
            case 'exp_zone_intervention':
            case 'exp_logements_recommandes':
            case 'exp_lieux_horaires_depart':
            case 'exp_periodes_fermeture':
            case 'exp_types_de_tarifs':
            case 'exp_faq':
            case 'photos_experience':
                return is_array($value) ? $value : [];

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
            case 'exp_prix_comprend':
            case 'exp_prix_ne_comprend_pas':
            case 'exp_le_service_comprend':
            case 'exp_service_a_prevoir':
                // Champs HTML longs / WYSIWYG
                return wp_kses_post($value);

            case 'exp_h1_custom':
            case 'exp_meta_titre':
            case 'exp_meta_description':
            case 'exp_type_de_prestation':
            case 'exp_heure_limite_de_commande':
                // Champs texte standards
                return sanitize_text_field($value);

            case 'exp_duree':
            case 'exp_capacite':
            case 'exp_age_minimum':
            case 'taux_tva':
            case 'pc_deposit_value':
            case 'pc_balance_delay_days':
            case 'pc_caution_amount':
                // Champs numériques
                return is_numeric($value) ? (float) $value : 0;

            case 'exp_exclude_sitemap':
            case 'exp_http_410':
            case 'exp_availability':
                // Champs booléens
                if ($value === "1" || $value === 1 || $value === true || $value === 'true') return true;
                if ($value === "0" || $value === 0 || $value === false || $value === 'false' || $value === '') return false;
                return (bool) $value;

            case 'exp_meta_canonical':
                return esc_url_raw($value);

            case 'exp_logements_recommandes':
                if (is_array($value)) return array_map('intval', array_filter($value));
                return [];

            case 'exp_accessibilite':
            case 'exp_periode':
            case 'exp_jour':
            case 'exp_a_prevoir':
            case 'exp_delai_de_reservation':
            case 'exp_zone_intervention':
                // Champs array (checkboxes) ou fallback texte
                if (is_array($value)) return array_map('sanitize_text_field', $value);
                return sanitize_text_field($value);

            case 'exp_lieux_horaires_depart':
            case 'exp_periodes_fermeture':
            case 'exp_types_de_tarifs':
            case 'exp_faq':
                // Champs complexes (Repeaters) : laisser ACF gérer la sanitisation
                return $value;

            case 'exp_hero_desktop':
            case 'exp_hero_mobile':
                // Champs images (Le JS envoie directement l'ID de l'image)
                return is_numeric($value) ? (int) $value : 0;

            case 'photos_experience':
                // Galerie (Le JS envoie une chaîne "id1,id2,id3", ACF exige un Array d'IDs)
                if (is_string($value) && !empty($value)) {
                    return array_map('intval', explode(',', $value));
                }
                return is_array($value) ? array_map('intval', $value) : [];

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

        // Si c'est déjà un ID numérique, le retourner
        if (is_numeric($value)) {
            $id = (int) $value;
            // Vérifier que l'attachment existe
            if ($id > 0 && get_post($id) && get_post_type($id) === 'attachment') {
                return $id;
            }
            return '';
        }

        // Si c'est une string qui ressemble à un ID
        if (is_string($value) && preg_match('/^\d+$/', trim($value))) {
            $id = (int) trim($value);
            // Vérifier que l'attachment existe
            if ($id > 0 && get_post($id) && get_post_type($id) === 'attachment') {
                return $id;
            }
            return '';
        }

        // Conversion URL vers ID via attachment_url_to_postid()
        if (is_string($value) && (strpos($value, 'http') === 0 || strpos($value, '/wp-content/uploads') !== false)) {
            error_log("[PCR Experience Formatter] 🔧 Conversion URL vers ID pour: {$value}");

            // Utiliser WordPress native function pour convertir URL vers ID
            $attachment_id = attachment_url_to_postid($value);

            if ($attachment_id > 0) {
                error_log("[PCR Experience Formatter] ✅ Conversion réussie: URL -> ID #{$attachment_id}");
                return (int) $attachment_id;
            } else {
                error_log("[PCR Experience Formatter] ❌ Échec conversion, URL gardée: {$value}");
                return $value;
            }
        }

        // Si c'est un array (cas d'un champ Gallery)
        if (is_array($value)) {
            return array_map(function ($item) {
                return $this->process_image_field($item);
            }, $value);
        }

        return $value;
    }

    /**
     * Traite une image pour l'affichage dans le dashboard.
     * Retourne un objet avec à la fois l'ID et l'URL pour compatibilité maximale.
     * * @param mixed $image_value Valeur retournée par ACF (URL ou ID selon config)
     * @return array|string Structure {id, url} ou string si URL directe
     */
    public function process_image_for_display($image_value)
    {
        if (empty($image_value)) {
            return null;
        }

        // Si c'est déjà une URL (return_format: url dans ACF)
        if (is_string($image_value) && (strpos($image_value, 'http') === 0 || strpos($image_value, '/wp-content/uploads') !== false)) {
            // Essayer de retrouver l'ID correspondant
            $attachment_id = attachment_url_to_postid($image_value);

            return [
                'id' => $attachment_id > 0 ? $attachment_id : null,
                'url' => $image_value,
                'type' => 'url'
            ];
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

        // Si c'est un objet ACF complet (array)
        if (is_array($image_value) && isset($image_value['id'])) {
            return [
                'id' => (int) $image_value['id'],
                'url' => $image_value['url'] ?: wp_get_attachment_url($image_value['id']) ?: '',
                'type' => 'array'
            ];
        }

        // Fallback : retourner tel quel
        return $image_value;
    }

    /**
     * Traite la galerie pour l'affichage dans le dashboard.
     * Normalise le format pour que JavaScript puisse afficher les miniatures.
     * * @param mixed $gallery_value Valeur retournée par ACF Gallery
     * @return array Tableau d'objets {id, url, thumbnail}
     */
    public function process_gallery_for_display($gallery_value)
    {
        if (empty($gallery_value) || !is_array($gallery_value)) {
            return [];
        }

        $processed_gallery = [];

        foreach ($gallery_value as $image) {
            $processed_image = null;

            if (is_array($image)) {
                // Format objet ACF standard : {ID, url, sizes, etc.}
                $image_id = isset($image['ID']) ? (int) $image['ID'] : (isset($image['id']) ? (int) $image['id'] : null);

                if ($image_id) {
                    $processed_image = [
                        'id' => $image_id,
                        'url' => $image['url'] ?? wp_get_attachment_url($image_id) ?? '',
                        'thumbnail' => wp_get_attachment_image_src($image_id, 'thumbnail')[0] ?? '',
                        'sizes' => $image['sizes'] ?? []
                    ];
                }
            } elseif (is_numeric($image)) {
                // Format ID simple
                $image_id = (int) $image;
                if ($image_id > 0 && get_post($image_id) && get_post_type($image_id) === 'attachment') {
                    $processed_image = [
                        'id' => $image_id,
                        'url' => wp_get_attachment_url($image_id) ?: '',
                        'thumbnail' => wp_get_attachment_image_src($image_id, 'thumbnail')[0] ?? '',
                        'sizes' => []
                    ];
                }
            } elseif (is_string($image)) {
                // Format URL directe (rare pour galerie mais possible)
                $image_id = attachment_url_to_postid($image);
                $processed_image = [
                    'id' => $image_id > 0 ? $image_id : null,
                    'url' => $image,
                    'thumbnail' => $image_id > 0 ? (wp_get_attachment_image_src($image_id, 'thumbnail')[0] ?? '') : $image,
                    'sizes' => []
                ];
            }

            if ($processed_image) {
                $processed_gallery[] = $processed_image;
            }
        }

        return $processed_gallery;
    }
}
