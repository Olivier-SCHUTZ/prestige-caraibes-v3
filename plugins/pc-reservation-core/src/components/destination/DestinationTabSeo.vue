<template>
  <div class="pc-tab-seo">
    <div class="pc-form-grid" style="display: grid; gap: 20px">

      <div class="pc-form-group" style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
        <label style="display: flex; align-items: center; gap: 10px; font-weight: 600; cursor: pointer; color: #1e293b;">
          <input type="checkbox" v-model="formData.dest_exclude_sitemap" style="width: 18px; height: 18px" />
          Exclure du Sitemap XML
        </label>
        
        <label style="display: flex; align-items: center; gap: 10px; font-weight: 600; cursor: pointer; color: #ef4444; margin-top: 10px;">
          <input type="checkbox" v-model="formData.dest_http_410" style="width: 18px; height: 18px" />
          Forcer en erreur 410 (Définitivement supprimé)
        </label>
        <p style="margin: 5px 0 0 28px; font-size: 0.85rem; color: #64748b;">
          Attention : Cochez "erreur 410" uniquement si la destination n'existe plus du tout et que vous souhaitez le signaler à Google.
        </p>
      </div>

      <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 10px 0" />

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px">
        <div class="pc-form-group">
          <label style="display: block; font-weight: 600; margin-bottom: 5px">Méta Titre</label>
          <input
            type="text"
            v-model="formData.dest_meta_title"
            class="pc-input"
            placeholder="Titre pour Google (max 60 caractères)"
            style="width: 100%; box-sizing: border-box; height: 40px; padding: 0 12px; border: 1px solid #cbd5e0; border-radius: 6px;"
          />
        </div>

        <div class="pc-form-group">
          <label style="display: block; font-weight: 600; margin-bottom: 5px">Méta Robots</label>
          <select
            v-model="metaRobots"
            class="pc-select"
            style="width: 100%; box-sizing: border-box; height: 40px; padding: 0 12px; border: 1px solid #cbd5e0; border-radius: 6px; background: white;"
          >
            <option value="index,follow">Index, Follow (Par défaut)</option>
            <option value="noindex,follow">NoIndex, Follow</option>
            <option value="noindex,nofollow">NoIndex, NoFollow</option>
          </select>
        </div>
      </div>

      <div class="pc-form-group">
        <label style="display: block; font-weight: 600; margin-bottom: 5px">Méta Description</label>
        <textarea
          v-model="formData.dest_meta_description"
          rows="3"
          placeholder="Description pour Google (max 160 caractères)"
          style="width: 100%; box-sizing: border-box; resize: vertical; padding: 12px; border: 1px solid #cbd5e0; border-radius: 6px; font-family: inherit;"
        ></textarea>
      </div>

      <div class="pc-form-group">
        <label style="display: block; font-weight: 600; margin-bottom: 5px">URL Canonique</label>
        <input
          type="url"
          v-model="formData.dest_meta_canonical"
          class="pc-input"
          placeholder="https://votre-site.com/..."
          style="width: 100%; box-sizing: border-box; height: 40px; padding: 0 12px; border: 1px solid #cbd5e0; border-radius: 6px;"
        />
      </div>

    </div>
  </div>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
  formData: {
    type: Object,
    required: true
  }
});

// Computed property qui force "index,follow" si la valeur en base est vide ou null
const metaRobots = computed({
    get: () => {
        return props.formData.dest_meta_robots || 'index,follow';
    },
    set: (val) => {
        props.formData.dest_meta_robots = val;
    }
});
</script>