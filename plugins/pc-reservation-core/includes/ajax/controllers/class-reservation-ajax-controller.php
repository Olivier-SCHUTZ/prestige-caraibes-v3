<?php
if (!defined('ABSPATH')) {
    exit;
}

// Chargement de notre nouvelle boîte à outils
require_once __DIR__ . '/class-legacy-utils.php';

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
        $remise_label  = sanitize_text_field(wp_unslash($_POST['remise_label'] ?? ''));
        $remise_amount = isset($_POST['remise_montant']) ? (float) $_POST['remise_montant'] : 0;
        $plus_label    = sanitize_text_field(wp_unslash($_POST['plus_label'] ?? ''));
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
                'prenom'             => sanitize_text_field(wp_unslash($_POST['prenom'] ?? '')),
                'nom'                => sanitize_text_field(wp_unslash($_POST['nom'] ?? '')),
                'email'              => sanitize_email(wp_unslash($_POST['email'] ?? '')),
                'telephone'          => sanitize_text_field(wp_unslash($_POST['telephone'] ?? '')),
                'commentaire_client' => sanitize_textarea_field(wp_unslash($_POST['commentaire_client'] ?? '')),
            ],
            'meta' => [
                'numero_devis'   => sanitize_text_field(wp_unslash($_POST['numero_devis'] ?? '')),
                'notes_internes' => sanitize_textarea_field(wp_unslash($_POST['notes_internes'] ?? '')),
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
     * Récupère la configuration des tarifs d'un logement (Version V2).
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

        // Appel à notre nouvelle boîte à outils !
        $config = PCR_Legacy_Utils::get_pricing_config($logement_id);

        if (empty($config)) {
            wp_send_json_error([
                'message' => 'Impossible de charger les tarifs pour ce logement.',
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
                'statut_paiement'    => $resa->statut_paiement ?? 'non_paye',
                'caution_statut'     => $resa->caution_statut ?? '' // <-- NOUVEAU : On ajoute la caution au colis !
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
     * Phase 2A & 2B : Payloads distincts et épurés selon le type
     */
    public static function ajax_get_reservation_details()
    {
        parent::verify_access('pc_resa_manual_create', 'security');

        $reservation_id = isset($_POST['reservation_id']) ? (int) $_POST['reservation_id'] : 0;
        if ($reservation_id <= 0) wp_send_json_error(['message' => 'ID invalide.']);

        $repo = PCR_Reservation_Repository::get_instance();
        $resa = $repo->get_by_id($reservation_id);

        if (!$resa) wp_send_json_error(['message' => 'Réservation introuvable.']);

        // Phase 2A : Scission des payloads selon le type
        if ($resa->type === 'experience') {
            $payload = self::build_experience_payload($resa);
        } else {
            $payload = self::build_location_payload($resa);
        }

        wp_send_json_success($payload);
    }

    /**
     * Phase 2A : Payload épuré pour les expériences
     */
    private static function build_experience_payload($resa)
    {
        $base = self::build_base_payload($resa);

        // Construction des données structurées pour les expériences
        $structured_experience_data = null;
        if (!empty($resa->detail_tarif)) {
            $structured_experience_data = self::reconstruct_experience_structure($resa);

            // Audit serveur pour debug
            if ($structured_experience_data) {
                error_log(print_r($structured_experience_data, true));
            }
        }

        return array_merge($base, [
            // Spécifique EXPÉRIENCE uniquement
            'structured_experience_data' => $structured_experience_data,
            'raw_tarif_type' => $resa->experience_tarif_type ?? '',
            'raw_date_experience' => $resa->date_experience ?? '',
            // ❌ PAS de caution pour les expériences
            // ❌ PAS de dates logement pour les expériences
        ]);
    }

    /**
     * Phase 2A : Payload épuré pour les logements
     */
    private static function build_location_payload($resa)
    {
        $base = self::build_base_payload($resa);

        // Construction des données de caution (UNIQUEMENT pour les logements)
        $caution_mode = 'aucune';
        if (!empty($resa->item_id) && class_exists('PCR_Payment_Service')) {
            $payment_rules = PCR_Payment_Service::get_instance()->get_item_payment_rules($resa->item_id);
            if (!empty($payment_rules['caution_type'])) {
                $caution_mode = $payment_rules['caution_type'];
            }
        } elseif (!empty($resa->caution_mode)) {
            $caution_mode = $resa->caution_mode; // Fallback legacy
        }

        return array_merge($base, [
            // Spécifique LOGEMENT uniquement
            'caution' => [
                'mode' => $caution_mode,
                'statut' => $resa->caution_statut ?? 'non_demande',
                'montant' => (float)($resa->caution_montant ?? 0),
                'reference' => $resa->caution_reference ?? ''
            ],
            'raw_date_arrivee' => $resa->date_arrivee ?? '',
            'raw_date_depart' => $resa->date_depart ?? '',
            // ❌ PAS de structured_experience_data pour les logements
            // ❌ PAS de raw_tarif_type pour les logements
            // ❌ PAS de date_experience pour les logements
        ]);
    }

    /**
     * Phase 2B : Payload de base commun aux deux types
     */
    private static function build_base_payload($resa)
    {
        // Calcul des paiements
        $payments = class_exists('PCR_Payment') ? PCR_Payment::get_for_reservation($resa->id) : [];
        $total_paid = 0;
        foreach ($payments as $pay) {
            if ($pay->statut === 'paye') {
                $total_paid += (float) $pay->montant;
            }
        }
        $total_due = max(0, (float)$resa->montant_total - $total_paid);

        // Lignes du devis (communes)
        $quote_lines = !empty($resa->detail_tarif) ? json_decode($resa->detail_tarif, true) : [];

        // Construction des occupants
        $occupants = intval($resa->adultes) . ' adulte(s)';
        if (!empty($resa->enfants)) $occupants .= ' - ' . intval($resa->enfants) . ' enfant(s)';
        if (!empty($resa->bebes)) $occupants .= ' - ' . intval($resa->bebes) . ' bébé(s)';

        return [
            // Données communes
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

            // Données communes formulaire (Logement & Expérience)
            'raw_type' => $resa->type ?? 'location',
            'raw_item_id' => (int) $resa->item_id,
            'raw_adultes' => (int) ($resa->adultes ?? 1),
            'raw_enfants' => (int) ($resa->enfants ?? 0),
            'raw_bebes' => (int) ($resa->bebes ?? 0),
            'raw_prenom' => $resa->prenom ?? '',
            'raw_nom' => $resa->nom ?? '',
            'raw_numero_devis' => $resa->numero_devis ?? '',
            'raw_remise_label' => $resa->remise_label ?? '',
            'raw_remise_montant' => isset($resa->remise_montant) ? (float) $resa->remise_montant : 0,
            'raw_plus_label' => $resa->plus_label ?? '',
            'raw_plus_montant' => isset($resa->plus_montant) ? (float) $resa->plus_montant : 0
        ];
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
        $experience_tarifs = [];

        foreach ($experiences_posts as $post) {
            $experiences[] = [
                'id' => $post->ID,
                'title' => $post->post_title
            ];

            // 🚀 NOUVEAU : On récupère et "traduit" les tarifs ACF pour Vue.js
            $tarifs = function_exists('get_field') ? get_field('exp_types_de_tarifs', $post->ID) : [];
            $formatted_tarifs = [];

            if (is_array($tarifs)) {
                foreach ($tarifs as $index => $t) {
                    // 1. Détermination du label et de la clé technique
                    $type = !empty($t['exp_type']) ? $t['exp_type'] : 'custom';
                    $label = 'Tarif ' . ($index + 1);
                    $key = 'tarif_' . $index;

                    if ($type === 'custom' && !empty($t['exp_type_custom'])) {
                        $label = $t['exp_type_custom'];
                        // 🚀 FIX : L'ancien système stockait le label brut en base de données, on l'utilise directement comme clé !
                        $key = $label;
                    } elseif ($type === 'adulte_enfant') {
                        $label = 'Par adulte / enfant';
                        $key = 'adulte_enfant';
                    } elseif ($type === 'forfait') {
                        $label = 'Forfait';
                        $key = 'forfait';
                    }

                    // 2. Formatage des lignes (Quantités, prix, observations)
                    $lines = [];
                    if (!empty($t['exp_tarifs_lignes']) && is_array($t['exp_tarifs_lignes'])) {
                        foreach ($t['exp_tarifs_lignes'] as $l_idx => $l) {
                            $lines[] = [
                                'uid'         => 'line_' . $index . '_' . $l_idx,
                                'type'        => !empty($l['type_ligne']) ? $l['type_ligne'] : 'personnalise',
                                'price'       => (float) ($l['tarif_valeur'] ?? 0),
                                'label'       => $l['tarif_nom_perso'] ?? '',
                                'enable_qty'  => !empty($l['tarif_enable_qty']),
                                'observation' => $l['tarif_observation'] ?? ''
                            ];
                        }
                    }

                    // 3. Formatage des options
                    $options = [];
                    if (!empty($t['exp_options_tarifaires']) && is_array($t['exp_options_tarifaires'])) {
                        foreach ($t['exp_options_tarifaires'] as $o_idx => $o) {
                            $options[] = [
                                'uid'        => 'opt_' . $index . '_' . $o_idx,
                                'label'      => $o['exp_description_option'] ?? '',
                                'price'      => (float) ($o['exp_tarif_option'] ?? 0),
                                'enable_qty' => !empty($o['option_enable_qty'])
                            ];
                        }
                    }

                    // 4. On assemble la ligne de configuration finale
                    $formatted_tarifs[] = [
                        'key'        => $key,
                        'label'      => $label,
                        'code'       => 'standard',
                        'lines'      => $lines,
                        'options'    => $options,
                        'fixed_fees' => []
                    ];
                }
            }
            $experience_tarifs[$post->ID] = $formatted_tarifs;
        }

        wp_send_json_success([
            'locations'   => $locations,
            'experiences' => $experiences,
            'experienceTarifs' => $experience_tarifs
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

            // Appel à notre nouvelle boîte à outils !
            $config = PCR_Legacy_Utils::get_pricing_config($item_id);

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

    /**
     * NOUVEAU : Reconstruit la structure des expériences pour le pré-remplissage
     * Version INDESTRUCTIBLE - Ne retourne jamais null
     * 
     * @param object $resa L'objet réservation depuis la base de données
     * @return array Les données structurées (toujours un tableau valide)
     */
    private static function reconstruct_experience_structure($resa)
    {
        // Valeurs par défaut pour garantir un retour valide
        $default_response = [
            'resolved_tarif_type' => '',
            'customQty' => [],
            'options' => []
        ];

        if (empty($resa->detail_tarif) || empty($resa->item_id)) {
            // Tentative de récupérer le premier tarif disponible comme fallback
            $fallback_tarif = self::get_first_available_tarif($resa->item_id);
            if ($fallback_tarif) {
                $default_response['resolved_tarif_type'] = $fallback_tarif;
            }
            return $default_response;
        }

        // 1. Décoder le JSON des lignes de devis avec nettoyage Unicode
        $quote_lines = json_decode($resa->detail_tarif, true);
        if (!is_array($quote_lines) || empty($quote_lines)) {
            $fallback_tarif = self::get_first_available_tarif($resa->item_id);
            if ($fallback_tarif) {
                $default_response['resolved_tarif_type'] = $fallback_tarif;
            }
            return $default_response;
        }

        // Correction du problème d'encodage Unicode sur les quote_lines
        $quote_lines = self::fix_unicode_in_quote_lines($quote_lines);

        // 2. Résoudre le tarif_type (mapping label -> clé technique ACF)
        $resolved_tarif_type = self::resolve_tarif_type($resa->item_id, $resa->experience_tarif_type ?? '');

        // 🚀 FALLBACK DE LA DERNIÈRE CHANCE
        if (!$resolved_tarif_type) {
            $resolved_tarif_type = self::fallback_resolve_tarif_from_quote_lines($resa->item_id, $quote_lines);
        }

        // Si toujours pas trouvé, on prend le premier disponible
        if (!$resolved_tarif_type) {
            $resolved_tarif_type = self::get_first_available_tarif($resa->item_id);
        }

        // On s'assure d'avoir au minimum un tarif_type
        if (!$resolved_tarif_type) {
            return $default_response;
        }

        // 3. Charger la configuration ACF de l'expérience
        $acf_config = self::get_acf_experience_config($resa->item_id, $resolved_tarif_type);
        if (!$acf_config) {
            // Même si on n'a pas la config, on retourne au moins le tarif_type résolu
            $default_response['resolved_tarif_type'] = $resolved_tarif_type;
            return $default_response;
        }

        // 4. Reconstruire customQty et options basé sur quote_lines
        $customQty = [];
        $options = [];

        foreach ($quote_lines as $line) {
            $mapped = self::map_quote_line_to_acf($line, $acf_config);
            if ($mapped) {
                $qty = isset($line['qty']) ? (int) $line['qty'] : self::extract_qty_from_label($line);

                if ($mapped['type'] === 'line') {
                    $customQty[$mapped['uid']] = $qty;
                } elseif ($mapped['type'] === 'option') {
                    $options[$mapped['uid']] = [
                        'selected' => true,
                        'qty' => $qty
                    ];
                }
            }
        }

        return [
            'resolved_tarif_type' => $resolved_tarif_type,
            'customQty' => $customQty,
            'options' => $options
        ];
    }

    /**
     * Résout le type de tarif (mapping label -> clé technique ACF)
     */
    private static function resolve_tarif_type($item_id, $tarif_type)
    {
        if (empty($tarif_type)) {
            return null;
        }

        // Si c'est déjà une clé technique valide, on la retourne
        if (in_array($tarif_type, ['forfait', 'adulte_enfant']) || strpos($tarif_type, 'tarif_') === 0) {
            return $tarif_type;
        }

        // Sinon, on cherche dans la configuration ACF
        if (!function_exists('get_field')) {
            return null;
        }

        $tarifs = get_field('exp_types_de_tarifs', $item_id);
        if (!is_array($tarifs)) {
            return null;
        }

        $tarif_type_lower = strtolower(trim($tarif_type));

        foreach ($tarifs as $index => $t) {
            $type = !empty($t['exp_type']) ? $t['exp_type'] : 'custom';

            if ($type === 'custom' && !empty($t['exp_type_custom'])) {
                $label_lower = strtolower(trim($t['exp_type_custom']));
                if ($label_lower === $tarif_type_lower) {
                    return $t['exp_type_custom']; // Retourne le label brut comme clé (compatibilité legacy)
                }
            } elseif ($type === 'adulte_enfant') {
                $labels = ['par adulte / enfant', 'adulte enfant', 'adulte/enfant'];
                if (in_array($tarif_type_lower, $labels)) {
                    return 'adulte_enfant';
                }
            } elseif ($type === 'forfait') {
                $labels = ['forfait', 'forfait groupe'];
                if (in_array($tarif_type_lower, $labels)) {
                    return 'forfait';
                }
            }
        }

        return null;
    }

    /**
     * Charge la configuration ACF pour un tarif spécifique
     */
    private static function get_acf_experience_config($item_id, $tarif_type)
    {
        if (!function_exists('get_field')) {
            return null;
        }

        $tarifs = get_field('exp_types_de_tarifs', $item_id);
        if (!is_array($tarifs)) {
            return null;
        }

        foreach ($tarifs as $index => $t) {
            $type = !empty($t['exp_type']) ? $t['exp_type'] : 'custom';
            $key = 'tarif_' . $index;

            if ($type === 'custom' && !empty($t['exp_type_custom'])) {
                $key = $t['exp_type_custom'];
            } elseif ($type === 'adulte_enfant') {
                $key = 'adulte_enfant';
            } elseif ($type === 'forfait') {
                $key = 'forfait';
            }

            if ($key === $tarif_type) {
                return [
                    'index' => $index,
                    'config' => $t
                ];
            }
        }

        return null;
    }

    /**
     * Nettoyeur ultime pour comparer les textes (Legacy BDD vs ACF)
     */
    private static function normalize_for_comparison($string)
    {
        if (empty($string)) return '';
        // 1. Fix des encodages cassés de l'ancienne BDD
        $brokenMap = [
            'u00e9' => 'e',
            'u00e8' => 'e',
            'u00ea' => 'e',
            'u00eb' => 'e',
            'u00e0' => 'a',
            'u00e2' => 'a',
            'u00e4' => 'a',
            'u00ee' => 'i',
            'u00ef' => 'i',
            'u00f4' => 'o',
            'u00f6' => 'o',
            'u00f9' => 'u',
            'u00fb' => 'u',
            'u00fc' => 'u',
            'u00e7' => 'c',
            'u0027' => ' ',
            'u00a0' => ' ',
            'u00d7' => ' '
        ];
        $string = str_ireplace(array_keys($brokenMap), array_values($brokenMap), $string);

        // 2. Minuscules et suppression manuelle des accents
        $string = mb_strtolower($string, 'UTF-8');
        $string = str_replace(['é', 'è', 'ê', 'ë'], 'e', $string);
        $string = str_replace(['à', 'á', 'â', 'ã', 'ä'], 'a', $string);
        $string = str_replace(['î', 'ï', 'í', 'ì'], 'i', $string);
        $string = str_replace(['ô', 'ö', 'ó', 'ò'], 'o', $string);
        $string = str_replace(['û', 'ü', 'ú', 'ù'], 'u', $string);
        $string = str_replace(['ç'], 'c', $string);

        // 3. Remplacer les tirets et parenthèses par des espaces
        $string = str_replace(['-', '_', '/', '\\', '(', ')', '[', ']', '+'], ' ', $string);

        // 4. Nettoyer les espaces multiples
        return trim(preg_replace('/\s+/', ' ', $string));
    }

    /**
     * Mappe une ligne de devis vers les UIDs ACF
     */
    private static function map_quote_line_to_acf($line, $acf_config)
    {
        if (!$acf_config || !isset($line['label']) && !isset($line['clean_label'])) {
            return null;
        }

        // 🚀 FIX PHP : Utilisation du nettoyeur ultime pour gommer 100% des différences
        $line_label = self::normalize_for_comparison($line['clean_label'] ?? $line['label'] ?? '');
        $index = $acf_config['index'];
        $config = $acf_config['config'];

        // 1. Vérifier les lignes principales (tarifs avec quantité)
        if (!empty($config['exp_tarifs_lignes']) && is_array($config['exp_tarifs_lignes'])) {
            foreach ($config['exp_tarifs_lignes'] as $l_idx => $l) {
                if (!empty($l['tarif_enable_qty'])) {
                    $acf_label = self::normalize_for_comparison($l['tarif_nom_perso'] ?? '');

                    if ($acf_label && (
                        $line_label === $acf_label ||
                        strpos($line_label, $acf_label) !== false ||
                        strpos($acf_label, $line_label) !== false
                    )) {
                        return [
                            'type' => 'line',
                            'uid' => 'line_' . $index . '_' . $l_idx
                        ];
                    }
                }
            }
        }

        // 2. Vérifier les options
        if (!empty($config['exp_options_tarifaires']) && is_array($config['exp_options_tarifaires'])) {
            foreach ($config['exp_options_tarifaires'] as $o_idx => $o) {
                $acf_label = self::normalize_for_comparison($o['exp_description_option'] ?? '');

                if ($acf_label && (
                    $line_label === $acf_label ||
                    strpos($line_label, $acf_label) !== false ||
                    strpos($acf_label, $line_label) !== false
                )) {
                    return [
                        'type' => 'option',
                        'uid' => 'opt_' . $index . '_' . $o_idx
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Extrait la quantité depuis un label (rétrocompatibilité)
     */
    private static function extract_qty_from_label($line)
    {
        $label = $line['label'] ?? '';

        // Correction du problème d'encodage Unicode avant extraction
        $label = str_replace('u00d7', '×', $label); // Fix pour le symbole cassé
        $label = str_replace('u00d7', ' ', $label); // Alternative : remplacer par un espace

        // Essaie d'extraire un nombre en début de chaîne "4 x Location" ou "2 Location"
        if (preg_match('/^(\d+)\s*[x×X]?\s*/', $label, $matches)) {
            return (int) $matches[1];
        }

        // Essaie d'extraire "Option x 4" ou "Option × 2"
        if (preg_match('/[x×X]\s*(\d+)$/', $label, $matches)) {
            return (int) $matches[1];
        }

        return 1; // Quantité par défaut
    }

    /**
     * Corrige les problèmes d'encodage Unicode dans les quote_lines
     * Notamment le symbole × qui devient u00d7 en base de données
     */
    private static function fix_unicode_in_quote_lines($quote_lines)
    {
        if (!is_array($quote_lines)) {
            return $quote_lines;
        }

        foreach ($quote_lines as &$line) {
            if (isset($line['label'])) {
                // Correction du symbole de multiplication cassé
                $line['label'] = str_replace('u00d7', '×', $line['label']);

                // Création d'un label "propre" pour la comparaison
                $line['clean_label'] = $line['label'];

                // Suppression des quantités pour avoir un label propre pour la comparaison
                $line['clean_label'] = preg_replace('/^\d+\s*[x×X]?\s*/', '', $line['clean_label']);
                $line['clean_label'] = preg_replace('/\s*[x×X]\s*\d+$/', '', $line['clean_label']);
                $line['clean_label'] = trim($line['clean_label']);
            }
        }

        return $quote_lines;
    }

    /**
     * FALLBACK DE LA DERNIÈRE CHANCE : 
     * Parcourt tous les tarifs ACF et cherche des correspondances avec les quote_lines
     */
    private static function fallback_resolve_tarif_from_quote_lines($item_id, $quote_lines)
    {
        if (empty($item_id) || !function_exists('get_field') || empty($quote_lines)) {
            return null;
        }

        $tarifs = get_field('exp_types_de_tarifs', $item_id);
        if (!is_array($tarifs)) {
            return null;
        }

        // Extraction des labels propres des quote_lines pour la comparaison
        $quote_labels = [];
        foreach ($quote_lines as $line) {
            $clean_label = self::normalize_for_comparison($line['clean_label'] ?? $line['label'] ?? '');
            if (!empty($clean_label)) {
                $quote_labels[] = $clean_label;
            }
        }

        if (empty($quote_labels)) {
            return null;
        }

        // Parcours de TOUS les tarifs ACF pour trouver des correspondances
        foreach ($tarifs as $index => $tarif) {
            $correspondances = 0;
            $total_items = 0;

            // Vérification des lignes de ce tarif
            if (!empty($tarif['exp_tarifs_lignes']) && is_array($tarif['exp_tarifs_lignes'])) {
                foreach ($tarif['exp_tarifs_lignes'] as $ligne) {
                    $total_items++;
                    $acf_label = self::normalize_for_comparison($ligne['tarif_nom_perso'] ?? '');

                    if (!empty($acf_label)) {
                        foreach ($quote_labels as $quote_label) {
                            if (
                                $acf_label === $quote_label ||
                                strpos($quote_label, $acf_label) !== false ||
                                strpos($acf_label, $quote_label) !== false
                            ) {
                                $correspondances++;
                                break; // Une correspondance trouvée pour cette ligne
                            }
                        }
                    }
                }
            }

            // Vérification des options de ce tarif
            if (!empty($tarif['exp_options_tarifaires']) && is_array($tarif['exp_options_tarifaires'])) {
                foreach ($tarif['exp_options_tarifaires'] as $option) {
                    $total_items++;
                    $acf_label = self::normalize_for_comparison($option['exp_description_option'] ?? '');

                    if (!empty($acf_label)) {
                        foreach ($quote_labels as $quote_label) {
                            if (
                                $acf_label === $quote_label ||
                                strpos($quote_label, $acf_label) !== false ||
                                strpos($acf_label, $quote_label) !== false
                            ) {
                                $correspondances++;
                                break; // Une correspondance trouvée pour cette option
                            }
                        }
                    }
                }
            }

            // Si on a trouvé des correspondances significatives (au moins 50% des éléments)
            if ($correspondances > 0 && $total_items > 0 && ($correspondances / $total_items) >= 0.5) {
                // Détermination de la clé de retour
                $type = !empty($tarif['exp_type']) ? $tarif['exp_type'] : 'custom';

                if ($type === 'custom' && !empty($tarif['exp_type_custom'])) {
                    return $tarif['exp_type_custom'];
                } elseif ($type === 'adulte_enfant') {
                    return 'adulte_enfant';
                } elseif ($type === 'forfait') {
                    return 'forfait';
                } else {
                    return 'tarif_' . $index; // Fallback générique
                }
            }
        }

        return null;
    }

    /**
     * Récupère le premier tarif disponible pour une expérience (fallback final)
     */
    private static function get_first_available_tarif($item_id)
    {
        if (empty($item_id) || !function_exists('get_field')) {
            return null;
        }

        $tarifs = get_field('exp_types_de_tarifs', $item_id);
        if (!is_array($tarifs) || empty($tarifs)) {
            return null;
        }

        // On prend le premier tarif disponible
        $first_tarif = reset($tarifs);
        $type = !empty($first_tarif['exp_type']) ? $first_tarif['exp_type'] : 'custom';

        if ($type === 'custom' && !empty($first_tarif['exp_type_custom'])) {
            return $first_tarif['exp_type_custom'];
        } elseif ($type === 'adulte_enfant') {
            return 'adulte_enfant';
        } elseif ($type === 'forfait') {
            return 'forfait';
        } else {
            return 'tarif_0'; // Premier tarif par défaut
        }
    }
}
