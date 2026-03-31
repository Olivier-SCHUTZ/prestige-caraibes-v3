import { defineStore } from "pinia";

export const useTemplateStore = defineStore("template", {
  state: () => ({
    templates: [], // La liste de tous les modèles
    currentTemplate: null, // Le modèle actuellement édité dans la modale
    loading: false,
    error: null,
  }),

  actions: {
    /**
     * Récupère la liste de tous les modèles de messages
     */
    async fetchTemplates() {
      this.loading = true;
      this.error = null;

      try {
        const formData = new FormData();
        formData.append("action", "pc_get_message_templates");
        formData.append("security", window.pcReservationVars.nonce);

        const response = await fetch(window.pcReservationVars.ajax_url, {
          method: "POST",
          body: formData,
        });

        const result = await response.json();

        if (result.success) {
          this.templates = result.data.items;
        } else {
          throw new Error(
            result.data?.message || "Erreur lors du chargement des modèles",
          );
        }
      } catch (err) {
        this.error = err.message;
        console.error("[Template Store] Erreur fetchTemplates:", err);
      } finally {
        this.loading = false;
      }
    },

    /**
     * Récupère les détails (metas) d'un modèle spécifique pour l'édition
     */
    async fetchTemplateDetails(postId) {
      this.loading = true;
      this.error = null;

      try {
        const formData = new FormData();
        formData.append("action", "pc_get_message_template_details");
        formData.append("security", window.pcReservationVars.nonce);
        formData.append("post_id", postId);

        const response = await fetch(window.pcReservationVars.ajax_url, {
          method: "POST",
          body: formData,
        });

        const result = await response.json();

        if (result.success) {
          this.currentTemplate = result.data.data;
        } else {
          throw new Error(
            result.data?.message || "Erreur lors du chargement des détails",
          );
        }
      } catch (err) {
        this.error = err.message;
        console.error("[Template Store] Erreur fetchTemplateDetails:", err);
      } finally {
        this.loading = false;
      }
    },

    /**
     * Sauvegarde (Création ou Mise à jour) d'un modèle
     */
    async saveTemplate(templateData) {
      this.loading = true;
      this.error = null;

      try {
        const formData = new FormData();
        formData.append("action", "pc_save_message_template");
        formData.append("security", window.pcReservationVars.nonce);

        // On envoie toutes les données (titre, contenu, metas) en JSON stringifié
        formData.append("payload", JSON.stringify(templateData));

        const response = await fetch(window.pcReservationVars.ajax_url, {
          method: "POST",
          body: formData,
        });

        const result = await response.json();

        if (result.success) {
          return {
            success: true,
            message: result.data.message,
            updatedId: result.data.id,
          };
        } else {
          throw new Error(
            result.data?.message || "Erreur lors de la sauvegarde",
          );
        }
      } catch (err) {
        this.error = err.message;
        console.error("[Template Store] Erreur saveTemplate:", err);
        return { success: false, message: err.message };
      } finally {
        this.loading = false;
      }
    },

    /**
     * Supprime un modèle (le met à la corbeille ou le supprime)
     */
    async deleteTemplate(postId) {
      this.loading = true;
      this.error = null;

      try {
        const formData = new FormData();
        formData.append("action", "pc_delete_message_template");
        formData.append("security", window.pcReservationVars.nonce);
        formData.append("post_id", postId);

        const response = await fetch(window.pcReservationVars.ajax_url, {
          method: "POST",
          body: formData,
        });

        const result = await response.json();

        if (result.success) {
          return { success: true, message: result.data.message };
        } else {
          throw new Error(
            result.data?.message || "Erreur lors de la suppression",
          );
        }
      } catch (err) {
        this.error = err.message;
        console.error("[Template Store] Erreur deleteTemplate:", err);
        return { success: false, message: err.message };
      } finally {
        this.loading = false;
      }
    },

    /**
     * Vide l'état courant (utile quand on ferme la modale d'édition ou qu'on clique sur "Nouveau")
     */
    resetCurrentTemplate() {
      this.currentTemplate = null;
    },
  },
});
