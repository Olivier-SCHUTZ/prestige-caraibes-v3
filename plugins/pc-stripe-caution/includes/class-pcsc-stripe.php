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
        if (empty($key)) return ['ok' => false, 'error' => 'Clé Stripe manquante.'];

        $url = 'https://api.stripe.com' . $path;
        $args = [
            'method'  => strtoupper($method),
            'timeout' => 45,
            'headers' => ['Authorization' => 'Bearer ' . $key, 'Stripe-Version' => '2023-10-16'],
        ];
        if (!empty($body)) $args['body'] = $body;

        $resp = wp_remote_request($url, $args);
        if (is_wp_error($resp)) return ['ok' => false, 'error' => 'WP HTTP Error: ' . $resp->get_error_message()];

        $code = wp_remote_retrieve_response_code($resp);
        $json = json_decode(wp_remote_retrieve_body($resp), true);

        if ($code < 200 || $code >= 300) {
            $msg = $json['error']['message'] ?? ('HTTP ' . $code);
            return ['ok' => false, 'error' => "Stripe Error: $msg", 'raw' => $json];
        }
        return ['ok' => true, 'data' => $json];
    }

    // NOUVELLE FONCTION : Créer le client explicitement
    public static function create_customer(string $email, string $name): array
    {
        $body = [
            'email' => $email,
            'name'  => $name,
            'description' => 'Client Caution - ' . $name,
        ];
        $res = self::api_request('POST', '/v1/customers', $body);
        if (!$res['ok']) return $res;
        return ['ok' => true, 'id' => $res['data']['id']];
    }

    public static function create_checkout_setup_session(array $params): array
    {
        $body = [
            'mode' => 'setup',
            'success_url' => $params['success_url'],
            'cancel_url'  => $params['cancel_url'],
            'payment_method_types' => ['card'],
        ];

        // Si on a déjà un ID client (ce qui sera le cas maintenant), on l'utilise
        if (!empty($params['customer_id'])) {
            $body['customer'] = $params['customer_id'];
        } else {
            $body['customer_email'] = $params['customer_email'];
        }

        // --- AJOUT POUR AFFICHER LE TEXTE SUR LA PAGE STRIPE ---
        if (!empty($params['checkout_message'])) {
            $body['custom_text[submit][message]'] = $params['checkout_message'];
        }
        // -------------------------------------------------------

        if (!empty($params['metadata']) && is_array($params['metadata'])) {
            foreach ($params['metadata'] as $k => $v) {
                $body["metadata[{$k}]"] = (string)$v;
                $body["setup_intent_data[metadata][{$k}]"] = (string)$v;
            }
        }

        $res = self::api_request('POST', '/v1/checkout/sessions', $body);
        if (!$res['ok']) return $res;

        return ['ok' => true, 'session_id' => $res['data']['id'] ?? '', 'url' => $res['data']['url'] ?? ''];
    }

    public static function retrieve_setup_intent(string $setup_intent_id): array
    {
        if (empty($setup_intent_id)) return ['ok' => false, 'error' => 'ID SetupIntent manquant.'];
        return self::api_request('GET', '/v1/setup_intents/' . rawurlencode($setup_intent_id));
    }

    public static function create_manual_hold_off_session(array $params): array
    {
        $amount = (int)($params['amount'] ?? 0);
        $customer_id = (string)($params['customer_id'] ?? '');
        $pm_id = (string)($params['payment_method_id'] ?? '');

        // --- AJOUT : Récupération ID dossier ---
        $case_id = $params['metadata']['pc_case_id'] ?? 'N/A';

        if ($amount < 100) return ['ok' => false, 'error' => 'Montant trop faible (min 1€).'];
        if (!$customer_id || !$pm_id) return ['ok' => false, 'error' => 'Paramètres manquants (Customer/PM).'];

        $body = [
            'amount' => (string)$amount,
            'currency' => 'eur',
            'customer' => $customer_id,
            'payment_method' => $pm_id,
            'confirm' => 'true',
            'off_session' => 'true',
            'capture_method' => 'manual',
            'description' => 'Caution #' . $case_id . ' - Prestige Caraïbes',
        ];

        if (!empty($params['metadata'])) {
            foreach ($params['metadata'] as $k => $v) $body["metadata[{$k}]"] = (string)$v;
        }

        $res = self::api_request('POST', '/v1/payment_intents', $body);
        if (!$res['ok']) return $res;

        $pi = $res['data'];
        $status = $pi['status'] ?? '';

        if ($status === 'requires_action') return ['ok' => false, 'error' => 'Échec: La banque demande une authentification 3DS.'];
        if ($status === 'requires_payment_method') return ['ok' => false, 'error' => 'Échec: Paiement refusé.'];

        return ['ok' => true, 'payment_intent_id' => $pi['id']];
    }

    public static function cancel_payment_intent(string $pi): array
    {
        return self::api_request('POST', '/v1/payment_intents/' . rawurlencode($pi) . '/cancel', []);
    }

    public static function capture_payment_intent(string $pi, ?int $amount = null): array
    {
        $body = [];
        if ($amount) $body['amount_to_capture'] = (string)$amount;
        return self::api_request('POST', '/v1/payment_intents/' . rawurlencode($pi) . '/capture', $body);
    }
}
