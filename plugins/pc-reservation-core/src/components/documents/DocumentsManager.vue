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
  const success = await store.generateDocument(props.reservationId, templateId);
  if (success) {
    // Optionnel : tu pourrais déclencher un toast ici si tu as un système global
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
.close-btn {
  background: none;
  border: none;
  font-size: 1.2rem;
  cursor: pointer;
  color: inherit;
}
</style>
