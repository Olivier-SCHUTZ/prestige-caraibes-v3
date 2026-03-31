<template>
  <div class="tab-messages-wrapper">
    <div class="tab-header">
      <p class="pc-help-text">Gérez vos emails automatiques et vos réponses rapides pour WhatsApp.</p>
      <button class="pc-btn pc-btn-primary" @click="openModal()">
        <span>➕</span> Nouveau Message
      </button>
    </div>

    <div v-if="store.loading && store.templates.length === 0" class="loading-state">
      ⏳ Chargement de vos modèles...
    </div>

    <div v-else-if="store.templates.length === 0" class="empty-state">
      📭 Aucun modèle de message configuré.
    </div>

    <div v-else class="table-responsive">
      <table class="pc-table">
        <thead>
          <tr>
            <th>Nom du modèle</th>
            <th>Catégorie</th>
            <th>Déclencheur</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="tpl in store.templates" :key="tpl.id">
            <td>
              <div class="font-bold">{{ tpl.title }}</div>
            </td>
            <td>
              <span :class="['category-badge', tpl.category]">
                {{ formatCategory(tpl.category) }}
              </span>
            </td>
            <td>
              <span class="text-sm">{{ formatType(tpl.type) }}</span>
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
    <TemplateMessageModal 
      v-if="isModalOpen" 
      @close="closeModal" 
    />

    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useTemplateStore } from '@/stores/template-store';
import TemplateMessageModal from '../components/TemplateMessageModal.vue';

const store = useTemplateStore();
const isModalOpen = ref(false);

const formatCategory = (cat) => {
  return cat === 'email_system' ? '📧 Email Système' : '💬 Réponse Rapide';
};

const formatType = (type) => {
  const types = {
    libre: 'Manuelle (Libre)',
    immediat: 'Immédiat (Action)',
    programme: 'Programmé (Délai)'
  };
  return types[type] || type;
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
  store.fetchTemplates(); // Rafraîchit la liste
};

onMounted(() => {
  store.fetchTemplates();
});
</script>

<style scoped>
.tab-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
.pc-help-text { color: #64748b; margin: 0; }

.table-responsive { overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px; }
.pc-table { width: 100%; border-collapse: collapse; text-align: left; }
.pc-table th { background: #f8fafc; padding: 1rem; color: #64748b; font-weight: 600; border-bottom: 2px solid #e2e8f0; }
.pc-table td { padding: 1rem; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
.pc-table tr:last-child td { border-bottom: none; }

.font-bold { font-weight: 600; color: #0f172a; }
.text-sm { font-size: 0.875rem; color: #475569; }
.text-right { text-align: right; }

.category-badge { padding: 4px 8px; border-radius: 6px; font-size: 0.85rem; font-weight: 500; }
.category-badge.email_system { background: #e0f2fe; color: #0369a1; }
.category-badge.quick_reply { background: #dcfce7; color: #166534; }

.pc-btn { padding: 0.5rem 1rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; }
.pc-btn-primary { background: #8b5cf6; color: white; }
.pc-btn-primary:hover { background: #7c3aed; }
.pc-btn-sm { padding: 0.4rem 0.8rem; font-size: 0.85rem; }
.pc-action-edit { background: white; border: 1px solid #cbd5e1; color: #334155; }
.pc-action-edit:hover { background: #f8fafc; }

.loading-state, .empty-state { text-align: center; padding: 3rem; color: #64748b; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1; }
</style>