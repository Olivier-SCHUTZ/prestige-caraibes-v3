<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Contrôleur AJAX pour la gestion du Calendrier.
 */
class PCR_Calendar_Ajax_Controller extends PCR_Base_Ajax_Controller
{
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
        parent::verify_access('pc_dashboard_calendar');

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
        parent::verify_access('pc_dashboard_calendar');

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
        parent::verify_access('pc_dashboard_calendar');

        $logement_id = isset($_POST['logement_id']) ? (int) $_POST['logement_id'] : 0;
        $start_date  = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $end_date    = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';
        // 🚀 NOUVEAU : On récupère le motif envoyé par Vue.js
        $motif       = isset($_POST['motif']) && $_POST['motif'] !== '' ? sanitize_text_field(wp_unslash($_POST['motif'])) : 'Blocage manuel via calendrier';

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
                'motif'         => $motif, // 🚀 On injecte la variable ici !
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
        parent::verify_access('pc_dashboard_calendar');

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
     * Met à jour le motif d'un blocage manuel existant.
     */
    public static function ajax_calendar_update_block()
    {
        parent::verify_access('pc_dashboard_calendar');

        $block_id = isset($_POST['block_id']) ? (int) $_POST['block_id'] : 0;
        $motif    = isset($_POST['motif']) ? sanitize_text_field(wp_unslash($_POST['motif'])) : '';

        if ($block_id <= 0) {
            wp_send_json_error(['message' => 'Blocage introuvable.'], 400);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'pc_unavailabilities';

        // Mise à jour de la base de données
        $updated = $wpdb->update(
            $table,
            ['motif' => $motif],
            ['id' => $block_id],
            ['%s'],
            ['%d']
        );

        if (false === $updated) {
            wp_send_json_error(['message' => 'Erreur lors de la mise à jour.'], 500);
        }

        wp_send_json_success(['updated' => true]);
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
     * Liste les logements actifs.
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
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => 'mode_reservation',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => 'mode_reservation',
                    'value'   => 'log_channel',
                    'compare' => '!=',
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
     * Récupère les événements depuis le cache iCal pour un logement.
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
     * Normalise les événements pour le front.
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
                'payment_status' => $event['payment_status'] ?? '',
                'label'          => $event['label'] ?? '',
            ];
        }

        return $normalized;
    }

    /**
     * Uniformise la source d'un événement.
     */
    protected static function normalize_event_source(array $event)
    {
        if (!empty($event['source'])) {
            $src = (string) $event['source'];
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

        $sql = "
            SELECT id, item_id, date_arrivee, date_depart, statut_reservation, statut_paiement, nom, prenom
            FROM {$table}
            WHERE type = %s
              AND statut_paiement IN ('paye', 'partiellement_paye', 'en_attente_paiement')
              AND item_id IN ({$ids_placeholder})
              AND date_arrivee IS NOT NULL
              AND date_depart IS NOT NULL
              AND date_depart >= %s
              AND date_arrivee <= %s
        ";

        $params = array_merge(['location'], $logement_ids, [$start_date, $end_date]);
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params));

        $events = [];
        foreach ((array) $rows as $row) {
            $nom = !empty($row->nom) ? strtoupper($row->nom) : 'Client';
            $prenom = !empty($row->prenom) ? $row->prenom : '';
            $label = trim("$nom $prenom (#{$row->id})");

            $events[] = [
                'item_id'        => (int) $row->item_id,
                'type'           => 'reservation',
                'start'          => sanitize_text_field($row->date_arrivee),
                'end'            => sanitize_text_field($row->date_depart),
                'status'         => sanitize_text_field($row->statut_reservation),
                'payment_status' => sanitize_text_field($row->statut_paiement),
                'label'          => $label,
            ];
        }

        return $events;
    }

    /**
     * Événements issus des blocages manuels.
     */
    protected static function get_manual_blocking_events(array $logement_ids, $start_date, $end_date)
    {
        global $wpdb;

        $ids_placeholder = implode(',', array_fill(0, count($logement_ids), '%d'));
        $table = $wpdb->prefix . 'pc_unavailabilities';
        $sql = "
            SELECT id, item_id, date_debut, date_fin, type_source, motif
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
                'label'   => sanitize_text_field($row->motif),
            ];
        }

        return $events;
    }

    /**
     * Événements issus des calendriers iCal.
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
     * Vérifie le chevauchement de deux plages de dates.
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
     * Convertit un tableau de dates en plages contiguës.
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
