<template>
  <div class="pc-settings-v2-container">
    <div class="header-actions">
      <h2>Configuration Globale <span class="v2-badge">V2</span></h2>
      <div class="actions-right">
        <span v-if="store.saveSuccessMessage" class="success-message">
          ✅ {{ store.saveSuccessMessage }}
        </span>
        <span v-if="store.error" class="error-message">
          ❌ {{ store.error }}
        </span>
        <button
          class="pc-btn pc-btn-primary"
          @click="handleSave"
          :disabled="store.loading"
        >
          {{ store.loading ? "⏳ Sauvegarde..." : "💾 Sauvegarder les réglages" }}
        </button>
      </div>
    </div>

    <div v-if="isLoadingInitial" class="loading-state">
      ⏳ Chargement de la configuration...
    </div>

    <div v-else class="settings-content">
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

      <div class="v2-tabs-content pc-form-container">
        <div v-show="activeTab === 'payments'">
          <SettingsTabPayments :formData="formData" />
        </div>
        
        <div v-show="activeTab === 'messaging'">
          <SettingsTabMessaging :formData="formData" />
        </div>

        <div v-show="activeTab === 'api'">
          <SettingsTabApi :formData="formData" />
        </div>

        <div v-show="activeTab === 'identity'">
          <SettingsTabIdentity :formData="formData" />
        </div>

        <div v-show="activeTab === 'legal'">
          <SettingsTabLegal :formData="formData" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from "vue";
import { useSettingsStore } from "@/stores/settings-store";
import SettingsTabPayments from "./tabs/SettingsTabPayments.vue";
import SettingsTabMessaging from "./tabs/SettingsTabMessaging.vue";
import SettingsTabApi from "./tabs/SettingsTabApi.vue";
import SettingsTabIdentity from "./tabs/SettingsTabIdentity.vue";
import SettingsTabLegal from "./tabs/SettingsTabLegal.vue";
// On importera les autres onglets ici au fur et à mesure

const store = useSettingsStore();

const activeTab = ref("payments");
const isLoadingInitial = ref(true);

// Les onglets (calqués sur ton ancienne configuration ACF)
const tabs = [
  { id: "payments", label: "Paiements (Stripe)" },
  { id: "messaging", label: "Messagerie" },
  { id: "api", label: "Connectivité / API" },
  { id: "identity", label: "Divers & Identité" },
  { id: "legal", label: "Documents, Légal & CGV" },
];

// L'objet local qui sera lié aux inputs (v-model)
const formData = ref({});

// Au montage, on récupère les données
onMounted(async () => {
  await store.fetchSettings();
  // On clone les données du store dans notre formData local
  formData.value = JSON.parse(JSON.stringify(store.settings));
  isLoadingInitial.value = false;
});

// Sauvegarde
const handleSave = async () => {
  await store.saveSettings(formData.value);
};
</script>

<style scoped>
/* On réutilise les excellents styles de ton module Destination ! */
.pc-settings-v2-container {
  background: white;
  padding: 2rem;
  border-radius: 12px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
  font-family: system-ui, -apple-system, sans-serif;
  border: 2px dashed #10b981; /* Vert émeraude pour repérer les Settings V2 */
  max-width: 1200px;
  margin: 0 auto;
}

.header-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #e2e8f0;
}

.header-actions h2 { margin: 0; color: #1e293b; display: flex; align-items: center; gap: 10px; }
.actions-right { display: flex; align-items: center; gap: 15px; }

.v2-badge { background-color: #10b981; color: white; font-size: 0.8rem; padding: 2px 8px; border-radius: 12px; }
.success-message { color: #10b981; font-weight: 500; }
.error-message { color: #ef4444; font-weight: 500; }

.v2-tabs-nav {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  border-bottom: 2px solid #e2e8f0;
  padding-bottom: 1rem;
  margin-bottom: 2rem;
}

.v2-tab-btn {
  padding: 0.5rem 1rem; background: transparent; border: none; border-radius: 6px;
  color: #64748b; font-weight: 500; cursor: pointer; transition: all 0.2s;
}
.v2-tab-btn:hover { background: #f1f5f9; color: #0f172a; }
.v2-tab-btn.active { background: #ecfdf5; color: #10b981; font-weight: 600; }

.pc-btn {
  padding: 0.6rem 1.2rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 600;
  transition: all 0.2s; display: flex; align-items: center; gap: 8px;
}
.pc-btn-primary { background: #10b981; color: white; }
.pc-btn-primary:hover:not(:disabled) { background: #059669; }
.pc-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.loading-state { text-align: center; padding: 4rem; color: #64748b; font-size: 1.1rem; }
.placeholder { padding: 3rem; text-align: center; color: #94a3b8; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1; }
</style>