<?php
if (!defined('ABSPATH')) {
    exit; // Sécurité : Empêche l'accès direct au fichier
}

/**
 * Couche de Gestion des Modèles (Template Manager) pour la Messagerie.
 * Gère les CPT, la configuration ACF, les metaboxes d'aide et les réponses rapides.
 * Pattern Singleton pour garantir une instance unique.
 */
class PCR_Template_Manager
{
    /**
     * @var PCR_Template_Manager Instance unique
     */
    private static $instance = null;

    /**
     * Constructeur privé (Singleton)
     */
    private function __construct() {}

    /**
     * Empêche le clonage
     */
    private function __clone() {}

    /**
     * Récupère l'instance unique du Manager.
     *
     * @return PCR_Template_Manager
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialise les hooks WordPress liés aux templates.
     */
    public function init_hooks()
    {
        add_action('init', [$this, 'register_content_types']);
        add_action('acf/init', [$this, 'register_template_fields']);
        add_filter('acf/load_field/name=pc_pdf_append_cgv', [$this, 'load_cgv_choices']);
        add_filter('acf/load_field/name=pc_msg_attachment', [$this, 'load_attachment_choices']);
        add_action('add_meta_boxes', [$this, 'add_variable_help_box']);
        add_action('init', [$this, 'handle_pdf_preview_request']);
        add_action('admin_footer', [$this, 'print_variable_insertion_script']);
    }

    /**
     * Enregistre les CPTs et la Taxonomie.
     */
    public function register_content_types()
    {
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
    }

    /**
     * Définit tous les champs ACF (Scénarios, Design Global, Modèle PDF).
     */
    public function register_template_fields()
    {
        if (!function_exists('acf_add_local_field_group')) return;

        acf_add_local_field_group([
            'key' => 'group_pc_scenario_config',
            'title' => 'Configuration du Scénario',
            'fields' => [
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
                    'choices' => [],
                    'return_format' => 'value',
                    'allow_null' => 1,
                    'ui' => 1,
                    'conditional_logic' => [
                        [['field' => 'field_pc_message_category', 'operator' => '==', 'value' => 'email_system']]
                    ],
                    'instructions' => 'Choisissez un document natif (généré automatiquement) ou un modèle PDF personnalisé.',
                ],
            ],
            'location' => [
                [['param' => 'post_type', 'operator' => '==', 'value' => 'pc_message']]
            ],
            'position' => 'acf_after_title',
        ]);
    }

    /**
     * Remplit dynamiquement le selecteur "Inclure CGV" depuis la bibliothèque globale.
     */
    public function load_cgv_choices($field)
    {
        $field['choices'] = [];

        $pcr_exists = class_exists('PCR_Fields');
        $has_acf    = function_exists('get_field');

        // Règle B : Récupération native du répéteur
        $cgv_library = get_option('options_pc_pdf_cgv_library') ?: get_option('pc_pdf_cgv_library') ?: ($pcr_exists ? PCR_Fields::get('pc_pdf_cgv_library', 'option') : ($has_acf ? get_field('pc_pdf_cgv_library', 'option') : []));

        // Règle C : Itération native PHP
        if (!empty($cgv_library) && is_array($cgv_library)) {
            foreach ($cgv_library as $row) {
                $title = $row['cgv_title'] ?? '';
                if ($title) {
                    $field['choices'][$title] = $title;
                }
            }
        }
        return $field;
    }

    /**
     * Remplit dynamiquement les choix de pièces jointes (Documents natifs + Modèles personnalisés).
     */
    public function load_attachment_choices($field)
    {
        $field['choices'] = [];

        // 1. Documents natifs
        $field['choices']['🏠 Documents Natifs'] = [
            'native_devis' => '📄 Devis commercial (généré automatiquement)',
            'native_facture' => '🧾 Facture principale (généré automatiquement)',
            'native_facture_acompte' => '💰 Facture d\'acompte (généré automatiquement)',
            'native_contrat' => '📋 Contrat de location (généré automatiquement)',
            'native_voucher' => '🎫 Voucher / Bon d\'échange (généré automatiquement)',
        ];

        // 2. Templates personnalisés
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
            $pcr_exists = class_exists('PCR_Fields');
            $has_acf    = function_exists('get_field');

            foreach ($templates as $template) {
                $raw_doc_type = $pcr_exists ? PCR_Fields::get('pc_doc_type', $template->ID) : ($has_acf ? get_field('pc_doc_type', $template->ID) : '');
                $doc_type = $raw_doc_type ?: 'document';

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
     * Ajoute le bouton "Aperçu" et l'aide-mémoire dans les metabox.
     */
    public function add_variable_help_box()
    {
        $screens = ['pc_message', 'pc_pdf_template'];

        foreach ($screens as $screen) {
            add_meta_box(
                'pc_variables_help',
                'Variables Disponibles',
                [$this, 'render_variable_help_box'],
                $screen,
                'side',
                'high'
            );
        }

        global $post;
        if ($post && $post->post_type === 'pc_pdf_template') {
            add_meta_box(
                'pc_pdf_preview_box',
                'Prévisualisation',
                [$this, 'render_preview_box'],
                'pc_pdf_template',
                'side',
                'high'
            );
        }
    }

    public function render_preview_box($post)
    {
        if ($post->post_status === 'auto-draft') {
            echo '<p><em>Veuillez sauvegarder le brouillon pour générer un aperçu.</em></p>';
        } else {
            $url = home_url('/?pc_action=preview_pdf&id=' . $post->ID);
            echo '<a href="' . esc_url($url) . '" target="_blank" class="button button-primary button-large" style="width:100%;text-align:center;">👁️ Prévisualiser le PDF</a>';
            echo '<p style="margin-top:10px;font-size:0.9em;color:#666;">Ouvre un nouvel onglet avec les données factices.</p>';
        }
    }

    public function render_variable_help_box()
    {
        echo '<div style="font-size: 12px; color: #444;">';
        echo '<p style="margin-bottom:10px; font-style:italic;">💡 Cliquez sur une variable pour l\'insérer.</p>';
        echo '<style>
            .pc-insert-var { cursor: pointer; color: #2271b1; border: 1px solid #dcdcde; background: #f6f7f7; padding: 2px 5px; border-radius: 3px; transition: all 0.2s; }
            .pc-insert-var:hover { background: #2271b1; color: #fff; border-color: #2271b1; }
        </style>';

        // Client
        echo '<strong style="display:block; border-bottom:1px solid #ddd; padding-bottom:3px; margin-bottom:5px;">👤 Données Client</strong>';
        echo '<ul style="margin: 0 0 15px 0; list-style:none;">';
        echo '<li><code class="pc-insert-var" title="Insérer">{prenom_client}</code></li>';
        echo '<li><code class="pc-insert-var" title="Insérer">{nom_client}</code></li>';
        echo '<li><code class="pc-insert-var" title="Insérer">{email_client}</code></li>';
        echo '<li><code class="pc-insert-var" title="Insérer">{telephone}</code></li>';
        echo '</ul>';

        // Séjour
        echo '<strong style="display:block; border-bottom:1px solid #ddd; padding-bottom:3px; margin-bottom:5px;">📅 Données Séjour</strong>';
        echo '<ul style="margin: 0 0 15px 0; list-style:none;">';
        echo '<li><code class="pc-insert-var" title="Insérer">{date_arrivee}</code></li>';
        echo '<li><code class="pc-insert-var" title="Insérer">{date_depart}</code></li>';
        echo '<li><code class="pc-insert-var" title="Insérer">{duree_sejour}</code></li>';
        echo '<li><code class="pc-insert-var" title="Insérer">{logement}</code></li>';
        echo '<li><code class="pc-insert-var" title="Insérer">{numero_resa}</code></li>';
        echo '</ul>';

        // Finances
        echo '<strong style="display:block; border-bottom:1px solid #ddd; padding-bottom:3px; margin-bottom:5px;">💶 Données Financières</strong>';
        echo '<ul style="margin: 0 0 15px 0; list-style:none;">';
        echo '<li><code class="pc-insert-var" title="Insérer">{montant_total}</code></li>';
        echo '<li><code class="pc-insert-var" title="Insérer">{acompte_paye}</code></li>';
        echo '<li><code class="pc-insert-var" title="Insérer">{solde_restant}</code></li>';
        echo '<li style="margin-top:8px; padding-top:5px; border-top:1px dashed #ccc;"><strong>Liens Stripe :</strong></li>';
        echo '<li><code class="pc-insert-var" title="Bloc Acompte">{lien_paiement_acompte}</code></li>';
        echo '<li><code class="pc-insert-var" title="Bloc Solde">{lien_paiement_solde}</code></li>';
        echo '<li><code class="pc-insert-var" title="Bloc Caution">{lien_paiement_caution}</code></li>';
        echo '</ul>';
        echo '</div>';
    }

    /**
     * Gère la requête d'aperçu PDF.
     */
    public function handle_pdf_preview_request()
    {
        if (isset($_GET['pc_action']) && $_GET['pc_action'] === 'preview_pdf' && isset($_GET['id'])) {
            $template_id = (int)$_GET['id'];

            if (!current_user_can('edit_posts')) {
                wp_die('Accès refusé');
            }

            if (class_exists('PCR_Documents')) {
                PCR_Documents::preview($template_id);
            } else {
                wp_die('Erreur : Moteur PDF introuvable.');
            }
            exit;
        }
    }

    /**
     * Script JS pour insérer les variables dans l'éditeur WP au clic.
     */
    public function print_variable_insertion_script()
    {
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type, ['pc_message', 'pc_pdf_template'])) {
            return;
        }

        echo '<script type="text/javascript">
            jQuery(document).ready(function($) {
                $(".pc-insert-var").on("click", function() {
                    var variable = $(this).text();
                    var editorId = "content"; 

                    if (typeof tinymce !== "undefined" && tinymce.get(editorId) && !tinymce.get(editorId).isHidden()) {
                        tinymce.get(editorId).execCommand("mceInsertContent", false, variable);
                    } else {
                        var textarea = document.getElementById(editorId);
                        if (textarea) {
                            var startPos = textarea.selectionStart;
                            var endPos = textarea.selectionEnd;
                            textarea.value = textarea.value.substring(0, startPos) + variable + textarea.value.substring(endPos, textarea.value.length);
                            textarea.selectionStart = textarea.selectionEnd = startPos + variable.length;
                            textarea.focus();
                        }
                    }
                });
            });
        </script>';
    }

    /**
     * Récupère les "Réponses Rapides" (Templates de type quick_reply).
     */
    public function get_quick_replies()
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
            return ['success' => true, 'templates' => [], 'total' => 0, 'message' => 'Aucun modèle trouvé.'];
        }

        $formatted_replies = [];
        $pcr_exists = class_exists('PCR_Fields');
        $has_acf    = function_exists('get_field');

        foreach ($quick_replies as $reply) {
            $category = $pcr_exists ? PCR_Fields::get('pc_message_category', $reply->ID) : ($has_acf ? get_field('pc_message_category', $reply->ID) : '');

            $raw_subject = $pcr_exists ? PCR_Fields::get('pc_msg_subject', $reply->ID) : ($has_acf ? get_field('pc_msg_subject', $reply->ID) : '');
            $subject = $raw_subject ?: $reply->post_title;

            $attachment_key = $pcr_exists ? PCR_Fields::get('pc_msg_attachment', $reply->ID) : ($has_acf ? get_field('pc_msg_attachment', $reply->ID) : '');
            $attachment_name = '';

            if ($attachment_key) {
                $names = [
                    'native_devis' => 'Devis Commercial',
                    'native_facture' => 'Facture',
                    'native_contrat' => 'Contrat'
                ];
                $attachment_name = $names[$attachment_key] ?? 'Document joint';
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
                'attachment_key' => $attachment_key,
                'attachment_name' => $attachment_name
            ];
        }

        return ['success' => true, 'templates' => $formatted_replies, 'total' => count($formatted_replies)];
    }

    /**
     * Récupère un template de réponse rapide spécifique avec variables remplacées.
     */
    public function get_quick_reply_with_vars($template_id, $reservation_id = null)
    {
        $template_id = (int) $template_id;
        if (!$template_id) return ['success' => false, 'message' => 'ID template manquant.'];

        $template = get_post($template_id);
        if (!$template || $template->post_type !== 'pc_message') {
            return ['success' => false, 'message' => 'Template introuvable.'];
        }

        $pcr_exists = class_exists('PCR_Fields');
        $has_acf    = function_exists('get_field');

        $category = $pcr_exists ? PCR_Fields::get('pc_message_category', $template_id) : ($has_acf ? get_field('pc_message_category', $template_id) : '');
        if ($category !== 'quick_reply') {
            return ['success' => false, 'message' => 'Ce template n\'est pas une réponse rapide.'];
        }

        $content = wp_strip_all_tags($template->post_content);

        $raw_subject = $pcr_exists ? PCR_Fields::get('pc_msg_subject', $template_id) : ($has_acf ? get_field('pc_msg_subject', $template_id) : '');
        $subject = $raw_subject ?: $template->post_title;

        if ($reservation_id) {
            $reservation_id = (int) $reservation_id;

            // On utilise la nouvelle façade ou le nouveau service pour zéro régression
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
}
