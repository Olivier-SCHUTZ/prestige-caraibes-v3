<?php
/**
 * Plugin Name: PC – iCal Availability Cache
 * Description: Fetches iCal data periodically and stores booked dates in a custom field for fast availability searches.
 * Author: Prestige Caraïbes + Gemini
 * Version: 1.0.0
 */

if ( ! defined('ABSPATH') ) exit;

// --- 1. Ajouter un intervalle de temps personnalisé pour le Cron ---
// On ajoute une option "Toutes les 30 minutes" pour notre tâche.
add_filter('cron_schedules', 'pc_add_cron_interval');
function pc_add_cron_interval($schedules) {
    $schedules['every_30_minutes'] = [
        'interval' => 1800, // 30 minutes * 60 seconds
        'display'  => esc_html__('Every 30 Minutes'),
    ];
    return $schedules;
}

// --- 2. Planifier la tâche si elle n'existe pas déjà ---
// On crée un "hook" (un crochet) qui va lancer notre fonction.
if ( ! wp_next_scheduled('pc_update_ical_cache_hook') ) {
    wp_schedule_event(time(), 'every_30_minutes', 'pc_update_ical_cache_hook');
}

// --- 3. Lier notre fonction à la tâche planifiée ---
add_action('pc_update_ical_cache_hook', 'pc_update_ical_cache_function');

/**
 * La fonction principale qui s'exécute toutes les 30 minutes.
 * Elle va chercher les logements, lire leur iCal et sauvegarder les dates.
 */
function pc_update_ical_cache_function() {

    // On cherche tous les logements qui ont un champ 'ical_url' rempli.
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
        'fields' => 'ids', // On ne récupère que les IDs, c'est plus léger.
    ];

    $logement_ids = new WP_Query($args);

    if ( ! $logement_ids->have_posts() ) {
        return; // Pas de logements avec iCal, on arrête.
    }

    foreach ( $logement_ids->posts as $post_id ) {
        $ical_url = get_field('ical_url', $post_id);

        // On récupère le contenu du fichier iCal
        $response = wp_remote_get($ical_url, ['timeout' => 20]);

        if ( is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200 ) {
            // Si l'URL ne fonctionne pas, on passe au suivant.
            continue;
        }

        $ical_data = wp_remote_retrieve_body($response);

        // On parse le fichier pour extraire les dates
        $booked_dates = pc_parse_ical_data($ical_data);

        // On sauvegarde la liste des dates occupées dans un champ caché.
        // Ce champ sera écrasé à chaque mise à jour.
        if ( ! empty($booked_dates) ) {
            update_post_meta($post_id, '_booked_dates_cache', $booked_dates);
        } else {
            // S'il n'y a pas de dates, on s'assure que le champ est vide.
            delete_post_meta($post_id, '_booked_dates_cache');
        }
    }
}

/**
 * Fonction simple pour parser les données iCal et extraire les dates.
 * @param string $ical_string Le contenu du fichier .ics.
 * @return array La liste des dates occupées au format Y-m-d.
 */
function pc_parse_ical_data($ical_string) {
    $events = [];
    preg_match_all('/BEGIN:VEVENT.*?END:VEVENT/si', $ical_string, $matches);

    if ( empty($matches[0]) ) {
        return [];
    }
    
    foreach ( $matches[0] as $event_str ) {
        $start_date = false;
        $end_date = false;

        if ( preg_match('/DTSTART(?:;[^:]+)?:(\d{8})/', $event_str, $start_match) ) {
            $start_date = DateTime::createFromFormat('Ymd', $start_match[1]);
        }
        
        if ( preg_match('/DTEND(?:;[^:]+)?:(\d{8})/', $event_str, $end_match) ) {
            // La date de fin en iCal est exclusive, donc on s'arrête la veille.
            $end_date = DateTime::createFromFormat('Ymd', $end_match[1]);
        }

        if ( $start_date && $end_date ) {
            // On génère la liste de toutes les dates pour cette réservation.
            $period = new DatePeriod($start_date, new DateInterval('P1D'), $end_date);
            foreach ($period as $date) {
                $events[] = $date->format('Y-m-d');
            }
        }
    }

    return array_unique($events);
}