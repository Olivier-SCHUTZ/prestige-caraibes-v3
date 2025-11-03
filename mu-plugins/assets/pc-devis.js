// wp-content/mu-plugins/assets/pc-devis.js (Version 2.5 - Ajout Bébés + Fix Capacité)
(function(){
  console.log('[pc-devis] script v2.5 (ajout bébés) chargé');

  // --- CORRECTION : Fonction de formatage monétaire robuste ---
  function eur(n) {
    n = Number(n) || 0;
    // Utilise Intl.NumberFormat pour une meilleure gestion des locales et décimales
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(n);
  }

  // --- Fonctions utilitaires complètes ---
  function ymd(d){
    if (!d || !(d instanceof Date)) return null; // Sécurité
    var m = String(d.getMonth()+1).padStart(2,'0');
    var dd= String(d.getDate()).padStart(2,'0');
    return d.getFullYear()+'-'+m+'-'+dd;
  }
  function addDays(d,n){ var x=new Date(d.getTime()); x.setDate(x.getDate()+n); return x; }
  function inRange(ymdStr, from, to){ return (ymdStr >= from && ymdStr <= to); }
  function normKey(x){
    if (x == null) return '';
    try { x = String(x); } catch(e){ return ''; }
    x = x.normalize ? x.normalize('NFKD').replace(/[\u0300-\u036f]/g,'') : x;
    return x.toLowerCase().replace(/[^a-z0-9%]+/g,'_').replace(/^_+|_+$/g,'');
  }
  function nightPrice(cfg, day){
    if (cfg && Array.isArray(cfg.seasons)){
      for (var i=0;i<cfg.seasons.length;i++){
        var s = cfg.seasons[i]; if (!s || !Array.isArray(s.periods)) continue;
        for (var j=0;j<s.periods.length;j++){
          var p = s.periods[j];
          if (p && p.from && p.to && inRange(day, p.from, p.to)){
            // Retourne le prix saisonnier s'il est défini et > 0, sinon le prix de base
            return Number(s.price) > 0 ? Number(s.price) : (Number(cfg.basePrice) || 0);
          }
        }
      }
    }
    return Number(cfg && cfg.basePrice)||0;
  }
  function requiredMinNights(cfg, nights){
    var req = Number(cfg && (cfg.minNights||cfg.min))||0;
    if (!cfg || !Array.isArray(cfg.seasons) || nights.length === 0) return req > 0 ? req : 1; // Minimum 1 nuit par défaut
    // Trouve le minimum le plus élevé parmi toutes les nuits sélectionnées
    for (var i=0;i<nights.length;i++){
      var d = nights[i];
      for (var s=0;s<cfg.seasons.length;s++){
        var S = cfg.seasons[s]; if (!S || !Array.isArray(S.periods)) continue;
        for (var p=0;p<S.periods.length;p++){
          var P = S.periods[p];
          if (P && P.from && P.to && inRange(d, P.from, P.to)){
            if (Number(S.min_nights) > req) req = Number(S.min_nights);
          }
        }
      }
    }
    return req > 0 ? req : 1; // Retourne au moins 1
  }
  function extraParamsFor(cfg, day){
    if (cfg && Array.isArray(cfg.seasons)){
      for (var i=0;i<cfg.seasons.length;i++){
        var s = cfg.seasons[i]; if (!s || !Array.isArray(s.periods)) continue;
        for (var j=0;j<s.periods.length;j++){
          var p = s.periods[j];
          if (p && p.from && p.to && inRange(day, p.from, p.to)){
            // Utilise les params saisonniers s'ils existent, sinon ceux de base
             return {
                fee: (s.extra_fee !== null && s.extra_fee !== '' ? Number(s.extra_fee) : Number(cfg.extraFee)) || 0,
                from: (s.extra_from !== null && s.extra_from !== '' ? Number(s.extra_from) : Number(cfg.extraFrom)) || 0
            };
          }
        }
      }
    }
    // Retourne les params de base par défaut
    return { fee:Number(cfg && cfg.extraFee)||0, from:Number(cfg && cfg.extraFrom)||0 };
  }

  // ---------- Initialisation d'une section de devis ----------
  function initOne(section){
    if (!section || section.__pcqInit) return;
    section.__pcqInit = true;

    var id  = section.id;
    var cfg = JSON.parse(section.getAttribute('data-pc-devis') || '{}');

    console.log('[pc-devis] Configuration reçue par le calendrier :', cfg);
    console.log('[pc-devis] Dates à désactiver :', cfg.icsDisable);

    const companyInfoEl = section.querySelector('.exp-devis-company-info');
    const logementTitleEl = section.querySelector('.exp-devis-experience-title');
    const companyInfo = companyInfoEl ? JSON.parse(companyInfoEl.textContent || '{}') : {};
    const logementTitle = logementTitleEl ? logementTitleEl.textContent || 'Logement' : 'Logement';
    let currentTotal = 0;
    let currentLines = [];
    var input    = document.getElementById(id+'-dates');
    var adults   = document.getElementById(id+'-adults');
    var children = document.getElementById(id+'-children');
    var infants  = document.getElementById(id+'-infants'); // <-- SÉLECTION BÉBÉS
    var msgBox   = document.getElementById(id+'-msg');
    var out      = document.getElementById(id+'-result');
    var lines    = document.getElementById(id+'-lines');
    var total    = document.getElementById(id+'-total');
    var pdfBtn   = document.getElementById(id+'-pdf-btn');
    if (!input){ console.warn('[pc-devis] Élément #'+id+'-dates introuvable'); return; }
    var CAP = Number(cfg.cap || 0);
    var ranges = Array.isArray(cfg.icsDisable) ? cfg.icsDisable.filter(function(r){ return r && r.from && r.to; }) : [];

    var disableRules = ranges.length ? [function(date){
      var s = ymd(date);
      for (var i = 0; i < ranges.length; i++){
        var r = ranges[i];
        if (s >= r.from && s <= r.to) return true;
      }
      return false;
    }] : [];

    var fp = window.flatpickr(input, {
      mode: 'range',
      dateFormat: 'd/m/Y', // Format d'affichage
      altInput: true,       // Crée un champ visible avec le bon format
      altFormat: "j M Y",   // Format pour le champ visible (ex: 1 Nov 2025)
      minDate: 'today',
      locale: (window.flatpickr.l10ns && window.flatpickr.l10ns.fr) ? 'fr' : undefined,
      disable: disableRules,
      appendTo: document.body, // Pour éviter les problèmes de z-index
      onReady: function(sel, str, inst){
        try { inst.calendarContainer.classList.add('pcq-cal'); } catch(e){}
      },
      onChange: compute, // Appelle compute quand la date change
      conjunction: ' au ' // Séparateur pour l'affichage de la plage
    });
    console.log('[pc-devis] flatpickr prêt pour', id, '|', ranges.length, 'plages désactivées.');

    function parseIntSafe(el){ var v = parseInt(el && el.value,10); return isFinite(v) && v>0 ? v : 0; }

    // --- Fonction clampCapacity MODIFIÉE ---
    function clampCapacity(sourceField){
        if (!CAP || CAP <= 0) return; // Ne rien faire si pas de capacité définie

        var a = parseIntSafe(adults);
        var c = parseIntSafe(children);
        var i = parseIntSafe(infants); // <-- Récupère la valeur des bébés
        var totalGuests = a + c + i; // <-- Calcule le total

        if (totalGuests > CAP) {
            // Affiche le message d'erreur
            if (msgBox) {
                 msgBox.textContent = 'Capacité max : '+CAP+' personnes (' + totalGuests + ' sélectionnés).';
            }

            // Optionnel mais recommandé : Réduire la valeur qui vient d'être augmentée
            var currentVal = 0;
            var inputElement = null;

            if (sourceField === 'adults') { inputElement = adults; currentVal = a; }
            else if (sourceField === 'children') { inputElement = children; currentVal = c; }
            else if (sourceField === 'infants') { inputElement = infants; currentVal = i; }

            // Réduire si nécessaire
            if (inputElement && currentVal > 0) {
                 var reductionNeeded = totalGuests - CAP;
                 var newValue = currentVal - reductionNeeded;
                 newValue = Math.max(parseInt(inputElement.min) || 0, newValue);
                 inputElement.value = newValue;
                 totalGuests = CAP; // Met à jour le total pour le message
                 if (msgBox) msgBox.textContent = 'Capacité max atteinte : '+CAP+' personnes.';
            }

        } else {
            // Efface le message d'erreur si le total est maintenant correct
            if (msgBox && msgBox.textContent.startsWith('Capacité max')) {
                msgBox.textContent = '';
            }
        }
    }

    // --- Fonction compute MODIFIÉE ---
    function compute(){
      try {
        // Le clamp est maintenant appelé par les écouteurs 'input', pas besoin ici directement
        var a = parseIntSafe(adults), c = parseIntSafe(children), i = parseIntSafe(infants), g = a + c + i; // <-- MODIFIÉ: inclut bébés dans g
        var fpi = input._flatpickr;
        if (!fpi || fpi.selectedDates.length < 2){ if (out) out.hidden = true; window.currentLogementTotal = 0; window.currentLogementLines = []; window.currentLogementSelection = null; section.dispatchEvent(new CustomEvent('devisLogementUpdated', { bubbles: true })); return; } // Reset si pas 2 dates

        var start = fpi.selectedDates[0], end = fpi.selectedDates[1];
        var nights = [];
        for (var d=new Date(start); d<end; d=addDays(d,1)) nights.push( ymd(d) );
        var nN = nights.length;
        if(nN <= 0) { if (out) out.hidden = true; window.currentLogementTotal = 0; window.currentLogementLines = []; window.currentLogementSelection = null; section.dispatchEvent(new CustomEvent('devisLogementUpdated', { bubbles: true })); return; } // Reset si 0 nuits

        var reqMin = requiredMinNights(cfg, nights);
        if (reqMin && nN < reqMin){ if (out) out.hidden = true; if (msgBox) msgBox.textContent = 'Séjour minimum : '+reqMin+' nuit'+(reqMin>1?'s':'')+'.'; window.currentLogementTotal = 0; window.currentLogementLines = []; window.currentLogementSelection = null; section.dispatchEvent(new CustomEvent('devisLogementUpdated', { bubbles: true })); return; } // Reset si min nuits
        var maxN = Number(cfg.maxNights)||0;
        if (maxN && nN > maxN){ if (out) out.hidden = true; if (msgBox) msgBox.textContent = 'Séjour maximum : '+maxN+' nuit'+(maxN>1?'s':'')+'.'; window.currentLogementTotal = 0; window.currentLogementLines = []; window.currentLogementSelection = null; section.dispatchEvent(new CustomEvent('devisLogementUpdated', { bubbles: true })); return; } // Reset si max nuits
        // Efface le message d'erreur de capacité si on est arrivé ici
        if (msgBox && msgBox.textContent.startsWith('Capacité max')) msgBox.textContent = '';
        // Efface les messages min/max nuits
        if (msgBox && (msgBox.textContent.startsWith('Séjour minimum') || msgBox.textContent.startsWith('Séjour maximum'))) msgBox.textContent = '';

        var lodging = 0; for (var ni=0;ni<nN;ni++){ lodging += nightPrice(cfg, nights[ni]); }
        var extras = 0; for (var k=0;k<nN;k++){ var ep = extraParamsFor(cfg, nights[k]); if (ep.fee > 0 && ep.from > 0 && g >= ep.from){ extras += (g - (ep.from-1)) * ep.fee; } }
        var cleaning = Number(cfg.cleaning)||0;
        var other = Number(cfg.otherFee)||0;
        var taxe = 0;
        var taxRaw = cfg.taxe_sejour || '';
        if (Array.isArray(taxRaw)) taxRaw = (taxRaw[0] != null ? taxRaw[0] : '');
        if (typeof taxRaw === 'object' && taxRaw !== null && taxRaw.value) { taxRaw = taxRaw.value; } // Compat ACF
        var taxKey = normKey(taxRaw);
        var isPct5 = (taxKey && ((/\b5\b/.test(taxKey) || taxKey.includes('5')) && (taxKey.includes('%') || taxKey.includes('pourcent') || taxKey.includes('pct'))));
        var m = taxKey.match(/([1-5])_?etoile/);
        var stars = m ? parseInt(m[1],10) : null;
        var classRates = {1:0.80, 2:0.90, 3:1.50, 4:2.30, 5:3.00};
        // Calcul taxe : 5% OU basée sur classement étoiles (si adultes > 0)
        if (isPct5 && nN > 0 && g > 0 && a > 0){ var A = (lodging / nN) / g; var B = 0.05 * A; taxe = B * nN * a; }
        else if (stars && classRates[stars] && a > 0){ taxe = classRates[stars] * a * nN; }

        var grand = lodging + extras + cleaning + other + taxe;
        currentTotal = grand; // Sauvegarde pour PDF
        currentLines = []; // Réinitialise pour PDF
        function dateFR(d){ return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' }); }
        currentLines.push({ label: `Hébergement du ${dateFR(start)} au ${dateFR(end)} (${nN} nuits)`, price: eur(lodging) });
        if (extras > 0) currentLines.push({ label: 'Invités supplémentaires', price: eur(extras) });
        if (cleaning > 0) currentLines.push({ label: 'Frais de ménage', price: eur(cleaning) });
        if (other > 0) currentLines.push({ label: cfg.otherLabel || 'Autres frais', price: eur(other) });
        if (taxe > 0) currentLines.push({ label: 'Taxe de séjour', price: eur(taxe) });

        if (lines) {
          lines.innerHTML = ''; // Vide les anciennes lignes
          currentLines.forEach(function(line) {
            var li = document.createElement('li');
            li.classList.add('pcq-line'); // Ajoute la classe pour le style si besoin
            var s1 = document.createElement('span'); s1.textContent = line.label;
            var s2 = document.createElement('span'); s2.textContent = line.price;
            li.appendChild(s1); li.appendChild(s2);
            lines.appendChild(li);
          });
        }
        if (total) total.textContent = eur(grand);
        if (out) out.hidden = false; // Affiche le résultat

        // --- Stockage pour URL Lodgify et export ---
        window.currentLogementTotal = grand;
        window.currentLogementLines = currentLines;
        window.currentLogementSelection = { // <-- MODIFIÉ: Stocke les sélections
            arrival: ymd(start),
            departure: ymd(end),
            adults: a,
            children: c,
            infants: i
        };

        // Déclenche l'événement pour mettre à jour le FAB etc.
        section.dispatchEvent(new CustomEvent('devisLogementUpdated', { bubbles: true }));

      } catch(e){
          console.error('[pc-devis] Erreur de calcul:', e);
          if (msgBox) msgBox.textContent = 'Erreur lors du calcul.';
          if (out) out.hidden = true;
          // Réinitialise les exports en cas d'erreur
          window.currentLogementTotal = 0;
          window.currentLogementLines = [];
          window.currentLogementSelection = null;
          section.dispatchEvent(new CustomEvent('devisLogementUpdated', { bubbles: true }));
        }
    }

    function generatePDF(){
        if (!window.jspdf || !window.jspdf.jsPDF) { alert("La librairie PDF n'est pas chargée."); return; }
        if (currentLines.length === 0 || currentTotal <= 0) { alert("Veuillez d'abord effectuer une simulation valide."); return; }
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        const date = new Date().toLocaleDateString('fr-FR');
        const a = parseIntSafe(adults), c = parseIntSafe(children), i = parseIntSafe(infants); // Récupère bébés pour le PDF aussi

        doc.setFontSize(20);
        doc.text(companyInfo.name || 'Estimation', 105, 20, { align: 'center' });
        doc.setFontSize(10);
        doc.text(`${companyInfo.address || ''}\n${companyInfo.city || ''}`, 105, 30, { align: 'center' });
        doc.line(15, 40, 195, 40);
        doc.setFontSize(12);
        doc.text(`Estimation pour : ${logementTitle}`, 15, 50);
        doc.text(`Date : ${date}`, 195, 50, { align: 'right' });
        doc.setFontSize(10);
        doc.text(`Pour ${a} adulte(s), ${c} enfant(s) et ${i} bébé(s)`, 15, 58); // Ajoute bébés
        let y = 70;
        doc.setFont('helvetica', 'bold');
        doc.text('Description', 15, y);
        doc.text('Montant', 195, y, { align: 'right' });
        doc.line(15, y + 2, 195, y + 2);
        y += 8;

        currentLines.forEach(line => {
            doc.setFont('helvetica', 'normal');
            doc.text(line.label, 15, y);
            // Utilise une police à chasse fixe (monospace) pour aligner les prix
            doc.setFont('courier', 'normal');
            doc.text(line.price, 195, y, { align: 'right' });
            y += 7;
        });

        y += 5;
        doc.line(15, y, 195, y);
        y += 8;
        doc.setFontSize(14);
        doc.setFont('helvetica', 'bold');
        doc.text('Total Estimé (TTC)', 15, y);
        doc.setFont('courier', 'bold'); // Total en gras monospace
        doc.text(eur(currentTotal), 195, y, { align: 'right' });
        y = 270; // Position du pied de page
        doc.line(15, y, 195, y);
        y += 8;
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(8);
        doc.text(`${companyInfo.legal || companyInfo.name} - ${companyInfo.phone || ''} - ${companyInfo.email || ''}`, 105, y, { align: 'center' });

        doc.save(`estimation-${logementTitle.replace(/[^a-z0-9]/gi, '_').toLowerCase()}.pdf`);
    }

    // --- LOGIQUE POUR LES BOUTONS +/- (STEPPER) - VERSION UNIFIÉE ---
    section.querySelectorAll('.exp-stepper-btn').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var input = this.parentElement.querySelector('.pcq-input');
        if (!input) return;

        var stepDirection = this.dataset.step; // 'plus' ou 'minus'
        var currentVal = parseInt(input.value, 10) || 0;
        var min = parseInt(input.min, 10);
        // var max = parseInt(input.max, 10); // Utile si on veut bloquer au max ici
        var step = 1;
        var inputName = input.getAttribute('name'); // Pour identifier le champ dans clampCapacity

        var newVal;
        if (stepDirection === 'plus') {
          newVal = currentVal + step;
          // Optionnel: Vérification MAX ici, mais clampCapacity s'en charge aussi
          // if (!isNaN(max) && newVal > max) {
          //   newVal = max;
          // }
        } else {
          newVal = currentVal - step;
        }

        // Vérification MIN
        if (!isNaN(min) && newVal < min) {
          newVal = min;
        }

        input.value = newVal;

        // Déclenche l'événement "input" pour recalculer et vérifier la capacité
        input.dispatchEvent(new Event('input', { bubbles: true }));
      });
    });

    // --- Écouteurs MODIFIÉS ---
    if (adults)   adults.addEventListener('input', function(){ clampCapacity('adults'); compute(); });
    if (children) children.addEventListener('input', function(){ clampCapacity('children'); compute(); });
    if (infants)  infants.addEventListener('input', function(){ clampCapacity('infants'); compute(); }); // <-- AJOUTÉ
    if (pdfBtn)   pdfBtn.addEventListener('click', generatePDF);

    compute(); // Calcul initial
  } // Fin de initOne

  // --- Lancement de l'initialisation ---
  function boot(){
    var sections = document.querySelectorAll('.pc-devis-section[data-pc-devis]');
    if (!sections.length) return;
    // Charge Flatpickr locale FR si disponible
     if (window.flatpickr && window.flatpickr.l10ns && window.flatpickr.l10ns.fr) {
         window.flatpickr.localize(window.flatpickr.l10ns.fr);
     }
    sections.forEach(initOne);
  }
  if (document.readyState === 'loading'){ document.addEventListener('DOMContentLoaded', boot); }
  else { boot(); }

})(); // Fin de l'IIFE