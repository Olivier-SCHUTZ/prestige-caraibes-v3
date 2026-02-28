<?php

/**
 * Shortcode : [experience_inclusions]
 * Affiche les inclusions, exclusions, recommandations et accessibilité.
 *
 * @package PC_Experiences
 */

if (!defined('ABSPATH')) {
    exit; // Sécurité
}

class PC_Experience_Inclusions_Shortcode extends PC_Experience_Shortcode_Base
{

    /**
     * Nom du shortcode.
     *
     * @var string
     */
    protected $shortcode_name = 'experience_inclusions';

    /**
     * Rendu HTML du shortcode.
     *
     * @param array $atts Attributs.
     */
    protected function render(array $atts): void
    {
        $experience_id = $this->get_experience_id();

        // Chargement conditionnel de FontAwesome si non présent
        if (!wp_style_is('font-awesome-6', 'enqueued')) {
            wp_enqueue_style('font-awesome-6', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css', [], null);
        }

        // Récupération des données ACF
        $prix_comprend = get_field('exp_prix_comprend', $experience_id);
        $prix_ne_comprend_pas = get_field('exp_prix_ne_comprend_pas', $experience_id);
        $a_prevoir_obj = get_field_object('exp_a_prevoir', $experience_id);
        $accessibilite_obj = get_field_object('exp_accessibilite', $experience_id);

        $prix_comprend_html = $prix_comprend ? wpautop($prix_comprend) : '';
        $prix_ne_comprend_pas_html = $prix_ne_comprend_pas ? wpautop($prix_ne_comprend_pas) : '';

        // Formatage "À prévoir"
        $a_prevoir_html = '';
        if (!empty($a_prevoir_obj['value']) && is_array($a_prevoir_obj['value'])) {
            $a_prevoir_html .= '<ul>';
            foreach ($a_prevoir_obj['value'] as $value) {
                $label = isset($a_prevoir_obj['choices'][$value]) ? $a_prevoir_obj['choices'][$value] : $value;
                $a_prevoir_html .= '<li>' . esc_html($label) . '</li>';
            }
            $a_prevoir_html .= '</ul>';
        }

        // Formatage "Accessibilité"
        $accessibilite_html = '';
        if (!empty($accessibilite_obj['value']) && is_array($accessibilite_obj['value'])) {
            $accessibilite_html .= '<ul>';
            foreach ($accessibilite_obj['value'] as $value) {
                $label = isset($accessibilite_obj['choices'][$value]) ? $accessibilite_obj['choices'][$value] : $value;
                $accessibilite_html .= '<li>' . esc_html($label) . '</li>';
            }
            $accessibilite_html .= '</ul>';
        }

        // Si aucune donnée à afficher, on arrête le rendu
        if (empty($prix_comprend_html) && empty($prix_ne_comprend_pas_html) && empty($a_prevoir_html) && empty($accessibilite_html)) {
            return;
        }

        // --- DÉBUT DU RENDU ---
?>
        <section class="exp-inclusions-section">
            <div class="exp-inclusions-grid">
                <?php if ($prix_comprend_html) : ?>
                    <div class="exp-inclusions-col">
                        <h3 class="exp-inclusions-title" data-icon="comprend"><i class="fas fa-check"></i> Le prix comprend</h3>
                        <div class="exp-inclusions-content"><?php echo $prix_comprend_html; ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($prix_ne_comprend_pas_html) : ?>
                    <div class="exp-inclusions-col">
                        <h3 class="exp-inclusions-title" data-icon="ne-comprend-pas"><i class="fas fa-times"></i> Le prix ne comprend pas</h3>
                        <div class="exp-inclusions-content"><?php echo $prix_ne_comprend_pas_html; ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($accessibilite_html) : ?>
                    <div class="exp-inclusions-col">
                        <h3 class="exp-inclusions-title" data-icon="accessibilite"><i class="fas fa-universal-access"></i> Accessibilité</h3>
                        <div class="exp-inclusions-content"><?php echo $accessibilite_html; ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($a_prevoir_html) : ?>
                    <div class="exp-inclusions-col">
                        <h3 class="exp-inclusions-title" data-icon="a-prevoir"><i class="fas fa-briefcase"></i> À prévoir</h3>
                        <div class="exp-inclusions-content"><?php echo $a_prevoir_html; ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
<?php
        // --- FIN DU RENDU ---
    }
}
