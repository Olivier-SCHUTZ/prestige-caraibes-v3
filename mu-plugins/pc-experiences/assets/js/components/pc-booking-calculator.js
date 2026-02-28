/**
 * Composant : Calculateur de Devis
 * Fichier : assets/js/components/pc-booking-calculator.js
 * Rôle : Gère l'interaction, le calcul et l'affichage dynamique du devis.
 */
document.addEventListener("DOMContentLoaded", function () {
  class PCBookingCalculator {
    constructor(devisWrap) {
      this.devisWrap = devisWrap;
      this.config = JSON.parse(devisWrap.dataset.expDevis || "{}");

      // Éléments du DOM
      this.typeSelect = devisWrap.querySelector('select[name="devis_type"]');
      this.totalAdultsInput = devisWrap.querySelector(
        'input[name="devis_adults"]',
      );
      this.totalChildrenInput = devisWrap.querySelector(
        'input[name="devis_children"]',
      );
      this.totalBebesInput = devisWrap.querySelector(
        'input[name="devis_bebes"]',
      );
      this.dynamicContainer =
        devisWrap.querySelector(".exp-dynamic-lines") ||
        devisWrap.querySelector(".exp-devis-counters");
      this.optionsContainer =
        devisWrap.querySelector(".exp-options-container") ||
        devisWrap.querySelector(".exp-devis-options");
      this.resultDiv = devisWrap.querySelector(".exp-devis-result");
      this.pdfBtn = devisWrap.querySelector('[id$="-pdf-btn"]');

      // Infos PDF
      const companyInfoEl = devisWrap.querySelector(".exp-devis-company-info");
      const experienceTitleEl = devisWrap.querySelector(
        ".exp-devis-experience-title",
      );
      this.companyInfo = companyInfoEl
        ? JSON.parse(companyInfoEl.textContent || "{}")
        : {};
      this.experienceTitle = experienceTitleEl
        ? experienceTitleEl.textContent || ""
        : "";
      this.pendingLabel =
        devisWrap.dataset.labelPending || "En attente de devis";

      // Variables globales conservées temporairement pour la compatibilité avec la modale de contact
      window.currentTotal = 0;
      window.currentLines = [];
      window.isSurDevis = false;
      window.hasValidSimulation = false;

      this.init();
    }

    init() {
      this.bindEvents();
      this.renderFormItems();
    }

    bindEvents() {
      // Gestion globale des steppers (+/-)
      this.devisWrap.addEventListener("click", (e) => {
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

        const minusBtn = stepper.querySelector(".minus");
        if (minusBtn) minusBtn.disabled = val <= 0;

        input.dispatchEvent(new Event("input", { bubbles: true }));
      });

      this.typeSelect.addEventListener("change", () => this.renderFormItems());
      this.devisWrap.addEventListener("input", () => this.calculate());

      if (this.pdfBtn) {
        this.pdfBtn.addEventListener("click", () => this.generatePDF());
      }
    }

    renderFormItems() {
      const selectedType = this.typeSelect.value;
      const typeConfig = this.config[selectedType];

      // A. Lignes dynamiques
      if (this.dynamicContainer) {
        this.dynamicContainer.innerHTML = "";
        if (typeConfig && Array.isArray(typeConfig.lines)) {
          let html = "";
          typeConfig.lines.forEach((ln, idx) => {
            const isStandard = ["adulte", "enfant", "bebe"].includes(ln.type);
            const isCustomQty = ln.type === "personnalise" && ln.enable_qty;
            const isFixed = ln.type === "personnalise" && !ln.enable_qty;

            if (isStandard || isCustomQty || isFixed) {
              const uniqueId = `qty-${selectedType}-${idx}`;
              const priceTxt = window.formatCurrency(ln.price);

              let obsHtml = "";
              if (ln.observation) {
                obsHtml = `<span class="exp-item-obs">${ln.observation}</span>`;
              }

              let leftColumn = `
                      <div class="exp-item-info">
                          <label for="${uniqueId}" class="exp-item-name">${ln.label}</label>
                          <span class="exp-item-price">${priceTxt}</span>
                          ${obsHtml}
                      </div>`;

              let rightColumn = "";
              if (isFixed) {
                rightColumn = `
                      <div class="exp-item-action">
                          <span style="font-size:0.9rem; font-weight:600; color:#0e2b5c;">1</span>
                          <input type="hidden" class="exp-dyn-qty" id="${uniqueId}" value="1">
                      </div>`;
              } else {
                let defVal = 0;
                if (ln.type === "adulte" && idx === 0) defVal = 1;
                rightColumn = `
                      <div class="exp-item-action">
                          <div class="exp-stepper">
                              <button type="button" class="exp-stepper-btn minus" ${defVal <= 0 ? "disabled" : ""}>−</button>
                              <input type="number" class="exp-dyn-qty" id="${uniqueId}" min="0" value="${defVal}" readonly>
                              <button type="button" class="exp-stepper-btn plus">+</button>
                          </div>
                      </div>`;
              }

              html += `
                  <div class="exp-list-item" data-line-index="${idx}" data-line-type="${ln.type}">
                      ${leftColumn}
                      ${rightColumn}
                  </div>`;
            }
          });
          this.dynamicContainer.innerHTML = html;
        }
      }

      // B. Options
      if (this.optionsContainer) {
        this.optionsContainer.innerHTML = "";
        if (typeConfig && typeConfig.options && typeConfig.options.length > 0) {
          let html = '<h4 class="exp-list-title">Options</h4>';
          typeConfig.options.forEach((opt, index) => {
            const id = `opt-${selectedType}-${index}`;
            const withQty = !!opt.enable_qty;
            const priceTxt = window.formatCurrency(opt.price);

            if (withQty) {
              html += `
                    <div class="exp-list-item">
                        <div class="exp-item-info">
                            <label for="${id}" class="exp-item-label">${opt.label}</label>
                            <span class="exp-item-price">+${priceTxt}</span>
                        </div>
                        <div class="exp-item-action">
                            <input type="checkbox" id="${id}" data-price="${opt.price}" data-label="${opt.label}" data-enable-qty="1" style="display:none;">
                            <div class="exp-stepper">
                                <button type="button" class="exp-stepper-btn minus" disabled>−</button>
                                <input type="number" class="exp-opt-qty" data-for="${id}" min="0" value="0" readonly>
                                <button type="button" class="exp-stepper-btn plus">+</button>
                            </div>
                        </div>
                    </div>`;
            } else {
              html += `
                    <div class="exp-list-item">
                        <div class="exp-item-info">
                            <label for="${id}" class="exp-item-label">${opt.label}</label>
                            <span class="exp-item-price">+${priceTxt}</span>
                        </div>
                        <div class="exp-item-action">
                            <div class="exp-checkbox-wrapper">
                                <input type="checkbox" id="${id}" data-price="${opt.price}" data-label="${opt.label}" data-enable-qty="">
                            </div>
                        </div>
                    </div>`;
            }
          });
          this.optionsContainer.innerHTML = html;

          this.optionsContainer
            .querySelectorAll('input[type="checkbox"][data-enable-qty=""]')
            .forEach((cb) => {
              cb.addEventListener("change", () => this.calculate());
            });
          this.optionsContainer
            .querySelectorAll(".exp-opt-qty")
            .forEach((qtyInput) => {
              qtyInput.addEventListener("input", (e) => {
                const targetId = e.target.dataset.for;
                const checkbox = document.getElementById(targetId);
                const val = parseInt(e.target.value) || 0;
                if (checkbox) checkbox.checked = val > 0;
                this.calculate();
              });
            });
        }
      }

      if (this.dynamicContainer) {
        this.dynamicContainer
          .querySelectorAll(".exp-dyn-qty")
          .forEach((inp) => {
            inp.addEventListener("input", () => this.calculate());
          });
      }

      this.calculate();
    }

    calculate() {
      const selectedType = this.typeSelect.value;
      const typeConfig = this.config[selectedType];
      if (!typeConfig) return;

      let total = 0;
      let lines = [];
      let resultHTML = '<h4 class="exp-result-title">Résumé du devis</h4><ul>';

      const code = typeConfig.code ? typeConfig.code : selectedType;
      const isSurDevis = code === "sur-devis";
      window.isSurDevis = isSurDevis;

      let sumAdults = 0;
      let sumChildren = 0;
      let sumBebes = 0;

      const items = this.dynamicContainer
        ? this.dynamicContainer.querySelectorAll(".exp-list-item")
        : [];

      items.forEach((item) => {
        const idx = parseInt(item.dataset.lineIndex);
        const type = item.dataset.lineType;
        const qtyInput = item.querySelector(".exp-dyn-qty");
        const qty = parseInt(qtyInput ? qtyInput.value : 0) || 0;

        const ln = typeConfig.lines[idx];
        if (!ln) return;

        if (type === "adulte") sumAdults += qty;
        if (type === "enfant") sumChildren += qty;
        if (type === "bebe") sumBebes += qty;

        if (qty <= 0) return;

        const unit = Number(ln.price) || 0;

        if (isSurDevis) {
          const labelQty = `${qty} × ${ln.label}`;
          lines.push({ label: labelQty, price: this.pendingLabel });
          resultHTML += `<li><span>${labelQty}</span><span class="exp-price--pending">${this.pendingLabel}</span></li>`;
        } else {
          if (type === "bebe" && unit === 0) {
            const labelQty = `${qty} × ${ln.label}`;
            lines.push({ label: labelQty, price: "Gratuit" });
            resultHTML += `<li><span>${labelQty}</span><span>Gratuit</span></li>`;
          } else {
            const sub = qty * unit;
            total += sub;
            const labelQty = `${qty} × ${ln.label}`;
            lines.push({ label: labelQty, price: window.formatCurrency(sub) });
            resultHTML += `<li><span>${labelQty}</span><span>${window.formatCurrency(sub)}</span></li>`;
          }
        }
      });

      if (this.totalAdultsInput) this.totalAdultsInput.value = sumAdults;
      if (this.totalChildrenInput) this.totalChildrenInput.value = sumChildren;
      if (this.totalBebesInput) this.totalBebesInput.value = sumBebes;

      const fixedFees = Array.isArray(typeConfig.fixed_fees)
        ? typeConfig.fixed_fees
        : [];
      if (fixedFees.length > 0) {
        const totalPeople = sumAdults + sumChildren + sumBebes;
        if (totalPeople > 0 || (items.length > 0 && window.currentTotal > 0)) {
          lines.push({ label: "Frais fixes", price: "", isSeparator: true });
          resultHTML += `<li class="separator"><strong>Frais fixes</strong></li>`;
          fixedFees.forEach((fee) => {
            const price = Number(fee.price) || 0;
            if (isSurDevis) {
              lines.push({ label: fee.label, price: this.pendingLabel });
              resultHTML += `<li><span>${fee.label}</span><span class="exp-price--pending">${this.pendingLabel}</span></li>`;
            } else {
              total += price;
              lines.push({
                label: fee.label,
                price: window.formatCurrency(price),
              });
              resultHTML += `<li><span>${fee.label}</span><span>${window.formatCurrency(price)}</span></li>`;
            }
          });
        }
      }

      if (this.optionsContainer) {
        const checkedOptions = this.optionsContainer.querySelectorAll(
          "input[type='checkbox']:checked",
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
              const qtyInput = this.optionsContainer.querySelector(
                `.exp-opt-qty[data-for="${targetId}"]`,
              );
              q = parseInt(qtyInput && qtyInput.value, 10) || 1;
              labelWithQty = `${q} × ${opt.dataset.label}`;
            }
            if (isSurDevis) {
              lines.push({ label: labelWithQty, price: this.pendingLabel });
              resultHTML += `<li><span>${labelWithQty}</span><span class="exp-price--pending">${this.pendingLabel}</span></li>`;
            } else {
              const unit = parseFloat(opt.dataset.price) || 0;
              const sub = unit * q;
              total += sub;
              lines.push({
                label: labelWithQty,
                price: window.formatCurrency(sub),
              });
              resultHTML += `<li><span>${labelWithQty}</span><span>${window.formatCurrency(sub)}</span></li>`;
            }
          });
        }
      }

      if (isSurDevis) {
        resultHTML += `</ul><div class="exp-result-total"><span>Total</span><span class="exp-price--pending">${this.pendingLabel}</span></div>`;
      } else {
        resultHTML += `</ul><div class="exp-result-total"><span>Total</span><span>${window.formatCurrency(total)}</span></div>`;
      }
      if (this.resultDiv) this.resultDiv.innerHTML = resultHTML;

      // Mise à jour des variables globales pour le reste du script
      window.currentTotal = isSurDevis ? 0 : total;
      window.currentLines = lines;

      const hasPeople = sumAdults + sumChildren + sumBebes > 0;
      window.hasValidSimulation = isSurDevis
        ? hasPeople
        : total > 0 || hasPeople;

      // Déclenche l'événement pour la Bottom-Sheet / Modale
      this.devisWrap.dispatchEvent(new CustomEvent("devisUpdated"));
    }

    generatePDF() {
      if (!window.PCPdfGenerator)
        return alert("Le module PDF n'est pas chargé.");
      window.PCPdfGenerator.generate({
        hasValidSimulation: window.hasValidSimulation,
        companyInfo: this.companyInfo,
        experienceTitle: this.experienceTitle,
        adultsCount: this.totalAdultsInput ? this.totalAdultsInput.value : 0,
        childrenCount: this.totalChildrenInput
          ? this.totalChildrenInput.value
          : 0,
        lines: window.currentLines || [],
        total: window.currentTotal || 0,
        isSurDevis: window.isSurDevis,
        pendingLabel: this.pendingLabel,
      });
    }
  }

  // Active le calculateur sur toutes les instances de la page
  document
    .querySelectorAll(".exp-devis-wrap[data-exp-devis]")
    .forEach((wrap) => {
      new PCBookingCalculator(wrap);
    });
});
