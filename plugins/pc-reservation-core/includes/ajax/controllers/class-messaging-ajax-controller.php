<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Contrôleur AJAX pour la Messagerie et le Channel Manager.
 */
class PCR_Messaging_Ajax_Controller extends PCR_Base_Ajax_Controller
{
    /**
     * Envoi d'un message manuel (basé sur un template ou libre) avec gestion des pièces jointes.
     */
    public static function ajax_send_message()
    {
        // 1. Sécurité centralisée
        parent::verify_access('pc_resa_manual_create', 'nonce');

        // 2. Données
        $reservation_id = isset($_POST['reservation_id']) ? (int) $_POST['reservation_id'] : 0;
        $template_id    = isset($_POST['template_id']) ? sanitize_text_field($_POST['template_id']) : ''; // Peut être 'custom'

        // Données message libre
        $custom_subject = isset($_POST['custom_subject']) ? sanitize_text_field($_POST['custom_subject']) : '';
        $custom_body    = isset($_POST['custom_body']) ? wp_kses_post($_POST['custom_body']) : '';

        // Support des pièces jointes
        $attachment_path = isset($_POST['attachment_path']) ? sanitize_text_field($_POST['attachment_path']) : '';

        if ($reservation_id <= 0) {
            wp_send_json_error(['message' => 'ID Réservation manquant.']);
        }

        if (!class_exists('PCR_Messaging')) {
            wp_send_json_error(['message' => 'Module Messagerie absent.']);
        }

        // Préparation des arguments pour le message libre
        $custom_args = [];
        if ($template_id === 'custom') {
            if (empty($custom_subject) || empty($custom_body)) {
                wp_send_json_error(['message' => 'Sujet et message requis pour l\'envoi manuel.']);
            }
            $custom_args = [
                'sujet' => $custom_subject,
                'corps' => $custom_body,
                'attachment_path' => $attachment_path
            ];
        } elseif (empty($template_id)) {
            wp_send_json_error(['message' => 'Veuillez choisir un modèle ou écrire un message.']);
        }

        // Gestion des pièces jointes
        $attachments = [];
        $temp_files = []; // Pour nettoyer les fichiers temporaires après envoi

        // 1. Pièce jointe système (Fichier existant OU Code natif)
        if (!empty($attachment_path)) {
            if (file_exists($attachment_path)) {
                $attachments[] = $attachment_path;
            } elseif (strpos($attachment_path, 'native_') === 0 || strpos($attachment_path, 'template_') === 0) {
                $attachments[] = $attachment_path;
            }
        }

        // 2. Pièce jointe uploadée par l'utilisateur
        if (!empty($_FILES['file_upload']) && $_FILES['file_upload']['error'] === UPLOAD_ERR_OK) {
            $uploaded_file = $_FILES['file_upload'];

            // Validation du fichier
            $allowed_types = [
                'application/pdf',
                'image/jpeg',
                'image/jpg',
                'image/png',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ];

            $max_size = 10 * 1024 * 1024; // 10MB

            if ($uploaded_file['size'] > $max_size) {
                wp_send_json_error(['message' => 'Le fichier est trop volumineux. Taille maximum : 10MB']);
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $detected_type = $finfo->file($uploaded_file['tmp_name']);

            if (!in_array($detected_type, $allowed_types)) {
                wp_send_json_error(['message' => 'Type de fichier non supporté.']);
            }

            // Déplacer le fichier vers un répertoire temporaire sécurisé
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/pc-temp-attachments';

            if (!file_exists($temp_dir)) {
                wp_mkdir_p($temp_dir);
            }

            // Nom de fichier sécurisé
            $file_extension = pathinfo($uploaded_file['name'], PATHINFO_EXTENSION);
            $temp_filename = 'temp_' . uniqid() . '.' . $file_extension;
            $temp_path = $temp_dir . '/' . $temp_filename;

            if (move_uploaded_file($uploaded_file['tmp_name'], $temp_path)) {
                $attachments[] = $temp_path;
                $temp_files[] = $temp_path; // Pour suppression ultérieure
            } else {
                wp_send_json_error(['message' => 'Erreur lors du traitement du fichier.']);
            }
        }

        // Ajouter les pièces jointes aux arguments
        if (!empty($attachments)) {
            $custom_args['attachments'] = $attachments;
        }

        // Appel
        $result = PCR_Messaging::send_message($template_id, $reservation_id, false, 'manuel', $custom_args);

        // Nettoyer les fichiers temporaires après l'envoi
        foreach ($temp_files as $temp_file) {
            if (file_exists($temp_file)) {
                unlink($temp_file);
            }
        }

        if (!$result['success']) {
            wp_send_json_error(['message' => $result['message']]);
        }

        // Récupérer le message créé pour l'affichage instantané
        $message_data = null;
        if (class_exists('PCR_Messaging')) {
            $conversation = PCR_Messaging::get_conversation($reservation_id);
            if ($conversation['success'] && !empty($conversation['messages'])) {
                // Prendre le dernier message (le plus récent)
                $messages = $conversation['messages'];
                $message_data = end($messages);
            }
        }

        wp_send_json_success([
            'message' => 'Message envoyé avec succès.',
            'new_message' => $message_data
        ]);
    }

    /**
     * Récupère l'historique d'une conversation.
     */
    public static function ajax_get_conversation_history()
    {
        // Sécurité centralisée
        parent::verify_access('pc_resa_manual_create', 'nonce');

        $reservation_id = isset($_REQUEST['reservation_id']) ? (int) $_REQUEST['reservation_id'] : 0;
        if ($reservation_id <= 0) {
            wp_send_json_error(['message' => 'ID réservation manquant.']);
        }

        if (!class_exists('PCR_Messaging')) {
            wp_send_json_error(['message' => 'Module Messagerie indisponible.']);
        }

        $conversation = PCR_Messaging::get_conversation($reservation_id);

        if (!$conversation['success']) {
            wp_send_json_error(['message' => 'Impossible de charger la conversation.']);
        }

        // Enrichir avec les données de la réservation pour le contexte
        $resa_data = null;
        if (class_exists('PCR_Reservation')) {
            $resa = PCR_Reservation::get_by_id($reservation_id);
            if ($resa) {
                $paid = 0;
                if (class_exists('PCR_Payment')) {
                    global $wpdb;
                    $table_pay = $wpdb->prefix . 'pc_payments';
                    $paid = (float) $wpdb->get_var($wpdb->prepare("SELECT SUM(montant) FROM $table_pay WHERE reservation_id = %d AND statut = 'paye'", $resa->id));
                }
                $total = (float) $resa->montant_total;
                $solde = max(0, $total - $paid);

                $acompte_theorique = (float) ($resa->montant_acompte ?? 0);
                $type_lien = ($paid < $acompte_theorique) ? 'acompte' : 'solde';

                $duree = 0;
                if ($resa->date_arrivee && $resa->date_depart) {
                    $duree = ceil((strtotime($resa->date_depart) - strtotime($resa->date_arrivee)) / 86400);
                }

                $resa_data = [
                    'id' => $resa->id,
                    'prenom' => $resa->prenom,
                    'nom' => $resa->nom,
                    'full_name' => $resa->prenom . ' ' . strtoupper($resa->nom),
                    'email' => $resa->email,
                    'telephone' => $resa->telephone,
                    'statut_reservation' => $resa->statut_reservation,
                    'statut_paiement' => $resa->statut_paiement,
                    'logement' => get_the_title($resa->item_id),
                    'date_arrivee' => date_i18n('d/m/Y', strtotime($resa->date_arrivee)),
                    'date_depart' => date_i18n('d/m/Y', strtotime($resa->date_depart)),
                    'duree_sejour' => $duree . ' nuit(s)',
                    'montant_total' => number_format($total, 2, ',', ' ') . ' €',
                    'acompte_paye' => number_format($paid, 2, ',', ' ') . ' €',
                    'solde_restant' => number_format($solde, 2, ',', ' ') . ' €',
                    'lien_paiement' => home_url('/paiement/?resa=' . $resa->id),
                    'type_lien_paiement' => $type_lien
                ];
            }
        }

        wp_send_json_success([
            'conversation_id' => $conversation['conversation_id'],
            'total_messages' => $conversation['total_messages'],
            'unread_count' => $conversation['unread_count'],
            'messages' => $conversation['messages'],
            'reservation' => $resa_data,
            'design_info' => [
                'supports_channels' => true,
                'available_channels' => ['email', 'airbnb', 'booking', 'sms', 'whatsapp'],
                'css_classes' => [
                    'container' => 'pc-resa-messages-list',
                    'bubble_base' => 'pc-msg-bubble',
                    'host_class' => 'pc-msg--host pc-msg--outgoing',
                    'guest_class' => 'pc-msg--guest pc-msg--incoming',
                    'see_more_class' => 'pc-msg-see-more',
                ],
                'glassmorphism_enabled' => true,
            ]
        ]);
    }

    /**
     * Marque des messages comme lus.
     */
    public static function ajax_mark_messages_read()
    {
        // Sécurité centralisée
        parent::verify_access('pc_resa_manual_create', 'nonce');

        $message_ids = isset($_REQUEST['message_ids']) ? $_REQUEST['message_ids'] : [];

        if (!is_array($message_ids)) {
            $message_ids = [$message_ids];
        }

        $message_ids = array_filter(array_map('intval', $message_ids));
        if (empty($message_ids)) {
            wp_send_json_error(['message' => 'Aucun message à marquer.']);
        }

        if (!class_exists('PCR_Messaging')) {
            wp_send_json_error(['message' => 'Module Messagerie indisponible.']);
        }

        $result = PCR_Messaging::mark_as_read($message_ids);

        if (!$result['success']) {
            wp_send_json_error(['message' => $result['message']]);
        }

        wp_send_json_success([
            'message' => $result['message'],
            'updated_count' => $result['updated_count']
        ]);
    }

    /**
     * Récupère les réponses rapides (templates).
     */
    public static function ajax_get_quick_replies()
    {
        // Sécurité centralisée
        parent::verify_access('pc_resa_manual_create', 'nonce');

        $reservation_id = isset($_REQUEST['reservation_id']) ? (int) $_REQUEST['reservation_id'] : 0;

        if (!class_exists('PCR_Messaging')) {
            wp_send_json_error(['message' => 'Module Messagerie indisponible.']);
        }

        $result = PCR_Messaging::get_quick_replies();

        if (!$result['success']) {
            wp_send_json_error(['message' => $result['message']]);
        }

        $templates = $result['templates'];
        if ($reservation_id > 0 && !empty($templates)) {
            foreach ($templates as &$template) {
                $enriched = PCR_Messaging::get_quick_reply_with_vars($template['id'], $reservation_id);
                if ($enriched['success']) {
                    $template['content_with_vars'] = $enriched['template']['content'];
                    $template['has_variables_replaced'] = true;
                } else {
                    $template['content_with_vars'] = $template['content'];
                    $template['has_variables_replaced'] = false;
                }
            }
        }

        wp_send_json_success([
            'templates' => $templates,
            'total' => $result['total'],
            'message' => $result['message'],
            'reservation_id' => $reservation_id,
            'variables_replaced' => $reservation_id > 0
        ]);
    }
}
