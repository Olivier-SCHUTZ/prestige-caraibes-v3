<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Contrôleur des formulaires Elementor
 * - Logements (fiche logement)
 * - Expériences (fiche expérience)
 *
 * Il suppose :
 *  - que les JS ajoutent bien les champs (total, lines_json, etc.)
 *  - que PCR_Reservation::create() est dispo
 */
class PCR_FormController
{
    public static function init()
    {
        // On ne fait rien si Elementor Pro n'est pas là
        if (!did_action('elementor_pro/init')) {
            return;
        }

        // Accroche sur la création d'un nouveau "record" Elementor
        add_action('elementor_pro/forms/new_record', [__CLASS__, 'handle_elementor_form'], 20, 2);
    }

    /**
     * Point d'entrée : chaque envoi de formulaire Elementor arrive ici
     */
    public static function handle_elementor_form($record, $handler)
    {
        // Sécurité de base
        if (empty($_POST)) {
            return;
        }

        $raw  = $record->get_formatted_data(); // Données du form Elementor (labels)
        $post = $_POST;                        // Données brutes + ajouts JS

        // Détection LOGEMENT / EXPERIENCE
        $is_logement   = isset($post['arrival']) && isset($post['departure']);
        $is_experience = isset($post['devis_type']) || isset($post['lines_json']);

        if (!$is_logement && !$is_experience) {
            // On ne touche pas aux autres formulaires du site
            return;
        }

        if ($is_logement) {
            self::handle_logement($raw, $post);
        }

        if ($is_experience) {
            self::handle_experience($raw, $post);
        }
    }

    /**
     * Traitement des demandes LOGEMENT
     */
    private static function handle_logement(array $raw, array $post)
    {
        if (!class_exists('PCR_Booking_Engine')) {
            return;
        }

        $item_id = get_the_ID() ?: 0;

        $lines_json = isset($post['lines_json']) ? wp_kses_post(wp_unslash($post['lines_json'])) : '';
        $type_flux  = (!empty($post['type_flux']) && $post['type_flux'] === 'devis') ? 'devis' : 'reservation';

        $payload = [
            'context' => [
                'type'             => 'location',
                'origine'          => 'site',
                'mode_reservation' => 'demande',
                'type_flux'        => $type_flux,
                'source'           => 'form_elementor',
            ],
            'item' => [
                'item_id'     => $item_id,
                'date_arrivee'=> sanitize_text_field($post['arrival'] ?? ''),
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
                'raw_lines_json' => $lines_json,
                'is_sur_devis'   => !empty($post['is_sur_devis']),
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
     * Traitement des demandes EXPERIENCE
     */
    private static function handle_experience(array $raw, array $post)
    {
        if (!class_exists('PCR_Booking_Engine')) {
            return;
        }

        $item_id = get_the_ID() ?: 0;

        $lines_json = isset($post['lines_json']) ? wp_kses_post(wp_unslash($post['lines_json'])) : '';
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
                'raw_lines_json' => $lines_json,
                'is_sur_devis'   => !empty($post['is_sur_devis']),
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
}
