<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gère le système de Messagerie (Templates, PDF, Envoi).
 */
class PCR_Messaging
{
    public static function init()
    {
        // 1. CPT & Taxonomies
        add_action('init', [__CLASS__, 'register_content_types']);

        // 2. Page d'options PDF (Design global)
        add_action('init', [__CLASS__, 'register_pdf_options_page']);

        // 3. Champs ACF (Logique conditionnelle & Design)
        add_action('acf/init', [__CLASS__, 'register_template_fields']);

        // 4. Charger dynamiquement les choix de CGV
        add_filter('acf/load_field/name=pc_pdf_append_cgv', [__CLASS__, 'load_cgv_choices']);

        // 5. Aide-mémoire Variables & Aperçu (Metabox)
        add_action('add_meta_boxes', [__CLASS__, 'add_variable_help_box']);

        // 6. Gestion de l'aperçu PDF
        add_action('init', [__CLASS__, 'handle_pdf_preview_request']);
    }

    /**
     * Enregistre les CPTs et la Taxonomie
     */
    public static function register_content_types()
    {
        // A. Modèles de Messages (Emails)
        register_post_type('pc_template', [
            'labels' => [
                'name' => 'Scénarios Emails',
                'singular_name' => 'Scénario',
                'menu_name' => 'PC Messages',
                'add_new_item' => 'Nouveau Scénario',
                'edit_item' => 'Modifier le Scénario',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_position' => 52,
            'menu_icon' => 'dashicons-email-alt',
            'supports' => ['title', 'editor'],
        ]);

        // B. Taxonomie : Catégorie (Logement / Expérience)
        register_taxonomy('pc_message_cat', ['pc_template'], [
            'labels' => [
                'name' => 'Catégorie',
                'singular_name' => 'Catégorie',
            ],
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
        ]);

        // C. Modèles de PDF (Contenu Spécifique)
        register_post_type('pc_pdf_template', [
            'labels' => [
                'name' => 'Modèles PDF',
                'singular_name' => 'Modèle PDF',
                'menu_name' => 'Modèles PDF',
                'add_new_item' => 'Nouveau Modèle PDF',
                'edit_item' => 'Modifier le Modèle PDF',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=pc_template', // Sous-menu de PC Messages
            'supports' => ['title', 'editor'], // Editor = Corps du PDF
        ]);
    }

    /**
     * Ajoute la page d'options pour le Design Global
     */
    public static function register_pdf_options_page()
    {
        if (function_exists('acf_add_options_sub_page')) {
            acf_add_options_sub_page([
                'page_title'  => 'Design & Configuration PDF',
                'menu_title'  => 'Réglages PDF',
                'parent_slug' => 'edit.php?post_type=pc_template',
            ]);
        }
    }

    /**
     * Définit tous les champs ACF (Scénarios, Design Global, Modèle PDF)
     */
    public static function register_template_fields()
    {
        if (!function_exists('acf_add_local_field_group')) return;

        // ... (Le groupe 'group_pc_scenario_config' reste inchangé, ne le touche pas) ...
        // SI TU AS BESOIN DE LE GARDER, JE LE REMETS CI-DESSOUS POUR ETRE SUR :
        acf_add_local_field_group([
            'key' => 'group_pc_scenario_config',
            'title' => 'Configuration du Scénario',
            'fields' => [
                [
                    'key' => 'field_pc_msg_type',
                    'label' => 'Type de message',
                    'name' => 'pc_msg_type',
                    'type' => 'select',
                    'choices' => [
                        'libre'     => 'Message personnalisé (libre)',
                        'immediat'  => 'Message immédiat après action sur le front',
                        'programme' => 'Message programmé suite à réservation',
                    ],
                    'default_value' => 'libre',
                ],
                [
                    'key' => 'field_pc_trigger_action',
                    'label' => 'Déclencheur (Action sur le site)',
                    'name' => 'pc_trigger_action',
                    'type' => 'select',
                    'choices' => [
                        'resa_directe'  => 'Nouvelle Réservation Directe (Confirmée/Payée)',
                        'demande_devis' => 'Nouvelle Demande de Réservation (En attente)',
                        'paiement_recu' => 'Paiement Reçu (Acompte ou Solde)',
                    ],
                    'conditional_logic' => [
                        [['field' => 'field_pc_msg_type', 'operator' => '==', 'value' => 'immediat']]
                    ],
                ],
                [
                    'key' => 'field_pc_trigger_relative',
                    'label' => 'Moment de l\'envoi',
                    'name' => 'pc_trigger_relative',
                    'type' => 'select',
                    'choices' => [
                        'before_checkin' => 'Avant l\'arrivée du client',
                        'after_checkin'  => 'Après l\'arrivée du client',
                        'before_checkout' => 'Avant le départ du client',
                        'after_checkout' => 'Après le départ du client',
                    ],
                    'conditional_logic' => [
                        [['field' => 'field_pc_msg_type', 'operator' => '==', 'value' => 'programme']]
                    ],
                ],
                [
                    'key' => 'field_pc_trigger_days',
                    'label' => 'Nombre de jours',
                    'name' => 'pc_trigger_days',
                    'type' => 'number',
                    'default_value' => 1,
                    'append' => 'jours',
                    'conditional_logic' => [
                        [['field' => 'field_pc_msg_type', 'operator' => '==', 'value' => 'programme']]
                    ],
                ],
                [
                    'key' => 'field_pc_msg_subject',
                    'label' => 'Sujet de l\'email',
                    'name' => 'pc_msg_subject',
                    'type' => 'text',
                    'required' => 1,
                ],
                [
                    'key' => 'field_pc_msg_attachment',
                    'label' => 'Joindre un PDF',
                    'name' => 'pc_msg_attachment',
                    'type' => 'post_object',
                    'post_type' => ['pc_pdf_template'],
                    'return_format' => 'id',
                    'allow_null' => 1,
                    'ui' => 1,
                ],
            ],
            'location' => [
                [['param' => 'post_type', 'operator' => '==', 'value' => 'pc_template']]
            ],
            'position' => 'acf_after_title',
        ]);

        // =================================================================
        // 2. RÉGLAGES GLOBAUX PDF (Page d'options) - MISE À JOUR LÉGALE
        // =================================================================
        acf_add_local_field_group([
            'key' => 'group_pc_pdf_global_settings',
            'title' => 'Design & Mentions Légales',
            'fields' => [
                // Onglet Identité
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
                    'return_format' => 'array',
                    'preview_size' => 'medium',
                ],
                [
                    'key' => 'field_pc_pdf_primary_color',
                    'label' => 'Couleur Principale',
                    'name' => 'pc_pdf_primary_color',
                    'type' => 'color_picker',
                    'default_value' => '#4338ca',
                ],

                // Onglet Mentions Légales (NOUVEAU)
                [
                    'key' => 'field_tab_pdf_legal',
                    'label' => 'Mentions Légales (Obligatoire)',
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
                    'label' => 'Siège Social',
                    'name' => 'pc_legal_address',
                    'type' => 'textarea',
                    'rows' => 2,
                ],
                [
                    'key' => 'field_pc_legal_siret',
                    'label' => 'SIRET / SIREN',
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
                    'placeholder' => 'RCS Pointe-à-Pitre',
                ],
                [
                    'key' => 'field_pc_legal_capital',
                    'label' => 'Capital Social',
                    'name' => 'pc_legal_capital',
                    'type' => 'text',
                    'placeholder' => '1 000 €',
                ],

                // Onglet Numérotation (NOUVEAU POUR FACTURES)
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
                    'instructions' => 'Exemple : FAC-2025-',
                ],
                [
                    'key' => 'field_pc_invoice_next',
                    'label' => 'Prochain Numéro',
                    'name' => 'pc_invoice_next',
                    'type' => 'number',
                    'default_value' => 1,
                    'instructions' => 'Le compteur s\'incrémentera automatiquement.',
                ],
                [
                    'key' => 'field_pc_quote_prefix',
                    'label' => 'Préfixe Devis',
                    'name' => 'pc_quote_prefix',
                    'type' => 'text',
                    'default_value' => 'DEV-' . date('Y') . '-',
                ],

                // Onglet Bibliothèque CGV
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
                [['param' => 'options_page', 'operator' => '==', 'value' => 'acf-options-reglages-pdf']]
            ],
        ]);

        // =================================================================
        // 3. CONFIGURATION DU MODÈLE PDF (CPT) - AJOUT CONTRAT
        // =================================================================
        acf_add_local_field_group([
            'key' => 'group_pc_pdf_template_config',
            'title' => 'Options du Document',
            'fields' => [
                [
                    'key' => 'field_pc_pdf_doc_type',
                    'label' => 'Type de document',
                    'name' => 'pc_pdf_doc_type',
                    'type' => 'select',
                    'choices' => [
                        'devis'   => 'Devis',
                        'facture' => 'Facture (Génère numéro)',
                        'contrat' => 'Contrat de Location', // AJOUTÉ ICI
                        'voucher' => 'Voucher / Bon d\'échange',
                        'libre'   => 'Document Libre',
                    ],
                ],
                [
                    'key' => 'field_pc_pdf_append_cgv',
                    'label' => 'Inclure des CGV ?',
                    'name' => 'pc_pdf_append_cgv',
                    'type' => 'select',
                    'ui' => 1,
                    'allow_null' => 1,
                    'placeholder' => 'Ne pas inclure de CGV',
                    'choices' => [],
                ],
                [
                    'key' => 'field_pc_pdf_preview_btn',
                    'label' => 'Aperçu',
                    'name' => 'pc_pdf_preview_btn',
                    'type' => 'message',
                    'message' => 'Sauvegardez pour voir l\'aperçu.',
                ],
            ],
            'location' => [
                [['param' => 'post_type', 'operator' => '==', 'value' => 'pc_pdf_template']]
            ],
            'position' => 'side',
        ]);
    }

    /**
     * Remplit dynamiquement le selecteur "Inclure CGV" depuis la bibliothèque globale
     */
    public static function load_cgv_choices($field)
    {
        $field['choices'] = [];

        // Récupérer les CGV depuis la page d'options
        if (have_rows('pc_pdf_cgv_library', 'option')) {
            while (have_rows('pc_pdf_cgv_library', 'option')) {
                the_row();
                $title = get_sub_field('cgv_title');
                if ($title) {
                    $field['choices'][$title] = $title;
                }
            }
        }
        return $field;
    }

    /**
     * Ajoute le bouton "Aperçu" et l'aide-mémoire dans les metabox
     */
    public static function add_variable_help_box()
    {
        // 1. Metabox Variables
        $screens = ['pc_template', 'pc_pdf_template'];
        foreach ($screens as $screen) {
            add_meta_box(
                'pc_variables_help',
                'Variables Disponibles',
                [__CLASS__, 'render_variable_help_box'],
                $screen,
                'side',
                'high'
            );
        }

        // 2. Bouton Aperçu (Uniquement pour PDF)
        global $post;
        if ($post && $post->post_type === 'pc_pdf_template') {
            add_meta_box(
                'pc_pdf_preview_box',
                'Prévisualisation',
                [__CLASS__, 'render_preview_box'],
                'pc_pdf_template',
                'side',
                'high'
            );
        }
    }

    public static function render_preview_box($post)
    {
        if ($post->post_status === 'auto-draft') {
            echo '<p><em>Veuillez sauvegarder le brouillon pour générer un aperçu.</em></p>';
        } else {
            $url = home_url('/?pc_action=preview_pdf&id=' . $post->ID);
            echo '<a href="' . esc_url($url) . '" target="_blank" class="button button-primary button-large" style="width:100%;text-align:center;">👁️ Prévisualiser le PDF</a>';
            echo '<p style="margin-top:10px;font-size:0.9em;color:#666;">Ouvre un nouvel onglet avec les données factices.</p>';
        }
    }

    public static function render_variable_help_box()
    {
?>
        <div style="font-size:0.9em; color:#555;">
            <p><strong>Données Client & Résa :</strong></p>
            <ul style="list-style:square; padding-left:20px; margin:0 0 10px;">
                <li><code>{prenom_client}</code></li>
                <li><code>{nom_client}</code></li>
                <li><code>{adresse_client}</code></li>
                <li><code>{date_arrivee}</code> / <code>{date_depart}</code></li>
                <li><code>{duree_sejour}</code></li>
            </ul>

            <p><strong>Données Financières :</strong></p>
            <ul style="list-style:square; padding-left:20px; margin:0 0 10px;">
                <li><code>[tableau_financier]</code> (Le tableau complet HT/TTC)</li>
                <li><code>{montant_total}</code></li>
                <li><code>{acompte_paye}</code></li>
                <li><code>{solde_restant}</code></li>
            </ul>

            <p><strong>Mentions Légales (Auto) :</strong></p>
            <ul style="list-style:square; padding-left:20px; margin:0;">
                <li><code>{mon_entreprise}</code> (Nom)</li>
                <li><code>{mon_adresse}</code></li>
                <li><code>{mon_siret}</code></li>
                <li><code>{mon_rcs}</code></li>
                <li><code>{ma_tva}</code></li>
                <li><code>{num_facture}</code> (Généré si type = Facture)</li>
            </ul>
        </div>
<?php
    }

    /**
     * Gère la requête d'aperçu PDF (Placeholder pour l'instant)
     */
    public static function handle_pdf_preview_request()
    {
        if (isset($_GET['pc_action']) && $_GET['pc_action'] === 'preview_pdf' && isset($_GET['id'])) {
            $pdf_id = (int)$_GET['id'];

            // Vérification simple des droits
            if (!current_user_can('edit_posts')) {
                wp_die('Accès refusé');
            }

            // ICI viendra plus tard la génération réelle avec TCPDF/Dompdf
            $content = get_post_field('post_content', $pdf_id);
            // Données factices pour la preview
            $fake_data = [
                '{prenom_client}' => 'Jean',
                '{nom_client}'    => 'Dupont',
                '{logement}'      => 'Villa Paradis',
                '{montant_total}' => '1 500,00 €',
            ];
            $html = strtr($content, $fake_data);

            echo '<div style="border:1px solid #ccc; padding:40px; max-width:800px; margin:20px auto; font-family:sans-serif;">';
            echo '<h1 style="color:red;text-align:center;">MODE APERÇU (HTML BRUT)</h1>';
            echo '<p style="text-align:center;">Le moteur PDF sera installé à la prochaine étape.</p>';
            echo '<hr>';
            echo $html;
            echo '</div>';
            exit;
        }
    }

    /**
     * MOTEUR D'ENVOI (Compatible Template OU Message Libre)
     */
    public static function send_message($template_identifier, $reservation_id, $force_send = false, $message_type = 'automatique', $custom_args = [])
    {
        $reservation_id = (int) $reservation_id;
        if (!$reservation_id) return ['success' => false, 'message' => 'ID réservation manquant.'];

        $subject = '';
        $body    = '';
        $template_code = '';
        $attachment_pdf_id = 0;

        // 1. DÉTERMINER LE CONTENU

        // CAS A : Message Libre (Personnalisé)
        if ($template_identifier === 'custom' || $template_identifier === 0) {
            if (empty($custom_args['sujet']) || empty($custom_args['corps'])) {
                return ['success' => false, 'message' => 'Sujet ou message manquant pour l\'envoi manuel.'];
            }
            $subject = sanitize_text_field($custom_args['sujet']);
            $body    = wp_kses_post($custom_args['corps']); // On garde le HTML basique
            $template_code = 'manuel_custom';
        }
        // CAS B : Utilisation d'un Modèle (Post ID)
        else {
            $template_post = null;
            if (is_numeric($template_identifier)) {
                $template_post = get_post($template_identifier);
            } else {
                // Recherche par ancien trigger si besoin
                $posts = get_posts([
                    'post_type' => 'pc_template',
                    'meta_key' => 'pc_msg_trigger',
                    'meta_value' => $template_identifier,
                    'numberposts' => 1
                ]);
                if (!empty($posts)) $template_post = $posts[0];
            }

            if (!$template_post) {
                return ['success' => false, 'message' => "Modèle introuvable ($template_identifier)."];
            }

            // Récupération des champs ACF ou valeurs par défaut
            $subject_raw = get_field('pc_msg_subject', $template_post->ID) ?: $template_post->post_title;
            $body_raw    = $template_post->post_content;
            $template_code = $template_post->post_name;

            // Récupération de l'ID du PDF joint (s'il y en a un)
            $attachment_pdf_id = (int) get_field('pc_msg_attachment', $template_post->ID);

            $subject = $subject_raw;
            $body    = $body_raw;
        }

        // 2. RÉCUPÉRATION DONNÉES RÉSERVATION
        if (!class_exists('PCR_Reservation')) return ['success' => false, 'message' => 'Core manquant.'];
        $resa = PCR_Reservation::get_by_id($reservation_id);
        if (!$resa) return ['success' => false, 'message' => 'Réservation introuvable.'];

        // 3. PRÉPARATION DES VARIABLES
        $item_title = get_the_title($resa->item_id);
        $paid_amount = self::get_paid_amount($reservation_id);
        $solde = (float)($resa->montant_total ?? 0) - $paid_amount;

        $vars = [
            '{id}'              => $resa->id,
            '{prenom_client}'   => ucfirst($resa->prenom),
            '{nom_client}'      => strtoupper($resa->nom),
            '{email_client}'    => $resa->email,
            '{telephone_client}' => $resa->telephone,
            '{logement}'        => $item_title,
            '{date_arrivee}'    => date_i18n('d/m/Y', strtotime($resa->date_arrivee)),
            '{date_depart}'     => date_i18n('d/m/Y', strtotime($resa->date_depart)),
            '{heure_arrivee}'   => '16:00', // À dynamiser plus tard
            '{heure_depart}'    => '10:00',
            '{montant_total}'   => number_format((float)$resa->montant_total, 2, ',', ' ') . ' €',
            '{acompte_paye}'    => number_format($paid_amount, 2, ',', ' ') . ' €',
            '{solde_restant}'   => number_format($solde, 2, ',', ' ') . ' €',
            '{numero_devis}'    => $resa->numero_devis,
            '{lien_paiement}'   => home_url('/paiement/?resa=' . $resa->id),
        ];

        // 4. REMPLACEMENT DES VARIABLES
        $subject = strtr($subject, $vars);
        $body    = strtr(wpautop($body), $vars);

        // 5. GESTION DES PIÈCES JOINTES (PDF)
        $attachments = [];
        if ($attachment_pdf_id > 0) {
            // ICI : Appeler le générateur de PDF (sera codé à l'étape suivante)
            // $pdf_path = PCR_PDF_Generator::generate($attachment_pdf_id, $resa);
            // if ($pdf_path) $attachments[] = $pdf_path;
        }

        // 6. ENVOI DE L'EMAIL
        $to = $resa->email;
        if (!is_email($to)) return ['success' => false, 'message' => 'Email client invalide.'];

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $sent = wp_mail($to, $subject, $body, $headers, $attachments);

        if (!$sent) {
            error_log("❌ Echec envoi mail Résa #{$reservation_id} à {$to}");
            return ['success' => false, 'message' => "Erreur technique d'envoi (wp_mail)."];
        }

        // 7. ENREGISTREMENT BDD
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'pc_messages',
            [
                'reservation_id' => $reservation_id,
                'canal'          => 'email',
                'direction'      => 'sortant',
                'type'           => $message_type,
                'template_code'  => $template_code,
                'sujet'          => $subject,
                'corps'          => $body,
                'dest_email'     => $to,
                'statut_envoi'   => 'envoye',
                'date_creation'  => current_time('mysql'),
                'date_envoi'     => current_time('mysql'),
                'date_maj'       => current_time('mysql'),
                'user_id'        => get_current_user_id() ?: 0
            ]
        );

        return ['success' => true, 'message' => 'Message envoyé avec succès.'];
    }

    /**
     * Helper : Calcule le montant déjà payé pour une résa
     */
    private static function get_paid_amount($resa_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pc_payments';
        $val = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(montant) FROM {$table} WHERE reservation_id = %d AND statut = 'paye'",
            $resa_id
        ));
        return (float) $val;
    }

    /**
     * CRON : Traitement des messages automatiques
     * A adapter pour lire les nouveaux champs ACF
     */
    public static function process_auto_messages()
    {
        // Placeholder pour l'instant : on doit écrire la logique de requêtage
        // basée sur 'pc_trigger_relative' et 'pc_trigger_days'
    }
}
