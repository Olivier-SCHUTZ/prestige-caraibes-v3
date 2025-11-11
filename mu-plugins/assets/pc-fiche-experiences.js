// /assets/pc-fiche-experiences.js (Version Finale v4.0 - Refonte Bottom-Sheet)

document.addEventListener("DOMContentLoaded", function () {
  /**
   * ===================================================================
   * FONCTION UTILITAIRE GLOBALE
   * ===================================================================
   */
  function formatCurrency(num) {
    num = Number(num) || 0;
    return new Intl.NumberFormat("fr-FR", {
      style: "currency",
      currency: "EUR",
    }).format(num);
  }

  // Normalisation des montants pour jsPDF (évite les slashs / espaces insécables)
  function formatCurrencyPDF(input) {
    if (typeof input !== "string") {
      // Si on me donne un nombre, je repasse par le formatteur fr-FR
      input = formatCurrency(input);
    }
    return (
      String(input)
        // remplace les slashs accidentels
        .replace(/\//g, " ")
        // remplace les espaces insécables & fines par un espace classique
        .replace(/\u00A0|\u202F/g, " ")
        // compacte les espaces multiples
        .replace(/\s{2,}/g, " ")
        .trim()
    );
  }

  /**
   * ===================================================================
   * PARTIE 1 : Le calculateur de devis [experience_devis]
   * ===================================================================
   * NOTE : Cette partie est quasi-inchangée. Elle fonctionne
   * parfaitement à l'intérieur de la nouvelle bottom-sheet.
   */
  function initExperienceDevis(devisWrap) {
    const config = JSON.parse(devisWrap.dataset.expDevis || "{}");
    const typeSelect = devisWrap.querySelector('select[name="devis_type"]');
    const adultsInput = devisWrap.querySelector('input[name="devis_adults"]');
    const childrenInput = devisWrap.querySelector(
      'input[name="devis_children"]'
    );
    const bebesInput = devisWrap.querySelector('input[name="devis_bebes"]');
    const optionsDiv = devisWrap.querySelector(".exp-devis-options");
    const customQtyDiv = devisWrap.querySelector(".exp-devis-customqty");
    const resultDiv = devisWrap.querySelector(".exp-devis-result");
    const pdfBtn = devisWrap.querySelector('[id$="-pdf-btn"]');
    const companyInfoEl = devisWrap.querySelector(".exp-devis-company-info");
    const experienceTitleEl = devisWrap.querySelector(
      ".exp-devis-experience-title"
    );

    const companyInfo = companyInfoEl
      ? JSON.parse(companyInfoEl.textContent || "{}")
      : {};
    const experienceTitle = experienceTitleEl
      ? experienceTitleEl.textContent || ""
      : "";
    // --- NEW: masque/affiche les compteurs selon has_counters ---
    function toggleCountersForType(typeKey) {
      const conf = config[typeKey];
      const show = !!(conf && conf.has_counters);
      const countersBlock = devisWrap.querySelector(".exp-devis-counters"); // wrapper PHP
      if (countersBlock) countersBlock.style.display = show ? "" : "none";
    }

    // Variables globales pour le partage entre modules
    window.currentTotal = 0;
    window.currentLines = [];
    window.isSurDevis = false;
    window.hasValidSimulation = false;
    const pendingLabel =
      devisWrap.dataset.labelPending || "En attente de devis";

    function calculate() {
      const selectedType = typeSelect.value;
      const typeConfig = config[selectedType];
      if (!typeConfig) return;

      const adults = parseInt(adultsInput.value, 10) || 0;
      const children = parseInt(childrenInput.value, 10) || 0;
      const bebes = parseInt(bebesInput.value, 10) || 0;

      let total = 0;
      let lines = [];
      let hasError = false;
      let resultHTML = '<h4 class="exp-result-title">Résumé du devis</h4><ul>';

      // NEW: logique robuste basée sur le code interne
      const code =
        typeConfig && typeConfig.code ? typeConfig.code : selectedType;
      const isSurDevis = code === "sur-devis";
      window.isSurDevis = isSurDevis;

      // NEW: lignes tarifaires structurées (adulte/enfant/bebe/personnalise)
      const tarifLines = Array.isArray(typeConfig.lines)
        ? typeConfig.lines
        : [];

      if (tarifLines.length > 0) {
        tarifLines.forEach((ln, idx) => {
          const t = ln.type;
          const unit = Number(ln.price) || 0;

          // Quantités selon le type
          let qty = 1;
          if (t === "adulte") qty = parseInt(adultsInput.value, 10) || 0;
          if (t === "enfant") qty = parseInt(childrenInput.value, 10) || 0;
          if (t === "bebe") qty = parseInt(bebesInput.value, 10) || 0;

          // Si pas de quantité pour A/E/B, rien à ajouter/afficher
          if ((t === "adulte" || t === "enfant" || t === "bebe") && qty <= 0) {
            if (ln.observation) {
              resultHTML += `<li class="note"><em>${ln.observation}</em></li>`;
            }
            return;
          }

          // Sur devis : pas de somme, affichage "pending"
          if (isSurDevis) {
            let labelQty;
            if (t === "personnalise") {
              const qtyInput = customQtyDiv
                ? customQtyDiv.querySelector(`#lineqty-${selectedType}-${idx}`)
                : null;
              const q = ln.enable_qty
                ? parseInt(qtyInput && qtyInput.value, 10) || 0
                : 1;
              labelQty = ln.enable_qty ? `${q} ${ln.label}` : ln.label;
            } else {
              labelQty = `${qty} ${ln.label}`;
            }
            lines.push({ label: labelQty, price: pendingLabel });
            resultHTML += `<li><span>${labelQty}</span><span class="exp-price--pending">${pendingLabel}</span></li>`;
            if (ln.observation) {
              resultHTML += `<li class="note"><em>${ln.observation}</em></li>`;
            }
            return; // pas de cumul
          }

          // Cas standard (prix)
          if (t === "bebe" && unit === 0) {
            const labelQty = `${qty} ${ln.label}`;
            lines.push({ label: labelQty, price: "Gratuit" });
            resultHTML += `<li><span>${labelQty}</span><span>Gratuit</span></li>`;
          } else if (t === "personnalise") {
            if (ln.enable_qty) {
              const qtyInput = customQtyDiv
                ? customQtyDiv.querySelector(`#lineqty-${selectedType}-${idx}`)
                : null;
              const q = parseInt(qtyInput && qtyInput.value, 10) || 0;
              const sub = q * unit;
              total += sub;
              const labelQty = `${q} ${ln.label}`;
              lines.push({ label: labelQty, price: formatCurrency(sub) });
              resultHTML += `<li><span>${labelQty}</span><span>${formatCurrency(
                sub
              )}</span></li>`;
            } else {
              total += unit; // forfait 1x
              lines.push({ label: ln.label, price: formatCurrency(unit) });
              resultHTML += `<li><span>${ln.label}</span><span>${formatCurrency(
                unit
              )}</span></li>`;
            }
          } else {
            const sub = qty * unit;
            total += sub;
            const labelQty = `${qty} ${ln.label}`;
            lines.push({ label: labelQty, price: formatCurrency(sub) });
            resultHTML += `<li><span>${labelQty}</span><span>${formatCurrency(
              sub
            )}</span></li>`;
          }

          if (ln.observation) {
            resultHTML += `<li class="note"><em>${ln.observation}</em></li>`;
          }
        });
      } else {
        // (fallback rare si pas migré) — laisser vide
      }

      // Options cochées
      const checkedOptions = optionsDiv.querySelectorAll("input:checked");
      if (checkedOptions.length) {
        lines.push({ label: "Options", price: "", isSeparator: true });
        resultHTML += `<li class="separator"><strong>Options</strong></li>`;
        checkedOptions.forEach((opt) => {
          const enableQty = opt.dataset.enableQty === "1";
          let q = 1;
          if (enableQty) {
            const qtyInput = optionsDiv.querySelector(
              `.exp-opt-qty[data-for="${opt.id}"]`
            );
            q = parseInt(qtyInput && qtyInput.value, 10) || 1;
          }
          const labelWithQty = enableQty
            ? `${opt.dataset.label} × ${q}`
            : opt.dataset.label;

          if (isSurDevis) {
            lines.push({ label: labelWithQty, price: pendingLabel });
            resultHTML += `<li><span>${labelWithQty}</span><span class="exp-price--pending">${pendingLabel}</span></li>`;
          } else {
            const unit = parseFloat(opt.dataset.price) || 0;
            const sub = unit * q;
            total += sub;
            lines.push({ label: labelWithQty, price: formatCurrency(sub) });
            resultHTML += `<li><span>${labelWithQty}</span><span>${formatCurrency(
              sub
            )}</span></li>`;
          }
        });
      }

      // Total
      if (isSurDevis) {
        resultHTML += `</ul><div class="exp-result-total"><span>Total</span><span class="exp-price--pending">${pendingLabel}</span></div>`;
      } else {
        resultHTML += `</ul><div class="exp-result-total"><span>Total</span><span>${formatCurrency(
          total
        )}</span></div>`;
      }

      if (resultDiv) resultDiv.innerHTML = resultHTML;

      // Expose pour le FAB et la modale de contact
      window.currentTotal = isSurDevis ? 0 : total;
      window.currentLines = lines;

      const qty = adults + children + bebes;
      window.hasValidSimulation = isSurDevis
        ? qty > 0 && !hasError
        : total > 0 && !hasError;

      // Trigger pour mettre à jour le FAB et la modale de contact
      devisWrap.dispatchEvent(new CustomEvent("devisUpdated"));
    }

    function updateOptions() {
      const selectedType = typeSelect.value;
      const typeConfig = config[selectedType];
      if (optionsDiv) {
        optionsDiv.innerHTML = "";
        if (typeConfig && typeConfig.options && typeConfig.options.length > 0) {
          let optionsHTML =
            '<h4 class="exp-options-title">Options disponibles</h4>';
          typeConfig.options.forEach(function (opt, index) {
            const id = `opt-${selectedType}-${index}`;
            const withQty = !!opt.enable_qty;
            optionsHTML += `<div class="exp-devis-checkbox">
    <input type="checkbox" id="${id}"
           data-price="${opt.price}"
           data-label="${opt.label}"
           data-enable-qty="${withQty ? "1" : ""}">
    <label for="${id}">${opt.label} (+${formatCurrency(opt.price)})</label>
    ${
      withQty
        ? `<input type="number" class="exp-opt-qty" data-for="${id}" min="1" value="1" style="width:80px;margin-left:8px;">`
        : ""
    }
  </div>`;
          });
          optionsDiv.innerHTML = optionsHTML;
          optionsDiv
            .querySelectorAll('input[type="checkbox"]')
            .forEach((cb) => {
              cb.addEventListener("change", calculate);
            });
          optionsDiv.querySelectorAll(".exp-opt-qty").forEach((qty) => {
            qty.addEventListener("input", calculate);
          });
        }
      }
      // (NOUVEAU) Champs de quantité pour lignes "personnalise" avec enable_qty
      if (customQtyDiv) {
        customQtyDiv.innerHTML = "";
        if (typeConfig && Array.isArray(typeConfig.lines)) {
          let html = "";
          typeConfig.lines.forEach((ln, idx) => {
            if (ln.type === "personnalise" && ln.enable_qty) {
              const id = `lineqty-${selectedType}-${idx}`;
              html += `<div class="exp-devis-field">
          <label for="${id}">${ln.label}</label>
          <input type="number" id="${id}" min="0" value="0" />
        </div>`;
            }
          });
          if (html) {
            customQtyDiv.innerHTML = `<h4 class="exp-options-title">Quantités personnalisées</h4>${html}`;
            customQtyDiv
              .querySelectorAll("input")
              .forEach((inp) => inp.addEventListener("input", calculate));
          }
        }
      }
      calculate(); // Recalculer après la mise à jour des options (au cas où le type change)
    }

    function generatePDF() {
      if (!window.jspdf) {
        return alert("La librairie PDF n'est pas chargée.");
      }
      if (!window.hasValidSimulation) {
        return alert("Veuillez d'abord effectuer une simulation valide.");
      }

      try {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        const date = new Date().toLocaleDateString("fr-FR");
        const a = parseInt(adultsInput.value, 10) || 0;
        const c = parseInt(childrenInput.value, 10) || 0;

        // --- Bloc en-tête (logo + adresse + contact) -> renvoie le Y bas du bloc
        const headerBottomY = (function drawHeaderBox() {
          const marginLeft = 12; // X du cadre
          const marginTop = 10; // Y du cadre
          const radius = 3;
          let boxW = 110; // élargi pour respirer
          const padTop = 4;
          const padBottom = 6;
          const padX = 3;
          const gapLogoText = 4; // espace entre bas du logo et texte
          const logoW = 40; // largeur affichée du logo (mm)

          // --- Mesure du logo (hauteur réelle à partir des props de l'image)
          let logoH = 12; // fallback
          if (companyInfo.logo_data) {
            try {
              const props = doc.getImageProperties(companyInfo.logo_data);
              if (props && props.width && props.height) {
                logoH = (logoW * props.height) / props.width; // en mm
              }
            } catch (e) {
              /* fallback 12mm */
            }
          }

          // --- Prépare le texte (wrapping + éventuelle réduction à 9pt)
          const PT2MM = 0.3527778;
          let bodyFS = 10;
          const lineH = 1.2;

          const rawLines = [
            (companyInfo.address || "").trim(),
            (companyInfo.city || "").trim(),
            (companyInfo.phone || "+590 690 63 11 81").trim(),
            (companyInfo.email || "guadeloupe@prestigecaraibes.com").trim(),
          ].filter(Boolean);

          const maxTextWidth = boxW - padX * 2;
          doc.setFont("helvetica", "normal");
          doc.setFontSize(bodyFS);

          let wrapped = doc.splitTextToSize(rawLines.join("\n"), maxTextWidth);
          if (wrapped.length > 6) {
            bodyFS = 9;
            doc.setFontSize(bodyFS);
            wrapped = doc.splitTextToSize(rawLines.join("\n"), maxTextWidth);
          }

          const lineHMM = bodyFS * PT2MM * lineH;
          const textBlockH = Math.max(1, wrapped.length) * lineHMM;

          // --- Hauteur finale du cadre : padding + max(logo, texte)
          const innerH = Math.max(logoH, gapLogoText + textBlockH);
          const boxH = padTop + innerH + padBottom;

          // --- Cadre (fond + bordure + ombre)
          doc.setDrawColor(180);
          doc.setLineWidth(0.25);
          doc.setFillColor(255, 255, 255);
          doc.roundedRect(
            marginLeft,
            marginTop,
            boxW,
            boxH,
            radius,
            radius,
            "FD"
          );

          doc.setDrawColor(220);
          doc.setLineWidth(0.1);
          doc.line(
            marginLeft + 1,
            marginTop + boxH + 1,
            marginLeft + boxW + 1,
            marginTop + boxH + 1
          );

          // --- Logo
          if (companyInfo.logo_data) {
            try {
              const x = marginLeft + padX;
              const y = marginTop + padTop;
              doc.addImage(
                companyInfo.logo_data,
                "PNG",
                x,
                y,
                logoW,
                0,
                undefined,
                "NONE"
              );
            } catch (e) {
              console.warn("Logo non chargé :", e);
            }
          }

          // --- Texte (sous le logo mesuré)
          const textX = marginLeft + padX;
          const textY = marginTop + padTop + logoH + gapLogoText;
          doc.text(wrapped, textX, textY, { align: "left" });

          return marginTop + boxH; // Y bas du cadre
        })();

        const gapAfterHeader = 6; // mm d'air sous le cadre
        const sepY = headerBottomY + gapAfterHeader;

        doc.line(15, sepY, 195, sepY);

        // Contenu (titre + date) positionné dynamiquement
        let yCursor = sepY + 8;
        doc.setFontSize(12);
        // Ligne de titre
        doc.text(`Estimation pour : ${experienceTitle}`, 15, yCursor);
        doc.text(`Date : ${date}`, 195, yCursor, { align: "right" });

        // Espace supplémentaire avant la ligne "Pour..."
        const gapAfterTitle = 6; // ajoute ~6 mm d’air
        const peopleY = yCursor + gapAfterTitle;

        doc.setFontSize(10);
        doc.text(`Pour ${a} adulte(s) et ${c} enfant(s)`, 15, peopleY);

        // Et on aligne le bloc suivant juste en dessous
        let y = peopleY + 12;
        doc.setFont("helvetica", "bold");
        doc.text("Description", 15, y);
        doc.text("Montant", 195, y, { align: "right" });
        doc.line(15, y + 2, 195, y + 2);
        y += 8;

        window.currentLines.forEach((line) => {
          if (line.isError) return;
          if (line.isSeparator) {
            y += 3;
            doc.setFont("helvetica", "bold");
            doc.text(line.label, 15, y);
            y += 7;
          } else {
            doc.setFont("helvetica", "normal");
            doc.text(line.label, 15, y);
            if (line.price) {
              doc.text(formatCurrencyPDF(line.price), 195, y, {
                align: "right",
              });
            }
            y += 7;
          }
        });

        y += 5;
        doc.line(15, y, 195, y);
        y += 8;
        doc.setFontSize(14);
        doc.setFont("helvetica", "bold");
        doc.text("Total Estimé (TTC)", 15, y);

        // Gère le cas "Sur devis" dans le PDF
        const totalText = window.isSurDevis
          ? pendingLabel
          : formatCurrency(window.currentTotal);
        doc.text(formatCurrencyPDF(window.currentTotal), 195, y, {
          align: "right",
        });

        y = 270;
        doc.line(15, y, 195, y);

        doc.setFontSize(8);
        const legalLines = [
          "SAS PRESTIGE CARAÏBES - 166C LOT. LES HAUTS DE JABRUN - 97111 MORNE A L'EAU, Guadeloupe",
          "N°TVA FR74948081351 - SIREN 948 081 351 00017 - RCS 948 081 351 R.C.S. Pointe-a-pitre",
          "Capital de 1 000,00 € - APE 96.09Z",
        ];

        // Ecrit les 3 lignes en bas à gauche
        let fy = y + 6;
        const pageWidth = doc.internal.pageSize.getWidth();
        legalLines.forEach(function (ln, i) {
          doc.text(ln, pageWidth / 2, fy + i * 5.5, { align: "center" });
        });

        // --- CGV depuis ACF (companyInfo.*) : ajoute des pages après la page devis ---
        (function appendTermsAndPaginate() {
          const termsRaw =
            (companyInfo &&
              (companyInfo.cgv_experience ||
                companyInfo.cgv ||
                companyInfo.terms ||
                companyInfo.terms_text ||
                companyInfo.conditions_generales)) ||
            "";

          if (!termsRaw || typeof termsRaw !== "string") return;

          // --- Paramètres généraux ---
          const marginLeft = 15;
          const marginRight = 15;
          const contentWidth =
            doc.internal.pageSize.getWidth() - marginLeft - marginRight;
          const pageHeight = doc.internal.pageSize.getHeight();
          const bodyFontSize = 10;
          const lineHeight = 1.2;
          const topY = 20; // départ du texte (on commence plus haut car plus d'entête)
          const bottomMargin = 40; // un peu plus pour ne jamais toucher le pied

          // --- Nettoyage du texte ---
          const cleaned = termsRaw
            .replace(/\r\n/g, "\n")
            .replace(/\n{3,}/g, "\n\n");

          // --- Calcul du nombre de lignes par page ---
          doc.setFont("helvetica", "normal");
          doc.setFontSize(bodyFontSize);
          const allLines = doc.splitTextToSize(cleaned, contentWidth);

          const PT_TO_MM = 0.3527777778;
          const lineHeightMM = bodyFontSize * lineHeight * PT_TO_MM;
          const usableHeight = pageHeight - topY - bottomMargin;
          const linesPerPage = Math.max(
            1,
            Math.floor(usableHeight / lineHeightMM)
          );

          // --- Pagination et rendu ---
          for (let start = 0; start < allLines.length; start += linesPerPage) {
            const chunk = allLines.slice(start, start + linesPerPage);
            doc.addPage();

            // (Plus d'entête ici — supprimé à ta demande)
            doc.setFont("helvetica", "normal");
            doc.setFontSize(bodyFontSize);
            doc.text(chunk, marginLeft, topY, {
              maxWidth: contentWidth,
              align: "left",
              lineHeightFactor: lineHeight,
            });

            // --- Pied légal centré ---
            const fy = pageHeight - 36;
            doc.setFontSize(8);
            const pageWidth = doc.internal.pageSize.getWidth();
            legalLines.forEach(function (ln, i) {
              doc.text(ln, pageWidth / 2, fy + i * 5.5, { align: "center" });
            });
          }
        })();

        // --- Sauvegarde du PDF ---
        doc.save(
          `estimation-${experienceTitle
            .replace(/[^a-z0-9]/gi, "_")
            .toLowerCase()}.pdf`
        );
      } catch (e) {
        console.error("Erreur détaillée lors de la création du PDF:", e);
        alert("Une erreur est survenue lors de la génération du PDF.");
      }
    }

    // Écouteurs d'événements pour le calcul
    [typeSelect, adultsInput, childrenInput, bebesInput].forEach((el) =>
      el.addEventListener("input", calculate)
    );
    typeSelect.addEventListener("change", () => {
      toggleCountersForType(typeSelect.value);
      updateOptions();
      calculate();
    });
    if (pdfBtn) pdfBtn.addEventListener("click", generatePDF);

    // Initialisation
    updateOptions();
    toggleCountersForType(typeSelect.value);
  }

  // Active le calculateur
  document
    .querySelectorAll(".exp-devis-wrap[data-exp-devis]")
    .forEach(initExperienceDevis);

  /**
   * ===================================================================
   * PARTIE 2 : La Bottom-Sheet, le FAB et la Modale de Contact (Refonte v4.0)
   * ===================================================================
   */

  // --- 1. Sélection des éléments ---

  // Calculateur (déjà initialisé, on a juste besoin de la référence)
  const devisWrap = document.querySelector(".exp-devis-wrap[data-exp-devis]");

  // Bouton Flottant (FAB)
  const fab = document.getElementById("exp-open-devis-sheet-btn");
  const fabPriceDisplay = document.getElementById("fab-price-display");

  // Bottom-Sheet (Panneau Devis)
  const devisSheet = document.getElementById("exp-devis-sheet");
  const closeSheetTriggers = document.querySelectorAll(
    "[data-close-devis-sheet]"
  );
  const openContactModalBtn = document.getElementById(
    "exp-open-modal-btn-local"
  ); // Bouton "Réserver" DANS la sheet

  // Modale de Contact (finale)
  const contactModal = document.getElementById("exp-booking-modal");
  const modalSummaryContainer = contactModal.querySelector(
    ".exp-booking-fieldset:first-of-type"
  );
  const modalSummaryContent = document.getElementById("modal-quote-summary");
  const modalHiddenDetails = document.getElementById(
    "modal-quote-details-hidden"
  );
  const closeContactModalTriggers =
    contactModal.querySelectorAll("[data-close-modal]");
  const form = document.getElementById("experience-booking-form");
  const devisErrorMsg = document.getElementById("exp-devis-error-msg"); // Message d'erreur DANS la sheet

  // Vérification minimale
  if (
    !devisWrap ||
    !fab ||
    !devisSheet ||
    !contactModal ||
    !openContactModalBtn
  ) {
    console.warn(
      "Certains éléments de réservation sont manquants. Le module de réservation est désactivé."
    );
    return;
  }

  const pendingLabel = devisWrap.dataset.labelPending || "En attente de devis";
  const defaultFabText = fabPriceDisplay.textContent || "Simuler un devis";

  // --- 2. Logique d'apparition du FAB ---

  function showFab() {
    if (!fab) return;
    fab.classList.add("is-visible");
  }

  // A-t-on déjà ouvert la sheet dans cette session ?
  if (sessionStorage.getItem("devisSheetOpened")) {
    showFab();
  } else {
    // Option 1: Afficher après 2 secondes
    const timer = setTimeout(showFab, 2000);

    // Option 2: Afficher après scroll de 30%
    const scrollThreshold = window.innerHeight * 0.3;
    function checkScroll() {
      if (window.scrollY > scrollThreshold) {
        showFab();
        clearTimeout(timer); // Annule le timer si le scroll a suffi
        window.removeEventListener("scroll", checkScroll); // N'écoute qu'une fois
      }
    }
    window.addEventListener("scroll", checkScroll, { passive: true });
  }

  // --- 3. Logique d'ouverture/fermeture des panneaux ---

  function openDevisSheet() {
    if (!devisSheet) return;
    if (fabPriceDisplay && fabPriceDisplay.textContent === "Merci ! 🌴") {
      fabPriceDisplay.textContent = defaultFabText;
    }
    devisSheet.setAttribute("aria-hidden", "false");
    devisSheet.classList.add("is-open");
    document.body.style.overflow = "hidden"; // Verrouille le scroll
    sessionStorage.setItem("devisSheetOpened", "true"); // Mémorise l'ouverture
    // Focus sur le premier élément interactif (le sélecteur de type)
    devisWrap.querySelector('select[name="devis_type"]').focus();
  }

  function closeDevisSheet() {
    if (!devisSheet) return;
    devisSheet.setAttribute("aria-hidden", "true");
    devisSheet.classList.remove("is-open");
    document.body.style.overflow = ""; // Déverrouille le scroll
  }

  function openContactModal() {
    if (!contactModal) return;
    contactModal.setAttribute("aria-hidden", "false");
    contactModal.classList.remove("is-hidden");
    document.body.style.overflow = "hidden"; // Garde le scroll verrouillé
    // Focus sur le premier champ
    contactModal.querySelector('input[name="prenom"]').focus();
  }

  function closeContactModal() {
    if (!contactModal) return;
    contactModal.setAttribute("aria-hidden", "true");
    contactModal.classList.add("is-hidden");
    document.body.style.overflow = ""; // Déverrouille le scroll

    // Gère la réinitialisation du formulaire après succès
    const successMessage = form.parentNode.querySelector(
      ".form-success-message"
    );
    if (successMessage) {
      successMessage.remove();
      form.style.display = "block";
    }
  }

  // --- 4. Le "Pont" : Gérer la demande de réservation ---

  function handleBookingRequest() {
    // Vérifie si une simulation valide a été faite (logique de `calculate()`)
    const canOpen = !!window.hasValidSimulation;

    if (document.activeElement) document.activeElement.blur();

    if (canOpen) {
      devisErrorMsg.classList.remove("is-visible");
      updateBookingInfo(true); // Force la mise à jour des infos de la modale contact
      closeDevisSheet();
      openContactModal();
    } else {
      // Affiche l'erreur DANS la sheet
      devisErrorMsg.textContent =
        "Merci de remplir les champs (Adultes, Enfants, Bébés) pour faire une simulation avant de demander une réservation.";
      devisErrorMsg.classList.add("is-visible");

      // Fait vibrer le calculateur pour attirer l'attention
      devisWrap.style.transition = "transform 0.1s ease-in-out";
      devisWrap.style.transform = "translateX(-10px)";
      setTimeout(() => {
        devisWrap.style.transform = "translateX(10px)";
        setTimeout(() => {
          devisWrap.style.transform = "translateX(0px)";
        }, 100);
      }, 100);
    }
  }

  // --- 5. Mise à jour des infos (FAB + Modale Contact) ---

  function updateBookingInfo(isOpeningContactModal = false) {
    const showPending =
      window.isSurDevis === true && window.hasValidSimulation === true;
    const showPriced =
      typeof window.currentTotal !== "undefined" && window.currentTotal > 0;

    // A. Mise à jour du FAB
    if (showPending) {
      // NOUVEAU : Texte plus clair pour les devis "sur demande"
      fabPriceDisplay.textContent = "Réserver (" + pendingLabel + ")";
    } else if (showPriced) {
      // NOUVEAU : Texte "Réserver pour : [PRIX]" comme demandé
      fabPriceDisplay.textContent =
        "Réserver pour : " + formatCurrency(window.currentTotal);
    } else {
      fabPriceDisplay.textContent = defaultFabText;
    }

    // B. Mise à jour de la Modale de Contact (seulement si nécessaire)
    if (isOpeningContactModal && (showPending || showPriced)) {
      let summaryHTML = "<ul>";
      let detailsText = "";
      (window.currentLines || []).forEach((line) => {
        if (line.isError) return;
        if (line.isSeparator) {
          summaryHTML += `<li class="separator"><strong>${line.label}</strong></li>`;
          detailsText += `\n--- ${line.label} ---\n`;
        } else {
          const priceTxt = showPending ? pendingLabel : line.price;
          summaryHTML += `<li><span>${line.label}</span><span>${priceTxt}</span></li>`;
          detailsText += `${line.label}: ${priceTxt}\n`;
        }
      });
      summaryHTML += "</ul>";
      detailsText += `\nTotal: ${
        showPending ? pendingLabel : fabPriceDisplay.textContent
      }`;

      modalSummaryContainer.style.display = "block";
      modalSummaryContent.innerHTML = summaryHTML;
      modalHiddenDetails.value = detailsText;
    } else if (isOpeningContactModal) {
      // Cas où on clique sur "Réserver" sans simulation valide (normalement bloqué par handleBookingRequest)
      modalSummaryContainer.style.display = "none";
      modalSummaryContent.innerHTML = "";
      modalHiddenDetails.value =
        "Aucune simulation de devis n'a été effectuée.";
    }
  }

  // --- 6. Logique d'envoi du formulaire (Inchangée) ---

  form.addEventListener("submit", function (event) {
    event.preventDefault();
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.textContent;
    submitBtn.textContent = "Envoi en cours...";
    submitBtn.disabled = true;
    const formData = new FormData(form);

    fetch(form.getAttribute("action"), { method: "POST", body: formData })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          form.style.display = "none";
          if (fabPriceDisplay) fabPriceDisplay.textContent = "Merci ! 🌴";
          const successMessage = document.createElement("div");
          successMessage.className = "form-success-message";
          successMessage.innerHTML = `<h4>Merci ${formData.get(
            "prenom"
          )} !</h4><p>${
            data.data.message
          }</p><button type="button" class="pc-btn pc-btn--secondary" data-close-modal>Fermer</button>`;
          form.insertAdjacentElement("afterend", successMessage);
          // Utilise la fonction de fermeture de la modale de contact
          successMessage
            .querySelector("[data-close-modal]")
            .addEventListener("click", closeContactModal);
        } else {
          alert("Erreur : " + (data.data.message || "Veuillez réessayer."));
          submitBtn.textContent = originalBtnText;
          submitBtn.disabled = false;
        }
      })
      .catch((error) => {
        console.error("Erreur:", error);
        alert("Une erreur technique est survenue.");
        submitBtn.textContent = originalBtnText;
        submitBtn.disabled = false;
      });
  });

  // --- 7. Écouteurs d'événements centraux ---

  // Ouvre la Sheet (Devis)
  fab.addEventListener("click", openDevisSheet);

  // Ferme la Sheet (Devis)
  closeSheetTriggers.forEach((trigger) =>
    trigger.addEventListener("click", closeDevisSheet)
  );

  // Ouvre la Modale (Contact) depuis la Sheet
  openContactModalBtn.addEventListener("click", handleBookingRequest);

  // Ferme la Modale (Contact)
  closeContactModalTriggers.forEach((trigger) =>
    trigger.addEventListener("click", closeContactModal)
  );

  // Met à jour le FAB quand le devis change
  devisWrap.addEventListener("devisUpdated", () => updateBookingInfo(false));

  // Gère la touche "Echap"
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      if (!contactModal.classList.contains("is-hidden")) {
        closeContactModal(); // Ferme la modale de contact en priorité
      } else if (devisSheet.classList.contains("is-open")) {
        closeDevisSheet(); // Sinon, ferme la sheet de devis
      }
    }
  });
});
