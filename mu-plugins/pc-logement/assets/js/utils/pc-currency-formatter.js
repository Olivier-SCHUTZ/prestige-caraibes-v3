/**
 * Utilitaire : PC Currency Formatter
 * Rôle : Standardiser l'affichage des prix en euros sur tout le site.
 */
class PCCurrencyFormatter {
  /**
   * Formate un nombre en devise (EUR) en supprimant les décimales inutiles.
   * @param {number|string} amount - Le montant à formater
   * @returns {string} Le montant formaté (ex: 1 500 € au lieu de 1 500,00 €)
   */
  static format(amount) {
    const num = Number(amount) || 0;
    const formatted = new Intl.NumberFormat("fr-FR", {
      style: "currency",
      currency: "EUR",
    }).format(num);

    // Nettoyage esthétique : on enlève les ",00" si le compte est rond
    return formatted.endsWith(",00")
      ? formatted.slice(0, -3) + " €"
      : formatted;
  }
}

// On l'expose globalement pour que les autres modules et l'ancien code puissent l'utiliser
window.PCCurrencyFormatter = PCCurrencyFormatter;
