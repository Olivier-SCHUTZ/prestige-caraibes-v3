<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gère l'export iCal public (Sortie).
 * URL : /?pc_action=ical_export&id={ID_LOGEMENT}&token={HASH}&mode={simple|full}
 */
class PCR_Ical_Export
{
    public static function init()
    {
        add_action('init', [__CLASS__, 'listen_for_export_request']);
    }

    public static function listen_for_export_request()
    {
        if (!isset($_GET['pc_action']) || $_GET['pc_action'] !== 'ical_export') {
            return;
        }

        $logement_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $token       = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        $mode        = isset($_GET['mode']) && $_GET['mode'] === 'full' ? 'full' : 'simple';

        if (!$logement_id || !$token) {
            wp_die('Paramètres manquants.', 'Erreur iCal', ['response' => 400]);
        }

        if (!self::verify_token($logement_id, $token)) {
            wp_die('Accès refusé. Token invalide.', 'Erreur iCal', ['response' => 403]);
        }

        self::render_ical($logement_id, $mode);
        exit;
    }

    private static function render_ical($logement_id, $mode)
    {
        global $wpdb;
        $title = get_the_title($logement_id);
        $blog_name = get_bloginfo('name');
        $suffix = $mode === 'full' ? '-full' : '-simple';

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="export-logement-' . $logement_id . $suffix . '.ics"');

        echo "BEGIN:VCALENDAR\r\n";
        echo "VERSION:2.0\r\n";
        echo "PRODID:-//PrestigeCaraibes//NONSGML v1.0//EN\r\n";
        echo "CALSCALE:GREGORIAN\r\n";
        echo "METHOD:PUBLISH\r\n";
        echo "X-WR-CALNAME:" . self::escape_ical_text("$blog_name - $title (" . ucfirst($mode) . ")") . "\r\n";

        // --- 1. RÉSERVATIONS INTERNES (Statut = reservee) ---
        $table_res = $wpdb->prefix . 'pc_reservations';
        $sql_res = "SELECT id, date_arrivee, date_depart, prenom, nom 
                    FROM {$table_res} 
                    WHERE item_id = %d 
                    AND statut_reservation = 'reservee'
                    AND date_arrivee IS NOT NULL 
                    AND date_depart IS NOT NULL";

        $reservations = $wpdb->get_results($wpdb->prepare($sql_res, $logement_id));

        foreach ($reservations as $res) {
            $summary = "Réservé: " . $res->prenom . " " . mb_substr($res->nom, 0, 1) . ".";
            $dt_start = str_replace('-', '', $res->date_arrivee);
            $dt_end   = date('Ymd', strtotime($res->date_depart . ' +1 day')); // Exclusif
            $uid      = 'pc-resa-' . $res->id . '@' . $_SERVER['HTTP_HOST'];

            self::print_vevent($dt_start, $dt_end, $uid, $summary, "Réservation Interne");
        }

        // --- 2. BLOCAGES MANUELS ---
        $table_unv = $wpdb->prefix . 'pc_unavailabilities';
        $sql_unv = "SELECT id, date_debut, date_fin, motif 
                    FROM {$table_unv} 
                    WHERE item_id = %d 
                    AND type_source = 'manuel'";

        $blocks = $wpdb->get_results($wpdb->prepare($sql_unv, $logement_id));

        foreach ($blocks as $blk) {
            $summary = "Fermé" . ($blk->motif ? ": " . $blk->motif : "");
            $dt_start = str_replace('-', '', $blk->date_debut);
            $dt_end   = date('Ymd', strtotime($blk->date_fin . ' +1 day'));
            $uid      = 'pc-block-' . $blk->id . '@' . $_SERVER['HTTP_HOST'];

            self::print_vevent($dt_start, $dt_end, $uid, $summary, "Blocage Manuel");
        }

        // --- 3. IMPORTS EXTERNES (Seulement si Mode FULL) ---
        // Pour éviter les boucles, le mode Simple (donné au proprio) n'inclut PAS les iCals externes.
        // Le mode Full (donné aux OTAs) inclut TOUT (Interne + Manuel + Autres OTAs importés).
        if ($mode === 'full') {
            $cached_dates = get_post_meta($logement_id, '_booked_dates_cache', true);
            if (is_array($cached_dates) && !empty($cached_dates)) {
                // Conversion liste de dates Y-m-d -> Plages de dates
                $ranges = self::dates_to_ranges($cached_dates);

                foreach ($ranges as $idx => $range) {
                    $dt_start = str_replace('-', '', $range['start']);
                    $dt_end   = date('Ymd', strtotime($range['end'] . ' +1 day'));
                    $uid      = 'pc-ext-' . $logement_id . '-' . $idx . '@' . $_SERVER['HTTP_HOST'];

                    self::print_vevent($dt_start, $dt_end, $uid, "Import Externe", "Synchronisé depuis un autre calendrier");
                }
            }
        }

        echo "END:VCALENDAR";
    }

    private static function print_vevent($dtstart, $dtend, $uid, $summary, $desc = '')
    {
        echo "BEGIN:VEVENT\r\n";
        echo "DTSTART;VALUE=DATE:{$dtstart}\r\n";
        echo "DTEND;VALUE=DATE:{$dtend}\r\n";
        echo "UID:{$uid}\r\n";
        echo "SUMMARY:" . self::escape_ical_text($summary) . "\r\n";
        if ($desc) echo "DESCRIPTION:" . self::escape_ical_text($desc) . "\r\n";
        echo "STATUS:CONFIRMED\r\n";
        echo "END:VEVENT\r\n";
    }

    private static function escape_ical_text($text)
    {
        $text = str_replace(["\r", "\n"], " ", $text);
        $text = str_replace([",", ";", "\\"], ["\\,", "\\;", "\\\\"], $text);
        return $text;
    }

    public static function get_token($logement_id)
    {
        return substr(md5($logement_id . wp_salt('auth')), 0, 12);
    }

    private static function verify_token($logement_id, $token)
    {
        return hash_equals(self::get_token($logement_id), $token);
    }

    public static function get_export_url($logement_id, $mode = 'simple')
    {
        $token = self::get_token($logement_id);
        return home_url("/?pc_action=ical_export&id={$logement_id}&token={$token}&mode={$mode}");
    }

    /**
     * Helper : Convertit un tableau de dates ['2025-01-01', '2025-01-02'] en plages
     */
    private static function dates_to_ranges($dates)
    {
        if (empty($dates)) return [];
        sort($dates);
        $ranges = [];
        $start = $dates[0];
        $end = $dates[0];

        for ($i = 1; $i < count($dates); $i++) {
            $current = $dates[$i];
            $next_expected = date('Y-m-d', strtotime($end . ' +1 day'));

            if ($current === $next_expected) {
                $end = $current;
            } else {
                $ranges[] = ['start' => $start, 'end' => $end];
                $start = $current;
                $end = $current;
            }
        }
        $ranges[] = ['start' => $start, 'end' => $end];
        return $ranges;
    }
}
