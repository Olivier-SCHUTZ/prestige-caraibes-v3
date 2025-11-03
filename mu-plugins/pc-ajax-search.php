<?php
/**
 * MU Plugin: PC AJAX Search — v29 (SSR Refactor - Step 3)
 */

if (!defined('ABSPATH')) exit;

// Fonction de template pour une vignette (inchangée)
function pc_get_vignette_html(array $data) {
    // ... (code de la fonction inchangé) ...
    $url        = isset($data['link']) ? esc_url($data['link']) : '#';
    $thumb_url  = isset($data['thumb']) ? esc_url($data['thumb']) : '';
    $title      = isset($data['title']) ? esc_html($data['title']) : 'Titre non disponible';
    $city       = isset($data['city']) ? esc_html($data['city']) : 'Lieu non disponible';
    $price      = isset($data['price']) ? esc_html($data['price']) : '';
    $rating_avg = isset($data['rating_avg']) ? floatval($data['rating_avg']) : 0;
    $rating_count = isset($data['rating_count']) ? intval($data['rating_count']) : 0;
    
    $stars_html = '';
    if ($rating_count > 0) {
        $rounded_rating = round($rating_avg);
        for ($i = 1; $i <= 5; $i++) {
            $star_class = ($i <= $rounded_rating) ? 'star filled' : 'star';
            $stars_html .= "<span class='{$star_class}'><svg width='18' height='18' viewBox='0 0 24 24'><path d='M12 17.3l-6.18 3.75 1.64-7.03L2 9.77l7.19-.61L12 2.5l2.81 6.66 7.19.61-5.46 4.25 1.64 7.03z'/></svg></span>";
        }
    } else {
        $stars_html = 'N.C';
    }
    
    $price_html = $price ? "À partir de <strong>{$price}€ par nuit</strong>" : '';

    ob_start();
    ?>
    <a href="<?php echo $url; ?>" class="pc-vignette" target="_blank" rel="noopener">
        <div class="pc-vignette__image">
            <?php if ($thumb_url): ?>
                <img src="<?php echo $thumb_url; ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy" decoding="async" />
            <?php endif; ?>
        </div>
        <div class="pc-vignette__content">
            <h3 class="pc-vignette__title"><?php echo $title; ?></h3>
            <div class="pc-vignette__location"><?php echo $city; ?>, Guadeloupe</div>
            <div class="pc-vignette__rating"><?php echo $stars_html; ?></div>
            <div class="pc-vignette__price"><?php echo $price_html; ?></div>
        </div>
    </a>
    <?php
    return ob_get_clean();
}

// Moteur de recherche (inchangé)
function pc_get_filtered_logements(array $filters) {
    // ... (code de la fonction inchangé) ...
    $paged        = isset($filters['page']) ? max(1, intval($filters['page'])) : 1;
    $ville        = isset($filters['ville']) ? sanitize_text_field($filters['ville']) : '';
    $date_arrivee = isset($filters['date_arrivee']) ? sanitize_text_field($filters['date_arrivee']) : '';
    $date_depart  = isset($filters['date_depart'])  ? sanitize_text_field($filters['date_depart'])  : '';
    $invites      = isset($filters['invites']) ? max(1, intval($filters['invites'])) : 1;
    $chambres     = isset($filters['chambres']) ? max(0, intval($filters['chambres'])) : 0;
    $sdb          = isset($filters['sdb'])      ? max(0, intval($filters['sdb']))      : 0;
    $prix_min     = isset($filters['prix_min']) && $filters['prix_min'] !== '' ? max(0, intval($filters['prix_min'])) : 0;
    $prix_max     = isset($filters['prix_max']) && $filters['prix_max'] !== '' ? max(0, intval($filters['prix_max'])) : 1000;
    $theme        = isset($filters['theme']) ? sanitize_text_field($filters['theme']) : '';
    $meta_query = ['relation' => 'AND'];
    $meta_query[] = ['key' => 'capacite', 'value' => $invites, 'compare' => '>=', 'type' => 'NUMERIC'];
    if ($chambres > 0) $meta_query[] = ['key' => 'nombre_de_chambres', 'value' => $chambres, 'compare' => '>=', 'type' => 'NUMERIC'];
    if ($sdb > 0) $meta_query[] = ['key' => 'nombre_sdb', 'value' => $sdb, 'compare' => '>=', 'type' => 'NUMERIC'];
    if ($prix_min > 0 || $prix_max < 1000) $meta_query[] = ['key' => 'base_price_from', 'value' => [$prix_min, $prix_max], 'compare' => 'BETWEEN', 'type' => 'NUMERIC'];
    if (!empty($theme)) $meta_query[] = ['key' => 'highlights', 'value' => '"' . $theme . '"', 'compare' => 'LIKE'];
    $args = [ 'post_type' => ['logement', 'villa', 'appartement'], 'posts_per_page' => -1, 'meta_query' => $meta_query, ];
    $query = new WP_Query($args);
    $final_posts = [];
    if ($query->have_posts()) {
        $ville_recherchee_sanitized = $ville ? sanitize_title($ville) : '';
        foreach ($query->posts as $post) {
            $post_id = $post->ID;
            if ($ville) {
                $ville_du_logement = get_field('ville', $post_id);
                if (empty($ville_du_logement) || sanitize_title(remove_accents($ville_du_logement)) !== $ville_recherchee_sanitized) continue;
            }
            if ($date_arrivee && $date_depart) {
                $dates_demandees = [];
                $period = new DatePeriod(new DateTime($date_arrivee), new DateInterval('P1D'), new DateTime($date_depart));
                foreach ($period as $date) { $dates_demandees[] = $date->format('Y-m-d'); }
                $dates_reservees = get_post_meta($post_id, '_booked_dates_cache', true);
                if (!empty($dates_reservees) && is_array($dates_reservees) && !empty(array_intersect($dates_demandees, $dates_reservees))) continue;
            }
            $final_posts[] = $post;
        }
    }
    $posts_per_page = 9;
    $total_results = count($final_posts);
    $total_pages = ceil($total_results / $posts_per_page);
    $posts_for_current_page = array_slice($final_posts, ($paged - 1) * $posts_per_page, $posts_per_page);
    $vignettes_data = [];
    foreach($posts_for_current_page as $post) {
        $stats = function_exists('pc_rev_get_internal_stats') ? pc_rev_get_internal_stats($post->ID) : ['avg' => 0, 'count' => 0];
        $vignettes_data[] = [ 'id' => $post->ID, 'title' => get_the_title($post->ID), 'link' => get_permalink($post->ID), 'thumb' => get_the_post_thumbnail_url($post->ID, 'medium_large') ?: '', 'price' => get_field('base_price_from', $post->ID), 'city' => get_field('ville', $post->ID), 'rating_avg' => $stats['avg'] ?? 0, 'rating_count' => $stats['count'] ?? 0, ];
    }
    $map_data = [];
    foreach($final_posts as $post) {
        $map_data[] = [ 'title' => get_the_title($post->ID), 'price' => get_field('base_price_from', $post->ID), 'latitude' => (float) get_field('latitude', $post->ID), 'longitude' => (float) get_field('longitude', $post->ID), 'link' => get_permalink($post->ID), ];
    }
    return [ 'vignettes' => $vignettes_data, 'map_data' => $map_data, 'pagination' => ['current_page' => $paged, 'total_pages' => $total_pages], ];
}

/**
 * ===================================================================
 * NOUVEAU : Fonction pour générer le HTML de la pagination
 * ===================================================================
 */
function pc_get_pagination_html(array $pagination_data) {
    $current = $pagination_data['current_page'];
    $total   = $pagination_data['total_pages'];

    if ($total <= 1) {
        return '';
    }

    $html = '<div class="pc-pagination">';
    for ($i = 1; $i <= $total; $i++) {
        if ($i === $current) {
            $html .= '<span class="current">' . $i . '</span>';
        } else {
            $html .= '<a href="#" data-page="' . $i . '">' . $i . '</a>';
        }
    }
    $html .= '</div>';
    return $html;
}


add_action('wp_ajax_pc_filter_logements', 'pc_ajax_filter_logements_callback');
add_action('wp_ajax_nopriv_pc_filter_logements', 'pc_ajax_filter_logements_callback');
/**
 * ===================================================================
 * Callback AJAX MIS À JOUR pour renvoyer du HTML
 * ===================================================================
 */
function pc_ajax_filter_logements_callback() {
    check_ajax_referer('pc_search_nonce', 'security');

    $filters = [
        'page'         => $_POST['page'] ?? 1,
        'ville'        => $_POST['ville'] ?? '',
        'date_arrivee' => $_POST['date_arrivee'] ?? '',
        'date_depart'  => $_POST['date_depart'] ?? '',
        'invites'      => $_POST['invites'] ?? 1,
        'chambres'     => $_POST['chambres'] ?? 0,
        'sdb'          => $_POST['sdb'] ?? 0,
        'prix_min'     => $_POST['prix_min'] ?? '',
        'prix_max'     => $_POST['prix_max'] ?? '',
        'theme'        => $_POST['theme'] ?? '',
    ];
    
    // On appelle notre moteur de recherche qui nous renvoie les données brutes
    $results = pc_get_filtered_logements($filters);
    
    // On transforme les données des vignettes en un seul bloc de HTML
    $vignettes_html = '';
    if (!empty($results['vignettes'])) {
        $vignettes_html .= '<div class="pc-results-grid">';
        foreach ($results['vignettes'] as $vignette_data) {
            $vignettes_html .= pc_get_vignette_html($vignette_data);
        }
        $vignettes_html .= '</div>';
    } else {
        $vignettes_html = '<div class="pc-no-results"><h3>Aucun logement ne correspond à votre recherche.</h3><p>Essayez d\'ajuster vos filtres.</p></div>';
    }

    // On génère le HTML pour la pagination
    $pagination_html = pc_get_pagination_html($results['pagination']);
    
    // On envoie la réponse JSON avec le HTML et les données pour la carte
    wp_send_json_success([
        'vignettes_html'  => $vignettes_html,
        'pagination_html' => $pagination_html,
        'map_data'        => $results['map_data'], // On continue d'envoyer les données brutes pour la carte
    ]);
}