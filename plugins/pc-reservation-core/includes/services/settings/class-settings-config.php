<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCR Settings Config - Déclaration de l'UI et des champs ACF
 * Centralise les pages d'options et les groupes de champs.
 * Pattern Singleton.
 * * @since 2.0.0 (Refactoring)
 */
class PCR_Settings_Config
{
    /**
     * Instance unique de la classe.
     * @var PCR_Settings_Config|null
     */
    private static $instance = null;

    /**
     * Constructeur privé (Singleton).
     */
    private function __construct() {}

    /**
     * Récupère l'instance unique.
     * @return PCR_Settings_Config
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Déclare les pages d'options ACF dans le menu d'administration.
     */
    public function register_options_pages()
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

        // 2. Sous-Page : "Configuration" (Générique avec onglets)
        if (function_exists('acf_add_options_sub_page')) {
            acf_add_options_sub_page([
                'page_title'  => 'Configuration - PC Réservation',
                'menu_title'  => 'Configuration',
                'parent_slug' => 'pc-reservation-settings',
                'menu_slug'   => 'pc-reservation-config',
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
    }

    /**
     * Enregistre les groupes de champs ACF locaux.
     */
    public function register_acf_fields()
    {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        // =================================================================
        // GROUPE 1 : CONFIGURATION GÉNÉRALE AVEC ONGLETS (Page Principale)
        // =================================================================
        acf_add_local_field_group([
            'key' => 'group_pc_config_settings',
            'title' => 'Configuration - PC Réservation',
            'fields' => [
                // Onglet 1: Paiements (Stripe)
                [
                    'key' => 'field_tab_payments',
                    'label' => 'Paiements (Stripe)',
                    'type' => 'tab',
                ],
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

                // Onglet 2: Messagerie
                [
                    'key' => 'field_tab_messaging',
                    'label' => 'Messagerie',
                    'type' => 'tab',
                ],
                [
                    'key' => 'field_pc_default_phone_prefix',
                    'label' => 'Préfixe téléphone par défaut',
                    'name' => 'pc_default_phone_prefix',
                    'type' => 'text',
                    'placeholder' => '+590',
                    'instructions' => 'Préfixe utilisé pour les numéros WhatsApp (ex: +590 pour la Guadeloupe)',
                    'default_value' => '+590',
                ],
                [
                    'key' => 'field_pc_whatsapp_message_template',
                    'label' => 'Template WhatsApp par défaut',
                    'name' => 'pc_whatsapp_template',
                    'type' => 'textarea',
                    'instructions' => 'Template de message WhatsApp utilisé pour le bouton "WhatsApp". Variables disponibles: {prenom_client}, {nom_client}, {numero_resa}',
                    'default_value' => 'Bonjour {prenom_client},\n\nConcernant votre réservation #{numero_resa}, je me permets de vous contacter.\n\nCordialement,\nÉquipe Prestige Caraïbes',
                    'rows' => 4,
                ],
                [
                    'key' => 'field_pc_email_signature',
                    'label' => 'Signature email par défaut',
                    'name' => 'pc_email_signature',
                    'type' => 'wysiwyg',
                    'instructions' => 'Signature ajoutée automatiquement aux emails envoyés depuis le système',
                    'media_upload' => 0,
                    'delay' => 1,
                    'toolbar' => 'basic',
                ],

                // Onglet 3: Connectivité / API
                [
                    'key' => 'field_tab_connectivity',
                    'label' => 'Connectivité / API',
                    'type' => 'tab',
                ],
                [
                    'key' => 'field_pc_api_provider',
                    'label' => 'Fournisseur d\'API Email',
                    'name' => 'pc_api_provider',
                    'type' => 'select',
                    'choices' => [
                        'none' => 'Aucun (désactivé)',
                        'brevo' => 'Brevo (Sendinblue)',
                    ],
                    'default_value' => 'none',
                    'ui' => 1,
                    'instructions' => 'Choisissez le service pour recevoir les emails entrants.',
                ],
                [
                    'key' => 'field_pc_api_key',
                    'label' => 'Clé API',
                    'name' => 'pc_api_key',
                    'type' => 'password',
                    'conditional_logic' => [[['field' => 'field_pc_api_provider', 'operator' => '!=', 'value' => 'none']]],
                    'instructions' => 'Clé API de votre fournisseur de service.',
                ],
                [
                    'key' => 'field_pc_webhook_secret',
                    'label' => 'Secret Webhook',
                    'name' => 'pc_webhook_secret',
                    'type' => 'text',
                    'conditional_logic' => [[['field' => 'field_pc_api_provider', 'operator' => '!=', 'value' => 'none']]],
                    'instructions' => 'Token de sécurité pour vérifier l\'authenticité des webhooks entrants.',
                    'placeholder' => 'Généré automatiquement si vide',
                ],
                [
                    'key' => 'field_pc_inbound_email_enabled',
                    'label' => 'Emails entrants activés',
                    'name' => 'pc_inbound_email_enabled',
                    'type' => 'true_false',
                    'default_value' => 0,
                    'conditional_logic' => [[['field' => 'field_pc_api_provider', 'operator' => '!=', 'value' => 'none']]],
                    'instructions' => 'Autoriser la réception d\'emails dans les conversations de réservation.',
                    'ui' => 1,
                ],
                [
                    'key' => 'field_pc_webhook_url_display',
                    'label' => 'URL du Webhook',
                    'name' => 'pc_webhook_url_display',
                    'type' => 'message',
                    'conditional_logic' => [[['field' => 'field_pc_api_provider', 'operator' => '!=', 'value' => 'none']]],
                    'message' => '', // Sera rempli dynamiquement
                    'new_lines' => '',
                ],

                // Section Simulateur de Webhook
                [
                    'key' => 'field_pc_webhook_simulator_separator',
                    'label' => '🛠️ Simulateur de Webhook (Test Local)',
                    'name' => 'pc_webhook_simulator_separator',
                    'type' => 'message',
                    'message' => '<div style="background: #f8fafc; border: 2px dashed #e2e8f0; border-radius: 8px; padding: 16px; margin: 12px 0;">
                        <h4 style="color: #475569; margin: 0 0 8px;">🧪 Testez la réception de messages</h4>
                        <p style="color: #64748b; margin: 0; font-size: 14px;">Simulez la réception d\'un webhook (Email Brevo ou WhatsApp) sans avoir besoin de configurer DNS ou tunnels. Collez un payload JSON ci-dessous pour tester l\'injection dans une conversation existante.</p>
                    </div>',
                    'new_lines' => '',
                ],
                [
                    'key' => 'field_pc_webhook_simulation_payload',
                    'label' => 'Payload JSON de simulation',
                    'name' => 'pc_webhook_simulation_payload',
                    'type' => 'textarea',
                    'rows' => 12,
                    'instructions' => 'Collez ici le JSON à simuler (exemple pré-rempli). Modifiez le numéro de réservation selon vos besoins.',
                    'default_value' => '{
  "event": "inbound_parsing_event",
  "subject": "Réponse au sujet de la réservation [Resa #115]",
  "items": [
    {
      "uuid": "12345",
      "SenderAddress": "client@gmail.com",
      "Subject": "Re: Votre séjour [Resa #115]",
      "RawHtmlBody": "Bonjour, merci pour ces infos ! J\'arrive à 14h."
    }
  ]
}',
                    'placeholder' => 'Collez votre payload JSON ici...',
                ],
                [
                    'key' => 'field_pc_webhook_simulation_button',
                    'label' => '',
                    'name' => 'pc_webhook_simulation_button',
                    'type' => 'message',
                    'message' => '<div style="margin-top: 10px;">
                        <button type="button" id="pc_trigger_simulation" class="button button-primary button-large" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 6px; padding: 12px 24px; font-weight: 600; text-shadow: none; box-shadow: 0 4px 14px 0 rgba(102, 126, 234, 0.4);">
                            🚀 Lancer la simulation (AJAX)
                        </button>
                        <div id="pc_simulation_results" style="margin-top: 15px; padding: 10px; border-radius: 5px; display: none;"></div>
                        
                        <p style="margin: 8px 0 0; font-size: 12px; color: #64748b;">
                            💡 <strong>Astuce :</strong> Pour WhatsApp, ajoutez <code>"type": "whatsapp", "reservation_id": 123</code> dans votre JSON.
                        </p>
                    </div>',
                    'new_lines' => '',
                ],

                // AJOUT : Onglet Divers / Identité
                [
                    'key' => 'field_tab_divers',
                    'label' => 'Divers & Identité',
                    'type' => 'tab',
                ],
                [
                    'key' => 'field_pc_general_logo',
                    'label' => 'Logo Principal du site',
                    'name' => 'pc_general_logo',
                    'type' => 'image',
                    'return_format' => 'url',
                    'preview_size' => 'medium',
                    'library' => 'all',
                    'instructions' => 'Ce logo sera utilisé dans les emails transactionnels et l\'interface.',
                ],
                [
                    'key' => 'field_tab_webapp',
                    'label' => 'Web App / Dashboard',
                    'type' => 'tab',
                ],
                [
                    'key' => 'field_pc_dashboard_slug',
                    'label' => 'URL de l\'Espace Propriétaire',
                    'name' => 'pc_dashboard_slug',
                    'type' => 'text',
                    'default_value' => 'espace-proprietaire',
                    'instructions' => 'L\'adresse pour accéder au dashboard (ex: espace-proprietaire). Sauvegardez les permaliens WP après modification.',
                    'prepend' => home_url('/'),
                ],
                [
                    'key' => 'field_pc_dashboard_menu_item',
                    'label' => 'Ajouter au menu',
                    'name' => 'pc_dashboard_menu_item',
                    'type' => 'true_false',
                    'ui' => 1,
                    'instructions' => 'Ajoute automatiquement un lien "Espace Propriétaire" dans le menu principal.',
                ],
                [
                    'key' => 'field_pc_dashboard_logo',
                    'label' => 'Logo du Dashboard (Fond Blanc)',
                    'name' => 'pc_dashboard_logo',
                    'type' => 'image',
                    'return_format' => 'url',
                    'preview_size' => 'medium', // Une vignette suffit
                    'instructions' => 'Choisissez un logo adapté à un fond blanc (ex: version couleur ou sombre). Si vide, le logo principal sera utilisé.',
                ],
            ],
            'location' => [
                [['param' => 'options_page', 'operator' => '==', 'value' => 'pc-reservation-config']],
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
                [
                    'key' => 'field_pc_legal_email',
                    'label' => 'Email de contact (Facturation)',
                    'name' => 'pc_legal_email',
                    'type' => 'email',
                ],
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

                // Onglet Banque (RIB)
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

                // Onglet CGV
                [
                    'key' => 'field_tab_pdf_cgv',
                    'label' => 'Conditions Générales',
                    'type' => 'tab',
                ],
                [
                    'key' => 'field_cgv_location',
                    'label' => 'CGV - Location/Logement',
                    'name' => 'cgv_location',
                    'type' => 'wysiwyg',
                    'instructions' => 'Ces CGV seront automatiquement ajoutées aux documents de type Location/Logement.',
                    'media_upload' => 0,
                    'delay' => 1,
                ],
                [
                    'key' => 'field_cgv_experience',
                    'label' => 'CGV - Expériences/Activités',
                    'name' => 'cgv_experience',
                    'type' => 'wysiwyg',
                    'instructions' => 'Ces CGV seront automatiquement ajoutées aux documents de type Expérience/Activité.',
                    'media_upload' => 0,
                    'delay' => 1,
                ],
                [
                    'key' => 'field_cgv_sejour',
                    'label' => 'CGV - Organisation Séjour',
                    'name' => 'cgv_sejour',
                    'type' => 'wysiwyg',
                    'instructions' => 'CGV pour les types mixtes ou services d\'organisation de séjour.',
                    'media_upload' => 0,
                    'delay' => 1,
                ],
                [
                    'key' => 'field_cgv_custom',
                    'label' => 'CGV - Personnalisé',
                    'name' => 'cgv_custom',
                    'type' => 'wysiwyg',
                    'instructions' => 'CGV personnalisées pour les cas spéciaux.',
                    'media_upload' => 0,
                    'delay' => 1,
                ],
            ],
            'location' => [
                // Attention : on cible ici la SOUS-PAGE créée plus haut
                [['param' => 'options_page', 'operator' => '==', 'value' => 'pc-reservation-documents']],
            ],
        ]);
    }
}
