(function () {
  const params = window.pcResaParams || {};
  const pcResaAjaxUrl = params.ajaxUrl || "";
  const pcResaManualNonce = params.manualNonce || "";
  const experiencePricingData = params.experienceTarifs || {};
  const logementQuote = params.logementQuote || window.PCLogementDevis || null;
  const translations = params.translations || {};
  const translate = (key, fallback) =>
    translations && translations[key] ? translations[key] : fallback;

  document.addEventListener("DOMContentLoaded", function () {
    // Garde-fou : si le script est chargé plusieurs fois
    if (window.pcDashboardCoreInitialized) {
      return;
    }
    window.pcDashboardCoreInitialized = true;

    // Initialisation des modules PCR (si présents)
    if (window.PCR) {
      if (window.PCR.Pricing) {
        window.PCR.Pricing.init({
          experienceTarifs: experiencePricingData,
          logementQuote: logementQuote,
        });
      }
      if (window.PCR.Documents) {
        window.PCR.Documents.init({
          ajaxUrl: pcResaAjaxUrl,
          nonce: pcResaManualNonce,
        });
      }
      if (window.PCR.Payments) {
        window.PCR.Payments.init({
          ajaxUrl: pcResaAjaxUrl,
          nonce: pcResaManualNonce,
        });
      }
      if (window.PCR.Messaging) {
        window.PCR.Messaging.init({
          ajaxUrl: pcResaAjaxUrl,
          nonce: pcResaManualNonce,
        });
      }
    }

    const createTemplate = document.getElementById("pc-resa-create-template");

    // Bouton "Créer une réservation"
    const createBtn = document.querySelector(".pc-resa-create-btn");

    // On vérifie que le bouton existe bien
    if (createBtn) {
      // 1. Comportement normal au clic (très important de le garder !)
      createBtn.addEventListener("click", function () {
        openManualCreateModal();
      });

      // 2. Auto-ouverture ET auto-remplissage avec Radar
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.get("auto_create") === "1") {
        // 🚀 Le fameux délai vital pour que ton Shell respire avant d'ouvrir la modale
        setTimeout(() => {
          openManualCreateModal(); // Ouverture !

          // Maintenant que l'ordre d'ouverture est donné, on lance le Radar
          let tentatives = 0;
          const radar = setInterval(() => {
            tentatives++;

            // On cherche les champs
            const typeSelect = document.querySelector('select[name="type"]');
            const logementSelect = document.querySelector(
              'select[name="item_id"]',
            );
            const datesInput = document.querySelector(
              'input[name="logement_dates"]',
            );

            // Si le champ Type est enfin dans le HTML, c'est gagné
            if (typeSelect && logementSelect) {
              clearInterval(radar); // On coupe le radar

              const start = urlParams.get("start");
              const end = urlParams.get("end");
              const logementId = urlParams.get("logement_id");

              // --- A. TYPE ---
              typeSelect.value = "location";
              typeSelect.dispatchEvent(new Event("change", { bubbles: true }));

              // --- B. LOGEMENT ---
              logementSelect.value = logementId;
              logementSelect.dispatchEvent(
                new Event("change", { bubbles: true }),
              );

              // Coup de pouce pour Select2/jQuery
              if (typeof jQuery !== "undefined") {
                jQuery('select[name="type"]').trigger("change");
                jQuery('select[name="item_id"]').trigger("change");
              }

              // --- C. DATES ---
              if (datesInput && start && end) {
                datesInput.removeAttribute("disabled");
                datesInput.removeAttribute("readonly");

                const formatFr = (ds) => {
                  const [y, m, d] = ds.split("-");
                  return `${d}/${m}/${y}`;
                };

                datesInput.value = `${formatFr(start)} - ${formatFr(end)}`;
                datesInput.dispatchEvent(new Event("input", { bubbles: true }));
                datesInput.dispatchEvent(
                  new Event("change", { bubbles: true }),
                );

                if (typeof jQuery !== "undefined") {
                  jQuery('input[name="logement_dates"]').trigger("change");
                }
              }

              // 🧹 Nettoyage de l'URL pour ne pas ré-ouvrir si on fait F5
              const cleanUrl =
                window.location.protocol +
                "//" +
                window.location.host +
                window.location.pathname +
                window.location.hash;
              window.history.replaceState({ path: cleanUrl }, "", cleanUrl);
            }

            // Sécurité : au bout de 5 secondes (25 essais), on abandonne
            if (tentatives > 25) {
              clearInterval(radar);
              console.warn("Radar annulé : champs introuvables.");
            }
          }, 200); // Scanne toutes les 200ms
        }, 500); // <-- LE DELAI SAUVEUR EST LÀ
      }
    }

    // Gestion des menus d'actions (3 petits points)
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
          if (!container) return;

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
      { capture: false },
    );

    // ============================================================
    // FONCTIONS DÉLÉGUÉES (Wrappers de compatibilité)
    // ============================================================
    // Gardées pour éviter les erreurs si d'autres scripts appellent ces noms
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
    // MODALES & ACTIONS
    // ============================================================

    const openManualCreateModal = (prefillData = null, options = {}) => {
      if (!createTemplate) return null;

      openResaModal(createTemplate.innerHTML);
      const modalContent = document.getElementById("pc-resa-modal-content");
      if (!modalContent) return null;

      let refs = null;

      // 🔥 UTILISATION DU NOUVEAU MODULE BOOKING FORM
      if (window.PCR && window.PCR.BookingForm) {
        refs = window.PCR.BookingForm.init(modalContent, prefillData, options);
      } else {
        console.error(
          "❌ Erreur critique : Le module PCR.BookingForm n'est pas chargé.",
        );
        modalContent.innerHTML = `<div style="padding:20px; color:red;">Erreur: Module de réservation manquant. Vérifiez la console.</div>`;
      }

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
      if (modalContent) modalContent.innerHTML = html;
      if (modal) modal.style.display = "block";
    }

    function closeResaModal() {
      const modal = document.getElementById("pc-resa-modal");
      const modalContent = document.getElementById("pc-resa-modal-content");
      if (modalContent) modalContent.innerHTML = "";
      if (modal) modal.style.display = "none";
    }

    const modalCloseBtn = document.getElementById("pc-resa-modal-close-btn");
    const modalCloseBackdrop = document.getElementById("pc-resa-modal-close");
    if (modalCloseBtn) modalCloseBtn.addEventListener("click", closeResaModal);
    if (modalCloseBackdrop)
      modalCloseBackdrop.addEventListener("click", closeResaModal);

    // ============================================================
    // ÉVÉNEMENTS GLOBAUX DASHBOARD
    // ============================================================

    // Clic sur "Fiche" dans le tableau
    document.addEventListener("click", function (e) {
      const btn = e.target.closest(".pc-resa-view-link");
      if (!btn) return;
      e.preventDefault();

      const id = btn.getAttribute("data-resa-id");
      const detailRow = document.querySelector(
        '.pc-resa-dashboard-row-detail[data-resa-id="' + id + '"]',
      );

      if (!detailRow) return;
      const card = detailRow.querySelector(".pc-resa-card");
      if (!card) return;

      openResaModal(card.innerHTML);

      // Re-attacher les événements spécifiques
      attachQuoteButtons();

      // Charger les documents via le module (si présent) ou fallback global
      if (
        window.PCR &&
        window.PCR.Documents &&
        typeof window.PCR.Documents.reloadList === "function"
      ) {
        window.PCR.Documents.reloadList(id);
        window.PCR.Documents.loadTemplates(id);
      } else {
        // Fallback legacy si le module Documents n'est pas encore migré
        if (typeof window.pc_reload_documents === "function")
          window.pc_reload_documents(id);
        if (typeof window.pc_load_templates === "function")
          window.pc_load_templates(id);
      }
    });

    // Ouverture automatique depuis le calendrier (via SessionStorage)
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
            openManualCreateModal(prefill, { context: "from_calendar" });
          }
        } catch (error) {
          window.sessionStorage.removeItem(key);
        }
      }
    }

    const attachQuoteButtons = () => {
      document.querySelectorAll(".pc-resa-edit-quote").forEach((btn) => {
        btn.addEventListener("click", function (e) {
          e.preventDefault();
          const rawData = this.getAttribute("data-prefill");
          // Utilisation safe via PCR.Utils si dispo
          const payload =
            window.PCR?.Utils?.parseJSONSafe(rawData) || JSON.parse(rawData);
          if (!payload) {
            alert("Impossible de charger les données du devis.");
            return;
          }
          openManualCreateModal(payload, { context: "edit" });
        });
      });
    };

    attachQuoteButtons();
  }); // Fin DOMContentLoaded

  // ============================================================
  // GESTION DES POPUPS GLOBAUX (Annulation / Confirmation)
  // ============================================================
  document.addEventListener("click", function (event) {
    // Annulation
    const cancelBtn = event.target.closest(".pc-resa-action-cancel-booking");
    if (cancelBtn) {
      event.preventDefault();
      const id = cancelBtn.dataset.reservationId;
      const popup = document.getElementById("pc-cancel-reservation-popup");
      if (popup) {
        popup.dataset.resaId = id;
        popup.hidden = false;
      }
      return;
    }

    // Fermeture Popups
    if (event.target.matches("[data-pc-popup-close]")) {
      const popup =
        event.target.closest(".pc-admin-popup") ||
        document.getElementById("pc-cancel-reservation-popup");
      if (popup) popup.hidden = true;
      return;
    }

    // Confirmation Annulation AJAX
    if (event.target.matches("[data-pc-popup-confirm]")) {
      const popup = document.getElementById("pc-cancel-reservation-popup");
      const id = popup.dataset.resaId;

      // Ici on pourrait déléguer à PCR.Utils.fetch ou garder le fetch natif
      const body = new URLSearchParams();
      body.append("action", "pc_cancel_reservation");
      body.append("nonce", pcResaManualNonce);
      body.append("reservation_id", id);

      fetch(pcResaAjaxUrl, {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: body.toString(),
      })
        .then((r) => r.json())
        .then((json) => {
          if (!json || !json.success) {
            alert(json?.data?.message || "Erreur.");
            return;
          }
          popup.hidden = true;
          window.location.reload();
        })
        .catch(() => alert("Erreur réseau."));
    }

    // Confirmation Réservation (Devis -> Confirmé)
    const confirmBtn = event.target.closest(".pc-resa-action-confirm-booking");
    if (confirmBtn) {
      event.preventDefault();
      const id = confirmBtn.dataset.reservationId;
      if (
        !confirm(
          "Confirmer cette réservation ? Elle apparaîtra dans le calendrier.",
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
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: body.toString(),
      })
        .then((r) => r.json())
        .then((json) => {
          if (json && json.success) window.location.reload();
          else {
            alert(
              json.data && json.data.message ? json.data.message : "Erreur.",
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
  });
})();
