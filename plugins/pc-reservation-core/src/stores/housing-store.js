import { defineStore } from "pinia";
import apiClient from "../services/api-client.js";

export const useHousingStore = defineStore("housing", {
  state: () => ({
    items: [],
    isLoading: false,
    error: null,
    pagination: {
      currentPage: 1,
      totalPages: 1,
    },
    filters: {
      search: "",
      status: "",
      mode: "",
      type: "",
    },
  }),

  actions: {
    async fetchHousings(page = 1) {
      this.isLoading = true;
      this.error = null;
      this.pagination.currentPage = page;

      try {
        const wpVars = window.pcReservationVars || { nonce: "" };
        const params = new URLSearchParams();

        // Champs standards pour correspondre à ton backend
        params.append("action", "pc_housing_get_list");
        params.append("page", this.pagination.currentPage);
        params.append("per_page", 20);
        params.append("search", this.filters.search);
        params.append("status_filter", this.filters.status);
        params.append("mode_filter", this.filters.mode); // Ajouté
        params.append("type_filter", this.filters.type); // Ajouté
        params.append("orderby", "title");
        params.append("order", "ASC");

        params.append("nonce", wpVars.nonce);
        params.append("security", wpVars.nonce);

        const response = await apiClient.post("", params);

        if (response.data && response.data.success) {
          this.items = response.data.data.items || [];
          this.pagination.totalPages = response.data.data.pages || 1;
          this.pagination.currentPage = response.data.data.current_page || 1;
        } else {
          throw new Error(response.data?.data?.message || "Erreur serveur");
        }
      } catch (err) {
        console.error("🚨 [Housing Store] Erreur:", err);
        this.error = "Erreur de chargement. Vérification de sécurité échouée.";
        this.items = [];
      } finally {
        this.isLoading = false;
      }
    },

    setFilters(newFilters) {
      this.filters = { ...this.filters, ...newFilters };
      this.fetchHousings(1); // Retour page 1 si on filtre
    },

    // PONT STRANGLER : Redirige vers la bonne fonction jQuery !
    openLegacyModal(id) {
      if (window.pcHousingManager) {
        if (id === 0) {
          console.log("🔄 Ouverture modale NOUVEAU logement");
          window.pcHousingManager.openNewHousingModal();
        } else {
          console.log("🔄 Ouverture modale EDITION logement #", id);
          window.pcHousingManager.openHousingModal(id);
        }
      } else {
        console.error(
          "❌ L'ancien script jQuery (pcHousingManager) est introuvable.",
        );
      }
    },
  },
});
