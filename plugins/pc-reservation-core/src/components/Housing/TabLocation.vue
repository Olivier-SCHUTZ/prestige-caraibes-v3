<template>
  <div class="v2-tab-content">
    <h4 class="pc-section-title">Adresse du logement</h4>
    <div class="pc-form-grid">
      <div class="pc-form-group pc-form-group--full">
        <label>Adresse (Rue, numéro)</label>
        <input
          type="text"
          v-model="modalStore.formData.adresse_rue"
          class="pc-input"
          placeholder="Ex: 123 Chemin des Alizés"
        />
      </div>

      <div class="pc-form-group">
        <label>Code Postal</label>
        <input
          type="text"
          v-model="modalStore.formData.code_postal"
          class="pc-input"
          placeholder="Ex: 97180"
        />
      </div>

      <div class="pc-form-group">
        <label>Ville</label>
        <input
          type="text"
          v-model="modalStore.formData.ville"
          class="pc-input"
          placeholder="Ex: Sainte-Anne"
        />
      </div>
    </div>

    <h4 class="pc-section-title">Coordonnées GPS</h4>
    <div class="pc-form-grid">
      <div class="pc-form-group">
        <label>Latitude</label>
        <input
          type="text"
          v-model="modalStore.formData.latitude"
          class="pc-input"
          placeholder="Ex: 16.225"
        />
      </div>

      <div class="pc-form-group">
        <label>Longitude</label>
        <input
          type="text"
          v-model="modalStore.formData.longitude"
          class="pc-input"
          placeholder="Ex: -61.383"
        />
      </div>

      <div class="pc-form-group">
        <label>Centre de la zone (Geo Coords)</label>
        <input
          type="text"
          v-model="modalStore.formData.geo_coords"
          class="pc-input"
          placeholder="Latitude, Longitude"
        />
      </div>

      <div class="pc-form-group">
        <label>Rayon d'affichage (mètres)</label>
        <input
          type="number"
          v-model="modalStore.formData.geo_radius_m"
          class="pc-input"
          placeholder="Par défaut: 600"
        />
      </div>
    </div>

    <h4 class="pc-section-title">Distances & Proximité (en km)</h4>
    <div class="pc-form-grid">
      <div class="pc-form-group">
        <label>Distance Aéroport (km)</label>
        <input
          type="number"
          v-model="modalStore.formData.prox_airport_km"
          class="pc-input"
          min="0"
          step="0.1"
        />
      </div>

      <div class="pc-form-group">
        <label>Distance Port (km)</label>
        <input
          type="number"
          v-model="modalStore.formData.prox_port_km"
          class="pc-input"
          min="0"
          step="0.1"
        />
      </div>

      <div class="pc-form-group">
        <label>Distance Plage (km)</label>
        <input
          type="number"
          v-model="modalStore.formData.prox_beach_km"
          class="pc-input"
          min="0"
          step="0.1"
        />
      </div>

      <div class="pc-form-group">
        <label>Distance Arrêt de bus (km)</label>
        <input
          type="number"
          v-model="modalStore.formData.prox_bus_km"
          class="pc-input"
          min="0"
          step="0.1"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { useHousingModalStore } from "../../stores/housing-modal-store.js";

const modalStore = useHousingModalStore();

// On s'assure que le rayon a une valeur par défaut de 600 comme dans l'ancien code
if (modalStore.housingId === 0 && !modalStore.formData.geo_radius_m) {
  modalStore.formData.geo_radius_m = 600;
}
</script>

<style scoped>
.pc-section-title {
  margin-top: 1.5rem;
  margin-bottom: 1rem;
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

.pc-input {
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

.pc-input:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}
</style>
