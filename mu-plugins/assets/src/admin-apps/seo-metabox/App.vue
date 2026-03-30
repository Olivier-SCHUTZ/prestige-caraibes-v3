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

      <button v-show="isSearchPage" type="button" :class="['pc-tab-btn', { 'is-active': currentTab === 'search' }]" @click="currentTab = 'search'">
        Recherche / Résultats
      </button>

      <button v-show="isCategoryPage" type="button" :class="['pc-tab-btn', { 'is-active': currentTab === 'category' }]" @click="currentTab = 'category'">
        Catégorie (Logements)
      </button>

      <button v-show="isStaticPage" type="button" :class="['pc-tab-btn', { 'is-active': currentTab === 'static' }]" @click="currentTab = 'static'">
        Pages statiques
      </button>
    </div>

    <div class="pc-tabs-content">
        
        <div v-show="currentTab === 'indexation'">
            <div class="pc-field-group">
                <label>
                    <input type="checkbox" v-model="formData.pc_exclude_sitemap">
                    Exclure du sitemap
                </label>
                <p class="pc-help-text">Cochez pour retirer cette page du sitemap. Un meta robots noindex,follow sera appliqué automatiquement si aucun autre robots n'est défini.</p>
            </div>
            <div class="pc-field-group">
                <label>
                    <input type="checkbox" v-model="formData.pc_http_410">
                    Servir un 410 Gone
                </label>
                <p class="pc-help-text">Optionnel. Cochez si le contenu est définitivement supprimé (sans remplacement). Accélère la désindexation.</p>
            </div>
        </div>

        <div v-show="currentTab === 'common'">
            <div class="pc-field-group">
                <label>Titre SEO (facultatif)</label>
                <input type="text" v-model="formData.pc_meta_title" class="large-text" placeholder="Titre SEO...">
                <p class="pc-help-text">Title personnalisé (50–60 caractères). Laissez vide pour utiliser le modèle automatique.</p>
            </div>
            
            <div class="pc-field-group">
                <label>Meta description (facultatif)</label>
                <textarea v-model="formData.pc_meta_description" class="large-text" rows="3"></textarea>
                <p class="pc-help-text">Résumé engageant (140–160 caractères) décrivant précisément le contenu de la page.</p>
            </div>

            <div class="pc-field-group">
                <label>URL canonique (facultatif)</label>
                <input type="url" v-model="formData.pc_meta_canonical" class="large-text" placeholder="https://...">
                <p class="pc-help-text">Laissez vide pour utiliser l’URL de cette page. À renseigner uniquement en cas de duplication maîtrisée.</p>
            </div>

            <div class="pc-field-group">
                <label>Meta robots</label>
                <select v-model="formData.pc_meta_robots">
                    <option value="index,follow">index,follow</option>
                    <option value="noindex,follow">noindex,follow</option>
                    <option value="noindex,nofollow">noindex,nofollow</option>
                </select>
                <p class="pc-help-text">Contrôle l’indexation de la page.</p>
            </div>

            <div class="pc-field-group">
                <label>
                    <input type="checkbox" v-model="formData.pc_qcm_enabled">
                    Activer un QCM/bloc filtre
                </label>
                <p class="pc-help-text">Active un bloc QCM/filtre sur la page (si votre template le supporte).</p>
            </div>

            <div v-if="formData.pc_qcm_enabled" class="pc-field-group pc-conditional">
                <label>Shortcode QCM (optionnel)</label>
                <input type="text" v-model="formData.pc_qcm_shortcode" class="large-text" placeholder="Ex: [elementor-template id='123']">
                <p class="pc-help-text">Collez ici le shortcode.</p>
            </div>

            <div class="pc-field-group" style="margin-top: 30px;">
              <FaqRepeater v-model="formData.pc_faq_items" />
              <p class="pc-help-text">Q/R visibles ; génère un FAQPage si non vide. Migration douce : fallback anciens champs si vide.</p>
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
                    <option value="service">Service</option>
                    <option value="contact">Contact</option>
                    <option value="terms">Conditions</option>
                    <option value="accueil">Accueil</option>
                </select>
                <p class="pc-help-text">Choisissez la nature de cette page pour générer le bon balisage JSON‑LD.</p>
            </div>

            <div v-if="formData.pc_schema_kind === 'search'" class="pc-field-group pc-conditional">
                <label>Type de Recherche :</label>
                <label><input type="radio" value="logement" v-model="formData.pc_search_type"> Logement</label>
                <label><input type="radio" value="experience" v-model="formData.pc_search_type"> Expérience</label>
            </div>
        </div>

        <div v-show="currentTab === 'search'">
            <div class="pc-field-group">
                <label>
                    <input type="checkbox" v-model="formData.pc_search_emit_itemlist">
                    Schéma des résultats (ItemList)
                </label>
                <p class="pc-help-text">Si activé, génère un ItemList avec les résultats réellement affichés (après filtres/QCM).</p>
            </div>
        </div>

        <div v-show="currentTab === 'category'">
            <div class="pc-field-group">
                <label>Introduction SEO (300–500 mots)</label>
                <textarea v-model="formData.pc_cat_intro" class="large-text" rows="5"></textarea>
                <p class="pc-help-text">Texte unique présentant la catégorie (bénéfices, types de biens, conseils).</p>
            </div>

            <div class="pc-field-group">
                <label>Mode de liste</label>
                <select v-model="formData.pc_cat_mode">
                    <option value="auto">Automatique (basé sur la requête)</option>
                    <option value="manual">Sélection manuelle</option>
                </select>
                <p class="pc-help-text">auto : liste alimentée automatiquement ; manual : sélection ci-dessous.</p>
            </div>

            <div v-if="formData.pc_cat_mode === 'manual'" class="pc-field-group pc-conditional">
                <label>Sélection manuelle de logements</label>
                <PostSelector v-model="formData.pc_cat_manual_items" />
                <p class="pc-help-text">Si ‘manual’, choisissez les logements à lister (ordre = affichage).</p>
            </div>
        </div>

        <div v-show="currentTab === 'static'">
            <div style="display: flex; gap: 30px; flex-wrap: wrap;">
              <div class="pc-field-group" style="flex: 1; min-width: 300px;">
                <label>Photo principale (Desktop)</label>
                <ImageUploader v-model="formData.serv_desktop_url" />
              </div>

              <div class="pc-field-group" style="flex: 1; min-width: 300px;">
                <label>Photo principale (Mobile)</label>
                <ImageUploader v-model="formData.serv_mobile_url" />
              </div>
            </div>
        </div>

    </div>

    <input type="hidden" name="pc_seo_payload" :value="JSON.stringify(formData)" />

  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import FaqRepeater from './components/FaqRepeater.vue'
import ImageUploader from './components/ImageUploader.vue'
import PostSelector from './components/PostSelector.vue'

// Définition des onglets fixes
const tabs = [
  { id: 'indexation', label: 'Indexation' },
  { id: 'common', label: 'Commun (Meta / QCM / FAQ)' },
  { id: 'type', label: 'Type de page (schéma)' }
]

const currentTab = ref('indexation')

// Objet réactif complet avec TOUS les champs par défaut
const formData = reactive({
    pc_exclude_sitemap: false,
    pc_http_410: false,
    pc_meta_title: '',
    pc_meta_description: '',
    pc_meta_canonical: '',
    pc_meta_robots: 'index,follow', // Valeur par défaut
    pc_qcm_enabled: false,
    pc_qcm_shortcode: '',
    pc_schema_kind: 'generic',
    pc_search_type: '',
    pc_search_emit_itemlist: true, // Valeur par défaut
    pc_faq_items: [],
    serv_desktop_url: '',
    serv_mobile_url: '',
    pc_cat_intro: '',
    pc_cat_mode: 'auto',
    pc_cat_manual_items: [] 
})

// Logiques conditionnelles pour l'affichage des onglets
const isStaticPage = computed(() => {
  const staticTypes = ['about', 'service', 'contact', 'terms', 'generic', 'accueil']
  return staticTypes.includes(formData.pc_schema_kind)
})
const isCategoryPage = computed(() => formData.pc_schema_kind === 'category')
const isSearchPage = computed(() => formData.pc_schema_kind === 'search')

// Initialisation au chargement de la page
onMounted(() => {
    if (window.PC_SEO_INITIAL_STATE) {
        // On fusionne les données sauvées avec nos valeurs par défaut
        Object.assign(formData, window.PC_SEO_INITIAL_STATE)
        
        // 🚀 CORRECTION : Fonction robuste pour convertir les anciennes valeurs ACF ("0", "1", "", etc.) en vrai booléen
        const isTrue = (val) => val === true || val === 1 || val === '1' || val === 'true';

        formData.pc_exclude_sitemap = isTrue(formData.pc_exclude_sitemap);
        formData.pc_http_410 = isTrue(formData.pc_http_410);
        formData.pc_qcm_enabled = isTrue(formData.pc_qcm_enabled);
        
        // Cas particulier pour ItemList qui est activé par défaut : on vérifie s'il est explicitement désactivé
        const il = formData.pc_search_emit_itemlist;
        formData.pc_search_emit_itemlist = !(il === false || il === 0 || il === '0' || il === 'false');
    }
})
</script>

<style scoped>
.pc-tabs-nav { border-bottom: 1px solid #ccd0d4; margin-bottom: 15px; }
.pc-tab-btn { background: none; border: none; padding: 10px 15px; cursor: pointer; }
.pc-tab-btn.is-active { border-bottom: 2px solid #2271b1; font-weight: bold; color: #2271b1; }
.pc-field-group { margin-bottom: 15px; }
.pc-field-group label { display: block; font-weight: 600; margin-bottom: 4px; }
.pc-help-text { color: #646970; font-style: italic; font-size: 13px; margin: 0 0 5px 0; }
.pc-conditional { padding: 10px; background: #f0f0f1; border-left: 4px solid #2271b1; }
</style>