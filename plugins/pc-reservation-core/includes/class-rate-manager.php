<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gestionnaire des Tarifs & Saisons (Backend)
 * Gère la lecture et la sauvegarde des champs ACF complexes pour le module "Rate Manager".
 */
class PCR_Rate_Manager
{
    // Clés ACF (Basées sur votre app.js original)
    const KEY_SEASON_REPEATER = 'field_pc_season_blocks_20250826';
    const KEY_PROMO_REPEATER  = 'field_693425b17049d'; // ou 'field_pc_promo_blocks' selon votre config

    /**
     * Récupère les données formatées pour le JS du Dashboard
     */
    public static function get_rates_data($post_id)
    {
        if (!$post_id) return ['seasons' => [], 'promos' => []];

        return [
            'seasons' => self::format_seasons(get_field(self::KEY_SEASON_REPEATER, $post_id)),
            'promos'  => self::format_promos(get_field(self::KEY_PROMO_REPEATER, $post_id)),
        ];
    }

    /**
     * Sauvegarde les données reçues du module JS
     * @param int $post_id
     * @param string $json_data Chaîne JSON brute envoyée par le JS
     */
    public static function save_rates_data($post_id, $json_data)
    {
        if (!$post_id || empty($json_data)) return;

        $data = json_decode(stripslashes($json_data), true);
        if (!is_array($data)) return;

        // 1. Sauvegarde des Saisons
        if (isset($data['seasons'])) {
            $acf_seasons = [];
            foreach ($data['seasons'] as $season) {
                // Formatage des périodes (sous-répéteur)
                $periods = [];
                if (!empty($season['periods'])) {
                    foreach ($season['periods'] as $p) {
                        $periods[] = [
                            'date_from' => $p['start'],
                            'date_to'   => $p['end']
                        ];
                    }
                }

                $acf_seasons[] = [
                    'season_name'            => sanitize_text_field($season['name']),
                    'season_price'           => floatval($season['price']),
                    'season_note'            => sanitize_textarea_field($season['note'] ?? ''),
                    'season_min_nights'      => intval($season['minNights'] ?? 0),
                    'season_extra_guest_fee' => floatval($season['guestFee'] ?? 0),
                    'season_extra_guest_from' => intval($season['guestFrom'] ?? 0),
                    'season_periods'         => $periods
                ];
            }
            // Mise à jour ACF
            update_field(self::KEY_SEASON_REPEATER, $acf_seasons, $post_id);
        }

        // 2. Sauvegarde des Promotions
        if (isset($data['promos'])) {
            $acf_promos = [];
            foreach ($data['promos'] as $promo) {
                // Formatage des périodes
                $periods = [];
                if (!empty($promo['periods'])) {
                    foreach ($promo['periods'] as $p) {
                        $periods[] = [
                            'date_from' => $p['start'],
                            'date_to'   => $p['end']
                        ];
                    }
                }

                $acf_promos[] = [
                    'nom_de_la_promotion' => sanitize_text_field($promo['name']),
                    'promo_type'          => sanitize_text_field($promo['promo_type']),
                    'promo_value'         => floatval($promo['value']),
                    'promo_valid_until'   => sanitize_text_field($promo['validUntil'] ?? ''), // Date format Ymd ou Y-m-d
                    'promo_periods'       => $periods
                ];
            }
            // Mise à jour ACF
            update_field(self::KEY_PROMO_REPEATER, $acf_promos, $post_id);
        }
    }

    // --- Helpers de formatage pour l'envoi au JS ---

    private static function format_seasons($acf_data)
    {
        if (!is_array($acf_data)) return [];
        $out = [];
        foreach ($acf_data as $row) {
            $periods = [];
            if (!empty($row['season_periods'])) {
                foreach ($row['season_periods'] as $p) {
                    $periods[] = ['start' => $p['date_from'], 'end' => $p['date_to']];
                }
            }
            $out[] = [
                'name'      => $row['season_name'],
                'price'     => $row['season_price'],
                'note'      => $row['season_note'],
                'minNights' => $row['season_min_nights'],
                'guestFee'  => $row['season_extra_guest_fee'],
                'guestFrom' => $row['season_extra_guest_from'],
                'periods'   => $periods
            ];
        }
        return $out;
    }

    private static function format_promos($acf_data)
    {
        if (!is_array($acf_data)) return [];
        $out = [];
        foreach ($acf_data as $row) {
            $periods = [];
            if (!empty($row['promo_periods'])) {
                foreach ($row['promo_periods'] as $p) {
                    $periods[] = ['start' => $p['date_from'], 'end' => $p['date_to']];
                }
            }
            $out[] = [
                'name'       => $row['nom_de_la_promotion'],
                'promo_type' => $row['promo_type'],
                'value'      => $row['promo_value'],
                'validUntil' => $row['promo_valid_until'],
                'periods'    => $periods
            ];
        }
        return $out;
    }
}
