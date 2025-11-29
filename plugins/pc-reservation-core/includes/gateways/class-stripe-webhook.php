<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Écouteur de Webhook Stripe.
 * Met à jour les statuts de paiement et de réservation automatiquement.
 * URL : https://votre-site.com/?pc_action=stripe_webhook
 */
class PCR_Stripe_Webhook
{
    public static function init()
    {
        add_action('init', [__CLASS__, 'listen']);
    }

    public static function listen()
    {
        if (!isset($_GET['pc_action']) || $_GET['pc_action'] !== 'stripe_webhook') {
            return;
        }

        // 1. Récupération du corps de la requête (JSON brut)
        $payload = @file_get_contents('php://input');
        $event = json_decode($payload, true);

        if (!$event || !isset($event['type'])) {
            status_header(400);
            exit('Payload invalide');
        }

        // 2. Filtrage : On ne traite que les sessions de paiement réussies
        if ($event['type'] === 'checkout.session.completed') {
            self::handle_checkout_session($event['data']['object']);
        }

        // Stripe attend un code 200 pour savoir que tout va bien
        status_header(200);
        exit('Webhook reçu');
    }

    /**
     * Traite le succès d'un paiement.
     */
    private static function handle_checkout_session($session)
    {
        // Récupération des métadonnées injectées lors de la création du lien
        $resa_id = isset($session['client_reference_id']) ? (int) $session['client_reference_id'] : 0;

        // On peut avoir passé le type de paiement dans les métadonnées
        $payment_type = isset($session['metadata']['payment_type']) ? $session['metadata']['payment_type'] : '';

        if (!$resa_id) {
            return; // Pas de lien avec une réservation
        }

        // --- A. Mise à jour de la ligne de paiement (pc_payments) ---
        global $wpdb;
        $table_pay = $wpdb->prefix . 'pc_payments';

        // On cherche la ligne de paiement correspondante 'en_attente' pour cette réservation
        // Idéalement, on aurait stocké l'ID du paiement dans les métadonnées, 
        // mais sinon on prend la plus logique (celle qui correspond au montant ou au type)

        $sql = "SELECT id FROM {$table_pay} WHERE reservation_id = %d AND statut != 'paye'";

        if ($payment_type) {
            $sql .= $wpdb->prepare(" AND type_paiement = %s", $payment_type);
        }

        // On trie par ID pour prendre la plus ancienne (souvent l'acompte)
        $sql .= " ORDER BY id ASC LIMIT 1";

        $payment_id = $wpdb->get_var($wpdb->prepare($sql, $resa_id));

        if ($payment_id) {
            $wpdb->update(
                $table_pay,
                [
                    'statut' => 'paye',
                    'date_paiement' => current_time('mysql'),
                    'gateway' => 'stripe',
                    'gateway_reference' => $session['payment_intent'] ?? $session['id'],
                    'gateway_status' => $session['payment_status'],
                    'raw_response' => json_encode($session), // On garde une trace pour debug
                    'date_maj' => current_time('mysql')
                ],
                ['id' => $payment_id]
            );
        }

        // --- B. Mise à jour de la réservation globale (pc_reservations) ---
        // On vérifie s'il reste de l'argent à payer
        self::update_reservation_status($resa_id);
    }

    /**
     * Recalcule le statut global de la réservation
     */
    private static function update_reservation_status($resa_id)
    {
        global $wpdb;
        $table_res = $wpdb->prefix . 'pc_reservations';
        $table_pay = $wpdb->prefix . 'pc_payments';

        $resa = $wpdb->get_row($wpdb->prepare("SELECT montant_total, statut_reservation FROM {$table_res} WHERE id = %d", $resa_id));
        if (!$resa) return;

        // Somme des paiements validés
        $paid_amount = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(montant) FROM {$table_pay} WHERE reservation_id = %d AND statut = 'paye'",
            $resa_id
        ));

        $total = (float) $resa->montant_total;
        $paid  = (float) $paid_amount;

        $new_status = 'non_paye';

        if ($paid >= $total && $total > 0) {
            $new_status = 'paye';
        } elseif ($paid > 0) {
            $new_status = 'partiellement_paye'; // Acompte versé
        }

        // On met à jour
        $wpdb->update(
            $table_res,
            ['statut_paiement' => $new_status, 'date_maj' => current_time('mysql')],
            ['id' => $resa_id]
        );
    }
}
