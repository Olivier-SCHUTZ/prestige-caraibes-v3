<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX côté dashboard front pour création manuelle.
 */
class PCR_Dashboard_Ajax
{
    public static function init()
    {
        add_action('wp_ajax_pc_manual_reservation_create', [__CLASS__, 'handle_manual_reservation']);
        add_action('wp_ajax_nopriv_pc_manual_reservation_create', [__CLASS__, 'handle_manual_reservation']);
    }

    public static function handle_manual_reservation()
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Veuillez vous connecter pour créer une réservation.']);
        }

        if (!self::current_user_can_manage()) {
            wp_send_json_error(['message' => 'Action non autorisée.']);
        }

        check_ajax_referer('pc_resa_manual_create', 'nonce');

        if (!class_exists('PCR_Booking_Engine')) {
            wp_send_json_error(['message' => 'Moteur de réservation indisponible.']);
        }

        $reservation_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $type = sanitize_text_field($_POST['type'] ?? 'experience');

        if ($type !== 'experience') {
            wp_send_json_error(['message' => 'Seules les expériences sont supportées pour le moment.']);
        }

        $item_id = isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0;

        if ($item_id <= 0) {
            wp_send_json_error(['message' => 'Veuillez choisir une expérience.']);
        }

        $mode_reservation = (isset($_POST['mode_reservation']) && $_POST['mode_reservation'] === 'directe') ? 'directe' : 'demande';
        $type_flux        = (isset($_POST['type_flux']) && $_POST['type_flux'] === 'devis') ? 'devis' : 'reservation';

        $remise_label  = sanitize_text_field($_POST['remise_label'] ?? '');
        $remise_amount = isset($_POST['remise_montant']) ? (float) $_POST['remise_montant'] : 0;

        $manual_adjustments = [];
        if ($remise_amount !== 0.0) {
            $manual_adjustments[] = [
                'type'            => 'remise',
                'label'           => $remise_label ? $remise_label : 'Remise exceptionnelle',
                'amount'          => 0 - abs($remise_amount),
                'apply_to_total'  => true,
            ];
        }

        $payload = [
            'context' => [
                'type'             => 'experience',
                'origine'          => 'manuel',
                'mode_reservation' => $mode_reservation,
                'type_flux'        => $type_flux,
                'source'           => 'dashboard',
            ],
            'item' => [
                'item_id'              => $item_id,
                'experience_tarif_type'=> sanitize_text_field($_POST['experience_tarif_type'] ?? ''),
                'date_experience'      => sanitize_text_field($_POST['date_experience'] ?? ''),
                'date_arrivee'         => sanitize_text_field($_POST['date_arrivee'] ?? ''),
                'date_depart'          => sanitize_text_field($_POST['date_depart'] ?? ''),
            ],
            'people' => [
                'adultes' => (int) ($_POST['adultes'] ?? 0),
                'enfants' => (int) ($_POST['enfants'] ?? 0),
                'bebes'   => (int) ($_POST['bebes'] ?? 0),
            ],
            'pricing' => [
                'currency'           => 'EUR',
                'total'              => isset($_POST['montant_total']) ? (float) $_POST['montant_total'] : 0,
                'raw_lines_json'     => isset($_POST['lines_json']) ? wp_kses_post(wp_unslash($_POST['lines_json'])) : '',
                'is_sur_devis'       => ($type_flux === 'devis'),
                'manual_adjustments' => $manual_adjustments,
            ],
            'customer' => [
                'prenom'             => sanitize_text_field($_POST['prenom'] ?? ''),
                'nom'                => sanitize_text_field($_POST['nom'] ?? ''),
                'email'              => sanitize_email($_POST['email'] ?? ''),
                'telephone'          => sanitize_text_field($_POST['telephone'] ?? ''),
                'commentaire_client' => sanitize_textarea_field($_POST['commentaire_client'] ?? ''),
            ],
            'meta' => [
                'numero_devis'   => sanitize_text_field($_POST['numero_devis'] ?? ''),
                'notes_internes' => sanitize_textarea_field($_POST['notes_internes'] ?? ''),
            ],
        ];

        $success_message = 'Réservation créée avec succès.';
        if ($reservation_id > 0) {
            $booking = PCR_Booking_Engine::update($reservation_id, $payload);
            $success_message = 'Réservation mise à jour.';
        } else {
            $booking = PCR_Booking_Engine::create($payload);
        }

        if (!$booking->success) {
            $message = 'Création impossible.';
            if (!empty($booking->errors)) {
                $message = implode(', ', $booking->errors);
            }
            wp_send_json_error(['message' => $message]);
        }

        wp_send_json_success([
            'message'        => $success_message,
            'reservation_id' => $booking->reservation_id,
            'statuts'        => $booking->data['statuts'],
        ]);
    }

    protected static function current_user_can_manage()
    {
        $capability = apply_filters('pc_resa_manual_creation_capability', 'manage_options');
        return current_user_can($capability);
    }
}
