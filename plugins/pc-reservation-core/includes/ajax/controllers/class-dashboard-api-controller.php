<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Contrôleur API pour le Dashboard V2 (Vue.js)
 * Gère les requêtes AJAX provenant de l'application moderne via Axios.
 */
class PCR_Dashboard_API_Controller
{

    /**
     * Initialise les hooks WordPress
     */
    public static function init()
    {
        // Le hook correspond à l'action "pcr_get_dashboard_stats" définie dans notre Store Pinia
        add_action('wp_ajax_pcr_get_dashboard_stats', [__CLASS__, 'get_dashboard_stats']);
    }

    /**
     * Retourne les statistiques pour les cartes du Dashboard
     */
    public static function get_dashboard_stats()
    {
        // 1. SÉCURITÉ ABSOLUE : Vérification du Nonce injecté par notre api-client.js
        // On utilise le nonce 'pc_resa_manual_create' qui est déjà déclaré dans tes variables JS globales
        check_ajax_referer('pc_resa_manual_create', 'security');

        // 2. VÉRIFICATION DES DROITS : S'assurer que l'utilisateur est bien connecté
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Non autorisé. Veuillez vous connecter.'], 403);
            wp_die();
        }

        // 3. LOGIQUE MÉTIER : 
        // Plus tard, tu remplaceras ces valeurs fixes par des appels à tes futurs 
        // Services de la Phase 1 (ex: ReservationRepository::get_total_for_user( $user_id ))
        // Pour valider le flux, on renvoie de VRAIES données structurées, différentes de nos mock data !

        $user_id = get_current_user_id();

        $stats = [
            'totalReservations' => 42,       // Changé (était 12 dans le Mock Front)
            'revenue'           => 15600,    // Changé (était 4250 dans le Mock Front)
            'pendingMessages'   => 1         // Changé (était 3 dans le Mock Front)
        ];

        // 4. RÉPONSE : Retourne un JSON propre ({ success: true, data: { ... } })
        wp_send_json_success($stats);
        wp_die();
    }
}

// Initialisation immédiate du contrôleur
PCR_Dashboard_API_Controller::init();
