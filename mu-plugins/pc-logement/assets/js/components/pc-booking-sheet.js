/**
 * Composant : PC Booking Sheet (Bottom Sheet Mobile)
 * Rôle : Contrôler l'interface utilisateur du panneau de réservation (classes, aria, body overflow).
 */
class PCBookingSheet {
  constructor() {
    this.sheet = document.getElementById("logement-devis-sheet");
    this.isOpen = false;
  }

  /**
   * Ouvre le panneau mobile
   */
  open() {
    if (!this.sheet || this.isOpen) return;
    this.sheet.setAttribute("aria-hidden", "false");
    this.sheet.classList.add("is-open");

    // Empêche le défilement de la page derrière le panneau
    document.body.style.overflow = "hidden";

    // Mémorise l'ouverture pour l'expérience utilisateur
    sessionStorage.setItem("logementSheetOpened", "true");

    this.isOpen = true;
  }

  /**
   * Ferme le panneau mobile
   */
  close() {
    if (!this.sheet || !this.isOpen) return;
    this.sheet.setAttribute("aria-hidden", "true");
    this.sheet.classList.remove("is-open");

    // Rétablit le défilement de la page
    document.body.style.overflow = "";

    this.isOpen = false;
  }
}

// Initialisation immédiate et exposition globale pour que l'ancien code puisse "utiliser la télécommande"
window.pcBookingSheet = new PCBookingSheet();
