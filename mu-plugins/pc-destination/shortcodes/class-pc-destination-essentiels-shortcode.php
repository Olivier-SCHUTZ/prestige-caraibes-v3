<?php

/**
 * Composant Shortcode : Informations essentielles de la Destination [pc_destination_essentiels]
 * Design : Ligne épurée dans une Card ombrée (Connecté à pc-base.css)
 *
 * @package PC_Destination
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Destination_Essentiels_Shortcode
{
    public function __construct()
    {
        add_shortcode('pc_destination_essentiels', [$this, 'render']);
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

        // Récupération sécurisée des données (Hybride : on tente PCR_Fields ou get_field par sécurité)
        $population = class_exists('PCR_Fields') ? PCR_Fields::get('dest_population', $post_id) : get_field('dest_population', $post_id);
        $surface    = class_exists('PCR_Fields') ? PCR_Fields::get('dest_surface_km2', $post_id) : get_field('dest_surface_km2', $post_id);
        $aeroport   = class_exists('PCR_Fields') ? PCR_Fields::get('dest_airport_distance_km', $post_id) : get_field('dest_airport_distance_km', $post_id);
        $sea_side   = class_exists('PCR_Fields') ? PCR_Fields::get('dest_sea_side', $post_id) : get_field('dest_sea_side', $post_id);

        // Formatage de la côte
        $sea_side_label = 'Non défini';
        if ($sea_side === 'caraibes') $sea_side_label = 'Mer des Caraïbes';
        if ($sea_side === 'atlantique') $sea_side_label = 'Océan Atlantique';

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
                            <?php echo !empty($population) ? "<strong>" . number_format_i18n((int)$population) . "</strong> Habitants" : "Population N/D"; ?>
                        </span>
                    </li>

                    <li class="pc-essentiel-item">
                        <span class="pc-essentiel-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <path d="M3 9h18"></path>
                                <path d="M9 21V9"></path>
                            </svg>
                        </span>
                        <span class="pc-essentiel-text">
                            <?php echo !empty($surface) ? "Superficie : <strong>" . str_replace('.0', '', (string)$surface) . "</strong> km²" : "Superficie N/D"; ?>
                        </span>
                    </li>

                    <li class="pc-essentiel-item">
                        <span class="pc-essentiel-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21.5 4c0 0-2 .5-3.5 2L14.5 9.5 6.3 7.7 4.5 8.5l6.6 4.4-4.8 4.8-2.9-.7-1.4 1.4 4.3 1.5 1.5 4.3 1.4-1.4-.7-2.9 4.8-4.8 4.4 6.6.8-1.8z"></path>
                            </svg>
                        </span>
                        <span class="pc-essentiel-text">
                            <?php echo !empty($aeroport) ? "Aéroport à <strong>" . str_replace('.0', '', (string)$aeroport) . "</strong> km" : "Aéroport N/D"; ?>
                        </span>
                    </li>

                    <li class="pc-essentiel-item">
                        <span class="pc-essentiel-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12c2 0 3-3 5-3s3 3 5 3 3-3 5-3 3 3 5 3"></path>
                                <path d="M2 18c2 0 3-3 5-3s3 3 5 3 3-3 5-3 3 3 5 3"></path>
                            </svg>
                        </span>
                        <span class="pc-essentiel-text">
                            <?php echo !empty($sea_side) ? "Côte : <strong>" . esc_html($sea_side_label) . "</strong>" : "Côte N/D"; ?>
                        </span>
                    </li>

                </ul>
            </div>
        </div>

        <style>
            /* Design raccordé à 100% sur pc-base.css */
            .pc-essentiels-wrapper {
                margin: 2rem 0;
                font-family: var(--pc-font-body, system-ui, sans-serif);
            }

            .pc-essentiels-card {
                background: #ffffff;
                border-radius: var(--pc-radius, 10px);
                box-shadow: var(--pc-shadow-soft, 0 8px 24px rgba(0, 0, 0, 0.08));
                border: 1px solid rgba(0, 0, 0, 0.05);
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
                color: var(--pc-color-text, #3a3a3a);
                font-size: var(--pc-text-size, 1.125rem);
                line-height: var(--pc-text-lh, 1.6);
            }

            .pc-essentiel-icon {
                display: flex;
                align-items: center;
                color: var(--pc-color-primary, #007a92);
            }

            .pc-essentiel-icon svg {
                width: 24px;
                height: 24px;
            }

            .pc-essentiel-text strong {
                color: var(--pc-color-heading, #1b3b5f);
                font-weight: 600;
                font-family: var(--pc-font-heading, inherit);
            }

            @media (min-width: 768px) {
                .pc-essentiel-item:not(:last-child)::after {
                    content: "·";
                    margin-left: 2rem;
                    color: var(--pc-color-muted, #6f6f6f);
                    font-size: 1.5rem;
                    line-height: 1;
                    opacity: 0.5;
                }
            }
        </style>

<?php echo ob_get_clean();
    }
}
