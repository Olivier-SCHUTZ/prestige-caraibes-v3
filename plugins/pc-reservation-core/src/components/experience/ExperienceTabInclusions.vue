<template>
  <div class="pc-tab-inclusions">
    <div class="pc-form-grid" style="display: grid; gap: 20px">
      <div class="pc-form-group pc-form-group--full">
        <label style="display: block; font-weight: 600; margin-bottom: 5px"
          >Le prix comprend</label
        >
        <textarea
          v-model="experience.exp_prix_comprend"
          class="pc-textarea"
          rows="4"
          placeholder="Ex: Le carburant, le skipper, les boissons rafraîchissantes..."
          style="
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
            resize: vertical;
          "
        ></textarea>
      </div>

      <div class="pc-form-group pc-form-group--full">
        <label style="display: block; font-weight: 600; margin-bottom: 5px"
          >Le prix ne comprend pas</label
        >
        <textarea
          v-model="experience.exp_prix_ne_comprend_pas"
          class="pc-textarea"
          rows="4"
          placeholder="Ex: Le repas du midi, le transfert depuis l'hôtel..."
          style="
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
            resize: vertical;
          "
        ></textarea>
      </div>

      <div
        class="pc-form-group pc-form-group--full"
        style="
          background: #f8fafc;
          padding: 20px;
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
          >Équipements à prévoir</label
        >
        <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 15px">
          Sélectionnez ce que le client doit apporter le jour de l'expérience :
        </p>

        <div style="display: flex; flex-wrap: wrap; gap: 15px">
          <label
            v-for="item in equipementsList"
            :key="item.value"
            style="
              display: flex;
              align-items: center;
              gap: 8px;
              cursor: pointer;
              background: white;
              padding: 6px 12px;
              border-radius: 20px;
              border: 1px solid #cbd5e0;
              font-size: 0.9rem;
            "
          >
            <input
              type="checkbox"
              :value="item.value"
              v-model="safeArray('exp_a_prevoir').value"
            />
            {{ item.label }}
          </label>
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

// Liste standard des équipements (basée sur ton domaine nautique/excursions)
const equipementsList = [
  { label: "Maillot de bain", value: "maillot_de_bain" },
  { label: "Serviette", value: "serviette" },
  { label: "Crème solaire", value: "creme_solaire" },
  { label: "Casquette / Chapeau", value: "casquette" },
  { label: "Lunettes de soleil", value: "lunettes_de_soleil" },
  { label: "Vêtements de rechange", value: "vetements_rechange" },
  { label: "Baskets / Chaussures fermées", value: "baskets" },
  { label: "Coupe-vent", value: "coupe_vent" },
  { label: "Bouteille d'eau", value: "bouteille_eau" },
  { label: "Appareil photo", value: "appareil_photo" },
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
