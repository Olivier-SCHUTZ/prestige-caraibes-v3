<template>
  <div class="tab-pdf-wrapper">
    <div class="tab-header">
      <p class="pc-help-text">Créez des modèles PDF personnalisés (Contrats, Devis, Vouchers...) pour vos réservations.</p>
      <button class="pc-btn pc-btn-primary" @click="openModal()">
        <span>➕</span> Nouveau Modèle PDF
      </button>
    </div>

    <div v-if="store.loading && store.templates.length === 0" class="loading-state">
      ⏳ Chargement de vos modèles PDF...
    </div>

    <div v-else-if="store.templates.length === 0" class="empty-state">
      📄 Aucun modèle PDF personnalisé.
    </div>

    <div v-else class="table-responsive">
      <table class="pc-table">
        <thead>
          <tr>
            <th>Nom du modèle PDF</th>
            <th>Type de Document</th>
            <th>Contexte</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="tpl in store.templates" :key="tpl.id">
            <td>
              <div class="font-bold">{{ tpl.title }}</div>
            </td>
            <td>
              <span class="type-badge">{{ formatType(tpl.type) }}</span>
            </td>
            <td>
              <span class="context-badge">{{ formatContext(tpl.context) }}</span>
            </td>
            <td class="text-right">
              <button @click="openModal(tpl.id)" class="pc-btn pc-btn-sm pc-action-edit">
                <span>✏️</span> Éditer
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

<TemplatePdfModal v-if="isModalOpen" @close="closeModal" />
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { usePdfTemplateStore } from '@/stores/pdf-template-store';
import TemplatePdfModal from '../components/TemplatePdfModal.vue';

const store = usePdfTemplateStore();
const isModalOpen = ref(false);

const formatType = (type) => {
  const types = {
    devis: '📄 Devis',
    facture: '🧾 Facture',
    facture_acompte: '💰 Facture d\'acompte',
    avoir: '↩️ Avoir',
    contrat: '📋 Contrat',
    voucher: '🎫 Voucher / Bon',
    document: '📝 Document générique'
  };
  return types[type] || type;
};

const formatContext = (context) => {
  const contexts = {
    global: '🌍 Global (Tout)',
    location: '🏠 Logements',
    experience: '🎯 Expériences'
  };
  return contexts[context] || context;
};

const openModal = async (id = null) => {
  if (id) {
    await store.fetchTemplateDetails(id);
  } else {
    store.resetCurrentTemplate();
  }
  isModalOpen.value = true;
};

const closeModal = () => {
  isModalOpen.value = false;
  store.fetchTemplates();
};

onMounted(() => {
  store.fetchTemplates();
});
</script>

<style scoped>
/* Les styles sont similaires à TemplatesTabMessages.vue */
.tab-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
.pc-help-text { color: #64748b; margin: 0; }
.table-responsive { overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px; }
.pc-table { width: 100%; border-collapse: collapse; text-align: left; }
.pc-table th { background: #f8fafc; padding: 1rem; color: #64748b; font-weight: 600; border-bottom: 2px solid #e2e8f0; }
.pc-table td { padding: 1rem; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
.font-bold { font-weight: 600; color: #0f172a; }
.text-right { text-align: right; }

.type-badge { background: #f1f5f9; color: #475569; padding: 4px 8px; border-radius: 6px; font-size: 0.85rem; font-weight: 500; border: 1px solid #e2e8f0; }
.context-badge { background: #ede9fe; color: #6d28d9; padding: 4px 8px; border-radius: 6px; font-size: 0.85rem; font-weight: 500; }

.pc-btn { padding: 0.5rem 1rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; }
.pc-btn-primary { background: #8b5cf6; color: white; }
.pc-btn-primary:hover { background: #7c3aed; }
.pc-btn-sm { padding: 0.4rem 0.8rem; font-size: 0.85rem; }
.pc-action-edit { background: white; border: 1px solid #cbd5e1; color: #334155; }
.pc-action-edit:hover { background: #f8fafc; }

.loading-state, .empty-state { text-align: center; padding: 3rem; color: #64748b; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1; }
</style>