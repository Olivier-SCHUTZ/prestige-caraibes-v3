<?php
if (!defined('ABSPATH')) {
    exit; // Sécurité : Empêche l'accès direct au fichier
}

/**
 * Couche Métier (Service) pour les Réservations.
 * Orchestre la validation des données et les interactions avec la base de données.
 * Pattern Singleton pour garantir une instance unique.
 */
class PCR_Reservation_Service
{
    /**
     * @var PCR_Reservation_Service Instance unique
     */
    private static $instance = null;

    /**
     * Constructeur privé (Singleton)
     */
    private function __construct() {}

    /**
     * Empêche le clonage
     */
    private function __clone() {}

    /**
     * Récupère l'instance unique du Service.
     *
     * @return PCR_Reservation_Service
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Crée une réservation.
     * Valide les données, insère en BDD, et génère le numéro de devis.
     *
     * @param array $data Données de la réservation
     * @return int|false ID de la réservation ou false
     */
    public function create(array $data)
    {
        // 1. Validation et nettoyage des données
        $prepared_data = PCR_Reservation_Validator::get_instance()->prepare_for_insert($data);

        // 2. Insertion en base de données
        $insert_id = PCR_Reservation_Repository::get_instance()->insert($prepared_data);

        // 3. Logique métier : Génération du numéro de devis
        if ($insert_id !== false) {
            $this->maybe_generate_quote_number($insert_id, $prepared_data);
        }

        return $insert_id;
    }

    /**
     * Met à jour une réservation existante.
     *
     * @param int   $reservation_id
     * @param array $data
     * @return bool
     */
    public function update($reservation_id, array $data)
    {
        if ($reservation_id <= 0) {
            return false;
        }

        // 1. Validation des données de mise à jour
        $prepared_data = PCR_Reservation_Validator::get_instance()->prepare_for_update($data);

        if (empty($prepared_data)) {
            return true;
        }

        $repository = PCR_Reservation_Repository::get_instance();

        // On récupère l'existant pour la fusion des données (nécessaire pour le numéro de devis)
        $existing = $repository->get_by_id($reservation_id);

        // 2. Mise à jour en base
        $updated = $repository->update($reservation_id, $prepared_data);

        // 3. Logique métier : Vérifier si on doit générer un numéro de devis
        if ($updated !== false && $existing) {
            $merged = array_merge((array) $existing, $prepared_data);
            $this->maybe_generate_quote_number($reservation_id, $merged);
        }

        return $updated !== false;
    }

    /**
     * Génère un numéro de devis formaté si celui-ci n'existe pas encore.
     *
     * @param int   $reservation_id
     * @param array $row Données complètes de la réservation
     */
    protected function maybe_generate_quote_number($reservation_id, array $row)
    {
        if ($reservation_id <= 0) {
            return;
        }

        $current_number = isset($row['numero_devis']) ? $row['numero_devis'] : '';
        if (!empty($current_number)) {
            return;
        }

        $type = isset($row['type']) ? $row['type'] : '';
        if ($type === 'location') {
            $generated = 'DEV-LOG-' . str_pad((string) $reservation_id, 6, '0', STR_PAD_LEFT);
        } elseif ($type === 'experience') {
            $generated = 'DEV-EXP-' . str_pad((string) $reservation_id, 6, '0', STR_PAD_LEFT);
        } else {
            return;
        }

        // Sauvegarde du numéro généré
        PCR_Reservation_Repository::get_instance()->update($reservation_id, ['numero_devis' => $generated]);
    }

    /**
     * Récupère une liste de réservations.
     *
     * @param array $args
     * @return array
     */
    public function get_list($args = [])
    {
        return PCR_Reservation_Repository::get_instance()->get_list($args);
    }

    /**
     * Retourne une réservation complète par ID.
     *
     * @param int $reservation_id
     * @return object|null
     */
    public function get_by_id($reservation_id)
    {
        return PCR_Reservation_Repository::get_instance()->get_by_id($reservation_id);
    }

    /**
     * Retourne le nombre total de réservations (pour pagination).
     *
     * @param string $type
     * @param int    $item_id
     * @return int
     */
    public function get_count($type = '', $item_id = 0)
    {
        return PCR_Reservation_Repository::get_instance()->get_count($type, $item_id);
    }
}
