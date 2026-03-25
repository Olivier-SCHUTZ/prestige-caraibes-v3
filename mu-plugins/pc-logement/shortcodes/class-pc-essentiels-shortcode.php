<?php

/**
 * Composant Shortcode : Informations essentielles du logement [pc_essentiels]
 * Design : Ligne épurée dans une Card ombrée
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Essentiels_Shortcode
{
    /**
     * Enregistrement du shortcode.
     */
    public function register()
    {
        add_shortcode('pc_essentiels', [$this, 'render']);
    }

    /**
     * Rendu HTML du shortcode.
     */
    public function render($atts)
    {
        // RÈGLE D'OR : Abstraction V3
        if (!class_exists('PCR_Fields')) {
            return '';
        }

        $post_id = get_the_ID();
        if (!$post_id) {
            return '';
        }

        // 1. Récupération des données brutes natives
        $capacite = (int) PCR_Fields::get('capacite', $post_id);
        $chambres = (int) PCR_Fields::get('nombre_de_chambres', $post_id);
        $lits     = (int) PCR_Fields::get('nombre_lits', $post_id);
        $sdb      = (float) PCR_Fields::get('nombre_sdb', $post_id);
        $surface  = (int) PCR_Fields::get('superficie', $post_id);

        // Si absolument tout est vide, on n'affiche rien
        if (!$capacite && !$chambres && !$lits && !$sdb && !$surface) {
            return '';
        }

        // 2. Rendu HTML
        ob_start(); ?>

        <div class="pc-essentiels-wrapper">
            <div class="pc-essentiels-card">
                <ul class="pc-essentiels-list">

                    <?php if ($capacite > 0): ?>
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
                                <strong><?php echo $capacite; ?></strong> voyageur<?php echo $capacite > 1 ? 's' : ''; ?>
                            </span>
                        </li>
                    <?php endif; ?>

                    <?php if ($chambres > 0): ?>
                        <li class="pc-essentiel-item">
                            <span class="pc-essentiel-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16h12V4a2 2 0 0 0-2-2z"></path>
                                    <path d="M14 9h.01"></path>
                                </svg>
                            </span>
                            <span class="pc-essentiel-text">
                                <strong><?php echo $chambres; ?></strong> chambre<?php echo $chambres > 1 ? 's' : ''; ?>
                            </span>
                        </li>
                    <?php endif; ?>

                    <?php if ($lits > 0): ?>
                        <li class="pc-essentiel-item">
                            <span class="pc-essentiel-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M2 4v16"></path>
                                    <path d="M2 8h18a2 2 0 0 1 2 2v10"></path>
                                    <path d="M2 17h20"></path>
                                    <path d="M6 8v9"></path>
                                </svg>
                            </span>
                            <span class="pc-essentiel-text">
                                <strong><?php echo $lits; ?></strong> lit<?php echo $lits > 1 ? 's' : ''; ?>
                            </span>
                        </li>
                    <?php endif; ?>

                    <?php if ($sdb > 0): ?>
                        <li class="pc-essentiel-item">
                            <span class="pc-essentiel-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M2 12h20"></path>
                                    <path d="M4 12v4a6 6 0 0 0 12 0v-4"></path>
                                    <path d="M10 12V4a2 2 0 0 1 2-2h4"></path>
                                </svg>
                            </span>
                            <span class="pc-essentiel-text">
                                <strong><?php echo str_replace('.0', '', (string)$sdb); ?></strong> salle<?php echo $sdb > 1 ? 's' : ''; ?> de bain
                            </span>
                        </li>
                    <?php endif; ?>

                    <?php if ($surface > 0): ?>
                        <li class="pc-essentiel-item">
                            <span class="pc-essentiel-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path>
                                </svg>
                            </span>
                            <span class="pc-essentiel-text">
                                <strong><?php echo $surface; ?></strong> m²
                            </span>
                        </li>
                    <?php endif; ?>

                </ul>
            </div>
        </div>

        <style>
            .pc-essentiels-wrapper {
                margin: 2rem 0;
                font-family: var(--pc-font-family-body, system-ui, sans-serif);
            }

            /* La Card Globale */
            .pc-essentiels-card {
                background: #ffffff;
                border-radius: var(--pc-border-radius, 16px);
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
                border: 1px solid #e2e8f0;
                padding: 1.5rem 2rem;
                /* Espacement intérieur agréable */
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
                /* Le chiffre est très légèrement plus grand */
            }

            /* Séparateurs sur Desktop uniquement */
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

<?php return ob_get_clean();
    }
}
