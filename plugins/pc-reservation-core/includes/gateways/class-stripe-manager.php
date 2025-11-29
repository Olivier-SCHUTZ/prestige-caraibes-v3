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

    /**
     * Crée un lien de CAUTION (Pré-autorisation / Empreinte bancaire).
     * L'argent est bloqué (hold) pendant 7 jours, mais pas débité.
     */
    public static function create_caution_link($reservation_id)
    {
        $secret_key = self::get_secret_key();
        if (empty($secret_key)) return ['success' => false, 'message' => 'Clé Stripe manquante.'];

        $resa = PCR_Reservation::get_by_id($reservation_id);
        if (!$resa) return ['success' => false, 'message' => 'Réservation introuvable.'];

        $amount = (float) $resa->caution_montant;
        if ($amount <= 0) return ['success' => false, 'message' => 'Montant caution nul.'];

        // Conversion en centimes
        $amount_cents = round($amount * 100);

        // URLs de retour
        $base_url = home_url('/');
        $success_url = add_query_arg([
            'pc_payment_return' => 'caution_success', // Marqueur spécifique
            'session_id' => '{CHECKOUT_SESSION_ID}',
            'resa_id' => $reservation_id
        ], $base_url);

        $cancel_url = add_query_arg([
            'pc_payment_return' => 'cancel',
            'resa_id' => $reservation_id
        ], $base_url);

        $item_title = get_the_title($resa->item_id);

        $body = [
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => "Caution (Empreinte Bancaire) - Réservation #{$reservation_id}",
                        'description' => "Empreinte pour {$item_title}. Aucun débit immédiat.",
                    ],
                    'unit_amount' => $amount_cents,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',

            // --- C'EST ICI QUE LA MAGIE OPÈRE (HOLD) ---
            'payment_intent_data' => [
                'capture_method'     => 'manual', // Bloque les fonds sans encaisser
                'setup_future_usage' => 'off_session', // Indispensable pour la rotation (renouvellement)
            ],
            // -------------------------------------------

            'success_url' => $success_url,
            'cancel_url' => $cancel_url,
            'client_reference_id' => $reservation_id,
            'customer_email' => $resa->email,
            'metadata' => [
                'reservation_id' => $reservation_id,
                'type'           => 'caution', // Marqueur pour le Webhook
                'plugin_source'  => 'pc-reservation-core'
            ]
        ];

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
            return ['success' => false, 'message' => 'Erreur Stripe : ' . $response->get_error_message()];
        }

        $body_response = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body_response['error'])) {
            return ['success' => false, 'message' => 'Stripe Error: ' . $body_response['error']['message']];
        }

        return [
            'success' => true,
            'url'     => $body_response['url'],
            'id'      => $body_response['id']
        ];
    }

    /**
     * Libère (Annule) une caution existante.
     */
    public static function release_caution($payment_intent_id)
    {
        $secret_key = self::get_secret_key();
        if (empty($payment_intent_id)) return ['success' => false, 'message' => 'ID Caution manquant.'];

        // API Stripe : Cancel
        $url = self::$api_url . '/payment_intents/' . $payment_intent_id . '/cancel';

        $response = wp_remote_post($url, [
            'method'    => 'POST',
            'headers'   => [
                'Authorization' => 'Bearer ' . $secret_key,
            ],
            'timeout'   => 45,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['error'])) {
            return ['success' => false, 'message' => $body['error']['message']];
        }

        return ['success' => true];
    }

    /**
     * Encaisse (Capture) tout ou partie de la caution.
     */
    public static function capture_caution($payment_intent_id, $amount_eur, $note = '')
    {
        $secret_key = self::get_secret_key();
        $amount_cents = round($amount_eur * 100);

        if ($amount_cents <= 0) return ['success' => false, 'message' => 'Montant invalide.'];

        // API Stripe : Capture
        $url = self::$api_url . '/payment_intents/' . $payment_intent_id . '/capture';

        $body = [
            'amount_to_capture' => $amount_cents
        ];

        // On ajoute la note dans les métadonnées Stripe
        if (!empty($note)) {
            $body['metadata'] = ['motif_encaissement' => $note];
        }

        $response = wp_remote_post($url, [
            'method'    => 'POST',
            'headers'   => [
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body'      => $body,
            'timeout'   => 45,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['error'])) {
            return ['success' => false, 'message' => $body['error']['message']];
        }

        return ['success' => true];
    }

    /**
     * RENOUVELLEMENT DE CAUTION (Rotation) - VERSION ROBUSTE
     * - Récupère l'ancienne caution.
     * - Si le Client Stripe manque mais que la Carte est là, on crée le Client à la volée (Auto-Repair).
     * - Crée la nouvelle empreinte et libère l'ancienne.
     */
    public static function rotate_caution($old_payment_intent_id, $amount_eur, $reservation_id)
    {
        $secret_key = self::get_secret_key();
        if (empty($old_payment_intent_id)) return ['success' => false, 'message' => 'Réf caution manquante.'];

        // --- A. Récupérer l'ancien PaymentIntent ---
        $url_get = self::$api_url . '/payment_intents/' . $old_payment_intent_id;
        $response_get = wp_remote_get($url_get, [
            'headers' => ['Authorization' => 'Bearer ' . $secret_key],
            'timeout' => 45
        ]);

        if (is_wp_error($response_get)) return ['success' => false, 'message' => 'Err Get: ' . $response_get->get_error_message()];

        $old_pi = json_decode(wp_remote_retrieve_body($response_get), true);
        if (isset($old_pi['error'])) return ['success' => false, 'message' => 'Stripe Get Error: ' . $old_pi['error']['message']];

        // Extraction robuste des IDs (gère si c'est un objet ou une string)
        $customer_id = isset($old_pi['customer']) ? (is_array($old_pi['customer']) ? $old_pi['customer']['id'] : $old_pi['customer']) : '';
        $payment_method_id = isset($old_pi['payment_method']) ? (is_array($old_pi['payment_method']) ? $old_pi['payment_method']['id'] : $old_pi['payment_method']) : '';

        // --- B. AUTO-RÉPARATION : Si Carte OK mais Client manquant ---
        if ($payment_method_id && empty($customer_id)) {
            // On crée le client maintenant pour sauver la mise
            $resa = PCR_Reservation::get_by_id($reservation_id);
            $name = ($resa->prenom ?? 'Client') . ' ' . ($resa->nom ?? '');
            $email = $resa->email ?? '';

            // 1. Création Client Stripe
            $cus_resp = wp_remote_post(self::$api_url . '/customers', [
                'headers' => ['Authorization' => 'Bearer ' . $secret_key, 'Content-Type' => 'application/x-www-form-urlencoded'],
                'body' => [
                    'email' => $email,
                    'name' => $name,
                    'description' => "Client Auto-Créé (Rotation Resa #$reservation_id)"
                ]
            ]);

            if (!is_wp_error($cus_resp)) {
                $cus_data = json_decode(wp_remote_retrieve_body($cus_resp), true);
                if (!empty($cus_data['id'])) {
                    $customer_id = $cus_data['id'];

                    // 2. Attacher la carte orpheline à ce nouveau client
                    wp_remote_post(self::$api_url . "/payment_methods/$payment_method_id/attach", [
                        'headers' => ['Authorization' => 'Bearer ' . $secret_key, 'Content-Type' => 'application/x-www-form-urlencoded'],
                        'body' => ['customer' => $customer_id]
                    ]);
                }
            }
        }

        // Vérification finale
        if (!$customer_id || !$payment_method_id) {
            $debug = "Cus=" . ($customer_id ?: 'NON') . " | PM=" . ($payment_method_id ?: 'NON');
            return ['success' => false, 'message' => "Echec : Données Stripe incomplètes ($debug)."];
        }

        // --- C. Créer la NOUVELLE caution (PaymentIntent off_session) ---
        $amount_cents = round($amount_eur * 100);

        $body_create = [
            'amount' => $amount_cents,
            'currency' => 'eur',
            'customer' => $customer_id,
            'payment_method' => $payment_method_id,
            'off_session' => 'true',
            'confirm' => 'true',
            'capture_method' => 'manual',
            'payment_method_types' => ['card'],
            'description' => "Renouvellement Caution (Resa #{$reservation_id})",
            'metadata' => [
                'reservation_id' => $reservation_id,
                'type' => 'caution_rotation',
                'old_ref' => $old_payment_intent_id
            ],
            // RETRAIT ICI : On ne redemande pas la sauvegarde car la carte est déjà liée au client.
        ];

        $response_create = wp_remote_post(self::$api_url . '/payment_intents', [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'body' => $body_create,
            'timeout' => 45
        ]);

        if (is_wp_error($response_create)) return ['success' => false, 'message' => 'Err Create: ' . $response_create->get_error_message()];

        $new_pi = json_decode(wp_remote_retrieve_body($response_create), true);
        if (isset($new_pi['error'])) return ['success' => false, 'message' => 'Echec création nouvelle caution : ' . $new_pi['error']['message']];

        if ($new_pi['status'] !== 'requires_capture') {
            return ['success' => false, 'message' => 'Statut incorrect pour la nouvelle caution : ' . $new_pi['status']];
        }

        $new_id = $new_pi['id'];

        // --- D. Annuler l'ANCIENNE caution ---
        $cancel_res = self::release_caution($old_payment_intent_id);
        $cancel_msg = $cancel_res['success'] ? 'Ancienne libérée.' : 'Attention: Echec libération ancienne.';

        return [
            'success' => true,
            'new_ref' => $new_id,
            'message' => "Rotation réussie. $cancel_msg"
        ];
    }
}
