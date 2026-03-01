<?php

/**
 * Module : Shortcodes de recommandations (Logements & Expériences)
 * Gère l'affichage des éléments recommandés sur la fiche Destination.
 */

if (!defined('ABSPATH')) {
    exit; // Sécurité
}

class PC_Destination_Recommendations_Shortcode
{
    /**
     * Enregistre les shortcodes
     */
    public function register()
    {
        add_shortcode('destination_logements_recommandes', [$this, 'render_logements']);
        add_shortcode('destination_experiences_recommandees', [$this, 'render_experiences']);
    }

    /**
     * Rendu du shortcode [destination_logements_recommandes]
     */
    public function render_logements()
    {
        if (!is_singular('destination') || !function_exists('get_field')) {
            return '';
        }

        $recommended_ids = get_field('dest_logements_recommandes');

        if (empty($recommended_ids)) {
            return '';
        }

        $args = [
            'post_type'      => ['villa', 'appartement'],
            'post__in'       => $recommended_ids,
            'posts_per_page' => 3,
            'orderby'        => 'post__in',
            'post_status'    => 'publish',
            'no_found_rows'  => true,
            'ignore_sticky_posts' => true,
        ];
        $query = new WP_Query($args);

        if (!$query->have_posts()) {
            return '';
        }

        $ville = get_the_title(get_queried_object_id());

        ob_start(); ?>
        <section class="dest-reco-section">
            <h3 class="dest-reco-title">Logements recommandés à <?php echo esc_html($ville); ?></h3>
            <div class="dest-reco-grid">
                <?php while ($query->have_posts()) : $query->the_post(); ?>
                    <div class="dest-reco-card">
                        <a href="<?php the_permalink(); ?>" class="dest-reco-card-link">
                            <?php if (has_post_thumbnail()) : ?>
                                <div class="dest-reco-card-image">
                                    <?php the_post_thumbnail('medium_large'); ?>
                                </div>
                            <?php endif; ?>
                            <div class="dest-reco-card-content">
                                <h4 class="dest-reco-card-title"><?php the_title(); ?></h4>
                                <?php
                                $price_from = get_field('base_price_from', get_the_ID());
                                if ($price_from) : ?>
                                    <div class="dest-reco-card-price">
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
        wp_reset_postdata();
        return ob_get_clean();
    }

    /**
     * Rendu du shortcode [destination_experiences_recommandees]
     */
    public function render_experiences()
    {
        if (!is_singular('destination') || !function_exists('get_field')) {
            return '';
        }

        $pid_dest = get_queried_object_id();
        $featured = (array) get_field('dest_exp_featured', $pid_dest);

        if (empty($featured)) {
            return '';
        }

        $ids = [];
        foreach ($featured as $item) {
            if (is_array($item) && isset($item['ID'])) {
                $ids[] = intval($item['ID']);
            } else {
                $ids[] = intval($item);
            }
        }
        $ids = array_filter(array_unique($ids));

        if (!$ids) return '';

        $q = new WP_Query([
            'post_type'      => 'experience',
            'post_status'    => 'publish',
            'post__in'       => $ids,
            'orderby'        => 'post__in',
            'posts_per_page' => 3,
            'no_found_rows'  => true,
            'ignore_sticky_posts' => true,
        ]);

        if (!$q->have_posts()) {
            return '';
        }

        $ville = get_the_title($pid_dest);

        ob_start(); ?>
        <section class="dest-reco-section">
            <h3 class="dest-reco-title">Expériences à proximité</h3>
            <div class="dest-reco-grid">
                <?php while ($q->have_posts()) : $q->the_post(); ?>
                    <div class="dest-reco-card">
                        <a href="<?php the_permalink(); ?>" class="dest-reco-card-link">
                            <?php if (has_post_thumbnail()) : ?>
                                <div class="dest-reco-card-image">
                                    <?php the_post_thumbnail('medium_large'); ?>
                                </div>
                            <?php endif; ?>
                            <div class="dest-reco-card-content">
                                <h4 class="dest-reco-card-title"><?php the_title(); ?></h4>
                                <?php
                                $price_from = null;
                                $types = get_field('exp_types_de_tarifs', get_the_ID());
                                if (is_array($types)) {
                                    foreach ($types as $row) {
                                        if (!is_array($row)) continue;
                                        $raw = $row['exp_tarif_adulte'] ?? '';
                                        if ($raw === '' || $raw === null) continue;

                                        $num = preg_replace('/[^0-9,.\-]/', '', (string) $raw);
                                        $num = str_replace(',', '.', $num);
                                        $val = floatval($num);
                                        if ($val > 0) {
                                            $price_from = is_null($price_from) ? $val : min($price_from, $val);
                                        }
                                    }
                                }
                                if (!is_null($price_from)) : ?>
                                    <div class="dest-reco-card-price">
                                        À partir de <?php echo esc_html(number_format_i18n($price_from, 0)); ?>€
                                    </div>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                <?php endwhile;
                wp_reset_postdata(); ?>
            </div>
        </section>
<?php
        return ob_get_clean();
    }
}
