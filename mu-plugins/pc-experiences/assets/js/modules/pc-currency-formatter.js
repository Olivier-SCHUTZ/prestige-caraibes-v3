/**
 * Module : Formateur de devises
 * Fichier : assets/js/modules/pc-currency-formatter.js
 * Rôle : Fournit des utilitaires globaux pour le formatage des prix (HTML et PDF)
 */
(function (window) {
  "use strict";

  // Formatage classique (Affichage HTML)
  window.formatCurrency = function (num) {
    num = Number(num) || 0;
    return new Intl.NumberFormat("fr-FR", {
      style: "currency",
      currency: "EUR",
    }).format(num);
  };

  // Normalisation des montants pour jsPDF (évite les slashs / espaces insécables)
  window.formatCurrencyPDF = function (input) {
    if (typeof input !== "string") {
      input = window.formatCurrency(input);
    }
    return String(input)
      .replace(/\//g, " ")
      .replace(/\u00A0|\u202F/g, " ")
      .replace(/\s{2,}/g, " ")
      .trim();
  };
})(window);
