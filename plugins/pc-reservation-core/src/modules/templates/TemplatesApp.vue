<template>
  <div class="pc-templates-v2-container">
    <div class="header-actions">
      <h2>Modèles & Automatisations <span class="v2-badge">V2</span></h2>
    </div>

    <div class="v2-tabs-nav">
      <button
        class="v2-tab-btn"
        :class="{ active: activeTab === 'messages' }"
        @click="activeTab = 'messages'"
      >
        💬 Modèles de Messages
      </button>
      <button
        class="v2-tab-btn"
        :class="{ active: activeTab === 'pdfs' }"
        @click="activeTab = 'pdfs'"
      >
        📄 Modèles PDF
      </button>
    </div>

    <div class="v2-tabs-content">
      
      <div v-show="activeTab === 'messages'">
        <TemplatesTabMessages />
      </div>

      <div v-show="activeTab === 'pdfs'">
        <TemplatesTabPdf />
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref } from "vue";
import TemplatesTabMessages from "./tabs/TemplatesTabMessages.vue";
import TemplatesTabPdf from "./tabs/TemplatesTabPdf.vue";

// On démarre par défaut sur l'onglet des messages
const activeTab = ref("messages");
</script>

<style scoped>
.pc-templates-v2-container {
  background: white;
  padding: 2rem;
  border-radius: 12px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
  font-family: system-ui, -apple-system, sans-serif;
  border: 2px dashed #8b5cf6; /* Violet pour les automatisations */
}

.header-actions { margin-bottom: 2rem; }
.header-actions h2 { margin: 0; color: #1e293b; display: flex; align-items: center; gap: 10px; }
.v2-badge { background-color: #8b5cf6; color: white; font-size: 0.8rem; padding: 2px 8px; border-radius: 12px; }

.v2-tabs-nav {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  border-bottom: 2px solid #e2e8f0;
  padding-bottom: 1rem;
  margin-bottom: 2rem;
}

.v2-tab-btn {
  padding: 0.6rem 1.2rem; background: transparent; border: none; border-radius: 8px;
  color: #64748b; font-weight: 500; cursor: pointer; transition: all 0.2s; font-size: 0.95rem;
}
.v2-tab-btn:hover { background: #f1f5f9; color: #0f172a; }
.v2-tab-btn.active { background: #ede9fe; color: #8b5cf6; font-weight: 600; }

.placeholder-state { text-align: center; padding: 4rem 2rem; color: #64748b; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1; }
.placeholder-state h3 { color: #334155; margin-top: 0; }
</style>