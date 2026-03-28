<template>
  <div class="pc-tab-sorties">
    <div
      class="pc-form-grid"
      style="
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
        margin-bottom: 30px;
      "
    >
      <div class="pc-form-group">
        <label style="display: block; font-weight: 600; margin-bottom: 5px"
          >Durée (h)</label
        >
        <input
          type="number"
          v-model="experience.exp_duree"
          class="pc-input"
          placeholder="Ex: 4"
          step="0.5"
          style="
            width: 100%;
            padding: 8px;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
          "
        />
      </div>
      <div class="pc-form-group">
        <label style="display: block; font-weight: 600; margin-bottom: 5px"
          >Capacité max (pers)</label
        >
        <input
          type="number"
          v-model="experience.exp_capacite"
          class="pc-input"
          placeholder="Ex: 12"
          style="
            width: 100%;
            padding: 8px;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
          "
        />
      </div>
      <div class="pc-form-group">
        <label style="display: block; font-weight: 600; margin-bottom: 5px"
          >Âge minimum</label
        >
        <input
          type="number"
          v-model="experience.exp_age_minimum"
          class="pc-input"
          placeholder="Ex: 3"
          style="
            width: 100%;
            padding: 8px;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
          "
        />
      </div>
    </div>

    <div
      style="
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-bottom: 30px;
        padding: 20px;
        background: #f8fafc;
        border-radius: 8px;
      "
    >
      <div>
        <label
          style="
            display: block;
            font-weight: 600;
            margin-bottom: 10px;
            color: #1e293b;
          "
          >Jours d'ouverture</label
        >
        <div style="display: flex; flex-wrap: wrap; gap: 10px">
          <label
            v-for="jour in [
              'Lundi',
              'Mardi',
              'Mercredi',
              'Jeudi',
              'Vendredi',
              'Samedi',
              'Dimanche',
            ]"
            :key="jour"
            style="
              display: flex;
              align-items: center;
              gap: 5px;
              cursor: pointer;
            "
          >
            <input
              type="checkbox"
              :value="jour.toLowerCase()"
              v-model="safeArray('exp_jour').value"
            />
            {{ jour }}
          </label>
        </div>
      </div>

      <div>
        <label
          style="
            display: block;
            font-weight: 600;
            margin-bottom: 10px;
            color: #1e293b;
          "
          >Période</label
        >
        <div style="display: flex; flex-wrap: wrap; gap: 10px">
          <label
            v-for="option in periodeOptions"
            :key="option.value"
            style="
              display: flex;
              align-items: center;
              gap: 5px;
              cursor: pointer;
            "
          >
            <input
              type="checkbox"
              :value="option.value"
              v-model="safeArray('exp_periode').value"
            />
            {{ option.label }}
          </label>
        </div>
      </div>
    </div>

    <div style="margin-bottom: 30px; padding: 20px; background: #f8fafc; border-radius: 8px;">
      <h4 style="margin: 0 0 15px 0; font-size: 1.1rem; color: #1e293b;">
        ♿ Accessibilité et niveau physique
      </h4>
      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 12px;">
        <label
          v-for="option in accessibiliteOptions"
          :key="option.value"
          style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer; color: #475569; font-size: 0.95rem;"
        >
          <input
            type="checkbox"
            :value="option.value"
            v-model="safeArray('exp_accessibilite').value"
            style="cursor: pointer; margin-top: 3px;"
          />
          {{ option.label }}
        </label>
      </div>
    </div>
    <div style="margin-bottom: 30px">
      <div
        style="
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 15px;
        "
      >
        <h4 style="margin: 0; font-size: 1.1rem; color: #1e293b">
          📍 Lieux et horaires de départ
        </h4>
        <button @click="addLieu" class="pc-btn pc-btn-sm pc-btn-secondary">
          <span>➕</span> Ajouter un lieu
        </button>
      </div>

      <div v-if="safeArray('exp_lieux_horaires_depart').value.length > 0">
        <div
          v-for="(lieu, index) in safeArray('exp_lieux_horaires_depart').value"
          :key="index"
          style="
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            position: relative;
          "
        >
          <div
            style="
              display: grid;
              grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
              gap: 10px;
            "
          >
            <div>
              <label style="font-size: 0.85rem; color: #64748b"
                >Lieu de départ</label
              >
              <input
                type="text"
                v-model="lieu.exp_lieu_depart"
                class="pc-input"
                placeholder="Ex: Marina"
                style="
                  width: 100%;
                  padding: 6px;
                  border: 1px solid #cbd5e0;
                  border-radius: 4px;
                "
              />
            </div>
            <div>
              <label style="font-size: 0.85rem; color: #64748b">Latitude</label>
              <input
                type="text"
                v-model="lieu.lat_exp"
                class="pc-input"
                placeholder="16.24"
                style="
                  width: 100%;
                  padding: 6px;
                  border: 1px solid #cbd5e0;
                  border-radius: 4px;
                "
              />
            </div>
            <div>
              <label style="font-size: 0.85rem; color: #64748b"
                >Longitude</label
              >
              <input
                type="text"
                v-model="lieu.longitude"
                class="pc-input"
                placeholder="-61.53"
                style="
                  width: 100%;
                  padding: 6px;
                  border: 1px solid #cbd5e0;
                  border-radius: 4px;
                "
              />
            </div>
            <div>
              <label style="font-size: 0.85rem; color: #64748b">Départ</label>
              <input
                type="time"
                v-model="lieu.exp_heure_depart"
                class="pc-input"
                style="
                  width: 100%;
                  padding: 6px;
                  border: 1px solid #cbd5e0;
                  border-radius: 4px;
                "
              />
            </div>
            <div>
              <label style="font-size: 0.85rem; color: #64748b">Retour</label>
              <input
                type="time"
                v-model="lieu.exp_heure_retour"
                class="pc-input"
                style="
                  width: 100%;
                  padding: 6px;
                  border: 1px solid #cbd5e0;
                  border-radius: 4px;
                "
              />
            </div>
          </div>
          <button
            @click="removeLieu(index)"
            class="pc-btn pc-btn-danger"
            style="
              position: absolute;
              top: -10px;
              right: -10px;
              padding: 4px 8px;
              border-radius: 50%;
              width: 24px;
              height: 24px;
              display: flex;
              align-items: center;
              justify-content: center;
              font-size: 10px;
            "
            title="Supprimer"
          >
            ✕
          </button>
        </div>
      </div>
      <div
        v-else
        style="
          padding: 15px;
          text-align: center;
          background: #f8fafc;
          border: 1px dashed #cbd5e0;
          border-radius: 8px;
          color: #64748b;
        "
      >
        Aucun lieu défini.
      </div>
    </div>

    <div>
      <div
        style="
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 15px;
        "
      >
        <h4 style="margin: 0; font-size: 1.1rem; color: #1e293b">
          ⛔ Périodes de fermeture
        </h4>
        <button @click="addFermeture" class="pc-btn pc-btn-sm pc-btn-secondary">
          <span>➕</span> Ajouter une fermeture
        </button>
      </div>

      <div v-if="safeArray('exp_periodes_fermeture').value.length > 0">
        <div
          v-for="(fermeture, index) in safeArray('exp_periodes_fermeture')
            .value"
          :key="index"
          style="
            background: #fff1f2;
            border: 1px solid #fecdd3;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            position: relative;
          "
        >
          <div
            style="display: grid; grid-template-columns: 1fr 1fr 2fr; gap: 10px"
          >
            <div>
              <label style="font-size: 0.85rem; color: #9f1239"
                >Date début</label
              >
              <input
                type="date"
                v-model="fermeture.date_debut"
                class="pc-input"
                style="
                  width: 100%;
                  padding: 6px;
                  border: 1px solid #fecdd3;
                  border-radius: 4px;
                "
              />
            </div>
            <div>
              <label style="font-size: 0.85rem; color: #9f1239">Date fin</label>
              <input
                type="date"
                v-model="fermeture.date_fin"
                class="pc-input"
                style="
                  width: 100%;
                  padding: 6px;
                  border: 1px solid #fecdd3;
                  border-radius: 4px;
                "
              />
            </div>
            <div>
              <label style="font-size: 0.85rem; color: #9f1239">Raison</label>
              <input
                type="text"
                v-model="fermeture.raison"
                class="pc-input"
                placeholder="Ex: Maintenance du bateau"
                style="
                  width: 100%;
                  padding: 6px;
                  border: 1px solid #fecdd3;
                  border-radius: 4px;
                "
              />
            </div>
          </div>
          <button
            @click="removeFermeture(index)"
            class="pc-btn pc-btn-danger"
            style="
              position: absolute;
              top: -10px;
              right: -10px;
              padding: 4px 8px;
              border-radius: 50%;
              width: 24px;
              height: 24px;
              display: flex;
              align-items: center;
              justify-content: center;
              font-size: 10px;
            "
            title="Supprimer"
          >
            ✕
          </button>
        </div>
      </div>
      <div
        v-else
        style="
          padding: 15px;
          text-align: center;
          background: #f8fafc;
          border: 1px dashed #cbd5e0;
          border-radius: 8px;
          color: #64748b;
        "
      >
        Aucune fermeture prévue.
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

// Helper utilitaire pour garantir qu'on a toujours un tableau pour le v-model
// sans faire planter Vue si l'API a renvoyé null ou undefined.
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

// ♿ OPTIONS D'ACCESSIBILITÉ (Récupérées depuis l'export ACF)
const accessibiliteOptions = [
  { value: 'accessible_pmr', label: 'Accessible aux personnes âgées ou mobilité réduite' },
  { value: 'accessible_pmr_f', label: 'Non accessible aux fauteuils roulants' },
  { value: 'poussettes', label: 'Accessible en poussette' },
  { value: 'animaux_admis', label: 'Animaux de compagnie admis' },
  { value: 'accessible_enfants', label: 'Accessible aux enfants' },
  { value: 'activité_physique', label: 'Activité physique ou sportive intense' },
  { value: 'activité_physique_m', label: 'Activité physique ou sportive moyenne' },
  { value: 'activité_physique_l', label: 'Activité physique ou sportive légère' }
];

// 📅 OPTIONS DE PÉRIODE (Récupérées depuis l'export ACF)
const periodeOptions = [
  { value: 'année', label: "toute l'année" },
  { value: 'saison', label: "en saison" },
  { value: 'réservation', label: "sur réservation" }
];

// Actions Lieux
const addLieu = () => {
  safeArray("exp_lieux_horaires_depart").value.push({
    exp_lieu_depart: "",
    lat_exp: "",
    longitude: "",
    exp_heure_depart: "",
    exp_heure_retour: "",
  });
};

const removeLieu = (index) => {
  safeArray("exp_lieux_horaires_depart").value.splice(index, 1);
};

// Actions Fermetures
const addFermeture = () => {
  safeArray("exp_periodes_fermeture").value.push({
    date_debut: "",
    date_fin: "",
    raison: "",
  });
};

const removeFermeture = (index) => {
  safeArray("exp_periodes_fermeture").value.splice(index, 1);
};
</script>
