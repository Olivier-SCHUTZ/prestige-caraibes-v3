<?php

/**
 * Composant Shortcode : Inclusions pour les Prestations / Services
 * Shortcode : [pc_experience_prestation_inclusions]
 * Design : Grille de Cards (Clone du design Inclusions)
 *
 * @package PC_Experiences
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Experience_Prestation_Inclusions_Shortcode extends PC_Experience_Shortcode_Base
{
    public function __construct()
    {
        add_shortcode('pc_experience_prestation_inclusions', [$this, 'render']);
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

        // Récupération native des champs WYSIWYG / Texte HTML
        $comprend  = trim((string) PCR_Fields::get('exp_le_service_comprend', $post_id));
        $a_prevoir = trim((string) PCR_Fields::get('exp_service_a_prevoir', $post_id));

        // Si tout est vide, on arrête le rendu
        if (empty($comprend) && empty($a_prevoir)) {
            return;
        }

        ob_start(); ?>

        <div class="pc-exp-inclusions-wrapper pc-equipements-wrapper pc-regles-wrapper">
            <div class="pc-equipements-grid">

                <?php if (!empty($comprend)) : ?>
                    <div class="pc-equip-box">
                        <div class="pc-equip-header">
                            <span class="pc-equip-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                </svg>
                            </span>
                            <h4 class="pc-equip-title">Le prix comprend</h4>
                        </div>
                        <div class="pc-equip-content pc-v3-content-raw pc-list-check">
                            <?php echo wpautop($comprend); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($a_prevoir)) : ?>
                    <div class="pc-equip-box">
                        <div class="pc-equip-header">
                            <span class="pc-equip-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="4" y="5" width="16" height="16" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="5"></line>
                                    <line x1="8" y1="2" x2="8" y2="5"></line>
                                    <line x1="4" y1="11" x2="20" y2="11"></line>
                                    <path d="M10 16h4"></path>
                                </svg>
                            </span>
                            <h4 class="pc-equip-title">À prévoir</h4>
                        </div>
                        <div class="pc-equip-content pc-v3-content-raw pc-list-standard">
                            <?php echo wpautop($a_prevoir); ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <style>
            /* On réutilise les bases de la grille Inclusions */
            .pc-exp-inclusions-wrapper {
                margin: 3rem 0;
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

            /* Contenu WYSIWYG */
            .pc-equip-content {
                line-height: 1.6;
            }

            .pc-equip-content p {
                margin-bottom: 0.75rem;
            }

            .pc-equip-content ul {
                list-style: none;
                padding: 0;
                margin: 0;
            }

            .pc-equip-content li {
                position: relative;
                padding-left: 1.75rem;
                margin-bottom: 0.75rem;
            }

            /* Puces spécifiques "Comprend" (Validation Verte) */
            .pc-list-check li::before {
                content: "✓";
                position: absolute;
                left: 0;
                top: 0;
                color: #10b981;
                font-weight: bold;
                font-size: 1.2rem;
            }

            /* Puces standards "À prévoir" (Point Bleu par défaut via pc-base.css ou défini ici) */
            .pc-list-standard li::before {
                content: "•";
                position: absolute;
                left: 0.25rem;
                top: 0;
                color: var(--pc-color-primary);
                font-weight: bold;
                font-size: 1.2rem;
            }
        </style>

<?php echo ob_get_clean();
    }
}
