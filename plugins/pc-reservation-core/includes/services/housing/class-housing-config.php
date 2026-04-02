<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCR Housing Config - Dictionnaire et Configuration des Logements
 * * Centralise tous les mappings ACF, les statuts et les modes de réservation.
 * Pattern Singleton.
 * * @since 2.0.0 (Refactoring)
 */
class PCR_Housing_Config
{
    /**
     * Instance unique de la classe.
     * @var PCR_Housing_Config|null
     */
    private static $instance = null;

    /**
     * Constructeur privé (Singleton).
     */
    private function __construct() {}

    /**
     * Récupère l'instance unique.
     * * @return PCR_Housing_Config
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Retourne le mapping complet des 78 champs ACF vers des clés normalisées.
     * * @return array Mapping [cle_normalisee => meta_key_acf]
     */
    public function get_mapped_fields()
    {
        return [
            // === MÉDIA & GALERIE ===
            'hero_desktop_url' => 'hero_desktop_url',
            'hero_mobile_url' => 'hero_mobile_url',
            'gallery_urls' => 'gallery_urls',
            'video_urls' => 'video_urls',
            'groupes_images' => 'groupes_images',

            // === CONTENU SEO ===
            'contenu_seo_titre_h1' => 'contenu_seo_titre_h1',
            'seo_long_html' => 'seo_long_html',
            'highlights' => 'highlights',
            'highlights_custom' => 'highlights_custom',
            'logement_experiences_recommandees' => 'logement_experiences_recommandees',
            'logement_faq' => 'logement_faq',

            // === DÉTAILS & CAPACITÉS ===
            'identifiant_lodgify' => 'identifiant_lodgify',
            'capacite' => 'capacite',
            'superficie' => 'superficie',
            'nombre_de_chambres' => 'nombre_de_chambres',
            'nombre_sdb' => 'nombre_sdb',
            'nombre_lits' => 'nombre_lits',

            // === ÉQUIPEMENTS (tous les groupes) ===
            'eq_piscine_spa' => 'eq_piscine_spa',
            'eq_parking_installations' => 'eq_parking_installations',
            'eq_politiques' => 'eq_politiques',
            'eq_divertissements' => 'eq_divertissements',
            'eq_cuisine_salle_a_manger' => 'eq_cuisine_salle_a_manger',
            'eq_caracteristiques_emplacement' => 'eq_caracteristiques_emplacement',
            'eq_salle_de_bain_blanchisserie' => 'eq_salle_de_bain_blanchisserie',
            'eq_chauffage_climatisation' => 'eq_chauffage_climatisation',
            'eq_internet_bureautique' => 'eq_internet_bureautique',
            'eq_securite_maison' => 'eq_securite_maison',

            // === EMPLACEMENT & PROXIMITÉS ===
            'geo_coords' => 'geo_coords',
            'geo_radius_m' => 'geo_radius_m',
            'prox_airport_km' => 'prox_airport_km',
            'prox_bus_km' => 'prox_bus_km',
            'prox_port_km' => 'prox_port_km',
            'prox_beach_km' => 'prox_beach_km',
            'adresse_rue' => 'adresse_rue',
            'ville' => 'ville',
            'code_postal' => 'code_postal',
            'latitude' => 'latitude',
            'longitude' => 'longitude',

            // === RÉSERVATION ===
            'politique_dannulation' => 'politique_dannulation',
            'regles_maison' => 'regles_maison',
            'horaire_arrivee' => 'horaire_arrivee',
            'horaire_depart' => 'horaire_depart',
            'icals_sync' => 'icals_sync',
            'lodgify_widget_embed' => 'lodgify_widget_embed',

            // === TARIFS ===
            'base_price_from' => 'base_price_from',
            'pc_promo_log' => 'pc-promo-log',
            'min_nights' => 'min_nights',
            'max_nights' => 'max_nights',
            'unite_de_prix' => 'unite_de_prix',
            'extra_guest_fee' => 'extra_guest_fee',
            'extra_guest_from' => 'extra_guest_from',
            'caution' => 'caution',
            'frais_menage' => 'frais_menage',
            'autres_frais' => 'autres_frais',
            'autres_frais_type' => 'autres_frais_type',
            'taxe_sejour' => 'taxe_sejour',

            // === TARIFS SAISON ===
            'pc_season_blocks' => 'pc_season_blocks',

            // === PROMOTIONS ===
            'pc_promo_blocks' => 'pc_promo_blocks',

            // === HÔTE ===
            'hote_nom' => 'hote_nom',
            'hote_description' => 'hote_description',
            'hote_photo' => 'hote_photo',

            // === OVERRIDES SEO ===
            'log_exclude_sitemap' => 'log_exclude_sitemap',
            'log_http_410' => 'log_http_410',
            'meta_titre' => 'meta_titre',
            'meta_description' => 'meta_description',
            'url_canonique' => 'url_canonique',
            'log_meta_robots' => 'log_meta_robots',
            'seo_gallery_urls' => 'seo_gallery_urls',

            // === GOOGLE VR ===
            'google_vr_accommodation_type' => 'google_vr_accommodation_type',
            'google_vr_amenities' => 'google_vr_amenities',

            // === CHANNEL MANAGER ===
            'taux_tva' => 'taux_tva',
            'taux_tva_menage' => 'taux_tva_menage',
            'mode_reservation' => 'mode_reservation',
            'ical_export_token' => 'ical_export_token',

            // === RÈGLES DE PAIEMENT (APLATIES) ===
            'pc_pay_mode' => 'pc_pay_mode',
            'pc_deposit_type' => 'pc_deposit_type',
            'pc_deposit_value' => 'pc_deposit_value',
            'pc_balance_delay_days' => 'pc_balance_delay_days',
            'pc_caution_amount' => 'pc_caution_amount',
            'pc_caution_type' => 'pc_caution_type',

            // === INFOS CONTRAT & PROPRIÉTAIRE (APLATIES) ===
            'log_proprietaire_identite' => 'log_proprietaire_identite',
            'personne_logement' => 'personne_logement',
            'proprietaire_adresse' => 'proprietaire_adresse',
            'description_contrat' => 'description_contrat',
            'equipements_contrat' => 'equipements_contrat',
            'has_piscine' => 'has_piscine',
            'has_jacuzzi' => 'has_jacuzzi',
            'has_guide_numerique' => 'has_guide_numerique',
        ];
    }

    /**
     * Retourne le mapping des meta_key avec traits d'union vers leurs vraies clés ACF.
     * * @return array
     */
    public function get_special_meta_keys()
    {
        return [
            'base_price_from' => 'prix-a-partir-de-e-nuit-prix-de-base',
            'unite_de_prix' => 'unite-de-prix',
            'caution' => 'caution-e',
        ];
    }

    /**
     * Retourne les FIELD KEYS ACF réels pour chaque champ.
     * * @return array Mapping [normalized_key => field_key_acf]
     */
    public function get_acf_field_keys()
    {
        return [
            'hero_desktop_url' => 'field_pc_hero_desktop_url',
            'hero_mobile_url' => 'field_pc_hero_mobile_url',
            'gallery_urls' => 'field_pc_gallery_urls',
            'video_urls' => 'field_pc_video_urls',
            'seo_gallery_urls' => 'field_pc_seo_gallery_urls',
            'capacite' => 'field_pc_capacite',
            'base_price_from' => 'field_pc_prix_base',
            'prix_nuit' => 'field_pc_prix_nuit',
            'mode_reservation' => 'field_692986ddcf6e3',
            'icals_sync' => 'icals_sync',
            'identifiant_lodgify' => 'identifiant_lodgify',
            'superficie' => 'superficie',
            'nombre_de_chambres' => 'nombre_de_chambres',
            'nombre_sdb' => 'nombre_sdb',
            'nombre_lits' => 'nombre_lits',
            'ville' => 'ville',
            'latitude' => 'latitude',
            'longitude' => 'longitude',
            'min_nights' => 'min_nights',
            'max_nights' => 'max_nights',
            'frais_menage' => 'frais_menage',
            'taux_tva' => 'taux_tva',
            'taux_tva_menage' => 'taux_tva_menage',
            'politique_dannulation' => 'politique_dannulation',
            'regles_maison' => 'regles_maison',
            'horaire_arrivee' => 'horaire_arrivee',
            'horaire_depart' => 'horaire_depart',
            'hote_nom' => 'hote_nom',
            'hote_description' => 'hote_description',
            'meta_titre' => 'meta_titre',
            'meta_description' => 'meta_description',
            'url_canonique' => 'url_canonique',
            'eq_piscine_spa' => 'eq_piscine_spa',
            'eq_parking_installations' => 'eq_parking_installations',
            'eq_politiques' => 'eq_politiques',
            'eq_divertissements' => 'eq_divertissements',
            'eq_cuisine_salle_a_manger' => 'eq_cuisine_salle_a_manger',
            'eq_caracteristiques_emplacement' => 'eq_caracteristiques_emplacement',
            'eq_salle_de_bain_blanchisserie' => 'eq_salle_de_bain_blanchisserie',
            'eq_chauffage_climatisation' => 'eq_chauffage_climatisation',
            'eq_internet_bureautique' => 'eq_internet_bureautique',
            'eq_securite_maison' => 'eq_securite_maison',
            'pc_pay_mode' => 'field_6919e7994db4b',
            'pc_deposit_type' => 'field_6919e7994db4c',
            'pc_deposit_value' => 'field_6919e7994db4d',
            'pc_balance_delay_days' => 'field_6919e7994db4e',
            'pc_caution_amount' => 'field_6919e7994db4f',
            'pc_caution_type' => 'field_6919e7994db50',
            'log_proprietaire_identite' => 'field_6930b2a1248f7',
            'personne_logement' => 'field_6930b83a248fe',
            'proprietaire_adresse' => 'field_6930b32b248f8',
            'description_contrat' => 'field_6930b751248fd',
            'equipements_contrat' => 'field_6930b54c248fc',
            'has_piscine' => 'field_6930b427248f9',
            'has_jacuzzi' => 'field_6930b4a5248fa',
            'has_guide_numerique' => 'field_6930b4c9248fb',
        ];
    }

    /**
     * Trouve la configuration ACF d'un champ par son slug.
     * * @param string $slug Le slug normalisé du champ
     * @return array|false Configuration du champ ou false si non trouvé
     */
    public function get_field_config_by_slug($slug)
    {
        $field_keys = $this->get_acf_field_keys();
        $special_keys = $this->get_special_meta_keys();
        $mapped_fields = $this->get_mapped_fields();

        $config = [
            'slug' => $slug,
            'key' => null,
            'meta_key' => null,
        ];

        if (isset($field_keys[$slug])) {
            $config['key'] = $field_keys[$slug];
            $config['meta_key'] = $field_keys[$slug];
            return $config;
        }

        if (isset($special_keys[$slug])) {
            $config['meta_key'] = $special_keys[$slug];
            return $config;
        }

        if (isset($mapped_fields[$slug])) {
            $config['meta_key'] = $mapped_fields[$slug];
            return $config;
        }

        return false;
    }

    /**
     * Retourne le label d'affichage pour un statut de post.
     * * @param string $status
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
     * * @param string $status
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
     * Retourne le label d'affichage pour un mode de réservation.
     * * @param string $mode
     * @return string
     */
    public function get_mode_label($mode)
    {
        $labels = [
            'log_demande' => 'Sur demande',
            'log_directe' => 'Réservation directe',
            'log_channel' => 'Channel Manager',
        ];

        return $labels[$mode] ?? 'Réservation directe';
    }

    /**
     * Retourne la classe CSS pour un mode de réservation.
     * * @param string $mode
     * @return string
     */
    public function get_mode_class($mode)
    {
        $classes = [
            'log_demande' => 'pc-mode--demand',
            'log_directe' => 'pc-mode--direct',
            'log_channel' => 'pc-mode--channel',
        ];

        return $classes[$mode] ?? 'pc-mode--direct';
    }
}
