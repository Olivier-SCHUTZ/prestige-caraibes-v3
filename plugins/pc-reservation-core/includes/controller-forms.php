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
        if (!class_exists('PCR_Reservation')) {
            return;
        }

        $item_id = get_the_ID() ?: 0;

        $data = [
            // Identification
            'type'               => 'location',
            'item_id'            => $item_id,
            'mode_reservation'   => 'demande',
            'origine'            => 'site',

            // Dates
            'date_arrivee'       => sanitize_text_field($post['arrival'] ?? ''),
            'date_depart'        => sanitize_text_field($post['departure'] ?? ''),

            // Personnes (version EN envoyée par le JS)
            'adultes'            => isset($post['adults']) ? (int) $post['adults'] : 0,
            'enfants'            => isset($post['children']) ? (int) $post['children'] : 0,
            'bebes'              => isset($post['infants']) ? (int) $post['infants'] : 0,

            // Tarif (sera mieux alimenté une fois les JS mis à jour)
            'montant_total'      => isset($post['total']) ? (float) $post['total'] : 0,
            'detail_tarif'       => isset($post['lines_json']) ? wp_kses_post($post['lines_json']) : null,
            'devise'             => 'EUR',

            // Client (labels Elementor -> $raw)
            'prenom'             => isset($raw['prenom']) ? sanitize_text_field($raw['prenom']) : '',
            'nom'                => isset($raw['nom']) ? sanitize_text_field($raw['nom']) : '',
            'email'              => isset($raw['email']) ? sanitize_email($raw['email']) : '',
            'telephone'          => isset($raw['telephone']) ? sanitize_text_field($raw['telephone']) : '',
            'langue'             => 'fr',

            // Statuts initiaux
            'statut_reservation' => 'en_attente',
            'statut_paiement'    => 'non_demande',

            // Caution (à enrichir plus tard)
            'caution_montant'    => 0,
            'caution_mode'       => 'aucune',
            'caution_statut'     => 'non_demande',

            // Système
            'date_creation'      => current_time('mysql'),
            'date_maj'           => current_time('mysql'),
        ];

        PCR_Reservation::create($data);
    }

    /**
     * Traitement des demandes EXPERIENCE
     */
    private static function handle_experience(array $raw, array $post)
    {
        if (!class_exists('PCR_Reservation')) {
            return;
        }

        $item_id = get_the_ID() ?: 0;

        $data = [
            // Identification
            'type'               => 'experience',
            'item_id'            => $item_id,
            'mode_reservation'   => 'demande',
            'origine'            => 'site',

            // Type tarifaire (grille choisie)
            'experience_tarif_type' => sanitize_text_field($post['devis_type'] ?? ''),

            // Date expérience (quand tu rajouteras le champ dans le form)
            'date_experience'    => sanitize_text_field($post['date_experience'] ?? ''),

            // Personnes (version FR côté expérience)
            'adultes'            => isset($post['devis_adults']) ? (int) $post['devis_adults'] : 0,
            'enfants'            => isset($post['devis_children']) ? (int) $post['devis_children'] : 0,
            'bebes'              => isset($post['devis_bebes']) ? (int) $post['devis_bebes'] : 0,

            // Tarif simulation
            'montant_total'      => isset($post['total']) ? (float) $post['total'] : 0,
            'detail_tarif'       => isset($post['lines_json']) ? wp_kses_post($post['lines_json']) : null,
            'devise'             => 'EUR',

            // Client
            'prenom'             => isset($raw['prenom']) ? sanitize_text_field($raw['prenom']) : '',
            'nom'                => isset($raw['nom']) ? sanitize_text_field($raw['nom']) : '',
            'email'              => isset($raw['email']) ? sanitize_email($raw['email']) : '',
            'telephone'          => isset($raw['telephone']) ? sanitize_text_field($raw['telephone']) : '',
            'langue'             => 'fr',

            // Statuts initiaux
            'statut_reservation' => 'en_attente',
            'statut_paiement'    => 'non_demande',

            // Caution (pas pour les expériences pour l’instant)
            'caution_montant'    => 0,
            'caution_mode'       => 'aucune',
            'caution_statut'     => 'non_demande',

            // Système
            'date_creation'      => current_time('mysql'),
            'date_maj'           => current_time('mysql'),
        ];

        PCR_Reservation::create($data);
    }
}
