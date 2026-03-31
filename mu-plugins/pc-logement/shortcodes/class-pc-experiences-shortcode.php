<?php

/**
 * Composant Shortcode : Expériences recommandées [logement_experiences_recommandees]
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Experiences_Shortcode extends PC_Shortcode_Base
{

    protected $tag = 'logement_experiences_recommandees';

    /**
     * Rendu principal du shortcode
     */
    public function render($atts, $content = null)
    {
        if (!is_singular()) {
            return '';
        }

        $post_id = get_the_ID();
        if (!$post_id) {
            return '';
        }

        // 1. Récupération des IDs recommandés via ACF
        $recommended_ids = PCR_Fields::get('logement_experiences_recommandees', $post_id);
        if (empty($recommended_ids)) {
            return '';
        }

        // 2. Requête WordPress
        $args = [
            'post_type'      => 'experience',
            'post__in'       => $recommended_ids,
            'orderby'        => 'post__in',
            'posts_per_page' => 3,
        ];

        $query = new WP_Query($args);

        if (!$query->have_posts()) {
            return '';
        }

        // 3. Affichage HTML
        ob_start(); ?>
        <section class="exp-reco-section">
            <h2 class="exp-reco-title">Excursions recommandées à proximité</h2>
            <div class="exp-reco-grid">
                <?php while ($query->have_posts()) : $query->the_post(); ?>
                    <?php $this->render_experience_card(get_the_ID()); ?>
                <?php endwhile; ?>
            </div>
        </section>
    <?php
        wp_reset_postdata(); // Restaure le contexte de la page principale
        return ob_get_clean();
    }

    /**
     * Pas d'assets externes pour ce composant
     */
    protected function enqueue_assets()
    {
        return;
    }

    /**
     * Helper : Affiche une carte d'expérience unique
     */
    private function render_experience_card($exp_id)
    {
        $price = $this->get_experience_price($exp_id);
    ?>
        <div class="exp-reco-card">
            <a href="<?php echo esc_url(get_permalink($exp_id)); ?>" class="exp-reco-card-link">
                <?php if (has_post_thumbnail($exp_id)) : ?>
                    <div class="exp-reco-card-image">
                        <?php echo get_the_post_thumbnail($exp_id, 'medium_large'); ?>
                    </div>
                <?php endif; ?>

                <div class="exp-reco-card-content">
                    <h3 class="exp-reco-card-title"><?php echo esc_html(get_the_title($exp_id)); ?></h3>
                    <?php if ($price > 0) : ?>
                        <div class="exp-reco-card-price">
                            À partir de <?php echo esc_html(number_format_i18n($price, 0)); ?>€ / personne
                        </div>
                    <?php endif; ?>
                </div>
            </a>
        </div>
<?php
    }

    /**
     * Helper : Extrait le prix "à partir de" depuis le répéteur des tarifs
     */
    private function get_experience_price($exp_id)
    {
        $price = 0;

        // 1. Protection vitale : On utilise le système natif PC-Reservation-Core
        if (!class_exists('PCR_Fields')) {
            return $price;
        }

        $pricing_tiers = PCR_Fields::get('exp_types_de_tarifs', $exp_id);

        if (is_array($pricing_tiers) && !empty($pricing_tiers)) {
            $first_tier = $pricing_tiers[0]; // On prend le premier palier

            // 2. Protection PHP 8 : On s'assure que c'est bien un tableau avant de chercher dedans
            if (is_array($first_tier) && isset($first_tier['exp_tarif_adulte']) && is_numeric($first_tier['exp_tarif_adulte'])) {
                $price = (float) $first_tier['exp_tarif_adulte'];
            }
        }

        return $price;
    }
}
