<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Noyau des réservations.
 * 
 * Pour l'instant :
 *  - init() réservé aux futurs hooks (calendrier, etc.)
 *  - create() insère une nouvelle réservation dans la table pc_reservations
 */
class PCR_Reservation
{
    public static function init()
    {
        // Plus tard : hooks, filtres, REST API interne, etc.
    }

    /**
     * Crée une réservation à partir d'un tableau normalisé $data.
     * Retourne l'ID de la réservation ou false en cas d'échec.
     */
    public static function create(array $data)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'pc_reservations';

        // Valeurs par défaut pour éviter les notices
        $defaults = [
            'type'               => '',
            'item_id'            => 0,
            'mode_reservation'   => 'demande',
            'origine'            => 'site',
            'ref_externe'        => null,
            'type_flux'          => 'reservation',
            'numero_devis'       => null,

            'date_arrivee'       => null,
            'date_depart'        => null,
            'date_experience'    => null,
            'experience_tarif_type' => null,

            'adultes'            => 0,
            'enfants'            => 0,
            'bebes'              => 0,
            'infos_personnes'    => null,

            'civilite'           => null,
            'prenom'             => null,
            'nom'                => null,
            'email'              => null,
            'telephone'          => null,
            'langue'             => 'fr',
            'commentaire_client' => null,

            'devise'             => 'EUR',
            'montant_total'      => 0,
            'montant_acompte'    => 0,
            'montant_solde'      => 0,
            'conditions_paiement' => null,
            'detail_tarif'       => null,

            'caution_montant'    => 0,
            'caution_mode'       => 'aucune',
            'caution_statut'     => 'non_demande',
            'caution_reference'  => null,
            'caution_date_demande'   => null,
            'caution_date_validation' => null,
            'caution_date_liberation' => null,

            'statut_reservation'  => 'en_attente_traitement',
            'statut_paiement'     => 'non_paye',
            'commentaire_interne' => null,
            'notes_internes'      => null,
            'snapshot_politique'  => null,
            'tags_internes'       => null,
            'user_responsable_id' => null,

            'date_creation'       => current_time('mysql'),
            'date_maj'            => current_time('mysql'),
            'date_annulation'     => null,
        ];

        $row = wp_parse_args($data, $defaults);

        // 🔒 Sécurité : ne garder que les colonnes réellement présentes dans la table
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table}", ARRAY_A);
        $allowed = [];

        if (!empty($columns)) {
            foreach ($columns as $col) {
                $allowed[$col['Field']] = true;
            }
            $row = array_intersect_key($row, $allowed);
        }

        $inserted = $wpdb->insert($table, $row);

        if ($inserted === false) {
            // Log détaillé en debug pour comprendre si ça replante
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PC Reservation] Erreur insert: ' . $wpdb->last_error . ' | Données: ' . print_r($row, true));
            }
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Met à jour une réservation existante.
     *
     * @param int   $reservation_id
     * @param array $data
     * @return bool
     */
    public static function update($reservation_id, array $data)
    {
        global $wpdb;

        $reservation_id = (int) $reservation_id;
        if ($reservation_id <= 0) {
            return false;
        }

        $table = $wpdb->prefix . 'pc_reservations';

        if (isset($data['date_creation'])) {
            unset($data['date_creation']);
        }

        if (!isset($data['date_maj'])) {
            $data['date_maj'] = current_time('mysql');
        }

        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table}", ARRAY_A);
        if (!empty($columns)) {
            $allowed = [];
            foreach ($columns as $col) {
                $allowed[$col['Field']] = true;
            }
            $data = array_intersect_key($data, $allowed);
        }

        if (empty($data)) {
            return true;
        }

        $updated = $wpdb->update($table, $data, ['id' => $reservation_id]);

        if ($updated === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PC Reservation] Erreur update: ' . $wpdb->last_error . ' | ID: ' . $reservation_id . ' | Données: ' . print_r($data, true));
            }
            return false;
        }

        return true;
    }

    /**
     * Retourne une liste de réservations (pour le dashboard, calendrier, etc.).
     *
     * @param array $args
     *  - 'limit'  (int) : nombre max de lignes (par défaut 50)
     *  - 'offset' (int) : offset (par défaut 0)
     *  - 'type'   (string) : 'location' ou 'experience' (optionnel)
     *
     * @return array Liste d'objets stdClass
     */
    public static function get_list($args = [])
    {
        global $wpdb;

        $defaults = [
            'limit'   => 50,
            'offset'  => 0,
            'type'    => '',
            'item_id' => 0,
        ];
        $args = wp_parse_args($args, $defaults);

        $table  = $wpdb->prefix . 'pc_reservations';
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

        // Requête simple : on ne dépend que de 'id' qui est sûr d'exister
        $sql = "
            SELECT *
            FROM {$table}
            WHERE {$where}
            ORDER BY id DESC
            LIMIT %d OFFSET %d
        ";

        $params[] = (int) $args['limit'];
        $params[] = (int) $args['offset'];

        $prepared = $wpdb->prepare($sql, $params);

        return $wpdb->get_results($prepared);
    }

    /**
     * Retourne une réservation complète par ID.
     *
     * @param int $reservation_id
     * @return stdClass|null
     */
    public static function get_by_id($reservation_id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'pc_reservations';

        $sql = "
            SELECT *
            FROM {$table}
            WHERE id = %d
            LIMIT 1
        ";

        return $wpdb->get_row($wpdb->prepare($sql, (int) $reservation_id));
    }
    /**
     * Retourne le nombre total de réservations (pour pagination)
     */
    public static function get_count($type = '', $item_id = 0)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'pc_reservations';
        $where = '1=1';
        $params = [];

        if (! empty($type)) {
            $where .= " AND type = %s";
            $params[] = $type;
        }

        if (! empty($item_id)) {
            $where .= " AND item_id = %d";
            $params[] = $item_id;
        }

        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
        return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }
}
