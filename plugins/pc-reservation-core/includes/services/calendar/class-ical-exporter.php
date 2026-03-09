<?php
if (!defined('ABSPATH')) {
    exit; // Sécurité : Empêche l'accès direct au fichier
}

/**
 * Service d'export iCal (iCal Exporter) pour le module Calendrier.
 * Gère le formatage et la génération des flux ICS pour les OTAs (Airbnb, Booking...).
 * Pattern Singleton pour garantir une instance unique.
 */
class PCR_Ical_Exporter
{
    /**
     * @var PCR_Ical_Exporter Instance unique
     */
    private static $instance = null;

    /**
     * Constructeur privé (Singleton)
     */
    private function __construct() {}

    /**
     * Empêche le clonage
     */
    private function __clone() {}

    /**
     * Récupère l'instance unique de l'Exporter.
     *
     * @return PCR_Ical_Exporter
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Génère et affiche le flux iCal complet pour un logement donné.
     *
     * @param int    $logement_id
     * @param string $mode 'simple' ou 'full'
     */
    public function render_ical($logement_id, $mode)
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
        echo "X-WR-CALNAME:" . $this->escape_ical_text("$blog_name - $title (" . ucfirst($mode) . ")") . "\r\n";

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

            $this->print_vevent($dt_start, $dt_end, $uid, $summary, "Réservation Interne");
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

            $this->print_vevent($dt_start, $dt_end, $uid, $summary, "Blocage Manuel");
        }

        // --- 3. IMPORTS EXTERNES (Seulement si Mode FULL) ---
        if ($mode === 'full') {
            $cached_dates = get_post_meta($logement_id, '_booked_dates_cache', true);
            if (is_array($cached_dates) && !empty($cached_dates)) {
                $ranges = $this->dates_to_ranges($cached_dates);

                foreach ($ranges as $idx => $range) {
                    $dt_start = str_replace('-', '', $range['start']);
                    $dt_end   = date('Ymd', strtotime($range['end'] . ' +1 day'));
                    $uid      = 'pc-ext-' . $logement_id . '-' . $idx . '@' . $_SERVER['HTTP_HOST'];

                    $this->print_vevent($dt_start, $dt_end, $uid, "Import Externe", "Synchronisé depuis un autre calendrier");
                }
            }
        }

        echo "END:VCALENDAR";
    }

    /**
     * Formate et imprime un événement iCal (VEVENT).
     */
    private function print_vevent($dtstart, $dtend, $uid, $summary, $desc = '')
    {
        echo "BEGIN:VEVENT\r\n";
        echo "DTSTART;VALUE=DATE:{$dtstart}\r\n";
        echo "DTEND;VALUE=DATE:{$dtend}\r\n";
        echo "UID:{$uid}\r\n";
        echo "SUMMARY:" . $this->escape_ical_text($summary) . "\r\n";
        if ($desc) echo "DESCRIPTION:" . $this->escape_ical_text($desc) . "\r\n";
        echo "STATUS:CONFIRMED\r\n";
        echo "END:VEVENT\r\n";
    }

    /**
     * Échappe le texte pour respecter la norme iCal.
     */
    private function escape_ical_text($text)
    {
        $text = str_replace(["\r", "\n"], " ", $text);
        $text = str_replace([",", ";", "\\"], ["\\,", "\\;", "\\\\"], $text);
        return $text;
    }

    /**
     * Génère un token de sécurité unique par logement.
     */
    public function get_token($logement_id)
    {
        return substr(md5($logement_id . wp_salt('auth')), 0, 12);
    }

    /**
     * Vérifie la validité d'un token.
     */
    public function verify_token($logement_id, $token)
    {
        return hash_equals($this->get_token($logement_id), $token);
    }

    /**
     * Génère l'URL d'export.
     */
    public function get_export_url($logement_id, $mode = 'simple')
    {
        $token = $this->get_token($logement_id);
        return home_url("/?pc_action=ical_export&id={$logement_id}&token={$token}&mode={$mode}");
    }

    /**
     * Helper : Convertit un tableau de dates ['2025-01-01', '2025-01-02'] en plages
     */
    private function dates_to_ranges($dates)
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
