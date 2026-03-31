import { defineStore } from "pinia";

export const usePdfTemplateStore = defineStore("pdfTemplate", {
  state: () => ({
    templates: [], // La liste de tous les modèles PDF
    currentTemplate: null, // Le modèle PDF en cours d'édition
    loading: false,
    error: null,
  }),

  actions: {
    async fetchTemplates() {
      this.loading = true;
      this.error = null;
      try {
        const formData = new FormData();
        formData.append("action", "pc_get_pdf_templates");
        formData.append("security", window.pcReservationVars.nonce);

        const response = await fetch(window.pcReservationVars.ajax_url, {
          method: "POST",
          body: formData,
        });
        const result = await response.json();
        if (result.success) {
          this.templates = result.data.items;
        } else {
          throw new Error(result.data?.message || "Erreur de chargement");
        }
      } catch (err) {
        this.error = err.message;
      } finally {
        this.loading = false;
      }
    },

    async fetchTemplateDetails(postId) {
      this.loading = true;
      this.error = null;
      try {
        const formData = new FormData();
        formData.append("action", "pc_get_pdf_template_details");
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
            result.data?.message || "Erreur de chargement des détails",
          );
        }
      } catch (err) {
        this.error = err.message;
      } finally {
        this.loading = false;
      }
    },

    async saveTemplate(templateData) {
      this.loading = true;
      this.error = null;
      try {
        const formData = new FormData();
        formData.append("action", "pc_save_pdf_template");
        formData.append("security", window.pcReservationVars.nonce);
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
        return { success: false, message: err.message };
      } finally {
        this.loading = false;
      }
    },

    async deleteTemplate(postId) {
      this.loading = true;
      this.error = null;
      try {
        const formData = new FormData();
        formData.append("action", "pc_delete_pdf_template");
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
          throw new Error(result.data?.message || "Erreur de suppression");
        }
      } catch (err) {
        this.error = err.message;
        return { success: false, message: err.message };
      } finally {
        this.loading = false;
      }
    },

    resetCurrentTemplate() {
      this.currentTemplate = null;
    },
  },
});
