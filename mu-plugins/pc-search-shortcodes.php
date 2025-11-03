<?php
/**
 * MU Plugin: PC Search Shortcodes (v6 – Final Responsive Fix)
 */
if (!defined('ABSPATH')) exit;

/* ---------------- Enqueue CSS/JS seulement si shortcode présent ---------------- */
/**
 * Patch: enqueuer universel pour charger CSS/JS aussi quand la "barre simple" est utilisée
 * À coller dans pc-search-shortcodes.php (remplace/ajoute la fonction pc_ss_enqueue_assets())
 */
if (!function_exists('pc_ss_enqueue_assets')){
function pc_ss_enqueue_assets(){
$ver = '7.4.2';


// CSS principal
if (!wp_style_is('pc-ss', 'enqueued')){
wp_enqueue_style(
'pc-ss',
plugins_url('assets/pc-search-shortcodes.css', __FILE__),
[],
$ver
);
}


// Flatpickr CSS (fallback si non chargé ailleurs)
if (!wp_style_is('flatpickr', 'enqueued')){
wp_enqueue_style('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], '4.6.13');
}


// Flatpickr JS + locale FR + rangePlugin (ordre garanti)
if (!wp_script_is('flatpickr', 'enqueued')){
wp_enqueue_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', [], '4.6.13', true);
}
if (!wp_script_is('flatpickr-fr', 'enqueued')){
wp_enqueue_script('flatpickr-fr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js', ['flatpickr'], '4.6.13', true);
}
if (!wp_script_is('flatpickr-range', 'enqueued')){
wp_enqueue_script('flatpickr-range', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/rangePlugin.js', ['flatpickr'], '4.6.13', true);
}


// JS du composant (dépend de flatpickr + fr + range)
if (!wp_script_is('pc-ss', 'enqueued')){
wp_enqueue_script(
'pc-ss',
plugins_url('assets/pc-search-shortcodes.js', __FILE__),
['jquery','flatpickr','flatpickr-fr','flatpickr-range'],
$ver,
true
);
}


// Localize minimal pour AJAX
if (!wp_script_is('pc-ss-local', 'enqueued')){
wp_register_script('pc-ss-local', '', [], $ver, true);
wp_enqueue_script('pc-ss-local');
wp_add_inline_script('pc-ss-local', 'window.pc_search_params = window.pc_search_params || { ajax_url: "'. esc_js(admin_url('admin-ajax.php')) .'", nonce: "'. esc_js(wp_create_nonce('pc_search_nonce')) .'" };');
}
}
}

/* ----------------------- Liste des villes (taxo ou ACF) ----------------------- */
function pc_ss_get_villes(): array {
  $out = [];
  // Option A : taxonomy "ville"
  $terms = get_terms(['taxonomy' => 'ville', 'hide_empty' => true]);
  if (!is_wp_error($terms) && $terms) {
    foreach ($terms as $t) { $out[$t->slug] = $t->name; }
  }
  // Option B : meta/ACF "ville"
  if (!$out) {
    global $wpdb;
    $rows = $wpdb->get_col("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'ville' AND meta_value <> '' ORDER BY meta_value ASC");
    if ($rows) { foreach ($rows as $val) { $out[sanitize_title($val)] = $val; } }
  }
  return $out;
}

/* ----------------------- Liste des équipements (taxo) ------------------------- */
function pc_ss_get_equipements(): array {
  $out = [];
  // Essaie d'abord une taxonomie classique : equipement / amenity / amenities
  foreach (['equipement','amenity','amenities'] as $tax) {
    if (!taxonomy_exists($tax)) continue;
    $terms = get_terms(['taxonomy' => $tax, 'hide_empty' => true]);
    if (!is_wp_error($terms) && $terms) {
      foreach ($terms as $t) { $out[] = ['slug' => $t->slug, 'name' => $t->name, 'tax' => $tax]; }
      break;
    }
  }
  return $out;
}

/* ----------------------------- Shortcodes ------------------------------------- */
add_action('init', function () {
  add_shortcode('barre_recherche_precise', 'pc_ss_render_precise');
  add_shortcode('barre_recherche_simple',  'pc_ss_render_simple');
});

/* ----------------------- Rendu : barre précise (AJAX + SSR + SEO Schema) ------------------------- */
// - version v9 (FINAL with ItemList connection)

function pc_ss_render_precise($atts = []) {
    pc_ss_enqueue_assets();
    $villes = pc_ss_get_villes();
    $eqs    = pc_ss_get_equipements();

    /**
     * ===================================================================
     * DÉBUT DE LA LOGIQUE DE RENDU CÔTÉ SERVEUR (SSR)
     * ===================================================================
     */
    // [PC START] Modification pour Server-Side Rendering (SSR) systématique
// ===================================================================
$initial_vignettes_html = '';
$initial_pagination_html = '';
$is_initial_ssr_load = true;

// On ne fait pas de SSR si c'est une requête AJAX (pour éviter de le faire 2x)
if (defined('DOING_AJAX') && DOING_AJAX) {
    $is_initial_ssr_load = false;
}

// On exécute systématiquement la recherche initiale pour la première page.
if ($is_initial_ssr_load) {

    // On récupère les filtres de l'URL (si présents) ou les valeurs par défaut
    $filters = [
        'page'         => max(1, intval(get_query_var('paged', 1))), // Gère la pagination native de WordPress
        'ville'        => sanitize_text_field($_GET['ville'] ?? ''),
        'date_arrivee' => sanitize_text_field($_GET['date_arrivee'] ?? ''),
        'date_depart'  => sanitize_text_field($_GET['date_depart'] ?? ''),
        'invites'      => intval($_GET['invites'] ?? 1),
        'theme'        => sanitize_text_field($_GET['theme'] ?? ''),
        'prix_min'     => intval($_GET['prix_min'] ?? 0),
        'prix_max'     => intval($_GET['prix_max'] ?? 0),
        'chambres'     => intval($_GET['chambres'] ?? 0),
        'sdb'          => intval($_GET['sdb'] ?? 0),
        'equipements'  => isset($_GET['equipements']) ? array_map('sanitize_text_field', (array) $_GET['equipements']) : [],
    ];

    // On appelle le moteur de recherche (je suppose que ces fonctions existent dans ton projet)
    $initial_results = function_exists('pc_get_filtered_logements') ? pc_get_filtered_logements($filters) : ['vignettes' => [], 'pagination' => []];

    // Connexion avec le moteur de Schéma SEO
    if (!empty($initial_results['vignettes'])) {
        $result_ids = wp_list_pluck($initial_results['vignettes'], 'id');
        $GLOBALS['pc_itemlist_ids'] = $result_ids;
    }

    // Génération du HTML des vignettes
    if (!empty($initial_results['vignettes'])) {
        $vignette_count = 0;
        $initial_vignettes_html .= '<div class="pc-results-grid">';
        foreach ($initial_results['vignettes'] as $vignette_data) {
            $is_lcp_candidate = ($vignette_count === 0 && $filters['page'] <= 1); // La première vignette de la première page
            if (function_exists('pc_get_vignette_html')) {
                $initial_vignettes_html .= pc_get_vignette_html($vignette_data, $is_lcp_candidate);
            }
            $vignette_count++;
        }
        $initial_vignettes_html .= '</div>';
    } else {
        $initial_vignettes_html = '<div class="pc-no-results"><h3>Aucun logement ne correspond à votre recherche.</h3><p>Essayez d\'ajuster vos filtres.</p></div>';
    }

    // Génération du HTML de la pagination
    $initial_pagination_html = function_exists('pc_get_pagination_html') ? pc_get_pagination_html($initial_results['pagination']) : '';
}
// ===================================================================
// [PC END]
    /**
     * ===================================================================
     * FIN DE LA LOGIQUE SSR
     * ===================================================================
     */

    // Le reste de la fonction (génération du formulaire) ne change pas...
    $options_villes = '';
    foreach ($villes as $slug => $label) {
        $options_villes .= '<option value="'.esc_attr($slug).'">'.esc_html($label).'</option>';
    }

    $equip_group = '';
    if (!empty($eqs)) {
        $chips = '';
        foreach ($eqs as $e) {
            $chips .= '<label class="pc-chip"><input type="checkbox" class="filter-eq" name="equipements[]" value="'.esc_attr($e['slug']).'" data-tax="'.esc_attr($e['tax']).'"><span>'.esc_html($e['name']).'</span></label>';
        }
        $equip_group = '<div class="pc-adv-group"><div class="pc-adv-label" style="margin-bottom:6px;">Équipements</div><div class="pc-adv-chips">'.$chips.'</div></div>';
    }

    // ... sauf ici, où l'on injecte nos résultats pré-calculés.
    $html = <<<HTML
<div class="pc-search-wrapper" data-pc-search-mode="ajax" role="search" aria-label="Recherche d'hébergements">
  <div class="pc-search-shell">
    <form id="pc-filters-form" class="pc-search-form" action="#" method="post" autocomplete="off">
      <div class="pc-search-field pc-search-field--location pc-area-loc"><label for="filter-ville" class="sr-only">Destination</label><select id="filter-ville" name="ville" class="pc-input" aria-label="Destination"><option value="" selected hidden>Destination</option>$options_villes</select></div>
      <div class="pc-search-field pc-search-field--date pc-area-arr"><label for="filter-date-arrivee" class="sr-only">Arrivée</label><input type="text" id="filter-date-arrivee" class="pc-input" placeholder="Arrivée" readonly aria-label="Date d'arrivée"></div>
      <div class="pc-search-field pc-search-field--date pc-area-dep"><label for="filter-date-depart" class="sr-only">Départ</label><input type="text" id="filter-date-depart" class="pc-input" placeholder="Départ" readonly aria-label="Date de départ"></div>
      <input type="hidden" id="filter-date-arrivee-iso" name="date_arrivee"><input type="hidden" id="filter-date-depart-iso"  name="date_depart">
      <div class="pc-search-field pc-search-field--guests pc-area-gst" style="position:relative;"><button type="button" class="pc-input pc-guests-trigger" id="guests-summary" aria-haspopup="dialog" aria-expanded="false">Invités</button><input type="hidden" id="filter-invites" name="invites" value="1"><div class="pc-guests-popover" hidden role="dialog" aria-label="Sélection des invités"><div class="pc-guests-row"><div><strong>Adultes</strong><br><span class="muted">13 ans et +</span></div><div><button type="button" class="guest-stepper" data-type="adultes" data-step="-1" aria-label="Moins d'adultes">−</button><span data-type="adultes" aria-live="polite">1</span><button type="button" class="guest-stepper" data-type="adultes" data-step="1" aria-label="Plus d'adultes">+</button></div></div><div class="pc-guests-row"><div><strong>Enfants</strong><br><span class="muted">2–12 ans</span></div><div><button type="button" class="guest-stepper" data-type="enfants" data-step="-1" aria-label="Moins d'enfants">−</button><span data-type="enfants" aria-live="polite">0</span><button type="button" class="guest-stepper" data-type="enfants" data-step="1" aria-label="Plus d'enfants">+</button></div></div><div class="pc-guests-row"><div><strong>Bébés</strong><br><span class="muted">−2 ans</span></div><div><button type="button" class="guest-stepper" data-type="bebes" data-step="-1" aria-label="Moins de bébés">−</button><span data-type="bebes" aria-live="polite">0</span><button type="button" class="guest-stepper" data-type="bebes" data-step="1" aria-label="Plus de bébés">+</button></div></div><div style="text-align:right;"><button type="button" class="pc-btn pc-btn--line pc-guests-close">Fermer</button></div></div></div>
      <button class="pc-search-submit pc-btn pc-btn--primary pc-area-btn" type="submit">Rechercher</button>
    </form>
    <div class="pc-row-adv-toggle" aria-label="Filtres avancés"><div><button type="button" class="pc-adv-toggle pc-btn pc-btn--line" aria-controls="pc-advanced">Plus de filtres</button></div><div><button type="button" class="pc-adv-reset pc-btn pc-btn--line">Réinitialiser</button></div></div>
    <div id="pc-advanced" class="pc-advanced" hidden><div class="pc-advanced__grid"><div class="pc-adv-field"><div class="pc-adv-label">Prix (€ / nuit)</div><div class="pc-price-range" data-min="0" data-max="2000" data-step="10"><input type="range" id="filter-prix-min-range" min="0" max="2000" step="10" value="0"><input type="range" id="filter-prix-max-range" min="0" max="2000" step="10" value="2000"></div><div class="pc-price-values"><input type="number" id="filter-prix-min" class="pc-input" placeholder="Min" min="0" step="10"><input type="number" id="filter-prix-max" class="pc-input" placeholder="Max" min="0" step="10"></div></div><div class="pc-adv-field"><div class="pc-adv-label">Chambres (min)</div><div class="pc-num-step"><button type="button" class="num-stepper" data-target="chambres" data-step="-1">−</button><input type="number" class="pc-input pc-num-input" id="filter-chambres" min="0" step="1" value="0"><button type="button" class="num-stepper" data-target="chambres" data-step="1">+</button></div></div><div class="pc-adv-field"><div class="pc-adv-label">Salles de bain (min)</div><div class="pc-num-step"><button type="button" class="num-stepper" data-target="sdb" data-step="-1">−</button><input type="number" class="pc-input pc-num-input" id="filter-sdb" min="0" step="1" value="0"><button type="button" class="num-stepper" data-target="sdb" data-step="1">+</button></div></div></div>$equip_group<div class="pc-adv-actions"><button type="button" class="pc-btn pc-btn--line pc-adv-close">Fermer</button></div></div>
  </div>
  <div id="pc-results-container" class="flow" style="margin-top:1rem;">
    $initial_vignettes_html
  </div>
  $initial_pagination_html
</div>
HTML;

    return $html;
}

/* ----------------------- Rendu : barre simple (GET) --------------------------- */
// - version v7
// - Markup full Grid (zones loc/arr/dep/gst/btn)
// - Mode GET (redirection) avec action sur $target
// - Conserve les mêmes IDs pour compatibilité JS (scopé par wrapper)

// Objectif: forcer un target ABSOLU, exposer data-pc-target pour que le JS puisse corriger l'action si un autre script la remplace

function pc_ss_render_simple($atts = []) {
  pc_ss_enqueue_assets();

  $a = shortcode_atts([
    'target' => '',
    'title'  => '',
  ], $atts, 'barre_recherche_simple');

  $target = trim($a['target']);
  if ($target === '') {
    $page = get_page_by_path('recherche');
    $target = $page ? get_permalink($page) : home_url('/');
  }

  // Normaliser en URL absolue
  if (strpos($target, 'http://') !== 0 && strpos($target, 'https://') !== 0) {
    // Chemin relatif → home_url
    $target = home_url( '/' . ltrim($target, '/') );
  }
  // Ajouter un trailing slash si WordPress le fait sur le site
  if (get_option('permalink_structure')) {
    $parsed = wp_parse_url($target);
    if (!isset($parsed['query']) && substr($target, -1) !== '/') {
      $target = trailingslashit($target);
    }
  }

  // Villes
  $villes = function_exists('pc_ss_get_villes') ? pc_ss_get_villes() : [];
  $options_villes = '';
  foreach ($villes as $slug => $label) {
    $options_villes .= '<option value="'.esc_attr($slug).'">'.esc_html($label).'</option>';
  }

  ob_start(); ?>
  <div class="pc-search-wrapper" data-pc-search-mode="get" data-pc-target="<?php echo esc_url($target); ?>" role="search" aria-label="Recherche d'hébergements (simple)">
    <div class="pc-search-shell">

      <form id="pc-filters-form" class="pc-search-form" action="<?php echo esc_url($target); ?>" method="get" autocomplete="off">
        <!-- Destination -->
        <div class="pc-search-field pc-search-field--location pc-area-loc">
          <label for="filter-ville" class="sr-only">Destination</label>
          <select id="filter-ville" name="ville" class="pc-input" aria-label="Destination">
            <option value="" selected hidden>Destination</option>
            <?php echo $options_villes; ?>
          </select>
        </div>

        <!-- Arrivée -->
        <div class="pc-search-field pc-search-field--date pc-area-arr">
          <label for="filter-date-arrivee" class="sr-only">Arrivée</label>
          <input type="text" id="filter-date-arrivee" class="pc-input" placeholder="Arrivée" readonly aria-label="Date d'arrivée">
        </div>

        <!-- Départ -->
        <div class="pc-search-field pc-search-field--date pc-area-dep">
          <label for="filter-date-depart" class="sr-only">Départ</label>
          <input type="text" id="filter-date-depart" class="pc-input" placeholder="Départ" readonly aria-label="Date de départ">
        </div>

        <!-- Valeurs ISO envoyées en GET -->
        <input type="hidden" id="filter-date-arrivee-iso" name="date_arrivee">
        <input type="hidden" id="filter-date-depart-iso"  name="date_depart">

        <!-- Invités -->
        <div class="pc-search-field pc-search-field--guests pc-area-gst" style="position:relative;">
          <button type="button" class="pc-input pc-guests-trigger" id="guests-summary" aria-haspopup="dialog" aria-expanded="false">Invités</button>
          <input type="hidden" id="filter-invites" name="invites" value="1">
          <div class="pc-guests-popover" hidden role="dialog" aria-label="Sélection des invités">
            <div class="pc-guests-row">
              <div><strong>Adultes</strong><br><span class="muted">13 ans et +</span></div>
              <div>
                <button type="button" class="guest-stepper" data-type="adultes" data-step="-1" aria-label="Moins d'adultes">−</button>
                <span data-type="adultes" aria-live="polite">1</span>
                <button type="button" class="guest-stepper" data-type="adultes" data-step="1" aria-label="Plus d'adultes">+</button>
              </div>
            </div>
            <div class="pc-guests-row">
              <div><strong>Enfants</strong><br><span class="muted">2–12 ans</span></div>
              <div>
                <button type="button" class="guest-stepper" data-type="enfants" data-step="-1" aria-label="Moins d'enfants">−</button>
                <span data-type="enfants" aria-live="polite">0</span>
                <button type="button" class="guest-stepper" data-type="enfants" data-step="1" aria-label="Plus d'enfants">+</button>
              </div>
            </div>
            <div class="pc-guests-row">
              <div><strong>Bébés</strong><br><span class="muted">−2 ans</span></div>
              <div>
                <button type="button" class="guest-stepper" data-type="bebes" data-step="-1" aria-label="Moins de bébés">−</button>
                <span data-type="bebes" aria-live="polite">0</span>
                <button type="button" class="guest-stepper" data-type="bebes" data-step="1" aria-label="Plus de bébés">+</button>
              </div>
            </div>
            <div style="text-align:right;"><button type="button" class="pc-btn pc-btn--line pc-guests-close">Fermer</button></div>
          </div>
        </div>

        <!-- Bouton principal -->
        <button class="pc-search-submit pc-btn pc-btn--primary pc-area-btn" type="submit">Rechercher</button>
      </form>

    </div>
  </div>
  <?php
  return ob_get_clean();
}

// [PC START] Fonction de rendu de vignette optimisée pour le LCP (v2)
// ===================================================================
if (!function_exists('pc_get_vignette_html')) {
    /**
     * Génère le HTML pour une vignette de logement.
     * @param array $data Les données de la vignette (id, title, permalink, image_url, etc.)
     * @param bool  $is_lcp Vrai si c'est la première vignette (élément LCP).
     * @return string Le HTML de la vignette.
     */
    function pc_get_vignette_html($data, $is_lcp = false) {
        // Définition des attributs de base de l'image pour corriger le CLS
        $img_attrs = [
            'src'     => esc_url($data['image_url'] ?? ''),
            'alt'     => esc_attr($data['title'] ?? ''),
            'width'   => '376', // Largeur explicite pour que le navigateur réserve l'espace
            'height'  => '230', // Hauteur explicite pour le ratio
        ];

        // Attributs spécifiques si c'est l'image LCP (la toute première)
        if ($is_lcp) {
            $img_attrs['loading']       = 'eager';       // Charger immédiatement, ne pas attendre
            $img_attrs['fetchpriority'] = 'high';      // Donner une priorité maximale au téléchargement
            $img_attrs['decoding']      = 'async';       // Ne pas bloquer l'affichage pendant le décodage
        } else {
            // Attributs pour toutes les autres images (non LCP)
            $img_attrs['loading']       = 'lazy';        // Charger uniquement quand elles approchent de l'écran
            $img_attrs['decoding']      = 'async';
        }

        // Construction de la chaîne d'attributs HTML
        $img_attrs_str = '';
        foreach ($img_attrs as $key => $val) {
            $img_attrs_str .= $key . '="' . $val . '" ';
        }

        $price_html = !empty($data['price']) ? '<div class="pc-vignette__price">À partir de '.esc_html($data['price']).'€ par nuit</div>' : '';

        $output = '
        <a href="'.esc_url($data['permalink'] ?? '#').'" class="pc-vignette">
            <div class="pc-vignette__image">
                <img '.trim($img_attrs_str).'>
            </div>
            <div class="pc-vignette__content">
                <h3 class="pc-vignette__title">'.esc_html($data['title'] ?? '').'</h3>
                <div class="pc-vignette__location">'.esc_html($data['location'] ?? '').'</div>
                '.$price_html.'
            </div>
        </a>';

        return $output;
    }
}
// [PC START] Injection du CSS critique pour la page de recherche
// ===================================================================
function pc_inject_critical_css_search_page() {
    // S'assure que ce code ne s'exécute que sur la bonne page
    if (is_page('recherche-de-logements')) {
        echo <<<HTML
<style id="pc-critical-css">
/* CSS Critique pour la page de recherche */
.pc-search-shell{background:#fff;border-radius:15px;box-shadow:0 8px 24px rgba(0,0,0,.06);padding:8px;width:100%;}
.pc-search-form{display:grid;grid-template-columns:1fr 1fr;grid-template-areas:"loc loc" "arr dep" "gst gst" "btn btn";gap:8px;align-items:stretch;}
.pc-area-loc{grid-area:loc}.pc-area-arr{grid-area:arr}.pc-area-dep{grid-area:dep}.pc-area-gst{grid-area:gst}.pc-area-btn{grid-area:btn}
.pc-input{width:100%;height:52px;padding:0 14px;border:1px solid #E5E7EB;border-radius:12px;background:#fff;font-size:16px}
.pc-search-submit{height:52px;border-radius:12px;white-space:nowrap}
.pc-row-adv-toggle{margin-top:8px;display:flex;justify-content:space-between;align-items:center}
.pc-results-grid{display:grid;grid-template-columns:1fr;gap:2rem;margin-top:1rem}
.pc-vignette{background:#fff;border-radius:12px;overflow:hidden;text-decoration:none;display:flex;flex-direction:column}
.pc-vignette__image{aspect-ratio:376/230;background-color:#f0f0f0}
.pc-vignette__image img{display:block;width:100%;height:100%;object-fit:cover}
.pc-vignette__content{padding:1rem 1.25rem;display:flex;flex-direction:column;gap:0.75rem}
.pc-vignette__title{font-size:1.25rem;font-weight:600;margin:0;color:#111827}
.pc-vignette__location{font-size:1rem;color:#374151}
</style>
HTML;
    }
}
add_action('wp_head', 'pc_inject_critical_css_search_page', 2); // Priorité 2 pour s'insérer juste après les preloads
// ===================================================================
// [PC END]