<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gère l'endpoint REST API pour recevoir les webhooks entrants
 * (Emails via Brevo, WhatsApp, etc.)
 */
class PCR_Rest_Webhook
{
    public static function init()
    {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);

        // Filter ACF pour afficher l'URL du webhook dynamiquement
        add_filter('acf/load_field/name=pc_webhook_url_display', [__CLASS__, 'load_webhook_url']);

        // Hook pour générer automatiquement le secret webhook si vide
        add_filter('acf/update_value/name=pc_webhook_secret', [__CLASS__, 'maybe_generate_webhook_secret'], 10, 3);
    }

    /**
     * Enregistre les routes REST API
     */
    public static function register_routes()
    {
        register_rest_route('pc-resa/v1', '/incoming-message', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'handle_webhook'],
            'permission_callback' => '__return_true', // Sécurité gérée manuellement avec le secret
        ]);

        // Route de test (GET) pour vérifier que l'endpoint fonctionne
        register_rest_route('pc-resa/v1', '/incoming-message', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'handle_test_request'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Charge dynamiquement l'URL du webhook dans le champ ACF
     */
    public static function load_webhook_url($field)
    {
        $webhook_url = home_url('/wp-json/pc-resa/v1/incoming-message');

        $field['message'] = sprintf(
            '<strong>URL à configurer chez votre fournisseur :</strong><br>' .
                '<code style="background: #f1f1f1; padding: 4px 8px; border-radius: 3px; font-family: monospace;">%s</code><br>' .
                '<em>Copiez cette URL dans les paramètres webhook de votre service.</em><br><br>' .
                '<small><a href="%s" target="_blank" style="color: #0073aa;">🔗 Tester l\'endpoint</a> (doit retourner un JSON de statut)</small>',
            $webhook_url,
            $webhook_url
        );

        return $field;
    }

    /**
     * Génère automatiquement un secret webhook si vide
     */
    public static function maybe_generate_webhook_secret($value, $post_id, $field)
    {
        // Si le champ est vide, générer un secret aléatoirement
        if (empty($value)) {
            $value = 'pcw_' . wp_generate_password(32, false); // 32 chars sans caractères spéciaux
        }

        return $value;
    }

    /**
     * Gère les requêtes de test (GET)
     */
    public static function handle_test_request($request)
    {
        return new WP_REST_Response([
            'success' => true,
            'message' => 'PC Reservation Webhook Endpoint actif',
            'endpoint' => 'POST /wp-json/pc-resa/v1/incoming-message',
            'version' => PC_RES_CORE_VERSION,
            'timestamp' => current_time('mysql'),
            'configured_provider' => get_field('pc_api_provider', 'option') ?: 'none',
            'inbound_enabled' => (bool) get_field('pc_inbound_email_enabled', 'option'),
        ], 200);
    }

    /**
     * Point d'entrée principal pour traiter les webhooks entrants
     */
    public static function handle_webhook($request)
    {
        // 1. Vérification de la configuration
        $api_provider = get_field('pc_api_provider', 'option');
        $inbound_enabled = get_field('pc_inbound_email_enabled', 'option');

        if ($api_provider === 'none' || !$inbound_enabled) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'webhook_disabled',
                'message' => 'Les webhooks entrants sont désactivés dans la configuration.',
            ], 200); // 200 pour éviter les retry du fournisseur
        }

        // 2. Sécurité : Vérification du secret webhook
        $webhook_secret = get_field('pc_webhook_secret', 'option');
        if (!empty($webhook_secret)) {
            $provided_secret = $request->get_header('X-PC-Webhook-Secret') ?:
                $request->get_param('secret') ?:
                $request->get_header('Authorization');

            if (empty($provided_secret) || !hash_equals($webhook_secret, trim(str_replace('Bearer ', '', $provided_secret)))) {
                error_log('[PCR_Webhook] Tentative d\'accès non autorisée - Secret invalide');
                return new WP_REST_Response([
                    'success' => false,
                    'error' => 'unauthorized',
                    'message' => 'Secret webhook invalide.',
                ], 401);
            }
        }

        // 3. Récupération du payload JSON
        $payload = $request->get_json_params();
        if (empty($payload)) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'empty_payload',
                'message' => 'Payload JSON manquant ou invalide.',
            ], 400);
        }

        // 4. Log pour debugging
        error_log('[PCR_Webhook] Payload reçu: ' . json_encode($payload, JSON_PRETTY_PRINT));

        // 5. Routage selon le type de message
        $message_type = $payload['type'] ?? 'email'; // Default à 'email'

        try {
            switch ($message_type) {
                case 'email':
                    return self::handle_email_webhook($payload, $request);

                case 'whatsapp':
                    return self::handle_whatsapp_webhook($payload, $request);

                default:
                    return new WP_REST_Response([
                        'success' => false,
                        'error' => 'unsupported_type',
                        'message' => "Type de message non supporté: {$message_type}",
                    ], 400);
            }
        } catch (Exception $e) {
            error_log('[PCR_Webhook] Erreur lors du traitement: ' . $e->getMessage());

            return new WP_REST_Response([
                'success' => false,
                'error' => 'processing_error',
                'message' => 'Erreur interne lors du traitement du webhook.',
                'debug' => WP_DEBUG ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Cas A : Traitement des emails entrants (Brevo Inbound Parse)
     */
    private static function handle_email_webhook($payload, $request)
    {
        // Structure typique Brevo Inbound Parse:
        // {
        //   "type": "email",
        //   "subject": "Re: Votre réservation #123",
        //   "from": { "email": "client@example.com", "name": "John Doe" },
        //   "to": [{ "email": "contact@prestige-caraibes.com" }],
        //   "text": "Contenu du message...",
        //   "html": "<p>Contenu HTML...</p>"
        // }

        $subject = $payload['subject'] ?? '';
        $from_email = $payload['from']['email'] ?? '';
        $from_name = $payload['from']['name'] ?? '';
        $text_content = $payload['text'] ?? '';
        $html_content = $payload['html'] ?? '';

        // Contenu préféré : HTML puis texte
        $message_content = !empty($html_content) ? $html_content : $text_content;

        if (empty($subject) || empty($from_email) || empty($message_content)) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'missing_email_data',
                'message' => 'Données email manquantes (subject, from.email, content).',
            ], 400);
        }

        $reservation_id = 0;

        // --- STRATÉGIE DE DÉTECTION "TRIDENT" ---

        // 1. Recherche dans le SUJET (Priorité 1)
        // Accepte: [#123], [Resa #123], #123
        if (preg_match('/(?:\[#|Resa #|#)(\d+)/i', $subject, $matches)) {
            $reservation_id = (int) $matches[1];
        }

        // 2. Recherche dans le CORPS (Priorité 2 - Si sujet échoue)
        // Cherche le watermark "Ref: #123" dans l'historique cité
        if (!$reservation_id && !empty($message_content)) {
            if (preg_match('/Ref:\s*#(\d+)/i', $message_content, $matches)) {
                $reservation_id = (int) $matches[1];
                error_log("[PCR_Webhook] ID trouvé dans le corps du message : #$reservation_id");
            }
        }

        // 3. Recherche par EMAIL EXPÉDITEUR (Priorité 3 - Mode "Intelligent")
        // Si le client écrit un tout nouveau mail sans référence
        if (!$reservation_id && !empty($from_email)) {
            // On utilise la méthode utilitaire existante pour trouver une résa active
            $found_id = self::find_active_reservation_by_email($from_email);
            if ($found_id) {
                $reservation_id = $found_id;
                error_log("[PCR_Webhook] ID déduit via l'email expéditeur ($from_email) : #$reservation_id");
            }
        }

        // ----------------------------------------

        // Si après tout ça on a toujours rien :
        if (!$reservation_id) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'no_reservation_id',
                'message' => 'Impossible de lier ce message : ID absent du sujet/corps et aucun séjour actif trouvé pour cet email.',
                'subject' => $subject,
            ], 200);
        }

        // Vérification que la réservation existe
        if (!class_exists('PCR_Reservation')) {
            throw new Exception('Classe PCR_Reservation non disponible');
        }

        $reservation = PCR_Reservation::get_by_id($reservation_id);
        if (!$reservation) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'reservation_not_found',
                'message' => "Réservation #{$reservation_id} introuvable.",
            ], 200);
        }

        // Injection dans la messagerie
        if (!class_exists('PCR_Messaging')) {
            throw new Exception('Classe PCR_Messaging non disponible');
        }

        $injection_result = PCR_Messaging::receive_external_message(
            $reservation_id,
            $message_content,
            'email',
            [
                'sender_email' => $from_email,
                'sender_name' => $from_name,
                'original_subject' => $subject,
                'external_id' => $payload['message_id'] ?? null,
                'webhook_source' => 'brevo',
            ]
        );

        if ($injection_result['success']) {
            return new WP_REST_Response([
                'success' => true,
                'message' => 'Email injecté avec succès dans la conversation.',
                'reservation_id' => $reservation_id,
                'message_id' => $injection_result['message_id'] ?? null,
            ], 200);
        } else {
            throw new Exception('Échec injection message: ' . $injection_result['message']);
        }
    }

    /**
     * Cas B : Traitement des messages WhatsApp entrants
     */
    private static function handle_whatsapp_webhook($payload, $request)
    {
        // Structure typique WhatsApp Business API:
        // {
        //   "type": "whatsapp",
        //   "from": "+33612345678",
        //   "to": "+590123456789",
        //   "text": "Bonjour, j'ai une question concernant ma réservation",
        //   "timestamp": "2024-01-15T10:30:00Z"
        // }

        $from_phone = $payload['from'] ?? '';
        $message_text = $payload['text'] ?? '';

        if (empty($from_phone) || empty($message_text)) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'missing_whatsapp_data',
                'message' => 'Données WhatsApp manquantes (from, text).',
            ], 400);
        }

        // Challenge : On n'a pas l'ID de réservation directement
        // Il faut chercher une réservation active liée à ce numéro
        $reservation_id = self::find_active_reservation_by_phone($from_phone);

        if (!$reservation_id) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'no_active_reservation',
                'message' => 'Aucune réservation active trouvée pour ce numéro de téléphone.',
                'phone' => $from_phone,
            ], 200);
        }

        // Injection dans la messagerie
        if (!class_exists('PCR_Messaging')) {
            throw new Exception('Classe PCR_Messaging non disponible');
        }

        $injection_result = PCR_Messaging::receive_external_message(
            $reservation_id,
            $message_text,
            'whatsapp',
            [
                'sender_phone' => $from_phone,
                'external_id' => $payload['message_id'] ?? null,
                'webhook_source' => 'whatsapp_business',
                'timestamp' => $payload['timestamp'] ?? null,
            ]
        );

        if ($injection_result['success']) {
            return new WP_REST_Response([
                'success' => true,
                'message' => 'Message WhatsApp injecté avec succès.',
                'reservation_id' => $reservation_id,
                'message_id' => $injection_result['message_id'] ?? null,
            ], 200);
        } else {
            throw new Exception('Échec injection WhatsApp: ' . $injection_result['message']);
        }
    }

    /**
     * Helper : Trouve une réservation active par numéro de téléphone
     * Cherche dans les réservations "En cours" ou "Futures"
     */
    private static function find_active_reservation_by_phone($phone)
    {
        global $wpdb;

        // Nettoyage du numéro (suppression des espaces, formatage)
        $clean_phone = preg_replace('/[^\+\d]/', '', $phone);

        // Recherche avec plusieurs variantes du numéro
        $phone_variants = [$clean_phone];

        // Ajouter des variantes selon le préfixe configuré
        $default_prefix = get_field('pc_default_phone_prefix', 'option') ?: '+590';

        if (strpos($clean_phone, $default_prefix) !== 0) {
            // Si le numéro ne commence pas par le préfixe, l'ajouter
            $phone_variants[] = $default_prefix . ltrim($clean_phone, '+0');
        }

        // Construire la requête avec LIKE pour plus de flexibilité
        $like_conditions = [];
        $prepare_values = [];

        foreach ($phone_variants as $variant) {
            $like_conditions[] = 'telephone LIKE %s';
            $prepare_values[] = '%' . $variant . '%';
        }

        $where_phone = '(' . implode(' OR ', $like_conditions) . ')';

        // Recherche dans les réservations récentes/futures
        $sql = "SELECT id FROM {$wpdb->prefix}pc_reservations 
                WHERE {$where_phone}
                AND (
                    statut IN ('confirmee', 'en_cours', 'paiement_partiel') 
                    OR date_depart >= CURDATE()
                )
                ORDER BY date_creation DESC 
                LIMIT 1";

        array_unshift($prepare_values, $sql); // Ajouter la requête au début pour wpdb->prepare
        $reservation_id = call_user_func_array([$wpdb, 'get_var'], [$wpdb->prepare($sql, ...$prepare_values)]);

        return $reservation_id ? (int) $reservation_id : null;
    }

    /**
     * Utilitaire : Nettoie et valide un numéro de téléphone
     */
    private static function normalize_phone($phone)
    {
        // Supprime tout sauf les chiffres et le +
        $clean = preg_replace('/[^\+\d]/', '', $phone);

        // Assure qu'il commence par +
        if (!empty($clean) && $clean[0] !== '+') {
            $clean = '+' . $clean;
        }

        return $clean;
    }
}
