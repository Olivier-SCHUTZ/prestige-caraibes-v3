<?php
/**
 * Plugin Name: PC Loop Components
 * Description: Fournit des shortcodes pour les grilles de boucle Elementor, comme la vignette de logement.
 * Version: 1.3
 */

if (!defined('ABSPATH')) exit;

add_shortcode('pc_loop_lodging_card', 'pc_render_loop_lodging_card');

function pc_render_loop_lodging_card() {
    global $post;

    $post_id = $post->ID;
    $permalink = get_permalink($post_id);
    $title = get_the_title($post_id);
    $thumbnail_url = get_the_post_thumbnail_url($post_id, 'large') ?: 'https://via.placeholder.com/400x288.png?text=Image+non+disponible';
    $ville = get_post_meta($post_id, 'ville', true);
    $location = $ville ? esc_html($ville) . ', Guadeloupe' : 'Guadeloupe';

    // --- NOUVEAU : Vérification de la promotion ---
    $is_promo = get_post_meta($post_id, 'pc-promo-log', true);

    // Calcul de la note moyenne
    $rating = 0;
    $review_args = [
        'post_type' => 'pc_review', 'post_status' => 'publish', 'posts_per_page' => -1,
        'meta_query' => [
            'relation' => 'AND',
            ['key' => 'pc_post_id', 'value' => $post_id],
            ['key' => 'pc_source', 'value' => 'internal']
        ],
        'fields' => 'ids',
    ];
    $review_query = new WP_Query($review_args);
    if (!empty($review_query->posts)) {
        $total_rating = 0;
        foreach ($review_query->posts as $review_id) {
            $total_rating += (float) get_post_meta($review_id, 'pc_rating', true);
        }
        if ($review_query->post_count > 0) {
            $rating = $total_rating / $review_query->post_count;
        }
    }
    
    // Génération du HTML pour la note
    $rating_html = '';
    if ($rating > 0) {
        $rating_html .= '<div class="pc-vignette__rating"><div class="pc-vignette__stars">';
        for ($i = 1; $i <= 5; $i++) {
            $class = ($i <= round($rating)) ? 'star filled' : 'star';
            $rating_html .= '<span class="' . $class . '"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg></span>';
        }
        $rating_html .= '</div></div>';
    } else {
        $rating_html = '<div class="pc-vignette__rating">N.C.</div>';
    }

    // Prix
    $price = get_post_meta($post_id, 'base_price_from', true);
    $price_html = '';
    if (!empty($price) && is_numeric($price)) {
        $price_html = '<div class="pc-vignette__price">À partir de ' . number_format($price, 0, ',', ' ') . '€ par nuit</div>';
    }

    ob_start();
    ?>
    <a href="<?php echo esc_url($permalink); ?>" class="pc-vignette">
        <?php if ($is_promo) : // --- NOUVEAU : Affichage conditionnel du ruban --- ?>
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