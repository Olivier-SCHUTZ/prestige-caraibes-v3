import { defineStore } from "pinia";
import documentApi from "../services/document-api";

export const useDocumentStore = defineStore("documents", {
  state: () => ({
    templates: [],
    documents: [],

    isLoadingTemplates: false,
    isLoadingDocuments: false,
    isGenerating: false,

    error: null,
    successMessage: null,

    // NOUVEAU : Gestion des blocages légaux (Factures existantes)
    requiresForce: false,
    forceMessage: null,
    pendingTemplateId: null,
  }),

  actions: {
    async fetchTemplates(reservationId) {
      this.isLoadingTemplates = true;
      this.error = null;
      try {
        const response = await documentApi.getTemplates(reservationId);
        const data = response.data.data || response.data;
        if (response.data.success || data.success) {
          // Le PHP renvoie les modèles dans un objet "documents" groupé (native/custom)
          this.templates = data.documents || {};
        } else {
          throw new Error(
            data.message || "Erreur lors du chargement des modèles.",
          );
        }
      } catch (err) {
        this.error = err.message;
      } finally {
        this.isLoadingTemplates = false;
      }
    },

    async fetchDocuments(reservationId) {
      this.isLoadingDocuments = true;
      this.error = null;
      try {
        const response = await documentApi.getDocuments(reservationId);
        const data = response.data.data || response.data;
        if (response.data.success || data.success) {
          this.documents = data.files || [];
        } else {
          throw new Error(
            data.message || "Erreur lors du chargement des documents.",
          );
        }
      } catch (err) {
        this.error = err.message;
      } finally {
        this.isLoadingDocuments = false;
      }
    },

    async generateDocument(reservationId, templateId, forceRegenerate = false) {
      this.isGenerating = true;
      this.error = null;
      this.successMessage = null;
      this.requiresForce = false; // Reset au début de chaque tentative

      try {
        const response = await documentApi.generateDocument(
          reservationId,
          templateId,
          forceRegenerate,
        );
        const data = response.data.data || response.data;

        if (response.data.success || data.success) {
          this.successMessage = "Document généré avec succès !";
          this.pendingTemplateId = null;
          // Recharger la liste des documents pour afficher le nouveau PDF
          await this.fetchDocuments(reservationId);
          return true;
        } else {
          // Interception du blocage PHP (ex: Facture existante ou Acompte manquant)
          if (
            data.error_code === "document_exists" ||
            data.error_code === "missing_deposit"
          ) {
            this.requiresForce = true;
            this.forceMessage = data.message;
            this.pendingTemplateId = templateId; // On mémorise quel modèle on voulait générer
            return false;
          }

          throw new Error(
            data.message || "Échec de la génération du document.",
          );
        }
      } catch (err) {
        this.error = err.message;
        return false;
      } finally {
        this.isGenerating = false;
      }
    },

    async deleteDocument(documentId, reservationId) {
      try {
        const response = await documentApi.deleteDocument(
          documentId,
          reservationId,
        );
        const data = response.data.data || response.data;
        if (response.data.success || data.success) {
          // Mise à jour locale rapide
          this.documents = this.documents.filter(
            (doc) => doc.id !== documentId,
          );
          return true;
        }
      } catch (err) {
        this.error = "Impossible de supprimer le document.";
        return false;
      }
    },

    clearMessages() {
      this.error = null;
      this.successMessage = null;
      this.requiresForce = false;
      this.forceMessage = null;
      this.pendingTemplateId = null;
    },
  },
});
