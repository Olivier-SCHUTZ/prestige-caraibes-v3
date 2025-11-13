// wp-content/mu-plugins/assets/pc-devis.js (Version 2.5 - Ajout Bébés + Fix Capacité)
(function () {
  console.log("[pc-devis] script v2.5 (ajout bébés) chargé");

  // --- CORRECTION : Fonction de formatage monétaire robuste ---
  function eur(n) {
    n = Number(n) || 0;
    // Utilise Intl.NumberFormat pour une meilleure gestion des locales et décimales
    return new Intl.NumberFormat("fr-FR", {
      style: "currency",
      currency: "EUR",
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    }).format(n);
  }

  // --- Normalisation montants pour jsPDF (supprime "/" & espaces insécables) ---
  function formatCurrencyPDF(input) {
    if (typeof input !== "string") {
      input = eur(input);
    }
    return String(input)
      .replace(/\//g, " ")
      .replace(/\u00A0|\u202F/g, " ")
      .replace(/\s{2,}/g, " ")
      .trim();
  }

  // --- Fonctions utilitaires complètes ---
  function ymd(d) {
    if (!d || !(d instanceof Date)) return null; // Sécurité
    var m = String(d.getMonth() + 1).padStart(2, "0");
    var dd = String(d.getDate()).padStart(2, "0");
    return d.getFullYear() + "-" + m + "-" + dd;
  }
  function addDays(d, n) {
    var x = new Date(d.getTime());
    x.setDate(x.getDate() + n);
    return x;
  }
  function inRange(ymdStr, from, to) {
    return ymdStr >= from && ymdStr <= to;
  }
  function normKey(x) {
    if (x == null) return "";
    try {
      x = String(x);
    } catch (e) {
      return "";
    }
    x = x.normalize ? x.normalize("NFKD").replace(/[\u0300-\u036f]/g, "") : x;
    return x
      .toLowerCase()
      .replace(/[^a-z0-9%]+/g, "_")
      .replace(/^_+|_+$/g, "");
  }
  function nightPrice(cfg, day) {
    if (cfg && Array.isArray(cfg.seasons)) {
      for (var i = 0; i < cfg.seasons.length; i++) {
        var s = cfg.seasons[i];
        if (!s || !Array.isArray(s.periods)) continue;
        for (var j = 0; j < s.periods.length; j++) {
          var p = s.periods[j];
          if (p && p.from && p.to && inRange(day, p.from, p.to)) {
            return Number(s.price) > 0
              ? Number(s.price)
              : Number(cfg.basePrice) || 0;
          }
        }
      }
    }
    return Number(cfg && cfg.basePrice) || 0;
  }
  function requiredMinNights(cfg, nights) {
    var req = Number(cfg && (cfg.minNights || cfg.min)) || 0;
    if (!cfg || !Array.isArray(cfg.seasons) || nights.length === 0)
      return req > 0 ? req : 1;
    for (var i = 0; i < nights.length; i++) {
      var d = nights[i];
      for (var s = 0; s < cfg.seasons.length; s++) {
        var S = cfg.seasons[s];
        if (!S || !Array.isArray(S.periods)) continue;
        for (var p = 0; p < S.periods.length; p++) {
          var P = S.periods[p];
          if (P && P.from && P.to && inRange(d, P.from, P.to)) {
            if (Number(S.min_nights) > req) req = Number(S.min_nights);
          }
        }
      }
    }
    return req > 0 ? req : 1;
  }
  function extraParamsFor(cfg, day) {
    if (cfg && Array.isArray(cfg.seasons)) {
      for (var i = 0; i < cfg.seasons.length; i++) {
        var s = cfg.seasons[i];
        if (!s || !Array.isArray(s.periods)) continue;
        for (var j = 0; j < s.periods.length; j++) {
          var p = s.periods[j];
          if (p && p.from && p.to && inRange(day, p.from, p.to)) {
            return {
              fee:
                (s.extra_fee !== null && s.extra_fee !== ""
                  ? Number(s.extra_fee)
                  : Number(cfg.extraFee)) || 0,
              from:
                (s.extra_from !== null && s.extra_from !== ""
                  ? Number(s.extra_from)
                  : Number(cfg.extraFrom)) || 0,
            };
          }
        }
      }
    }
    return {
      fee: Number(cfg && cfg.extraFee) || 0,
      from: Number(cfg && cfg.extraFrom) || 0,
    };
  }

  // ---------- Initialisation d'une section de devis ----------
  function initOne(section) {
    if (!section || section.__pcqInit) return;

    var id = section.id;
    var cfg = JSON.parse(section.getAttribute("data-pc-devis") || "{}");
    var isManual = !!cfg.manualQuote;

    const companyInfoEl = section.querySelector(".exp-devis-company-info");
    const logementTitleEl = section.querySelector(
      ".exp-devis-experience-title"
    );
    const companyInfo = companyInfoEl
      ? JSON.parse(companyInfoEl.textContent || "{}")
      : {};
    const logementTitle = logementTitleEl
      ? logementTitleEl.textContent || "Logement"
      : "Logement";
    let currentTotal = 0;
    let currentLines = [];
    var input = document.getElementById(id + "-dates");
    var adults = document.getElementById(id + "-adults");
    var children = document.getElementById(id + "-children");
    var infants = document.getElementById(id + "-infants");
    var msgBox = document.getElementById(id + "-msg");
    var out = document.getElementById(id + "-result");
    var lines = document.getElementById(id + "-lines");
    var total = document.getElementById(id + "-total");
    var pdfBtn = document.getElementById(id + "-pdf-btn");
    if (isManual && pdfBtn) pdfBtn.style.display = "none";
    if (!input) {
      console.warn("[pc-devis] Élément #" + id + "-dates introuvable");
      return;
    }
    var CAP = Number(cfg.cap || 0);
    var ranges = Array.isArray(cfg.icsDisable)
      ? cfg.icsDisable.filter(function (r) {
          return r && r.from && r.to;
        })
      : [];

    var disableRules = ranges.length
      ? [
          function (date) {
            var s = ymd(date);
            for (var i = 0; i < ranges.length; i++) {
              var r = ranges[i];
              if (s >= r.from && s <= r.to) return true;
            }
            return false;
          },
        ]
      : [];

    // ---- Résoudre une fonction flatpickr fiable (l'attente est gérée par l'orchestrateur) ----
    var FP =
      window.flatpickr && typeof window.flatpickr === "function"
        ? window.flatpickr
        : window.Flatpickr && typeof window.Flatpickr === "function"
        ? window.Flatpickr
        : null;

    if (!FP) {
      console.error(
        "[pc-devis] flatpickr indisponible pour",
        id,
        "(attendu via l'orchestrateur)."
      );
      return;
    }

    // On marque la section comme initialisée uniquement ici
    section.__pcqInit = true;

    var fp = FP(input, {
      mode: "range",
      dateFormat: "d/m/Y",
      altInput: true,
      altFormat: "j M Y",
      minDate: "today",
      locale:
        window.flatpickr && window.flatpickr.l10ns && window.flatpickr.l10ns.fr
          ? "fr"
          : undefined,
      disable: disableRules,

      appendTo: document.body,
      onReady: function (sel, str, inst) {
        try {
          inst.calendarContainer.classList.add("pcq-cal");
        } catch (e) {}

        // NOUVEAU : Déclenche le calcul initial UNIQUEMENT APRÈS
        // que l'instance `inst` (et donc `input._flatpickr`) soit entièrement disponible.
        compute();
      },
      onChange: compute,
      conjunction: " au ",
    });

    // Logs déplacés ici pour éviter le spam pendant les retries
    console.log("[pc-devis] Configuration reçue par le calendrier :", cfg);
    console.log("[pc-devis] Dates à désactiver :", cfg.icsDisable);
    console.log(
      "[pc-devis] flatpickr prêt pour " +
        id +
        " | " +
        (cfg.icsDisable || []).length +
        " plages désactivées."
    );

    console.log(
      "[pc-devis] flatpickr prêt pour",
      id,
      "|",
      ranges.length,
      "plages désactivées."
    );

    function parseIntSafe(el) {
      var v = parseInt(el && el.value, 10);
      return isFinite(v) && v > 0 ? v : 0;
    }

    function clampCapacity(sourceField) {
      if (!CAP || CAP <= 0) return;

      var a = parseIntSafe(adults);
      var c = parseIntSafe(children);
      var i = parseIntSafe(infants);
      var totalGuests = a + c + i;

      if (totalGuests > CAP) {
        if (msgBox) {
          msgBox.textContent =
            "Capacité max : " +
            CAP +
            " personnes (" +
            totalGuests +
            " sélectionnés).";
        }

        var currentVal = 0;
        var inputElement = null;

        if (sourceField === "adults") {
          inputElement = adults;
          currentVal = a;
        } else if (sourceField === "children") {
          inputElement = children;
          currentVal = c;
        } else if (sourceField === "infants") {
          inputElement = infants;
          currentVal = i;
        }

        if (inputElement && currentVal > 0) {
          var reductionNeeded = totalGuests - CAP;
          var newValue = currentVal - reductionNeeded;
          newValue = Math.max(parseInt(inputElement.min) || 0, newValue);
          inputElement.value = newValue;
          totalGuests = CAP;
          if (msgBox)
            msgBox.textContent =
              "Capacité max atteinte : " + CAP + " personnes.";
        }
      } else {
        if (msgBox && msgBox.textContent.startsWith("Capacité max")) {
          msgBox.textContent = "";
        }
      }
    }

    function compute() {
      try {
        var fpi = input._flatpickr; // <-- LECTURE EN PREMIER (NOUVEAU)

        // Ligne 336 : NOUVEAU : Sortie de sécurité immédiate si l'instance n'est pas attachée.
        if (!fpi) {
          return;
        }

        var a = parseIntSafe(adults), // <-- Déclarations déplacées APRES le garde
          c = parseIntSafe(children),
          i = parseIntSafe(infants),
          g = a + c + i;

        // La ligne 341 (ancienne) est maintenant :
        if (fpi.selectedDates.length < 2) {
          if (msgBox) msgBox.textContent = "Choisissez vos dates";
          if (out) out.hidden = !isManual ? true : false;
          if (lines)
            lines.innerHTML = isManual
              ? '<li class="pcq-line"><span>En attente de devis personnalisé</span><span></span></li>'
              : "";
          if (total) total.hidden = true;

          window.currentLogementTotal = 0;
          window.currentLogementLines = [];
          window.currentLogementSelection = null;

          section.dispatchEvent(
            new CustomEvent("devisLogementUpdated", {
              bubbles: true,
              detail: { manual: isManual },
            })
          );
          return;
        }
        var start = fpi.selectedDates[0],
          end = fpi.selectedDates[1];
        if (msgBox) msgBox.textContent = "";
        var nights = [];
        for (var d = new Date(start); d < end; d = addDays(d, 1))
          nights.push(ymd(d));
        var nN = nights.length;
        if (nN <= 0) {
          if (out) out.hidden = true;
          window.currentLogementTotal = 0;
          window.currentLogementLines = [];
          window.currentLogementSelection = null;
          section.dispatchEvent(
            new CustomEvent("devisLogementUpdated", { bubbles: true })
          );
          return;
        }

        var reqMin = requiredMinNights(cfg, nights);
        if (reqMin && nN < reqMin) {
          if (out) out.hidden = true;
          if (msgBox)
            msgBox.textContent =
              "Séjour minimum : " +
              reqMin +
              " nuit" +
              (reqMin > 1 ? "s" : "") +
              ".";
          window.currentLogementTotal = 0;
          window.currentLogementLines = [];
          window.currentLogementSelection = null;
          section.dispatchEvent(
            new CustomEvent("devisLogementUpdated", { bubbles: true })
          );
          return;
        }
        var maxN = Number(cfg.maxNights) || 0;
        if (maxN && nN > maxN) {
          if (out) out.hidden = true;
          if (msgBox)
            msgBox.textContent =
              "Séjour maximum : " +
              maxN +
              " nuit" +
              (maxN > 1 ? "s" : "") +
              ".";
          window.currentLogementTotal = 0;
          window.currentLogementLines = [];
          window.currentLogementSelection = null;
          section.dispatchEvent(
            new CustomEvent("devisLogementUpdated", { bubbles: true })
          );
          return;
        }
        if (msgBox && msgBox.textContent.startsWith("Séjour minimum"))
          msgBox.textContent = "";
        if (msgBox && msgBox.textContent.startsWith("Séjour maximum"))
          msgBox.textContent = "";

        if (isManual) {
          var a = parseIntSafe(adults),
            c = parseIntSafe(children),
            i = parseIntSafe(infants);

          if (lines) {
            lines.innerHTML =
              '<li class="pcq-line"><span>En attente de devis personnalisé</span><span></span></li>';
          }
          if (total) total.hidden = true;
          if (out) out.hidden = false;

          window.currentLogementTotal = 0;
          window.currentLogementLines = [
            { label: "En attente de devis personnalisé", price: "" },
          ];
          window.currentLogementSelection = {
            arrival: ymd(fpi.selectedDates[0]),
            departure: ymd(fpi.selectedDates[1]),
            adults: a,
            children: c,
            infants: i,
          };

          section.dispatchEvent(
            new CustomEvent("devisLogementUpdated", {
              bubbles: true,
              detail: { manual: true },
            })
          );
          return;
        }

        var lodging = 0;
        for (var ni = 0; ni < nN; ni++) {
          lodging += nightPrice(cfg, nights[ni]);
        }
        var extras = 0;
        for (var k = 0; k < nN; k++) {
          var ep = extraParamsFor(cfg, nights[k]);
          if (ep.fee > 0 && ep.from > 0 && g >= ep.from) {
            extras += (g - (ep.from - 1)) * ep.fee;
          }
        }
        var cleaning = Number(cfg.cleaning) || 0;
        var other = Number(cfg.otherFee) || 0;
        var taxe = 0;
        var taxRaw = cfg.taxe_sejour || "";
        if (Array.isArray(taxRaw)) taxRaw = taxRaw[0] != null ? taxRaw[0] : "";
        if (typeof taxRaw === "object" && taxRaw !== null && taxRaw.value) {
          taxRaw = taxRaw.value;
        }
        var taxKey = normKey(taxRaw);
        var isPct5 =
          taxKey &&
          (/\b5\b/.test(taxKey) || taxKey.includes("5")) &&
          (taxKey.includes("%") ||
            taxKey.includes("pourcent") ||
            taxKey.includes("pct"));
        var m = taxKey.match(/([1-5])_?etoile/);
        var stars = m ? parseInt(m[1], 10) : null;
        var classRates = { 1: 0.8, 2: 0.9, 3: 1.5, 4: 2.3, 5: 3.0 };
        if (isPct5 && nN > 0 && g > 0 && a > 0) {
          var A = lodging / nN / g;
          var B = 0.05 * A;
          taxe = B * nN * a;
        } else if (stars && classRates[stars] && a > 0) {
          taxe = classRates[stars] * a * nN;
        }

        var grand = lodging + extras + cleaning + other + taxe;
        currentTotal = grand;
        currentLines = [];
        function dateFR(d) {
          return d.toLocaleDateString("fr-FR", {
            day: "2-digit",
            month: "2-digit",
            year: "numeric",
          });
        }
        currentLines.push({
          label: `Hébergement du ${dateFR(start)} au ${dateFR(
            end
          )} (${nN} nuits)`,
          price: eur(lodging),
        });
        if (extras > 0)
          currentLines.push({
            label: "Invités supplémentaires",
            price: eur(extras),
          });
        if (cleaning > 0)
          currentLines.push({ label: "Frais de ménage", price: eur(cleaning) });
        if (other > 0)
          currentLines.push({
            label: cfg.otherLabel || "Autres frais",
            price: eur(other),
          });
        if (taxe > 0)
          currentLines.push({ label: "Taxe de séjour", price: eur(taxe) });

        if (lines) {
          lines.innerHTML = "";
          currentLines.forEach(function (line) {
            var li = document.createElement("li");
            li.classList.add("pcq-line");
            var s1 = document.createElement("span");
            s1.textContent = line.label;
            var s2 = document.createElement("span");
            s2.textContent = line.price;
            li.appendChild(s1);
            li.appendChild(s2);
            lines.appendChild(li);
          });
        }
        if (total) {
          total.textContent = eur(grand);
          total.hidden = false;
        }
        if (out) out.hidden = false;

        window.currentLogementTotal = grand;
        window.currentLogementLines = currentLines;
        window.currentLogementSelection = {
          arrival: ymd(start),
          departure: ymd(end),
          adults: a,
          children: c,
          infants: i,
        };

        section.dispatchEvent(
          new CustomEvent("devisLogementUpdated", { bubbles: true })
        );
      } catch (e) {
        console.error("[pc-devis] Erreur de calcul:", e);
        if (msgBox) msgBox.textContent = "Erreur lors du calcul.";
        if (out) out.hidden = true;
        window.currentLogementTotal = 0;
        window.currentLogementLines = [];
        window.currentLogementSelection = null;
        section.dispatchEvent(
          new CustomEvent("devisLogementUpdated", { bubbles: true })
        );
      }
    }

    function generatePDF() {
      if (!window.jspdf || !window.jspdf.jsPDF) {
        alert("La librairie PDF n'est pas chargée.");
        return;
      }

      // --- Récupération des lignes + total du devis logement
      // Essaye d'abord les variables globales usuelles, sinon les alias.
      const lines = Array.isArray(window.currentLogementLines)
        ? window.currentLogementLines
        : Array.isArray(window.currentLines)
        ? window.currentLines
        : [];
      const total =
        typeof window.currentLogementTotal === "number"
          ? window.currentLogementTotal
          : typeof window.currentTotal === "number"
          ? window.currentTotal
          : 0;

      if (!lines || !lines.length || total <= 0) {
        alert("Veuillez d'abord effectuer une simulation valide.");
        return;
      }

      const { jsPDF } = window.jspdf;
      const doc = new jsPDF({ unit: "mm", format: "a4" });

      // --- Lecture des données injectées par le shortcode
      function readCompanyInfo() {
        try {
          const el = document.querySelector(".exp-devis-company-info");
          return el ? JSON.parse(el.textContent) : {};
        } catch (e) {
          return {};
        }
      }
      const companyInfo = readCompanyInfo();

      const logementTitle = (
        document.querySelector(".exp-devis-experience-title")?.textContent ||
        document.title ||
        "Logement"
      ).trim();
      const date = new Date().toLocaleDateString("fr-FR");

      // Adultes / Enfants (ton markup utilise name="devis_adults"/"devis_children")
      const parseIntSafe = (v) => {
        const n = parseInt(String(v ?? "").replace(/\D+/g, ""), 10);
        return isNaN(n) ? 0 : n;
      };
      const a = parseIntSafe(
        document.querySelector('[name="devis_adults"]')?.value ?? window.adults
      );
      const c = parseIntSafe(
        document.querySelector('[name="devis_children"]')?.value ??
          window.children
      );

      // --- Pied légal (3 lignes, centré, répété sur chaque page)
      const footerLines = [
        "SAS PRESTIGE CARAÏBES - 166C LOT. LES HAUTS DE JABRUN - 97111 MORNE A L'EAU, Guadeloupe",
        "N°TVA FR74948081351 - SIREN 948 081 351 00017 - RCS 948 081 351 R.C.S. Pointe-a-pitre",
        "Capital de 1 000,00 € - APE 96.09Z",
      ];
      function drawFooter(docInstance) {
        const pageH = docInstance.internal.pageSize.getHeight();
        docInstance.setFontSize(8);
        const yStart = pageH - 14;
        footerLines.forEach((txt, i) => {
          docInstance.text(txt, 105, yStart + i * 4.5, { align: "center" });
        });
      }

      // --- Header : cadre arrondi + logo (40mm) + coordonnées (wrapping)
      function drawHeaderBox() {
        const marginLeft = 12;
        const marginTop = 12;
        const frameX = marginLeft;
        const frameY = marginTop;
        const frameW = 90;
        const radius = 3;

        // Coordonnées (wrapping + shrink si long)
        doc.setFontSize(10);
        const rows = [];
        if (companyInfo.address) rows.push(String(companyInfo.address));
        if (companyInfo.city) rows.push(String(companyInfo.city));
        if (companyInfo.phone) rows.push(String(companyInfo.phone));
        if (companyInfo.email) rows.push(String(companyInfo.email));
        const maxWidth = 90;
        let wrapped = doc.splitTextToSize(rows.join("\n").trim(), maxWidth);
        const textLineH = 4.5;
        const textBlockH = wrapped.length * textLineH;

        // Logo (après le texte pour rester au 1er plan)
        const hasLogo = !!companyInfo.logo_data;
        const logoW = 40;
        const logoH = hasLogo ? 12 : 0; // ratio estimé

        const pad = 5;
        const gapLogoText = hasLogo ? 4 : 0;
        const frameH = pad + logoH + gapLogoText + textBlockH + pad;

        // Cadre
        doc.setDrawColor(180, 180, 180);
        doc.roundedRect(frameX, frameY, frameW, frameH, radius, radius);
        // Petite ombre
        doc.setDrawColor(210, 210, 210);
        doc.line(
          frameX + 1.5,
          frameY + frameH,
          frameX + frameW - 1.5,
          frameY + frameH
        );

        // Logo (x=15,y=14 relatifs : approximés via frameX/frameY)
        if (hasLogo) {
          const logoX = frameX + 3;
          const logoY = frameY + 2;
          doc.addImage(
            companyInfo.logo_data,
            "PNG",
            logoX,
            logoY,
            logoW,
            logoH,
            undefined,
            "NONE"
          );
        }

        // Coordonnées sous le logo
        let textY = frameY + (hasLogo ? 2 + logoH + gapLogoText : 6);
        doc.setFontSize(10);
        wrapped.forEach((line, idx) => {
          doc.text(line, frameX + 4, textY + idx * textLineH);
        });

        return frameY + frameH; // headerBottomY
      }

      const headerBottomY = drawHeaderBox();

      // --- Séparateur + titres
      const sepY = headerBottomY + 6;
      doc.setDrawColor(0, 0, 0);
      doc.line(15, sepY, 195, sepY);

      let yCursor = sepY + 8;
      doc.setFontSize(12);
      doc.text(`Estimation pour : ${logementTitle}`, 15, yCursor);
      doc.text(`Date : ${date}`, 195, yCursor, { align: "right" });

      const gapAfterTitle = 6;
      const peopleY = yCursor + gapAfterTitle;
      doc.setFontSize(10);
      doc.text(`Pour ${a} adulte(s) et ${c} enfant(s)`, 15, peopleY);

      // --- Tableau devis
      let y = peopleY + 12;
      const pageH = doc.internal.pageSize.getHeight();
      const bottomLimit = pageH - 22;

      doc.setFontSize(11);
      doc.text("Description", 15, y); // ← libellé corrigé
      doc.text("Montant", 195, y, { align: "right" });

      // fine rule sous l’en-tête (même style que l’ancien PDF)
      doc.setLineWidth(0.2);
      doc.line(15, y + 2, 195, y + 2);

      y += 6;

      doc.setFontSize(10);
      lines.forEach((line) => {
        const description = (line?.label || line?.name || line?.title || "")
          .toString()
          .trim();
        const price = formatCurrencyPDF(line?.price ?? line?.amount ?? 0);
        const descWrapped = doc.splitTextToSize(description || "—", 150);

        descWrapped.forEach((dLine, i) => {
          if (y > bottomLimit) {
            drawFooter(doc);
            doc.addPage();
            y = 20;
          }
          if (i === 0) {
            doc.text(dLine, 15, y);
            doc.text(price, 195, y, { align: "right" });
          } else {
            doc.text(dLine, 15, y);
          }
          y += 6;
        });
      });

      // fine rule avant le total (comme l’ancien PDF)
      doc.setLineWidth(0.2);
      if (y + 4 > bottomLimit) {
        drawFooter(doc);
        doc.addPage();
        y = 20;
      }
      doc.line(15, y + 2, 195, y + 2);
      y += 8;

      // Total
      if (y + 10 > bottomLimit) {
        drawFooter(doc);
        doc.addPage();
        y = 20;
      }
      // style total : gros + gras
      doc.setFont(undefined, "bold");
      doc.setFontSize(14);
      doc.text("Total Estimé (TTC)", 15, y);
      doc.text(formatCurrencyPDF(total), 195, y, { align: "right" });

      // retour au style normal pour la suite éventuelle
      doc.setFont(undefined, "normal");
      doc.setFontSize(10);
      y += 12;

      // Pied légal page 1
      drawFooter(doc);

      // --- CGV (multi-pages) : priorité au champ Location
      const termsRaw =
        (companyInfo &&
          (companyInfo.cgv_location ||
            companyInfo.cgv_experience ||
            companyInfo.cgv ||
            companyInfo.terms ||
            companyInfo.terms_text ||
            companyInfo.conditions_generales)) ||
        "";

      const cgv = String(termsRaw).trim();
      if (cgv) {
        doc.addPage();
        const left = 15,
          right = 195,
          width = right - left;
        const top = 20,
          lineH = 5;
        doc.setFontSize(12);
        doc.text("Conditions Générales de Location", left, top);
        let yy = top + 8;

        doc.setFontSize(10);
        const paragraphs = cgv.split(/\n{2,}/);
        paragraphs.forEach((p) => {
          const wrapped = doc.splitTextToSize(p, width);
          wrapped.forEach((ln) => {
            if (yy > pageH - 22) {
              drawFooter(doc);
              doc.addPage();
              yy = 20;
            }
            doc.text(ln, left, yy);
            yy += lineH;
          });
          yy += 2;
        });
        drawFooter(doc);
      }

      // Nom de fichier
      const file = `estimation-${(logementTitle || "logement")
        .replace(/[^a-z0-9\u00C0-\u024F]+/gi, "_")
        .toLowerCase()}.pdf`;
      doc.save(file);
    }

    section.querySelectorAll(".exp-stepper-btn").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var input = this.parentElement.querySelector(".pcq-input");
        if (!input) return;

        var stepDirection = this.dataset.step;
        var currentVal = parseInt(input.value, 10) || 0;
        var min = parseInt(input.min, 10);
        var step = 1;
        var inputName = input.getAttribute("name");

        var newVal;
        if (stepDirection === "plus") {
          newVal = currentVal + step;
        } else {
          newVal = currentVal - step;
        }

        if (!isNaN(min) && newVal < min) {
          newVal = min;
        }

        input.value = newVal;

        input.dispatchEvent(new Event("input", { bubbles: true }));
      });
    });

    if (adults)
      adults.addEventListener("input", function () {
        clampCapacity("adults");
        compute();
      });
    if (children)
      children.addEventListener("input", function () {
        clampCapacity("children");
        compute();
      });
    if (infants)
      infants.addEventListener("input", function () {
        clampCapacity("infants");
        compute();
      });
    if (pdfBtn) pdfBtn.addEventListener("click", generatePDF);
  }

  function boot() {
    var sections = document.querySelectorAll(
      ".pc-devis-section[data-pc-devis]"
    );
    if (!sections.length) return;

    // SUPPRESSION du bloc IF synchrone qui provoquait le TypeError.
    // L'initialisation et la gestion des retries sont maintenant entièrement
    // déléguées à la fonction initOne() pour chaque section.

    sections.forEach(initOne);
  }

  // NOUVEAU: Logique d'attente explicite pour flatpickr (fallback autonome)
  function waitForFlatpickr() {
    if (
      window.flatpickr &&
      (typeof window.flatpickr === "function" ||
        typeof window.Flatpickr === "function")
    ) {
      boot();
    } else {
      setTimeout(waitForFlatpickr, 50);
    }
  }

  // Branche orchestrateur : si présent, on lui délègue l'init
  if (
    window.PCOrchestrator &&
    typeof window.PCOrchestrator.registerDevisInit === "function"
  ) {
    window.PCOrchestrator.registerDevisInit(boot);
  } else {
    // Fallback : comportement actuel si l'orchestrateur n'est pas chargé
    waitForFlatpickr();
  }
})();
