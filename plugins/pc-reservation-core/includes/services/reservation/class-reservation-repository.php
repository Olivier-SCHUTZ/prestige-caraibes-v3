<?php
if (!defined('ABSPATH')) {
    exit; // Sécurité : Empêche l'accès direct au fichier
}

/**
 * Couche Accès aux Données (Repository) pour les Réservations.
 * Gère exclusivement les requêtes SQL (CRUD) vers la table pc_reservations.
 * Pattern Singleton pour garantir une instance unique.
 */
class PCR_Reservation_Repository
{
    /**
     * @var PCR_Reservation_Repository Instance unique
     */
    private static $instance = null;

    /**
     * @var string Nom de la table avec le préfixe
     */
    private $table_name;

    /**
     * Constructeur privé (Singleton)
     */
    private function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'pc_reservations';
    }

    /**
     * Empêche le clonage
     */
    private function __clone() {}

    /**
     * Récupère l'instance unique du Repository.
     *
     * @return PCR_Reservation_Repository
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Insère une nouvelle réservation.
     *
     * @param array $data Données nettoyées prêtes à l'insertion
     * @return int|false ID inséré ou false en cas d'erreur
     */
    public function insert(array $data)
    {
        global $wpdb;

        $inserted = $wpdb->insert($this->table_name, $data);

        if ($inserted === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PC Reservation] Erreur insert (Repository): ' . $wpdb->last_error . ' | Données: ' . print_r($data, true));
            }
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Met à jour une réservation existante.
     *
     * @param int   $reservation_id ID de la réservation
     * @param array $data Données à mettre à jour
     * @return bool Succès ou échec
     */
    public function update($reservation_id, array $data)
    {
        global $wpdb;

        $updated = $wpdb->update($this->table_name, $data, ['id' => (int) $reservation_id]);

        if ($updated === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PC Reservation] Erreur update (Repository): ' . $wpdb->last_error . ' | ID: ' . $reservation_id);
            }
            return false;
        }

        return true;
    }

    /**
     * Récupère une réservation par son ID.
     *
     * @param int $reservation_id
     * @return object|null
     */
    public function get_by_id($reservation_id)
    {
        global $wpdb;

        $sql = "SELECT * FROM {$this->table_name} WHERE id = %d LIMIT 1";
        return $wpdb->get_row($wpdb->prepare($sql, (int) $reservation_id));
    }

    /**
     * Récupère une liste de réservations.
     *
     * @param array $args Arguments (limit, offset, type, item_id)
     * @return array
     */
    public function get_list(array $args = [])
    {
        global $wpdb;

        $defaults = [
            'limit'   => 50,
            'offset'  => 0,
            'type'    => '',
            'item_id' => 0,
        ];
        $args = wp_parse_args($args, $defaults);

        $where  = '1=1';
        $params = [];

        if (!empty($args['type'])) {
            $where   .= ' AND type = %s';
            $params[] = $args['type'];
        }

        if (!empty($args['item_id'])) {
            $where   .= ' AND item_id = %d';
            $params[] = (int) $args['item_id'];
        }

        $sql = "
            SELECT *
            FROM {$this->table_name}
            WHERE {$where}
            ORDER BY id DESC
            LIMIT %d OFFSET %d
        ";

        $params[] = (int) $args['limit'];
        $params[] = (int) $args['offset'];

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * Retourne le nombre total de réservations (pour la pagination).
     *
     * @param string $type
     * @param int    $item_id
     * @return int
     */
    public function get_count($type = '', $item_id = 0)
    {
        global $wpdb;

        $where = '1=1';
        $params = [];

        if (!empty($type)) {
            $where .= " AND type = %s";
            $params[] = $type;
        }

        if (!empty($item_id)) {
            $where .= " AND item_id = %d";
            $params[] = (int) $item_id;
        }

        $sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where}";
        return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    /**
     * Récupère les colonnes existantes de la table pour des raisons de sécurité.
     *
     * @return array Tableau associatif des colonnes autorisées
     */
    public function get_allowed_columns()
    {
        global $wpdb;

        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_name}", ARRAY_A);
        $allowed = [];

        if (!empty($columns)) {
            foreach ($columns as $col) {
                $allowed[$col['Field']] = true;
            }
        }

        return $allowed;
    }
}
