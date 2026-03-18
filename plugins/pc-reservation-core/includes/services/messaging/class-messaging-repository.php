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

    /**
     * NOUVEAU : Récupère les conversations récentes avec statistiques (pour Dashboard Vue 3).
     *
     * @param int $limit Nombre de conversations à retourner
     * @return array
     */
    public function get_recent_conversations_with_stats($limit = 50)
    {
        global $wpdb;

        $sql = "
            SELECT 
                m.reservation_id,
                m.conversation_id,
                r.prenom,
                r.nom,
                r.email,
                r.statut_reservation,
                MAX(m.date_creation) as last_activity,
                COUNT(m.id) as total_messages,
                SUM(CASE WHEN m.direction = 'entrant' AND m.read_at IS NULL THEN 1 ELSE 0 END) as unread_count
            FROM {$this->table_messages} m
            LEFT JOIN {$this->table_reservations} r ON m.reservation_id = r.id
            GROUP BY m.reservation_id
            ORDER BY last_activity DESC
            LIMIT %d
        ";

        return $wpdb->get_results($wpdb->prepare($sql, (int) $limit), ARRAY_A);
    }

    /**
     * NOUVEAU : Recherche avancée "Full Text" dans les messages.
     *
     * @param string $query Texte à rechercher
     * @param int|null $reservation_id Filtrer par réservation
     * @param array $filters Filtres supplémentaires (channel, dates)
     * @return array
     */
    public function search_full_text($query, $reservation_id = null, $filters = [])
    {
        global $wpdb;

        $sql = "SELECT m.*, r.prenom, r.nom 
                FROM {$this->table_messages} m
                LEFT JOIN {$this->table_reservations} r ON m.reservation_id = r.id
                WHERE 1=1";
        $params = [];

        // Recherche textuelle
        if (!empty($query)) {
            $sql .= " AND (m.sujet LIKE %s OR m.corps LIKE %s)";
            $like = '%' . $wpdb->esc_like($query) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        // Filtre réservation
        if (!empty($reservation_id)) {
            $sql .= " AND m.reservation_id = %d";
            $params[] = (int) $reservation_id;
        }

        // Filtre canal
        if (!empty($filters['channel'])) {
            $sql .= " AND m.channel_source = %s";
            $params[] = sanitize_text_field($filters['channel']);
        }

        // Filtres dates
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(m.date_creation) >= %s";
            $params[] = sanitize_text_field($filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(m.date_creation) <= %s";
            $params[] = sanitize_text_field($filters['date_to']);
        }

        $sql .= " ORDER BY m.date_creation DESC LIMIT 100";

        // Préparation de la requête si on a des paramètres
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return $wpdb->get_results($sql, ARRAY_A);
    }
}
