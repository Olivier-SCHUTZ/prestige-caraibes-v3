/**
 * Module utilitaires pour PC Reservation Core
 * Contient les fonctions utilitaires communes utilisées dans le dashboard
 *
 * @namespace window.PCR.Utils
 */

// Initialisation du namespace global
window.PCR = window.PCR || {};

/**
 * Utilitaires pour le formatage, parsing et validation des données
 */
window.PCR.Utils = {
  /**
   * Formateur de devise français (EUR)
   * @type {Intl.NumberFormat}
   */
  currencyFormatter: new Intl.NumberFormat("fr-FR", {
    style: "currency",
    currency: "EUR",
  }),

  /**
   * Formate un montant en devise française (EUR)
   * @param {number} amount - Le montant à formater
   * @returns {string} Le montant formaté (ex: "125,50 €")
   */
  formatPrice: function (amount) {
    return this.currencyFormatter.format(amount || 0);
  },

  /**
   * Échappe les caractères HTML spéciaux pour éviter les injections XSS
   * @param {*} value - La valeur à échapper
   * @returns {string} La chaîne avec les caractères HTML échappés
   */
  escapeHtml: function (value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  },

  /**
   * Parse du JSON de manière sécurisée avec gestion d'erreurs
   * @param {string} value - La chaîne JSON à parser
   * @returns {*|null} L'objet parsé ou null en cas d'erreur
   */
  parseJSONSafe: function (value) {
    if (!value) {
      return null;
    }
    try {
      return JSON.parse(value);
    } catch (error) {
      console.error("JSON parse error", error);
      return null;
    }
  },

  /**
   * Décode le texte avec gestion des caractères Unicode échappés
   * @param {*} value - La valeur à décoder
   * @returns {string} Le texte décodé
   */
  decodeText: function (value) {
    if (value == null) {
      return "";
    }
    let str = String(value);

    // Décodage des séquences Unicode \uXXXX
    str = str.replace(/\\u([0-9a-fA-F]{4})/g, (_m, g1) => {
      try {
        return JSON.parse('"\\u' + g1 + '"');
      } catch (e) {
        return _m;
      }
    });

    // Décodage des séquences Unicode uXXXX (sans antislash)
    str = str.replace(/u([0-9a-fA-F]{4})/g, (_m, g1) => {
      try {
        return JSON.parse('"\\u' + g1 + '"');
      } catch (e) {
        return _m;
      }
    });

    // Remplacement des espaces insécables
    str = str.replace(/\u00a0|\u202f/g, " ");
    return str;
  },

  /**
   * Rend le résumé des lignes stockées dans le DOM
   * @param {Array} lines - Tableau des lignes à afficher
   * @param {HTMLElement} summaryBody - Élément DOM où injecter le HTML des lignes
   * @param {HTMLElement} summaryTotal - Élément DOM où afficher le total
   * @param {number} totalValue - Valeur totale à afficher
   */
  renderStoredLinesSummary: function (
    lines,
    summaryBody,
    summaryTotal,
    totalValue,
  ) {
    if (!summaryBody || !Array.isArray(lines) || lines.length === 0) {
      return;
    }

    let html = "<ul>";
    lines.forEach((line) => {
      const rawLabel = this.decodeText(line.label || "");
      const rawPrice = this.decodeText(line.price || "");

      let formattedPrice = rawPrice;
      const numericPrice = parseFloat(
        rawPrice.replace(/[^\d,\.-]/g, "").replace(",", "."),
      );
      if (!Number.isNaN(numericPrice) && rawPrice !== "") {
        formattedPrice = this.formatPrice(numericPrice);
      }
      const separator = formattedPrice ? " \u2013 " : "";
      html += `<li><span>${rawLabel}</span><span>${separator}${formattedPrice}</span></li>`;
    });
    html += "</ul>";

    summaryBody.innerHTML = html;

    if (summaryTotal) {
      const numericTotal =
        typeof totalValue === "number"
          ? totalValue
          : parseFloat(totalValue || 0);
      summaryTotal.textContent = this.formatPrice(numericTotal);
    }
  },
};

console.log("[PC Reservation Core] Module Utils chargé ✅", window.PCR.Utils);
