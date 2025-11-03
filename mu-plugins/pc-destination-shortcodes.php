<?php
/**
 * Prestige Caraïbes — Destinations (shortcodes + FAQ schema)
 * Ajout non-destructif : déposer ce fichier et l'inclure via require_once.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('pc_register_destination_shortcodes')) {
  add_action('init', 'pc_register_destination_shortcodes');
  function pc_register_destination_shortcodes() {
    add_shortcode('pc_destination_logements', 'pc_sc_destination_logements');
    add_shortcode('pc_destination_experiences', 'pc_sc_destination_experiences');
    add_shortcode('pc_destinations_hub', 'pc_sc_destinations_hub');
  }
}

function pc_safe_int($v, $default=0){ $v = is_numeric($v) ? intval($v) : $default; return max(0,$v); }

function pc_get_posts_by_rel_destination($post_type, $dest_id, $args = []) {
  $defaults = [
    'post_type' => $post_type,
    'post_status' => 'publish',
    'posts_per_page' => 12,
    'orderby' => 'date',
    'order' => 'DESC',
    'meta_query' => [[
      'key' => 'rel_destination',
      'value' => sprintf('"%d"', $dest_id), // ACF relationship stoké en array sérialisé
      'compare' => 'LIKE',
    ]],
  ];
  $qargs = wp_parse_args($args, $defaults);
  return new WP_Query($qargs);
}

function pc_render_cards($q, $context='logement'){
  ob_start();
  if ($q->have_posts()) {
    echo '<div class="pc-grid pc-grid--cards">';
    while ($q->have_posts()) { $q->the_post();
      $pid = get_the_ID();
      $permalink = get_permalink($pid);
      $title = get_the_title($pid);
      $thumb = get_the_post_thumbnail($pid, 'medium_large', ['loading'=>'lazy', 'class'=>'pc-card__img']);
      echo '<article class="pc-card pc-card--'.$context.'">';
      echo $thumb ? $thumb : '';
      echo '<div class="pc-card__body">';
      echo '<h3 class="pc-card__title"><a href="'.esc_url($permalink).'">'.esc_html($title).'</a></h3>';
      if ($context === 'logement') {
        $cap = function_exists('get_field') ? get_field('capacite', $pid) : '';
        if ($cap) echo '<div class="pc-card__meta">'.esc_html($cap).' pers.</div>';
      }
      echo '</div></article>';
    }
    echo '</div>';
  } else {
    echo '<p class="pc-empty">Aucun résultat pour cette destination.</p>';
  }
  wp_reset_postdata();
  return ob_get_clean();
}

function pc_sc_destination_logements($atts){
  $a = shortcode_atts([
    'id' => get_queried_object_id(),
    'per_page' => 12,
    'orderby' => 'date',
    'order' => 'DESC',
  ], $atts, 'pc_destination_logements');

  $id = pc_safe_int($a['id'], get_queried_object_id());
  if (!$id) return '';

  $q = pc_get_posts_by_rel_destination('logement', $id, [
    'posts_per_page' => pc_safe_int($a['per_page'], 12),
    'orderby' => sanitize_key($a['orderby']),
    'order' => in_array(strtoupper($a['order']), ['ASC','DESC']) ? strtoupper($a['order']) : 'DESC',
  ]);
  return '<section class="pc-section pc-dest-logements">'.pc_render_cards($q, 'logement').'</section>';
}

function pc_sc_destination_experiences($atts){
  $a = shortcode_atts([
    'id' => get_queried_object_id(),
    'limit' => 3,
  ], $atts, 'pc_destination_experiences');

  $id = pc_safe_int($a['id'], get_queried_object_id());
  if (!$id) return '';

  $featured = function_exists('get_field') ? (array) get_field('dest_exp_featured', $id) : [];
  $html = '<section class="pc-section pc-dest-experiences">';

  if (!empty($featured)) {
    $ids = array_map('intval', is_array($featured) && isset($featured[0]['ID']) ? wp_list_pluck($featured, 'ID') : $featured);
    $q = new WP_Query([
      'post_type' => 'experience',
      'post_status' => 'publish',
      'post__in' => $ids,
      'orderby' => 'post__in',
      'posts_per_page' => pc_safe_int($a['limit'], 3),
    ]);
  } else {
    $q = pc_get_posts_by_rel_destination('experience', $id, [
      'posts_per_page' => pc_safe_int($a['limit'], 3),
    ]);
  }
  $html .= pc_render_cards($q, 'experience').'</section>';
  return $html;
}

function pc_sc_destinations_hub($atts){
  $a = shortcode_atts([
    'region' => 'all', // all | grande-terre | basse-terre | iles-voisines
    'featured_only' => 'false',
    'orderby' => 'meta_value_num', // dest_order
    'order' => 'ASC',
  ], $atts, 'pc_destinations_hub');

  $meta = ['relation' => 'AND', [ 'key' => 'dest_order', 'compare' => 'EXISTS' ]];

  if ($a['featured_only'] === 'true') {
    $meta[] = [ 'key' => 'dest_featured', 'value' => '1', 'compare' => '=' ];
  }
  if ($a['region'] !== 'all') {
    $meta[] = [ 'key' => 'dest_region', 'value' => sanitize_text_field($a['region']), 'compare' => '=' ];
  }

  $q = new WP_Query([
    'post_type' => 'destination',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'meta_query' => $meta,
    'meta_key' => 'dest_order',
    'orderby' => sanitize_key($a['orderby']),
    'order' => in_array(strtoupper($a['order']), ['ASC','DESC']) ? strtoupper($a['order']) : 'ASC',
  ]);

  ob_start();
  if ($q->have_posts()) {
    echo '<div class="pc-grid pc-grid--destinations">';
    while ($q->have_posts()) { $q->the_post();
      $pid = get_the_ID();
      $permalink = get_permalink($pid);
      $title = get_the_title($pid);
      $slogan = function_exists('get_field') ? (string) get_field('dest_slogan', $pid) : '';
      $img_id = function_exists('get_field') ? (int) get_field('dest_hero_desktop', $pid) : 0;
      $thumb = $img_id ? wp_get_attachment_image($img_id, 'large', false, ['loading'=>'lazy', 'class'=>'pc-card__img']) : get_the_post_thumbnail($pid, 'large', ['loading'=>'lazy','class'=>'pc-card__img']);
      echo '<article class="pc-card pc-card--destination">';
      echo $thumb ?: '';
      echo '<div class="pc-card__body">';
      echo '<h3 class="pc-card__title"><a href="'.esc_url($permalink).'">'.esc_html($title).'</a></h3>';
      if ($slogan) echo '<p class="pc-card__excerpt">'.esc_html($slogan).'</p>';
      echo '<p><a class="pc-btn" href="'.esc_url($permalink).'">Découvrir</a></p>';
      echo '</div></article>';
    }
    echo '</div>';
  }
  wp_reset_postdata();
  return ob_get_clean();
}

// JSON-LD: FAQ pour destination (non-destructif, ajouté au <head> sans toucher à ton mu-plugin)
add_action('wp_head', function(){
  if (!is_singular('destination')) return;
  if (!function_exists('get_field')) return;
  $pid = get_queried_object_id();
  $faqs = (array) get_field('dest_faq', $pid);
  if (!$faqs) return;

  $items = [];
  foreach ($faqs as $row){
    $q = trim(wp_strip_all_tags($row['question'] ?? ''));
    $a = trim(wp_strip_all_tags($row['reponse'] ?? ''));
    if ($q && $a) {
      $items[] = [
        '@type' => 'Question',
        'name' => $q,
        'acceptedAnswer' => [
          '@type' => 'Answer',
          'text' => $a
        ]
      ];
    }
  }
  if (!$items) return;

  $data = [
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => $items
  ];
  echo '<script type="application/ld+json">'.wp_json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).'</script>';
}, 30);

/**
 * ===================================================================
 * SHORTCODE [destination_logements_recommandes]
 * ===================================================================
 * Clone du rendu Expérience, mais pour les fiches Destination.
 * Lit le champ ACF: dest_logements_recommandes (IDs de villa/appartement).
 * Usage : [destination_logements_recommandes]
 */
add_shortcode('destination_logements_recommandes', function() {
    if (!is_singular('destination') || !function_exists('get_field')) {
        return '';
    }

    // IDs choisis manuellement dans la fiche Destination
    $recommended_ids = get_field('dest_logements_recommandes');

    if (empty($recommended_ids)) {
        return ''; // Si rien n'est sélectionné, on n'affiche rien
    }

    // On cible les deux types de logement utilisés sur ton site
    $args = [
        'post_type'      => ['villa', 'appartement'],
        'post__in'       => $recommended_ids,
        'posts_per_page' => 3,
        'orderby'        => 'post__in', // respecter l'ordre ACF
        'post_status'    => 'publish',
        'no_found_rows'  => true,
        'ignore_sticky_posts' => true,
    ];
    $query = new WP_Query($args);
    if (!$query->have_posts()) return '';

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
                            // Même logique que le shortcode Expérience : prix de base si dispo
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
});

/**
 * ===================================================================
 * SHORTCODE [destination_infos]
 * ===================================================================
 * Affiche les blocs "Informations pratiques" d'une fiche Destination.
 * - Source ACF: dest_infos (répéteur: titre, contenu, icone)
 * - Titre de section par défaut: "Informations pratiques"
 * - Icônes: toujours affichées
 *   - 1) Si "icone" (classe FA) est renseigné dans ACF → on l'utilise
 *   - 2) Sinon, mapping auto basé sur des mots-clés du titre
 *   - 3) Sinon, fallback générique: fa-solid fa-circle-info
 *
 * Attributs (optionnels):
 *  - title : remplace le H2 (défaut: "Informations pratiques")
 *  - cols_desktop="3" cols_tablet="2" cols_mobile="1"
 *  - max="0" (0 = tous)
 *  - order="as_entered|alpha" (tri alpha sur le titre si "alpha")
 *  - anchor="" (id de section)
 */
add_shortcode('destination_infos', function($atts){
    if (!is_singular('destination') || !function_exists('get_field')) {
        return '';
    }

    $a = shortcode_atts([
        'title'        => 'Informations pratiques',
        'cols_desktop' => '3',
        'cols_tablet'  => '2',
        'cols_mobile'  => '1',
        'max'          => '0',
        'order'        => 'as_entered', // alpha pour tri par titre
        'anchor'       => '',
    ], $atts, 'destination_infos');

    $pid   = get_queried_object_id();
    $rows  = get_field('dest_infos', $pid);
    if (empty($rows) || !is_array($rows)) {
        return '';
    }

    // Tri optionnel "alpha" par titre
    if (strtolower($a['order']) === 'alpha') {
        usort($rows, function($x,$y){
            $tx = isset($x['titre']) ? remove_accents(wp_strip_all_tags($x['titre'])) : '';
            $ty = isset($y['titre']) ? remove_accents(wp_strip_all_tags($y['titre'])) : '';
            return strcasecmp($tx, $ty);
        });
    }

    // Limite optionnelle
    $max = intval($a['max']);
    if ($max > 0 && count($rows) > $max) {
        $rows = array_slice($rows, 0, $max);
    }

    // Mapping sémantique → icônes FA (fallback si vide)
    $map_icons = [
        'plage|bord de mer|plages'     => 'fa-solid fa-umbrella-beach',
        'restaurant|resto|bars'        => 'fa-solid fa-utensils',
        'marché|marche|marches'        => 'fa-solid fa-basket-shopping',
        'golf'                          => 'fa-solid fa-golf-ball-tee',
        'marina|port'                   => 'fa-solid fa-anchor',
        'activite|activites|loisir'     => 'fa-solid fa-person-swimming',
        'randonnee|rando|sentier'       => 'fa-solid fa-person-hiking',
        'transport|bus|taxi|aeroport'   => 'fa-solid fa-bus',
        'famille|enfant'                => 'fa-solid fa-children',
        'commerce|supermarche|boutique' => 'fa-solid fa-store',
        'securite|sante|pharmacie'      => 'fa-solid fa-shield-heart',
        'parking|stationnement'         => 'fa-solid fa-square-parking',
        'meteo|saison|periode'          => 'fa-solid fa-sun',
        'distance|acces|localisation'   => 'fa-solid fa-location-dot',
    ];
    $fallback_icon = 'fa-solid fa-circle-info';

    // Normalisation (accents/espaces) pour la détection de mots-clés
    $normalize = function($s){
        $s = remove_accents( wp_strip_all_tags( (string)$s ) );
        $s = strtolower($s);
        return $s;
    };

    // Attributs de grille (data-*)
    $cols_mobile  = max(1, intval($a['cols_mobile']));
    $cols_tablet  = max(1, intval($a['cols_tablet']));
    $cols_desktop = max(1, intval($a['cols_desktop']));

    $section_id   = sanitize_title($a['anchor']);
    $title        = $a['title'];

    ob_start();
    ?>
    <section class="dest-infos-section" role="region" <?php echo $section_id ? 'id="'.esc_attr($section_id).'"' : ''; ?> aria-labelledby="dest-infos-title">
      <h3 id="dest-infos-title" class="dest-infos-title"><?php echo esc_html($title); ?></h3>

      <div class="dest-infos-grid"
           data-cols-mobile="<?php echo esc_attr($cols_mobile); ?>"
           data-cols-tablet="<?php echo esc_attr($cols_tablet); ?>"
           data-cols-desktop="<?php echo esc_attr($cols_desktop); ?>">

        <?php foreach ($rows as $row):
            $titre   = isset($row['titre']) ? trim($row['titre']) : '';
            $contenu = isset($row['contenu']) ? $row['contenu'] : '';
            $icone   = isset($row['icone']) ? trim($row['icone']) : '';

            // Ignore si tout est vide
            if ($titre === '' && trim(wp_strip_all_tags($contenu)) === '') continue;

            // Icône: ACF > mapping par mots-clés > fallback
            if ($icone === '') {
                $t = $normalize($titre);
                $picked = '';
                foreach ($map_icons as $pattern => $fa) {
                    // pattern simple basé sur strpos après normalisation
                    $found = false;
                    foreach (explode('|', $pattern) as $needle) {
                        if ($needle !== '' && strpos($t, $needle) !== false) { $found = true; break; }
                    }
                    if ($found) { $picked = $fa; break; }
                }
                $icone = $picked ?: $fallback_icon;
            }
            ?>
            <article class="dest-infos-card">
              <div class="dest-infos-icon" aria-hidden="true">
                <i class="<?php echo esc_attr($icone); ?>"></i>
              </div>

              <?php if ($titre !== ''): ?>
                <h4 class="dest-infos-card-title"><?php echo esc_html($titre); ?></h4>
              <?php endif; ?>

              <?php if ($contenu !== ''): ?>
                <div class="dest-infos-card-content">
                  <?php echo wp_kses_post($contenu); ?>
                </div>
              <?php endif; ?>
            </article>
        <?php endforeach; ?>

      </div>
    </section>
    <?php
    return ob_get_clean();
});

/**
 * ===================================================================
 * SHORTCODE [destination_experiences_recommandees]
 * ===================================================================
 * Affiche jusqu'à 3 expériences "à proximité" (sélectionnées dans ACF dest_exp_featured)
 * Rendu identique aux "Logements recommandés" (classes dest-reco-*)
 * Affiche "À partir de …€" en prenant le plus bas "Tarif Adulte" (exp_tarif_adulte)
 */
add_shortcode('destination_experiences_recommandees', function(){
    if (!is_singular('destination') || !function_exists('get_field')) {
        return '';
    }

    $pid_dest = get_queried_object_id();

    // 1) IDs sélectionnés dans la fiche Destination (ACF: dest_exp_featured)
    $featured = (array) get_field('dest_exp_featured', $pid_dest);
    if (empty($featured)) return '';

    // Normaliser en tableau d'IDs
    $ids = [];
    foreach ($featured as $item) {
        if (is_array($item) && isset($item['ID'])) { $ids[] = intval($item['ID']); }
        else { $ids[] = intval($item); }
    }
    $ids = array_filter(array_unique($ids));

    if (!$ids) return '';

    // 2) Requête expériences (respect de l'ordre choisi dans ACF)
    $q = new WP_Query([
        'post_type'      => 'experience',
        'post_status'    => 'publish',
        'post__in'       => $ids,
        'orderby'        => 'post__in',
        'posts_per_page' => 3,
        'no_found_rows'  => true,
        'ignore_sticky_posts' => true,
    ]);
    if (!$q->have_posts()) return '';

    // Titre section
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
                            // 3) Prix "À partir de …€" : min des "Tarif Adulte" des types de tarifs
                            $price_from = null;
                            $types = get_field('exp_types_de_tarifs', get_the_ID());
                            if (is_array($types)) {
                                foreach ($types as $row) {
                                    if (!is_array($row)) continue;
                                    $raw = $row['exp_tarif_adulte'] ?? '';
                                    if ($raw === '' || $raw === null) continue;

                                    // Tolérant aux formats "12,34" / "12.34" / "12 €"
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
                                    À partir de <?php echo esc_html( number_format_i18n($price_from, 0) ); ?>€ 
                                </div>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>
    </section>
    <?php
    return ob_get_clean();
});
