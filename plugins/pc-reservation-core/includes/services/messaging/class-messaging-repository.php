<?php
if (!defined('ABSPATH')) {
    exit; // Sécurité : Empêche l'accès direct au fichier
}

/**
 * Couche Accès aux Données (Repository) pour la Messagerie.
 * Gère exclusivement les requêtes SQL vers la table pc_messages (et quelques requêtes transverses liées).
 * Pattern Singleton pour garantir une instance unique.
 */
class PCR_Messaging_Repository
{
    /**
     * @var PCR_Messaging_Repository Instance unique
     */
    private static $instance = null;

    private $table_messages;
    private $table_reservations;
    private $table_payments;

    /**
     * Constructeur privé (Singleton)
     */
    private function __construct()
    {
        global $wpdb;
        $this->table_messages = $wpdb->prefix . 'pc_messages';
        $this->table_reservations = $wpdb->prefix . 'pc_reservations';
        $this->table_payments = $wpdb->prefix . 'pc_payments';
    }

    /**
     * Empêche le clonage
     */
    private function __clone() {}

    /**
     * Récupère l'instance unique du Repository.
     *
     * @return PCR_Messaging_Repository
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Insère un nouveau message dans l'historique.
     *
     * @param array $data
     * @return int|false L'ID du message ou false en cas d'erreur
     */
    public function insert_message(array $data)
    {
        global $wpdb;

        $inserted = $wpdb->insert($this->table_messages, $data);

        if ($inserted === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PCR_Messaging_Repository] Erreur insertion message: ' . $wpdb->last_error);
            }
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Récupère le conversation_id pour une réservation donnée.
     * Retourne l'ID de la résa par défaut s'il n'y a pas encore de conversation.
     *
     * @param int $reservation_id
     * @return int
     */
    public function get_conversation_id($reservation_id)
    {
        global $wpdb;

        $conversation_id = $wpdb->get_var($wpdb->prepare(
            "SELECT conversation_id FROM {$this->table_messages} 
             WHERE reservation_id = %d 
             ORDER BY id ASC 
             LIMIT 1",
            (int) $reservation_id
        ));

        return $conversation_id ? (int) $conversation_id : (int) $reservation_id;
    }

    /**
     * Récupère tous les messages d'une réservation (Historique).
     *
     * @param int $reservation_id
     * @return array
     */
    public function get_messages_by_reservation($reservation_id)
    {
        global $wpdb;

        $sql = "SELECT 
            id, conversation_id, canal, channel_source, direction, sender_type, type,
            sujet, corps, template_code, dest_email, exp_email, statut_envoi,
            read_at, delivered_at, date_creation, date_envoi, external_id, metadata, user_id
        FROM {$this->table_messages} 
        WHERE reservation_id = %d 
        ORDER BY date_creation ASC";

        return $wpdb->get_results($wpdb->prepare($sql, (int) $reservation_id), ARRAY_A);
    }

    /**
     * Marque un ou plusieurs messages comme lus.
     *
     * @param array $message_ids Tableau d'IDs
     * @return int Nombre de lignes mises à jour
     */
    public function mark_messages_read(array $message_ids)
    {
        global $wpdb;

        if (empty($message_ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($message_ids), '%d'));
        $now = current_time('mysql');

        $sql = "UPDATE {$this->table_messages} 
                SET read_at = %s, date_maj = %s 
                WHERE id IN ($placeholders) AND read_at IS NULL";

        $params = array_merge([$now, $now], $message_ids);

        return $wpdb->query($wpdb->prepare($sql, $params));
    }

    /**
     * Trouve une réservation active par email du client.
     *
     * @param string $email
     * @return int|null
     */
    public function find_active_reservation_by_email($email)
    {
        global $wpdb;

        $reservation_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_reservations} 
             WHERE email = %s 
             AND (
                 statut IN ('confirmee', 'en_cours', 'paiement_partiel') 
                 OR date_depart >= CURDATE()
             )
             ORDER BY date_creation DESC 
             LIMIT 1",
            $email
        ));

        return $reservation_id ? (int) $reservation_id : null;
    }

    /**
     * Calcule le montant déjà payé pour une réservation (Aide pour les variables du message).
     *
     * @param int $reservation_id
     * @return float
     */
    public function get_paid_amount($reservation_id)
    {
        global $wpdb;

        $val = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(montant) FROM {$this->table_payments} 
             WHERE reservation_id = %d AND statut = 'paye'",
            (int) $reservation_id
        ));

        return (float) $val;
    }

    /**
     * Récupère les statistiques des messages externes entrants.
     *
     * @param int $days Période en jours
     * @return array
     */
    public function get_external_messages_stats($days = 30)
    {
        global $wpdb;
        $stats = [];

        // Messages par canal
        $stats['by_channel'] = $wpdb->get_results($wpdb->prepare(
            "SELECT channel_source, COUNT(*) as count 
             FROM {$this->table_messages} 
             WHERE direction = 'entrant' 
             AND type = 'externe'
             AND date_creation >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY channel_source 
             ORDER BY count DESC",
            (int) $days
        ), ARRAY_A);

        // Messages non lus (global, pas limité aux X jours)
        $stats['unread_count'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_messages} 
             WHERE direction = 'entrant' 
             AND read_at IS NULL"
        );

        // Total sur la période
        $total_external = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_messages} 
             WHERE direction = 'entrant' 
             AND type = 'externe'
             AND date_creation >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            (int) $days
        ));

        $stats['total_external'] = $total_external;
        $stats['period_days'] = $days;
        $stats['avg_per_day'] = $days > 0 ? round($total_external / $days, 1) : 0;

        return $stats;
    }
}
