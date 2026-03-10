<template>
  <div class="v2-tab-content">
    <h4 class="v2-tab-title">Informations Générales</h4>

    <div class="pc-form-grid">
      <div class="pc-form-group" v-if="modalStore.housingId === 0">
        <label>Type de logement <span class="text-red-500">*</span></label>
        <select v-model="modalStore.formData.type" class="pc-select">
          <option value="" disabled>Sélectionner un type</option>
          <option value="villa">Villa</option>
          <option value="appartement">Appartement</option>
        </select>
        <small class="pc-help-text"
          >Le type ne pourra plus être modifié après la création.</small
        >
      </div>

      <div class="pc-form-group pc-form-group--full">
        <label>Nom du logement <span class="text-red-500">*</span></label>
        <input
          type="text"
          v-model="modalStore.formData.title"
          class="pc-input"
          placeholder="Ex: Villa Paradis"
        />
      </div>

      <div class="pc-form-group">
        <label>Identifiant Lodgify</label>
        <input
          type="text"
          v-model="modalStore.formData.identifiant_lodgify"
          class="pc-input"
        />
      </div>

      <div class="pc-form-group">
        <label>Capacité (personnes)</label>
        <input
          type="number"
          v-model="modalStore.formData.capacite"
          class="pc-input"
          min="1"
        />
      </div>

      <div class="pc-form-group">
        <label>Superficie (m²)</label>
        <input
          type="number"
          v-model="modalStore.formData.superficie"
          class="pc-input"
          min="1"
        />
      </div>

      <div class="pc-form-group">
        <label>Nombre de chambres</label>
        <input
          type="number"
          v-model="modalStore.formData.nombre_de_chambres"
          class="pc-input"
          min="0"
        />
      </div>

      <div class="pc-form-group">
        <label>Salles de bain</label>
        <input
          type="number"
          v-model="modalStore.formData.nombre_sdb"
          class="pc-input"
          min="0"
          step="0.5"
        />
      </div>

      <div class="pc-form-group">
        <label>Nombre de lits</label>
        <input
          type="number"
          v-model="modalStore.formData.nombre_lits"
          class="pc-input"
          min="0"
        />
      </div>

      <div class="pc-form-group pc-form-group--full">
        <label>Description du logement</label>
        <textarea
          v-model="modalStore.formData.content"
          class="pc-textarea"
          rows="6"
          placeholder="Décrivez le logement..."
        ></textarea>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useHousingModalStore } from "../../stores/housing-modal-store.js";

const modalStore = useHousingModalStore();
</script>

<style scoped>
.v2-tab-title {
  margin-top: 0;
  margin-bottom: 1.5rem;
  font-size: 1.25rem;
  color: #0f172a;
  border-bottom: 2px solid #f1f5f9;
  padding-bottom: 0.5rem;
}

.pc-form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.5rem;
}

.pc-form-group {
  display: flex;
  flex-direction: column;
}

.pc-form-group--full {
  grid-column: 1 / -1;
}

.pc-form-group label {
  font-weight: 500;
  color: #334155;
  margin-bottom: 0.5rem;
  font-size: 0.95rem;
}

.pc-input,
.pc-select,
.pc-textarea {
  padding: 0.75rem 1rem;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  background: white;
  font-size: 0.95rem;
  font-family: inherit;
  transition: border-color 0.2s;
  /* CORRECTIONS WORDPRESS */
  box-sizing: border-box;
  height: auto !important; /* Force WP à ignorer sa hauteur par défaut */
  line-height: 1.5;
}

.pc-input:focus,
.pc-select:focus,
.pc-textarea:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.pc-textarea {
  resize: vertical;
}

.text-red-500 {
  color: #ef4444;
}

.pc-help-text {
  font-size: 0.8rem;
  color: #64748b;
  margin-top: 0.25rem;
}
</style>
