<?php

/**
 * Helper : Moteur de calcul des disponibilités et parsing iCal
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Availability_Helper
{

    /**
     * Récupère TOUTES les indisponibilités (Interne + Externe + Manuel)
     * Format renvoyé : JSON array de tableaux [start_date, end_date]
     */
    public static function get_combined_availability($logement_id)
    {
        global $wpdb;
        $ranges = [];
        $today = current_time('Y-m-d');

        // 1. External iCals (via le champ ACF ical_url)
        $ical_url = PCR_Fields::get('ical_url', $logement_id) ?: '';
        if ($ical_url) {
            $ext_ranges = json_decode(self::get_ics_disabled_ranges($ical_url, 24), true);
            if (is_array($ext_ranges)) {
                $ranges = array_merge($ranges, $ext_ranges);
            }
        }

        // 2. Réservations Internes (Statut 'reservee')
        $table_res = $wpdb->prefix . 'pc_reservations';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_res'") === $table_res) {
            $internal_res = $wpdb->get_results($wpdb->prepare(
                "SELECT date_arrivee, date_depart FROM {$table_res} 
                 WHERE item_id = %d AND statut_reservation = 'reservee' 
                 AND date_depart >= %s",
                $logement_id,
                $today
            ));

            foreach ($internal_res as $res) {
                // On libère le jour du départ (-1 day) pour permettre le chassé-croisé
                $end_date = date('Y-m-d', strtotime($res->date_depart . ' -1 day'));
                if ($end_date >= $res->date_arrivee) {
                    $ranges[] = [$res->date_arrivee, $end_date];
                }
            }
        }

        // 3. Blocages Manuels (Table pc_unavailabilities)
        $table_unv = $wpdb->prefix . 'pc_unavailabilities';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_unv'") === $table_unv) {
            $manual_blocks = $wpdb->get_results($wpdb->prepare(
                "SELECT date_debut, date_fin FROM {$table_unv} 
                 WHERE item_id = %d AND date_fin >= %s",
                $logement_id,
                $today
            ));

            foreach ($manual_blocks as $blk) {
                $ranges[] = [$blk->date_debut, $blk->date_fin];
            }
        }

        return wp_json_encode($ranges);
    }

    /**
     * Parse un fichier iCal externe distant et extrait les plages bloquées
     */
    public static function get_ics_disabled_ranges($url, $max_months = 24)
    {
        if (!$url) return '[]';

        $resp = wp_remote_get($url, ['timeout' => 10]);
        if (is_wp_error($resp)) return '[]';

        $ics = wp_remote_retrieve_body($resp);
        if (!$ics) return '[]';

        // Nettoyage et fusion des lignes iCal
        $ics = str_replace("\r\n", "\n", $ics);
        $lines = preg_split('/\n/', $ics);
        $unfolded = [];

        foreach ($lines as $line) {
            if (isset($unfolded[count($unfolded) - 1]) && (isset($line[0]) && ($line[0] === ' ' || $line[0] === "\t"))) {
                $unfolded[count($unfolded) - 1] .= substr($line, 1);
            } else {
                $unfolded[] = $line;
            }
        }

        $ranges = [];
        $inEvent = false;
        $dtStart = null;
        $dtEnd = null;

        foreach ($unfolded as $l) {
            if (stripos($l, 'BEGIN:VEVENT') === 0) {
                $inEvent = true;
                $dtStart = $dtEnd = null;
                continue;
            }
            if (stripos($l, 'END:VEVENT') === 0) {
                if ($inEvent && $dtStart && $dtEnd) {
                    try {
                        $start = new DateTimeImmutable($dtStart);
                        $end   = (new DateTimeImmutable($dtEnd))->modify('-1 day'); // DTEND exclusif
                        if ($end >= $start) {
                            $ranges[] = [$start->format('Y-m-d'), $end->format('Y-m-d')];
                        }
                    } catch (Exception $e) {
                    }
                }
                $inEvent = false;
                $dtStart = $dtEnd = null;
                continue;
            }

            if (!$inEvent) continue;

            if (stripos($l, 'DTSTART') === 0) {
                $v = substr($l, (strpos($l, ':') + 1));
                $dtStart = self::normalize_dt($v);
            }
            if (stripos($l, 'DTEND') === 0) {
                $v = substr($l, (strpos($l, ':') + 1));
                $dtEnd = self::normalize_dt($v);
            }
        }

        // Tri chronologique
        usort($ranges, function ($a, $b) {
            return strcmp($a[0], $b[0]);
        });

        // Filtre de limite de mois
        if ($max_months > 0) {
            try {
                $limit = (new DateTimeImmutable('first day of this month'))->modify("+{$max_months} months")->format('Y-m-d');
                $out = [];
                foreach ($ranges as $r) {
                    if ($r[0] <= $limit) $out[] = $r;
                }
                $ranges = $out;
            } catch (Exception $e) {
            }
        }

        return wp_json_encode($ranges);
    }

    /**
     * Utilitaire interne : Normalise les différents formats de dates iCal
     */
    private static function normalize_dt($v)
    {
        $v = trim($v);

        if (preg_match('/^\d{8}$/', $v)) {
            $dt = DateTimeImmutable::createFromFormat('Ymd', $v, new DateTimeZone('UTC'));
            return $dt ? $dt->format('Y-m-d') : null;
        }

        if (preg_match('/^\d{8}T\d{6}Z$/', $v)) {
            try {
                $dt = new DateTimeImmutable($v);
                return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
            } catch (Exception $e) {
                return null;
            }
        }

        if (preg_match('/^\d{8}T\d{6}$/', $v)) {
            $dt = DateTimeImmutable::createFromFormat('Ymd\THis', $v, new DateTimeZone('UTC'));
            return $dt ? $dt->format('Y-m-d') : null;
        }

        try {
            $dt = new DateTimeImmutable($v);
            return $dt->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    }
}
