<template>
  <Teleport to="body">
    <div v-if="isModalOpen" id="experience-modal-v2" class="v2-modal-wrapper">
      <div class="v2-modal-overlay" @click="store.closeModal"></div>

      <div class="v2-modal-container">
        <div class="v2-modal-header">
          <h2>{{ modalTitle }}</h2>
          <button class="v2-close-btn" @click="store.closeModal">✕</button>
        </div>

        <div class="v2-modal-body">
          <div v-if="isLoading" class="v2-loading-state">
            ⏳ Chargement des détails...
          </div>

          <div v-else-if="error" class="v2-error-state">⚠️ {{ error }}</div>

          <div v-else-if="currentExperience">
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
              <div v-show="activeTab === 'seo'">
                <ExperienceTabSeo />
              </div>

              <div v-show="activeTab === 'main'">
                <ExperienceTabMain />
              </div>

              <div v-show="activeTab === 'sorties'">
                <ExperienceTabSorties />
              </div>
              <div v-show="activeTab === 'inclusions'">
                <ExperienceTabInclusions />
              </div>
              <div v-show="activeTab === 'services'">
                <ExperienceTabServices />
              </div>
              <div v-show="activeTab === 'galerie'">
                <ExperienceTabGalerie />
              </div>
              <div v-show="activeTab === 'faq'">
                <ExperienceTabFaq />
              </div>
              <div v-show="activeTab === 'tarifs'">
                <ExperienceTabTarifs />
              </div>
              <div v-show="activeTab === 'paiement'">
                <ExperienceTabPaiement />
              </div>
            </div>
          </div>
        </div>

        <div class="v2-modal-footer">
          <div class="footer-left">
            <button v-if="currentExperience?.id" class="pc-btn pc-btn-danger">
              <span>🗑️</span> Supprimer
            </button>
          </div>
          <div class="footer-right">
            <button
              class="pc-btn pc-btn-secondary"
              @click="store.closeModal"
              :disabled="isSaving"
            >
              Annuler
            </button>
            <button
              class="pc-btn pc-btn-primary"
              @click="handleSave"
              :disabled="isSaving || isLoading"
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
import { ref, computed } from "vue";
import { storeToRefs } from "pinia";
import { useExperienceStore } from "../stores/experience-store";
import ExperienceTabMain from "./experience/ExperienceTabMain.vue";
import ExperienceTabFaq from "./experience/ExperienceTabFaq.vue";
import ExperienceTabGalerie from "./experience/ExperienceTabGalerie.vue";
import ExperienceTabSorties from "./experience/ExperienceTabSorties.vue";
import ExperienceTabInclusions from "./experience/ExperienceTabInclusions.vue";
import ExperienceTabServices from "./experience/ExperienceTabServices.vue";
import ExperienceTabSeo from "./experience/ExperienceTabSeo.vue";
import ExperienceTabPaiement from "./experience/ExperienceTabPaiement.vue";
import ExperienceTabTarifs from "./experience/ExperienceTabTarifs.vue";

const store = useExperienceStore();
const { isModalOpen, currentExperience, isLoading, isSaving, error } =
  storeToRefs(store);

const activeTab = ref("main");

const tabs = [
  { id: "seo", label: "SEO & Liaisons" },
  { id: "main", label: "Détails Principaux" },
  { id: "sorties", label: "Détails Sorties" },
  { id: "inclusions", label: "Inclusions" },
  { id: "services", label: "Services" },
  { id: "galerie", label: "Galerie" },
  { id: "faq", label: "FAQ" },
  { id: "tarifs", label: "Tarifs" },
  { id: "paiement", label: "Règles & Paiement" },
];

const modalTitle = computed(() => {
  if (isLoading.value) return "Chargement...";
  if (!currentExperience.value) return "";
  if (currentExperience.value.id) {
    return (
      currentExperience.value.exp_h1_custom ||
      currentExperience.value.title ||
      "Édition de l'expérience"
    );
  }
  return "Nouvelle expérience";
});

const handleSave = async () => {
  await store.saveExperience();
};
</script>

<style scoped>
/* Isolation totale de la V2 par rapport au CSS de WordPress/jQuery */
.v2-modal-wrapper {
  position: fixed;
  inset: 0;
  z-index: 150000; /* Modifié pour laisser passer la modale Media WP (160000) */
  display: flex;
  align-items: center;
  justify-content: center;
  font-family:
    system-ui,
    -apple-system,
    sans-serif;
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
  color: #3b82f6;
  font-weight: 600;
}

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

/* Réutilisation des styles de boutons de ton application */
.pc-btn {
  padding: 0.6rem 1.2rem;
  border-radius: 8px;
  border: none;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.2s;
}
.pc-btn-primary {
  background: #4f46e5;
  color: white;
}
.pc-btn-primary:hover:not(:disabled) {
  background: #4338ca;
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
</style>
