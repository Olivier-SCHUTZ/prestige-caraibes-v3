<template>
  <div class="pc-destination-v2-container">
    <div class="header-actions">
      <h2>Mes Destinations <span class="v2-badge">V2</span></h2>
      <button @click="openModal()" class="pc-btn pc-btn-primary">
        <span>➕</span> Nouvelle Destination
      </button>
    </div>

    <div class="filters-bar">
      <input
        type="text"
        v-model="searchQuery"
        @input="onSearch"
        placeholder="Rechercher une destination..."
        class="pc-input search-input"
      />
      <select
        v-model="statusFilter"
        @change="onFilter"
        class="pc-select status-select"
      >
        <option value="all">Tous les statuts</option>
        <option value="publish">Publié</option>
        <option value="draft">Brouillon</option>
        <option value="pending">En attente</option>
        <option value="private">Privé</option>
      </select>
    </div>

    <div v-if="store.loading && store.destinations.length === 0" class="loading-state">
      ⏳ Chargement des destinations en cours...
    </div>

    <div v-else-if="store.error" class="error-state">
      🚨 {{ store.error }}
    </div>

    <div v-else-if="store.destinations.length === 0" class="empty-state">
      📭 Aucune destination trouvée avec ces critères.
    </div>

    <div v-else class="table-responsive">
      <table class="pc-table">
        <thead>
          <tr>
            <th width="80">Image</th>
            <th>Nom de la destination</th>
            <th>Région</th>
            <th>Statut</th>
            <th class="text-center">Mise en avant</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="dest in store.destinations" :key="dest.id">
            <td>
              <img 
                :src="dest.image?.thumbnail || fallbackImage" 
                :alt="dest.title" 
                class="destination-thumb" 
              />
            </td>
            <td>
              <div class="font-bold">{{ dest.title }}</div>
              <div class="text-sm">/{{ dest.slug }}</div>
            </td>
            <td>
              <span class="region-badge">{{ formatRegion(dest.dest_region) }}</span>
            </td>
            <td>
              <span :class="['pc-status-badge', dest.status_class || 'pc-status--draft']">
                {{ dest.status_label || 'Brouillon' }}
              </span>
            </td>
            <td class="text-center">
              <span v-if="dest.dest_featured" title="Mise en avant">⭐</span>
              <span v-else class="text-muted" title="Standard">☆</span>
            </td>
            <td class="text-right">
              <button
                @click.stop.prevent="openModal(dest.id)"
                class="pc-btn pc-btn-sm pc-action-edit"
              >
                <span>✏️</span> Éditer
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="store.pagination.pages > 1" class="pc-pagination">
      <button
        :disabled="store.pagination.currentPage === 1"
        @click="changePage(store.pagination.currentPage - 1)"
      >
        ‹ Précédent
      </button>
      <span class="page-info">
        Page {{ store.pagination.currentPage }} sur {{ store.pagination.pages }}
      </span>
      <button
        :disabled="store.pagination.currentPage === store.pagination.pages"
        @click="changePage(store.pagination.currentPage + 1)"
      >
        Suivant ›
      </button>
    </div>

    <DestinationModal v-if="isModalOpen" @close="closeModal" @saved="onSaved" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
// On utilise l'alias '@' défini dans ton vite.config.js pour un chemin infaillible
import { useDestinationStore } from '@/stores/destination-store';
import DestinationModal from '@/components/DestinationModal.vue';

const store = useDestinationStore();

// Variables locales pour interagir avec les inputs
const searchQuery = ref("");
const statusFilter = ref("all");
let searchTimeout = null;

// État pour la modale
const isModalOpen = ref(false);

// L'image de secours (SVG intégré pour éviter les requêtes inutiles)
const fallbackImage =
  "data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjYwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0yMCAyMEg0MEMzNS41ODE3IDMzLjMzMzMgMjguNDE4MyAzMy4zMzMzIDI0IDMzLjMzMzNWNDBIMjBWMjBaIiBmaWxsPSIjOTRBM0I4Ci8+Cjwvc3ZnPgo=";

// Formatage de la région pour l'affichage
const formatRegion = (regionCode) => {
    const regions = {
        'grande-terre': 'Grande-Terre',
        'basse-terre': 'Basse-Terre',
        'iles-voisines': 'Îles voisines'
    };
    return regions[regionCode] || regionCode || 'Non définie';
};

// Recherche avec Debounce (attend 500ms après la dernière frappe)
const onSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    store.setFilters({ search: searchQuery.value });
  }, 500);
};

// Filtre sur le statut
const onFilter = () => {
  store.setFilters({ status: statusFilter.value });
};

// Changement de page
const changePage = (page) => {
  if (page >= 1 && page <= store.pagination.pages) {
    store.fetchDestinations(page);
  }
};

// Ouvrir la modale (Création ou Édition)
const openModal = async (destinationId = null) => {
    if (destinationId) {
        await store.fetchDestinationDetails(destinationId);
    } else {
        store.resetCurrentDestination();
    }
    isModalOpen.value = true;
};

// Fermer la modale
const closeModal = () => {
    isModalOpen.value = false;
    store.resetCurrentDestination();
};

// Action après sauvegarde réussie
const onSaved = () => {
    store.fetchDestinations(store.pagination.currentPage);
};

// Au montage, on charge les données !
onMounted(() => {
  store.fetchDestinations(1);
});
</script>

<style scoped>
.pc-destination-v2-container {
  background: white;
  padding: 2rem;
  border-radius: 12px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
  font-family: system-ui, -apple-system, sans-serif;
  border: 2px dashed #3b82f6; /* Bordure bleue pour repérer notre module V2 Destination */
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
  background-color: #3b82f6; /* Bleu pour destination */
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
  box-sizing: border-box;
  height: 42px;
}

.status-select {
  flex: 1;
  padding: 0.75rem 1rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: white;
  font-size: 0.95rem;
  box-sizing: border-box;
  height: 42px;
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

.destination-thumb {
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
.text-muted {
  color: #cbd5e1;
}

.region-badge {
  background: #f1f5f9;
  color: #475569;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 0.85rem;
  border: 1px solid #e2e8f0;
}

.pc-status-badge {
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 600;
  display: inline-block;
}

/* Correspondance avec les statuts générés par ton PHP */
.pc-status--published {
  background: #dcfce7;
  color: #166534;
}
.pc-status--draft {
  background: #f1f5f9;
  color: #475569;
}
.pc-status--pending {
  background: #fef9c3;
  color: #854d0e;
}
.pc-status--private {
  background: #fee2e2;
  color: #991b1b;
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