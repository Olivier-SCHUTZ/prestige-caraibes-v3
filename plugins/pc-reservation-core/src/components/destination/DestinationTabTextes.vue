<template>
  <div class="pc-tab-textes">
    <div class="pc-form-grid" style="display: grid; gap: 20px">

      <div class="pc-form-group" style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
         <label style="display: flex; align-items: center; gap: 10px; font-weight: 600; cursor: pointer; color: #1e293b;">
          <input type="checkbox" v-model="formData.dest_featured" style="width: 18px; height: 18px" />
          Mettre en avant cette destination
        </label>
        <p style="margin: 5px 0 0 28px; font-size: 0.85rem; color: #64748b;">
          Sera affichée en priorité dans le Hub et le menu principal.
        </p>

        <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 15px 0" />

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px">
            <div class="pc-form-group">
              <label style="display: block; font-weight: 600; margin-bottom: 5px">Slogan (Vignette)</label>
              <input type="text" v-model="formData.dest_slogan" class="pc-input" placeholder="Ex: La perle des Caraïbes..." style="width: 100%; box-sizing: border-box; height: 40px; padding: 0 12px; border: 1px solid #cbd5e0; border-radius: 6px;" />
            </div>
            <div class="pc-form-group">
              <label style="display: block; font-weight: 600; margin-bottom: 5px">Ordre d'affichage</label>
              <input type="number" v-model="formData.dest_order" class="pc-input" placeholder="0" style="width: 100%; box-sizing: border-box; height: 40px; padding: 0 12px; border: 1px solid #cbd5e0; border-radius: 6px;" />
            </div>
        </div>
      </div>

      <div class="pc-form-group">
        <label style="display: block; font-weight: 600; margin-bottom: 5px">Titre principal (H1 SEO)</label>
        <input type="text" v-model="formData.dest_h1" class="pc-input" placeholder="Titre optimisé pour le référencement" style="width: 100%; box-sizing: border-box; height: 40px; padding: 0 12px; border: 1px solid #cbd5e0; border-radius: 6px;" />
      </div>

      <div class="pc-form-group">
        <label style="display: block; font-weight: 600; margin-bottom: 5px">Introduction (Description complète)</label>
        <textarea v-model="formData.dest_intro" rows="6" placeholder="Saisissez la description de la destination..." style="width: 100%; box-sizing: border-box; resize: vertical; padding: 12px; border: 1px solid #cbd5e0; border-radius: 6px; font-family: inherit;"></textarea>
      </div>

      <div class="pc-form-group" style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
         <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 1.1rem; color: #1e293b;">🔗 Relations & Recommandations</h3>
         <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px">
            <div class="pc-form-group">
              <label style="display: block; font-weight: 600; margin-bottom: 5px">Expériences liées (IDs)</label>
              <input type="text" v-model="experiencesLiees" class="pc-input" placeholder="Ex: 142, 156" style="width: 100%; box-sizing: border-box; height: 40px; padding: 0 12px; border: 1px solid #cbd5e0; border-radius: 6px;" />
              <small style="color: #64748b; font-size: 0.8rem">IDs des expériences séparés par des virgules (Max 3).</small>
            </div>

            <div class="pc-form-group">
              <label style="display: block; font-weight: 600; margin-bottom: 5px">Logements liés (IDs)</label>
              <input type="text" v-model="logementsLies" class="pc-input" placeholder="Ex: 89, 102" style="width: 100%; box-sizing: border-box; height: 40px; padding: 0 12px; border: 1px solid #cbd5e0; border-radius: 6px;" />
              <small style="color: #64748b; font-size: 0.8rem">IDs des logements séparés par des virgules (Max 3).</small>
            </div>
         </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { computed } from "vue";

// Récupération du formData global passé par la modale parente
const props = defineProps({
  formData: {
    type: Object,
    required: true
  }
});

// Helper intelligent pour convertir le tableau d'IDs Expériences en texte (et inversement)
const experiencesLiees = computed({
  get: () => {
    if (Array.isArray(props.formData.dest_exp_featured)) {
      return props.formData.dest_exp_featured.join(", ");
    }
    return props.formData.dest_exp_featured || "";
  },
  set: (val) => {
    if (!val) {
      props.formData.dest_exp_featured = [];
    } else {
      props.formData.dest_exp_featured = val
        .split(",")
        .map((s) => s.trim())
        .filter((s) => s !== "");
    }
  },
});

// Helper intelligent pour convertir le tableau d'IDs Logements en texte (et inversement)
const logementsLies = computed({
  get: () => {
    if (Array.isArray(props.formData.dest_logements_recommandes)) {
      return props.formData.dest_logements_recommandes.join(", ");
    }
    return props.formData.dest_logements_recommandes || "";
  },
  set: (val) => {
    if (!val) {
      props.formData.dest_logements_recommandes = [];
    } else {
      props.formData.dest_logements_recommandes = val
        .split(",")
        .map((s) => s.trim())
        .filter((s) => s !== "");
    }
  },
});
</script>