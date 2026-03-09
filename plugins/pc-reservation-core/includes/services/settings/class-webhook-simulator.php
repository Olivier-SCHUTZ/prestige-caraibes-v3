<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCR Webhook Simulator - Service de simulation de webhooks
 * Isole la logique de test local pour les webhooks Brevo et WhatsApp.
 * Pattern Singleton.
 * * @since 2.0.0 (Refactoring)
 */
class PCR_Webhook_Simulator
{
    /**
     * Instance unique de la classe.
     * @var PCR_Webhook_Simulator|null
     */
    private static $instance = null;

    /**
     * Constructeur privé (Singleton).
     */
    private function __construct() {}

    /**
     * Récupère l'instance unique.
     * @return PCR_Webhook_Simulator
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Logique principale de traitement de la simulation webhook.
     * * @param array $payload Payload JSON décodé
     * @return array Résultat de la simulation
     */
    public function process_simulation($payload)
    {
        // Détecter le type de webhook selon la structure
        $message_type = $this->detect_webhook_type($payload);

        switch ($message_type) {
            case 'brevo_email':
                return $this->simulate_brevo_email($payload);

            case 'whatsapp':
                return $this->simulate_whatsapp($payload);

            default:
                return [
                    'success' => false,
                    'message' => 'Format de webhook non reconnu. Supportés : Brevo Email, WhatsApp.'
                ];
        }
    }

    /**
     * Détecte le type de webhook selon sa structure.
     * * @param array $payload
     * @return string
     */
    private function detect_webhook_type($payload)
    {
        // Format Brevo Email (Inbound Parse)
        if (
            isset($payload['items']) && is_array($payload['items']) &&
            isset($payload['items'][0]['SenderAddress']) &&
            isset($payload['items'][0]['Subject'])
        ) {
            return 'brevo_email';
        }

        // Format Brevo Email (structure alternative)
        if (isset($payload['subject']) && isset($payload['from']['email'])) {
            return 'brevo_email_alt';
        }

        // Format WhatsApp
        if (
            isset($payload['from']) && isset($payload['text']) &&
            (isset($payload['type']) && $payload['type'] === 'whatsapp')
        ) {
            return 'whatsapp';
        }

        return 'unknown';
    }

    /**
     * Simule la réception d'un email Brevo.
     * * @param array $payload
     * @return array
     */
    private function simulate_brevo_email($payload)
    {
        // Structure standard Brevo Inbound Parse
        $email_data = $payload['items'][0] ?? [];

        $sender_email = $email_data['SenderAddress'] ?? '';
        $subject = $email_data['Subject'] ?? $payload['subject'] ?? '';
        $content = $email_data['RawHtmlBody'] ?? $email_data['RawTextBody'] ?? '';

        if (empty($sender_email) || empty($subject) || empty($content)) {
            return [
                'success' => false,
                'message' => 'Données email manquantes (SenderAddress, Subject, RawHtmlBody/RawTextBody).'
            ];
        }

        // Extraction de l'ID de réservation depuis le sujet : pattern #123 ou [Resa #123]
        if (!preg_match('/(?:#|Resa #)(\d+)/', $subject, $matches)) {
            return [
                'success' => false,
                'message' => 'Aucun ID de réservation trouvé dans le sujet. Format attendu : #123 ou [Resa #123]',
                'subject' => $subject
            ];
        }

        $reservation_id = (int) $matches[1];

        // Vérification que la réservation existe
        if (!class_exists('PCR_Reservation')) {
            return [
                'success' => false,
                'message' => 'Classe PCR_Reservation non disponible. Vérifiez que le plugin est correctement initialisé.'
            ];
        }

        $reservation = PCR_Reservation::get_by_id($reservation_id);
        if (!$reservation) {
            return [
                'success' => false,
                'message' => "Réservation #{$reservation_id} introuvable."
            ];
        }

        // Injection via PCR_Messaging
        if (!class_exists('PCR_Messaging')) {
            return [
                'success' => false,
                'message' => 'Classe PCR_Messaging non disponible.'
            ];
        }

        $metadata = [
            'sender_email' => $sender_email,
            'sender_name' => $email_data['SenderName'] ?? 'Client',
            'original_subject' => $subject,
            'external_id' => $email_data['uuid'] ?? null,
            'webhook_source' => 'brevo_simulation',
            'simulation' => true,
            'simulated_at' => current_time('mysql'),
            'admin_user_id' => get_current_user_id(),
        ];

        $injection_result = PCR_Messaging::receive_external_message(
            $reservation_id,
            $content,
            'email',
            $metadata
        );

        if ($injection_result['success']) {
            return [
                'success' => true,
                'message' => 'Message simulé injecté avec succès dans la conversation.',
                'reservation_id' => $reservation_id,
                'message_id' => $injection_result['message_id'] ?? null,
                'sender_email' => $sender_email
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Échec injection : ' . $injection_result['message']
            ];
        }
    }

    /**
     * Simule la réception d'un message WhatsApp.
     * * @param array $payload
     * @return array
     */
    private function simulate_whatsapp($payload)
    {
        $from_phone = $payload['from'] ?? '';
        $message_text = $payload['text'] ?? '';

        if (empty($from_phone) || empty($message_text)) {
            return [
                'success' => false,
                'message' => 'Données WhatsApp manquantes (from, text).'
            ];
        }

        // Pour la simulation, nous devons demander l'ID de réservation
        // car on n'a pas de recherche automatique par téléphone implémentée ici
        if (isset($payload['reservation_id'])) {
            $reservation_id = (int) $payload['reservation_id'];
        } else {
            return [
                'success' => false,
                'message' => 'Pour WhatsApp, ajoutez "reservation_id": 123 dans votre JSON de simulation.'
            ];
        }

        // Vérification que la réservation existe
        if (!class_exists('PCR_Reservation')) {
            return [
                'success' => false,
                'message' => 'Classe PCR_Reservation non disponible.'
            ];
        }

        $reservation = PCR_Reservation::get_by_id($reservation_id);
        if (!$reservation) {
            return [
                'success' => false,
                'message' => "Réservation #{$reservation_id} introuvable."
            ];
        }

        // Injection via PCR_Messaging
        if (!class_exists('PCR_Messaging')) {
            return [
                'success' => false,
                'message' => 'Classe PCR_Messaging non disponible.'
            ];
        }

        $metadata = [
            'sender_phone' => $from_phone,
            'external_id' => $payload['message_id'] ?? null,
            'webhook_source' => 'whatsapp_simulation',
            'simulation' => true,
            'simulated_at' => current_time('mysql'),
            'admin_user_id' => get_current_user_id(),
        ];

        $injection_result = PCR_Messaging::receive_external_message(
            $reservation_id,
            $message_text,
            'whatsapp',
            $metadata
        );

        if ($injection_result['success']) {
            return [
                'success' => true,
                'message' => 'Message WhatsApp simulé injecté avec succès.',
                'reservation_id' => $reservation_id,
                'message_id' => $injection_result['message_id'] ?? null,
                'sender_phone' => $from_phone
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Échec injection WhatsApp : ' . $injection_result['message']
            ];
        }
    }
}
