<?php

/**
 * Composant Shortcode : Informations essentielles pour les Prestations / Services
 * Shortcode : [pc_experience_prestation_essentiels]
 * Design : Ligne épurée dans une Card ombrée (Clone du design Essentiels)
 *
 * @package PC_Experiences
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Experience_Prestation_Essentiels_Shortcode extends PC_Experience_Shortcode_Base
{
    public function __construct()
    {
        add_shortcode('pc_experience_prestation_essentiels', [$this, 'render']);
    }

    /**
     * Rendu HTML du shortcode.
     */
    public function render(array $atts = []): void
    {
        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }

        // 1. Décodeur V3 spécial pour extraire proprement les chaînes ou tableaux
        $format_array = function ($raw) {
            if (is_array($raw)) {
                $flat = array_map(function ($item) {
                    return is_array($item) ? ($item['label'] ?? $item['value'] ?? '') : $item;
                }, $raw);
                return implode(', ', array_filter($flat));
            }
            return $raw;
        };

        // Récupération sécurisée des nouveaux champs
        $type_presta  = trim((string) $format_array(PCR_Fields::get('exp_type_de_prestation', $post_id)));
        $zone         = trim((string) $format_array(PCR_Fields::get('exp_zone_intervention', $post_id)));
        $delai        = trim((string) $format_array(PCR_Fields::get('exp_delai_de_reservation', $post_id)));
        $heure_limite = trim((string) $format_array(PCR_Fields::get('exp_heure_limite_de_commande', $post_id)));

        // 2. Rendu HTML avec fallbacks élégants
        ob_start(); ?>

        <div class="pc-essentiels-wrapper">
            <div class="pc-essentiels-card">
                <ul class="pc-essentiels-list">

                    <li class="pc-essentiel-item">
                        <span class="pc-essentiel-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                            </svg>
                        </span>
                        <span class="pc-essentiel-text">
                            <?php echo !empty($type_presta) ? "Prestation : <strong>" . esc_html($type_presta) . "</strong>" : "Prestation <strong>sur mesure</strong>"; ?>
                        </span>
                    </li>

                    <li class="pc-essentiel-item">
                        <span class="pc-essentiel-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                        </span>
                        <span class="pc-essentiel-text">
                            <?php echo !empty($zone) ? "Zone : <strong>" . esc_html($zone) . "</strong>" : "Intervention sur <strong>toute l'île</strong>"; ?>
                        </span>
                    </li>

                    <li class="pc-essentiel-item">
                        <span class="pc-essentiel-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21.5 2v6h-19V2h19zM21.5 16v6h-19v-6h19zM2.5 8l6 4-6 4M21.5 8l-6 4 6 4"></path>
                            </svg>
                        </span>
                        <span class="pc-essentiel-text">
                            <?php echo !empty($delai) ? "Réservation : <strong>" . esc_html($delai) . "</strong> à l'avance" : "Réservation <strong>flexible</strong>"; ?>
                        </span>
                    </li>

                    <li class="pc-essentiel-item">
                        <span class="pc-essentiel-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="13" r="8"></circle>
                                <polyline points="12 9 12 13 14 15"></polyline>
                                <path d="M5 3L2 6"></path>
                                <path d="M19 3l3 3"></path>
                            </svg>
                        </span>
                        <span class="pc-essentiel-text">
                            <?php echo !empty($heure_limite) ? "Commande limite : <strong>" . esc_html($heure_limite) . "</strong>" : "<strong>Pas d'heure limite</strong> de commande"; ?>
                        </span>
                    </li>

                </ul>
            </div>
        </div>

        <style>
            /* On embarque les mêmes classes pour profiter des tokens V3 du pc-base.css */
            .pc-essentiels-wrapper {
                margin: 2rem 0;
            }

            .pc-essentiels-card {
                background: #ffffff;
                border-radius: var(--pc-border-radius, 16px);
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
                border: 1px solid #e2e8f0;
                padding: 1.5rem 2rem;
            }

            .pc-essentiels-list {
                display: flex;
                flex-wrap: wrap;
                gap: 1.5rem 2rem;
                list-style: none;
                margin: 0;
                padding: 0;
                align-items: center;
                justify-content: flex-start;
            }

            .pc-essentiel-item {
                display: flex;
                align-items: center;
                gap: 0.75rem;
            }

            .pc-essentiel-icon {
                display: flex;
                align-items: center;
            }

            .pc-essentiel-icon svg {
                width: 24px;
                height: 24px;
            }

            @media (min-width: 768px) {
                .pc-essentiel-item:not(:last-child)::after {
                    content: "·";
                    margin-left: 2rem;
                    color: #cbd5e1;
                    font-size: 1.5rem;
                    line-height: 1;
                }
            }
        </style>

<?php echo ob_get_clean();
    }
}
