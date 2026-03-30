<template>
  <div class="pc-post-seo-wrap">
    <input type="hidden" name="pc_post_seo_payload" :value="payloadString" />

    <div class="pc-field-group checkbox-group">
      <label>
        <input type="checkbox" v-model="formData.post_exclude_sitemap" />
        Exclure du sitemap
      </label>
      <p class="description">Cochez pour retirer cette page du sitemap. Un meta robots noindex,follow sera appliqué automatiquement si aucun autre robots n'est défini.</p>
    </div>

    <div class="pc-field-group checkbox-group">
      <label>
        <input type="checkbox" v-model="formData.post_http_410" />
        Servir un 410 Gone
      </label>
      <p class="description">Optionnel. Cochez si le contenu est définitivement supprimé (sans remplacement). Accélère la désindexation.</p>
    </div>

    <div class="pc-field-group">
      <label>Méta titre</label>
      <input type="text" v-model="formData.post_og_title" placeholder="Title personnalisé (50–60 caractères)..." />
      <p class="description">Title personnalisé (50–60 caractères). Laissez vide pour utiliser le modèle automatique.</p>
    </div>

    <div class="pc-field-group">
      <label>Meta description</label>
      <textarea v-model="formData.post_og_description" rows="3" placeholder="Résumé engageant..."></textarea>
      <p class="description">Résumé engageant (140–160 caractères) décrivant précisément le contenu de la page.</p>
    </div>

    <div class="pc-field-group">
      <label>URL canonique (facultatif)</label>
      <input type="url" v-model="formData.post_meta_canonical" />
      <p class="description">Laissez vide pour utiliser l’URL de cette page. À renseigner uniquement en cas de duplication maîtrisée.</p>
    </div>

    <div class="pc-field-group">
      <label>Meta robots</label>
      <select v-model="formData.post_meta_robots">
        <option value="index,follow">index,follow</option>
        <option value="noindex,follow">noindex,follow</option>
        <option value="noindex,nofollow">noindex,nofollow</option>
      </select>
      <p class="description">Contrôle l’indexation de la page.</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';

const formData = ref({
  post_exclude_sitemap: false,
  post_http_410: false,
  post_og_title: '',
  post_og_description: '',
  post_meta_canonical: '',
  post_meta_robots: 'index,follow'
});

onMounted(() => {
  if (window.PC_POST_SEO_INITIAL_STATE) {
    const initData = window.PC_POST_SEO_INITIAL_STATE;

    // Rétrocompatibilité depuis la base de données WP/ACF ("0" ou "1" vers Boolean)
    formData.value.post_exclude_sitemap = initData.post_exclude_sitemap == '1' || initData.post_exclude_sitemap === true;
    formData.value.post_http_410 = initData.post_http_410 == '1' || initData.post_http_410 === true;

    // Autres champs avec fallbacks
    formData.value.post_og_title = initData.post_og_title || '';
    formData.value.post_og_description = initData.post_og_description || '';
    formData.value.post_meta_canonical = initData.post_meta_canonical || '';
    formData.value.post_meta_robots = initData.post_meta_robots || 'index,follow';
  }
});

// Génération du payload pour PHP
const payloadString = computed(() => JSON.stringify(formData.value));
</script>

<style scoped>
.pc-post-seo-wrap {
  display: flex;
  flex-direction: column;
  gap: 20px;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
  padding: 10px 0;
}
.pc-field-group {
  display: flex;
  flex-direction: column;
}
.pc-field-group label {
  font-weight: 600;
  margin-bottom: 5px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.checkbox-group label {
  font-weight: normal;
  font-size: 14px;
}
.pc-field-group input[type="text"],
.pc-field-group input[type="url"],
.pc-field-group textarea,
.pc-field-group select {
  width: 100%;
  max-width: 100%;
  padding: 6px 8px;
  border: 1px solid #8c8f94;
  border-radius: 4px;
  box-shadow: 0 0 0 transparent;
  transition: box-shadow .1s linear;
}
.pc-field-group input[type="text"]:focus,
.pc-field-group input[type="url"]:focus,
.pc-field-group textarea:focus,
.pc-field-group select:focus {
  border-color: #2271b1;
  box-shadow: 0 0 0 1px #2271b1;
  outline: 2px solid transparent;
}
.pc-field-group .description {
  margin: 4px 0 0;
  color: #646970;
  font-size: 13px;
  font-style: italic;
}
</style>