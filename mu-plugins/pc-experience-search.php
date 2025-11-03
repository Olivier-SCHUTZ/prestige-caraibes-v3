<?php
/**
 * Plugin Name: PC - Recherche d'Expériences
 * Description: Fournit un shortcode [barre_recherche_experiences] pour afficher un formulaire de recherche AJAX pour les expériences.
 * Version: 2.0 (Refactor pour SSR Schema)
 * Author: Prestige Caraïbes
 */

if (!defined('ABSPATH')) exit;

// --- Enregistrement du shortcode et des assets (inchangé) ---
add_action('init', function () {
    add_shortcode('barre_recherche_experiences', 'pc_exp_render_search_bar');
});
add_action('wp_ajax_pc_filter_experiences', 'pc_exp_ajax_filter_handler');
add_action('wp_ajax_nopriv_pc_filter_experiences', 'pc_exp_ajax_filter_handler');

function pc_exp_enqueue_assets() {
    // ... (code de la fonction inchangé) ...
    $version = '2.0.0';
    wp_enqueue_style('pc-experience-search-css', plugins_url('assets/experience-search.css', __FILE__), [], $version);
    wp_enqueue_script('pc-experience-search-js', plugins_url('assets/pc-experience-search.js', __FILE__), ['jquery'], $version, true);
    wp_localize_script('pc-experience-search-js', 'pc_exp_ajax', ['ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('pc_experience_search_nonce')]);
}

// --- Fonctions de récupération des données (inchangé) ---
function pc_exp_get_categories(): array {
    // ... (code de la fonction inchangé) ...
    $categories = [];
    $terms = get_terms(['taxonomy' => 'categorie_experience', 'hide_empty' => true]);
    if (!is_wp_error($terms) && !empty($terms)) { foreach ($terms as $term) { $categories[$term->slug] = $term->name; } }
    return $categories;
}

function pc_exp_get_villes(): array {
    // ... (code de la fonction inchangé) ...
    $villes_cache = get_transient('pc_exp_villes_list');
    if ($villes_cache !== false) { return $villes_cache; }
    $villes = [];
    $experience_ids = new WP_Query(['post_type' => 'experience', 'posts_per_page' => -1, 'fields' => 'ids']);
    if ($experience_ids->have_posts()) {
        foreach ($experience_ids->posts as $post_id) {
            if (have_rows('exp_lieux_horaires_depart', $post_id)) {
                while (have_rows('exp_lieux_horaires_depart', $post_id)) {
                    the_row();
                    $lieu = get_sub_field('exp_lieu_depart');
                    if (!empty($lieu) && !in_array($lieu, $villes)) { $villes[] = trim($lieu); }
                }
            }
        }
    }
    $villes = array_unique($villes);
    sort($villes);
    set_transient('pc_exp_villes_list', $villes, 12 * HOUR_IN_SECONDS);
    return $villes;
}

// --- Affichage de la barre de recherche (inchangé) ---
// Modification pour SSR + rendu initial des vignettes
function pc_exp_render_search_bar($atts = []) {
    pc_exp_enqueue_assets();
    $categories = pc_exp_get_categories();
    $villes = pc_exp_get_villes();

    // Récupération des filtres initiaux depuis l'URL (pour l'hydratation JS)
    $initial_category = isset($_GET['categorie']) ? sanitize_text_field($_GET['categorie']) : '';

    $options_categories = '';
    foreach ($categories as $slug => $label) {
        $selected = selected($initial_category, $slug, false);
        $options_categories .= '<option value="' . esc_attr($slug) . '"' . $selected . '>' . esc_html($label) . '</option>';
    }

    $options_villes = '';
    foreach ($villes as $ville_name) {
        $options_villes .= '<option value="'.esc_attr($ville_name).'">'.esc_html($ville_name).'</option>';
    }

    ob_start();
    ?>
    <div class="pc-exp-search-wrapper" role="search" aria-label="Recherche d'expériences">
        <div class="pc-exp-search-shell">
            <form id="pc-exp-filters-form" class="pc-exp-search-form" action="#" method="post" autocomplete="off">
                <div class="pc-exp-search-field"><label for="filter-exp-category" class="sr-only">Catégorie</label><select id="filter-exp-category" name="exp_category" class="pc-input"><option value="">Toutes les catégories</option><?php echo $options_categories; ?></select></div>
                <div class="pc-exp-search-field"><label for="filter-exp-ville" class="sr-only">Destination</label><select id="filter-exp-ville" name="exp_ville" class="pc-input"><option value="">Toute la Guadeloupe</option><?php echo $options_villes; ?></select></div>
                <div class="pc-exp-search-field pc-exp-search-field--keyword"><label for="filter-exp-keyword" class="sr-only">Mot-clé</label><input type="text" id="filter-exp-keyword" name="exp_keyword" class="pc-input" placeholder="Ex: kayak, randonnée..."></div>
                <button class="pc-exp-search-submit pc-btn pc-btn--primary" type="submit">Rechercher</button>
            </form>
            <div class="pc-exp-row-adv-toggle"><button type="button" class="pc-exp-adv-toggle pc-btn pc-btn--line" aria-controls="pc-exp-advanced" aria-expanded="false">Plus de filtres</button></div>
            <div id="pc-exp-advanced" class="pc-exp-advanced" hidden><div class="pc-exp-advanced__grid"><div class="pc-exp-adv-field"><div class="pc-exp-adv-label">Participants (min)</div><div class="pc-num-step"><button type="button" class="num-stepper" data-target="participants" data-step="-1" aria-label="Moins de participants">−</button><input type="number" class="pc-input pc-num-input" id="filter-exp-participants" name="exp_participants" min="1" step="1" value="1"><button type="button" class="num-stepper" data-target="participants" data-step="1" aria-label="Plus de participants">+</button></div></div><div class="pc-exp-adv-field"><div class="pc-exp-adv-label">Prix (€ / personne)</div><div class="pc-price-values"><input type="number" id="filter-exp-prix-min" name="exp_prix_min" class="pc-input" placeholder="Min" min="0" step="10"><input type="number" id="filter-exp-prix-max" name="exp_prix_max" class="pc-input" placeholder="Max" min="0" step="10"></div></div></div></div>
        </div>
        
        <div id="pc-exp-results-container" class="flow" style="margin-top:2rem;">
            <?php
            // On exécute la recherche initiale côté serveur
            $initial_filters = ['category' => $initial_category];
            $initial_results = pc_get_filtered_experiences($initial_filters);

            // On passe les données de la carte au JS pour l'initialisation
            wp_localize_script('pc-experience-search-js', 'pc_exp_initial_data', ['map_data' => $initial_results['map_data']]);
            
            // On affiche les vignettes initiales
            echo pc_exp_render_vignettes_html($initial_results['vignettes'], true); // true pour marquer la 1ère image LCP

            // On affiche la pagination initiale
            echo pc_exp_render_pagination_html($initial_results['pagination']['current_page'], $initial_results['pagination']['total_pages']);
            ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * NOUVEAU : Fonction dédiée au rendu HTML des vignettes
 * @param array $vignettes - Les données des vignettes à afficher.
 * @param bool $is_first_load - Indique si c'est le premier chargement de la page (pour le LCP).
 * @return string - Le HTML des vignettes.
 */
function pc_exp_render_vignettes_html(array $vignettes, bool $is_first_load = false): string {
    if (empty($vignettes)) {
        return '<div class="pc-no-results"><h3>Aucune expérience ne correspond à votre recherche.</h3><p>Essayez d\'ajuster vos filtres.</p></div>';
    }

    $html = '<div class="pc-exp-results-grid pc-results-grid">';
    $first = true;

    foreach ($vignettes as $item) {
        $price_html = $item['price'] ? '<div class="pc-vignette__price">À partir de ' . esc_html($item['price']) . '€</div>' : '';
        $location_html = $item['city'] ? '<div class="pc-vignette__location">' . esc_html($item['city']) . '</div>' : '';
        
        $image_attrs = 'src="' . esc_url($item['thumb']) . '" alt="' . esc_attr($item['title']) . '" width="300" height="200"';

        // Attributs spéciaux pour l'image LCP
        if ($is_first_load && $first) {
            $image_attrs .= ' fetchpriority="high" loading="eager" decoding="async"';
            $first = false;
        } else {
            $image_attrs .= ' loading="lazy" decoding="async"';
        }

        $image_html = $item['thumb'] ? '<img ' . $image_attrs . '>' : '';

        $html .= sprintf(
            '<a href="%s" class="pc-vignette" target="_blank" rel="noopener">
                <div class="pc-vignette__image">%s</div>
                <div class="pc-vignette__content">
                    <h3 class="pc-vignette__title">%s</h3>
                    %s
                    %s
                </div>
            </a>',
            esc_url($item['link']),
            $image_html,
            esc_html($item['title']),
            $location_html,
            $price_html
        );
    }
    $html .= '</div>';
    return $html;
}

/**
 * NOUVEAU : Fonction dédiée au rendu HTML de la pagination
 * @return string - Le HTML de la pagination.
 */
function pc_exp_render_pagination_html(int $current, int $total): string {
    if ($total <= 1) return '';

    $html = '<div class="pc-pagination">';
    for ($i = 1; $i <= $total; $i++) {
        $html .= ($i === $current) ? '<span class="current">' . $i . '</span>' : '<a href="#" data-page="' . $i . '">' . $i . '</a>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * ===================================================================
 * NOUVEAU : Le "Moteur de Recherche" pour les Expériences
 * ===================================================================
 * Cette fonction contient toute la logique de recherche et retourne les résultats.
 */
function pc_get_filtered_experiences(array $filters): array {
    $category     = isset($filters['category']) ? sanitize_text_field($filters['category']) : '';
    $ville        = isset($filters['ville']) ? sanitize_text_field($filters['ville']) : '';
    $keyword      = isset($filters['keyword']) ? sanitize_text_field($filters['keyword']) : '';
    $participants = isset($filters['participants']) ? intval($filters['participants']) : 1;
    $prix_min     = isset($filters['prix_min']) && is_numeric($filters['prix_min']) ? floatval($filters['prix_min']) : 0;
    $prix_max     = isset($filters['prix_max']) && is_numeric($filters['prix_max']) ? floatval($filters['prix_max']) : 99999;
    $page         = isset($filters['page']) ? intval($filters['page']) : 1;

    $args = ['post_type' => 'experience', 'posts_per_page' => -1, 's' => $keyword];
    if (!empty($category)) { $args['tax_query'] = [['taxonomy' => 'categorie_experience', 'field' => 'slug', 'terms' => $category]]; }
    $meta_query = ['relation' => 'AND'];
    if ($participants > 1) { $meta_query[] = ['key' => 'exp_capacite', 'value' => $participants, 'compare' => '>=', 'type' => 'NUMERIC']; }
    if (count($meta_query) > 1) { $args['meta_query'] = $meta_query; }

    $query = new WP_Query($args);
    $final_posts = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();

            if (!empty($ville)) {
                $ville_trouvee = false;
                if (have_rows('exp_lieux_horaires_depart', $post_id)) {
                    while (have_rows('exp_lieux_horaires_depart', $post_id)) {
                        the_row();
                        if (get_sub_field('exp_lieu_depart') === $ville) { $ville_trouvee = true; break; }
                    }
                }
                if (!$ville_trouvee) continue;
            }

            $tarifs = get_field('exp_types_de_tarifs', $post_id);
            $base_price = null;
            if ($tarifs) { foreach ($tarifs as $tarif) { $prix_adulte = !empty($tarif['exp_tarif_adulte']) ? floatval($tarif['exp_tarif_adulte']) : null; if ($prix_adulte !== null && ($base_price === null || $prix_adulte < $base_price)) { if($prix_adulte > 0) $base_price = $prix_adulte; } } }
            if (($prix_min > 0 || $prix_max < 99999) && ($base_price === null || $base_price < $prix_min || $base_price > $prix_max)) { continue; }
            
            $final_posts[] = get_post();
        }
    }
    wp_reset_postdata();
    
    $posts_per_page = 9;
    $total_results = count($final_posts);
    $total_pages = ceil($total_results / $posts_per_page);
    $posts_for_current_page = array_slice($final_posts, ($page - 1) * $posts_per_page, $posts_per_page);
    
    $vignettes_data = []; $map_data = [];
    foreach($posts_for_current_page as $post) {
        $post_id = $post->ID;
        $tarifs = get_field('exp_types_de_tarifs', $post_id);
        $base_price = null;
        if ($tarifs) { foreach ($tarifs as $tarif) { $prix_adulte = !empty($tarif['exp_tarif_adulte']) ? floatval($tarif['exp_tarif_adulte']) : null; if ($prix_adulte !== null && ($base_price === null || $prix_adulte < $base_price)) { if($prix_adulte > 0) $base_price = $prix_adulte; } } }
        $lieux = get_field('exp_lieux_horaires_depart', $post_id);
        $lat = null; $lng = null; $city_name = '';
        if ($lieux && !empty($lieux[0])) { $lat = $lieux[0]['lat_exp'] ?? null; $lng = $lieux[0]['longitude'] ?? null; $city_name = $lieux[0]['exp_lieu_depart'] ?? ''; }
        $vignette_data = ['id' => $post_id, 'title' => get_the_title($post), 'link' => get_permalink($post), 'thumb' => get_the_post_thumbnail_url($post_id, 'medium_large'), 'price' => $base_price, 'city' => $city_name, 'lat' => $lat, 'lng' => $lng];
        $vignettes_data[] = $vignette_data;
        if($lat && $lng) { $map_data[] = $vignette_data; }
    }
    
    return [
        'vignettes' => $vignettes_data,
        'map_data' => $map_data,
        'pagination' => ['current_page' => $page, 'total_pages' => $total_pages]
    ];
}

/**
 * ===================================================================
 * La fonction AJAX, maintenant simplifiée
 * ===================================================================
 */
// Simplification du handler AJAX pour retourner du HTML
function pc_exp_ajax_filter_handler() {
    check_ajax_referer('pc_experience_search_nonce', 'security');

    $filters = [
        'category'     => $_POST['category'] ?? '',
        'ville'        => $_POST['ville'] ?? '',
        'keyword'      => $_POST['keyword'] ?? '',
        'participants' => $_POST['participants'] ?? 1,
        'prix_min'     => $_POST['prix_min'] ?? '',
        'prix_max'     => $_POST['prix_max'] ?? '',
        'page'         => $_POST['page'] ?? 1,
    ];

    // On appelle notre moteur de recherche
    $results = pc_get_filtered_experiences($filters);
    
    // On génère le HTML pour les vignettes et la pagination
    $vignettes_html = pc_exp_render_vignettes_html($results['vignettes']);
    $pagination_html = pc_exp_render_pagination_html($results['pagination']['current_page'], $results['pagination']['total_pages']);

    wp_send_json_success([
        'vignettes_html' => $vignettes_html,
        'pagination_html' => $pagination_html,
        'map_data' => $results['map_data']
    ]);
}