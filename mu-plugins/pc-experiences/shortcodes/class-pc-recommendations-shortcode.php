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
    protected function render(array $atts = []): void
    {
        $experience_id = $this->get_experience_id();
        if (!$experience_id) return;

        // --- 1. DÉCODEUR V3 POUR CHAMP RELATION (IDs) ---
        $raw_ids = PCR_Fields::get('exp_logements_recommandes', $experience_id);
        $recommended_ids = is_string($raw_ids) ? json_decode($raw_ids, true) : $raw_ids;

        if (empty($recommended_ids) || !is_array($recommended_ids)) {
            return;
        }

        // Sécurisation : on s'assure de n'avoir que des entiers valides
        $recommended_ids = array_map('intval', $recommended_ids);
        $recommended_ids = array_filter($recommended_ids);

        if (empty($recommended_ids)) {
            return;
        }

        // --- 2. REQUÊTE WORDPRESS ---
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

        // --- 3. RENDU HTML ET CSS EMBARQUÉ ---
        ob_start(); ?>

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
                                $price_from = PCR_Fields::get('base_price_from', get_the_ID());
                                if ($price_from) :
                                ?>
                                    <div class="exp-reco-card-price">
                                        À partir de <strong><?php echo esc_html(number_format_i18n($price_from, 0)); ?> €</strong> / nuit
                                    </div>
                                <?php endif; ?>
                            </div>

                        </a>
                    </div>
                <?php endwhile; ?>
            </div>
        </section>

        <style>
            /* CSS Embarqué avec les Tokens Globaux (Prestige Caraïbes V3) */
            .exp-reco-section {
                padding: 3rem 0;
                font-family: var(--pc-font-body, system-ui, sans-serif);
            }

            .exp-reco-title {
                font-family: var(--pc-font-heading, system-ui);
                font-size: 1.8rem;
                font-weight: 700;
                text-align: center;
                margin-bottom: 2.5rem;
                color: var(--pc-color-heading, #1b3b5f);
            }

            .exp-reco-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 2rem;
            }

            .exp-reco-card {
                background: #ffffff;
                border-radius: var(--pc-border-radius, 12px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
                border: 1px solid #e2e8f0;
                transition: transform 0.3s ease, box-shadow 0.3s ease;
                overflow: hidden;
                height: 100%;
                display: flex;
                flex-direction: column;
            }

            .exp-reco-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
            }

            .exp-reco-card-link {
                display: flex;
                flex-direction: column;
                height: 100%;
                text-decoration: none;
                color: inherit;
            }

            .exp-reco-card-image {
                aspect-ratio: 16/10;
                overflow: hidden;
                position: relative;
            }

            .exp-reco-card-image img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                transition: transform 0.5s ease;
            }

            .exp-reco-card:hover .exp-reco-card-image img {
                transform: scale(1.05);
            }

            .exp-reco-card-content {
                padding: 1.5rem;
                display: flex;
                flex-direction: column;
                flex-grow: 1;
                justify-content: space-between;
            }

            .exp-reco-card-title {
                font-family: var(--pc-font-heading, system-ui);
                font-size: 1.15rem;
                font-weight: 600;
                margin: 0 0 1rem 0;
                color: var(--pc-color-heading, #1b3b5f);
                line-height: 1.3;
            }

            .exp-reco-card-price {
                font-size: 1rem;
                color: var(--pc-color-text, #475569);
            }

            .exp-reco-card-price strong {
                color: var(--pc-color-primary, #007a92);
                font-size: 1.1rem;
            }
        </style>

<?php
        wp_reset_postdata();
        echo ob_get_clean();
    }
}
