<?php
/**
 * Plugin Name: PC - Shortcode Liste Logements
 * Description: Fournit un shortcode [liste_logements_dropdown] pour afficher un menu déroulant de logements.
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

/**
 * Charge les fichiers CSS et JS dédiés au shortcode.
 * Les chemins sont mis à jour pour correspondre aux nouveaux noms de fichiers.
 */
function pc_sll_enqueue_assets() {
    // CSS
    wp_enqueue_style(
        'pc-sll-css',
        plugins_url('assets/shortcode-liste-logement.css', __FILE__),
        [],
        '1.0'
    );

    // JS
    wp_enqueue_script(
        'pc-sll-js',
        plugins_url('assets/shortcode-liste-logement-v2.js', __FILE__),
        [], // Le JS est autonome, pas de dépendance nécessaire
        '1.0',
        true // Charger dans le footer
    );
}

/**
 * Récupère la liste des villes depuis la taxonomie.
 */
function pc_sll_get_villes(): array {
  $out = [];
  $terms = get_terms(['taxonomy' => 'ville', 'hide_empty' => true]);
  if (!is_wp_error($terms) && $terms) {
    foreach ($terms as $t) { $out[$t->slug] = $t->name; }
  }
  return $out;
}


/**
 * Génère le HTML du shortcode.
 */
function pc_sll_render_dropdown($atts = []) {
    // On s'assure que les assets sont chargés uniquement si le shortcode est utilisé.
    pc_sll_enqueue_assets();

    $a = shortcode_atts([
      'label'    => 'Nos logements',
      'max'      => '5', // Nombre de logements visibles avant scroll
      'search'   => '#', // Lien du bouton "Rechercher"
    ], $atts, 'liste_logements_dropdown');

    // Requête pour récupérer les logements (villas et appartements)
    $lis = '';
    $q = new WP_Query([
        'post_type'      => ['villa', 'appartement'],
        'post_status'    => 'publish',
        'posts_per_page' => 100,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'fields'         => 'ids',
    ]);

    if ($q->have_posts()) {
        foreach ($q->posts as $pid) {
          $title = get_the_title($pid);
          $url   = get_permalink($pid);
          $lis  .= '<li class="pc-dd__item" role="none"><a role="menuitem" href="'.esc_url($url).'">'.esc_html($title).'</a></li>';
        }
    }
    wp_reset_postdata();

    if ($lis === '') {
      $lis = '<li class="pc-dd__item" role="none"><span role="menuitem" aria-disabled="true">Aucun logement</span></li>';
    }

    // Markup final du composant
    $chev = '<svg class="pc-dd__chev" width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M7.41 8.58 12 13.17l4.59-4.59L18 10l-6 6-6-6z"/></svg>';

    ob_start(); ?>
    <div class="pc-dd pc-dd--lodgings" data-pc-dd-max="<?php echo (int)$a['max']; ?>">
      <button type="button" class="pc-dd__btn">
        <span><?php echo esc_html($a['label']); ?></span><?php echo $chev; ?>
      </button>

    <div class="pc-dd__panel" hidden role="menu">
        <div class="pc-dd__search">
            <input type="text" class="pc-dd__filter" placeholder="Rechercher un logement par nom…" inputmode="search" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" name="sll-no-autofill" />
        </div>
        <ul class="pc-dd__list" role="none"><?php echo $lis; ?></ul>
        <div class="pc-dd__footer">
          <a class="pc-dd__search" href="<?php echo esc_url($a['search']); ?>">
            <span>Rechercher</span><?php echo $chev; ?>
          </a>
        </div>
      </div>
    </div>
    <?php
    return ob_get_clean();
}

// On enregistre le nouveau nom de shortcode
add_shortcode('liste_logements_dropdown', 'pc_sll_render_dropdown');