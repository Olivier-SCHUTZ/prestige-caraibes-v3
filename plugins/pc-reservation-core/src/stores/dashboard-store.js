import { defineStore } from "pinia";
import apiClient from "../services/api-client"; // Assure-toi que ce fichier existe d'après tes migrations précédentes

export const useDashboardStore = defineStore("dashboard", {
  state: () => ({
    // Statistiques
    stats: {
      totalReservations: 0,
      revenue: 0,
      pendingMessages: 0,
    },

    // État UI
    isLoading: false,
    error: null,
  }),

  actions: {
    async fetchStats() {
      this.isLoading = true;
      this.error = null;

      try {
        // Utilisation de l'action définie dans class-dashboard-api-controller.php
        // Note : Ton apiClient doit gérer l'injection du nonce "security" ou tu dois l'ajouter ici
        const response = await apiClient.post("", {
          action: "pcr_get_dashboard_stats",
          security: window.pc_resa_globals?.nonce || "", // À adapter selon comment tu injectes tes variables JS globales
        });

        if (response.data.success) {
          this.stats = response.data.data;
        } else {
          throw new Error(response.data.data?.message || "Erreur API");
        }
      } catch (err) {
        console.error("Erreur Dashboard Store:", err);
        this.error = "Impossible de charger les statistiques.";
      } finally {
        this.isLoading = false;
      }
    },
  },
});
