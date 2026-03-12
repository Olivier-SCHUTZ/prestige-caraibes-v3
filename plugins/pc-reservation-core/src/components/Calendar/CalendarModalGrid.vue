<template>
  <div class="modal-grid-wrapper">
    <div class="modal-nav-header">
      <button
        class="nav-btn"
        @click="prevMonth"
        :disabled="store.singleLoading"
      >
        ◀ Précédent
      </button>
      <h4 class="current-month">{{ monthNameFull }}</h4>
      <button
        class="nav-btn"
        @click="nextMonth"
        :disabled="store.singleLoading"
      >
        Suivant ▶
      </button>
    </div>

    <div
      v-if="store.singleLoading"
      class="text-center p-4"
      style="
        color: #64748b;
        font-weight: bold;
        height: 100px;
        display: flex;
        align-items: center;
        justify-content: center;
      "
    >
      ⏳ Chargement de la période...
    </div>

    <div v-else class="pc-cal-grid-container custom-scrollbar">
      <table class="pc-cal-table">
        <thead>
          <tr>
            <th
              v-for="day in daysInPeriod"
              :key="day.date"
              class="pc-cal-th-day"
              :class="{
                'is-weekend': day.isWeekend,
                'is-today': day.isToday,
                'is-month-start': day.showMonthLabel,
              }"
            >
              <div class="day-name">{{ day.dayName }}</div>
              <div class="day-number">
                <span v-if="day.showMonthLabel" class="month-label">{{
                  day.monthName
                }}</span>
                {{ day.dayNumber }}
              </div>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr class="pc-cal-row single-property-row">
            <td
              v-for="(day, index) in daysInPeriod"
              :key="day.date"
              class="pc-cal-td-cell cursor-pointer"
              :class="{
                'is-weekend': day.isWeekend,
                'is-today': day.isToday,
                'is-selected': isDaySelected(day.date),
              }"
              @click="handleCellClick(day)"
              @mouseenter="handleCellHover(day)"
            >
              <div v-if="index === 0" class="pc-cal-events-layer">
                <div
                  v-for="(event, eIdx) in validEvents"
                  :key="event.id || eIdx"
                  class="pc-cal-event"
                  :style="getEventStyle(event)"
                  :title="event.label || getEventDefaultLabel(event)"
                  @click.stop="handleEventClick(event)"
                >
                  <div
                    class="pc-cal-event-bg"
                    :class="getEventClass(event)"
                  ></div>
                  <span class="pc-cal-event-label">{{
                    event.label || getEventDefaultLabel(event)
                  }}</span>
                </div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from "vue";
import { useCalendarStore } from "../../stores/calendar-store";

const store = useCalendarStore();

// On initialise la modale sur le mois actuellement sélectionné dans le store
const localDate = ref(new Date(store.currentYear, store.currentMonth - 1, 1));
const isLoading = ref(false);

// Formatage du mois (ex: "Mars 2026")
const monthNameFull = computed(() => {
  const nomMois = new Intl.DateTimeFormat("fr-FR", {
    month: "long",
    year: "numeric",
  }).format(localDate.value);
  return nomMois.charAt(0).toUpperCase() + nomMois.slice(1);
});

// Calcul des 45 jours à partir du 1er du mois
const daysInPeriod = computed(() => {
  const days = [];
  const start = new Date(localDate.value);
  const end = new Date(start);
  end.setDate(end.getDate() + 44); // 45 jours d'affichage

  const dayNames = ["Dim", "Lun", "Mar", "Mer", "Jeu", "Ven", "Sam"];
  const monthNamesShort = [
    "Jan",
    "Fév",
    "Mar",
    "Avr",
    "Mai",
    "Juin",
    "Juil",
    "Aoû",
    "Sep",
    "Oct",
    "Nov",
    "Déc",
  ];
  const todayStr = new Date().toISOString().split("T")[0];

  let current = new Date(start);
  while (current <= end) {
    const dateStr = current.toISOString().split("T")[0];
    const dayOfWeek = current.getDay();
    const month = current.getMonth();

    const isFirstDayOfMonth = current.getDate() === 1;
    const isFirstDayOfGrid = days.length === 0;

    days.push({
      date: dateStr,
      dayName: dayNames[dayOfWeek],
      dayNumber: current.getDate(),
      monthName: monthNamesShort[month],
      showMonthLabel: isFirstDayOfMonth || isFirstDayOfGrid,
      isWeekend: dayOfWeek === 0 || dayOfWeek === 6,
      isToday: dateStr === todayStr,
    });

    current.setDate(current.getDate() + 1);
  }
  return days;
});

// Navigation (+ / -)
const prevMonth = async () => {
  localDate.value = new Date(
    localDate.value.getFullYear(),
    localDate.value.getMonth() - 1,
    1,
  );
  await fetchEvents();
};

const nextMonth = async () => {
  localDate.value = new Date(
    localDate.value.getFullYear(),
    localDate.value.getMonth() + 1,
    1,
  );
  await fetchEvents();
};

// Requête API pour changer de mois dans la modale
const fetchEvents = async () => {
  isLoading.value = true;
  await store.fetchSingleCalendar(
    store.selectedLogement.id,
    localDate.value.getMonth() + 1,
    localDate.value.getFullYear(),
  );
  isLoading.value = false;
};

// --- LOGIQUE DES BANDES CONTINUES ---
const validEvents = computed(() => {
  return store.singleEvents.filter((e) => e.start_date && e.end_date);
});

const getEventStyle = (event) => {
  if (!daysInPeriod.value.length) return { display: "none" };

  const parseDate = (dStr) =>
    new Date(dStr.substring(0, 10) + "T00:00:00Z").getTime();
  const gridStart = parseDate(daysInPeriod.value[0].date);
  const eventStart = parseDate(event.start_date);
  const eventEnd = parseDate(event.end_date);
  const dayMs = 1000 * 60 * 60 * 24;

  let offsetDays = (eventStart - gridStart) / dayMs;
  let durationDays = (eventEnd - eventStart) / dayMs;

  // Si l'événement a commencé avant le 1er du mois
  if (offsetDays < 0) {
    durationDays += offsetDays;
    offsetDays = 0;
  }

  // Si la résa est finie ou n'est pas dans la période affichée
  if (durationDays <= 0) return { display: "none" };
  if (durationDays < 0.5) durationDays = 1;

  // On garde l'espace de 3px entre les bandes pour la lisibilité
  return {
    left: `calc(${offsetDays} * 100% + 3px)`,
    width: `calc(${durationDays} * 100% - 6px)`,
  };
};

const getEventClass = (event) => {
  if (event.source === "ical") return "bg-ical";
  if (event.source === "manual" || event.type === "blocking")
    return "bg-manual";

  // Totalement réglé -> Vert
  if (event.payment_status === "paye") return "bg-paye";

  // Acompte réglé -> Bleu
  if (event.payment_status === "partiellement_paye") return "bg-partiel";

  // Devis envoyé / En attente -> Orange
  if (event.payment_status === "en_attente_paiement") return "bg-pending";

  return "bg-default";
};

const getEventDefaultLabel = (event) => {
  if (event.source === "ical") return "Import iCal";
  if (event.source === "manual") return "Blocage Manuel";
  return "Réservation";
};

// --- LOGIQUE DE SÉLECTION (CLIC & SURVOL) ---
const selectionStep = ref(0); // 0: repos, 1: 1er clic fait, 2: 2ème clic fait
const selectionStart = ref(null);
const selectionEnd = ref(null);

const handleCellClick = (day) => {
  // Si on démarre une nouvelle sélection (ou si on avait déjà fini)
  if (selectionStep.value === 0 || selectionStep.value === 2) {
    selectionStart.value = day.date;
    selectionEnd.value = day.date;
    selectionStep.value = 1;
    store.clearSelection(); // Fait disparaître la barre d'action
  }
  // Si on valide la sélection au 2ème clic
  else if (selectionStep.value === 1) {
    selectionEnd.value = day.date;
    selectionStep.value = 2;

    // Remettre dans l'ordre chronologique si sélectionné à l'envers
    let start = selectionStart.value;
    let end = selectionEnd.value;
    if (start > end) {
      [start, end] = [end, start];
    }

    store.setSelection(start, end); // Fait apparaître la barre d'action
  }
};

const handleCellHover = (day) => {
  // Si on est en train de sélectionner (entre le clic 1 et 2), on met à jour la fin visuelle
  if (selectionStep.value === 1) {
    selectionEnd.value = day.date;
  }
};

// Vérifie si une case doit être colorée en bleu
const isDaySelected = (dateStr) => {
  if (!selectionStart.value || !selectionEnd.value) return false;
  const start =
    selectionStart.value < selectionEnd.value
      ? selectionStart.value
      : selectionEnd.value;
  const end =
    selectionStart.value > selectionEnd.value
      ? selectionStart.value
      : selectionEnd.value;
  return dateStr >= start && dateStr <= end;
};

// Quand le store annule la sélection (via le bouton Annuler), on réinitialise l'interface
store.$subscribe((mutation, state) => {
  if (state.selection === null && selectionStep.value === 2) {
    selectionStep.value = 0;
    selectionStart.value = null;
    selectionEnd.value = null;
  }
});

// Clic sur un événement existant
const handleEventClick = (event) => {
  // Si c'est un blocage manuel, on ouvre la barre en mode édition !
  if (
    event.type === "blocking" &&
    event.source === "manual" &&
    event.block_id
  ) {
    store.setEditSelection(event);
    selectionStep.value = 0; // On annule toute sélection de date en cours
  } else if (event.type === "reservation") {
    alert(`Réservation : ${event.label}\nStatut : ${event.status}`);
  }
};
</script>

<style scoped>
.modal-grid-wrapper {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

/* --- EN-TÊTE NAVIGATION --- */
.modal-nav-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #f8fafc;
  padding: 12px 20px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.current-month {
  margin: 0;
  font-size: 1.1rem;
  font-weight: bold;
  color: #1e293b;
}

.nav-btn {
  background: white;
  border: 1px solid #cbd5e1;
  padding: 8px 16px;
  border-radius: 6px;
  cursor: pointer;
  color: #3b82f6;
  font-weight: bold;
  transition: all 0.2s;
}

.nav-btn:hover:not(:disabled) {
  background: #eff6ff;
  border-color: #3b82f6;
}

.nav-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* --- GRILLE TIMELINE --- */
.pc-cal-grid-container {
  overflow-x: auto;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.pc-cal-table {
  width: 100%;
  border-collapse: collapse;
  display: table !important;
}

/* 🚀 FIX : On force toutes les lignes du tableau à rester à l'horizontale */
.pc-cal-table thead {
  display: table-header-group !important;
}
.pc-cal-table tbody {
  display: table-row-group !important;
}
.pc-cal-row {
  display: table-row !important;
}

.pc-cal-th-day,
.pc-cal-td-cell {
  display: table-cell !important;
  vertical-align: middle !important;
}

.pc-cal-th-day {
  padding: 10px 5px;
  text-align: center;
  border: 1px solid #e2e8f0;
  min-width: 45px;
  width: 45px;
  background: #f8fafc;
}

.pc-cal-td-cell {
  border: 1px solid #e2e8f0;
  /* Hauteur un peu plus grande pour cette ligne unique */
  height: 80px;
  position: relative !important;
}

.day-name {
  font-size: 0.75rem;
  color: #64748b;
  text-transform: uppercase;
}
.day-number {
  font-size: 1rem;
  font-weight: bold;
  color: #1e293b;
}

.is-weekend {
  background-color: #f1f5f9;
}

/* Marqueur "Aujourd'hui" */
.pc-cal-th-day.is-today,
.pc-cal-td-cell.is-today {
  background-color: #eff6ff !important;
  border-left: 2px solid #3b82f6 !important;
  border-right: 2px solid #3b82f6 !important;
}
.pc-cal-th-day.is-today {
  border-top: 2px solid #3b82f6 !important;
}
.is-today .day-number {
  color: #1d4ed8;
}

/* Labels des mois */
.month-label {
  display: block;
  font-size: 0.65rem;
  text-transform: uppercase;
  color: #ef4444;
  line-height: 1;
  margin-bottom: 2px;
}
.is-month-start {
  border-left: 2px solid #ef4444 !important;
}

/* --- BANDES CONTINUES --- */
.pc-cal-events-layer {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  z-index: 5;
}

.pc-cal-event {
  position: absolute;
  /* On centre la bande verticalement dans la case de 80px */
  top: 15px;
  bottom: 15px;
  z-index: 10;
  border-radius: 4px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
  border: 1px solid rgba(255, 255, 255, 0.5);
  display: flex;
  align-items: center;
  padding: 0 8px;
  cursor: pointer;
  transition: transform 0.2s;
}

.pc-cal-event:hover {
  transform: translateY(-2px);
  z-index: 20;
}

.pc-cal-event-bg {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: -1;
  border-radius: 4px;
  opacity: 0.95;
}

.pc-cal-event-label {
  color: #ffffff;
  font-size: 0.85rem;
  font-weight: 600;
  white-space: nowrap;
  text-overflow: ellipsis;
  overflow: hidden;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.4);
}

/* Mapping Couleurs */
.bg-paye {
  background: linear-gradient(135deg, #059669, #047857);
}
.bg-partiel {
  background: linear-gradient(135deg, #0284c7, #0369a1);
}
.bg-pending {
  background: linear-gradient(135deg, #ea580c, #c2410c);
}
.bg-manual {
  background: linear-gradient(135deg, #f0b429, #d97706);
}
.bg-ical {
  background: linear-gradient(135deg, #5c6f82, #475569);
}
.bg-default {
  background: linear-gradient(135deg, #64748b, #475569);
}

/* --- STYLES DE LA SÉLECTION --- */
.cursor-pointer {
  cursor: crosshair;
}

.pc-cal-td-cell.is-selected {
  background-color: #e0e7ff !important; /* Bleu très clair */
  /* On simule une bordure pour englober toute la période */
  box-shadow:
    inset 0 2px 0 0 #6366f1,
    inset 0 -2px 0 0 #6366f1;
}

/* Bordure gauche sur la 1ère case de la sélection */
.pc-cal-td-cell.is-selected:first-child,
.pc-cal-td-cell:not(.is-selected) + .pc-cal-td-cell.is-selected {
  box-shadow:
    inset 0 2px 0 0 #6366f1,
    inset 0 -2px 0 0 #6366f1,
    inset 2px 0 0 0 #6366f1;
}

/* Note : Les bandes colorées (événements) restent cliquables au-dessus de la sélection */
</style>
