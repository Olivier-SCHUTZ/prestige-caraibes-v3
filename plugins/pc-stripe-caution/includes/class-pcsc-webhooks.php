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
                'permission_callback' => '__return_true',
            ]);
        });
    }

    private static function verify_signature(string $payload, string $sig_header, string $secret): bool
    {
        // Stripe-Signature: t=...,v1=...,v0=...
        if (!$secret) return false;
        if (!$sig_header) return false;

        $parts = explode(',', $sig_header);
        $t = null;
        $v1s = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if (strpos($p, 't=') === 0) $t = substr($p, 2);
            if (strpos($p, 'v1=') === 0) $v1s[] = substr($p, 3);
        }
        if (!$t || empty($v1s)) return false;

        $signed = $t . '.' . $payload;
        $expected = hash_hmac('sha256', $signed, $secret);

        foreach ($v1s as $sig) {
            if (hash_equals($expected, $sig)) return true;
        }
        return false;
    }

    public static function handle(WP_REST_Request $req)
    {
        $payload = $req->get_body();
        $sig = $req->get_header('stripe-signature');

        $secret = defined('PC_STRIPE_WEBHOOK_SECRET') ? PC_STRIPE_WEBHOOK_SECRET : '';
        if (!self::verify_signature($payload, $sig, $secret)) {
            return new WP_REST_Response(['ok' => false, 'error' => 'Invalid signature'], 400);
        }

        $event = json_decode($payload, true);
        if (!is_array($event) || empty($event['type'])) {
            return new WP_REST_Response(['ok' => false, 'error' => 'Invalid payload'], 400);
        }

        $type = $event['type'];
        $obj  = $event['data']['object'] ?? [];

        // On cible surtout la fin du setup
        if ($type === 'checkout.session.completed') {
            // Si mode=setup, on récupère setup_intent
            $mode = $obj['mode'] ?? '';
            if ($mode === 'setup') {
                $setup_intent_id = $obj['setup_intent'] ?? '';
                $customer_id = $obj['customer'] ?? '';
                $case_id = $obj['metadata']['pc_case_id'] ?? null;

                if ($setup_intent_id && $customer_id && $case_id) {
                    $si = PCSC_Stripe::retrieve_setup_intent($setup_intent_id);
                    if ($si['ok']) {
                        $pm = $si['data']['payment_method'] ?? '';
                        PCSC_DB::update_case((int)$case_id, [
                            'status' => 'setup_ok',
                            'stripe_setup_intent_id' => $setup_intent_id,
                            'stripe_customer_id' => $customer_id,
                            'stripe_payment_method_id' => $pm,
                            'last_error' => null,
                        ]);
                        PCSC_DB::append_note((int)$case_id, 'Setup terminé (Checkout). PM enregistré chez Stripe.');
                    } else {
                        PCSC_DB::update_case((int)$case_id, [
                            'status' => 'setup_failed',
                            'last_error' => $si['error'] ?? 'SetupIntent retrieve failed',
                        ]);
                        PCSC_DB::append_note((int)$case_id, 'Setup terminé mais récupération SetupIntent en échec.');
                    }
                }
            }
        }

        return new WP_REST_Response(['ok' => true], 200);
    }
}
