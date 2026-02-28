/**
 * Composant : Bottom-Sheet de Réservation
 * Fichier : assets/js/components/pc-booking-sheet.js
 * Rôle : Gère l'affichage, l'animation et la validation intermédiaire du panneau de devis.
 */
document.addEventListener("DOMContentLoaded", function () {
  class PCBookingSheet {
    constructor() {
      this.sheet = document.getElementById("exp-devis-sheet");
      this.devisWrap = document.querySelector(
        ".exp-devis-wrap[data-exp-devis]",
      );
      this.closeTriggers = document.querySelectorAll(
        "[data-close-devis-sheet]",
      );
      this.openModalBtn = document.getElementById("exp-open-modal-btn-local");
      this.errorMsg = document.getElementById("exp-devis-error-msg");

      if (!this.sheet || !this.devisWrap) return;

      this.init();
    }

    init() {
      this.bindEvents();
    }

    open() {
      this.sheet.setAttribute("aria-hidden", "false");
      this.sheet.classList.add("is-open");
      document.body.style.overflow = "hidden"; // Verrouille le scroll
      sessionStorage.setItem("devisSheetOpened", "true");

      const firstInput = this.devisWrap.querySelector(
        'select[name="devis_type"]',
      );
      if (firstInput) firstInput.focus();
    }

    close() {
      this.sheet.setAttribute("aria-hidden", "true");
      this.sheet.classList.remove("is-open");
      document.body.style.overflow = ""; // Déverrouille le scroll
    }

    handleBookingRequest() {
      if (document.activeElement) document.activeElement.blur();

      // Utilise la variable globale maintenue par notre calculateur
      if (window.hasValidSimulation) {
        if (this.errorMsg) this.errorMsg.classList.remove("is-visible");
        this.close();

        // Demande l'ouverture de la modale de contact via un événement personnalisé
        document.dispatchEvent(new CustomEvent("pcOpenContactModal"));
      } else {
        // Affiche l'erreur DANS la sheet
        if (this.errorMsg) {
          this.errorMsg.textContent =
            "Merci de remplir les champs (Adultes, Enfants, Bébés) pour faire une simulation avant de demander une réservation.";
          this.errorMsg.classList.add("is-visible");
        }

        // Fait vibrer le calculateur pour attirer l'attention
        this.devisWrap.style.transition = "transform 0.1s ease-in-out";
        this.devisWrap.style.transform = "translateX(-10px)";
        setTimeout(() => {
          this.devisWrap.style.transform = "translateX(10px)";
          setTimeout(() => {
            this.devisWrap.style.transform = "translateX(0px)";
          }, 100);
        }, 100);
      }
    }

    bindEvents() {
      // Écoute la demande d'ouverture (venant du FAB)
      document.addEventListener("pcOpenDevisSheet", () => this.open());

      // Fermeture de la sheet
      this.closeTriggers.forEach((trigger) => {
        trigger.addEventListener("click", () => this.close());
      });

      // Validation et passage à l'étape suivante
      if (this.openModalBtn) {
        this.openModalBtn.addEventListener("click", () =>
          this.handleBookingRequest(),
        );
      }

      // Touche Echap (Ferme la sheet uniquement si la modale de contact n'est pas par-dessus)
      document.addEventListener("keydown", (event) => {
        if (
          event.key === "Escape" &&
          this.sheet.classList.contains("is-open")
        ) {
          const contactModal = document.getElementById("exp-booking-modal");
          if (!contactModal || contactModal.classList.contains("is-hidden")) {
            this.close();
          }
        }
      });
    }
  }

  // Initialisation
  new PCBookingSheet();
});
