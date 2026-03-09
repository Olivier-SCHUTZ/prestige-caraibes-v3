<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Contrôleur AJAX pour la gestion des Réservations (Création, Annulation, Confirmation).
 */
class PCR_Reservation_Ajax_Controller extends PCR_Base_Ajax_Controller
{
    /**
     * Gère la création manuelle (ou devis) d'une réservation.
     */
    public static function handle_manual_reservation()
    {
        // Vérification du nonce en premier
        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'pc_resa_manual_create')) {
            wp_send_json_error(['message' => 'Nonce invalide - veuillez actualiser la page.']);
        }

        // Autorisation : Si connecté, on vérifie les droits. Sinon, on laisse passer (nopriv)
        if (is_user_logged_in() && !parent::current_user_can_manage()) {
            wp_send_json_error(['message' => 'Action non autorisée.']);
        }

        if (!class_exists('PCR_Booking_Engine')) {
            wp_send_json_error(['message' => 'Moteur de réservation indisponible.']);
        }

        $reservation_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $type = sanitize_text_field($_POST['type'] ?? 'experience');
        if (!in_array($type, ['experience', 'location'], true)) {
            wp_send_json_error(['message' => 'Type de réservation inconnu.']);
        }

        $item_id = isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0;
        $existing_resa = null;
        if ($reservation_id > 0 && class_exists('PCR_Reservation')) {
            $existing_resa = PCR_Reservation::get_by_id($reservation_id);
        }

        if ($item_id <= 0) {
            $label = $type === 'location' ? 'logement' : 'expérience';
            wp_send_json_error(['message' => sprintf('Veuillez choisir un %s.', $label)]);
        }

        $experience_tarif_type = sanitize_text_field($_POST['experience_tarif_type'] ?? '');
        if ($type === 'experience' && $experience_tarif_type === '') {
            wp_send_json_error(['message' => 'Sélectionnez un type de tarif pour cette expérience.']);
        }

        $date_experience = sanitize_text_field($_POST['date_experience'] ?? '');
        $date_arrivee    = sanitize_text_field($_POST['date_arrivee'] ?? '');
        $date_depart     = sanitize_text_field($_POST['date_depart'] ?? '');

        if ($type === 'experience') {
            if ($date_experience === '' && $existing_resa && !empty($existing_resa->date_experience)) {
                $date_experience = $existing_resa->date_experience;
            }
        }
        if ($type === 'location' && ($date_arrivee === '' || $date_depart === '')) {
            wp_send_json_error(['message' => 'Indiquez les dates d’arrivée et de départ.']);
        }

        $mode_reservation = (isset($_POST['mode_reservation']) && $_POST['mode_reservation'] === 'directe') ? 'directe' : 'demande';
        $type_flux        = (isset($_POST['type_flux']) && $_POST['type_flux'] === 'devis') ? 'devis' : 'reservation';
        $source_val       = sanitize_text_field($_POST['source'] ?? 'direct');
        $remise_label  = sanitize_text_field($_POST['remise_label'] ?? '');
        $remise_amount = isset($_POST['remise_montant']) ? (float) $_POST['remise_montant'] : 0;
        $plus_label    = sanitize_text_field($_POST['plus_label'] ?? '');
        $plus_amount   = isset($_POST['plus_montant']) ? (float) $_POST['plus_montant'] : 0;

        $manual_adjustments = [];
        if ($remise_amount !== 0.0) {
            $manual_adjustments[] = [
                'type'            => 'remise',
                'label'           => $remise_label ? $remise_label : 'Remise exceptionnelle',
                'amount'          => 0 - abs($remise_amount),
                'apply_to_total'  => true,
            ];
        }
        if ($plus_amount !== 0.0) {
            $manual_adjustments[] = [
                'type'            => 'plus_value',
                'label'           => $plus_label ? $plus_label : 'Plus-value',
                'amount'          => abs($plus_amount),
                'apply_to_total'  => true,
            ];
        }

        $payload = [
            'context' => [
                'type'             => $type,
                'origine'          => 'manuel',
                'mode_reservation' => $mode_reservation,
                'type_flux'        => $type_flux,
                'source'           => $source_val,
            ],
            'item' => [
                'item_id'              => $item_id,
                'experience_tarif_type' => $type === 'experience' ? $experience_tarif_type : '',
                'date_experience'      => $type === 'experience' ? $date_experience : '',
                'date_arrivee'         => $type === 'location' ? $date_arrivee : '',
                'date_depart'          => $type === 'location' ? $date_depart : '',
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

    /**
     * Récupère la configuration des tarifs d'un logement.
     */
    public static function handle_logement_config()
    {
        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'pc_resa_manual_create')) {
            wp_send_json_error([
                'message' => 'Nonce invalide.',
                'code'    => 'invalid_nonce',
            ], 400);
        }

        // Autorisation : Si connecté, on vérifie les droits. Sinon, on laisse passer (nopriv)
        if (is_user_logged_in() && !parent::current_user_can_manage()) {
            wp_send_json_error([
                'message' => 'Action non autorisée.',
                'code'    => 'forbidden',
            ], 403);
        }

        $logement_id = isset($_REQUEST['logement_id']) ? (int) $_REQUEST['logement_id'] : 0;
        if ($logement_id <= 0) {
            wp_send_json_error([
                'message' => 'Logement introuvable.',
                'code'    => 'missing_logement',
            ], 400);
        }

        if (!function_exists('pc_resa_get_logement_pricing_config')) {
            wp_send_json_error([
                'message' => 'Config logement indisponible.',
                'code'    => 'config_unavailable',
            ], 500);
        }

        $config = pc_resa_get_logement_pricing_config($logement_id);
        if (empty($config)) {
            wp_send_json_error([
                'message' => 'Impossible de charger ce logement.',
                'code'    => 'logement_config_empty',
            ], 404);
        }

        wp_send_json_success([
            'config' => $config,
        ]);
    }

    /**
     * Annule une réservation depuis le dashboard.
     */
    public static function ajax_cancel_reservation()
    {
        // Utilisation de notre super vérification stricte
        parent::verify_access('pc_resa_manual_create', 'nonce');

        if (!class_exists('PCR_Booking_Engine')) {
            wp_send_json_error(['message' => 'Moteur de réservation indisponible.'], 500);
        }

        $reservation_id = isset($_POST['reservation_id']) ? (int) $_POST['reservation_id'] : 0;
        if ($reservation_id <= 0) {
            wp_send_json_error(['message' => 'Réservation introuvable.'], 400);
        }

        $result = PCR_Booking_Engine::cancel($reservation_id);

        if (!$result->success) {
            $message = 'Impossible d’annuler la réservation.';
            if (!empty($result->errors)) {
                $message .= ' (' . implode(', ', $result->errors) . ')';
            }
            wp_send_json_error(['message' => $message], 500);
        }

        wp_send_json_success([
            'message' => 'Réservation annulée.',
            'statuts' => $result->data['statuts'] ?? [],
        ]);
    }

    /**
     * Confirme une réservation (passe de "demande/devis" à "réservation confirmée").
     */
    public static function ajax_confirm_reservation()
    {
        // Utilisation de notre super vérification stricte
        parent::verify_access('pc_resa_manual_create', 'nonce');

        $reservation_id = isset($_POST['reservation_id']) ? (int) $_POST['reservation_id'] : 0;
        if ($reservation_id <= 0) {
            wp_send_json_error(['message' => 'ID manquant.'], 400);
        }

        if (!class_exists('PCR_Booking_Engine')) {
            wp_send_json_error(['message' => 'Moteur indisponible.'], 500);
        }

        $payload = [
            'context' => [
                'type_flux'        => 'reservation',
                'mode_reservation' => 'directe',
                'origine'          => 'manuelle',
            ]
        ];

        $result = PCR_Booking_Engine::update($reservation_id, $payload);

        if (!$result->success) {
            wp_send_json_error(['message' => 'Erreur lors de la confirmation.'], 500);
        }

        wp_send_json_success([
            'message' => 'Réservation confirmée avec succès.',
            'statuts' => $result->data['statuts'] ?? [],
        ]);
    }
}
