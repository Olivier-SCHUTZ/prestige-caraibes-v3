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
                    'post_type' => 'pc_message', // CORRIGÉ ICI AUSSI (anciennement pc_template)
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
            $attachment_pdf_id = (int) get_field('pc_msg_attachment', $template_post->ID);

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

        // 5. PDF JOINT (Si module PDF actif)
        $attachments = [];
        if ($attachment_pdf_id > 0 && class_exists('PCR_Documents')) {
            $gen = PCR_Documents::generate($attachment_pdf_id, $resa->id, true);
            if ($gen['success'] && !empty($gen['url'])) {
                // Convertir URL en chemin local pour wp_mail
                $upload_dir = wp_upload_dir();
                $local_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $gen['url']);
                if (file_exists($local_path)) {
                    $attachments[] = $local_path;
                }
            }
        }

        // 6. ENVOI
        $to = $resa->email;
        if (!is_email($to)) return ['success' => false, 'message' => 'Email client invalide.'];

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $sent = wp_mail($to, $subject, $body, $headers, $attachments);

        if (!$sent) {
            error_log("❌ Echec envoi mail Résa #{$reservation_id} à {$to}");
            return ['success' => false, 'message' => "Erreur technique d'envoi (wp_mail)."];
        }

        // 7. BDD
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
