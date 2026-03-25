<?php

/**
 * Composant Shortcode : Règles de la maison et Horaires [pc_regles]
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Regles_Shortcode extends PC_Shortcode_Base
{
    protected $tag = 'pc_regles';

    public function render($atts, $content = null)
    {
        $post = get_post();

        if (!$post) {
            return '';
        }

        // 1. Récupération des données natives
        $regles  = PCR_Fields::get('regles_maison', $post->ID);
        $arrivee = PCR_Fields::get('horaire_arrivee', $post->ID);
        $depart  = PCR_Fields::get('horaire_depart', $post->ID);

        // Si tout est vide, on n'affiche rien
        if (empty($regles) && empty($arrivee) && empty($depart)) {
            return '';
        }

        // 2. Affichage HTML
        ob_start(); ?>
        <div class="pc-regles-wrapper">
            <div class="pc-regles-card">

                <div class="pc-regles-texte-col">
                    <h3 class="pc-regles-title">Règles de la maison</h3>
                    <div class="pc-regles-texte">
                        <?php if (!empty($regles)): ?>
                            <?php echo wpautop(wp_kses_post($regles)); ?>
                        <?php else: ?>
                            <p>Aucune règle spécifique n'a été renseignée pour ce logement.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="pc-regles-horaires-col">
                    <div class="pc-horaire-box">
                        <div class="pc-horaire-item">
                            <span class="pc-horaire-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                                    <polyline points="10 17 15 12 10 7" />
                                    <line x1="15" y1="12" x2="3" y2="12" />
                                </svg>
                            </span>
                            <div class="pc-horaire-info">
                                <strong>Arrivée</strong>
                                <span>À partir de <?php echo esc_html($arrivee ?: '16:00'); ?></span>
                            </div>
                        </div>

                        <div class="pc-horaire-divider"></div>

                        <div class="pc-horaire-item">
                            <span class="pc-horaire-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                                    <polyline points="16 17 21 12 16 7" />
                                    <line x1="21" y1="12" x2="9" y2="12" />
                                </svg>
                            </span>
                            <div class="pc-horaire-info">
                                <strong>Départ</strong>
                                <span>Avant <?php echo esc_html($depart ?: '11:00'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
<?php return ob_get_clean();
    }

    protected function enqueue_assets()
    {
        return;
    }
}
