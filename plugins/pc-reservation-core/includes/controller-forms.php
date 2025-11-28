<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Contrôleur des formulaires Elementor (Version Finale & Propre)
 * Gère les formulaires natifs Elementor pour Logements et Expériences.
 */
class PCR_FormController
{
    public static function init()
    {
        // Pas de condition stricte ici : on écoute simplement.
        // Si Elementor n'est pas là, l'action ne se déclenchera jamais, donc aucun risque d'erreur.
        add_action('elementor_pro/forms/new_record', [__CLASS__, 'handle_elementor_form'], 20, 2);
    }

    /**
     * Point d'entrée
     */
    public static function handle_elementor_form($record, $handler)
    {
        if (empty($_POST)) {
            return;
        }

        $raw  = $record->get_formatted_data();
        $post = $_POST;

        // Détection intelligente du type de formulaire
        $is_logement   = isset($post['arrival']) && isset($post['departure']);
        $is_experience = isset($post['devis_type']) || isset($post['lines_json']);

        if ($is_logement) {
            self::handle_logement($record, $raw, $post);
        } elseif ($is_experience) {
            self::handle_experience($raw, $post);
        }
    }

    /**
     * Traitement LOGEMENT
     */
    private static function handle_logement($record, array $raw, array $post)
    {
        if (!class_exists('PCR_Booking_Engine')) {
            return;
        }

        // 1. Récupération ROBUSTE de l'ID (Priorité à la page affichée)
        $item_id = 0;

        // A. Via le champ caché queried_id (souvent présent dans les popups)
        if (!empty($post['queried_id'])) {
            $item_id = (int) $post['queried_id'];
        }
        // B. Via le champ post_id
        elseif (!empty($post['post_id'])) {
            $item_id = (int) $post['post_id'];
        }
        // C. Via l'objet Elementor (ID du template ou de la page)
        elseif (method_exists($record, 'get_main_post_id')) {
            $item_id = (int) $record->get_main_post_id();
        }
        // D. Fallback ultime
        else {
            $item_id = get_the_ID() ?: 0;
        }

        $pricing_lines = self::prepare_pricing_lines($post);
        $type_flux  = (!empty($post['type_flux']) && $post['type_flux'] === 'devis') ? 'devis' : 'reservation';

        // 2. Détection du mode via ACF
        $mode_reservation = 'demande'; // Par défaut

        if ($item_id > 0 && function_exists('get_field')) {
            $setting = get_field('mode_reservation', $item_id);

            // Gestion format tableau/string
            if (is_array($setting)) {
                $setting = $setting['value'] ?? ($setting[0] ?? '');
            }
            $setting = (string) $setting;

            if ($setting === 'log_directe' || $setting === 'log_direct') {
                $mode_reservation = 'directe';
            } elseif ($setting === 'log_demande') {
                $mode_reservation = 'demande';
            } elseif ($setting === 'log_channel') {
                $mode_reservation = 'demande';
            }
        }

        $payload = [
            'context' => [
                'type'             => 'location',
                'origine'          => 'site',
                'mode_reservation' => $mode_reservation,
                'type_flux'        => $type_flux,
                'source'           => 'form_elementor',
            ],
            'item' => [
                'item_id'     => $item_id,
                'date_arrivee' => sanitize_text_field($post['arrival'] ?? ''),
                'date_depart' => sanitize_text_field($post['departure'] ?? ''),
            ],
            'people' => [
                'adultes' => isset($post['adults']) ? (int) $post['adults'] : 0,
                'enfants' => isset($post['children']) ? (int) $post['children'] : 0,
                'bebes'   => isset($post['infants']) ? (int) $post['infants'] : 0,
            ],
            'pricing' => [
                'currency'       => 'EUR',
                'total'          => isset($post['total']) ? (float) $post['total'] : 0,
                'lines'          => $pricing_lines['lines'],
                'raw_lines_json' => $pricing_lines['raw_lines_json'],
                'lines_json'     => $pricing_lines['raw_lines_json'],
                'is_sur_devis'   => !empty($post['is_sur_devis']),
                'manual_adjustments' => [],
            ],
            'customer' => [
                'prenom'             => isset($raw['prenom']) ? sanitize_text_field($raw['prenom']) : '',
                'nom'                => isset($raw['nom']) ? sanitize_text_field($raw['nom']) : '',
                'email'              => isset($raw['email']) ? sanitize_email($raw['email']) : '',
                'telephone'          => isset($raw['telephone']) ? sanitize_text_field($raw['telephone']) : '',
                'langue'             => 'fr',
                'commentaire_client' => isset($raw['message']) ? sanitize_textarea_field($raw['message']) : '',
            ],
        ];

        PCR_Booking_Engine::create($payload);
    }

    /**
     * Traitement EXPERIENCE
     */
    private static function handle_experience(array $raw, array $post)
    {
        if (!class_exists('PCR_Booking_Engine')) {
            return;
        }

        $item_id = 0;
        if (!empty($post['post_id'])) {
            $item_id = (int) $post['post_id'];
        } else {
            $item_id = get_the_ID() ?: 0;
        }

        $pricing_lines = self::prepare_pricing_lines($post);
        $type_flux  = (!empty($post['type_flux']) && $post['type_flux'] === 'devis') ? 'devis' : 'reservation';

        $payload = [
            'context' => [
                'type'             => 'experience',
                'origine'          => 'site',
                'mode_reservation' => 'demande',
                'type_flux'        => $type_flux,
                'source'           => 'form_elementor',
            ],
            'item' => [
                'item_id'               => $item_id,
                'experience_tarif_type' => sanitize_text_field($post['devis_type'] ?? ''),
                'date_experience'       => sanitize_text_field($post['date_experience'] ?? ''),
            ],
            'people' => [
                'adultes' => isset($post['devis_adults']) ? (int) $post['devis_adults'] : 0,
                'enfants' => isset($post['devis_children']) ? (int) $post['devis_children'] : 0,
                'bebes'   => isset($post['devis_bebes']) ? (int) $post['devis_bebes'] : 0,
            ],
            'pricing' => [
                'currency'       => 'EUR',
                'total'          => isset($post['total']) ? (float) $post['total'] : 0,
                'lines'          => $pricing_lines['lines'],
                'raw_lines_json' => $pricing_lines['raw_lines_json'],
                'lines_json'     => $pricing_lines['raw_lines_json'],
                'is_sur_devis'   => !empty($post['is_sur_devis']),
                'manual_adjustments' => [],
            ],
            'customer' => [
                'prenom'             => isset($raw['prenom']) ? sanitize_text_field($raw['prenom']) : '',
                'nom'                => isset($raw['nom']) ? sanitize_text_field($raw['nom']) : '',
                'email'              => isset($raw['email']) ? sanitize_email($raw['email']) : '',
                'telephone'          => isset($raw['telephone']) ? sanitize_text_field($raw['telephone']) : '',
                'langue'             => 'fr',
                'commentaire_client' => isset($raw['message']) ? sanitize_textarea_field($raw['message']) : '',
            ],
        ];

        PCR_Booking_Engine::create($payload);
    }

    private static function prepare_pricing_lines(array $post)
    {
        $raw_json      = isset($post['lines_json']) ? wp_unslash($post['lines_json']) : '';
        $sanitized_raw = $raw_json !== '' ? wp_kses_post($raw_json) : '';
        $lines         = [];

        if ($raw_json !== '') {
            $decoded = json_decode($raw_json, true);
            if (!is_array($decoded) && $sanitized_raw !== '' && $sanitized_raw !== $raw_json) {
                $decoded = json_decode($sanitized_raw, true);
            }
            if (is_array($decoded)) {
                foreach ($decoded as $line) {
                    $normalized_line = self::normalize_pricing_line($line);
                    if (!empty($normalized_line)) {
                        $lines[] = $normalized_line;
                    }
                }
            }
        }

        if (!empty($lines)) {
            $encoded = wp_json_encode($lines);
            if ($encoded !== false) {
                $sanitized_raw = $encoded;
            }
        }

        return [
            'lines'          => $lines,
            'raw_lines_json' => $sanitized_raw,
        ];
    }

    private static function normalize_pricing_line($line)
    {
        if (!is_array($line)) {
            return [];
        }
        $normalized = [];
        foreach ($line as $key => $value) {
            if (is_string($value)) {
                $normalized[$key] = wp_kses_post($value);
                continue;
            }
            if (is_int($value) || is_float($value)) {
                $normalized[$key] = $value;
                continue;
            }
            if (is_bool($value)) {
                $normalized[$key] = $value;
                continue;
            }
            if (is_numeric($value)) {
                $normalized[$key] = (float) $value;
                continue;
            }
        }
        return $normalized;
    }
}
