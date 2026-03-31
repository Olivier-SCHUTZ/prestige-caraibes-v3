<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCR ACF Fields - Déclarations programmatiques des champs ACF
 * 
 * Réintègre l'onglet "Promotions" qui était géré par l'ancien plugin pc-rate-manager.
 * Utilise acf_add_local_field_group() pour éviter les problèmes de sync JSON.
 * 
 * @since 0.1.5
 */
class PCR_ACF_Fields
{
    /**
     * Initialisation des hooks ACF.
     */
    public static function init()
    {
        // Hook existant pour les promotions
        add_action('acf/init', [self::class, 'add_promotions_tab']);

        // NOUVEAU HOOK : Ajout des champs Channel Manager & Rescue (Paiement, Contrat, Google)
        add_action('acf/init', [self::class, 'add_channel_manager_fields']);

        error_log('[PCR ACF Fields] Initialisation des déclarations ACF programmatiques');
    }

    /**
     * Ajoute l'onglet "Promotions" et ses champs au groupe existant.
     * 
     * IMPORTANT: Cette fonction utilise acf_add_local_field() pour ajouter
     * individuellement les champs au groupe existant "group_pc_fiche_logement".
     */
    public static function add_promotions_tab()
    {
        if (!function_exists('acf_add_local_field')) {
            error_log('[PCR ACF Fields] ACF non disponible pour add_promotions_tab');
            return;
        }

        try {
            // 1. ONGLET "Promotions"
            acf_add_local_field([
                'key' => 'field_693425857049c',
                'label' => 'Promotions',
                'name' => '',
                'type' => 'tab',
                'parent' => 'group_pc_fiche_logement', // S'accrocher au groupe existant
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => ''
                ],
                'placement' => 'left',
                'endpoint' => 0
            ]);

            // 2. RÉPÉTEUR "Promotions & Offres"
            acf_add_local_field([
                'key' => 'field_693425b17049d',
                'label' => 'Promotions & Offres',
                'name' => 'pc_promo_blocks',
                'type' => 'repeater',
                'parent' => 'group_pc_fiche_logement',
                'instructions' => 'Ajoutez ici les promotions et offres spéciales pour ce logement.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => ''
                ],
                'collapsed' => 'field_693425d17049e', // Collapse sur le nom
                'min' => 0,
                'max' => 10,
                'layout' => 'row',
                'button_label' => 'Ajouter une promotion',
                'sub_fields' => [
                    // 2.1 Nom de la promotion
                    [
                        'key' => 'field_693425d17049e',
                        'label' => 'Nom de la promotion',
                        'name' => 'nom_de_la_promotion',
                        'type' => 'text',
                        'instructions' => 'Exemple: "Séjour longue durée", "Promotion été 2024"',
                        'required' => 0, // ✅ CORRECTION: Pas obligatoire
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '40',
                            'class' => '',
                            'id' => ''
                        ],
                        'default_value' => '',
                        'placeholder' => 'Nom de la promotion',
                        'prepend' => '',
                        'append' => '',
                        'maxlength' => 100
                    ],

                    // 2.2 Type de promotion
                    [
                        'key' => 'field_693425f17049f',
                        'label' => 'Type',
                        'name' => 'promo_type',
                        'type' => 'select',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '30',
                            'class' => '',
                            'id' => ''
                        ],
                        'choices' => [
                            'percent' => 'Pourcentage (%)',
                            'fixed' => 'Montant fixe (€)'
                        ],
                        'default_value' => 'percent',
                        'allow_null' => 0,
                        'multiple' => 0,
                        'ui' => 1,
                        'return_format' => 'value',
                        'ajax' => 0,
                        'placeholder' => ''
                    ],

                    // 2.3 Valeur de la promotion
                    [
                        'key' => 'field_69342610704a0',
                        'label' => 'Valeur',
                        'name' => 'promo_value',
                        'type' => 'number',
                        'instructions' => 'Valeur de la remise (ex: 10 pour 10% ou 50 pour 50€)',
                        'required' => 0, // ✅ CORRECTION: Pas obligatoire
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '30',
                            'class' => '',
                            'id' => ''
                        ],
                        'default_value' => '',
                        'placeholder' => '0',
                        'prepend' => '',
                        'append' => '',
                        'min' => 0,
                        'max' => 100,
                        'step' => 0.01
                    ],

                    // 2.4 Date de validité
                    [
                        'key' => 'field_69342631704a1',
                        'label' => 'Valide jusqu\'au',
                        'name' => 'promo_valid_until',
                        'type' => 'date_picker',
                        'instructions' => 'Date limite de validité de cette promotion',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '100',
                            'class' => '',
                            'id' => ''
                        ],
                        'display_format' => 'd/m/Y',
                        'return_format' => 'Y-m-d',
                        'first_day' => 1
                    ],

                    // 2.5 Répéteur des périodes
                    [
                        'key' => 'field_69342652704a2',
                        'label' => 'Périodes d\'application',
                        'name' => 'promo_periods',
                        'type' => 'repeater',
                        'instructions' => 'Définissez les périodes pendant lesquelles cette promotion s\'applique.',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '100',
                            'class' => '',
                            'id' => ''
                        ],
                        'collapsed' => 'field_69342670704a3',
                        'min' => 0,
                        'max' => 20,
                        'layout' => 'table',
                        'button_label' => 'Ajouter une période',
                        'sub_fields' => [
                            // 2.5.1 Date de début
                            [
                                'key' => 'field_69342670704a3',
                                'label' => 'Du',
                                'name' => 'period_date_from',
                                'type' => 'date_picker',
                                'instructions' => '',
                                'required' => 0,
                                'conditional_logic' => 0,
                                'wrapper' => [
                                    'width' => '50',
                                    'class' => '',
                                    'id' => ''
                                ],
                                'display_format' => 'd/m/Y',
                                'return_format' => 'Y-m-d',
                                'first_day' => 1
                            ],

                            // 2.5.2 Date de fin
                            [
                                'key' => 'field_69342690704a4',
                                'label' => 'Au',
                                'name' => 'period_date_to',
                                'type' => 'date_picker',
                                'instructions' => '',
                                'required' => 0,
                                'conditional_logic' => 0,
                                'wrapper' => [
                                    'width' => '50',
                                    'class' => '',
                                    'id' => ''
                                ],
                                'display_format' => 'd/m/Y',
                                'return_format' => 'Y-m-d',
                                'first_day' => 1
                            ]
                        ]
                    ]
                ]
            ]);

            error_log('[PCR ACF Fields] ✅ Onglet "Promotions" ajouté avec succès au groupe group_pc_fiche_logement');
        } catch (Exception $e) {
            error_log('[PCR ACF Fields] ❌ Erreur lors de l\'ajout de l\'onglet Promotions : ' . $e->getMessage());
        }
    }

    /**
     * Fonction helper pour récupérer les promotions d'un logement.
     * 
     * @param int $post_id ID du post logement
     * @return array Promotions actives
     */
    public static function get_housing_promotions($post_id)
    {
        // Utilisation du nouveau wrapper PCR_Fields avec fallback de sécurité
        $promotions = class_exists('PCR_Fields')
            ? PCR_Fields::get('pc_promo_blocks', $post_id)
            : (function_exists('get_field') ? get_field('pc_promo_blocks', $post_id) : []);

        if (!is_array($promotions) || empty($promotions)) {
            return [];
        }

        $active_promotions = [];
        $current_date = date('Y-m-d');

        foreach ($promotions as $promo) {
            // Vérifier la validité globale
            if (!empty($promo['promo_valid_until']) && $promo['promo_valid_until'] < $current_date) {
                continue; // Promotion expirée
            }

            // Vérifier les périodes si définies
            $periods = $promo['promo_periods'] ?? [];
            $is_in_period = false;

            if (empty($periods)) {
                // Pas de période définie = toujours active (si pas expirée)
                $is_in_period = true;
            } else {
                // Vérifier si on est dans une des périodes
                foreach ($periods as $period) {
                    $date_from = $period['period_date_from'] ?? '';
                    $date_to = $period['period_date_to'] ?? '';

                    if (empty($date_from) && empty($date_to)) {
                        $is_in_period = true; // Période mal configurée, on considère comme active
                        break;
                    }

                    if (!empty($date_from) && $current_date < $date_from) {
                        continue; // Pas encore commencé
                    }

                    if (!empty($date_to) && $current_date > $date_to) {
                        continue; // Déjà terminé
                    }

                    $is_in_period = true;
                    break;
                }
            }

            if ($is_in_period) {
                // Formater les données pour l'usage
                $active_promotions[] = [
                    'nom' => $promo['nom_de_la_promotion'] ?? '',
                    'type' => $promo['promo_type'] ?? 'percent',
                    'value' => (float) ($promo['promo_value'] ?? 0),
                    'valid_until' => $promo['promo_valid_until'] ?? '',
                    'periods' => $periods
                ];
            }
        }

        return $active_promotions;
    }

    /**
     * Fonction helper pour calculer le prix avec promotions appliquées.
     * 
     * @param float $base_price Prix de base
     * @param array $promotions Promotions à appliquer
     * @return array [prix_final, economie, promotions_appliquees]
     */
    public static function calculate_promotional_price($base_price, $promotions)
    {
        $final_price = $base_price;
        $total_savings = 0;
        $applied_promotions = [];

        foreach ($promotions as $promo) {
            $savings = 0;

            if ($promo['type'] === 'percent') {
                $savings = $base_price * ($promo['value'] / 100);
            } elseif ($promo['type'] === 'fixed') {
                $savings = $promo['value'];
            }

            // Ne pas descendre en dessous de zéro
            $savings = min($savings, $final_price);

            $final_price -= $savings;
            $total_savings += $savings;

            $applied_promotions[] = [
                'nom' => $promo['nom'],
                'type' => $promo['type'],
                'value' => $promo['value'],
                'economie' => $savings
            ];
        }

        return [
            'prix_final' => max(0, $final_price),
            'economie_totale' => $total_savings,
            'promotions_appliquees' => $applied_promotions
        ];
    }

    /**
     * Restaure les onglets manquants : Channel Manager, Paiement, Contrat et Google VR.
     * Utilise les clés originales du JSON pour ne pas perdre de données.
     */
    public static function add_channel_manager_fields()
    {
        if (!function_exists('acf_add_local_field')) {
            return;
        }

        $parent_group = 'group_pc_fiche_logement'; // Le groupe principal existant

        try {
            // =========================================================
            // 1. ONGLET : RÈGLES CHANNEL MANAGER
            // =========================================================
            acf_add_local_field([
                'key' => 'field_6919e74557fd5',
                'label' => 'Règles Channel Manager',
                'type' => 'tab',
                'parent' => $parent_group,
            ]);

            // --- Taux de TVA ---
            acf_add_local_field([
                'key' => 'field_692db54735845',
                'label' => 'Taux de TVA applicable',
                'name' => 'taux_tva',
                'type' => 'number',
                'parent' => $parent_group,
                'wrapper' => ['width' => '50'],
                'append' => '%',
            ]);

            // --- Taux de TVA Ménage ---
            acf_add_local_field([
                'key' => 'field_692ffbf5ec554',
                'label' => 'Taux de TVA applicable ménage',
                'name' => 'taux_tva_menage',
                'type' => 'number',
                'parent' => $parent_group,
                'wrapper' => ['width' => '50'],
                'append' => '%',
            ]);

            // --- Mode de réservation ---
            acf_add_local_field([
                'key' => 'field_692986ddcf6e3',
                'label' => 'Mode de réservation',
                'name' => 'mode_reservation',
                'type' => 'select',
                'parent' => $parent_group,
                'choices' => [
                    'log_demande' => 'Logement sur demande',
                    'log_directe' => 'Logement en réservation directe',
                    'log_channel' => 'Autre Channel Manager'
                ],
            ]);

            // =========================================================
            // 2. SECTION : RÈGLES DE PAIEMENT (APLATIES)
            // =========================================================

            // Message de séparation visuelle
            acf_add_local_field([
                'key' => 'field_payment_rules_separator',
                'label' => 'Règles de paiement',
                'name' => '',
                'type' => 'message',
                'parent' => $parent_group,
                'message' => 'Configurez les modalités de paiement pour ce logement.',
                'new_lines' => 'wpautop',
                'esc_html' => 0,
                'wrapper' => [
                    'width' => '100',
                    'class' => 'acf-payment-rules-header',
                    'id' => ''
                ]
            ]);

            // Mode de paiement
            acf_add_local_field([
                'key' => 'field_6919e7994db4b',
                'label' => 'Mode de paiement',
                'name' => 'pc_pay_mode',
                'type' => 'select',
                'parent' => $parent_group,
                'wrapper' => ['width' => '50'],
                'choices' => [
                    'acompte_plus_solde' => 'Acompte plus solde',
                    'total_a_la_reservation' => 'Total à la réservation',
                    'sur_place' => 'Sur place',
                    'sur_devis' => 'Sur devis'
                ],
                'default_value' => 'acompte_plus_solde',
                'return_format' => 'value'
            ]);

            // Type d'acompte
            acf_add_local_field([
                'key' => 'field_6919e7994db4c',
                'label' => 'Type d\'acompte',
                'name' => 'pc_deposit_type',
                'type' => 'select',
                'parent' => $parent_group,
                'wrapper' => ['width' => '50'],
                'choices' => [
                    'pourcentage' => 'Pourcentage (%)',
                    'montant_fixe' => 'Montant fixe (€)'
                ],
                'default_value' => 'pourcentage',
                'return_format' => 'value'
            ]);

            // Valeur de l'acompte
            acf_add_local_field([
                'key' => 'field_6919e7994db4d',
                'label' => 'Valeur acompte',
                'name' => 'pc_deposit_value',
                'type' => 'number',
                'parent' => $parent_group,
                'wrapper' => ['width' => '50'],
                'instructions' => 'Montant en € ou pourcentage selon le type choisi',
                'min' => 0,
                'step' => 0.01
            ]);

            // Délai de solde
            acf_add_local_field([
                'key' => 'field_6919e7994db4e',
                'label' => 'Solde (jours avant arrivée)',
                'name' => 'pc_balance_delay_days',
                'type' => 'number',
                'parent' => $parent_group,
                'wrapper' => ['width' => '50'],
                'instructions' => 'Nombre de jours avant l\'arrivée pour payer le solde',
                'min' => 0,
                'step' => 1
            ]);

            // Montant de la caution
            acf_add_local_field([
                'key' => 'field_6919e7994db4f',
                'label' => 'Montant de la caution',
                'name' => 'pc_caution_amount',
                'type' => 'number',
                'parent' => $parent_group,
                'wrapper' => ['width' => '50'],
                'prepend' => '€',
                'min' => 0,
                'step' => 0.01
            ]);

            // Méthode de caution
            acf_add_local_field([
                'key' => 'field_6919e7994db50',
                'label' => 'Méthode de caution',
                'name' => 'pc_caution_type',
                'type' => 'select',
                'parent' => $parent_group,
                'wrapper' => ['width' => '50'],
                'choices' => [
                    'aucune' => 'Aucune caution',
                    'empreinte' => 'Empreinte bancaire',
                    'encaissement' => 'Caution encaissée'
                ],
                'default_value' => 'empreinte',
                'return_format' => 'value'
            ]);

            // =========================================================
            // 3. SECTION : INFOS CONTRAT & PROPRIÉTAIRE (APLATIE)
            // =========================================================

            // TITRE SÉPARATEUR
            acf_add_local_field([
                'key' => 'field_contrat_heading',
                'label' => 'Informations Contrat & Propriétaire',
                'name' => '',
                'type' => 'message',
                'parent' => $parent_group,
                'message' => 'Ces informations apparaissent sur le contrat de location PDF.',
            ]);

            // Champ 1 : Identité Propriétaire
            acf_add_local_field([
                'key' => 'field_6930b2a1248f7',
                'label' => 'Identité du propriétaire (Société ou Nom)',
                'name' => 'log_proprietaire_identite',
                'type' => 'text',
                'parent' => $parent_group,
                'placeholder' => 'ex: EI VILLA TREZEL',
                'wrapper' => ['width' => '50'],
            ]);

            // Champ 2 : Capacité Max (Contrat)
            acf_add_local_field([
                'key' => 'field_6930b83a248fe',
                'label' => 'Capacité Max (Assurance)',
                'name' => 'personne_logement',
                'type' => 'number',
                'parent' => $parent_group,
                'wrapper' => ['width' => '50'],
            ]);

            // Champ 3 : Adresse du bien
            acf_add_local_field([
                'key' => 'field_6930b32b248f8',
                'label' => 'Adresse complète du bien loué',
                'name' => 'proprietaire_adresse',
                'type' => 'textarea',
                'parent' => $parent_group,
                'rows' => 3,
                'new_lines' => 'br',
            ]);

            // Champ 4 : Descriptif
            acf_add_local_field([
                'key' => 'field_6930b751248fd',
                'label' => 'Descriptif succinct du logement',
                'name' => 'description_contrat',
                'type' => 'textarea',
                'parent' => $parent_group,
                'rows' => 3,
                'placeholder' => 'Ex: Villa T4 avec piscine...'
            ]);

            // Champ 5 : Équipements
            acf_add_local_field([
                'key' => 'field_6930b54c248fc',
                'label' => 'Liste des équipements (Contrat)',
                'name' => 'equipements_contrat',
                'type' => 'textarea',
                'parent' => $parent_group,
                'rows' => 3,
                'placeholder' => 'Clim, Wifi, TV...'
            ]);

            // Options Booléennes (Checkboxes) - Alignées
            acf_add_local_field([
                'key' => 'field_6930b427248f9',
                'label' => 'Piscine ?',
                'name' => 'has_piscine',
                'type' => 'true_false',
                'parent' => $parent_group,
                'ui' => 1,
                'wrapper' => ['width' => '33'],
            ]);

            acf_add_local_field([
                'key' => 'field_6930b4a5248fa',
                'label' => 'Jacuzzi ?',
                'name' => 'has_jacuzzi',
                'type' => 'true_false',
                'parent' => $parent_group,
                'ui' => 1,
                'wrapper' => ['width' => '33'],
            ]);

            acf_add_local_field([
                'key' => 'field_6930b4c9248fb',
                'label' => 'Livret d\'accueil numérique ?',
                'name' => 'has_guide_numerique',
                'type' => 'true_false',
                'parent' => $parent_group,
                'ui' => 1,
                'wrapper' => ['width' => '34'],
            ]);

            // =========================================================
            // 4. ONGLET : GOOGLE VR (RESCUE)
            // =========================================================
            acf_add_local_field([
                'key' => 'field_rescue_tab_google',
                'label' => 'Google VR (Rescue)',
                'type' => 'tab',
                'parent' => $parent_group,
            ]);

            acf_add_local_field([
                'key' => 'field_google_vr_accommodation_type',
                'label' => 'Type de location (Google)',
                'name' => 'google_vr_accommodation_type',
                'type' => 'select',
                'parent' => $parent_group,
                'required' => 0, // 🚑 IMPORTANT : FORCE NON REQUIS
                'choices' => [
                    'EntirePlace' => 'Logement entier',
                    'PrivateRoom' => 'Chambre privée',
                    'SharedRoom' => 'Chambre partagée'
                ],
                'default_value' => 'EntirePlace'
            ]);

            error_log('[PCR ACF Fields] ✅ Champs Channel Manager restaurés avec succès.');
        } catch (Exception $e) {
            error_log('[PCR ACF Fields] ❌ Erreur ajout Channel Manager : ' . $e->getMessage());
        }
    }
}
