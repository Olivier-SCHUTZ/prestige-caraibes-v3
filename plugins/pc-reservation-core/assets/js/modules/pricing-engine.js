/**
 * Module Pricing Engine - Système de calcul de prix et devis
 *
 * @author Développeur Senior JS
 * @since v0.1.1
 */

(function () {
  "use strict";

  // Initialisation du namespace si nécessaire
  if (!window.PCR) {
    window.PCR = {};
  }

  /**
   * Module Pricing - Gestion des calculs de prix
   */
  window.PCR.Pricing = {
    // Données de tarification chargées à l'initialisation
    _experiencePricingData: {},
    _logementQuote: null,

    /**
     * Initialise le module avec les données nécessaires
     * @param {Object} params - Paramètres d'initialisation
     * @param {Object} params.experienceTarifs - Données de tarification des expériences
     * @param {Object} params.logementQuote - Instance du calculateur de devis logement
     */
    init: function (params = {}) {
      this._experiencePricingData = params.experienceTarifs || {};
      this._logementQuote =
        params.logementQuote || window.PCLogementDevis || null;

      console.log("[PCR.Pricing] Module initialisé avec", {
        experiences: Object.keys(this._experiencePricingData).length,
        hasLogementQuote: !!this._logementQuote,
      });
    },

    /**
     * Récupère la configuration tarifaire pour une expérience donnée
     * @param {string|number} expId - ID de l'expérience
     * @param {string} key - Clé du tarif recherché
     * @returns {Object|null} Configuration du tarif ou null
     */
    getTarifConfig: function (expId, key) {
      if (!expId || !this._experiencePricingData[expId]) {
        return null;
      }
      return (
        this._experiencePricingData[expId].find((tarif) => tarif.key === key) ||
        null
      );
    },

    /**
     * Remplit les selects [data-tarif-select] pour une expérience donnée
     * @param {string|number} expId - ID de l'expérience
     * @param {string} selectedKey - Clé présélectionnée (optionnel)
     */
    populateTarifOptions: function (expId, selectedKey = "") {
      const selects = document.querySelectorAll("select[data-tarif-select]");
      selects.forEach((select) => {
        // Vide le select
        select.innerHTML = "";

        if (
          !expId ||
          !this._experiencePricingData[expId] ||
          this._experiencePricingData[expId].length === 0
        ) {
          const opt = document.createElement("option");
          opt.value = "";
          opt.textContent = "Sélectionnez une expérience d'abord";
          select.appendChild(opt);
          select.disabled = true;
          select.required = true;
          return;
        }

        // Option par défaut
        const defaultOpt = document.createElement("option");
        defaultOpt.value = "";
        defaultOpt.textContent = "Sélectionnez un tarif";
        select.appendChild(defaultOpt);

        this._experiencePricingData[expId].forEach((tarif) => {
          const opt = document.createElement("option");
          opt.value = tarif.key || "";
          opt.textContent = tarif.label || tarif.key || "Tarif";
          select.appendChild(opt);
        });

        select.disabled = false;
        select.required = true;
        if (selectedKey) {
          select.value = selectedKey;
        }
      });
    },

    /**
     * Calcule un devis basé sur la configuration et les compteurs
     * @param {Object} config - Configuration tarifaire
     * @param {Object} counts - Nombres de participants {adultes, enfants, bebes}
     * @param {Object} extras - Options supplémentaires {customQty, options}
     * @returns {Object} Résultat du calcul
     */
    computeQuote: function (config, counts, extras = {}) {
      if (!config) {
        return {
          lines: [],
          html: "",
          total: 0,
          isSurDevis: false,
        };
      }

      const pendingLabel = "En attente de devis";
      const isSurDevis = config.code === "sur-devis";
      const customQtyMap = extras.customQty || {};
      const selectedOptions = Array.isArray(extras.options)
        ? extras.options
        : [];
      let total = 0;
      let html = "<ul>";
      const lines = [];

      const appendLine = (label, amount, formatted) => {
        const priceDisplay =
          formatted ||
          (isSurDevis ? pendingLabel : PCR.Utils.formatPrice(amount));
        html += `<li><span>${label}</span><span>${priceDisplay}</span></li>`;
        lines.push({
          label,
          price: priceDisplay,
        });
        if (!isSurDevis && amount) {
          total += amount;
        }
      };

      // Calcul des lignes principales
      (config.lines || []).forEach((line, index) => {
        const type = line.type || "personnalise";
        const unit = parseFloat(line.price) || 0;
        let qty = 1;

        if (type === "adulte") qty = counts.adultes;
        else if (type === "enfant") qty = counts.enfants;
        else if (type === "bebe") qty = counts.bebes;
        else if (line.enable_qty) {
          const mapKey = line.uid || `line_${index}`;
          if (typeof customQtyMap[mapKey] !== "undefined") {
            qty = parseInt(customQtyMap[mapKey], 10) || 0;
          } else if (line.default_qty) {
            qty = parseInt(line.default_qty, 10) || 0;
          } else {
            qty = 0;
          }
        }

        if (
          (type === "adulte" || type === "enfant" || type === "bebe") &&
          qty <= 0
        ) {
          if (line.observation) {
            html += `<li class="note">${line.observation}</li>`;
          }
          return;
        }

        if (line.enable_qty && qty <= 0) {
          if (line.observation) {
            html += `<li class="note">${line.observation}</li>`;
          }
          return;
        }

        if (qty <= 0) {
          return;
        }

        const label = `${qty} ${line.label || ""}`.trim();
        const amount = qty * unit;

        if (type === "bebe" && unit === 0 && !isSurDevis) {
          html += `<li><span>${label}</span><span>Gratuit</span></li>`;
          lines.push({
            label,
            price: "Gratuit",
          });
          if (line.observation) {
            html += `<li class="note">${line.observation}</li>`;
          }
          return;
        }

        appendLine(label, amount);

        if (line.observation) {
          html += `<li class="note">${line.observation}</li>`;
        }
      });

      // Frais fixes
      (config.fixed_fees || []).forEach((fee) => {
        const label = fee.label || "Frais fixes";
        const amount = parseFloat(fee.price) || 0;
        if (!label || amount === 0) {
          return;
        }
        appendLine(label, amount);
      });

      // Options sélectionnées
      if (selectedOptions.length) {
        html += '<li class="pc-resa-summary-sep"><strong>Options</strong></li>';
        selectedOptions.forEach((opt) => {
          const optLabel = opt.label || "Option";
          const optQty = Math.max(1, parseInt(opt.qty, 10) || 1);
          const label = optQty > 1 ? `${optLabel} × ${optQty}` : optLabel;
          const amount = (parseFloat(opt.price) || 0) * optQty;
          appendLine(label, amount);
        });
      }

      html += "</ul>";

      return {
        lines,
        html,
        total,
        isSurDevis,
        pendingLabel,
      };
    },

    /**
     * Applique les résultats du calcul au formulaire
     * @param {Object} args - Arguments contenant result et éléments DOM
     */
    applyQuoteToForm: function (args) {
      const {
        result,
        linesTextarea,
        totalInput,
        summaryBody,
        summaryTotal,
        remiseLabel,
        remiseAmount,
        plusLabel,
        plusAmount,
      } = args;

      let summaryHtml = result.html;
      const linesJson = [...result.lines];
      const remiseValue =
        parseFloat(
          remiseAmount && remiseAmount.value ? remiseAmount.value : 0,
        ) || 0;

      // Application de la remise
      if (remiseValue > 0) {
        const label =
          remiseLabel && remiseLabel.value
            ? remiseLabel.value
            : "Remise exceptionnelle";
        const signed = -Math.abs(remiseValue);
        const display = result.isSurDevis
          ? result.pendingLabel
          : PCR.Utils.formatPrice(signed);
        summaryHtml = summaryHtml.replace(
          "</ul>",
          `<li><span>${label}</span><span>${display}</span></li></ul>`,
        );
        if (!result.isSurDevis) {
          result.total += signed;
        }
      }

      // Application de la plus-value
      const plusValue =
        parseFloat(plusAmount && plusAmount.value ? plusAmount.value : 0) || 0;
      if (plusValue > 0) {
        const label =
          plusLabel && plusLabel.value ? plusLabel.value : "Plus-value";
        const display = result.isSurDevis
          ? result.pendingLabel
          : PCR.Utils.formatPrice(Math.abs(plusValue));
        summaryHtml = summaryHtml.replace(
          "</ul>",
          `<li><span>${label}</span><span>${display}</span></li></ul>`,
        );
        if (!result.isSurDevis) {
          result.total += Math.abs(plusValue);
        }
      }

      // Mise à jour des éléments DOM
      if (summaryBody) {
        summaryBody.innerHTML =
          summaryHtml ||
          '<p class="pc-resa-field-hint">Aucun calcul disponible.</p>';
      }
      if (summaryTotal) {
        summaryTotal.textContent = result.isSurDevis
          ? result.pendingLabel
          : PCR.Utils.formatPrice(Math.max(result.total, 0));
      }
      if (totalInput) {
        totalInput.value = result.isSurDevis
          ? ""
          : Math.max(result.total, 0).toFixed(2);
      }
      if (linesTextarea) {
        linesTextarea.value = linesJson.length ? JSON.stringify(linesJson) : "";
      }
    },

    /**
     * Calcule un devis logement (délégation vers le moteur existant)
     * @param {Object} config - Configuration du logement
     * @param {Object} params - Paramètres du séjour
     * @returns {Object} Résultat du calcul
     */
    calculateLogementQuote: function (config, params) {
      if (
        !this._logementQuote ||
        typeof this._logementQuote.calculateQuote !== "function"
      ) {
        return {
          success: false,
          message: "Le moteur logement n'est pas chargé.",
        };
      }
      return this._logementQuote.calculateQuote(config, params);
    },
  };

  console.log("[PCR.Pricing] Module chargé");
})();
