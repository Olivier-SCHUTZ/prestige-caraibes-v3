<?php
/**
 * Plugin Name: PC — Performance Hints (preconnect / preload)
 * Description: Injecte preconnect ciblés + preload (image LCP + polices) selon le type de page et les champs ACF.
 * Author: Prestige Caraïbes
 * Version: 1.1.0
 */

if (!defined('ABSPATH')) exit;

/* =========================
   Helpers (contexte)
   ========================= */

/** Origin (scheme+host) from absolute/protocol-relative URL */
function pc_perf_origin($url){
  if (!$url) return '';
  if (strpos($url, '//') === 0) $url = 'https:'.$url; // support //example.com/...
  $p = @parse_url($url);
  if (empty($p['scheme']) || empty($p['host'])) return '';
  return $p['scheme'].'://'.$p['host'];
}

/** Origin absolue du site (ex: https://prestigecaraibes.com) */
function pc_perf_site_origin(){
  $u = home_url();
  $p = @parse_url($u);
  return (!empty($p['scheme']) && !empty($p['host'])) ? $p['scheme'].'://'.$p['host'] : '';
}

/** Convertit un chemin relatif (ex: /wp-content/...) en URL absolue du site courant */
function pc_perf_resolve_url($u){
  if (!$u) return '';
  // URL absolue
  if (filter_var($u, FILTER_VALIDATE_URL)) return $u;
  // //cdn.example.com/...
  if (strpos($u, '//') === 0) return 'https:'.$u;
  // /wp-content/...
  if ($u[0] === '/') return rtrim(home_url(), '/').$u;
  // wp-content/...
  if (strpos($u, 'wp-content/') === 0) return rtrim(home_url(), '/').'/'.$u;
  return $u;
}

/** Deviner type MIME image depuis l’extension (safe defaults) */
function pc_perf_img_type($url){
  $path = parse_url($url, PHP_URL_PATH);
  $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
  if ($ext === 'webp') return 'image/webp';
  if ($ext === 'avif') return 'image/avif';
  if ($ext === 'jpg' || $ext === 'jpeg') return 'image/jpeg';
  if ($ext === 'png') return 'image/png';
  return 'image/*';
}

/** URL image depuis ACF (accepte URL absolue, chemin relatif, array ACF ou ID) */
function pc_perf_img($acf_key){
  if (!function_exists('get_field')) return '';
  $v = get_field($acf_key);

  // ACF image peut être un array (return array) ou un ID (return id)
  if (is_array($v)) {
    if (!empty($v['url'])) {
      $v = $v['url'];
    } elseif (!empty($v['ID'])) {
      $v = wp_get_attachment_url($v['ID']);
    } else {
      $v = '';
    }
  } elseif (is_numeric($v)) {
    $v = wp_get_attachment_url(intval($v));
  }

  $u = trim((string)$v);
  if (!$u) return '';
  $u = pc_perf_resolve_url($u);
  return filter_var($u, FILTER_VALIDATE_URL) ? $u : '';
}

/* =========================
   Collecte du contexte
   ========================= */

/** Retourne ['lcp_mobile' => url, 'lcp_desktop' => url] selon le type de contenu */
function pc_perf_collect_lcp_images(){
  $m = $d = '';

  // Fiches Logement = logement + villa + appartement
  if (is_singular(array('logement','villa','appartement'))) {
    $d = pc_perf_img('hero_desktop_url');
    $m = pc_perf_img('hero_mobile_url');
  }
  // Fiches Expériences
  elseif (is_singular('experience')) {
    $d = pc_perf_img('exp_hero_desktop');
    $m = pc_perf_img('exp_hero_mobile');
  }
  // Fiches Destinations
  // [PC START] Ajout preload LCP pour la page de recherche de logements
// ===================================================================
// Page de recherche de logements
elseif (is_page('recherche-de-logements')) {
    // On exécute une requête très légère pour trouver la première vignette qui sera affichée par le SSR.
    // On s'assure de réutiliser les filtres de base si présents dans l'URL.
    $args = [
        'post_type'      => ['logement', 'villa', 'appartement'],
        'posts_per_page' => 1,
        'post_status'    => 'publish',
        'fields'         => 'ids',
        'meta_query'     => [],
        'tax_query'      => [],
    ];

    // Si une ville est filtrée en GET, on l'ajoute à la requête pour être précis
    if (!empty($_GET['ville'])) {
        $args['tax_query'][] = [
            'taxonomy' => 'ville',
            'field'    => 'slug',
            'terms'    => sanitize_text_field($_GET['ville']),
        ];
    }

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        $post_id = $query->posts[0];
        if (has_post_thumbnail($post_id)) {
            // On précharge une taille d'image adaptée au LCP mobile/desktop. 'large' est un bon compromis.
            $thumb_url = get_the_post_thumbnail_url($post_id, 'large'); 
            if ($thumb_url) {
                $d = pc_perf_resolve_url($thumb_url);
                $m = $d; // L'image est la même pour les deux vues sur cette page
            }
        }
    }
    wp_reset_postdata();
}
// Ajout preload LCP pour la page de recherche d'expériences
elseif (is_page('recherche-dexperiences')) {
    $initial_category = isset($_GET['categorie']) ? sanitize_text_field($_GET['categorie']) : '';

    $args = [
        'post_type'      => 'experience',
        'posts_per_page' => 1,
        'post_status'    => 'publish',
        'fields'         => 'ids',
    ];

    if (!empty($initial_category)) {
        $args['tax_query'] = [[
            'taxonomy' => 'categorie_experience',
            'field'    => 'slug',
            'terms'    => $initial_category,
        ]];
    }

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        $post_id = $query->posts[0];
        if (has_post_thumbnail($post_id)) {
            $thumb_url = get_the_post_thumbnail_url($post_id, 'medium_large'); 
            if ($thumb_url) {
                $d = pc_perf_resolve_url($thumb_url);
                $m = $d;
            }
        }
    }
    wp_reset_postdata();
}

  elseif (is_singular('destination')) {
    $d = pc_perf_img('dest_hero_desktop');
    $m = pc_perf_img('dest_hero_mobile');
    // (facultatif) fallback desktop vers image mise en avant si ACF vide
    if (empty($d)) {
      $thumb = get_the_post_thumbnail_url(get_queried_object_id(), 'full');
      if ($thumb) $d = pc_perf_resolve_url($thumb);
    }
  }
  // Pages statiques (Accueil / Services)
  elseif (is_page()) {
    $d = pc_perf_img('serv_desktop_url');
    $m = pc_perf_img('serv_mobile_url');
  }
  // Articles : image mise en avant (desktop only)
  elseif (is_single()) {
    $thumb = get_the_post_thumbnail_url(get_queried_object_id(), 'full');
    $d = $thumb ? pc_perf_resolve_url($thumb) : '';
    $m = '';
  }

  // (Optionnel) Fallback mobile → desktop si mobile vide
  if (empty($m) && !empty($d) && defined('PC_PERF_LCP_FALLBACK_MOBILE_TO_DESKTOP') && PC_PERF_LCP_FALLBACK_MOBILE_TO_DESKTOP) {
    $m = $d;
  }

  return [
    'lcp_mobile'  => $m,
    'lcp_desktop' => $d,
  ];
}

/** La page affiche-t-elle une carte Leaflet/OSM ? */
function pc_perf_page_uses_map(){
  if (is_admin() || is_feed()) return false;

  // Pages de recherche
  if (is_page()) {
    $slug = str_replace(trailingslashit(home_url()), '/', trailingslashit(get_permalink()));
    if (strpos($slug, '/recherche-de-logements/') !== false
     || strpos($slug, '/recherche-dexperiences/') !== false) {
      return true;
    }
  }

  // Fiches avec carte : logement + villa + appartement + expérience
  if (is_singular(array('logement','villa','appartement','experience'))) {
    return true;
  }

  return false;
}

/** URLs WOFF2 à précharger (Poppins 600 + Lora Regular) — support URL OU PATH relatifs */
function pc_perf_font_urls(){
  $candidates = [];

  // 1) Variables URL directes (facultatif)
  if (defined('PC_PERF_FONT_POPPINS_600_URL') && PC_PERF_FONT_POPPINS_600_URL) {
    $candidates[] = PC_PERF_FONT_POPPINS_600_URL;
  }
  if (defined('PC_PERF_FONT_LORA_REGULAR_URL') && PC_PERF_FONT_LORA_REGULAR_URL) {
    $candidates[] = PC_PERF_FONT_LORA_REGULAR_URL;
  }

  // 2) Variables PATH relatifs (préférées si présentes)
  if (defined('PC_PERF_FONT_POPPINS_600_PATH') && PC_PERF_FONT_POPPINS_600_PATH) {
    $candidates[] = pc_perf_resolve_url(PC_PERF_FONT_POPPINS_600_PATH);
  }
  if (defined('PC_PERF_FONT_LORA_REGULAR_PATH') && PC_PERF_FONT_LORA_REGULAR_PATH) {
    $candidates[] = pc_perf_resolve_url(PC_PERF_FONT_LORA_REGULAR_PATH);
  }

  // 3) Filtre optionnel développeur
  $candidates = apply_filters('pc_perf_font_urls', $candidates);

  // Filtrage final : URLs valides uniquement, dédup, max 2 (above the fold)
  $out = [];
  foreach ($candidates as $u){
    $u = pc_perf_resolve_url($u);
    if (filter_var($u, FILTER_VALIDATE_URL)) $out[] = $u;
  }
  return array_slice(array_values(array_unique($out)), 0, 2);
}

/** Domaines à preconnect pour la page en cours (max ~6), en excluant l’origin du site */
function pc_perf_collect_preconnect($lcp){
  $hosts = [];

  // GTM + Iubenda (chargés partout)
  $hosts[] = 'https://www.googletagmanager.com';
  $hosts[] = 'https://embeds.iubenda.com';

  // Origines des ressources LCP (si différentes du site)
  foreach (['lcp_mobile','lcp_desktop'] as $k){
    if (!empty($lcp[$k])) {
      $o = pc_perf_origin($lcp[$k]);
      if ($o) $hosts[] = $o;
    }
  }

  // Polices (préloadées) -> préconnecte leur origine
  $fonts = pc_perf_font_urls();
  foreach ($fonts as $f){
    $o = pc_perf_origin($f);
    if ($o) $hosts[] = $o;
  }

  // Carte OSM uniquement si la page l’utilise
  if (pc_perf_page_uses_map()){
    $hosts[] = 'https://tile.openstreetmap.org';
  }

  // Déduplication
  $hosts = array_values(array_unique(array_filter($hosts)));

  // ⚠️ Retire l’origin du site (inutile en preconnect)
  $origin = pc_perf_site_origin();
  $origin_host = $origin ? parse_url($origin, PHP_URL_HOST) : '';
  if ($origin_host){
    $hosts = array_values(array_filter($hosts, function($h) use ($origin_host){
      $h_host = parse_url($h, PHP_URL_HOST);
      return !$h_host || strcasecmp($h_host, $origin_host) !== 0;
    }));
  }

  // Budget max
  if (count($hosts) > 6) $hosts = array_slice($hosts, 0, 6);
  return $hosts;
}

/* =========================
   Émission dans <head>
   ========================= */

function pc_perf_hints_emit(){
  if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST) || wp_doing_ajax()) return;

  $lcp   = pc_perf_collect_lcp_images();
  $hosts = pc_perf_collect_preconnect($lcp);
  $fonts = pc_perf_font_urls();

  // preconnect
  foreach ($hosts as $h){
    echo '<link rel="preconnect" href="'.esc_url($h).'" crossorigin>'."\n";
  }

  // preload polices (si configurées)
  foreach ($fonts as $f){
    echo '<link rel="preload" as="font" type="font/woff2" href="'.esc_url($f).'" crossorigin>'."\n";
  }

  // preload LCP — mobile
  if (!empty($lcp['lcp_mobile'])){
    $t = pc_perf_img_type($lcp['lcp_mobile']);
    echo '<link rel="preload" media="(max-width: 767px)" as="image" href="'.esc_url($lcp['lcp_mobile']).'" fetchpriority="high" type="'.esc_attr($t).'">'."\n";
  }

  // preload LCP — desktop
  if (!empty($lcp['lcp_desktop'])){
    $t = pc_perf_img_type($lcp['lcp_desktop']);
    echo '<link rel="preload" media="(min-width: 768px)" as="image" href="'.esc_url($lcp['lcp_desktop']).'" fetchpriority="high" type="'.esc_attr($t).'">'."\n";
  }
}
add_action('wp_head', 'pc_perf_hints_emit', 1);

/* =========================
   fetchpriority="high"
   ========================= */

// --- Mémo LCP pour attributs IMG ---
$GLOBALS['PC_PERF_LCP_URLS'] = ['m' => '', 'd' => ''];

// Hooke l’émission pour mémoriser les URLs LCP
add_action('wp_head', function(){
  if (function_exists('pc_perf_collect_lcp_images')) {
    $lcp = pc_perf_collect_lcp_images();
    if (!empty($lcp['lcp_mobile']))  $GLOBALS['PC_PERF_LCP_URLS']['m'] = $lcp['lcp_mobile'];
    if (!empty($lcp['lcp_desktop'])) $GLOBALS['PC_PERF_LCP_URLS']['d'] = $lcp['lcp_desktop'];
  }
}, 0);

// Donne la priorité à l’IMG qui matche l’URL LCP (mobile/desktop)
add_filter('wp_get_attachment_image_attributes', function($attr){
  if (empty($attr['src']) || empty($GLOBALS['PC_PERF_LCP_URLS'])) return $attr;
  $u = $attr['src'];
  $lcp = $GLOBALS['PC_PERF_LCP_URLS'];
  // Match souple (srcset peut pointer vers variantes), on compare par nom de fichier
  $is_lcp = false;
  foreach (['m','d'] as $k){
    if (!empty($lcp[$k]) && basename(parse_url($lcp[$k], PHP_URL_PATH)) === basename(parse_url($u, PHP_URL_PATH))) {
      $is_lcp = true; break;
    }
  }
  if ($is_lcp){
    $attr['loading']        = 'eager';
    $attr['decoding']       = 'async';
    $attr['fetchpriority']  = 'high';
  }
  return $attr;
}, 20);

/* =========================
   Nettoyage Google Fonts
   ========================= */

// Retire tout preconnect/dns-prefetch vers Google Fonts
add_filter('wp_resource_hints', function($urls, $relation){
  if (!is_array($urls)) return $urls;
  if ($relation === 'preconnect' || $relation === 'dns-prefetch'){
    $urls = array_filter($urls, function($u){
      return stripos($u, 'fonts.googleapis.com') === false
          && stripos($u, 'fonts.gstatic.com') === false;
    });
  }
  return array_values($urls);
}, 999, 2);

// Bloque l'enqueue de CSS Google Fonts par n'importe quel thème/plugin
add_filter('style_loader_src', function($src){
  if (stripos($src, 'fonts.googleapis.com') !== false || stripos($src, 'fonts.gstatic.com') !== false){
    return false;
  }
  return $src;
}, 10, 1);

// Défile tout style déjà enregistré pointant vers Google Fonts
add_action('wp_enqueue_scripts', function(){
  global $wp_styles;
  if (empty($wp_styles->registered)) return;
  foreach($wp_styles->registered as $handle => $obj){
    $src = isset($obj->src) ? $obj->src : '';
    if ($src && (stripos($src, 'fonts.googleapis.com') !== false || stripos($src, 'fonts.gstatic.com') !== false)){
      wp_dequeue_style($handle);
      wp_deregister_style($handle);
    }
  }
}, 20);

/* =========================
   “Marteau ultime” (retire <link ... Google Fonts> imprimés en dur)
   ========================= */
add_action('template_redirect', function(){
  if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST) || wp_doing_ajax()) return;
  ob_start(function($html){
    return preg_replace('~<link[^>]+(fonts\.googleapis\.com|fonts\.gstatic\.com)[^>]*>~i', '', $html);
  });
}, 0);
add_action('shutdown', function(){
  if (ob_get_level() > 0) @ob_end_flush();
}, 9999);
