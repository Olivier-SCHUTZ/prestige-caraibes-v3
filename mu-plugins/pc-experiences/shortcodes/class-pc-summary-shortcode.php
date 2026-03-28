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
    protected function render(array $atts = []): void
    {
        $post_id = $this->get_experience_id();
        if (!$post_id) return;

        // --- 1. FONCTIONS DE DÉCODAGE V3 ---

        // Décodeur pour le Répéteur JSON
        $raw_locations = PCR_Fields::get('exp_lieux_horaires_depart', $post_id);
        $locations = is_string($raw_locations) ? json_decode($raw_locations, true) : $raw_locations;
        if (!is_array($locations)) $locations = [];

        // Décodeur pour les cases à cocher / Selects multiples
        $format_choices = function ($raw) {
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE) $raw = $decoded;
            }
            if (is_array($raw)) {
                $flat = array_map(function ($item) {
                    return is_array($item) ? ($item['label'] ?? $item['value'] ?? '') : $item;
                }, $raw);
                return array_filter($flat);
            }
            return $raw ? [$raw] : [];
        };

        // --- 2. TRAITEMENT DES DONNÉES ---

        // A. Lieux de départ (Extraction des noms uniques)
        $location_names = [];
        foreach ($locations as $loc) {
            if (!empty($loc['exp_lieu_depart'])) {
                $location_names[] = trim(wp_strip_all_tags($loc['exp_lieu_depart']));
            }
        }
        $location_names = array_unique($location_names);
        if (empty($location_names)) $location_names[] = 'Non spécifié';

        // B. Horaires & Jours (Français naturel)
        $jours_arr = $format_choices(PCR_Fields::get('exp_jour', $post_id));
        $jours_lower = array_map('strtolower', $jours_arr);

        // Règle 1 : Si vide ou contient "tous", on affiche "tous les jours de la semaine"
        if (empty($jours_arr) || in_array('tous', $jours_lower) || in_array('tous les jours', $jours_lower)) {
            $jours_depart_text = 'tous les jours de la semaine';
        } else {
            // Règle 2 : On liste les jours avec une virgule, et un "et" pour le dernier (ex: "lundi, mardi et jeudi")
            $last_jour = array_pop($jours_arr);
            $jours_depart_text = empty($jours_arr) ? $last_jour : implode(', ', $jours_arr) . ' et ' . $last_jour;
        }

        $horaires_items = [];
        foreach ($locations as $loc) {
            $lieu = esc_html($loc['exp_lieu_depart'] ?? '');
            $heure_depart = !empty($loc['exp_heure_depart']) ? wp_date('H:i', strtotime($loc['exp_heure_depart'])) : '';
            $heure_retour = !empty($loc['exp_heure_retour']) ? wp_date('H:i', strtotime($loc['exp_heure_retour'])) : '';

            // Construction propre de la phrase
            $text = 'Départ <strong>' . esc_html($jours_depart_text) . '</strong>';
            if ($lieu) {
                $text .= ' de <strong>' . $lieu . '</strong>';
            }
            if ($heure_depart) {
                $text .= ' à <strong>' . esc_html($heure_depart) . '</strong>';
            }
            if ($heure_retour) {
                $text .= '. Retour prévu vers <strong>' . esc_html($heure_retour) . '</strong>.';
            } else {
                $text .= '.';
            }

            $horaires_items[] = $text;
        }
        if (empty($horaires_items)) $horaires_items[] = 'Horaires à confirmer lors de la réservation.';

        // C. Disponibilité (Période en Français naturel)
        $periode_arr = $format_choices(PCR_Fields::get('exp_periode', $post_id));
        $periode_lower = array_map('strtolower', $periode_arr);

        // Règle : Si vide ou contient "année", on formule joliment
        if (empty($periode_arr) || in_array('année', $periode_lower) || in_array("toute l'année", $periode_lower)) {
            $periode_text = "toute l'année";
        } else {
            $last_periode = array_pop($periode_arr);
            $periode_text = empty($periode_arr) ? $last_periode : implode(', ', $periode_arr) . ' et ' . $last_periode;
        }

        $titre_page = get_the_title($post_id);

        // Phrase reformulée, plus fluide et élégante, avec une note grisée et plus petite pour la "sous-réserve"
        $disponibilite_item = sprintf(
            "L'expérience <strong>%s</strong> est disponible <strong>%s</strong>.<br><em style='font-size:0.9em; color:#64748b; margin-top:6px; display:inline-block;'>(Sous réserve d'un nombre de participants suffisant et de conditions météorologiques favorables)</em>",
            esc_html($titre_page),
            esc_html($periode_text)
        );

        // --- 3. RENDU HTML (STYLE "LOGEMENT" pc-equipements) ---
        ob_start(); ?>

        <div class="pc-exp-summary-wrapper pc-equipements-wrapper pc-regles-wrapper" id="a-savoir">
            <div class="pc-equipements-grid">

                <div class="pc-equip-box">
                    <div class="pc-equip-header">
                        <span class="pc-equip-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                        </span>
                        <h4 class="pc-equip-title">Lieu de départ & retour</h4>
                    </div>
                    <ul class="pc-equip-list">
                        <?php foreach ($location_names as $lname): ?>
                            <li><?php echo esc_html($lname); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="pc-equip-box">
                    <div class="pc-equip-header">
                        <span class="pc-equip-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                        </span>
                        <h4 class="pc-equip-title">Heures de départ & retour</h4>
                    </div>
                    <ul class="pc-equip-list">
                        <?php foreach ($horaires_items as $h_item): ?>
                            <li><?php echo $h_item; // Pas de esc_html car on a mis des <strong> 
                                ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="pc-equip-box">
                    <div class="pc-equip-header">
                        <span class="pc-equip-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                                <path d="M8 14h.01"></path>
                                <path d="M12 14h.01"></path>
                                <path d="M16 14h.01"></path>
                                <path d="M8 18h.01"></path>
                                <path d="M12 18h.01"></path>
                                <path d="M16 18h.01"></path>
                            </svg>
                        </span>
                        <h4 class="pc-equip-title">Disponibilités</h4>
                    </div>
                    <ul class="pc-equip-list">
                        <li><?php echo $disponibilite_item; ?></li>
                    </ul>
                </div>

            </div>
        </div>

        <style>
            /* Injection de secours du CSS "Logement" au cas où le fichier global ne serait pas chargé ici */
            .pc-exp-summary-wrapper {
                margin: 3rem 0;
                font-family: var(--pc-font-family-body, system-ui, sans-serif);
            }

            .pc-equipements-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            @media (min-width: 768px) {
                .pc-equipements-grid {
                    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                }
            }

            .pc-equip-box {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: var(--pc-border-radius, 16px);
                padding: 1.5rem;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
                height: 100%;
            }

            .pc-equip-header {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                margin-bottom: 1rem;
            }

            .pc-equip-icon {
                color: var(--pc-primary, #0e2b5c);
                width: 24px;
                height: 24px;
                display: flex;
            }

            .pc-equip-title {
                font-size: 1.15rem;
                font-weight: 600;
                color: #1e293b;
                margin: 0;
            }

            .pc-equip-list {
                list-style: none;
                padding: 0;
                margin: 0;
            }

            .pc-equip-list li {
                position: relative;
                padding-left: 1.5rem;
                margin-bottom: 0.75rem;
                color: #475569;
                line-height: 1.6;
            }

            .pc-equip-list li::before {
                content: "•";
                position: absolute;
                left: 0;
                color: var(--pc-primary, #0e2b5c);
                font-weight: bold;
                font-size: 1.2rem;
                line-height: 1.2;
            }
        </style>

<?php echo ob_get_clean();
    }
}
