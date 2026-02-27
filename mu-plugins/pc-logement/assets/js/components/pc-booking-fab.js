/**
 * Composant : PC Booking FAB (Floating Action Button)
 * Rôle : Gérer l'apparition et la mise à jour du texte du bouton flottant.
 */
class PCBookingFAB {
  constructor(stateManager) {
    this.stateManager = stateManager;
    this.fab = document.getElementById("logement-open-devis-sheet-btn");
    this.priceDisplay = document.getElementById("fab-logement-price-display");
    this.devisSource = document.querySelector(".pc-devis-section");

    if (!this.fab || !this.priceDisplay || !this.devisSource) return;

    // Récupération de la configuration initiale
    const cfg = JSON.parse(this.devisSource.dataset.pcDevis || "{}");
    this.basePrice = Number(cfg.basePrice || 0);
    this.isManualQuote = this.devisSource.dataset.manualQuote === "1";

    this.initialHTML = this.getInitialHTML();

    this.init();
  }

  init() {
    // 1. Définition du texte de base selon le mode
    if (this.isManualQuote) {
      this.setBaseLabel();
    }

    // 2. Gestion de l'apparition (Scroll ou Timer)
    this.initVisibility();

    // 3. Abonnement aux changements de prix via notre nouveau State Manager
    this.stateManager.on("totalUpdated", this.updateText.bind(this));
  }

  getInitialHTML() {
    if (this.isManualQuote) {
      const desk =
        this.basePrice > 0
          ? `À partir de ${window.PCCurrencyFormatter.format(this.basePrice)} sur devis`
          : "Sur devis";
      const mob =
        this.basePrice > 0
          ? `Dès ${window.PCCurrencyFormatter.format(this.basePrice)} — sur devis`
          : "Sur devis";
      return window.innerWidth <= 480 ? mob : desk;
    }
    return this.priceDisplay.innerHTML;
  }

  setBaseLabel() {
    const desk =
      this.basePrice > 0
        ? `À partir de ${window.PCCurrencyFormatter.format(this.basePrice)} sur devis`
        : "Sur devis";
    const mob =
      this.basePrice > 0
        ? `Dès ${window.PCCurrencyFormatter.format(this.basePrice)} — sur devis`
        : "Sur devis";
    this.priceDisplay.textContent = window.innerWidth <= 480 ? mob : desk;
  }

  initVisibility() {
    const showFab = () => this.fab.classList.add("is-visible");

    if (sessionStorage.getItem("logementSheetOpened")) {
      showFab();
    } else {
      const timer = setTimeout(showFab, 2000);
      const scrollThreshold = window.innerHeight * 0.3;

      const checkScroll = () => {
        if (window.scrollY > scrollThreshold) {
          showFab();
          clearTimeout(timer);
          window.removeEventListener("scroll", checkScroll);
        }
      };
      window.addEventListener("scroll", checkScroll, { passive: true });
    }
  }

  updateText(data) {
    if (this.isManualQuote) return;

    // Protection pour ne pas écraser les messages de succès ou de redirection
    const currentText = this.priceDisplay.textContent;
    if (currentText === "Merci ! 🌴" || currentText === "Redirection...")
      return;

    if (data && data.total > 0) {
      this.priceDisplay.textContent =
        "Réserver pour : " + window.PCCurrencyFormatter.format(data.total);
    } else {
      this.priceDisplay.innerHTML = this.initialHTML;
    }
  }
}

// Initialisation dès que le DOM est prêt
document.addEventListener("DOMContentLoaded", () => {
  // On vérifie que nos fondations sont bien chargées
  if (window.pcStateManager && window.PCCurrencyFormatter) {
    window.pcBookingFAB = new PCBookingFAB(window.pcStateManager);
  }
});
