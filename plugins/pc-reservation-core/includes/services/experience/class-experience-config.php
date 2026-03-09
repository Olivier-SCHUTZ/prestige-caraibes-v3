<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCR Experience Config - Dictionnaire et Configuration des Expériences
 * Centralise tous les mappings ACF et les statuts.
 * Pattern Singleton.
 * * @since 2.0.0 (Refactoring)
 */
class PCR_Experience_Config
{
    /**
     * Instance unique de la classe.
     * @var PCR_Experience_Config|null
     */
    private static $instance = null;

    /**
     * Constructeur privé (Singleton).
     */
    private function __construct() {}

    /**
     * Récupère l'instance unique.
     * @return PCR_Experience_Config
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Retourne les FIELD KEYS ACF réels pour chaque champ d'expérience.
     * Ces clés sont nécessaires pour que update_field() fonctionne correctement.
     * * @return array Mapping [normalized_key => field_key_acf]
     */
    public function get_acf_field_keys()
    {
        return [
            // === GÉNÉRAL & SEO ===
            'exp_exclude_sitemap' => 'field_68db7babdb30a',
            'exp_http_410' => 'field_68db7bf4db30b',
            'exp_meta_titre' => 'field_66dcc831d111b',
            'exp_meta_description' => 'field_66dcc867d111c',
            'exp_meta_canonical' => 'field_68db7c44db30c',
            'exp_meta_robots' => 'field_68db7ca7db30d',
            'exp_logements_recommandes' => 'field_66dcc8a4d111d',
            'exp_availability' => 'field_68d509f885264',

            // === DÉTAILS PRINCIPAUX ===
            'exp_h1_custom' => 'field_68beb1671e633',
            'exp_hero_desktop' => 'field_68beb1cd1e634',
            'exp_hero_mobile' => 'field_68beb2221e635',

            // === DÉTAILS SORTIES ===
            'exp_duree' => 'field_66dcc94cd111f',
            'exp_capacite' => 'field_66dcc9a3d1120',
            'exp_age_minimum' => 'field_66dcc9f9d1121',
            'exp_accessibilite' => 'field_66dcca37d1122',
            'exp_periode' => 'field_68bec10e3bc0f',
            'exp_jour' => 'field_68bf09049c6ae',
            'exp_periodes_fermeture' => 'field_66dccab9d1123',
            'exp_lieux_horaires_depart' => 'field_66dccb67d1126',

            // === INCLUSIONS & PRÉ-REQUIS ===
            'exp_prix_comprend' => 'field_66dcccc2d112c',
            'exp_prix_ne_comprend_pas' => 'field_66dccd1cd112d',
            'exp_a_prevoir' => 'field_66dccd4dd112e',

            // === SERVICES ===
            'exp_delai_de_reservation' => 'field_68dcd02002938',
            'exp_zone_intervention' => 'field_68dcd26402939',
            'exp_type_de_prestation' => 'field_68dcd3f60293a',
            'exp_heure_limite_de_commande' => 'field_68dcd5180293b',
            'exp_le_service_comprend' => 'field_68dce6573eeb6',
            'exp_service_a_prevoir' => 'field_68dce6ca3eeb7',

            // === GALERIE ===
            'photos_experience' => 'field_66dccda9d1130',

            // === FAQ ===
            'exp_faq' => 'field_66dcce25d1132',

            // === TARIFS ===
            'exp_types_de_tarifs' => 'field_66dcceddd1136',

            // === RÈGLES CHANNEL MANAGER & PAIEMENT ===
            'taux_tva' => 'field_692db668fa552',
            'pc_pay_mode' => 'field_6919d4793e90d',
            'pc_deposit_type' => 'field_6919e2d8b01b8',
            'pc_deposit_value' => 'field_6919e38eb01b9',
            'pc_balance_delay_days' => 'field_6919e3e5b01ba',
            'pc_caution_amount' => 'field_6919e424b01bb',
            'pc_caution_mode' => 'field_6919e47bb01bc',
        ];
    }

    /**
     * Retourne le mapping complet des champs ACF d'expérience vers des clés normalisées.
     * * @return array Mapping [cle_normalisee => meta_key_acf]
     */
    public function get_mapped_fields()
    {
        return [
            // === GÉNÉRAL ===
            'exp_h1_custom' => 'exp_h1_custom',
            'exp_exclude_sitemap' => 'exp_exclude_sitemap',
            'exp_http_410' => 'exp_http_410',
            'exp_availability' => 'exp_availability',

            // === SEO ===
            'exp_meta_titre' => 'exp_meta_titre',
            'exp_meta_description' => 'exp_meta_description',
            'exp_meta_canonical' => 'exp_meta_canonical',
            'exp_meta_robots' => 'exp_meta_robots',

            // === RELATIONS ===
            'exp_logements_recommandes' => 'exp_logements_recommandes',

            // === DÉTAILS DE L'EXPÉRIENCE ===
            'exp_duree' => 'exp_duree',
            'exp_capacite' => 'exp_capacite',
            'exp_age_minimum' => 'exp_age_minimum',
            'exp_accessibilite' => 'exp_accessibilite',
            'exp_periode' => 'exp_periode',
            'exp_jour' => 'exp_jour',

            // === LIEUX & HORAIRES (Repeater) ===
            'exp_lieux_horaires_depart' => 'exp_lieux_horaires_depart',

            // === PÉRIODES DE FERMETURE (Repeater) ===
            'exp_periodes_fermeture' => 'exp_periodes_fermeture',

            // === INCLUSIONS & EXCLUSIONS ===
            'exp_prix_comprend' => 'exp_prix_comprend',
            'exp_prix_ne_comprend_pas' => 'exp_prix_ne_comprend_pas',
            'exp_a_prevoir' => 'exp_a_prevoir',

            // === SERVICES ===
            'exp_delai_de_reservation' => 'exp_delai_de_reservation',
            'exp_zone_intervention' => 'exp_zone_intervention',
            'exp_type_de_prestation' => 'exp_type_de_prestation',
            'exp_heure_limite_de_commande' => 'exp_heure_limite_de_commande',
            'exp_le_service_comprend' => 'exp_le_service_comprend',
            'exp_service_a_prevoir' => 'exp_service_a_prevoir',

            // === IMAGES & MÉDIAS ===
            'exp_hero_desktop' => 'exp_hero_desktop',
            'exp_hero_mobile' => 'exp_hero_mobile',
            'photos_experience' => 'photos_experience',

            // === TARIFS (Repeater Complexe) ===
            'exp_types_de_tarifs' => 'exp_types_de_tarifs',

            // === PAIEMENT (Champs compatibles avec Housing) ===
            'taux_tva' => 'taux_tva',
            'pc_pay_mode' => 'pc_pay_mode',
            'pc_deposit_type' => 'pc_deposit_type',
            'pc_deposit_value' => 'pc_deposit_value',
            'pc_balance_delay_days' => 'pc_balance_delay_days',
            'pc_caution_amount' => 'pc_caution_amount',
            'pc_caution_mode' => 'pc_caution_mode',

            'exp_faq' => 'exp_faq',
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
}
