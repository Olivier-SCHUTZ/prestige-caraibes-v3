<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PCR_Document_Repository
 * * Gère toutes les interactions avec la base de données pour les documents (table wp_pc_documents).
 * Implémente le pattern Singleton.
 */
class PCR_Document_Repository
{
    /**
     * @var PCR_Document_Repository|null
     */
    private static $instance = null;

    /**
     * Retourne l'instance unique de la classe.
     *
     * @return PCR_Document_Repository
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructeur privé pour le Singleton.
     */
    private function __construct() {}

    /**
     * Retourne le nom de la table des documents avec le préfixe WP.
     *
     * @return string
     */
    private function get_table_name()
    {
        global $wpdb;
        return $wpdb->prefix . 'pc_documents';
    }

    /**
     * Récupère le document le plus récent d'un certain type pour une réservation.
     *
     * @param int    $reservation_id
     * @param string $type_doc
     * @return object|null
     */
    public function get_latest_document_by_type($reservation_id, $type_doc)
    {
        global $wpdb;
        $table = $this->get_table_name();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} 
             WHERE reservation_id = %d 
             AND type_doc = %s 
             ORDER BY date_creation DESC 
             LIMIT 1",
            (int) $reservation_id,
            $type_doc
        ));
    }

    /**
     * Vérifie si une facture d'acompte a déjà été générée pour une réservation.
     *
     * @param int $reservation_id
     * @return bool
     */
    public function has_deposit_invoice($reservation_id)
    {
        global $wpdb;
        $table = $this->get_table_name();

        $has_acompte = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE reservation_id = %d AND type_doc = 'facture_acompte'",
            (int) $reservation_id
        ));

        return !empty($has_acompte);
    }

    /**
     * Archive un document existant en modifiant son type.
     *
     * @param int    $document_id
     * @param string $archived_type (ex: 'facture_archived_167890987')
     * @return int|false
     */
    public function archive_document($document_id, $archived_type)
    {
        global $wpdb;
        $table = $this->get_table_name();

        return $wpdb->update(
            $table,
            ['type_doc' => $archived_type],
            ['id' => (int) $document_id],
            ['%s'],
            ['%d']
        );
    }

    /**
     * Sauvegarde ou met à jour un document (Gère la contrainte d'unicité ON DUPLICATE KEY).
     *
     * @param int    $reservation_id
     * @param string $type_doc
     * @param string $numero_doc
     * @param string $nom_fichier
     * @param string $chemin_fichier
     * @param string $url_fichier
     * @param int    $user_id
     * @return int|false
     */
    public function upsert_document($reservation_id, $type_doc, $numero_doc, $nom_fichier, $chemin_fichier, $url_fichier, $user_id)
    {
        global $wpdb;
        $table = $this->get_table_name();

        return $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (reservation_id, type_doc, numero_doc, nom_fichier, chemin_fichier, url_fichier, user_id, date_creation)
             VALUES (%d, %s, %s, %s, %s, %s, %d, NOW())
             ON DUPLICATE KEY UPDATE 
             url_fichier = VALUES(url_fichier), chemin_fichier = VALUES(chemin_fichier), date_creation = NOW()",
            (int) $reservation_id,
            $type_doc,
            $numero_doc,
            $nom_fichier,
            $chemin_fichier,
            $url_fichier,
            (int) $user_id
        ));
    }

    /**
     * Insère un nouveau document (utilisé principalement pour les avoirs et l'historique).
     *
     * @param array $data Données à insérer.
     * @return int|false
     */
    public function insert_document($data)
    {
        global $wpdb;
        $table = $this->get_table_name();

        $wpdb->insert($table, $data);
        return $wpdb->insert_id;
    }

    /**
     * Récupère la liste de tous les documents associés à une réservation.
     *
     * @param int $reservation_id
     * @return array
     */
    public function get_documents_for_reservation($reservation_id)
    {
        global $wpdb;
        $table = $this->get_table_name();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT type_doc, nom_fichier, url_fichier, date_creation 
             FROM {$table} 
             WHERE reservation_id = %d 
             ORDER BY date_creation DESC",
            (int) $reservation_id
        ));
    }
}
