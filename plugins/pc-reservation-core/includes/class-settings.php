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

        // Enregistrement des champs ACF
        add_action('acf/init', [__CLASS__, 'register_fields']);

        // ✨ NOUVEAU : Hooks pour le simulateur AJAX
        add_action('wp_ajax_pc_simulate_webhook', [__CLASS__, 'ajax_handle_simulation']);
        add_action('admin_footer', [__CLASS__, 'print_admin_scripts']);
    }

    public static function register_fields()
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

                // ✨ NOUVEAU : Section Simulateur de Webhook
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

                // **NOUVEAU SYSTÈME CGV SIMPLIFIÉ**
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

    /**
     * ✨ NOUVEAU : Gère la simulation de webhook depuis la page de configuration
     */
    public static function handle_webhook_simulation()
    {
        // 1. Vérifie si le bouton a été cliqué
        if (!isset($_POST['pc_simulate_webhook']) || !isset($_POST['pc_webhook_simulation_payload'])) {
            return;
        }

        // 2. Vérification permissions (On simplifie le nonce pour le test)
        if (!current_user_can('manage_options')) {
            wp_die('Accès refusé');
        }

        // 3. Récupération Payload
        $payload_json = trim(stripslashes($_POST['pc_webhook_simulation_payload']));
        $payload = json_decode($payload_json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_die('❌ <strong>Erreur JSON :</strong> ' . json_last_error_msg());
        }

        // 4. Exécution
        try {
            $result = self::process_webhook_simulation($payload);

            // 5. AFFICHAGE DU RÉSULTAT ET ARRÊT IMMÉDIAT (Anti-Redirection)
            if ($result['success']) {
                $html = '<div style="font-family:sans-serif; padding:20px; background:#dcfce7; border:1px solid #22c55e; color:#14532d; max-width:600px; margin:50px auto; border-radius:8px;">';
                $html .= '<h2>✅ Simulation Réussie !</h2>';
                $html .= '<p>' . esc_html($result['message']) . '</p>';
                $html .= '<ul>';
                if (isset($result['reservation_id'])) $html .= '<li><strong>Réservation :</strong> #' . intval($result['reservation_id']) . '</li>';
                $html .= '</ul>';
                $html .= '<a href="javascript:history.back()" style="display:inline-block; margin-top:10px; padding:10px 20px; background:#166534; color:white; text-decoration:none; border-radius:4px;">⬅️ Retour</a>';
                $html .= '</div>';
                wp_die($html, 'Simulation OK', ['response' => 200]);
            } else {
                $html = '<div style="font-family:sans-serif; padding:20px; background:#fee2e2; border:1px solid #ef4444; color:#7f1d1d; max-width:600px; margin:50px auto; border-radius:8px;">';
                $html .= '<h2>❌ Échec de la Simulation</h2>';
                $html .= '<p><strong>Erreur :</strong> ' . esc_html($result['message']) . '</p>';
                $html .= '<a href="javascript:history.back()" style="display:inline-block; margin-top:10px; padding:10px 20px; background:#991b1b; color:white; text-decoration:none; border-radius:4px;">⬅️ Retour & Corriger</a>';
                $html .= '</div>';
                wp_die($html, 'Simulation Erreur', ['response' => 200]);
            }
        } catch (Exception $e) {
            wp_die('❌ <strong>Exception :</strong> ' . $e->getMessage());
        }
    }

    /**
     * ✨ Logique de traitement de la simulation webhook
     * Reproduit la même logique que PCR_Rest_Webhook mais localement
     * 
     * @param array $payload Payload JSON décodé
     * @return array Résultat de la simulation
     */
    private static function process_webhook_simulation($payload)
    {
        // Détecter le type de webhook selon la structure
        $message_type = self::detect_webhook_type($payload);

        switch ($message_type) {
            case 'brevo_email':
                return self::simulate_brevo_email($payload);

            case 'whatsapp':
                return self::simulate_whatsapp($payload);

            default:
                return [
                    'success' => false,
                    'message' => 'Format de webhook non reconnu. Supportés : Brevo Email, WhatsApp.'
                ];
        }
    }

    /**
     * Détecte le type de webhook selon sa structure
     */
    private static function detect_webhook_type($payload)
    {
        // Format Brevo Email (Inbound Parse)
        if (
            isset($payload['items']) && is_array($payload['items']) &&
            isset($payload['items'][0]['SenderAddress']) &&
            isset($payload['items'][0]['Subject'])
        ) {
            return 'brevo_email';
        }

        // Format Brevo Email (structure alternative)
        if (isset($payload['subject']) && isset($payload['from']['email'])) {
            return 'brevo_email_alt';
        }

        // Format WhatsApp
        if (
            isset($payload['from']) && isset($payload['text']) &&
            (isset($payload['type']) && $payload['type'] === 'whatsapp')
        ) {
            return 'whatsapp';
        }

        return 'unknown';
    }

    /**
     * Simule la réception d'un email Brevo
     */
    private static function simulate_brevo_email($payload)
    {
        // Structure standard Brevo Inbound Parse
        $email_data = $payload['items'][0] ?? [];

        $sender_email = $email_data['SenderAddress'] ?? '';
        $subject = $email_data['Subject'] ?? $payload['subject'] ?? '';
        $content = $email_data['RawHtmlBody'] ?? $email_data['RawTextBody'] ?? '';

        if (empty($sender_email) || empty($subject) || empty($content)) {
            return [
                'success' => false,
                'message' => 'Données email manquantes (SenderAddress, Subject, RawHtmlBody/RawTextBody).'
            ];
        }

        // Extraction de l'ID de réservation depuis le sujet : pattern #123 ou [Resa #123]
        if (!preg_match('/(?:#|Resa #)(\d+)/', $subject, $matches)) {
            return [
                'success' => false,
                'message' => 'Aucun ID de réservation trouvé dans le sujet. Format attendu : #123 ou [Resa #123]',
                'subject' => $subject
            ];
        }

        $reservation_id = (int) $matches[1];

        // Vérification que la réservation existe
        if (!class_exists('PCR_Reservation')) {
            return [
                'success' => false,
                'message' => 'Classe PCR_Reservation non disponible. Vérifiez que le plugin est correctement initialisé.'
            ];
        }

        $reservation = PCR_Reservation::get_by_id($reservation_id);
        if (!$reservation) {
            return [
                'success' => false,
                'message' => "Réservation #{$reservation_id} introuvable."
            ];
        }

        // Injection via PCR_Messaging
        if (!class_exists('PCR_Messaging')) {
            return [
                'success' => false,
                'message' => 'Classe PCR_Messaging non disponible.'
            ];
        }

        $metadata = [
            'sender_email' => $sender_email,
            'sender_name' => $email_data['SenderName'] ?? 'Client',
            'original_subject' => $subject,
            'external_id' => $email_data['uuid'] ?? null,
            'webhook_source' => 'brevo_simulation',
            'simulation' => true,
            'simulated_at' => current_time('mysql'),
            'admin_user_id' => get_current_user_id(),
        ];

        $injection_result = PCR_Messaging::receive_external_message(
            $reservation_id,
            $content,
            'email',
            $metadata
        );

        if ($injection_result['success']) {
            return [
                'success' => true,
                'message' => 'Message simulé injecté avec succès dans la conversation.',
                'reservation_id' => $reservation_id,
                'message_id' => $injection_result['message_id'] ?? null,
                'sender_email' => $sender_email
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Échec injection : ' . $injection_result['message']
            ];
        }
    }

    /**
     * Simule la réception d'un message WhatsApp
     */
    private static function simulate_whatsapp($payload)
    {
        $from_phone = $payload['from'] ?? '';
        $message_text = $payload['text'] ?? '';

        if (empty($from_phone) || empty($message_text)) {
            return [
                'success' => false,
                'message' => 'Données WhatsApp manquantes (from, text).'
            ];
        }

        // Pour la simulation, nous devons demander l'ID de réservation
        // car on n'a pas de recherche automatique par téléphone implémentée ici
        if (isset($payload['reservation_id'])) {
            $reservation_id = (int) $payload['reservation_id'];
        } else {
            return [
                'success' => false,
                'message' => 'Pour WhatsApp, ajoutez "reservation_id": 123 dans votre JSON de simulation.'
            ];
        }

        // Vérification que la réservation existe
        if (!class_exists('PCR_Reservation')) {
            return [
                'success' => false,
                'message' => 'Classe PCR_Reservation non disponible.'
            ];
        }

        $reservation = PCR_Reservation::get_by_id($reservation_id);
        if (!$reservation) {
            return [
                'success' => false,
                'message' => "Réservation #{$reservation_id} introuvable."
            ];
        }

        // Injection via PCR_Messaging
        if (!class_exists('PCR_Messaging')) {
            return [
                'success' => false,
                'message' => 'Classe PCR_Messaging non disponible.'
            ];
        }

        $metadata = [
            'sender_phone' => $from_phone,
            'external_id' => $payload['message_id'] ?? null,
            'webhook_source' => 'whatsapp_simulation',
            'simulation' => true,
            'simulated_at' => current_time('mysql'),
            'admin_user_id' => get_current_user_id(),
        ];

        $injection_result = PCR_Messaging::receive_external_message(
            $reservation_id,
            $message_text,
            'whatsapp',
            $metadata
        );

        if ($injection_result['success']) {
            return [
                'success' => true,
                'message' => 'Message WhatsApp simulé injecté avec succès.',
                'reservation_id' => $reservation_id,
                'message_id' => $injection_result['message_id'] ?? null,
                'sender_phone' => $from_phone
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Échec injection WhatsApp : ' . $injection_result['message']
            ];
        }
    }

    /**
     * Injecte le Javascript pour le bouton de simulation
     */
    public static function print_admin_scripts()
    {
        // On s'assure d'être sur la page de config
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'pc-reservation-config') === false) {
            return;
        }
?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#pc_trigger_simulation').on('click', function(e) {
                    e.preventDefault();

                    var $btn = $(this);
                    var $resultBox = $('#pc_simulation_results');

                    // 1. Récupérer le JSON depuis le champ ACF (par son nom ou clé)
                    // ACF génère le name comme acf[key]
                    var jsonPayload = $('textarea[name="acf[field_pc_webhook_simulation_payload]"]').val();

                    if (!jsonPayload) {
                        alert('Le champ JSON est vide !');
                        return;
                    }

                    // UI Loading
                    $btn.prop('disabled', true).text('⏳ Traitement...');
                    $resultBox.hide().removeClass().text('');

                    // 2. Appel AJAX
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'pc_simulate_webhook',
                            payload: jsonPayload,
                            security: '<?php echo wp_create_nonce("pc_sim_nonce"); ?>'
                        },
                        success: function(response) {
                            $resultBox.show();
                            if (response.success) {
                                $resultBox.css({
                                    'background': '#dcfce7',
                                    'color': '#166534',
                                    'border': '1px solid #22c55e'
                                });
                                $resultBox.html('<strong>✅ Succès :</strong> ' + response.data.message);
                            } else {
                                $resultBox.css({
                                    'background': '#fee2e2',
                                    'color': '#991b1b',
                                    'border': '1px solid #ef4444'
                                });
                                $resultBox.html('<strong>❌ Erreur :</strong> ' + (response.data.message || 'Erreur inconnue'));
                            }
                        },
                        error: function() {
                            $resultBox.show().css({
                                'background': '#fee2e2',
                                'color': '#991b1b'
                            });
                            $resultBox.text('❌ Erreur serveur (500)');
                        },
                        complete: function() {
                            $btn.prop('disabled', false).text('🚀 Lancer la simulation (AJAX)');
                        }
                    });
                });
            });
        </script>
<?php
    }

    /**
     * Traite la simulation via AJAX
     */
    public static function ajax_handle_simulation()
    {
        check_ajax_referer('pc_sim_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Accès refusé']);
        }

        $payload_json = isset($_POST['payload']) ? stripslashes($_POST['payload']) : '';
        $payload = json_decode($payload_json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(['message' => 'JSON Invalide : ' . json_last_error_msg()]);
        }

        try {
            // On réutilise ta logique existante process_webhook_simulation
            $result = self::process_webhook_simulation($payload);

            if ($result['success']) {
                $msg = $result['message'];
                if (isset($result['reservation_id'])) $msg .= " (Résa #{$result['reservation_id']})";
                wp_send_json_success(['message' => $msg]);
            } else {
                wp_send_json_error(['message' => $result['message']]);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Exception : ' . $e->getMessage()]);
        }
    }
}
