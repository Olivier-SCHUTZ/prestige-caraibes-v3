<template>
  <div class="pc-housing-v2-container">
    <div class="header-actions">
      <h2>Mes Logements <span class="v2-badge">V2</span></h2>
      <button @click="openModal(0)" class="pc-btn pc-btn-primary">
        <span>➕</span> Nouveau Logement
      </button>
    </div>

    <div class="filters-bar">
      <input
        type="text"
        v-model="searchQuery"
        @input="onSearch"
        placeholder="Rechercher un logement..."
        class="pc-input search-input"
      />
      <select v-model="statusFilter" @change="onFilter" class="pc-select">
        <option value="">Tous les statuts</option>
        <option value="publish">Publié</option>
        <option value="draft">Brouillon</option>
      </select>
      <select v-model="modeFilter" @change="onFilter" class="pc-select">
        <option value="">Tous les modes</option>
        <option value="log_directe">Réservation Directe</option>
        <option value="log_demande">Sur Demande</option>
      </select>
      <select v-model="typeFilter" @change="onFilter" class="pc-select">
        <option value="">Tous les types</option>
        <option value="villa">Villa</option>
        <option value="appartement">Appartement</option>
      </select>
    </div>

    <div v-if="store.isLoading" class="loading-state">
      ⏳ Chargement des logements...
    </div>
    <div v-else-if="store.error" class="error-state">🚨 {{ store.error }}</div>
    <div v-else-if="store.items.length === 0" class="empty-state">
      📭 Aucun logement trouvé.
    </div>

    <div v-else class="table-responsive">
      <table class="pc-table">
        <thead>
          <tr>
            <th>Image</th>
            <th>Nom / Type</th>
            <th>Capacité</th>
            <th>Prix Dès</th>
            <th>Localisation</th>
            <th>Statut</th>
            <th>Mode</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="house in store.items" :key="house.id">
            <td>
              <img
                :src="house.image?.thumbnail || fallbackImage"
                class="housing-thumb"
                alt="Miniature"
              />
            </td>
            <td>
              <div class="font-bold">{{ house.title }}</div>
              <div class="text-xs text-slate-500">
                {{ house.type || "Non défini" }}
              </div>
            </td>
            <td>
              <span class="pc-badge-gray">{{ house.capacite || 0 }} pers.</span>
            </td>
            <td>
              <span class="font-bold text-emerald">{{
                formatPrice(house.base_price_from)
              }}</span>
            </td>
            <td class="text-sm">{{ house.ville || "Non définie" }}</td>
            <td>
              <span
                :class="[
                  'pc-status-badge',
                  house.status_class || 'pc-status-draft',
                ]"
              >
                {{ house.status_label || "Brouillon" }}
              </span>
            </td>
            <td>
              <span :class="['pc-mode-badge', house.mode_class]">
                {{ house.mode_label || "Non défini" }}
              </span>
            </td>
            <td class="text-right">
              <button
                @click="openModal(house.id)"
                class="pc-btn pc-btn-sm pc-action-edit"
              >
                <span>✏️</span> Éditer
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="store.pagination.totalPages > 1" class="pc-pagination">
      <button
        :disabled="store.pagination.currentPage === 1"
        @click="changePage(store.pagination.currentPage - 1)"
      >
        ‹ Précédent
      </button>
      <span class="page-info"
        >Page {{ store.pagination.currentPage }} sur
        {{ store.pagination.totalPages }}</span
      >
      <button
        :disabled="store.pagination.currentPage === store.pagination.totalPages"
        @click="changePage(store.pagination.currentPage + 1)"
      >
        Suivant ›
      </button>
    </div>

    <HousingModal />
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useHousingStore } from "../../stores/housing-store.js";
import { useHousingModalStore } from "../../stores/housing-modal-store.js";
import HousingModal from "../../components/HousingModal.vue";

const store = useHousingStore();
const modalStore = useHousingModalStore();

const searchQuery = ref("");
const statusFilter = ref("");
const modeFilter = ref("");
const typeFilter = ref("");
let searchTimeout = null;

const fallbackImage =
  "data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjYwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0yMCAyMEg0MEMzNS41ODE3IDMzLjMzMzMgMjguNDE4MyAzMy4zMzMzIDI0IDMzLjMzMzNWNDBIMjBWMjBaIiBmaWxsPSIjOTRBM0I4Ci8+Cjwvc3ZnPgo=";

const onSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    store.setFilters({ search: searchQuery.value });
  }, 500);
};

const onFilter = () => {
  store.setFilters({
    status: statusFilter.value,
    mode: modeFilter.value,
    type: typeFilter.value,
  });
};

const changePage = (page) => {
  store.fetchHousings(page);
};

const formatPrice = (price) => {
  if (!price || price <= 0) return "Non défini";
  return parseFloat(price).toFixed(0) + "€";
};

const openModal = (id) => {
  modalStore.openModal(id);
};

onMounted(() => {
  store.fetchHousings();
});
</script>

<style scoped>
.pc-housing-v2-container {
  background: white;
  padding: 2rem;
  border-radius: 12px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
  font-family:
    system-ui,
    -apple-system,
    sans-serif;
  border: 2px dashed #6366f1;
}
.header-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}
.header-actions h2 {
  margin: 0;
  color: #1e293b;
  display: flex;
  align-items: center;
  gap: 10px;
}
.v2-badge {
  background-color: #6366f1;
  color: white;
  font-size: 0.8rem;
  padding: 2px 8px;
  border-radius: 12px;
}
.filters-bar {
  display: flex;
  gap: 1rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}
.pc-input,
.pc-select {
  padding: 0.75rem 1rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.95rem;
  background: white;
  /* CORRECTIONS WORDPRESS */
  box-sizing: border-box;
  height: auto !important;
  line-height: 1.5;
}
.search-input {
  flex: 2;
  min-width: 200px;
}
.pc-select {
  flex: 1;
  min-width: 150px;
}
.table-responsive {
  overflow-x: auto;
}
.pc-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}
.pc-table th {
  background: #f8fafc;
  padding: 1rem;
  color: #64748b;
  font-weight: 600;
  border-bottom: 2px solid #e2e8f0;
  white-space: nowrap;
}
.pc-table td {
  padding: 1rem;
  border-bottom: 1px solid #e2e8f0;
  vertical-align: middle;
}
.housing-thumb {
  width: 60px;
  height: 60px;
  object-fit: cover;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
.font-bold {
  font-weight: 600;
  color: #0f172a;
}
.text-xs {
  font-size: 0.75rem;
}
.text-sm {
  font-size: 0.875rem;
  color: #475569;
}
.text-slate-500 {
  color: #64748b;
}
.text-emerald {
  color: #10b981;
}
.text-right {
  text-align: right;
}
.pc-badge-gray {
  background: #f1f5f9;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 0.85rem;
  font-weight: 500;
}
.pc-status-badge,
.pc-mode-badge {
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 600;
  display: inline-block;
}
.pc-status-publish {
  background: #dcfce7;
  color: #166534;
}
.pc-status-draft {
  background: #f1f5f9;
  color: #475569;
}
.pc-mode-badge {
  background: #e0e7ff;
  color: #3730a3;
}
.pc-btn {
  padding: 0.6rem 1.2rem;
  border-radius: 8px;
  border: none;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.pc-btn-primary {
  background: #6366f1;
  color: white;
}
.pc-btn-primary:hover {
  background: #4f46e5;
  transform: translateY(-1px);
}
.pc-btn-sm {
  padding: 0.4rem 0.8rem;
  font-size: 0.85rem;
}
.pc-action-edit {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #334155;
}
.pc-action-edit:hover {
  background: #e2e8f0;
}
.pc-pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 1rem;
  margin-top: 2rem;
}
.pc-pagination button {
  padding: 0.5rem 1rem;
  border: 1px solid #e2e8f0;
  background: white;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 500;
  color: #334155;
}
.pc-pagination button:hover:not(:disabled) {
  background: #f1f5f9;
}
.pc-pagination button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  background: #f8fafc;
}
.loading-state,
.error-state,
.empty-state {
  text-align: center;
  padding: 4rem 2rem;
  color: #64748b;
  background: #f8fafc;
  border-radius: 8px;
  font-size: 1.1rem;
}
.error-state {
  color: #ef4444;
  background: #fef2f2;
  border: 1px solid #fecaca;
}
</style>
