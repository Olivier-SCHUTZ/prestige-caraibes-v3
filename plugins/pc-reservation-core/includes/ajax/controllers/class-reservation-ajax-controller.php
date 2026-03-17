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
        // Vérification du nonce en premier (compatibilité Vue 'security' et legacy 'nonce')
        $nonce_val = $_REQUEST['security'] ?? $_REQUEST['nonce'] ?? '';
        $nonce = sanitize_text_field(wp_unslash($nonce_val));
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
        // Compatibilité Vue 'security' et legacy 'nonce'
        $nonce_val = $_REQUEST['security'] ?? $_REQUEST['nonce'] ?? '';
        $nonce = sanitize_text_field(wp_unslash($nonce_val));
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

    /**
     * NOUVEAU : Récupère la liste des réservations pour le tableau du Dashboard
     */
    public static function ajax_get_reservations_list()
    {
        // 1. Sécurité (On écoute la clé 'security' envoyée par api-client.js)
        parent::verify_access('pc_resa_manual_create', 'security');

        // Vérification de la présence de la classe Repository
        if (!class_exists('PCR_Reservation_Repository')) {
            wp_send_json_error(['message' => 'Le Repository des réservations est introuvable.']);
        }

        // 2. Requête DB via le Repository existant
        $repo = PCR_Reservation_Repository::get_instance();

        // NOUVEAU : Gestion de la pagination (30 par page)
        $per_page = 30;
        $current_page = isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1;
        $offset = ($current_page - 1) * $per_page;

        // NOUVEAU : Récupération du filtre de type
        $type_filter = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $args = [
            'limit'  => $per_page,
            'offset' => $offset
        ];
        if (!empty($type_filter)) {
            $args['type'] = $type_filter; // Ton repository gère déjà ça !
        }

        // On récupère les réservations avec le bon offset et le bon filtre
        $reservations_db = $repo->get_list($args);
        // On récupère le total (en lui passant le type pour que le nombre de pages soit juste)
        $total_count = $repo->get_count($type_filter);

        $formatted_reservations = [];

        foreach ($reservations_db as $resa) {
            // Formatage des dates selon le type
            $dates = '-';
            if ($resa->type === 'location' && !empty($resa->date_arrivee) && !empty($resa->date_depart)) {
                $dates = date_i18n('d/m/Y', strtotime($resa->date_arrivee)) . ' - ' . date_i18n('d/m/Y', strtotime($resa->date_depart));
            } elseif ($resa->type === 'experience' && !empty($resa->date_experience)) {
                $dates = date_i18n('d/m/Y', strtotime($resa->date_experience));
            }

            // Récupération du nom du logement/expérience
            $item_name = 'Inconnu';
            if (!empty($resa->item_id)) {
                $item_name = get_the_title($resa->item_id);
            }

            // Construction de l'objet attendu par notre tableau Vue.js
            $formatted_reservations[] = [
                'id'         => (int) $resa->id,
                'client'     => trim(($resa->prenom ?? '') . ' ' . strtoupper($resa->nom ?? '')),
                'type'       => $resa->type ?? 'location',
                'item_name'  => $item_name,
                'dates'      => $dates,
                'montant'    => number_format((float)($resa->montant_total ?? 0), 2, ',', ' '),
                'statut_reservation' => $resa->statut_reservation ?? 'en_attente_traitement',
                'statut_paiement'    => $resa->statut_paiement ?? 'non_paye'
            ];
        }

        // 3. Réponse
        wp_send_json_success([
            'reservations' => $formatted_reservations,
            'total'        => $total_count
        ]);
    }
    /**
     * Récupère TOUS les détails d'une réservation pour la modale (Vue 3)
     */
    public static function ajax_get_reservation_details()
    {
        parent::verify_access('pc_resa_manual_create', 'security');

        $reservation_id = isset($_POST['reservation_id']) ? (int) $_POST['reservation_id'] : 0;
        if ($reservation_id <= 0) wp_send_json_error(['message' => 'ID invalide.']);

        $repo = PCR_Reservation_Repository::get_instance();
        $resa = $repo->get_by_id($reservation_id);

        if (!$resa) wp_send_json_error(['message' => 'Réservation introuvable.']);

        // 1. Calcul des paiements
        $payments = class_exists('PCR_Payment') ? PCR_Payment::get_for_reservation($resa->id) : [];
        $total_paid = 0;
        foreach ($payments as $pay) {
            if ($pay->statut === 'paye') {
                $total_paid += (float) $pay->montant;
            }
        }
        $total_due = max(0, (float)$resa->montant_total - $total_paid);

        // 2. Lignes du devis
        $quote_lines = !empty($resa->detail_tarif) ? json_decode($resa->detail_tarif, true) : [];

        // 3. Construction des occupants
        $occupants = intval($resa->adultes) . ' adulte(s)';
        if (!empty($resa->enfants)) $occupants .= ' - ' . intval($resa->enfants) . ' enfant(s)';
        if (!empty($resa->bebes)) $occupants .= ' - ' . intval($resa->bebes) . ' bébé(s)';

        // 4. Renvoi du JSON structuré
        wp_send_json_success([
            'id' => $resa->id,
            'client_email' => $resa->email,
            'client_phone' => $resa->telephone,
            'client_lang' => $resa->langue ?? 'fr',
            'client_message' => wp_unslash($resa->commentaire_client ?? ''),
            'notes_internes' => wp_unslash($resa->notes_internes ?? ''),
            'occupants' => $occupants,
            'source' => $resa->source ?? 'direct',
            'montant_total' => (float)$resa->montant_total,
            'total_paye' => $total_paid,
            'total_du' => $total_due,
            'payments' => $payments,
            'quote_lines' => $quote_lines,
            'caution' => [
                'mode' => $resa->caution_mode ?? 'aucune',
                'statut' => $resa->caution_statut ?? 'non_demande',
                'montant' => (float)($resa->caution_montant ?? 0)
            ]
        ]);
    }

    /**
     * Récupère la liste des logements et expériences pour le formulaire de création
     */
    public static function ajax_get_booking_items()
    {
        parent::verify_access('pc_resa_manual_create', 'security');

        // 1. On cherche à la fois dans les villas et les appartements !
        $logements_posts = get_posts([
            'post_type'      => ['villa', 'appartement'], // <-- LA MAGIE EST ICI
            'numberposts'    => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC'
        ]);

        $locations = [];
        foreach ($logements_posts as $post) {
            // Petit bonus : on ajoute le type devant le nom pour que ce soit joli dans la liste
            $type_label = ($post->post_type === 'villa') ? '🏡 Villa' : '🏢 Appart.';
            $locations[] = [
                'id' => $post->ID,
                'title' => $type_label . ' : ' . $post->post_title
            ];
        }

        // 2. On cherche les expériences (si le nom est bien 'experience', sinon dis-le moi !)
        $experiences_posts = get_posts([
            'post_type'      => 'experience',
            'numberposts'    => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC'
        ]);

        $experiences = [];
        foreach ($experiences_posts as $post) {
            $experiences[] = [
                'id' => $post->ID,
                'title' => $post->post_title
            ];
        }

        wp_send_json_success([
            'locations'   => $locations,
            'experiences' => $experiences
        ]);
    }

    /**
     * Calcule le prix (Devis) d'une réservation en direct pour le formulaire Vue 3
     */
    public static function ajax_calculate_price()
    {
        parent::verify_access('pc_resa_manual_create', 'security');

        // Récupération des données envoyées par Vue.js
        $type    = sanitize_text_field($_POST['type'] ?? 'location');
        $item_id = (int) ($_POST['item_id'] ?? 0);
        $adultes = (int) ($_POST['adultes'] ?? 2);
        $enfants = (int) ($_POST['enfants'] ?? 0);
        $bebes   = (int) ($_POST['bebes'] ?? 0);

        if ($item_id === 0) {
            wp_send_json_error(['message' => 'Veuillez sélectionner un logement ou une expérience.']);
        }

        $lines = [];
        $total = 0.0;

        // 🌴 CALCUL POUR LES EXPÉRIENCES (Le vrai moteur !)
        if ($type === 'experience') {
            if (class_exists('PCR_Booking_Pricing_Calculator')) {
                $calc = PCR_Booking_Pricing_Calculator::get_instance();

                // 🚀 NOUVEAU : On écoute enfin le choix du tarif fait par l'utilisateur !
                $experience_tarif_type = sanitize_text_field($_POST['experience_tarif_type'] ?? '');

                // On prépare les données au format attendu par ta classe
                $normalized = [
                    'context' => ['type' => 'experience'],
                    'item'    => [
                        'item_id' => $item_id,
                        'experience_tarif_type' => $experience_tarif_type // 🚀 CORRIGÉ
                    ],
                    'pricing' => ['currency' => 'EUR', 'lines' => [], 'total' => 0],
                    'people'  => [
                        'adultes' => $adultes,
                        'enfants' => $enfants,
                        'bebes'   => $bebes,
                    ]
                ];

                // On lance ton vrai calcul !
                $result = $calc->maybe_autofill_pricing($normalized);

                $lines = $result['pricing']['lines'] ?? [];
                $total = $result['pricing']['total'] ?? 0;
            } else {
                wp_send_json_error(['message' => 'Le calculateur d\'expérience est introuvable sur le serveur.']);
            }
        }
        // 🏠 CALCUL POUR LES LOGEMENTS (Avec notre nouvelle classe !)
        else {
            $date_arrivee = sanitize_text_field($_POST['date_arrivee'] ?? '');
            $date_depart  = sanitize_text_field($_POST['date_depart'] ?? '');

            if (empty($date_arrivee) || empty($date_depart)) {
                wp_send_json_error(['message' => 'Veuillez sélectionner vos dates de séjour.']);
            }

            // On récupère la configuration du logement
            $config = pc_resa_get_logement_pricing_config($item_id);

            if (!$config) {
                wp_send_json_error(['message' => 'Configuration tarifaire introuvable pour ce logement.']);
            }

            // On vérifie que notre nouvelle classe est bien chargée
            // (Assure-toi que ton autoloader charge bien class-housing-pricing-calculator.php ou inclus-le manuellement si besoin)
            if (!class_exists('PCR_Housing_Pricing_Calculator')) {
                wp_send_json_error(['message' => 'Le calculateur de logement (PCR_Housing_Pricing_Calculator) est introuvable.']);
            }

            $calculator = PCR_Housing_Pricing_Calculator::get_instance();

            $args = [
                'date_arrivee' => $date_arrivee,
                'date_depart'  => $date_depart,
                'adults'       => $adultes,
                'children'     => $enfants,
                'infants'      => $bebes,
            ];

            // On lance le calcul robuste
            $result = $calculator->calculate_quote($config, $args);

            if (!$result['success']) {
                wp_send_json_error(['message' => $result['message']]);
            }

            // Si c'est un logement "Sur devis", on adapte la réponse pour court-circuiter le calcul
            if (!empty($result['isSurDevis'])) {
                wp_send_json_success([
                    'montant_total' => 0,
                    'lignes_devis'  => $result['lines']
                ]);
            }

            $lines = $result['lines'];
            $total = $result['total'];
        }

        // On renvoie la réponse finale unifiée (valable pour Logement ET Expérience)
        wp_send_json_success([
            'montant_total' => $total,
            'lignes_devis'  => $lines
        ]);
    }
}
