(function () {
  const params = window.pcResaParams || {};
  const pcResaAjaxUrl = params.ajaxUrl || "";
  const pcResaManualNonce = params.manualNonce || "";
  const experiencePricingData = params.experienceTarifs || {};
  const experienceOptions = params.experienceOptions || {};
  const logementOptions = params.logementOptions || {};
  const logementQuote = params.logementQuote || window.PCLogementDevis || null;
  const translations = params.translations || {};
  const translate = (key, fallback) =>
    translations && translations[key] ? translations[key] : fallback;

  document.addEventListener("DOMContentLoaded", function () {
    // Garde-fou : si le script est chargé plusieurs fois, on n'initialise qu'une seule fois
    if (window.pcDashboardCoreInitialized) {
      return;
    }
    window.pcDashboardCoreInitialized = true;

    const logementConfigCache = {};
    const logementConfigPromises = {};
    const currencyFormatter = new Intl.NumberFormat("fr-FR", {
      style: "currency",
      currency: "EUR",
    });
    const createTemplate = document.getElementById("pc-resa-create-template");

    // Bouton "Créer une réservation" (ouverture manuelle du popup)
    const createBtn = document.querySelector(".pc-resa-create-btn");
    if (createBtn && createTemplate) {
      createBtn.addEventListener("click", function () {
        openManualCreateModal();
      });
    }

    const closeAllActionMenus = () => {
      document.querySelectorAll(".pc-resa-actions-more").forEach((menu) => {
        menu.classList.remove("is-open");
        const toggle = menu.querySelector(".pc-resa-actions-toggle");
        if (toggle) {
          toggle.setAttribute("aria-expanded", "false");
        }
      });
    };

    document.addEventListener(
      "click",
      (event) => {
        const toggle = event.target.closest(".pc-resa-actions-toggle");
        if (toggle) {
          event.preventDefault();
          const container = toggle.closest(".pc-resa-actions-more");
          if (!container) {
            return;
          }
          const wasOpen = container.classList.contains("is-open");
          closeAllActionMenus();
          if (!wasOpen) {
            container.classList.add("is-open");
            toggle.setAttribute("aria-expanded", "true");
          }
          return;
        }

        if (event.target.closest(".pc-resa-actions-menu__link")) {
          closeAllActionMenus();
          return;
        }

        if (!event.target.closest(".pc-resa-actions-more")) {
          closeAllActionMenus();
        }
      },
      {
        capture: false,
      }
    );

    const formatPrice = (amount) => currencyFormatter.format(amount || 0);
    const escapeHtml = (value) =>
      String(value == null ? "" : value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");

    const parseJSONSafe = (value) => {
      if (!value) {
        return null;
      }
      try {
        return JSON.parse(value);
      } catch (error) {
        console.error("JSON parse error", error);
        return null;
      }
    };

    const fetchLogementConfig = (logementId) => {
      if (!logementId) {
        return Promise.reject(new Error("missing_logement_id"));
      }
      const cacheKey = String(logementId);
      if (logementConfigCache[cacheKey]) {
        return Promise.resolve(logementConfigCache[cacheKey]);
      }
      if (logementConfigPromises[cacheKey]) {
        return logementConfigPromises[cacheKey];
      }
      const formData = new FormData();
      formData.append("action", "pc_manual_logement_config");
      formData.append("nonce", pcResaManualNonce);
      formData.append("logement_id", cacheKey);
      console.log("[pc-devis] Demande config logement :", cacheKey);
      const promise = fetch(pcResaAjaxUrl, {
        method: "POST",
        body: formData,
        credentials: "same-origin",
      })
        .then(async (response) => {
          const raw = await response.text();
          let payload = null;
          if (raw) {
            try {
              payload = JSON.parse(raw);
            } catch (error) {
              payload = null;
            }
          }
          if (!response.ok) {
            const message =
              payload && payload.data && payload.data.message
                ? payload.data.message
                : raw || translate("genericError", "Erreur serveur");
            const err = new Error(message);
            err.payload = payload;
            throw err;
          }
          if (
            !payload ||
            !payload.success ||
            !payload.data ||
            !payload.data.config
          ) {
            const message =
              payload && payload.data && payload.data.message
                ? payload.data.message
                : "Config logement introuvable";
            const err = new Error(message);
            err.payload = payload;
            throw err;
          }
          console.log(
            "[pc-devis] Configuration reçue par le calendrier :",
            payload.data.config || {}
          );
          if (payload.data && payload.data.config) {
            console.log(
              "[pc-devis] Dates à désactiver :",
              payload.data.config.icsDisable || []
            );
          }
          logementConfigCache[cacheKey] = payload.data.config;
          return payload.data.config;
        })
        .finally(() => {
          delete logementConfigPromises[cacheKey];
        });
      logementConfigPromises[cacheKey] = promise;
      return promise;
    };

    const decodeText = (value) => {
      if (value == null) {
        return "";
      }
      let str = String(value);
      str = str.replace(/\\u([0-9a-fA-F]{4})/g, (_m, g1) => {
        try {
          return JSON.parse('"\\u' + g1 + '"');
        } catch (e) {
          return _m;
        }
      });
      str = str.replace(/u([0-9a-fA-F]{4})/g, (_m, g1) => {
        try {
          return JSON.parse('"\\u' + g1 + '"');
        } catch (e) {
          return _m;
        }
      });
      str = str.replace(/\u00a0|\u202f/g, " ");
      return str;
    };

    const renderStoredLinesSummary = (
      lines,
      summaryBody,
      summaryTotal,
      totalValue
    ) => {
      if (!summaryBody || !Array.isArray(lines) || lines.length === 0) {
        return;
      }
      let html = "<ul>";
      lines.forEach((line) => {
        const rawLabel = decodeText(line.label || "");
        const rawPrice = decodeText(line.price || "");

        let formattedPrice = rawPrice;
        const numericPrice = parseFloat(
          rawPrice.replace(/[^\d,\.-]/g, "").replace(",", ".")
        );
        if (!Number.isNaN(numericPrice) && rawPrice !== "") {
          formattedPrice = formatPrice(numericPrice);
        }
        const separator = formattedPrice ? " \u2013 " : "";
        html += `<li><span>${rawLabel}</span><span>${separator}${formattedPrice}</span></li>`;
      });
      html += "</ul>";
      summaryBody.innerHTML = html;
      if (summaryTotal) {
        const numericTotal =
          typeof totalValue === "number"
            ? totalValue
            : parseFloat(totalValue || 0);
        summaryTotal.textContent = formatPrice(numericTotal);
      }
    };

    const getTarifConfig = (expId, key) => {
      if (!expId || !experiencePricingData[expId]) {
        return null;
      }
      return (
        experiencePricingData[expId].find((tarif) => tarif.key === key) || null
      );
    };

    // Remplit les selects [data-tarif-select] pour une expérience donnée
    const populateTarifOptions = (expId, selectedKey = "") => {
      const selects = document.querySelectorAll("select[data-tarif-select]");
      selects.forEach((select) => {
        // vide le select
        select.innerHTML = "";

        if (
          !expId ||
          !experiencePricingData[expId] ||
          experiencePricingData[expId].length === 0
        ) {
          const opt = document.createElement("option");
          opt.value = "";
          opt.textContent = "Sélectionnez une expérience d'abord";
          select.appendChild(opt);
          select.disabled = true;
          select.required = true;
          return;
        }

        // option par défaut
        const defaultOpt = document.createElement("option");
        defaultOpt.value = "";
        defaultOpt.textContent = "Sélectionnez un tarif";
        select.appendChild(defaultOpt);

        experiencePricingData[expId].forEach((tarif) => {
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
    };

    const computeQuote = (config, counts, extras = {}) => {
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
          formatted || (isSurDevis ? pendingLabel : formatPrice(amount));
        html += `<li><span>${label}</span><span>${priceDisplay}</span></li>`;
        lines.push({
          label,
          price: priceDisplay,
        });
        if (!isSurDevis && amount) {
          total += amount;
        }
      };

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

      (config.fixed_fees || []).forEach((fee) => {
        const label = fee.label || "Frais fixes";
        const amount = parseFloat(fee.price) || 0;
        if (!label || amount === 0) {
          return;
        }
        appendLine(label, amount);
      });

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
    };

    const applyQuoteToForm = (args) => {
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
          remiseAmount && remiseAmount.value ? remiseAmount.value : 0
        ) || 0;

      if (remiseValue > 0) {
        const label =
          remiseLabel && remiseLabel.value
            ? remiseLabel.value
            : "Remise exceptionnelle";
        const signed = -Math.abs(remiseValue);
        const display = result.isSurDevis
          ? result.pendingLabel
          : formatPrice(signed);
        summaryHtml = summaryHtml.replace(
          "</ul>",
          `<li><span>${label}</span><span>${display}</span></li></ul>`
        );
        if (!result.isSurDevis) {
          result.total += signed;
        }
      }

      const plusValue =
        parseFloat(plusAmount && plusAmount.value ? plusAmount.value : 0) || 0;
      if (plusValue > 0) {
        const label =
          plusLabel && plusLabel.value ? plusLabel.value : "Plus-value";
        const display = result.isSurDevis
          ? result.pendingLabel
          : formatPrice(Math.abs(plusValue));
        summaryHtml = summaryHtml.replace(
          "</ul>",
          `<li><span>${label}</span><span>${display}</span></li></ul>`
        );
        if (!result.isSurDevis) {
          result.total += Math.abs(plusValue);
        }
      }

      if (summaryBody) {
        summaryBody.innerHTML =
          summaryHtml ||
          '<p class="pc-resa-field-hint">Aucun calcul disponible.</p>';
      }
      if (summaryTotal) {
        summaryTotal.textContent = result.isSurDevis
          ? result.pendingLabel
          : formatPrice(Math.max(result.total, 0));
      }
      if (totalInput) {
        totalInput.value = result.isSurDevis
          ? ""
          : Math.max(result.total, 0).toFixed(2);
      }
      if (linesTextarea) {
        linesTextarea.value = linesJson.length ? JSON.stringify(linesJson) : "";
      }
    };

    async function handleManualCreateSubmit(form, submitBtn) {
      const formData = new FormData(form);
      formData.set(
        "action",
        formData.get("action") || "pc_manual_reservation_create"
      );
      formData.set("nonce", pcResaManualNonce);

      const participantsAdults = form.querySelector(
        'input[name="participants_adultes"]'
      );
      const participantsEnfants = form.querySelector(
        'input[name="participants_enfants"]'
      );
      const participantsBebes = form.querySelector(
        'input[name="participants_bebes"]'
      );
      const participantsEnabled =
        form.getAttribute("data-participants-enabled") === "1";
      if (participantsEnabled) {
        if (participantsAdults && participantsAdults.value !== "") {
          formData.set(
            "adultes",
            parseInt(participantsAdults.value || "0", 10) || 0
          );
        }
        if (participantsEnfants && participantsEnfants.value !== "") {
          formData.set(
            "enfants",
            parseInt(participantsEnfants.value || "0", 10) || 0
          );
        }
        if (participantsBebes && participantsBebes.value !== "") {
          formData.set(
            "bebes",
            parseInt(participantsBebes.value || "0", 10) || 0
          );
        }
      }

      const typeValue = formData.get("type") || "experience";
      if (typeValue === "experience") {
        if (!formData.get("item_id")) {
          alert("Sélectionnez une expérience.");
          return;
        }
        if (!formData.get("experience_tarif_type")) {
          alert("Sélectionnez un type de tarif.");
          return;
        }
      } else if (typeValue === "location") {
        if (!formData.get("item_id")) {
          alert("Sélectionnez un logement.");
          return;
        }
        if (!formData.get("date_arrivee") || !formData.get("date_depart")) {
          alert("Choisissez les dates du séjour logement.");
          return;
        }
        if (!formData.get("lines_json")) {
          alert("Calculez le devis logement avant de continuer.");
          return;
        }
      } else {
        alert("Type de réservation inconnu.");
        return;
      }

      const originalText = submitBtn.textContent;
      submitBtn.disabled = true;
      submitBtn.textContent = "Création en cours...";

      try {
        const response = await fetch(pcResaAjaxUrl, {
          method: "POST",
          body: formData,
          credentials: "same-origin",
        });

        const responseText = await response.text();

        if (!response.ok) {
          console.error(
            "Manual creation HTTP error",
            response.status,
            responseText
          );
          alert("Erreur serveur (" + response.status + ").");
          submitBtn.textContent = originalText;
          submitBtn.disabled = false;
          return;
        }
        let result;
        try {
          result = JSON.parse(responseText);
        } catch (parseError) {
          const trimmed = responseText.trim();
          let userMessage = "Réponse inattendue du serveur.";
          if (trimmed === "0") {
            userMessage =
              "Session expirée ou accès refusé. Merci de vous reconnecter à WordPress.";
          }
          console.error("Manual creation raw response:", responseText);
          alert(userMessage);
          submitBtn.textContent = originalText;
          submitBtn.disabled = false;
          return;
        }

        if (result.success) {
          const successMsg =
            result.data && result.data.message
              ? result.data.message
              : "Réservation enregistrée";
          submitBtn.textContent = successMsg;
          setTimeout(function () {
            window.location.reload();
          }, 800);
        } else {
          const errorMsg =
            result.data && result.data.message
              ? result.data.message
              : "Une erreur est survenue.";
          alert(errorMsg);
          submitBtn.textContent = originalText;
          submitBtn.disabled = false;
        }
      } catch (error) {
        console.error("Manual creation error", error);
        alert("Erreur technique pendant la création.");
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
      }
    }

    function initManualCreateForm(container, prefillData = null, options = {}) {
      const form = container.querySelector(".pc-resa-create-form");
      if (!form) {
        return null;
      }

      const submitBtn = form.querySelector(".pc-resa-create-submit");
      const sendBtn = form.querySelector(".pc-resa-create-send");
      const typeSelect = form.querySelector('select[name="type"]');
      const typeFluxSelect = form.querySelector('select[name="type_flux"]');
      const modeSelect = form.querySelector('select[name="mode_reservation"]');
      const itemSelect = form.querySelector('select[name="item_id"]');
      const tarifSelect = form.querySelector(
        'select[name="experience_tarif_type"]'
      );
      const linesTextarea = form.querySelector('textarea[name="lines_json"]');
      const totalInput = form.querySelector('input[name="montant_total"]');
      const summaryBody = container.querySelector("[data-quote-summary]");
      const summaryTotal = container.querySelector("[data-quote-total]");
      const remiseLabel = form.querySelector('input[name="remise_label"]');
      const remiseAmount = form.querySelector('input[name="remise_montant"]');
      const remiseClearBtn = form.querySelector(".pc-resa-remise-clear");
      const participantsAdultsField = form.querySelector(
        'input[name="participants_adultes"]'
      );
      const participantsEnfantsField = form.querySelector(
        'input[name="participants_enfants"]'
      );
      const participantsBebesField = form.querySelector(
        'input[name="participants_bebes"]'
      );
      const participantsSection = form.querySelector(
        "[data-participants-section]"
      );
      const plusLabel = form.querySelector('input[name="plus_label"]');
      const plusAmount = form.querySelector('input[name="plus_montant"]');
      const plusClearBtn = form.querySelector(".pc-resa-plus-clear");
      const counters = form.querySelectorAll("[data-quote-counter]");
      const countersWrapper = form.querySelector("[data-quote-counters]");
      const capacityWarning = form.querySelector("[data-capacity-warning]");
      const customSection = form.querySelector("[data-quote-custom-section]");
      const customList = form.querySelector("[data-quote-customqty]");
      const optionsSection = form.querySelector("[data-quote-options-section]");
      const optionsList = form.querySelector("[data-quote-options]");
      const dateExperienceInput = form.querySelector(
        'input[name="date_experience"]'
      );
      const prenomInput = form.querySelector('input[name="prenom"]');
      const nomInput = form.querySelector('input[name="nom"]');
      const emailInput = form.querySelector('input[name="email"]');
      const telephoneInput = form.querySelector('input[name="telephone"]');
      const commentaireField = form.querySelector(
        'textarea[name="commentaire_client"]'
      );
      const notesField = form.querySelector('textarea[name="notes_internes"]');
      const numeroDevisInput = form.querySelector('input[name="numero_devis"]');
      const adultField = form.querySelector('input[name="adultes"]');
      const childField = form.querySelector('input[name="enfants"]');
      const babyField = form.querySelector('input[name="bebes"]');
      const typeLabel = form.querySelector("[data-item-label]");
      const typeHints = container.querySelectorAll("[data-type-hint]");
      const typeToggleNodes = container.querySelectorAll("[data-type-toggle]");
      const logementRangeInput = form.querySelector("[data-logement-range]");
      const arrivalInput = form.querySelector('input[name="date_arrivee"]');
      const departInput = form.querySelector('input[name="date_depart"]');
      const logementAvailability = form.querySelector(
        "[data-logement-availability]"
      );
      if (form) {
        form.setAttribute("data-participants-enabled", "0");
      }

      const prefill = prefillData || null;
      const opts = options || {};
      let logementCalendar = null;
      let pendingLogementRange = null;
      let currentLogementId = "";
      let currentLogementConfig = null;

      const formatYMD = (date) => {
        if (!(date instanceof Date)) {
          return "";
        }
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, "0");
        const day = String(date.getDate()).padStart(2, "0");
        return `${year}-${month}-${day}`;
      };

      const setTypeLabel = (currentType) => {
        if (!typeLabel) {
          return;
        }
        typeLabel.textContent =
          currentType === "location" ? "Logement" : "Expérience";
      };

      const toggleTypeHints = (currentType) => {
        typeHints.forEach((hint) => {
          const expected = hint.getAttribute("data-type-hint");
          if (!expected) {
            return;
          }
          hint.style.display = expected === currentType ? "" : "none";
        });
      };

      const ensureCountersVisible = () => {
        if (countersWrapper) {
          countersWrapper.style.display = "";
        }
      };

      const toggleTypeSections = (currentType) => {
        typeToggleNodes.forEach((node) => {
          const expected = node.getAttribute("data-type-toggle");
          if (!expected) {
            return;
          }
          node.style.display = expected === currentType ? "" : "none";
        });
        if (currentType === "location") {
          ensureCountersVisible();
        }
      };

      const populateItemOptions = (currentType, selectedId) => {
        if (!itemSelect) {
          return;
        }
        const source =
          currentType === "location" ? logementOptions : experienceOptions;
        const placeholder =
          currentType === "location"
            ? "Sélectionnez un logement"
            : "Sélectionnez une expérience";
        let html = `<option value=\"\">${placeholder}</option>`;
        Object.keys(source || {}).forEach((id) => {
          const label = source[id] || "";
          if (!label) {
            return;
          }
          html += `<option value=\"${id}\">${escapeHtml(label)}</option>`;
        });
        itemSelect.innerHTML = html;
        let targetValue = typeof selectedId === "undefined" ? "" : selectedId;
        if (!targetValue) {
          targetValue =
            currentType === "location" ? lastLocationId : lastExperienceId;
        }
        if (targetValue && source[String(targetValue)]) {
          itemSelect.value = String(targetValue);
        } else {
          itemSelect.value = "";
        }
        if (currentType === "location") {
          lastLocationId = itemSelect.value || "";
        } else {
          lastExperienceId = itemSelect.value || "";
        }
        if (currentType !== "experience" && tarifSelect) {
          tarifSelect.value = "";
          tarifSelect.disabled = true;
        } else if (tarifSelect) {
          tarifSelect.disabled = false;
        }
      };

      const setLogementAvailabilityMessage = (message) => {
        if (logementAvailability) {
          logementAvailability.textContent = message || "";
        }
      };

      const destroyLogementCalendar = () => {
        if (
          logementCalendar &&
          typeof logementCalendar.destroy === "function"
        ) {
          logementCalendar.destroy();
        }
        logementCalendar = null;
      };

      const initLogementCalendar = (
        config,
        rangeToApply = null,
        clearExisting = true
      ) => {
        if (!logementRangeInput) {
          return;
        }
        destroyLogementCalendar();
        if (clearExisting) {
          logementRangeInput.value = "";
          if (arrivalInput) {
            arrivalInput.value = "";
          }
          if (departInput) {
            departInput.value = "";
          }
        }
        logementRangeInput.disabled = !config;
        if (!config) {
          return;
        }
        const disableRanges = Array.isArray(config.icsDisable)
          ? config.icsDisable.filter((range) => range && range.from && range.to)
          : [];
        const disableRules = disableRanges.length
          ? [
              function (date) {
                const s = formatYMD(date);
                return disableRanges.some(
                  (range) => s >= range.from && s <= range.to
                );
              },
            ]
          : [];
        const bootCalendar = () => {
          if (typeof window.flatpickr !== "function") {
            setTimeout(bootCalendar, 150);
            return;
          }
          if (window.flatpickr.l10ns && window.flatpickr.l10ns.fr) {
            window.flatpickr.localize(window.flatpickr.l10ns.fr);
          }
          console.log(
            "[pc-devis] INIT FLATPICKR DASHBOARD",
            logementRangeInput,
            {
              mode: "range",
              dateFormat: "d/m/Y",
              disableRanges,
            }
          );
          logementCalendar = window.flatpickr(logementRangeInput, {
            mode: "range",
            dateFormat: "d/m/Y",
            altInput: false,
            minDate: "today",
            disable: disableRules,
            onChange(selectedDates) {
              if (selectedDates.length === 2) {
                if (arrivalInput) {
                  arrivalInput.value = formatYMD(selectedDates[0]);
                }
                if (departInput) {
                  departInput.value = formatYMD(selectedDates[1]);
                }
              } else {
                if (arrivalInput) {
                  arrivalInput.value = "";
                }
                if (departInput) {
                  departInput.value = "";
                }
              }
              updateQuote();
            },
          });
          if (logementCalendar && logementCalendar.config) {
            console.log(
              "[pc-devis] FLATPICKR CONFIG FINALE",
              logementCalendar.config.disable
            );
          }
          if (
            rangeToApply &&
            Array.isArray(rangeToApply) &&
            rangeToApply.length === 2
          ) {
            logementCalendar.setDate(rangeToApply, true, "Y-m-d");
            if (arrivalInput) {
              arrivalInput.value = rangeToApply[0];
            }
            if (departInput) {
              departInput.value = rangeToApply[1];
            }
          }
        };
        bootCalendar();
      };

      const updateCapacityLimits = (config) => {
        if (!capacityWarning) {
          return;
        }
        if (!config || typeof config.cap === "undefined") {
          capacityWarning.style.display = "none";
          capacityWarning.removeAttribute("data-current-cap");
          return;
        }
        const capValue = parseInt(config.cap, 10) || 0;
        if (capValue <= 0) {
          capacityWarning.style.display = "none";
          capacityWarning.removeAttribute("data-current-cap");
          return;
        }
        capacityWarning.style.display = "";
        capacityWarning.textContent = `Capacité maximale : ${capValue} personnes (adultes + enfants).`;
        capacityWarning.setAttribute("data-current-cap", String(capValue));
        enforceCapacity({
          adultes: parseInt(adultField ? adultField.value : "0", 10) || 0,
          enfants: parseInt(childField ? childField.value : "0", 10) || 0,
          bebes: parseInt(babyField ? babyField.value : "0", 10) || 0,
        });
      };

      const enforceCapacity = (counts) => {
        if (!capacityWarning) {
          return;
        }
        const capAttr = capacityWarning.getAttribute("data-current-cap");
        if (!capAttr) {
          capacityWarning.style.display = "none";
          return;
        }
        const capValue = parseInt(capAttr, 10) || 0;
        if (capValue <= 0) {
          capacityWarning.style.display = "none";
          return;
        }
        let adultesCount = counts.adultes || 0;
        let enfantsCount = counts.enfants || 0;
        let totalGuests = adultesCount + enfantsCount;
        if (totalGuests > capValue) {
          const overflow = totalGuests - capValue;
          if (enfantsCount > 0) {
            const newChildren = Math.max(0, enfantsCount - overflow);
            enfantsCount = newChildren;
            if (childField) {
              childField.value = String(newChildren);
            }
          }
          totalGuests = adultesCount + enfantsCount;
          if (totalGuests > capValue && adultesCount > 0) {
            adultesCount = Math.max(0, capValue - enfantsCount);
            if (adultField) {
              adultField.value = String(adultesCount);
            }
          }
          capacityWarning.textContent = `Capacité max ${capValue} personnes atteinte.`;
        } else {
          capacityWarning.textContent = `Capacité maximale : ${capValue} personnes (adultes + enfants).`;
        }
        counts.adultes = adultesCount;
        counts.enfants = enfantsCount;
        capacityWarning.style.display = "";
      };

      const prepareLogementConfig = (logementId, options = {}) => {
        currentLogementId = logementId || "";
        currentLogementConfig = null;
        initLogementCalendar(null, null, !options.range);
        if (!logementId) {
          setLogementAvailabilityMessage(
            "Sélectionnez un logement pour afficher les disponibilités."
          );
          updateCapacityLimits(null);
          updateQuote();
          return;
        }
        setLogementAvailabilityMessage("Chargement des disponibilités...");
        fetchLogementConfig(logementId)
          .then((config) => {
            currentLogementConfig = config;
            setLogementAvailabilityMessage(
              "Les périodes grisées sont indisponibles."
            );
            initLogementCalendar(config, options.range || null);
            pendingLogementRange = null;
            updateCapacityLimits(config);
            updateQuote();
          })
          .catch((error) => {
            console.error("Logement config error", error);
            const msg =
              error && error.message
                ? error.message
                : "Impossible de charger les disponibilités.";
            setLogementAvailabilityMessage(msg);
            updateCapacityLimits(null);
            updateQuote();
          });
      };

      const toggleAdjustmentButton = (input, btn) => {
        if (!btn || !input) {
          return;
        }
        const hasValue = input.value && parseFloat(input.value) > 0;
        btn.disabled = !hasValue;
      };
      const refreshRemiseButton = () =>
        toggleAdjustmentButton(remiseAmount, remiseClearBtn);
      const refreshPlusButton = () =>
        toggleAdjustmentButton(plusAmount, plusClearBtn);
      const normalizeLabelKey = (value) => {
        if (typeof value !== "string") {
          value = value == null ? "" : String(value);
        }
        return value.trim().toLowerCase().replace(/\s+/g, " ");
      };
      const parseQtyFromLabel = (label) => {
        if (!label) {
          return 0;
        }
        const m = label.match(/^(\d+)\s*[x×]?\s*(.+)$/i);
        if (m && m[1]) {
          const qty = parseInt(m[1], 10);
          return Number.isNaN(qty) ? 0 : qty;
        }
        return 0;
      };
      const deriveQtyMapFromLines = (lines) => {
        const out = {};
        if (!Array.isArray(lines)) {
          return out;
        }
        lines.forEach((line) => {
          const rawLabel = decodeText(line && line.label ? line.label : "");
          const qty = parseQtyFromLabel(rawLabel);
          if (qty > 0) {
            const normalized = normalizeLabelKey(
              rawLabel.replace(/^(\d+)\s*[x×]?\s*/, "")
            );
            if (normalized) {
              out[normalized] = qty;
            }
          }
        });
        return out;
      };
      const normalizePrefillQtyMap = (map) => {
        const out = {};
        if (!map || typeof map !== "object") {
          return out;
        }
        Object.keys(map).forEach((key) => {
          const qty = parseInt(map[key], 10);
          if (!Number.isNaN(qty) && qty > 0) {
            out[normalizeLabelKey(key)] = qty;
          }
        });
        return out;
      };
      let prefillQtyMap = normalizePrefillQtyMap(
        prefill && prefill.lines_qty_map
      );
      const applyPrefillSelections = () => {
        if (!prefillQtyMap || Object.keys(prefillQtyMap).length === 0) {
          return;
        }

        if (customList) {
          customList
            .querySelectorAll("input[data-custom-line]")
            .forEach((input) => {
              const labelEl = input.closest(".pc-resa-field");
              const labelTextEl = labelEl
                ? labelEl.querySelector(".pc-resa-field-label")
                : null;
              const labelText = labelTextEl ? labelTextEl.textContent : "";
              const key = normalizeLabelKey(labelText);
              const qty = prefillQtyMap[key];
              if (qty && qty > 0) {
                input.value = qty;
                input.dispatchEvent(new Event("input"));
              }
            });
        }

        if (optionsList) {
          optionsList
            .querySelectorAll('input[type="checkbox"][data-option-label]')
            .forEach((checkbox) => {
              const encodedLabel =
                checkbox.getAttribute("data-option-label") || "";
              let labelDecoded = encodedLabel;
              try {
                labelDecoded = decodeURIComponent(encodedLabel);
              } catch (error) {
                // ignore decode errors
              }
              const key = normalizeLabelKey(labelDecoded);
              const qty = prefillQtyMap[key];
              if (!qty || qty <= 0) {
                return;
              }
              checkbox.checked = true;
              const optId = checkbox.getAttribute("data-option-id");
              if (optId) {
                const qtyInput = optionsList.querySelector(
                  `[data-option-qty-for="${optId}"]`
                );
                if (qtyInput) {
                  qtyInput.disabled = false;
                  qtyInput.value = qty;
                }
              }
            });
        }
      };

      const getCustomQtyValues = () => {
        const map = {};
        if (!customList) {
          return map;
        }
        customList
          .querySelectorAll("input[data-custom-line]")
          .forEach((input) => {
            const key = input.getAttribute("data-custom-line");
            if (!key) {
              return;
            }
            const value = parseInt(input.value, 10);
            if (!Number.isNaN(value)) {
              map[key] = value;
            }
          });
        return map;
      };

      const getSelectedOptions = () => {
        const selected = [];
        if (!optionsList) {
          return selected;
        }
        optionsList
          .querySelectorAll('input[type="checkbox"]')
          .forEach((checkbox) => {
            if (!checkbox.checked) {
              return;
            }
            const optionId = checkbox.getAttribute("data-option-id") || "";
            const encodedLabel =
              checkbox.getAttribute("data-option-label") || "";
            let label = encodedLabel;
            try {
              label = decodeURIComponent(encodedLabel);
            } catch (error) {
              // ignore decode errors and keep encoded value
            }
            const price =
              parseFloat(checkbox.getAttribute("data-option-price") || "0") ||
              0;
            let qty = 1;
            if (checkbox.dataset.enableQty === "1") {
              const qtyInput = optionsList.querySelector(
                `[data-option-qty-for="${optionId}"]`
              );
              qty = parseInt(qtyInput && qtyInput.value, 10) || 1;
            }
            selected.push({
              id: optionId,
              label,
              price,
              qty,
            });
          });
        return selected;
      };

      const toggleCountersVisibility = (config) => {
        if (!countersWrapper) {
          return;
        }
        const shouldDisplay = !!(config && config.has_counters);
        countersWrapper.style.display = shouldDisplay ? "" : "none";
      };

      const setParticipantsEnabled = (enabled) => {
        if (!form) {
          return;
        }
        form.setAttribute("data-participants-enabled", enabled ? "1" : "0");
      };

      const normalizeCode = (value) => {
        if (typeof value !== "string") {
          value = value == null ? "" : String(value);
        }
        return value
          .normalize("NFD")
          .replace(/[\u0300-\u036f]/g, "")
          .trim()
          .toLowerCase()
          .replace(/[^a-z0-9]+/g, "-");
      };

      const isCustomExperienceConfig = (config) => {
        if (!config) {
          return false;
        }
        const code = normalizeCode(config.code || "");
        const label = normalizeCode(config.label || "");
        return (
          ["custom", "personnalise", "personnalisee"].includes(code) ||
          ["custom", "personnalise", "personnalisee"].includes(label)
        );
      };

      const toggleParticipantsSection = (config) => {
        if (!participantsSection) {
          setParticipantsEnabled(false);
          return;
        }
        const typeValue = typeSelect ? typeSelect.value : "experience";
        const shouldShow =
          typeValue === "experience" && isCustomExperienceConfig(config);
        participantsSection.style.display = shouldShow ? "" : "none";
        setParticipantsEnabled(shouldShow);
      };

      function renderCustomQtyInputs(config) {
        if (!customList) {
          return;
        }
        if (!config || !Array.isArray(config.lines)) {
          if (customSection) {
            customSection.style.display = "none";
          }
          return;
        }
        const linesWithQty = config.lines.filter((line) => line.enable_qty);
        if (linesWithQty.length === 0) {
          if (customSection) {
            customSection.style.display = "none";
          }
          return;
        }
        let html = "";
        linesWithQty.forEach((line, index) => {
          const inputKey = line.uid || `line_${index}`;
          const defaultValue =
            line.default_qty && parseInt(line.default_qty, 10) > 0
              ? parseInt(line.default_qty, 10)
              : 0;
          html += `
                            <label class="pc-resa-field pc-resa-field--inline">
                                <span class="pc-resa-field-label">${escapeHtml(
                                  line.label || "Service"
                                )}</span>
                                <input type="number" min="0" value="${defaultValue}" data-custom-line="${inputKey}">
                            </label>
                        `;
        });
        customList.innerHTML = html;
        if (customSection) {
          customSection.style.display = "";
        }
        customList
          .querySelectorAll("input[data-custom-line]")
          .forEach((input) => {
            input.addEventListener("input", updateQuote);
          });
      }

      function renderOptionsInputs(config) {
        if (!optionsList) {
          return;
        }
        optionsList.innerHTML = "";
        if (
          !config ||
          !Array.isArray(config.options) ||
          config.options.length === 0
        ) {
          if (optionsSection) {
            optionsSection.style.display = "none";
          }
          return;
        }
        let html = "";
        config.options.forEach((opt, index) => {
          const optionId = opt.uid || `option_${index}`;
          const safeLabel = escapeHtml(opt.label || "Option");
          const encodedLabel = encodeURIComponent(opt.label || "");
          const amountDisplay = formatPrice(opt.price || 0);
          html += `
                            <label class="pc-resa-option-item">
                                <input type="checkbox"
                                    data-option-id="${optionId}"
                                    data-option-label="${encodedLabel}"
                                    data-option-price="${
                                      parseFloat(opt.price) || 0
                                    }"
                                    data-enable-qty="${
                                      opt.enable_qty ? "1" : "0"
                                    }">
                                <span class="pc-resa-option-item__meta">
                                    <span>${safeLabel}</span>
                                    <span class="pc-resa-option-item__price">+ ${amountDisplay}</span>
                                </span>
                            </label>
                        `;
          if (opt.enable_qty) {
            const defaultQty =
              opt.default_qty && parseInt(opt.default_qty, 10) > 0
                ? parseInt(opt.default_qty, 10)
                : 1;
            html += `
                                <div class="pc-resa-option-qty">
                                    <label class="pc-resa-field pc-resa-field--inline">
                                        <span class="pc-resa-field-label">Quantité</span>
                                        <input type="number" min="1" value="${defaultQty}" data-option-qty-for="${optionId}" disabled>
                                    </label>
                                </div>
                            `;
          }
        });
        optionsList.innerHTML = html;
        if (optionsSection) {
          optionsSection.style.display = "";
        }
        optionsList
          .querySelectorAll('input[type="checkbox"]')
          .forEach((checkbox) => {
            checkbox.addEventListener("change", function () {
              const optId = this.getAttribute("data-option-id");
              const qtyInput = optionsList.querySelector(
                `[data-option-qty-for="${optId}"]`
              );
              if (qtyInput) {
                qtyInput.disabled = !this.checked;
              }
              updateQuote();
            });
          });
        optionsList
          .querySelectorAll("[data-option-qty-for]")
          .forEach((input) => {
            input.addEventListener("input", updateQuote);
          });
      }

      const refreshDynamicSections = (config) => {
        toggleCountersVisibility(config);
        toggleParticipantsSection(config);
        renderCustomQtyInputs(config);
        renderOptionsInputs(config);
      };

      let currentType = typeSelect
        ? typeSelect.value || "experience"
        : "experience";
      if (prefill && prefill.type) {
        currentType = prefill.type;
      }
      if (typeSelect) {
        typeSelect.value = currentType;
      }
      if (
        prefill &&
        prefill.type === "location" &&
        prefill.date_arrivee &&
        prefill.date_depart
      ) {
        pendingLogementRange = [prefill.date_arrivee, prefill.date_depart];
        if (arrivalInput) {
          arrivalInput.value = prefill.date_arrivee;
        }
        if (departInput) {
          departInput.value = prefill.date_depart;
        }
      }

      const initialItemId =
        prefill && prefill.item_id ? String(prefill.item_id) : "";
      let lastExperienceId = currentType === "experience" ? initialItemId : "";
      let lastLocationId = currentType === "location" ? initialItemId : "";
      populateItemOptions(currentType, initialItemId);
      setTypeLabel(currentType);
      toggleTypeHints(currentType);
      toggleTypeSections(currentType);

      // Ensure hidden "id" field exists so edits submit the reservation id instead of creating a new one
      let idInput = form.querySelector('input[name="id"]');
      if (!idInput) {
        idInput = document.createElement("input");
        idInput.type = "hidden";
        idInput.name = "id";
        form.appendChild(idInput);
      }
      if (prefill && typeof prefill.id !== "undefined" && prefill.id) {
        idInput.value = String(prefill.id);
      } else {
        idInput.value = "0";
      }

      // Si on ouvre en mode édition, changer l'action envoyée pour appeler le handler update côté serveur
      // Garder l'action "pc_manual_reservation_create" même en mode édition
      // Le handler serveur attend cette action et doit traiter id != 0 comme une mise à jour.
      const actionInput = form.querySelector('input[name="action"]');
      const desiredAction = "pc_manual_reservation_create";
      if (actionInput) {
        actionInput.value = desiredAction;
      } else {
        const a = document.createElement("input");
        a.type = "hidden";
        a.name = "action";
        a.value = desiredAction;
        form.appendChild(a);
      }

      if (prefill) {
        if (dateExperienceInput && prefill.date_experience) {
          dateExperienceInput.value = prefill.date_experience;
        }
        if (adultField && typeof prefill.adultes !== "undefined") {
          adultField.value = prefill.adultes;
        }
        if (childField && typeof prefill.enfants !== "undefined") {
          childField.value = prefill.enfants;
        }
        if (babyField && typeof prefill.bebes !== "undefined") {
          babyField.value = prefill.bebes;
        }
        if (prenomInput) {
          prenomInput.value = prefill.prenom || "";
        }
        if (nomInput) {
          nomInput.value = prefill.nom || "";
        }
        if (emailInput) {
          emailInput.value = prefill.email || "";
        }
        if (telephoneInput) {
          telephoneInput.value = prefill.telephone || "";
        }
        if (commentaireField) {
          commentaireField.value = prefill.commentaire_client || "";
        }
        if (notesField) {
          notesField.value = prefill.notes_internes || "";
        }
        if (numeroDevisInput) {
          numeroDevisInput.value = prefill.numero_devis || "";
        }
        if (
          remiseLabel &&
          typeof prefill.remise_label !== "undefined" &&
          prefill.remise_label !== ""
        ) {
          remiseLabel.value = prefill.remise_label;
        }
        if (
          remiseAmount &&
          typeof prefill.remise_montant !== "undefined" &&
          prefill.remise_montant !== ""
        ) {
          remiseAmount.value = prefill.remise_montant;
        }
        if (
          plusLabel &&
          typeof prefill.plus_label !== "undefined" &&
          prefill.plus_label !== ""
        ) {
          plusLabel.value = prefill.plus_label;
        }
        if (
          plusAmount &&
          typeof prefill.plus_montant !== "undefined" &&
          prefill.plus_montant !== ""
        ) {
          plusAmount.value = prefill.plus_montant;
        }
        if (participantsAdultsField) {
          participantsAdultsField.value =
            prefill.participants &&
            typeof prefill.participants.adultes !== "undefined"
              ? prefill.participants.adultes
              : prefill.adultes || 0;
        }
        if (participantsEnfantsField) {
          participantsEnfantsField.value =
            prefill.participants &&
            typeof prefill.participants.enfants !== "undefined"
              ? prefill.participants.enfants
              : prefill.enfants || 0;
        }
        if (participantsBebesField) {
          participantsBebesField.value =
            prefill.participants &&
            typeof prefill.participants.bebes !== "undefined"
              ? prefill.participants.bebes
              : prefill.bebes || 0;
        }
      }

      if (!prefill) {
        if (participantsAdultsField && adultField) {
          participantsAdultsField.value = adultField.value || "";
        }
        if (participantsEnfantsField && childField) {
          participantsEnfantsField.value = childField.value || "";
        }
        if (participantsBebesField && babyField) {
          participantsBebesField.value = babyField.value || "";
        }
      }

      refreshRemiseButton();
      refreshPlusButton();

      const initialTarifKey = prefill ? prefill.experience_tarif_type : "";
      if (currentType === "experience") {
        populateTarifOptions(initialItemId, initialTarifKey);
        if (tarifSelect && initialTarifKey) {
          tarifSelect.value = initialTarifKey;
        }
        const initialConfig =
          initialItemId && initialTarifKey
            ? getTarifConfig(initialItemId, initialTarifKey)
            : null;
        refreshDynamicSections(initialConfig);
        applyPrefillSelections();
        updateCapacityLimits(null);
      } else {
        if (customSection) {
          customSection.style.display = "none";
        }
        if (optionsSection) {
          optionsSection.style.display = "none";
        }
        toggleParticipantsSection(null);
        prepareLogementConfig(initialItemId, {
          range: pendingLogementRange,
        });
      }

      let storedLines = null;
      if (prefill && prefill.lines_json) {
        if (linesTextarea) {
          linesTextarea.value = prefill.lines_json;
        }
        const parsedLines = parseJSONSafe(prefill.lines_json);
        if (Array.isArray(parsedLines)) {
          storedLines = parsedLines;
        }
      }
      if (
        (!prefillQtyMap || Object.keys(prefillQtyMap).length === 0) &&
        storedLines
      ) {
        prefillQtyMap = deriveQtyMapFromLines(storedLines);
      }

      if (storedLines && summaryBody && summaryTotal) {
        renderStoredLinesSummary(
          storedLines,
          summaryBody,
          summaryTotal,
          prefill ? prefill.montant_total : 0
        );
      }

      if (
        totalInput &&
        prefill &&
        typeof prefill.montant_total !== "undefined"
      ) {
        totalInput.value = parseFloat(prefill.montant_total || 0).toFixed(2);
      }

      function updateQuote() {
        if (!summaryBody || !summaryTotal) {
          return;
        }

        const typeValue = typeSelect ? typeSelect.value : "experience";
        const counts = {
          adultes: parseInt(adultField ? adultField.value : "0", 10) || 0,
          enfants: parseInt(childField ? childField.value : "0", 10) || 0,
          bebes: parseInt(babyField ? babyField.value : "0", 10) || 0,
        };
        if (typeValue !== "location" && capacityWarning) {
          capacityWarning.style.display = "none";
        }

        if (typeValue === "location") {
          enforceCapacity(counts);
          if (
            !logementQuote ||
            typeof logementQuote.calculateQuote !== "function"
          ) {
            summaryBody.innerHTML =
              '<p class="pc-resa-field-hint">Le moteur logement n’est pas chargé.</p>';
            summaryTotal.textContent = "—";
            if (totalInput) totalInput.value = "";
            if (linesTextarea) linesTextarea.value = "";
            return;
          }
          const logementId = itemSelect ? itemSelect.value : "";
          if (!logementId) {
            summaryBody.innerHTML =
              '<p class="pc-resa-field-hint">Sélectionnez un logement.</p>';
            summaryTotal.textContent = "—";
            if (totalInput) totalInput.value = "";
            if (linesTextarea) linesTextarea.value = "";
            return;
          }
          const config =
            logementId === currentLogementId && currentLogementConfig
              ? currentLogementConfig
              : null;
          if (!config) {
            summaryBody.innerHTML =
              '<p class="pc-resa-field-hint">Chargement des données du logement…</p>';
            summaryTotal.textContent = "—";
            if (totalInput) totalInput.value = "";
            if (linesTextarea) linesTextarea.value = "";
            return;
          }
          const arrivalValue = arrivalInput ? arrivalInput.value : "";
          const departValue = departInput ? departInput.value : "";
          if (!arrivalValue || !departValue) {
            summaryBody.innerHTML =
              '<p class="pc-resa-field-hint">Choisissez les dates du séjour.</p>';
            summaryTotal.textContent = "—";
            if (totalInput) totalInput.value = "";
            if (linesTextarea) linesTextarea.value = "";
            return;
          }
          const result = logementQuote.calculateQuote(config, {
            date_arrivee: arrivalValue,
            date_depart: departValue,
            adults: counts.adultes,
            children: counts.enfants,
            infants: counts.bebes,
          });
          if (!result.success) {
            summaryBody.innerHTML = `<p class="pc-resa-field-hint">${escapeHtml(
              result.message || "Sélection invalide."
            )}</p>`;
            summaryTotal.textContent = "—";
            if (totalInput) totalInput.value = "";
            if (linesTextarea) linesTextarea.value = "";
            return;
          }
          applyQuoteToForm({
            result,
            linesTextarea,
            totalInput,
            summaryBody,
            summaryTotal,
            remiseLabel,
            remiseAmount,
            plusLabel,
            plusAmount,
          });
          return;
        }

        const expId = itemSelect ? itemSelect.value : "";
        const tarifKey = tarifSelect ? tarifSelect.value : "";

        if (!expId || !tarifKey) {
          summaryBody.innerHTML =
            '<p class="pc-resa-field-hint">Sélectionnez une expérience et un tarif.</p>';
          summaryTotal.textContent = "—";
          if (totalInput) totalInput.value = "";
          if (linesTextarea) linesTextarea.value = "";
          return;
        }

        const config = getTarifConfig(expId, tarifKey);
        if (!config) {
          summaryBody.innerHTML =
            '<p class="pc-resa-field-hint">Impossible de charger ce tarif (ACF).</p>';
          summaryTotal.textContent = "—";
          return;
        }

        const customQty = getCustomQtyValues();
        const selectedOptions = getSelectedOptions();
        const result = computeQuote(config, counts, {
          customQty,
          options: selectedOptions,
        });
        applyQuoteToForm({
          result,
          linesTextarea,
          totalInput,
          summaryBody,
          summaryTotal,
          remiseLabel,
          remiseAmount,
          plusLabel,
          plusAmount,
        });
      }

      if (typeSelect) {
        typeSelect.addEventListener("change", function () {
          const nextType = this.value || "experience";
          populateItemOptions(nextType);
          setTypeLabel(nextType);
          toggleTypeHints(nextType);
          toggleTypeSections(nextType);
          if (nextType === "experience") {
            const expId = itemSelect ? itemSelect.value : "";
            populateTarifOptions(expId);
            const cfg =
              expId && tarifSelect
                ? getTarifConfig(expId, tarifSelect.value)
                : null;
            refreshDynamicSections(cfg);
            currentLogementId = "";
            currentLogementConfig = null;
            setLogementAvailabilityMessage("");
            initLogementCalendar(null);
            updateCapacityLimits(null);
          } else {
            if (customSection) {
              customSection.style.display = "none";
            }
            if (optionsSection) {
              optionsSection.style.display = "none";
            }
            toggleParticipantsSection(null);
            prepareLogementConfig(itemSelect ? itemSelect.value : "");
          }
          updateQuote();
        });
      }

      if (itemSelect) {
        itemSelect.addEventListener("change", function () {
          const current = typeSelect ? typeSelect.value : "experience";
          if (current === "experience") {
            populateTarifOptions(this.value);
            refreshDynamicSections(null);
            updateCapacityLimits(null);
          } else {
            prepareLogementConfig(this.value);
          }
          console.log(
            "[pc-devis] Sélection logement changée :",
            this.value || "(vide)"
          );
          if (current === "experience") {
            lastExperienceId = this.value || "";
          } else {
            lastLocationId = this.value || "";
          }
          updateQuote();
        });
      }

      if (tarifSelect) {
        tarifSelect.addEventListener("change", function () {
          if (typeSelect && typeSelect.value !== "experience") {
            return;
          }
          const expId = itemSelect ? itemSelect.value : "";
          const cfg = expId ? getTarifConfig(expId, this.value) : null;
          refreshDynamicSections(cfg);
          updateQuote();
        });
      }

      counters.forEach((input) => {
        input.addEventListener("input", updateQuote);
      });

      if (remiseLabel) {
        remiseLabel.addEventListener("input", () => {
          updateQuote();
        });
      }
      if (remiseAmount) {
        remiseAmount.addEventListener("input", () => {
          refreshRemiseButton();
          updateQuote();
        });
        refreshRemiseButton();
      }
      if (remiseClearBtn) {
        remiseClearBtn.addEventListener("click", function () {
          if (remiseLabel) {
            remiseLabel.value = "Remise exceptionnelle";
          }
          if (remiseAmount) {
            remiseAmount.value = "";
          }
          refreshRemiseButton();
          updateQuote();
        });
      }

      [
        participantsAdultsField,
        participantsEnfantsField,
        participantsBebesField,
      ].forEach((field) => {
        if (field) {
          field.addEventListener("input", () => {
            // pas de recalcul du devis
          });
        }
      });

      if (plusLabel) {
        plusLabel.addEventListener("input", () => {
          updateQuote();
        });
      }
      if (plusAmount) {
        plusAmount.addEventListener("input", () => {
          refreshPlusButton();
          updateQuote();
        });
        refreshPlusButton();
      }
      if (plusClearBtn) {
        plusClearBtn.addEventListener("click", function () {
          if (plusLabel) {
            plusLabel.value = "Plus-value";
          }
          if (plusAmount) {
            plusAmount.value = "";
          }
          refreshPlusButton();
          updateQuote();
        });
      }

      form.addEventListener("submit", function (e) {
        e.preventDefault();
        if (!submitBtn) {
          return;
        }
        handleManualCreateSubmit(form, submitBtn);
      });

      if (!storedLines) {
        updateQuote();
      }

      if (sendBtn) {
        sendBtn.addEventListener("click", function () {
          if (typeFluxSelect) {
            typeFluxSelect.value = "devis";
          }
          handleManualCreateSubmit(form, sendBtn);
        });
      }

      return {
        form,
        submitBtn,
        sendBtn,
        typeFluxSelect,
      };
    }

    const openManualCreateModal = (prefillData = null, options = {}) => {
      if (!createTemplate) {
        return null;
      }

      openResaModal(createTemplate.innerHTML);

      const modalContent = document.getElementById("pc-resa-modal-content");
      if (!modalContent) {
        return null;
      }

      const refs = initManualCreateForm(modalContent, prefillData, options);

      const cancelBtn = modalContent.querySelector(".pc-resa-create-cancel");
      if (cancelBtn) {
        cancelBtn.addEventListener("click", function () {
          closeResaModal();
        });
      }

      return refs;
    };

    function openResaModal(html) {
      const modal = document.getElementById("pc-resa-modal");
      const modalContent = document.getElementById("pc-resa-modal-content");

      if (modalContent) {
        modalContent.innerHTML = html;
      }
      if (modal) {
        modal.style.display = "block";
      }
    }

    function closeResaModal() {
      const modal = document.getElementById("pc-resa-modal");
      const modalContent = document.getElementById("pc-resa-modal-content");

      if (modalContent) {
        modalContent.innerHTML = "";
      }
      if (modal) {
        modal.style.display = "none";
      }
    }

    const modalCloseBtn = document.getElementById("pc-resa-modal-close-btn");
    const modalCloseBackdrop = document.getElementById("pc-resa-modal-close");
    if (modalCloseBtn) {
      modalCloseBtn.addEventListener("click", closeResaModal);
    }
    if (modalCloseBackdrop) {
      modalCloseBackdrop.addEventListener("click", closeResaModal);
    }

    // ============================================================
    // DÉCLENCHEUR UNIQUE : OUVERTURE FICHE + CHARGEMENT DOCS
    // ============================================================
    document.addEventListener("click", function (e) {
      // On surveille les clics sur le bouton "Fiche"
      const btn = e.target.closest(".pc-resa-view-link");
      if (!btn) return;

      e.preventDefault();
      const id = btn.getAttribute("data-resa-id");
      console.log("🖱️ Clic détecté sur Fiche #" + id); // PREUVE DE VIE

      // 1. Récupération du HTML caché
      const detailRow = document.querySelector(
        '.pc-resa-dashboard-row-detail[data-resa-id="' + id + '"]'
      );
      if (!detailRow) {
        console.error("❌ Ligne de détail introuvable pour #" + id);
        return;
      }

      const card = detailRow.querySelector(".pc-resa-card");
      if (!card) return;

      // 2. Injection du HTML dans la modale
      openResaModal(card.innerHTML);
      console.log("✅ Modale ouverte");

      // 3. Ré-attachement des événements (boutons actions)
      if (typeof attachQuoteButtons === "function") {
        attachQuoteButtons();
      }

      // 4. LANCEMENT DU CHARGEMENT DES DOCUMENTS (immédiat)
      if (typeof window.pc_reload_documents === "function") {
        window.pc_reload_documents(id);
      } else {
        console.error(
          "❌ Erreur : La fonction pc_reload_documents est absente"
        );
      }
    });

    // Ouverture automatique du popup si on arrive depuis le calendrier
    if (typeof window !== "undefined" && window.sessionStorage) {
      const key = "pc_resa_from_calendar";
      const raw = window.sessionStorage.getItem(key);

      if (raw) {
        try {
          const parsed = JSON.parse(raw);
          window.sessionStorage.removeItem(key);

          if (parsed && typeof parsed === "object") {
            const prefill = {
              type: "location",
              item_id: parsed.logementId || "",
              date_arrivee: parsed.start || "",
              date_depart: parsed.end || "",
            };

            openManualCreateModal(prefill, {
              context: "from_calendar",
            });
          }
        } catch (error) {
          // eslint-disable-next-line no-console
          console.error(
            "[pc-reservations] erreur prefill depuis calendrier",
            error
          );
          window.sessionStorage.removeItem(key);
        }
      }
    }

    const attachQuoteButtons = () => {
      document.querySelectorAll(".pc-resa-edit-quote").forEach((btn) => {
        btn.addEventListener("click", function (e) {
          e.preventDefault();
          const rawData = this.getAttribute("data-prefill");
          const payload = parseJSONSafe(rawData);
          if (!payload) {
            alert("Impossible de charger les données du devis.");
            return;
          }
          openManualCreateModal(payload, {
            context: "edit",
          });
        });
      });

      document.querySelectorAll(".pc-resa-resend-quote").forEach((btn) => {
        btn.addEventListener("click", function (e) {
          e.preventDefault();
          const reservationId = this.getAttribute("data-resa-id") || "";
          console.log("[pc-reservations] Renvoyer devis TODO", reservationId);
        });
      });
    };

    attachQuoteButtons();
    // --- DEBUT DU NOUVEAU CODE (Bouton Lien Stripe) ---
    document.addEventListener("click", function (e) {
      const btn = e.target.closest(".pc-resa-payment-generate-link");
      if (btn) {
        e.preventDefault();
        const paymentId = btn.getAttribute("data-payment-id");
        const originalText = btn.textContent;

        if (btn.disabled) return;

        btn.textContent = "⏳ Création...";
        btn.disabled = true;

        const formData = new URLSearchParams();
        formData.append("action", "pc_stripe_get_link");
        formData.append("nonce", pcResaManualNonce);
        formData.append("payment_id", paymentId);

        fetch(pcResaAjaxUrl, {
          method: "POST",
          body: formData,
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.success && data.data.url) {
              navigator.clipboard.writeText(data.data.url).then(() => {
                btn.textContent = "✅ Lien copié !";
                btn.style.color = "#16a34a";
                setTimeout(() => {
                  btn.textContent = "🔗 Générer nouveau lien";
                  btn.style.color = "#4f46e5";
                  btn.disabled = false;
                }, 3000);
              });
            } else {
              alert(
                "Erreur : " +
                  (data.data && data.data.message
                    ? data.data.message
                    : "Impossible de créer le lien.")
              );
              btn.textContent = originalText;
              btn.disabled = false;
            }
          })
          .catch((err) => {
            console.error(err);
            alert("Erreur technique.");
            btn.textContent = originalText;
            btn.disabled = false;
          });
      }
    });

    // --- GESTION CAUTION (Empreinte) ---
    document.addEventListener("click", function (e) {
      const btn = e.target.closest(".pc-resa-caution-generate");
      if (btn) {
        e.preventDefault();
        const resaId = btn.getAttribute("data-resa-id");
        const originalText = btn.textContent;

        if (btn.disabled) return;
        btn.textContent = "⏳ ...";
        btn.disabled = true;

        const formData = new URLSearchParams();
        formData.append("action", "pc_stripe_get_caution_link");
        formData.append("nonce", pcResaManualNonce);
        formData.append("reservation_id", resaId);

        fetch(pcResaAjaxUrl, {
          method: "POST",
          body: formData,
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.success && data.data.url) {
              navigator.clipboard.writeText(data.data.url).then(() => {
                btn.textContent = "✅ Copié !";
                btn.style.color = "#16a34a";
                setTimeout(() => {
                  btn.textContent = originalText;
                  btn.style.color = "#4f46e5";
                  btn.disabled = false;
                  // Recharger la page pour voir le changement de statut
                  window.location.reload();
                }, 1500);
              });
            } else {
              alert(
                "Erreur : " +
                  (data.data && data.data.message
                    ? data.data.message
                    : "Impossible.")
              );
              btn.textContent = originalText;
              btn.disabled = false;
            }
          })
          .catch((err) => {
            console.error(err);
            alert("Erreur technique.");
            btn.textContent = originalText;
            btn.disabled = false;
          });
      }
    });

    // --- GESTION ACTIONS CAUTION (Libérer / Encaisser) ---
    document.addEventListener("click", function (e) {
      const btn = e.target.closest(".pc-resa-caution-action");
      if (!btn) return;

      e.preventDefault();
      const action = btn.dataset.action;
      const id = btn.dataset.id;
      const ref = btn.dataset.ref;

      if (!ref) {
        alert("Référence Stripe manquante.");
        return;
      }

      // 1. LIBÉRATION (Pas de changement, simple confirm)
      if (action === "release") {
        if (
          !confirm(
            "Voulez-vous libérer (annuler) cette caution maintenant ?\nAction irréversible."
          )
        )
          return;

        btn.disabled = true;
        btn.textContent = "...";

        const formData = new URLSearchParams();
        formData.append("action", "pc_stripe_release_caution");
        formData.append("nonce", pcResaManualNonce);
        formData.append("reservation_id", id);
        formData.append("ref", ref);

        fetch(pcResaAjaxUrl, {
          method: "POST",
          body: formData,
        })
          .then((r) => r.json())
          .then((data) => {
            if (data.success) {
              window.location.reload();
            } else {
              alert("Erreur : " + data.data.message);
              btn.disabled = false;
              btn.textContent = "Libérer";
            }
          });
      }

      // 2. ENCAISSEMENT (Ouverture Popup)
      else if (action === "capture") {
        const max = btn.dataset.max;
        const popup = document.getElementById("pc-capture-caution-popup");

        // Remplissage du popup
        document.getElementById("pc-capture-resa-id").value = id;
        document.getElementById("pc-capture-ref").value = ref;
        document.getElementById("pc-capture-max-display").textContent = max;
        document.getElementById("pc-capture-amount").value = max; // Par défaut tout le montant
        document.getElementById("pc-capture-amount").max = max;
        document.getElementById("pc-capture-note").value = ""; // Reset note

        popup.hidden = false;
      }
    });

    // --- SOUMISSION DU POPUP D'ENCAISSEMENT ---
    const captureConfirmBtn = document.getElementById("pc-capture-confirm-btn");
    if (captureConfirmBtn) {
      captureConfirmBtn.addEventListener("click", function () {
        const id = document.getElementById("pc-capture-resa-id").value;
        const ref = document.getElementById("pc-capture-ref").value;
        const amount = parseFloat(
          document.getElementById("pc-capture-amount").value
        );
        const note = document.getElementById("pc-capture-note").value;
        const max = parseFloat(
          document.getElementById("pc-capture-amount").max
        );

        if (isNaN(amount) || amount <= 0 || amount > max) {
          alert("Montant invalide (Max: " + max + "€)");
          return;
        }
        if (!note.trim()) {
          if (!confirm("Voulez-vous vraiment encaisser sans mettre de motif ?"))
            return;
        }

        const btn = this;
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = "Encaissement...";

        const formData = new URLSearchParams();
        formData.append("action", "pc_stripe_capture_caution");
        formData.append("nonce", pcResaManualNonce);
        formData.append("reservation_id", id);
        formData.append("ref", ref);
        formData.append("amount", amount);
        formData.append("note", note); // On envoie la note

        fetch(pcResaAjaxUrl, {
          method: "POST",
          body: formData,
        })
          .then((r) => r.json())
          .then((data) => {
            if (data.success) {
              // Plus d'alerte, rechargement direct pour voir la note
              window.location.reload();
            } else {
              alert("Erreur : " + (data.data.message || "Inconnue"));
              btn.disabled = false;
              btn.textContent = originalText;
            }
          })
          .catch((err) => {
            console.error(err);
            alert("Erreur technique.");
            btn.disabled = false;
            btn.textContent = originalText;
          });
      });
    }

    // --- GESTION ROTATION CAUTION (Renouvellement) ---

    // 1. Clic sur le bouton "Renouveler" -> Ouverture Popup
    document.addEventListener("click", function (e) {
      const btn = e.target.closest(".pc-resa-caution-rotate");
      if (!btn) return;

      e.preventDefault();
      const id = btn.dataset.id;
      const ref = btn.dataset.ref;

      document.getElementById("pc-rotate-resa-id").value = id;
      document.getElementById("pc-rotate-ref").value = ref;

      document.getElementById("pc-rotate-caution-popup").hidden = false;
    });

    // 2. Confirmation dans le Popup -> Appel AJAX
    const rotateConfirmBtn = document.getElementById("pc-rotate-confirm-btn");
    if (rotateConfirmBtn) {
      rotateConfirmBtn.addEventListener("click", function () {
        const id = document.getElementById("pc-rotate-resa-id").value;
        const ref = document.getElementById("pc-rotate-ref").value;

        const btn = this;
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = "Traitement en cours...";

        const formData = new URLSearchParams();
        formData.append("action", "pc_stripe_rotate_caution");
        formData.append("nonce", pcResaManualNonce);
        formData.append("reservation_id", id);
        formData.append("old_ref", ref);

        fetch(pcResaAjaxUrl, {
          method: "POST",
          body: formData,
        })
          .then((r) => r.json())
          .then((data) => {
            if (data.success) {
              // Succès : on recharge pour voir la nouvelle réf et la note interne
              window.location.reload();
            } else {
              alert("Erreur : " + (data.data.message || "Inconnue"));
              btn.disabled = false;
              btn.textContent = originalText;
            }
          })
          .catch((err) => {
            console.error(err);
            alert("Erreur technique (voir console).");
            btn.disabled = false;
            btn.textContent = originalText;
          });
      });
    }
  }); // <--- C'EST ICI QUE CA MANQUAIT (Fermeture du DOMContentLoaded)

  // --- GESTION DES POPUPS (Annulation / Confirmation) ---
  document.addEventListener("click", function (event) {
    /* --- OUVERTURE DU POPUP ANNULATION --- */
    const cancelBtn = event.target.closest(".pc-resa-action-cancel-booking");
    if (cancelBtn) {
      event.preventDefault();
      const id = cancelBtn.dataset.reservationId;
      const popup = document.getElementById("pc-cancel-reservation-popup");
      popup.dataset.resaId = id;
      popup.hidden = false;
      return;
    }

    /* --- FERMETURE DU POPUP --- */
    if (event.target.matches("[data-pc-popup-close]")) {
      document.getElementById("pc-cancel-reservation-popup").hidden = true;
      return;
    }

    /* --- CONFIRMATION ANNULATION (Action réelle) --- */
    if (event.target.matches("[data-pc-popup-confirm]")) {
      const popup = document.getElementById("pc-cancel-reservation-popup");
      const id = popup.dataset.resaId;
      const body = new URLSearchParams();
      body.append("action", "pc_cancel_reservation");
      body.append("nonce", pcResaManualNonce);
      body.append("reservation_id", id);

      fetch(pcResaAjaxUrl, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: body.toString(),
      })
        .then((r) => r.json())
        .then((json) => {
          if (!json || !json.success) {
            console.error(
              json && json.data && json.data.message
                ? json.data.message
                : "Erreur."
            );
            return;
          }
          popup.hidden = true;
          window.location.reload();
        })
        .catch(() => {
          console.error("Erreur réseau.");
        });
    }

    /* --- CONFIRMATION RÉSERVATION (Passer de Devis à Confirmé) --- */
    const confirmBtn = event.target.closest(".pc-resa-action-confirm-booking");
    if (confirmBtn) {
      event.preventDefault();
      const id = confirmBtn.dataset.reservationId;
      if (
        !confirm(
          "Confirmer cette réservation ? Elle apparaîtra dans le calendrier."
        )
      )
        return;

      const originalText = confirmBtn.textContent;
      confirmBtn.textContent = "...";
      confirmBtn.disabled = true;

      const body = new URLSearchParams();
      body.append("action", "pc_confirm_reservation");
      body.append("nonce", pcResaManualNonce);
      body.append("reservation_id", id);

      fetch(pcResaAjaxUrl, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: body.toString(),
      })
        .then((r) => r.json())
        .then((json) => {
          if (json && json.success) {
            window.location.reload();
          } else {
            alert(
              json.data && json.data.message ? json.data.message : "Erreur."
            );
            confirmBtn.textContent = originalText;
            confirmBtn.disabled = false;
          }
        })
        .catch((err) => {
          console.error(err);
          alert("Erreur réseau.");
          confirmBtn.textContent = originalText;
          confirmBtn.disabled = false;
        });
    }

    // ============================================================
    //  NOUVELLE GESTION MESSAGERIE (Templates + Libre + Popups)
    // ============================================================

    // 1. Écouteur global pour les clics (Délégation)
    document.addEventListener("click", function (event) {
      // --- A. OUVERTURE POPUP "ENVOYER UN MESSAGE" ---
      const msgBtn = event.target.closest(".pc-resa-open-msg-modal");
      if (msgBtn) {
        event.preventDefault();

        // Remplissage des champs cachés
        document.getElementById("pc-msg-resa-id").value = msgBtn.dataset.resaId;
        document.getElementById("pc-msg-client-name").textContent =
          msgBtn.dataset.client;

        // Réinitialisation de l'interface
        const tplSelect = document.getElementById("pc-msg-template");
        tplSelect.value = "";
        document.getElementById("pc-msg-custom-area").style.display = "none";
        document.getElementById("pc-msg-template-hint").style.display = "block";

        const feedback = document.getElementById("pc-msg-feedback");
        feedback.style.display = "none";
        feedback.className = "";

        // Affichage Popup
        document.getElementById("pc-send-message-popup").hidden = false;
        return;
      }

      // --- B. OUVERTURE POPUP "VOIR PLUS" (Lecture) ---
      const seeMoreBtn = event.target.closest(
        '[data-action="view-full-message"]'
      );
      if (seeMoreBtn) {
        event.preventDefault();
        const content = seeMoreBtn.getAttribute("data-content");

        // Injection du contenu dans la modale de lecture
        const viewer = document.getElementById("pc-read-message-content");
        viewer.innerHTML = content; // Affiche le HTML (br, p...)

        document.getElementById("pc-read-message-popup").hidden = false;
        return;
      }
    });

    // 2. Gestion du switch "Template" vs "Message Libre"
    const tplSelect = document.getElementById("pc-msg-template");
    if (tplSelect) {
      tplSelect.addEventListener("change", function () {
        const isCustom = this.value === "custom";
        document.getElementById("pc-msg-custom-area").style.display = isCustom
          ? "block"
          : "none";
        document.getElementById("pc-msg-template-hint").style.display = isCustom
          ? "none"
          : "block";
      });
    }

    // 3. Envoi du Message (Avec protection anti-doublon radicale)
    const msgSendBtn = document.getElementById("pc-msg-send-btn");
    const feedbackBox = document.getElementById("pc-msg-feedback");

    if (msgSendBtn) {
      // Astuce : On clone le bouton pour tuer tous les anciens écouteurs parasites (responsables de l'envoi x5)
      const newBtn = msgSendBtn.cloneNode(true);
      msgSendBtn.parentNode.replaceChild(newBtn, msgSendBtn);

      newBtn.addEventListener("click", function (e) {
        e.preventDefault();

        // Protection visuelle
        if (this.disabled) return;

        // Récupération des valeurs
        const id = document.getElementById("pc-msg-resa-id").value;
        const templateId = document.getElementById("pc-msg-template").value;
        const customSubject = document.getElementById(
          "pc-msg-custom-subject"
        ).value;
        const customBody = document.getElementById("pc-msg-custom-body").value;

        // Validation
        if (!templateId) {
          showFeedback(
            "⚠️ Veuillez choisir un modèle ou 'Nouveau message'.",
            false
          );
          return;
        }
        if (
          templateId === "custom" &&
          (!customSubject.trim() || !customBody.trim())
        ) {
          showFeedback("⚠️ Le sujet et le message sont obligatoires.", false);
          return;
        }

        // UI Loading
        const originalText = this.textContent;
        this.textContent = "Envoi en cours...";
        this.disabled = true;
        feedbackBox.style.display = "none";

        // Préparation Données
        const formData = new URLSearchParams();
        formData.append("action", "pc_send_message");
        formData.append("nonce", pcResaManualNonce);
        formData.append("reservation_id", id);
        formData.append("template_id", templateId);

        if (templateId === "custom") {
          formData.append("custom_subject", customSubject);
          formData.append("custom_body", customBody);
        }

        // Envoi AJAX
        fetch(pcResaAjaxUrl, {
          method: "POST",
          body: formData,
        })
          .then((r) => r.json())
          .then((data) => {
            if (data.success) {
              showFeedback("✅ Message envoyé !", true);
              setTimeout(() => {
                document.getElementById("pc-send-message-popup").hidden = true;
                window.location.reload();
              }, 1000);
            } else {
              throw new Error(data.data.message || "Erreur inconnue");
            }
          })
          .catch((err) => {
            console.error(err);
            showFeedback("❌ Erreur : " + (err.message || "Technique"), false);
            this.textContent = originalText;
            this.disabled = false;
          });
      });
    }

    // Helper pour afficher les messages dans la popup
    function showFeedback(msg, isSuccess) {
      if (!feedbackBox) return;
      feedbackBox.textContent = msg;
      feedbackBox.style.background = isSuccess ? "#dcfce7" : "#fee2e2";
      feedbackBox.style.color = isSuccess ? "#15803d" : "#b91c1c";
      feedbackBox.style.display = "block";
    }

    // ============================================================
    //  GESTION DES DOCUMENTS PDF & FACTURATION
    // ============================================================

    const parseServerJson = (rawText) => {
      if (!rawText) {
        return null;
      }
      const jsonStart = rawText.indexOf("{");
      const cleanText = jsonStart >= 0 ? rawText.slice(jsonStart) : rawText;
      try {
        return JSON.parse(cleanText);
      } catch (error) {
        console.error("[pc-documents] JSON invalide", error, rawText);
        return null;
      }
    };

    const setDocumentsLoading = (container) => {
      const tbody = container.querySelector(".pc-docs-tbody");
      if (!tbody) {
        return;
      }
      tbody.innerHTML =
        '<tr><td colspan="4" style="text-align:center; padding:15px; color:#2271b1;">Chargement...</td></tr>';
    };

    const renderDocumentsRows = (container, documents) => {
      const tbody = container.querySelector(".pc-docs-tbody");
      if (!tbody) {
        return;
      }

      if (!Array.isArray(documents) || documents.length === 0) {
        tbody.innerHTML =
          '<tr><td colspan="4" style="text-align:center; padding:15px; color:#999;">Aucun document.</td></tr>';
        return;
      }

      const rows = documents
        .map(
          (doc) =>
            `<tr>
              <td style="padding:8px;">${doc.type_doc || ""}</td>
              <td style="padding:8px;">${doc.nom_fichier || ""}</td>
              <td style="padding:8px;">${doc.date_creation || ""}</td>
              <td style="padding:8px; text-align:right;"><a href="${
                doc.url_fichier
              }" target="_blank" rel="noopener">👁️ Voir</a></td>
            </tr>`
        )
        .join("");
      tbody.innerHTML = rows;
    };

    const showDocumentsError = (container, message) => {
      const tbody = container.querySelector(".pc-docs-tbody");
      if (!tbody) {
        return;
      }
      tbody.innerHTML = `<tr><td colspan="4" style="color:red; text-align:center;">${escapeHtml(
        message || "Erreur serveur"
      )}</td></tr>`;
    };

    window.pc_reload_documents = function (reservationId) {
      if (!reservationId) {
        return Promise.resolve(null);
      }

      const modalContent = document.getElementById("pc-resa-modal-content");
      if (!modalContent) {
        return Promise.resolve(null);
      }

      const container = modalContent.querySelector(
        '.pc-documents-list-container[data-resa-id="' + reservationId + '"]'
      );
      if (!container) {
        return Promise.resolve(null);
      }

      setDocumentsLoading(container);

      const formData = new URLSearchParams();
      formData.append("action", "pc_get_documents_list");
      formData.append("reservation_id", reservationId);
      formData.append("nonce", pcResaManualNonce);

      return fetch(pcResaAjaxUrl, {
        method: "POST",
        body: formData,
      })
        .then((response) => response.text())
        .then((rawText) => {
          const payload = parseServerJson(rawText);
          if (!payload || !payload.success) {
            const message =
              (payload &&
                payload.data &&
                (payload.data.message || payload.data.error)) ||
              "Erreur lors du chargement des documents.";
            showDocumentsError(container, message);
            return null;
          }

          renderDocumentsRows(container, payload.data);
          return payload;
        })
        .catch((error) => {
          console.error("[pc-documents] Erreur de chargement", error);
          showDocumentsError(
            container,
            "Erreur technique pendant le chargement des documents."
          );
          return null;
        });
    };

    const openPdfPreview = (url) => {
      const modal = document.getElementById("pc-pdf-preview-modal");
      const iframe = document.getElementById("pc-pdf-iframe");

      if (modal && iframe) {
        iframe.src = url;
        modal.style.display = "flex";
        return;
      }

      window.open(url, "_blank");
    };

    const closePdfPreview = () => {
      const modal = document.getElementById("pc-pdf-preview-modal");
      const iframe = document.getElementById("pc-pdf-iframe");

      if (modal) {
        modal.style.display = "none";
      }
      if (iframe) {
        iframe.src = "";
      }
    };

    document.addEventListener("click", function (e) {
      const btn = e.target.closest(".pc-btn-generate-doc");
      if (!btn || btn.disabled) {
        return;
      }

      e.preventDefault();

      const wrapper = btn.closest(".pc-doc-actions");
      const reservationId = btn.getAttribute("data-resa-id");
      const templateSelect = wrapper
        ? wrapper.querySelector(".pc-doc-template-select")
        : null;
      const forceCheckbox = wrapper
        ? wrapper.querySelector(".pc-doc-force-regen")
        : null;

      if (!reservationId || !templateSelect) {
        console.error("[pc-documents] Contexte génération incomplet");
        return;
      }

      if (!templateSelect.value) {
        alert("⚠️ Veuillez sélectionner un modèle.");
        templateSelect.focus();
        templateSelect.style.borderColor = "#ef4444";
        return;
      }
      templateSelect.style.borderColor = "#ccc";

      const originalContent = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML =
        '<span class="spinner is-active" style="float:none;margin:0;"></span>';

      const formData = new FormData();
      formData.append("action", "pc_generate_document");
      formData.append("reservation_id", reservationId);
      formData.append("template_id", templateSelect.value);
      formData.append(
        "force",
        forceCheckbox && forceCheckbox.checked ? "true" : "false"
      );
      formData.append("nonce", pcResaManualNonce);

      fetch(pcResaAjaxUrl, {
        method: "POST",
        body: formData,
      })
        .then((response) => response.text())
        .then((rawText) => {
          const payload = parseServerJson(rawText);
          if (
            !payload ||
            !payload.success ||
            !payload.data ||
            !payload.data.url
          ) {
            const message =
              (payload &&
                payload.data &&
                (payload.data.message || payload.data.error)) ||
              "Impossible de générer le document.";
            throw new Error(message);
          }

          window.pc_reload_documents(reservationId);
          openPdfPreview(payload.data.url);
        })
        .catch((error) => {
          console.error("[pc-documents] Erreur génération", error);
          alert("❌ " + (error.message || "Erreur technique."));
        })
        .finally(() => {
          btn.disabled = false;
          btn.innerHTML = originalContent;
        });
    });

    const closePdfBtn = document.getElementById("pc-close-pdf-modal");
    if (closePdfBtn) {
      closePdfBtn.addEventListener("click", closePdfPreview);
    }

    const pdfModal = document.getElementById("pc-pdf-preview-modal");
    if (pdfModal) {
      pdfModal.addEventListener("click", function (e) {
        if (e.target === this) {
          closePdfPreview();
        }
      });
    }
  });
})();
