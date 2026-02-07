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

        // 3. Champs ACF (Logique conditionnelle & Design)
        add_action('acf/init', [__CLASS__, 'register_template_fields']);

        // 4. Charger dynamiquement les choix de CGV
        add_filter('acf/load_field/name=pc_pdf_append_cgv', [__CLASS__, 'load_cgv_choices']);

        // ✨ 4.2 NOUVEAU : Charger dynamiquement les documents natifs + templates
        add_filter('acf/load_field/name=pc_msg_attachment', [__CLASS__, 'load_attachment_choices']);

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
        // A. Modèles de Messages (Emails) - RENOMMÉ selon cahier des charges
        register_post_type('pc_message', [
            'labels' => [
                'name' => 'Messagerie personnalisée',
                'singular_name' => 'Message',
                'menu_name' => 'Messagerie personnalisée',
                'add_new_item' => 'Nouveau Message',
                'edit_item' => 'Modifier le Message',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'pc-reservation-settings',
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
        // SUPPRIMÉ : Enregistrement déplacé dans class-documents.php pour être sous PC Réservation
        /* register_post_type('pc_pdf_template', [
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
        ]); */
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
                // NOUVEAU: Distinction Type de message (Email vs Chat)
                [
                    'key' => 'field_pc_message_category',
                    'label' => 'Catégorie de message',
                    'name' => 'pc_message_category',
                    'type' => 'select',
                    'choices' => [
                        'email_system' => '📧 Email Système (Factures, Devis, Confirmations)',
                        'quick_reply'  => '💬 Réponse Rapide (Snippets pour Chat/WhatsApp)',
                    ],
                    'default_value' => 'email_system',
                    'instructions' => 'Choisissez le type d\'utilisation de ce message.',
                ],
                [
                    'key' => 'field_pc_msg_type',
                    'label' => 'Type de déclencheur',
                    'name' => 'pc_msg_type',
                    'type' => 'select',
                    'choices' => [
                        'libre'     => 'Message personnalisé (libre)',
                        'immediat'  => 'Message immédiat après action sur le front',
                        'programme' => 'Message programmé suite à réservation',
                    ],
                    'default_value' => 'libre',
                    'conditional_logic' => [
                        [['field' => 'field_pc_message_category', 'operator' => '==', 'value' => 'email_system']]
                    ],
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
                    'conditional_logic' => [
                        [['field' => 'field_pc_message_category', 'operator' => '==', 'value' => 'email_system']]
                    ],
                    'instructions' => 'Le sujet n\'est utilisé que pour les emails système. Les réponses rapides WhatsApp n\'ont pas de sujet.',
                ],
                [
                    'key' => 'field_pc_msg_attachment',
                    'label' => 'Joindre un PDF',
                    'name' => 'pc_msg_attachment',
                    'type' => 'select',
                    'choices' => [], // ✨ NOUVEAU : Sera rempli dynamiquement avec les documents natifs + templates
                    'return_format' => 'value',
                    'allow_null' => 1,
                    'ui' => 1,
                    'conditional_logic' => [
                        [['field' => 'field_pc_message_category', 'operator' => '==', 'value' => 'email_system']]
                    ],
                    'instructions' => '✨ NOUVEAU : Choisissez un document natif (généré automatiquement) ou un modèle PDF personnalisé.',
                ],
            ],
            'location' => [
                [['param' => 'post_type', 'operator' => '==', 'value' => 'pc_message']]
            ],
            'position' => 'acf_after_title',
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
     * ✨ NOUVEAU : Remplit dynamiquement les choix de pièces jointes
     * Combine les documents natifs + les templates PDF personnalisés
     */
    public static function load_attachment_choices($field)
    {
        $field['choices'] = [];

        // === 1️⃣ DOCUMENTS NATIFS (Hardcodés) ===
        $field['choices']['🏠 Documents Natifs'] = [
            'native_devis' => '📄 Devis commercial (généré automatiquement)',
            'native_facture' => '🧾 Facture principale (généré automatiquement)',
            'native_facture_acompte' => '💰 Facture d\'acompte (généré automatiquement)',
            'native_contrat' => '📋 Contrat de location (généré automatiquement)',
            'native_voucher' => '🎫 Voucher / Bon d\'échange (généré automatiquement)',
        ];

        // === 2️⃣ TEMPLATES PDF PERSONNALISÉS (Depuis BDD) ===
        $templates_args = [
            'post_type' => 'pc_pdf_template',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ];

        $templates = get_posts($templates_args);

        if (!empty($templates)) {
            $custom_choices = [];

            foreach ($templates as $template) {
                $doc_type = get_field('pc_doc_type', $template->ID) ?: 'document';

                // Icône selon le type
                $icon = '📄';
                switch ($doc_type) {
                    case 'devis':
                        $icon = '📄';
                        break;
                    case 'facture':
                        $icon = '🧾';
                        break;
                    case 'facture_acompte':
                        $icon = '💰';
                        break;
                    case 'avoir':
                        $icon = '↩️';
                        break;
                    case 'contrat':
                        $icon = '📋';
                        break;
                    case 'voucher':
                        $icon = '🎫';
                        break;
                    default:
                        $icon = '📄';
                        break;
                }

                $custom_choices['template_' . $template->ID] = $icon . ' ' . $template->post_title . ' (modèle personnalisé)';
            }

            if (!empty($custom_choices)) {
                $field['choices']['🎨 Modèles Personnalisés'] = $custom_choices;
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
        // CORRECTION ICI : 'pc_message' au lieu de 'pc_template'
        $screens = ['pc_message', 'pc_pdf_template'];

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
        <div style="font-size: 12px; color: #444;">
            <p style="margin-bottom:10px;">Utilisez ces codes pour personnaliser vos messages :</p>

            <strong style="display:block; border-bottom:1px solid #ddd; padding-bottom:3px; margin-bottom:5px;">👤 Données Client</strong>
            <ul style="margin: 0 0 15px 15px; list-style:square;">
                <li><code>{prenom_client}</code></li>
                <li><code>{nom_client}</code></li>
                <li><code>{email_client}</code></li>
                <li><code>{telephone}</code></li>
                <li><code>{adresse_client}</code></li>
            </ul>

            <strong style="display:block; border-bottom:1px solid #ddd; padding-bottom:3px; margin-bottom:5px;">📅 Données Séjour</strong>
            <ul style="margin: 0 0 15px 15px; list-style:square;">
                <li><code>{date_arrivee}</code></li>
                <li><code>{date_depart}</code></li>
                <li><code>{duree_sejour}</code></li>
                <li><code>{logement}</code></li>
                <li><code>{numero_resa}</code></li>
            </ul>

            <strong style="display:block; border-bottom:1px solid #ddd; padding-bottom:3px; margin-bottom:5px;">💶 Données Financières</strong>
            <ul style="margin: 0 0 15px 15px; list-style:square;">
                <li><code>{montant_total}</code> (TTC)</li>
                <li><code>{acompte_paye}</code> (Déjà réglé)</li>
                <li><code>{solde_restant}</code> (Reste à payer)</li>
                <li><code>{lien_paiement}</code> (Lien direct)</li>
            </ul>
        </div>
<?php
    }

    /**
     * Gère la requête d'aperçu PDF
     */
    public static function handle_pdf_preview_request()
    {
        if (isset($_GET['pc_action']) && $_GET['pc_action'] === 'preview_pdf' && isset($_GET['id'])) {
            $template_id = (int)$_GET['id'];

            // Sécurité
            if (!current_user_can('edit_posts')) {
                wp_die('Accès refusé');
            }

            // On vérifie que la classe Documents est chargée
            if (class_exists('PCR_Documents')) {
                // On appelle la nouvelle méthode de prévisualisation
                PCR_Documents::preview($template_id);
            } else {
                wp_die('Erreur : Moteur PDF (PCR_Documents) introuvable.');
            }
            exit;
        }
    }

    /**
     * ✨ MOTEUR D'ENVOI CHANNEL MANAGER (Compatible Template OU Message Libre)
     * Version 2.0 avec support multi-canal et threading
     */
    public static function send_message($template_identifier, $reservation_id, $force_send = false, $message_type = 'automatique', $custom_args = [])
    {
        $reservation_id = (int) $reservation_id;
        if (!$reservation_id) return ['success' => false, 'message' => 'ID réservation manquant.'];

        $subject = '';
        $body    = '';
        $template_code = '';
        $attachment_pdf_id = 0;

        // ✨ NOUVEAUX PARAMÈTRES CHANNEL MANAGER
        $channel_source = $custom_args['channel_source'] ?? 'email';
        $sender_type = $custom_args['sender_type'] ?? 'host';
        $external_id = $custom_args['external_id'] ?? null;
        $metadata = $custom_args['metadata'] ?? [];

        // 1. DÉTERMINER LE CONTENU
        // CAS A : Message Libre
        if ($template_identifier === 'custom' || $template_identifier === 0) {
            if (empty($custom_args['sujet']) || empty($custom_args['corps'])) {
                return ['success' => false, 'message' => 'Sujet ou message manquant pour l\'envoi manuel.'];
            }
            $subject = sanitize_text_field($custom_args['sujet']);
            $body    = wp_kses_post($custom_args['corps']);
            $template_code = 'manuel_custom';
        }
        // CAS B : Utilisation d'un Modèle
        else {
            $template_post = null;
            if (is_numeric($template_identifier)) {
                $template_post = get_post($template_identifier);
            } else {
                $posts = get_posts([
                    'post_type' => 'pc_message',
                    'meta_key' => 'pc_msg_trigger',
                    'meta_value' => $template_identifier,
                    'numberposts' => 1
                ]);
                if (!empty($posts)) $template_post = $posts[0];
            }

            if (!$template_post) {
                return ['success' => false, 'message' => "Modèle introuvable ($template_identifier)."];
            }

            $subject_raw = get_field('pc_msg_subject', $template_post->ID) ?: $template_post->post_title;
            $body_raw    = $template_post->post_content;
            $template_code = $template_post->post_name;
            $attachment_pdf_id = get_field('pc_msg_attachment', $template_post->ID); // ✨ NOUVEAU : String ou Int

            $subject = $subject_raw;
            $body    = $body_raw;
        }

        // 2. RÉCUPÉRATION DONNÉES RÉSERVATION
        if (!class_exists('PCR_Reservation')) return ['success' => false, 'message' => 'Core manquant.'];
        $resa = PCR_Reservation::get_by_id($reservation_id);
        if (!$resa) return ['success' => false, 'message' => 'Réservation introuvable.'];

        // 3. PRÉPARATION DES VARIABLES (Mise à jour complète)
        $item_title = get_the_title($resa->item_id);
        $paid_amount = self::get_paid_amount($reservation_id);
        $solde = (float)($resa->montant_total ?? 0) - $paid_amount;

        // Calculs Durée & Adresse
        $ts_arr = strtotime($resa->date_arrivee);
        $ts_dep = strtotime($resa->date_depart);
        $duree  = ($ts_arr && $ts_dep) ? ceil(($ts_dep - $ts_arr) / 86400) : 0;

        $adresse_client = trim(($resa->adresse ?? '') . ' ' . ($resa->code_postal ?? '') . ' ' . ($resa->ville ?? ''));
        if (empty($adresse_client)) $adresse_client = "Adresse non renseignée";

        $vars = [
            '{id}'              => $resa->id,
            '{prenom_client}'   => ucfirst($resa->prenom),
            '{nom_client}'      => strtoupper($resa->nom),
            '{email_client}'    => $resa->email,
            '{telephone}'       => $resa->telephone,
            '{telephone_client}' => $resa->telephone,
            '{adresse_client}'  => $adresse_client,

            '{logement}'        => $item_title,
            '{date_arrivee}'    => date_i18n('d/m/Y', $ts_arr),
            '{date_depart}'     => date_i18n('d/m/Y', $ts_dep),
            '{duree_sejour}'    => $duree . ' nuit(s)',
            '{numero_resa}'     => $resa->id,
            '{numero_devis}'    => $resa->numero_devis,

            '{montant_total}'   => number_format((float)$resa->montant_total, 2, ',', ' ') . ' €',
            '{acompte_paye}'    => number_format($paid_amount, 2, ',', ' ') . ' €',
            '{solde_restant}'   => number_format($solde, 2, ',', ' ') . ' €',
            '{lien_paiement}'   => home_url('/paiement/?resa=' . $resa->id),
        ];

        // 4. REMPLACEMENT
        $subject = strtr($subject, $vars);
        $body    = strtr(wpautop($body), $vars);

        // --- 🔒 SÉCURITÉ CHANNEL MANAGER ---
        // 1. Force l'ID dans le sujet si absent (Format: [#123])
        $prefix = "[#{$resa->id}]";
        if (strpos($subject, $prefix) === false) {
            $subject = $prefix . ' ' . $subject;
        }

        // 2. Ajoute le "Watermark" invisible en bas du corps pour le tracking en cas de changement de sujet
        // On le met en couleur blanche ou très petit pour être discret mais lisible par le robot
        $watermark = "<div style='color:#ffffff; font-size:1px; opacity:0;'>Ref: #{$resa->id}</div>";
        $body .= $watermark;
        // -----------------------------------

        // 5. PDF JOINT (Si module PDF actif) + ✨ NOUVEAU : Support pièces jointes custom
        $attachments = [];

        // A. ✨ NOUVEAU : Document natif (native_devis, native_facture, etc.)
        if (!empty($attachment_pdf_id) && is_string($attachment_pdf_id) && strpos($attachment_pdf_id, 'native_') === 0) {
            if (class_exists('PCR_Documents')) {
                // Mapping des types natifs vers les types PCR_Documents
                $native_mappings = [
                    'native_devis' => 'devis',
                    'native_facture' => 'facture',
                    'native_facture_acompte' => 'facture_acompte',
                    'native_contrat' => 'contrat',
                    'native_voucher' => 'voucher',
                ];

                $doc_type = $native_mappings[$attachment_pdf_id] ?? null;
                if ($doc_type) {
                    // Générer le document natif dynamiquement
                    $gen = PCR_Documents::generate_native($doc_type, $resa->id);
                    if ($gen['success'] && !empty($gen['path']) && file_exists($gen['path'])) {
                        $attachments[] = $gen['path'];
                        error_log("[PCR_Messaging] Document natif généré : {$doc_type} -> {$gen['path']}");
                    } else {
                        error_log("[PCR_Messaging] Échec génération document natif : {$doc_type} - " . ($gen['message'] ?? 'Erreur inconnue'));
                    }
                }
            }
        }
        // B. Template PDF personnalisé (template_123 ou ID numérique)
        elseif (!empty($attachment_pdf_id)) {
            $template_id = 0;

            // Cas 1 : template_123 -> extraire l'ID
            if (is_string($attachment_pdf_id) && strpos($attachment_pdf_id, 'template_') === 0) {
                $template_id = (int) str_replace('template_', '', $attachment_pdf_id);
            }
            // Cas 2 : ID numérique direct
            elseif (is_numeric($attachment_pdf_id)) {
                $template_id = (int) $attachment_pdf_id;
            }

            if ($template_id > 0 && class_exists('PCR_Documents')) {
                $gen = PCR_Documents::generate($template_id, $resa->id, true);
                if ($gen['success'] && !empty($gen['url'])) {
                    $upload_dir = wp_upload_dir();
                    $local_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $gen['url']);
                    if (file_exists($local_path)) {
                        $attachments[] = $local_path;
                        error_log("[PCR_Messaging] Template PDF généré : ID {$template_id} -> {$local_path}");
                    }
                }
            }
        }

        // ✨ Gestion des pièces jointes (Fichiers ou Natifs)
        if (!empty($custom_args['attachment_path'])) {
            $path = $custom_args['attachment_path'];

            // Cas 1 : Document natif (ex: native_devis)
            if (strpos($path, 'native_') === 0) {
                // Vérification de sécurité avant appel
                if (class_exists('PCR_Documents') && method_exists('PCR_Documents', 'generate_native')) {
                    try {
                        $doc_type = str_replace('native_', '', $path);
                        // Génération intelligente (récupère l'existant ou crée)
                        $gen = PCR_Documents::generate_native($doc_type, $resa->id);

                        if ($gen['success'] && !empty($gen['path'])) {
                            $attachments[] = $gen['path'];
                        }
                    } catch (Exception $e) {
                        error_log("[PCR Error] Echec PDF : " . $e->getMessage());
                    }
                }
            }
            // Cas 2 : Fichier existant sur le disque
            elseif (file_exists($path)) {
                $attachments[] = $path;
            }
        }
        // 6. ENVOI (Seulement pour email pour l'instant)
        $delivery_success = false;
        if ($channel_source === 'email') {
            $to = $resa->email;
            if (!is_email($to)) return ['success' => false, 'message' => 'Email client invalide.'];

            $headers = ['Content-Type: text/html; charset=UTF-8'];
            $delivery_success = wp_mail($to, $subject, $body, $headers, $attachments);

            if (!$delivery_success) {
                error_log("❌ Echec envoi mail Résa #{$reservation_id} à {$to}");
            }
        } else {
            // Autres canaux (SMS, WhatsApp, etc.) - Pour l'instant on simule le succès
            $delivery_success = true;
        }

        // 7. ✨ BDD CHANNEL MANAGER - Structure enrichie
        global $wpdb;
        $conversation_id = self::get_or_create_conversation_id($reservation_id);

        // --- A. PRÉPARATION DES MÉTADONNÉES (On prépare d'abord les PJ) ---
        // On s'assure que $metadata est un tableau
        $metadata = is_array($metadata) ? $metadata : [];

        if (!empty($attachments)) {
            $meta_attachments = [];
            foreach ($attachments as $att_path) {
                $meta_attachments[] = [
                    'name' => basename($att_path), // Nom du fichier (ex: Devis-123.pdf)
                    'type' => 'file'
                ];
            }
            $metadata['attachments'] = $meta_attachments;
        }
        // ------------------------------------------------------------------

        // --- B. CONSTRUCTION UNIQUE DU MESSAGE ---
        $message_data = [
            'reservation_id'  => $reservation_id,
            'conversation_id' => $conversation_id,
            'canal'           => $channel_source,
            'channel_source'  => $channel_source,
            'direction'       => 'sortant',
            'sender_type'     => $sender_type,
            'type'            => $message_type,
            'template_code'   => $template_code,
            'sujet'           => $subject,
            'corps'           => $body,
            'dest_email'      => $resa->email,
            'statut_envoi'    => $delivery_success ? 'envoye' : 'echec',
            'date_creation'   => current_time('mysql'),
            'date_envoi'      => $delivery_success ? current_time('mysql') : null,
            'delivered_at'    => $delivery_success ? current_time('mysql') : null,
            'date_maj'        => current_time('mysql'),
            'user_id'         => get_current_user_id() ?: 0,
            'external_id'     => $external_id,
            'metadata'        => !empty($metadata) ? json_encode($metadata) : null,
        ];

        // --- C. INSERTION ---
        $wpdb->insert($wpdb->prefix . 'pc_messages', $message_data);

        if (!$delivery_success) {
            return ['success' => false, 'message' => "Erreur technique d'envoi (wp_mail)."];
        }

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
     * ✨ NOUVELLES MÉTHODES CHANNEL MANAGER
     */

    /**
     * Récupère ou crée un conversation_id pour une réservation
     * Utilise reservation_id comme base pour simplifier le threading
     */
    private static function get_or_create_conversation_id($reservation_id)
    {
        global $wpdb;

        // Pour l'instant, on utilise simplement reservation_id comme conversation_id
        // Cela crée un 1:1 mapping (1 réservation = 1 conversation)
        // Plus tard on pourra avoir plusieurs conversations par réservation si besoin
        $conversation_id = $wpdb->get_var($wpdb->prepare(
            "SELECT conversation_id FROM {$wpdb->prefix}pc_messages 
             WHERE reservation_id = %d 
             ORDER BY id ASC 
             LIMIT 1",
            $reservation_id
        ));

        if ($conversation_id) {
            return (int) $conversation_id;
        }

        // Première fois : utiliser reservation_id comme conversation_id
        return (int) $reservation_id;
    }

    /**
     * ✨ DESIGN-AWARE : Récupère l'historique complet optimisé pour le design glassmorphisme
     * @param int $reservation_id ID de la réservation
     * @return array Structure conversationnelle avec CSS pre-computed pour design actuel
     */
    public static function get_conversation($reservation_id)
    {
        $reservation_id = (int) $reservation_id;
        if (!$reservation_id) return ['success' => false, 'messages' => []];

        global $wpdb;
        $table = $wpdb->prefix . 'pc_messages';

        $sql = "SELECT 
            id,
            conversation_id,
            canal,
            channel_source,
            direction,
            sender_type,
            type,
            sujet,
            corps,
            template_code,
            dest_email,
            exp_email,
            statut_envoi,
            read_at,
            delivered_at,
            date_creation,
            date_envoi,
            external_id,
            metadata,
            user_id
        FROM {$table} 
        WHERE reservation_id = %d 
        ORDER BY date_creation ASC";

        $messages = $wpdb->get_results($wpdb->prepare($sql, $reservation_id), 'ARRAY_A');

        if (!$messages) {
            return ['success' => true, 'messages' => [], 'conversation_id' => $reservation_id];
        }

        $conversation_data = [];
        foreach ($messages as $msg) {
            // ✨ DESIGN-AWARE : Pre-compute des classes CSS pour le design glassmorphisme
            $design_data = self::compute_message_design_data($msg);

            $conversation_data[] = [
                'id' => (int) $msg['id'],
                'conversation_id' => (int) $msg['conversation_id'],
                'canal' => $msg['canal'],
                'channel_source' => $msg['channel_source'],
                'direction' => $msg['direction'],
                'sender_type' => $msg['sender_type'],
                'type' => $msg['type'],
                'sujet' => $msg['sujet'],
                'corps' => $msg['corps'],
                'template_code' => $msg['template_code'],
                'statut_envoi' => $msg['statut_envoi'],
                'is_read' => !empty($msg['read_at']),
                'is_delivered' => !empty($msg['delivered_at']),
                'date_creation' => $msg['date_creation'],
                'date_envoi' => $msg['date_envoi'],
                'date_relative' => self::format_relative_time($msg['date_creation']),
                'external_id' => $msg['external_id'],
                'metadata' => $msg['metadata'] ? json_decode($msg['metadata'], true) : null,
                'user_id' => (int) $msg['user_id'],

                // ✨ DESIGN-AWARE : Données pré-calculées pour le frontend glassmorphisme
                'sender_avatar' => $design_data['avatar'],
                'sender_name' => $design_data['sender_name'],
                'css_classes' => $design_data['css_classes'],
                'channel_icon' => $design_data['channel_icon'],
                'status_badge' => $design_data['status_badge'],
                'formatted_date' => $design_data['formatted_date'],
                'truncated_preview' => $design_data['truncated_preview'],
                'needs_see_more' => $design_data['needs_see_more'],
                'bubble_style' => $design_data['bubble_style'], // CSS inline pour bulles dynamiques
            ];
        }

        return [
            'success' => true,
            'conversation_id' => (int) $messages[0]['conversation_id'],
            'total_messages' => count($conversation_data),
            'unread_count' => count(array_filter($conversation_data, function ($msg) {
                return !$msg['is_read'] && $msg['direction'] === 'entrant';
            })),
            'messages' => $conversation_data
        ];
    }

    /**
     * ✨ DESIGN-AWARE : Pre-compute toutes les données visuelles pour un message
     * Compatible avec le design glassmorphisme existant
     */
    private static function compute_message_design_data($msg)
    {
        $sender_type = $msg['sender_type'] ?? 'host';
        $channel_source = $msg['channel_source'] ?? 'email';
        $direction = $msg['direction'] ?? 'sortant';

        // === CSS CLASSES POUR ALIGNMENT BULLES ===
        $css_classes = [];

        // Classes de base (compatibles avec le design actuel)
        $css_classes[] = 'pc-msg-bubble';

        // Alignment selon sender_type (CRITQUE pour le CSS d'alignment)
        if ($sender_type === 'guest') {
            $css_classes[] = 'pc-msg--guest';      // Aligné à droite, bulle bleue
            $css_classes[] = 'pc-msg--incoming';
        } else {
            $css_classes[] = 'pc-msg--host';       // Aligné à gauche, bulle violette
            $css_classes[] = 'pc-msg--outgoing';
        }

        // Classes pour source (pour icônes futures)
        $css_classes[] = 'pc-msg--' . $channel_source;

        // === AVATARS SELON DESIGN ACTUEL ===
        $avatar = self::get_sender_avatar($sender_type, $msg['user_id'] ?? 0);

        // === ICÔNE CANAL (DESIGN-AWARE) ===
        $channel_icons = [
            'email' => '✉️',
            'airbnb' => '🏠',
            'booking' => '🏨',
            'sms' => '📱',
            'whatsapp' => '💬',
            'system' => '🤖'
        ];
        $channel_icon = $channel_icons[$channel_source] ?? '✉️';

        // === STATUS BADGE GLASSMORPHISME ===
        $status_badge = self::compute_status_badge($msg);

        // === FORMATAGE DATE POUR DESIGN ACTUEL ===
        $formatted_date = self::format_display_date($msg['date_creation'] ?? '');

        // === TRONCATURE (MÊME LOGIQUE QUE L'EXISTANT) ===
        $content = $msg['corps'] ?? '';
        $plain_text = strip_tags($content);
        $needs_see_more = strlen($plain_text) > 100;
        $truncated_preview = $needs_see_more ? substr($plain_text, 0, 100) . '...' : $plain_text;

        // === BULLE STYLE DYNAMIQUE (GRADIENT SELON CANAL) ===
        $bubble_style = self::compute_bubble_gradient($channel_source, $sender_type);

        // === NOM SENDER AVEC CONTEXT ===
        $sender_name = self::get_sender_name($sender_type, $msg['user_id'] ?? 0);
        if ($channel_source !== 'email') {
            $sender_name .= " via " . ucfirst($channel_source);
        }

        return [
            'avatar' => $avatar,
            'sender_name' => $sender_name,
            'css_classes' => implode(' ', $css_classes),
            'channel_icon' => $channel_icon,
            'status_badge' => $status_badge,
            'formatted_date' => $formatted_date,
            'truncated_preview' => $truncated_preview,
            'needs_see_more' => $needs_see_more,
            'bubble_style' => $bubble_style,
        ];
    }

    /**
     * Compute status badge avec design glassmorphisme
     */
    private static function compute_status_badge($msg)
    {
        $statut = $msg['statut_envoi'] ?? 'brouillon';
        $is_read = !empty($msg['read_at']);
        $is_delivered = !empty($msg['delivered_at']);

        $badges = [
            'envoye' => ['text' => 'Envoyé', 'class' => 'pc-badge--success', 'icon' => '✅'],
            'echec' => ['text' => 'Échec', 'class' => 'pc-badge--error', 'icon' => '❌'],
            'brouillon' => ['text' => 'Brouillon', 'class' => 'pc-badge--draft', 'icon' => '📝'],
        ];

        $badge_info = $badges[$statut] ?? $badges['brouillon'];

        // Enrichir avec statut lecture
        if ($is_delivered && $is_read) {
            $badge_info['text'] .= ' · Lu';
            $badge_info['icon'] .= '👁️';
        }

        return $badge_info;
    }

    /**
     * Format date pour affichage (même style que l'existant)
     */
    private static function format_display_date($datetime)
    {
        if (!$datetime) return '';

        $timestamp = strtotime($datetime);
        $now = current_time('timestamp');
        $diff = $now - $timestamp;

        // Même logique que format_relative_time mais avec plus de détails
        if ($diff < 3600) return sprintf('Il y a %dm', floor($diff / 60));
        if ($diff < 86400) return sprintf('Il y a %dh', floor($diff / 3600));
        if ($diff < 604800) return date_i18n('D j M', $timestamp);

        return date_i18n('j M Y', $timestamp);
    }

    /**
     * Compute gradient de bulle selon canal (glassmorphisme)
     */
    private static function compute_bubble_gradient($channel_source, $sender_type)
    {
        // Gradients par canal (design cohérent avec dashboard-modals.css)
        $gradients = [
            'email' => 'linear-gradient(135deg, #6366f1 0%, #4f46e5 100%)', // Violet comme design actuel
            'airbnb' => 'linear-gradient(135deg, #ff385c 0%, #e31c5f 100%)', // Rose Airbnb
            'booking' => 'linear-gradient(135deg, #003580 0%, #0071c2 100%)', // Bleu Booking
            'sms' => 'linear-gradient(135deg, #10b981 0%, #059669 100%)', // Vert SMS
            'whatsapp' => 'linear-gradient(135deg, #25d366 0%, #128c7e 100%)', // Vert WhatsApp
            'system' => 'linear-gradient(135deg, #64748b 0%, #475569 100%)', // Gris système
        ];

        $base_gradient = $gradients[$channel_source] ?? $gradients['email'];

        // Ajuster opacité selon sender (guest = plus transparent)
        if ($sender_type === 'guest') {
            return $base_gradient . '; opacity: 0.9;';
        }

        return $base_gradient;
    }

    /**
     * Marque un ou plusieurs messages comme lus
     * @param array|int $message_ids ID(s) des messages à marquer
     * @return array Résultat de l'opération
     */
    public static function mark_as_read($message_ids)
    {
        if (!is_array($message_ids)) {
            $message_ids = [(int) $message_ids];
        }

        $message_ids = array_filter(array_map('intval', $message_ids));
        if (empty($message_ids)) {
            return ['success' => false, 'message' => 'Aucun message à marquer.'];
        }

        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($message_ids), '%d'));
        $now = current_time('mysql');

        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}pc_messages 
             SET read_at = %s, date_maj = %s 
             WHERE id IN ($placeholders) AND read_at IS NULL",
            array_merge([$now, $now], $message_ids)
        ));

        return [
            'success' => true,
            'updated_count' => $updated,
            'message' => sprintf('%d message(s) marqué(s) comme lu(s).', $updated)
        ];
    }

    /**
     * Helper : Format relatif pour l'affichage (Il y a 2h, Hier, etc.)
     */
    private static function format_relative_time($datetime)
    {
        if (!$datetime) return '';

        $timestamp = strtotime($datetime);
        $now = current_time('timestamp');
        $diff = $now - $timestamp;

        if ($diff < 60) return 'À l\'instant';
        if ($diff < 3600) return sprintf('Il y a %dm', floor($diff / 60));
        if ($diff < 86400) return sprintf('Il y a %dh', floor($diff / 3600));
        if ($diff < 604800) return sprintf('Il y a %dj', floor($diff / 86400));

        return date_i18n('j M Y', $timestamp);
    }

    /**
     * Helper : Avatar selon le type d'expéditeur
     */
    private static function get_sender_avatar($sender_type, $user_id = 0)
    {
        switch ($sender_type) {
            case 'guest':
                return '👤'; // Avatar client
            case 'system':
                return '🤖'; // Avatar système
            case 'host':
            default:
                if ($user_id > 0) {
                    return get_avatar_url($user_id, ['size' => 32]) ?: '🏠';
                }
                return '🏠'; // Avatar hôte par défaut
        }
    }

    /**
     * Helper : Nom selon le type d'expéditeur
     */
    private static function get_sender_name($sender_type, $user_id = 0)
    {
        switch ($sender_type) {
            case 'guest':
                return 'Client';
            case 'system':
                return 'Système automatique';
            case 'host':
            default:
                if ($user_id > 0) {
                    $user = get_userdata($user_id);
                    return $user ? $user->display_name : 'Équipe';
                }
                return 'Équipe';
        }
    }

    /**
     * ✨ NOUVEAU : Récupère les "Réponses Rapides" pour le Channel Manager
     * Retourne uniquement les messages de type "quick_reply" (texte brut, sans HTML complexe)
     * @return array Liste des templates de réponses rapides formatés pour le frontend
     */
    /**
     * ✨ Récupère TOUS les messages pour la bulle de chat (Correction)
     */
    public static function get_quick_replies()
    {
        $args = [
            'post_type' => 'pc_message',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ];

        $quick_replies = get_posts($args);

        if (empty($quick_replies)) {
            return [
                'success' => true,
                'templates' => [],
                'total' => 0,
                'message' => 'Aucun modèle trouvé.'
            ];
        }

        $formatted_replies = [];

        foreach ($quick_replies as $reply) {
            $category = get_field('pc_message_category', $reply->ID);
            $subject = get_field('pc_msg_subject', $reply->ID) ?: $reply->post_title;

            // Récupération de la pièce jointe configurée
            $attachment_key = get_field('pc_msg_attachment', $reply->ID);
            $attachment_name = '';

            // Si une pièce jointe est définie, on lui donne un nom lisible pour l'UI
            if ($attachment_key) {
                // Mapping simple pour l'affichage UI
                $names = [
                    'native_devis' => 'Devis Commercial',
                    'native_facture' => 'Facture',
                    'native_contrat' => 'Contrat'
                ];
                $attachment_name = $names[$attachment_key] ?? 'Document joint';

                // Si c'est un template custom (template_123)
                if (strpos($attachment_key, 'template_') === 0) {
                    $attachment_name = 'Document Personnalisé';
                }
            }

            $content = trim(wp_strip_all_tags($reply->post_content));
            $icon = ($category === 'email_system') ? '📧 ' : '💬 ';

            $formatted_replies[] = [
                'id' => $reply->ID,
                'title' => $icon . $reply->post_title,
                'subject' => $subject,
                'content' => $content,
                'preview' => substr($content, 0, 60) . '...',
                'category' => $category,
                // On envoie les nouvelles infos au JS
                'attachment_key' => $attachment_key,
                'attachment_name' => $attachment_name
            ];
        }

        return [
            'success' => true,
            'templates' => $formatted_replies,
            'total' => count($formatted_replies)
        ];
    }

    /**
     * ✨ NOUVEAU : Récupère un template de réponse rapide spécifique avec variables remplacées
     * @param int $template_id ID du template
     * @param int $reservation_id ID de la réservation pour le remplacement des variables
     * @return array Template avec contenu personnalisé
     */
    public static function get_quick_reply_with_vars($template_id, $reservation_id = null)
    {
        $template_id = (int) $template_id;
        if (!$template_id) {
            return ['success' => false, 'message' => 'ID template manquant.'];
        }

        // Vérifier que c'est bien un template de réponse rapide
        $template = get_post($template_id);
        if (!$template || $template->post_type !== 'pc_message') {
            return ['success' => false, 'message' => 'Template introuvable.'];
        }

        $category = get_field('pc_message_category', $template_id);
        if ($category !== 'quick_reply') {
            return ['success' => false, 'message' => 'Ce template n\'est pas une réponse rapide.'];
        }

        // Contenu de base
        $content = wp_strip_all_tags($template->post_content);
        $subject = get_field('pc_msg_subject', $template_id) ?: $template->post_title;

        // Remplacement des variables si reservation_id fourni
        if ($reservation_id) {
            $reservation_id = (int) $reservation_id;

            if (class_exists('PCR_Reservation')) {
                $resa = PCR_Reservation::get_by_id($reservation_id);

                if ($resa) {
                    $vars = [
                        '{prenom}' => ucfirst($resa->prenom),
                        '{prenom_client}' => ucfirst($resa->prenom),
                        '{nom_client}' => strtoupper($resa->nom),
                        '{numero_resa}' => $resa->id,
                        '{numero_devis}' => $resa->numero_devis,
                        '{email_client}' => $resa->email,
                        '{telephone}' => $resa->telephone,
                        '{logement}' => get_the_title($resa->item_id),
                    ];

                    // Remplacement
                    $content = strtr($content, $vars);
                    $subject = strtr($subject, $vars);
                }
            }
        }

        return [
            'success' => true,
            'template' => [
                'id' => $template_id,
                'title' => $template->post_title,
                'subject' => $subject,
                'content' => $content,
                'original_content' => wp_strip_all_tags($template->post_content),
                'has_variables' => (bool) $reservation_id,
            ]
        ];
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

    /**
     * ✨ NOUVEAU : Injection de messages externes (Webhooks)
     * Reçoit un message depuis un canal externe et l'injecte dans la bonne conversation
     * 
     * @param int $reservation_id ID de la réservation
     * @param string $content Contenu du message
     * @param string $channel Canal source (email, whatsapp, sms, etc.)
     * @param array $metadata Métadonnées additionnelles
     * @return array Résultat de l'injection
     */
    public static function receive_external_message($reservation_id, $content, $channel = 'email', $metadata = [])
    {
        $reservation_id = (int) $reservation_id;

        // Validation des paramètres
        if (!$reservation_id || empty($content)) {
            return [
                'success' => false,
                'message' => 'Paramètres manquants (reservation_id, content).'
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

        // Récupération/création du conversation_id
        $conversation_id = self::get_or_create_conversation_id($reservation_id);

        // Préparation des données du message
        global $wpdb;
        $table = $wpdb->prefix . 'pc_messages';

        // Extraction des métadonnées
        $sender_email = $metadata['sender_email'] ?? null;
        $sender_name = $metadata['sender_name'] ?? null;
        $sender_phone = $metadata['sender_phone'] ?? null;
        $external_id = $metadata['external_id'] ?? null;
        $webhook_source = $metadata['webhook_source'] ?? 'unknown';
        $original_subject = $metadata['original_subject'] ?? null;

        // Données du message pour injection
        $message_data = [
            'reservation_id'  => $reservation_id,
            'conversation_id' => $conversation_id,
            'canal'           => $channel, // Compatibilité ancienne
            'channel_source'  => $channel,
            'direction'       => 'entrant', // IMPORTANT : Message entrant
            'sender_type'     => 'guest',   // IMPORTANT : Vient du client (affichage à droite)
            'type'            => 'externe', // Type spécifique pour les messages webhook
            'template_code'   => null,      // Pas de template pour les messages entrants
            'sujet'           => $original_subject ?: "Message via " . ucfirst($channel),
            'corps'           => wp_kses_post($content), // Nettoyage du HTML
            'dest_email'      => null,      // Pas applicable pour les entrants
            'exp_email'       => $sender_email,
            'statut_envoi'    => 'recu',    // Statut spécifique aux entrants
            'date_creation'   => current_time('mysql'),
            'date_envoi'      => null,      // Pas applicable
            'delivered_at'    => current_time('mysql'), // Considéré comme délivré immédiatement
            'read_at'         => null,      // Non lu par défaut
            'date_maj'        => current_time('mysql'),
            'user_id'         => 0,         // Pas d'utilisateur pour les messages externes
            'external_id'     => $external_id,
            'metadata'        => !empty($metadata) ? json_encode($metadata) : null,
        ];

        // Insertion en base
        $inserted = $wpdb->insert($table, $message_data);

        if ($inserted === false) {
            error_log('[PCR_Messaging] Erreur insertion message externe: ' . $wpdb->last_error);
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'insertion en base de données.'
            ];
        }

        $message_id = $wpdb->insert_id;

        // Log de succès
        error_log(sprintf(
            '[PCR_Messaging] Message externe injecté avec succès - ID: %d, Résa: %d, Canal: %s, Source: %s',
            $message_id,
            $reservation_id,
            $channel,
            $webhook_source
        ));

        // Optionnel : Notification temps réel (si WebSockets implémentés plus tard)
        do_action('pcr_external_message_received', [
            'message_id' => $message_id,
            'reservation_id' => $reservation_id,
            'channel' => $channel,
            'sender' => [
                'email' => $sender_email,
                'name' => $sender_name,
                'phone' => $sender_phone,
            ],
            'content_preview' => substr(wp_strip_all_tags($content), 0, 100),
        ]);

        return [
            'success' => true,
            'message_id' => $message_id,
            'conversation_id' => $conversation_id,
            'message' => 'Message externe injecté avec succès dans la conversation.'
        ];
    }

    /**
     * Helper : Trouve une réservation active par email du client
     * Utilitaire pour les cas où on n'a pas le numéro de téléphone
     */
    public static function find_active_reservation_by_email($email)
    {
        if (!is_email($email)) {
            return null;
        }

        global $wpdb;

        $reservation_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}pc_reservations 
             WHERE email = %s 
             AND (
                 statut IN ('confirmee', 'en_cours', 'paiement_partiel') 
                 OR date_depart >= CURDATE()
             )
             ORDER BY date_creation DESC 
             LIMIT 1",
            $email
        ));

        return $reservation_id ? (int) $reservation_id : null;
    }

    /**
     * ✨ NOUVEAU : Statistiques des messages entrants (pour dashboard)
     * Retourne des métriques utiles pour le monitoring
     */
    public static function get_external_messages_stats($days = 30)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pc_messages';

        $stats = [];

        // Messages entrants par canal (derniers X jours)
        $stats['by_channel'] = $wpdb->get_results($wpdb->prepare(
            "SELECT channel_source, COUNT(*) as count 
             FROM {$table} 
             WHERE direction = 'entrant' 
             AND type = 'externe'
             AND date_creation >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY channel_source 
             ORDER BY count DESC",
            $days
        ), ARRAY_A);

        // Messages non lus
        $stats['unread_count'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} 
             WHERE direction = 'entrant' 
             AND read_at IS NULL"
        );

        // Moyenne par jour
        $total_external = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} 
             WHERE direction = 'entrant' 
             AND type = 'externe'
             AND date_creation >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));

        $stats['avg_per_day'] = $days > 0 ? round($total_external / $days, 1) : 0;
        $stats['period_days'] = $days;
        $stats['total_external'] = (int) $total_external;

        return $stats;
    }
}
