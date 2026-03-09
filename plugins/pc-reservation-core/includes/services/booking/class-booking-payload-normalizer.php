<?php
if (!defined('ABSPATH')) {
    exit; // Sécurité : Empêche l'accès direct au fichier
}

/**
 * Service de Normalisation pour le Moteur de Réservation.
 * S'assure que le payload entrant (front ou back) a toujours la même structure.
 * Pattern Singleton pour garantir une instance unique.
 */
class PCR_Booking_Payload_Normalizer
{
    /**
     * @var PCR_Booking_Payload_Normalizer Instance unique
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
     * Récupère l'instance unique du Normalizer.
     *
     * @return PCR_Booking_Payload_Normalizer
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Normalise les payloads entrants avec des valeurs par défaut strictes.
     *
     * @param array $payload Données brutes
     * @return array Données normalisées
     */
    public function normalize(array $payload)
    {
        $context_defaults = [
            'type'             => 'experience',
            'type_flux'        => 'reservation',
            'origine'          => 'site',
            'mode_reservation' => 'demande',
            'source'           => 'direct',
        ];

        $item_defaults = [
            'item_id'              => 0,
            'experience_tarif_type' => '',
            'date_experience'      => '',
            'date_arrivee'         => '',
            'date_depart'          => '',
        ];

        $people_defaults = [
            'adultes' => 0,
            'enfants' => 0,
            'bebes'   => 0,
        ];

        $pricing_defaults = [
            'currency'           => 'EUR',
            'total'              => 0,
            'lines'              => [],
            'raw_lines_json'     => '',
            'lines_json'         => '',
            'is_sur_devis'       => false,
            'manual_adjustments' => [],
            'snapshot_politique' => null,
        ];

        $customer_defaults = [
            'civilite'           => '',
            'prenom'             => '',
            'nom'                => '',
            'email'              => '',
            'telephone'          => '',
            'langue'             => 'fr',
            'commentaire_client' => '',
        ];

        $meta_defaults = [
            'numero_devis'   => '',
            'notes_internes' => '',
            'commentaire_interne' => '',
            'ref_externe'    => '',
        ];

        // Parse les arguments avec les valeurs par défaut
        $context  = wp_parse_args($payload['context'] ?? [], $context_defaults);
        $item     = wp_parse_args($payload['item'] ?? [], $item_defaults);
        $people   = wp_parse_args($payload['people'] ?? [], $people_defaults);
        $pricing  = wp_parse_args($payload['pricing'] ?? [], $pricing_defaults);
        $customer = wp_parse_args($payload['customer'] ?? [], $customer_defaults);
        $meta     = wp_parse_args($payload['meta'] ?? [], $meta_defaults);

        // Récupération sécurisée du json_lines
        if (empty($pricing['raw_lines_json']) && !empty($pricing['lines_json'])) {
            $pricing['raw_lines_json'] = $pricing['lines_json'];
        }

        // --- DÉLÉGATION AU PRICING CALCULATOR ---
        // On hydrate les lignes de prix
        $calculator = PCR_Booking_Pricing_Calculator::get_instance();
        $pricing = $calculator->hydrate_pricing_lines($pricing);

        // Formatage des ajustements manuels
        $manual_adjustments = [];
        if (!empty($pricing['manual_adjustments']) && is_array($pricing['manual_adjustments'])) {
            foreach ($pricing['manual_adjustments'] as $adj) {
                if (!is_array($adj)) {
                    continue;
                }
                $manual_adjustments[] = [
                    'type'           => isset($adj['type']) ? sanitize_key($adj['type']) : 'adjustment',
                    'label'          => isset($adj['label']) ? sanitize_text_field($adj['label']) : '',
                    'amount'         => isset($adj['amount']) ? (float) $adj['amount'] : 0,
                    'apply_to_total' => array_key_exists('apply_to_total', $adj) ? (bool) $adj['apply_to_total'] : true,
                ];
            }
        }

        $pricing['manual_adjustments'] = $manual_adjustments;
        $pricing['total'] = (float) $pricing['total'];

        $normalized = [
            'context'  => $context,
            'item'     => $item,
            'people'   => [
                'adultes' => max(0, (int) $people['adultes']),
                'enfants' => max(0, (int) $people['enfants']),
                'bebes'   => max(0, (int) $people['bebes']),
            ],
            'pricing'  => $pricing,
            'customer' => $customer,
            'meta'     => $meta,
        ];

        // Remplissage automatique des tarifs si nécessaire
        return $calculator->maybe_autofill_pricing($normalized);
    }
}
