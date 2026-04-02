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
            // On enlève la meta_query stricte car on veut potentiellement checker tous les logements
            'fields' => 'ids',
        ];

        $logement_ids = new WP_Query($args);

        if (!$logement_ids->have_posts()) {
            PC_Cache_Helper::log("Aucun logement trouvé pour la synchronisation iCal. Fin de la tâche.", "WARNING");
            return;
        }

        $success_count = 0;
        $error_count = 0;

        foreach ($logement_ids->posts as $post_id) {
            $icals_sync = class_exists('PCR_Fields') ? PCR_Fields::get('icals_sync', $post_id) : get_post_meta($post_id, 'icals_sync', true);

            if (is_string($icals_sync)) {
                $icals_sync = json_decode($icals_sync, true);
            }

            // Si le logement n'a aucun flux iCal, on passe au suivant
            if (!is_array($icals_sync) || empty($icals_sync)) {
                continue;
            }

            $all_booked_dates = [];

            // On boucle sur toutes les URLs du répéteur
            foreach ($icals_sync as $ical) {
                if (empty($ical['url'])) continue;

                // Requête HTTP avec timeout conservé à 20s
                $response = wp_remote_get($ical['url'], ['timeout' => 20]);

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
                    $all_booked_dates = array_merge($all_booked_dates, $booked_dates);
                }
            } // Fin de la boucle foreach ($icals_sync as $ical)

            // On dédoublonne les dates et on sauvegarde
            if (!empty($all_booked_dates)) {
                $all_booked_dates = array_unique($all_booked_dates);
                update_post_meta($post_id, '_booked_dates_cache', $all_booked_dates);
                $success_count++;
            } else {
                delete_post_meta($post_id, '_booked_dates_cache');
                PC_Cache_Helper::log("Le calendrier du logement ID {$post_id} a été vidé (0 réservation trouvée sur tous les flux).", "INFO");
            }
        } // Fin de la boucle foreach ($logement_ids->posts)

        PC_Cache_Helper::log("Fin de la synchronisation. Logements traités avec succès : {$success_count} | Erreurs de flux : {$error_count}.", "INFO");
    }

    /**
     * Synchronise immédiatement un logement spécifique (Appelé lors de la sauvegarde)
     */
    public function sync_single_logement($post_id)
    {
        $icals_sync = class_exists('PCR_Fields') ? PCR_Fields::get('icals_sync', $post_id) : get_post_meta($post_id, 'icals_sync', true);

        if (is_string($icals_sync)) {
            $icals_sync = json_decode($icals_sync, true);
        }

        if (!is_array($icals_sync) || empty($icals_sync)) {
            delete_post_meta($post_id, '_booked_dates_cache');
            return false;
        }

        $all_booked_dates = [];
        foreach ($icals_sync as $ical) {
            if (empty($ical['url'])) continue;

            // Timeout à 10s pour ne pas bloquer l'interface de sauvegarde trop longtemps
            $response = wp_remote_get($ical['url'], ['timeout' => 10]);

            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200) {
                continue;
            }

            $ical_data = wp_remote_retrieve_body($response);
            $booked_dates = $this->parse_ical_data($ical_data);

            if (!empty($booked_dates)) {
                $all_booked_dates = array_merge($all_booked_dates, $booked_dates);
            }
        }

        if (!empty($all_booked_dates)) {
            $all_booked_dates = array_unique($all_booked_dates);
            update_post_meta($post_id, '_booked_dates_cache', $all_booked_dates);
            return true;
        } else {
            delete_post_meta($post_id, '_booked_dates_cache');
            return false;
        }
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
