<?php
// MU Plugin: Prestige Caraïbes – Fiche Logement Shortcodes (Version finale corrigée)

if (!defined('ABSPATH')) exit;

/**
 * ===================================================================
 * Étape 1 : Chargement des Scripts et Styles
 * ===================================================================
 */
add_action('wp_enqueue_scripts', function () {
  // On ne charge que sur les pages où c'est nécessaire
  if (! is_singular(['villa', 'appartement', 'logement']) && ! is_page(['reserver', 'demande-sejour'])) {
    return;
  }

  // Chargement du style principal de la mise en page
  $ui_css_path = WP_CONTENT_DIR . '/mu-plugins/assets/pc-ui.css';
  if (file_exists($ui_css_path)) {
    $ui_css_url  = content_url('mu-plugins/assets/pc-ui.css');
    $ui_css_ver  = filemtime($ui_css_path);
    wp_enqueue_style('pc-ui', $ui_css_url, ['pc-base'], $ui_css_ver);
  }

  // >> Chargement du script de la galerie filtrable
  $pc_gallery_js_path = WP_CONTENT_DIR . '/mu-plugins/assets/pc-gallerie.js';
  if (file_exists($pc_gallery_js_path)) {
    wp_enqueue_script(
      'pc-gallerie',
      content_url('mu-plugins/assets/pc-gallerie.js'),
      ['glightbox-js'],                   // dépend GLightbox si dispo (déjà enfileté chez vous)
      filemtime($pc_gallery_js_path),
      true
    );
  }
  // --- S'assurer que Flatpickr + locale FR sont chargés ---
  wp_enqueue_style('flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css');
  wp_enqueue_script('flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js', [], null, true);
  wp_enqueue_script('flatpickr-fr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js', ['flatpickr-js'], null, true);

  // Chargement du script d'orchestration (attente Flatpickr + initialisations)
  $orchestrator_js_path = WP_CONTENT_DIR . '/mu-plugins/assets/pc-orchestrator.js';
  if (file_exists($orchestrator_js_path)) {
    $orchestrator_js_url = content_url('mu-plugins/assets/pc-orchestrator.js');
    $orchestrator_js_ver = filemtime($orchestrator_js_path);
    wp_enqueue_script('pc-orchestrator-js', $orchestrator_js_url, [], $orchestrator_js_ver, true);
  }

  // Chargement du script du devis (dépend de flatpickr + orchestrateur)
  $devis_js_path = WP_CONTENT_DIR . '/mu-plugins/assets/pc-devis.js';
  if (file_exists($devis_js_path)) {
    $devis_js_url = content_url('mu-plugins/assets/pc-devis.js');
    $devis_js_ver = filemtime($devis_js_path);
    wp_enqueue_script('pc-devis-js', $devis_js_url, ['flatpickr-fr', 'pc-orchestrator-js'], $devis_js_ver, true);
  }

  // Chargement du script de la barre/modale de réservation
  $logement_js_path = WP_CONTENT_DIR . '/mu-plugins/assets/pc-fiche-logement.js';
  if (file_exists($logement_js_path)) {
    $logement_js_url = content_url('mu-plugins/assets/pc-fiche-logement.js');
    $logement_js_ver = filemtime($logement_js_path);
    wp_enqueue_script('pc-fiche-logement-js', $logement_js_url, ['pc-devis-js'], $logement_js_ver, true);

    // On passe des données de PHP vers notre script pc-fiche-logement-js.
    wp_localize_script('pc-fiche-logement-js', 'pcLogementData', [
      'nonce' => wp_create_nonce('logement_booking_request_nonce')
    ]);
  }

  // Activation du script d'initialisation dans le footer
  add_action('wp_footer', 'pc_render_final_init_script', 99);
}, 20);

/**
 * ===================================================================
 * Étape 2 : Script d'Initialisation Final (Footer)
 * ===================================================================
 */
function pc_render_final_init_script()
{
  if (! is_singular(['villa', 'appartement', 'logement']) && ! is_page(['reserver', 'demande-sejour'])) {
    return;
  }
?>
  <script id="pc-final-init-script-unified">
    window.addEventListener('load', function() {
      try {
        // --- 1. Initialisation de la Galerie [pc_gallery] (Méthode Corrigée) ---
        if (typeof GLightbox !== 'undefined' && document.querySelector('.pc-gallery .glightbox')) {
          const lightbox = GLightbox({
            selector: '.pc-gallery .glightbox',
            loop: true,
            touchNavigation: true
          });
        }

        // --- 2. Initialisation des Calendriers (Flatpickr) ---
        if (typeof flatpickr !== 'undefined' && window.flatpickr.l10ns && window.flatpickr.l10ns.fr) {
          flatpickr.localize(flatpickr.l10ns.fr);

          // a) Calendrier de disponibilité [pc_ical_calendar]
          var icalInput = document.querySelector('.pc-cal-input');
          if (icalInput) {
            var icalContainer = icalInput.closest('.pc-cal');
            var disabledRanges = JSON.parse(icalContainer.querySelector('.pc-ical-disabled-json').textContent || '[]');
            flatpickr(icalInput, {
              inline: true,
              mode: 'range',
              dateFormat: 'Y-m-d',
              locale: 'fr',
              minDate: 'today',
              showMonths: window.innerWidth >= 1025 ? 3 : (window.innerWidth >= 641 ? 2 : 1),
              disable: disabledRanges,
              clickOpens: false,
              allowInput: false
            });
          }

          // b) Calendrier du formulaire de réservation [pc_booking_request_form]
          var formInput = document.querySelector('.pcbk-form .pcbk-date');
          if (formInput) {
            var disabledEl = document.querySelector('.pcbk-disabled-json');
            var ranges = disabledEl ? JSON.parse(disabledEl.value || '[]') : [];
            var disableForm = ranges.map(function(r) {
              return {
                from: r[0],
                to: r[1]
              };
            });
            flatpickr(formInput, {
              locale: 'fr',
              mode: 'range',
              dateFormat: 'Y-m-d',
              minDate: 'today',
              disable: disableForm
            });
          }
        }
      } catch (e) {
        console.error("Erreur lors de l'initialisation des composants.", e);
      }
    });
  </script>
<?php
}

/**
 * ===================================================================
 * Étape 3 : Définition de tous les shortcodes
 * ===================================================================
 */

// [pc_seo_readmore]
add_shortcode('pc_seo_readmore', function ($atts = []) {
  $a = shortcode_atts([
    'bg'      => '',
    'max'     => '220px',
    'variant' => '',
    'fsize'   => '',
    'lheight' => '',
  ], $atts, 'pc_seo_readmore');
  $post = get_post();
  if (!$post || !function_exists('get_field')) return '';
  $html = (string) get_field('seo_long_html', $post->ID);
  if ($html === '') return '';
  $allow_unit = '(?:px|rem|em|%)';
  $fsize   = ($a['fsize']   && preg_match("/^\s*\d*\.?\d+\s*$allow_unit\s*$/", $a['fsize']))   ? trim($a['fsize'])   : '';
  $lheight = ($a['lheight'] && preg_match("/^\s*\d*\.?\d+\s*$allow_unit\s*$/", $a['lheight'])) ? trim($a['lheight']) : '';
  $vars = [];
  if ($a['bg']   !== '') $vars[] = "--pc-seo-bg: {$a['bg']}";
  if ($a['max']  !== '') $vars[] = "--pc-seo-max: {$a['max']}";
  if ($a['variant'] === 'sm') {
    $vars[] = "--pc-seo-fsize:1rem";
    $vars[] = "--pc-seo-lh:1.7em";
  }
  if ($fsize)   $vars[] = "--pc-seo-fsize: {$fsize}";
  if ($lheight) $vars[] = "--pc-seo-lh: {$lheight}";
  $style_attr = $vars ? ' style="' . esc_attr(implode(';', $vars)) . '"' : '';
  $id = 'pc-seo-' . wp_rand(1000, 999999);
  ob_start(); ?>
  <section id="<?php echo esc_attr($id); ?>" class="pc-seo-box" <?php echo $style_attr; ?>>
    <div class="pc-seo__content" aria-expanded="false"><?php echo $html; ?></div>
    <div class="pc-seo__fade" aria-hidden="true"></div>
    <button type="button" class="pc-seo__toggle" data-more="Voir plus" data-less="Voir moins">Voir plus</button>
    <script>
      (function() {
        var box = document.getElementById('<?php echo esc_js($id); ?>');
        if (!box) return;
        var btn = box.querySelector('.pc-seo__toggle');
        var cnt = box.querySelector('.pc-seo__content');
        if (!btn || !cnt) return;
        btn.addEventListener('click', function() {
          var open = box.classList.toggle('is-open');
          cnt.setAttribute('aria-expanded', open ? 'true' : 'false');
          btn.textContent = open ? (btn.getAttribute('data-less') || 'Voir moins') : (btn.getAttribute('data-more') || 'Voir plus');
        });
      })();
    </script>
  </section>
<?php return ob_get_clean();
});

// [pc_highlights]
if (shortcode_exists('pc_highlights')) {
  remove_shortcode('pc_highlights');
}
add_shortcode('pc_highlights', function ($atts = []) {
  $a = shortcode_atts(['limit' => '', 'icons' => '1'], $atts, 'pc_highlights');
  if (!function_exists('get_field')) return '';
  $post_id = get_the_ID();
  static $pc_fa_link_printed = false;
  if ($a['icons'] !== '0' && !$pc_fa_link_printed) {
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer" />';
    $pc_fa_link_printed = true;
  }
  $checked = (array) get_field('highlights', $post_id);
  $custom  = (string) get_field('highlights_custom', $post_id);
  $label_map = ['parking' => 'Parking', 'internet' => 'Wi-Fi', 'wifi' => 'Wi-Fi', 'piscine' => 'Piscine', 'clim' => 'Climatisation', 'vue_mer' => 'Vue mer', 'front_mer' => 'Front de mer', 'jacuzzi' => 'Jacuzzi', 'spa' => 'Spa', 'barbecue' => 'Barbecue', 'classement' => 'Classement'];
  $fa_map = ['parking' => 'fa-solid fa-square-parking fas fa-parking', 'internet' => 'fa-solid fa-wifi fas fa-wifi', 'wifi' => 'fa-solid fa-wifi fas fa-wifi', 'piscine' => 'fa-solid fa-water fas fa-water', 'clim' => 'fa-regular fa-snowflake far fa-snowflake fas fa-snowflake', 'vue_mer' => 'fa-solid fa-water fas fa-water', 'front_mer' => 'fa-solid fa-umbrella-beach fas fa-umbrella-beach', 'jacuzzi' => 'fa-solid fa-bath fas fa-bath', 'spa' => 'fa-solid fa-bath fas fa-bath', 'barbecue' => 'fa-solid fa-fire fas fa-fire', 'classement' => 'fa-solid fa-star fas fa-star'];
  $entries = [];
  foreach ($checked as $slug) {
    $label = $label_map[$slug] ?? ucfirst(str_replace('_', ' ', $slug));
    if ($label) {
      $entries[] = ['slug' => $slug, 'label' => $label, 'fa' => $fa_map[$slug] ?? '', 'custom' => false];
    }
  }
  if ($custom) {
    foreach (preg_split('/\r\n|\r|\n/', $custom) as $line) {
      $line = trim($line);
      if ($line !== '') {
        $entries[] = ['slug' => null, 'label' => $line, 'fa' => '', 'custom' => true];
      }
    }
  }
  if ($a['limit'] !== '' && is_numeric($a['limit'])) {
    $entries = array_slice($entries, 0, (int) $a['limit']);
  }
  if (empty($entries)) return '';
  ob_start(); ?>
  <div class="pc-hl">
    <?php foreach ($entries as $e): ?>
      <span class="pc-hl__item">
        <?php if ($a['icons'] !== '0' && !$e['custom'] && !empty($e['fa'])): ?>
          <span class="pc-hl__icon" aria-hidden="true"><i class="<?php echo esc_attr($e['fa']); ?>"></i></span>
        <?php endif; ?>
        <span class="pc-hl__label"><?php echo esc_html($e['label']); ?></span>
      </span>
    <?php endforeach; ?>
  </div>
  <?php return ob_get_clean();
});


// [pc_gallery] — v2 (hérité + nouveau filtrable)
add_shortcode('pc_gallery', function ($atts = []) {
  $a = shortcode_atts([
    'limit'   => 6,                 // n images visibles (grille)
    'class'   => '',
    'field'   => 'gallery_urls',    // champ texte (Mode A existant)
  ], $atts, 'pc_gallery');

  $post = get_post();
  if (!$post || !function_exists('get_field')) return '';

  // === MODE A (héritage) : si field "gallery_urls" rempli ===
  $raw = get_field($a['field'], $post->ID, false);
  $urls = [];
  if (!empty($raw)) {
    // Support lignes (retours) et/ou virgules, et repeater éventuel
    if (is_string($raw)) {
      $parts = preg_split('~[\r\n,]+~', trim($raw));
      foreach ($parts as $p) {
        $p = trim($p);
        if ($p) $urls[] = esc_url_raw($p);
      }
    } elseif (is_array($raw)) {
      foreach ($raw as $row) {
        if (is_array($row) && !empty($row['url'])) {
          $urls[] = esc_url_raw($row['url']);
        } elseif (is_string($row)) {
          $urls[] = esc_url_raw($row);
        }
      }
    }
    $urls = array_values(array_unique(array_filter($urls)));
  }

  // Si on a des URLs → garder le rendu existant (grille + bouton)
  if (!empty($urls)) {
    $gallery_id = 'pcg-extern-' . $post->ID;
    $visible = $a['limit'] ? array_slice($urls, 0, (int)$a['limit']) : $urls;
    ob_start(); ?>
    <section class="pc-gallery <?php echo esc_attr($a['class']); ?>"
      data-mode="external"
      data-gallery-id="<?php echo esc_attr($gallery_id); ?>">
      <div class="pc-grid">
        <?php foreach ($visible as $i => $href): ?>
          <a class="pc-item pc-glink" href="<?php echo esc_url($href); ?>"
            data-gallery="<?php echo esc_attr($gallery_id); ?>"
            aria-label="<?php echo esc_attr(sprintf('Voir la photo %d', $i + 1)); ?>">
            <img src="<?php echo esc_url($href); ?>" loading="lazy" decoding="async" alt="" />
          </a>
        <?php endforeach; ?>
      </div>

      <div class="pc-morewrap">
        <button class="pc-more" type="button"
          data-gallery-id="<?php echo esc_attr($gallery_id); ?>"
          data-mode="external"
          data-total="<?php echo esc_attr(count($urls)); ?>">
          <?php echo esc_html(sprintf('Voir les %d photos', count($urls))); ?>
        </button>
      </div>

      <!-- Sources Lightbox (cachées) -->
      <div class="pc-lightbox-src" hidden>
        <?php foreach ($urls as $href): ?>
          <a href="<?php echo esc_url($href); ?>"
            class="pc-glightbox"
            data-group="<?php echo esc_attr($gallery_id); ?>"></a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php
    return ob_get_clean();
  }

  // === MODE B (nouveau) : ACF groupes_images (catégories + images) ===
  $groups = get_field('groupes_images', $post->ID); // répéteur
  $slugify = function ($s) {
    $s = remove_accents(mb_strtolower((string)$s, 'UTF-8'));
    $s = preg_replace('~[^a-z0-9]+~u', '-', $s);
    return trim($s, '-') ?: 'autre';
  };

  $cats = []; // [ ['label'=>..., 'slug'=>..., 'items'=>[['id'=>, 'src'=>, 'alt'=>, 'title'=>], ...]] ]

  // Fonction pour transformer une valeur machine en libellé humain (ex: chambre_1 → Chambre 1)
  $humanize = function ($v) {
    $v = trim((string)$v);
    if ($v === '') return '';
    $v = str_replace('_', ' ', $v);
    return mb_convert_case($v, MB_CASE_TITLE, 'UTF-8');
  };

  if (is_array($groups)) {
    foreach ($groups as $g) {
      if (empty($g)) continue;

      // Définition du label humain
      if (!empty($g['categorie']) && $g['categorie'] !== 'autre') {
        $label = $humanize($g['categorie']); // ex: chambre_1 → Chambre 1
      } else {
        $label = trim((string)($g['categorie_personnalisee'] ?? ''));
      }
      if ($label === '') $label = 'Autre';

      // Slug basé sur le label
      $slug = $slugify($label);

      // Images du groupe
      $images = [];
      if (!empty($g['images_du_groupe']) && is_array($g['images_du_groupe'])) {
        foreach ($g['images_du_groupe'] as $img) {
          $id = is_array($img) && isset($img['ID']) ? (int)$img['ID'] : (is_numeric($img) ? (int)$img : 0);
          if (!$id) continue;
          $src = wp_get_attachment_image_url($id, 'large');
          if (!$src) continue;
          $alt   = get_post_meta($id, '_wp_attachment_image_alt', true);
          $title = get_the_title($id);
          $images[] = ['id' => $id, 'src' => $src, 'alt' => $alt ?: $title, 'title' => $title];
        }
      }

      // Catégorie sans images → ignorée
      if (empty($images)) continue;

      $cats[] = ['label' => $label, 'slug' => $slug, 'items' => $images];
    }
  }

  // Aucune image au total → message
  $total_all = 0;
  foreach ($cats as $c) {
    $total_all += count($c['items']);
  }
  if ($total_all === 0) {
    return '<div class="pc-gallery"><p class="pc-empty">Aucune photo pour ce logement</p></div>';
  }

  $gallery_id = 'pcg-acf-' . $post->ID;

  ob_start(); ?>
  <section class="pc-gallery <?php echo esc_attr($a['class']); ?>"
    data-mode="acf"
    data-gallery-id="<?php echo esc_attr($gallery_id); ?>"
    data-i18n-all="<?php echo esc_attr('Toutes les photos'); ?>"
    data-i18n-see-all="<?php echo esc_attr('Voir les %d photos'); ?>"
    data-i18n-see-cat="<?php echo esc_attr('Voir les %d photos (%s)'); ?>">

    <div class="pc-gallery-toolbar">
      <label class="pc-gallery-label" for="<?php echo esc_attr($gallery_id . '-select'); ?>">Catégorie</label>
      <select id="<?php echo esc_attr($gallery_id . '-select'); ?>"
        class="pc-gallery-select"
        aria-label="Filtrer les photos par catégorie">
        <option value="all" selected>Toutes les photos</option>
        <?php foreach ($cats as $c): ?>
          <option value="<?php echo esc_attr($c['slug']); ?>"><?php echo esc_html($c['label']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="pc-grid" data-limit="<?php echo esc_attr((int)$a['limit']); ?>">
      <!-- Remplie par JS au chargement (ordre ACF respecté) -->
    </div>

    <div class="pc-morewrap">
      <button class="pc-more" type="button"
        data-gallery-id="<?php echo esc_attr($gallery_id); ?>"
        data-mode="acf"
        data-total-all="<?php echo esc_attr($total_all); ?>"
        data-cats="<?php echo esc_attr(wp_json_encode(array_map(function ($c) {
                      return ['slug' => $c['slug'], 'label' => $c['label'], 'count' => count($c['items'])];
                    }, $cats))); ?>">
        <?php echo esc_html(sprintf('Voir les %d photos', $total_all)); ?>
      </button>
    </div>

    <!-- Sources Lightbox (cachées), en ordre ACF strict -->
    <div class="pc-lightbox-src" hidden>
      <?php foreach ($cats as $c): ?>
        <?php foreach ($c['items'] as $img): ?>
          <a href="<?php echo esc_url($img['src']); ?>"
            class="pc-glightbox"
            data-group="<?php echo esc_attr($gallery_id); ?>"
            data-cat="<?php echo esc_attr($c['slug']); ?>"
            data-title="<?php echo esc_attr($img['title']); ?>"></a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>
  </section>
<?php
  return ob_get_clean();
});

// [pc_location_map]
add_shortcode('pc_location_map', function ($atts = []) {
  $a = shortcode_atts([
    'zoom'  => 13,
    'color' => '#e74c3c', // cercle
    'class' => '',
  ], $atts, 'pc_location_map');
  $p = get_post();
  if (!$p) return '';
  if (!function_exists('get_field')) return '';
  $coords = trim((string)get_field('geo_coords', $p->ID));
  if (!$coords || strpos($coords, ',') === false) return ''; // rien si pas de coord.
  [$lat, $lng] = array_map('trim', explode(',', $coords, 2));
  $lat = floatval($lat);
  $lng = floatval($lng);
  if (!$lat || !$lng) return '';
  $radius = (int) get_field('geo_radius_m', $p->ID);
  if ($radius <= 0) $radius = 600;
  $id = 'pcmap-' . wp_rand(1000, 9999);
  wp_enqueue_style('leaflet', 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css', [], null);
  wp_enqueue_script('leaflet', 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js', [], null, true);
  ob_start(); ?>
  <section class="pc-map-wrap <?php echo esc_attr($a['class']); ?>">
    <div id="<?php echo esc_attr($id); ?>" class="pc-map"></div>
  </section>
  <script>
    (function() {
      function init() {
        if (!window.L) return setTimeout(init, 50);
        var map = L.map('<?php echo esc_js($id); ?>', {
          center: [<?php echo $lat; ?>, <?php echo $lng; ?>],
          zoom: <?php echo (int)$a['zoom']; ?>,
          scrollWheelZoom: false
        });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          attribution: '© OpenStreetMap'
        }).addTo(map);
        L.circle([<?php echo $lat; ?>, <?php echo $lng; ?>], {
          radius: <?php echo $radius; ?>,
          color: '<?php echo esc_js($a['color']); ?>',
          fillColor: '<?php echo esc_js($a['color']); ?>',
          fillOpacity: .25,
          weight: 2
        }).addTo(map);
        map.on('focus', function() {
          map.scrollWheelZoom.enable();
        });
        map.on('blur', function() {
          map.scrollWheelZoom.disable();
        });
        map.on('click', function(e) {
          if (e.originalEvent.metaKey || e.originalEvent.ctrlKey) {
            var url = 'https://www.google.com/maps/dir/?api=1&destination=<?php echo $lat; ?>,<?php echo $lng; ?>';
            window.open(url, '_blank');
          }
        });
      }
      init();
    })();
  </script>
<?php
  return ob_get_clean();
});

// [pc_proximites]
add_shortcode('pc_proximites', function ($atts = []) {
  $p = get_post();
  if (!$p) return '';
  if (!function_exists('get_field')) return '';
  $vals = [
    ['slug' => 'airport', 'label' => "Aéroport", 'val' => get_field('prox_airport_km', $p->ID), 'svg' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 13l20 0" stroke="currentColor" stroke-width="2"/><path d="M3 10l6 3-1 6 3-3 5 3 1-4-5-5z" fill="none" stroke="currentColor" stroke-width="2"/></svg>'],
    ['slug' => 'bus',    'label' => "Autobus", 'val' => get_field('prox_bus_km', $p->ID),    'svg' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="12" rx="2" stroke="currentColor" fill="none" stroke-width="2"/><circle cx="7" cy="17" r="1.5" fill="currentColor"/><circle cx="17" cy="17" r="1.5" fill="currentColor"/></svg>'],
    ['slug' => 'port',   'label' => "Port",    'val' => get_field('prox_port_km', $p->ID),   'svg' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h16v3a8 8 0 01-16 0z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 3v9" stroke="currentColor" stroke-width="2"/></svg>'],
    ['slug' => 'beach',  'label' => "Plage",   'val' => get_field('prox_beach_km', $p->ID),  'svg' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17c2 0 2-2 4-2s2 2 4 2 2-2 4-2 2 2 4 2" stroke="currentColor" stroke-width="2" fill="none"/><path d="M8 7c3-3 5-3 8 0" stroke="currentColor" stroke-width="2" fill="none"/></svg>'],
  ];
  $vals = array_values(array_filter($vals, function ($it) {
    return $it['val'] !== '' && $it['val'] !== null;
  }));
  if (!$vals) return '';
  ob_start(); ?>
  <div class="pc-prox-grid">
    <?php foreach ($vals as $it):
      $km = is_numeric($it['val']) ? (float)$it['val'] : '';
      $txt = $km !== '' ? rtrim(rtrim(number_format($km, 1, ',', ' '), '0'), ',') . ' Km' : '';
    ?>
      <div class="pc-prox" data-prox="<?php echo esc_attr($it['slug']); ?>">
        <span class="pc-prox-ico" aria-hidden="true"><?php echo $it['svg']; ?></span>
        <span class="pc-prox-val"><?php echo esc_html($txt); ?></span>
        <span class="pc-prox-lab"><?php echo esc_html($it['label']); ?></span>
      </div>
    <?php endforeach; ?>
  </div>
<?php
  return ob_get_clean();
});

// [pc_ical_calendar]
add_shortcode('pc_ical_calendar', function ($atts = []) {
  $a = shortcode_atts([
    'url'        => '',
    'max_months' => 24,
    'min'        => 'today',
    'class'      => '',
  ], $atts, 'pc_ical_calendar');
  $post = get_post();
  if (!$post) return '';
  if (!$a['url'] && function_exists('get_field')) {
    $a['url'] = (string) get_field('ical_url', $post->ID);
  }
  if (!$a['url']) {
    $message = 'Faites votre demande, nous vous renseignerons sur les disponibilités de ce logement.';
    // On ajoute une classe 'pc-cal-missing' pour le style
    return '<div class="pc-cal-missing">' . esc_html($message) . '</div>';
  }
  $cache_key = 'pc_ics_' . md5($a['url']);
  $ics = get_transient($cache_key);
  if ($ics === false) {
    $res = wp_remote_get($a['url'], ['timeout' => 10]);
    if (is_wp_error($res))  return '';
    $code = wp_remote_retrieve_response_code($res);
    if ($code != 200)       return '';
    $ics = wp_remote_retrieve_body($res);
    set_transient($cache_key, $ics, 2 * HOUR_IN_SECONDS);
  }
  if (!function_exists('pc_parse_ics_ranges')) return '';
  $ranges = pc_parse_ics_ranges($ics);
  $id   = 'pc-cal-input-' . $post->ID;
  $json = wp_json_encode($ranges);
  ob_start(); ?>
  <section class="pc-cal <?php echo esc_attr($a['class']); ?>"
    data-max-months="<?php echo esc_attr((int)$a['max_months']); ?>"
    data-min-date="<?php echo esc_attr($a['min']); ?>">
    <div class="pc-cal-row">
      <input id="<?php echo esc_attr($id); ?>" class="pc-cal-input" type="text" style="display:none;" />
      <script type="application/json" class="pc-ical-disabled-json">
        <?php echo $json; ?>
      </script>
    </div>
  </section>
<?php
  return ob_get_clean();
});

/* ================= Helpers ICS ================= */
function pc_ics_to_date($raw)
{
  $raw = trim($raw);
  if (preg_match('/^\d{8}$/', $raw)) {
    $dt = DateTime::createFromFormat('Ymd', $raw, wp_timezone());
    return $dt ? $dt->format('Y-m-d') : null;
  }
  if (preg_match('/^\d{8}T\d{6}Z$/', $raw)) {
    $dt = DateTime::createFromFormat('Ymd\THis\Z', $raw, new DateTimeZone('UTC'));
    if ($dt) {
      $dt->setTimezone(wp_timezone());
      return $dt->format('Y-m-d');
    }
  }
  if (preg_match('/^\d{8}T\d{6}$/', $raw)) {
    $dt = DateTime::createFromFormat('Ymd\THis', $raw, wp_timezone());
    return $dt ? $dt->format('Y-m-d') : null;
  }
  return null;
}
function pc_parse_ics_ranges($ics)
{
  if (!$ics) return [];
  $ics = preg_replace("/\r\n[ \t]/", "", $ics);
  $lines = preg_split("/\r\n|\n|\r/", $ics);
  $ranges = [];
  $in = false;
  $dtstart = '';
  $dtend = '';
  $status = '';
  foreach ($lines as $line) {
    $line = trim($line);
    if ($line === 'BEGIN:VEVENT') {
      $in = true;
      $dtstart = '';
      $dtend = '';
      $status = '';
      continue;
    }
    if ($line === 'END:VEVENT') {
      if ($in && (!$status || strtoupper($status) !== 'CANCELLED') && $dtstart) {
        $start = pc_ics_to_date($dtstart);
        $end   = $dtend ? pc_ics_to_date($dtend) : $start;
        if ($end) {
          $end = (new DateTime($end))->modify('-1 day')->format('Y-m-d');
        }
        if ($start && $end && $end >= $start) {
          $ranges[] = ['from' => $start, 'to' => $end];
        }
      }
      $in = false;
      continue;
    }
    if (!$in) continue;
    if (stripos($line, 'DTSTART') === 0) {
      $dtstart = substr($line, strpos($line, ':') + 1);
    } elseif (stripos($line, 'DTEND') === 0) {
      $dtend   = substr($line, strpos($line, ':') + 1);
    } elseif (stripos($line, 'STATUS:') === 0) {
      $status  = trim(substr($line, 7));
    }
  }
  return $ranges;
}
add_action('wp_head', function () {
  if (! is_singular(['logement', 'villa', 'appartement'])) return; ?>
  <style>
    html,
    body {
      max-width: 100%;
      overflow-x: hidden;
    }

    img,
    iframe,
    video {
      max-width: 100%;
      height: auto;
    }

    @media (max-width:1024px) {
      body .elementor-section.elementor-section-stretched {
        width: 100% !important;
        left: 0 !important;
        right: 0 !important;
      }

      .elementor-section,
      .elementor-container,
      .elementor-widget,
      .elementor-widget-wrap {
        max-width: 100%;
        overflow-x: clip;
      }

      .pc-hero,
      .pc-tabs-wrap,
      .pc-gallery,
      .pc-proximites,
      .pc-location-map,
      .pc-ical,
      .pc-reviews {
        overflow-x: clip;
      }
    }

    :root {
      --pc-primary: #0e2b5c;
      --pc-accent: #005F73;
      --pc-sticky-top: 68px;
    }

    section[id] {
      scroll-margin-top: calc(var(--pc-sticky-top) + 12px);
    }

    .pc-hero h1 {
      font-size: clamp(1.75rem, 3.5vw + 1rem, 3rem);
      line-height: 1.2;
    }

    .pc-tabs-wrap {
      position: sticky;
      top: var(--pc-sticky-top, 68px);
      z-index: 30;
      background: #f9f9f9;
      border-bottom: 1px solid #eaeaea;
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 4px 8px;
    }

    .pc-tabs-arrow {
      background: transparent;
      border: 0;
      color: #0e2b5c;
      font-size: 22px;
      line-height: 1;
      padding: 6px 8px;
      cursor: pointer;
      user-select: none;
      flex: 0 0 auto;
    }

    .pc-tabs-arrow[disabled] {
      opacity: .35;
      cursor: default;
    }

    .pc-tabs-scroller {
      flex: 1 1 auto;
      min-width: 0;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
      scrollbar-width: none;
      scroll-snap-type: x proximity;
    }

    .pc-tabs-scroller::-webkit-scrollbar {
      display: none;
    }

    .pc-tabs-nav {
      display: inline-flex;
      gap: .25rem;
      padding: .5rem .25rem;
      white-space: nowrap;
    }

    .pc-tabs-nav a {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      padding: 10px 14px;
      border-radius: 12px;
      text-decoration: none;
      color: #0e2b5c;
      font-size: 1rem;
      font-weight: 500;
      scroll-snap-align: start;
    }

    .pc-tabs-nav a:hover {
      text-decoration: underline;
      text-underline-offset: 3px;
    }

    .pc-tabs-nav a.is-active {
      background: #0e2b5c;
      color: #fff;
    }

    @media (min-width:1024px) {
      .pc-tabs-arrow {
        display: none;
      }
    }

    @media (max-width:480px) {
      .pc-tabs-nav a {
        padding: 9px 12px;
        font-size: .95rem;
      }
    }

    .pc-gallery,
    .pc-gallery-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 8px;
    }

    @media (min-width:768px) {

      .pc-gallery,
      .pc-gallery-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
      }
    }

    .pc-proximites {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 10px;
    }

    @media (max-width:480px) {
      .pc-proximites {
        grid-template-columns: 1fr;
      }
    }

    #pc-location-map,
    .pc-location-map {
      height: clamp(260px, 38vw, 420px);
      border-radius: 16px;
      overflow: hidden;
    }

    .pc-ical {
      max-width: 360px;
      margin: 0 auto;
    }

    .flatpickr-calendar {
      font-size: 14px;
    }

    .pc-reviews .pc-reviews-head h3 {
      margin: 0;
      font-size: 1.5rem;
      font-weight: 500;
      font-family: Poppins, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    }

    .pc-reviews .pc-review-card {
      padding: 16px;
    }

    .pc-reviews .pc-stars svg {
      width: 18px;
      height: 18px;
    }

    .pc-reviews .pc-rev-toggle-form {
      margin-left: auto;
    }

    @media (max-width:640px) {
      .pc-reviews .pc-reviews-head {
        align-items: flex-start;
        gap: .5rem;
      }

      .pc-reviews .pc-rev-toggle-form {
        width: 100%;
        text-align: center;
        margin-left: 0;
      }

      .pc-reviews .pc-review-card {
        padding: 14px;
      }

      .pc-reviews .pc-stars svg {
        width: 16px;
        height: 16px;
      }

      .pc-reviews .pc-rev-more {
        width: 100%;
      }
    }
  </style>
  <?php });

/* ============================================================
 * PC — Réservation : Barre sticky (redir), Router, Formulaire
 * ============================================================ */

if (!function_exists('pc_sc_booking_bar')) {
  add_shortcode('pc_booking_bar', 'pc_sc_booking_bar');
  /**
   * Barre Sticky de Réservation [pc_booking_bar] (Version 3 - Refonte Bottom-Sheet)
   * - Génère le FAB (bouton pilule), la structure de la Bottom-Sheet (vide),
   * et la modale de contact (si pas Lodgify).
   * - Le shortcode [pc_devis] sera DÉPLACÉ par JS dans la bottom-sheet au chargement.
   */
  function pc_sc_booking_bar($atts = [])
  {
    if (!is_singular(['villa', 'appartement', 'logement']) || is_page(['reserver', 'demande-sejour'])) return '';
    $post_id = get_the_ID();

    $lodgify_embed = get_field('lodgify_widget_embed', $post_id);
    $has_lodgify = !empty(trim($lodgify_embed));

    // Données pour l'affichage initial du bouton "pilule"
    $price = get_field('base_price_from', $post_id);
    $unite = get_field('unite_de_prix', $post_id) ?: 'nuit';

    ob_start();
  ?>

    <button type="button" class="exp-booking-fab" id="logement-open-devis-sheet-btn">
      <span id="fab-logement-price-display">
        <?php if ($price): ?>
          À partir de <?php echo esc_html(number_format_i18n($price, 0)) . '€'; ?>
        <?php else: ?>
          Estimer le séjour
        <?php endif; ?>
      </span>
    </button>

    <div class="exp-devis-sheet" id="logement-devis-sheet" role="dialog" aria-modal="true" aria-labelledby="logement-devis-sheet-title" aria-hidden="true">
      <div class="exp-devis-sheet__overlay" data-close-devis-sheet></div>

      <div class="exp-devis-sheet__content" role="document">
        <div class="exp-devis-sheet__header">
          <h3 class="exp-devis-sheet__title" id="logement-devis-sheet-title">Estimez votre séjour</h3>
          <button class="exp-devis-sheet__close" aria-label="Fermer" data-close-devis-sheet>×</button>
        </div>

        <div class="exp-devis-sheet__body" id="logement-devis-sheet-body">
        </div>
      </div>
    </div>

    <?php
    // =============================================
    // INCHANGÉ : 3. La Modale de Contact (si besoin)
    // =============================================
    // La modale n'est affichée que si Lodgify est absent
    if (!$has_lodgify) {
      // Le code de la modale reste le même qu'avant, on l'insère ici
    ?>
      <div class="exp-booking-modal is-hidden" id="logement-booking-modal" role="dialog" aria-modal="true">
        <div class="exp-booking-modal__overlay" data-close-modal></div>
        <div class="exp-booking-modal__content">
          <button class="exp-booking-modal__close" aria-label="Fermer" data-close-modal>×</button>
          <h3 class="exp-booking-modal__title">Réserver maintenant</h3>

          <form id="logement-booking-form" method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="logement_booking_request">
            <input type="hidden" name="logement_id" value="<?php echo esc_attr($post_id); ?>">
            <?php wp_nonce_field('logement_booking_request_nonce', 'nonce'); ?>

            <p class="honeypot-field" style="display:none !important; visibility:hidden !important; opacity:0 !important; height:0 !important; width:0 !important; position:absolute !important; left:-9999px !important;" aria-hidden="true">
              <label for="booking-reason-logement">Motif</label>
              <input type="text" id="booking-reason-logement" name="booking_reason_logement" tabindex="-1" autocomplete="off">
            </p>
            <fieldset class="exp-booking-fieldset">

              <fieldset class="exp-booking-fieldset">
                <legend class="visually-hidden">Votre simulation</legend>
                <h3 class="exp-booking-fieldset-title">Votre simulation</h3>

                <div id="modal-quote-summary-logement"></div>
                <textarea name="quote_details" id="modal-quote-details-hidden-logement" style="display:none;"></textarea>
              </fieldset>

              <fieldset class="exp-booking-fieldset">
                <legend class="visually-hidden">Vos coordonnées</legend>
                <h3 class="exp-booking-fieldset-title">Vos coordonnées</h3>

                <div class="exp-booking-form-grid">
                  <div class="exp-booking-field"><label for="booking-prenom-logement">Prénom*</label><input type="text" id="booking-prenom-logement" name="prenom" required></div>
                  <div class="exp-booking-field"><label for="booking-nom-logement">Nom*</label><input type="text" id="booking-nom-logement" name="nom" required></div>
                  <div class="exp-booking-field"><label for="booking-email-logement">Email*</label><input type="email" id="booking-email-logement" name="email" required></div>
                  <div class="exp-booking-field"><label for="booking-tel-logement">Téléphone*</label><input type="text" id="booking-tel-logement" name="tel" required></div>
                </div>
              </fieldset>

              <fieldset class="exp-booking-fieldset">
                <legend class="visually-hidden">Informations supplémentaires</legend>
                <h3 class="exp-booking-fieldset-title">Informations supplémentaires</h3>

                <div class="exp-booking-field">
                  <label for="booking-message-logement" class="visually-hidden">Votre message</label>
                  <textarea id="booking-message-logement" name="message" rows="3" placeholder="Avez-vous des questions ou des demandes particulières ?"></textarea>
                </div>
              </fieldset>

              <div class="exp-booking-modal__actions">
                <p class="exp-booking-disclaimer">Cette demande est sans engagement.</p>
                <button type="submit" class="pc-btn pc-btn--primary">Envoyer la demande</button>
              </div>
          </form>
        </div>
      </div>
    <?php
    }
    return ob_get_clean();
  }
}
/* ---------- Router /reserver : affiche Lodgify si présent sinon redirige vers /demande-sejour ---------- */
if (!function_exists('pc_sc_booking_router')) {
  add_shortcode('pc_booking_router', 'pc_sc_booking_router');
  function pc_sc_booking_router($atts = [])
  {
    $a = shortcode_atts([
      'header' => '0',
    ], $atts, 'pc_booking_router');
    $id = isset($_GET['l']) ? absint($_GET['l']) : 0;
    if (!$id || !get_post($id)) return '<p>Référence logement manquante.</p>';
    $GLOBALS['pc_current_logement_id'] = $id;
    $force_form = (isset($_GET['mode']) && $_GET['mode'] === 'form');
    $embed_raw = get_field('lodgify_widget_embed', $id);
    $embed = is_string($embed_raw) ? trim($embed_raw) : '';
    $has_div     = (stripos($embed, '<div') !== false);
    $has_marker  = (stripos($embed, 'lodgify-book-now-box') !== false) || (stripos($embed, 'data-rental-id') !== false);
    $has_embed   = (!$force_form) && ($embed !== '') && $has_div && $has_marker;
    if (!$has_embed) {
      $url = home_url('/demande-sejour/?l=' . $id);
      if (!headers_sent()) {
        wp_safe_redirect($url);
        exit;
      }
      echo '<script>location.replace(' . json_encode($url) . ');</script><noscript><meta http-equiv="refresh" content="0;url=' . esc_attr($url) . '"></noscript>';
      return '';
    }
    $title = get_the_title($id);
    $price = get_field('base_price_from', $id);
    $unite = get_field('unite_de_prix', $id) ?: 'par nuit';
    $show_header = ($a['header'] === '1');
    ob_start();
    if ($show_header) {
      echo '<h1 class="pcbk-h1">Réserver — ' . esc_html($title) . '</h1>';
      if ($price) echo '<p class="pcbk-meta">À partir de <strong>' . esc_html(number_format_i18n($price, 0)) . '€</strong> ' . esc_html($unite) . '</p>';
      echo '<style>.pcbk-h1{font:500 1.5rem/1.3 "Poppins",system-ui}.pcbk-meta{opacity:.85;margin:.25rem 0 1rem}</style>';
    }
    echo '<div id="pcbk-lodgify">' . $embed . '</div>';
    ?>
    <script src="https://app.lodgify.com/book-now-box/stable/renderBookNowBox.js" defer></script>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        function tryInit() {
          var el = document.querySelector('#pcbk-lodgify #lodgify-book-now-box,[data-rental-id]');
          if (!el) return;
          if (!el.id) el.id = 'lodgify-book-now-box';
          if (window.renderBookNowBox) {
            try {
              window.renderBookNowBox(el.id);
            } catch (e) {
              try {
                window.renderBookNowBox(el.id, {});
              } catch (e2) {}
            }
          } else {
            setTimeout(tryInit, 60);
          }
        }
        tryInit();
      });
    </script>
  <?php
    return ob_get_clean();
  }
}

/* ===== ICS helper (collez ICI) ===== */
if (!function_exists('pc_ics_disabled_ranges')) {
  function pc_ics_disabled_ranges($url, $max_months = 24)
  {
    if (!$url) return '[]';
    $resp = wp_remote_get($url, ['timeout' => 10]);
    if (is_wp_error($resp)) return '[]';
    $ics = wp_remote_retrieve_body($resp);
    if (!$ics) return '[]';
    $ics = str_replace("\r\n", "\n", $ics);
    $lines = preg_split('/\n/', $ics);
    $unfolded = [];
    foreach ($lines as $line) {
      if (isset($unfolded[count($unfolded) - 1]) && (isset($line[0]) && ($line[0] === ' ' || $line[0] === "\t"))) {
        $unfolded[count($unfolded) - 1] .= substr($line, 1);
      } else {
        $unfolded[] = $line;
      }
    }
    $ranges = [];
    $inEvent = false;
    $dtStart = null;
    $dtEnd = null;
    foreach ($unfolded as $l) {
      if (stripos($l, 'BEGIN:VEVENT') === 0) {
        $inEvent = true;
        $dtStart = $dtEnd = null;
        continue;
      }
      if (stripos($l, 'END:VEVENT') === 0) {
        if ($inEvent && $dtStart && $dtEnd) {
          try {
            $start = new DateTimeImmutable($dtStart);
            $end   = (new DateTimeImmutable($dtEnd))->modify('-1 day'); // DTEND exclusif
            if ($end >= $start) {
              $ranges[] = [$start->format('Y-m-d'), $end->format('Y-m-d')];
            }
          } catch (Exception $e) {
          }
        }
        $inEvent = false;
        $dtStart = $dtEnd = null;
        continue;
      }
      if (!$inEvent) continue;
      if (stripos($l, 'DTSTART') === 0) {
        $v = substr($l, (strpos($l, ':') + 1));
        $dtStart = pc_ics_normalize_dt($v);
      }
      if (stripos($l, 'DTEND') === 0) {
        $v = substr($l, (strpos($l, ':') + 1));
        $dtEnd = pc_ics_normalize_dt($v);
      }
    }
    usort($ranges, function ($a, $b) {
      return strcmp($a[0], $b[0]);
    });
    if ($max_months > 0) {
      $limit = (new DateTimeImmutable('first day of this month'))->modify("+{$max_months} months")->format('Y-m-d');
      $out = [];
      foreach ($ranges as $r) {
        if ($r[0] <= $limit) $out[] = $r;
      }
      $ranges = $out;
    }
    return wp_json_encode($ranges);
  }
  function pc_ics_normalize_dt($v)
  {
    $v = trim($v);
    if (preg_match('/^\d{8}$/', $v)) {
      $dt = DateTimeImmutable::createFromFormat('Ymd', $v, new DateTimeZone('UTC'));
      return $dt ? $dt->format('Y-m-d') : null;
    }
    if (preg_match('/^\d{8}T\d{6}Z$/', $v)) {
      try {
        $dt = new DateTimeImmutable($v);
        return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
      } catch (Exception $e) {
        return null;
      }
    }
    if (preg_match('/^\d{8}T\d{6}$/', $v)) {
      $dt = DateTimeImmutable::createFromFormat('Ymd\THis', $v, new DateTimeZone('UTC'));
      return $dt ? $dt->format('Y-m-d') : null;
    }
    try {
      $dt = new DateTimeImmutable($v);
      return $dt->format('Y-m-d');
    } catch (Exception $e) {
      return null;
    }
  }
}
/* ===== fin helper ICS ===== */

// URL retour vers la fiche logement
add_shortcode('pc_return_url', function () {
  $id = isset($_GET['l']) ? absint($_GET['l']) : 0;
  return $id ? esc_url(get_permalink($id)) : esc_url(home_url('/'));
});

// Titre du logement
add_shortcode('pc_title', function () {
  $id = isset($_GET['l']) ? absint($_GET['l']) : 0;
  return $id ? esc_html(get_the_title($id)) : '';
});

// Champ ACF du logement (texte)
add_shortcode('pc_acf', function ($atts) {
  $a = shortcode_atts(['field' => '', 'default' => ''], $atts);
  $id = isset($_GET['l']) ? absint($_GET['l']) : 0;
  if (!$id || !$a['field']) return esc_html($a['default']);
  $val = get_field($a['field'], $id);
  if (is_array($val)) $val = implode(', ', array_map('trim', $val));
  return $val !== '' && $val !== null ? esc_html($val) : esc_html($a['default']);
});
// ---------- Page /demande-sejour : formulaire léger (FR + ICS disable + bouton aux couleurs + CAPACITÉ MAX) ----------
if (!function_exists('pc_sc_booking_request_form')) {
  add_shortcode('pc_booking_request_form', 'pc_sc_booking_request_form');
  function pc_sc_booking_request_form()
  {
    $id = isset($_GET['l']) ? absint($_GET['l']) : 0;
    if (!$id || !get_post($id)) {
      return '<p>Référence logement manquante.</p>';
    }
    $title    = get_the_title($id);
    $price    = get_field('base_price_from', $id);
    $unite    = get_field('unite_de_prix', $id) ?: 'par nuit';
    $ical     = get_field('ical_url', $id);
    $capacity = intval(get_field('capacite', $id)); // ← capacité ACF
    $disabled_json = $ical ? pc_ics_disabled_ranges($ical, 24) : '[]';
    $err_html = '';
    if (isset($_GET['err']) && $_GET['err'] === 'cap') {
      $cap = isset($_GET['cap']) ? intval($_GET['cap']) : $capacity;
      $err_html = '<div class="pcbk-error">Le nombre de personnes dépasse la capacité du logement (max. ' . esc_html($cap) . ').</div>';
    }
    ob_start(); ?>
    <div class="pcbk-request">
      <h1 class="pcbk-h1">Demande de réservation — <?php echo esc_html($title); ?></h1>
      <?php if ($price): ?>
        <p class="pcbk-meta">À partir de <strong><?php echo esc_html(number_format_i18n($price, 0)); ?>€</strong> <?php echo esc_html($unite); ?></p>
      <?php endif; ?>
      <?php echo $err_html; ?>
      <form class="pcbk-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="pc_booking_request">
        <?php wp_nonce_field('pc_booking_request', 'pcbk_nonce'); ?>
        <input type="hidden" name="logement_id" value="<?php echo esc_attr($id); ?>">
        <input type="hidden" class="pcbk-disabled-json" value='<?php echo esc_attr($disabled_json); ?>'>
        <label>Vos dates</label>
        <input class="pcbk-date" type="text" name="dates" placeholder="Arrivée – Départ" readonly>
        <label>Nombre de personnes (enfants inclus)</label>
        <input
          type="number" name="guests" min="1" step="1" required
          <?php if ($capacity > 0) echo ' max="' . esc_attr($capacity) . '"'; ?>
          <?php if ($capacity > 0) echo ' placeholder="Max ' . $capacity . '"'; ?>>
        <?php if ($capacity > 0): ?>
          <small class="pcbk-hint">Capacité maximale : <?php echo esc_html($capacity); ?> personnes.</small>
        <?php endif; ?>
        <label>Nom</label>
        <input type="text" name="nom" required>
        <label>Prénom</label>
        <input type="text" name="prenom" required>
        <label>E-mail</label>
        <input type="email" name="email" required>
        <label>Téléphone</label>
        <input type="tel" name="tel" required>
        <p class="pcbk-disclaimer">Rassurez-vous, cette demande est sans engagement.</p>
        <button type="submit" class="pcbk-submit">Envoyer la demande</button>
      </form>
    </div>
    <style>
      .pcbk-h1 {
        font: 500 1.5rem/1.3 "Poppins", system-ui
      }

      .pcbk-meta {
        opacity: .85;
        margin: .25rem 0 1rem
      }

      .pcbk-form {
        display: grid;
        gap: .6rem;
        max-width: 640px
      }

      .pcbk-form input,
      .pcbk-form button {
        padding: .65rem .75rem;
        font-size: 1rem
      }

      .pcbk-disclaimer {
        font-size: .9rem;
        opacity: .8;
        margin: .25rem 0 .5rem
      }

      .pcbk-hint {
        display: block;
        opacity: .8;
        margin-top: .2rem
      }

      .pcbk-error {
        background: #fdecea;
        color: #b00020;
        border: 1px solid #f5c2c0;
        padding: .75rem 1rem;
        border-radius: 10px;
        margin: .5rem 0 1rem;
      }

      .pcbk-submit {
        background: #005F73;
        color: #fff;
        border: 0;
        border-radius: 14px;
        cursor: pointer;
        font-weight: 600;
        padding: .75rem 1rem;
      }

      .pcbk-submit:hover {
        background: #007A92;
        color: #fff;
      }

      @media (max-width: 767px) {

        html,
        body {
          width: 100%;
          overflow-x: hidden;
        }

        .pcbk-request {
          overflow-x: hidden;
          padding-left: 12px;
          padding-right: 12px;
        }

        .pcbk-request,
        .pcbk-request * {
          box-sizing: border-box;
          max-width: 100%;
        }

        .pcbk-form {
          width: 100%;
        }

        .flatpickr-calendar,
        .flatpickr-calendar.open {
          max-width: calc(100vw - 24px);
          left: 12px !important;
          right: 12px !important;
        }

        .flatpickr-calendar .flatpickr-days {
          width: auto;
        }

        .flatpickr-calendar .dayContainer {
          min-width: auto;
        }
      }
    </style>
  <?php
    return ob_get_clean();
  }
}
/* ---------- Handler e-mail (soumission du formulaire /demande-sejour) ---------- */
add_action('admin_post_nopriv_pc_booking_request', 'pc_handle_booking_request');
add_action('admin_post_pc_booking_request',        'pc_handle_booking_request');
if (!function_exists('pc_handle_booking_request')) {
  function pc_handle_booking_request()
  {
    if (!isset($_POST['pcbk_nonce']) || !wp_verify_nonce($_POST['pcbk_nonce'], 'pc_booking_request')) wp_die('Nonce invalide.');
    $id     = isset($_POST['logement_id']) ? absint($_POST['logement_id']) : 0;
    $name   = sanitize_text_field(($_POST['prenom'] ?? '') . ' ' . ($_POST['nom'] ?? ''));
    $email  = sanitize_email($_POST['email'] ?? '');
    $tel    = sanitize_text_field($_POST['tel'] ?? '');
    $dates  = sanitize_text_field($_POST['dates'] ?? '');
    $guests = intval($_POST['guests'] ?? 0);
    if (!$id || !get_post($id)) wp_die('Logement invalide.');
    $capacity = intval(get_field('capacite', $id));
    if ($capacity > 0 && $guests > $capacity) {
      $url = add_query_arg([
        'l'   => $id,
        'err' => 'cap',
        'cap' => $capacity
      ], home_url('/demande-sejour/'));
      wp_safe_redirect($url);
      exit;
    }
    $title = get_the_title($id);
    $to = 'guadeloupe@prestigecaraibes.com';
    $subject = 'Demande de réservation — ' . $title;
    $body = "Logement: {$title} (ID {$id})\nDates: {$dates}\nPersonnes: {$guests}\nNom: {$name}\nEmail: {$email}\nTéléphone: {$tel}\n";
    $headers = ['Content-Type: text/plain; charset=UTF-8'];
    if ($email) {
      wp_mail($to, $subject, $body, $headers);
      wp_mail($email, 'Votre demande — Prestige Caraïbes', "Bonjour {$name},\n\nMerci pour votre demande concernant {$title}.\nNous revenons vers vous très vite.\n\n— Prestige Caraïbes", $headers);
    }
    wp_safe_redirect(add_query_arg('sent', '1', wp_get_referer() ?: home_url()));
    exit;
  }
}
// [pc_return_x fixed="1"]
add_shortcode('pc_return_x', function ($atts) {
  $a = shortcode_atts(['fixed' => '1', 'title' => 'Fermer et revenir à la fiche'], $atts, 'pc_return_x');
  $id  = isset($_GET['l']) ? absint($_GET['l']) : 0;
  $url = $id ? get_permalink($id) : home_url('/');
  ob_start(); ?>
  <a href="<?php echo esc_url($url); ?>" class="pcbk-close-x" aria-label="<?php echo esc_attr($a['title']); ?>" title="<?php echo esc_attr($a['title']); ?>">×</a>
  <?php if ($a['fixed'] === '1'): ?>
    <style>
      .pcbk-close-x {
        position: fixed;
        top: 12px;
        right: 12px;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f9f9f9;
        color: #0e2b5c;
        text-decoration: none;
        border-radius: 50%;
        box-shadow: 0 6px 18px rgba(0, 0, 0, .12);
        z-index: 10000;
        font-size: 24px;
        line-height: 1;
      }

      .pcbk-close-x:hover {
        background: #005F73;
        color: #fff;
      }

      @media (max-width:480px) {
        .pcbk-close-x {
          top: 8px;
          right: 8px;
          width: 36px;
          height: 36px;
          font-size: 22px;
        }
      }
    </style>
  <?php endif;
  return ob_get_clean();
});
// [pc_return_button label="← Retour à la fiche"]
add_shortcode('pc_return_button', function ($atts) {
  $a = shortcode_atts(['label' => '← Retour à la fiche'], $atts, 'pc_return_button');
  $id  = isset($_GET['l']) ? absint($_GET['l']) : 0;
  $url = $id ? get_permalink($id) : home_url('/');
  ob_start(); ?>
  <a href="<?php echo esc_url($url); ?>" class="pcbk-backbtn"><?php echo esc_html($a['label']); ?></a>
  <style>
    .pcbk-backbtn {
      display: inline-block;
      padding: 12px 18px;
      background: #005F73;
      color: #fff !important;
      border-radius: 14px;
      text-decoration: none;
      font-weight: 600;
    }

    .pcbk-backbtn:hover {
      background: #007A92;
      color: #fff !important;
    }
  </style>
<?php
  return ob_get_clean();
});
// [pc_tarifs_table]
add_shortcode('pc_tarifs_table', function ($atts = []) {
  if (!function_exists('get_field')) return '';
  $post_id = get_the_ID();
  if (!$post_id) return '';
  $fmt_eur = function ($n) {
    if ($n === '' || $n === null) return '';
    return '€' . number_format((float)$n, 0, ',', ' ');
  };
  $fmt_surcharge = function ($fee, $from) {
    $fee  = (float) $fee;
    $from = (int) $from;
    if ($fee > 0 && $from > 0) {
      return '+ ' . $fmt = '€' . number_format($fee, 0, ',', ' ') . ' par invité/nuit après ' . $from . ' invités';
    }
    return '';
  };
  $mois = ['01' => 'janv.', '02' => 'févr.', '03' => 'mars', '04' => 'avr.', '05' => 'mai', '06' => 'juin', '07' => 'juil.', '08' => 'août', '09' => 'sept.', '10' => 'oct.', '11' => 'nov.', '12' => 'déc.'];
  $fmt_date = function ($ymd) use ($mois) {
    if (!$ymd) return '';
    $y = substr($ymd, 0, 4);
    $m = substr($ymd, 5, 2);
    $d = ltrim(substr($ymd, 8, 2), '0');
    $labelM = isset($mois[$m]) ? $mois[$m] : $m;
    return $d . ' ' . $labelM . ' ' . $y;
  };
  $fmt_range = function ($from, $to) use ($fmt_date) {
    if (!$from && !$to) return '';
    return trim($fmt_date($from) . ' - ' . $fmt_date($to));
  };
  $base_price   = get_field('base_price_from', $post_id);
  $unit         = get_field('unite_de_prix',   $post_id);
  $extra_fee    = get_field('extra_guest_fee', $post_id);
  $extra_from   = get_field('extra_guest_from', $post_id);
  $seasons = (array) get_field('pc_season_blocks', $post_id);
  $prepared = [];
  foreach ($seasons as $row) {
    $name  = isset($row['season_name']) ? trim($row['season_name']) : '';
    $note  = isset($row['season_note']) ? trim($row['season_note']) : '';
    $price = $row['season_price'] ?? '';
    $min   = $row['season_min_nights'] ?? '';
    $efee  = $row['season_extra_guest_fee'] ?? '';
    $efrom = $row['season_extra_guest_from'] ?? '';
    $periods = isset($row['season_periods']) && is_array($row['season_periods']) ? $row['season_periods'] : [];
    if (!empty($periods)) {
      $prepared[] = [
        'name' => $name ?: 'Saison',
        'note' => $note,
        'price' => $price,
        'min'  => $min,
        'efee' => ($efee !== '' ? $efee : $extra_fee),
        'efrom' => ($efrom !== '' ? (int)$efrom : (int)$extra_from),
        'periods' => array_map(function ($p) use ($fmt_range) {
          $f = $p['date_from'] ?? '';
          $t = $p['date_to']   ?? '';
          return [
            'from' => $f,
            'to'   => $t,
            'label' => $fmt_range($f, $t)
          ];
        }, $periods)
      ];
    }
  }
  $unit_label = 'Par Jour';
  if ($unit) {
    $u = trim(mb_strtolower($unit, 'UTF-8'));
    $u = preg_replace('/^\s*par\s+/u', '', $u);
    $unit_label = 'Par ' . mb_convert_case($u, MB_CASE_TITLE, 'UTF-8');
  }
  ob_start(); ?>
  <section class="pc-prices" aria-label="Tarifs">
    <div class="pc-price__head">
      <div class="pc-price__head-left"></div>
      <div class="pc-price__unit"><?php echo esc_html($unit_label); ?></div>
    </div>
    <div class="pc-price__row">
      <div class="pc-price__title">Tarif Par Défaut</div>
      <div class="pc-price__amount"><?php echo esc_html($fmt_eur($base_price)); ?></div>
      <?php if ($txt = $fmt_surcharge($extra_fee, $extra_from)) : ?>
        <div class="pc-price__note"><?php echo esc_html($txt); ?></div>
      <?php endif; ?>
    </div>
    <?php foreach ($prepared as $s): ?>
      <div class="pc-price__card">
        <div class="pc-price__row">
          <div class="pc-price__title"><?php echo esc_html($s['name']); ?></div>
          <div class="pc-price__amount">
            <?php echo esc_html($fmt_eur($s['price'] !== '' ? $s['price'] : $base_price)); ?>
          </div>
          <?php if ($txt = $fmt_surcharge($s['efee'], $s['efrom'])) : ?>
            <div class="pc-price__note"><?php echo esc_html($txt); ?></div>
          <?php endif; ?>
        </div>
        <?php if (!empty($s['periods'])): ?>
          <ul class="pc-price__periods">
            <?php foreach ($s['periods'] as $p): if (!$p['label']) continue; ?>
              <li class="pc-price__period"><?php echo esc_html($p['label']); ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </section>
<?php
  return ob_get_clean();
});

// [pc_devis]
add_shortcode('pc_devis', function ($atts = []) {
  if (!function_exists('get_field')) return '';
  $post_id = get_the_ID();
  if (!$post_id) return '';
  $lodgify_embed = get_field('lodgify_widget_embed', $post_id);
  $has_lodgify = !empty(trim($lodgify_embed));
  $company_info = [
    'name'    => get_field('pc_org_name', 'option') ?: get_bloginfo('name'),
    'legal'   => get_field('pc_org_legal_name', 'option'),
    'address' => get_field('pc_org_address_street', 'option'),
    'city'    => get_field('pc_org_address_postal', 'option') . ' ' . get_field('pc_org_address_locality', 'option'),
    'phone'   => get_field('pc_org_phone', 'option'),
    'email'   => get_field('pc_org_email', 'option'),
    'vat'     => get_field('pc_org_vat_id', 'option'),
    'logo'    => get_field('pc_org_logo', 'option'),
  ];

  // --- CGV Location (ACF option) -> texte nettoyé pour le PDF
  $terms_raw  = get_field('cgv_location', 'option');
  $terms_text = $terms_raw ? trim(wp_strip_all_tags(wp_kses_post($terms_raw))) : '';
  $company_info['cgv_location'] = $terms_text;

  // --- Logo bleu aplati (PNG rendu opaque sur fond blanc -> base64)
  //     Remplace le transparent afin d'avoir un rendu net dans jsPDF
  $uploads  = wp_get_upload_dir();
  // ⚠️ adapte le chemin si ton logo est ailleurs dans ta médiathèque
  $rel_path = '2025/06/Logo-Prestige-Caraibes-bleu.png';
  $abs_path = trailingslashit($uploads['basedir']) . $rel_path;

  if (is_readable($abs_path)) {
    $src = imagecreatefrompng($abs_path);
    if ($src) {
      $w = imagesx($src);
      $h = imagesy($src);
      $dst = imagecreatetruecolor($w, $h);

      // On dessine un fond blanc puis on colle le PNG par dessus
      imagealphablending($dst, true);
      imagesavealpha($dst, false);
      $white = imagecolorallocate($dst, 255, 255, 255);
      imagefilledrectangle($dst, 0, 0, $w, $h, $white);
      imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);

      ob_start();
      imagepng($dst, null, 9);
      $png = ob_get_clean();

      $company_info['logo_data'] = 'data:image/png;base64,' . base64_encode($png);

      imagedestroy($dst);
      imagedestroy($src);
    }
  }

  $base_price   = (float) get_field('base_price_from', $post_id);
  $unit         = (string) get_field('unite_de_prix',   $post_id);
  $min_nights   = (int)    get_field('min_nights',      $post_id);
  $max_nights   = (int)    get_field('max_nights',      $post_id);
  $cap          = (int)    get_field('capacite',        $post_id);
  if ($cap <= 0) $cap = 1;
  $extra_fee    = (float)  get_field('extra_guest_fee',  $post_id);
  $extra_from   = (int)    get_field('extra_guest_from', $post_id);
  $cleaning     = (float)  get_field('frais_menage',      $post_id);
  $other_fee    = (float)  get_field('autres_frais',      $post_id);
  $other_label  = (string) get_field('autres_frais_type', $post_id);
  $taxe_choices = (array)  get_field('taxe_sejour',       $post_id);
  $unit_is_week = (stripos($unit, 'semaine') !== false);
  $seasons_raw = (array) get_field('pc_season_blocks', $post_id);
  $seasons = [];
  $manual = (bool) get_field('pc_manual_quote', $post->ID);
  foreach ($seasons_raw as $s) {
    $price = isset($s['season_price']) ? (float)$s['season_price'] : 0.0;
    if ($unit_is_week && $price > 0) $price = $price / 7.0;
    if (! is_array($s)) {
      $s = [];
    }
    $seasons[] = [
      'name'        => trim((string)($s['season_name'] ?? 'Saison')),
      'min_nights'  => (int)($s['season_min_nights'] ?? 0),

      // 2. MODIFICATION (Ligne 1046 corrigée avec '??')
      'extra_fee'   => ($s['season_extra_guest_fee'] ?? '') !== '' ? (float)$s['season_extra_guest_fee'] : $extra_fee,

      // 3. MODIFICATION (Ligne 1047 corrigée avec '??')
      'extra_from'  => ($s['season_extra_guest_from'] ?? '') !== '' ? (int)$s['season_extra_guest_from'] : $extra_from,

      // Le reste est inchangé
      'price'       => ($price > 0 ? $price : ($unit_is_week ? ($base_price / 7.0) : $base_price)),
      'periods'     => array_values(array_map(function ($p) {
        return ['from' => (string)($p['date_from'] ?? ''), 'to' => (string)($p['date_to'] ?? '')];
      }, (array)($s['season_periods'] ?? [])))
    ];
  }
  $ical_url    = (string) get_field('ical_url', $post_id);
  $ics_disable = [];
  if ($ical_url && function_exists('pc_parse_ics_ranges')) {
    $cache_key = 'pc_ics_body_' . md5($ical_url);
    $ics_body  = get_transient($cache_key);
    if ($ics_body === false) {
      $resp = wp_remote_get($ical_url, ['timeout' => 10]);
      $ics_body = (!is_wp_error($resp) && 200 === wp_remote_retrieve_response_code($resp))
        ? (string) wp_remote_retrieve_body($resp)
        : '';
      if ($ics_body !== '') {
        set_transient($cache_key, $ics_body, 2 * HOUR_IN_SECONDS);
      }
    }
    if ($ics_body !== '') {
      $ics_disable = pc_parse_ics_ranges($ics_body);
    }
  }
  $cfg = [
    'basePrice'   => $unit_is_week ? ($base_price / 7.0) : $base_price,
    'cap'         => $cap,
    'minNights'   => max(1, $min_nights ?: 1),
    'maxNights'   => max(1, $max_nights ?: 365),
    'extraFee'    => $extra_fee,
    'extraFrom'   => max(0, $extra_from),
    'cleaning'    => $cleaning,
    'otherFee'    => $other_fee,
    'otherLabel'  => $other_label ?: 'Autres frais',
    'taxe_sejour' => $taxe_choices,
    'seasons'     => $seasons,
    'icsDisable'  => $ics_disable,
    'lodgifyId'      => get_field('identifiant_lodgify', $post_id) ?: '',
    'lodgifyAccount' => 'marine-schutz-431222',
    'manualQuote' => $manual,
  ];
  $id = 'pc-devis-' . $post_id;
  $data = esc_attr(wp_json_encode($cfg));
  ob_start(); ?>
  <section id="<?php echo esc_attr($id); ?>" class="pc-devis-section" data-pc-devis="<?php echo $data; ?>" data-manual-quote="<?php echo $manual ? '1' : '0'; ?>">
    <div class="exp-devis-wrap">
      <h3 class="exp-devis-title">Estimez le coût de votre séjour</h3>
      <div class="exp-devis-form">
        <div class="exp-devis-field" style="grid-column: 1 / -1;">
          <label for="<?php echo esc_attr($id); ?>-dates">Vos dates</label>
          <input type="text" id="<?php echo esc_attr($id); ?>-dates" name="dates" class="pcq-input" placeholder="Arrivée – Départ" readonly>
        </div>
        <div class="exp-devis-field">
          <label for="<?php echo esc_attr($id); ?>-adults">Adultes</label>
          <div class="exp-stepper">
            <button type="button" class="exp-stepper-btn" data-step="minus" aria-label="Retirer un adulte">-</button>
            <input type="number" id="<?php echo esc_attr($id); ?>-adults" name="devis_adults" class="pcq-input" min="1" value="1">
            <button type="button" class="exp-stepper-btn" data-step="plus" aria-label="Ajouter un adulte">+</button>
          </div>
        </div>
        <div class="exp-devis-field">
          <label for="<?php echo esc_attr($id); ?>-children">Enfants</label>
          <div class="exp-stepper">
            <button type="button" class="exp-stepper-btn" data-step="minus" aria-label="Retirer un enfant">-</button>
            <input type="number" id="<?php echo esc_attr($id); ?>-children" name="devis_children" class="pcq-input" min="0" placeholder="0">
            <button type="button" class="exp-stepper-btn" data-step="plus" aria-label="Ajouter un enfant">+</button>
          </div>
        </div>
        <div class="exp-devis-field">
          <label for="<?php echo esc_attr($id); ?>-infants">Bébés</label>
          <div class="exp-stepper">
            <button type="button" class="exp-stepper-btn" data-step="minus" aria-label="Retirer un bébé">-</button>
            <input type="number" id="<?php echo esc_attr($id); ?>-infants" name="devis_infants" class="pcq-input" min="0" placeholder="0">
            <button type="button" class="exp-stepper-btn" data-step="plus" aria-label="Ajouter un bébé">+</button>
          </div>
        </div>
        <div id="<?php echo esc_attr($id); ?>-msg" class="pcq-msg" role="status" aria-live="polite"></div>
        <div class="exp-devis-result" id="<?php echo esc_attr($id); ?>-result" hidden>
          <h4 class="exp-result-title">Résumé de l'estimation</h4>
          <ul id="<?php echo esc_attr($id); ?>-lines" class="pcq-lines"></ul>
          <div class="exp-result-total">
            <span>Total</span>
            <strong id="<?php echo esc_attr($id); ?>-total">—</strong>
          </div>
        </div>
        <div class="exp-devis-error" id="logement-devis-error-msg"></div>
        <div class="exp-devis-actions">
          <button type="button" id="<?php echo esc_attr($id); ?>-pdf-btn" class="pc-btn pc-btn--secondary">
            <i class="fas fa-file-pdf"></i> Télécharger l'estimation
          </button>
          <?php if ($has_lodgify) : ?>
            <button type="button" id="logement-lodgify-reserve-btn" class="pc-btn pc-btn--primary">
              Réserver maintenant
            </button>
          <?php else : ?>
            <button type="button" id="logement-open-modal-btn-local" class="pc-btn pc-btn--primary">
              Réserver maintenant
            </button>
          <?php endif; ?>
        </div>
      </div><?php // Fin de .exp-devis-form 
            ?>
      <div class="exp-devis-company-info" style="display:none;"><?php echo wp_json_encode($company_info); ?></div>
      <div class="exp-devis-experience-title" style="display:none;"><?php echo esc_html(get_the_title()); ?></div>
    </div><?php // Fin de .exp-devis-wrap 
          ?>
  </section>
<?php
  return ob_get_clean();
});

// [logement_experiences_recommandees]
add_shortcode('logement_experiences_recommandees', function ($atts = []) {
  if (!is_singular() || !function_exists('get_field')) {
    return '';
  }
  $recommended_ids = get_field('logement_experiences_recommandees');
  if (empty($recommended_ids)) {
    return '';
  }
  $args = [
    'post_type'      => 'experience',
    'post__in'       => $recommended_ids,
    'orderby'        => 'post__in',
    'posts_per_page' => 3,
  ];
  $query = new WP_Query($args);
  if (!$query->have_posts()) {
    return '';
  }
  ob_start();
?>
  <section class="exp-reco-section">
    <h2 class="exp-reco-title">Excursions recommandées à proximité</h2>
    <div class="exp-reco-grid">
      <?php while ($query->have_posts()) : $query->the_post(); ?>
        <div class="exp-reco-card">
          <a href="<?php the_permalink(); ?>" class="exp-reco-card-link">
            <?php if (has_post_thumbnail()) : ?>
              <div class="exp-reco-card-image">
                <?php the_post_thumbnail('medium_large'); ?>
              </div>
            <?php endif; ?>
            <div class="exp-reco-card-content">
              <h3 class="exp-reco-card-title"><?php the_title(); ?></h3>
              <?php
              $price = 0;
              $pricing_tiers = get_field('exp_types_de_tarifs', get_the_ID());
              if (is_array($pricing_tiers) && !empty($pricing_tiers)) {
                $first_tier = $pricing_tiers[0];
                if (isset($first_tier['exp_tarif_adulte']) && is_numeric($first_tier['exp_tarif_adulte'])) {
                  $price = (float) $first_tier['exp_tarif_adulte'];
                }
              }
              if ($price > 0) :
              ?>
                <div class="exp-reco-card-price">
                  À partir de <?php echo esc_html(number_format_i18n($price, 0)); ?>€ / personne
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
 * GESTIONNAIRE D'ENVOI POUR LA MODALE LOGEMENT
 * ===================================================================
 */
add_action('admin_post_nopriv_logement_booking_request', 'pc_handle_logement_booking_request');
add_action('admin_post_logement_booking_request', 'pc_handle_logement_booking_request');

if (!function_exists('pc_handle_logement_booking_request')) {
  function pc_handle_logement_booking_request()
  {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'logement_booking_request_nonce')) {
      wp_send_json_error(['message' => 'La vérification de sécurité a échoué.']);
      return;
    }

    // === DÉBUT: VÉRIFICATION ANTI-BOT (Logement Modale) ===

    // 1. Vérification du Honeypot
    if (!empty($_POST['booking_reason_logement'])) {
      wp_send_json_success(['message' => 'Votre demande a bien été envoyée !']); // Silent fail
      return;
    }

    // 2. Vérification que la simulation n'est PAS vide ou par défaut (CORRIGÉ)
    $quote_details_raw = $_POST['quote_details'] ?? '';
    if (
      empty($quote_details_raw) ||
      $quote_details_raw === 'Aucune simulation.' ||
      $quote_details_raw === 'Aucune simulation de devis n\'a été effectuée.'
    ) {
      wp_send_json_success(['message' => 'Votre demande a bien été envoyée !']); // Silent fail
      return;
    }
    // === FIN: VÉRIFICATION ANTI-BOT ===

    $logement_id = isset($_POST['logement_id']) ? absint($_POST['logement_id']) : 0;
    $prenom = sanitize_text_field($_POST['prenom'] ?? '');
    $nom = sanitize_text_field($_POST['nom'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $tel = sanitize_text_field($_POST['tel'] ?? '');

    // On utilise notre variable vérifiée
    $quote_details = sanitize_textarea_field($quote_details_raw);

    $adultes = isset($_POST['adultes']) ? absint($_POST['adultes']) : 0;
    $enfants = isset($_POST['enfants']) ? absint($_POST['enfants']) : 0;

    // --- CORRECTION BUG : Ajout des bébés ---
    $bebes = isset($_POST['bebes']) ? absint($_POST['bebes']) : 0;

    $message = sanitize_textarea_field($_POST['message'] ?? '');

    if (!$logement_id || empty($prenom) || empty($nom) || !is_email($email)) {
      wp_send_json_error(['message' => 'Veuillez remplir tous les champs obligatoires.']);
      return;
    }

    // ===== INTELLIGENCE AJOUTÉE : Détection du mode (Direct vs Demande) =====
    $mode_reservation = 'demande'; // Par défaut

    if (function_exists('get_field')) {
      $setting = get_field('mode_reservation', $logement_id);

      // Gestion robuste du format de retour ACF
      if (is_array($setting)) {
        $setting = $setting['value'] ?? ($setting[0] ?? '');
      }
      $setting = (string) $setting;

      if ($setting === 'log_directe' || $setting === 'log_direct') {
        $mode_reservation = 'directe';
      } elseif ($setting === 'log_channel') {
        // Optionnel : Bloquer ou traiter comme demande
        $mode_reservation = 'demande';
      }
    }

    // ===== NOYAU RÉSERVATION : enregistrement dans wp_pc_reservations (si plugin actif) =====
    if (class_exists('PCR_Reservation')) {
      $resa_data = [
        // Identification
        'type'             => 'location',
        'item_id'          => $logement_id,
        'mode_reservation' => $mode_reservation,
        'origine'          => 'site',

        // Dates (pour l’instant probablement vides tant que le JS ne les envoie pas)
        'date_arrivee'     => isset($_POST['arrival'])   ? sanitize_text_field($_POST['arrival'])   : null,
        'date_depart'      => isset($_POST['departure']) ? sanitize_text_field($_POST['departure']) : null,

        // Personnes
        'adultes'          => $adultes,
        'enfants'          => $enfants,
        'bebes'            => $bebes,

        // Client
        'prenom'           => $prenom,
        'nom'              => $nom,
        'email'            => $email,
        'telephone'        => $tel,
        'commentaire_client' => $message,

        // Tarif (sera alimenté correctement quand on aura branché le JS)
        'devise'           => 'EUR',
        'montant_total'    => isset($_POST['total'])      ? (float) $_POST['total']      : 0,
        'detail_tarif'     => isset($_POST['lines_json']) ? wp_kses_post($_POST['lines_json']) : null,

        // Statuts initiaux
        'statut_reservation' => ($mode_reservation === 'directe') ? 'reservee' : 'en_attente_traitement',
        'statut_paiement'    => ($mode_reservation === 'directe') ? 'en_attente_paiement' : 'non_paye',

        // Système
        'date_creation'    => current_time('mysql'),
        'date_maj'         => current_time('mysql'),
      ];

      // Création de la réservation
      $resa_id = PCR_Reservation::create($resa_data);

      // Génération des paiements selon les règles ACF de la fiche
      if ($resa_id && class_exists('PCR_Payment')) {
        PCR_Payment::generate_for_reservation($resa_id);
      }
    }
    // ===== FIN NOYAU RÉSERVATION =====

    $logement_title = get_the_title($logement_id);
    $headers = ['Content-Type: text/plain; charset=UTF-8'];
    $admin_to = 'guadeloupe@prestigecaraibes.com';
    $admin_subject = "Nouvelle demande pour le logement : " . $logement_title;
    $admin_body  = "Une nouvelle demande de réservation a été effectuée.\n\n";
    $admin_body .= "Logement : " . $logement_title . " (ID: " . $logement_id . ")\n\n";
    $admin_body .= "CLIENT\n--------------------------------\n";
    $admin_body .= "Prénom et Nom : " . $prenom . " " . $nom . "\n";
    $admin_body .= "Email : " . $email . "\n";
    $admin_body .= "Téléphone : " . $tel . "\n";

    // --- CORRECTION BUG : Ajout des bébés dans l'email ---
    $admin_body .= "Participants : " . $adultes . " Adulte(s), " . $enfants . " Enfant(s) et " . $bebes . " Bébé(s)\n\n";

    if (!empty($message)) {
      $admin_body .= "MESSAGE SUPPLÉMENTAIRE\n--------------------------------\n";
      $admin_body .= $message . "\n\n";
    }
    $admin_body .= "DÉTAILS DE LA SIMULATION\n--------------------------------\n";
    $admin_body .= $quote_details . "\n";

    $mail_sent_admin = wp_mail($admin_to, $admin_subject, $admin_body, $headers);

    $client_subject = "Confirmation de votre demande pour : " . $logement_title;
    $client_body  = "Bonjour " . $prenom . ",\n\n";
    $client_body .= "Nous avons bien reçu votre demande concernant le logement \"" . $logement_title . "\".\n\n";
    $client_body .= "Nous allons vérifier les disponibilités et nous revenons vers vous dans les plus brefs délais.\n\n";
    $client_body .= "Cordialement,\nL'équipe Prestige Caraïbes";
    wp_mail($email, $client_subject, $client_body, $headers);

    wp_send_json_success(['message' => 'Votre demande a bien été envoyée ! Un email de confirmation vous a été adressé.']);
    exit;
  }
}
