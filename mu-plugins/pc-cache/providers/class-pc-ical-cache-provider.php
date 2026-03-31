<?php

/**
 * PC iCal Cache Provider
 * Moteur de synchronisation critique des calendriers
 * Logique de parsing stricte (0 régression) + Logs de sécurité
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Ical_Cache_Provider
{

    /**
     * Exécute la synchronisation de tous les logements
     */
    public function sync_all_logements()
    {
        PC_Cache_Helper::log("Démarrage de la synchronisation iCal globale.", "INFO");

        $args = [
            'post_type'      => ['logement', 'villa', 'appartement'],
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => 'ical_url',
                    'value'   => '',
                    'compare' => '!=',
                ],
            ],
            'fields' => 'ids',
        ];

        $logement_ids = new WP_Query($args);

        if (!$logement_ids->have_posts()) {
            PC_Cache_Helper::log("Aucun logement avec une URL iCal trouvée. Fin de la tâche.", "WARNING");
            return;
        }

        $success_count = 0;
        $error_count = 0;

        foreach ($logement_ids->posts as $post_id) {
            $ical_url = class_exists('PCR_Fields') ? PCR_Fields::get('ical_url', $post_id) : get_post_meta($post_id, 'ical_url', true);

            // Requête HTTP avec timeout conservé à 20s
            $response = wp_remote_get($ical_url, ['timeout' => 20]);

            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200) {
                // NOUVEAU : On loggue l'erreur pour savoir quel logement pose problème
                $error_msg = is_wp_error($response) ? $response->get_error_message() : 'Code HTTP ' . wp_remote_retrieve_response_code($response);
                PC_Cache_Helper::log("Échec de synchronisation pour le logement ID {$post_id}. Erreur : {$error_msg}", "ERROR");
                $error_count++;
                continue;
            }

            $ical_data = wp_remote_retrieve_body($response);

            // Parsing exact (Zéro régression)
            $booked_dates = $this->parse_ical_data($ical_data);

            if (!empty($booked_dates)) {
                update_post_meta($post_id, '_booked_dates_cache', $booked_dates);
                $success_count++;
            } else {
                delete_post_meta($post_id, '_booked_dates_cache');
                // On loggue un calendrier vide car cela peut être normal (vide) ou anormal (erreur de fichier source)
                PC_Cache_Helper::log("Le calendrier du logement ID {$post_id} a été vidé (0 réservation trouvée).", "INFO");
            }
        }

        PC_Cache_Helper::log("Fin de la synchronisation. Succès : {$success_count} | Échecs : {$error_count}.", "INFO");
    }

    /**
     * Parsing iCal (Logique d'origine stricte préservée)
     */
    private function parse_ical_data($ical_string)
    {
        $events = [];
        preg_match_all('/BEGIN:VEVENT.*?END:VEVENT/si', $ical_string, $matches);

        if (empty($matches[0])) {
            return [];
        }

        foreach ($matches[0] as $event_str) {
            $start_date = false;
            $end_date = false;

            if (preg_match('/DTSTART(?:;[^:]+)?:(\d{8})/', $event_str, $start_match)) {
                $start_date = DateTime::createFromFormat('Ymd', $start_match[1]);
            }

            if (preg_match('/DTEND(?:;[^:]+)?:(\d{8})/', $event_str, $end_match)) {
                // La date de fin en iCal est exclusive, on s'arrête la veille
                $end_date = DateTime::createFromFormat('Ymd', $end_match[1]);
            }

            if ($start_date && $end_date) {
                $period = new DatePeriod($start_date, new DateInterval('P1D'), $end_date);
                foreach ($period as $date) {
                    $events[] = $date->format('Y-m-d');
                }
            }
        }

        return array_unique($events);
    }
}
