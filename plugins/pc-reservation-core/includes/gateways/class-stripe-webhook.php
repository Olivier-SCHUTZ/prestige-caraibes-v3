<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Écouteur de Webhook Stripe (Version Debug).
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

        // 1. Log d'entrée
        error_log('[Stripe Webhook] Réception d\'un signal...');

        $payload = @file_get_contents('php://input');
        $event = json_decode($payload, true);

        if (!$event || !isset($event['type'])) {
            error_log('[Stripe Webhook] Erreur : Payload invalide ou vide.');
            status_header(400);
            exit('Payload invalide');
        }

        // 2. Vérification du type d'événement
        if ($event['type'] !== 'checkout.session.completed') {
            // On ignore poliment les autres événements pour ne pas spammer les logs
            status_header(200);
            exit('Event ignored');
        }

        error_log('[Stripe Webhook] Événement checkout.session.completed reçu.');

        $session = $event['data']['object'];
        self::handle_checkout_session($session);

        status_header(200);
        exit('Webhook processed');
    }

    private static function handle_checkout_session($session)
    {
        global $wpdb;

        // Récupération ID Réservation
        $resa_id = isset($session['client_reference_id']) ? (int) $session['client_reference_id'] : 0;
        $stripe_id = $session['id'] ?? 'inconnu';
        $amount_received = isset($session['amount_total']) ? $session['amount_total'] / 100 : 0;

        error_log(sprintf('[Stripe Webhook] Session ID: %s | Resa ID: %d | Montant: %s', $stripe_id, $resa_id, $amount_received));

        if (!$resa_id) {
            error_log('[Stripe Webhook] Erreur : Pas de client_reference_id (ID réservation) dans la session.');
            return;
        }

        // --- A. Mise à jour de la ligne de paiement (pc_payments) ---
        $table_pay = $wpdb->prefix . 'pc_payments';

        // On cherche le paiement "en_attente" le plus pertinent pour cette réservation.
        // On vérifie le montant (marge d'erreur de 1€ pour arrondis) pour être sûr de valider le bon (acompte vs solde).
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_pay} 
             WHERE reservation_id = %d 
             AND statut != 'paye'
             AND (montant BETWEEN %f AND %f)
             LIMIT 1",
            $resa_id,
            $amount_received - 1,
            $amount_received + 1
        ));

        // Fallback : Si pas trouvé par montant, on prend le premier 'en_attente' (souvent l'acompte)
        if (!$payment) {
            error_log('[Stripe Webhook] Pas de paiement trouvé par montant exact. Tentative fallback sur le premier en attente.');
            $payment = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_pay} WHERE reservation_id = %d AND statut = 'en_attente' LIMIT 1",
                $resa_id
            ));
        }

        if ($payment) {
            $updated = $wpdb->update(
                $table_pay,
                [
                    'statut'            => 'paye',
                    'date_paiement'     => current_time('mysql'),
                    'gateway'           => 'stripe',
                    'gateway_reference' => $session['payment_intent'] ?? $stripe_id,
                    'gateway_status'    => $session['payment_status'],
                    'raw_response'      => json_encode($session),
                    'date_maj'          => current_time('mysql')
                ],
                ['id' => $payment->id]
            );

            if ($updated !== false) {
                error_log(sprintf('[Stripe Webhook] SUCCÈS : Paiement ID %d marqué comme PAYÉ.', $payment->id));
            } else {
                error_log(sprintf('[Stripe Webhook] ERREUR SQL lors de la mise à jour du paiement ID %d.', $payment->id));
            }
        } else {
            error_log('[Stripe Webhook] ERREUR CRITIQUE : Aucune ligne de paiement en attente trouvée pour cette réservation.');
        }

        // --- B. Mise à jour de la réservation globale ---
        self::update_reservation_status($resa_id);
    }

    private static function update_reservation_status($resa_id)
    {
        global $wpdb;
        $table_res = $wpdb->prefix . 'pc_reservations';
        $table_pay = $wpdb->prefix . 'pc_payments';

        $resa = $wpdb->get_row($wpdb->prepare("SELECT montant_total, statut_reservation FROM {$table_res} WHERE id = %d", $resa_id));
        if (!$resa) return;

        // Total déjà payé
        $paid_amount = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(montant) FROM {$table_pay} WHERE reservation_id = %d AND statut = 'paye'",
            $resa_id
        ));

        $total = (float) $resa->montant_total;
        $paid  = (float) $paid_amount;

        $new_status = 'non_paye';
        // Tolérance de 1€ pour les arrondis
        if ($paid >= ($total - 1) && $total > 0) {
            $new_status = 'paye';
        } elseif ($paid > 0) {
            $new_status = 'partiellement_paye';
        }

        error_log(sprintf('[Stripe Webhook] Recalcul Statut Résa #%d : Total=%s, Payé=%s => Nouveau statut: %s', $resa_id, $total, $paid, $new_status));

        $wpdb->update(
            $table_res,
            ['statut_paiement' => $new_status, 'date_maj' => current_time('mysql')],
            ['id' => $resa_id]
        );
    }
}
