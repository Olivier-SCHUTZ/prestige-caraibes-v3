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

    // Éléments du DOM
    const typeSelect = devisWrap.querySelector('select[name="devis_type"]');
    const adultsInput = devisWrap.querySelector('input[name="devis_adults"]');
    const childrenInput = devisWrap.querySelector(
      'input[name="devis_children"]'
    );
    const bebesInput = devisWrap.querySelector('input[name="devis_bebes"]');

    // Nouveaux conteneurs (via le nouveau HTML PHP)
    const optionsContainer =
      devisWrap.querySelector(".exp-options-container") ||
      devisWrap.querySelector(".exp-devis-options");
    const customQtyContainer =
      devisWrap.querySelector(".exp-custom-qty-container") ||
      devisWrap.querySelector(".exp-devis-customqty");
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
    const pendingLabel =
      devisWrap.dataset.labelPending || "En attente de devis";

    // Variables globales
    window.currentTotal = 0;
    window.currentLines = [];
    window.isSurDevis = false;
    window.hasValidSimulation = false;

    // --- 1. GESTION DU STEPPER (+/-) ---
    // Délégation d'événement : gère tous les boutons +/-, même ceux créés dynamiquement
    devisWrap.addEventListener("click", function (e) {
      const btn = e.target.closest(".exp-stepper-btn");
      if (!btn) return;

      const stepper = btn.closest(".exp-stepper");
      const input = stepper.querySelector("input");
      const isPlus = btn.classList.contains("plus");

      let val = parseInt(input.value, 10) || 0;

      if (isPlus) {
        val++;
      } else {
        if (val > 0) val--;
      }

      input.value = val;

      // Active/Désactive le bouton minus
      const minusBtn = stepper.querySelector(".minus");
      if (minusBtn) minusBtn.disabled = val <= 0;

      // Déclenche l'événement 'input' pour lancer le calcul
      input.dispatchEvent(new Event("input", { bubbles: true }));
    });

    // Initialisation des boutons minus au chargement
    devisWrap.querySelectorAll(".exp-stepper input").forEach((input) => {
      const val = parseInt(input.value, 10) || 0;
      const minus = input.parentElement.querySelector(".minus");
      if (minus) minus.disabled = val <= 0;
    });

    // --- 2. UPDATE TEXTES STATIQUES (ADULTE/ENFANT) ---
    function updateStaticInputs(typeKey) {
      const typeConfig = config[typeKey];

      // Nettoyage
      devisWrap
        .querySelectorAll(".exp-item-obs")
        .forEach((el) => (el.textContent = ""));

      const fieldMap = {
        adulte: { inputName: "devis_adults", defaultLabel: "Adultes" },
        enfant: { inputName: "devis_children", defaultLabel: "Enfants" },
        bebe: { inputName: "devis_bebes", defaultLabel: "Bébés" },
      };

      // Reset labels par défaut
      Object.values(fieldMap).forEach((map) => {
        const input = devisWrap.querySelector(`input[name="${map.inputName}"]`);
        if (input) {
          const row = input.closest(".exp-list-item");
          const labelEl = row ? row.querySelector(".exp-item-label") : null;
          if (labelEl) labelEl.innerHTML = map.defaultLabel;
        }
      });

      if (!typeConfig || !Array.isArray(typeConfig.lines)) return;

      // Injection données ACF
      typeConfig.lines.forEach((line) => {
        if (fieldMap[line.type]) {
          const map = fieldMap[line.type];
          const input = devisWrap.querySelector(
            `input[name="${map.inputName}"]`
          );
          if (!input) return;

          const row = input.closest(".exp-list-item");
          const labelEl = row.querySelector(".exp-item-label");
          const obsEl = row.querySelector(".exp-item-obs");

          // Précision (ex: 3-12 ans)
          if (line.precision && labelEl) {
            labelEl.innerHTML = `${map.defaultLabel} <span class="exp-item-precision">(${line.precision})</span>`;
          }
          // Observation (ex: Gratuit)
          if (line.observation && obsEl) {
            obsEl.textContent = line.observation;
          }
        }
      });
    }

    // --- 3. GÉNÉRATION DYNAMIQUE (OPTIONS & CUSTOM QTY) ---
    function updateOptions() {
      const selectedType = typeSelect.value;
      const typeConfig = config[selectedType];

      // A. Génération Quantités Personnalisées
      if (customQtyContainer) {
        customQtyContainer.innerHTML = "";
        if (typeConfig && Array.isArray(typeConfig.lines)) {
          let html = "";
          typeConfig.lines.forEach((ln, idx) => {
            if (ln.type === "personnalise" && ln.enable_qty) {
              const id = `lineqty-${selectedType}-${idx}`;
              // Structure Ligne "Stepper"
              html += `
                        <div class="exp-list-item">
                            <div class="exp-item-info">
                                <span class="exp-item-label">${ln.label}</span>
                                <span class="exp-item-price">${formatCurrency(
                                  ln.price
                                )}</span>
                                ${
                                  ln.observation
                                    ? `<span class="exp-item-obs">${ln.observation}</span>`
                                    : ""
                                }
                            </div>
                            <div class="exp-item-action">
                                <div class="exp-stepper">
                                    <button type="button" class="exp-stepper-btn minus" disabled>−</button>
                                    <input type="number" id="${id}" min="0" value="0" readonly>
                                    <button type="button" class="exp-stepper-btn plus">+</button>
                                </div>
                            </div>
                        </div>`;
            }
          });
          customQtyContainer.innerHTML = html;
        }
      }

      // B. Génération Options
      if (optionsContainer) {
        optionsContainer.innerHTML = "";
        if (typeConfig && typeConfig.options && typeConfig.options.length > 0) {
          let html = '<h4 class="exp-list-title">Options</h4>';

          typeConfig.options.forEach(function (opt, index) {
            const id = `opt-${selectedType}-${index}`;
            const withQty = !!opt.enable_qty;
            const priceTxt = formatCurrency(opt.price);

            if (withQty) {
              // OPTION AVEC QUANTITÉ (Stepper)
              html += `
                    <div class="exp-list-item">
                        <div class="exp-item-info">
                            <label for="${id}" class="exp-item-label">${opt.label}</label>
                            <span class="exp-item-price">+${priceTxt}</span>
                        </div>
                        <div class="exp-item-action">
                            <input type="checkbox" id="${id}" 
                                   data-price="${opt.price}" 
                                   data-label="${opt.label}" 
                                   data-enable-qty="1" 
                                   style="display:none;">
                            
                            <div class="exp-stepper">
                                <button type="button" class="exp-stepper-btn minus" disabled>−</button>
                                <input type="number" class="exp-opt-qty" data-for="${id}" min="0" value="0" readonly>
                                <button type="button" class="exp-stepper-btn plus">+</button>
                            </div>
                        </div>
                    </div>`;
            } else {
              // OPTION SIMPLE (Checkbox)
              html += `
                    <div class="exp-list-item">
                        <div class="exp-item-info">
                            <label for="${id}" class="exp-item-label">${opt.label}</label>
                            <span class="exp-item-price">+${priceTxt}</span>
                        </div>
                        <div class="exp-item-action">
                            <div class="exp-checkbox-wrapper">
                                <input type="checkbox" id="${id}" 
                                       data-price="${opt.price}" 
                                       data-label="${opt.label}" 
                                       data-enable-qty="">
                            </div>
                        </div>
                    </div>`;
            }
          });
          optionsContainer.innerHTML = html;

          // Logique de réattachement pour les options
          optionsContainer
            .querySelectorAll('input[type="checkbox"][data-enable-qty=""]')
            .forEach((cb) => {
              cb.addEventListener("change", calculate);
            });
          optionsContainer
            .querySelectorAll(".exp-opt-qty")
            .forEach((qtyInput) => {
              qtyInput.addEventListener("input", function () {
                const targetId = this.dataset.for;
                const checkbox = document.getElementById(targetId);
                const val = parseInt(this.value) || 0;
                if (checkbox) checkbox.checked = val > 0;
                calculate();
              });
            });
        }
      }

      // C. Écouteurs sur Custom Qty
      if (customQtyContainer) {
        customQtyContainer.querySelectorAll("input").forEach((inp) => {
          inp.addEventListener("input", calculate);
        });
      }
      calculate();
    }

    // Affiche/Masque les compteurs A/E/B si le type ne les utilise pas
    function toggleCountersForType(typeKey) {
      const conf = config[typeKey];
      const show = !!(conf && conf.has_counters);
      const countersBlock = devisWrap.querySelector(".exp-devis-counters");
      if (countersBlock) countersBlock.style.display = show ? "" : "none";
    }

    function calculate() {
      const selectedType = typeSelect.value;
      const typeConfig = config[selectedType];
      if (!typeConfig) return;

      const adults = parseInt(adultsInput.value, 10) || 0;
      const children = parseInt(childrenInput.value, 10) || 0;
      const bebes = parseInt(bebesInput.value, 10) || 0;

      let total = 0;
      let lines = [];
      let resultHTML = '<h4 class="exp-result-title">Résumé du devis</h4><ul>';

      const code =
        typeConfig && typeConfig.code ? typeConfig.code : selectedType;
      const isSurDevis = code === "sur-devis";
      window.isSurDevis = isSurDevis;

      const tarifLines = Array.isArray(typeConfig.lines)
        ? typeConfig.lines
        : [];

      if (tarifLines.length > 0) {
        tarifLines.forEach((ln, idx) => {
          const t = ln.type;
          const unit = Number(ln.price) || 0;
          let qty = 1;

          if (t === "adulte") qty = adults;
          if (t === "enfant") qty = children;
          if (t === "bebe") qty = bebes;

          // Skip si qté 0
          if ((t === "adulte" || t === "enfant" || t === "bebe") && qty <= 0)
            return;

          // Cas Custom Qty
          if (t === "personnalise" && ln.enable_qty) {
            const qtyInput = customQtyContainer
              ? customQtyContainer.querySelector(
                  `#lineqty-${selectedType}-${idx}`
                )
              : null;
            qty = parseInt(qtyInput && qtyInput.value, 10) || 0;
            if (qty <= 0) return;
          }

          if (isSurDevis) {
            const labelQty =
              t === "personnalise" && !ln.enable_qty
                ? ln.label
                : `${qty} × ${ln.label}`;
            lines.push({ label: labelQty, price: pendingLabel });
            resultHTML += `<li><span>${labelQty}</span><span class="exp-price--pending">${pendingLabel}</span></li>`;
          } else {
            if (t === "bebe" && unit === 0) {
              const labelQty = `${qty} × ${ln.label}`;
              lines.push({ label: labelQty, price: "Gratuit" });
              resultHTML += `<li><span>${labelQty}</span><span>Gratuit</span></li>`;
            } else {
              const sub =
                t === "personnalise" && !ln.enable_qty ? unit : qty * unit;
              total += sub;
              const labelQty =
                t === "personnalise" && !ln.enable_qty
                  ? ln.label
                  : `${qty} × ${ln.label}`;
              lines.push({ label: labelQty, price: formatCurrency(sub) });
              resultHTML += `<li><span>${labelQty}</span><span>${formatCurrency(
                sub
              )}</span></li>`;
            }
          }
        });
      }

      // Frais fixes
      const fixedFees = Array.isArray(typeConfig.fixed_fees)
        ? typeConfig.fixed_fees
        : [];
      if (fixedFees.length > 0) {
        lines.push({ label: "Frais fixes", price: "", isSeparator: true });
        resultHTML += `<li class="separator"><strong>Frais fixes</strong></li>`;
        fixedFees.forEach((fee) => {
          const price = Number(fee.price) || 0;
          if (isSurDevis) {
            lines.push({ label: fee.label, price: pendingLabel });
            resultHTML += `<li><span>${fee.label}</span><span class="exp-price--pending">${pendingLabel}</span></li>`;
          } else {
            total += price;
            lines.push({ label: fee.label, price: formatCurrency(price) });
            resultHTML += `<li><span>${fee.label}</span><span>${formatCurrency(
              price
            )}</span></li>`;
          }
        });
      }

      // Options
      if (optionsContainer) {
        const checkedOptions = optionsContainer.querySelectorAll(
          "input[type='checkbox']:checked"
        );
        if (checkedOptions.length) {
          lines.push({ label: "Options", price: "", isSeparator: true });
          resultHTML += `<li class="separator"><strong>Options</strong></li>`;
          checkedOptions.forEach((opt) => {
            const enableQty = opt.dataset.enableQty === "1";
            let q = 1;
            let labelWithQty = opt.dataset.label;
            if (enableQty) {
              const targetId = opt.id;
              const qtyInput = optionsContainer.querySelector(
                `.exp-opt-qty[data-for="${targetId}"]`
              );
              q = parseInt(qtyInput && qtyInput.value, 10) || 1;
              labelWithQty = `${q} × ${opt.dataset.label}`;
            }
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
      }

      if (isSurDevis) {
        resultHTML += `</ul><div class="exp-result-total"><span>Total</span><span class="exp-price--pending">${pendingLabel}</span></div>`;
      } else {
        resultHTML += `</ul><div class="exp-result-total"><span>Total</span><span>${formatCurrency(
          total
        )}</span></div>`;
      }
      if (resultDiv) resultDiv.innerHTML = resultHTML;

      window.currentTotal = isSurDevis ? 0 : total;
      window.currentLines = lines;
      // Validation simple: au moins 1 personne ou 1 item
      const qtyTotal = adults + children + bebes;
      window.hasValidSimulation = isSurDevis
        ? qtyTotal > 0 || lines.length > 0
        : total > 0;
      devisWrap.dispatchEvent(new CustomEvent("devisUpdated"));
    }

    // --- Génération PDF (Inchangée) ---
    function generatePDF() {
      if (!window.jspdf) return alert("Librairie PDF manquante.");
      if (!window.hasValidSimulation)
        return alert("Veuillez effectuer une simulation valide.");

      try {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        const date = new Date().toLocaleDateString("fr-FR");
        const a = parseInt(adultsInput.value, 10) || 0;
        const c = parseInt(childrenInput.value, 10) || 0;

        // Entête
        const headerBottomY = (function drawHeaderBox() {
          const marginLeft = 12;
          const marginTop = 10;
          const radius = 3;
          let boxW = 110;
          const padTop = 4;
          const padBottom = 6;
          const padX = 3;
          const gapLogoText = 4;
          const logoW = 40;
          let logoH = 12;
          if (companyInfo.logo_data) {
            try {
              const props = doc.getImageProperties(companyInfo.logo_data);
              if (props && props.width && props.height)
                logoH = (logoW * props.height) / props.width;
            } catch (e) {}
          }
          const rawLines = [
            (companyInfo.address || "").trim(),
            (companyInfo.city || "").trim(),
            (companyInfo.phone || "").trim(),
            (companyInfo.email || "").trim(),
          ].filter(Boolean);
          doc.setFont("helvetica", "normal");
          doc.setFontSize(10);
          const wrapped = doc.splitTextToSize(
            rawLines.join("\n"),
            boxW - padX * 2
          );
          const textBlockH = Math.max(1, wrapped.length) * 4.2;
          const boxH =
            padTop + Math.max(logoH, gapLogoText + textBlockH) + padBottom;

          doc.setDrawColor(180);
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
          if (companyInfo.logo_data)
            doc.addImage(
              companyInfo.logo_data,
              "PNG",
              marginLeft + padX,
              marginTop + padTop,
              logoW,
              logoH
            );
          doc.text(
            wrapped,
            marginLeft + padX,
            marginTop + padTop + logoH + gapLogoText
          );
          return marginTop + boxH;
        })();

        const sepY = headerBottomY + 6;
        doc.line(15, sepY, 195, sepY);
        let yCursor = sepY + 8;
        doc.setFontSize(12);
        doc.text(`Estimation pour : ${experienceTitle}`, 15, yCursor);
        doc.text(`Date : ${date}`, 195, yCursor, { align: "right" });
        doc.setFontSize(10);
        doc.text(`Pour ${a} adulte(s) et ${c} enfant(s)`, 15, yCursor + 6);

        let y = yCursor + 18;
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
            if (line.price)
              doc.text(formatCurrencyPDF(line.price), 195, y, {
                align: "right",
              });
            y += 7;
          }
        });

        y += 5;
        doc.line(15, y, 195, y);
        y += 8;
        doc.setFontSize(14);
        doc.setFont("helvetica", "bold");
        doc.text("Total Estimé (TTC)", 15, y);
        doc.text(
          formatCurrencyPDF(
            window.isSurDevis ? pendingLabel : window.currentTotal
          ),
          195,
          y,
          { align: "right" }
        );

        // Footer & CGV
        y = 270;
        doc.line(15, y, 195, y);
        doc.setFontSize(8);
        doc.setFont("helvetica", "normal");
        const legalLines = [
          "SAS PRESTIGE CARAÏBES - 166C LOT. LES HAUTS DE JABRUN - 97111 MORNE A L'EAU",
          "N°TVA FR74948081351 - SIREN 948 081 351",
        ];
        legalLines.forEach((ln, i) =>
          doc.text(ln, 105, y + 6 + i * 4, { align: "center" })
        );

        doc.save(
          `estimation-${experienceTitle
            .replace(/[^a-z0-9]/gi, "_")
            .toLowerCase()}.pdf`
        );
      } catch (e) {
        alert("Erreur PDF: " + e.message);
      }
    }

    // Event Listeners principaux
    typeSelect.addEventListener("change", () => {
      const val = typeSelect.value;
      toggleCountersForType(val);
      updateStaticInputs(val);
      updateOptions();
      calculate();
    });
    // On écoute aussi 'input' sur devisWrap (dispatché par les steppers)
    devisWrap.addEventListener("input", calculate);

    if (pdfBtn) pdfBtn.addEventListener("click", generatePDF);

    // Initialisation
    const initVal = typeSelect.value;
    toggleCountersForType(initVal);
    updateStaticInputs(initVal);
    updateOptions();
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

    // NOUVEAU : infos noyau réservation uniquement si plugin actif
    if (window.pcResaCoreActive) {
      formData.append("total", String(window.currentTotal || 0));
      formData.append("lines_json", JSON.stringify(window.currentLines || []));
      formData.append("is_sur_devis", window.isSurDevis ? "1" : "0");

      const typeSelect = document.querySelector('select[name="devis_type"]');
      if (typeSelect) {
        formData.append("devis_type", typeSelect.value || "");
      }

      const dateInput = form.querySelector('input[name="date_experience"]');
      if (dateInput) {
        formData.append("date_experience", dateInput.value || "");
      }

      // ⬇️ NOUVEAU : on pousse aussi les participants du calculateur
      const devisWrap = document.querySelector("[data-exp-devis]");
      if (devisWrap) {
        const adultsInput = devisWrap.querySelector(
          'input[name="devis_adults"]'
        );
        const childrenInput = devisWrap.querySelector(
          'input[name="devis_children"]'
        );
        const bebesInput = devisWrap.querySelector('input[name="devis_bebes"]');

        if (adultsInput) {
          formData.append("devis_adults", adultsInput.value || "0");
        }
        if (childrenInput) {
          formData.append("devis_children", childrenInput.value || "0");
        }
        if (bebesInput) {
          formData.append("devis_bebes", bebesInput.value || "0");
        }
      }
    }

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
