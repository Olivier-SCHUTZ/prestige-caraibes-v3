<?php
/* ---------- Fondations CSS globales ---------- */
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('pc-base', content_url('mu-plugins/pc-base.css'), [], '1.0');
}, 5);

/**
 * Plugin Name: Prestige CaraÃ¯bes â€” SEO & Contenu Global (V1)
 * Description: GÃ¨re CPTs, Taxonomies, Shortcodes, Titres, Metas, Schemas, Sitemaps & robots.txt de maniÃ¨re dynamique.
 * Author: PC SEO
 * Version: 1.1
 */

if (!defined('ABSPATH')) { exit; }

/** Destinations â€” Shortcodes (Hub + grilles) */
add_action('plugins_loaded', function () {
    $pc_dest_sc = WPMU_PLUGIN_DIR . '/pc-destination-shortcodes.php';
    if (file_exists($pc_dest_sc)) {
        require_once $pc_dest_sc;
    }
}, 1);

// === Préchargement automatique des polices Elementor auto-hébergées ===
if (!defined('PC_PERF_FONT_POPPINS_600_PATH')) {
  define('PC_PERF_FONT_POPPINS_600_PATH', '/wp-content/uploads/2025/08/Poppins-SemiBold.woff2');
}
if (!defined('PC_PERF_FONT_LORA_REGULAR_PATH')) {
  define('PC_PERF_FONT_LORA_REGULAR_PATH', '/wp-content/uploads/2025/08/Lora-Regular.woff2');
}

add_action('wp_enqueue_scripts', function () {
  if (is_admin()) return;

  $should = ( is_singular('destination')
           || is_post_type_archive('destination')
           || is_tax('destination_cat') );

  if (!$should && is_singular()) {
    $post = get_queried_object();
    if ($post && !empty($post->post_content)) {
      $should = (
        has_shortcode($post->post_content, 'pc_destination_hub') ||
        has_shortcode($post->post_content, 'pc_destination_grid') ||
        has_shortcode($post->post_content, 'destination_logements_recommandes') ||
        has_shortcode($post->post_content, 'destination_infos') ||
        has_shortcode($post->post_content, 'destination_experiences_recommandees') ||
        has_shortcode($post->post_content, 'pc_destination_logements') ||
        has_shortcode($post->post_content, 'pc_destination_experiences')
      );
    }
  }

  if ($should) {
    $path = WPMU_PLUGIN_DIR . '/assets/pc-destination.css';
    $url  = WPMU_PLUGIN_URL . '/assets/pc-destination.css';
    if (file_exists($path)) {
      wp_enqueue_style('pc-destination', $url, [], filemtime($path), 'all');
    }
  }
}, 30);

add_filter('upload_mimes', function($m){ $m['svg']='image/svg+xml'; return $m; });

function pc_enqueue_external_libraries() {
    if ( ! is_singular(['villa', 'appartement', 'logement', 'experience']) && ! is_page(['reserver', 'demande-sejour', 'recherche-de-logements', 'recherche-dexperiences']) ) {
        return;
    }
    wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
    wp_enqueue_style('flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css');
    wp_enqueue_style('glightbox-css', 'https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css');
    wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], null, true);
    wp_enqueue_script('flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr', [], null, true);
    wp_enqueue_script('flatpickr-fr', 'https://npmcdn.com/flatpickr/dist/l10n/fr.js', ['flatpickr-js'], null, true);
    wp_enqueue_script('glightbox-js', 'https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js', [], null, true);
    wp_enqueue_script('jspdf', 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js', [], null, true);
}
add_action('wp_enqueue_scripts', 'pc_enqueue_external_libraries');

/*
 * Chargement des composants pour les grilles Elementor (vignette logement)
 *
 * Charge le shortcode de la vignette et son CSS uniquement sur la page
 * d'accueil et les pages catégories de logements spécifiées.
 */
add_action('wp_enqueue_scripts', 'pc_load_loop_grid_components');
function pc_load_loop_grid_components() {

    // Liste des slugs des pages où charger les composants.
    $target_pages = [
        'location-villa-en-guadeloupe',
        'location-villa-de-luxe-en-guadeloupe',
        'location-grande-villa-en-guadeloupe',
        'promotion-villa-en-guadeloupe',
        'location-appartement-en-guadeloupe'
    ];

    // Condition : charger uniquement sur la page d'accueil OU sur l'une des pages cibles.
    if (is_front_page() || is_page($target_pages)) {
        
        // 1. Charger le fichier PHP qui définit le shortcode [pc_loop_lodging_card]
        $php_path = __DIR__ . '/pc-loop-components.php';
        if (file_exists($php_path)) {
            require_once $php_path;
        }

        // 2. Charger le fichier CSS pour la vignette
        // Ce CSS dépendra des variables globales chargées par pc-base.css
        $style_path = __DIR__ . '/assets/pc-loop-card.css';
        if (file_exists($style_path)) {
            $style_url = plugin_dir_url(__FILE__) . 'assets/pc-loop-card.css';
            $version = filemtime($style_path);
            wp_enqueue_style('pc-loop-card-style', $style_url, ['pc-base'], $version); // On déclare la dépendance à 'pc-base'
        }
    }
}

if (!function_exists('pc_faq_already_printed')) {
  function pc_faq_already_printed(){ return !empty($GLOBALS['pc_faq_printed']); }
}
if (!function_exists('pc_mark_faq_printed')) {
  function pc_mark_faq_printed(){ $GLOBALS['pc_faq_printed'] = true; }
}

/* ============================================================
 * 2. MOTEUR SEO 100% DYNAMIQUE
 * Ã‰TAPE 1 â€” Crawl & indexation : Sitemaps / robots.txt / noindex / 410 / Canonical Guard
 * ------------------------------------------------------------
 * Coller ce bloc Ã  partir de la ligne 64.
 * DÃ©pendances : aucune (ACF optionnel). Fonctionne mÃªme sans ACF.
 * ============================================================ */

/** Helpers sÃ»rs (ACF facultatif) */
if (!function_exists('pcseo_get_field')) {
	function pcseo_get_field($key, $post_id = null, $default = '') {
		if (function_exists('get_field')) {
			$val = get_field($key, $post_id);
			return ($val === null || $val === '') ? $default : $val;
		}
		if ($post_id && is_numeric($post_id)) {
			$val = get_post_meta($post_id, $key, true);
			return ($val === null || $val === '') ? $default : $val;
		}
		return $default;
	}
}
if (!function_exists('pcseo_truthy')) {
	function pcseo_truthy($v) {
		return in_array(strtolower(trim((string)$v)), ['1','yes','on','true','vrai','oui'], true);
	}
}

/** ===============================
 * Helpers champs ACF multi-CPT
 * =============================== */
if (!function_exists('pcseo_field_prefix_for')) {
  function pcseo_field_prefix_for($post_type){
    switch ($post_type) {
      case 'post':         return 'post';
      case 'villa':
      case 'appartement':  return 'log';
      case 'experience':   return 'exp';
      case 'destination':
      case 'pc_destination':
      case 'destinations': return 'dest';
      case 'page':
      default:             return 'pc';
    }
  }
}
if (!function_exists('pcseo_truthy')) {
  function pcseo_truthy($v){
    $v = strtolower(trim((string)$v));
    return in_array($v, ['1','on','yes','true','vrai','oui'], true) || $v === '1';
  }
}
/**
 * Lecture ACF/META robuste (gÃ¨re subfield ET group_subfield)
 * $suffix âˆˆ { 'meta_robots', 'exclude_sitemap', 'http_410', ... }
 */
if (!function_exists('pcseo_get_meta')) {
  function pcseo_get_meta($post_id, $suffix){
    $post_id = (int) $post_id;
    if (!$post_id) return '';

    $pt     = get_post_type($post_id);
    $prefix = function_exists('pcseo_field_prefix_for') ? pcseo_field_prefix_for($pt) : 'pc';

    // clÃ©s candidates (subfield direct)
    $key_sub = "{$prefix}_{$suffix}";

    // clÃ©s candidates (group + subfield)
    $group   = "{$prefix}_seo_overrides";
    // 2 variantes frÃ©quentes selon versions ACF / historiques de sauvegarde
    $key_group_sub1 = "{$group}_{$prefix}_{$suffix}"; // ex: dest_seo_overrides_dest_meta_robots
    $key_group_sub2 = "{$group}_{$suffix}";           // ex: dest_seo_overrides_meta_robots

    // Ordre d'essai (ACF -> post_meta) sur toutes les clÃ©s candidates
    $candidates = [$key_sub, $key_group_sub1, $key_group_sub2];

    foreach ($candidates as $k){
      if (function_exists('get_field')) {
        $v = get_field($k, $post_id);
        if ($v !== null && $v !== false && $v !== '') return $v;
      }
      $v = get_post_meta($post_id, $k, true);
      if ($v !== '' && $v !== null) return $v;
    }

    // Dernier filet : si ACF sait renvoyer le group sous forme de tableau, on lit dedans
    if (function_exists('get_field')) {
      $grp = get_field($group, $post_id);
      if (is_array($grp)) {
        // le nom du subfield dans lâ€™export est bien 'dest_meta_robots' pour Destination, etc.
        $sub_name = "{$prefix}_{$suffix}"; // ex: dest_meta_robots
        if (array_key_exists($sub_name, $grp) && $grp[$sub_name] !== '') {
          return $grp[$sub_name];
        }
        // fallback ultra dÃ©fensif (au cas oÃ¹) : essaye sans prefix dans le tableau
        if (array_key_exists($suffix, $grp) && $grp[$suffix] !== '') {
          return $grp[$suffix];
        }
      }
    }

    return '';
  }
}
if (!function_exists('pcseo_is_noindex')) {
  function pcseo_is_noindex($robots_value){
    return is_string($robots_value) && stripos($robots_value, 'noindex') !== false;
  }
}

/* ===============================
 * Fonctions PONT sur les helpers pcseo_*
 * (compatibilitÃ© avec le reste du fichier)
 * =============================== */
if (!function_exists('pc_get_meta_robots')) {
  function pc_get_meta_robots($post_id, $post_type = null){
    return (string) pcseo_get_meta($post_id, 'meta_robots');
  }
}
if (!function_exists('pc_is_excluded_from_sitemap')) {
  function pc_is_excluded_from_sitemap($post_id, $post_type = null){
    return pcseo_truthy( pcseo_get_meta($post_id, 'exclude_sitemap') );
  }
}
if (!function_exists('pc_is_http_410')) {
  function pc_is_http_410($post_id, $post_type = null){
    return pcseo_truthy( pcseo_get_meta($post_id, 'http_410') );
  }
}

/* ============================================================
 * PCSEO â€” DÃ©tecter si un mÃ©ta ACF est VRAIMENT enregistrÃ© (pas la valeur par dÃ©faut)
 * GÃ¨re les deux formes : subfield direct et group_subfield.
 * $suffix âˆˆ { 'meta_robots','exclude_sitemap','http_410', ... }
 * ============================================================ */
if (!function_exists('pcseo_meta_exists')) {
  function pcseo_meta_exists($post_id, $suffix){
    $pt     = get_post_type($post_id);
    $prefix = function_exists('pcseo_field_prefix_for') ? pcseo_field_prefix_for($pt) : 'pc';

    // ClÃ©s possibles
    $key_sub       = "{$prefix}_{$suffix}";
    $group         = "{$prefix}_seo_overrides";
    $key_group_sub = "{$group}_{$prefix}_{$suffix}"; // ex: dest_seo_overrides_dest_meta_robots
    $key_group_sub2= "{$group}_{$suffix}";           // ex: dest_seo_overrides_meta_robots

    foreach ([$key_sub, $key_group_sub, $key_group_sub2] as $k) {
      if (metadata_exists('post', $post_id, $k)) return true;
    }
    return false;
  }
}

/* ============================================================
 * 2.A â€” Colonne "Indexation" en admin (liste des posts)
 * ============================================================ */

if ( is_admin() ) {

  if ( ! function_exists('pcseo_admin_indexation_types') ) {
    function pcseo_admin_indexation_types(){
      return array('page','post','villa','appartement','experience','destination');
    }
  }

  foreach ( pcseo_admin_indexation_types() as $pt ){

    // Ajout de la colonne
    add_filter("manage_{$pt}_posts_columns", function($cols){
      $out = array();
      $inserted = false;
      foreach ($cols as $k=>$v){
        $out[$k] = $v;
        if ($k === 'title'){
          $out['pc_indexation'] = 'Indexation';
          $inserted = true;
        }
      }
      if (!$inserted){
        $out['pc_indexation'] = 'Indexation';
      }
      return $out;
    });

    // Rendu de la colonne
    add_action("manage_{$pt}_posts_custom_column", function($col, $post_id) use ($pt){
  if ($col !== 'pc_indexation') return;

  $robots = function_exists('pcseo_get_meta') ? (string) pcseo_get_meta($post_id, 'meta_robots') : '';
  $exclude= function_exists('pcseo_get_meta') ? pcseo_get_meta($post_id, 'exclude_sitemap') : '';
  $gone  = function_exists('pcseo_get_meta') ? pcseo_get_meta($post_id, 'http_410') : '';

  // Robots effectif = robots explicite SI le champ a Ã©tÃ© SAISI en base,
  // sinon fallback exclude => noindex,follow, sinon index,follow.
  $robots_norm = strtolower(str_replace(' ','', $robots));
  $has_robots_set = function_exists('pcseo_meta_exists') ? pcseo_meta_exists($post_id, 'meta_robots') : false;

  if (pcseo_truthy($exclude) && !pcseo_is_noindex($robots_norm)) {
    // prioritÃ© Ã  EXCLUDE : force "noindex,follow" mÃªme si robots vaut "index,follow" par dÃ©faut
    $effective = 'noindex,follow';
  } elseif ($has_robots_set && $robots_norm !== '') {
    // robots explicitement SAISI en base : on respecte
    $effective = $robots_norm;
  } else {
    // dÃ©faut : index,follow
    $effective = 'index,follow';
  }

  $badge = function($txt, $type='muted'){
    $bg = ($type==='ok')?'#E6FFE8':(($type==='warn')?'#FFF7E6':(($type==='err')?'#FFECEC':'#F3F4F6'));
    $fg = ($type==='ok')?'#0E7A2E':(($type==='warn')?'#8A5100':(($type==='err')?'#9B1C1C':'#374151'));
    return '<span style="display:inline-block;padding:.2em .5em;border-radius:6px;background:'.$bg.';color:'.$fg.';font-size:12px;">'.$txt.'</span>';
  };

  // Affichage
  $is_noindex = (strpos($effective, 'noindex') === 0);
  echo $badge($effective, $is_noindex ? 'warn' : 'ok').' ';
  if (pcseo_truthy($exclude)) echo $badge('excl. sitemap','warn').' ';
  if (pcseo_truthy($gone))    echo $badge('410','err').' ';
}, 10, 2);


    // (Optionnel) triable : laissÃ© neutre pour ne pas dÃ©grader la perf
    add_filter("manage_edit-{$pt}_sortable_columns", function($cols){
      $cols['pc_indexation'] = 'pc_indexation';
      return $cols;
    });
    add_action('pre_get_posts', function($q){
      if (!is_admin() || !$q->is_main_query()) return;
      if ($q->get('orderby') !== 'pc_indexation') return;
      // Pas de tri spÃ©cifique (Ã©vite un meta-join global)
    });

  } // foreach
}


/* ============================================================
 * 2.B â€” Meta robots (sortie unique via wp_robots) â€” v5 (prioritÃ© EXCLUDE)
 * ============================================================ */
add_filter('wp_robots', function(array $robots){

  // Utilitaire : imposer EXACTEMENT la directive souhaitÃ©e (sans extras WP)
  $set_exact = function(array &$robots, string $dir){
    // Neutraliser les directives ajoutÃ©es par WP
    $robots['max-image-preview'] = null;
    $robots['max-snippet']       = null;
    $robots['max-video-preview'] = null;
    $robots['noarchive']         = null;
    $robots['nosnippet']         = null;

    // Normaliser & poser les 4 flags standard
    $dir = strtolower(str_replace(' ', '', $dir));
    $noindex  = strpos($dir, 'noindex')  !== false;
    $nofollow = strpos($dir, 'nofollow') !== false;

    $robots['noindex']  = $noindex ?: null;
    $robots['index']    = $noindex ? null : true;
    $robots['nofollow'] = $nofollow ?: null;
    $robots['follow']   = $nofollow ? null : true;
  };

  // 1) Pages de recherche : forcer proprement noindex,follow
  if ( is_search() ) {
    $set_exact($robots, 'noindex,follow');
    return $robots;
  }

  // 2) Contenus singuliers : directive explicite + prioritÃ© Ã  EXCLUDE
  if ( is_singular() ) {
    $id = get_queried_object_id();
    if ($id) {
      // Valeur robots (ACF) + info "le champ a-t-il Ã©tÃ© rÃ©ellement saisi ?"
      $robots_val = function_exists('pcseo_get_meta') ? (string) pcseo_get_meta($id, 'meta_robots') : '';
      $robots_val = strtolower(str_replace(' ', '', trim($robots_val)));
      $has_robots_set = function_exists('pcseo_meta_exists') ? pcseo_meta_exists($id, 'meta_robots') : false;

      // Exclude sitemap ?
      $exclude_raw = function_exists('pcseo_get_meta') ? pcseo_get_meta($id, 'exclude_sitemap') : '';
      $exclude     = function_exists('pcseo_truthy') ? pcseo_truthy($exclude_raw) : false;

      // ðŸŸ¡ PRIORITÃ‰ EXCLUDE : si exclude=1 et robots n'est pas dÃ©jÃ  un "noindex",
      // on force noindex,follow (mÃªme si ACF a "index,follow" par dÃ©faut).
      if ($exclude && function_exists('pcseo_is_noindex') && !pcseo_is_noindex($robots_val)) {
        $set_exact($robots, 'noindex,follow');
        return $robots;
      }

      // Si une valeur robots a Ã©tÃ© explicitement SAISIE en base â†’ on respecte
      if ($has_robots_set && $robots_val !== '') {
        $set_exact($robots, $robots_val);
        return $robots;
      }

      // DÃ©faut : index,follow explicite (Ã©vite d'avoir uniquement max-image-preview:large)
      $set_exact($robots, 'index,follow');
      return $robots;
    }
  }

  // 3) Autres contextes (archives, etc.) : laisser WP faire
  return $robots;

}, 999);


/* -----------------------------------------
 * SITEMAPS XML : BASELINE SAFE (multi-CPT)
 * ----------------------------------------- */

/* Activer le sitemap Core (si un autre plugin le coupe) */
add_filter('wp_sitemaps_enabled', '__return_true', 99);

/* Retirer complÃ¨tement "users" et "taxonomies" de lâ€™index */
add_filter('wp_sitemaps_add_provider', function($provider, $name){
  if ($name === 'users' || $name === 'taxonomies') return false;
  return $provider;
}, 10, 2);

/* SÃ©curitÃ© : mÃªme si un plugin rÃ©active les taxos, on neutralise la liste */
add_filter('wp_sitemaps_taxonomies', function($taxonomies){
  return []; // aucune taxonomy exposÃ©e
}, 10, 1);

/* Limiter les post types exposÃ©s (et ne jamais retourner un tableau vide) */
add_filter('wp_sitemaps_post_types', function($post_types){
  // Garde seulement ceux dÃ©jÃ  enregistrÃ©s ET prÃ©sents dans la whitelist
  $whitelist = ['page','post','villa','appartement','experience','destination'];
  $keep = array_intersect_key($post_types, array_flip($whitelist));

  // Ne JAMAIS casser lâ€™index : sâ€™il nâ€™en reste aucun, on renvoie lâ€™original
  return !empty($keep) ? $keep : $post_types;
}, 10, 1);

/* PrÃ©-filtre : exclure via *_exclude_sitemap (multi-CPT + compat legacy)
   â€” pas de logique "noindex" ici pour lâ€™instant (stabilitÃ©) */
add_filter('wp_sitemaps_posts_query_args', function($args, $post_type){
  if (!function_exists('pcseo_field_prefix_for')) return $args;

  $prefix = pcseo_field_prefix_for($post_type);
  if (!$prefix) return $args; // garde-fou

  $truthy = ['1','yes','on','true','vrai','oui'];

  // clÃ©s multi-CPT + compat ancienne clÃ© (pc_exclude_sitemap)
  $keys = ["{$prefix}_exclude_sitemap", 'pc_exclude_sitemap'];

  // On ne garde QUE les posts oÃ¹ AUCUNE de ces clÃ©s nâ€™est cochÃ©e
  $mq = ['relation' => 'AND'];
  foreach ($keys as $key){
    $mq[] = [
      'relation' => 'OR',
      ['key' => $key, 'compare' => 'NOT EXISTS'],
      ['key' => $key, 'value' => $truthy, 'compare' => 'NOT IN'],
    ];
  }

  // Si un autre plugin a dÃ©jÃ  posÃ© une meta_query, on merge proprement
  if (!empty($args['meta_query']) && is_array($args['meta_query'])) {
    $args['meta_query'] = array_merge(['relation' => 'AND'], $args['meta_query'], [$mq]);
  } else {
    $args['meta_query'] = $mq;
  }

  return $args;
}, 10, 2);

/* IMPORTANT :
   - On NE met PAS de "filet post-query" (wp_sitemaps_posts / _entry) ici,
     pour Ã©viter tout risque de trous/lignes vides et garder lâ€™index stable.
   - On ne filtre PAS encore sur *_meta_robots = noindex Ã  ce stade (on le fera
     aprÃ¨s avoir validÃ© le retour Ã  la normale, de prÃ©fÃ©rence en prÃ©-requÃªte).
*/


/* ============================================================
 * 2.D â€” HTTP 410 Multi-CPT (v2, rendu propre + robots)
 * ============================================================ */

/**
 * Si *_http_410 est cochÃ© sur un contenu singulier :
 * - renvoie le statut 410
 * - force X-Robots-Tag: noindex,follow
 * - imprime <meta name="robots" content="noindex,follow"> via wp_robots
 * - tente un template dÃ©diÃ© 410.php (thÃ¨me/child), sinon fallback 404.php,
 *   sinon un petit message propre avec lien retour accueil.
 */
add_action('template_redirect', function () {
  if (is_admin() || is_preview() || is_customize_preview()) return;
  if (!is_singular()) return;

  $id = get_queried_object_id(); if (!$id) return;

  // Flag 410 activÃ© ?
  $gone = function_exists('pcseo_get_meta') ? pcseo_get_meta($id, 'http_410') : '';
  if (!function_exists('pcseo_truthy') || !pcseo_truthy($gone)) return;

  // 1) En-tÃªtes HTTP + anti-cache
  status_header(410);              // Status: 410 Gone
  header('X-Robots-Tag: noindex, follow', true); // Assure le noindex cÃ´tÃ© header
  nocache_headers();

  // 2) Forcer la meta robots noindex,follow dans le <head>
  add_filter('wp_robots', function(array $robots){
    // neutralise les extras WP
    $robots['max-image-preview'] = null;
    $robots['max-snippet']       = null;
    $robots['max-video-preview'] = null;
    $robots['noarchive']         = null;
    $robots['nosnippet']         = null;
    // impose noindex,follow
    $robots['noindex']  = true;
    $robots['index']    = null;
    $robots['nofollow'] = null;
    $robots['follow']   = true;
    return $robots;
  }, 999);

  // 3) Variables dispo dans le template
  set_query_var('pcseo_is_410', true);
  set_query_var('pcseo_410_message', 'Cette page nâ€™existe plus ou a Ã©tÃ© supprimÃ©e.');

  // 4) Template : 410.php > gone.php > 404.php > fallback minimal
  $tpl = '';
  if (function_exists('locate_template')) {
    $tpl = locate_template(['410.php', 'gone.php', '404.php'], false, false);
  }

  if ($tpl) {
    include $tpl; // les templates du thÃ¨me appellent get_header()/get_footer()
  } else {
    // Fallback minimal, propre et responsive
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!doctype html>
    <html lang="fr">
      <head>
        <meta charset="utf-8">
        <title>410 â€” Cette page nâ€™existe plus</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>
          body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:0;background:#fff;color:#111;}
          .wrap{max-width:740px;margin:12vh auto;padding:0 24px;text-align:center}
          h1{font-size:clamp(28px,4vw,36px);margin:0 0 .5em}
          p{font-size:clamp(16px,2.2vw,18px);color:#444;margin:.5em 0}
          a.btn{display:inline-block;margin-top:1em;padding:.7em 1.1em;border-radius:8px;border:1px solid #e5e7eb;text-decoration:none}
          a.btn:hover{background:#f9fafb}
        </style>
      </head>
      <body>
        <main class="wrap">
          <h1>Cette page nâ€™existe plus</h1>
          <p>Le contenu demandÃ© a Ã©tÃ© retirÃ© ou nâ€™est plus disponible.</p>
          <p><a class="btn" href="<?php echo esc_url(home_url('/')); ?>">â† Retour Ã  lâ€™accueil</a></p>
        </main>
      </body>
    </html>
    <?php
  }
  exit;
}, 2);

/* ============================================================
 * 2.E â€” HTML Sitemap Shortcode (+ JSON-LD sur /plan-du-site/)
 * Shortcode :
 *   [pc_html_sitemap
 *      show="pages,destinations,logements,experiences,articles"
 *      depth="2"
 *      limit_posts="50"
 *      exclude_ids=""
 *      include_slugs=""
 *      exclude_slugs=""
 *   ]
 * RÃ¨gles :
 *  - Aligne UX avec sitemap XML, MAIS on peut forcer lâ€™inclusion de pages noindex via include_slugs.
 *  - exclude_slugs masque des pages (ex : â€œreserverâ€, â€œdemande-dinformationâ€).
 *  - AUCUN doublon entre sections (mÃ©mo par ID et par slug).
 * DÃ©pendances : pcseo_post_is_excluded_from_sitemap(), pcseo_detect_post_type() si dÃ©jÃ  dÃ©finies plus haut.
 * ============================================================ */

/** Fallbacks doux si les helpers n'existent pas dÃ©jÃ  */
if (!function_exists('pcseo_post_is_excluded_from_sitemap')) {
  function pcseo_post_is_excluded_from_sitemap($post) {
    if (!$post) return true;
    if ($post->post_status !== 'publish') return true;
    if (!empty($post->post_password)) return true;

    $pid = $post->ID;
    // 410 ?
    $http410 = pcseo_get_meta($pid, 'http_410');
    if (pcseo_truthy($http410)) return true;

    // robots noindex ?
    $robots = pcseo_get_meta($pid, 'meta_robots');
    if (pcseo_is_noindex($robots)) return true;

    // exclude sitemap ?
    $exclude = pcseo_get_meta($pid, 'exclude_sitemap');
    if (pcseo_truthy($exclude)) return true;

    return false;
  }
}
if (!function_exists('pcseo_detect_post_type')) {
    function pcseo_detect_post_type($candidates) {
        foreach ($candidates as $pt) if (post_type_exists($pt)) return $pt;
        return null;
    }
}
/** RÃ©cupÃ©ration de pages par slugs (pour include_slugs) */
if (!function_exists('pcseo_get_posts_by_slugs')) {
	function pcseo_get_posts_by_slugs($slugs = []) {
		if (empty($slugs)) return [];
		return get_posts([
			'post_type'      => 'page',
			'name__in'       => $slugs,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'post__in',
		]);
	}
}

/** GÃ©nÃ©rateur principal : retourne [ 'html' => ..., 'urls' => [...] ] */
if (!function_exists('pcseo_build_html_sitemap')) {
	function pcseo_build_html_sitemap($atts = []) {

		$defaults = [
			'show'           => 'pages,destinations,logements,experiences,articles',
			'depth'          => 2,      // profondeur de lâ€™arbo â€œPagesâ€
			'limit_posts'    => 50,     // pour Magazine
			'exclude_ids'    => '',     // CSV dâ€™IDs Ã  masquer
			'include_slugs'  => '',     // slugs Ã  AJOUTER mÃªme si noindex/exclu
			'exclude_slugs'  => '',     // slugs Ã  MASQUER (ex : reserver)
		];
		$a = shortcode_atts($defaults, $atts, 'pc_html_sitemap');

		$sections      = array_filter(array_map('trim', explode(',', strtolower($a['show']))));
		$depth         = max(1, (int)$a['depth']);
		$limit         = max(1, (int)$a['limit_posts']);
		$exclude_ids   = array_filter(array_map('intval', explode(',', $a['exclude_ids'])));
		$exclude_ids   = array_combine($exclude_ids, $exclude_ids); // lookup rapide
		$include_slugs = array_values(array_unique(array_filter(array_map('trim', explode(',', strtolower($a['include_slugs']))))));
		$exclude_slugs = array_values(array_unique(array_filter(array_map('trim', explode(',', strtolower($a['exclude_slugs']))))));

		// Cache (inclut tous les paramÃ¨tres pour Ã©viter un rendu "figÃ©")
		$key    = 'pcseo_html_sitemap_' . md5(json_encode([$sections,$depth,$limit,$exclude_ids,$include_slugs,$exclude_slugs]));
		$cached = get_transient($key);
		if (is_array($cached) && isset($cached['html'], $cached['urls'])) return $cached;

		$html = [];
		$urls = [];

		// Anti-doublons cross-sections
		$already_listed_ids   = [];
		$already_listed_slugs = [];

		$html[] = '<nav class="pc-html-sitemap" aria-label="Plan du site"><div class="pc-html-sitemap__grid">';

		/* ============ PAGES (arbo) ============ */
		if (in_array('pages', $sections, true)) {
			$all_pages = get_pages([
				'sort_column' => 'menu_order,post_title',
				'sort_order'  => 'ASC',
				'post_status' => ['publish'],
			]);
			$by_id = []; $childs = [];
			foreach ($all_pages as $p) {
				if (isset($exclude_ids[$p->ID])) continue;
				$slug = sanitize_title($p->post_name);
				if (in_array($slug, $exclude_slugs, true)) continue;
				$by_id[$p->ID] = $p;
				$parent = (int)$p->post_parent;
				$childs[$parent][] = $p->ID;
			}

			$print_tree = function($parent_id, $level) use (&$print_tree, $childs, $by_id, $depth, &$urls, $exclude_ids, &$already_listed_ids, &$already_listed_slugs, $exclude_slugs){
				if ($level > $depth || empty($childs[$parent_id])) return '';
				$out = '<ul>';
				foreach ($childs[$parent_id] as $cid) {
					$p = $by_id[$cid] ?? null;
					if (!$p) continue;

					$slug = sanitize_title($p->post_name);
					if (in_array($slug, $exclude_slugs, true)) continue;
					if (isset($exclude_ids[$p->ID])) continue;
					if (pcseo_post_is_excluded_from_sitemap($p)) continue;

					$link  = get_permalink($p);
					$title = esc_html(get_the_title($p));
					$out  .= '<li><a href="'.esc_url($link).'">'.$title.'</a>';

					$urls[] = $link;
					$already_listed_ids[$p->ID]  = true;
					$already_listed_slugs[$slug] = true;

					if (!empty($childs[$cid])) $out .= $print_tree($cid, $level + 1);
					$out .= '</li>';
				}
				$out .= '</ul>';
				return $out;
			};

			$tree = $print_tree(0, 1);
			if ($tree !== '' && $tree !== '<ul></ul>') {
				$html[] = '<section class="pc-html-sitemap__section">';
				$html[] = '<h2>Pages</h2>';
				$html[] = $tree;
				$html[] = '</section>';
			}
		}

		/* ---- Ajout des pages manuelles (include_slugs) ----
		 * Affiche mÃªme si noindex/exclu sitemap (VOLONTAIRE), mais respecte exclude_slugs et sans doublon.
		 */
		if (!empty($include_slugs)) {
			$manual_pages = pcseo_get_posts_by_slugs($include_slugs);
			if (!empty($manual_pages)) {
				$items = [];
				foreach ($manual_pages as $p) {
					if (!$p || $p->post_type !== 'page') continue;
					$slug = sanitize_title($p->post_name);
					if (in_array($slug, $exclude_slugs, true)) continue;
					if (!empty($already_listed_ids[$p->ID]) || !empty($already_listed_slugs[$slug])) continue; // pas de doublons

					$link = get_permalink($p);
					$items[$slug] = '<li><a href="'.esc_url($link).'">'.esc_html(get_the_title($p)).'</a></li>';

					$urls[] = $link;
					$already_listed_ids[$p->ID]  = true;
					$already_listed_slugs[$slug] = true;
				}
				if (!empty($items)) {
					// respecter l'ordre donnÃ© dans include_slugs
                    $ordered = [];
                    foreach ($include_slugs as $s) if (isset($items[$s])) $ordered[] = $items[$s];

					if (!empty($ordered)) {
						$html[] = '<section class="pc-html-sitemap__section">';
						$html[] = '<h2>À propos & infos pratiques</h2>';
						$html[] = '<ul>'.implode('', $ordered).'</ul>';
						$html[] = '</section>';
					}
				}
			}
		}

		/* ============ DESTINATIONS ============ */
		if (in_array('destinations', $sections, true)) {
			$pt = pcseo_detect_post_type(['pc_destination','destination']);
			if ($pt) {
				$q = new WP_Query([
					'post_type'      => $pt,
					'posts_per_page' => -1,
					'post_status'    => 'publish',
					'orderby'        => 'title',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				]);
				$list = [];
				if ($q->have_posts()) {
					$list[] = '<ul>';
					while ($q->have_posts()) { $q->the_post();
						$p = get_post();
						$slug = sanitize_title($p->post_name);
						if (in_array($slug, $exclude_slugs, true)) continue;
						if (pcseo_post_is_excluded_from_sitemap($p)) continue;
						if (!empty($already_listed_ids[$p->ID]) || !empty($already_listed_slugs[$slug])) continue;

						$link = get_permalink($p);
						$list[] = '<li><a href="'.esc_url($link).'">'.esc_html(get_the_title()).'</a></li>';
						$urls[] = $link;
						$already_listed_ids[$p->ID]  = true;
						$already_listed_slugs[$slug] = true;
					}
					$list[] = '</ul>';
					wp_reset_postdata();
				}
				if (!empty($list)) {
					$html[] = '<section class="pc-html-sitemap__section">';
					$html[] = '<h2>Destinations</h2>';
					$html[] = implode('', $list);
					$html[] = '</section>';
				}
			}
		}

		/* ============ LOGEMENTS (v2 - Gère explicitement villa + appartement) ============ */
if (in_array('logements', $sections, true)) {
    // On définit explicitement la liste de tous les types de contenus "logement"
    $logement_post_types = [];
    $candidates = ['villa', 'appartement', 'logement', 'pc_logement'];
    foreach ($candidates as $c) {
        if (post_type_exists($c)) {
            $logement_post_types[] = $c;
        }
    }

    if (!empty($logement_post_types)) {
        $q = new WP_Query([
            'post_type'      => $logement_post_types, // On passe le tableau ['villa', 'appartement']
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ]);
        $list = [];
        if ($q->have_posts()) {
            $list[] = '<ul>';
            while ($q->have_posts()) { $q->the_post();
                $p = get_post();
                $slug = sanitize_title($p->post_name);
                if (in_array($slug, $exclude_slugs, true)) continue;
                if (pcseo_post_is_excluded_from_sitemap($p)) continue;
                if (!empty($already_listed_ids[$p->ID]) || !empty($already_listed_slugs[$slug])) continue;

                $link = get_permalink($p);
                $list[] = '<li><a href="'.esc_url($link).'">'.esc_html(get_the_title()).'</a></li>';
                $urls[] = $link;
                $already_listed_ids[$p->ID]  = true;
                $already_listed_slugs[$slug] = true;
            }
            $list[] = '</ul>';
            wp_reset_postdata();
        }
        if (count($list) > 2) { // S'assurer que la liste n'est pas vide
            $html[] = '<section class="pc-html-sitemap__section">';
            $html[] = '<h2>Logements</h2>';
            $html[] = implode('', $list);
            $html[] = '</section>';
        }
    }
}

		/* ============ EXPÃ‰RIENCES ============ */
		if (in_array('experiences', $sections, true)) {
			$pt = pcseo_detect_post_type(['pc_experience','experience']);
			if ($pt) {
				$q = new WP_Query([
					'post_type'      => $pt,
					'posts_per_page' => -1,
					'post_status'    => 'publish',
					'orderby'        => 'title',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				]);
				$list = [];
				if ($q->have_posts()) {
					$list[] = '<ul>';
					while ($q->have_posts()) { $q->the_post();
						$p = get_post();
						$slug = sanitize_title($p->post_name);
						if (in_array($slug, $exclude_slugs, true)) continue;
						if (pcseo_post_is_excluded_from_sitemap($p)) continue;
						if (!empty($already_listed_ids[$p->ID]) || !empty($already_listed_slugs[$slug])) continue;

						$link = get_permalink($p);
						$list[] = '<li><a href="'.esc_url($link).'">'.esc_html(get_the_title()).'</a></li>';
						$urls[] = $link;
						$already_listed_ids[$p->ID]  = true;
						$already_listed_slugs[$slug] = true;
					}
					$list[] = '</ul>';
					wp_reset_postdata();
				}
				if (!empty($list)) {
					$html[] = '<section class="pc-html-sitemap__section">';
					$html[] = '<h2>Expériences</h2>';
					$html[] = implode('', $list);
					$html[] = '</section>';
				}
			}
		}

		/* ============ ARTICLES (Magazine) ============ */
		if (in_array('articles', $sections, true)) {
			$q = new WP_Query([
				'post_type'      => 'post',
				'posts_per_page' => $limit,
				'post_status'    => 'publish',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			]);
			$list = [];
			if ($q->have_posts()) {
				$list[] = '<ul>';
				while ($q->have_posts()) { $q->the_post();
					$p = get_post();
					$slug = sanitize_title($p->post_name);
					if (in_array($slug, $exclude_slugs, true)) continue;
					if (pcseo_post_is_excluded_from_sitemap($p)) continue;
					if (!empty($already_listed_ids[$p->ID]) || !empty($already_listed_slugs[$slug])) continue;

					$link = get_permalink($p);
					$list[] = '<li><a href="'.esc_url($link).'">'.esc_html(get_the_title()).'</a></li>';
					$urls[] = $link;
					$already_listed_ids[$p->ID]  = true;
					$already_listed_slugs[$slug] = true;
				}
				$list[] = '</ul>';
				wp_reset_postdata();
			}
			if (!empty($list)) {
				$html[] = '<section class="pc-html-sitemap__section">';
				$html[] = '<h2>Magazine</h2>';
				$html[] = implode('', $list);
				$html[] = '</section>';
			}
		}

		$html[] = '</div></nav>';

		$out = ['html' => implode('', $html), 'urls' => array_values(array_unique($urls))];
		set_transient($key, $out, 12 * HOUR_IN_SECONDS);
		return $out;
	}
}

/** Shortcode public */
add_shortcode('pc_html_sitemap', function($atts = []) {
	$res = pcseo_build_html_sitemap($atts);
	return $res['html'];
});

/** Purge cache Ã  chaque sauvegarde (rebuild auto) */
add_action('save_post', function(){
	global $wpdb;
	$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_pcseo_html_sitemap_%' OR option_name LIKE '_transient_timeout_pcseo_html_sitemap_%'");
});

/* -----------------------------------------
 * BONUS : JSON-LD ItemList uniquement sur /plan-du-site/
 * ----------------------------------------- */
add_action('wp_head', function(){
	if (!is_page('plan-du-site')) return;

	$res  = pcseo_build_html_sitemap([
		'show'           => 'pages,destinations,logements,experiences,articles',
		'depth'          => 2,
		'limit_posts'    => 100,
		// Lâ€™ItemList reflÃ¨te la navigation : pas dâ€™include forcÃ© ici.
	]);
	$urls = $res['urls'];
	if (empty($urls)) return;

	$itemList = [
		'@context'        => 'https://schema.org',
		'@type'           => 'ItemList',
		'name'            => 'Plan du site',
		'itemListOrder'   => 'https://schema.org/ItemListOrderAscending',
		'numberOfItems'   => count($urls),
		'itemListElement' => [],
	];
	$pos = 1;
	foreach ($urls as $u) {
		$itemList['itemListElement'][] = [
			'@type'    => 'ListItem',
			'position' => $pos++,
			'url'      => $u,
		];
	}
	echo '<script type="application/ld+json">'.wp_json_encode($itemList, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."</script>\n";
}, 90);

/* ============================================================
 * 5 â€” Canonical Guard (v2 final)
 * - Supprime les canonicals Core & plugins
 * - Imprime un unique canonique calculÃ© "safe"
 * - ZÃ©ro canonique sur 404 et 410
 * - "Search-like" : noindex + canonique sans query
 * ============================================================ */

/** 5.A â€” Contexte d'exÃ©cution */
if (!function_exists('pcseo_cano_should_run')) {
  function pcseo_cano_should_run() {
    if (is_admin()) return false;
    if (defined('REST_REQUEST') && REST_REQUEST) return false;
    if (defined('DOING_AJAX')   && DOING_AJAX)   return false;

    // Hors sitemaps, feeds, robots.txt
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    if (is_feed()) return false;
    if ($uri && (strpos($uri, '/wp-sitemap') !== false)) return false;
    if ($uri && (strpos($uri, '/robots.txt') !== false)) return false;

    return true;
  }
}

/** 5.B â€” DÃ©tection "search-like"
 *  Certaines pages doivent Ãªtre traitÃ©es comme une recherche (facettes, filtresâ€¦)
 *  â†’ noindex + canonique "base" (sans query)
 */
if (!function_exists('pcseo_is_search_like')) {
  function pcseo_is_search_like() {
    if (is_search()) return true;

    // ðŸ”§ Liste ajustable : slugs des pages de recherche/facettes
    $paths = array(
      '/recherche-experiences/',
      '/recherche-logements/',
      '/recherche/',
    );
    $req = parse_url(home_url(add_query_arg(null, null)), PHP_URL_PATH);
    foreach ($paths as $p) {
      if (stripos($req, rtrim($p,'/').'/') !== false || rtrim($req,'/') === rtrim($p,'/')) {
        return true;
      }
    }
    return false;
  }
}

/** 5.C — Calcul canonique "safe" (corrigé pour la pagination Elementor) */
if (!function_exists('pcseo_compute_canonical_url')) {
  function pcseo_compute_canonical_url($strip_query=false) {
    // Pas de canonique sur 404 / 410
    if (is_404()) return '';
    if (get_query_var('pcseo_is_410')) return '';

    // Base selon contexte
    if (is_singular()) {
      $url = get_permalink();
    } elseif (is_home() && !is_front_page()) {
      $url = get_permalink(get_option('page_for_posts'));
    } elseif (is_front_page()) {
      $url = home_url('/');
    } elseif (is_post_type_archive()) {
      $url = get_post_type_archive_link(get_query_var('post_type') ?: get_post_type());
    } elseif (is_tax() || is_category() || is_tag()) {
      $term = get_queried_object();
      $url  = ($term && !is_wp_error($term)) ? get_term_link($term) : '';
    } else {
      $url = home_url(add_query_arg(null, null));
    }
    if (empty($url) || is_wp_error($url)) return '';

    // === CORRECTION PAGINATION ELEMENTOR ===
    // On détecte le numéro de page (standard OU Elementor)
    $paged = max(1, (int)get_query_var('paged'), (int)get_query_var('page'));
    if (!empty($_GET)) {
        foreach ($_GET as $key => $value) {
            if (strpos($key, 'e-page-') === 0 && is_numeric($value) && (int)$value > 1) {
                $paged = (int) $value;
                // On reconstruit l'URL avec le paramètre Elementor
                $url = add_query_arg($key, $paged, $url);
                break;
            }
        }
    }
    
    if ($paged > 1 && strpos($url, 'e-page-') === false) {
        // Fallback pour la pagination standard de WordPress
        $url = get_pagenum_link($paged);
    }
    // === FIN CORRECTION ===

    // Nettoyage des query args (trackers, etc.)
    $kill = array(
      'utm_source','utm_medium','utm_campaign','utm_term','utm_content','utm_id',
      'gclid','fbclid','msclkid','dclid','igshid',
      '_ga','_gl','_ke','vero_id','mkt_tok','mc_cid','mc_eid',
      'ref','ref_','aff','affiliate','utm_referrer','spm','si','li_fat_id',
    );
    // On ne supprime PAS le paramètre de pagination Elementor
    $preserved_args = [];
    if (!empty($_GET)) {
        foreach ($_GET as $key => $value) {
            if (strpos($key, 'e-page-') === 0) {
                $preserved_args[$key] = $value;
            }
        }
    }
    
    $url = remove_query_arg($kill, $url);
    if ($strip_query) { // Pour les pages de recherche
      $url = strtok($url, '?');
    }

    $url = set_url_scheme($url);
    $url = user_trailingslashit($url);

    // Forcer domaine local (sécurité)
    $home_host = parse_url(home_url(), PHP_URL_HOST);
    $url_host  = parse_url($url, PHP_URL_HOST);
    if ($home_host && $url_host && strcasecmp($home_host, $url_host) !== 0) {
        $parts = wp_parse_url($url);
        $path  = $parts['path'] ?? '/';
        $query = $parts['query'] ?? '';
        $url = set_url_scheme('http://' . $home_host . $path . ($query ? '?' . $query : ''));
        $url = user_trailingslashit($url);
    }
    return $url;
  }
}

/** 5.D â€” DÃ©sactiver les canonicals Core & plugins courants */
add_action('init', function(){
  if (!pcseo_cano_should_run()) return;

  // Core
  remove_action('wp_head', 'rel_canonical');

  // Quelques plugins SEO (si jamais actifs un jour)
  // Rank Math
  if (has_action('wp_head', 'rank_math/frontend/canonical')) {
    remove_action('wp_head', 'rank_math/frontend/canonical', 20);
  }
  // SEOPress
  if (has_action('seopress_pro_head', 'seopress_advanced_advanced_robots_canonical')) {
    remove_action('seopress_pro_head', 'seopress_advanced_advanced_robots_canonical', 10);
  }
  // AIOSEO
  if (has_action('wp_head', 'aioseo_head')) {
    // AIOSEO imprime plein de choses, on ne retire pas tout le head, on se contente
    // de mettre notre canonique en dernier (voir plus bas).
  }
}, 20);

/** 5.E â€” Injection du canonique unique (et rÃ¨gles "search-like") */
add_action('wp_head', function(){
  if (!pcseo_cano_should_run()) return;

  // Cas search-like : forcer noindex + canonique "base" (sans query)
  if (pcseo_is_search_like()) {
    add_filter('wp_robots', function(array $r){
      // nettoie extras WP
      $r['max-image-preview'] = null;
      $r['max-snippet']       = null;
      $r['max-video-preview'] = null;
      $r['noarchive']         = null;
      $r['nosnippet']         = null;
      // impose noindex,follow
      $r['noindex']  = true;
      $r['index']    = null;
      $r['nofollow'] = null;
      $r['follow']   = true;
      return $r;
    }, 999);
    $url = pcseo_compute_canonical_url(true); // strip query
    if ($url) echo "\n<link rel=\"canonical\" href=\"".esc_url($url)."\" />\n";
    return;
  }

  // 404 / 410 : pas de canonique (pcseo_compute_canonical_url() renverra vide)
  $url = pcseo_compute_canonical_url(false);
  if ($url) {
    echo "\n<link rel=\"canonical\" href=\"".esc_url($url)."\" />\n";
  }
}, 999);

/* ============================================================
 * 6.A SOCIAL + TITRE/DESCRIPTION + AUDIT
 * ============================================================ */

/* ========= 6.0 A Helpers gÃ©nÃ©raux (utilisÃ©s par 6.A/6.B/6.C) ========= */

if (!function_exists('pcseo_get_option')) {
  function pcseo_get_option($key){
    // ACF Options Page
    if (function_exists('get_field')) {
      $v = get_field($key, 'option');
      if ($v !== null && $v !== false && $v !== '') return $v;
    }
    return get_option($key, '');
  }
}

if (!function_exists('pcseo_abs_url')) {
  function pcseo_abs_url($url){
    if (!$url) return '';
    // si relative, la convertir en absolue
    if (strpos($url, '//') === 0) { return (is_ssl()?'https:':'http:').$url; }
    if (parse_url($url, PHP_URL_SCHEME)) return $url;
    if ($url[0] === '/') return home_url($url);
    return home_url('/'.$url);
  }
}

if (!function_exists('pcseo_plain')) {
  function pcseo_plain($html, $len = 180){
    $t = trim( wp_strip_all_tags( (string)$html ) );
    $t = preg_replace('/\s+/', ' ', $t);
    if ($len && mb_strlen($t) > $len) $t = rtrim(mb_substr($t, 0, $len-1)).'â€¦';
    return $t;
  }
}

if (!function_exists('pcseo_get_seo_title')) {
  function pcseo_get_seo_title($post_id){
    $pt = get_post_type($post_id);
    $acf_key = '';

    switch ($pt){
      case 'page':        $acf_key = 'pc_meta_title';          break; // Pages
      case 'villa':
      case 'appartement': $acf_key = 'meta_titre';             break; // Logements
      case 'experience':  $acf_key = 'exp_meta_titre';         break; // ExpÃ©riences
      case 'destination': $acf_key = 'dest_meta_title';        break; // Destinations
      default:            $acf_key = '';                       break; // Posts & autres: laisser WP
    }

    // Lecture directe ACF si possible
    if ($acf_key && function_exists('get_field')) {
      $v = get_field($acf_key, $post_id);
      if (!empty($v)) return pcseo_plain($v, 300);
    }

    // Fallback gÃ©nÃ©rique via meta helper (gÃ¨re group/subfield)
    if ($acf_key) {
      $suffix = preg_replace('~^(pc_|exp_|dest_|post_)~','', $acf_key); // ex: dest_meta_title -> meta_title
      $v = pcseo_get_meta($post_id, $suffix);
      if (!empty($v)) return pcseo_plain($v, 300);
    }

    return ''; // Laisser WP composer <title> si rien
  }
}

if (!function_exists('pcseo_get_seo_description')) {
  function pcseo_get_seo_description($post_id){
    $pt = get_post_type($post_id);
    $acf_key = '';

    switch ($pt){
      case 'page':        $acf_key = 'pc_meta_description';    break; // Pages
      case 'villa':
      case 'appartement': $acf_key = 'meta_description';       break; // Logements
      case 'experience':  $acf_key = 'exp_meta_description';   break; // ExpÃ©riences
      case 'destination': $acf_key = 'dest_meta_description';  break; // Destinations
      case 'post':        $acf_key = 'post_og_description';    break; // Articles (champ social dÃ©diÃ©)
    }

    if ($acf_key && function_exists('get_field')) {
      $v = get_field($acf_key, $post_id);
      if (!empty($v)) return pcseo_plain($v, 180);
    }

    if ($acf_key) {
      $suffix = preg_replace('~^(pc_|exp_|dest_|post_)~','', $acf_key);
      $v = pcseo_get_meta($post_id, $suffix);
      if (!empty($v)) return pcseo_plain($v, 180);
    }

    // Fallback: excerpt puis titre
    $ex = get_post_field('post_excerpt', $post_id, 'raw');
    if (!empty($ex)) return pcseo_plain($ex, 180);

    return pcseo_plain(get_the_title($post_id), 180);
  }
}

if (!function_exists('pcseo_pick_og_image')) {
  function pcseo_pick_og_image($post_id){
    $pt = get_post_type($post_id);
    $candidates = array();

    // PrioritÃ©s par type
    if ($pt === 'villa' || $pt === 'appartement'){
      $hero = pcseo_get_meta($post_id, 'hero_desktop_url'); // URL
      if ($hero) $candidates[] = $hero;
      $gallery = pcseo_get_meta($post_id, 'seo_gallery_urls'); // lignes d'URLs
      if ($gallery){
        foreach (preg_split('/\r\n|\r|\n/', (string)$gallery) as $u){
          $u = trim($u);
          if ($u) $candidates[] = $u;
        }
      }
    } elseif ($pt === 'experience'){
      $img = function_exists('get_field') ? get_field('exp_hero_desktop', $post_id) : pcseo_get_meta($post_id, 'hero_desktop');
      if (is_array($img) && !empty($img['url'])) $candidates[] = $img['url'];
      elseif (is_numeric($img)) $candidates[] = wp_get_attachment_url($img);
    } elseif ($pt === 'destination'){
      $img = function_exists('get_field') ? get_field('dest_hero_desktop', $post_id) : pcseo_get_meta($post_id, 'hero_desktop');
      if (is_array($img) && !empty($img['url'])) $candidates[] = $img['url'];
      elseif (is_numeric($img)) $candidates[] = wp_get_attachment_url($img);
    }

    // Featured image
    $thumb = get_the_post_thumbnail_url($post_id, 'full');
    if ($thumb) $candidates[] = $thumb;

    // Logo global (options)
    $org_logo = function_exists('get_field') ? get_field('pc_org_logo', 'option') : '';
    if (is_array($org_logo) && !empty($org_logo['url'])) $candidates[] = $org_logo['url'];
    elseif (is_numeric($org_logo)) $candidates[] = wp_get_attachment_url($org_logo);

    foreach ($candidates as $u){
      $abs = pcseo_abs_url($u);
      if ($abs) return $abs;
    }
    return '';
  }
}

/* ========= 6.A — Social cards (Open Graph + Twitter) — v2.2 (Blog Home custom text) ========= */
add_action('wp_head', function(){
  if (is_admin()) return;
  if (!is_singular() && !is_home() && !is_front_page() && !is_post_type_archive() && !is_tax() && !is_category() && !is_tag() && !is_search()) return;

  $title = $desc = $image = $url = $type = '';
  $site_name = pcseo_get_option('pc_org_name'); // nom organisation/site (options)
  if (!$site_name) $site_name = get_bloginfo('name');

  // 1) HOME (vitrine) => toujours website
  if ( is_front_page() ) {
    $type  = 'website';
    $url   = home_url('/');
    if (get_queried_object_id()) {
      $home_id = get_queried_object_id();
      $title = pcseo_get_seo_title($home_id) ?: trim(wp_get_document_title());
      $desc  = pcseo_plain(pcseo_get_seo_description($home_id), 200);
    } else {
      $title = trim(wp_get_document_title());
      $desc  = pcseo_plain(get_bloginfo('description'), 200);
    }
    $image = pcseo_pick_og_image( get_queried_object_id() ?: 0 );
  }

  // 2) PAGE ARTICLES (is_home sans être front_page) => website
  elseif ( is_home() && !is_front_page() ) {
    $type  = 'website';
    $pid   = get_option('page_for_posts');
    $url   = get_permalink($pid);
    
    // Vos contenus personnalisés pour la page du blog
    $title = "Vacances en Guadeloupe - votre Magazine Prestige Caraïbes";
    $desc = "Retrouvez tous les Conseils pratiques de professionnels pour bien préparer et réussir vos vacances en Guadeloupe";

    $image = $pid ? pcseo_pick_og_image($pid) : '';
  }

  // 3) SINGULAR (pages, logements, etc.)
  elseif ( is_singular() ) {
    $id    = get_queried_object_id();
    $type  = 'article';
    $title = pcseo_get_seo_title($id) ?: get_the_title($id);
    $desc  = pcseo_plain(pcseo_get_seo_description($id), 200);
    $image = pcseo_pick_og_image($id);
    $url   = get_permalink($id);
  }
  
  // 4) RECHERCHE
  elseif ( is_search() ) {
    $type  = 'website';
    $title = 'Recherche';
    $desc  = pcseo_plain(get_bloginfo('description'), 200);
    $url   = pcseo_abs_url( remove_query_arg(array('s','paged'), home_url( parse_url(add_query_arg(null,null), PHP_URL_PATH) )) );
    $image = '';
  }

  // 5) ARCHIVES / TAXONOMIES
  else {
    $type  = 'website';
    $title = trim(wp_get_document_title());
    $desc  = pcseo_plain(get_bloginfo('description'), 200);
    $url   = pcseo_abs_url( home_url( add_query_arg(null, null) ) );
    $image = '';
  }

  static $pcseo_og_done = false;
  if ($pcseo_og_done) return;
  $pcseo_og_done = true;

  echo "\n\n";
  $locale = str_replace('-', '_', get_locale() ?: 'fr_FR');
  echo '<meta property="og:locale" content="'.esc_attr($locale).'" />'."\n";
  if ($site_name) {
    echo '<meta property="og:site_name" content="'.esc_attr($site_name).'" />'."\n";
  }
  echo '<meta property="og:type" content="'.esc_attr($type ?: 'website').'" />'."\n";
  if ($url)   echo '<meta property="og:url" content="'.esc_url($url).'" />'."\n";
  if ($title) echo '<meta property="og:title" content="'.esc_attr($title).'" />'."\n";
  if ($desc)  echo '<meta property="og:description" content="'.esc_attr($desc).'" />'."\n";
  if ($image) echo '<meta property="og:image" content="'.esc_url($image).'" />'."\n";
  echo '<meta name="twitter:card" content="summary_large_image" />'."\n";
  if ($title) echo   '<meta name="twitter:title" content="'.esc_attr($title).'" />'."\n";
  if ($desc)  echo   '<meta name="twitter:description" content="'.esc_attr($desc).'" />'."\n";
  if ($image) echo   '<meta name="twitter:image" content="'.esc_url($image).'" />'."\n";
}, 48);


/* ========= 6.B — Title & Meta description — v1.2 (Blog Home custom text) ========= */

add_filter('pre_get_document_title', function($title){
  // Page du Blog
  if (is_home() && !is_front_page()){
    return "Vacances en Guadeloupe - votre Magazine Prestige Caraïbes";
  }
  // Pages singulières avec override ACF
  if (is_singular()) {
    $id = get_queried_object_id();
    if ($id) {
      $custom = pcseo_get_seo_title($id);
      return $custom ?: $title;
    }
  }
  return $title;
}, 20);

add_action('wp_head', function(){
  // Page du Blog
  if (is_home() && !is_front_page()) {
      $desc = "Retrouvez tous les Conseils pratiques de professionnels pour bien préparer et réussir vos vacances en Guadeloupe";
      echo "\n<meta name=\"description\" content=\"".esc_attr($desc)."\" />\n";
      return;
  }
  // Pages singulières
  if (is_singular()) {
    $id = get_queried_object_id(); if (!$id) return;
    if (is_404() || get_query_var('pcseo_is_410')) return;
    $desc = pcseo_get_seo_description($id);
    if (!$desc) return;
    static $pcseo_desc_done = false;
    if ($pcseo_desc_done) return;
    $pcseo_desc_done = true;
    echo "\n<meta name=\"description\" content=\"".esc_attr($desc)."\" />\n";
  }
}, 7);


/* ========= 6.C â€” Mini-audit admin (compteurs + export CSV) ========= */

if (is_admin()) {
  add_action('admin_menu', function(){
    add_menu_page(
      'SEO Audit PC',
      'SEO Audit PC',
      'manage_options',
      'pcseo-audit',
      'pcseo_render_audit_page',
      'dashicons-visibility',
      80
    );
  });

  function pcseo_render_audit_page(){
    if (!current_user_can('manage_options')) return;

    // Export CSV ?
    if (!empty($_GET['pcseo_export']) && check_admin_referer('pcseo_export_csv')){
      pcseo_export_csv();
      exit;
    }

    $types = array('page','post','villa','appartement','experience','destination');
    $stats = array();
    $rows  = array();

    foreach ($types as $pt){
      $q = new WP_Query(array(
        'post_type'      => $pt,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
      ));
      $noindex = $exclude = $gone = 0;

      foreach ($q->posts as $pid){
        $robots  = (string) pcseo_get_meta($pid, 'meta_robots');
        $exclude_b = pcseo_truthy( pcseo_get_meta($pid, 'exclude_sitemap') );
        $gone_b    = pcseo_truthy( pcseo_get_meta($pid, 'http_410') );

        // logique "effective" (mÃªme que la colonne + wp_robots)
        $robots_norm = strtolower(str_replace(' ','', $robots));
        $has_robots_set = function_exists('pcseo_meta_exists') ? pcseo_meta_exists($pid, 'meta_robots') : false;

        if ($exclude_b && !pcseo_is_noindex($robots_norm)) {
          $effective = 'noindex,follow';
        } elseif ($has_robots_set && $robots_norm !== '') {
          $effective = $robots_norm;
        } else {
          $effective = 'index,follow';
        }

        if (pcseo_is_noindex($effective)) $noindex++;
        if ($exclude_b) $exclude++;
        if ($gone_b)    $gone++;

        $rows[] = array(
          'type' => $pt,
          'id'   => $pid,
          'title'=> get_the_title($pid),
          'url'  => get_permalink($pid),
          'robots'=> $effective,
          'exclude'=> $exclude_b ? '1':'',
          '410'    => $gone_b ? '1':'',
        );
      }

      $stats[$pt] = array('noindex'=>$noindex, 'exclude'=>$exclude, 'gone'=>$gone, 'total'=>count($q->posts));
    }

    // Rendu HTML simple
    echo '<div class="wrap"><h1>SEO Audit â€” Prestige CaraÃ¯bes</h1>';

    echo '<h2>Compteurs par type</h2>';
    echo '<table class="widefat striped"><thead><tr><th>Type</th><th>Total</th><th>Noindex</th><th>Excl. sitemap</th><th>410</th></tr></thead><tbody>';
    foreach ($stats as $pt=>$s){
      printf('<tr><td>%s</td><td>%d</td><td>%d</td><td>%d</td><td>%d</td></tr>',
        esc_html($pt), $s['total'], $s['noindex'], $s['exclude'], $s['gone']
      );
    }
    echo '</tbody></table>';

    $export_url = wp_nonce_url( admin_url('admin.php?page=pcseo-audit&pcseo_export=1'), 'pcseo_export_csv');
    echo '<p><a href="'.esc_url($export_url).'" class="button button-primary">Exporter CSV</a></p>';

    // Liste (optionnel : limitÃ© aux 200 premiÃ¨res pour lisibilitÃ©)
    echo '<h2>DÃ©tails (premiers Ã©lÃ©ments)</h2>';
    echo '<table class="widefat striped"><thead><tr><th>Type</th><th>ID</th><th>Titre</th><th>URL</th><th>Robots</th><th>Excl.</th><th>410</th></tr></thead><tbody>';
    $i=0;
    foreach ($rows as $r){
      if (++$i>200) break;
      printf('<tr><td>%s</td><td>%d</td><td>%s</td><td><a href="%s" target="_blank">%s</a></td><td>%s</td><td>%s</td><td>%s</td></tr>',
        esc_html($r['type']),
        $r['id'],
        esc_html($r['title']),
        esc_url($r['url']),
        esc_html($r['url']),
        esc_html($r['robots']),
        esc_html($r['exclude']),
        esc_html($r['410'])
      );
    }
    echo '</tbody></table>';

    echo '</div>';
  }

  function pcseo_export_csv(){
    $types = array('page','post','villa','appartement','experience','destination');
    $out = fopen('php://output', 'w');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=pcseo-audit.csv');

    fputcsv($out, array('type','id','title','url','robots','exclude','410'));

    foreach ($types as $pt){
      $q = new WP_Query(array(
        'post_type'      => $pt,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
      ));
      foreach ($q->posts as $pid){
        $robots  = (string) pcseo_get_meta($pid, 'meta_robots');
        $exclude_b = pcseo_truthy( pcseo_get_meta($pid, 'exclude_sitemap') );
        $gone_b    = pcseo_truthy( pcseo_get_meta($pid, 'http_410') );

        $robots_norm = strtolower(str_replace(' ','', $robots));
        $has_robots_set = function_exists('pcseo_meta_exists') ? pcseo_meta_exists($pid, 'meta_robots') : false;

        if ($exclude_b && !pcseo_is_noindex($robots_norm)) {
          $effective = 'noindex,follow';
        } elseif ($has_robots_set && $robots_norm !== '') {
          $effective = $robots_norm;
        } else {
          $effective = 'index,follow';
        }

        fputcsv($out, array(
          $pt,
          $pid,
          get_the_title($pid),
          get_permalink($pid),
          $effective,
          $exclude_b ? '1':'',
          $gone_b ? '1':'',
        ));
      }
    }
    fclose($out);
  }
}

/* ============================================================
 * 7.A â€” JSON-LD VacationRental (villa/appartement/logement) â€” VERSION FINALE CORRIGÃ‰E
 * ============================================================ */
add_action('wp_footer', function () {
  // Pas en mode Ã©dition Elementor
  if (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->editor->is_edit_mode()) return;
  if (!is_singular()) return;

  $p = get_queried_object();
  // On s'assure que le code s'exÃ©cute pour tous les types de logements
  if (!$p || !in_array($p->post_type, ['villa','appartement','logement'], true)) return;

  static $done = false; if ($done) return; $done = true;

  // --- Helpers locaux ---
  $getf = function($key, $default = '', $raw = false) use ($p){
    if (function_exists('get_field')) {
      $v = get_field($key, $p->ID);
      if ($raw) return $v;
      if ($v !== null && $v !== false && $v !== '') return is_string($v) ? trim($v) : $v;
    }
    if (function_exists('pcseo_get_meta')) {
      $suffix = preg_replace('~^(pc_|exp_|dest_|post_)~','', $key);
      $v = pcseo_get_meta($p->ID, $suffix);
      if ($raw) return $v;
      if ($v !== '' && $v !== null) return is_string($v) ? trim($v) : $v;
    }
    $v = get_post_meta($p->ID, $key, true);
    if ($raw) return $v;
    return ($v !== '' && $v !== null) ? (is_string($v) ? trim($v) : $v) : $default;
  };
  $clean_txt = function($html, $len=1200){
    $t = wp_strip_all_tags((string)$html, true);
    $t = preg_replace('/\s+/', ' ', $t);
    if ($len && mb_strlen($t) > $len) $t = rtrim(mb_substr($t, 0, $len-1)).'â€¦';
    return $t;
  };
  $arr_filter = function($a){
    if (!is_array($a)) return $a;
    foreach ($a as $k=>$v){
      if (is_array($v)) $a[$k] = array_filter($v, fn($x)=>$x!==null && $x!=='' && $x!==[]);
    }
    return array_filter($a, fn($x)=>$x!==null && $x!=='' && $x!==[]);
  };

  // --- DonnÃ©es principales ---
  $name        = get_the_title($p);
  $identifier  = (string)$p->ID;
  $url         = get_permalink($p);
  $description = $clean_txt($getf('seo_long_html', ''));
  
  // Images
  $imgs = [];
  $raw_urls = $getf('seo_gallery_urls', '', true);
  if (is_string($raw_urls) && trim($raw_urls) !== '') {
    foreach (preg_split('/\R+/', trim($raw_urls)) as $u){
      $u = trim($u);
      if ($u) $imgs[] = esc_url($u);
    }
  }
  if (empty($imgs) && has_post_thumbnail($p->ID)) {
    $thumb = wp_get_attachment_image_url(get_post_thumbnail_id($p->ID), 'full');
    if ($thumb) $imgs[] = $thumb;
  }
  if (count($imgs) > 12) $imgs = array_slice($imgs, 0, 12);

  // Adresse
  $address = [];
  $streetAddress   = $getf('adresse_rue', '');
  $addressLocality = $getf('ville', '');
  $postalCode      = $getf('code_postal', '');
  if ($streetAddress || $addressLocality || $postalCode) {
    $address = [
      '@type'           => 'PostalAddress',
      'streetAddress'   => $streetAddress ?: null,
      'addressLocality' => $addressLocality ?: null,
      'addressRegion'   => 'Guadeloupe',
      'postalCode'      => $postalCode ?: null,
      'addressCountry'  => 'GP',
    ];
  }

  // Geo
  $geo = []; $lat = $lng = null;
  $gc = $getf('geo_coords', '');
  if (is_string($gc) && strpos($gc, ',') !== false) { [$lat,$lng] = array_map('trim', explode(',', $gc, 2)); }
  if (!$lat || !$lng) { $lat2 = $getf('latitude', ''); $lng2 = $getf('longitude', ''); if ($lat2 !== '' && $lng2 !== '') { $lat = $lat2; $lng = $lng2; } }
  if (!$lat || !$lng) { $old = $getf('coordonnees', ''); if (is_string($old) && strpos($old, ',') !== false) { [$la,$lo] = array_map('trim', explode(',', $old, 2)); if ($la !== '' && $lo !== '') { $lat = $la; $lng = $lo; } } }
  if ($lat !== null && $lng !== null && $lat !== '' && $lng !== '') {
    $geo = ['@type' => 'GeoCoordinates', 'latitude'  => (float)$lat, 'longitude' => (float)$lng];
  }

  // === Construction de l'objet principal ===
  $data = [
    '@context'    => 'https://schema.org',
    '@type'       => 'VacationRental',
    'name'        => $name,
    'identifier'  => (string) $identifier,
    'url'         => $url,
    'description' => $description,
  ];

  // Premier "additionalType" (type de bÃ¢timent)
  if ($p->post_type === 'villa') {
    $data['additionalType'] = 'House';
  } elseif ($p->post_type === 'appartement') {
    $data['additionalType'] = 'Apartment';
  } else {
    $data['additionalType'] = 'Residence'; 
  }

  if (!empty($imgs))    $data['image']   = $imgs;
  if (!empty($address)) $data['address'] = $arr_filter($address);
  if (!empty($geo))     $data['geo']     = $geo;
  
  $accommodation = ['@type' => 'Accommodation'];

  // CORRECTION : On ajoute le DEUXIÃˆME "additionalType" (type de location)
  $accommodation['additionalType'] = 'EntirePlace'; // Toujours logement entier

  $bedrooms  = (int)$getf('nombre_de_chambres', 0);
  $bathrooms = (float)$getf('nombre_sdb', 0);
  $capacity  = (int)$getf('capacite', 0);

  if ($bedrooms > 0)  $accommodation['numberOfBedrooms']       = $bedrooms;
  if ($bathrooms > 0) $accommodation['numberOfBathroomsTotal']  = $bathrooms;
  if ($capacity > 0)  $accommodation['occupancy']               = ['@type'=>'QuantitativeValue','value'=>$capacity];

  $features = [];
  $amenKeys  = (array)$getf('google_vr_amenities', []);
  foreach ($amenKeys as $k) {
    $k = is_string($k) ? trim($k) : '';
    if ($k !== '') $features[] = ['@type'=>'LocationFeatureSpecification','name'=>$k,'value'=>true];
  }
  if (!empty($features)) $accommodation['amenityFeature'] = $features;
  
  if (count($accommodation) > 1) { // Ne pas ajouter si seulement @type
      $data['containsPlace'] = $arr_filter($accommodation);
  }

  // Avis internes
  $args = [ 'post_type' => 'pc_review', 'post_status' => 'publish', 'posts_per_page' => 5, 'meta_query' => [['key'=>'pc_post_id','value'=>$p->ID],['key'=>'pc_source','value'=>'internal']] ];
  $review_posts = get_posts($args);
  if ($review_posts) {
    $sum=0; $cnt=0; $reviews=[];
    foreach ($review_posts as $rp){
      $rating = (float)get_post_meta($rp->ID, 'pc_rating', true);
      if ($rating > 0) {
        $sum += $rating; $cnt++;
        $reviews[] = [
          '@type' => 'Review',
          'author' => ['@type'=>'Person','name'=> (get_post_meta($rp->ID,'pc_reviewer_name',true) ?: 'Client vÃ©rifiÃ©')],
          'datePublished' => get_the_date('Y-m-d', $rp->ID),
          'reviewBody' => wp_strip_all_tags(get_post_meta($rp->ID,'pc_body',true)),
          'reviewRating' => ['@type'=>'Rating','ratingValue'=>$rating,'bestRating'=>'5'],
          'contentReferenceTime' => (function($d){ return $d ? date('c', strtotime($d.'-01')) : null; })( get_post_meta($rp->ID,'pc_stayed_date',true) ),
        ];
      }
    }
    if ($cnt > 0) {
      $data['aggregateRating'] = ['@type'=>'AggregateRating','ratingValue'=>round($sum/$cnt,1),'reviewCount'=>$cnt];
      $valid = array_values(array_filter($reviews, fn($r)=>!empty($r['contentReferenceTime'])));
      if ($valid) $data['review'] = $valid;
    }
  }

  // Fallback description si vide
  if (empty($data['description'])) {
    $desc = get_post_field('post_excerpt', $p->ID);
    if ($desc === '' || $desc === null) $desc = get_post_field('post_content', $p->ID);
    $data['description'] = $clean_txt($desc);
  }

  // Impression
  $json = wp_json_encode($arr_filter($data), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  if ($json) echo "\n<script type='application/ld+json' class='pc-seo-vr-schema'>{$json}</script>\n";
}, 99);

/* ============================================================
 * 7.H — JSON-LD Product (pour les fiches Expérience) - v1.1
 * Gère les prix fixes et les services "sur devis".
 * Intègre les avis (aggregateRating + review).
 * Corrige le champ 'brand' pour Merchant Listing.
 * ============================================================ */
add_action('wp_footer', function () {
  // Garde-fous : uniquement sur les fiches Expérience, pas en admin/éditeur
  if (!is_singular('experience')) return;
  if (is_admin() || (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->editor->is_edit_mode())) return;

  // Verrou pour éviter les doublons
  static $done = false; if ($done) return; $done = true;

  $p = get_queried_object();
  if (!$p) return;

  // --- Helpers locaux pour lire les champs ACF ---
  $getf = function($key, $default = '') use ($p){
    if (function_exists('get_field')) {
      $v = get_field($key, $p->ID);
      if ($v !== null && $v !== false && $v !== '') return is_string($v) ? trim($v) : $v;
    }
    return $default;
  };
  $clean_txt = function($html, $len=1200){
    $t = wp_strip_all_tags((string)$html, true);
    $t = preg_replace('/\s+/', ' ', $t);
    if ($len && mb_strlen($t) > $len) $t = rtrim(mb_substr($t, 0, $len-1)).'…';
    return $t;
  };
  $arr_filter = function($a){ // Filtre récursif pour nettoyer le tableau final
    if (!is_array($a)) return $a;
    foreach ($a as $k=>$v){
      if (is_array($v)) $a[$k] = array_filter($v, fn($x)=>$x!==null && $x!=='' && $x!==[]);
    }
    return array_filter($a, fn($x)=>$x!==null && $x!=='' && $x!==[]);
  };

  // --- Données principales du produit ---
  $name = $getf('exp_h1_custom') ?: get_the_title($p->ID);
  $description = $getf('exp_meta_description') ?: $clean_txt(get_the_excerpt($p->ID));
  $image_url = $getf('exp_hero_desktop') ?: get_the_post_thumbnail_url($p->ID, 'full');
  
  // --- Logique pour l'offre (prix ou devis) ---
  $tarifs = $getf('exp_types_de_tarifs', []);
  if (empty($tarifs) || !is_array($tarifs)) {
      return; // Pas de tarif, pas de schéma
  }
  
  $first_offer_row = $tarifs[0];
  $offer_type = $first_offer_row['exp_type'] ?? '';
  $price_value = (float)($first_offer_row['exp_tarif_adulte'] ?? 0);
  $availability_status = $getf('exp_availability', 'InStock');

  $offer_data = [
      '@type' => 'Offer',
      'priceCurrency' => 'EUR',
      'availability' => 'https://schema.org/' . $availability_status,
  ];

  if ($offer_type === 'sur-devis') {
      $offer_data['price'] = '0';
      $offer_data['priceSpecification'] = [
          '@type' => 'PriceSpecification',
          'price' => '0',
          'priceCurrency' => 'EUR',
          'valueAddedTaxIncluded' => 'true',
          'priceType' => 'Tarif sur devis'
      ];
  } elseif ($price_value > 0) {
      $offer_data['price'] = $price_value;
  } else {
      return; // Offre mal configurée (ni devis, ni prix > 0), on arrête.
  }

  // --- Construction de l'objet principal ---
  $data = [
    '@context'    => 'https://schema.org',
    '@type'       => 'Product',
    'name'        => $name,
    'sku'         => (string) $p->ID,
    'url'         => get_permalink($p->ID),
    'brand'       => ['@type' => 'Brand', 'name' => 'Prestige Caraïbes'], // <-- MODIFICATION ICI
    'offers'      => $offer_data,
  ];

  if ($description) $data['description'] = $description;
  if ($image_url)   $data['image'] = esc_url($image_url);

  // --- Récupération des avis (logique partagée avec VacationRental) ---
  $args = [ 
      'post_type' => 'pc_review', 
      'post_status' => 'publish', 
      'posts_per_page' => 5, // Limite pour ne pas surcharger
      'meta_query' => [
          ['key'=>'pc_post_id','value'=> $p->ID],
          ['key'=>'pc_source','value'=>'internal'] // Uniquement les avis internes
      ] 
  ];
  $review_posts = get_posts($args);
  if ($review_posts) {
    $sum=0; $cnt=0; $reviews=[];
    foreach ($review_posts as $rp){
      $rating = (float)get_post_meta($rp->ID, 'pc_rating', true);
      if ($rating > 0) {
        $sum += $rating; $cnt++;
        $reviews[] = [
          '@type' => 'Review',
          'author' => ['@type'=>'Person','name'=> (get_post_meta($rp->ID,'pc_reviewer_name',true) ?: 'Client vérifié')],
          'datePublished' => get_the_date('Y-m-d', $rp->ID),
          'reviewBody' => wp_strip_all_tags(get_post_meta($rp->ID,'pc_body',true)),
          'reviewRating' => ['@type'=>'Rating','ratingValue'=>$rating,'bestRating'=>'5'],
        ];
      }
    }
    if ($cnt > 0) {
      $data['aggregateRating'] = ['@type'=>'AggregateRating','ratingValue'=>round($sum/$cnt,1),'reviewCount'=>$cnt];
      $data['review'] = $reviews;
    }
  }

  // --- Impression finale du JSON-LD ---
  $json = wp_json_encode($arr_filter($data), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  if ($json) {
    echo "\n<script type='application/ld+json' class='pc-seo-product-schema'>{$json}</script>\n";
  }
}, 98); // Priorité juste avant VacationRental pour garder une logique d'ordre

// ============================================================
// 7.F â€” JSON-LD Article / BlogPosting (articles du magazine)
// - Cible : singular 'post' uniquement
// - Champs : headline, description (excerpt>content), dates, author,
//            image (FI ou fallback), mainEntityOfPage, publisher (#organization)
// - Garde-fou : print-once, pas en mode Ã©diteur Elementor
// ============================================================
add_action('wp_footer', function () {
    if (is_admin()) return;
    if (!is_singular('post')) return;
    if (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->editor->is_edit_mode()) return;

    static $done = false; if ($done) return; $done = true;

    $p   = get_queried_object();
    if (!$p || $p->post_type !== 'post') return;
    $id  = (int) $p->ID;

    // DonnÃ©es de base
    $url     = get_permalink($id);
    $lang    = get_bloginfo('language') ?: 'fr-FR';
    $title   = get_the_title($id);

    // Description : excerpt > content, propre & coupÃ©e
    $rawDesc = get_post_field('post_excerpt', $id);
    if ($rawDesc === '' || $rawDesc === null) {
        $rawDesc = get_post_field('post_content', $id);
    }
    $desc = function_exists('pcd_trim') ? pcd_trim(wp_strip_all_tags((string)$rawDesc), 300) : wp_strip_all_tags((string)$rawDesc);

    // Image : FI -> URL, sinon fallback site icon / logo
    $img = '';
    $thumb_id = get_post_thumbnail_id($id);
    if ($thumb_id) {
        $img = wp_get_attachment_image_url($thumb_id, 'full');
    }
    if (!$img) {
        $img = get_site_icon_url(512);
        if (!$img) {
            $logo_id = get_theme_mod('custom_logo');
            if ($logo_id) $img = wp_get_attachment_image_url($logo_id, 'full');
        }
    }

    // Auteur (WP user) + dates
    $author_name = get_the_author_meta('display_name', $p->post_author) ?: 'RÃ©daction';
    $datePub     = get_post_time('c', true, $id);
    $dateMod     = get_post_modified_time('c', true, $id);

    // Section = 1re catÃ©gorie (si dispo)
    $section = '';
    $cats = get_the_category($id);
    if (!empty($cats) && is_array($cats)) {
        $section = wp_strip_all_tags($cats[0]->name);
    }

    // IDs de graph existants (#organization / #website) â€” mÃªme convention que le bloc C
    $site_url = rtrim(home_url('/'), '/') . '/';
    $org_id   = $site_url . '#organization';
    $web_id   = $site_url . '#website';

    // Construction Article/BlogPosting
    $data = [
        '@context'         => 'https://schema.org',
        '@type'            => ['Article','BlogPosting'],
        'mainEntityOfPage' => [ '@type' => 'WebPage', '@id' => $url ],
        'isPartOf'         => [ '@id' => $web_id ],
        'publisher'        => [ '@id' => $org_id ],
        'inLanguage'       => $lang,
        'url'              => $url,
        'headline'         => $title,
        'datePublished'    => $datePub,
        'dateModified'     => $dateMod,
        'author'           => [ '@type' => 'Person', 'name' => $author_name ],
    ];
    if ($desc !== '') $data['description'] = $desc;
    if ($img)         $data['image']       = $img;
    if ($section)     $data['articleSection'] = $section;

    // Impression (mÃªme helper que WebPage/FAQ)
    if (!function_exists('pcd_print_jsonld')) {
        // fallback minimal si jamais
        echo "\n<script type='application/ld+json' class='pc-seo-article-schema'>"
            . wp_json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
            . "</script>\n";
    } else {
        pcd_print_jsonld($data, 'pc-seo-article-schema');
    }
}, 96);

/* ============================================================
 * 7.Gb (Corrigé) — JSON-LD pour le Blog et ses Archives Uniquement
 * Cible : Page principale du blog, catégories d'articles, étiquettes, etc.
 * NE se déclenche PAS sur les archives de logements, destinations, etc.
 * ============================================================ */
add_action('wp_head', function () {
    // Conditions de déclenchement strictes pour le blog
    $is_blog_context = is_home() || (is_archive() && (is_category() || is_tag() || is_post_type_archive('post')));
    if (!$is_blog_context || is_admin() || is_feed() || is_404()) {
        return;
    }

    static $done = false; if ($done) return; $done = true;

    // Le reste de la logique est identique à l'ancien bloc 7.Gb
    $type = 'CollectionPage';
    $url = home_url(add_query_arg(null, null));

    if (is_home()) {
        $name = get_the_title((int) get_option('page_for_posts')) ?: 'Magazine';
        $desc = get_bloginfo('description') ?: '';
    } else {
        $name = wp_strip_all_tags(get_the_archive_title());
        $td = term_description(get_queried_object());
        $desc = $td ? wp_strip_all_tags($td) : '';
    }
    $desc = trim($desc);
    if (mb_strlen($desc) > 300) $desc = mb_substr($desc, 0, 300) . '…';

    $lang   = get_bloginfo('language') ?: 'fr-FR';
    $site   = rtrim(home_url('/'), '/') . '/';
    $web_id = $site . '#website';

    global $wp_query;
    $items = [];
    if ($wp_query instanceof WP_Query && !empty($wp_query->posts)) {
        $paged   = max(1, (int) get_query_var('paged'));
        $perpage = (int) get_query_var('posts_per_page', 10);
        $offset  = ($paged - 1) * $perpage;
        $pos     = 1;
        foreach (array_slice($wp_query->posts, 0, 10) as $post_obj) {
            $pid = (int) $post_obj->ID;
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $offset + $pos++,
                'item'     => ['@id' => get_permalink($pid), 'name' => get_the_title($pid)]
            ];
        }
    }
    $mainEntity = !empty($items) ? ['@type' => 'ItemList', 'itemListElement' => $items] : null;

    $data = [
        '@context'         => 'https://schema.org',
        '@type'            => $type,
        'name'             => $name,
        'url'              => $url,
        'inLanguage'       => $lang,
        'isPartOf'         => ['@id' => $web_id],
    ];
    if ($desc !== '') $data['description'] = $desc;
    if ($mainEntity)  $data['mainEntity']  = $mainEntity;

    echo "\n<script type='application/ld+json' class='pc-seo-blog-archive-schema'>"
       . wp_json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
       . "</script>\n";
}, 48);

// ============================================================
// 7.Gc â€” SchÃ©ma pour les Pages de Recherche (Logements ET ExpÃ©riences)
// VERSION FINALE (BasÃ©e sur le nouveau champ ACF `pc_search_type`)
// ============================================================
add_action('wp_footer', function () {
    if (!is_singular('page')) return;

    $page_id = (int) get_queried_object_id();
    if (!$page_id || !function_exists('get_field')) return;

    // On vÃ©rifie d'abord si c'est une page de recherche
    $kind = (string) get_field('pc_schema_kind', $page_id);
    if ($kind !== 'search') {
        return;
    }

    // ENSUITE, on lit votre nouveau champ pour savoir QUEL type de recherche c'est
    $search_type = (string) get_field('pc_search_type', $page_id);
    if (empty($search_type)) {
        return;
    }
    
    $items = [];
    $pos   = 1;

    // --- On choisit le bon moteur de recherche en fonction du nouveau champ ---

    if ($search_type === 'logement' && function_exists('pc_get_filtered_logements')) {
        // --- CAS 1 : RECHERCHE DE LOGEMENTS ---
        $filters = [
            'page' => 1, 'ville' => sanitize_text_field($_GET['ville'] ?? ''),
            'date_arrivee' => sanitize_text_field($_GET['date_arrivee'] ?? ''),
            'date_depart' => sanitize_text_field($_GET['date_depart'] ?? ''),
            'invites' => intval($_GET['invites'] ?? 1),
            'theme' => sanitize_text_field($_GET['theme'] ?? ''),
        ];
        $results = pc_get_filtered_logements($filters);

        if (!empty($results['vignettes'])) {
            foreach ($results['vignettes'] as $vignette) {
                $items[] = [
                    '@type' => 'ListItem', 'position' => $pos++,
                    'item'  => [
                        '@type'   => 'VacationRental',
                        'name'    => $vignette['title'],
                        'url'     => $vignette['link'],
                        'image'   => $vignette['thumb'],
                        'address' => ['@type' => 'PostalAddress', 'addressLocality' => $vignette['city'], 'addressRegion' => 'Guadeloupe', 'addressCountry' => 'GP']
                    ]
                ];
            }
        }

    } elseif ($search_type === 'experience' && function_exists('pc_get_filtered_experiences')) {
        // --- CAS 2 : RECHERCHE D'EXPÃ‰RIENCES ---
        $filters = [
            'page' => 1, 'category' => sanitize_text_field($_GET['categorie'] ?? ''),
            'ville' => sanitize_text_field($_GET['ville'] ?? ''),
            'keyword' => sanitize_text_field($_GET['keyword'] ?? ''),
        ];
        $results = pc_get_filtered_experiences($filters);

        if (!empty($results['vignettes'])) {
            foreach ($results['vignettes'] as $vignette) {
                $items[] = [
                    '@type' => 'ListItem', 'position' => $pos++,
                    'item'  => [
                        '@type' => 'Trip',
                        'name'  => $vignette['title'],
                        'url'   => $vignette['link'],
                        'image' => $vignette['thumb'],
                    ]
                ];
            }
        }
    }

    // S'il n'y a rien Ã  montrer, on s'arrÃªte.
    if (empty($items)) return;

    // --- Construction du schÃ©ma final ---
    $data = [
        '@context'         => 'https://schema.org',
        '@type'            => 'SearchResultsPage',
        'name'             => get_the_title($page_id),
        'url'              => get_permalink($page_id),
        'mainEntityOfPage' => get_permalink($page_id),
        'mainEntity'       => ['@type' => 'ItemList', 'itemListElement' => $items],
    ];

    echo "\n<script type='application/ld+json' class='pc-seo-search-results-schema'>"
       . wp_json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
       . "</script>\n";
}, 1);

/* ============================================================
 * 7.B â€” JSON-LD BreadcrumbList (Home > â€¦ > Page courante)
 * - Pas sur 404/410
 * - GÃ¨re : singular (page/post/CPT), archives, taxonomies
 * - Essaie dâ€™insÃ©rer lâ€™archive du CPT si disponible
 * - ChaÃ®ne parentale pour les pages hiÃ©rarchiques
 * - Filtrable via 'pcseo_breadcrumb_items'
 * ============================================================ */
add_action('wp_footer', function () {
  // Pas en admin / Elementor Ã©dit
  if (is_admin()) return;
  if (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->editor->is_edit_mode()) return;

  // Pas sur 404/410
  if (is_404() || get_query_var('pcseo_is_410')) return;
  
  if ( is_front_page() ) return;

  $items = [];
  $pos   = 1;

  // 1) Home
  $home_url  = home_url('/');
  $site_name = get_bloginfo('name');
  $items[] = [
    '@type'    => 'ListItem',
    'position' => $pos++,
    'name'     => $site_name ?: 'Accueil',
    'item'     => $home_url,
  ];

  // 2) Contexte
  if (is_singular()) {
    $id  = get_queried_object_id();
    $pt  = get_post_type($id);
    $perma = get_permalink($id);

    // 2.a â€” si post (blog) et page des articles configurÃ©e
    if ($pt === 'post') {
      $blog_id = (int) get_option('page_for_posts');
      if ($blog_id) {
        $items[] = [
          '@type'=>'ListItem','position'=>$pos++,
          'name' => get_the_title($blog_id),
          'item' => get_permalink($blog_id),
        ];
      }
    }

    // 2.b â€” archive OU page override (logements/destinations/â€¦)
$override = apply_filters('pcseo_breadcrumb_archive_map', [
  'villa'       => '/location-villa/',
  'appartement' => '/location-appartement/',
  'destination' => '/destinations/',
  // 'experience'  => '/experiences/', // tu peux laisser lâ€™archive native si elle existe
]);

if (isset($override[$pt])) {
  $path = $override[$pt];
  $url  = home_url( user_trailingslashit(ltrim($path, '/')) );

  // Si une vraie page existe, on prend son titre, sinon un label par dÃ©faut
  $title = '';
  $page_obj = get_page_by_path( trim($path, '/') );
  if ($page_obj) {
    $title = get_the_title($page_obj);
  }
  if ($title === '' || $title === null) {
    $labels = [
      'villa'       => 'location-villa',
      'appartement' => 'location-appartement',
      'destination' => 'destinations',
      'experience'  => 'ExpÃ©riences',
    ];
    $title = $labels[$pt] ?? ucfirst($pt);
  }

  // Ã‰vite le doublon si lâ€™URL override == URL courante
  if ($url !== get_permalink($id)) {
    $items[] = [
      '@type'=>'ListItem','position'=>$pos++,
      'name' => $title,
      'item' => $url,
    ];
  }
} else {
  // fallback : archive native si disponible
  $pto = get_post_type_object($pt);
  if ($pto && !empty($pto->has_archive)) {
    $archive_url = get_post_type_archive_link($pt);
    if ($archive_url && $archive_url !== get_permalink($id)) {
      $items[] = [
        '@type'=>'ListItem','position'=>$pos++,
        'name' => $pto->labels->name ?: ucfirst($pt),
        'item' => $archive_url,
      ];
    }
  }
}

    // 2.c â€” si type hiÃ©rarchique : ajouter parents (pages)
    if (is_post_type_hierarchical($pt)) {
      $anc = array_reverse( get_post_ancestors($id) );
      foreach ($anc as $pid) {
        $items[] = [
          '@type'=>'ListItem','position'=>$pos++,
          'name' => get_the_title($pid),
          'item' => get_permalink($pid),
        ];
      }
    }

    // 2.d â€” Ã©lÃ©ment courant
    $items[] = [
      '@type'=>'ListItem','position'=>$pos++,
      'name' => get_the_title($id),
      'item' => $perma,
    ];
  }
  elseif (is_category() || is_tag() || is_tax()) {
    // Taxonomies : parents â†’ terme courant
    $term = get_queried_object();
    if ($term && !is_wp_error($term)) {
      // parent chain
      $anc_ids = array_reverse( get_ancestors($term->term_id, $term->taxonomy, 'taxonomy') );
      foreach ($anc_ids as $tid) {
        $t = get_term($tid, $term->taxonomy);
        if ($t && !is_wp_error($t)) {
          $items[] = [
            '@type'=>'ListItem','position'=>$pos++,
            'name' => $t->name,
            'item' => get_term_link($t),
          ];
        }
      }
      // courant
      $items[] = [
        '@type'=>'ListItem','position'=>$pos++,
        'name' => $term->name,
        'item' => get_term_link($term),
      ];
    }
  }
  elseif (is_post_type_archive()) {
    $pt = get_query_var('post_type');
    if (!$pt) $pt = get_post_type();
    $pto = $pt ? get_post_type_object($pt) : null;
    $items[] = [
      '@type'=>'ListItem','position'=>$pos++,
      'name' => ($pto && $pto->labels->name) ? $pto->labels->name : 'Archive',
      'item' => home_url( add_query_arg(null, null) ),
    ];
  }
  elseif (is_home() && !is_front_page()) {
    // page des articles
    $blog_id = (int) get_option('page_for_posts');
    if ($blog_id) {
      $items[] = [
        '@type'=>'ListItem','position'=>$pos++,
        'name' => get_the_title($blog_id),
        'item' => get_permalink($blog_id),
      ];
    }
  }
  else {
    // Front page / recherche / autres : on ne sort pas de breadcrumb (inutile)
    return;
  }

  // Personnalisation possible
  $items = apply_filters('pcseo_breadcrumb_items', $items);

  // Sanitize + sortie
  $items = array_values(array_filter($items, function($li){
    return !empty($li['name']) && !empty($li['item']);
  }));

  if (count($items) < 2) return; // au moins Home > Courant

  $json = wp_json_encode([
    '@context'        => 'https://schema.org',
    '@type'           => 'BreadcrumbList',
    'itemListElement' => $items,
  ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

  if ($json) {
    echo "\n<script type='application/ld+json' class='pc-seo-breadcrumb'>{$json}</script>\n";
  }
}, 100);

/* ============================================================
 * 7.C â€” JSON-LD globals : Organization + WebSite (+ SearchAction)
 * ============================================================ */
add_action('wp_head', function () {
  // Gardes
  if (is_admin()) return;
  if (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->editor->is_edit_mode()) return;
  $uri = $_SERVER['REQUEST_URI'] ?? '';
  if (is_feed() || strpos($uri,'/wp-sitemap')!==false || strpos($uri,'/robots.txt')!==false) return;

  /* ---- helpers (shims) ---- */
  if (!function_exists('pcseo_clean_lines')) {
    function pcseo_clean_lines($txt){
      $out=[]; foreach (preg_split('/\R+/', (string)$txt) as $l){ $l=trim($l); if($l!=='') $out[]=$l; } return $out;
    }
  }
  if (!function_exists('pcseo_jsonld_print')) {
    function pcseo_jsonld_print($data, $class){
      static $done=[]; $key=$class.($data['@type']??'');
      if(isset($done[$key])) return;
      // filtre rÃ©cursif
      $f = function($v) use (&$f){ if (is_array($v)) { $v=array_filter($v,$f); } return $v!=='' && $v!==null && $v!==[]; };
      $json = wp_json_encode(array_filter($data,$f), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
      if ($json) echo "\n<script type='application/ld+json' class='".esc_attr($class)."'>$json</script>\n";
      $done[$key]=1;
    }
  }
  if (!function_exists('pcseo_get_option')) {
    function pcseo_get_option($key){ return function_exists('get_field') ? (get_field($key,'option') ?: '') : get_option($key,''); }
  }
  
  /* ---- Options ACF (avec fallbacks) ---- */
  $site_url  = pcseo_get_option('pc_org_url') ?: home_url('/');
  $site_url  = trailingslashit($site_url);
  $site_name = pcseo_get_option('pc_org_name') ?: get_bloginfo('name');
  $legal     = pcseo_get_option('pc_org_legal_name') ?: '';
  $logo      = pcseo_get_option('pc_org_logo') ?: ''; // URL dans tes Options ACF

  if (!$logo && function_exists('get_custom_logo')) {
    $cid = get_theme_mod('custom_logo'); if ($cid) $logo = wp_get_attachment_image_url($cid, 'full');
  }
  if (!$logo && function_exists('get_site_icon_url')) { $logo = get_site_icon_url(); }

  $phone = pcseo_get_option('pc_org_phone') ?: '';
  $email = pcseo_get_option('pc_org_email') ?: '';
  $vat   = pcseo_get_option('pc_org_vat_id') ?: '';

  $addr = [
    '@type'           => 'PostalAddress',
    'streetAddress'   => pcseo_get_option('pc_org_address_street') ?: '',
    'addressLocality' => pcseo_get_option('pc_org_address_locality') ?: '',
    'addressRegion'   => pcseo_get_option('pc_org_address_region') ?: '',
    'postalCode'      => pcseo_get_option('pc_org_address_postal') ?: '',
    'addressCountry'  => pcseo_get_option('pc_org_address_country') ?: '',
  ];
  $addr = array_filter($addr, fn($v)=>$v!=='' && $v!==null);

  $sameas = pcseo_clean_lines( pcseo_get_option('pc_org_sameas') ?: '' );

  $lang   = get_bloginfo('language') ?: 'fr-FR';
  $org_id = $site_url.'#organization';
  $web_id = $site_url.'#website';

  /* ---- Organization ---- */
  $org = [
    '@context'   => 'https://schema.org',
    '@type'      => 'Organization',
    '@id'        => $org_id,
    'url'        => $site_url,
    'name'       => $site_name,
    'inLanguage' => $lang,
  ];
  if ($legal)   $org['legalName'] = $legal;
  if ($logo)    $org['logo']      = $logo;
  if ($phone)   $org['telephone'] = $phone;
  if ($email)   $org['email']     = $email;
  if ($vat)     $org['vatID']     = $vat;
  if (!empty($addr) && count($addr)>1) $org['address'] = $addr;
  if (!empty($sameas)) $org['sameAs'] = array_values($sameas);

  pcseo_jsonld_print($org, 'pc-seo-organization-schema');

  /* ---- WebSite (+ SearchAction) ---- */
  $search_target = pcseo_get_option('pc_site_search_target') ?: '';
  if ($search_target === '' || stripos($search_target,'{search_term_string}')===false) {
    $search_target = home_url('/?s={search_term_string}');
  }

  $web = [
    '@context'      => 'https://schema.org',
    '@type'         => 'WebSite',
    '@id'           => $web_id,
    'url'           => $site_url,
    'name'          => $site_name,
    'inLanguage'    => $lang,
    'publisher'     => ['@id' => $org_id],
    'potentialAction' => [
      '@type'       => 'SearchAction',
      'target'      => $search_target,
      'query-input' => 'required name=search_term_string',
    ],
  ];

  pcseo_jsonld_print($web, 'pc-seo-website-schema');
}, 48);

/* ==================================================================
 * BLOC FINAL (DÉFINITIF) — WebPage + ItemList + FAQ
 * Gère tous les schémas pour les pages statiques (post_type=page).
 * ================================================================== */

// Helpers pour lire les champs et imprimer le JSON-LD
if (!function_exists('pcd_get')) {
    function pcd_get($name, $post_id, $default = '') {
        if (function_exists('get_field')) {
            $v = get_field($name, $post_id);
            return ($v === null || $v === false) ? $default : $v;
        }
        return get_post_meta($post_id, $name, true) ?: $default;
    }
}
if (!function_exists('pcd_print_jsonld')) {
    function pcd_print_jsonld(array $data, string $class = '') {
        if (empty($data)) return;
        $json = wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $class_attr = $class ? " class='" . esc_attr($class) . "'" : '';
        echo "\n<script type='application/ld+json'{$class_attr}>{$json}</script>\n";
    }
}

add_action('wp_head', function () {
    if (!is_singular('page')) return;

    $page_id = get_queried_object_id();
    if (!$page_id) return;

    $kind = pcd_get('pc_schema_kind', $page_id, 'generic');
    $item_ids = [];

    // --- 1. Construction du schéma WebPage de base ---
    $url = get_permalink($page_id);
    $types = ['WebPage'];
    
    if (in_array($kind, ['accueil', 'category'])) {
        $types[] = 'CollectionPage';
    }

    $webpage = [
        '@context' => 'https://schema.org',
        '@type'    => array_unique($types),
        '@id'      => trailingslashit($url) . '#webpage',
        'url'      => $url,
        'name'     => get_the_title($page_id),
        'isPartOf' => ['@id' => rtrim(home_url('/'), '/') . '/#website'],
    ];
    $desc_acf = pcd_get('pc_meta_description', $page_id);
    if($desc_acf) $webpage['description'] = $desc_acf;

    // --- 2. Logique ItemList (UNIQUEMENT pour 'accueil' et 'category') ---
    if (in_array($kind, ['accueil', 'category'])) {
        $category_slug_to_query = '';
        $is_paginated = false;

        if ($kind === 'accueil' && is_front_page()) {
            $category_slug_to_query = 'accueil';
        } else {
            $page_slug = get_post_field('post_name', $page_id);
            $slug_to_category_map = [
                'location-appartement-en-guadeloupe'   => 'appartements',
                'promotion-villa-en-guadeloupe'        => 'promotions',
                'location-grande-villa-en-guadeloupe'  => 'grandes-villas',
                'location-villa-en-guadeloupe'         => 'villas-traditions',
                'location-villa-de-luxe-en-guadeloupe' => 'villas-prestige',
            ];
            if (isset($slug_to_category_map[$page_slug])) {
                $category_slug_to_query = $slug_to_category_map[$page_slug];
                $is_paginated = true;
            }
        }

        if (!empty($category_slug_to_query)) {
            $current_page = max(1, get_query_var('paged'), get_query_var('page'));
            if (!empty($_GET)) {
                foreach ($_GET as $key => $value) {
                    if (strpos($key, 'e-page-') === 0 && is_numeric($value)) {
                        $current_page = (int) $value;
                        break;
                    }
                }
            }
            
            $query_args = [
                'post_type' => ['logement', 'villa', 'appartement'], 'post_status' => 'publish', 'fields' => 'ids', 'no_found_rows' => true,
                'tax_query' => [['taxonomy' => 'categorie_logement', 'field' => 'slug', 'terms' => $category_slug_to_query]],
            ];

            if ($is_paginated) {
                $query_args['posts_per_page'] = 6;
                $query_args['paged'] = $current_page;
            } else {
                $query_args['posts_per_page'] = -1;
            }
            
            $loop_query = new WP_Query($query_args);
            $item_ids = $loop_query->posts;

            if (!empty($item_ids)) {
                $items = [];
                $pos = 1;
                if ($is_paginated) {
                    $posts_per_page = 6;
                    $pos = (($current_page - 1) * $posts_per_page) + 1;
                }
                foreach ($item_ids as $pid) {
                    $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'item' => ['@id' => get_permalink($pid), 'name' => get_the_title($pid)]];
                }
                if (!empty($items)) {
                    $webpage['mainEntity'] = ['@type' => 'ItemList', 'itemListElement' => $items, 'numberOfItems' => count($items)];
                }
            }
        }
    }

    pcd_print_jsonld($webpage, 'pc-seo-page-schema');

    // --- 3. Générer le schéma FAQ (s'exécute pour TOUTES les pages si le champ est rempli) ---

// VÉRIFICATION DU VERROU : Si une FAQ a déjà été imprimée, on s'arrête ici.
if (!empty($GLOBALS['pc_faq_schema_printed'])) { return; }

$faq_rows = pcd_get('pc_faq_items', $page_id, []);
if (is_array($faq_rows) && !empty($faq_rows)) {
    $main = [];
    foreach ($faq_rows as $row) {
        $q = isset($row['question']) ? trim(wp_strip_all_tags($row['question'])) : '';
        $a = isset($row['answer']) ? trim(wp_strip_all_tags($row['answer'])) : '';

        if ($q !== '' && $a !== '') {
            $main[] = [
                '@type' => 'Question',
                'name'  => $q,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $a
                ]
            ];
        }
    }
    if (!empty($main)) {
        $faq_schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'FAQPage',
            'mainEntity'  => $main
        ];
        pcd_print_jsonld($faq_schema, 'pc-seo-faq-schema');

        // ON POSE LE VERROU : On indique que la FAQ a été imprimée.
        $GLOBALS['pc_faq_schema_printed'] = true;
    }
}
}, 11);

/* ==================================================================
 * BLOC UNIFIÉ CPT — Schémas pour Destination, Expérience & Logement
 * Génère TouristDestination pour les fiches Destination.
 * Génère FAQPage pour tous les CPTs concernés.
 * ================================================================== */

add_action('wp_head', function () {
    // Garde-fous
    if (function_exists('\Elementor\Plugin::instance') && \Elementor\Plugin::$instance->editor->is_edit_mode()) return;
    if (!is_singular(['destination', 'experience', 'villa', 'appartement', 'logement'])) return;

    $p = get_queried_object();
    if (!$p || !isset($p->post_type)) return;

    // --- LOGIQUE SPÉCIFIQUE À LA DESTINATION ---
    if ($p->post_type === 'destination') {
        $name      = pcd_get('dest_h1', $p->ID) ?: get_the_title($p->ID);
        $intro_raw = pcd_get('dest_intro', $p->ID) ?: get_the_excerpt($p->ID);
        $desc      = trim(wp_strip_all_tags($intro_raw));
        $url       = get_permalink($p->ID);
        
        // ** LOGIQUE DE RÉCUPÉRATION D'IMAGE CORRIGÉE **
        $img_data  = pcd_get('dest_hero_desktop', $p->ID);
        $img_url   = '';
        if (is_array($img_data) && !empty($img_data['url'])) {
            $img_url = $img_data['url']; // Cas 1: Le champ retourne un tableau ACF
        } elseif (is_numeric($img_data)) {
            $img_url = wp_get_attachment_image_url($img_data, 'full'); // Cas 2: Le champ retourne un ID numérique
        } elseif (is_string($img_data)) {
            $img_url = $img_data; // Cas 3: Le champ retourne déjà une URL
        }

        // Construction du schéma TouristDestination
        $destination_schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'TouristDestination',
            'name'        => $name,
            'url'         => $url,
        ];
        if ($desc) $destination_schema['description'] = $desc;
        if ($img_url) $destination_schema['image'] = esc_url($img_url); // On s'assure que l'URL est propre

        // On l'imprime
        pcd_print_jsonld($destination_schema, 'pc-seo-destination-schema');
    }

    // --- LOGIQUE FAQ (COMMUNE À TOUS LES CPTs) ---
    if (empty($GLOBALS['pc_faq_schema_printed'])) {
        $field_map = [
            'destination' => 'dest_faq', 'experience' => 'exp_faq',
            'villa' => 'log_faq', 'appartement' => 'log_faq', 'logement' => 'log_faq',
        ];

        if (array_key_exists($p->post_type, $field_map)) {
            $rows = pcd_get($field_map[$p->post_type], $p->ID, []);
            if (is_array($rows) && !empty($rows)) {
                $question_keys = ['question', 'exp_question', 'dest_question', 'log_question'];
                $answer_keys   = ['answer', 'reponse', 'exp_reponse', 'dest_reponse', 'log_reponse'];
                $main_entity = [];

                foreach ($rows as $row) {
                    $q = ''; $a = '';
                    if (is_array($row)) {
                        foreach ($question_keys as $key) { if (!empty($row[$key])) { $q = $row[$key]; break; } }
                        foreach ($answer_keys as $key) { if (!empty($row[$key])) { $a = $row[$key]; break; } }
                    }
                    $q = trim(wp_strip_all_tags($q));
                    $a = trim(wp_strip_all_tags($a));
                    if ($q !== '' && $a !== '') {
                        $main_entity[] = ['@type' => 'Question', 'name' => $q, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a]];
                    }
                }

                if (!empty($main_entity)) {
                    $faq_schema = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $main_entity];
                    pcd_print_jsonld($faq_schema, 'pc-seo-cpt-faq-schema');
                    $GLOBALS['pc_faq_schema_printed'] = true; // On pose le verrou
                }
            }
        }
    }
}, 12);

// ============================================================
// 7.D+ & 7.E+ â€” Fiches Destination & ExpÃ©rience : ItemList recommandÃ©s (FIABLE)
// VERSION FINALE GÃ‰RANT LOGEMENTS ET EXPÃ‰RIENCES
// ============================================================
add_action('wp_footer', function () {
    if (is_admin() || is_feed() || is_404()) return;
    if (!is_singular(['destination', 'experience']) || !function_exists('get_field')) return;

    $page_id = get_queried_object_id();
    $post_type = get_post_type($page_id);
    
    // --- Fonction interne pour gÃ©nÃ©rer un schÃ©ma ItemList ---
    $generate_itemlist_schema = function(array $ids, $schema_class_name) {
        if (empty($ids)) return;

        $items = [];
        $pos = 1;
        foreach ($ids as $pid) {
            $items[] = ['@type'=>'ListItem', 'position'=>$pos++, 'item'=>['@id'=>get_permalink($pid), 'name'=>get_the_title($pid)]];
        }
        if (empty($items)) return;

        $data = ['@context'=>'https://schema.org', '@type'=>'ItemList', 'itemListElement'=>$items];
        echo "\n<script type='application/ld+json' class='{$schema_class_name}'>"
           . wp_json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
           . "</script>\n";
    };

    // --- On gÃ©nÃ¨re les schÃ©mas en fonction de la page ---
    if ($post_type === 'destination') {
        // Pour les destinations, on gÃ©nÃ¨re DEUX listes si les donnÃ©es existent
        $logement_ids = (array) get_field('dest_logements_recommandes', $page_id);
        $experience_ids = (array) get_field('dest_exp_featured', $page_id);

        $generate_itemlist_schema($logement_ids, 'pc-seo-destination-lodging-list');
        $generate_itemlist_schema($experience_ids, 'pc-seo-destination-experience-list');

    } elseif ($post_type === 'experience') {
        // Pour les expÃ©riences, on ne gÃ©nÃ¨re qu'une liste de logements
        $logement_ids = (array) get_field('exp_logements_recommandes', $page_id);
        $generate_itemlist_schema($logement_ids, 'pc-seo-experience-lodging-list');
    }
}, 52);

/* ==================================================================
 * BLOC DE SÉCURITÉ — Gardien Anti-Doublons de Schéma FAQ
 * Objectif : Supprimer tout schéma FAQPage qui n'est pas généré par nos blocs.
 * ================================================================== */
add_action('template_redirect', function () {
    // Ne s'exécute que sur le front-end
    if (is_admin() || wp_doing_ajax()) return;

    ob_start(function($html){
        // Regex pour trouver tous les scripts ld+json
        if (!preg_match_all('~<script\b[^>]*type=["\']application/ld\+json["\'][^>]*>.*?</script>~is', $html, $matches)) {
            return $html;
        }

        $valid_faq_found = false;
        $tags_to_remove = [];

        foreach ($matches[0] as $script_tag) {
            // Est-ce que ce script contient un schéma FAQ ?
            if (strpos($script_tag, '"@type":"FAQPage"') !== false) {
                // Est-ce que c'est un de nos schémas légitimes (qui ont une classe) ?
                if (preg_match('/class=["\'][^"\']*pc-seo-[^"\']*-schema[^"\']*["\']/i', $script_tag)) {
                    // Si on a déjà trouvé un de nos schémas FAQ, celui-ci est un doublon légitime, on le retire.
                    if ($valid_faq_found) {
                        $tags_to_remove[] = $script_tag;
                    } else {
                        // C'est le premier de nos schémas qu'on trouve, on le garde.
                        $valid_faq_found = true;
                    }
                } else {
                    // Ce schéma FAQ n'a pas notre classe, c'est un intrus. On le retire.
                    $tags_to_remove[] = $script_tag;
                }
            }
        }

        // Si on a trouvé des tags à supprimer, on les retire du code HTML
        if (!empty($tags_to_remove)) {
            $html = str_replace($tags_to_remove, '', $html);
        }

        return $html;
    });
}, 9999);

/* ============================================================
 * 8. — OPTIMISATIONS DE PERFORMANCE
 * ============================================================ */

/*
 * 8.A - Supprimer le CSS des blocs WordPress (Gutenberg) si non utilisé
 * S'active sur les pages construites avec Elementor qui n'utilisent pas l'éditeur de blocs.
 */
add_action('wp_enqueue_scripts', function() {
    // Cible les pages, articles, et CPTs. On exclut les archives.
    if (is_singular()) {
        global $post;
        // Si la page n'a pas de blocs Gutenberg, on retire les CSS inutiles.
        if ($post && !has_blocks($post->post_content)) {
            wp_dequeue_style('wp-block-library');
            wp_dequeue_style('wp-block-library-theme');
            wp_dequeue_style('classic-theme-styles'); // Pour les thèmes plus anciens
        }
    }
    // Pour les archives (comme la page blog), on les retire systématiquement.
    elseif (is_home() || is_archive() || is_search()) {
        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('wp-block-library-theme');
        wp_dequeue_style('classic-theme-styles');
    }
}, 100);