<?php
if (!defined('ABSPATH')) exit;

class PCSC_Stripe
{

    private static function secret_key(): string
    {
        return defined('PC_STRIPE_SECRET_KEY') ? PC_STRIPE_SECRET_KEY : '';
    }

    private static function api_request(string $method, string $path, array $body = []): array
    {
        $key = self::secret_key();
        if (!$key) return ['ok' => false, 'error' => 'Clé Stripe absente (PC_STRIPE_SECRET_KEY).'];

        $url = 'https://api.stripe.com' . $path;

        $args = [
            'method'  => strtoupper($method),
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
            ],
        ];

        if (!empty($body)) {
            $args['body'] = $body; // application/x-www-form-urlencoded
        }

        $resp = wp_remote_request($url, $args);
        if (is_wp_error($resp)) {
            return ['ok' => false, 'error' => $resp->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($resp);
        $json = json_decode(wp_remote_retrieve_body($resp), true);

        if ($code < 200 || $code >= 300) {
            $msg = $json['error']['message'] ?? ('Stripe HTTP ' . $code);
            $type = $json['error']['type'] ?? '';
            $decline = $json['error']['decline_code'] ?? '';
            $code2 = $json['error']['code'] ?? '';
            $detail = trim("{$msg} {$type} {$code2} {$decline}");
            return ['ok' => false, 'error' => $detail ?: $msg, 'raw' => $json];
        }

        return ['ok' => true, 'data' => $json];
    }

    public static function create_checkout_setup_session(array $params): array
    {
        // params: success_url, cancel_url, customer_email, metadata[]
        $body = [
            'mode' => 'setup',
            'success_url' => $params['success_url'],
            'cancel_url'  => $params['cancel_url'],
            'customer_email' => $params['customer_email'],
        ];

        // metadata sur setup_intent
        if (!empty($params['metadata']) && is_array($params['metadata'])) {
            foreach ($params['metadata'] as $k => $v) {
                $body["setup_intent_data[metadata][{$k}]"] = (string)$v;
            }
        }

        // optionnel: UI plus clean chez Stripe
        $body['billing_address_collection'] = 'auto';

        $res = self::api_request('POST', '/v1/checkout/sessions', $body);
        if (!$res['ok']) return $res;

        $data = $res['data'];
        return [
            'ok' => true,
            'session_id' => $data['id'] ?? '',
            'url' => $data['url'] ?? '',
        ];
    }

    public static function retrieve_setup_intent(string $setup_intent_id): array
    {
        return self::api_request('GET', '/v1/setup_intents/' . rawurlencode($setup_intent_id));
    }

    public static function create_manual_hold_off_session(array $params): array
    {
        // params: amount (int), currency, customer_id, payment_method_id, metadata[]
        $body = [
            'amount' => (string)$params['amount'],
            'currency' => 'eur',
            'customer' => (string)$params['customer_id'],
            'payment_method' => (string)$params['payment_method_id'],
            'confirm' => 'true',
            'off_session' => 'true',
            'capture_method' => 'manual',
            'description' => 'Caution - Prestige Caraïbes',
        ];

        if (!empty($params['metadata']) && is_array($params['metadata'])) {
            foreach ($params['metadata'] as $k => $v) {
                $body["metadata[{$k}]"] = (string)$v;
            }
        }

        $res = self::api_request('POST', '/v1/payment_intents', $body);
        if (!$res['ok']) return ['ok' => false, 'error' => $res['error'], 'raw' => $res['raw'] ?? null];

        $pi = $res['data'];
        $status = $pi['status'] ?? '';
        if ($status === 'requires_action') {
            return ['ok' => false, 'error' => 'requires_action (SCA)'];
        }
        if ($status !== 'requires_capture' && $status !== 'succeeded') {
            return ['ok' => false, 'error' => 'Statut PI inattendu: ' . $status];
        }

        return ['ok' => true, 'payment_intent_id' => $pi['id']];
    }

    public static function cancel_payment_intent(string $payment_intent_id): array
    {
        $res = self::api_request('POST', '/v1/payment_intents/' . rawurlencode($payment_intent_id) . '/cancel', []);
        if (!$res['ok']) return $res;
        return ['ok' => true];
    }

    public static function capture_payment_intent(string $payment_intent_id, ?int $amount_to_capture = null): array
    {
        $body = [];
        if (!is_null($amount_to_capture)) {
            $body['amount_to_capture'] = (string)$amount_to_capture;
        }
        $res = self::api_request('POST', '/v1/payment_intents/' . rawurlencode($payment_intent_id) . '/capture', $body);
        if (!$res['ok']) return $res;
        return ['ok' => true, 'data' => $res['data']];
    }
}
