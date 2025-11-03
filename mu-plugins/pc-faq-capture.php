<?php
// === [pc_faq_render] — Affiche un accordéon FAQ depuis ACF + enqueuing CSS dédié ===

// --- Helpers d'enqueue pour ce shortcode
if (!function_exists('pc_faq_register_style')) {
    function pc_faq_register_style() {
        // Chemins / URLs
        $rel_path = '/mu-plugins/assets/pc-faq-capture.css';
        $path = WP_CONTENT_DIR . $rel_path;
        $url  = content_url(ltrim($rel_path, '/'));

        if (file_exists($path)) {
            wp_register_style(
                'pc-faq-capture',
                $url,
                [],                       // pas de dépendances
                (string) @filemtime($path),
                'all'
            );
        } else {
            // Pas de CSS dédié trouvé : on ne casse pas, on continue sans.
        }
    }
}

// Détecter la présence du shortcode dans le contenu classique (optimisation)
add_action('wp_enqueue_scripts', function () {
    if (!is_singular()) return;
    global $post;
    if (!$post instanceof WP_Post) return;

    // On enregistre le style au cas où
    pc_faq_register_style();

    // Si le shortcode est dans post_content, on l’enqueue tôt (cas Gutenberg/classique)
    if (has_shortcode($post->post_content, 'pc_faq_render')) {
        if (wp_style_is('pc-faq-capture', 'registered')) {
            wp_enqueue_style('pc-faq-capture');
        }
    }
}, 20);

// Shortcode d'affichage
add_shortcode('pc_faq_render', function ($atts = []) {
    $atts = shortcode_atts([
        'post_id' => 0,
        'open'    => '0',
        'class'   => '',
    ], $atts, 'pc_faq_render');

    $post_id = (int)($atts['post_id'] ?: get_queried_object_id());
    if (!$post_id) return '';

    // Récup ACF repeater
    $rows = function_exists('get_field') ? get_field('pc_faq_items', $post_id) : [];
    if (!is_array($rows) || empty($rows)) return '';

    // Enregistrer + tenter d'enqueuer le CSS (utile quand l'éditeur est Elementor)
    pc_faq_register_style();
    if (wp_style_is('pc-faq-capture', 'registered')) {
        wp_enqueue_style('pc-faq-capture');
    }

    // Fallback inline si, malgré tout, le CSS n’est pas sorti (exécution trop tardive)
    static $pc_faq_css_injected = false;
    $inline_css = '';
    if (!$pc_faq_css_injected && !wp_style_is('pc-faq-capture', 'enqueued')) {
        $rel_path = '/mu-plugins/assets/pc-faq-capture.css';
        $path = WP_CONTENT_DIR . $rel_path;
        if (file_exists($path)) {
            $css = @file_get_contents($path);
            if ($css) {
                $inline_css = "<style id='pc-faq-capture-inline'>\n" . $css . "\n</style>";
                $pc_faq_css_injected = true;
            }
        }
    }

    $open_first = in_array(strtolower((string)$atts['open']), ['1','true','yes','first'], true);
    $classes = 'pc-faq-accordion';
    if (!empty($atts['class'])) {
        $classes .= ' ' . preg_replace('/[^a-z0-9_\\- ]/i', '', $atts['class']);
    }

    ob_start();
    echo $inline_css; // si besoin, injecte le CSS (une seule fois)
    ?>
    <div class="<?php echo esc_attr($classes); ?>">
        <?php foreach ($rows as $i => $row):
            $q = ''; $a = '';
            if (is_array($row)) {
                $q = isset($row['question']) ? wp_strip_all_tags((string)$row['question'], true) : '';
                $a = isset($row['answer'])   ? (string)$row['answer'] : '';
            } elseif (is_object($row)) {
                $q = isset($row->question) ? wp_strip_all_tags((string)$row->question, true) : '';
                $a = isset($row->answer)   ? (string)$row->answer : '';
            }
            $q = trim($q); $a = trim($a);
            if ($q === '' || $a === '') continue;

            $a = wp_kses_post($a);
            $a = wpautop($a);
            $open_attr = ($open_first && $i === 0) ? ' open' : '';
            ?>
            <details class="pc-faq-item"<?php echo $open_attr; ?>>
                <summary class="pc-faq-q"><?php echo esc_html($q); ?></summary>
                <div class="pc-faq-a"><?php echo $a; ?></div>
            </details>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
});

/**
 * ============================================================
 * Shortcodes miroirs FAQ pour Destination / Expérience / Logement
 * - Destination : lit ACF repeater "dest_faq"
 * - Expérience : lit ACF repeater "exp_faq"
 * - Logement  : lit ACF repeater "log_faq" (prévu)
 * Rendu visuel identique à [pc_faq_render] :
 * wrapper .pc-faq-accordion > details.pc-faq-item > summary.pc-faq-q + .pc-faq-a
 * CSS : /mu-plugins/assets/pc-faq-capture.css (déjà présent)
 * ============================================================
 */

if (!function_exists('pc_faq_enqueue_styles')) {
  function pc_faq_enqueue_styles() {
    // S’assure que la feuille est bien chargée quand on utilise ces shortcodes
    if (defined('WPMU_PLUGIN_DIR') && defined('WPMU_PLUGIN_URL')) {
      $css_path = WPMU_PLUGIN_DIR . '/assets/pc-faq-capture.css';
      if (file_exists($css_path)) {
        wp_enqueue_style('pc-faq-capture', WPMU_PLUGIN_URL . '/assets/pc-faq-capture.css', [], '1.0', 'all');
      }
    }
  }
}

if (!function_exists('pc_faq_render_items')) {
  /**
   * Rend les items FAQ au format accordéon <details>/<summary>
   * $rows = [ [question => "...", reponse => "..."], ... ] (array|object)
   * Logique robuste : gère les variantes exp_question/exp_reponse, dest_*, log_*, answer, etc.
   */
  function pc_faq_render_items($rows, $title = '') {
    if (!is_array($rows) || empty($rows)) return '';

    // Clés possibles (array de priorités)
    $Q_KEYS = ['question','exp_question','dest_question','log_question','faq_question'];
    $A_KEYS = ['reponse','exp_reponse','dest_reponse','log_reponse','answer','exp_answer','dest_answer','log_answer','faq_reponse','faq_answer'];

    // Helper : lit une valeur depuis array|object, sinon cherche un nom "proche"
    $read_key = function($row, $candidates) {
      // accès direct
      if (is_array($row)) {
        foreach ($candidates as $k) {
          if (isset($row[$k]) && $row[$k] !== '') return (string) $row[$k];
        }
      } elseif (is_object($row)) {
        foreach ($candidates as $k) {
          if (isset($row->{$k}) && $row->{$k} !== '') return (string) $row->{$k};
        }
      }
      // recherche "large" par similarité (ex: my_exp_question_fr)
      $kv = is_array($row) ? $row : (is_object($row) ? get_object_vars($row) : []);
      foreach ($kv as $k => $v) {
        if ($v === '') continue;
        $kn = strtolower(remove_accents((string)$k));
        foreach ($candidates as $cand) {
          $cn = strtolower(remove_accents((string)$cand));
          if ($cn !== '' && strpos($kn, $cn) !== false) return (string)$v;
        }
      }
      return '';
    };

    ob_start();
    if ($title !== '') {
      echo '<h3 class="pc-faq-title">'.esc_html($title).'</h3>';
    }
    echo '<div class="pc-faq-accordion">';

    foreach ($rows as $row) {
      $q_raw = $read_key($row, $Q_KEYS);
      $a_raw = $read_key($row, $A_KEYS);

      // Nettoyage : Question en texte, Réponse avec HTML autorisé
      $q = trim( wp_strip_all_tags( (string)$q_raw, true ) );
      $a = trim( (string) wp_kses_post( $a_raw ) );

      if ($q === '' || $a === '') continue;

      echo '<details class="pc-faq-item">';
        echo '<summary class="pc-faq-q">'.esc_html($q).'</summary>';
        echo '<div class="pc-faq-a">'.$a.'</div>';
      echo '</details>';
    }

    echo '</div>';
    return ob_get_clean();
  }
}

/**
 * [destination_faq title="FAQ"] — lit dest_faq (Destination)
 */
add_shortcode('destination_faq', function($atts){
  if (!function_exists('get_field')) return '';
  $a = shortcode_atts(['title' => 'Prestige Caraïbes vous réponds', 'post_id' => 0], $atts, 'destination_faq');

  $pid = intval($a['post_id']);
  if (!$pid) {
    if (!is_singular('destination')) return '';
    $pid = get_queried_object_id();
  }

  $rows = get_field('dest_faq', $pid);
  if (empty($rows)) return '';

  pc_faq_enqueue_styles();
  return pc_faq_render_items($rows, $a['title']);
});

/**
 * [experience_faq title="FAQ"] — lit exp_faq (Expérience)
 */
add_shortcode('experience_faq', function($atts){
  if (!function_exists('get_field')) return '';
  $a = shortcode_atts(['title' => 'Prestige Caraïbes vous réponds', 'post_id' => 0], $atts, 'experience_faq');

  $pid = intval($a['post_id']);
  if (!$pid) {
    if (!is_singular('experience')) return '';
    $pid = get_queried_object_id();
  }

  $rows = get_field('exp_faq', $pid);
  if (empty($rows)) return '';

  pc_faq_enqueue_styles();
  return pc_faq_render_items($rows, $a['title']);
});

/**
 * [logement_faq title="FAQ"] — lit log_faq (Logement : villa/appartement/logement)
 */
add_shortcode('logement_faq', function($atts){
  if (!function_exists('get_field')) return '';
  $a = shortcode_atts(['title' => 'Prestige Caraïbes vous réponds', 'post_id' => 0], $atts, 'logement_faq');

  $pid = intval($a['post_id']);
  if (!$pid) {
    if (!is_singular(['villa','appartement','logement'])) return '';
    $pid = get_queried_object_id();
  }

  $rows = get_field('log_faq', $pid);
  if (empty($rows)) return '';

  pc_faq_enqueue_styles();
  return pc_faq_render_items($rows, $a['title']);
});

