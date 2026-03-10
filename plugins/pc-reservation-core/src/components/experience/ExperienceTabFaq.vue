<template>
  <div class="pc-tab-faq">
    <div style="margin-bottom: 20px">
      <h3 style="margin-top: 0; color: #1e293b; font-size: 1.2rem">
        Foire Aux Questions
      </h3>
      <p style="color: #64748b; font-size: 0.9rem">
        Ajoutez les questions fréquentes que se posent vos clients pour cette
        expérience.
      </p>
    </div>

    <div v-if="experience.exp_faq && experience.exp_faq.length > 0">
      <div
        v-for="(item, index) in experience.exp_faq"
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
            <label style="display: block; font-weight: 600; margin-bottom: 5px"
              >Question</label
            >
            <input
              type="text"
              v-model="item.exp_question"
              class="pc-input"
              placeholder="Ex: Faut-il savoir nager ?"
              style="
                width: 100%;
                padding: 8px 12px;
                border: 1px solid #cbd5e0;
                border-radius: 6px;
              "
            />
          </div>

          <div class="pc-form-group pc-form-group--full">
            <label style="display: block; font-weight: 600; margin-bottom: 5px"
              >Réponse</label
            >
            <textarea
              v-model="item.exp_reponse"
              class="pc-textarea"
              rows="3"
              placeholder="Ex: Oui, c'est obligatoire pour des raisons de sécurité."
              style="
                width: 100%;
                padding: 8px 12px;
                border: 1px solid #cbd5e0;
                border-radius: 6px;
                resize: vertical;
              "
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
      style="
        padding: 20px;
        text-align: center;
        border: 2px dashed #cbd5e0;
        border-radius: 8px;
        margin-bottom: 15px;
        color: #64748b;
      "
    >
      Aucune question pour le moment.
    </div>

    <button
      @click="addFaq"
      class="pc-btn pc-btn-secondary"
      style="margin-top: 10px"
    >
      <span>➕</span> Ajouter une question
    </button>
  </div>
</template>

<script setup>
import { storeToRefs } from "pinia";
import { useExperienceStore } from "../../stores/experience-store";

const store = useExperienceStore();
const { currentExperience: experience } = storeToRefs(store);

// Méthode pour ajouter une ligne vide
const addFaq = () => {
  // Sécurité : on s'assure que le tableau existe
  if (!experience.value.exp_faq) {
    experience.value.exp_faq = [];
  }

  experience.value.exp_faq.push({
    exp_question: "",
    exp_reponse: "",
  });
};

// Méthode pour supprimer une ligne basée sur son index
const removeFaq = (index) => {
  if (confirm("Voulez-vous vraiment supprimer cette question ?")) {
    experience.value.exp_faq.splice(index, 1);
  }
};
</script>
