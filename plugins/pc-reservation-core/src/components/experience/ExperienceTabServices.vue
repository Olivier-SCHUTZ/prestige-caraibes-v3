<template>
  <div class="pc-tab-services">
    <div class="pc-form-grid" style="display: grid; gap: 20px">
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px">
        <div class="pc-form-group">
          <label style="display: block; font-weight: 600; margin-bottom: 5px"
            >Type de prestation</label
          >
          <select
            v-model="experience.exp_type_de_prestation"
            class="pc-select"
            style="
              width: 100%;
              padding: 8px 12px;
              border: 1px solid #cbd5e0;
              border-radius: 6px;
              background: white;
            "
          >
            <option value="">Sélectionner un type...</option>
            <option value="sur_place">Sur place (Point de rendez-vous)</option>
            <option value="a_domicile">À domicile / Au logement</option>
            <option value="avec_transfert">Avec transfert inclus</option>
          </select>
        </div>

        <div class="pc-form-group">
          <label style="display: block; font-weight: 600; margin-bottom: 5px"
            >Heure limite de commande</label
          >
          <input
            type="time"
            v-model="experience.exp_heure_limite_de_commande"
            class="pc-input"
            style="
              width: 100%;
              padding: 8px 12px;
              border: 1px solid #cbd5e0;
              border-radius: 6px;
            "
          />
          <small style="color: #64748b; font-size: 0.8rem"
            >Heure max la veille (ou le jour même selon vos règles)</small
          >
        </div>
      </div>

      <div
        class="pc-form-group"
        style="
          background: #f8fafc;
          padding: 15px;
          border-radius: 8px;
          border: 1px solid #e2e8f0;
        "
      >
        <label
          style="
            display: block;
            font-weight: 600;
            margin-bottom: 10px;
            color: #1e293b;
          "
          >Délai de réservation requis</label
        >
        <div style="display: flex; flex-wrap: wrap; gap: 15px">
          <label
            v-for="delai in delaisList"
            :key="delai.value"
            style="
              display: flex;
              align-items: center;
              gap: 5px;
              cursor: pointer;
            "
          >
            <input
              type="checkbox"
              :value="delai.value"
              v-model="safeArray('exp_delai_de_reservation').value"
            />
            {{ delai.label }}
          </label>
        </div>
      </div>

      <div
        class="pc-form-group"
        style="
          background: #f8fafc;
          padding: 15px;
          border-radius: 8px;
          border: 1px solid #e2e8f0;
        "
      >
        <label
          style="
            display: block;
            font-weight: 600;
            margin-bottom: 10px;
            color: #1e293b;
          "
          >Zones d'intervention / Départ</label
        >
        <div style="display: flex; flex-wrap: wrap; gap: 15px">
          <label
            v-for="zone in zonesList"
            :key="zone.value"
            style="
              display: flex;
              align-items: center;
              gap: 5px;
              cursor: pointer;
            "
          >
            <input
              type="checkbox"
              :value="zone.value"
              v-model="safeArray('exp_zone_intervention').value"
            />
            {{ zone.label }}
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

const delaisList = [
  { label: "Immédiat", value: "immediat" },
  { label: "24h à l'avance", value: "24h" },
  { label: "48h à l'avance", value: "48h" },
  { label: "72h à l'avance", value: "72h" },
  { label: "1 semaine à l'avance", value: "1_semaine" },
];

const zonesList = [
  { label: "Grande-Terre", value: "grande_terre" },
  { label: "Basse-Terre", value: "basse_terre" },
  { label: "Les Saintes", value: "les_saintes" },
  { label: "Marie-Galante", value: "marie_galante" },
  { label: "La Désirade", value: "la_desirade" },
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
