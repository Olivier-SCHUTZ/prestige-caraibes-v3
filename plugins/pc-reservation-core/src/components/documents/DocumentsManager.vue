<template>
  <div class="documents-manager">
    <div v-if="store.error" class="pc-alert pc-alert-danger">
      {{ store.error }}
      <button class="close-btn" @click="store.clearMessages">&times;</button>
    </div>
    <div v-if="store.successMessage" class="pc-alert pc-alert-success">
      {{ store.successMessage }}
      <button class="close-btn" @click="store.clearMessages">&times;</button>
    </div>

    <div
      v-if="store.requiresForce"
      class="pc-alert pc-alert-warning pc-legal-alert"
    >
      <div class="pc-alert-content" style="width: 100%">
        <strong>⚠️ Action requise :</strong>
        <p style="margin: 8px 0" v-html="store.forceMessage"></p>
        <div
          class="pc-alert-actions"
          style="margin-top: 15px; display: flex; gap: 10px"
        >
          <button
            class="pc-btn-outline pc-btn-warning"
            @click="handleForceGenerate"
            :disabled="store.isGenerating"
          >
            {{
              store.isGenerating
                ? "Création en cours..."
                : "✅ Je comprends, générer l'avoir et la nouvelle facture"
            }}
          </button>
          <button
            class="pc-btn-outline"
            @click="store.clearMessages"
            :disabled="store.isGenerating"
          >
            ❌ Annuler
          </button>
        </div>
      </div>
    </div>

    <DocumentTemplateSelector
      :templates="store.templates"
      :loading="store.isLoadingTemplates"
      :isGenerating="store.isGenerating"
      @generate="handleGenerate"
    />

    <DocumentsList
      :documents="store.documents"
      :loading="store.isLoadingDocuments"
      @delete="handleDelete"
    />
  </div>
</template>

<script setup>
import { onMounted } from "vue";
import { useDocumentStore } from "@/stores/document-store";
import DocumentTemplateSelector from "./DocumentTemplateSelector.vue";
import DocumentsList from "./DocumentsList.vue";

const props = defineProps({
  reservationId: { type: [Number, String], required: true },
  reservationType: { type: String, default: "location" }, // 'location' ou 'experience'
});

const store = useDocumentStore();

onMounted(() => {
  // Au chargement du composant, on fetch les données
  store.fetchTemplates(props.reservationId);
  store.fetchDocuments(props.reservationId);
});

const handleGenerate = async (templateId) => {
  // 1er essai : génération normale (force = false)
  const success = await store.generateDocument(
    props.reservationId,
    templateId,
    false,
  );
  if (success) openLatestDocument();
};

const handleForceGenerate = async () => {
  if (!store.pendingTemplateId) return;

  // 2ème essai : l'utilisateur a accepté l'avertissement légal, on force (force = true)
  const success = await store.generateDocument(
    props.reservationId,
    store.pendingTemplateId,
    true,
  );
  if (success) openLatestDocument();
};

const openLatestDocument = () => {
  // Ouvre automatiquement le PDF fraîchement généré dans un nouvel onglet
  if (store.documents && store.documents.length > 0) {
    const doc = store.documents[0]; // Le PHP renvoie la liste avec le plus récent en premier
    const url = doc.url_fichier || doc.url || doc.secure_download_url;
    if (url) window.open(url, "_blank");
  }
};

const handleDelete = async (documentId) => {
  if (confirm("Es-tu sûr de vouloir supprimer ce document définitivement ?")) {
    await store.deleteDocument(documentId, props.reservationId);
  }
};
</script>

<style scoped>
.documents-manager {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.pc-alert {
  padding: 12px 15px;
  border-radius: 4px;
  margin-bottom: 15px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.pc-alert-danger {
  background-color: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
}
.pc-alert-success {
  background-color: #d4edda;
  color: #155724;
  border: 1px solid #c3e6cb;
}
.pc-alert-warning {
  background-color: #fff3cd;
  color: #856404;
  border: 1px solid #ffeeba;
}
.pc-legal-alert {
  align-items: flex-start;
}
.pc-btn-outline {
  background: white;
  border: 1px solid #cbd5e1;
  padding: 8px 12px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.9em;
  font-weight: 600;
  color: #333;
  transition: all 0.2s;
}
.pc-btn-outline:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.pc-btn-warning {
  border-color: #856404;
  color: #856404;
}
.pc-btn-warning:hover:not(:disabled) {
  background: #856404;
  color: white;
}
.close-btn {
  background: none;
  border: none;
  font-size: 1.2rem;
  cursor: pointer;
  color: inherit;
}
</style>
