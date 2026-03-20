<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Boîte à outils pour les anciennes fonctions (Legacy) nécessaires au Dashboard V2
 * Regroupe la récupération des tarifs ACF et le formatage des dates du calendrier.
 */
class PCR_Legacy_Utils
{
    /**
     * Récupère et formate la config de prix via ACF pour le moteur de calcul.
     */
    public static function get_pricing_config($post_id)
    {
        if (!function_exists('get_field')) return null;

        $post_id = (int) $post_id;
        if ($post_id <= 0) return null;

        $base_price   = (float) get_field('base_price_from', $post_id);
        $unit         = (string) get_field('unite_de_prix',   $post_id);
        $min_nights   = (int)    get_field('min_nights',      $post_id);
        $max_nights   = (int)    get_field('max_nights',      $post_id);
        $cap          = (int)    get_field('capacite',        $post_id);
        if ($cap <= 0) $cap = 1;

        $extra_fee    = (float)  get_field('extra_guest_fee',  $post_id);
        $extra_from   = (int)    get_field('extra_guest_from', $post_id);
        $cleaning     = (float)  get_field('frais_menage',      $post_id);
        $other_fee    = (float)  get_field('autres_frais',      $post_id);
        $other_label  = (string) get_field('autres_frais_type', $post_id);
        $taxe_choices = (array)  get_field('taxe_sejour',       $post_id);
        $unit_is_week = (stripos($unit, 'semaine') !== false);
        $seasons_raw  = (array) get_field('pc_season_blocks', $post_id);
        $manual_quote = (bool) get_field('pc_manual_quote', $post_id);

        $seasons = [];
        foreach ($seasons_raw as $s) {
            $price = isset($s['season_price']) ? (float) $s['season_price'] : 0.0;
            if ($unit_is_week && $price > 0) $price = $price / 7.0;
            if (!is_array($s)) $s = [];

            $seasons[] = [
                'name'        => trim((string) ($s['season_name'] ?? 'Saison')),
                'min_nights'  => (int) ($s['season_min_nights'] ?? 0),
                'extra_fee'   => ($s['season_extra_guest_fee'] ?? '') !== '' ? (float) $s['season_extra_guest_fee'] : $extra_fee,
                'extra_from'  => ($s['season_extra_guest_from'] ?? '') !== '' ? (int) $s['season_extra_guest_from'] : $extra_from,
                'price'       => ($price > 0 ? $price : ($unit_is_week ? ($base_price / 7.0) : $base_price)),
                'periods'     => array_values(array_map(function ($p) {
                    return [
                        'from' => (string) ($p['date_from'] ?? ''),
                        'to'   => (string) ($p['date_to'] ?? ''),
                    ];
                }, (array) ($s['season_periods'] ?? []))),
            ];
        }

        $ics_disable = [];

        // 0. Récupération des iCal distants (Airbnb, Booking...)
        $ical_url = (string) get_field('ical_url', $post_id);
        if ($ical_url && function_exists('pc_parse_ics_ranges')) {
            $cache_key = 'pc_ics_body_' . md5($ical_url);
            $ics_body  = get_transient($cache_key);
            if ($ics_body === false) {
                $resp = wp_remote_get($ical_url, ['timeout' => 10]);
                if (! is_wp_error($resp) && 200 === wp_remote_retrieve_response_code($resp)) {
                    $ics_body = (string) wp_remote_retrieve_body($resp);
                    if ($ics_body !== '') {
                        set_transient($cache_key, $ics_body, 2 * HOUR_IN_SECONDS);
                    }
                } else {
                    $ics_body = '';
                }
            }
            if ($ics_body !== '') {
                $ics_disable = pc_parse_ics_ranges($ics_body);
            }
        }

        // 1. Cache iCal Externe
        $booked_dates = get_post_meta($post_id, '_booked_dates_cache', true);
        if (is_array($booked_dates) && !empty($booked_dates)) {
            $ics_disable = array_merge($ics_disable, self::dates_to_ranges($booked_dates));
        }

        // 2. Réservations Internes + Blocages Manuels
        global $wpdb;
        $today_sql = current_time('Y-m-d');

        // A. Réservations internes
        $table_res = $wpdb->prefix . 'pc_reservations';
        $internal_res = $wpdb->get_results($wpdb->prepare(
            "SELECT date_arrivee, date_depart FROM {$table_res} WHERE item_id = %d AND statut_reservation = 'reservee' AND date_depart >= %s",
            $post_id,
            $today_sql
        ));
        foreach ($internal_res as $r) {
            $end_date = date('Y-m-d', strtotime($r->date_depart . ' -1 day'));
            if ($end_date >= $r->date_arrivee) {
                $ics_disable[] = ['from' => $r->date_arrivee, 'to' => $end_date];
            }
        }

        // B. Blocages manuels
        $table_unv = $wpdb->prefix . 'pc_unavailabilities';
        $unv_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_unv'") === $table_unv;
        if ($unv_table_exists) {
            $manual_blocks = $wpdb->get_results($wpdb->prepare(
                "SELECT date_debut, date_fin FROM {$table_unv} WHERE item_id = %d AND date_fin >= %s",
                $post_id,
                $today_sql
            ));
            foreach ($manual_blocks as $b) {
                $ics_disable[] = ['from' => $b->date_debut, 'to' => $b->date_fin];
            }
        }

        // Fusion
        if (!empty($ics_disable)) {
            $ics_disable = self::merge_ranges($ics_disable);
        }

        return [
            'title'       => get_the_title($post_id),
            'basePrice'   => $unit_is_week ? ($base_price / 7.0) : $base_price,
            'cap'         => $cap,
            'minNights'   => max(1, $min_nights ?: 1),
            'maxNights'   => max(1, $max_nights ?: 365),
            'extraFee'    => $extra_fee,
            'extraFrom'   => max(0, $extra_from),
            'cleaning'    => $cleaning,
            'otherFee'    => $other_fee,
            'otherLabel'  => $other_label ?: 'Autres frais',
            'taxe_sejour' => $taxe_choices,
            'seasons'     => $seasons,
            'icsDisable'  => $ics_disable,
            'manualQuote' => $manual_quote,
        ];
    }

    /**
     * Convertit un tableau de dates en plages pour le Calendrier
     */
    public static function dates_to_ranges($dates)
    {
        if (!is_array($dates) || empty($dates)) return [];
        $normalized = [];
        foreach ($dates as $date) {
            $value = trim((string) $date);
            if ($value === '') continue;
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $timestamp = strtotime($value);
                if ($timestamp === false) continue;
                $value = date('Y-m-d', $timestamp);
            }
            $normalized[] = $value;
        }
        if (empty($normalized)) return [];

        sort($normalized);
        $ranges = [];
        $currentStart = $normalized[0];
        $currentEnd = $normalized[0];
        for ($i = 1, $max = count($normalized); $i < $max; $i++) {
            $date = $normalized[$i];
            $prevNext = date('Y-m-d', strtotime($currentEnd . ' +1 day'));
            if ($date === $prevNext) {
                $currentEnd = $date;
                continue;
            }
            $ranges[] = ['from' => $currentStart, 'to' => $currentEnd];
            $currentStart = $date;
            $currentEnd = $date;
        }
        $ranges[] = ['from' => $currentStart, 'to' => $currentEnd];
        return $ranges;
    }

    /**
     * Fusionne les plages de dates qui se chevauchent
     */
    public static function merge_ranges($ranges)
    {
        $valid = [];
        foreach ($ranges as $range) {
            if (empty($range['from']) || empty($range['to'])) continue;
            $from = substr((string) $range['from'], 0, 10);
            $to   = substr((string) $range['to'], 0, 10);
            if (!$from || !$to || $to < $from) continue;
            $valid[] = ['from' => $from, 'to' => $to];
        }
        if (empty($valid)) return [];

        usort($valid, function ($a, $b) {
            return strcmp($a['from'], $b['from']);
        });

        $merged = [];
        $current = array_shift($valid);
        foreach ($valid as $range) {
            $currentEndPlusOne = date('Y-m-d', strtotime($current['to'] . ' +1 day'));
            if ($range['from'] <= $currentEndPlusOne) {
                if ($range['to'] > $current['to']) $current['to'] = $range['to'];
            } else {
                $merged[] = $current;
                $current = $range;
            }
        }
        $merged[] = $current;
        return $merged;
    }
}
