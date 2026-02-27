/**
 * Intégration : PC Lodgify Connector
 * Rôle : Gérer la validation des données et la génération de l'URL de redirection vers le checkout Lodgify.
 */
class PCLodgifyConnector {
  constructor(config) {
    this.accountId = config.lodgifyAccount;
    this.websiteId = config.lodgifyId;
    this.baseUrl = "https://checkout.lodgify.com/fr/";
  }

  /**
   * Valide si la sélection actuelle permet une réservation Lodgify
   * @returns {string|null} Un message d'erreur si invalide, null si tout est OK
   */
  validateSelection(selection) {
    if (!selection)
      return "Veuillez d'abord sélectionner vos dates et le nombre d'invités.";
    if (!selection.arrival || !selection.departure)
      return "Veuillez sélectionner vos dates d'arrivée et de départ.";
    if (!selection.adults || selection.adults < 1)
      return "Veuillez indiquer au moins 1 adulte.";
    if (!this.websiteId || !this.accountId)
      return "Erreur de configuration, impossible de générer le lien de réservation.";

    return null;
  }

  /**
   * Construit l'URL complète avec les paramètres de réservation
   */
  buildUrl(selection) {
    const adults = parseInt(selection.adults, 10) || 0;
    const children = parseInt(selection.children, 10) || 0;
    const infants =
      selection.infants != null ? parseInt(selection.infants, 10) || 0 : 0;

    return `${this.baseUrl}${this.accountId}/${this.websiteId}/contact?currency=EUR&arrival=${selection.arrival}&departure=${selection.departure}&adults=${adults}&children=${children}&infants=${infants}`;
  }

  /**
   * Tente de rediriger l'utilisateur vers la page de paiement
   * @throws {Error} Si la validation échoue ou si le popup est bloqué
   */
  redirectToCheckout(selection) {
    // 1. Validation
    const error = this.validateSelection(selection);
    if (error) {
      throw new Error(error);
    }

    // 2. Construction de l'URL
    const url = this.buildUrl(selection);

    // 3. Redirection (ouverture dans un nouvel onglet)
    const newWindow = window.open(url, "_blank");

    if (!newWindow) {
      throw new Error("PopupBlocked");
    }

    return true;
  }
}

// On l'expose globalement pour que l'ancien code puisse l'utiliser
window.PCLodgifyConnector = PCLodgifyConnector;
