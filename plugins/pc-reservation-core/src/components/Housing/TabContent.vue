<template>
  <div class="v2-tab-content">
    <h4 class="pc-section-title">Contenu enrichi & SEO</h4>
    <div class="pc-form-grid">
      <div class="pc-form-group pc-form-group--full">
        <label>Titre H1 Personnalisé (SEO)</label>
        <input
          type="text"
          v-model="modalStore.formData.contenu_seo_titre_h1"
          class="pc-input"
          placeholder="Ex: Magnifique Villa avec vue mer à Sainte-Anne"
        />
      </div>

      <div class="pc-form-group pc-form-group--full">
        <label>Description longue (HTML autorisé)</label>
        <textarea
          v-model="modalStore.formData.seo_long_html"
          class="pc-textarea"
          rows="6"
          placeholder="<p>Description détaillée du logement pour optimiser le référencement...</p>"
        ></textarea>
      </div>
    </div>

    <h4 class="pc-section-title">Points Forts (Highlights)</h4>
    <div class="pc-form-grid">
      <div class="pc-form-group pc-form-group--full">
        <div class="pc-checkbox-group">
          <label
            class="pc-checkbox-label"
            v-for="highlight in highlightOptions"
            :key="highlight.value"
          >
            <input
              type="checkbox"
              :value="highlight.value"
              v-model="modalStore.formData.highlights"
            />
            <span>{{ highlight.label }}</span>
          </label>
        </div>
      </div>

      <div class="pc-form-group pc-form-group--full">
        <label>Points forts personnalisés (Texte libre)</label>
        <input
          type="text"
          v-model="modalStore.formData.highlights_custom"
          class="pc-input"
          placeholder="Ex: Chef à domicile sur demande, Conciergerie 24/7"
        />
      </div>
    </div>

    <h4 class="pc-section-title">Ventes Croisées (Cross-selling)</h4>
    <div class="pc-form-grid">
      <div class="pc-form-group pc-form-group--full">
        <label>Expériences recommandées (IDs séparés par des virgules)</label>
        <input
          type="text"
          v-model="experiencesText"
          class="pc-input"
          placeholder="Ex: 105, 204, 306"
        />
        <small class="pc-help-text"
          >Saisissez les IDs des expériences que vous souhaitez mettre en avant
          sur la page de ce logement.</small
        >
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { useHousingModalStore } from "../../stores/housing-modal-store.js";

const modalStore = useHousingModalStore();

// Sécurisation des tableaux pour les checkboxes multiples
if (
  !modalStore.formData.highlights ||
  !Array.isArray(modalStore.formData.highlights)
) {
  modalStore.formData.highlights = [];
}
if (
  !modalStore.formData.logement_experiences_recommandees ||
  !Array.isArray(modalStore.formData.logement_experiences_recommandees)
) {
  modalStore.formData.logement_experiences_recommandees = [];
}

// Propriété calculée (Computed) pour transformer le tableau d'IDs en texte (et inversement)
const experiencesText = computed({
  get: () => modalStore.formData.logement_experiences_recommandees.join(", "),
  set: (val) => {
    modalStore.formData.logement_experiences_recommandees = val
      .split(",")
      .map((id) => id.trim())
      .filter((id) => id);
  },
});

// Options par défaut (À modifier avec tes vraies options ACF si différentes)
const highlightOptions = [
  { value: "vue_mer", label: "🌊 Vue mer" },
  { value: "piscine_privee", label: "🏊 Piscine privée" },
  { value: "acces_plage", label: "🏖️ Accès direct plage" },
  { value: "jardin_tropical", label: "🌴 Jardin tropical" },
  { value: "calme_absolu", label: "🤫 Calme absolu" },
  { value: "proche_commodites", label: "🛍️ Proche commodités" },
  { value: "wifi_haut_debit", label: "🚀 Wi-Fi Haut débit" },
  { value: "parking_prive", label: "🚗 Parking privé" },
];
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
  gap: 1.5rem;
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

.pc-checkbox-group {
  display: grid;
  grid-template-columns: repeat(3, 1fr); /* 3 colonnes pour les points forts */
  gap: 1rem;
  background: #f8fafc;
  padding: 1.5rem;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.pc-checkbox-label {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  cursor: pointer;
  font-weight: normal !important;
  color: #334155;
  font-size: 0.95rem;
}

.pc-checkbox-label input[type="checkbox"] {
  margin-top: 3px;
}

.pc-help-text {
  font-size: 0.8rem;
  color: #64748b;
  margin-top: 0.5rem;
}
</style>
