<?php
/**
 * Plugin Name: PC – Avis (CPT + Shortcodes + AJAX)
 * Description: Avis clients légers (CPT) + affichage, "Voir plus" en AJAX et formulaire masqué/affiché au clic. JSON-LD agrégat (internes).
 */

if ( ! defined('ABSPATH') ) exit;

/* ------------------------------------------------------------------------
 *  CPT pc_review
 * ---------------------------------------------------------------------- */
add_action('init', function () {
  register_post_type('pc_review', [
    'labels' => [
  'name'                  => 'Avis',
  'singular_name'         => 'Avis',
  'menu_name'             => 'Avis',
  'name_admin_bar'        => 'Avis',
  'add_new'               => 'Ajouter',
  'add_new_item'          => 'Ajouter un avis',
  'edit_item'             => 'Modifier l’avis',
  'new_item'              => 'Nouvel avis',
  'view_item'             => 'Voir l’avis',
  'search_items'          => 'Rechercher des avis',
  'not_found'             => 'Aucun avis trouvé',
  'not_found_in_trash'    => 'Aucun avis dans la corbeille',
  'all_items'             => 'Tous les avis',
],
    'public' => false,
    'show_ui' => true,
    'menu_icon' => 'dashicons-star-filled',
    'supports' => ['title'],
  ]);
});
// 2) Auto-remplissage du titre — PLACE-LE ICI (après le CPT)
add_action('save_post_pc_review', function($post_id, $post, $update){
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;

  // anti-boucle
  if (get_post_meta($post_id, '_pc_set_title', true)) return;

  $name  = trim((string) get_post_meta($post_id, 'pc_reviewer_name', true));
  $pid   = (int) get_post_meta($post_id, 'pc_post_id', true);
  $villa = $pid ? get_the_title($pid) : '';

  $parts = [];
  if ($name)  $parts[] = 'Avis de ' . $name;
  if ($villa) $parts[] = $villa;
  if (!$parts) $parts[] = 'Avis';
  $new_title = implode(' – ', $parts);

  $current = get_post_field('post_title', $post_id);
  if (!$current || $current === 'Auto Draft') {
    update_post_meta($post_id, '_pc_set_title', 1);
    wp_update_post(['ID' => $post_id, 'post_title' => $new_title]);
    delete_post_meta($post_id, '_pc_set_title');
  }
}, 20, 3);

/* ------------------------------------------------------------------------
 * Colonnes Admin
 * ---------------------------------------------------------------------- */
/**
 * Ajoute une colonne "Lié à" dans la liste des avis de l'admin.
 */
// 1. Déclarer la nouvelle colonne
add_filter('manage_pc_review_posts_columns', function($columns) {
    $new_columns = [];
    foreach ($columns as $key => $title) {
        $new_columns[$key] = $title;
        // Placer la nouvelle colonne juste après la colonne 'title'
        if ($key === 'title') {
            $new_columns['related_post'] = 'Lié à';
        }
    }
    return $new_columns;
});

// 2. Afficher le contenu de la colonne
add_action('manage_pc_review_posts_custom_column', function($column, $post_id) {
    if ($column === 'related_post') {
        $related_id = get_post_meta($post_id, 'pc_post_id', true);
        if ( $related_id && ($related_post = get_post($related_id)) ) {
            
                $post_type_obj = get_post_type_object(get_post_type($related_post));
                $type_label = $post_type_obj ? $post_type_obj->labels->singular_name : 'Contenu';

                // Lien pour modifier le post lié
                $edit_link = get_edit_post_link($related_id);
                
                echo '<strong><a href="' . esc_url($edit_link) . '">' . esc_html(get_the_title($related_id)) . '</a></strong>';
                echo '<br><em>(' . esc_html($type_label) . ')</em>';

        } else {
            echo '—';
        }
    }
}, 10, 2);

/* ------------------------------------------------------------------------
 *  Helpers
 * ---------------------------------------------------------------------- */
function pc_rev_stars_svg($rating){
  $r = max(0, min(5, intval($rating)));
  $out = '<div class="pc-stars" aria-label="'.esc_attr($r).'/5" style="display:flex">';
  for($i=1;$i<=5;$i++){
    $filled = $i <= $r ? 'currentColor' : 'none';
    $stroke = '#0e2b5c';
    $out .= '<svg width="18" height="18" viewBox="0 0 24 24" style="margin-right:4px">
      <path d="M12 17.3l-6.18 3.75 1.64-7.03L2 9.77l7.19-.61L12 2.5l2.81 6.66 7.19.61-5.46 4.25 1.64 7.03z"
        fill="'.$filled.'" stroke="'.$stroke.'" stroke-width="1.5"/></svg>';
  }
  return $out.'</div>';
}

function pc_rev_get_internal_stats($post_id){
  $q = new WP_Query([
    'post_type'=>'pc_review','post_status'=>'publish','nopaging'=>true,
    'meta_query'=>[
      ['key'=>'pc_post_id','value'=>$post_id,'compare'=>'='],
      ['key'=>'pc_source','value'=>'internal','compare'=>'=']
    ],
    'fields'=>'ids'
  ]);
  $ids = $q->posts;
  $count = count($ids);
  $sum = 0;
  foreach($ids as $rid){ $sum += intval(get_post_meta($rid,'pc_rating',true)); }
  return ['count'=>$count,'avg'=>$count? round($sum/$count,1):0];
}
// Normalise 20250409 -> 2025-04-09 ; 202504 -> 2025-04 ; garde Y-m ou Y-m-d
function pc_rev_format_stayed($raw){
  $raw = trim((string)$raw);
  if(!$raw) return '';
  if(preg_match('/^\d{8}$/', $raw)){ // YYYYMMDD
    return substr($raw,0,4).'-'.substr($raw,4,2).'-'.substr($raw,6,2);
  }
  if(preg_match('/^\d{6}$/', $raw)){ // YYYYMM
    return substr($raw,0,4).'-'.substr($raw,4,2);
  }
  if(preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) return $raw; // YYYY-MM-DD
  if(preg_match('/^\d{4}-\d{2}$/', $raw))     return $raw; // YYYY-MM
  if(preg_match('/^\d{4}\/\d{2}\/\d{2}$/', $raw)) return str_replace('/','-',$raw);
  $dt = date_create($raw);
  return $dt ? $dt->format('Y-m-d') : $raw;
}
function pc_rev_render_card($rid){
  $name    = get_post_meta($rid,'pc_reviewer_name',true) ?: get_the_title($rid);
  $loc     = get_post_meta($rid,'pc_reviewer_location',true);
  $rating  = intval(get_post_meta($rid,'pc_rating',true));
  $title   = get_post_meta($rid,'pc_title',true) ?: get_the_title($rid);
  $body    = get_post_meta($rid,'pc_body',true) ?: get_post_field('post_content',$rid);
  $stayed  = get_post_meta($rid,'pc_stayed_date',true);
  $source  = get_post_meta($rid,'pc_source',true) ?: 'internal';

  ob_start(); ?>
  <article class="pc-review-card" style="background:#fff;border:1px solid #e7e7e7;border-radius:16px;padding:18px;margin-bottom:14px">
    <div style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start">
      <div style="font-weight:700"><?php echo esc_html($name); ?><?php echo $loc?' <span style="opacity:.7">('.esc_html($loc).')</span>':''; ?></div>
      <div style="display:flex;align-items:center;color:#0e2b5c"><?php echo pc_rev_stars_svg($rating); ?></div>
    </div>
    <?php if($title): ?><h3 style="font-family:Lora,serif;font-size:1.25rem;margin:.35rem 0 0"><?php echo esc_html($title); ?></h3><?php endif; ?>
    <div style="margin:.4rem 0 0;line-height:1.7"><?php echo wpautop(esc_html($body)); ?></div>
    <div style="opacity:.7;margin-top:8px">
      <?php
        $tail = [];
        $stayed_fmt = pc_rev_format_stayed($stayed);
if($stayed_fmt) $tail[] = 'séjourné le '.esc_html($stayed_fmt);
        if($source && $source!=='internal') $tail[] = 'via '.esc_html(ucfirst($source));
        if($tail) echo implode(' · ', $tail);
      ?>
    </div>
  </article>
  <?php
  return ob_get_clean();
}

/* Compte total d'avis publiés (toutes sources) pour un post donné */
function pc_rev_total_count($post_id){
  $q = new WP_Query([
    'post_type'=>'pc_review','post_status'=>'publish','nopaging'=>true,
    'meta_query'=>[['key'=>'pc_post_id','value'=>$post_id,'compare'=>'=']],
    'fields'=>'ids'
  ]);
  return count($q->posts);
}

/* ------------------------------------------------------------------------
 *  Assets (JS) – petit script vanilla pour Voir plus + Afficher formulaire
 * ---------------------------------------------------------------------- */
// --- JS en footer : injecté seulement si au moins un shortcode a tourné ---
add_action('wp_footer', function(){
  if ( empty($GLOBALS['pc_reviews_needs_js']) ) return; ?>
  <script>
  (function(){
    function qs(s,ctx){return (ctx||document).querySelector(s)}
    function qsa(s,ctx){return (ctx||document).querySelectorAll(s)}

    // Toggle formulaire (révèle #pc-review-form et scroll)
    qsa('.pc-rev-toggle-form').forEach(function(btn){
      btn.addEventListener('click', function(e){
        e.preventDefault();
        var f = qs('#pc-review-form');
        if(!f) return;
        if(f.style.display==='none' || getComputedStyle(f).display==='none'){ f.style.display='block'; }
        f.scrollIntoView({behavior:'smooth',block:'start'});
      });
    });

    // Voir plus (AJAX)
    qsa('.pc-rev-more').forEach(function(btn){
      btn.addEventListener('click', function(e){
        e.preventDefault();
        var wrap = btn.closest('.pc-reviews');
        if(!wrap) return;
        var pid   = btn.getAttribute('data-post');
        var off   = parseInt(btn.getAttribute('data-offset'),10);
        var limit = parseInt(btn.getAttribute('data-limit'),10);
        var nonce = btn.getAttribute('data-nonce');
        if(!pid || !limit || isNaN(off)) return;

        btn.disabled = true;
        btn.setAttribute('data-loading','1');
        var txt = btn.textContent;
        btn.textContent = 'Chargement…';

        var form = new FormData();
        form.append('action','pc_reviews_more');
        form.append('post_id', pid);
        form.append('offset', off);
        form.append('limit', limit);
        form.append('_nonce', nonce);

        fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {method:'POST', body: form, credentials:'same-origin'})
          .then(function(r){ return r.json();})
          .then(function(json){
            btn.disabled = false;
            btn.textContent = txt;
            if(!json || !json.success) return;
            var data = json.data || {};
            var list = qs('.pc-reviews-list', wrap);
            if(list && data.html){
              var temp = document.createElement('div');
              temp.innerHTML = data.html;
              Array.from(temp.children).forEach(function(card){ list.appendChild(card); });
            }
            if(data.hasMore){
              btn.setAttribute('data-offset', data.nextOffset);
            } else {
              btn.parentNode && btn.parentNode.removeChild(btn);
            }
          })
          .catch(function(){
            btn.disabled=false; btn.textContent=txt;
          });
      });
    });
  })();
  </script>
<?php }, 100);

/* ------------------------------------------------------------------------
 *  Shortcode [pc_reviews limit="5"]
 *  - Affiche les X derniers + bouton “Voir plus” en AJAX si total > X
 *  - Bouton “Laisser un avis” qui révèle le formulaire (id #pc-review-form)
 * ---------------------------------------------------------------------- */
add_shortcode('pc_reviews', function($atts){
  if(!is_singular()) return '';
  $post_id = get_the_ID();
  $a = shortcode_atts(['limit'=>5], $atts);
  $limit = max(1, intval($a['limit']));

  // Stats (moyenne interne)
  $stats = pc_rev_get_internal_stats($post_id);

  // Query initiale
  $q = new WP_Query([
    'post_type'=>'pc_review','post_status'=>'publish','posts_per_page'=>$limit,
    'meta_query'=>[['key'=>'pc_post_id','value'=>$post_id,'compare'=>'=']],
    'orderby'=>['date'=>'DESC']
  ]);

  $total = pc_rev_total_count($post_id);
  $has_more = $total > $limit;
  $nonce = wp_create_nonce('pc_reviews_more'); // AJAX nonce

  // Flag pour enqueuer le JS
  $GLOBALS['pc_reviews_needs_js'] = true;

  ob_start(); ?>
  <section id="avis" class="pc-reviews" style="--pc-primary:#0e2b5c;--pc-accent:#005F73">
    <div class="pc-reviews-head" style="display:flex;gap:.75rem;align-items:center;margin-bottom:1rem">
      <h3 style="margin:0;font-size:1.7rem;font-weight:500;
           font-family:Poppins,system-ui,-apple-system,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif">
  Avis
</h3>
      <?php if($stats['count']): ?>
        <div style="display:flex;align-items:center;color:var(--pc-primary)">
          <strong style="margin-right:.35rem"><?php echo esc_html($stats['avg']); ?></strong>
          <?php echo pc_rev_stars_svg(round($stats['avg'])); ?>
          <span style="opacity:.8;margin-left:.25rem">(<?php echo intval($stats['count']); ?>)</span>
        </div>
      <?php endif; ?>
      <a href="#pc-review-form" class="pc-rev-toggle-form" style="margin-left:auto;padding:.55rem .9rem;border-radius:.55rem;background:#0e2b5c;color:#fff;text-decoration:none">Laisser un avis</a>
    </div>

    <div class="pc-reviews-list">
      <?php
      if($q->have_posts()){
        while($q->have_posts()){ $q->the_post(); echo pc_rev_render_card(get_the_ID()); }
        wp_reset_postdata();
      } else {
        echo '<p>Soyez le premier à laisser un avis.</p>';
      }
      ?>
    </div>

    <?php if($has_more): ?>
  <div style="text-align:center;margin-top:10px">
    <button type="button" class="pc-rev-more"
      data-post="<?php echo esc_attr($post_id); ?>"
      data-offset="<?php echo esc_attr($limit); ?>"
      data-limit="<?php echo esc_attr($limit); ?>"
      data-nonce="<?php echo esc_attr($nonce); ?>"
      style="padding:.6rem 1rem;border:1px solid #0e2b5c;border-radius:.6rem;background:#fff;color:#0e2b5c">
      Voir plus
    </button>
  </div>
<?php endif; ?>
  </section>
  <?php
  return ob_get_clean();
});

/* ------------------------------------------------------------------------
 *  Shortcode [pc_review_form open="0"]
 *  - open="0" => caché (par défaut), open="1" => visible
 *  - Le bouton “Laisser un avis” du bloc avis le révèle et scroll jusqu’au form
 * ---------------------------------------------------------------------- */
add_shortcode('pc_review_form', function($atts){
  if(!is_singular()) return '';
  $a = shortcode_atts(['open'=>'0'], $atts);
  $open = ($a['open'] === '1');

  $post_id = get_the_ID();
  $msg = '';

  if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['pc_review_nonce']) && wp_verify_nonce($_POST['pc_review_nonce'],'pc_review_submit')){
    if(!empty($_POST['website'])) return ''; // honeypot
    $ipkey = 'pc_rev_ip_'.md5($_SERVER['REMOTE_ADDR']);
    if(get_transient($ipkey)){
      $msg = '<div style="color:#b00020">Trop de tentatives, réessayez plus tard.</div>';
    } else {
      $name = sanitize_text_field($_POST['pc_name'] ?? '');
      $email= sanitize_email($_POST['pc_email'] ?? '');
      $loc  = sanitize_text_field($_POST['pc_location'] ?? '');
      $rating = max(1,min(5,intval($_POST['pc_rating'] ?? 0)));
      $title  = sanitize_text_field($_POST['pc_title'] ?? '');
      $body   = wp_kses_post($_POST['pc_body'] ?? '');
      $stayed = sanitize_text_field($_POST['pc_stayed'] ?? '');
      if($name && $email && $rating && $body){
        $rid = wp_insert_post([
          'post_type'=>'pc_review','post_status'=>'pending',
          'post_title'=>$title ?: ('Avis de '.$name),'post_content'=>$body
        ]);
        if($rid && !is_wp_error($rid)){
          update_post_meta($rid,'pc_post_id',$post_id);
          update_post_meta($rid,'pc_reviewer_name',$name);
          update_post_meta($rid,'pc_reviewer_location',$loc);
          update_post_meta($rid,'pc_rating',$rating);
          update_post_meta($rid,'pc_title',$title);
          update_post_meta($rid,'pc_body',$body);
          update_post_meta($rid,'pc_stayed_date',$stayed);
          update_post_meta($rid,'pc_source','internal');
          if($email) update_post_meta($rid,'pc_email',$email);

          wp_mail(get_option('admin_email'),'Nouvel avis en attente',
            "Un avis a été soumis pour la fiche #$post_id.\nModérez-le dans l’admin.");

          set_transient($ipkey,1, 15 * MINUTE_IN_SECONDS);
          $msg = '<div style="color:#0a7a3b">Merci ! Votre avis est bien enregistré et sera publié après validation.</div>';
        }
      } else {
        $msg = '<div style="color:#b00020">Merci de remplir les champs obligatoires.</div>';
      }
    }
  }

  // Flag JS (pour que le bouton “Laisser un avis” fonctionne même si on charge le form seul)
  $GLOBALS['pc_reviews_needs_js'] = true;

  ob_start(); ?>
  <form id="pc-review-form" method="post"
        style="<?php echo $open ? '' : 'display:none;'; ?>margin-top:16px;border:1px solid #e7e7e7;border-radius:16px;padding:18px">
    <?php echo $msg; ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <div><label>Nom*</label><input name="pc_name" required style="width:100%;padding:.6rem;border:1px solid #ccc;border-radius:8px"></div>
      <div><label>Email*</label><input type="email" name="pc_email" required style="width:100%;padding:.6rem;border:1px solid #ccc;border-radius:8px"></div>
      <div><label>Pays (optionnel)</label><input name="pc_location" style="width:100%;padding:.6rem;border:1px solid #ccc;border-radius:8px"></div>
      <div><label>Note*</label>
        <select name="pc_rating" required style="width:100%;padding:.6rem;border:1px solid #ccc;border-radius:8px">
          <option value="">—</option><?php for($i=5;$i>=1;$i--) echo "<option value='$i'>$i</option>"; ?>
        </select>
      </div>
      <div style="grid-column:1/-1"><label>Titre (optionnel)</label><input name="pc_title" style="width:100%;padding:.6rem;border:1px solid #ccc;border-radius:8px"></div>
      <div style="grid-column:1/-1"><label>Votre avis*</label><textarea name="pc_body" required rows="6" style="width:100%;padding:.6rem;border:1px solid #ccc;border-radius:8px"></textarea></div>
      <div><label>Mois de séjour (YYYY-MM)</label><input name="pc_stayed" placeholder="2025-08" style="width:100%;padding:.6rem;border:1px solid #ccc;border-radius:8px"></div>
    </div>
    <input type="text" name="website" style="display:none">
    <?php wp_nonce_field('pc_review_submit','pc_review_nonce'); ?>
    <button style="margin-top:12px;padding:.7rem 1.1rem;border-radius:.6rem;background:#0e2b5c;color:#fff;border:0">Envoyer mon avis</button>
  </form>
  <?php return ob_get_clean();
});

/* ------------------------------------------------------------------------
 *  AJAX: Voir plus
 *  - action: pc_reviews_more
 *  - expects: post_id, offset, limit, _nonce
 * ---------------------------------------------------------------------- */
add_action('wp_ajax_pc_reviews_more', 'pc_reviews_ajax_more');
add_action('wp_ajax_nopriv_pc_reviews_more', 'pc_reviews_ajax_more');
function pc_reviews_ajax_more(){
  $nonce = isset($_POST['_nonce']) ? $_POST['_nonce'] : '';
  if( ! wp_verify_nonce($nonce, 'pc_reviews_more') ){
    wp_send_json_error(['message'=>'Invalid nonce'], 403);
  }
  $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
  $offset  = isset($_POST['offset'])  ? max(0, intval($_POST['offset'])) : 0;
  $limit   = isset($_POST['limit'])   ? max(1, intval($_POST['limit'])) : 5;
  if(!$post_id){ wp_send_json_error(['message'=>'Bad post_id'], 400); }

  $total = pc_rev_total_count($post_id);

  $q = new WP_Query([
    'post_type'=>'pc_review','post_status'=>'publish',
    'posts_per_page'=>$limit,'offset'=>$offset,
    'meta_query'=>[['key'=>'pc_post_id','value'=>$post_id,'compare'=>'=']],
    'orderby'=>['date'=>'DESC']
  ]);

  ob_start();
  if($q->have_posts()){
    while($q->have_posts()){ $q->the_post();
      echo pc_rev_render_card(get_the_ID());
    }
    wp_reset_postdata();
  }
  $html = ob_get_clean();

  $nextOffset = $offset + $limit;
  $hasMore = $nextOffset < $total;

  wp_send_json_success([
    'html' => $html,
    'nextOffset' => $nextOffset,
    'hasMore' => $hasMore,
  ]);
}

/* ------------------------------------------------------------------------
 *  JSON-LD : agrégat + 3 avis internes récents
 *  (Le builder VR appliquera le filtre s'il l'appelle)
 * ---------------------------------------------------------------------- */
add_filter('pc_vacationrental_schema', function($schema, $post_id){
  $stats = pc_rev_get_internal_stats($post_id);
  if($stats['count']){
    $schema['aggregateRating'] = [
      '@type'=>'AggregateRating',
      'ratingValue'=> $stats['avg'],
      'reviewCount'=> $stats['count']
    ];
    $q = new WP_Query([
      'post_type'=>'pc_review','post_status'=>'publish','posts_per_page'=>3,
      'meta_query'=>[
        ['key'=>'pc_post_id','value'=>$post_id,'compare'=>'='],
        ['key'=>'pc_source','value'=>'internal','compare'=>'=']
      ]
    ]);
    $reviews = [];
    while($q->have_posts()){ $q->the_post();
      $rid = get_the_ID();
      $reviews[] = [
        '@type'=>'Review',
        'author'=>['@type'=>'Person','name'=> get_post_meta($rid,'pc_reviewer_name',true) ?: 'Client'],
        'reviewBody'=> wp_strip_all_tags(get_post_meta($rid,'pc_body',true) ?: get_the_content()),
        'reviewRating'=> ['@type'=>'Rating','ratingValue'=> intval(get_post_meta($rid,'pc_rating',true)), 'bestRating'=>5, 'worstRating'=>1],
        'datePublished'=> get_the_date('c', $rid)
      ];
    } wp_reset_postdata();
    if($reviews) $schema['review'] = $reviews;
  }
  return $schema;
}, 10, 2);
