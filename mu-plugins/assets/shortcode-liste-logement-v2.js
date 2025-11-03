(function(){
  'use strict';

  // ===================================================================
  // SECTION 1 : LOGIQUE D'OUVERTURE / FERMETURE DU DROPDOWN
  // ===================================================================

  // Fonction pour fermer tous les dropdowns ouverts
  function closeAllDropdowns(except){
    document.querySelectorAll('.pc-dd[aria-expanded="true"]').forEach(function(wrapper){
      if (except && wrapper === except) return;
      var btn = wrapper.querySelector('.pc-dd__btn');
      var panel = wrapper.querySelector('.pc-dd__panel');
      if (btn) btn.setAttribute('aria-expanded','false');
      if (panel) panel.hidden = true;
      wrapper.setAttribute('aria-expanded','false');
    });
  }

  // Gère le clic sur le bouton ET le clic en dehors pour fermer
  document.addEventListener('click', function(e){
    var btn = e.target.closest && e.target.closest('.pc-dd__btn');
    
    // CAS 1 : Clic sur le bouton pour ouvrir/fermer
    if (btn){
      e.preventDefault();
      var wrapper = btn.closest('.pc-dd');
      var panel = wrapper ? wrapper.querySelector('.pc-dd__panel') : null;
      if (!panel) return;

      var isOpen = wrapper.getAttribute('aria-expanded') === 'true';
      
      closeAllDropdowns(wrapper); // Ferme les autres avant d'ouvrir
      
      wrapper.setAttribute('aria-expanded', !isOpen);
      btn.setAttribute('aria-expanded', !isOpen);
      panel.hidden = isOpen;

      // Correctif pour le focus sur Chrome
      if (!isOpen) {
        setTimeout(function() {
          var input = wrapper.querySelector('.pc-dd__filter');
          if (input) input.focus();
        }, 100);
      }
      return;
    }

    // CAS 2 : Clic en dehors du composant pour le fermer
    var openWrapper = document.querySelector('.pc-dd[aria-expanded="true"]');
    if (openWrapper && !openWrapper.contains(e.target)) {
        closeAllDropdowns();
    }
  });


  // ===================================================================
  // SECTION 2 : LOGIQUE DU FILTRE DE RECHERCHE ET NAVIGATION
  // ===================================================================

  function pcNorm(s){
    if(!s) return '';
    s = s.trim().toLowerCase();
    s = s.replace(/^(l['’]|le|la|les)\s+/i,'');
    return s.normalize('NFD').replace(/[\u0300-\u036f]/g,'');
  }

  function pcApplyFilter(input){
    var wrapper = input.closest('.pc-dd') || input.closest('.pc-dd__panel');
    if (!wrapper) return;
    var list = wrapper.querySelector('.pc-dd__list');
    if (!list) return;

    var items = Array.from(list.querySelectorAll('.pc-dd__item'));
    if (!items.length) return;

    if (!items[0].dataset.norm){
      items.forEach(function(li){ li.dataset.norm = pcNorm(li.textContent); });
    }

    var q = pcNorm(input.value);
    items.forEach(function(li){
      var match = !q || li.dataset.norm.indexOf(q) !== -1;
      li.hidden = !match;
      li.classList.remove('is-sel');
    });
  }

  function handleFilterEvent(e) {
    var input = e.target.closest && e.target.closest('.pc-dd__filter');
    if (!input) return;
    pcApplyFilter(input);
  }

  document.addEventListener('input', handleFilterEvent);
  document.addEventListener('keyup', handleFilterEvent);

  // Gère la navigation clavier (Echap, flèches, Entrée)
  document.addEventListener('keydown', function(e){
    var openWrapper = document.querySelector('.pc-dd[aria-expanded="true"]');
    
    // Gère la touche "Echap" pour fermer
    if (e.key === 'Escape' && openWrapper) {
      closeAllDropdowns();
      var btn = openWrapper.querySelector('.pc-dd__btn');
      if (btn) btn.focus();
      return;
    }

    // Gère les flèches et Entrée DANS le champ de recherche
    var input = e.target.closest && e.target.closest('.pc-dd__filter');
    if (input) {
      var list = openWrapper && openWrapper.querySelector('.pc-dd__list');
      if (!list) return;

      var items = Array.from(list.querySelectorAll('.pc-dd__item')).filter(function(li){ return !li.hidden; });
      var selIndex = items.findIndex(function(li){ return li.classList.contains('is-sel'); });

      if (e.key === 'ArrowDown'){
        selIndex = Math.min(items.length-1, selIndex+1);
        items.forEach(function(li){ li.classList.remove('is-sel'); });
        if (items[selIndex]) items[selIndex].classList.add('is-sel');
        e.preventDefault(); e.stopPropagation();
      }
      if (e.key === 'ArrowUp'){
        selIndex = Math.max(-1, selIndex-1);
        items.forEach(function(li){ li.classList.remove('is-sel'); });
        if (items[selIndex]) items[selIndex].classList.add('is-sel');
        e.preventDefault(); e.stopPropagation();
      }
      if (e.key === 'Enter'){
        var target = (selIndex >= 0 && items[selIndex]) ? items[selIndex] : items[0];
        if (target){
          var a = target.querySelector('a');
          if (a) {
            e.preventDefault(); e.stopPropagation();
            window.location.href = a.href;
          }
        }
      }
    }
  });

  // Anti-autofill (Safari/Chrome)
  (function hardenInputs(){
    document.querySelectorAll('.pc-dd__filter').forEach(function(el){
      el.setAttribute('type','text');
      el.setAttribute('inputmode','search');
      el.setAttribute('autocomplete','off');
      el.setAttribute('autocorrect','off');
      el.setAttribute('autocapitalize','off');
      el.setAttribute('spellcheck','false');
      el.setAttribute('name','sll-no-autofill');
      var form = el.closest('form');
      if (form) form.setAttribute('autocomplete','off');
    });
    if (document.readyState === 'loading'){
      document.addEventListener('DOMContentLoaded', hardenInputs, {once:true});
    }
  })();

})();