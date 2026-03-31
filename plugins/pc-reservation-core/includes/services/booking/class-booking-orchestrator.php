<?php
if (!defined('ABSPATH')) {
    exit; // Sécurité : Empêche l'accès direct au fichier
}

/**
 * Objet de Résultat standardisé pour les créations/mises à jour de réservations.
 */
class PCR_Booking_Result
{
    public $success = false;
    public $reservation_id = 0;
    public $errors = [];
    public $data = [];

    public function __construct($args = [])
    {
        foreach ($args as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    public function to_array()
    {
        return [
            'success'        => $this->success,
            'reservation_id' => $this->reservation_id,
            'errors'         => $this->errors,
            'data'           => $this->data,
        ];
    }
}

/**
 * Service d'Orchestration pour le Moteur de Réservation.
 * Relie le Normalizer, le Pricing Calculator, et les modules de BDD (Reservation & Payment).
 * Pattern Singleton pour garantir une instance unique.
 */
class PCR_Booking_Orchestrator
{
    /**
     * @var PCR_Booking_Orchestrator Instance unique
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
     * Récupère l'instance unique de l'Orchestrateur.
     *
     * @return PCR_Booking_Orchestrator
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Point d'entrée : création d'une réservation à partir d'un payload.
     *
     * @param array $payload
     * @return PCR_Booking_Result
     */
    public function create(array $payload)
    {
        $result = new PCR_Booking_Result();

        if (!class_exists('PCR_Reservation')) {
            $result->errors[] = 'reservation_module_missing';
            return $result;
        }

        // 1. Normalisation via le service dédié
        $normalized = PCR_Booking_Payload_Normalizer::get_instance()->normalize($payload);

        if (empty($normalized['item']['item_id'])) {
            $result->errors[] = 'missing_item';
            return $result;
        }

        // 2. Préparation de la ligne pour la BDD
        $reservation_row = $this->build_reservation_row($normalized);

        // 3. Insertion via le service Réservation
        $reservation_id = PCR_Reservation::create($reservation_row);

        if (!$reservation_id) {
            $result->errors[] = 'db_insert_failed';
            return $result;
        }

        $result->reservation_id = $reservation_id;
        $result->success        = true;

        $payments = [];

        // 4. Génération de l'échéancier via le service Paiement
        if (class_exists('PCR_Payment')) {
            PCR_Payment::generate_for_reservation($reservation_id);
            $payments = PCR_Payment::get_for_reservation($reservation_id);
        }

        // 5. Retour des données structurées
        $result->data = [
            'context' => $normalized['context'],
            'pricing' => [
                'total'              => $reservation_row['montant_total'],
                'detail_tarif'       => $reservation_row['detail_tarif'],
                'lines'              => $normalized['pricing']['lines'],
                'is_sur_devis'       => $normalized['pricing']['is_sur_devis'],
                'manual_adjustments' => $normalized['pricing']['manual_adjustments'],
            ],
            'statuts' => [
                'reservation' => $reservation_row['statut_reservation'],
                'paiement'    => $reservation_row['statut_paiement'],
            ],
            'payments' => $payments,
        ];

        return $result;
    }

    /**
     * Met à jour une réservation existante avec un payload.
     *
     * @param int   $reservation_id
     * @param array $payload
     * @return PCR_Booking_Result
     */
    public function update($reservation_id, array $payload)
    {
        $result = new PCR_Booking_Result([
            'reservation_id' => (int) $reservation_id,
        ]);

        if ($result->reservation_id <= 0) {
            $result->errors[] = 'invalid_reservation_id';
            return $result;
        }

        if (!class_exists('PCR_Reservation')) {
            $result->errors[] = 'reservation_module_missing';
            return $result;
        }

        $existing = PCR_Reservation::get_by_id($result->reservation_id);
        if (!$existing) {
            $result->errors[] = 'reservation_not_found';
            return $result;
        }

        // [HYDRATATION] On complète le payload avec les données existantes
        if (empty($payload['context']['type'])) $payload['context']['type'] = $existing->type;
        if (empty($payload['context']['mode_reservation'])) $payload['context']['mode_reservation'] = $existing->mode_reservation;
        if (empty($payload['context']['origine'])) $payload['context']['origine'] = $existing->origine;
        if (empty($payload['context']['source'])) $payload['context']['source'] = isset($existing->source) ? $existing->source : 'direct';

        if (empty($payload['item']['item_id'])) $payload['item']['item_id'] = $existing->item_id;
        if (empty($payload['item']['date_arrivee'])) $payload['item']['date_arrivee'] = $existing->date_arrivee;
        if (empty($payload['item']['date_depart'])) $payload['item']['date_depart'] = $existing->date_depart;
        if (empty($payload['item']['date_experience'])) $payload['item']['date_experience'] = $existing->date_experience;
        if (empty($payload['item']['experience_tarif_type'])) $payload['item']['experience_tarif_type'] = $existing->experience_tarif_type;

        if (!isset($payload['people']['adultes'])) $payload['people']['adultes'] = $existing->adultes;
        if (!isset($payload['people']['enfants'])) $payload['people']['enfants'] = $existing->enfants;
        if (!isset($payload['people']['bebes'])) $payload['people']['bebes'] = $existing->bebes;

        if (empty($payload['customer']['prenom'])) $payload['customer']['prenom'] = $existing->prenom;
        if (empty($payload['customer']['nom'])) $payload['customer']['nom'] = $existing->nom;
        if (empty($payload['customer']['email'])) $payload['customer']['email'] = $existing->email;
        if (empty($payload['customer']['telephone'])) $payload['customer']['telephone'] = $existing->telephone;
        if (empty($payload['customer']['commentaire_client'])) $payload['customer']['commentaire_client'] = $existing->commentaire_client;

        if (!isset($payload['pricing']['total'])) {
            $payload['pricing']['total'] = $existing->montant_total;
            if (empty($payload['pricing']['lines_json']) && !empty($existing->detail_tarif)) {
                $payload['pricing']['lines_json'] = $existing->detail_tarif;
            }
        }

        if (empty($payload['meta']['notes_internes'])) $payload['meta']['notes_internes'] = $existing->notes_internes;
        if (empty($payload['meta']['numero_devis'])) $payload['meta']['numero_devis'] = $existing->numero_devis;

        // Normalisation
        $normalized = PCR_Booking_Payload_Normalizer::get_instance()->normalize($payload);

        // Fix spécifique pour les dates d'expérience
        if (
            isset($normalized['context']['type']) &&
            $normalized['context']['type'] === 'experience' &&
            (empty($normalized['item']['date_experience'])) &&
            !empty($existing->date_experience)
        ) {
            $normalized['item']['date_experience'] = $existing->date_experience;
        }

        $reservation_row = $this->build_reservation_row($normalized);
        unset($reservation_row['date_creation']);

        $updated = PCR_Reservation::update($result->reservation_id, $reservation_row);

        if (!$updated) {
            $result->errors[] = 'db_update_failed';
            return $result;
        }

        $result->success = true;
        $payments = [];

        if (class_exists('PCR_Payment')) {
            PCR_Payment::regenerate_for_reservation(
                $result->reservation_id,
                ['preserve_statuses' => true]
            );
            $payments = PCR_Payment::get_for_reservation($result->reservation_id);
        }

        $result->data = [
            'context' => $normalized['context'],
            'pricing' => [
                'total'              => $reservation_row['montant_total'],
                'detail_tarif'       => $reservation_row['detail_tarif'],
                'lines'              => $normalized['pricing']['lines'],
                'is_sur_devis'       => $normalized['pricing']['is_sur_devis'],
                'manual_adjustments' => $normalized['pricing']['manual_adjustments'],
            ],
            'statuts' => [
                'reservation' => $reservation_row['statut_reservation'],
                'paiement'    => $reservation_row['statut_paiement'],
            ],
            'payments' => $payments,
        ];

        return $result;
    }

    /**
     * Annule une réservation.
     *
     * @param int $reservation_id
     * @return PCR_Booking_Result
     */
    public function cancel($reservation_id)
    {
        $result = new PCR_Booking_Result([
            'reservation_id' => (int) $reservation_id,
        ]);

        if ($result->reservation_id <= 0) {
            $result->errors[] = 'invalid_reservation_id';
            return $result;
        }

        if (!class_exists('PCR_Reservation')) {
            $result->errors[] = 'reservation_module_missing';
            return $result;
        }

        $existing = PCR_Reservation::get_by_id($result->reservation_id);
        if (!$existing) {
            $result->errors[] = 'reservation_not_found';
            return $result;
        }

        $updated = PCR_Reservation::update($result->reservation_id, [
            'statut_reservation' => 'annulee',
        ]);

        if (!$updated) {
            $result->errors[] = 'db_update_failed';
            return $result;
        }

        $result->success = true;
        $result->data = [
            'statuts' => [
                'reservation' => 'annulee',
                'paiement'    => isset($existing->statut_paiement) ? $existing->statut_paiement : '',
            ],
        ];

        return $result;
    }

    /**
     * Transforme les données normalisées en une ligne compatible avec la BDD.
     */
    protected function build_reservation_row(array $normalized)
    {
        $context  = $normalized['context'];
        $item     = $normalized['item'];
        $people   = $normalized['people'];
        $pricing  = $normalized['pricing'];
        $customer = $normalized['customer'];
        $meta     = $normalized['meta'];

        $calculator = PCR_Booking_Pricing_Calculator::get_instance();

        $original_lines = is_array($pricing['lines']) ? $pricing['lines'] : [];
        $original_lines = $calculator->remove_existing_adjustments_from_lines($original_lines, $pricing['manual_adjustments']);
        $detail_lines   = $calculator->merge_lines_with_adjustments($original_lines, $pricing['manual_adjustments'], $pricing['currency']);

        if (!empty($detail_lines)) {
            $detail_tarif = wp_json_encode($detail_lines);
        } elseif (!empty($pricing['raw_lines_json'])) {
            $detail_tarif = wp_kses_post($pricing['raw_lines_json']);
        } else {
            $detail_tarif = '';
        }

        $statuts = $this->determine_statuses($context, $pricing);

        $lines_total      = $calculator->calculate_total_from_lines($original_lines);
        $adjustments_total = $calculator->sum_manual_adjustments($pricing['manual_adjustments']);
        $final_total      = $pricing['total'];

        if ($final_total <= 0) {
            $final_total = $lines_total + $adjustments_total;
        }

        return [
            'type'                  => $context['type'],
            'item_id'               => (int) $item['item_id'],
            'mode_reservation'      => $context['mode_reservation'],
            'origine'               => $context['origine'],
            'type_flux'             => $context['type_flux'],
            'source'                => $context['source'],
            'numero_devis'          => $meta['numero_devis'] ?: null,
            'ref_externe'           => $meta['ref_externe'] ?: null,

            'date_arrivee'          => $item['date_arrivee'] ?: null,
            'date_depart'           => $item['date_depart'] ?: null,
            'date_experience'       => $item['date_experience'] ?: null,
            'experience_tarif_type' => $item['experience_tarif_type'] ?: null,

            'adultes'               => $people['adultes'],
            'enfants'               => $people['enfants'],
            'bebes'                 => $people['bebes'],

            'civilite'              => $customer['civilite'] ?: null,
            'prenom'                => $customer['prenom'] ?: null,
            'nom'                   => $customer['nom'] ?: null,
            'email'                 => $customer['email'] ?: null,
            'telephone'             => $customer['telephone'] ?: null,
            'langue'                => $customer['langue'] ?: 'fr',
            'commentaire_client'    => $customer['commentaire_client'] ?: null,

            'devise'                => $pricing['currency'] ?: 'EUR',
            'montant_total'         => $final_total,
            'detail_tarif'          => $detail_tarif,
            'snapshot_politique'    => $pricing['snapshot_politique'] ?: null,

            'caution_montant' => (function () use ($item) {
                $rules = class_exists('PCR_Fields')
                    ? PCR_Fields::get('regles_de_paiement', (int)$item['item_id'])
                    : (function_exists('get_field') ? get_field('regles_de_paiement', (int)$item['item_id']) : []);
                return isset($rules['pc_caution_amount']) ? (float)$rules['pc_caution_amount'] : 0;
            })(),

            'caution_mode' => (function () use ($item) {
                $rules = class_exists('PCR_Fields')
                    ? PCR_Fields::get('regles_de_paiement', (int)$item['item_id'])
                    : (function_exists('get_field') ? get_field('regles_de_paiement', (int)$item['item_id']) : []);
                return (isset($rules['pc_caution_type']) && !empty($rules['pc_caution_type']))
                    ? (string)$rules['pc_caution_type']
                    : 'aucune';
            })(),

            'caution_statut' => 'non_demande',

            'statut_reservation'    => $statuts['reservation'],
            'statut_paiement'       => $statuts['paiement'],
            'notes_internes'        => $meta['notes_internes'] ?: null,
            'commentaire_interne'   => $meta['commentaire_interne'] ?: null,

            'date_creation'         => current_time('mysql'),
            'date_maj'              => current_time('mysql'),
        ];
    }

    /**
     * Détermine les statuts initiaux (NOUVELLE LOGIQUE CLEAN).
     */
    protected function determine_statuses(array $context, array $pricing)
    {
        if ($context['type_flux'] === 'devis' || !empty($pricing['is_sur_devis'])) {
            return [
                'reservation' => 'brouillon',
                'paiement'    => 'sur_devis',
            ];
        }

        if ($context['mode_reservation'] === 'directe') {
            return [
                'reservation' => 'reservee',
                'paiement'    => 'en_attente_paiement',
            ];
        }

        return [
            'reservation' => 'en_attente_traitement',
            'paiement'    => 'en_attente_paiement',
        ];
    }
}
