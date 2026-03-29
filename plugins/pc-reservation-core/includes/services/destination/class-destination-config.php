<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCR Destination Config - Dictionnaire et Configuration des Destinations
 * Centralise tous les mappings ACF et les statuts.
 * Pattern Singleton.
 *
 * @since 2.0.0
 */
class PCR_Destination_Config
{
    /**
     * Instance unique de la classe.
     * @var PCR_Destination_Config|null
     */
    private static $instance = null;

    /**
     * Constructeur privé (Singleton).
     */
    private function __construct() {}

    /**
     * Récupère l'instance unique.
     * @return PCR_Destination_Config
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Retourne les FIELD KEYS ACF réels pour chaque champ de destination.
     * Ces clés sont nécessaires pour que update_field() fonctionne correctement.
     *
     * @return array Mapping [normalized_key => field_key_acf]
     */
    public function get_acf_field_keys()
    {
        return [
            // === INFOS PRINCIPALES ===
            'dest_hero_desktop' => 'field_dest_hero_desktop',
            'dest_hero_mobile' => 'field_dest_hero_mobile',
            'dest_region' => 'field_dest_region',
            'dest_geo_lat' => 'field_68d508744e3cb',
            'dest_geo_lng' => 'field_68d5092e4e3cc',
            'dest_population' => 'field_68cd4838fb4cd',
            'dest_surface_km2' => 'field_68cd491bfb4ce',
            'dest_airport_distance_km' => 'field_68cd4972fb4cf',
            'dest_sea_side' => 'field_68cd49e7fb4d0',

            // === CONTENUS TEXTUELS ===
            'dest_h1' => 'field_dest_h1',
            'dest_intro' => 'field_dest_intro',
            'dest_slogan' => 'field_dest_slogan',
            'dest_infos' => 'field_dest_infos',
            'dest_faq' => 'field_dest_faq',
            'dest_exp_featured' => 'field_dest_exp_featured',
            'dest_logements_recommandes' => 'field_68ced86ebdcac',
            'dest_featured' => 'field_dest_featured',
            'dest_order' => 'field_dest_order',

            // === SEO ===
            'dest_exclude_sitemap' => 'field_68dac244a9b47',
            'dest_http_410' => 'field_68db743b9753c',
            'dest_meta_title' => 'field_68db71b0120c5',
            'dest_meta_description' => 'field_68db7212120c6',
            'dest_meta_canonical' => 'field_68db728b120c7',
            'dest_meta_robots' => 'field_68db72e5120c8',
        ];
    }

    /**
     * Retourne le mapping complet des champs ACF de destination vers des clés normalisées.
     *
     * @return array Mapping [cle_normalisee => meta_key_acf]
     */
    public function get_mapped_fields()
    {
        return [
            // === GÉNÉRAL & GÉOGRAPHIE ===
            'dest_region' => 'dest_region',
            'dest_geo_lat' => 'dest_geo_lat',
            'dest_geo_lng' => 'dest_geo_lng',
            'dest_population' => 'dest_population',
            'dest_surface_km2' => 'dest_surface_km2',
            'dest_airport_distance_km' => 'dest_airport_distance_km',
            'dest_sea_side' => 'dest_sea_side',

            // === CONTENUS ===
            'dest_h1' => 'dest_h1',
            'dest_intro' => 'dest_intro',
            'dest_slogan' => 'dest_slogan',
            'dest_featured' => 'dest_featured',
            'dest_order' => 'dest_order',

            // === IMAGES ===
            'dest_hero_desktop' => 'dest_hero_desktop',
            'dest_hero_mobile' => 'dest_hero_mobile',

            // === RELATIONS ===
            'dest_exp_featured' => 'dest_exp_featured',
            'dest_logements_recommandes' => 'dest_logements_recommandes',

            // === RÉPÉTEURS (CONVERTIS EN JSON) ===
            'dest_infos' => 'dest_infos',
            'dest_faq' => 'dest_faq',

            // === SEO ===
            'dest_exclude_sitemap' => 'dest_exclude_sitemap',
            'dest_http_410' => 'dest_http_410',
            'dest_meta_title' => 'dest_meta_title',
            'dest_meta_description' => 'dest_meta_description',
            'dest_meta_canonical' => 'dest_meta_canonical',
            'dest_meta_robots' => 'dest_meta_robots',
        ];
    }

    /**
     * Trouve la configuration ACF d'un champ par son slug.
     *
     * @param string $slug Le slug normalisé du champ
     * @return array|false Configuration du champ ou false si non trouvé
     */
    public function get_field_config_by_slug($slug)
    {
        $field_keys = $this->get_acf_field_keys();
        $mapped_fields = $this->get_mapped_fields();

        $config = [
            'slug' => $slug,
            'key' => null,
            'meta_key' => null,
        ];

        // 1. Vérifier d'abord les field keys ACF
        if (isset($field_keys[$slug])) {
            $config['key'] = $field_keys[$slug];
            $config['meta_key'] = $field_keys[$slug];
            return $config;
        }

        // 2. Fallback vers le mapping standard
        if (isset($mapped_fields[$slug])) {
            $config['meta_key'] = $mapped_fields[$slug];
            return $config;
        }

        return false;
    }

    /**
     * Retourne les choix pour le champ région.
     *
     * @return array
     */
    public function get_region_choices()
    {
        return [
            'grande-terre' => 'Grande-Terre',
            'basse-terre' => 'Basse-Terre',
            'iles-voisines' => 'Îles voisines'
        ];
    }

    /**
     * Retourne les choix pour le champ type de plage.
     *
     * @return array
     */
    public function get_sea_side_choices()
    {
        return [
            'caraibes' => 'Mer des Caraïbes',
            'atlantique' => 'Océan Atlantique'
        ];
    }

    /**
     * Retourne les choix pour meta robots.
     *
     * @return array
     */
    public function get_meta_robots_choices()
    {
        return [
            'index,follow' => 'index,follow',
            'noindex,follow' => 'noindex,follow',
            'noindex,nofollow' => 'noindex,nofollow'
        ];
    }

    /**
     * Retourne le label d'affichage pour un statut de post.
     *
     * @param string $status
     * @return string
     */
    public function get_status_label($status)
    {
        $labels = [
            'publish' => 'Publié',
            'pending' => 'En attente',
            'draft' => 'Brouillon',
            'private' => 'Privé',
            'trash' => 'Corbeille',
        ];

        return $labels[$status] ?? ucfirst($status);
    }

    /**
     * Retourne la classe CSS pour un statut de post.
     *
     * @param string $status
     * @return string
     */
    public function get_status_class($status)
    {
        $classes = [
            'publish' => 'pc-status--published',
            'pending' => 'pc-status--pending',
            'draft' => 'pc-status--draft',
            'private' => 'pc-status--private',
            'trash' => 'pc-status--trash',
        ];

        return $classes[$status] ?? 'pc-status--unknown';
    }

    /**
     * Validation des champs spécifiques aux destinations.
     *
     * @param array $data Données à valider
     * @return array Erreurs de validation
     */
    public function validate_destination_data($data)
    {
        $errors = [];

        // Validation des coordonnées géographiques
        if (!empty($data['dest_geo_lat']) && !is_numeric($data['dest_geo_lat'])) {
            $errors['dest_geo_lat'] = 'La latitude doit être un nombre décimal valide';
        }

        if (!empty($data['dest_geo_lng']) && !is_numeric($data['dest_geo_lng'])) {
            $errors['dest_geo_lng'] = 'La longitude doit être un nombre décimal valide';
        }

        // Validation du slogan
        if (!empty($data['dest_slogan']) && strlen($data['dest_slogan']) > 140) {
            $errors['dest_slogan'] = 'Le slogan ne peut pas dépasser 140 caractères';
        }

        // Validation de la meta description
        if (!empty($data['dest_meta_description'])) {
            $len = strlen($data['dest_meta_description']);
            if ($len > 0 && ($len < 140 || $len > 160)) {
                $errors['dest_meta_description'] = 'La meta description doit faire entre 140 et 160 caractères';
            }
        }

        // Validation des expériences featured (max 3)
        if (!empty($data['dest_exp_featured']) && is_array($data['dest_exp_featured'])) {
            if (count($data['dest_exp_featured']) > 3) {
                $errors['dest_exp_featured'] = 'Maximum 3 expériences peuvent être mises en avant';
            }
        }

        // Validation des logements recommandés (max 3)
        if (!empty($data['dest_logements_recommandes']) && is_array($data['dest_logements_recommandes'])) {
            if (count($data['dest_logements_recommandes']) > 3) {
                $errors['dest_logements_recommandes'] = 'Maximum 3 logements peuvent être recommandés';
            }
        }

        return $errors;
    }
}
