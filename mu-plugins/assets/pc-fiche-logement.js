// /assets/pc-fiche-logement.js (Version 4.2 - Orchestré)

(function () {
  function initLogementBooking() {
    // --- PARTIE 1 : "LE DÉMÉNAGEMENT" ---
    const devisSource = document.querySelector(".pc-devis-section");
    const isManualQuote =
      devisSource && devisSource.dataset.manualQuote === "1";
    const devisTarget = document.getElementById("logement-devis-sheet-body");
    let devisMoved = false;

    if (devisSource && devisTarget) {
      devisTarget.appendChild(devisSource);
      devisMoved = true;
    }

    function initializeBookingLogic() {
      const fab = document.getElementById("logement-open-devis-sheet-btn");
      const fabPriceDisplay = document.getElementById(
        "fab-logement-price-display"
      );
      const devisSheet = document.getElementById("logement-devis-sheet");
      const closeSheetTriggers = document.querySelectorAll(
        "[data-close-devis-sheet]"
      );
      const openContactModalBtn = document.getElementById(
        "logement-open-modal-btn-local"
      );
      const openLodgifyBtn = document.getElementById(
        "logement-lodgify-reserve-btn"
      );
      const contactModal = document.getElementById("logement-booking-modal");
      const closeContactModalTriggers =
        document.querySelectorAll("[data-close-modal]");
      const form = document.getElementById("logement-booking-form");
      const devisErrorMsg = devisSource
        ? devisSource.querySelector("#logement-devis-error-msg")
        : null;

      const cfg = devisSource
        ? JSON.parse(devisSource.dataset.pcDevis || "{}")
        : {};
      const isManualQuote =
        devisSource && devisSource.dataset.manualQuote === "1";

      if (isManualQuote && openContactModalBtn) {
        openContactModalBtn.textContent = "Demander votre devis";
      }
      if (isManualQuote && openLodgifyBtn) {
        openLodgifyBtn.textContent = "Demander votre devis";
      }

      if (!fab || !devisSheet) return;

      const eur = (n) => {
        const t = new Intl.NumberFormat("fr-FR", {
          style: "currency",
          currency: "EUR",
        }).format(Number(n) || 0);
        return t.endsWith(",00") ? t.slice(0, -3) + " €" : t;
      };
      const base = Number((cfg && cfg.basePrice) || 0);
      let quoteSubmitted = false;

      const baseDesk =
        base > 0 ? `À partir de ${eur(base)} sur devis` : "Sur devis";
      const baseMob = base > 0 ? `Dès ${eur(base)} — sur devis` : "Sur devis";

      function setFabBaseLabel() {
        if (!fabPriceDisplay) return;
        fabPriceDisplay.textContent =
          window.innerWidth <= 480 ? baseMob : baseDesk;
      }
      function setFabConfirmLabel() {
        if (!fabPriceDisplay) return;
        fabPriceDisplay.textContent = "Confirmez votre demande";
      }
      const initialFabHTML = (function () {
        if (!fabPriceDisplay) return "Estimer le séjour";
        if (isManualQuote) {
          const desk =
            base > 0 ? `À partir de ${eur(base)} sur devis` : "Sur devis";
          const mob = base > 0 ? `Dès ${eur(base)} — sur devis` : "Sur devis";
          return window.innerWidth <= 480 ? mob : desk;
        }
        return fabPriceDisplay.innerHTML;
      })();
      if (isManualQuote) setFabBaseLabel();

      function showFab() {
        if (fab) fab.classList.add("is-visible");
      }
      if (sessionStorage.getItem("logementSheetOpened")) {
        showFab();
      } else {
        const timer = setTimeout(showFab, 2000);
        const scrollThreshold = window.innerHeight * 0.3;
        function checkScroll() {
          if (window.scrollY > scrollThreshold) {
            showFab();
            clearTimeout(timer);
            window.removeEventListener("scroll", checkScroll);
          }
        }
        window.addEventListener("scroll", checkScroll, { passive: true });
      }

      function openDevisSheet() {
        if (!devisSheet) return;
        devisSheet.setAttribute("aria-hidden", "false");
        devisSheet.classList.add("is-open");
        document.body.style.overflow = "hidden";
        sessionStorage.setItem("logementSheetOpened", "true");
        if (
          fabPriceDisplay &&
          (fabPriceDisplay.textContent === "Merci ! 🌴" ||
            fabPriceDisplay.textContent === "Redirection...")
        ) {
          fabPriceDisplay.innerHTML = initialFabHTML;
        }

        // Ancien focus auto sur le champ de dates (ouvrait le calendrier de façon intempestive).
        // On le supprime pour laisser l'utilisateur ouvrir le calendrier manuellement.
        // if (devisSource) {
        //   const dateInput = devisSource.querySelector('input[name="dates"]');
        //   if (dateInput) setTimeout(() => dateInput.focus(), 50);
        // }
      }
      function closeDevisSheet() {
        if (!devisSheet) return;
        devisSheet.classList.remove("is-open");
        if (isManualQuote && !quoteSubmitted) {
          const sel = window.currentLogementSelection;
          if (sel && sel.arrival && sel.departure) setFabConfirmLabel();
          else setFabBaseLabel();
        }
        document.body.style.overflow = "";
      }
      function openContactModal() {
        if (!contactModal) return;
        contactModal.setAttribute("aria-hidden", "false");
        contactModal.classList.remove("is-hidden");
        document.body.style.overflow = "hidden";
        const prenomInput = contactModal.querySelector('input[name="prenom"]');
        if (prenomInput) setTimeout(() => prenomInput.focus(), 50);
      }
      function closeContactModal() {
        if (!contactModal || !form) return;
        contactModal.setAttribute("aria-hidden", "true");
        contactModal.classList.add("is-hidden");
        document.body.style.overflow = "";
        const successMessage = form.parentNode.querySelector(
          ".form-success-message"
        );
        if (successMessage) {
          successMessage.remove();
          form.style.display = "block";
        }
      }

      function formatCurrency(num) {
        num = Number(num) || 0;
        const formatted = new Intl.NumberFormat("fr-FR", {
          style: "currency",
          currency: "EUR",
        }).format(num);
        return formatted.endsWith(",00")
          ? formatted.slice(0, -3) + " €"
          : formatted;
      }
      function updateFabText() {
        if (isManualQuote) return;
        if (!fabPriceDisplay) return;
        if (
          fabPriceDisplay.textContent === "Merci ! 🌴" ||
          fabPriceDisplay.textContent === "Redirection..."
        )
          return;
        const total = window.currentLogementTotal;
        if (typeof total !== "undefined" && total > 0) {
          fabPriceDisplay.textContent =
            "Réserver pour : " + formatCurrency(total);
        } else {
          fabPriceDisplay.innerHTML = initialFabHTML;
        }
      }

      // --------- CORRIGÉ : remplit toujours les inputs cachés + texte récap ----------
      function updateModalInfo() {
        if (!contactModal) return;
        const modalSummary = document.getElementById(
          "modal-quote-summary-logement"
        );
        const modalHiddenDetails = document.getElementById(
          "modal-quote-details-hidden-logement"
        );
        if (!modalSummary || !modalHiddenDetails) return;

        const sel = window.currentLogementSelection || null;
        const total = window.currentLogementTotal;
        const lines = window.currentLogementLines;

        const ensureHidden = (name) => {
          let input = form ? form.querySelector(`input[name="${name}"]`) : null;
          if (!input && form) {
            input = document.createElement("input");
            input.type = "hidden";
            input.name = name;
            form.appendChild(input);
          }
          return input;
        };
        const hArrival = ensureHidden("arrival");
        const hDeparture = ensureHidden("departure");
        const hNights = ensureHidden("nights");
        const hAdults = ensureHidden("adults");
        const hChildren = ensureHidden("children");
        const hInfants = ensureHidden("infants");
        const hManual = ensureHidden("manual_quote");

        if (sel) {
          const arrival = sel.arrival || "";
          const departure = sel.departure || "";
          const nights =
            arrival && departure
              ? Math.max(
                  0,
                  Math.ceil(
                    (new Date(departure) - new Date(arrival)) / 86400000
                  )
                )
              : 0;
          const adults = parseInt(sel.adults || 0, 10);
          const children = parseInt(sel.children || 0, 10);
          const infants = parseInt(sel.infants || 0, 10);

          if (hArrival) hArrival.value = arrival;
          if (hDeparture) hDeparture.value = departure;
          if (hNights) hNights.value = String(nights);
          if (hAdults) hAdults.value = String(adults);
          if (hChildren) hChildren.value = String(children);
          if (hInfants) hInfants.value = String(infants);
          if (hManual) hManual.value = isManualQuote ? "1" : "0";
        }

        if (
          !isManualQuote &&
          typeof total !== "undefined" &&
          total > 0 &&
          lines &&
          lines.length > 0
        ) {
          // --- DETECTION DU MODE ---
          const isDirectBooking = cfg.bookingMode === "directe";

          // SI C'EST UNE "DEMANDE" CLASSIQUE : On garde l'affichage simple
          if (!isDirectBooking) {
            let summaryHTML = "<ul>";
            let detailsText = "";
            lines.forEach((line) => {
              summaryHTML += `<li><span>${line.label}</span><span>${line.price}</span></li>`;
              detailsText += `${line.label}: ${line.price}\n`;
            });
            summaryHTML += "</ul>";
            summaryHTML += `<div style="text-align:right; font-weight:bold; margin-top:10px;">Total estimé : ${formatCurrency(
              total
            )}</div>`;

            detailsText += `\nTotal: ${formatCurrency(total)}`;
            modalSummary.innerHTML = summaryHTML;
            modalHiddenDetails.value = detailsText;

            // On s'assure que le bouton reste "Envoyer la demande" (on reset le conteneur d'actions au cas où)
            const actionsContainer = document.querySelector(
              ".exp-booking-modal__actions"
            );
            if (actionsContainer) {
              // On remet le HTML par défaut (Bouton classique)
              actionsContainer.innerHTML = `
                    <p class="exp-booking-disclaimer">Cette demande est sans engagement.</p>
                    <button type="submit" class="pc-btn pc-btn--primary">Envoyer la demande</button>
                  `;
            }
            return; // ON S'ARRÊTE LÀ POUR LE MODE DEMANDE
          }

          // ============================================================
          // SI C'EST UNE "RÉSERVATION DIRECTE" : On affiche l'échéancier + Stripe
          // ============================================================

          const payRules = cfg.payment || {
            mode: "acompte_plus_solde",
            deposit_val: 30,
            delay_days: 30,
          };
          let dueNow = total;
          let dueLater = 0;
          let isDeposit = false;

          // Calcul des 30 jours
          const today = new Date();
          const arrivalDate = new Date(sel.arrival);
          const diffTime = arrivalDate - today;
          const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
          const forceTotal = diffDays <= (payRules.delay_days || 30);

          if (payRules.mode === "acompte_plus_solde" && !forceTotal) {
            isDeposit = true;
            if (payRules.deposit_type === "montant_fixe") {
              dueNow = payRules.deposit_val;
            } else {
              dueNow = total * (payRules.deposit_val / 100);
            }
            dueLater = total - dueNow;
          }

          // 1. Facture Détaillée
          let summaryHTML = '<div class="pc-invoice-lines">';
          let detailsText = "";
          lines.forEach((line) => {
            summaryHTML += `<div class="pc-invoice-row"><span class="pc-lbl">${line.label}</span><span class="pc-val">${line.price}</span></div>`;
            detailsText += `${line.label}: ${line.price}\n`;
          });
          summaryHTML += `<div class="pc-invoice-total"><span class="pc-lbl">Total</span><span class="pc-val">${formatCurrency(
            total
          )}</span></div>`;
          summaryHTML += "</div>";
          detailsText += `\nTotal: ${formatCurrency(total)}`;

          // 2. Échéancier
          if (isDeposit && dueLater > 0) {
            const balanceDate = new Date(arrivalDate);
            balanceDate.setDate(
              arrivalDate.getDate() - (payRules.delay_days || 30)
            );
            const dateStr = balanceDate.toLocaleDateString("fr-FR", {
              day: "numeric",
              month: "long",
              year: "numeric",
            });

            summaryHTML += `
              <div class="pc-payment-schedule">
                  <div class="pc-schedule-header">📅 Calendrier de paiement</div>
                  <div class="pc-schedule-row is-now">
                      <div class="pc-col-date"><strong>Acompte à régler</strong><br><span class="pc-tag-now">Immédiat</span></div>
                      <div class="pc-col-amount">${formatCurrency(dueNow)}</div>
                  </div>
                  <div class="pc-schedule-row">
                      <div class="pc-col-date"><strong>Solde à venir</strong><br><span class="pc-text-muted">Le ${dateStr}</span></div>
                      <div class="pc-col-amount">${formatCurrency(
                        dueLater
                      )}</div>
                  </div>
              </div>`;
          } else {
            summaryHTML += `
              <div class="pc-payment-schedule">
                  <div class="pc-schedule-row is-now">
                      <div class="pc-col-date"><strong>Total à régler</strong><br><span class="pc-tag-now">Immédiat</span></div>
                      <div class="pc-col-amount">${formatCurrency(total)}</div>
                  </div>
              </div>`;
          }

          modalSummary.innerHTML = summaryHTML;
          modalHiddenDetails.value = detailsText;

          // 3. Bouton STRIPE UNIQUEMENT
          const actionsContainer = document.querySelector(
            ".exp-booking-modal__actions"
          );
          if (actionsContainer) {
            actionsContainer.innerHTML = ""; // On vide tout

            const btnStripe = document.createElement("button");
            btnStripe.type = "submit";
            btnStripe.className = "pc-btn pc-btn--primary pc-btn-full";
            // Texte explicite : "Payer 900€" ou "Payer la totalité"
            btnStripe.innerHTML = `💳 Payer ${formatCurrency(
              dueNow
            )} (Carte Bancaire)`;

            const disclaimer = document.createElement("p");
            disclaimer.className = "exp-booking-disclaimer";
            disclaimer.textContent =
              "Paiement sécurisé. Réservation confirmée immédiatement.";

            actionsContainer.appendChild(disclaimer);
            actionsContainer.appendChild(btnStripe);
          }
        } else {
          if (sel && sel.arrival && sel.departure) {
            const nights = hNights ? hNights.value : "";
            const recap = [
              "EN ATTENTE DE DEVIS PERSONNALISÉ",
              `Période : ${sel.arrival
                .split("-")
                .reverse()
                .join("/")} → ${sel.departure.split("-").reverse().join("/")}`,
              `Voyageurs : ${sel.adults || 0} adultes, ${
                sel.children || 0
              } enfants, ${sel.infants || 0} bébés`,
              nights ? `Nuits : ${nights}` : "",
            ]
              .filter(Boolean)
              .join("\n");
            modalSummary.innerHTML = `<pre class="pcq-manual-recap">${recap.replace(
              /\n/g,
              "<br>"
            )}</pre>`;
            modalHiddenDetails.value = recap;
          } else {
            modalSummary.innerHTML = "<p>Choisissez vos dates</p>";
            modalHiddenDetails.value =
              "Aucune simulation de devis n'a été effectuée.";
          }
        }
      }
      // ------------------------------------------------------------------------------

      function handleLodgifyRedirect() {
        if (isManualQuote) {
          closeDevisSheet();
          openContactModal();
          return;
        }
        const selection = window.currentLogementSelection;
        const cfg = devisSource
          ? JSON.parse(devisSource.dataset.pcDevis || "{}")
          : {};
        let errorMessage = null;

        if (!selection)
          errorMessage =
            "Veuillez d'abord sélectionner vos dates et le nombre d'invités.";
        else if (!selection.arrival || !selection.departure)
          errorMessage =
            "Veuillez sélectionner vos dates d'arrivée et de départ.";
        else if (!selection.adults || selection.adults < 1)
          errorMessage = "Veuillez indiquer au moins 1 adulte.";
        else if (!cfg.lodgifyId || !cfg.lodgifyAccount)
          errorMessage =
            "Erreur de configuration, impossible de générer le lien de réservation.";

        if (errorMessage) {
          if (devisErrorMsg) {
            devisErrorMsg.textContent = errorMessage;
            devisErrorMsg.classList.add("is-visible");
            if (devisSource) {
              devisSource.style.transition = "transform 0.1s ease-in-out";
              devisSource.style.transform = "translateX(-10px)";
              setTimeout(() => {
                devisSource.style.transform = "translateX(10px)";
                setTimeout(() => {
                  devisSource.style.transform = "translateX(0px)";
                }, 100);
              }, 100);
            }
          } else {
            alert(errorMessage);
          }
          return;
        }
        if (devisErrorMsg) devisErrorMsg.classList.remove("is-visible");

        try {
          const baseUrl = "https://checkout.lodgify.com/fr/";
          const adults = parseInt(selection.adults, 10) || 0;
          const children = parseInt(selection.children, 10) || 0;
          const infants =
            selection.infants != null
              ? parseInt(selection.infants, 10) || 0
              : 0;

          const url = `${baseUrl}${cfg.lodgifyAccount}/${cfg.lodgifyId}/contact?currency=EUR&arrival=${selection.arrival}&departure=${selection.departure}&adults=${adults}&children=${children}&infants=${infants}`;

          const newWindow = window.open(url, "_blank");
          // N'affiche l'alerte qu'en cas d'échec manifeste (null)
          if (!newWindow) {
            alert(
              "Votre navigateur a peut-être bloqué l'ouverture de la page de réservation. Veuillez autoriser les popups pour ce site."
            );
          }
          // Ferme la sheet quelle que soit l'issue
          closeDevisSheet();

          if (fabPriceDisplay) {
            fabPriceDisplay.textContent = "Redirection...";
            setTimeout(() => {
              if (
                fabPriceDisplay &&
                fabPriceDisplay.textContent === "Redirection..."
              ) {
                fabPriceDisplay.innerHTML = initialFabHTML;
              }
            }, 4000);
          }
        } catch (e) {
          console.error("[Logement JS] Erreur URL Lodgify:", e);
          alert(
            "Une erreur est survenue lors de la tentative de redirection vers la réservation."
          );
        }
      }

      function handleBookingRequest(ev) {
        if (ev) ev.preventDefault();

        const btn = ev && ev.currentTarget ? ev.currentTarget : null;
        const section = btn
          ? btn.closest(".pc-booking-sheet")?.querySelector(".pc-devis-section")
          : document.querySelector(".pc-devis-section");
        if (!section) {
          openContactModal();
          return;
        }

        const cfg = JSON.parse(section.getAttribute("data-pc-devis") || "{}");
        const isManual = !!cfg.manualQuote;

        const sel = window.currentLogementSelection;

        const id = section.id || "pc-devis";
        const msgBox = document.getElementById(id + "-msg");

        const hasRange = !!(sel && sel.arrival && sel.departure);
        if (!hasRange) {
          if (msgBox) {
            msgBox.textContent = "Choisissez vos dates";
            msgBox.classList.add("is-visible");
          } else alert("Choisissez vos dates");
          return;
        }

        if (isManual) {
          if (msgBox) msgBox.classList.remove("is-visible");
          updateModalInfo();
          closeDevisSheet();
          openContactModal();
          return;
        }

        const hasValidSimulation =
          typeof window.currentLogementTotal !== "undefined" &&
          window.currentLogementTotal > 0;
        if (hasValidSimulation) {
          if (msgBox) msgBox.classList.remove("is-visible");
          updateModalInfo();
          closeDevisSheet();
          openContactModal();
        } else {
          const errorMessage =
            "Veuillez remplir le simulateur ci-dessus avant de faire une demande.";
          if (msgBox) {
            msgBox.textContent = errorMessage;
            msgBox.classList.add("is-visible");
          } else alert(errorMessage);
        }
      }

      if (form && contactModal) {
        form.addEventListener("submit", function (event) {
          event.preventDefault();
          const nonceValue =
            typeof pcLogementData !== "undefined" && pcLogementData.nonce
              ? pcLogementData.nonce
              : null;
          const nonceInput = form.querySelector('input[name="nonce"]');
          if (nonceInput && nonceValue) nonceInput.value = nonceValue;

          const submitBtn = form.querySelector('button[type="submit"]');
          const originalBtnText = submitBtn.textContent;
          submitBtn.textContent = "Envoi en cours...";
          submitBtn.disabled = true;
          const formData = new FormData(form);

          if (devisSource) {
            const adultsInput = devisSource.querySelector(
              'input[name="devis_adults"]'
            );
            const childrenInput = devisSource.querySelector(
              'input[name="devis_children"]'
            );
            const infantsInput = devisSource.querySelector(
              'input[name="devis_infants"]'
            );
            formData.append("adultes", adultsInput ? adultsInput.value : "0");
            formData.append(
              "enfants",
              childrenInput ? childrenInput.value : "0"
            );
            formData.append("bebes", infantsInput ? infantsInput.value : "0");

            // Ajoute toujours les métadonnées de sélection pour le mail
            const ss = window.currentLogementSelection || {};
            if (ss.arrival) formData.append("arrival", ss.arrival);
            if (ss.departure) formData.append("departure", ss.departure);
            const nn =
              ss.arrival && ss.departure
                ? Math.max(
                    0,
                    Math.ceil(
                      (new Date(ss.departure) - new Date(ss.arrival)) / 86400000
                    )
                  )
                : 0;
            formData.append("nights", String(nn));
            formData.append("adults", String(parseInt(ss.adults || 0, 10)));
            formData.append("children", String(parseInt(ss.children || 0, 10)));
            formData.append("infants", String(parseInt(ss.infants || 0, 10)));
            formData.append("manual_quote", isManualQuote ? "1" : "0");

            // NOUVEAU : n'envoyer les infos de total que si le noyau est actif
            if (window.pcResaCoreActive) {
              formData.append(
                "total",
                String(window.currentLogementTotal || 0)
              );
              formData.append(
                "lines_json",
                JSON.stringify(window.currentLogementLines || [])
              );
            }
          }

          fetch(form.getAttribute("action"), { method: "POST", body: formData })
            .then((response) => response.json())
            .then((data) => {
              if (data.success) {
                // --- DÉBUT MODIF : REDIRECTION STRIPE ---
                if (data.data && data.data.payment_url) {
                  if (fabPriceDisplay)
                    fabPriceDisplay.textContent = "Redirection Paiement...";
                  // Redirection immédiate vers Stripe
                  window.location.href = data.data.payment_url;
                  return; // On arrête tout ici, la page va changer
                }
                // --- FIN MODIF ---

                quoteSubmitted = true;
                form.style.display = "none";
                if (fabPriceDisplay) fabPriceDisplay.textContent = "Merci ! 🌴";
                const successMessage = document.createElement("div");
                successMessage.className = "form-success-message";
                const prenom = formData.get("prenom");
                const prenomSanitized = prenom
                  ? prenom.replace(/</g, "&lt;").replace(/>/g, "&gt;")
                  : "";
                successMessage.innerHTML = `<h4>Merci ${prenomSanitized} !</h4><p>${data.data.message}</p><button type="button" class="pc-btn pc-btn--secondary" data-close-modal>Fermer</button>`;
                form.insertAdjacentElement("afterend", successMessage);
                const closeBtn =
                  successMessage.querySelector("[data-close-modal]");
                if (closeBtn)
                  closeBtn.addEventListener("click", function () {
                    closeContactModal();
                    closeDevisSheet();
                  });
              } else {
                alert(
                  "Erreur : " +
                    (data.data ? data.data.message : "Veuillez réessayer.")
                );
                submitBtn.textContent = originalBtnText;
                submitBtn.disabled = false;
              }
            })
            .catch((error) => {
              console.error("Erreur Fetch:", error);
              alert("Une erreur technique est survenue.");
              submitBtn.textContent = originalBtnText;
              submitBtn.disabled = false;
            });
        });
        if (closeContactModalTriggers) {
          closeContactModalTriggers.forEach((trigger) =>
            trigger.addEventListener("click", closeContactModal)
          );
        }
      }

      if (fab) fab.addEventListener("click", openDevisSheet);
      if (closeSheetTriggers)
        closeSheetTriggers.forEach((trigger) =>
          trigger.addEventListener("click", closeDevisSheet)
        );
      if (openContactModalBtn)
        openContactModalBtn.addEventListener("click", handleBookingRequest);
      if (openLodgifyBtn)
        openLodgifyBtn.addEventListener("click", handleLodgifyRedirect);

      document.addEventListener("devisLogementUpdated", function () {
        updateFabText();
        updateModalInfo();
      });

      document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
          if (contactModal && !contactModal.classList.contains("is-hidden"))
            closeContactModal();
          else if (devisSheet && devisSheet.classList.contains("is-open"))
            closeDevisSheet();
        }
      });
    }

    if (devisMoved) {
      setTimeout(initializeBookingLogic, 50);
    } else {
      initializeBookingLogic();
    }
  } // fin de initLogementBooking()

  // Branche orchestrateur : si présent, on lui délègue le timing
  if (
    window.PCOrchestrator &&
    typeof window.PCOrchestrator.registerLogementInit === "function"
  ) {
    window.PCOrchestrator.registerLogementInit(initLogementBooking);
  } else {
    // Fallback : comportement actuel si l'orchestrateur n'est pas chargé
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", initLogementBooking);
    } else {
      initLogementBooking();
    }
  }
})();
