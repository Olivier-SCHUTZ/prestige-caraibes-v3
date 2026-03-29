import { defineStore } from "pinia";

export const useDestinationStore = defineStore("destination", {
  state: () => ({
    destinations: [],
    currentDestination: null,
    loading: false,
    error: null,
    pagination: {
      total: 0,
      pages: 0,
      currentPage: 1,
      perPage: 20,
    },
    filters: {
      status: "all",
      search: "",
    },
  }),

  actions: {
    /**
     * Récupère la liste des destinations paginée et filtrée
     */
    async fetchDestinations(page = 1) {
      this.loading = true;
      this.error = null;

      try {
        const formData = new FormData();
        formData.append("action", "pc_get_destinations");
        // Utilisation des variables globales passées par pc-reservation-core.php
        formData.append("security", window.pcReservationVars.nonce);
        formData.append("page", page);
        formData.append("per_page", this.pagination.perPage);
        formData.append("status", this.filters.status);
        formData.append("search", this.filters.search);

        const response = await fetch(window.pcReservationVars.ajax_url, {
          method: "POST",
          body: formData,
        });

        const result = await response.json();

        if (result.success) {
          this.destinations = result.data.items;
          this.pagination.total = result.data.total;
          this.pagination.pages = result.data.pages;
          this.pagination.currentPage = result.data.current_page;
        } else {
          throw new Error(
            result.data?.message ||
              "Erreur lors du chargement des destinations",
          );
        }
      } catch (err) {
        this.error = err.message;
        console.error("[Destination Store] Erreur fetchDestinations:", err);
      } finally {
        this.loading = false;
      }
    },

    /**
     * Récupère les données complètes d'une seule destination (pour l'édition)
     */
    async fetchDestinationDetails(postId) {
      this.loading = true;
      this.error = null;

      try {
        const formData = new FormData();
        formData.append("action", "pc_get_destination_details");
        formData.append("security", window.pcReservationVars.nonce);
        formData.append("post_id", postId);

        const response = await fetch(window.pcReservationVars.ajax_url, {
          method: "POST",
          body: formData,
        });

        const result = await response.json();

        if (result.success) {
          this.currentDestination = result.data.data;
        } else {
          throw new Error(
            result.data?.message || "Erreur lors du chargement des détails",
          );
        }
      } catch (err) {
        this.error = err.message;
        console.error(
          "[Destination Store] Erreur fetchDestinationDetails:",
          err,
        );
      } finally {
        this.loading = false;
      }
    },

    /**
     * Sauvegarde (Création ou Mise à jour)
     */
    async saveDestination(destinationData) {
      this.loading = true;
      this.error = null;

      try {
        const formData = new FormData();
        formData.append("action", "pc_save_destination");
        formData.append("security", window.pcReservationVars.nonce);
        formData.append("post_id", destinationData.id || 0);

        // 🚀 PROTECTION ANTI-BUG [object Object] :
        // On transforme l'objet en JSON stringifié ici.
        // Le Backend PHP le décodera proprement.
        formData.append("payload", JSON.stringify(destinationData));

        const response = await fetch(window.pcReservationVars.ajax_url, {
          method: "POST",
          body: formData,
        });

        const result = await response.json();

        if (result.success) {
          return {
            success: true,
            message: result.data.message,
            updatedId: result.data.data.id,
          };
        } else {
          throw new Error(
            result.data?.message || "Erreur lors de la sauvegarde",
          );
        }
      } catch (err) {
        this.error = err.message;
        console.error("[Destination Store] Erreur saveDestination:", err);
        return { success: false, message: err.message };
      } finally {
        this.loading = false;
      }
    },

    /**
     * Vide l'état courant (utile quand on ferme la modale d'édition)
     */
    resetCurrentDestination() {
      this.currentDestination = null;
    },

    /**
     * Met à jour les filtres et relance la recherche depuis la page 1
     */
    setFilters(newFilters) {
      this.filters = { ...this.filters, ...newFilters };
      this.fetchDestinations(1);
    },
  },
});
