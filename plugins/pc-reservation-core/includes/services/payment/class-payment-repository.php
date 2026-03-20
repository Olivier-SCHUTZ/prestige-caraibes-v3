<?php
if (!defined('ABSPATH')) {
    exit; // Sécurité : Empêche l'accès direct au fichier
}

/**
 * Couche Accès aux Données (Repository) pour les Paiements.
 * Gère exclusivement les requêtes SQL vers la table pc_payments.
 * Pattern Singleton pour garantir une instance unique.
 */
class PCR_Payment_Repository
{
    /**
     * @var PCR_Payment_Repository Instance unique
     */
    private static $instance = null;

    /**
     * @var string Nom de la table des paiements avec le préfixe
     */
    private $table_pay;

    /**
     * @var string Nom de la table des réservations avec le préfixe
     */
    private $table_res;

    /**
     * Constructeur privé (Singleton)
     */
    private function __construct()
    {
        global $wpdb;
        $this->table_pay = $wpdb->prefix . 'pc_payments';
        $this->table_res = $wpdb->prefix . 'pc_reservations';
    }

    /**
     * Empêche le clonage
     */
    private function __clone() {}

    /**
     * Récupère l'instance unique du Repository.
     *
     * @return PCR_Payment_Repository
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Insère une nouvelle ligne de paiement.
     *
     * @param array $data Données prêtes à l'insertion
     * @return int|false ID inséré ou false
     */
    public function insert(array $data)
    {
        global $wpdb;

        $inserted = $wpdb->insert($this->table_pay, $data);

        if ($inserted === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PC Reservation] Erreur insert (Payment Repository): ' . $wpdb->last_error . ' | Données: ' . print_r($data, true));
            }
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Retourne tous les paiements liés à une réservation.
     *
     * @param int $reservation_id
     * @return array Liste d'objets (lignes de paiement)
     */
    public function get_for_reservation($reservation_id)
    {
        global $wpdb;

        $sql = "
            SELECT *
            FROM {$this->table_pay}
            WHERE reservation_id = %d
            ORDER BY id ASC
        ";

        return $wpdb->get_results($wpdb->prepare($sql, (int) $reservation_id));
    }

    /**
     * Supprime toutes les lignes de paiement pour une réservation donnée.
     *
     * @param int $reservation_id
     * @return int|false Nombre de lignes supprimées ou false
     */
    public function delete_for_reservation($reservation_id)
    {
        global $wpdb;
        return $wpdb->delete($this->table_pay, ['reservation_id' => (int) $reservation_id]);
    }

    /**
     * Récupère les données de base d'une réservation (utilisé pour calculer les paiements).
     *
     * @param int $reservation_id
     * @return array|null Tableau associatif ou null
     */
    public function get_reservation_data($reservation_id)
    {
        global $wpdb;
        $sql = "SELECT * FROM {$this->table_res} WHERE id = %d LIMIT 1";
        return $wpdb->get_row($wpdb->prepare($sql, (int) $reservation_id), ARRAY_A);
    }

    /**
     * Met à jour le statut de paiement et/ou réservation de la table pc_reservations.
     *
     * @param int    $reservation_id
     * @param string $statut_paiement
     * @param string $statut_reservation
     * @return bool
     */
    public function update_reservation_statuses($reservation_id, $statut_paiement, $statut_reservation = null)
    {
        global $wpdb;

        $updates = [
            'statut_paiement' => $statut_paiement,
            'date_maj'        => current_time('mysql'),
        ];

        if ($statut_reservation !== null) {
            $updates['statut_reservation'] = $statut_reservation;
        }

        $updated = $wpdb->update($this->table_res, $updates, ['id' => (int) $reservation_id]);

        return $updated !== false;
    }

    /**
     * Met à jour une ligne de paiement spécifique.
     *
     * @param int   $payment_id ID du paiement
     * @param array $data       Données à mettre à jour
     * @return bool
     */
    public function update_payment($payment_id, array $data)
    {
        global $wpdb;
        $data['date_maj'] = current_time('mysql');

        $updated = $wpdb->update(
            $this->table_pay,
            $data,
            ['id' => (int) $payment_id]
        );

        return $updated !== false;
    }
}
