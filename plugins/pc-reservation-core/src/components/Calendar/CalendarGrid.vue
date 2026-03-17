<template>
  <div class="pc-cal-grid-container">
    <table class="pc-cal-table">
      <thead>
        <tr>
          <th class="pc-cal-th-logement">Logements ({{ logements.length }})</th>
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
        <tr v-if="logements.length === 0">
          <td :colspan="daysInPeriod.length + 1" class="text-center p-4">
            Aucun logement trouvé pour cette période.
          </td>
        </tr>
        <tr v-for="logement in logements" :key="logement.id" class="pc-cal-row">
          <td class="pc-cal-td-logement">
            <button
              class="pc-cal-logement-btn"
              @click="store.openModal(logement)"
            >
              <strong>{{ logement.title }}</strong>
            </button>
          </td>
          <td
            v-for="(day, index) in daysInPeriod"
            :key="`${logement.id}-${day.date}`"
            class="pc-cal-td-cell"
            :class="{ 'is-weekend': day.isWeekend, 'is-today': day.isToday }"
          >
            <div v-if="index === 0" class="pc-cal-events-layer">
              <div
                v-for="(event, eIdx) in getEventsForLogement(logement.id)"
                :key="event.id || `${logement.id}-${eIdx}`"
                class="pc-cal-event"
                :style="getEventStyle(event)"
                :title="event.label || getEventDefaultLabel(event)"
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
</template>

<script setup>
import { computed } from "vue";
import { useCalendarStore } from "../../stores/calendar-store";

const store = useCalendarStore();

const props = defineProps({
  logements: { type: Array, required: true },
  events: { type: Array, required: true },
  startDate: { type: String, required: true },
  extendedEndDate: { type: String, required: true },
});

// 🚀 L'ARME FATALE V2 (TITANIUM) : Gère tous les formats (FR / EN) et bloque les fuseaux horaires
const getUtcNoon = (dStr) => {
  if (!dStr) return 0;
  let y, m, d;
  // On récupère les 10 premiers caractères (la date)
  const cleanStr = String(dStr).trim().substring(0, 10);

  if (cleanStr.includes("/")) {
    // Cas 1 : Format français JJ/MM/AAAA (ex: "01/04/2026")
    const parts = cleanStr.split("/");
    d = parseInt(parts[0], 10);
    m = parseInt(parts[1], 10);
    y = parseInt(parts[2], 10);
    if (y < 100) y += 2000; // Sécurité si l'année est sur 2 chiffres
  } else if (cleanStr.includes("-")) {
    // Cas 2 : Format Base de données YYYY-MM-DD (ex: "2026-04-01")
    const parts = cleanStr.split("-");
    y = parseInt(parts[0], 10);
    m = parseInt(parts[1], 10);
    d = parseInt(parts[2], 10);
  } else {
    // Cas 3 : Fallback natif en dernier recours
    const fb = new Date(dStr);
    y = fb.getFullYear();
    m = fb.getMonth() + 1;
    d = fb.getDate();
  }

  // On force midi UTC pour que la date ne bouge PLUS JAMAIS !
  return Date.UTC(y, m - 1, d, 12, 0, 0);
};

// Calcul de tous les jours entre startDate et extendedEndDate
const daysInPeriod = computed(() => {
  if (!props.startDate || !props.extendedEndDate) return [];

  const days = [];
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

  const today = new Date();
  const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, "0")}-${String(today.getDate()).padStart(2, "0")}`;

  let currentUtc = getUtcNoon(props.startDate);
  const endUtc = getUtcNoon(props.extendedEndDate);
  const dayMs = 1000 * 60 * 60 * 24;

  while (currentUtc <= endUtc) {
    const d = new Date(currentUtc);
    const y = d.getUTCFullYear();
    const m = d.getUTCMonth();
    const dateNum = d.getUTCDate();
    const dayOfWeek = d.getUTCDay();

    const dateStr = `${y}-${String(m + 1).padStart(2, "0")}-${String(dateNum).padStart(2, "0")}`;
    const isFirstDayOfMonth = dateNum === 1;
    const isFirstDayOfGrid = days.length === 0;

    days.push({
      date: dateStr,
      dayName: dayNames[dayOfWeek],
      dayNumber: dateNum,
      monthName: monthNamesShort[m],
      showMonthLabel: isFirstDayOfMonth || isFirstDayOfGrid,
      isWeekend: dayOfWeek === 0 || dayOfWeek === 6,
      isToday: dateStr === todayStr,
    });

    currentUtc += dayMs;
  }
  return days;
});

// 1. Filtrer les événements pour un logement donné
const getEventsForLogement = (logementId) => {
  return props.events.filter((e) => e.logement_id === logementId);
};

// 2. Calculer la position (gauche) et la largeur de la barre
const getEventStyle = (event) => {
  if (!props.startDate || !event.start_date || !event.end_date)
    return { display: "none" };

  const gridStart = getUtcNoon(props.startDate);
  const eventStart = getUtcNoon(event.start_date);
  const eventEnd = getUtcNoon(event.end_date);
  const dayMs = 1000 * 60 * 60 * 24;

  // Calcul du nombre de jours exacts depuis le début de la grille
  let startOffsetDays = Math.round((eventStart - gridStart) / dayMs);
  let endOffsetDays = Math.round((eventEnd - gridStart) / dayMs);

  // 🚀 LE SECRET EST ICI : On décale visuellement l'affichage à midi (+0.5 jour)
  let visualStart = startOffsetDays + 0.5;
  let visualEnd = endOffsetDays + 0.5;

  // Si la réservation a commencé avant le premier jour affiché du calendrier
  if (visualStart < 0) {
    visualStart = 0; // On coupe proprement au bord gauche
  }

  // La largeur est la différence entre la fin visuelle et le début visuel
  let visualWidth = visualEnd - visualStart;

  // Sécurité anti-bug graphique
  if (visualWidth <= 0) return { display: "none" };

  // 🚀 LE FIX DÉFINITIF : On remplace "100%" par "45px" (la taille exacte de la colonne) !
  // Ainsi le navigateur ne peut plus faire dériver la barre à cause des bordures.
  return {
    left: `calc(${visualStart} * 45px + 3px)`,
    width: `calc(${visualWidth} * 45px - 6px)`,
  };
};

// 3. Définir la couleur de fond selon le statut
const getEventClass = (event) => {
  if (event.source === "ical") return "bg-ical";
  if (event.source === "manual" || event.type === "blocking")
    return "bg-manual";

  // 🚀 NOUVEAU MAPPING STRICT BASÉ SUR TES STATUTS DE PAIEMENT

  // Totalement réglé -> Vert
  if (event.payment_status === "paye") return "bg-paye";

  // Acompte réglé -> Bleu
  if (event.payment_status === "partiellement_paye") return "bg-partiel";

  // Devis envoyé / En attente -> Orange
  if (event.payment_status === "en_attente_paiement") return "bg-pending";

  return "bg-default";
};

// 4. Texte par défaut si pas de label
const getEventDefaultLabel = (event) => {
  if (event.source === "ical") return "Import iCal";
  if (event.source === "manual") return "Blocage Manuel";
  return "Réservation";
};
</script>

<style scoped>
.pc-cal-grid-container {
  overflow-x: auto;
  margin-top: 20px;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

/* 🚀 On force l'affichage en tableau pour écraser l'ancien CSS */
.pc-cal-table {
  table-layout: fixed !important; /* Le secret est ici : force les colonnes égales */
  width: max-content;
  border-collapse: collapse;
  display: table !important;
}

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
.pc-cal-th-logement,
.pc-cal-td-logement,
.pc-cal-td-cell {
  display: table-cell !important;
  vertical-align: middle !important;
}

.pc-cal-th-logement,
.pc-cal-td-logement {
  width: 200px;
  min-width: 200px;
  position: sticky;
  left: 0;
  background: #fff;
  z-index: 10;
  border-right: 2px solid #e2e8f0;
  padding: 12px;
  text-align: left;
}

.pc-cal-th-day {
  padding: 10px 0 !important; /* On retire le padding horizontal */
  text-align: center;
  border: 1px solid #e2e8f0;
  width: 45px !important;
  min-width: 45px !important;
  max-width: 45px !important;
  box-sizing: border-box !important;
  background: #f8fafc;
}

.pc-cal-td-cell {
  border: 1px solid #e2e8f0;
  height: 50px;
  padding: 0 !important; /* Crucial pour que les pourcentages soient parfaits */
  width: 45px !important;
  min-width: 45px !important;
  max-width: 45px !important;
  box-sizing: border-box !important;
  position: relative;
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

/* 🚀 Mise en évidence de la colonne "Aujourd'hui" sur toute la hauteur */
.pc-cal-th-day.is-today,
.pc-cal-td-cell.is-today {
  background-color: #eff6ff !important; /* Fond bleu très clair */
  border-left: 2px solid #3b82f6 !important; /* Bordure gauche bleue */
  border-right: 2px solid #3b82f6 !important; /* Bordure droite bleue */
}

/* Bordure haute uniquement pour l'en-tête */
.pc-cal-th-day.is-today {
  border-top: 2px solid #3b82f6 !important;
}

.is-today .day-number {
  color: #1d4ed8; /* Bleu plus foncé pour le chiffre */
}

/* --- STYLES DES ÉVÉNEMENTS --- */

.month-label {
  display: block;
  font-size: 0.65rem;
  text-transform: uppercase;
  color: #ef4444; /* Rouge pour bien marquer la rupture du mois */
  line-height: 1;
  margin-bottom: 2px;
}
.is-month-start {
  border-left: 2px solid #ef4444 !important; /* Barre verticale pour séparer les mois */
}

/* --- STYLES DES ÉVÉNEMENTS --- */
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
  top: 6px;
  bottom: 6px;
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
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
  text-overflow: ellipsis;
  overflow: hidden;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.4);
}

/* Mapping Couleurs selon l'analyse */
.bg-paye {
  background: linear-gradient(135deg, #059669, #047857);
} /* Vert foncé */
.bg-partiel {
  background: linear-gradient(135deg, #0284c7, #0369a1);
} /* Bleu */
.bg-pending {
  background: linear-gradient(135deg, #ea580c, #c2410c);
} /* Orange */
.bg-manual {
  background: linear-gradient(135deg, #f0b429, #d97706);
} /* Jaune */
.bg-ical {
  background: linear-gradient(135deg, #5c6f82, #475569);
} /* Gris */
.bg-default {
  background: linear-gradient(135deg, #64748b, #475569);
}

.pc-cal-logement-btn {
  background: none;
  border: none;
  padding: 0;
  text-align: left;
  cursor: pointer;
  color: #1d4ed8; /* Bleu lien */
  font-family: inherit;
  font-size: 1rem;
}
.pc-cal-logement-btn:hover {
  text-decoration: underline;
}
</style>
