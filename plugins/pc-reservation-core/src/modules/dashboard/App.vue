<template>
  <div class="pc-dashboard-v2-container">
    <header class="v2-header">
      <h2>Espace Propriétaire (Version 2.0)</h2>
      <span class="status-badge" :class="{ loading: store.isLoading }">
        {{ store.isLoading ? "Chargement..." : "Actif 🚀" }}
      </span>
    </header>

    <p class="description">
      Cette interface est propulsée par Vue.js 3 et Pinia. Les données
      ci-dessous proviennent de notre nouveau Store de manière asynchrone via
      Axios.
    </p>

    <div class="stats-grid">
      <StatCard title="Réservations" :value="store.stats.totalReservations" />

      <StatCard
        title="Revenus Générés"
        :value="store.formattedRevenue"
        :is-revenue="true"
      />

      <StatCard
        title="Messages en attente"
        :value="store.stats.pendingMessages"
        :has-alert="store.hasUnreadMessages"
        alert-text="À traiter !"
      />
    </div>

    <div v-if="store.error" class="error-notice">ℹ️ {{ store.error }}</div>
  </div>
</template>

<script setup>
import { onMounted } from "vue";
import { useDashboardStore } from "../../stores/dashboard-store.js";
import StatCard from "../../components/StatCard.vue"; // ✨ L'import de notre composant

// Initialisation du store Pinia
const store = useDashboardStore();

// Au montage du composant dans le DOM, on déclenche l'appel réseau
onMounted(() => {
  store.fetchDashboardStats();
});
</script>

<style scoped>
/* Le CSS est maintenant beaucoup plus court car le style des cartes est dans StatCard.vue ! */
.pc-dashboard-v2-container {
  padding: 2rem;
  background-color: #f8fafc;
  border: 2px dashed #3b82f6;
  border-radius: 8px;
  font-family:
    system-ui,
    -apple-system,
    sans-serif;
  margin-top: 20px;
  margin-bottom: 20px;
}

.v2-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

h2 {
  color: #1e293b;
  margin: 0;
}

.description {
  color: #64748b;
  font-size: 0.95rem;
  margin-bottom: 2rem;
}

.status-badge {
  background-color: #10b981;
  color: white;
  padding: 6px 12px;
  border-radius: 20px;
  font-weight: 600;
  font-size: 0.85rem;
  transition: all 0.3s ease;
}

.status-badge.loading {
  background-color: #f59e0b;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
  margin-bottom: 1.5rem;
}

.error-notice {
  background: #eff6ff;
  color: #1d4ed8;
  padding: 1rem;
  border-radius: 8px;
  font-size: 0.9rem;
  border-left: 4px solid #3b82f6;
}
</style>
