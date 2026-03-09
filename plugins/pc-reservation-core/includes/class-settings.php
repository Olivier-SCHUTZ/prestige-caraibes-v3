<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCR Settings - Façade de Configuration (Refactoring v2)
 * Cette classe agit désormais comme un proxy vers la nouvelle architecture 
 * (Config / Controller / Simulator) pour garantir la non-régression (Pattern Strangler).
 * * @since 1.0.0
 * @since 2.0.0 (Refactoring en Façade)
 */
class PCR_Settings
{
    /**
     * Initialisation globale appelée par le plugin principal.
     */
    public static function init()
    {
        // Enregistrement des pages d'options
        PCR_Settings_Config::get_instance()->register_options_pages();

        // Enregistrement des champs ACF (sur le hook acf/init)
        add_action('acf/init', [PCR_Settings_Config::get_instance(), 'register_acf_fields']);

        // Initialisation des hooks AJAX et UI
        PCR_Settings_Controller::get_instance()->init_hooks();
    }

    /**
     * Méthode de rétrocompatibilité pour l'enregistrement des champs.
     */
    public static function register_fields()
    {
        PCR_Settings_Config::get_instance()->register_acf_fields();
    }

    /**
     * Méthode de rétrocompatibilité pour le webhook non-AJAX.
     */
    public static function handle_webhook_simulation()
    {
        PCR_Settings_Controller::get_instance()->handle_webhook_simulation();
    }

    /**
     * Méthode de rétrocompatibilité pour l'injection JS.
     */
    public static function print_admin_scripts()
    {
        PCR_Settings_Controller::get_instance()->print_admin_scripts();
    }

    /**
     * Méthode de rétrocompatibilité pour l'AJAX.
     */
    public static function ajax_handle_simulation()
    {
        PCR_Settings_Controller::get_instance()->ajax_handle_simulation();
    }
}
