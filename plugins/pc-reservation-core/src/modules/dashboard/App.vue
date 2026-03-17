<template>
  <div class="pc-reservation-dashboard">
    <header class="dashboard-header">
      <h1>Tableau de bord de Réservations</h1>
      <span v-if="dashboardStore.isLoading">Chargement des données...</span>
    </header>

    <div v-if="dashboardStore.error" class="error-notice">
      {{ dashboardStore.error }}
    </div>

    <div
      v-if="!dashboardStore.isLoading && !dashboardStore.error"
      class="stats-test-grid"
    >
      <div class="stat-card">
        <h3>Total Résas</h3>
        <p>{{ dashboardStore.stats.totalReservations }}</p>
      </div>
      <div class="stat-card">
        <h3>Revenus</h3>
        <p>{{ dashboardStore.stats.revenue }} €</p>
      </div>
      <div class="stat-card">
        <h3>Messages en attente</h3>
        <p>{{ dashboardStore.stats.pendingMessages }}</p>
      </div>
    </div>

    <div
      style="
        margin-top: 20px;
        padding: 15px;
        background: #fff;
        border-radius: 6px;
      "
    >
      <h3>Test d'action API Réservation</h3>
      <button
        @click="testCancel"
        :disabled="reservationsStore.isLoading"
        style="padding: 10px; cursor: pointer"
      >
        {{
          reservationsStore.isLoading
            ? "Annulation en cours..."
            : "Tester annulation (ID fictif : 99999)"
        }}
      </button>
      <p
        v-if="reservationsStore.error"
        style="color: red; margin-top: 10px; font-weight: bold"
      >
        Erreur interceptée depuis le PHP (C'est normal !) :
        {{ reservationsStore.error }}
      </p>
    </div>

    <ReservationList />

    <ReservationModal />

    <BookingForm />
  </div>
</template>

<script setup>
import { onMounted } from "vue";
import { useDashboardStore } from "../../stores/dashboard-store";
import { useReservationsStore } from "../../stores/reservations-store";
import ReservationList from "../../components/dashboard/ReservationList.vue";
import ReservationModal from "../../components/dashboard/ReservationModal.vue";
import BookingForm from "../../components/dashboard/BookingForm.vue";

const dashboardStore = useDashboardStore();
const reservationsStore = useReservationsStore(); // NOUVELLE INSTANCE

onMounted(() => {
  // On lance la récupération des stats au montage de l'application
  dashboardStore.fetchStats();
});

// NOUVELLE FONCTION DE TEST
const testCancel = async () => {
  try {
    // On tente d'annuler une réservation qui n'existe pas pour tester l'erreur renvoyée par PHP
    await reservationsStore.cancelReservation(99999);
  } catch (e) {
    // L'erreur est gérée par le store et affichée dans le template
  }
};
</script>

<style scoped>
.pc-reservation-dashboard {
  padding: 20px;
  background: #f9f9f9;
  border-radius: 8px;
}
.stats-test-grid {
  display: flex;
  gap: 20px;
  margin-top: 20px;
}
.stat-card {
  background: white;
  padding: 15px 25px;
  border-radius: 6px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
.error-notice {
  color: red;
  padding: 10px;
  background: #ffe6e6;
  border-radius: 4px;
}
</style>
