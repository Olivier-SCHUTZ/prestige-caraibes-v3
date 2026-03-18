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

<style scoped>
/* ==========================================================================
   Styles encapsulés du Header Calendrier (issus de pc-calendar.css)
   ========================================================================== */

.pc-cal-heading {
  /* Variables locales rapatriées du :root */
  --pc-cal-primary: #6366f1;
  --pc-cal-primary-dark: #4f46e5;
  --pc-cal-gradient-primary: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
  --pc-cal-text-primary: #1f2937;
  --pc-cal-text-secondary: #6b7280;

  display: grid;
  grid-template-columns: 1fr auto;
  gap: 2rem;
  align-items: start;
  margin-bottom: 3rem;
  padding: 2rem 0;
  border-bottom: 2px solid rgba(99, 102, 241, 0.1);
  position: relative;
}

.pc-cal-heading::after {
  content: "";
  position: absolute;
  bottom: -2px;
  left: 0;
  right: 0;
  height: 2px;
  background: linear-gradient(90deg, #6366f1 0%, #4f46e5 50%, #7c3aed 100%);
  border-radius: 1px;
}

.pc-cal-heading__text {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.pc-cal-eyebrow {
  letter-spacing: 0.15em;
  text-transform: uppercase;
  font-size: 0.8rem;
  font-weight: 800;
  color: var(--pc-cal-primary);
  margin: 0;
  text-shadow: 0 1px 2px rgba(99, 102, 241, 0.2);
}

.pc-cal-title {
  margin: 0;
  font-size: 2rem;
  font-weight: 700;
  line-height: 1.3;
  color: var(--pc-cal-text-primary);
  text-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.pc-cal-subtitle {
  margin: 0;
  font-size: 1.1rem;
  color: var(--pc-cal-text-secondary);
  font-weight: 500;
  line-height: 1.5;
}

/* === ACTIONS ET CHAMPS === */
.pc-cal-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 1rem;
  flex-wrap: wrap;
}

.pc-cal-select-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  font-size: 0.9rem;
}

.pc-cal-select-label {
  font-weight: 700;
  color: var(--pc-cal-text-primary);
  font-size: 0.9rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 0;
}

.pc-cal-select {
  min-width: 160px;
  padding: 0.9rem 3rem 0.9rem 1.2rem;
  border-radius: 12px;
  border: 2px solid #e2e8f0;
  background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
  font-family: inherit;
  font-size: 1rem;
  font-weight: 500;
  color: var(--pc-cal-text-primary) !important;
  transition: all 0.2s ease;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
  appearance: none;
  -webkit-appearance: none;
  -moz-appearance: none;
  cursor: pointer;
  position: relative;
  background-image:
    linear-gradient(45deg, transparent 50%, var(--pc-cal-primary) 50%),
    linear-gradient(135deg, var(--pc-cal-primary) 50%, transparent 50%);
  background-position:
    calc(100% - 18px) 50%,
    calc(100% - 12px) 50%;
  background-size:
    8px 8px,
    8px 8px;
  background-repeat: no-repeat;
  line-height: 1.4;
  height: auto;
  min-height: 3.2rem;
}

.pc-cal-select:focus {
  outline: none;
  border-color: var(--pc-cal-primary);
  box-shadow:
    0 0 0 3px rgba(99, 102, 241, 0.1),
    0 4px 8px rgba(0, 0, 0, 0.1);
  background: #ffffff;
}

.pc-cal-select:hover {
  border-color: rgba(99, 102, 241, 0.3);
  box-shadow: 0 4px 12px rgba(99, 102, 241, 0.1);
}

.pc-cal-today-btn {
  padding: 0.75rem 1.5rem;
  border-radius: 12px;
  border: none;
  background: var(--pc-cal-gradient-primary);
  color: #ffffff;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
  position: relative;
  overflow: hidden;
  margin-top: auto; /* Aligne avec le bas des selects */
  height: 3.2rem;
}

.pc-cal-today-btn:hover {
  box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
  transform: translateY(-1px);
}

/* === LÉGENDES === */
.pc-cal-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem 1.5rem;
  margin: 1.5rem 0;
}

.pc-cal-legend__item {
  display: inline-flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem 1rem;
  background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
  border-radius: 24px;
  border: 2px solid rgba(99, 102, 241, 0.1);
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--pc-cal-text-primary);
  transition: all 0.2s ease;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.pc-cal-dot {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid rgba(255, 255, 255, 0.2);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  flex-shrink: 0;
}

/* Couleurs des pastilles */
.pc-cal-dot--paye {
  background: linear-gradient(135deg, #059669 0%, #047857 100%);
  border-color: #047857;
}
.pc-cal-dot--partiel {
  background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
  border-color: #0369a1;
}
.pc-cal-dot--pending {
  background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
  border-color: #c2410c;
}
.pc-cal-dot--manual {
  background: linear-gradient(135deg, #f0b429 0%, #d97706 100%);
}
.pc-cal-dot--ical {
  background: linear-gradient(135deg, #5c6f82 0%, #475569 100%);
}

/* Badges dynamiques (Utilisation de :has pour styliser le conteneur en fonction du dot) */
.pc-cal-legend__item:has(.pc-cal-dot--paye) {
  background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
  border-color: rgba(22, 163, 74, 0.3);
  color: #166534;
}
.pc-cal-legend__item:has(.pc-cal-dot--pending) {
  background: linear-gradient(135deg, #fef3c7 0%, #fed7aa 100%);
  border-color: rgba(245, 158, 11, 0.3);
  color: #92400e;
}
.pc-cal-legend__item:has(.pc-cal-dot--manual) {
  background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
  border-color: rgba(240, 180, 41, 0.3);
  color: #92400e;
}
.pc-cal-legend__item:has(.pc-cal-dot--ical) {
  background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
  border-color: rgba(92, 111, 130, 0.3);
  color: #475569;
}

/* Responsive */
@media (max-width: 960px) {
  .pc-cal-heading {
    grid-template-columns: 1fr;
    gap: 1.5rem;
  }
  .pc-cal-actions {
    justify-content: flex-start;
  }
}

@media (max-width: 680px) {
  .pc-cal-actions {
    width: 100%;
  }
  .pc-cal-select-group {
    width: 100%;
  }
  .pc-cal-select {
    width: 100%;
  }
  .pc-cal-today-btn {
    width: 100%;
    margin-top: 10px;
  }
}
</style>
