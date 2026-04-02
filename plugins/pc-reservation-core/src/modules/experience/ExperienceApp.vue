<template>
  <div class="pc-experience-v2-container">
    <div class="header-actions">
      <h2>Mes Expériences <span class="v2-badge">V2</span></h2>
      <button @click="openModal()" class="pc-btn pc-btn-primary">
        <span>➕</span> Nouvelle Expérience
      </button>
    </div>

    <div class="filters-bar">
      <input
        type="text"
        v-model="searchQuery"
        @input="onSearch"
        placeholder="Rechercher une expérience..."
        class="pc-input search-input"
      />
      <select
        v-model="statusFilter"
        @change="onFilter"
        class="pc-select status-select"
      >
        <option value="">Tous les statuts</option>
        <option value="publish">Publié</option>
        <option value="draft">Brouillon</option>
      </select>
    </div>

    <div v-if="store.isLoading" class="loading-state">
      ⏳ Chargement des expériences en cours...
    </div>

    <div v-else-if="store.error" class="error-state">🚨 {{ store.error }}</div>

    <div v-else-if="store.items.length === 0" class="empty-state">
      📭 Aucune expérience trouvée avec ces critères.
    </div>

    <div v-else class="table-responsive">
      <table class="pc-table">
        <thead>
          <tr>
            <th>Image</th>
            <th>Nom</th>
            <th>Durée</th>
            <th>Capacité</th>
            <th>Lieu</th>
            <th class="text-center">TVA</th>
            <th>Statut</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in store.items" :key="item.id">
            <td>
              <img
                :src="item.image?.thumbnail || fallbackImage"
                :alt="item.title"
                class="experience-thumb"
              />
            </td>
            <td class="font-bold">{{ item.title }}</td>
            <td>{{ item.duree ? item.duree + "h" : "Non définie" }}</td>
            <td>
              {{ item.capacite ? item.capacite + " pers" : "Non définie" }}
            </td>
            <td class="text-sm">{{ item.lieu_depart || "Non défini" }}</td>
            <td class="text-center font-bold">
              {{ item.taux_tva !== "" ? item.taux_tva + "%" : "N/D" }}
            </td>
            <td>
              <span
                :class="[
                  'pc-status-badge',
                  item.status_class || 'pc-status-draft',
                ]"
              >
                {{ item.status_label || "Brouillon" }}
              </span>
            </td>
            <td class="text-right">
              <button
                @click.stop.prevent="openModal(item.id)"
                class="pc-btn pc-btn-sm v2-action-edit"
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

    <ExperienceModal />
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useExperienceStore } from "../../stores/experience-store.js";
import ExperienceModal from "../../components/ExperienceModal.vue";

const store = useExperienceStore();

// Variables locales pour interagir avec les inputs
const searchQuery = ref("");
const statusFilter = ref("");
let searchTimeout = null;

// L'image de secours (SVG intégré pour éviter les requêtes inutiles)
const fallbackImage =
  "data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjYwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0yMCAyMEg0MEMzNS41ODE3IDMzLjMzMzMgMjguNDE4MyAzMy4zMzMzIDI0IDMzLjMzMzNWNDBIMjBWMjBaIiBmaWxsPSIjOTRBM0I4Ci8+Cjwvc3ZnPgo=";

// Recherche avec Debounce (attend 500ms après la dernière frappe avant de lancer la recherche)
const onSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    store.setSearch(searchQuery.value);
  }, 500);
};

// Filtre sur le statut
const onFilter = () => {
  store.setStatus(statusFilter.value);
};

// Changement de page
const changePage = (page) => {
  if (page >= 1 && page <= store.pagination.totalPages) {
    store.fetchExperiences(page);
  }
};

// PONT STRANGLER : Appelle la modale jQuery
const openModal = (id = null) => {
  store.openModal(id);
};

// Au montage, on charge les données !
onMounted(() => {
  store.fetchExperiences();
});
</script>

<style scoped>
.pc-experience-v2-container {
  background: white;
  padding: 2rem;
  border-radius: 12px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
  font-family:
    system-ui,
    -apple-system,
    sans-serif;
  border: 2px dashed #10b981; /* Bordure verte pour repérer notre module V2 */
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
  background-color: #10b981;
  color: white;
  font-size: 0.8rem;
  padding: 2px 8px;
  border-radius: 12px;
}

.filters-bar {
  display: flex;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.search-input {
  flex: 2;
  padding: 0.75rem 1rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.95rem;
}

.status-select {
  flex: 1;
  padding: 0.75rem 1rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: white;
  font-size: 0.95rem;
  min-height: 42px;
  box-sizing: border-box;
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

.experience-thumb {
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

.text-sm {
  font-size: 0.875rem;
  color: #475569;
}

.text-center {
  text-align: center;
}
.text-right {
  text-align: right;
}

.pc-status-badge {
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
  background: #4f46e5;
  color: white;
}
.pc-btn-primary:hover {
  background: #4338ca;
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
  transition: all 0.2s;
}

.pc-pagination button:hover:not(:disabled) {
  background: #f1f5f9;
}

.pc-pagination button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  background: #f8fafc;
}

.page-info {
  font-size: 0.9rem;
  color: #64748b;
  font-weight: 500;
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
