<template>
  <div class="pc-cal-modal-overlay" @click.self="store.closeModal">
    <div class="pc-cal-modal-content">
      <div class="pc-cal-modal-header">
        <h3>📅 Planning de : {{ store.selectedLogement?.title }}</h3>
        <button class="pc-cal-modal-close" @click="store.closeModal">✕</button>
      </div>

      <div class="pc-cal-modal-body">
        <CalendarModalGrid />

        <CalendarSelectionBar />
      </div>
    </div>
  </div>
</template>

<script setup>
import { useCalendarStore } from "../../stores/calendar-store";
import CalendarModalGrid from "./CalendarModalGrid.vue";
import CalendarSelectionBar from "./CalendarSelectionBar.vue";
const store = useCalendarStore();
</script>

<style scoped>
.pc-cal-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(15, 23, 42, 0.7);
  z-index: 99999;
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(4px);
}

.pc-cal-modal-content {
  background: #fff;
  width: 90%;
  max-width: 1000px;
  min-height: 500px;
  border-radius: 12px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
  display: flex;
  flex-direction: column;
}

.pc-cal-modal-header {
  padding: 20px;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #f8fafc;
  border-radius: 12px 12px 0 0;
}

.pc-cal-modal-header h3 {
  margin: 0;
  font-size: 1.25rem;
}

.pc-cal-modal-close {
  background: transparent;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  color: #64748b;
}

.pc-cal-modal-close:hover {
  color: #ef4444;
}

.pc-cal-modal-body {
  padding: 20px;
  flex: 1;
}

.loading-overlay {
  display: flex;
  justify-content: center;
  align-items: center;
  height: 200px;
  font-weight: bold;
  color: #64748b;
}
</style>
