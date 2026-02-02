(function () {
  // Initialisation du Namespace PCR
  window.PCR = window.PCR || {};

  // ============================================================
  // 1. VARIABLES D'ÉTAT LOCALES (CACHE)
  // ============================================================
  const logementConfigCache = {};
  const logementConfigPromises = {};

  // ============================================================
  // 3. WRAPPERS POUR LE MODULE PRICING (Compatibilité)
  // ============================================================
  // Ces fonctions permettent au code existant de continuer à fonctionner
  // sans devoir chercher/remplacer partout "window.PCR.Pricing..."
  const getTarifConfig = (expId, key) => {
    return window.PCR?.Pricing?.getTarifConfig(expId, key) || null;
  };

  const populateTarifOptions = (expId, selectedKey = "") => {
    if (window.PCR?.Pricing?.populateTarifOptions) {
      window.PCR.Pricing.populateTarifOptions(expId, selectedKey);
    }
  };

  const computeQuote = (config, counts, extras = {}) => {
    if (window.PCR?.Pricing?.computeQuote) {
      return window.PCR.Pricing.computeQuote(config, counts, extras);
    }
    return { lines: [], html: "", total: 0, isSurDevis: false };
  };

  const applyQuoteToForm = (args) => {
    if (window.PCR?.Pricing?.applyQuoteToForm) {
      window.PCR.Pricing.applyQuoteToForm(args);
    }
  };

  // ============================================================
  // 4. LOGIQUE MÉTIER (Extract du Core)
  // ============================================================

  const fetchLogementConfig = (logementId) => {
    // LAZY LOAD : Récupération des params au moment de l'appel
    const params = window.pcResaParams || {};
    const pcResaAjaxUrl = params.ajaxUrl || "";
    const pcResaManualNonce = params.manualNonce || "";
    const translate = (key, fallback) => params.translations?.[key] || fallback;

    if (!pcResaAjaxUrl) {
      return Promise.reject(
        new Error("AJAX URL manquante - pcResaParams non chargé"),
      );
    }

    if (!pcResaManualNonce) {
      return Promise.reject(
        new Error("Nonce manquant - vérifiez wp_localize_script"),
      );
    }

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
    console.log("[pc-booking-form] Demande config logement :", cacheKey);

    const promise = fetch(pcResaAjaxUrl, {
      method: "POST",
      body: formData,
      credentials: "include", // 🔑 SOLUTION : Force l'envoi des cookies de session
      headers: {
        "X-Requested-With": "XMLHttpRequest",
      },
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
        logementConfigCache[cacheKey] = payload.data.config;
        return payload.data.config;
      })
      .finally(() => {
        delete logementConfigPromises[cacheKey];
      });
    logementConfigPromises[cacheKey] = promise;
    return promise;
  };

  async function handleManualCreateSubmit(form, submitBtn) {
    // LAZY LOAD : Récupération des params au moment de la soumission
    const params = window.pcResaParams || {};
    const pcResaAjaxUrl = params.ajaxUrl || "";
    const pcResaManualNonce = params.manualNonce || "";

    const formData = new FormData(form);
    formData.set(
      "action",
      formData.get("action") || "pc_manual_reservation_create",
    );
    formData.set("nonce", pcResaManualNonce);

    const participantsAdults = form.querySelector(
      'input[name="participants_adultes"]',
    );
    const participantsEnfants = form.querySelector(
      'input[name="participants_enfants"]',
    );
    const participantsBebes = form.querySelector(
      'input[name="participants_bebes"]',
    );
    const participantsEnabled =
      form.getAttribute("data-participants-enabled") === "1";

    if (participantsEnabled) {
      if (participantsAdults && participantsAdults.value !== "") {
        formData.set(
          "adultes",
          parseInt(participantsAdults.value || "0", 10) || 0,
        );
      }
      if (participantsEnfants && participantsEnfants.value !== "") {
        formData.set(
          "enfants",
          parseInt(participantsEnfants.value || "0", 10) || 0,
        );
      }
      if (participantsBebes && participantsBebes.value !== "") {
        formData.set(
          "bebes",
          parseInt(participantsBebes.value || "0", 10) || 0,
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
        credentials: "include", // 🔑 SOLUTION : Force l'envoi des cookies de session
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      });

      const responseText = await response.text();

      if (!response.ok) {
        console.error(
          "Manual creation HTTP error",
          response.status,
          responseText,
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
          userMessage = "Session expirée. Reconnectez-vous.";
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
    // LAZY LOAD : C'est ici que tes listes (options) sont chargées correctement !
    const params = window.pcResaParams || {};
    const experienceOptions = params.experienceOptions || {};
    const logementOptions = params.logementOptions || {};
    const logementQuote =
      params.logementQuote || window.PCLogementDevis || null;

    const form = container.querySelector(".pc-resa-create-form");
    if (!form) return null;

    // SÉLECTEURS
    const submitBtn = form.querySelector(".pc-resa-create-submit");
    const sendBtn = form.querySelector(".pc-resa-create-send");
    const typeSelect = form.querySelector('select[name="type"]');
    const typeFluxSelect = form.querySelector('select[name="type_flux"]');
    const sourceSelect = form.querySelector('select[name="source"]');
    const itemSelect = form.querySelector('select[name="item_id"]');
    const tarifSelect = form.querySelector(
      'select[name="experience_tarif_type"]',
    );
    const linesTextarea = form.querySelector('textarea[name="lines_json"]');
    const totalInput = form.querySelector('input[name="montant_total"]');
    const summaryBody = container.querySelector("[data-quote-summary]");
    const summaryTotal = container.querySelector("[data-quote-total]");
    const remiseLabel = form.querySelector('input[name="remise_label"]');
    const remiseAmount = form.querySelector('input[name="remise_montant"]');
    const remiseClearBtn = form.querySelector(".pc-resa-remise-clear");

    // Champs Participants
    const participantsAdultsField = form.querySelector(
      'input[name="participants_adultes"]',
    );
    const participantsEnfantsField = form.querySelector(
      'input[name="participants_enfants"]',
    );
    const participantsBebesField = form.querySelector(
      'input[name="participants_bebes"]',
    );
    const participantsSection = form.querySelector(
      "[data-participants-section]",
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
      'input[name="date_experience"]',
    );

    // Champs Client
    const prenomInput = form.querySelector('input[name="prenom"]');
    const nomInput = form.querySelector('input[name="nom"]');
    const emailInput = form.querySelector('input[name="email"]');
    const telephoneInput = form.querySelector('input[name="telephone"]');
    const commentaireField = form.querySelector(
      'textarea[name="commentaire_client"]',
    );
    const notesField = form.querySelector('textarea[name="notes_internes"]');
    const numeroDevisInput = form.querySelector('input[name="numero_devis"]');

    // Champs Comptage Global
    const adultField = form.querySelector('input[name="adultes"]');
    const childField = form.querySelector('input[name="enfants"]');
    const babyField = form.querySelector('input[name="bebes"]');

    // UI Helpers
    const typeLabel = form.querySelector("[data-item-label]");
    const typeHints = container.querySelectorAll("[data-type-hint]");
    const typeToggleNodes = container.querySelectorAll("[data-type-toggle]");
    const logementRangeInput = form.querySelector("[data-logement-range]");
    const arrivalInput = form.querySelector('input[name="date_arrivee"]');
    const departInput = form.querySelector('input[name="date_depart"]');
    const logementAvailability = form.querySelector(
      "[data-logement-availability]",
    );

    if (form) form.setAttribute("data-participants-enabled", "0");

    const prefill = prefillData || null;
    let logementCalendar = null;
    let pendingLogementRange = null;
    let currentLogementId = "";
    let currentLogementConfig = null;
    let lastExperienceId = "";
    let lastLocationId = "";

    // --- HELPERS INTERNES ---
    const formatYMD = (date) => {
      if (!(date instanceof Date)) return "";
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, "0");
      const day = String(date.getDate()).padStart(2, "0");
      return `${year}-${month}-${day}`;
    };

    const setTypeLabel = (currentType) => {
      if (typeLabel)
        typeLabel.textContent =
          currentType === "location" ? "Logement" : "Expérience";
    };

    const toggleTypeHints = (currentType) => {
      typeHints.forEach((hint) => {
        const expected = hint.getAttribute("data-type-hint");
        if (expected)
          hint.style.display = expected === currentType ? "" : "none";
      });
    };

    const ensureCountersVisible = () => {
      if (countersWrapper) countersWrapper.style.display = "";
    };

    const toggleTypeSections = (currentType) => {
      typeToggleNodes.forEach((node) => {
        const expected = node.getAttribute("data-type-toggle");
        if (expected)
          node.style.display = expected === currentType ? "" : "none";
      });
      if (currentType === "location") ensureCountersVisible();
    };

    const populateItemOptions = (currentType, selectedId) => {
      if (!itemSelect) return;
      const source =
        currentType === "location" ? logementOptions : experienceOptions;
      const placeholder =
        currentType === "location"
          ? "Sélectionnez un logement"
          : "Sélectionnez une expérience";

      let html = `<option value=\"\">${placeholder}</option>`;
      Object.keys(source || {}).forEach((id) => {
        const label = source[id] || "";
        if (label) {
          // Utilisation de PCR.Utils si dispo, sinon fallback simple
          const safeLabel = window.PCR.Utils
            ? window.PCR.Utils.escapeHtml(label)
            : label;
          html += `<option value=\"${id}\">${safeLabel}</option>`;
        }
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

      if (currentType === "location") lastLocationId = itemSelect.value || "";
      else lastExperienceId = itemSelect.value || "";

      if (currentType !== "experience" && tarifSelect) {
        tarifSelect.value = "";
        tarifSelect.disabled = true;
      } else if (tarifSelect) {
        tarifSelect.disabled = false;
      }
    };

    const setLogementAvailabilityMessage = (message) => {
      if (logementAvailability)
        logementAvailability.textContent = message || "";
    };

    const destroyLogementCalendar = () => {
      if (logementCalendar && typeof logementCalendar.destroy === "function") {
        logementCalendar.destroy();
      }
      logementCalendar = null;
    };

    const initLogementCalendar = (
      config,
      rangeToApply = null,
      clearExisting = true,
    ) => {
      if (!logementRangeInput) return;
      destroyLogementCalendar();

      if (clearExisting) {
        logementRangeInput.value = "";
        if (arrivalInput) arrivalInput.value = "";
        if (departInput) departInput.value = "";
      }

      logementRangeInput.disabled = !config;
      if (!config) return;

      const disableRanges = Array.isArray(config.icsDisable)
        ? config.icsDisable.filter((range) => range && range.from && range.to)
        : [];

      const bootCalendar = () => {
        if (typeof window.flatpickr !== "function") {
          setTimeout(bootCalendar, 150);
          return;
        }
        if (window.flatpickr.l10ns && window.flatpickr.l10ns.fr) {
          window.flatpickr.localize(window.flatpickr.l10ns.fr);
        }
        console.log("[pc-booking-form] INIT FLATPICKR (Mode Permissif)");

        logementCalendar = window.flatpickr(logementRangeInput, {
          mode: "range",
          dateFormat: "d/m/Y",
          altInput: false,
          minDate: "today",
          onDayCreate: function (dObj, dStr, fp, dayElem) {
            const dateYMD = formatYMD(dayElem.dateObj);
            const isBlocked = disableRanges.some(
              (range) => dateYMD >= range.from && dateYMD <= range.to,
            );
            if (isBlocked) {
              dayElem.style.backgroundColor = "#fee2e2";
              dayElem.style.color = "#b91c1c";
              dayElem.style.textDecoration = "line-through";
              dayElem.title = "Période occupée (Forçage possible)";
            }
          },
          onChange(selectedDates) {
            if (selectedDates.length === 2) {
              const startYMD = formatYMD(selectedDates[0]);
              const endYMD = formatYMD(selectedDates[1]);
              if (arrivalInput) arrivalInput.value = startYMD;
              if (departInput) departInput.value = endYMD;

              let hasOverlap = false;
              if (disableRanges.length > 0) {
                hasOverlap = disableRanges.some(
                  (range) => startYMD <= range.to && endYMD >= range.from,
                );
              }

              if (hasOverlap) {
                const popup = document.getElementById("pc-overlap-popup");
                if (popup) {
                  popup.hidden = false;
                  const btnCancel =
                    document.getElementById("pc-overlap-cancel");
                  if (btnCancel)
                    btnCancel.onclick = () => {
                      logementCalendar.clear();
                      popup.hidden = true;
                    };
                  const btnConfirm =
                    document.getElementById("pc-overlap-confirm");
                  if (btnConfirm)
                    btnConfirm.onclick = () => {
                      popup.hidden = true;
                    };
                }
              }
            } else {
              if (arrivalInput) arrivalInput.value = "";
              if (departInput) departInput.value = "";
            }
            updateQuote();
          },
        });

        if (
          rangeToApply &&
          Array.isArray(rangeToApply) &&
          rangeToApply.length === 2
        ) {
          logementCalendar.setDate(rangeToApply, true, "Y-m-d");
          if (arrivalInput) arrivalInput.value = rangeToApply[0];
          if (departInput) departInput.value = rangeToApply[1];
        }
      };
      bootCalendar();
    };

    const updateCapacityLimits = (config) => {
      if (!capacityWarning) return;
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
      if (!capacityWarning) return;
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
          if (childField) childField.value = String(newChildren);
        }
        totalGuests = adultesCount + enfantsCount;
        if (totalGuests > capValue && adultesCount > 0) {
          adultesCount = Math.max(0, capValue - enfantsCount);
          if (adultField) adultField.value = String(adultesCount);
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
          "Sélectionnez un logement pour afficher les disponibilités.",
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
            "Les périodes grisées sont indisponibles.",
          );
          initLogementCalendar(config, options.range || null);
          pendingLogementRange = null;
          updateCapacityLimits(config);
          updateQuote();
        })
        .catch((error) => {
          console.error("Logement config error", error);
          setLogementAvailabilityMessage(
            "Impossible de charger les disponibilités.",
          );
          updateCapacityLimits(null);
          updateQuote();
        });
    };

    const toggleAdjustmentButton = (input, btn) => {
      if (!btn || !input) return;
      const hasValue = input.value && parseFloat(input.value) > 0;
      btn.disabled = !hasValue;
    };
    const refreshRemiseButton = () =>
      toggleAdjustmentButton(remiseAmount, remiseClearBtn);
    const refreshPlusButton = () =>
      toggleAdjustmentButton(plusAmount, plusClearBtn);

    const normalizeLabelKey = (value) => {
      if (typeof value !== "string") value = value == null ? "" : String(value);
      return value.trim().toLowerCase().replace(/\s+/g, " ");
    };
    const parseQtyFromLabel = (label) => {
      if (!label) return 0;
      const m = label.match(/^(\d+)\s*[x×]?\s*(.+)$/i);
      return m && m[1] ? parseInt(m[1], 10) : 0;
    };
    const deriveQtyMapFromLines = (lines) => {
      const out = {};
      if (!Array.isArray(lines)) return out;
      lines.forEach((line) => {
        const rawLabel = window.PCR.Utils
          ? window.PCR.Utils.decodeText(line && line.label ? line.label : "")
          : line.label || "";
        const qty = parseQtyFromLabel(rawLabel);
        if (qty > 0) {
          const normalized = normalizeLabelKey(
            rawLabel.replace(/^(\d+)\s*[x×]?\s*/, ""),
          );
          if (normalized) out[normalized] = qty;
        }
      });
      return out;
    };
    const normalizePrefillQtyMap = (map) => {
      const out = {};
      if (!map || typeof map !== "object") return out;
      Object.keys(map).forEach((key) => {
        const qty = parseInt(map[key], 10);
        if (!Number.isNaN(qty) && qty > 0) out[normalizeLabelKey(key)] = qty;
      });
      return out;
    };

    let prefillQtyMap = normalizePrefillQtyMap(
      prefill && prefill.lines_qty_map,
    );

    const applyPrefillSelections = () => {
      if (!prefillQtyMap || Object.keys(prefillQtyMap).length === 0) return;
      if (customList) {
        customList
          .querySelectorAll("input[data-custom-line]")
          .forEach((input) => {
            const labelEl = input.closest(".pc-resa-field");
            const labelText = labelEl
              ? labelEl.querySelector(".pc-resa-field-label")?.textContent
              : "";
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
            } catch (e) {}
            const key = normalizeLabelKey(labelDecoded);
            const qty = prefillQtyMap[key];
            if (!qty || qty <= 0) return;
            checkbox.checked = true;
            const optId = checkbox.getAttribute("data-option-id");
            if (optId) {
              const qtyInput = optionsList.querySelector(
                `[data-option-qty-for="${optId}"]`,
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
      if (!customList) return map;
      customList
        .querySelectorAll("input[data-custom-line]")
        .forEach((input) => {
          const key = input.getAttribute("data-custom-line");
          if (!key) return;
          const value = parseInt(input.value, 10);
          if (!Number.isNaN(value)) map[key] = value;
        });
      return map;
    };

    const getSelectedOptions = () => {
      const selected = [];
      if (!optionsList) return selected;
      optionsList
        .querySelectorAll('input[type="checkbox"]')
        .forEach((checkbox) => {
          if (!checkbox.checked) return;
          const optionId = checkbox.getAttribute("data-option-id") || "";
          const encodedLabel = checkbox.getAttribute("data-option-label") || "";
          let label = encodedLabel;
          try {
            label = decodeURIComponent(encodedLabel);
          } catch (e) {}
          const price =
            parseFloat(checkbox.getAttribute("data-option-price") || "0") || 0;
          let qty = 1;
          if (checkbox.dataset.enableQty === "1") {
            const qtyInput = optionsList.querySelector(
              `[data-option-qty-for="${optionId}"]`,
            );
            qty = parseInt(qtyInput && qtyInput.value, 10) || 1;
          }
          selected.push({ id: optionId, label, price, qty });
        });
      return selected;
    };

    const toggleCountersVisibility = (config) => {
      if (!countersWrapper) return;
      const shouldDisplay = !!(config && config.has_counters);
      countersWrapper.style.display = shouldDisplay ? "" : "none";
    };

    const setParticipantsEnabled = (enabled) => {
      if (form)
        form.setAttribute("data-participants-enabled", enabled ? "1" : "0");
    };

    const normalizeCode = (value) => {
      if (typeof value !== "string") value = value == null ? "" : String(value);
      return value
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-");
    };

    const isCustomExperienceConfig = (config) => {
      if (!config) return false;
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
      if (!customList) return;
      if (!config || !Array.isArray(config.lines)) {
        if (customSection) customSection.style.display = "none";
        return;
      }
      const linesWithQty = config.lines.filter((line) => line.enable_qty);
      if (linesWithQty.length === 0) {
        if (customSection) customSection.style.display = "none";
        return;
      }
      let html = "";
      linesWithQty.forEach((line, index) => {
        const inputKey = line.uid || `line_${index}`;
        const defaultValue =
          line.default_qty && parseInt(line.default_qty, 10) > 0
            ? parseInt(line.default_qty, 10)
            : 0;
        const safeLabel = window.PCR.Utils
          ? window.PCR.Utils.escapeHtml(line.label || "Service")
          : line.label || "Service";
        html += `
              <label class="pc-resa-field pc-resa-field--inline">
                  <span class="pc-resa-field-label">${safeLabel}</span>
                  <input type="number" min="0" value="${defaultValue}" data-custom-line="${inputKey}">
              </label>
            `;
      });
      customList.innerHTML = html;
      if (customSection) customSection.style.display = "";
      customList
        .querySelectorAll("input[data-custom-line]")
        .forEach((input) => {
          input.addEventListener("input", updateQuote);
        });
    }

    function renderOptionsInputs(config) {
      if (!optionsList) return;
      optionsList.innerHTML = "";
      if (
        !config ||
        !Array.isArray(config.options) ||
        config.options.length === 0
      ) {
        if (optionsSection) optionsSection.style.display = "none";
        return;
      }
      let html = "";
      config.options.forEach((opt, index) => {
        const optionId = opt.uid || `option_${index}`;
        const safeLabel = window.PCR.Utils
          ? window.PCR.Utils.escapeHtml(opt.label || "Option")
          : opt.label || "Option";
        const encodedLabel = encodeURIComponent(opt.label || "");
        const amountDisplay = window.PCR.Utils
          ? window.PCR.Utils.formatPrice(opt.price || 0)
          : (opt.price || 0) + "€";
        html += `
              <label class="pc-resa-option-item">
                  <input type="checkbox"
                      data-option-id="${optionId}"
                      data-option-label="${encodedLabel}"
                      data-option-price="${parseFloat(opt.price) || 0}"
                      data-enable-qty="${opt.enable_qty ? "1" : "0"}">
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
      if (optionsSection) optionsSection.style.display = "";
      optionsList
        .querySelectorAll('input[type="checkbox"]')
        .forEach((checkbox) => {
          checkbox.addEventListener("change", function () {
            const optId = this.getAttribute("data-option-id");
            const qtyInput = optionsList.querySelector(
              `[data-option-qty-for="${optId}"]`,
            );
            if (qtyInput) qtyInput.disabled = !this.checked;
            updateQuote();
          });
        });
      optionsList.querySelectorAll("[data-option-qty-for]").forEach((input) => {
        input.addEventListener("input", updateQuote);
      });
    }

    const refreshDynamicSections = (config) => {
      toggleCountersVisibility(config);
      toggleParticipantsSection(config);
      renderCustomQtyInputs(config);
      renderOptionsInputs(config);
    };

    // --- LOGIQUE D'INITIALISATION FORMULAIRE ---
    let currentType = typeSelect
      ? typeSelect.value || "experience"
      : "experience";
    if (sourceSelect && prefill && prefill.source)
      sourceSelect.value = prefill.source;
    if (prefill && prefill.type) currentType = prefill.type;
    if (typeSelect) typeSelect.value = currentType;

    if (
      prefill &&
      prefill.type === "location" &&
      prefill.date_arrivee &&
      prefill.date_depart
    ) {
      pendingLogementRange = [prefill.date_arrivee, prefill.date_depart];
      if (arrivalInput) arrivalInput.value = prefill.date_arrivee;
      if (departInput) departInput.value = prefill.date_depart;
    }

    const initialItemId =
      prefill && prefill.item_id ? String(prefill.item_id) : "";
    lastExperienceId = currentType === "experience" ? initialItemId : "";
    lastLocationId = currentType === "location" ? initialItemId : "";
    populateItemOptions(currentType, initialItemId);
    setTypeLabel(currentType);
    toggleTypeHints(currentType);
    toggleTypeSections(currentType);

    let idInput = form.querySelector('input[name="id"]');
    if (!idInput) {
      idInput = document.createElement("input");
      idInput.type = "hidden";
      idInput.name = "id";
      form.appendChild(idInput);
    }
    idInput.value = prefill && prefill.id ? String(prefill.id) : "0";

    const actionInput = form.querySelector('input[name="action"]');
    const desiredAction = "pc_manual_reservation_create";
    if (actionInput) actionInput.value = desiredAction;
    else {
      const a = document.createElement("input");
      a.type = "hidden";
      a.name = "action";
      a.value = desiredAction;
      form.appendChild(a);
    }

    // PREFILL VALUES
    if (prefill) {
      if (dateExperienceInput && prefill.date_experience)
        dateExperienceInput.value = prefill.date_experience;
      if (adultField) adultField.value = prefill.adultes || "0";
      if (childField) childField.value = prefill.enfants || "0";
      if (babyField) babyField.value = prefill.bebes || "0";
      if (prenomInput) prenomInput.value = prefill.prenom || "";
      if (nomInput) nomInput.value = prefill.nom || "";
      if (emailInput) emailInput.value = prefill.email || "";
      if (telephoneInput) telephoneInput.value = prefill.telephone || "";
      if (commentaireField)
        commentaireField.value = prefill.commentaire_client || "";
      if (notesField) notesField.value = prefill.notes_internes || "";
      if (numeroDevisInput) numeroDevisInput.value = prefill.numero_devis || "";
      if (remiseLabel && prefill.remise_label)
        remiseLabel.value = prefill.remise_label;
      if (remiseAmount && prefill.remise_montant)
        remiseAmount.value = prefill.remise_montant;
      if (plusLabel && prefill.plus_label) plusLabel.value = prefill.plus_label;
      if (plusAmount && prefill.plus_montant)
        plusAmount.value = prefill.plus_montant;

      if (participantsAdultsField)
        participantsAdultsField.value =
          prefill.participants?.adultes ?? (prefill.adultes || 0);
      if (participantsEnfantsField)
        participantsEnfantsField.value =
          prefill.participants?.enfants ?? (prefill.enfants || 0);
      if (participantsBebesField)
        participantsBebesField.value =
          prefill.participants?.bebes ?? (prefill.bebes || 0);
    } else {
      if (participantsAdultsField && adultField)
        participantsAdultsField.value = adultField.value || "";
      if (participantsEnfantsField && childField)
        participantsEnfantsField.value = childField.value || "";
      if (participantsBebesField && babyField)
        participantsBebesField.value = babyField.value || "";
    }
    refreshRemiseButton();
    refreshPlusButton();

    const initialTarifKey = prefill ? prefill.experience_tarif_type : "";
    if (currentType === "experience") {
      populateTarifOptions(initialItemId, initialTarifKey);
      if (tarifSelect && initialTarifKey) tarifSelect.value = initialTarifKey;
      const initialConfig =
        initialItemId && initialTarifKey
          ? getTarifConfig(initialItemId, initialTarifKey)
          : null;
      refreshDynamicSections(initialConfig);
      applyPrefillSelections();
      updateCapacityLimits(null);
    } else {
      if (customSection) customSection.style.display = "none";
      if (optionsSection) optionsSection.style.display = "none";
      toggleParticipantsSection(null);
      prepareLogementConfig(initialItemId, { range: pendingLogementRange });
    }

    let storedLines = null;
    if (prefill && prefill.lines_json) {
      if (linesTextarea) linesTextarea.value = prefill.lines_json;
      const parsedLines = window.PCR.Utils
        ? window.PCR.Utils.parseJSONSafe(prefill.lines_json)
        : JSON.parse(prefill.lines_json);
      if (Array.isArray(parsedLines)) storedLines = parsedLines;
    }
    if (
      (!prefillQtyMap || Object.keys(prefillQtyMap).length === 0) &&
      storedLines
    ) {
      prefillQtyMap = deriveQtyMapFromLines(storedLines);
    }

    if (storedLines && summaryBody && summaryTotal) {
      if (window.PCR.Utils)
        window.PCR.Utils.renderStoredLinesSummary(
          storedLines,
          summaryBody,
          summaryTotal,
          prefill ? prefill.montant_total : 0,
        );
    }

    if (totalInput && prefill && typeof prefill.montant_total !== "undefined") {
      totalInput.value = parseFloat(prefill.montant_total || 0).toFixed(2);
    }

    // --- FONCTION DE MISE À JOUR DU PRIX ---
    function updateQuote() {
      if (!summaryBody || !summaryTotal) return;
      const typeValue = typeSelect ? typeSelect.value : "experience";
      const counts = {
        adultes: parseInt(adultField ? adultField.value : "0", 10) || 0,
        enfants: parseInt(childField ? childField.value : "0", 10) || 0,
        bebes: parseInt(babyField ? babyField.value : "0", 10) || 0,
      };

      if (typeValue !== "location" && capacityWarning)
        capacityWarning.style.display = "none";

      if (typeValue === "location") {
        enforceCapacity(counts);
        if (
          !logementQuote ||
          typeof logementQuote.calculateQuote !== "function"
        ) {
          summaryBody.innerHTML =
            '<p class="pc-resa-field-hint">Le moteur logement n’est pas chargé.</p>';
          summaryTotal.textContent = "—";
          return;
        }
        const logementId = itemSelect ? itemSelect.value : "";
        if (!logementId) {
          summaryBody.innerHTML =
            '<p class="pc-resa-field-hint">Sélectionnez un logement.</p>';
          summaryTotal.textContent = "—";
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
          return;
        }
        const arrivalValue = arrivalInput ? arrivalInput.value : "";
        const departValue = departInput ? departInput.value : "";
        if (!arrivalValue || !departValue) {
          summaryBody.innerHTML =
            '<p class="pc-resa-field-hint">Choisissez les dates du séjour.</p>';
          summaryTotal.textContent = "—";
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
          summaryBody.innerHTML = `<p class="pc-resa-field-hint">${window.PCR.Utils ? window.PCR.Utils.escapeHtml(result.message || "Sélection invalide.") : result.message || "Erreur"}</p>`;
          summaryTotal.textContent = "—";
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

      // CAS EXPÉRIENCE
      const expId = itemSelect ? itemSelect.value : "";
      const tarifKey = tarifSelect ? tarifSelect.value : "";

      if (!expId || !tarifKey) {
        summaryBody.innerHTML =
          '<p class="pc-resa-field-hint">Sélectionnez une expérience et un tarif.</p>';
        summaryTotal.textContent = "—";
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

    // Listeners
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
          if (customSection) customSection.style.display = "none";
          if (optionsSection) optionsSection.style.display = "none";
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
        if (current === "experience") lastExperienceId = this.value || "";
        else lastLocationId = this.value || "";
        updateQuote();
      });
    }

    if (tarifSelect) {
      tarifSelect.addEventListener("change", function () {
        if (typeSelect && typeSelect.value !== "experience") return;
        const expId = itemSelect ? itemSelect.value : "";
        const cfg = expId ? getTarifConfig(expId, this.value) : null;
        refreshDynamicSections(cfg);
        updateQuote();
      });
    }

    counters.forEach((input) => input.addEventListener("input", updateQuote));

    if (remiseLabel) remiseLabel.addEventListener("input", updateQuote);
    if (remiseAmount) {
      remiseAmount.addEventListener("input", () => {
        refreshRemiseButton();
        updateQuote();
      });
      refreshRemiseButton();
    }
    if (remiseClearBtn) {
      remiseClearBtn.addEventListener("click", () => {
        if (remiseLabel) remiseLabel.value = "Remise exceptionnelle";
        if (remiseAmount) remiseAmount.value = "";
        refreshRemiseButton();
        updateQuote();
      });
    }

    if (plusLabel) plusLabel.addEventListener("input", updateQuote);
    if (plusAmount) {
      plusAmount.addEventListener("input", () => {
        refreshPlusButton();
        updateQuote();
      });
      refreshPlusButton();
    }
    if (plusClearBtn) {
      plusClearBtn.addEventListener("click", () => {
        if (plusLabel) plusLabel.value = "Plus-value";
        if (plusAmount) plusAmount.value = "";
        refreshPlusButton();
        updateQuote();
      });
    }

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      if (!submitBtn) return;
      handleManualCreateSubmit(form, submitBtn);
    });

    if (!storedLines) updateQuote();

    if (sendBtn) {
      sendBtn.addEventListener("click", function () {
        if (typeFluxSelect) typeFluxSelect.value = "devis";
        handleManualCreateSubmit(form, sendBtn);
      });
    }

    return { form, submitBtn, sendBtn, typeFluxSelect };
  }

  // ============================================================
  // 5. EXPOSITION DU MODULE
  // ============================================================
  window.PCR.BookingForm = {
    init: function (container, prefillData, options) {
      return initManualCreateForm(container, prefillData, options);
    },
  };
})();
