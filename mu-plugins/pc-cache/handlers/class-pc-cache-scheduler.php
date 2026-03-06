<?php

/**
 * PC Cache Scheduler
 * Gère les tâches planifiées (CRON) pour les synchronisations
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Cache_Scheduler
{

    public function __construct()
    {
        // 1. Ajouter l'intervalle de temps
        add_filter('cron_schedules', [$this, 'add_cron_intervals']);

        // 2. Planifier la tâche si elle n'existe pas
        add_action('init', [$this, 'schedule_events']);

        // 3. Associer le hook de la tâche à notre Provider
        // On conserve EXACTEMENT le même nom de hook pour ne pas casser les crons déjà en file d'attente sur ton serveur
        add_action('pc_update_ical_cache_hook', [$this, 'execute_ical_sync']);
    }

    /**
     * Ajoute un intervalle "Toutes les 30 minutes"
     */
    public function add_cron_intervals($schedules)
    {
        $schedules['every_30_minutes'] = [
            'interval' => 1800, // 30 minutes
            'display'  => esc_html__('Every 30 Minutes'),
        ];
        return $schedules;
    }

    /**
     * Enregistre l'événement CRON
     */
    public function schedule_events()
    {
        if (!wp_next_scheduled('pc_update_ical_cache_hook')) {
            wp_schedule_event(time(), 'every_30_minutes', 'pc_update_ical_cache_hook');
        }
    }

    /**
     * Lance la synchronisation via le Provider
     */
    public function execute_ical_sync()
    {
        $provider = new PC_Ical_Cache_Provider();
        $provider->sync_all_logements();
    }
}
