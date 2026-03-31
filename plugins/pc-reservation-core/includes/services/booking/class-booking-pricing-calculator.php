<?php
if (!defined('ABSPATH')) {
    exit; // Sécurité : Empêche l'accès direct au fichier
}

/**
 * Service de Calcul des Prix (Pricing Calculator) pour le Moteur de Réservation.
 * Gère la génération des lignes de prix, les ajustements manuels et les calculs de totaux.
 * Pattern Singleton pour garantir une instance unique.
 */
class PCR_Booking_Pricing_Calculator
{
    /**
     * @var PCR_Booking_Pricing_Calculator Instance unique
     */
    private static $instance = null;

    /**
     * @var array Cache pour les choix de types d'expériences
     */
    private $experience_type_choices = null;

    /**
     * Constructeur privé (Singleton)
     */
    private function __construct() {}

    /**
     * Empêche le clonage
     */
    private function __clone() {}

    /**
     * Récupère l'instance unique du Calculator.
     *
     * @return PCR_Booking_Pricing_Calculator
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Hydrate les lignes de tarification à partir des différentes sources possibles.
     *
     * @param array $pricing
     * @return array
     */
    public function hydrate_pricing_lines(array $pricing)
    {
        if (!isset($pricing['lines'])) $pricing['lines'] = [];

        $pricing = $this->maybe_merge_pricing_lines_from_source($pricing, $pricing['lines']);
        if (empty($pricing['lines']) && !empty($pricing['raw_lines_json'])) {
            $pricing = $this->maybe_merge_pricing_lines_from_source($pricing, $pricing['raw_lines_json']);
        }
        if (empty($pricing['lines']) && !empty($pricing['lines_json'])) {
            $pricing = $this->maybe_merge_pricing_lines_from_source($pricing, $pricing['lines_json']);
        }

        // 🚀 THE FIX : Sas de décontamination ! On nettoie les vieilles lignes du front-end legacy !
        if (!empty($pricing['lines']) && is_array($pricing['lines'])) {
            $pricing['lines'] = $this->clean_legacy_lines($pricing['lines']);

            // 🚀 FIX PDF CRITIQUE : On force l'UTF-8 pour empêcher la création de "\u20ac" (Symbole €) 
            // qui se transformait en chiffre "20" dans le générateur de facture !
            $encoded = wp_json_encode($pricing['lines'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded !== false) {
                $pricing['raw_lines_json'] = $encoded;
                $pricing['lines_json'] = $encoded;
            }
        }

        if (!is_array($pricing['lines'])) $pricing['lines'] = [];

        return $pricing;
    }

    /**
     * 🚀 NOUVEAU : Intercepte et répare le JSON du front-end avant sauvegarde
     */
    private function clean_legacy_lines(array $lines)
    {
        $cleaned = [];
        foreach ($lines as $line) {
            $label = $line['label'] ?? '';

            // 1. On supprime la ligne inutile "Options"
            if (strtolower(trim($label)) === 'options' && empty($line['amount'])) {
                continue;
            }

            // 2. Réparation du prix
            $amount = 0;
            if (isset($line['amount']) && is_numeric($line['amount'])) {
                $amount = (float)$line['amount'];
            } elseif (!empty($line['price'])) {
                $amount = $this->parse_price_to_float($line['price']);
            }

            // 3. Correction des symboles cassés
            $label = str_replace(['\u00d7', 'u00d7'], '×', $label);

            $qty = isset($line['qty']) ? (int)$line['qty'] : 0;
            $clean_label = $line['clean_label'] ?? $label;

            // 4. Extraction de la quantité si absente (🚀 FIX : Ajout du flag /u pour supporter l'UTF-8 sur le symbole ×)
            if ($qty <= 0) {
                if (preg_match('/^(?:(\d+)\s*[x×X]\s*)/u', $label, $matches) || preg_match('/^(\d+)\s+/u', $label, $matches)) {
                    $qty = (int)$matches[1];
                    $clean_label = preg_replace('/^(?:\d+\s*[x×X]\s*|\d+\s+)/u', '', $label);
                } elseif (preg_match('/(?:\s*[x×X]\s*(\d+))$/u', $label, $matches)) {
                    $qty = (int)$matches[1];
                    $clean_label = preg_replace('/(?:\s*[x×X]\s*\d+)$/u', '', $label);
                } else {
                    $qty = 1;
                }
            }

            // 🚀 SÉCURITÉ : On nettoie les espaces invisibles corrompus ou caractères spéciaux restants au début/fin
            $clean_label = trim(preg_replace('/^[\pZ\pC]+|[\pZ\pC]+$/u', '', $clean_label));

            // 5. Réparation du texte "undefined"
            $price_str = $line['price'] ?? '';
            if (empty($price_str) || strpos((string)$price_str, 'undefined') !== false) {
                $price_str = $this->format_currency_display($amount, 'EUR');
            }

            $cleaned[] = [
                'label'       => trim($label),
                'clean_label' => $clean_label,
                'qty'         => $qty,
                'amount'      => $amount,
                'price'       => $price_str
            ];
        }
        return $cleaned;
    }

    private function maybe_merge_pricing_lines_from_source(array $pricing, $source)
    {
        $extracted = $this->extract_pricing_lines_payload($source);
        if (!empty($extracted['lines'])) $pricing['lines'] = $extracted['lines'];

        $current_total = isset($pricing['total']) ? (float) $pricing['total'] : 0;
        if ($current_total <= 0 && isset($extracted['total'])) {
            $pricing['total'] = (float) $extracted['total'];
        }

        if (empty($pricing['manual_adjustments']) && !empty($extracted['manual_adjustments'])) {
            $pricing['manual_adjustments'] = $extracted['manual_adjustments'];
        }
        return $pricing;
    }

    private function extract_pricing_lines_payload($source)
    {
        $result = ['lines' => [], 'total' => null, 'manual_adjustments' => null];
        if (empty($source)) return $result;

        if (is_string($source)) {
            $decoded = json_decode(wp_unslash($source), true);
            if (!is_array($decoded)) return $result;
            $source = $decoded;
        }

        if (!is_array($source)) return $result;

        if ($this->is_associative_array($source) && isset($source['lines']) && is_array($source['lines'])) {
            $result['lines'] = $source['lines'];
            if (isset($source['total'])) $result['total'] = (float) $source['total'];
            if (!empty($source['manual_adjustments']) && is_array($source['manual_adjustments'])) {
                $result['manual_adjustments'] = $source['manual_adjustments'];
            }
            return $result;
        }

        foreach ($source as $line) {
            if (is_array($line)) $result['lines'][] = $line;
        }
        return $result;
    }

    private function is_associative_array(array $array)
    {
        if ($array === []) return false;
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Remplit automatiquement les tarifs (particulièrement pour les expériences).
     *
     * @param array $normalized
     * @return array
     */
    public function maybe_autofill_pricing(array $normalized)
    {
        $context = $normalized['context'];
        $pricing = $normalized['pricing'];

        if ($context['type'] === 'experience') {
            $needs_lines = empty($pricing['lines']);
            $needs_total = ($pricing['total'] <= 0);

            if ($needs_lines || $needs_total) {
                $auto = $this->auto_generate_experience_pricing($normalized);
                if (!empty($auto)) {
                    if ($needs_lines && !empty($auto['lines'])) $pricing['lines'] = $auto['lines'];
                    if ($needs_total && isset($auto['total'])) $pricing['total'] = (float) $auto['total'];
                    if (!empty($auto['is_sur_devis'])) $pricing['is_sur_devis'] = true;
                }
            }
        }

        $normalized['pricing'] = $pricing;
        return $normalized;
    }

    private function auto_generate_experience_pricing(array $normalized)
    {
        if (!class_exists('PCR_Fields') && !function_exists('get_field')) return null;

        $item_id = (int) ($normalized['item']['item_id'] ?? 0);
        $identifier = $normalized['item']['experience_tarif_type'] ?? '';

        if (!$item_id || !$identifier) return null;

        $row_info = $this->find_experience_pricing_row($item_id, $identifier);
        if (!$row_info) return null;

        $row      = $row_info['row'];
        $currency = $normalized['pricing']['currency'] ?: 'EUR';
        $people   = $normalized['people'];
        $lines = [];
        $total = 0.0;

        if ($row_info['code'] === 'sur-devis') {
            return ['lines' => [], 'total' => 0, 'is_sur_devis' => true];
        }

        $line_defs = isset($row['exp_tarifs_lignes']) ? (array) $row['exp_tarifs_lignes'] : [];
        foreach ($line_defs as $def) {
            $type  = isset($def['type_ligne']) ? (string) $def['type_ligne'] : 'personnalise';
            $unit  = isset($def['tarif_valeur']) ? (float) $def['tarif_valeur'] : 0;
            $label = $this->resolve_line_label($type, $def);
            $qty = 1;

            if ($type === 'adulte') $qty = max(0, (int) ($people['adultes'] ?? 0));
            elseif ($type === 'enfant') $qty = max(0, (int) ($people['enfants'] ?? 0));
            elseif ($type === 'bebe') $qty = max(0, (int) ($people['bebes'] ?? 0));
            elseif (!empty($def['tarif_enable_qty'])) $qty = max(0, (int) ($def['tarif_qty_default'] ?? 0));

            if (($type === 'adulte' || $type === 'enfant' || $type === 'bebe') && $qty <= 0) continue;
            if ($qty <= 0) continue;

            $amount = $qty * $unit;
            $display_label = trim($qty . ' ' . $label);

            if ($type === 'bebe' && $unit <= 0) {
                $lines[] = [
                    'label' => $display_label,
                    'clean_label' => trim($label),
                    'qty' => $qty,
                    'price' => __('Gratuit', 'pc'),
                    'amount' => 0
                ];
                continue;
            }

            $lines[] = [
                'label' => $display_label,
                'clean_label' => trim($label),
                'qty' => $qty,
                'price' => $this->format_currency_display($amount, $currency),
                'amount' => $amount
            ];
            $total += $amount;
        }

        $fixed_fees = isset($row['exp-frais-fixes']) ? (array) $row['exp-frais-fixes'] : [];
        foreach ($fixed_fees as $fee) {
            $fee_label = trim((string) ($fee['exp_description_frais_fixe'] ?? ''));
            $fee_amount = isset($fee['exp_tarif_frais_fixe']) ? (float) $fee['exp_tarif_frais_fixe'] : 0;
            if ($fee_label === '' || $fee_amount == 0) continue;
            $lines[] = [
                'label' => $fee_label,
                'clean_label' => $fee_label,
                'qty' => 1,
                'price' => $this->format_currency_display($fee_amount, $currency),
                'amount' => $fee_amount
            ];
            $total += $fee_amount;
        }

        return ['lines' => $lines, 'total' => $total];
    }

    private function find_experience_pricing_row($item_id, $identifier)
    {
        if (!$item_id || (!class_exists('PCR_Fields') && !function_exists('get_field'))) return null;

        $rows = class_exists('PCR_Fields')
            ? PCR_Fields::get('exp_types_de_tarifs', $item_id)
            : (function_exists('get_field') ? get_field('exp_types_de_tarifs', $item_id) : null);

        if (empty($rows)) return null;

        $identifier      = (string) $identifier;
        $identifier_slug = sanitize_title($identifier);

        if ($identifier === '' && count($rows) === 1) {
            $first_row   = $rows[0];
            $first_code  = isset($first_row['exp_type']) ? (string) $first_row['exp_type'] : '';
            $first_key   = $first_code !== '' ? $first_code . '_0' : 'tarif_0';
            $first_label = $this->resolve_experience_type_label($first_row);
            return ['row' => $first_row, 'key' => $first_key, 'code' => $first_code, 'label' => $first_label];
        }

        foreach ($rows as $index => $row) {
            $code = isset($row['exp_type']) ? (string) $row['exp_type'] : '';
            $key  = $code !== '' ? $code . '_' . $index : 'tarif_' . $index;
            $label = $this->resolve_experience_type_label($row);
            $candidates = array_filter([$key, $code, $label, sanitize_title($key), sanitize_title($code), sanitize_title($label)]);

            foreach ($candidates as $candidate) {
                if ($candidate === '') continue;
                if ($candidate === $identifier || sanitize_title($candidate) === $identifier_slug) {
                    return ['row' => $row, 'key' => $key, 'code' => $code, 'label' => $label];
                }
            }
        }
        return null;
    }

    private function resolve_experience_type_label(array $row)
    {
        if (function_exists('pc_exp_type_label')) {
            return pc_exp_type_label($row, $this->get_experience_type_choices());
        }

        $type_value   = isset($row['exp_type']) ? (string) $row['exp_type'] : '';
        $custom_label = isset($row['exp_type_custom']) ? trim((string) $row['exp_type_custom']) : '';

        if ($type_value === 'custom' && $custom_label !== '') return $custom_label;

        $choices = $this->get_experience_type_choices();
        if ($type_value !== '' && isset($choices[$type_value])) return (string) $choices[$type_value];

        return $type_value !== '' ? ucwords(str_replace(['_', '-'], ' ', $type_value)) : __('Type', 'pc');
    }

    private function get_experience_type_choices()
    {
        if ($this->experience_type_choices !== null) return $this->experience_type_choices;

        $choices = [];

        // Sécurisation spéciale : On tente de récupérer l'objet du champ (configuration)
        $field_object = null;
        if (class_exists('PCR_Fields') && method_exists('PCR_Fields', 'get_field_object')) {
            $field_object = PCR_Fields::get_field_object('exp_types_de_tarifs');
        } elseif (function_exists('get_field_object')) {
            $field_object = get_field_object('exp_types_de_tarifs');
        }

        if (!empty($field_object)) {
            if (!empty($field_object['sub_fields'])) {
                foreach ($field_object['sub_fields'] as $sub_field) {
                    if (!empty($sub_field['name']) && $sub_field['name'] === 'exp_type' && !empty($sub_field['choices'])) {
                        $choices = (array) $sub_field['choices'];
                        break;
                    }
                }
            }
        }

        $this->experience_type_choices = $choices;
        return $choices;
    }

    private function resolve_line_label($type, array $def)
    {
        if ($type === 'adulte') return __('Adulte', 'pc');
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

    /**
     * Fusionne les lignes existantes avec les ajustements manuels.
     *
     * @param array $lines
     * @param array $adjustments
     * @param string $currency
     * @return array
     */
    public function merge_lines_with_adjustments(array $lines, array $adjustments, $currency)
    {
        if (empty($adjustments)) return $lines;
        $result = $lines;
        foreach ($adjustments as $adj) {
            $label = $adj['label'] ?: 'Ajustement';
            $amount = isset($adj['amount']) ? (float) $adj['amount'] : 0;
            $result[] = [
                'label'         => $label,
                'price'         => $this->format_adjustment_price($amount, $currency),
                'amount'        => $amount,
                'is_adjustment' => true,
            ];
        }
        return $result;
    }

    public function calculate_total_from_lines(array $lines)
    {
        $sum = 0.0;
        foreach ($lines as $line) {
            if (isset($line['amount']) && is_numeric($line['amount'])) {
                $sum += (float) $line['amount'];
                continue;
            }
            if (empty($line['price'])) continue;
            $sum += $this->parse_price_to_float($line['price']);
        }
        return $sum;
    }

    public function sum_manual_adjustments(array $adjustments)
    {
        $sum = 0.0;
        foreach ($adjustments as $adj) {
            $apply = array_key_exists('apply_to_total', $adj) ? (bool) $adj['apply_to_total'] : true;
            if (!$apply) continue;
            $sum += isset($adj['amount']) ? (float) $adj['amount'] : 0;
        }
        return $sum;
    }

    public function remove_existing_adjustments_from_lines(array $lines, array $adjustments)
    {
        if (empty($lines)) return $lines;
        if (empty($adjustments)) {
            $filtered = [];
            foreach ($lines as $line) {
                $is_flagged = !empty($line['is_adjustment']) || !empty($line['is_manual_adjustment']) || (isset($line['source']) && $line['source'] === 'manual_adjustment');
                if ($is_flagged) continue;
                $filtered[] = $line;
            }
            return $filtered;
        }
        foreach ($adjustments as $adj) {
            $target_label  = $this->normalize_adjustment_label($adj['label'] ?? '');
            $target_amount = isset($adj['amount']) ? (float) $adj['amount'] : 0;
            foreach ($lines as $index => $line) {
                $line_label = $this->normalize_adjustment_label($line['label'] ?? '');
                if ($target_label !== '' && $line_label !== $target_label) continue;
                $is_flagged = !empty($line['is_adjustment']) || !empty($line['is_manual_adjustment']) || (isset($line['source']) && $line['source'] === 'manual_adjustment');
                $line_amount = null;
                if (isset($line['amount']) && is_numeric($line['amount'])) $line_amount = (float) $line['amount'];
                elseif (!empty($line['price'])) $line_amount = $this->parse_price_to_float($line['price']);

                if ($is_flagged || ($line_amount !== null && abs($line_amount - $target_amount) < 0.01)) {
                    unset($lines[$index]);
                    break;
                }
            }
        }
        return array_values($lines);
    }

    private function normalize_adjustment_label($label)
    {
        $label = (string) $label;
        return $label === '' ? '' : strtolower(trim($label));
    }

    private function parse_price_to_float($value)
    {
        if (is_numeric($value)) return (float) $value;
        $value = html_entity_decode((string) $value);
        $value = str_replace(["\xC2\xA0", ' '], '', $value);
        $value = preg_replace('/[^0-9,\.\-]/', '', $value);
        if ($value === '' || $value === '-' || $value === '--') return 0;
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

    private function format_adjustment_price($amount, $currency)
    {
        return $this->format_currency_display($amount, $currency);
    }

    private function format_currency_display($amount, $currency)
    {
        $formatted = function_exists('number_format_i18n') ? number_format_i18n($amount, 2) : number_format($amount, 2, ',', ' ');
        $symbol = $this->currency_symbol($currency);
        return trim($formatted . ' ' . $symbol);
    }

    private function currency_symbol($currency)
    {
        $currency = strtoupper($currency ?: 'EUR');
        if ($currency === 'EUR') return '€';
        return $currency;
    }
}
