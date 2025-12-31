<?php
if (!defined('ABSPATH')) exit;

class PCSC_Webhooks
{
    public static function init(): void
    {
        add_action('rest_api_init', function () {
            register_rest_route('pcsc/v1', '/stripe/webhook', [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'handle'],
                'permission_callback' => '__return_true'
            ]);
        });
    }

    private static function get_webhook_secret(): string
    {
        // 1. Priorité aux réglages plugin
        if (class_exists('PCSC_Settings')) {
            $secret = PCSC_Settings::get_option('stripe_webhook_secret');
            if (!empty($secret)) return $secret;
        }

        // 2. Fallback constante wp-config
        return defined('PC_STRIPE_WEBHOOK_SECRET') ? PC_STRIPE_WEBHOOK_SECRET : '';
    }

    public static function handle(WP_REST_Request $req)
    {
        $payload = $req->get_body();
        $sig_header = $req->get_header('stripe-signature');
        $secret = self::get_webhook_secret();

        // --- SÉCURITÉ : Vérification de la signature ---
        if (!empty($secret)) {
            if (empty($sig_header)) {
                return new WP_REST_Response(['error' => 'Signature manquante'], 400);
            }

            if (!self::verify_signature($payload, $sig_header, $secret)) {
                // Log de sécurité (utile pour debugger au début)
                error_log('[PCSC] Échec validation signature Webhook.');
                return new WP_REST_Response(['error' => 'Signature invalide'], 400);
            }
        }
        // -----------------------------------------------

        $event = json_decode($payload, true);
        if (!$event || empty($event['type'])) return new WP_REST_Response(['ok' => false], 400);

        if ($event['type'] === 'checkout.session.completed') {
            $obj = $event['data']['object'];
            $case_id = $obj['metadata']['pc_case_id'] ?? null;
            $setup_intent_id = $obj['setup_intent'] ?? '';

            if ($case_id) {
                $case_id = (int)$case_id;
                // On récupère les détails finaux
                $si = PCSC_Stripe::retrieve_setup_intent($setup_intent_id);
                if ($si['ok']) {
                    $pm = $si['data']['payment_method'] ?? '';
                    $cust_from_stripe = $si['data']['customer'] ?? '';

                    $update_data = [
                        'status' => 'setup_ok',
                        'stripe_setup_intent_id' => $setup_intent_id,
                        'stripe_payment_method_id' => $pm,
                        'last_error' => null,
                    ];

                    if ($cust_from_stripe) {
                        $update_data['stripe_customer_id'] = $cust_from_stripe;
                    }

                    PCSC_DB::update_case($case_id, $update_data);
                    PCSC_DB::append_note($case_id, "Webhook OK (Sécurisé) : Carte ($pm) confirmée.");

                    if (class_exists('PCSC_Mailer')) {
                        $case = PCSC_DB::get_case($case_id);
                        if ($case) {
                            PCSC_Mailer::send_admin_card_saved($case['booking_ref'], $case['customer_email']);
                            PCSC_DB::append_note($case_id, "Mail alerte Admin envoyé.");
                        }
                    }
                }
            }
        }
        return new WP_REST_Response(['ok' => true], 200);
    }

    /**
     * Vérification manuelle de la signature Stripe (HMAC SHA256)
     * Sans dépendre de la librairie officielle Stripe PHP.
     */
    private static function verify_signature(string $payload, string $sig_header, string $secret): bool
    {
        // Format header : t=1492774577,v1=5257a869e7ecebeda32...
        $parts = explode(',', $sig_header);
        $timestamp = '';
        $signature = '';

        foreach ($parts as $part) {
            $sub = explode('=', $part, 2);
            if (count($sub) === 2) {
                if (trim($sub[0]) === 't') $timestamp = trim($sub[1]);
                if (trim($sub[0]) === 'v1') $signature = trim($sub[1]);
            }
        }

        if (empty($timestamp) || empty($signature)) return false;

        // Protection Replay Attack (tolérance 5 min)
        if (abs(time() - (int)$timestamp) > 300) return false;

        $signed_payload = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $signed_payload, $secret);

        return hash_equals($expected, $signature);
    }
}
