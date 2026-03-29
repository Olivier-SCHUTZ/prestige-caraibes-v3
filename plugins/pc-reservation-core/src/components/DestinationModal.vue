<template>
  <Teleport to="body">
    <div id="destination-modal-v2" class="v2-modal-wrapper">
      <div class="v2-modal-overlay" @click="$emit('close')"></div>

      <div class="v2-modal-container">
        <div class="v2-modal-header">
          <h2>{{ modalTitle }}</h2>
          <button class="v2-close-btn" @click="$emit('close')" title="Fermer">✕</button>
        </div>

        <div class="v2-modal-body">
          <div v-if="store.loading" class="v2-loading-state">
            ⏳ Chargement des détails...
          </div>

          <div v-else-if="store.error" class="v2-error-state">
            ⚠️ {{ store.error }}
          </div>

          <div v-else class="pc-form-container">
            
            <div class="pc-form-row">
              <div class="pc-form-group pc-col-8">
                <label>Nom de la destination <span class="pc-text-danger">*</span></label>
                <input type="text" v-model="formData.title" class="pc-input" required placeholder="Ex: Sainte-Anne" />
              </div>
              <div class="pc-form-group pc-col-4">
                <label>Statut</label>
                <select v-model="formData.status" class="pc-input">
                  <option value="publish">Publié</option>
                  <option value="draft">Brouillon</option>
                  <option value="pending">En attente</option>
                  <option value="private">Privé</option>
                </select>
              </div>
            </div>

            <div class="v2-tabs-nav">
              <button
                v-for="tab in tabs"
                :key="tab.id"
                class="v2-tab-btn"
                :class="{ active: activeTab === tab.id }"
                @click="activeTab = tab.id"
              >
                {{ tab.label }}
              </button>
            </div>

            <div class="v2-tabs-content">
              <div v-show="activeTab === 'main'">
                <DestinationTabMain :formData="formData" />
              </div>

              <div v-show="activeTab === 'textes'">
                <DestinationTabTextes :formData="formData" />
              </div>

              <div v-show="activeTab === 'infos'">
                <DestinationTabInfos :formData="formData" />
              </div>

              <div v-show="activeTab === 'faq'">
                <DestinationTabFaq :formData="formData" />
              </div>

              <div v-show="activeTab === 'seo'">
                <DestinationTabSeo :formData="formData" />
              </div>

            </div>
          </div>
        </div>

        <div class="v2-modal-footer">
          <div class="footer-left">
            <span class="pc-text-danger" v-if="saveError">{{ saveError }}</span>
            <button v-if="formData.id" class="pc-btn pc-btn-danger">
              <span>🗑️</span> Supprimer
            </button>
          </div>
          <div class="footer-right">
            <button
              class="pc-btn pc-btn-secondary"
              @click="$emit('close')"
              :disabled="isSaving"
            >
              Annuler
            </button>
            <button
              class="pc-btn pc-btn-primary"
              @click="handleSave"
              :disabled="isSaving || store.loading"
            >
              {{ isSaving ? "⏳ Sauvegarde..." : "💾 Sauvegarder" }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useDestinationStore } from "@/stores/destination-store";
import DestinationTabMain from "./destination/DestinationTabMain.vue";
import DestinationTabInfos from "./destination/DestinationTabInfos.vue";
import DestinationTabFaq from "./destination/DestinationTabFaq.vue";
import DestinationTabSeo from "./destination/DestinationTabSeo.vue";
import DestinationTabTextes from "./destination/DestinationTabTextes.vue";

const emit = defineEmits(["close", "saved"]);
const store = useDestinationStore();

const activeTab = ref("main");
const isSaving = ref(false);
const saveError = ref("");

const tabs = [
  { id: "main", label: "Infos principales" },
  { id: "textes", label: "Contenus & Relations" },
  { id: "infos", label: "Infos Pratiques" },
  { id: "faq", label: "FAQ" },
  { id: "seo", label: "SEO" },
];

// Initialisation de toutes les données du formulaire
const formData = ref({
    id: 0,
    title: '',
    slug: '',
    status: 'draft',
    content: '',
    dest_hero_desktop: null,
    dest_hero_mobile: null,
    dest_region: '',
    dest_geo_lat: 0,
    dest_geo_lng: 0,
    dest_population: 0,
    dest_surface_km2: 0,
    dest_airport_distance_km: 0,
    dest_sea_side: 'caraibes',
    dest_h1: '',
    dest_intro: '',
    dest_slogan: '',
    dest_featured: false,
    dest_order: 0,
    dest_exp_featured: [],
    dest_logements_recommandes: [],
    dest_infos: [],
    dest_faq: [],
    dest_exclude_sitemap: false,
    dest_http_410: false,
    dest_meta_title: '',
    dest_meta_description: '',
    dest_meta_canonical: '',
    dest_meta_robots: 'index,follow'
});

// Titre dynamique de la modale
const modalTitle = computed(() => {
  if (store.loading) return "Chargement...";
  if (formData.value.id) {
    return `Édition : ${formData.value.title}`;
  }
  return "Nouvelle destination";
});

// Au montage, on peuple formData avec les données du store si on édite
onMounted(() => {
    if (store.currentDestination) {
        const rawData = JSON.parse(JSON.stringify(store.currentDestination));
        formData.value = { ...formData.value, ...rawData };
    }
});

// Sauvegarde
const handleSave = async () => {
    if (!formData.value.title) {
        saveError.value = "Le nom de la destination est obligatoire.";
        return;
    }

    isSaving.value = true;
    saveError.value = "";

    const result = await store.saveDestination(formData.value);

    isSaving.value = false;

    if (result.success) {
        emit("saved");
        emit("close");
    } else {
        saveError.value = result.message || "Erreur lors de la sauvegarde.";
    }
};
</script>

<style scoped>
/* ---------------------------------------------------
   Isolation totale de la V2 par rapport à WordPress
--------------------------------------------------- */
.v2-modal-wrapper {
  position: fixed;
  inset: 0;
  z-index: 150000; /* Laisse passer la modale Media WP (160000) */
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: system-ui, -apple-system, sans-serif;
}

.v2-modal-overlay {
  position: absolute;
  inset: 0;
  background-color: rgba(15, 23, 42, 0.75);
  backdrop-filter: blur(4px);
}

.v2-modal-container {
  position: relative;
  background: white;
  width: 95%;
  max-width: 900px;
  max-height: 90vh;
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  overflow: hidden;
}

.v2-modal-header {
  padding: 1.5rem 2rem;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #f8fafc;
}

.v2-modal-header h2 {
  margin: 0;
  font-size: 1.5rem;
  color: #0f172a;
  font-weight: 600;
}

.v2-close-btn {
  background: transparent;
  border: none;
  font-size: 1.5rem;
  color: #64748b;
  cursor: pointer;
  transition: color 0.2s;
}

.v2-close-btn:hover {
  color: #ef4444;
}

.v2-modal-body {
  padding: 2rem;
  overflow-y: auto;
  flex: 1;
}

/* ---------------------------------------------------
   Mise en page des champs (Grille)
--------------------------------------------------- */
.pc-form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}
.pc-col-8 { flex: 2; }
.pc-col-4 { flex: 1; }
.pc-form-group {
    display: flex;
    flex-direction: column;
}
.pc-form-group label {
    font-weight: 600;
    margin-bottom: 8px;
    color: #334155;
    font-size: 0.95rem;
}
.pc-input {
    width: 100%;
    box-sizing: border-box; /* LA LIGNE MAGIQUE */
    height: 40px; /* Force une hauteur identique pour les select et input */
    padding: 0 12px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 1rem;
    transition: border-color 0.2s;
    background: white;
}
.pc-input:focus {
    outline: none;
    border-color: #3b82f6; /* Bleu Destination */
}
.pc-text-danger { color: #ef4444; }

/* ---------------------------------------------------
   Onglets V2
--------------------------------------------------- */
.v2-tabs-nav {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  border-bottom: 2px solid #e2e8f0;
  padding-bottom: 1rem;
  margin-bottom: 2rem;
}

.v2-tab-btn {
  padding: 0.5rem 1rem;
  background: transparent;
  border: none;
  border-radius: 6px;
  color: #64748b;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.v2-tab-btn:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.v2-tab-btn.active {
  background: #eff6ff;
  color: #3b82f6; /* Bleu Destination */
  font-weight: 600;
}

/* ---------------------------------------------------
   Footer & Boutons
--------------------------------------------------- */
.v2-modal-footer {
  padding: 1.5rem 2rem;
  border-top: 1px solid #e2e8f0;
  background: #f8fafc;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.footer-right {
  display: flex;
  gap: 1rem;
}

.v2-loading-state,
.v2-error-state {
  text-align: center;
  padding: 3rem;
  border-radius: 8px;
}
.v2-error-state {
  background: #fef2f2;
  color: #ef4444;
}

.pc-btn {
  padding: 0.6rem 1.2rem;
  border-radius: 8px;
  border: none;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 8px;
}
.pc-btn-primary {
  background: #3b82f6; /* Bleu Destination */
  color: white;
}
.pc-btn-primary:hover:not(:disabled) {
  background: #2563eb;
}
.pc-btn-secondary {
  background: white;
  border: 1px solid #cbd5e0;
  color: #475569;
}
.pc-btn-secondary:hover:not(:disabled) {
  background: #f8fafc;
}
.pc-btn-danger {
  background: #ef4444;
  color: white;
}
.pc-btn-danger:hover:not(:disabled) {
  background: #dc2626;
}
.pc-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Utilitaires temporaires */
.pc-text-muted { color: #94a3b8; }
.pc-text-center { text-align: center; }
.pc-p-4 { padding: 1.5rem; }
</style>