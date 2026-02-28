<?php

/**
 * Shortcode : [experience_logements_recommandes]
 * Affiche les logements recommandés à proximité de l'expérience.
 *
 * @package PC_Experiences
 */

if (!defined('ABSPATH')) {
    exit; // Sécurité
}

class PC_Experience_Recommendations_Shortcode extends PC_Experience_Shortcode_Base
{

    /**
     * Nom du shortcode.
     *
     * @var string
     */
    protected $shortcode_name = 'experience_logements_recommandes';

    /**
     * Rendu HTML du shortcode.
     *
     * @param array $atts Attributs.
     */
    protected function render(array $atts): void
    {
        $experience_id = $this->get_experience_id();
        $recommended_ids = get_field('exp_logements_recommandes', $experience_id);

        if (empty($recommended_ids)) {
            return;
        }

        $args = [
            'post_type'      => ['villa', 'appartement'],
            'post__in'       => $recommended_ids,
            'orderby'        => 'post__in',
            'posts_per_page' => -1,
        ];

        $query = new WP_Query($args);

        if (!$query->have_posts()) {
            return;
        }

        // --- DÉBUT DU RENDU ---
?>
        <section class="exp-reco-section">
            <h2 class="exp-reco-title">Logements recommandés à proximité</h2>
            <div class="exp-reco-grid">
                <?php while ($query->have_posts()) : $query->the_post(); ?>
                    <div class="exp-reco-card">
                        <a href="<?php the_permalink(); ?>" class="exp-reco-card-link">
                            <?php if (has_post_thumbnail()) : ?>
                                <div class="exp-reco-card-image">
                                    <?php the_post_thumbnail('medium_large'); ?>
                                </div>
                            <?php endif; ?>
                            <div class="exp-reco-card-content">
                                <h3 class="exp-reco-card-title"><?php the_title(); ?></h3>
                                <?php
                                $price_from = get_field('base_price_from', get_the_ID());
                                if ($price_from) :
                                ?>
                                    <div class="exp-reco-card-price">
                                        À partir de <?php echo esc_html(number_format_i18n($price_from, 0)); ?>€ / nuit
                                    </div>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                <?php endwhile; ?>
            </div>
        </section>
<?php
        wp_reset_postdata(); // Toujours réinitialiser la requête après une boucle custom
        // --- FIN DU RENDU ---
    }
}
