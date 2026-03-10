<template>
  <div class="pc-tab-main">
    <div class="pc-form-grid" style="display: grid; gap: 15px">
      <div
        class="pc-form-group pc-form-group--full"
        style="margin-bottom: 15px"
      >
        <label style="display: block; font-weight: 600; margin-bottom: 5px"
          >Titre H1 personnalisé</label
        >
        <input
          type="text"
          v-model="experience.exp_h1_custom"
          class="pc-input"
          placeholder="Laissez vide pour utiliser le titre principal"
          style="
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
          "
        />
        <small style="color: #64748b; margin-top: 4px; display: block">
          Surcharge le titre principal de l'expérience sur la page.
        </small>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px">
        <div class="pc-form-group">
          <WpMediaUploader
            v-model="experience.exp_hero_desktop"
            label="Image Hero Desktop"
            placeholder="💻 Sélectionner l'image (Format PC)"
          />
        </div>

        <div class="pc-form-group">
          <WpMediaUploader
            v-model="experience.exp_hero_mobile"
            label="Image Hero Mobile"
            placeholder="📱 Sélectionner l'image (Format Mobile)"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { storeToRefs } from "pinia";
import { useExperienceStore } from "../../stores/experience-store";
import WpMediaUploader from "../common/WpMediaUploader.vue";

const store = useExperienceStore();

// On récupère "currentExperience" depuis le store en le renommant localement "experience"
// storeToRefs permet de garder la réactivité (le v-model mettra à jour le store en temps réel)
const { currentExperience: experience } = storeToRefs(store);
</script>
