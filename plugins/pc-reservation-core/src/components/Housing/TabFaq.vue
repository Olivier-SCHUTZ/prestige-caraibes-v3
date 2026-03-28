<template>
  <div class="pc-tab-faq">
    <div style="margin-bottom: 20px">
      <h3 style="margin-top: 0; color: #1e293b; font-size: 1.2rem">
        Foire Aux Questions (Logement)
      </h3>
      <p style="color: #64748b; font-size: 0.9rem">
        Ajoutez les questions fréquentes que se posent vos voyageurs pour ce logement.
      </p>
    </div>

    <div v-if="housing.logement_faq && housing.logement_faq.length > 0">
      <div
        v-for="(item, index) in housing.logement_faq"
        :key="index"
        class="faq-row"
        style="
          background: #f8fafc;
          border: 1px solid #e2e8f0;
          border-radius: 8px;
          padding: 15px;
          margin-bottom: 15px;
          position: relative;
        "
      >
        <div class="pc-form-grid" style="display: grid; gap: 15px">
          <div class="pc-form-group pc-form-group--full">
            <label style="display: block; font-weight: 600; margin-bottom: 5px">Question</label>
            <input
              type="text"
              v-model="item.question"
              class="pc-input"
              placeholder="Ex: Y a-t-il une place de parking ?"
              style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e0; border-radius: 6px;"
            />
          </div>

          <div class="pc-form-group pc-form-group--full">
            <label style="display: block; font-weight: 600; margin-bottom: 5px">Réponse</label>
            <textarea
              v-model="item.reponse"
              class="pc-textarea"
              rows="3"
              placeholder="Ex: Oui, une place privée et sécurisée est à votre disposition."
              style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e0; border-radius: 6px; resize: vertical;"
            ></textarea>
          </div>
        </div>

        <button
          @click="removeFaq(index)"
          class="pc-btn pc-btn-danger pc-btn-sm"
          style="position: absolute; top: 15px; right: 15px; padding: 4px 8px"
          title="Supprimer cette question"
        >
          ✕
        </button>
      </div>
    </div>

    <div
      v-else
      style="padding: 20px; text-align: center; border: 2px dashed #cbd5e0; border-radius: 8px; margin-bottom: 15px; color: #64748b;"
    >
      Aucune question pour le moment.
    </div>

    <button @click="addFaq" class="pc-btn pc-btn-secondary" style="margin-top: 10px">
      <span>➕</span> Ajouter une question
    </button>
  </div>
</template>

<script setup>
import { storeToRefs } from "pinia";
import { useHousingModalStore } from "../../stores/housing-modal-store";
import { onMounted, watch } from "vue";

const store = useHousingModalStore();
const { formData: housing } = storeToRefs(store);

// Sécurité : Nettoie les données corrompues au chargement
const sanitizeFaqData = () => {
  const currentVal = housing.value.logement_faq;

  if (!currentVal) {
    housing.value.logement_faq = [];
    return;
  }

  // 1. Si la BDD a renvoyé une chaîne JSON
  if (typeof currentVal === 'string') {
    try {
      housing.value.logement_faq = JSON.parse(currentVal);
    } catch (e) {
      housing.value.logement_faq = [];
    }
    return; // On arrête là, la conversion en tableau relancera le watch une seule fois proprement
  }

  // 2. Si c'est bien un tableau, on le nettoie UNIQUEMENT si nécessaire (Évite la boucle infinie)
  if (Array.isArray(currentVal)) {
    const hasBadData = currentVal.some(item => typeof item !== 'object' || item === null || Array.isArray(item));
    
    if (hasBadData) {
      housing.value.logement_faq = currentVal.filter(
        item => typeof item === 'object' && item !== null && !Array.isArray(item)
      );
    }
  } else {
    housing.value.logement_faq = [];
  }
};

// 🛑 On a retiré "deep: true" pour ne pas surveiller chaque frappe au clavier dans les inputs !
watch(() => housing.value.logement_faq, () => {
  sanitizeFaqData();
}, { immediate: true });

const addFaq = () => {
  if (!Array.isArray(housing.value.logement_faq)) {
    housing.value.logement_faq = [];
  }
  housing.value.logement_faq.push({
    question: "",
    reponse: "",
  });
};

const removeFaq = (index) => {
  if (confirm("Voulez-vous vraiment supprimer cette question ?")) {
    housing.value.logement_faq.splice(index, 1);
  }
};
</script>