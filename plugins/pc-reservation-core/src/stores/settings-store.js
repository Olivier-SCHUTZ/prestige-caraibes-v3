import { defineStore } from "pinia";

export const useSettingsStore = defineStore("settings", {
  state: () => ({
    // Toutes nos configurations seront stockées ici
    settings: {},
    loading: false,
    error: null,
    saveSuccessMessage: "",
  }),

  actions: {
    /**
     * Récupère la configuration globale depuis l'API native
     */
    async fetchSettings() {
      this.loading = true;
      this.error = null;

      try {
        const formData = new FormData();
        formData.append("action", "pc_get_global_settings");
        // Utilisation des variables globales passées par le bridge dans pc-reservation-core.php
        formData.append("security", window.pcReservationVars.nonce);

        const response = await fetch(window.pcReservationVars.ajax_url, {
          method: "POST",
          body: formData,
        });

        const result = await response.json();

        if (result.success) {
          this.settings = result.data.data; // Le backend renvoie un objet avec les clés natives
        } else {
          throw new Error(
            result.data?.message || "Erreur lors du chargement des paramètres",
          );
        }
      } catch (err) {
        this.error = err.message;
        console.error("[Settings Store] Erreur fetchSettings:", err);
      } finally {
        this.loading = false;
      }
    },

    /**
     * Sauvegarde la configuration globale
     */
    async saveSettings(settingsData) {
      this.loading = true;
      this.error = null;
      this.saveSuccessMessage = "";

      try {
        const formData = new FormData();
        formData.append("action", "pc_save_global_settings");
        formData.append("security", window.pcReservationVars.nonce);

        // Envoi des données en JSON stringifié (comme pour les destinations)
        formData.append("payload", JSON.stringify(settingsData));

        const response = await fetch(window.pcReservationVars.ajax_url, {
          method: "POST",
          body: formData,
        });

        const result = await response.json();

        if (result.success) {
          this.saveSuccessMessage = result.data.message;
          // On met à jour le state local avec les nouvelles données
          this.settings = { ...this.settings, ...settingsData };
          return { success: true };
        } else {
          throw new Error(
            result.data?.message || "Erreur lors de la sauvegarde",
          );
        }
      } catch (err) {
        this.error = err.message;
        console.error("[Settings Store] Erreur saveSettings:", err);
        return { success: false, message: err.message };
      } finally {
        this.loading = false;

        // Efface le message de succès après 3 secondes
        if (this.saveSuccessMessage) {
          setTimeout(() => {
            this.saveSuccessMessage = "";
          }, 3000);
        }
      }
    },
  },
});
