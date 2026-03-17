<template>
  <div class="pc-calendar-dashboard">
    <CalendarHeader
      v-model:month="store.currentMonth"
      v-model:year="store.currentYear"
      @refresh="fetchData"
    />

    <div v-if="store.loading" class="loading-state">
      <p>⏳ Chargement du calendrier...</p>
    </div>

    <div v-else-if="store.error" class="error-state">
      <p>❌ {{ store.error }}</p>
      <button @click="fetchData">Réessayer</button>
    </div>

    <div v-else class="success-state">
      <CalendarGrid
        v-if="store.logements"
        :logements="store.logements"
        :events="store.events"
        :start-date="store.startDate"
        :extended-end-date="store.extendedEndDate"
      />

      <CalendarModal v-if="store.modalOpen" />
    </div>
  </div>
</template>

<script setup>
import { onMounted, onUnmounted } from "vue";
import { useCalendarStore } from "../../stores/calendar-store";
import CalendarHeader from "../../components/Calendar/CalendarHeader.vue";
import CalendarGrid from "../../components/Calendar/CalendarGrid.vue";
import CalendarModal from "../../components/Calendar/CalendarModal.vue";

const store = useCalendarStore();

const fetchData = async () => {
  await store.fetchGlobalCalendar(store.currentMonth, store.currentYear);
};

// 🚀 L'ÉCOUTEUR : Quand une réservation est modifiée (créée, annulée, etc.)
const handleCalendarRefresh = () => {
  store.clearSelection(); // Fait disparaître la barre noire
  fetchData(); // Rafraîchit le calendrier en arrière-plan !
};

onMounted(() => {
  fetchData();
  // On branche nos deux écouteurs
  window.addEventListener("pc-reservation-created", handleCalendarRefresh);
  window.addEventListener("pc-refresh-calendar", handleCalendarRefresh);
});

onUnmounted(() => {
  // On débranche proprement
  window.removeEventListener("pc-reservation-created", handleCalendarRefresh);
  window.removeEventListener("pc-refresh-calendar", handleCalendarRefresh);
});
</script>

<style scoped>
.pc-calendar-dashboard {
  /* On retire nos styles de test pour laisser le CSS legacy gérer le layout principal */
  margin-top: 20px;
}

.debug-panel {
  margin-top: 20px;
  padding: 15px;
  background: #ecfdf5;
  border: 1px solid #10b981;
  border-radius: 6px;
  color: #065f46;
}

.error-state,
.loading-state {
  margin-top: 20px;
  padding: 20px;
  text-align: center;
  font-weight: bold;
}
</style>
