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
        add_action('wp_ajax_pc_manual_logement_config', [__CLASS__, 'handle_logement_config']);
        add_action('wp_ajax_nopriv_pc_manual_logement_config', [__CLASS__, 'handle_logement_config']);
        add_action('wp_ajax_pc_get_calendar_global', [__CLASS__, 'ajax_get_calendar_global']);
        add_action('wp_ajax_nopriv_pc_get_calendar_global', [__CLASS__, 'ajax_get_calendar_global']);
        add_action('wp_ajax_pc_get_global_calendar', [__CLASS__, 'ajax_get_global_calendar']);
        add_action('wp_ajax_nopriv_pc_get_global_calendar', [__CLASS__, 'ajax_get_global_calendar']);
        add_action('wp_ajax_pc_get_single_calendar', [__CLASS__, 'ajax_get_single_calendar']);
        add_action('wp_ajax_nopriv_pc_get_single_calendar', [__CLASS__, 'ajax_get_single_calendar']);
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
                'source'           => 'dashboard',
            ],
            'item' => [
                'item_id'              => $item_id,
                'experience_tarif_type'=> $type === 'experience' ? $experience_tarif_type : '',
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

        if (!is_user_logged_in()) {
            wp_send_json_error([
                'message' => 'Veuillez vous connecter.',
                'code'    => 'not_logged_in',
            ], 403);
        }

        if (!self::current_user_can_manage()) {
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
                'start_date'=> $range['start'],
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
            'start_date'=> $range['start'],
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
        $posts = get_posts([
            'post_type'      => ['logement', 'villa', 'appartement'],
            'post_status'    => ['publish', 'pending'],
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ]);

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
                'logement_id' => (int) ($event['item_id'] ?? 0),
                'start_date'  => isset($event['start']) ? substr((string) $event['start'], 0, 10) : '',
                'end_date'    => isset($event['end']) ? substr((string) $event['end'], 0, 10) : '',
                'source'      => self::normalize_event_source($event),
                'type'        => $event['type'] ?? '',
                'status'      => $event['status'] ?? '',
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
            if ($event['source'] === 'ical_cache') {
                return 'ical';
            }
            return (string) $event['source'];
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
        $sql = "
            SELECT id, item_id, date_arrivee, date_depart, statut_reservation
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
            $events[] = [
                'item_id'   => (int) $row->item_id,
                'type'      => 'reservation',
                'start'     => sanitize_text_field($row->date_arrivee),
                'end'       => sanitize_text_field($row->date_depart),
                'status'    => sanitize_text_field($row->statut_reservation),
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
            SELECT item_id, date_debut, date_fin, type_source
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
}
