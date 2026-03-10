<template>
  <div class="pc-tab-seo">
    <div class="pc-form-grid" style="display: grid; gap: 20px">
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
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            cursor: pointer;
            color: #1e293b;
          "
        >
          <input
            type="checkbox"
            v-model="experience.exp_availability"
            style="width: 18px; height: 18px"
          />
          Expérience disponible à la réservation
        </label>
        <p style="margin: 5px 0 0 28px; font-size: 0.85rem; color: #64748b">
          Si décoché, l'expérience apparaîtra comme "Indisponible" côté client.
        </p>
      </div>

      <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 10px 0" />

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px">
        <div class="pc-form-group">
          <label style="display: block; font-weight: 600; margin-bottom: 5px"
            >Méta Titre</label
          >
          <input
            type="text"
            v-model="experience.exp_meta_titre"
            class="pc-input"
            placeholder="Titre pour Google (max 60 caractères)"
            style="
              width: 100%;
              padding: 8px 12px;
              border: 1px solid #cbd5e0;
              border-radius: 6px;
            "
          />
        </div>

        <div class="pc-form-group">
          <label style="display: block; font-weight: 600; margin-bottom: 5px"
            >Méta Robots</label
          >
          <select
            v-model="experience.exp_meta_robots"
            class="pc-select"
            style="
              width: 100%;
              padding: 8px 12px;
              border: 1px solid #cbd5e0;
              border-radius: 6px;
              background: white;
            "
          >
            <option value="index,follow">Index, Follow (Par défaut)</option>
            <option value="noindex,follow">NoIndex, Follow</option>
            <option value="noindex,nofollow">NoIndex, NoFollow</option>
          </select>
        </div>
      </div>

      <div class="pc-form-group">
        <label style="display: block; font-weight: 600; margin-bottom: 5px"
          >Méta Description</label
        >
        <textarea
          v-model="experience.exp_meta_description"
          class="pc-textarea"
          rows="2"
          placeholder="Description pour Google (max 160 caractères)"
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
          >URL Canonique</label
        >
        <input
          type="url"
          v-model="experience.exp_meta_canonical"
          class="pc-input"
          placeholder="https://..."
          style="
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
          "
        />
      </div>

      <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 10px 0" />

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px">
        <div
          class="pc-form-group"
          style="display: flex; flex-direction: column; gap: 10px"
        >
          <label
            style="
              display: flex;
              align-items: center;
              gap: 8px;
              cursor: pointer;
            "
          >
            <input type="checkbox" v-model="experience.exp_exclude_sitemap" />
            Exclure du Sitemap XML
          </label>
          <label
            style="
              display: flex;
              align-items: center;
              gap: 8px;
              cursor: pointer;
              color: #ef4444;
            "
          >
            <input type="checkbox" v-model="experience.exp_http_410" />
            Forcer en erreur 410 (Définitivement supprimé)
          </label>
        </div>

        <div class="pc-form-group">
          <label style="display: block; font-weight: 600; margin-bottom: 5px"
            >Liaisons : Logements recommandés</label
          >
          <input
            type="text"
            v-model="liaisons"
            class="pc-input"
            placeholder="IDs séparés par des virgules (ex: 142, 156)"
            style="
              width: 100%;
              padding: 8px 12px;
              border: 1px solid #cbd5e0;
              border-radius: 6px;
            "
          />
          <small style="color: #64748b; font-size: 0.8rem"
            >Saisissez les IDs des logements à lier à cette expérience.</small
          >
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

// Computed property pour gérer le tableau d'IDs des logements comme une string séparée par des virgules
// car l'ancien code jQuery utilisait split(',') et join(',')
const liaisons = computed({
  get: () => {
    if (Array.isArray(experience.value.exp_logements_recommandes)) {
      return experience.value.exp_logements_recommandes.join(", ");
    }
    return experience.value.exp_logements_recommandes || "";
  },
  set: (val) => {
    if (!val) {
      experience.value.exp_logements_recommandes = [];
    } else {
      experience.value.exp_logements_recommandes = val
        .split(",")
        .map((s) => s.trim())
        .filter((s) => s !== "");
    }
  },
});
</script>
