<template>
  <div class="pc-rates-calendar-wrapper">
    <FullCalendar :options="calendarOptions" />
  </div>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { useHousingModalStore } from "@/stores/housing-modal-store"; // Adaptez le chemin '@'
import FullCalendar from "@fullcalendar/vue3";
import multiMonthPlugin from "@fullcalendar/multimonth";
import interactionPlugin from "@fullcalendar/interaction";

const store = useHousingModalStore();

// Utilitaire pour ajouter des jours (FullCalendar a des dates de fin exclusives)
const addDays = (dateStr, days) => {
  let d = new Date(dateStr);
  d.setDate(d.getDate() + days);
  return d.toISOString().split("T")[0];
};

// Transformer les données du Store en événements FullCalendar
const calendarEvents = computed(() => {
  let events = [];

  // Saisons
  store.formData.rates.seasons.forEach((s) => {
    if (s.periods && Array.isArray(s.periods)) {
      s.periods.forEach((p, index) => {
        events.push({
          title: s.name,
          start: p.start,
          end: addDays(p.end, 1),
          backgroundColor: s.color,
          borderColor: s.color,
          classNames: ["pc-event-season"],
          // Utilisation de zIndex via les extendedProps pour le tri ou CSS
          extendedProps: {
            type: "season",
            entityId: s.id,
            periodIndex: index,
            price: s.price,
            zIndex: 10,
          },
        });
      });
    }
  });

  // Promos
  store.formData.rates.promos.forEach((p) => {
    if (p.periods && Array.isArray(p.periods)) {
      p.periods.forEach((period, index) => {
        events.push({
          title: p.name,
          start: period.start,
          end: addDays(period.end, 1),
          backgroundColor: "transparent",
          borderColor: p.color,
          classNames: ["pc-event-promo"],
          extendedProps: {
            type: "promo",
            entityId: p.id,
            periodIndex: index,
            value: p.value,
            promoType: p.promo_type || "percent",
            zIndex: 50,
          },
        });
      });
    }
  });

  return events;
});

// Configuration de FullCalendar
const calendarOptions = computed(() => ({
  plugins: [multiMonthPlugin, interactionPlugin],
  initialView: "multiMonthYear",
  multiMonthMaxColumns: 1, // Vue verticale type Airbnb
  locale: "fr",
  firstDay: 1,
  editable: true,
  droppable: true,
  eventOverlap: true,
  eventOrder: "extendedProps.zIndex",
  events: calendarEvents.value,

  // Personnalisation du contenu HTML des événements
  eventContent: (arg) => {
    const props = arg.event.extendedProps;
    let html = `<div class="pc-evt-title">${arg.event.title}</div>`;

    if (props.type === "season") {
      html += `<div class="pc-evt-price">${props.price}€</div>`;
    } else if (props.type === "promo") {
      const valDisplay =
        props.promoType === "percent" ? `-${props.value}%` : `-${props.value}€`;
      html += `<div class="pc-evt-promo-badge">${valDisplay}</div>`;
    }

    return { html: `<div class="pc-event-body">${html}</div>` };
  },

  // Injection du prix de base sur les jours vides
  dayCellDidMount: (arg) => {
    const frame = arg.el.querySelector(".fc-daygrid-day-frame");
    if (frame && store.basePrice > 0) {
      const cellDate = arg.date.toISOString().split("T")[0];
      const hasSeasonEvent = store.formData.rates.seasons.some((season) => {
        return (
          season.periods &&
          season.periods.some((period) => {
            return cellDate >= period.start && cellDate <= period.end;
          })
        );
      });

      if (!hasSeasonEvent) {
        const priceEl = document.createElement("div");
        priceEl.className = "pc-base-price";
        priceEl.textContent = store.basePrice + "€";
        frame.appendChild(priceEl);
      }
    }
  },

  // Événements d'interaction
  eventReceive: (info) => {
    const type = info.event.extendedProps.type;
    const entityId = info.event.extendedProps.entityId;
    const start = info.event.startStr;

    let end = info.event.endStr;
    if (!end) {
      end = start;
    } else {
      // Ajustement exclusif -> inclusif
      let d = new Date(end);
      d.setDate(d.getDate() - 1);
      end = d.toISOString().split("T")[0];
    }

    store.addRatePeriod(type, entityId, start, end);
    info.event.remove(); // Le store gère la réactivité, on supprime le ghost
  },

  eventDrop: (info) => handleEventChange(info),
  eventResize: (info) => handleEventChange(info),

  eventClick: (info) => {
    if (confirm("Supprimer cette période du calendrier ?")) {
      store.removeRatePeriod(
        info.event.extendedProps.type,
        info.event.extendedProps.entityId,
        info.event.extendedProps.periodIndex,
      );
    }
  },
}));

const handleEventChange = (info) => {
  const props = info.event.extendedProps;
  const start = info.event.startStr;

  let end = info.event.endStr;
  if (!end) {
    end = start;
  } else {
    let d = new Date(end);
    d.setDate(d.getDate() - 1);
    end = d.toISOString().split("T")[0];
  }

  store.updateRatePeriod(
    props.type,
    props.entityId,
    props.periodIndex,
    start,
    end,
  );
};
</script>

<style scoped>
.pc-rates-calendar-wrapper {
  flex: 1;
  background: #ffffff;
  border-radius: 16px;
  padding: 15px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
  overflow: hidden;
  position: relative;
  min-height: 600px;
}

/* === FULLCALENDAR OVERRIDES VIA :deep() === */
:deep(.fc) {
  font-family: inherit;
  font-size: 13px;
}
:deep(.fc-toolbar-title) {
  font-size: 18px;
  font-weight: 700;
  color: #1e293b;
}
:deep(.fc-button-primary) {
  background: #f1f5f9;
  border: none;
  color: #475569;
  font-weight: 600;
  text-transform: capitalize;
  padding: 8px 16px;
  border-radius: 8px;
}
:deep(.fc-button-primary:hover),
:deep(.fc-button-primary.fc-button-active) {
  background: #0f172a;
  color: white;
}
:deep(.fc-daygrid-day-frame) {
  position: relative;
  min-height: 110px;
  padding: 0;
  background: #fff;
  border: 1px solid #e1e5ee;
  border-radius: 12px;
  overflow: visible;
  margin: 2px;
  transition: background 0.2s;
}
:deep(.fc-daygrid-day:hover .fc-daygrid-day-frame) {
  background: #f8fafc;
}
:deep(.pc-base-price) {
  position: absolute;
  bottom: 12px;
  right: 12px;
  font-size: 12px;
  font-weight: 600;
  color: #9aa5b1;
  z-index: 1;
}
:deep(.fc-daygrid-day-number) {
  position: absolute !important;
  top: 4px;
  right: 6px;
  font-size: 14px !important;
  font-weight: 700 !important;
  color: #0a0a0a !important;
  text-shadow: 0 1px 4px rgba(255, 255, 255, 0.85);
  z-index: 10 !important;
  pointer-events: none;
}
:deep(.fc-daygrid-day-events) {
  position: absolute;
  top: 32px;
  bottom: 10px;
  left: 6px;
  right: 6px;
  margin: 0;
  padding: 0;
}
:deep(.fc-daygrid-event-harness) {
  margin: 0 !important;
}
:deep(.fc-daygrid-event) {
  margin: 0 !important;
  width: 100% !important;
  position: relative;
}

/* Événements Saisons */
:deep(.pc-event-season) {
  border: none !important;
  border-radius: 12px !important;
  padding: 12px 14px !important;
  min-height: 64px !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  text-align: center !important;
  box-shadow: 0 10px 30px -18px rgba(0, 0, 0, 0.35) !important;
  color: #fff !important;
  z-index: 2 !important;
}

/* Événements Promos - Fond hachuré */
:deep(.pc-event-promo) {
  background: repeating-linear-gradient(
    45deg,
    rgba(239, 68, 68, 0.85),
    rgba(239, 68, 68, 0.85) 10px,
    rgba(239, 68, 68, 0.6) 10px,
    rgba(239, 68, 68, 0.6) 20px
  ) !important;
  border: 2px solid #b91c1c !important;
  border-radius: 12px !important;
  padding: 12px 14px !important;
  min-height: 64px !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  text-align: center !important;
  color: #fff !important;
  z-index: 100 !important;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3) !important;
}

/* Contenu injecté via eventContent */
:deep(.pc-event-body) {
  line-height: 1.3;
  font-size: 14px;
  font-weight: 700;
}
:deep(.pc-evt-title) {
  font-weight: 700;
  color: #fff;
  margin-bottom: 4px;
}
:deep(.pc-evt-price) {
  font-weight: 700;
  color: #fff;
  font-size: 13px;
}
:deep(.pc-evt-promo-badge) {
  background: #fff;
  color: #ef4444;
  font-weight: 800;
  border-radius: 4px;
  padding: 2px 6px;
  display: inline-block;
  margin-top: 4px;
  font-size: 0.9em;
}
</style>
