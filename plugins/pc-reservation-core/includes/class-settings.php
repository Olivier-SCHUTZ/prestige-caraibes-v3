<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gère la configuration globale du plugin.
 * Centralise : Paiements (Stripe), Identité Entreprise, Banques et Design PDF.
 */
class PCR_Settings
{
    public static function init()
    {
        // 1. Page Principale : "PC Réservation" (Page vide mais accessible pour les CPT)
        if (function_exists('acf_add_options_page')) {
            acf_add_options_page([
                'page_title'  => 'PC Réservation - Tableau de bord',
                'menu_title'  => 'PC Réservation',
                'menu_slug'   => 'pc-reservation-settings',
                'capability'  => 'manage_options',
                'icon_url'    => 'dashicons-calendar-alt',
                'redirect'    => false, // Garde la page accessible pour les CPT
            ]);
        }

        // 2. Sous-Page : "Configuration Stripe"
        if (function_exists('acf_add_options_sub_page')) {
            acf_add_options_sub_page([
                'page_title'  => 'Configuration Stripe & Paiements',
                'menu_title'  => 'Configuration Stripe',
                'parent_slug' => 'pc-reservation-settings',
                'menu_slug'   => 'pc-reservation-stripe',
            ]);
        }

        // 3. Sous-Page : "Documents & Légal" (Pour PDF, Identité, Banque)
        if (function_exists('acf_add_options_sub_page')) {
            acf_add_options_sub_page([
                'page_title'  => 'Configuration Documents & Légal',
                'menu_title'  => 'Documents & Légal',
                'parent_slug' => 'pc-reservation-settings', // Enfant de la page principale
                'menu_slug'   => 'pc-reservation-documents',
            ]);
        }

        // Enregistrement des champs ACF
        add_action('acf/init', [__CLASS__, 'register_fields']);
    }

    public static function register_fields()
    {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        // =================================================================
        // GROUPE 1 : CONFIGURATION STRIPE (Page Principale)
        // =================================================================
        acf_add_local_field_group([
            'key' => 'group_pc_stripe_settings',
            'title' => 'Configuration Stripe & Paiements',
            'fields' => [
                [
                    'key' => 'field_pc_stripe_mode',
                    'label' => 'Mode',
                    'name' => 'pc_stripe_mode',
                    'type' => 'button_group',
                    'choices' => [
                        'test' => 'Test (Sandbox)',
                        'live' => 'Live (Production)',
                    ],
                    'default_value' => 'test',
                    'layout' => 'horizontal',
                ],
                // Clés TEST
                [
                    'key' => 'field_pc_stripe_test_pk',
                    'label' => 'Clé Publique (Test)',
                    'name' => 'pc_stripe_test_pk',
                    'type' => 'text',
                    'conditional_logic' => [[['field' => 'field_pc_stripe_mode', 'operator' => '==', 'value' => 'test']]],
                ],
                [
                    'key' => 'field_pc_stripe_test_sk',
                    'label' => 'Clé Secrète (Test)',
                    'name' => 'pc_stripe_test_sk',
                    'type' => 'password',
                    'conditional_logic' => [[['field' => 'field_pc_stripe_mode', 'operator' => '==', 'value' => 'test']]],
                ],
                // Clés LIVE
                [
                    'key' => 'field_pc_stripe_live_pk',
                    'label' => 'Clé Publique (Live)',
                    'name' => 'pc_stripe_live_pk',
                    'type' => 'text',
                    'conditional_logic' => [[['field' => 'field_pc_stripe_mode', 'operator' => '==', 'value' => 'live']]],
                ],
                [
                    'key' => 'field_pc_stripe_live_sk',
                    'label' => 'Clé Secrète (Live)',
                    'name' => 'pc_stripe_live_sk',
                    'type' => 'password',
                    'conditional_logic' => [[['field' => 'field_pc_stripe_mode', 'operator' => '==', 'value' => 'live']]],
                ],
                [
                    'key' => 'field_pc_stripe_webhook_secret',
                    'label' => 'Secret Webhook (Signature)',
                    'name' => 'pc_stripe_webhook_secret',
                    'type' => 'text',
                    'instructions' => 'Nécessaire pour valider les paiements automatiquement.',
                ],
            ],
            'location' => [
                [['param' => 'options_page', 'operator' => '==', 'value' => 'pc-reservation-stripe']],
            ],
        ]);

        // =================================================================
        // GROUPE 2 : DOCUMENTS, IDENTITÉ & BANQUE (Sous-Page)
        // =================================================================
        acf_add_local_field_group([
            'key' => 'group_pc_pdf_global_settings',
            'title' => 'Design, Identité & Mentions Légales',
            'fields' => [
                // Onglet Identité Visuelle
                [
                    'key' => 'field_tab_pdf_identity',
                    'label' => 'Identité Visuelle',
                    'type' => 'tab',
                ],
                [
                    'key' => 'field_pc_pdf_logo',
                    'label' => 'Logo',
                    'name' => 'pc_pdf_logo',
                    'type' => 'image',
                    'return_format' => 'url', // URL est souvent plus simple pour Dompdf
                    'preview_size' => 'medium',
                ],
                [
                    'key' => 'field_pc_pdf_primary_color',
                    'label' => 'Couleur Principale',
                    'name' => 'pc_pdf_primary_color',
                    'type' => 'color_picker',
                    'default_value' => '#4338ca',
                ],

                // Onglet Mentions Légales & Contact
                [
                    'key' => 'field_tab_pdf_legal',
                    'label' => 'Mentions Légales & Contact',
                    'type' => 'tab',
                ],
                [
                    'key' => 'field_pc_legal_name',
                    'label' => 'Raison Sociale / Nom',
                    'name' => 'pc_legal_name',
                    'type' => 'text',
                    'placeholder' => 'SARL Prestige Caraïbes',
                ],
                [
                    'key' => 'field_pc_legal_address',
                    'label' => 'Siège Social (Adresse complète)',
                    'name' => 'pc_legal_address',
                    'type' => 'textarea',
                    'rows' => 3,
                    'instructions' => 'Apparaîtra en haut des documents.',
                ],
                // [NOUVEAU] Contact
                [
                    'key' => 'field_pc_legal_email',
                    'label' => 'Email de contact (Facturation)',
                    'name' => 'pc_legal_email',
                    'type' => 'email',
                ],
                // [NOUVEAU] Téléphone
                [
                    'key' => 'field_pc_legal_phone',
                    'label' => 'Téléphone (Facturation)',
                    'name' => 'pc_legal_phone',
                    'type' => 'text',
                ],
                // Identifiants
                [
                    'key' => 'field_pc_legal_siret',
                    'label' => 'SIRET',
                    'name' => 'pc_legal_siret',
                    'type' => 'text',
                ],
                [
                    'key' => 'field_pc_legal_tva',
                    'label' => 'Numéro TVA Intracommunautaire',
                    'name' => 'pc_legal_tva',
                    'type' => 'text',
                    'instructions' => 'Si non assujetti, mettre : "TVA non applicable, art. 293 B du CGI".',
                ],
                [
                    'key' => 'field_pc_legal_rcs',
                    'label' => 'RCS / RM (Ville)',
                    'name' => 'pc_legal_rcs',
                    'type' => 'text',
                ],
                [
                    'key' => 'field_pc_legal_capital',
                    'label' => 'Capital Social',
                    'name' => 'pc_legal_capital',
                    'type' => 'text',
                ],

                // [NOUVEAU] Onglet Banque (RIB)
                [
                    'key' => 'field_tab_pdf_bank',
                    'label' => 'Coordonnées Bancaires',
                    'type' => 'tab',
                ],
                [
                    'key' => 'field_pc_bank_name',
                    'label' => 'Nom de la Banque',
                    'name' => 'pc_bank_name',
                    'type' => 'text',
                ],
                [
                    'key' => 'field_pc_bank_iban',
                    'label' => 'IBAN',
                    'name' => 'pc_bank_iban',
                    'type' => 'text',
                ],
                [
                    'key' => 'field_pc_bank_bic',
                    'label' => 'BIC / SWIFT',
                    'name' => 'pc_bank_bic',
                    'type' => 'text',
                ],

                // Onglet Numérotation
                [
                    'key' => 'field_tab_pdf_numbering',
                    'label' => 'Numérotation',
                    'type' => 'tab',
                ],
                [
                    'key' => 'field_pc_invoice_prefix',
                    'label' => 'Préfixe Facture',
                    'name' => 'pc_invoice_prefix',
                    'type' => 'text',
                    'default_value' => 'FAC-' . date('Y') . '-',
                ],
                [
                    'key' => 'field_pc_invoice_next',
                    'label' => 'Prochain Numéro Facture',
                    'name' => 'pc_invoice_next',
                    'type' => 'number',
                    'default_value' => 1,
                ],
                [
                    'key' => 'field_pc_quote_prefix',
                    'label' => 'Préfixe Devis',
                    'name' => 'pc_quote_prefix',
                    'type' => 'text',
                    'default_value' => 'DEV-' . date('Y') . '-',
                ],
                [
                    'key' => 'field_pc_credit_note_prefix',
                    'label' => 'Préfixe Avoir',
                    'name' => 'pc_credit_note_prefix',
                    'type' => 'text',
                    'default_value' => 'AVOIR-' . date('Y') . '-',
                ],
                [
                    'key' => 'field_pc_credit_note_next',
                    'label' => 'Prochain Numéro Avoir',
                    'name' => 'pc_credit_note_next',
                    'type' => 'number',
                    'default_value' => 1,
                ],

                // Onglet Bibliothèque CGV
                // [NOUVEAU] Onglet Affectation Automatique
                [
                    'key' => 'field_tab_pdf_assignment',
                    'label' => 'Affectation Auto CGV',
                    'type' => 'tab',
                ],
                [
                    'key' => 'field_pc_cgv_default_location',
                    'label' => 'CGV par défaut : Logement',
                    'name' => 'pc_cgv_default_location',
                    'type' => 'select',
                    'ui' => 1,
                    'instructions' => 'Ces CGV seront automatiquement ajoutées aux Factures et Devis de type LOCATION.',
                    'choices' => [], // Sera rempli dynamiquement
                ],
                [
                    'key' => 'field_pc_cgv_default_experience',
                    'label' => 'CGV par défaut : Expérience',
                    'name' => 'pc_cgv_default_experience',
                    'type' => 'select',
                    'ui' => 1,
                    'instructions' => 'Ces CGV seront automatiquement ajoutées aux Factures et Devis de type EXPÉRIENCE.',
                    'choices' => [], // Sera rempli dynamiquement
                ],
                [
                    'key' => 'field_tab_pdf_cgv',
                    'label' => 'Bibliothèque CGV',
                    'type' => 'tab',
                ],
                [
                    'key' => 'field_pc_pdf_cgv_library',
                    'label' => 'Vos Conditions Générales',
                    'name' => 'pc_pdf_cgv_library',
                    'type' => 'repeater',
                    'button_label' => 'Ajouter une version CGV',
                    'layout' => 'row',
                    'sub_fields' => [
                        [
                            'key' => 'field_cgv_title',
                            'label' => 'Nom interne',
                            'name' => 'cgv_title',
                            'type' => 'text',
                        ],
                        [
                            'key' => 'field_cgv_content',
                            'label' => 'Contenu',
                            'name' => 'cgv_content',
                            'type' => 'wysiwyg',
                        ],
                    ],
                ],
            ],
            'location' => [
                // Attention : on cible ici la SOUS-PAGE créée plus haut
                [['param' => 'options_page', 'operator' => '==', 'value' => 'pc-reservation-documents']],
            ],
        ]);
    }
}
