<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gère les requêtes AJAX pour Stripe (Génération de liens).
 */
class PCR_Stripe_Ajax
{
    public static function init()
    {
        // Action pour l'admin connecté
        add_action('wp_ajax_pc_stripe_get_link', [__CLASS__, 'handle_get_link']);
        // Pas de nopriv ici : c'est l'admin qui génère le lien manuellement.
        // Pour le front, c'est le contrôleur de formulaire qui s'en chargera directement.
    }

    public static function handle_get_link()
    {
        // 1. Sécurité
        check_ajax_referer('pc_resa_manual_create', 'nonce'); // On réutilise le nonce du dashboard

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Non autorisé.']);
        }

        // 2. Récupération des données
        $payment_id = isset($_POST['payment_id']) ? (int) $_POST['payment_id'] : 0;

        if ($payment_id <= 0) {
            wp_send_json_error(['message' => 'ID de paiement manquant.']);
        }

        // 3. Lecture du paiement en base
        global $wpdb;
        $table_pay = $wpdb->prefix . 'pc_payments';
        $payment = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_pay} WHERE id = %d", $payment_id));

        if (!$payment) {
            wp_send_json_error(['message' => 'Ligne de paiement introuvable.']);
        }

        if ($payment->statut === 'paye') {
            wp_send_json_error(['message' => 'Ce montant est déjà payé.']);
        }

        $amount = (float) $payment->montant;
        if ($amount <= 0) {
            wp_send_json_error(['message' => 'Montant nul, pas de paiement nécessaire.']);
        }

        // 4. Appel au Manager Stripe
        if (!class_exists('PCR_Stripe_Manager')) {
            wp_send_json_error(['message' => 'Classe Stripe Manager absente.']);
        }

        // On passe l'ID de la RÉSERVATION, pas du paiement, car le Manager a besoin des infos client
        // Mais on pourra passer l'ID du paiement en métadonnée si on améliore le Manager plus tard.
        // Pour l'instant, le manager attend ($reservation_id, $amount, $type).

        $result = PCR_Stripe_Manager::create_payment_link(
            $payment->reservation_id,
            $amount,
            $payment->type_paiement
        );

        if (!$result['success']) {
            wp_send_json_error(['message' => $result['message']]);
        }

        // 5. Succès : on renvoie l'URL
        wp_send_json_success([
            'url' => $result['url'],
            'id'  => $result['id'] // ID session Stripe
        ]);
    }
}
