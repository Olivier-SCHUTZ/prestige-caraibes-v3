<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCR Experience Data Mapper - Tableaux de mapping
 * 
 * Contient UNIQUEMENT les mappings des champs ACF pour le CPT experience.
 * Source de vérité : mu-plugins/pc-acf-json/group_66dcc7e9c5a16.json
 * 
 * @since 0.2.0
 */
class PCR_Experience_Data_Mapper
{
    /**
     * Retourne le mapping complet des champs ACF vers des clés normalisées.
     * Basé sur l'analyse du JSON ACF group_66dcc7e9c5a16.json
     * 
     * @return array Mapping [cle_normalisee => meta_key_acf]
     */
    public static function get_mapped_fields()
    {
        return [
            // === TAB 1: SEO & LIAISONS ===
            'exp_exclude_sitemap' => 'exp_exclude_sitemap',
            'exp_http_410' => 'exp_http_410',
            'exp_meta_titre' => 'exp_meta_titre',
            'exp_meta_description' => 'exp_meta_description',
            'exp_meta_canonical' => 'exp_meta_canonical',
            'exp_meta_robots' => 'exp_meta_robots',
            'exp_logements_recommandes' => 'exp_logements_recommandes',
            'exp_availability' => 'exp_availability',

            // === TAB 2: DÉTAILS PRINCIPAUX ===
            'exp_h1_custom' => 'exp_h1_custom',
            'exp_hero_desktop' => 'exp_hero_desktop',
            'exp_hero_mobile' => 'exp_hero_mobile',

            // === TAB 3: DÉTAILS SORTIES ===
            'exp_duree' => 'exp_duree',
            'exp_capacite' => 'exp_capacite',
            'exp_age_minimum' => 'exp_age_minimum',
            'exp_accessibilite' => 'exp_accessibilite',
            'exp_periode' => 'exp_periode',
            'exp_jour' => 'exp_jour',
            'exp_periodes_fermeture' => 'exp_periodes_fermeture',
            'exp_lieux_horaires_depart' => 'exp_lieux_horaires_depart',

            // === TAB 4: INCLUSIONS & PRÉ-REQUIS ===
            'exp_prix_comprend' => 'exp_prix_comprend',
            'exp_prix_ne_comprend_pas' => 'exp_prix_ne_comprend_pas',
            'exp_a_prevoir' => 'exp_a_prevoir',

            // === TAB 5: DÉTAILS SERVICES ===
            'exp_delai_de_reservation' => 'exp_delai_de_reservation',
            'exp_zone_intervention' => 'exp_zone_intervention',
            'exp_type_de_prestation' => 'exp_type_de_prestation',
            'exp_heure_limite_de_commande' => 'exp_heure_limite_de_commande',
            'exp_le_service_comprend' => 'exp_le_service_comprend',
            'exp_service_a_prevoir' => 'exp_service_a_prevoir',

            // === TAB 6: GALERIE PHOTOS ===
            'photos_experience' => 'photos_experience',

            // === TAB 7: FAQ ===
            'exp_faq' => 'exp_faq',

            // === TAB 8: TARIFS ===
            'exp_types_de_tarifs' => 'exp_types_de_tarifs',

            // === TAB 9: RÈGLES CHANNEL MANAGER ===
            'taux_tva' => 'taux_tva',
            'regles_de_paiement' => 'regles_de_paiement',
        ];
    }

    /**
     * Retourne les FIELD KEYS ACF réels pour chaque champ.
     * Ces clés sont nécessaires pour que update_field() fonctionne correctement.
     * Source : group_66dcc7e9c5a16.json
     * 
     * @return array Mapping [normalized_key => field_key_acf]
     */
    public static function get_acf_field_keys()
    {
        return [
            // === TAB 1: SEO & LIAISONS ===
            'exp_exclude_sitemap' => 'field_68db7babdb30a',
            'exp_http_410' => 'field_68db7bf4db30b',
            'exp_meta_titre' => 'field_66dcc831d111b',
            'exp_meta_description' => 'field_66dcc867d111c',
            'exp_meta_canonical' => 'field_68db7c44db30c',
            'exp_meta_robots' => 'field_68db7ca7db30d',
            'exp_logements_recommandes' => 'field_66dcc8a4d111d',
            'exp_availability' => 'field_68d509f885264',

            // === TAB 2: DÉTAILS PRINCIPAUX ===
            'exp_h1_custom' => 'field_68beb1671e633',
            'exp_hero_desktop' => 'field_68beb1cd1e634',
            'exp_hero_mobile' => 'field_68beb2221e635',

            // === TAB 3: DÉTAILS SORTIES ===
            'exp_duree' => 'field_66dcc94cd111f',
            'exp_capacite' => 'field_66dcc9a3d1120',
            'exp_age_minimum' => 'field_66dcc9f9d1121',
            'exp_accessibilite' => 'field_66dcca37d1122',
            'exp_periode' => 'field_68bec10e3bc0f',
            'exp_jour' => 'field_68bf09049c6ae',
            'exp_periodes_fermeture' => 'field_66dccab9d1123',
            'exp_lieux_horaires_depart' => 'field_66dccb67d1126',

            // === TAB 4: INCLUSIONS & PRÉ-REQUIS ===
            'exp_prix_comprend' => 'field_66dcccc2d112c',
            'exp_prix_ne_comprend_pas' => 'field_66dccd1cd112d',
            'exp_a_prevoir' => 'field_66dccd4dd112e',

            // === TAB 5: DÉTAILS SERVICES ===
            'exp_delai_de_reservation' => 'field_68dcd02002938',
            'exp_zone_intervention' => 'field_68dcd26402939',
            'exp_type_de_prestation' => 'field_68dcd3f60293a',
            'exp_heure_limite_de_commande' => 'field_68dcd5180293b',
            'exp_le_service_comprend' => 'field_68dce6573eeb6',
            'exp_service_a_prevoir' => 'field_68dce6ca3eeb7',

            // === TAB 6: GALERIE PHOTOS ===
            'photos_experience' => 'field_66dccda9d1130',

            // === TAB 7: FAQ ===
            'exp_faq' => 'field_66dcce25d1132',

            // === TAB 8: TARIFS ===
            'exp_types_de_tarifs' => 'field_66dcceddd1136',

            // === TAB 9: RÈGLES CHANNEL MANAGER ===
            'taux_tva' => 'field_692db668fa552',
            'regles_de_paiement' => 'field_6919d3b37ec50',
        ];
    }

    /**
     * Retourne les field keys des sous-champs pour les repeaters complexes.
     * Nécessaire pour la stratégie "Flatten" des repeaters.
     * 
     * @return array Mapping des sous-champs
     */
    public static function get_repeater_sub_fields()
    {
        return [
            // === REPEATER: Périodes de fermeture ===
            'exp_periodes_fermeture' => [
                'debut_fermeture' => 'field_66dccb00d1124',
                'fin_fermeture' => 'field_66dccb31d1125',
            ],

            // === REPEATER: Lieux et horaires de départ ===
            'exp_lieux_horaires_depart' => [
                'exp_lieu_depart' => 'field_66dccb99d1127',
                'lat_exp' => 'field_68beab879029c',
                'longitude' => 'field_68beac099029d',
                'exp_heure_depart' => 'field_66dccc32d1129',
                'exp_heure_retour' => 'field_66dccc58d112a',
            ],

            // === REPEATER: FAQ ===
            'exp_faq' => [
                'exp_question' => 'field_66dcce59d1133',
                'exp_reponse' => 'field_66dcce7ed1134',
            ],

            // === REPEATER: Types de tarifs ===
            'exp_types_de_tarifs' => [
                'exp_type' => 'field_66dccf34d1137',
                'exp_type_custom' => 'field_690fbd8e6aff8',
                'exp_options_tarifaires' => 'field_66dcd1a0d113d',
                'exp-frais-fixes' => 'field_69189c9dfcb64',
                'exp_tarifs_lignes' => 'field_6911d4141ac38',
            ],

            // === SOUS-REPEATER: Options tarifaires ===
            'exp_options_tarifaires' => [
                'exp_description_option' => 'field_66dcd20ad113e',
                'exp_tarif_option' => 'field_66dcd250d113f',
                'option_enable_qty' => 'field_6911dd3a636a5',
            ],

            // === SOUS-REPEATER: Frais fixes ===
            'exp-frais-fixes' => [
                'exp_description_frais_fixe' => 'field_69189d65fcb65',
                'exp_tarif_frais_fixe' => 'field_69189de5fcb66',
            ],

            // === SOUS-REPEATER: Lignes de tarifs ===
            'exp_tarifs_lignes' => [
                'type_ligne' => 'field_6911d6131ac39',
                'tarif_valeur' => 'field_6911d7821ac3a',
                'tarif_enable_qty' => 'field_6911d9851ac3b',
                'precision_age_enfant' => 'field_6911dae01ac3c',
                'precision_age_bebe' => 'field_6911dbb41ac3d',
                'tarif_nom_perso' => 'field_6911dc051ac3e',
                'tarif_observation' => 'field_6911dc931ac3f',
            ],

            // === GROUP: Règles de paiement ===
            'regles_de_paiement' => [
                'pc_pay_mode' => 'field_6919d4793e90d',
                'pc_deposit_type' => 'field_6919e2d8b01b8',
                'pc_deposit_value' => 'field_6919e38eb01b9',
                'pc_balance_delay_days' => 'field_6919e3e5b01ba',
                'pc_caution_amount' => 'field_6919e424b01bb',
                'pc_caution_mode' => 'field_6919e47bb01bc',
            ],
        ];
    }

    /**
     * Retourne la configuration d'un champ par son slug normalisé.
     * 
     * @param string $slug Le slug normalisé du champ
     * @return array|false Configuration du champ ou false si non trouvé
     */
    public static function get_field_config_by_slug($slug)
    {
        $field_keys = self::get_acf_field_keys();
        $mapped_fields = self::get_mapped_fields();

        $config = [
            'slug' => $slug,
            'key' => null,
            'meta_key' => null,
        ];

        // 1. Vérifier d'abord les field keys ACF
        if (isset($field_keys[$slug])) {
            $config['key'] = $field_keys[$slug];
            $config['meta_key'] = $mapped_fields[$slug] ?? $slug;
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
     * Retourne les choix disponibles pour les champs select/checkbox.
     * Extrait du JSON ACF pour maintenir la cohérence.
     * 
     * @return array Choix par champ
     */
    public static function get_field_choices()
    {
        return [
            'exp_availability' => [
                'InStock' => 'Réservable actuellement',
                'SoldOut' => 'Complet / Plus de places',
                'PreOrder' => 'Bientôt disponible',
            ],

            'exp_meta_robots' => [
                'index,follow' => 'index,follow',
                'noindex,follow' => 'noindex,follow',
                'noindex,nofollow' => 'noindex,nofollow',
            ],

            'exp_accessibilite' => [
                'accessible_pmr' => 'Accessible aux personnes âgées ou mobilité réduite',
                'accessible_pmr_f' => 'Non accessible aux fauteuils roulants',
                'poussettes' => 'Accessible en poussette',
                'animaux_admis' => 'Animaux de compagnie admis',
                'accessible_enfants' => 'Accessible aux enfants',
                'activité_physique' => 'Activité physique ou sportive intense',
                'activité_physique_m' => 'Activité physique ou sportive moyenne',
                'activité_physique_l' => 'Activité physique ou sportive légère',
            ],

            'exp_periode' => [
                'année' => 'toute l\'année',
                'saison' => 'en saison',
                'réservation' => 'sur réservation',
            ],

            'exp_jour' => [
                'tous' => 'tous les jours',
                'lundi' => 'lundi',
                'mardi' => 'mardi',
                'mercredi' => 'mercredi',
                'jeudi' => 'jeudi',
                'vendredi' => 'vendredi',
                'samedi' => 'samedi',
                'dimanche' => 'dimanche',
            ],

            'exp_a_prevoir' => [
                'creme_solaire' => 'Crème solaire minérale',
                'serviette' => 'Serviette de bain',
                'maillot_de_bain' => 'Maillot de bain',
                'eau_collations' => 'Une bouteille d\'eau et collations',
                'appareil_photo' => 'Appareil photo',
                'chaussures_marche' => 'Chaussures de marche',
                'Chaussons_eau  Chaussons d\'eau' => 'Chaussons_eau  Chaussons d\'eau',
                'Imperméable' => 'Imperméable/coupe vent',
                'Vêtements' => 'Vêtements de rechange',
                'Casquette' => 'Casquette/Bob',
                'Snorkeling' => 'Équipement de snorkeling',
                'Teeshirt' => 'Tee-shirts anti UV',
            ],

            'exp_delai_de_reservation' => [
                '24h' => '24h à l\'avance',
                '48h' => '48h à l\'avance',
                '72h' => '72h à l\'avance',
                '1 semaine' => '1 semaine à l\'avance',
                'Avant départ' => 'Avant le départ',
            ],

            'exp_zone_intervention' => [
                'Guadeloupe' => 'Guadeloupe',
                'Grande-Terre' => 'Grande-Terre',
                'Basse-Terre' => 'Basse-Terre',
                'Saint-François, alentours' => 'Saint-François, alentours',
                'Sainte-Anne, alentours' => 'Sainte-Anne, alentours',
                'Le Gosier, alentours' => 'Le Gosier, alentours',
                'Morne à l\'Eau, alentours' => 'Morne à l\'Eau, alentours',
                'Baie-Mahault, alentours' => 'Baie-Mahault, alentours',
                'Deshaies, alentours' => 'Deshaies, alentours',
                'Bouillante, alentours' => 'Bouillante, alentours',
                'Basse-Terre, alentours' => 'Basse-Terre, alentours',
            ],

            'exp_type' => [
                'journee' => 'Journée',
                'demi-journee' => 'Demi-journée',
                'unique' => 'Unique / Forfaitaire',
                'sur-devis' => 'Sur devis',
                'custom' => 'Autre (personnalisé)',
            ],

            'type_ligne' => [
                'adulte' => 'Adulte',
                'enfant' => 'Enfant',
                'bebe' => 'Bébé',
                'personnalise' => 'Personnalisé / Forfait',
            ],

            'pc_pay_mode' => [
                'acompte_plus_solde' => 'Acompte plus solde',
                'total_a_la_reservation' => 'Total à la réservation',
                'sur_place' => 'Sur place',
                'sur_devis' => 'Sur devis',
            ],

            'pc_deposit_type' => [
                'pourcentage' => 'Pourcentage',
                'montant_fixe' => 'Montant fixe',
            ],

            'pc_caution_mode' => [
                'aucune' => 'Aucune caution',
                'empreinte' => 'Empreinte bancaire',
                'encaissement' => 'Caution encaisser',
            ],
        ];
    }

    /**
     * Retourne les types de champs pour la validation et le traitement.
     * 
     * @return array Types par champ
     */
    public static function get_field_types()
    {
        return [
            // Champs booléens
            'exp_exclude_sitemap' => 'boolean',
            'exp_http_410' => 'boolean',
            'option_enable_qty' => 'boolean',
            'tarif_enable_qty' => 'boolean',

            // Champs numériques
            'exp_duree' => 'number',
            'exp_capacite' => 'number',
            'exp_age_minimum' => 'number',
            'exp_heure_limite_de_commande' => 'number',
            'lat_exp' => 'number',
            'longitude' => 'number',
            'exp_tarif_option' => 'number',
            'exp_tarif_frais_fixe' => 'number',
            'tarif_valeur' => 'number',
            'taux_tva' => 'number',
            'pc_deposit_value' => 'number',
            'pc_balance_delay_days' => 'number',
            'pc_caution_amount' => 'number',

            // Champs date/time
            'debut_fermeture' => 'date',
            'fin_fermeture' => 'date',
            'exp_heure_depart' => 'time',
            'exp_heure_retour' => 'time',

            // Champs images
            'exp_hero_desktop' => 'image',
            'exp_hero_mobile' => 'image',
            'photos_experience' => 'gallery',

            // Champs select
            'exp_availability' => 'select',
            'exp_meta_robots' => 'select',
            'exp_type' => 'select',
            'type_ligne' => 'select',
            'pc_pay_mode' => 'select',
            'pc_deposit_type' => 'select',
            'pc_caution_mode' => 'select',

            // Champs checkbox
            'exp_accessibilite' => 'checkbox',
            'exp_periode' => 'checkbox',
            'exp_jour' => 'checkbox',
            'exp_a_prevoir' => 'checkbox',
            'exp_delai_de_reservation' => 'checkbox',
            'exp_zone_intervention' => 'checkbox',

            // Champs relationship
            'exp_logements_recommandes' => 'relationship',

            // Champs repeater
            'exp_periodes_fermeture' => 'repeater',
            'exp_lieux_horaires_depart' => 'repeater',
            'exp_faq' => 'repeater',
            'exp_types_de_tarifs' => 'repeater',
            'exp_options_tarifaires' => 'repeater',
            'exp-frais-fixes' => 'repeater',
            'exp_tarifs_lignes' => 'repeater',

            // Champs group
            'regles_de_paiement' => 'group',

            // Champs texte/textarea par défaut
            // Tous les autres sont des champs text/textarea
        ];
    }

    /**
     * Retourne les champs organisés par onglet selon la structure ACF.
     * Facilite l'utilisation dans les shortcodes et l'interface d'administration.
     * Basé sur la structure des 9 onglets du JSON ACF group_66dcc7e9c5a16.json
     * 
     * @return array Champs organisés par onglet [nom_onglet => array_de_champs]
     */
    public static function get_fields_by_tab()
    {
        return [
            'seo_liaisons' => [
                'exp_exclude_sitemap',
                'exp_http_410',
                'exp_meta_titre',
                'exp_meta_description',
                'exp_meta_canonical',
                'exp_meta_robots',
                'exp_logements_recommandes',
                'exp_availability',
            ],

            'details_principaux' => [
                'exp_h1_custom',
                'exp_hero_desktop',
                'exp_hero_mobile',
            ],

            'details_sorties' => [
                'exp_duree',
                'exp_capacite',
                'exp_age_minimum',
                'exp_accessibilite',
                'exp_periode',
                'exp_jour',
                'exp_periodes_fermeture',
                'exp_lieux_horaires_depart',
            ],

            'inclusions_prerequis' => [
                'exp_prix_comprend',
                'exp_prix_ne_comprend_pas',
                'exp_a_prevoir',
            ],

            'details_services' => [
                'exp_delai_de_reservation',
                'exp_zone_intervention',
                'exp_type_de_prestation',
                'exp_heure_limite_de_commande',
                'exp_le_service_comprend',
                'exp_service_a_prevoir',
            ],

            'galerie_photos' => [
                'photos_experience',
            ],

            'faq' => [
                'exp_faq',
            ],

            'tarifs' => [
                'exp_types_de_tarifs',
            ],

            'regles_channel_manager' => [
                'taux_tva',
                'regles_de_paiement',
            ],
        ];
    }
}
