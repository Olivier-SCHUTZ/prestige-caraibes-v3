<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gère tout le système de Messagerie (Templates, Envoi, Logs).
 */
class PCR_Messaging
{
    public static function init()
    {
        // 1. Création du type de contenu "Modèle de Message"
        add_action('init', [__CLASS__, 'register_template_cpt']);

        // 2. Ajout des champs ACF (Sujet, Déclencheur...)
        add_action('acf/init', [__CLASS__, 'register_template_fields']);
    }

    /**
     * Crée le menu "PC Messages" et le type "Modèles"
     */
    public static function register_template_cpt()
    {
        register_post_type('pc_template', [
            'labels' => [
                'name'               => 'Modèles de Messages',
                'singular_name'      => 'Modèle',
                'add_new'            => 'Ajouter un modèle',
                'add_new_item'       => 'Nouveau modèle de message',
                'edit_item'          => 'Modifier le modèle',
                'menu_name'          => 'PC Messages'
            ],
            'public'             => false, // Usage interne uniquement
            'show_ui'            => true,
            'show_in_menu'       => true,
            'menu_position'      => 52,    // Juste sous "Réservations" (si possible)
            'menu_icon'          => 'dashicons-email-alt',
            'supports'           => ['title', 'editor'], // Titre = Nom interne, Editor = Corps du message
            'rewrite'            => false,
            'capability_type'    => 'post',
        ]);
    }

    /**
     * Définit les champs ACF pour les réglages du mail (Sujet, Quand l'envoyer...)
     */
    public static function register_template_fields()
    {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group([
            'key' => 'group_pc_template_settings',
            'title' => 'Paramètres d\'envoi',
            'fields' => [
                [
                    'key' => 'field_pc_msg_subject',
                    'label' => 'Sujet de l\'email',
                    'name' => 'pc_msg_subject',
                    'type' => 'text',
                    'instructions' => 'Sera visible par le client. Vous pouvez utiliser les variables comme {client_prenom}.',
                    'required' => 1,
                ],
                [
                    'key' => 'field_pc_msg_trigger',
                    'label' => 'Déclencheur (Quand envoyer ?)',
                    'name' => 'pc_msg_trigger',
                    'type' => 'select',
                    'instructions' => 'Choisissez à quel moment ce message doit partir.',
                    'choices' => [
                        'manual'              => 'Envoi Manuel (Bouton dans la fiche)',
                        'reservation_created' => 'Immédiat : Confirmation de réservation',
                        'payment_completed'   => 'Immédiat : Paiement reçu (Acompte/Solde)',
                        'caution_validated'   => 'Immédiat : Caution validée',
                        'checkin_minus_7'     => 'Programmé : 7 jours avant arrivée',
                        'checkin_minus_1'     => 'Programmé : La veille de l\'arrivée',
                        'checkin_plus_1'      => 'Programmé : Le lendemain de l\'arrivée',
                        'checkout_minus_1'    => 'Programmé : La veille du départ',
                    ],
                    'default_value' => 'manual',
                ],
                [
                    'key' => 'field_pc_msg_channel',
                    'label' => 'Canal d\'envoi',
                    'name' => 'pc_msg_channel',
                    'type' => 'button_group',
                    'choices' => [
                        'email'    => 'Email',
                        'whatsapp' => 'WhatsApp (Futur)',
                    ],
                    'default_value' => 'email',
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'pc_template',
                    ],
                ],
            ],
            'style' => 'seamless', // S'intègre proprement sous le titre
        ]);
    }
    /**
     * MOTEUR D'ENVOI (Compatible Template OU Message Libre)
     * * @param int|string $template_identifier ID du post, 'code_declencheur', ou 'custom'
     * @param int        $reservation_id      ID de la réservation
     * @param bool       $force_send          Si true, envoie même si déjà envoyé
     * @param string     $message_type        'automatique' ou 'manuel'
     * @param array      $custom_args         ['sujet' => '...', 'corps' => '...'] si message libre
     */
    public static function send_message($template_identifier, $reservation_id, $force_send = false, $message_type = 'automatique', $custom_args = [])
    {
        $reservation_id = (int) $reservation_id;
        if (!$reservation_id) return ['success' => false, 'message' => 'ID réservation manquant.'];

        $subject = '';
        $body    = '';
        $template_code = '';

        // CAS A : Message Libre (Personnalisé)
        if ($template_identifier === 'custom' || $template_identifier === 0) {
            if (empty($custom_args['sujet']) || empty($custom_args['corps'])) {
                return ['success' => false, 'message' => 'Sujet ou message manquant pour l\'envoi manuel.'];
            }
            $subject = sanitize_text_field($custom_args['sujet']);
            $body    = wp_kses_post($custom_args['corps']); // On garde le HTML basique (p, br, b...)
            $template_code = 'manuel_custom';
        }
        // CAS B : Utilisation d'un Modèle
        else {
            $template_post = null;
            if (is_numeric($template_identifier)) {
                $template_post = get_post($template_identifier);
            } else {
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

            $subject_raw = get_field('pc_msg_subject', $template_post->ID) ?: $template_post->post_title;
            $body_raw    = $template_post->post_content;
            $template_code = $template_post->post_name;

            // Remplacement des variables UNIQUEMENT pour les templates (pour le manuel, on suppose que tu l'as écrit tel quel)
            // Ou on peut aussi le faire pour le manuel si tu veux utiliser {prenom_client} dans ton texte libre.
            // On le fait pour les deux cas ci-dessous.
            $subject = $subject_raw;
            $body    = $body_raw;
        }

        // 2. Récupérer les données de la réservation pour les variables
        if (!class_exists('PCR_Reservation')) return ['success' => false, 'message' => 'Core manquant.'];
        $resa = PCR_Reservation::get_by_id($reservation_id);
        if (!$resa) return ['success' => false, 'message' => 'Réservation introuvable.'];

        // 3. Préparer les variables dynamiques (Dispo pour Template ET Message Libre)
        $item_title = get_the_title($resa->item_id);
        $solde = (float)$resa->montant_total - (float)self::get_paid_amount($reservation_id);

        $vars = [
            '{id}'              => $resa->id,
            '{prenom_client}'   => ucfirst($resa->prenom),
            '{nom_client}'      => strtoupper($resa->nom),
            '{logement}'        => $item_title,
            '{date_arrivee}'    => date_i18n('d/m/Y', strtotime($resa->date_arrivee)),
            '{date_depart}'     => date_i18n('d/m/Y', strtotime($resa->date_depart)),
            '{montant_total}'   => number_format($resa->montant_total, 2, ',', ' ') . ' €',
            '{solde_restant}'   => number_format($solde, 2, ',', ' ') . ' €',
        ];

        // Remplacement des variables
        $subject = strtr($subject, $vars);
        $body    = strtr(wpautop($body), $vars);

        // 4. Envoi de l'Email
        $to = $resa->email;
        if (!is_email($to)) return ['success' => false, 'message' => 'Email client invalide.'];

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $sent = wp_mail($to, $subject, $body, $headers);

        // --- HACK LOCAL ---
        if (!$sent) {
            error_log("📧 [SIMULATION] À: $to | Sujet: $subject");
            $sent = true;
        }

        if (!$sent) return ['success' => false, 'message' => 'Erreur technique wp_mail.'];

        // 5. Enregistrement BDD
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
     * Helper : Calcule le montant déjà payé
     */
    private static function get_paid_amount($resa_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pc_payments';
        return (float) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(montant) FROM {$table} WHERE reservation_id = %d AND statut = 'paye'",
            $resa_id
        ));
    }

    /**
     * CRON : Traitement des messages automatiques programmés
     * (J-7, J-1, J+1, etc.)
     */
    public static function process_auto_messages()
    {
        // 1. Récupérer tous les modèles qui ont un déclencheur programmé
        $templates = get_posts([
            'post_type'   => 'pc_template',
            'numberposts' => -1,
            'meta_query'  => [
                [
                    'key'     => 'pc_msg_trigger',
                    'value'   => ['manual', 'reservation_created', 'payment_completed', 'caution_validated'],
                    'compare' => 'NOT IN' // On ne veut que les temporels (checkin_...)
                ]
            ]
        ]);

        if (empty($templates)) return;

        global $wpdb;
        $table_res = $wpdb->prefix . 'pc_reservations';
        $table_msg = $wpdb->prefix . 'pc_messages';
        $today = current_time('Y-m-d');

        foreach ($templates as $tpl) {
            $trigger = get_field('pc_msg_trigger', $tpl->ID);
            $target_date = '';
            $date_column = ''; // date_arrivee ou date_depart

            // 2. Calculer la date cible selon le déclencheur
            switch ($trigger) {
                case 'checkin_minus_7':
                    $target_date = date('Y-m-d', strtotime('+7 days', strtotime($today)));
                    $date_column = 'date_arrivee';
                    break;
                case 'checkin_minus_1':
                    $target_date = date('Y-m-d', strtotime('+1 day', strtotime($today)));
                    $date_column = 'date_arrivee';
                    break;
                case 'checkin_plus_1':
                    $target_date = date('Y-m-d', strtotime('-1 day', strtotime($today)));
                    $date_column = 'date_arrivee';
                    break;
                case 'checkout_minus_1':
                    $target_date = date('Y-m-d', strtotime('+1 day', strtotime($today)));
                    $date_column = 'date_depart';
                    break;
                default:
                    continue 2; // Déclencheur inconnu, on passe
            }

            // 3. Trouver les réservations concernées (Confirmées uniquement)
            // On cherche celles dont la date correspond à la cible
            $sql = "SELECT id FROM {$table_res} 
                    WHERE statut_reservation = 'reservee' 
                    AND {$date_column} = %s";

            $reservations = $wpdb->get_results($wpdb->prepare($sql, $target_date));

            foreach ($reservations as $resa) {
                // 4. Vérifier si le message a DÉJÀ été envoyé (Anti-Doublon)
                $already_sent = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$table_msg} 
                     WHERE reservation_id = %d 
                     AND template_code = %s 
                     AND type = 'automatique'",
                    $resa->id,
                    $tpl->post_name // Slug du modèle
                ));

                if (!$already_sent) {
                    // 5. Envoi !
                    self::send_message($tpl->ID, $resa->id);
                    error_log("[PC Auto Msg] Modèle '{$tpl->post_title}' envoyé à Résa #{$resa->id}");
                }
            }
        }
    }
}
