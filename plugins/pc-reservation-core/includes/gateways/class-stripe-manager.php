<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gère les échanges directs avec l'API Stripe.
 */
class PCR_Stripe_Manager
{
    private static $api_url = 'https://api.stripe.com/v1';

    /**
     * Récupère la clé API Secrète selon le mode (Test/Live) configuré dans ACF
     */
    private static function get_secret_key()
    {
        if (!function_exists('get_field')) return '';

        $mode = get_field('pc_stripe_mode', 'option');
        if ($mode === 'live') {
            return get_field('pc_stripe_live_sk', 'option');
        }
        return get_field('pc_stripe_test_sk', 'option');
    }

    /**
     * Crée un lien de paiement (Checkout Session)
     * * @param int    $reservation_id ID de la réservation
     * @param float  $amount         Montant à payer en EUROS (ex: 150.00)
     * @param string $type           Type de paiement ('acompte', 'solde', 'total')
     * * @return array ['success' => bool, 'url' => string, 'message' => string]
     */
    public static function create_payment_link($reservation_id, $amount, $type = 'total')
    {
        $secret_key = self::get_secret_key();
        if (empty($secret_key)) {
            return ['success' => false, 'message' => 'Configuration Stripe incomplète (Clé manquante).'];
        }

        $resa = PCR_Reservation::get_by_id($reservation_id);
        if (!$resa) {
            return ['success' => false, 'message' => 'Réservation introuvable.'];
        }

        // Stripe travaille en centimes (10.00€ = 1000)
        $amount_cents = round($amount * 100);
        if ($amount_cents <= 0) {
            return ['success' => false, 'message' => 'Montant invalide (0).'];
        }

        // Libellé clair pour le client sur la page Stripe
        $label_type = ucfirst(str_replace('_', ' ', $type)); // ex: "Acompte"
        $product_name = sprintf('Réservation #%d - %s', $reservation_id, $label_type);

        // Description avec le nom du logement/expérience si possible
        $item_title = get_the_title($resa->item_id);
        $description  = sprintf('Paiement pour %s %s - %s', $resa->prenom, $resa->nom, $item_title);

        // URLs de retour (Où le client revient après paiement)
        // On crée une simple page de remerciement virtuelle via un paramètre URL
        $base_url = home_url('/');
        $success_url = add_query_arg([
            'pc_payment_return' => 'success',
            'session_id' => '{CHECKOUT_SESSION_ID}', // Stripe remplacera ça
            'resa_id' => $reservation_id
        ], $base_url);

        $cancel_url = add_query_arg([
            'pc_payment_return' => 'cancel',
            'resa_id' => $reservation_id
        ], $base_url);

        // Construction du paquet de données pour Stripe
        $body = [
            'payment_method_types' => ['card'],
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'eur',
                        'product_data' => [
                            'name' => $product_name,
                            'description' => $description,
                        ],
                        'unit_amount' => $amount_cents,
                    ],
                    'quantity' => 1,
                ]
            ],
            'mode' => 'payment', // Paiement immédiat
            'success_url' => $success_url,
            'cancel_url' => $cancel_url,
            'client_reference_id' => $reservation_id,
            'customer_email' => $resa->email, // Pré-remplit l'email sur Stripe
            'metadata' => [
                'reservation_id' => $reservation_id,
                'payment_type'   => $type,
                'plugin_source'  => 'pc-reservation-core'
            ]
        ];

        // Envoi à Stripe via HTTP (pas besoin de librairie externe)
        $response = wp_remote_post(self::$api_url . '/checkout/sessions', [
            'method'    => 'POST',
            'headers'   => [
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body'      => $body,
            'timeout'   => 45,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => 'Erreur de connexion Stripe : ' . $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body_response = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            $error_msg = $body_response['error']['message'] ?? 'Erreur inconnue Stripe';
            return ['success' => false, 'message' => 'Stripe Error: ' . $error_msg];
        }

        // SUCCÈS !
        return [
            'success' => true,
            'url'     => $body_response['url'], // L'URL de paiement vers laquelle rediriger
            'id'      => $body_response['id']   // ID de session (cs_test_...) utile pour le suivi
        ];
    }
}
