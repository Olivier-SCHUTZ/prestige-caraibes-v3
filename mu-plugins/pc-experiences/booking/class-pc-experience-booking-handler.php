<?php

/**
 * Gestionnaire des soumissions de formulaire de réservation.
 * Gère la validation, l'anti-spam, l'envoi d'emails et l'intégration au moteur de réservation.
 *
 * @package PC_Experiences
 */

if (!defined('ABSPATH')) {
    exit; // Sécurité
}

class PC_Experience_Booking_Handler
{

    /**
     * Initialise les hooks WordPress pour intercepter les requêtes du formulaire.
     */
    public function __construct()
    {
        add_action('admin_post_nopriv_experience_booking_request', [$this, 'handle_request']);
        add_action('admin_post_experience_booking_request', [$this, 'handle_request']);
    }

    /**
     * Traite la requête POST envoyée par le formulaire de réservation.
     */
    public function handle_request(): void
    {
        // Validation stricte du nonce de sécurité
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'experience_booking_request_nonce')) {
            wp_send_json_error(['message' => 'La vérification de sécurité a échoué.']);
            return;
        }

        // === DÉBUT: VÉRIFICATION ANTI-BOT ===
        // 1. Vérification du Honeypot (si rempli = bot)
        if (!empty($_POST['booking_reason'])) {
            wp_send_json_success(['message' => 'Votre demande a bien été envoyée !']);
            return;
        }

        // 2. Vérification de la présence du devis
        $quote_details_raw = $_POST['quote_details'] ?? '';
        if (empty($quote_details_raw)) {
            wp_send_json_success(['message' => 'Votre demande a bien été envoyée !']);
            return;
        }
        // === FIN: VÉRIFICATION ANTI-BOT ===

        // Nettoyage et sécurisation de toutes les données entrantes
        $experience_id = isset($_POST['experience_id']) ? absint($_POST['experience_id']) : 0;
        $prenom        = sanitize_text_field($_POST['prenom'] ?? '');
        $nom           = sanitize_text_field($_POST['nom'] ?? '');
        $email         = sanitize_email($_POST['email'] ?? '');
        $tel           = sanitize_text_field($_POST['tel'] ?? '');
        $whatsapp      = sanitize_text_field($_POST['whatsapp'] ?? 'Non précisé');
        $quote_details = sanitize_textarea_field($quote_details_raw);
        $message       = sanitize_textarea_field($_POST['message'] ?? '');

        // Validation des champs obligatoires
        if (!$experience_id || empty($prenom) || empty($nom) || !is_email($email)) {
            wp_send_json_error(['message' => 'Veuillez remplir tous les champs obligatoires.']);
            return;
        }

        // ===== NOYAU RÉSERVATION : enregistrement (si plugin actif) =====
        if (class_exists('PCR_Booking_Engine')) {
            $lines_json = isset($_POST['lines_json']) ? wp_kses_post(wp_unslash($_POST['lines_json'])) : '';
            $type_flux  = isset($_POST['type_flux']) && $_POST['type_flux'] === 'devis' ? 'devis' : 'reservation';

            $payload = [
                'context' => [
                    'type'             => 'experience',
                    'origine'          => 'site',
                    'mode_reservation' => 'demande',
                    'type_flux'        => $type_flux,
                    'source'           => 'form_public',
                ],
                'item' => [
                    'item_id'               => $experience_id,
                    'experience_tarif_type' => sanitize_text_field($_POST['devis_type'] ?? ''),
                    'date_experience'       => sanitize_text_field($_POST['date_experience'] ?? ''),
                ],
                'people' => [
                    'adultes' => intval($_POST['devis_adults'] ?? 0),
                    'enfants' => intval($_POST['devis_children'] ?? 0),
                    'bebes'   => intval($_POST['devis_bebes'] ?? 0),
                ],
                'pricing' => [
                    'currency'       => 'EUR',
                    'total'          => isset($_POST['total']) ? (float) $_POST['total'] : 0,
                    'raw_lines_json' => $lines_json,
                    'is_sur_devis'   => !empty($_POST['is_sur_devis']),
                ],
                'customer' => [
                    'prenom'             => $prenom,
                    'nom'                => $nom,
                    'email'              => $email,
                    'telephone'          => $tel,
                    'commentaire_client' => $message,
                ],
            ];

            $booking = PCR_Booking_Engine::create($payload);

            if (!$booking->success) {
                error_log('[PC Booking] Création expérience impossible : ' . implode(', ', $booking->errors));
            }
        }
        // ===== FIN NOYAU RÉSERVATION =====

        // Préparation des emails
        $experience_title = get_the_title($experience_id);
        $headers          = ['Content-Type: text/plain; charset=UTF-8'];

        // Email Admin
        $admin_to      = 'guadeloupe@prestigecaraibes.com';
        $admin_subject = "Nouvelle demande pour l'expérience : " . $experience_title;
        $admin_body    = "Une nouvelle demande de réservation a été effectuée.\n\n";
        $admin_body   .= "Expérience : " . $experience_title . " (ID: " . $experience_id . ")\n\n";
        $admin_body   .= "CLIENT\n";
        $admin_body   .= "--------------------------------\n";
        $admin_body   .= "Prénom et Nom : " . $prenom . " " . $nom . "\n";
        $admin_body   .= "Email : " . $email . "\n";
        $admin_body   .= "Téléphone : " . $tel . "\n";
        $admin_body   .= "Contact WhatsApp OK : " . $whatsapp . "\n\n";
        if (!empty($message)) {
            $admin_body .= "MESSAGE / OBSERVATIONS\n";
            $admin_body .= "--------------------------------\n";
            $admin_body .= $message . "\n\n";
        }
        $admin_body   .= "DÉTAILS DE LA SIMULATION\n";
        $admin_body   .= "--------------------------------\n";
        $admin_body   .= $quote_details . "\n";

        $mail_sent_admin = wp_mail($admin_to, $admin_subject, $admin_body, $headers);

        // Email Client
        $client_subject = "Confirmation de votre demande pour : " . $experience_title;
        $client_body    = "Bonjour " . $prenom . ",\n\n";
        $client_body   .= "Nous avons bien reçu votre demande de réservation pour l'expérience \"" . $experience_title . "\".\n\n";
        $client_body   .= "Nous allons vérifier les disponibilités et nous revenons vers vous dans les plus brefs délais.\n\n";
        $client_body   .= "Cordialement,\n";
        $client_body   .= "L'équipe Prestige Caraïbes";

        wp_mail($email, $client_subject, $client_body, $headers);

        // Réponse JSON pour le frontend
        if ($mail_sent_admin) {
            wp_send_json_success(['message' => 'Votre demande a bien été envoyée ! Nous vous avons également envoyé un email de confirmation.']);
        } else {
            wp_send_json_error(['message' => 'Le serveur n\'a pas pu envoyer l\'email. Veuillez nous contacter directement.']);
        }

        exit;
    }
}
