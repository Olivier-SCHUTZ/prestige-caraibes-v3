<template>
  <div class="pc-tab-services">
    <div class="pc-form-grid" style="display: grid; gap: 20px">
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px">
        <div class="pc-form-group">
        <label style="display: block; font-weight: 600; margin-bottom: 5px;">
          Type de prestation
        </label>
        <input
          type="text"
          v-model="experience.exp_type_de_prestation"
          class="pc-input"
          placeholder="Ex: Massage relaxant"
          style="width: 100%; padding: 8px; border: 1px solid #cbd5e0; border-radius: 6px;"
        />
        <small style="color: #64748b; margin-top: 4px; display: block; line-height: 1.4;">
          Pour un chef à domicile (ex: "Créole, Française"). Pour un massage (ex: "Massage relaxant", "Soin du visage"). Pour un petit-déjeuner (ex: "Continental", "Créole"). Indique si le service à table est compris (ex: "Service à table inclus").
        </small>
      </div>

        <div class="pc-form-group">
        <label style="display: block; font-weight: 600; margin-bottom: 5px;">
          Heure limite de commande
        </label>
        <div style="display: flex; align-items: center; gap: 10px;">
          <input
            type="number"
            min="0"
            max="23"
            v-model="experience.exp_heure_limite_de_commande"
            class="pc-input"
            placeholder="Ex: 18"
            style="width: 100px; padding: 8px; border: 1px solid #cbd5e0; border-radius: 6px;"
          />
          <span style="color: #64748b;">h</span>
        </div>
        <small style="color: #64748b; margin-top: 4px; display: block;">
          Idéal pour les livraisons (ex: entrez "18" pour une commande avant 18h).
        </small>
      </div>
      </div>

      <div class="pc-form-group" style="margin-bottom: 20px;">
        <label style="display: block; font-weight: 600; margin-bottom: 10px;">
          Délai de réservation requis
        </label>
        <div style="display: flex; flex-wrap: wrap; gap: 15px;">
          <label
            v-for="option in delaiOptions"
            :key="option.value"
            style="display: flex; align-items: center; gap: 5px; cursor: pointer;"
          >
            <input
              type="checkbox"
              :value="option.value"
              v-model="safeArray('exp_delai_de_reservation').value"
            />
            {{ option.label }}
          </label>
        </div>
      </div>

      <div class="pc-form-group" style="margin-bottom: 20px;">
        <label style="display: block; font-weight: 600; margin-bottom: 10px;">
          Zones d'intervention / Départ
        </label>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px;">
          <label
            v-for="option in zoneOptions"
            :key="option.value"
            style="display: flex; align-items: center; gap: 5px; cursor: pointer;"
          >
            <input
              type="checkbox"
              :value="option.value"
              v-model="safeArray('exp_zone_intervention').value"
            />
            {{ option.label }}
          </label>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px">
        <div class="pc-form-group">
          <label style="display: block; font-weight: 600; margin-bottom: 5px"
            >Le service comprend</label
          >
          <textarea
            v-model="experience.exp_le_service_comprend"
            class="pc-textarea"
            rows="3"
            placeholder="Ex: Mise à disposition du matériel..."
            style="
              width: 100%;
              padding: 10px;
              border: 1px solid #cbd5e0;
              border-radius: 6px;
              resize: vertical;
            "
          ></textarea>
        </div>

        <div class="pc-form-group">
          <label style="display: block; font-weight: 600; margin-bottom: 5px"
            >Service à prévoir</label
          >
          <textarea
            v-model="experience.exp_service_a_prevoir"
            class="pc-textarea"
            rows="3"
            placeholder="Ex: Prévoir d'arriver 15 min en avance..."
            style="
              width: 100%;
              padding: 10px;
              border: 1px solid #cbd5e0;
              border-radius: 6px;
              resize: vertical;
            "
          ></textarea>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { useExperienceStore } from "../../stores/experience-store";

const store = useExperienceStore();
const { currentExperience: experience } = storeToRefs(store);

// ⏳ OPTIONS DÉLAI DE RÉSERVATION
const delaiOptions = [
  { value: '24h', label: "24h à l'avance" },
  { value: '48h', label: "48h à l'avance" },
  { value: '72h', label: "72h à l'avance" },
  { value: '1 semaine', label: "1 semaine à l'avance" },
  { value: 'Avant départ', label: "Avant le départ" }
];

// 🗺️ OPTIONS ZONES D'INTERVENTION
const zoneOptions = [
  { value: 'Guadeloupe', label: "Guadeloupe" },
  { value: 'Grande-Terre', label: "Grande-Terre" },
  { value: 'Basse-Terre', label: "Basse-Terre" },
  { value: 'Saint-François, alentours', label: "Saint-François, alentours" },
  { value: 'Sainte-Anne, alentours', label: "Sainte-Anne, alentours" },
  { value: 'Le Gosier, alentours', label: "Le Gosier, alentours" },
  { value: 'Morne à l\'Eau, alentours', label: "Morne à l'Eau, alentours" },
  { value: 'Baie-Mahault, alentours', label: "Baie-Mahault, alentours" },
  { value: 'Deshaies, alentours', label: "Deshaies, alentours" },
  { value: 'Bouillante, alentours', label: "Bouillante, alentours" },
  { value: 'Basse-Terre, alentours', label: "Basse-Terre, alentours" }
];

// Helper utilitaire pour sécuriser le tableau de checkboxes
const safeArray = (key) =>
  computed({
    get: () => {
      if (!experience.value[key] || !Array.isArray(experience.value[key])) {
        experience.value[key] = [];
      }
      return experience.value[key];
    },
    set: (val) => {
      experience.value[key] = val;
    },
  });
</script>
