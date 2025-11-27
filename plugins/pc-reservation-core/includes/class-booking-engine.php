<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Moteur commun de création de réservations.
 * Normalise les payloads front / back puis alimente PCR_Reservation + PCR_Payment.
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

class PCR_Booking_Engine
{
    protected static $experience_type_choices = null;
    /**
     * Point d'entrée : création d'une réservation à partir d'un payload normalisé.
     */
    public static function create(array $payload)
    {
        $result = new PCR_Booking_Result();

        if (!class_exists('PCR_Reservation')) {
            $result->errors[] = 'reservation_module_missing';
            return $result;
        }

        $normalized = self::normalize_payload($payload);

        if (empty($normalized['item']['item_id'])) {
            $result->errors[] = 'missing_item';
            return $result;
        }

        $reservation_row = self::build_reservation_row($normalized);

        $reservation_id = PCR_Reservation::create($reservation_row);

        if (!$reservation_id) {
            $result->errors[] = 'db_insert_failed';
            return $result;
        }

        $result->reservation_id = $reservation_id;
        $result->success        = true;

        $payments = [];

        if (class_exists('PCR_Payment')) {
            PCR_Payment::generate_for_reservation($reservation_id);
            $payments = PCR_Payment::get_for_reservation($reservation_id);
        }

        $result->data = [
            'context' => $normalized['context'],
            'pricing' => [
                'total'           => $reservation_row['montant_total'],
                'detail_tarif'    => $reservation_row['detail_tarif'],
                'lines'           => $normalized['pricing']['lines'],
                'is_sur_devis'    => $normalized['pricing']['is_sur_devis'],
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
     * Met à jour une réservation existante avec un payload normalisé.
     *
     * @param int   $reservation_id
     * @param array $payload
     * @return PCR_Booking_Result
     */
    public static function update($reservation_id, array $payload)
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

        $normalized = self::normalize_payload($payload);
        if (
            isset($normalized['context']['type']) &&
            $normalized['context']['type'] === 'experience' &&
            (empty($normalized['item']['date_experience'])) &&
            !empty($existing->date_experience)
        ) {
            $normalized['item']['date_experience'] = $existing->date_experience;
        }
        $reservation_row = self::build_reservation_row($normalized);

        unset($reservation_row['date_creation']);

        if (!empty($existing->statut_reservation)) {
            $reservation_row['statut_reservation'] = $existing->statut_reservation;
        }

        if (!empty($existing->statut_paiement)) {
            $reservation_row['statut_paiement'] = $existing->statut_paiement;
        }

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
                [
                    'preserve_statuses' => true,
                    'statut_reservation' => $reservation_row['statut_reservation'] ?? '',
                    'statut_paiement'    => $reservation_row['statut_paiement'] ?? '',
                ]
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
     * Annule une réservation (statut_reservation = annulee) sans toucher aux paiements.
     */
    public static function cancel($reservation_id)
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

        // On ne touche pas aux paiements, uniquement au statut de réservation
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
     * Normalise les payloads (front, back, API, etc.) afin d'obtenir une structure commune.
     */
    public static function normalize_payload(array $payload)
    {
        $context_defaults = [
            'type'             => 'experience',
            'type_flux'        => 'reservation',
            'origine'          => 'site',
            'mode_reservation' => 'demande',
            'source'           => 'front',
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

        $context  = wp_parse_args($payload['context'] ?? [], $context_defaults);
        $item     = wp_parse_args($payload['item'] ?? [], $item_defaults);
        $people   = wp_parse_args($payload['people'] ?? [], $people_defaults);
        $pricing  = wp_parse_args($payload['pricing'] ?? [], $pricing_defaults);
        $customer = wp_parse_args($payload['customer'] ?? [], $customer_defaults);
        $meta     = wp_parse_args($payload['meta'] ?? [], $meta_defaults);

        if (empty($pricing['raw_lines_json']) && !empty($pricing['lines_json'])) {
            $pricing['raw_lines_json'] = $pricing['lines_json'];
        }

        $pricing = self::hydrate_pricing_lines($pricing);

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

        return self::maybe_autofill_pricing($normalized);
    }

    protected static function hydrate_pricing_lines(array $pricing)
    {
        if (!isset($pricing['lines'])) {
            $pricing['lines'] = [];
        }

        $pricing = self::maybe_merge_pricing_lines_from_source($pricing, $pricing['lines']);

        if (empty($pricing['lines']) && !empty($pricing['raw_lines_json'])) {
            $pricing = self::maybe_merge_pricing_lines_from_source($pricing, $pricing['raw_lines_json']);
        }

        if (empty($pricing['lines']) && !empty($pricing['lines_json'])) {
            $pricing = self::maybe_merge_pricing_lines_from_source($pricing, $pricing['lines_json']);
        }

        if (empty($pricing['raw_lines_json']) && !empty($pricing['lines']) && is_array($pricing['lines'])) {
            $encoded = wp_json_encode($pricing['lines']);
            if ($encoded !== false) {
                $pricing['raw_lines_json'] = $encoded;
            }
        }

        if (!is_array($pricing['lines'])) {
            $pricing['lines'] = [];
        }

        return $pricing;
    }

    protected static function maybe_merge_pricing_lines_from_source(array $pricing, $source)
    {
        $extracted = self::extract_pricing_lines_payload($source);

        if (!empty($extracted['lines'])) {
            $pricing['lines'] = $extracted['lines'];
        }

        $current_total = isset($pricing['total']) ? (float) $pricing['total'] : 0;
        if ($current_total <= 0 && isset($extracted['total'])) {
            $pricing['total'] = (float) $extracted['total'];
        }

        if (empty($pricing['manual_adjustments']) && !empty($extracted['manual_adjustments'])) {
            $pricing['manual_adjustments'] = $extracted['manual_adjustments'];
        }

        return $pricing;
    }

    protected static function extract_pricing_lines_payload($source)
    {
        $result = [
            'lines' => [],
            'total' => null,
            'manual_adjustments' => null,
        ];

        if (empty($source)) {
            return $result;
        }

        if (is_string($source)) {
            $decoded = json_decode(wp_unslash($source), true);
            if (!is_array($decoded)) {
                return $result;
            }
            $source = $decoded;
        }

        if (!is_array($source)) {
            return $result;
        }

        if (self::is_associative_array($source) && isset($source['lines']) && is_array($source['lines'])) {
            $result['lines'] = $source['lines'];

            if (isset($source['total'])) {
                $result['total'] = (float) $source['total'];
            }

            if (!empty($source['manual_adjustments']) && is_array($source['manual_adjustments'])) {
                $result['manual_adjustments'] = $source['manual_adjustments'];
            }

            return $result;
        }

        foreach ($source as $line) {
            if (is_array($line)) {
                $result['lines'][] = $line;
            }
        }

        return $result;
    }

    protected static function is_associative_array(array $array)
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Transforme les données normalisées en une ligne compatible avec la table wp_pc_reservations.
     */
    public static function build_reservation_row(array $normalized)
    {
        $context  = $normalized['context'];
        $item     = $normalized['item'];
        $people   = $normalized['people'];
        $pricing  = self::hydrate_pricing_lines($normalized['pricing']);
        $customer = $normalized['customer'];
        $meta     = $normalized['meta'];

        $original_lines = is_array($pricing['lines']) ? $pricing['lines'] : [];
        $original_lines = self::remove_existing_adjustments_from_lines($original_lines, $pricing['manual_adjustments']);
        $detail_lines   = self::merge_lines_with_adjustments($original_lines, $pricing['manual_adjustments'], $pricing['currency']);

        if (!empty($detail_lines)) {
            $detail_tarif = wp_json_encode($detail_lines);
        } elseif (!empty($pricing['raw_lines_json'])) {
            $detail_tarif = wp_kses_post($pricing['raw_lines_json']);
        } else {
            $detail_tarif = '';
        }

        $statuts = self::determine_statuses($context, $pricing);

        $lines_total      = self::calculate_total_from_lines($original_lines);
        $adjustments_total = self::sum_manual_adjustments($pricing['manual_adjustments']);
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

            'statut_reservation'    => $statuts['reservation'],
            'statut_paiement'       => $statuts['paiement'],
            'notes_internes'        => $meta['notes_internes'] ?: null,
            'commentaire_interne'   => $meta['commentaire_interne'] ?: null,

            'date_creation'         => current_time('mysql'),
            'date_maj'              => current_time('mysql'),
        ];
    }

    /**
     * Détermine les statuts initiaux selon le flux et le mode.
     */
    protected static function determine_statuses(array $context, array $pricing)
    {
        // Flux "devis" ou tarification sur devis : la réservation reste un devis
        if ($context['type_flux'] === 'devis' || !empty($pricing['is_sur_devis'])) {
            return [
                'reservation' => 'devis_envoye',
                'paiement'    => 'non_concerne',
            ];
        }

        // Flux "réservation" + mode directe : réservation confirmée qui doit apparaître au calendrier
        if ($context['mode_reservation'] === 'directe') {
            return [
                'reservation' => 'reservee',
                'paiement'    => 'en_attente_paiement',
            ];
        }

        // Flux "réservation" + mode "demande" : en attente de traitement (ne doit PAS apparaître au calendrier)
        return [
            'reservation' => 'en_attente_traitement',
            'paiement'    => 'en_attente_paiement',
        ];
    }

    protected static function merge_lines_with_adjustments(array $lines, array $adjustments, $currency)
    {
        if (empty($adjustments)) {
            return $lines;
        }

        $result = $lines;
        foreach ($adjustments as $adj) {
            $label = $adj['label'] ?: 'Ajustement';
            $amount = isset($adj['amount']) ? (float) $adj['amount'] : 0;
            $result[] = [
                'label'         => $label,
                'price'         => self::format_adjustment_price($amount, $currency),
                'amount'        => $amount,
                'is_adjustment' => true,
            ];
        }

        return $result;
    }

    protected static function calculate_total_from_lines(array $lines)
    {
        $sum = 0.0;
        foreach ($lines as $line) {
            if (isset($line['amount']) && is_numeric($line['amount'])) {
                $sum += (float) $line['amount'];
                continue;
            }

            if (empty($line['price'])) {
                continue;
            }

            $sum += self::parse_price_to_float($line['price']);
        }

        return $sum;
    }

    protected static function sum_manual_adjustments(array $adjustments)
    {
        $sum = 0.0;

        foreach ($adjustments as $adj) {
            $apply = array_key_exists('apply_to_total', $adj) ? (bool) $adj['apply_to_total'] : true;
            if (!$apply) {
                continue;
            }
            $sum += isset($adj['amount']) ? (float) $adj['amount'] : 0;
        }

        return $sum;
    }

    protected static function remove_existing_adjustments_from_lines(array $lines, array $adjustments)
    {
        if (empty($lines)) {
            return $lines;
        }

        if (empty($adjustments)) {
            $filtered = [];
            foreach ($lines as $line) {
                $is_flagged = !empty($line['is_adjustment']) || !empty($line['is_manual_adjustment']) || (isset($line['source']) && $line['source'] === 'manual_adjustment');
                if ($is_flagged) {
                    continue;
                }
                $filtered[] = $line;
            }
            return $filtered;
        }

        foreach ($adjustments as $adj) {
            $target_label  = self::normalize_adjustment_label($adj['label'] ?? '');
            $target_amount = isset($adj['amount']) ? (float) $adj['amount'] : 0;

            foreach ($lines as $index => $line) {
                $line_label = self::normalize_adjustment_label($line['label'] ?? '');

                if ($target_label !== '' && $line_label !== $target_label) {
                    continue;
                }

                $is_flagged = !empty($line['is_adjustment']) || !empty($line['is_manual_adjustment']) || (isset($line['source']) && $line['source'] === 'manual_adjustment');
                $line_amount = null;
                if (isset($line['amount']) && is_numeric($line['amount'])) {
                    $line_amount = (float) $line['amount'];
                } elseif (!empty($line['price'])) {
                    $line_amount = self::parse_price_to_float($line['price']);
                }

                if ($is_flagged || ($line_amount !== null && abs($line_amount - $target_amount) < 0.01)) {
                    unset($lines[$index]);
                    break;
                }
            }
        }

        return array_values($lines);
    }

    protected static function normalize_adjustment_label($label)
    {
        $label = (string) $label;
        return $label === '' ? '' : strtolower(trim($label));
    }

    protected static function parse_price_to_float($value)
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = html_entity_decode((string) $value);
        $value = str_replace(["\xC2\xA0", ' '], '', $value);
        $value = preg_replace('/[^0-9,\.\-]/', '', $value);

        if ($value === '' || $value === '-' || $value === '--') {
            return 0;
        }

        $comma_pos = strrpos($value, ',');
        $dot_pos   = strrpos($value, '.');

        if ($comma_pos !== false && $dot_pos !== false) {
            if ($comma_pos > $dot_pos) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif ($comma_pos !== false && $dot_pos === false) {
            $value = str_replace(',', '.', $value);
        }

        return (float) $value;
    }

    protected static function format_adjustment_price($amount, $currency)
    {
        return self::format_currency_display($amount, $currency);
    }

    protected static function format_currency_display($amount, $currency)
    {
        $formatted = function_exists('number_format_i18n')
            ? number_format_i18n($amount, 2)
            : number_format($amount, 2, ',', ' ');

        $symbol = self::currency_symbol($currency);
        return trim($formatted . ' ' . $symbol);
    }

    protected static function currency_symbol($currency)
    {
        $currency = strtoupper($currency ?: 'EUR');
        if ($currency === 'EUR') {
            return '€';
        }

        return $currency;
    }

    protected static function maybe_autofill_pricing(array $normalized)
    {
        $context = $normalized['context'];
        $pricing = $normalized['pricing'];

        if ($context['type'] === 'experience') {
            $needs_lines = empty($pricing['lines']);
            $needs_total = ($pricing['total'] <= 0);

            if ($needs_lines || $needs_total) {
                $auto = self::auto_generate_experience_pricing($normalized);
                if (!empty($auto)) {
                    if ($needs_lines && !empty($auto['lines'])) {
                        $pricing['lines'] = $auto['lines'];
                    }
                    if ($needs_total && isset($auto['total'])) {
                        $pricing['total'] = (float) $auto['total'];
                    }
                    if (!empty($auto['is_sur_devis'])) {
                        $pricing['is_sur_devis'] = true;
                    }
                }
            }
        }

        $normalized['pricing'] = $pricing;
        return $normalized;
    }

    protected static function auto_generate_experience_pricing(array $normalized)
    {
        if (!function_exists('get_field')) {
            return null;
        }

        $item_id = (int) ($normalized['item']['item_id'] ?? 0);
        $identifier = $normalized['item']['experience_tarif_type'] ?? '';

        if (!$item_id || !$identifier) {
            return null;
        }

        $row_info = self::find_experience_pricing_row($item_id, $identifier);
        if (!$row_info) {
            return null;
        }

        $row      = $row_info['row'];
        $currency = $normalized['pricing']['currency'] ?: 'EUR';
        $people   = $normalized['people'];

        $lines = [];
        $total = 0.0;

        if ($row_info['code'] === 'sur-devis') {
            return [
                'lines'        => [],
                'total'        => 0,
                'is_sur_devis' => true,
            ];
        }

        $line_defs = isset($row['exp_tarifs_lignes']) ? (array) $row['exp_tarifs_lignes'] : [];

        foreach ($line_defs as $def) {
            $type  = isset($def['type_ligne']) ? (string) $def['type_ligne'] : 'personnalise';
            $unit  = isset($def['tarif_valeur']) ? (float) $def['tarif_valeur'] : 0;
            $label = self::resolve_line_label($type, $def);

            $qty = 1;
            if ($type === 'adulte') {
                $qty = max(0, (int) ($people['adultes'] ?? 0));
            } elseif ($type === 'enfant') {
                $qty = max(0, (int) ($people['enfants'] ?? 0));
            } elseif ($type === 'bebe') {
                $qty = max(0, (int) ($people['bebes'] ?? 0));
            } elseif (!empty($def['tarif_enable_qty'])) {
                $qty = max(0, (int) ($def['tarif_qty_default'] ?? 0));
            }

            if (($type === 'adulte' || $type === 'enfant' || $type === 'bebe') && $qty <= 0) {
                continue;
            }

            if ($qty <= 0) {
                continue;
            }

            $amount = $qty * $unit;
            $display_label = trim($qty . ' ' . $label);

            if ($type === 'bebe' && $unit <= 0) {
                $lines[] = [
                    'label'  => $display_label,
                    'price'  => __('Gratuit', 'pc'),
                    'amount' => 0,
                ];
                continue;
            }

            $lines[] = [
                'label'  => $display_label,
                'price'  => self::format_currency_display($amount, $currency),
                'amount' => $amount,
            ];
            $total += $amount;
        }

        $fixed_fees = isset($row['exp-frais-fixes']) ? (array) $row['exp-frais-fixes'] : [];
        foreach ($fixed_fees as $fee) {
            $fee_label = trim((string) ($fee['exp_description_frais_fixe'] ?? ''));
            $fee_amount = isset($fee['exp_tarif_frais_fixe']) ? (float) $fee['exp_tarif_frais_fixe'] : 0;
            if ($fee_label === '' || $fee_amount == 0) {
                continue;
            }

            $lines[] = [
                'label'  => $fee_label,
                'price'  => self::format_currency_display($fee_amount, $currency),
                'amount' => $fee_amount,
            ];
            $total += $fee_amount;
        }

        return [
            'lines' => $lines,
            'total' => $total,
        ];
    }

    /**
     * Indique si le tarif sélectionné correspond à une expérience personnalisée.
     */
    public static function is_custom_experience_type($item_id, $identifier)
    {
        $row_info = self::find_experience_pricing_row((int) $item_id, (string) $identifier);
        if (!$row_info) {
            return false;
        }

        $code = isset($row_info['code']) ? (string) $row_info['code'] : '';
        $normalized = sanitize_title($code);

        return in_array($normalized, ['custom', 'personnalise', 'personnalisee'], true);
    }

    protected static function find_experience_pricing_row($item_id, $identifier)
    {
        if (!$item_id || !function_exists('get_field')) {
            return null;
        }

        $rows = get_field('exp_types_de_tarifs', $item_id);
        if (empty($rows)) {
            return null;
        }

        $identifier      = (string) $identifier;
        $identifier_slug = sanitize_title($identifier);

        if ($identifier === '' && count($rows) === 1) {
            $first_row   = $rows[0];
            $first_code  = isset($first_row['exp_type']) ? (string) $first_row['exp_type'] : '';
            $first_key   = $first_code !== '' ? $first_code . '_0' : 'tarif_0';
            $first_label = self::resolve_experience_type_label($first_row);

            return [
                'row'  => $first_row,
                'key'  => $first_key,
                'code' => $first_code,
                'label' => $first_label,
            ];
        }

        foreach ($rows as $index => $row) {
            $code = isset($row['exp_type']) ? (string) $row['exp_type'] : '';
            $key  = $code !== '' ? $code . '_' . $index : 'tarif_' . $index;
            $label = self::resolve_experience_type_label($row);

            $candidates = array_filter([
                $key,
                $code,
                $label,
                sanitize_title($key),
                sanitize_title($code),
                sanitize_title($label),
            ]);

            foreach ($candidates as $candidate) {
                if ($candidate === '') {
                    continue;
                }
                if ($candidate === $identifier || sanitize_title($candidate) === $identifier_slug) {
                    return [
                        'row'  => $row,
                        'key'  => $key,
                        'code' => $code,
                        'label' => $label,
                    ];
                }
            }
        }

        return null;
    }

    protected static function resolve_experience_type_label(array $row)
    {
        if (function_exists('pc_exp_type_label')) {
            return pc_exp_type_label($row, self::get_experience_type_choices());
        }

        $type_value   = isset($row['exp_type']) ? (string) $row['exp_type'] : '';
        $custom_label = isset($row['exp_type_custom']) ? trim((string) $row['exp_type_custom']) : '';

        if ($type_value === 'custom' && $custom_label !== '') {
            return $custom_label;
        }

        $choices = self::get_experience_type_choices();
        if ($type_value !== '' && isset($choices[$type_value])) {
            return (string) $choices[$type_value];
        }

        return $type_value !== '' ? ucwords(str_replace(['_', '-'], ' ', $type_value)) : __('Type', 'pc');
    }

    protected static function get_experience_type_choices()
    {
        if (self::$experience_type_choices !== null) {
            return self::$experience_type_choices;
        }

        $choices = [];

        if (function_exists('get_field_object')) {
            $field_object = get_field_object('exp_types_de_tarifs');
            if (!empty($field_object['sub_fields'])) {
                foreach ($field_object['sub_fields'] as $sub_field) {
                    if (!empty($sub_field['name']) && $sub_field['name'] === 'exp_type' && !empty($sub_field['choices'])) {
                        $choices = (array) $sub_field['choices'];
                        break;
                    }
                }
            }
        }

        self::$experience_type_choices = $choices;
        return $choices;
    }

    protected static function resolve_line_label($type, array $def)
    {
        if ($type === 'adulte') {
            return __('Adulte', 'pc');
        }
        if ($type === 'enfant') {
            $precision = isset($def['precision_age_enfant']) ? trim((string) $def['precision_age_enfant']) : '';
            return $precision ? sprintf(__('Enfant (%s)', 'pc'), $precision) : __('Enfant', 'pc');
        }
        if ($type === 'bebe') {
            $precision = isset($def['precision_age_bebe']) ? trim((string) $def['precision_age_bebe']) : '';
            return $precision ? sprintf(__('Bébé (%s)', 'pc'), $precision) : __('Bébé', 'pc');
        }

        $custom = isset($def['tarif_nom_perso']) ? trim((string) $def['tarif_nom_perso']) : '';
        return $custom !== '' ? $custom : __('Forfait', 'pc');
    }
}
