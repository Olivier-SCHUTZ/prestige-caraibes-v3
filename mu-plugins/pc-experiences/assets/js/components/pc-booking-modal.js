/**
 * Composant : Modale de Réservation (Contact)
 * Fichier : assets/js/components/pc-booking-modal.js
 * Rôle : Gère l'affichage du résumé, la modale de contact et la soumission du formulaire en AJAX.
 */
document.addEventListener("DOMContentLoaded", function () {
  class PCBookingModal {
    constructor() {
      this.modal = document.getElementById("exp-booking-modal");
      if (!this.modal) return;

      this.summaryContainer = this.modal.querySelector(
        ".exp-booking-fieldset:first-of-type",
      );
      this.summaryContent = document.getElementById("modal-quote-summary");
      this.hiddenDetails = document.getElementById(
        "modal-quote-details-hidden",
      );
      this.closeTriggers = this.modal.querySelectorAll("[data-close-modal]");
      this.form = document.getElementById("experience-booking-form");

      const devisWrap = document.querySelector(
        ".exp-devis-wrap[data-exp-devis]",
      );
      this.pendingLabel = devisWrap
        ? devisWrap.dataset.labelPending || "En attente de devis"
        : "En attente de devis";

      this.init();
    }

    init() {
      this.bindEvents();
    }

    open() {
      this.modal.setAttribute("aria-hidden", "false");
      this.modal.classList.remove("is-hidden");
      document.body.style.overflow = "hidden"; // Verrouille le scroll

      const firstInput = this.modal.querySelector('input[name="prenom"]');
      if (firstInput) firstInput.focus();
    }

    close() {
      this.modal.setAttribute("aria-hidden", "true");
      this.modal.classList.add("is-hidden");
      document.body.style.overflow = ""; // Déverrouille le scroll

      // Gère la réinitialisation du formulaire après succès
      if (this.form) {
        const successMessage = this.form.parentNode.querySelector(
          ".form-success-message",
        );
        if (successMessage) {
          successMessage.remove();
          this.form.style.display = "block";
        }
      }
    }

    updateSummary() {
      const showPending =
        window.isSurDevis === true && window.hasValidSimulation === true;
      const showPriced =
        typeof window.currentTotal !== "undefined" && window.currentTotal > 0;

      if (showPending || showPriced) {
        let summaryHTML = "<ul>";
        let detailsText = "";

        (window.currentLines || []).forEach((line) => {
          if (line.isError) return;
          if (line.isSeparator) {
            summaryHTML += `<li class="separator"><strong>${line.label}</strong></li>`;
            detailsText += `\n--- ${line.label} ---\n`;
          } else {
            const priceTxt = showPending ? this.pendingLabel : line.price;
            summaryHTML += `<li><span>${line.label}</span><span>${priceTxt}</span></li>`;
            detailsText += `${line.label}: ${priceTxt}\n`;
          }
        });

        summaryHTML += "</ul>";

        const finalPriceDisplay = showPending
          ? this.pendingLabel
          : window.formatCurrency(window.currentTotal);
        detailsText += `\nTotal: Réserver pour : ${finalPriceDisplay}`;

        if (this.summaryContainer)
          this.summaryContainer.style.display = "block";
        if (this.summaryContent) this.summaryContent.innerHTML = summaryHTML;
        if (this.hiddenDetails) this.hiddenDetails.value = detailsText;
      } else {
        if (this.summaryContainer) this.summaryContainer.style.display = "none";
        if (this.summaryContent) this.summaryContent.innerHTML = "";
        if (this.hiddenDetails)
          this.hiddenDetails.value =
            "Aucune simulation de devis n'a été effectuée.";
      }
    }

    handleFormSubmit(event) {
      event.preventDefault();

      const submitBtn = this.form.querySelector('button[type="submit"]');
      const originalBtnText = submitBtn.textContent;
      submitBtn.textContent = "Envoi en cours...";
      submitBtn.disabled = true;

      const formData = new FormData(this.form);

      // Ajout des infos noyau réservation si le plugin est actif
      if (window.pcResaCoreActive) {
        formData.append("total", String(window.currentTotal || 0));
        formData.append(
          "lines_json",
          JSON.stringify(window.currentLines || []),
        );
        formData.append("is_sur_devis", window.isSurDevis ? "1" : "0");

        const typeSelect = document.querySelector('select[name="devis_type"]');
        if (typeSelect) formData.append("devis_type", typeSelect.value || "");

        const dateInput = this.form.querySelector(
          'input[name="date_experience"]',
        );
        if (dateInput)
          formData.append("date_experience", dateInput.value || "");

        // Pousse aussi les participants du calculateur
        const devisWrap = document.querySelector("[data-exp-devis]");
        if (devisWrap) {
          const adultsInput = devisWrap.querySelector(
            'input[name="devis_adults"]',
          );
          const childrenInput = devisWrap.querySelector(
            'input[name="devis_children"]',
          );
          const bebesInput = devisWrap.querySelector(
            'input[name="devis_bebes"]',
          );

          if (adultsInput)
            formData.append("devis_adults", adultsInput.value || "0");
          if (childrenInput)
            formData.append("devis_children", childrenInput.value || "0");
          if (bebesInput)
            formData.append("devis_bebes", bebesInput.value || "0");
        }
      }

      fetch(this.form.getAttribute("action"), {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            this.form.style.display = "none";

            // Émet un événement global pour dire que la réservation a réussi (le FAB écoute ça)
            document.dispatchEvent(new CustomEvent("pcBookingSuccess"));

            const successMessage = document.createElement("div");
            successMessage.className = "form-success-message";
            successMessage.innerHTML = `<h4>Merci ${formData.get("prenom")} !</h4><p>${data.data.message}</p><button type="button" class="pc-btn pc-btn--secondary" data-close-modal>Fermer</button>`;

            this.form.insertAdjacentElement("afterend", successMessage);
            successMessage
              .querySelector("[data-close-modal]")
              .addEventListener("click", () => this.close());
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
    }

    bindEvents() {
      // Écoute la demande d'ouverture (venant de la Bottom-Sheet)
      document.addEventListener("pcOpenContactModal", () => {
        this.updateSummary();
        this.open();
      });

      // Fermeture de la modale
      this.closeTriggers.forEach((trigger) => {
        trigger.addEventListener("click", () => this.close());
      });

      // Soumission du formulaire
      if (this.form) {
        this.form.addEventListener("submit", (e) => this.handleFormSubmit(e));
      }

      // Touche Echap
      document.addEventListener("keydown", (event) => {
        if (
          event.key === "Escape" &&
          !this.modal.classList.contains("is-hidden")
        ) {
          this.close();
        }
      });
    }
  }

  // Initialisation
  new PCBookingModal();
});
