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
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        // Sécurisation maximale pour la récupération de l'option (Natif > PCR_Fields > ACF)
        $endpoint_secret = PCR_Fields::get('pc_stripe_webhook_secret', 'option', '');

        if (empty($endpoint_secret)) {
            error_log('[Stripe Webhook] ❌ Erreur : Webhook secret non configuré.');
            status_header(500);
            exit('Configuration error');
        }

        $event = null;
        try {
            // Cette méthode valide la signature ET retourne l'objet événement sécurisé
            $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
        } catch (\UnexpectedValueException $e) {
            error_log('[Stripe Webhook] ❌ Erreur : Payload invalide.');
            status_header(400);
            exit('Payload invalide');
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            error_log('[Stripe Webhook] 🚨 ALERTE SÉCURITÉ : Signature invalide - tentative de fraude ? IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            status_header(400);
            exit('Signature invalide');
        }

        // Utiliser l'objet $event sécurisé généré par la librairie Stripe
        if ($event->type !== 'checkout.session.completed') {
            status_header(200);
            exit('Event ignored');
        }

        // Récupérer l'objet session de manière sécurisée et le convertir en vrai tableau PHP
        $session = $event->data->object;
        self::handle_checkout_session($session->toArray());
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
        $payment_id_meta = isset($session['metadata']['payment_id']) ? (int) $session['metadata']['payment_id'] : 0;

        $payment = null;

        // 1. Recherche précise par ID de paiement (priorité absolue)
        if ($payment_id_meta > 0) {
            $payment = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_pay} WHERE id = %d AND statut != 'paye'", $payment_id_meta));
        }

        // 2. Fallback : Recherche du paiement correspondant (par montant approximatif)
        if (!$payment) {
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
        }

        // Fallback : premier paiement en attente
        if (!$payment) {
            $payment = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_pay} WHERE reservation_id = %d AND statut = 'en_attente' LIMIT 1",
                $resa_id
            ));
        }

        if ($payment) {
            // Validation stricte du montant
            $expected_amount = (float) $payment->montant;
            if (abs($amount_received - $expected_amount) > 0.01) {
                error_log(sprintf(
                    '[SECURITY ALERT] Montant suspect pour le paiement ID %d - Attendu: %.2f€, Reçu: %.2f€, Session: %s',
                    $payment->id,
                    $expected_amount,
                    $amount_received,
                    $stripe_id
                ));
                return; // On stoppe le processus silencieusement pour ne pas valider la ligne
            }

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
