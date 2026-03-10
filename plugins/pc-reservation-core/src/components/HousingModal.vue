<template>
  <Teleport to="body">
    <div
      v-if="modalStore.isOpen"
      id="housing-modal-v2"
      class="v2-modal-wrapper"
    >
      <div class="v2-modal-overlay" @click.stop.prevent="closeModal"></div>

      <div class="v2-modal-container">
        <div class="v2-modal-header">
          <h2>{{ modalTitle }}</h2>
          <button class="v2-close-btn" @click.stop.prevent="closeModal">
            ✕
          </button>
        </div>

        <div class="v2-modal-body">
          <div v-if="modalStore.isLoading" class="v2-loading-state">
            ⏳ Chargement des détails...
          </div>

          <div v-else-if="modalStore.error" class="v2-error-state">
            ⚠️ {{ modalStore.error }}
          </div>

          <div v-else>
            <div class="v2-tabs-nav">
              <button
                v-for="tab in tabs"
                :key="tab.id"
                class="v2-tab-btn"
                :class="{ active: modalStore.activeTab === tab.id }"
                @click.stop.prevent="modalStore.setTab(tab.id)"
              >
                {{ tab.label }}
              </button>
            </div>

            <div class="v2-tabs-content">
              <div v-show="modalStore.activeTab === 'general'">
                <TabGeneral />
              </div>

              <div v-show="modalStore.activeTab === 'location'">
                <TabLocation />
              </div>

              <div v-show="modalStore.activeTab === 'rates'">
                <TabRates />
              </div>

              <div v-show="modalStore.activeTab === 'images'">
                <TabImages />
              </div>

              <div v-show="modalStore.activeTab === 'amenities'">
                <TabAmenities />
              </div>

              <div v-show="modalStore.activeTab === 'content'">
                <TabContent />
              </div>

              <div v-show="modalStore.activeTab === 'booking'">
                <TabBooking />
              </div>

              <div v-show="modalStore.activeTab === 'config'">
                <TabConfig />
              </div>
            </div>
          </div>
        </div>

        <div class="v2-modal-footer">
          <div class="footer-left">
            <button
              v-if="modalStore.housingId !== 0"
              class="pc-btn pc-btn-danger"
              @click.stop.prevent="deleteHousing"
            >
              <span>🗑️</span> Supprimer
            </button>
          </div>
          <div class="footer-right">
            <button
              class="pc-btn pc-btn-secondary"
              @click.stop.prevent="closeModal"
              :disabled="modalStore.isSaving"
            >
              Annuler
            </button>
            <button
              class="pc-btn pc-btn-primary"
              :disabled="modalStore.isSaving"
              @click.stop.prevent="saveHousing"
            >
              {{ modalStore.isSaving ? "⏳ Sauvegarde..." : "💾 Sauvegarder" }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed } from "vue";
import { useHousingModalStore } from "../stores/housing-modal-store.js";
import { useHousingStore } from "../stores/housing-store.js";
import TabGeneral from "./Housing/TabGeneral.vue";
import TabLocation from "./Housing/TabLocation.vue";
import TabRates from "./Housing/TabRates.vue";
import TabImages from "./Housing/TabImages.vue";
import TabAmenities from "./Housing/TabAmenities.vue";
import TabContent from "./Housing/TabContent.vue";
import TabBooking from "./Housing/TabBooking.vue";
import TabConfig from "./Housing/TabConfig.vue";

const modalStore = useHousingModalStore();
const listStore = useHousingStore();

const tabs = [
  { id: "general", label: "Général" },
  { id: "location", label: "Localisation" },
  { id: "rates", label: "Tarifs & Paiement" },
  { id: "images", label: "Images & Galerie" },
  { id: "amenities", label: "Équipements" },
  { id: "content", label: "Contenu & SEO" },
  { id: "booking", label: "Réservation & Hôte" },
  { id: "config", label: "Configuration" },
];

const modalTitle = computed(() => {
  if (modalStore.isLoading) return "Chargement...";
  if (modalStore.housingId === 0) return "Nouveau logement";
  return modalStore.formData.title || "Édition du logement";
});

const closeModal = () => {
  modalStore.closeModal();
};

const saveHousing = async () => {
  if (modalStore.housingId === 0 && !modalStore.formData.type) {
    alert("Veuillez sélectionner le type de logement (Villa ou Appartement).");
    return;
  }

  const success = await modalStore.saveHousing();
  if (success) {
    alert("Logement sauvegardé avec succès !");
    listStore.fetchHousings(listStore.pagination.currentPage);
  }
};

const deleteHousing = () => {
  if (
    confirm(
      "Êtes-vous sûr de vouloir supprimer ce logement ? Cette action est irréversible.",
    )
  ) {
    alert("Fonction de suppression à implémenter");
  }
};
</script>

<style scoped>
/* CSS Calqué sur ExperienceModal.vue */
.v2-modal-wrapper {
  position: fixed;
  inset: 0;
  z-index: 150000;
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

/* Boutons PC */
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
