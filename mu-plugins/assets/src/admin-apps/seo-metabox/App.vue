<template>
  <div class="pc-vue-metabox-wrapper">
    
    <div class="pc-tabs-nav">
      <button 
        v-for="tab in tabs" 
        :key="tab.id"
        type="button"
        :class="['pc-tab-btn', { 'is-active': currentTab === tab.id }]"
        @click="currentTab = tab.id"
      >
        {{ tab.label }}
      </button>
    </div>

    <div class="pc-tabs-content">
        <div v-show="currentTab === 'indexation'">
            <div class="pc-field-group">
                <label>
                    <input type="checkbox" v-model="formData.pc_exclude_sitemap">
                    Exclure du sitemap (Applique un meta robots noindex)
                </label>
            </div>
            </div>

        <div v-show="currentTab === 'common'">
            <div class="pc-field-group">
                <label>Titre SEO (facultatif)</label>
                <input type="text" v-model="formData.pc_meta_title" class="large-text" placeholder="Titre SEO...">
            </div>
            </div>

        <div v-show="currentTab === 'type'">
            <div class="pc-field-group">
                <label>Type de page (schéma JSON-LD)</label>
                <select v-model="formData.pc_schema_kind">
                    <option value="generic">Générique</option>
                    <option value="search">Recherche</option>
                    <option value="category">Catégorie</option>
                    <option value="about">À propos</option>
                    </select>
            </div>

            <div v-if="formData.pc_schema_kind === 'search'" class="pc-field-group pc-conditional">
                <label>Type de Recherche :</label>
                <label><input type="radio" value="logement" v-model="formData.pc_search_type"> Logement</label>
                <label><input type="radio" value="experience" v-model="formData.pc_search_type"> Expérience</label>
            </div>
        </div>
    </div>

    <input type="hidden" name="pc_seo_payload" :value="JSON.stringify(formData)" />

  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'

// Définition des onglets
const tabs = [
  { id: 'indexation', label: 'Indexation' },
  { id: 'common', label: 'Commun (Meta / QCM / FAQ)' },
  { id: 'type', label: 'Type de page (schéma)' },
  { id: 'category', label: 'Catégorie (Logements)' },
  { id: 'search', label: 'Recherche' }
]

const currentTab = ref('indexation')

// Objet réactif qui contiendra toutes les données du formulaire
const formData = reactive({
    pc_exclude_sitemap: false,
    pc_http_410: false,
    pc_meta_title: '',
    pc_meta_description: '',
    pc_schema_kind: 'generic',
    pc_search_type: '',
    pc_faq_items: [],
    // ... définir les autres valeurs par défaut
})

// Au montage, on hydrate notre objet réactif avec les données envoyées par PHP
onMounted(() => {
    if (window.PC_SEO_INITIAL_STATE) {
        Object.assign(formData, window.PC_SEO_INITIAL_STATE)
    }
})
</script>

<style scoped>
/* Tu pourras mettre ton CSS ici pour styliser la metabox (ou utiliser Tailwind si tu l'installes) */
.pc-tabs-nav { border-bottom: 1px solid #ccd0d4; margin-bottom: 15px; }
.pc-tab-btn { background: none; border: none; padding: 10px 15px; cursor: pointer; }
.pc-tab-btn.is-active { border-bottom: 2px solid #2271b1; font-weight: bold; color: #2271b1; }
.pc-field-group { margin-bottom: 15px; }
.pc-field-group label { display: block; font-weight: 600; margin-bottom: 5px; }
.pc-conditional { padding: 10px; background: #f0f0f1; border-left: 4px solid #2271b1; }
</style>