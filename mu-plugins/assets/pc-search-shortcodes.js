;(function(w,d,$){
  'use strict';
  if(!$||!$.fn){ console.error('[PC Search] jQuery missing'); return; }

  jQuery(function($){
    // [PC START] Initialisation différée et logique JS optimisée
// ===================================================================
var $wrappers = $('.pc-search-wrapper');
if (!$wrappers.length) return;

// Déclaration des variables globales au script
var resultsContainer = $('#pc-results-container');
var mapContainer = $('#pc-map-container');
var map = null, markersLayer = null;
var themeFromUrl = new URLSearchParams(w.location.search).get('theme') || '';

function debounce(fn, t){ var h=null; return function(){ var ctx=this, args=arguments; clearTimeout(h); h=setTimeout(function(){ fn.apply(ctx,args); }, t||350); }; }
var debouncedSearch = debounce(function(){ performSearch(1); }, 350);

// Fonction pour mettre à jour la carte (inchangée)
function updateMapMarkers(items){
  if(!map||!markersLayer) return;
  markersLayer.clearLayers();
  if(!items||!items.length) return;
  var bounds=[];
  items.forEach(function(it){
    var lat=it.latitude||it.lat, lng=it.longitude||it.lng; if(!lat||!lng) return;
    var ll=[parseFloat(lat), parseFloat(lng)];
    var title = it.title||it.post_title||'';
    var price = it.price||it.base_price_from||'';
    var link  = it.link||it.permalink||'#';
    L.marker(ll).addTo(markersLayer)
      .bindPopup('<strong>'+title+'</strong><br>'+(price? (price+'€ / nuit') : '')+'<br><a href="'+link+'" target="_blank" rel="noopener">Voir la villa</a>');
    bounds.push(ll);
  });
  if(bounds.length) map.fitBounds(bounds,{padding:[50,50]});
}

// Fonctions de rendu AJAX (inchangées)
function renderVignettes(html){
  if(!resultsContainer.length) return;
  resultsContainer.html(html);
}
function renderPagination(html){
  $('.pc-pagination').remove();
  if (html) {
      resultsContainer.after(html);
  }
}

function collectFormData($root){
  var eq=[]; $root.find('.filter-eq:checked').each(function(){ eq.push($(this).val()); });
  return {
    action:'pc_filter_logements', security:(w.pc_search_params&&w.pc_search_params.nonce)||'',
    ville:$root.find('#filter-ville').val()||'',
    date_arrivee:$root.find('#filter-date-arrivee-iso').val()||'',
    date_depart:$root.find('#filter-date-depart-iso').val()||'',
    invites:$root.find('#filter-invites').val()||'1',
    prix_min:$root.find('#filter-prix-min').val()||'',
    prix_max:$root.find('#filter-prix-max').val()||'',
    chambres:$root.find('#filter-chambres').val()||'0',
    sdb:$root.find('#filter-sdb').val()||'0',
    equipements:eq
  };
}

// Fonction de recherche AJAX (inchangée)
function performSearch(page){
  page = page||1;
  var $root = $('.pc-search-wrapper[data-pc-search-mode="ajax"]').first();
  if(!$root.length) return;
  var data = collectFormData($root); data.page=page;

  if(themeFromUrl){ data.theme = themeFromUrl; }

  resultsContainer.addClass('is-loading'); $('.pc-pagination').remove();
  $.ajax({
    url:(w.pc_search_params&&w.pc_search_params.ajax_url)||'', type:'POST', data:data,
    success:function(res){
      resultsContainer.removeClass('is-loading');
      var d = (res && res.data) ? res.data : {};
      if(res && res.success && d){
        renderVignettes(d.vignettes_html || '<div class="pc-no-results"><h3>Une erreur est survenue.</h3><p>Veuillez réessayer.</p></div>');
        renderPagination(d.pagination_html || '');
        updateMapMarkers(d.map_data || []);
      } else {
        renderVignettes('<div class="pc-no-results"><h3>Une erreur est survenue.</h3><p>Veuillez réessayer.</p></div>');
      }
    },
    error:function(){
      resultsContainer.removeClass('is-loading');
      renderVignettes('<div class="pc-no-results"><h3>Une erreur de communication est survenue.</h3><p>Veuillez réessayer.</p></div>');
    }
  });
}

// Nouvelle fonction qui initialise les scripts lourds
function initializeNonCriticalJS($root) {
    // Initialisation de la carte Leaflet
    if (mapContainer.length && typeof w.L !== 'undefined' && !map) {
        map = L.map(mapContainer[0]).setView([16.265, -61.551], 10);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
        markersLayer = new L.LayerGroup().addTo(map);
        // Si le SSR a chargé des vignettes, on lance une recherche pour peupler la carte
        if ($root.data('pc-search-mode') === 'ajax' && resultsContainer.find('.pc-vignette').length > 0) {
            performSearch(1);
        }
    }

    // Initialisation de Flatpickr
    var $arrVis = $root.find('#filter-date-arrivee');
    if (typeof w.flatpickr !== 'undefined' && $arrVis.length && !$root.data('fp')) {
        var months = function() { return w.matchMedia('(min-width:768px)').matches ? 2 : 1; };
        var cfg = {
            locale: (w.flatpickr && w.flatpickr.l10ns && w.flatpickr.l10ns.fr) ? 'fr' : 'default',
            minDate: 'today',
            mode: 'range',
            dateFormat: 'd/m/y',
            showMonths: months(),
            allowInput: false,
            onChange: function(sel) {
                var $depVis=$root.find('#filter-date-depart'), $arrIso=$root.find('#filter-date-arrivee-iso'), $depIso=$root.find('#filter-date-depart-iso');
                if (!sel || !sel.length) { $arrVis.val(''); $depVis.val(''); $arrIso.val(''); $depIso.val(''); } 
                else if (sel.length === 1) { $arrVis.val(w.flatpickr.formatDate(sel[0], 'd/m/y')); $depVis.val(''); $arrIso.val(w.flatpickr.formatDate(sel[0], 'Y-m-d')); $depIso.val(''); }
                else { $arrVis.val(w.flatpickr.formatDate(sel[0], 'd/m/y')); $depVis.val(w.flatpickr.formatDate(sel[1], 'd/m/y')); $arrIso.val(w.flatpickr.formatDate(sel[0], 'Y-m-d')); $depIso.val(w.flatpickr.formatDate(sel[1], 'Y-m-d')); if ($root.data('pc-search-mode') === 'ajax') { themeFromUrl = ''; performSearch(1); } }
            }
        };
        if (typeof w.rangePlugin !== 'undefined') {
            cfg.plugins = [new w.rangePlugin({ input: '#filter-date-depart' })];
        }
        var fp = w.flatpickr($arrVis[0], cfg);
        $root.data('fp', fp);
        var mq = w.matchMedia('(min-width:768px)');
        var upd = function() { try { var m = mq.matches ? 2 : 1; if (fp && fp.config.showMonths !== m) { fp.set('showMonths', m); if (fp.redraw) fp.redraw(); } } catch (e) {} };
        if (mq.addEventListener) mq.addEventListener('change', upd); else mq.addListener(upd);
    }
}
// ===================================================================
// [PC END]

    $wrappers.each(function(){
        // [PC START] Appel différé de l'initialisation
// ===================================================================
var $root = $(this);

// On utilise requestIdleCallback pour lancer les initialisations lourdes
// quand le navigateur est inactif, afin de ne pas bloquer le rendu.
if ('requestIdleCallback' in window) {
    requestIdleCallback(function() { initializeNonCriticalJS($root); });
} else {
    // Fallback pour les anciens navigateurs
    setTimeout(function() { initializeNonCriticalJS($root); }, 300);
}
// ===================================================================
// [PC END]
      var $root=$(this); var mode=$root.data('pc-search-mode')||'ajax'; var $form=$root.find('#pc-filters-form');
      var $hiddenInv=$root.find('#filter-invites'), $summary=$root.find('#guests-summary'), $pop=$root.find('.pc-guests-popover');
      var $arrIso = $root.find('#filter-date-arrivee-iso'); // <--- AJOUTER
      var $depIso = $root.find('#filter-date-depart-iso'); // <--- AJOUTER
      var $arrVis = $root.find('#filter-date-arrivee');     // <--- AJOUTER
      var $depVis = $root.find('#filter-date-depart');     // <--- AJOUTER
      function updateGuests(){ var a=parseInt($root.find('span[data-type="adultes"]').text(),10)||1; var e=parseInt($root.find('span[data-type="enfants"]').text(),10)||0; var b=parseInt($root.find('span[data-type="bebes"]').text(),10)||0; var total=Math.max(1,a+e); $hiddenInv.val(total); if($summary.length){ var txt=a+' adulte'+(a>1?'s':''); if(e>0) txt+=', '+e+' enfant'+(e>1?'s':''); if(b>0) txt+=', '+b+' bébé'+(b>1?'s':''); $summary.text(txt); } }
      updateGuests();
      $root.on('click','.pc-guests-trigger',function(e){ e.stopPropagation(); $pop.prop('hidden',!$pop.prop('hidden')); });
      $root.on('click','.pc-guests-close',function(e){ e.stopPropagation(); $pop.prop('hidden',true); });
      $root.on('click','.guest-stepper',function(){ var t=$(this).data('type'), s=parseInt($(this).data('step'),10), $sp=$root.find('span[data-type="'+t+'"]').first(), v=parseInt($sp.text(),10)||0; var nv=v+s; if(t==='adultes'&&nv<1) nv=1; if(nv<0) nv=0; $sp.text(nv); updateGuests(); if(mode==='ajax') debouncedSearch(); });
      $(d).on('click',function(e){ if(!$(e.target).closest('.pc-search-field--guests').length){ $('.pc-guests-popover').prop('hidden',true); } });

      $root.on('click','.pc-adv-toggle',function(e){ e.preventDefault(); $root.find('.pc-advanced').prop('hidden',function(i,v){ return !v; }); });
      $root.on('click','.pc-adv-close', function(e){ e.preventDefault(); $root.find('.pc-advanced').prop('hidden',true); });
      $(d).on('click',function(e){ if(!$(e.target).closest('.pc-advanced,.pc-adv-toggle').length){ $('.pc-advanced').prop('hidden',true); } });

      var $wrapP=$root.find('.pc-price-range');
      if($wrapP.length){ var $minR=$root.find('#filter-prix-min-range'), $maxR=$root.find('#filter-prix-max-range'), $minN=$root.find('#filter-prix-min'), $maxN=$root.find('#filter-prix-max'), rMin=parseInt($wrapP.data('min')||0,10), rMax=parseInt($wrapP.data('max')||2000,10), rStep=parseInt($wrapP.data('step')||10,10); $minR.attr({min:rMin,max:rMax,step:rStep}); $maxR.attr({min:rMin,max:rMax,step:rStep}); function clamp(v,min,max){ v=parseInt(v,10); if(isNaN(v)) return min; return Math.max(min,Math.min(max,v)); } function syncRange(){ var a=clamp($minR.val(),rMin,rMax), b=clamp($maxR.val(),rMin,rMax); if(a>b){ var t=a; a=b; b=t; } $minN.val(a||''); $maxN.val(b===rMax?'':b); if(mode==='ajax') debouncedSearch(); } function syncNumber(){ var a=clamp($minN.val()||rMin,rMin,rMax), b=clamp($maxN.val()||rMax,rMin,rMax); if(a>b){ var t=a; a=b; b=t; } $minR.val(a); $maxR.val(b); if(mode==='ajax') debouncedSearch(); } $minR.on('input change',syncRange); $maxR.on('input change',syncRange); $minN.on('input change',syncNumber); $maxN.on('input change',syncNumber); }
      $root.on('click','.num-stepper',function(){ var t=$(this).data('target'), s=parseInt($(this).data('step'),10); var $i=$root.find('#filter-'+t); var v=parseInt($i.val(),10)||0; $i.val(Math.max(0,v+s)).trigger('change'); if(mode==='ajax') debouncedSearch(); });
      $root.on('input change','#filter-chambres,#filter-sdb',function(){ var v=parseInt($(this).val(),10); if(isNaN(v)||v<0) v=0; $(this).val(v); if(mode==='ajax') debouncedSearch(); });

      $root.on('click','.pc-adv-reset',function(e){ e.preventDefault();
        var inst=$root.data('fp'); if(inst&&inst.clear) inst.clear();
        $root.find('#filter-ville,#filter-date-arrivee,#filter-date-depart,#filter-date-arrivee-iso,#filter-date-depart-iso').val('');
        $root.find('span[data-type="adultes"]').text('1');
        $root.find('span[data-type="enfants"], span[data-type="bebes"]').text('0');
        updateGuests();
        if($wrapP.length){ var min=$root.find('#filter-prix-min-range').attr('min')||'0'; var max=$root.find('#filter-prix-max-range').attr('max')||''; $root.find('#filter-prix-min-range').val(min); $root.find('#filter-prix-max-range').val(max); $root.find('#filter-prix-min').val(''); $root.find('#filter-prix-max').val(''); }
        $root.find('#filter-chambres,#filter-sdb').val('0');
        $root.find('.filter-eq:checked').prop('checked',false);
        themeFromUrl = '';
        if(mode==='ajax') performSearch(1);
      });
      if(mode==='ajax'){
        if($form.length){ $form.off('submit').on('submit',function(e){ e.preventDefault(); themeFromUrl = ''; performSearch(1); }); }
        $root.find('#filter-ville').on('change',function(){ themeFromUrl = ''; performSearch(1); });
        $root.on('change','.filter-eq',function(){ themeFromUrl = ''; performSearch(1); });
      }
      if(mode==='ajax'){
        var params = new URLSearchParams(w.location.search);
        var didSomething=false;
        if(params.has('ville')){ $root.find('#filter-ville').val(params.get('ville')); didSomething=true; }
        var dA=params.get('date_arrivee'), dD=params.get('date_depart');
        if(dA && dD){
          $arrIso.val(dA); $depIso.val(dD);
          try {
            // On met à jour les champs de date VISIBLES (format d/m/y)
            if (w.flatpickr && w.flatpickr.parseDate) {
              $arrVis.val(w.flatpickr.formatDate(w.flatpickr.parseDate(dA, 'Y-m-d'), 'd/m/y'));
              $depVis.val(w.flatpickr.formatDate(w.flatpickr.parseDate(dD, 'Y-m-d'), 'd/m/y'));
            }
            // On met à jour l'instance flatpickr (fp) SI elle est déjà initialisée
            var fp = $root.data('fp');
            if (fp && fp.setDate) {
                fp.setDate([dA, dD], false, 'Y-m-d');
            }
          } catch (e) {
            console.warn('[PC Search] Error setting dates from URL params', e);
          }
          didSomething=true;
        }
        if(params.has('invites')){
          var inv=Math.max(1, parseInt(params.get('invites'),10)||1);
          $root.find('span[data-type="adultes"]').text(inv);
          $root.find('span[data-type="enfants"]').text('0');
          $root.find('span[data-type="bebes"]').text('0');
          updateGuests();
          didSomething=true;
        }
        if(themeFromUrl) didSomething = true;
        performSearch(1);
      }
    });

    $(d).on('click','.pc-pagination a',function(e){ e.preventDefault(); var page=parseInt($(this).data('page'),10)||1; performSearch(page); var $t=$('.pc-search-wrapper'); if($t.length){ $('html,body').animate({scrollTop:$t.offset().top-50},500); } });
  });
})(window,document,window.jQuery);