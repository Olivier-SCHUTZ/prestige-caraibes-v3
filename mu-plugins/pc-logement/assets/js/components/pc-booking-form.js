/**
 * Composant : PC Booking Form
 * Rôle : Gérer la soumission du formulaire, le calcul de l'échéancier Stripe,
 * et le remplissage dynamique de la modale.
 */
class PCBookingForm {
  constructor() {
    this.form = document.getElementById("logement-booking-form");
    this.devisSource = document.querySelector(".pc-devis-section");
    this.isManualQuote =
      this.devisSource && this.devisSource.dataset.manualQuote === "1";
    this.cfg = this.devisSource
      ? JSON.parse(this.devisSource.dataset.pcDevis || "{}")
      : {};

    if (this.form) {
      this.form.addEventListener("submit", this.handleSubmit.bind(this));
    }
  }

  /**
   * Met à jour le récapitulatif visuel et les champs cachés avant l'envoi
   */
  updateModalInfo() {
    const modalSummary = document.getElementById(
      "modal-quote-summary-logement",
    );
    const modalHiddenDetails = document.getElementById(
      "modal-quote-details-hidden-logement",
    );
    if (!modalSummary || !modalHiddenDetails) return;

    const sel = window.currentLogementSelection || null;
    const total = window.currentLogementTotal || 0;
    const lines = window.currentLogementLines || [];

    const ensureHidden = (name) => {
      let input = this.form
        ? this.form.querySelector(`input[name="${name}"]`)
        : null;
      if (!input && this.form) {
        input = document.createElement("input");
        input.type = "hidden";
        input.name = name;
        this.form.appendChild(input);
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
              Math.ceil((new Date(departure) - new Date(arrival)) / 86400000),
            )
          : 0;
      if (hArrival) hArrival.value = arrival;
      if (hDeparture) hDeparture.value = departure;
      if (hNights) hNights.value = String(nights);
      if (hAdults) hAdults.value = String(parseInt(sel.adults || 0, 10));
      if (hChildren) hChildren.value = String(parseInt(sel.children || 0, 10));
      if (hInfants) hInfants.value = String(parseInt(sel.infants || 0, 10));
      if (hManual) hManual.value = this.isManualQuote ? "1" : "0";
    }

    if (!this.isManualQuote && total > 0 && lines && lines.length > 0) {
      const isDirectBooking = this.cfg.bookingMode === "directe";

      if (!isDirectBooking) {
        let summaryHTML = "<ul>";
        let detailsText = "";
        lines.forEach((line) => {
          summaryHTML += `<li><span>${line.label}</span><span>${line.price}</span></li>`;
          detailsText += `${line.label}: ${line.price}\n`;
        });
        summaryHTML += "</ul>";
        summaryHTML += `<div style="text-align:right; font-weight:bold; margin-top:10px;">Total estimé : ${window.PCCurrencyFormatter.format(total)}</div>`;
        detailsText += `\nTotal: ${window.PCCurrencyFormatter.format(total)}`;

        modalSummary.innerHTML = summaryHTML;
        modalHiddenDetails.value = detailsText;

        const actionsContainer = document.querySelector(
          ".exp-booking-modal__actions",
        );
        if (actionsContainer) {
          actionsContainer.innerHTML = `
                        <p class="exp-booking-disclaimer">Cette demande est sans engagement.</p>
                        <button type="submit" class="pc-btn pc-btn--primary">Envoyer la demande</button>
                    `;
        }
        return;
      }

      // Mode Réservation Directe (Stripe)
      const payRules = this.cfg.payment || {
        mode: "acompte_plus_solde",
        deposit_val: 30,
        delay_days: 30,
      };
      let dueNow = total;
      let dueLater = 0;
      let isDeposit = false;

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

      let summaryHTML = '<div class="pc-invoice-lines">';
      let detailsText = "";
      lines.forEach((line) => {
        summaryHTML += `<div class="pc-invoice-row"><span class="pc-lbl">${line.label}</span><span class="pc-val">${line.price}</span></div>`;
        detailsText += `${line.label}: ${line.price}\n`;
      });
      summaryHTML += `<div class="pc-invoice-total"><span class="pc-lbl">Total</span><span class="pc-val">${window.PCCurrencyFormatter.format(total)}</span></div></div>`;
      detailsText += `\nTotal: ${window.PCCurrencyFormatter.format(total)}`;

      if (isDeposit && dueLater > 0) {
        const balanceDate = new Date(arrivalDate);
        balanceDate.setDate(
          arrivalDate.getDate() - (payRules.delay_days || 30),
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
                        <div class="pc-col-amount">${window.PCCurrencyFormatter.format(dueNow)}</div>
                    </div>
                    <div class="pc-schedule-row">
                        <div class="pc-col-date"><strong>Solde à venir</strong><br><span class="pc-text-muted">Le ${dateStr}</span></div>
                        <div class="pc-col-amount">${window.PCCurrencyFormatter.format(dueLater)}</div>
                    </div>
                </div>`;
      } else {
        summaryHTML += `
                <div class="pc-payment-schedule">
                    <div class="pc-schedule-row is-now">
                        <div class="pc-col-date"><strong>Total à régler</strong><br><span class="pc-tag-now">Immédiat</span></div>
                        <div class="pc-col-amount">${window.PCCurrencyFormatter.format(total)}</div>
                    </div>
                </div>`;
      }

      modalSummary.innerHTML = summaryHTML;
      modalHiddenDetails.value = detailsText;

      const actionsContainer = document.querySelector(
        ".exp-booking-modal__actions",
      );
      if (actionsContainer) {
        actionsContainer.innerHTML = `
                    <p class="exp-booking-disclaimer">Paiement sécurisé. Réservation confirmée immédiatement.</p>
                    <button type="submit" class="pc-btn pc-btn--primary pc-btn-full">💳 Payer ${window.PCCurrencyFormatter.format(dueNow)} (Carte Bancaire)</button>
                `;
      }
    } else {
      if (sel && sel.arrival && sel.departure) {
        const nights = hNights ? hNights.value : "";
        const recap = [
          "EN ATTENTE DE DEVIS PERSONNALISÉ",
          `Période : ${sel.arrival.split("-").reverse().join("/")} → ${sel.departure.split("-").reverse().join("/")}`,
          `Voyageurs : ${sel.adults || 0} adultes, ${sel.children || 0} enfants, ${sel.infants || 0} bébés`,
          nights ? `Nuits : ${nights}` : "",
        ]
          .filter(Boolean)
          .join("\n");
        modalSummary.innerHTML = `<pre class="pcq-manual-recap">${recap.replace(/\n/g, "<br>")}</pre>`;
        modalHiddenDetails.value = recap;
      } else {
        modalSummary.innerHTML = "<p>Choisissez vos dates</p>";
        modalHiddenDetails.value =
          "Aucune simulation de devis n'a été effectuée.";
      }
    }
  }

  /**
   * Gère le clic sur "Réserver" ou "Demander un devis"
   */
  handleBookingClick(ev) {
    if (ev) ev.preventDefault();

    const sel = window.currentLogementSelection;
    const hasRange = !!(sel && sel.arrival && sel.departure);
    const msgBox = document.getElementById("pc-devis-msg"); // ID par défaut

    if (!hasRange) {
      if (msgBox) {
        msgBox.textContent = "Choisissez vos dates";
        msgBox.classList.add("is-visible");
      } else alert("Choisissez vos dates");
      return;
    }

    if (this.isManualQuote) {
      if (msgBox) msgBox.classList.remove("is-visible");
      this.updateModalInfo();
      if (window.pcBookingSheet) window.pcBookingSheet.close();
      if (window.pcBookingModal) window.pcBookingModal.open();
      return;
    }

    const hasValidSimulation = window.currentLogementTotal > 0;
    if (hasValidSimulation) {
      if (msgBox) msgBox.classList.remove("is-visible");
      this.updateModalInfo();
      if (window.pcBookingSheet) window.pcBookingSheet.close();
      if (window.pcBookingModal) window.pcBookingModal.open();
    } else {
      const errorMessage =
        "Veuillez remplir le simulateur ci-dessus avant de faire une demande.";
      if (msgBox) {
        msgBox.textContent = errorMessage;
        msgBox.classList.add("is-visible");
      } else alert(errorMessage);
    }
  }

  /**
   * Gère l'envoi du formulaire via Fetch API
   */
  handleSubmit(event) {
    event.preventDefault();

    const nonceValue =
      typeof pcLogementData !== "undefined" && pcLogementData.nonce
        ? pcLogementData.nonce
        : null;
    const nonceInput = this.form.querySelector('input[name="nonce"]');
    if (nonceInput && nonceValue) nonceInput.value = nonceValue;

    const submitBtn = this.form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.textContent;
    submitBtn.textContent = "Envoi en cours...";
    submitBtn.disabled = true;

    const formData = new FormData(this.form);

    if (this.devisSource) {
      const adultsInput = this.devisSource.querySelector(
        'input[name="devis_adults"]',
      );
      const childrenInput = this.devisSource.querySelector(
        'input[name="devis_children"]',
      );
      const infantsInput = this.devisSource.querySelector(
        'input[name="devis_infants"]',
      );
      formData.append("adultes", adultsInput ? adultsInput.value : "0");
      formData.append("enfants", childrenInput ? childrenInput.value : "0");
      formData.append("bebes", infantsInput ? infantsInput.value : "0");

      const ss = window.currentLogementSelection || {};
      if (ss.arrival) formData.append("arrival", ss.arrival);
      if (ss.departure) formData.append("departure", ss.departure);
      const nn =
        ss.arrival && ss.departure
          ? Math.max(
              0,
              Math.ceil(
                (new Date(ss.departure) - new Date(ss.arrival)) / 86400000,
              ),
            )
          : 0;

      formData.append("nights", String(nn));
      formData.append("adults", String(parseInt(ss.adults || 0, 10)));
      formData.append("children", String(parseInt(ss.children || 0, 10)));
      formData.append("infants", String(parseInt(ss.infants || 0, 10)));
      formData.append("manual_quote", this.isManualQuote ? "1" : "0");

      if (window.pcResaCoreActive) {
        formData.append("total", String(window.currentLogementTotal || 0));
        formData.append(
          "lines_json",
          JSON.stringify(window.currentLogementLines || []),
        );
      }
    }

    fetch(this.form.getAttribute("action"), { method: "POST", body: formData })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          // Redirection Stripe
          if (data.data && data.data.payment_url) {
            const fabPriceDisplay = document.getElementById(
              "fab-logement-price-display",
            );
            if (fabPriceDisplay)
              fabPriceDisplay.textContent = "Redirection Paiement...";
            window.location.href = data.data.payment_url;
            return;
          }

          // Mode Devis Classique (Succès)
          this.form.style.display = "none";
          const fabPriceDisplay = document.getElementById(
            "fab-logement-price-display",
          );
          if (fabPriceDisplay) fabPriceDisplay.textContent = "Merci ! 🌴";

          const successMessage = document.createElement("div");
          successMessage.className = "form-success-message";
          const prenom = formData.get("prenom");
          const prenomSanitized = prenom
            ? prenom.replace(/</g, "&lt;").replace(/>/g, "&gt;")
            : "";

          successMessage.innerHTML = `<h4>Merci ${prenomSanitized} !</h4><p>${data.data.message}</p><button type="button" class="pc-btn pc-btn--secondary" data-close-modal>Fermer</button>`;
          this.form.insertAdjacentElement("afterend", successMessage);

          const closeBtn = successMessage.querySelector("[data-close-modal]");
          if (closeBtn) {
            closeBtn.addEventListener("click", () => {
              if (window.pcBookingModal) window.pcBookingModal.close();
              if (window.pcBookingSheet) window.pcBookingSheet.close();
            });
          }
        } else {
          alert(
            "Erreur : " +
              (data.data ? data.data.message : "Veuillez réessayer."),
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
  }
}

// Initialisation globale
window.pcBookingForm = new PCBookingForm();
