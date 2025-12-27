<?php
if (!defined('ABSPATH')) exit;

class PCSC_Webhooks
{
    public static function init(): void
    {
        add_action('rest_api_init', function () {
            register_rest_route('pcsc/v1', '/stripe/webhook', ['methods' => 'POST', 'callback' => [__CLASS__, 'handle'], 'permission_callback' => '__return_true']);
        });
    }

    public static function handle(WP_REST_Request $req)
    {
        $payload = $req->get_body();
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

                    // Ici, plus de panique si 'customer' est vide dans le webhook.
                    // On l'a déjà dans la base de données via le shortcode !
                    // On ne fait que mettre à jour le PM et le statut.

                    // (Optionnel) on peut récupérer le customer s'il est là
                    $cust_from_stripe = $si['data']['customer'] ?? '';

                    $update_data = [
                        'status' => 'setup_ok',
                        'stripe_setup_intent_id' => $setup_intent_id,
                        'stripe_payment_method_id' => $pm,
                        'last_error' => null,
                    ];

                    // Si Stripe nous renvoie un customer ID, on le prend, sinon on garde celui qu'on a déjà en DB
                    if ($cust_from_stripe) {
                        $update_data['stripe_customer_id'] = $cust_from_stripe;
                    }

                    PCSC_DB::update_case($case_id, $update_data);
                    PCSC_DB::append_note($case_id, "Webhook OK : Carte ($pm) confirmée.");
                }
            }
        }
        return new WP_REST_Response(['ok' => true], 200);
    }
}
