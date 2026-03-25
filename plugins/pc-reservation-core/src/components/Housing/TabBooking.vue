<template>
  <div class="v2-tab-content">
    <h4 class="pc-section-title">Conditions de séjour</h4>
    <div class="pc-form-grid">
      <div class="pc-form-group">
        <label>Horaire d'arrivée (Check-in)</label>
        <input
          type="time"
          v-model="modalStore.formData.horaire_arrivee"
          class="pc-input"
        />
      </div>

      <div class="pc-form-group">
        <label>Horaire de départ (Check-out)</label>
        <input
          type="time"
          v-model="modalStore.formData.horaire_depart"
          class="pc-input"
        />
      </div>

      <div class="pc-form-group pc-form-group--full">
        <label>Politique d'annulation</label>
        <textarea
          v-model="modalStore.formData.politique_dannulation"
          class="pc-textarea"
          rows="4"
          placeholder="Ex: Remboursement intégral jusqu'à 30 jours avant l'arrivée..."
        ></textarea>
      </div>

      <div class="pc-form-group pc-form-group--full">
        <label>Règles de la maison</label>
        <textarea
          v-model="modalStore.formData.regles_maison"
          class="pc-textarea"
          rows="4"
          placeholder="Ex: Pas de fêtes, respect du voisinage après 22h..."
        ></textarea>
      </div>
    </div>

    <h4 class="pc-section-title">Informations de l'hôte</h4>
    <div class="pc-form-grid">
      <div class="pc-form-group pc-form-group--full">
        <label>Nom de l'hôte</label>
        <input
          type="text"
          v-model="modalStore.formData.hote_nom"
          class="pc-input"
          placeholder="Ex: Marie & Jean"
        />
      </div>

      <div class="pc-form-group pc-form-group--full">
        <label>Description de l'hôte</label>
        <textarea
          v-model="modalStore.formData.hote_description"
          class="pc-textarea"
          rows="4"
          placeholder="Présentation de l'hôte..."
        ></textarea>
      </div>

      <div class="pc-form-group pc-form-group--full">
        <label>Photo de l'hôte (ID de l'image Media WP)</label>
        <input
          type="number"
          v-model="modalStore.formData.hote_photo"
          class="pc-input"
          placeholder="Ex: 1542"
        />
      </div>
    </div>

    <h4 class="pc-section-title">Intégrations externes</h4>
    <div class="pc-form-grid">
      <div class="pc-form-group pc-form-group--full">
        <label>Widget Lodgify (Code Embed HTML)</label>
        <textarea
          v-model="modalStore.formData.lodgify_widget_embed"
          class="pc-textarea pc-textarea-code"
          rows="4"
          placeholder='<div class="lodgify-widget">...</div>'
        ></textarea>
        <small class="pc-help-text"
          >Collez ici le code HTML/JS fourni par Lodgify pour afficher leur
          calendrier ou module de réservation.</small
        >
      </div>
    </div>
  </div>
</template>

<script setup>
import { useHousingModalStore } from "../../stores/housing-modal-store.js";

const modalStore = useHousingModalStore();
</script>

<style scoped>
.pc-section-title {
  margin-top: 3rem;
  margin-bottom: 1.5rem;
  font-size: 1.1rem;
  color: #1e293b;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 0.5rem;
}

.pc-section-title:first-child {
  margin-top: 0;
}

.pc-form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  column-gap: 2rem;
  row-gap: 1.5rem;
  margin-bottom: 2rem;
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
.pc-textarea {
  padding: 0.75rem 1rem;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  background: white;
  font-size: 0.95rem;
  font-family: inherit;
  box-sizing: border-box;
  height: auto !important;
  line-height: 1.5;
}

.pc-textarea {
  resize: vertical;
}

.pc-textarea-code {
  font-family: monospace;
  font-size: 0.85rem;
  background: #f8fafc;
}

.pc-help-text {
  font-size: 0.8rem;
  color: #64748b;
  margin-top: 0.5rem;
}
</style>
