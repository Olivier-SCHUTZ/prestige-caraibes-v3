/**
 * Module : PC State Manager
 * Rôle : Gérer l'état global de la réservation (dates, montants, règles)
 * et notifier les composants UI des changements.
 */
class PCStateManager {
  constructor() {
    this.state = {
      selection: null,
      total: 0,
      lines: [],
      isManualQuote: false,
    };
    // Système d'abonnement aux événements
    this.listeners = new Map();

    // 🌉 PONT DE RÉTROCOMPATIBILITÉ
    // On écoute l'ancien calculateur (pc-devis.js) pour synchroniser le nouveau système
    document.addEventListener("devisLogementUpdated", () => {
      const total = window.currentLogementTotal || 0;
      const lines = window.currentLogementLines || [];
      const selection = window.currentLogementSelection || null;

      // Mise à jour silencieuse de l'état interne
      this.state.total = total;
      this.state.lines = lines;
      this.state.selection = selection;

      // Notification à nos nouveaux composants (comme le FAB)
      this.emit("totalUpdated", { total, lines });
      this.emit("selectionChanged", selection);
    });
  }

  /**
   * S'abonner à un changement d'état
   */
  on(event, callback) {
    if (!this.listeners.has(event)) {
      this.listeners.set(event, []);
    }
    this.listeners.get(event).push(callback);
  }

  /**
   * Déclencher un événement
   */
  emit(event, data) {
    if (this.listeners.has(event)) {
      this.listeners.get(event).forEach((callback) => callback(data));
    }
    // NOTE: On ne dispatch plus 'devisLogementUpdated' ici pour éviter une boucle infinie avec le constructor.
  }

  /**
   * Mettre à jour la sélection (dates, voyageurs)
   */
  updateSelection(selection) {
    this.state.selection = selection;

    // Maintien de la variable globale pour non-régression
    window.currentLogementSelection = selection;

    this.emit("selectionChanged", selection);
  }

  /**
   * Mettre à jour les totaux financiers
   */
  updateTotal(total, lines) {
    this.state.total = total;
    this.state.lines = lines;

    // Maintien des variables globales pour non-régression
    window.currentLogementTotal = total;
    window.currentLogementLines = lines;

    this.emit("totalUpdated", { total, lines });
  }

  /**
   * Définir le mode de devis (manuel vs automatique)
   */
  setManualQuote(isManual) {
    this.state.isManualQuote = isManual;
    this.emit("manualQuoteChanged", isManual);
  }

  /**
   * Récupérer l'état actuel en lecture seule
   */
  getState() {
    return { ...this.state };
  }
}

// Initialisation de l'instance globale
window.pcStateManager = new PCStateManager();
