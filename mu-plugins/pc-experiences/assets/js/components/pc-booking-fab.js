/**
 * Composant : Bouton Flottant (FAB)
 * Fichier : assets/js/components/pc-booking-fab.js
 * Rôle : Gère l'apparition et la mise à jour du bouton de réservation flottant.
 */
document.addEventListener("DOMContentLoaded", function () {
  class PCBookingFAB {
    constructor() {
      this.fab = document.getElementById("exp-open-devis-sheet-btn");
      this.priceDisplay = document.getElementById("fab-price-display");
      this.devisWrap = document.querySelector(
        ".exp-devis-wrap[data-exp-devis]",
      );

      if (!this.fab) return;

      this.defaultText = this.priceDisplay
        ? this.priceDisplay.textContent
        : "Simuler un devis";
      this.pendingLabel = this.devisWrap
        ? this.devisWrap.dataset.labelPending || "En attente de devis"
        : "En attente de devis";

      this.init();
    }

    init() {
      this.initVisibility();
      this.bindEvents();
    }

    initVisibility() {
      // Apparition conditionnelle
      if (sessionStorage.getItem("devisSheetOpened")) {
        this.show();
      } else {
        this.timer = setTimeout(() => this.show(), 2000);
        this.scrollHandler = () => this.checkScroll();
        window.addEventListener("scroll", this.scrollHandler, {
          passive: true,
        });
      }
    }

    checkScroll() {
      const scrollThreshold = window.innerHeight * 0.3;
      if (window.scrollY > scrollThreshold) {
        this.show();
        clearTimeout(this.timer);
        window.removeEventListener("scroll", this.scrollHandler);
      }
    }

    show() {
      this.fab.classList.add("is-visible");
    }

    updatePrice() {
      if (!this.priceDisplay) return;

      const showPending =
        window.isSurDevis === true && window.hasValidSimulation === true;
      const showPriced =
        typeof window.currentTotal !== "undefined" && window.currentTotal > 0;

      if (showPending) {
        this.priceDisplay.textContent = "Réserver (" + this.pendingLabel + ")";
      } else if (showPriced) {
        this.priceDisplay.textContent =
          "Réserver pour : " + window.formatCurrency(window.currentTotal);
      } else {
        this.priceDisplay.textContent = this.defaultText;
      }
    }

    bindEvents() {
      // 1. Écoute le calculateur pour mettre à jour son prix
      if (this.devisWrap) {
        this.devisWrap.addEventListener("devisUpdated", () =>
          this.updatePrice(),
        );
      }

      // 2. Émet un événement global lors du clic (pour ouvrir la Bottom-Sheet)
      this.fab.addEventListener("click", () => {
        if (
          this.priceDisplay &&
          this.priceDisplay.textContent === "Merci ! 🌴"
        ) {
          this.priceDisplay.textContent = this.defaultText;
        }
        document.dispatchEvent(new CustomEvent("pcOpenDevisSheet"));
      });

      // 3. Écoute le succès final de la modale pour afficher "Merci"
      document.addEventListener("pcBookingSuccess", () => {
        if (this.priceDisplay) this.priceDisplay.textContent = "Merci ! 🌴";
      });
    }
  }

  // Initialisation
  new PCBookingFAB();
});
