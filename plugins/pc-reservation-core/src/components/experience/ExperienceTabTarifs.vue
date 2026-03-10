<template>
  <div class="pc-tab-tarifs">
    <div
      style="
        margin-bottom: 20px;
        padding: 15px;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 15px;
      "
    >
      <label style="font-weight: 600; color: #1e293b"
        >Taux de TVA par défaut (%)</label
      >
      <input
        type="number"
        v-model="experience.taux_tva"
        class="pc-input"
        placeholder="Ex: 8.5"
        step="0.1"
        style="
          width: 100px;
          padding: 8px;
          border: 1px solid #cbd5e0;
          border-radius: 6px;
        "
      />
    </div>

    <div v-if="tarifs.length > 0">
      <div
        v-for="(tarif, tIndex) in tarifs"
        :key="tIndex"
        style="
          border: 2px solid #e2e8f0;
          padding: 20px;
          margin-bottom: 25px;
          border-radius: 12px;
          background: white;
          box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        "
      >
        <div
          style="
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
          "
        >
          <div style="display: flex; gap: 15px; flex: 1">
            <div style="width: 250px">
              <label
                style="display: block; font-weight: 600; margin-bottom: 5px"
                >Type de configuration</label
              >
              <select
                v-model="tarif.exp_type"
                class="pc-select"
                style="
                  width: 100%;
                  padding: 8px;
                  border: 1px solid #cbd5e0;
                  border-radius: 6px;
                "
              >
                <option value="unique">Unique / Forfaitaire</option>
                <option value="journee">Journée</option>
                <option value="demi-journee">Demi-journée</option>
                <option value="sur-devis">Sur Devis</option>
                <option value="custom">Personnalisé</option>
              </select>
            </div>
            <div
              v-if="tarif.exp_type === 'custom'"
              style="flex: 1; max-width: 300px"
            >
              <label
                style="display: block; font-weight: 600; margin-bottom: 5px"
                >Nom personnalisé</label
              >
              <input
                type="text"
                v-model="tarif.exp_type_custom"
                class="pc-input"
                placeholder="Ex: Soirée VIP"
                style="
                  width: 100%;
                  padding: 8px;
                  border: 1px solid #cbd5e0;
                  border-radius: 6px;
                "
              />
            </div>
          </div>
          <button
            @click="removeTarifType(tIndex)"
            class="pc-btn pc-btn-danger pc-btn-sm"
            title="Supprimer ce bloc"
          >
            <span>🗑️</span> Retirer
          </button>
        </div>

        <div style="margin-bottom: 20px">
          <h4 style="margin: 0 0 10px 0; color: #1e293b">
            💰 Lignes de tarifs
          </h4>
          <div
            v-for="(ligne, lIndex) in getArray(tarif, 'exp_tarifs_lignes')"
            :key="lIndex"
            style="
              display: grid;
              grid-template-columns: 1fr 1fr auto 1fr 1fr auto;
              gap: 10px;
              background: #f8fafc;
              padding: 10px;
              border: 1px solid #cbd5e0;
              border-radius: 6px;
              margin-bottom: 10px;
              align-items: center;
            "
          >
            <select
              v-model="ligne.type_ligne"
              class="pc-select"
              style="
                padding: 6px;
                border-radius: 4px;
                border: 1px solid #cbd5e0;
              "
            >
              <option value="adulte">Adulte</option>
              <option value="enfant">Enfant</option>
              <option value="bebe">Bébé</option>
              <option value="personnalise">Personnalisé</option>
            </select>
            <input
              type="number"
              v-model="ligne.tarif_valeur"
              class="pc-input"
              placeholder="Tarif €"
              style="
                padding: 6px;
                border-radius: 4px;
                border: 1px solid #cbd5e0;
              "
            />
            <label
              style="
                display: flex;
                align-items: center;
                gap: 5px;
                font-size: 0.9rem;
              "
              ><input type="checkbox" v-model="ligne.tarif_enable_qty" />
              Qté?</label
            >
            <input
              type="text"
              v-model="ligne.tarif_nom_perso"
              class="pc-input"
              placeholder="Précision (ex: 3-12 ans)"
              style="
                padding: 6px;
                border-radius: 4px;
                border: 1px solid #cbd5e0;
              "
            />
            <input
              type="text"
              v-model="ligne.tarif_observation"
              class="pc-input"
              placeholder="Observation"
              style="
                padding: 6px;
                border-radius: 4px;
                border: 1px solid #cbd5e0;
              "
            />
            <button
              @click="removeLigne(tarif, lIndex)"
              class="pc-btn pc-btn-danger"
              style="padding: 4px 8px"
            >
              ✕
            </button>
          </div>
          <button
            @click="addLigne(tarif)"
            class="pc-btn pc-btn-secondary pc-btn-sm"
          >
            ➕ Ajouter une ligne
          </button>
        </div>

        <div style="margin-bottom: 20px">
          <h4 style="margin: 0 0 10px 0; color: #1e293b">
            ⭐ Options tarifaires
          </h4>
          <div
            v-for="(option, oIndex) in getArray(
              tarif,
              'exp_options_tarifaires',
            )"
            :key="oIndex"
            style="
              display: grid;
              grid-template-columns: 2fr 1fr auto auto;
              gap: 10px;
              background: #fffbeb;
              padding: 10px;
              border: 1px solid #fde68a;
              border-radius: 6px;
              margin-bottom: 10px;
              align-items: center;
            "
          >
            <input
              type="text"
              v-model="option.exp_description_option"
              class="pc-input"
              placeholder="Description (ex: Skipper)"
              style="
                padding: 6px;
                border-radius: 4px;
                border: 1px solid #fcd34d;
              "
            />
            <input
              type="number"
              v-model="option.exp_tarif_option"
              class="pc-input"
              placeholder="Tarif €"
              style="
                padding: 6px;
                border-radius: 4px;
                border: 1px solid #fcd34d;
              "
            />
            <label
              style="
                display: flex;
                align-items: center;
                gap: 5px;
                font-size: 0.9rem;
              "
              ><input type="checkbox" v-model="option.option_enable_qty" />
              Qté?</label
            >
            <button
              @click="removeOption(tarif, oIndex)"
              class="pc-btn pc-btn-danger"
              style="padding: 4px 8px"
            >
              ✕
            </button>
          </div>
          <button
            @click="addOption(tarif)"
            class="pc-btn pc-btn-secondary pc-btn-sm"
          >
            ➕ Ajouter une option
          </button>
        </div>

        <div>
          <h4 style="margin: 0 0 10px 0; color: #1e293b">🏷️ Frais fixes</h4>
          <div
            v-for="(frais, fIndex) in getArray(tarif, 'exp-frais-fixes')"
            :key="fIndex"
            style="
              display: grid;
              grid-template-columns: 2fr 1fr auto;
              gap: 10px;
              background: #f0f9ff;
              padding: 10px;
              border: 1px solid #bae6fd;
              border-radius: 6px;
              margin-bottom: 10px;
              align-items: center;
            "
          >
            <input
              type="text"
              v-model="frais.exp_description_frais_fixe"
              class="pc-input"
              placeholder="Description (ex: Nettoyage)"
              style="
                padding: 6px;
                border-radius: 4px;
                border: 1px solid #7dd3fc;
              "
            />
            <input
              type="number"
              v-model="frais.exp_tarif_frais_fixe"
              class="pc-input"
              placeholder="Tarif €"
              style="
                padding: 6px;
                border-radius: 4px;
                border: 1px solid #7dd3fc;
              "
            />
            <button
              @click="removeFrais(tarif, fIndex)"
              class="pc-btn pc-btn-danger"
              style="padding: 4px 8px"
            >
              ✕
            </button>
          </div>
          <button
            @click="addFrais(tarif)"
            class="pc-btn pc-btn-secondary pc-btn-sm"
          >
            ➕ Ajouter un frais fixe
          </button>
        </div>
      </div>
    </div>

    <div
      v-else
      style="
        padding: 30px;
        text-align: center;
        border: 2px dashed #cbd5e0;
        border-radius: 12px;
        margin-bottom: 20px;
      "
    >
      <p style="color: #64748b; font-size: 1.1rem">
        Aucun tarif n'est configuré pour cette expérience.
      </p>
    </div>

    <button
      @click="addTarifType"
      class="pc-btn pc-btn-primary"
      style="
        width: 100%;
        justify-content: center;
        padding: 12px;
        font-size: 1.1rem;
      "
    >
      <span>➕</span> Ajouter un nouveau type de tarif
    </button>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { useExperienceStore } from "../../stores/experience-store";

const store = useExperienceStore();
const { currentExperience: experience } = storeToRefs(store);

// Propriété calculée pour sécuriser le tableau principal
const tarifs = computed(() => {
  if (!experience.value.exp_types_de_tarifs) {
    experience.value.exp_types_de_tarifs = [];
  }
  return experience.value.exp_types_de_tarifs;
});

// Helper pour sécuriser les sous-tableaux dynamiques
const getArray = (tarif, key) => {
  if (!tarif[key] || !Array.isArray(tarif[key])) {
    tarif[key] = [];
  }
  return tarif[key];
};

// --- Actions Root ---
const addTarifType = () => {
  tarifs.value.push({
    exp_type: "unique",
    exp_type_custom: "",
    exp_tarifs_lignes: [],
    exp_options_tarifaires: [],
    "exp-frais-fixes": [], // Attention à la clé avec tirets spécifique à ACF
  });
};

const removeTarifType = (index) => {
  if (
    confirm(
      "Attention: Voulez-vous vraiment supprimer ce bloc de tarification et tout son contenu ?",
    )
  ) {
    tarifs.value.splice(index, 1);
  }
};

// --- Actions Lignes ---
const addLigne = (tarif) => {
  getArray(tarif, "exp_tarifs_lignes").push({
    type_ligne: "personnalise",
    tarif_valeur: 0,
    tarif_enable_qty: false,
    tarif_nom_perso: "",
    tarif_observation: "",
  });
};
const removeLigne = (tarif, index) => tarif.exp_tarifs_lignes.splice(index, 1);

// --- Actions Options ---
const addOption = (tarif) => {
  getArray(tarif, "exp_options_tarifaires").push({
    exp_description_option: "",
    exp_tarif_option: 0,
    option_enable_qty: false,
  });
};
const removeOption = (tarif, index) =>
  tarif.exp_options_tarifaires.splice(index, 1);

// --- Actions Frais ---
const addFrais = (tarif) => {
  getArray(tarif, "exp-frais-fixes").push({
    exp_description_frais_fixe: "",
    exp_tarif_frais_fixe: 0,
  });
};
const removeFrais = (tarif, index) => tarif["exp-frais-fixes"].splice(index, 1);
</script>
