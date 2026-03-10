import { defineStore } from "pinia";
import apiClient from "../services/api-client.js";

/**
 * Store Pinia pour le Dashboard
 * Architecture V2 - Gère l'état global et les appels API pour l'accueil de l'espace propriétaire.
 */
export const useDashboardStore = defineStore("dashboard", {
  // 1. STATE : Les données brutes stockées en mémoire
  state: () => ({
    isLoading: false,
    error: null,
    stats: {
      totalReservations: 0,
      revenue: 0,
      pendingMessages: 0,
    },
  }),

  // 2. GETTERS : Données calculées à partir du state (comme des computed properties)
  getters: {
    hasUnreadMessages: (state) => state.stats.pendingMessages > 0,
    formattedRevenue: (state) => {
      return new Intl.NumberFormat("fr-FR", {
        style: "currency",
        currency: "EUR",
      }).format(state.stats.revenue);
    },
  },

  // 3. ACTIONS : Les méthodes qui modifient le state ou font des appels API
  actions: {
    async fetchDashboardStats() {
      this.isLoading = true;
      this.error = null;

      try {
        // Appel AJAX vers le backend WordPress via notre client sécurisé
        // L'action 'pcr_get_dashboard_stats' devra être créée plus tard dans tes contrôleurs PHP v2
        const response = await apiClient.post("", {
          action: "pcr_get_dashboard_stats",
        });

        // Si le backend WordPress répond { success: true, data: { ... } }
        if (response.data && response.data.success) {
          this.stats = response.data.data;
        } else {
          throw new Error("Format de réponse invalide ou endpoint non prêt.");
        }
      } catch (err) {
        console.warn(
          "⚠️ [Store] Impossible de joindre l'API PHP (normal si le contrôleur n'est pas encore codé). Injection de fausses données pour le développement.",
        );
        this.error = "Affichage en mode hors-ligne/développement.";

        // MOCK DATA : Pour nous permettre d'avancer sur le Front-end
        // sans attendre que le backend PHP soit 100% terminé (Pattern Strangler)
        this.stats = {
          totalReservations: 12,
          revenue: 4250,
          pendingMessages: 3,
        };
      } finally {
        this.isLoading = false;
      }
    },
  },
});
