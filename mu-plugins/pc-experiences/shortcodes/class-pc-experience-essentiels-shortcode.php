<?php

/**
 * Composant Shortcode : Informations essentielles de l'Expérience [pc_experience_essentiels]
 * Design : Ligne épurée dans une Card ombrée (Hérité de Housing)
 *
 * @package PC_Experiences
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Experience_Essentiels_Shortcode extends PC_Experience_Shortcode_Base
{
    public function __construct()
    {
        add_shortcode('pc_experience_essentiels', [$this, 'render']);
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

        // 1. Décodeur V3 spécial pour les cases à cocher (Tableaux multiples)
        $format_array = function ($raw) {
            if (is_array($raw)) {
                // Sépare les valeurs par une virgule si l'admin a coché plusieurs cases
                $flat = array_map(function ($item) {
                    return is_array($item) ? ($item['label'] ?? $item['value'] ?? '') : $item;
                }, $raw);
                return implode(', ', array_filter($flat));
            }
            return $raw;
        };

        // Récupération sécurisée
        $capacite = (int) $format_array(PCR_Fields::get('exp_capacite', $post_id));
        $age_mini = (int) $format_array(PCR_Fields::get('exp_age_minimum', $post_id));
        $duree    = (float) $format_array(PCR_Fields::get('exp_duree', $post_id));
        $periode  = trim((string) $format_array(PCR_Fields::get('exp_periode', $post_id)));

        // 2. Rendu HTML (Affichage permanent avec valeurs par défaut)
        ob_start(); ?>

        <div class="pc-essentiels-wrapper">
            <div class="pc-essentiels-card">
                <ul class="pc-essentiels-list">

                    <li class="pc-essentiel-item">
                        <span class="pc-essentiel-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                        </span>
                        <span class="pc-essentiel-text">
                            <?php echo $capacite > 0 ? "Jusqu'à <strong>{$capacite}</strong> personne" . ($capacite > 1 ? 's' : '') : "Pas de limite de personnes"; ?>
                        </span>
                    </li>

                    <li class="pc-essentiel-item">
                        <span class="pc-essentiel-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                        </span>
                        <span class="pc-essentiel-text">
                            <?php echo $duree > 0 ? "Durée : <strong>" . str_replace('.0', '', (string)$duree) . "</strong> heure" . ($duree > 1 ? 's' : '') : "Temps libre (durée aléatoire)"; ?>
                        </span>
                    </li>

                    <li class="pc-essentiel-item">
                        <span class="pc-essentiel-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                <circle cx="9" cy="11" r="2"></circle>
                                <path d="M15 9h2"></path>
                                <path d="M15 13h2"></path>
                                <path d="M7 17c1.33-1.33 4-1.33 5.33 0"></path>
                            </svg>
                        </span>
                        <span class="pc-essentiel-text">
                            <?php echo $age_mini > 0 ? "Dès <strong>{$age_mini}</strong> an" . ($age_mini > 1 ? 's' : '') : "Pas de limite d'âge"; ?>
                        </span>
                    </li>

                    <li class="pc-essentiel-item">
                        <span class="pc-essentiel-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                        </span>
                        <span class="pc-essentiel-text">
                            <?php echo !empty($periode) ? "Ouverture : <strong>" . esc_html($periode) . "</strong>" : "Toute l'année"; ?>
                        </span>
                    </li>

                </ul>
            </div>
        </div>

        <style>
            /* On réutilise strictement la logique CSS de Housing pour un design system parfait */
            .pc-essentiels-wrapper {
                margin: 2rem 0;
                font-family: var(--pc-font-family-body, system-ui, sans-serif);
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
                color: #475569;
                font-size: 1.05rem;
            }

            .pc-essentiel-icon {
                display: flex;
                align-items: center;
                color: var(--pc-primary, #0e2b5c);
            }

            .pc-essentiel-icon svg {
                width: 24px;
                height: 24px;
            }

            .pc-essentiel-text strong {
                color: #1e293b;
                font-weight: 600;
                font-size: 1.15rem;
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

<?php echo ob_get_clean(); // V3 : echo au lieu de return car la méthode est void
    }
}
