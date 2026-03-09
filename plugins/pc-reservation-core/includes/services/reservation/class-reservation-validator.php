<?php
if (!defined('ABSPATH')) {
    exit; // Sécurité : Empêche l'accès direct au fichier
}

/**
 * Couche de Validation (Validator) pour les Réservations.
 * Gère l'application des valeurs par défaut et le filtrage des colonnes autorisées.
 * Pattern Singleton pour garantir une instance unique.
 */
class PCR_Reservation_Validator
{
    /**
     * @var PCR_Reservation_Validator Instance unique
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
     * Récupère l'instance unique du Validator.
     *
     * @return PCR_Reservation_Validator
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Prépare et nettoie les données avant une création (INSERT).
     * Applique les valeurs par défaut et retire les colonnes inexistantes.
     *
     * @param array $data Données brutes
     * @return array Données nettoyées prêtes pour le Repository
     */
    public function prepare_for_insert(array $data)
    {
        // Valeurs par défaut extraites de l'ancienne classe PCR_Reservation
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

        // Fusionne les données entrantes avec les valeurs par défaut
        $row = wp_parse_args($data, $defaults);

        // Sécurité : on filtre pour ne garder que les colonnes existantes en BDD
        return $this->filter_allowed_columns($row);
    }

    /**
     * Prépare et nettoie les données avant une mise à jour (UPDATE).
     *
     * @param array $data Données brutes
     * @return array Données nettoyées prêtes pour le Repository
     */
    public function prepare_for_update(array $data)
    {
        // On ne met jamais à jour la date de création
        if (isset($data['date_creation'])) {
            unset($data['date_creation']);
        }

        // On force la date de mise à jour si non fournie
        if (!isset($data['date_maj'])) {
            $data['date_maj'] = current_time('mysql');
        }

        // Sécurité : on filtre pour ne garder que les colonnes existantes
        return $this->filter_allowed_columns($data);
    }

    /**
     * Vérifie les clés d'un tableau par rapport aux colonnes de la base de données.
     * Fait appel au Repository pour connaître les colonnes valides.
     *
     * @param array $data
     * @return array
     */
    private function filter_allowed_columns(array $data)
    {
        // On récupère les colonnes depuis le Repository
        $allowed = PCR_Reservation_Repository::get_instance()->get_allowed_columns();

        if (!empty($allowed)) {
            $data = array_intersect_key($data, $allowed);
        }

        return $data;
    }
}
