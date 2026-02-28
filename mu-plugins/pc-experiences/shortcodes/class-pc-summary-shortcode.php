<?php

/**
 * Shortcode : [experience_summary]
 * Affiche le résumé de l'expérience (Lieux, Horaires, Disponibilités).
 *
 * @package PC_Experiences
 */

if (!defined('ABSPATH')) {
    exit; // Sécurité
}

class PC_Experience_Summary_Shortcode extends PC_Experience_Shortcode_Base
{

    /**
     * Nom du shortcode.
     *
     * @var string
     */
    protected $shortcode_name = 'experience_summary';

    /**
     * Rendu HTML du shortcode.
     *
     * @param array $atts Attributs.
     */
    protected function render(array $atts): void
    {
        $experience_id = $this->get_experience_id();

        // 1. Gestion des lieux de départ
        $locations_repeater = get_field('exp_lieux_horaires_depart', $experience_id);
        $location_names = [];
        if (is_array($locations_repeater)) {
            $location_names = wp_list_pluck($locations_repeater, 'exp_lieu_depart');
        }
        $locations_text = !empty($location_names) ? implode(', ', $location_names) : 'Non spécifié';

        // 2. Gestion des jours
        $jours_field_object = get_field_object('exp_jour', $experience_id);
        $jours_values = $jours_field_object['value'] ?? [];
        $jours_choices = $jours_field_object['choices'] ?? [];
        $jours_labels = [];

        if (is_array($jours_values)) {
            foreach ($jours_values as $value) {
                if (isset($jours_choices[$value])) {
                    $jours_labels[] = $jours_choices[$value];
                }
            }
        }
        $jours_depart_text = !empty($jours_labels) ? implode(', ', $jours_labels) : 'jours non spécifiés';

        // 3. Gestion des horaires (HTML)
        $horaires_html = '';
        if (is_array($locations_repeater)) {
            foreach ($locations_repeater as $location) {
                $lieu = esc_html($location['exp_lieu_depart'] ?? '');
                $heure_depart = !empty($location['exp_heure_depart']) ? wp_date('H:i', strtotime($location['exp_heure_depart'])) : '';
                $heure_retour = !empty($location['exp_heure_retour']) ? wp_date('H:i', strtotime($location['exp_heure_retour'])) : '';

                $horaires_html .= '<p>';
                $horaires_html .= 'Départ ' . esc_html($jours_depart_text) . ' de ' . $lieu;
                if ($heure_depart) {
                    $horaires_html .= ' à ' . esc_html($heure_depart);
                }
                $horaires_html .= '<br>';
                if ($heure_retour) {
                    $horaires_html .= 'Retour vers ' . esc_html($heure_retour);
                }
                $horaires_html .= '</p>';
            }
        }

        // 4. Gestion de la période et de la disponibilité
        $titre_page = get_the_title($experience_id);
        $periode_field_object = get_field_object('exp_periode', $experience_id);
        $periode_values = $periode_field_object['value'] ?? [];
        $periode_choices = $periode_field_object['choices'] ?? [];
        $periode_labels = [];

        if (is_array($periode_values)) {
            foreach ($periode_values as $value) {
                if (isset($periode_choices[$value])) {
                    $periode_labels[] = $periode_choices[$value];
                }
            }
        }
        $periode_text = !empty($periode_labels) ? implode(' et ', $periode_labels) : 'période non spécifiée';
        $disponibilite_text = 'Sortie ' . esc_html($titre_page) . ' disponible ' . esc_html($periode_text) . ' (sous réserve d’un nombre de personnes suffisants et des conditions météorologiques favorables)';

        // --- DÉBUT DU RENDU ---
?>
        <section class="exp-summary">
            <div class="exp-summary-row">
                <div class="exp-summary-label">Lieu de départ & de retour</div>
                <div class="exp-summary-data">
                    <ul>
                        <li><?php echo esc_html($locations_text); ?></li>
                    </ul>
                </div>
            </div>
            <div class="exp-summary-row">
                <div class="exp-summary-label">Heure de départ et retour</div>
                <div class="exp-summary-data"><?php echo $horaires_html; ?></div>
            </div>
            <div class="exp-summary-row">
                <div class="exp-summary-label">Disponibilité</div>
                <div class="exp-summary-data"><?php echo $disponibilite_text; ?></div>
            </div>
        </section>
<?php
        // --- FIN DU RENDU ---
    }
}
