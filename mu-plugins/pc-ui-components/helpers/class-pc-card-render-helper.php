<?php

/**
 * PC Card Render Helper
 * Prépare les données et génère le HTML final des vignettes (Logements, Expériences)
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Card_Render_Helper
{

    /**
     * Génère la carte HTML complète pour un logement
     *
     * @param int $post_id L'ID du logement
     * @return string Le code HTML de la vignette
     */
    public static function render_lodging_card($post_id)
    {
        $permalink = get_permalink($post_id);
        $title = get_the_title($post_id);
        $thumbnail_url = get_the_post_thumbnail_url($post_id, 'large') ?: 'https://via.placeholder.com/400x288.png?text=Image+non+disponible';
        $ville = get_post_meta($post_id, 'ville', true);
        $location = $ville ? esc_html($ville) . ', Guadeloupe' : 'Guadeloupe';

        // Vérification de la promotion
        $is_promo = get_post_meta($post_id, 'pc-promo-log', true);

        // Prix
        $price = get_post_meta($post_id, 'base_price_from', true);
        $price_html = '';
        if (!empty($price) && is_numeric($price)) {
            $price_html = '<div class="pc-vignette__price">À partir de ' . number_format($price, 0, ',', ' ') . '€ par nuit</div>';
        }

        // Appel au Helper des notes (Rating)
        $rating = PC_Rating_Helper::get_average_rating($post_id);
        $rating_html = PC_Rating_Helper::render_stars_html($rating);

        ob_start();
?>
        <a href="<?php echo esc_url($permalink); ?>" class="pc-vignette">
            <?php if ($is_promo) : ?>
                <div class="pc-vignette__promo-ribbon">Promotion</div>
            <?php endif; ?>

            <div class="pc-vignette__image">
                <img src="<?php echo esc_url($thumbnail_url); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
            </div>
            <div class="pc-vignette__content">
                <h3 class="pc-vignette__title"><?php echo esc_html($title); ?></h3>
                <div class="pc-vignette__location"><?php echo $location; ?></div>
                <?php echo $rating_html; ?>
                <?php echo $price_html; ?>
            </div>
        </a>
<?php
        return ob_get_clean();
    }
}
