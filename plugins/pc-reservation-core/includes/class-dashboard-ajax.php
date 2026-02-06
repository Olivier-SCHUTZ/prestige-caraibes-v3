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
        // 1. Calendrier & Réservations
        add_action('wp_ajax_pc_manual_reservation_create', [__CLASS__, 'handle_manual_reservation']);
        add_action('wp_ajax_nopriv_pc_manual_reservation_create', [__CLASS__, 'handle_manual_reservation']);
        add_action('wp_ajax_pc_manual_logement_config', [__CLASS__, 'handle_logement_config']);
        add_action('wp_ajax_nopriv_pc_manual_logement_config', [__CLASS__, 'handle_logement_config']);
        add_action('wp_ajax_pc_get_calendar_global', [__CLASS__, 'ajax_get_calendar_global']);
        add_action('wp_ajax_nopriv_pc_get_calendar_global', [__CLASS__, 'ajax_get_calendar_global']);
        add_action('wp_ajax_pc_get_global_calendar', [__CLASS__, 'ajax_get_global_calendar']);
        add_action('wp_ajax_nopriv_pc_get_global_calendar', [__CLASS__, 'ajax_get_global_calendar']);
        add_action('wp_ajax_pc_get_single_calendar', [__CLASS__, 'ajax_get_single_calendar']);
        add_action('wp_ajax_nopriv_pc_get_single_calendar', [__CLASS__, 'ajax_get_single_calendar']);
        add_action('wp_ajax_pc_calendar_create_block', [__CLASS__, 'ajax_calendar_create_block']);
        add_action('wp_ajax_pc_calendar_delete_block', [__CLASS__, 'ajax_calendar_delete_block']);
        add_action('wp_ajax_pc_cancel_reservation', [__CLASS__, 'ajax_cancel_reservation']);

        // 2. Actions Confirmations & Messages
        add_action('wp_ajax_pc_confirm_reservation', [__CLASS__, 'ajax_confirm_reservation']);
        add_action('wp_ajax_pc_send_message', [__CLASS__, 'ajax_send_message']);

        // ✨ 2.1 NOUVEAUX ENDPOINTS CHANNEL MANAGER
        add_action('wp_ajax_pc_get_conversation_history', [__CLASS__, 'ajax_get_conversation_history']);
        add_action('wp_ajax_pc_mark_messages_read', [__CLASS__, 'ajax_mark_messages_read']);
        add_action('wp_ajax_pc_get_quick_replies', [__CLASS__, 'ajax_get_quick_replies']);

        // ✨ 2.2 NOUVEAU ENDPOINT PHASE 4 - PIÈCES JOINTES
        add_action('wp_ajax_pc_get_reservation_files', [__CLASS__, 'ajax_get_reservation_files']);

        // 3. API DOCUMENTS (Le correctif final)
        // On connecte les actions AJAX directement aux méthodes statiques de la classe Documents
        add_action('wp_ajax_pc_get_documents_templates', [__CLASS__, 'ajax_get_documents_templates']);
        add_action('wp_ajax_pc_get_documents_list', ['PCR_Documents', 'ajax_get_documents_list']);
        add_action('wp_ajax_pc_generate_document', ['PCR_Documents', 'ajax_generate_document']);
    }

    public static function handle_manual_reservation()
    {
        // 🔧 SOLUTION : Même logique que pour handle_logement_config
        // Vérification du nonce en premier
        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'pc_resa_manual_create')) {
            wp_send_json_error(['message' => 'Nonce invalide - veuillez actualiser la page.']);
        }

        // Autorisation basée sur le nonce valide (même principe que pour logement_config)
        if (is_user_logged_in() && !self::current_user_can_manage()) {
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

    public static function handle_logement_config()
    {
        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'pc_resa_manual_create')) {
            wp_send_json_error([
                'message' => 'Nonce invalide.',
                'code'    => 'invalid_nonce',
            ], 400);
        }

        // 🔧 SOLUTION : Autorisation basée sur le nonce valide
        // Le nonce prouve que l'utilisateur était connecté lors de sa génération
        // même si la session AJAX ne maintient pas is_user_logged_in()
        if (is_user_logged_in() && !self::current_user_can_manage()) {
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

    protected static function current_user_can_manage()
    {
        $capability = apply_filters('pc_resa_manual_creation_capability', 'manage_options');
        return current_user_can($capability);
    }

    /**
     * Retourne le planning global (logements + événements) pour un mois donné.
     */
    public static function ajax_get_global_calendar()
    {
        self::ajax_get_calendar_global();
    }

    /**
     * Retourne le planning global (logements + événements) pour un mois donné.
     */
    public static function ajax_get_calendar_global()
    {
        self::assert_calendar_access();

        $month = isset($_REQUEST['month']) ? (int) $_REQUEST['month'] : (int) current_time('n');
        $year  = isset($_REQUEST['year']) ? (int) $_REQUEST['year'] : (int) current_time('Y');
        $range = self::get_month_range($month, $year);

        $logements = self::get_calendar_logements();
        $logements_count = is_array($logements) ? count($logements) : 0;
        error_log(sprintf('[PC CALENDAR] Global request %02d/%d | logements=%d', $range['month'], $range['year'], $logements_count));

        if (empty($logements)) {
            wp_send_json_success([
                'month'     => $range['month'],
                'year'      => $range['year'],
                'start_date' => $range['start'],
                'end_date'  => $range['end'],
                'extended_end' => $range['extended_end'],
                'logements' => [],
                'events'    => [],
            ]);
        }

        $logement_ids = array_map('intval', array_column($logements, 'id'));
        $raw_events = self::build_calendar_events($logement_ids, $range['start'], $range['extended_end']);
        $events = self::normalize_events($raw_events);

        $events_count = is_array($events) ? count($events) : 0;
        error_log(sprintf('[PC CALENDAR] Global payload %02d/%d | logements=%d | events=%d', $range['month'], $range['year'], $logements_count, $events_count));

        wp_send_json_success([
            'month'     => $range['month'],
            'year'      => $range['year'],
            'start_date' => $range['start'],
            'end_date'  => $range['end'],
            'extended_end' => $range['extended_end'],
            'logements' => $logements,
            'events'    => $events,
        ]);
    }

    /**
     * Retourne le calendrier d'un logement spécifique.
     */
    public static function ajax_get_single_calendar()
    {
        self::assert_calendar_access();

        $logement_id = isset($_REQUEST['logement_id']) ? (int) $_REQUEST['logement_id'] : 0;
        if ($logement_id <= 0) {
            wp_send_json_error(['message' => 'Logement introuvable.'], 400);
        }

        $month = isset($_REQUEST['month']) ? (int) $_REQUEST['month'] : (int) current_time('n');
        $year  = isset($_REQUEST['year']) ? (int) $_REQUEST['year'] : (int) current_time('Y');
        $range = self::get_month_range($month, $year);

        $logements = self::get_calendar_logements();
        $known_ids = array_column($logements, 'id');
        if (!in_array($logement_id, $known_ids, true)) {
            wp_send_json_error(['message' => 'Ce logement n’est pas actif.'], 404);
        }

        $events = self::build_calendar_events([$logement_id], $range['start'], $range['extended_end']);
        $events = self::normalize_events($events);
        $events_count = is_array($events) ? count($events) : 0;
        error_log(sprintf('[PC CALENDAR] Single payload logement=%d | events=%d', $logement_id, $events_count));

        $title = '';
        foreach ($logements as $lg) {
            if ((int) $lg['id'] === $logement_id) {
                $title = $lg['title'];
                break;
            }
        }

        wp_send_json_success([
            'month'    => $range['month'],
            'year'     => $range['year'],
            'start_date' => $range['start'],
            'end_date'   => $range['end'],
            'extended_end' => $range['extended_end'],
            'logement' => [
                'id'    => $logement_id,
                'title' => $title,
            ],
            'events'   => $events,
        ]);
    }

    /**
     * Crée un blocage manuel sur un logement pour une plage de dates.
     */
    public static function ajax_calendar_create_block()
    {
        self::assert_calendar_access();

        $logement_id = isset($_POST['logement_id']) ? (int) $_POST['logement_id'] : 0;
        $start_date  = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $end_date    = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';

        if ($logement_id <= 0 || $start_date === '' || $end_date === '') {
            wp_send_json_error(['message' => 'Logement ou dates invalides.'], 400);
        }

        // Validation du format de date (Y-m-d)
        $start_dt = \DateTime::createFromFormat('Y-m-d', $start_date);
        $end_dt   = \DateTime::createFromFormat('Y-m-d', $end_date);
        if (!$start_dt || !$end_dt || $start_dt > $end_dt) {
            wp_send_json_error(['message' => 'Plage de dates invalide.'], 400);
        }

        global $wpdb;
        $table   = $wpdb->prefix . 'pc_unavailabilities';
        $now     = current_time('mysql');
        $user_id = get_current_user_id();

        $inserted = $wpdb->insert(
            $table,
            [
                'item_id'       => $logement_id,
                'date_debut'    => $start_date,
                'date_fin'      => $end_date,
                'type_source'   => 'manuel',
                'motif'         => 'Blocage manuel via calendrier',
                'date_creation' => $now,
                'date_maj'      => $now,
                'user_id'       => $user_id > 0 ? $user_id : null,
            ],
            [
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
            ]
        );

        if (false === $inserted) {
            wp_send_json_error(['message' => 'Erreur lors de la création du blocage.'], 500);
        }

        $block_id = (int) $wpdb->insert_id;

        wp_send_json_success([
            'id'          => $block_id,
            'logement_id' => $logement_id,
            'start_date'  => $start_date,
            'end_date'    => $end_date,
        ]);
    }

    /**
     * Supprime un blocage manuel existant.
     */
    public static function ajax_calendar_delete_block()
    {
        self::assert_calendar_access();

        $block_id = isset($_POST['block_id']) ? (int) $_POST['block_id'] : 0;
        if ($block_id <= 0) {
            wp_send_json_error(['message' => 'Blocage introuvable.'], 400);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'pc_unavailabilities';

        // Vérifier que le blocage existe et est bien de type manuel
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, type_source FROM {$table} WHERE id = %d",
                $block_id
            )
        );

        if (!$row) {
            wp_send_json_error(['message' => 'Blocage introuvable.'], 404);
        }
        if ((string) $row->type_source !== 'manuel') {
            wp_send_json_error(['message' => 'Ce blocage ne peut pas être supprimé.'], 400);
        }

        $deleted = $wpdb->delete($table, ['id' => $block_id], ['%d']);

        if (false === $deleted) {
            wp_send_json_error(['message' => 'Erreur lors de la suppression du blocage.'], 500);
        }

        wp_send_json_success(['deleted' => true]);
    }

    /**
     * Annule une réservation depuis le dashboard.
     */
    public static function ajax_cancel_reservation()
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Veuillez vous connecter.'], 403);
        }

        if (!self::current_user_can_manage()) {
            wp_send_json_error(['message' => 'Action non autorisée.'], 403);
        }

        // On réutilise le même nonce que pour la création manuelle
        check_ajax_referer('pc_resa_manual_create', 'nonce');

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
        if (!is_user_logged_in() || !self::current_user_can_manage()) {
            wp_send_json_error(['message' => 'Action non autorisée.'], 403);
        }

        check_ajax_referer('pc_resa_manual_create', 'nonce');

        $reservation_id = isset($_POST['reservation_id']) ? (int) $_POST['reservation_id'] : 0;
        if ($reservation_id <= 0) {
            wp_send_json_error(['message' => 'ID manquant.'], 400);
        }

        if (!class_exists('PCR_Booking_Engine')) {
            wp_send_json_error(['message' => 'Moteur indisponible.'], 500);
        }

        // Action simple du "Chef de Gare" : Je confirme.
        // On passe en "directe" / "reservation" pour que le moteur applique "reservee".
        $payload = [
            'context' => [
                'type_flux'        => 'reservation',
                'mode_reservation' => 'directe',
                'origine'          => 'manuelle',
            ]
        ];

        // Hydratation via PCR_Booking_Engine::update pour ne pas perdre les données
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
     * Vérifie le nonce + la connexion + la capacité de gestion.
     */
    protected static function assert_calendar_access()
    {
        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'pc_dashboard_calendar')) {
            wp_send_json_error(['message' => 'Nonce invalide.'], 400);
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Veuillez vous connecter.'], 403);
        }

        if (!self::current_user_can_manage()) {
            wp_send_json_error(['message' => 'Action non autorisée.'], 403);
        }
    }

    /**
     * Normalise une plage {start, end} pour un mois/année donnés.
     */
    protected static function get_month_range($month, $year)
    {
        $month = max(1, min(12, (int) $month));
        $year  = max(2000, min(2100, (int) $year));

        $start = sprintf('%04d-%02d-01', $year, $month);
        $end   = date('Y-m-t', strtotime($start));
        $extended_end = date('Y-m-d', strtotime($end . ' +15 days'));

        return [
            'month' => $month,
            'year'  => $year,
            'start' => $start,
            'end'   => $end,
            'extended_end' => $extended_end,
        ];
    }

    /**
     * Liste les logements actifs (CPT logement/villa/appartement).
     *
     * @return array
     */
    protected static function get_calendar_logements()
    {
        $args = [
            'post_type'      => ['logement', 'villa', 'appartement'],
            'post_status'    => ['publish', 'pending'],
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
            // FILTRE : On exclut ceux qui ont 'mode_reservation' == 'log_channel'
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => 'mode_reservation',
                    'compare' => 'NOT EXISTS', // Garde ceux qui n'ont pas encore le champ
                ],
                [
                    'key'     => 'mode_reservation',
                    'value'   => 'log_channel',
                    'compare' => '!=', // Garde ceux qui ont le champ mais PAS à 'log_channel'
                ],
            ],
        ];

        $posts = get_posts($args);

        if (empty($posts)) {
            return [];
        }

        $logements = [];
        foreach ($posts as $pid) {
            $title = get_the_title($pid);
            if (!$title) {
                continue;
            }
            $logements[] = [
                'id'    => (int) $pid,
                'title' => $title,
            ];
        }

        return $logements;
    }

    /**
     * Récupère les événements (réservations/bloquages) depuis le cache iCal pour un logement.
     *
     * @param int    $logement_id
     * @param string $start_date
     * @param string $end_date
     * @return array
     */
    protected static function get_cached_events_for_logement($logement_id, $start_date, $end_date)
    {
        $logement_id = (int) $logement_id;
        if ($logement_id <= 0) {
            return [];
        }

        $dates = get_post_meta($logement_id, '_booked_dates_cache', true);

        if (!is_array($dates) || empty($dates)) {
            return [];
        }

        return self::convert_dates_to_ranges($dates, $logement_id, $start_date, $end_date, 'ical_cache');
    }

    /**
     * Construit tous les événements pour le calendrier.
     *
     * @param array  $logement_ids
     * @param string $start_date
     * @param string $end_date
     * @return array
     */
    protected static function build_calendar_events(array $logement_ids, $start_date, $end_date)
    {
        if (empty($logement_ids)) {
            return [];
        }

        $events = [];

        $events = array_merge(
            $events,
            self::get_reservation_events($logement_ids, $start_date, $end_date),
            self::get_manual_blocking_events($logement_ids, $start_date, $end_date),
            self::get_ical_events($logement_ids, $start_date, $end_date)
        );

        usort($events, function ($a, $b) {
            return strcmp($a['start'], $b['start']);
        });

        return $events;
    }

    /**
     * Normalise les événements pour le front (noms de clés homogènes).
     *
     * @param array $raw_events
     * @return array
     */
    protected static function normalize_events(array $raw_events)
    {
        $normalized = [];
        foreach ($raw_events as $event) {
            $normalized[] = [
                'logement_id'    => (int) ($event['item_id'] ?? 0),
                'start_date'     => isset($event['start']) ? substr((string) $event['start'], 0, 10) : '',
                'end_date'       => isset($event['end']) ? substr((string) $event['end'], 0, 10) : '',
                'source'         => self::normalize_event_source($event),
                'block_id'       => (isset($event['id']) && (($event['type'] ?? '') === 'blocking')) ? (int) $event['id'] : 0,
                'type'           => $event['type'] ?? '',
                'status'         => $event['status'] ?? '',
                // [FIX] On laisse passer les infos de paiement et de texte pour le calendrier
                'payment_status' => $event['payment_status'] ?? '',
                'label'          => $event['label'] ?? '',
            ];
        }

        return $normalized;
    }

    /**
     * Uniformise la source d'un événement.
     *
     * @param array $event
     * @return string
     */
    protected static function normalize_event_source(array $event)
    {
        if (!empty($event['source'])) {
            $src = (string) $event['source'];

            // Normalisation des différentes variantes
            if ($src === 'ical_cache') {
                return 'ical';
            }
            if ($src === 'manuel' || $src === 'manual') {
                return 'manual';
            }

            return $src;
        }

        $type = isset($event['type']) ? (string) $event['type'] : '';
        if ($type === 'reservation') {
            return 'reservation';
        }
        if ($type === 'blocking') {
            return 'manual';
        }

        return 'unknown';
    }

    /**
     * Événements issus des réservations confirmées.
     */
    protected static function get_reservation_events(array $logement_ids, $start_date, $end_date)
    {
        global $wpdb;

        $ids_placeholder = implode(',', array_fill(0, count($logement_ids), '%d'));
        $table = $wpdb->prefix . 'pc_reservations';

        // [FIX] Récupération nom + paiement pour affichage
        $sql = "
            SELECT id, item_id, date_arrivee, date_depart, statut_reservation, statut_paiement, nom, prenom
            FROM {$table}
            WHERE type = %s
              AND statut_reservation = %s
              AND item_id IN ({$ids_placeholder})
              AND date_arrivee IS NOT NULL
              AND date_depart IS NOT NULL
              AND date_depart >= %s
              AND date_arrivee <= %s
        ";

        $params = array_merge(['location', 'reservee'], $logement_ids, [$start_date, $end_date]);
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params));

        $events = [];
        foreach ((array) $rows as $row) {
            // Construction du Label : "Nom Prénom (#ID)"
            $nom = !empty($row->nom) ? strtoupper($row->nom) : 'Client';
            $prenom = !empty($row->prenom) ? $row->prenom : '';
            $label = trim("$nom $prenom (#{$row->id})");

            $events[] = [
                'item_id'        => (int) $row->item_id,
                'type'           => 'reservation',
                'start'          => sanitize_text_field($row->date_arrivee),
                'end'            => sanitize_text_field($row->date_depart),
                'status'         => sanitize_text_field($row->statut_reservation),
                'payment_status' => sanitize_text_field($row->statut_paiement), // Info vitale pour la couleur
                'label'          => $label, // Info vitale pour le texte
            ];
        }

        return $events;
    }

    /**
     * Événements issus des blocages manuels (table pc_unavailabilities).
     */
    protected static function get_manual_blocking_events(array $logement_ids, $start_date, $end_date)
    {
        global $wpdb;

        $ids_placeholder = implode(',', array_fill(0, count($logement_ids), '%d'));
        $table = $wpdb->prefix . 'pc_unavailabilities';
        $sql = "
            SELECT id, item_id, date_debut, date_fin, type_source
            FROM {$table}
            WHERE item_id IN ({$ids_placeholder})
              AND date_fin >= %s
              AND date_debut <= %s
        ";

        $params = array_merge($logement_ids, [$start_date, $end_date]);
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params));

        $events = [];
        foreach ((array) $rows as $row) {
            $events[] = [
                'id'      => (int) $row->id,
                'item_id' => (int) $row->item_id,
                'type'    => 'blocking',
                'start'   => sanitize_text_field($row->date_debut),
                'end'     => sanitize_text_field($row->date_fin),
                'source'  => sanitize_text_field($row->type_source),
            ];
        }

        return $events;
    }

    /**
     * Événements issus des calendriers iCal (aucune donnée sensible renvoyée).
     */
    protected static function get_ical_events(array $logement_ids, $start_date, $end_date)
    {
        if (!function_exists('get_field')) {
            return [];
        }

        $events = [];

        foreach ($logement_ids as $logement_id) {
            $logement_id = (int) $logement_id;
            $cached_dates = get_post_meta($logement_id, '_booked_dates_cache', true);

            if (is_array($cached_dates) && !empty($cached_dates)) {
                $events = array_merge(
                    $events,
                    self::convert_dates_to_ranges($cached_dates, $logement_id, $start_date, $end_date, 'ical_cache')
                );
                continue;
            }

            $ical_url = (string) get_field('ical_url', $logement_id);
            if ($ical_url === '') {
                continue;
            }

            $ranges = self::fetch_ical_ranges($ical_url);
            if (empty($ranges)) {
                continue;
            }

            foreach ($ranges as $range) {
                $start = isset($range['start']) ? substr($range['start'], 0, 10) : '';
                $end   = isset($range['end']) ? substr($range['end'], 0, 10) : '';
                if (!self::range_overlaps($start, $end, $start_date, $end_date)) {
                    continue;
                }
                $events[] = [
                    'item_id' => $logement_id,
                    'type'    => 'blocking',
                    'start'   => $start,
                    'end'     => $end,
                    'source'  => 'ical',
                ];
            }
        }

        return $events;
    }

    /**
     * Récupère et met en cache les ranges d'un flux iCal.
     *
     * @param string $ical_url
     * @return array
     */
    protected static function fetch_ical_ranges($ical_url)
    {
        $cache_key = 'pc_ics_ranges_' . md5($ical_url);
        $ranges = get_transient($cache_key);
        if ($ranges !== false) {
            return is_array($ranges) ? $ranges : [];
        }

        $body = '';
        if (function_exists('wp_remote_get')) {
            $resp = wp_remote_get($ical_url, ['timeout' => 10]);
            if (!is_wp_error($resp) && 200 === wp_remote_retrieve_response_code($resp)) {
                $body = (string) wp_remote_retrieve_body($resp);
            }
        }

        if ($body === '' || !function_exists('pc_parse_ics_ranges')) {
            set_transient($cache_key, [], HOUR_IN_SECONDS);
            return [];
        }

        $ranges = pc_parse_ics_ranges($body);
        if (!is_array($ranges)) {
            $ranges = [];
        }

        set_transient($cache_key, $ranges, 2 * HOUR_IN_SECONDS);

        return $ranges;
    }

    /**
     * Vérifie le chevauchement de deux plages de dates (YYYY-mm-dd).
     */
    protected static function range_overlaps($start_a, $end_a, $start_b, $end_b)
    {
        if (!$start_a || !$end_a || !$start_b || !$end_b) {
            return false;
        }

        $a_start = strtotime($start_a);
        $a_end   = strtotime($end_a);
        $b_start = strtotime($start_b);
        $b_end   = strtotime($end_b);

        return ($a_start <= $b_end) && ($a_end >= $b_start);
    }

    /**
     * Convertit un tableau de dates (YYYY-mm-dd) en plages contiguës.
     *
     * @param array  $dates
     * @param int    $logement_id
     * @param string $start_date
     * @param string $end_date
     * @param string $source
     * @return array
     */
    protected static function convert_dates_to_ranges(array $dates, $logement_id, $start_date, $end_date, $source = 'ical')
    {
        $filtered = [];
        foreach ($dates as $d) {
            $d = sanitize_text_field($d);
            if ($d && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                if ($d >= $start_date && $d <= $end_date) {
                    $filtered[] = $d;
                }
            }
        }

        if (empty($filtered)) {
            return [];
        }

        sort($filtered);

        $events = [];
        $range_start = null;
        $range_end = null;

        foreach ($filtered as $date) {
            if ($range_start === null) {
                $range_start = $date;
                $range_end   = $date;
                continue;
            }

            $expected = date('Y-m-d', strtotime($range_end . ' +1 day'));
            if ($date === $expected) {
                $range_end = $date;
            } else {
                $events[] = [
                    'item_id' => (int) $logement_id,
                    'type'    => 'blocking',
                    'start'   => $range_start,
                    'end'     => $range_end,
                    'source'  => $source,
                ];
                $range_start = $date;
                $range_end   = $date;
            }
        }

        if ($range_start !== null) {
            $events[] = [
                'item_id' => (int) $logement_id,
                'type'    => 'blocking',
                'start'   => $range_start,
                'end'     => $range_end,
                'source'  => $source,
            ];
        }

        return $events;
    }

    /**
     * Envoi d'un message manuel (basé sur un template).
     */
    public static function ajax_send_message()
    {
        // 1. Sécurité
        check_ajax_referer('pc_resa_manual_create', 'nonce');
        if (!is_user_logged_in() || !self::current_user_can_manage()) {
            wp_send_json_error(['message' => 'Action non autorisée.']);
        }

        // 2. Données
        $reservation_id = isset($_POST['reservation_id']) ? (int) $_POST['reservation_id'] : 0;
        $template_id    = isset($_POST['template_id']) ? sanitize_text_field($_POST['template_id']) : ''; // Peut être 'custom'

        // Données message libre
        $custom_subject = isset($_POST['custom_subject']) ? sanitize_text_field($_POST['custom_subject']) : '';
        $custom_body    = isset($_POST['custom_body']) ? wp_kses_post($_POST['custom_body']) : '';

        // ✨ NOUVEAU PHASE 4 : Support des pièces jointes
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

        // ✨ NOUVEAU PHASE 4 : Gestion des pièces jointes
        $attachments = [];
        $temp_files = []; // Pour nettoyer les fichiers temporaires après envoi

        // 1. Pièce jointe système (Fichier existant OU Code natif)
        if (!empty($attachment_path)) {
            // Si c'est un fichier réel sur le disque
            if (file_exists($attachment_path)) {
                $attachments[] = $attachment_path;
            }
            // OU SI c'est un code spécial (commence par native_ ou template_)
            elseif (strpos($attachment_path, 'native_') === 0 || strpos($attachment_path, 'template_') === 0) {
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

        // ✨ NOUVEAU : Récupérer le message créé pour l'affichage instantané
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
     * ✨ NOUVEAU ENDPOINT CHANNEL MANAGER : Récupère l'historique d'une conversation
     */
    public static function ajax_get_conversation_history()
    {
        // 1. Sécurité
        check_ajax_referer('pc_resa_manual_create', 'nonce');
        if (!is_user_logged_in() || !self::current_user_can_manage()) {
            wp_send_json_error(['message' => 'Action non autorisée.']);
        }

        // 2. Paramètres
        $reservation_id = isset($_REQUEST['reservation_id']) ? (int) $_REQUEST['reservation_id'] : 0;
        if ($reservation_id <= 0) {
            wp_send_json_error(['message' => 'ID réservation manquant.']);
        }

        // 3. Vérifier que le module est disponible
        if (!class_exists('PCR_Messaging')) {
            wp_send_json_error(['message' => 'Module Messagerie indisponible.']);
        }

        // 4. Récupérer la conversation
        $conversation = PCR_Messaging::get_conversation($reservation_id);

        if (!$conversation['success']) {
            wp_send_json_error(['message' => 'Impossible de charger la conversation.']);
        }

        // 5. Enrichir avec les données de la réservation pour le contexte
        $resa_data = null;
        if (class_exists('PCR_Reservation')) {
            $resa = PCR_Reservation::get_by_id($reservation_id);
            if ($resa) {
                $resa_data = [
                    'id' => $resa->id,
                    'prenom' => $resa->prenom,
                    'nom' => $resa->nom,
                    'email' => $resa->email,
                    'statut_reservation' => $resa->statut_reservation,
                    'statut_paiement' => $resa->statut_paiement,
                ];
            }
        }

        wp_send_json_success([
            'conversation_id' => $conversation['conversation_id'],
            'total_messages' => $conversation['total_messages'],
            'unread_count' => $conversation['unread_count'],
            'messages' => $conversation['messages'],
            'reservation' => $resa_data,

            // ✨ DESIGN-AWARE : Métadonnées pour le frontend glassmorphisme
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
     * ✨ NOUVEAU ENDPOINT CHANNEL MANAGER : Marque des messages comme lus
     */
    public static function ajax_mark_messages_read()
    {
        // 1. Sécurité
        check_ajax_referer('pc_resa_manual_create', 'nonce');
        if (!is_user_logged_in() || !self::current_user_can_manage()) {
            wp_send_json_error(['message' => 'Action non autorisée.']);
        }

        // 2. Paramètres
        $message_ids = isset($_REQUEST['message_ids']) ? $_REQUEST['message_ids'] : [];

        // Support pour un seul ID ou un tableau d'IDs
        if (!is_array($message_ids)) {
            $message_ids = [$message_ids];
        }

        $message_ids = array_filter(array_map('intval', $message_ids));
        if (empty($message_ids)) {
            wp_send_json_error(['message' => 'Aucun message à marquer.']);
        }

        // 3. Vérifier que le module est disponible
        if (!class_exists('PCR_Messaging')) {
            wp_send_json_error(['message' => 'Module Messagerie indisponible.']);
        }

        // 4. Marquer comme lu
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
     * ✨ NOUVEAU ENDPOINT CHANNEL MANAGER : Récupère les réponses rapides (templates)
     */
    public static function ajax_get_quick_replies()
    {
        // 1. Sécurité
        check_ajax_referer('pc_resa_manual_create', 'nonce');
        if (!is_user_logged_in() || !self::current_user_can_manage()) {
            wp_send_json_error(['message' => 'Action non autorisée.']);
        }

        // 2. Paramètres (optionnel)
        $reservation_id = isset($_REQUEST['reservation_id']) ? (int) $_REQUEST['reservation_id'] : 0;

        // 3. Vérifier que le module est disponible
        if (!class_exists('PCR_Messaging')) {
            wp_send_json_error(['message' => 'Module Messagerie indisponible.']);
        }

        // 4. Récupérer les réponses rapides
        $result = PCR_Messaging::get_quick_replies();

        if (!$result['success']) {
            wp_send_json_error(['message' => $result['message']]);
        }

        // 5. Si reservation_id fourni, on peut enrichir les templates avec les variables
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

    /**
     * ✨ **NOUVELLE API HYBRIDE** : Récupère la liste des documents disponibles
     * selon votre cahier des charges - Documents Natifs + Documents Personnalisés
     */
    public static function ajax_get_documents_templates()
    {
        // 1. Sécurité
        check_ajax_referer('pc_resa_manual_create', 'nonce');
        if (!is_user_logged_in() || !self::current_user_can_manage()) {
            wp_send_json_error(['message' => 'Action non autorisée.']);
        }

        // 2. Récupération données réservation
        $reservation_id = isset($_POST['reservation_id']) ? (int) $_POST['reservation_id'] : 0;
        if ($reservation_id <= 0) {
            wp_send_json_error(['message' => 'ID Réservation manquant.']);
        }

        // Récupérer la réservation pour connaître le type
        if (!class_exists('PCR_Reservation')) {
            wp_send_json_error(['message' => 'Core Réservation manquant.']);
        }

        $resa = PCR_Reservation::get_by_id($reservation_id);
        if (!$resa) {
            wp_send_json_error(['message' => 'Réservation introuvable.']);
        }

        $reservation_type = $resa->type ?? 'location'; // 'location' ou 'experience'

        // 3. **GROUPE A : Documents Natifs (Hardcodés)**
        $documents_natifs = [];

        // Toujours visibles
        $documents_natifs[] = [
            'id' => 'native_devis',
            'type' => 'devis',
            'label' => '📄 Devis commercial',
            'description' => 'Document natif - Devis pour la réservation',
            'group' => 'native'
        ];

        $documents_natifs[] = [
            'id' => 'native_facture',
            'type' => 'facture',
            'label' => '🧾 Facture (Solde/Totale)',
            'description' => 'Document natif - Facture principale',
            'group' => 'native'
        ];

        $documents_natifs[] = [
            'id' => 'native_facture_acompte',
            'type' => 'facture_acompte',
            'label' => '💰 Facture d\'Acompte',
            'description' => 'Document natif - Facture d\'acompte',
            'group' => 'native'
        ];

        // Conditionnels selon le type
        if (in_array($reservation_type, ['location', 'mixte'])) {
            $documents_natifs[] = [
                'id' => 'native_contrat',
                'type' => 'contrat',
                'label' => '📋 Contrat de Location',
                'description' => 'Document natif - Contrat pour logements',
                'group' => 'native'
            ];
        }

        if (in_array($reservation_type, ['experience', 'mixte'])) {
            $documents_natifs[] = [
                'id' => 'native_voucher',
                'type' => 'voucher',
                'label' => '🎫 Voucher / Bon d\'échange',
                'description' => 'Document natif - Voucher pour expériences',
                'group' => 'native'
            ];
        }

        // Note: L'avoir est géré automatiquement et caché de la création manuelle

        // 4. **GROUPE B : Documents Personnalisés (BDD)**
        $documents_personnalises = [];

        $templates_args = [
            'post_type' => 'pc_pdf_template',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ];

        $templates = get_posts($templates_args);

        foreach ($templates as $template) {
            $model_context = get_field('pc_model_context', $template->ID) ?: 'global';

            // Filtrage selon le contexte
            $show_template = false;
            if ($model_context === 'global') {
                $show_template = true; // Toujours visible
            } elseif ($model_context === 'location' && $reservation_type === 'location') {
                $show_template = true;
            } elseif ($model_context === 'experience' && $reservation_type === 'experience') {
                $show_template = true;
            }

            if ($show_template) {
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

                $documents_personnalises[] = [
                    'id' => 'template_' . $template->ID,
                    'template_id' => $template->ID,
                    'type' => $doc_type,
                    'label' => $icon . ' ' . $template->post_title,
                    'description' => 'Modèle personnalisé - ' . ($template->post_excerpt ?: 'Document personnalisé'),
                    'group' => 'custom',
                    'context' => $model_context
                ];
            }
        }

        // 5. Construction de la réponse finale
        $response = [
            'reservation_id' => $reservation_id,
            'reservation_type' => $reservation_type,
            'documents' => [
                'native' => [
                    'label' => '🏠 Documents Natifs',
                    'description' => 'Documents intégrés au système, toujours disponibles',
                    'items' => $documents_natifs
                ],
                'custom' => [
                    'label' => '🎨 Modèles Personnalisés',
                    'description' => 'Documents créés dans PC Réservation > Modèles PDF',
                    'items' => $documents_personnalises
                ]
            ],
            'total_count' => count($documents_natifs) + count($documents_personnalises)
        ];

        wp_send_json_success($response);
    }

    /**
     * ✨ NOUVEAU ENDPOINT PHASE 4 : Récupère les documents PDF générés pour une réservation
     * Utilise PCR_Documents pour lister les fichiers existants qui peuvent être joints aux emails
     */
    public static function ajax_get_reservation_files()
    {
        // 1. Sécurité
        check_ajax_referer('pc_resa_manual_create', 'nonce');
        if (!is_user_logged_in() || !self::current_user_can_manage()) {
            wp_send_json_error(['message' => 'Action non autorisée.']);
        }

        // 2. Paramètres
        $reservation_id = isset($_REQUEST['reservation_id']) ? (int) $_REQUEST['reservation_id'] : 0;
        if ($reservation_id <= 0) {
            wp_send_json_error(['message' => 'ID réservation manquant.']);
        }

        // 3. Vérifier que les modules sont disponibles
        if (!class_exists('PCR_Documents')) {
            wp_send_json_error(['message' => 'Module Documents indisponible.']);
        }

        if (!class_exists('PCR_Reservation')) {
            wp_send_json_error(['message' => 'Module Réservation indisponible.']);
        }

        // 4. Vérifier que la réservation existe
        $resa = PCR_Reservation::get_by_id($reservation_id);
        if (!$resa) {
            wp_send_json_error(['message' => 'Réservation introuvable.']);
        }

        // 5. Récupérer les documents générés via PCR_Documents
        // Utiliser la méthode qui liste les fichiers existants pour cette réservation
        $upload_dir = wp_upload_dir();
        $resa_folder = $upload_dir['basedir'] . '/pc-reservation/documents/' . $reservation_id;

        $files = [];

        // Vérifier si le dossier existe
        if (is_dir($resa_folder)) {
            $file_list = scandir($resa_folder);

            foreach ($file_list as $filename) {
                if ($filename === '.' || $filename === '..') {
                    continue;
                }

                $file_path = $resa_folder . '/' . $filename;
                if (!is_file($file_path)) {
                    continue;
                }

                // Filtrer seulement les PDF
                if (pathinfo($filename, PATHINFO_EXTENSION) !== 'pdf') {
                    continue;
                }

                // Déterminer le type de document selon le nom de fichier
                $doc_type = 'document';
                $display_name = $filename;

                if (strpos($filename, 'devis') !== false) {
                    $doc_type = 'devis';
                    $display_name = 'Devis commercial';
                } elseif (strpos($filename, 'facture') !== false) {
                    if (strpos($filename, 'acompte') !== false) {
                        $doc_type = 'facture_acompte';
                        $display_name = 'Facture d\'acompte';
                    } else {
                        $doc_type = 'facture';
                        $display_name = 'Facture';
                    }
                } elseif (strpos($filename, 'contrat') !== false) {
                    $doc_type = 'contrat';
                    $display_name = 'Contrat de location';
                } elseif (strpos($filename, 'voucher') !== false) {
                    $doc_type = 'voucher';
                    $display_name = 'Voucher / Bon d\'échange';
                } elseif (strpos($filename, 'avoir') !== false) {
                    $doc_type = 'avoir';
                    $display_name = 'Avoir';
                }

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

                $file_size = filesize($file_path);
                $file_size_formatted = '';
                if ($file_size < 1024) {
                    $file_size_formatted = $file_size . ' B';
                } elseif ($file_size < 1024 * 1024) {
                    $file_size_formatted = round($file_size / 1024, 1) . ' KB';
                } else {
                    $file_size_formatted = round($file_size / (1024 * 1024), 1) . ' MB';
                }

                $files[] = [
                    'name' => $display_name,
                    'filename' => $filename,
                    'path' => $file_path,
                    'url' => $upload_dir['baseurl'] . '/pc-reservation/documents/' . $reservation_id . '/' . $filename,
                    'type' => $doc_type,
                    'icon' => $icon,
                    'size' => $file_size,
                    'size_formatted' => $file_size_formatted,
                    'created' => date('Y-m-d H:i:s', filemtime($file_path))
                ];
            }
        }

        // 6. Tri par type puis par nom
        usort($files, function ($a, $b) {
            // Priorité des types
            $priority = [
                'devis' => 1,
                'facture_acompte' => 2,
                'facture' => 3,
                'contrat' => 4,
                'voucher' => 5,
                'avoir' => 6,
                'document' => 7
            ];

            $a_priority = $priority[$a['type']] ?? 99;
            $b_priority = $priority[$b['type']] ?? 99;

            if ($a_priority === $b_priority) {
                return strcmp($a['name'], $b['name']);
            }

            return $a_priority <=> $b_priority;
        });

        wp_send_json_success([
            'reservation_id' => $reservation_id,
            'files' => $files,
            'total_count' => count($files),
            'folder_path' => $resa_folder,
            'message' => count($files) > 0
                ? sprintf('%d document(s) disponible(s) pour cette réservation.', count($files))
                : 'Aucun document généré pour cette réservation.'
        ]);
    }
}
