/**
 * Composant : PC Booking Modal
 * Rôle : Gérer l'ouverture et la fermeture de la modale de contact/réservation,
 * ainsi que le nettoyage visuel du formulaire.
 */
class PCBookingModal {
  constructor() {
    this.modal = document.getElementById("logement-booking-modal");
    this.form = document.getElementById("logement-booking-form");
    this.isOpen = false;
  }

  /**
   * Ouvre la modale
   */
  open() {
    if (!this.modal || this.isOpen) return;

    this.modal.setAttribute("aria-hidden", "false");
    this.modal.classList.remove("is-hidden");
    document.body.style.overflow = "hidden";

    // Focus automatique sur le premier champ pour l'accessibilité
    const prenomInput = this.modal.querySelector('input[name="prenom"]');
    if (prenomInput) setTimeout(() => prenomInput.focus(), 50);

    this.isOpen = true;
  }

  /**
   * Ferme la modale et réinitialise l'affichage du formulaire
   */
  close() {
    if (!this.modal || !this.isOpen) return;

    this.modal.setAttribute("aria-hidden", "true");
    this.modal.classList.add("is-hidden");
    document.body.style.overflow = "";

    // Nettoyage : Si un message de succès était affiché, on l'enlève et on remet le formulaire
    if (this.form) {
      const successMessage = this.form.parentNode.querySelector(
        ".form-success-message",
      );
      if (successMessage) {
        successMessage.remove();
        this.form.style.display = "block";
      }
    }

    this.isOpen = false;
  }
}

// Initialisation immédiate et exposition globale pour l'ancien code
window.pcBookingModal = new PCBookingModal();
