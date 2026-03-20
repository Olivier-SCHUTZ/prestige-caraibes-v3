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
        add_action('wp_ajax_pcr_get_dashboard_stats', [__CLASS__, 'get_dashboard_stats']);
        // NOUVEAU HOOK POUR LES PAIEMENTS MANUELS
        add_action('wp_ajax_pc_update_payment_status', [__CLASS__, 'update_payment_status']);
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

    /**
     * Met à jour manuellement le statut d'un paiement (Virement, Espèces...)
     */
    public static function update_payment_status()
    {
        check_ajax_referer('pc_resa_manual_create', 'security');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Non autorisé. Veuillez vous connecter.'], 403);
            wp_die();
        }

        $payment_id = isset($_POST['payment_id']) ? (int) $_POST['payment_id'] : 0;
        $status     = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $method     = isset($_POST['method']) ? sanitize_text_field($_POST['method']) : '';

        if (!$payment_id || !$status) {
            wp_send_json_error(['message' => 'Données manquantes.']);
        }

        // On appelle le repository (qui est déjà chargé par ton autoloader)
        $repo = PCR_Payment_Repository::get_instance();

        $data = [
            'statut'       => $status,
            'methode'      => $method,
            'url_paiement' => null // On vide l'URL Stripe pour la désactiver visuellement
        ];

        // Si on marque comme payé, on enregistre la date du jour
        if ($status === 'paye') {
            $data['date_paiement'] = current_time('mysql');
        }

        $updated = $repo->update_payment($payment_id, $data);

        if ($updated) {
            // --- NOUVEAU : On réveille le cerveau central ---
            global $wpdb;
            $table_pay = $wpdb->prefix . 'pc_payments';
            // On retrouve l'ID de la réservation liée à ce paiement
            $resa_id = $wpdb->get_var($wpdb->prepare("SELECT reservation_id FROM {$table_pay} WHERE id = %d", $payment_id));

            if ($resa_id) {
                // On recalcule le statut global (ATTENTION: Remplace PCR_Reservation par le vrai nom de ta classe si besoin)
                PCR_Reservation::update_payment_status($resa_id);
            }
            // -------------------------------------------------

            wp_send_json_success(['message' => 'Statut de paiement mis à jour.']);
        } else {
            wp_send_json_error(['message' => 'Erreur lors de la mise à jour en base.']);
        }

        wp_die();
    }
}

// Initialisation immédiate du contrôleur
PCR_Dashboard_API_Controller::init();
