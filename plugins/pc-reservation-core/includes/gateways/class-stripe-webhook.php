<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Écouteur de Webhook Stripe (Version Finale : Paiements + Cautions).
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

        // Log d'entrée
        error_log('[Stripe Webhook] Signal reçu...');

        $payload = @file_get_contents('php://input');
        $event = json_decode($payload, true);

        if (!$event || !isset($event['type'])) {
            status_header(400);
            exit('Payload invalide');
        }

        if ($event['type'] !== 'checkout.session.completed') {
            status_header(200);
            exit('Event ignored');
        }

        $session = $event['data']['object'];
        self::handle_checkout_session($session);

        status_header(200);
        exit('Webhook processed');
    }

    private static function handle_checkout_session($session)
    {
        global $wpdb;

        $resa_id = isset($session['client_reference_id']) ? (int) $session['client_reference_id'] : 0;
        $stripe_id = $session['id'] ?? 'inconnu';
        $payment_intent = $session['payment_intent'] ?? $stripe_id;

        // On récupère le type depuis les métadonnées (ajouté lors de la création du lien)
        $type_action = isset($session['metadata']['type']) ? $session['metadata']['type'] : 'payment';

        error_log(sprintf('[Stripe Webhook] Session: %s | Resa: %d | Type: %s', $stripe_id, $resa_id, $type_action));

        if (!$resa_id) return;

        // ====================================================
        // CAS 1 : C'EST UNE CAUTION (Empreinte Bancaire)
        // ====================================================
        if ($type_action === 'caution') {
            $table_res = $wpdb->prefix . 'pc_reservations';

            $updated = $wpdb->update(
                $table_res,
                [
                    'caution_statut'          => 'empreinte_validee',
                    'caution_reference'       => $payment_intent, // ID pour capturer/libérer plus tard
                    'caution_date_validation' => current_time('mysql'),
                    'date_maj'                => current_time('mysql')
                ],
                ['id' => $resa_id]
            );

            if ($updated !== false) {
                error_log(sprintf('[Stripe Webhook] ✅ Caution validée pour Résa #%d (Ref: %s)', $resa_id, $payment_intent));
            } else {
                error_log(sprintf('[Stripe Webhook] ❌ Erreur SQL update caution Résa #%d', $resa_id));
            }
            return; // On s'arrête là pour une caution
        }

        // ====================================================
        // CAS 2 : C'EST UN PAIEMENT CLASSIQUE (Acompte/Solde)
        // ====================================================
        $amount_received = isset($session['amount_total']) ? $session['amount_total'] / 100 : 0;
        $table_pay = $wpdb->prefix . 'pc_payments';

        // Recherche du paiement correspondant (par montant approximatif)
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

        // Fallback : premier paiement en attente
        if (!$payment) {
            $payment = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_pay} WHERE reservation_id = %d AND statut = 'en_attente' LIMIT 1",
                $resa_id
            ));
        }

        if ($payment) {
            $wpdb->update(
                $table_pay,
                [
                    'statut'            => 'paye',
                    'date_paiement'     => current_time('mysql'),
                    'gateway'           => 'stripe',
                    'gateway_reference' => $payment_intent,
                    'gateway_status'    => $session['payment_status'],
                    'raw_response'      => json_encode($session),
                    'date_maj'          => current_time('mysql')
                ],
                ['id' => $payment->id]
            );
            error_log(sprintf('[Stripe Webhook] ✅ Paiement ID %d validé.', $payment->id));
        }

        // Mise à jour du statut global de la réservation
        self::update_reservation_status($resa_id);
    }

    private static function update_reservation_status($resa_id)
    {
        global $wpdb;
        $table_res = $wpdb->prefix . 'pc_reservations';
        $table_pay = $wpdb->prefix . 'pc_payments';

        $resa = $wpdb->get_row($wpdb->prepare("SELECT montant_total FROM {$table_res} WHERE id = %d", $resa_id));
        if (!$resa) return;

        $paid_amount = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(montant) FROM {$table_pay} WHERE reservation_id = %d AND statut = 'paye'",
            $resa_id
        ));

        $total = (float) $resa->montant_total;
        $paid  = (float) $paid_amount;

        $new_status = 'non_paye';
        if ($paid >= ($total - 1) && $total > 0) {
            $new_status = 'paye';
        } elseif ($paid > 0) {
            $new_status = 'partiellement_paye';
        }

        $wpdb->update(
            $table_res,
            ['statut_paiement' => $new_status, 'date_maj' => current_time('mysql')],
            ['id' => $resa_id]
        );
    }
}
