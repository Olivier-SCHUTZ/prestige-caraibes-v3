<template>
  <div class="pc-cal-heading">
    <div class="pc-cal-heading__text">
      <p class="pc-cal-eyebrow">Dashboard</p>
      <h2 class="pc-cal-title">Dashboard Calendrier</h2>
      <p class="pc-cal-subtitle">Période affichée : mois courant + 15 jours.</p>
      <div class="pc-cal-legend">
        <span class="pc-cal-legend__item">
          <span class="pc-cal-dot pc-cal-dot--paye"></span>Réservé (Soldé)
        </span>
        <span class="pc-cal-legend__item">
          <span class="pc-cal-dot pc-cal-dot--partiel"></span>Acompte payé
        </span>
        <span class="pc-cal-legend__item">
          <span class="pc-cal-dot pc-cal-dot--pending"></span>Réservé (En
          attente)
        </span>
        <span class="pc-cal-legend__item">
          <span class="pc-cal-dot pc-cal-dot--manual"></span>Blocage Manuel
        </span>
        <span class="pc-cal-legend__item">
          <span class="pc-cal-dot pc-cal-dot--ical"></span>iCal / Import
        </span>
      </div>
    </div>
    <div class="pc-cal-actions">
      <div class="pc-cal-select-group">
        <label class="pc-cal-select-label" for="vue-cal-month">Mois</label>
        <select
          id="vue-cal-month"
          class="pc-cal-select"
          v-model="localMonth"
          @change="onFilterChange"
        >
          <option
            v-for="(name, index) in monthNames"
            :key="index"
            :value="index + 1"
          >
            {{ name }}
          </option>
        </select>
      </div>
      <div class="pc-cal-select-group">
        <label class="pc-cal-select-label" for="vue-cal-year">Année</label>
        <select
          id="vue-cal-year"
          class="pc-cal-select"
          v-model="localYear"
          @change="onFilterChange"
        >
          <option v-for="year in availableYears" :key="year" :value="year">
            {{ year }}
          </option>
        </select>
      </div>
      <button type="button" class="pc-cal-today-btn" @click="goToToday">
        Aujourd’hui
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from "vue";

const props = defineProps({
  month: { type: Number, required: true },
  year: { type: Number, required: true },
});

const emit = defineEmits(["update:month", "update:year", "refresh"]);

const localMonth = ref(props.month);
const localYear = ref(props.year);

// Noms des mois en français
const monthNames = [
  "Janvier",
  "Février",
  "Mars",
  "Avril",
  "Mai",
  "Juin",
  "Juillet",
  "Août",
  "Septembre",
  "Octobre",
  "Novembre",
  "Décembre",
];

// Années disponibles (année actuelle - 1 jusqu'à + 3)
const currentRealYear = new Date().getFullYear();
const availableYears = computed(() => {
  const years = [];
  for (let i = currentRealYear - 1; i <= currentRealYear + 3; i++) {
    years.push(i);
  }
  return years;
});

// Synchro props -> local
watch(
  () => props.month,
  (newVal) => {
    localMonth.value = newVal;
  },
);
watch(
  () => props.year,
  (newVal) => {
    localYear.value = newVal;
  },
);

const onFilterChange = () => {
  emit("update:month", parseInt(localMonth.value));
  emit("update:year", parseInt(localYear.value));
  emit("refresh"); // Déclenche le rechargement de l'API
};

const goToToday = () => {
  const today = new Date();
  localMonth.value = today.getMonth() + 1;
  localYear.value = today.getFullYear();
  onFilterChange();
};
</script>
