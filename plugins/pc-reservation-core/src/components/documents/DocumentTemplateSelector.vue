<template>
  <div class="pc-doc-selector pcr-card-section">
    <h3>📄 Générer un nouveau document</h3>

    <div v-if="loading" class="text-muted">Chargement des modèles...</div>

    <div v-else class="pc-selector-flex">
      <select v-model="selectedTemplate" class="pc-select">
        <option value="" disabled>-- Choisir un modèle --</option>

        <optgroup
          v-for="(group, key) in templates"
          :key="key"
          :label="group.label"
        >
          <option v-for="tpl in group.items" :key="tpl.id" :value="tpl.id">
            {{ tpl.label }}
          </option>
        </optgroup>
      </select>

      <button
        class="btn-success"
        :disabled="!selectedTemplate || isGenerating"
        @click="emitGenerate"
      >
        <span v-if="isGenerating">⏳ Génération en cours...</span>
        <span v-else>⚙️ Générer le PDF</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from "vue";

const props = defineProps({
  templates: { type: [Array, Object], required: true }, // Modifié pour accepter l'objet groupé du backend
  loading: { type: Boolean, default: false },
  isGenerating: { type: Boolean, default: false },
});

const emit = defineEmits(["generate"]);
const selectedTemplate = ref("");

// Smart Select : Auto-sélection s'il n'y a qu'un seul modèle disponible
watch(
  () => props.templates,
  (newTemplates) => {
    if (!newTemplates) return;

    let totalItems = 0;
    let firstItemId = null;

    // Parcours des groupes (native / custom) pour compter les modèles
    Object.values(newTemplates).forEach((group) => {
      if (group && group.items && group.items.length > 0) {
        totalItems += group.items.length;
        if (!firstItemId) firstItemId = group.items[0].id;
      }
    });

    // S'il n'y a qu'un seul choix possible, on le sélectionne d'office
    if (totalItems === 1 && firstItemId) {
      selectedTemplate.value = firstItemId;
    }
  },
  { immediate: true, deep: true },
);

const emitGenerate = () => {
  if (selectedTemplate.value) {
    emit("generate", selectedTemplate.value);
  }
};
</script>

<style scoped>
.pc-doc-selector {
  margin-bottom: 20px;
}
.pc-selector-flex {
  display: flex;
  gap: 15px;
  align-items: center;
}
.pc-select {
  flex: 1;
  padding: 8px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  font-size: 1rem;
}
</style>
