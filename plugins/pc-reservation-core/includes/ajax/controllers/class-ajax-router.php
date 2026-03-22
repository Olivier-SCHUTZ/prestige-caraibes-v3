<?php
if (!defined('ABSPATH')) {
    exit; // Sécurité : Empêche l'accès direct au fichier
}

/**
 * Routeur principal pour les requêtes AJAX du Dashboard.
 * Pattern Singleton pour garantir une instance unique.
 */
class PCR_Ajax_Router
{
    /**
     * @var PCR_Ajax_Router Instance unique de la classe
     */
    private static $instance = null;

    /**
     * Empêche l'instanciation directe (Singleton)
     */
    private function __construct() {}

    /**
     * Empêche le clonage (Singleton)
     */
    private function __clone() {}

    /**
     * Récupère l'instance unique du routeur.
     *
     * @return PCR_Ajax_Router
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialise tous les hooks AJAX.
     * Nous allons migrer les add_action() ici étape par étape.
     */
    public function init()
    {
        // --------------------------------------------------------
        // 🗓️ 1. CALENDRIER (Sera migré à l'Étape 3)
        // --------------------------------------------------------
        add_action('wp_ajax_pc_calendar_create_resa', ['PCR_Calendar_Ajax_Controller', 'ajax_calendar_create_resa']);
        add_action('wp_ajax_pc_get_calendar_global', ['PCR_Calendar_Ajax_Controller', 'ajax_get_calendar_global']);
        add_action('wp_ajax_nopriv_pc_get_calendar_global', ['PCR_Calendar_Ajax_Controller', 'ajax_get_calendar_global']);
        add_action('wp_ajax_pc_get_global_calendar', ['PCR_Calendar_Ajax_Controller', 'ajax_get_global_calendar']);
        add_action('wp_ajax_nopriv_pc_get_global_calendar', ['PCR_Calendar_Ajax_Controller', 'ajax_get_global_calendar']);
        add_action('wp_ajax_pc_get_single_calendar', ['PCR_Calendar_Ajax_Controller', 'ajax_get_single_calendar']);
        add_action('wp_ajax_nopriv_pc_get_single_calendar', ['PCR_Calendar_Ajax_Controller', 'ajax_get_single_calendar']);
        add_action('wp_ajax_pc_calendar_create_block', ['PCR_Calendar_Ajax_Controller', 'ajax_calendar_create_block']);
        add_action('wp_ajax_pc_calendar_delete_block', ['PCR_Calendar_Ajax_Controller', 'ajax_calendar_delete_block']);
        add_action('wp_ajax_pc_calendar_update_block', ['PCR_Calendar_Ajax_Controller', 'ajax_calendar_update_block']);

        // --------------------------------------------------------
        // 🛎️ 2. RÉSERVATIONS
        // --------------------------------------------------------
        add_action('wp_ajax_pc_get_reservations_list', ['PCR_Reservation_Ajax_Controller', 'ajax_get_reservations_list']);
        add_action('wp_ajax_pc_get_reservation_details', ['PCR_Reservation_Ajax_Controller', 'ajax_get_reservation_details']);
        add_action('wp_ajax_pc_get_booking_items', ['PCR_Reservation_Ajax_Controller', 'ajax_get_booking_items']);
        add_action('wp_ajax_pc_calculate_price', ['PCR_Reservation_Ajax_Controller', 'ajax_calculate_price']);
        add_action('wp_ajax_pc_manual_reservation_create', ['PCR_Reservation_Ajax_Controller', 'handle_manual_reservation']);
        add_action('wp_ajax_nopriv_pc_manual_reservation_create', ['PCR_Reservation_Ajax_Controller', 'handle_manual_reservation']);
        add_action('wp_ajax_pc_manual_logement_config', ['PCR_Reservation_Ajax_Controller', 'handle_logement_config']);
        add_action('wp_ajax_nopriv_pc_manual_logement_config', ['PCR_Reservation_Ajax_Controller', 'handle_logement_config']);
        add_action('wp_ajax_pc_cancel_reservation', ['PCR_Reservation_Ajax_Controller', 'ajax_cancel_reservation']);
        add_action('wp_ajax_pc_confirm_reservation', ['PCR_Reservation_Ajax_Controller', 'ajax_confirm_reservation']);

        // --------------------------------------------------------
        // ✉️ 3. MESSAGERIE & CHANNEL MANAGER
        // --------------------------------------------------------
        add_action('wp_ajax_pc_send_message', ['PCR_Messaging_Ajax_Controller', 'ajax_send_message']);
        add_action('wp_ajax_pc_get_conversation_history', ['PCR_Messaging_Ajax_Controller', 'ajax_get_conversation_history']);
        add_action('wp_ajax_pc_mark_messages_read', ['PCR_Messaging_Ajax_Controller', 'ajax_mark_messages_read']);
        add_action('wp_ajax_pc_get_quick_replies', ['PCR_Messaging_Ajax_Controller', 'ajax_get_quick_replies']);

        // --------------------------------------------------------
        // 📄 4. DOCUMENTS
        // --------------------------------------------------------
        add_action('wp_ajax_pc_get_reservation_files', ['PCR_Document_Ajax_Controller', 'ajax_get_reservation_files']);
        add_action('wp_ajax_pc_get_documents_templates', ['PCR_Document_Ajax_Controller', 'ajax_get_documents_templates']);

        // Ces deux-là appelaient déjà la classe métier directement, on les migre ici pour centraliser le routage
        add_action('wp_ajax_pc_get_documents_list', ['PCR_Documents', 'ajax_get_documents_list']);
        add_action('wp_ajax_pc_generate_document', ['PCR_Documents', 'ajax_generate_document']);

        // --------------------------------------------------------
        // 🏠 5. HOUSING MANAGER
        // --------------------------------------------------------
        add_action('wp_ajax_pc_housing_get_list', ['PCR_Housing_Ajax_Controller', 'ajax_housing_get_list']);
        add_action('wp_ajax_pc_housing_get_details', ['PCR_Housing_Ajax_Controller', 'ajax_housing_get_details']);
        add_action('wp_ajax_pc_housing_save', ['PCR_Housing_Ajax_Controller', 'ajax_housing_save']);
        add_action('wp_ajax_pc_housing_delete', ['PCR_Housing_Ajax_Controller', 'ajax_housing_delete']);

        // --------------------------------------------------------
        // 🌴 6. EXPERIENCE MANAGER
        // --------------------------------------------------------
        add_action('wp_ajax_pc_experience_get_list', ['PCR_Experience_Ajax_Controller', 'ajax_experience_get_list']);
        add_action('wp_ajax_pc_experience_get_details', ['PCR_Experience_Ajax_Controller', 'ajax_experience_get_details']);
        add_action('wp_ajax_pc_experience_save', ['PCR_Experience_Ajax_Controller', 'ajax_experience_save']);
        add_action('wp_ajax_pc_experience_delete', ['PCR_Experience_Ajax_Controller', 'ajax_experience_delete']);

        // --------------------------------------------------------
        // ⚙️ 7. FIELD MANAGER NATIVE (Refonte ACF)
        // --------------------------------------------------------
        add_action('wp_ajax_pc_get_native_fields', ['PCR_Field_Ajax_Controller', 'ajax_get_fields']);
        add_action('wp_ajax_pc_save_native_fields', ['PCR_Field_Ajax_Controller', 'ajax_save_fields']);
    }
}
