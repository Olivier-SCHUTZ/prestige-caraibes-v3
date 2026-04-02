<template>
  <div class="pc-rates-calendar-wrapper">
    <FullCalendar :key="componentKey" :options="calendarOptions" />
  </div>
</template>

<script setup>
import { computed, ref, watch, onMounted } from "vue";
import { useHousingModalStore } from "../../stores/housing-modal-store.js"; // Chemin corrigé
import FullCalendar from "@fullcalendar/vue3";
import multiMonthPlugin from "@fullcalendar/multimonth";
import interactionPlugin from "@fullcalendar/interaction";

const store = useHousingModalStore();

// Hack Vue 3 : Forcer le calendrier à se redimensionner quand l'onglet s'affiche
onMounted(() => {
  setTimeout(() => {
    window.dispatchEvent(new Event("resize"));
  }, 150);
});

// On crée un dictionnaire exact des jours (Map) au lieu d'un tableau d'événements
const calendarDataMap = computed(() => {
  const map = {};

  const getLocalString = (dateObj) => {
    const y = dateObj.getFullYear();
    const m = String(dateObj.getMonth() + 1).padStart(2, '0');
    const d = String(dateObj.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  };

  // 1. Saisons
  store.formData.rates.seasons.forEach((s) => {
    if (s.periods && Array.isArray(s.periods)) {
      s.periods.forEach((p, index) => {
        let current = new Date(p.start + 'T00:00:00');
        const end = new Date(p.end + 'T00:00:00');
        while (current <= end) {
          const dateStr = getLocalString(current);
          map[dateStr] = {
            type: "season",
            entityId: s.id,
            periodIndex: index,
            title: s.name,
            color: s.color,
            price: s.price,
            basePriceForPromo: s.price
          };
          current.setDate(current.getDate() + 1);
        }
      });
    }
  });

  // 2. Promos (Elles écrasent la saison en cours sur le dictionnaire)
  store.formData.rates.promos.forEach((p) => {
    if (p.periods && Array.isArray(p.periods) && p.periods.length > 0) {
      p.periods.forEach((period, index) => {
        // Sécurité vitale : ignorer les périodes vides créées par l'UI
        if (!period.start || !period.end) return;
        
        let current = new Date(period.start + 'T00:00:00');
        const end = new Date(period.end + 'T00:00:00');
        
        while (current <= end) {
          const dateStr = getLocalString(current);
          const basePrice = (map[dateStr] && map[dateStr].basePriceForPromo) 
                              ? map[dateStr].basePriceForPromo 
                              : store.basePrice;
          
          let calculatedPrice = basePrice;
          const promoValue = parseFloat(p.value) || 0;
          if (p.promo_type === "percent") {
            calculatedPrice = basePrice - (basePrice * (promoValue / 100));
          } else {
            calculatedPrice = basePrice - promoValue;
          }
          calculatedPrice = Math.max(0, calculatedPrice);

          map[dateStr] = {
            type: "promo",
            entityId: p.id,
            periodIndex: index,
            title: p.name || "Promo",
            color: p.color || "#ef4444",
            promoType: p.promo_type || "percent",
            value: p.value,
            calculatedPrice: calculatedPrice
          };
          current.setDate(current.getDate() + 1);
        }
      });
    }
  });

  return map;
});

// Fonction réutilisable pour dessiner/mettre à jour une case
const renderCell = (frame, dateStr) => {
  if (!frame) return;
  const existingData = frame.querySelector('.pc-injected-cell');
  if (existingData) existingData.remove();

  const dayData = calendarDataMap.value[dateStr];

  if (dayData) {
    const contentEl = document.createElement("div");
    contentEl.className = `pc-injected-cell ${dayData.type === 'season' ? 'pc-event-season' : 'pc-event-promo'}`;
    
    if (dayData.type === "season") {
      contentEl.style.backgroundColor = dayData.color;
      contentEl.title = `Saison : ${dayData.title}\nTarif : ${dayData.price}€ (Cliquez pour supprimer)`;
      
      // On remet le texte, mais ultra-compact
      contentEl.innerHTML = `
        <div class="pc-evt-title">${dayData.title}</div>
        <div class="pc-evt-price">${dayData.price}€</div>
      `;
    } else if (dayData.type === "promo") {
      contentEl.style.borderColor = dayData.color;
      const valDisplay = dayData.promoType === "percent" ? `-${dayData.value}%` : `-${dayData.value}€`;
      contentEl.title = `Promo : ${dayData.title}\nTarif final : ${dayData.calculatedPrice.toFixed(2)}€\nRéduction appliquée : ${valDisplay} (Cliquez pour supprimer)`;
      
      // Affichage compact pour que tout rentre
      contentEl.innerHTML = `
        <div class="pc-evt-title">${dayData.title}</div>
        <div class="pc-evt-price">${dayData.calculatedPrice.toFixed(2)}€</div>
        <div class="pc-evt-promo-badge">${valDisplay}</div>
      `;
    }
    frame.appendChild(contentEl);
  } else if (store.basePrice > 0) {
    const priceEl = document.createElement("div");
    priceEl.className = "pc-base-price pc-injected-cell";
    priceEl.textContent = store.basePrice + "€";
    priceEl.title = `Prix de base : ${store.basePrice}€`;
    frame.appendChild(priceEl);
  }
};

const componentKey = ref(0);

// Watcher : On écoute directement les tarifs du Store.
// Dès qu'une promo ou saison est ajoutée/supprimée, on incrémente la clé.
// Cela force FullCalendar à se reconstruire instantanément avec les bonnes priorités.
watch(
  () => store.formData.rates,
  () => {
    componentKey.value += 1;
  },
  { deep: true }
);

// Configuration de FullCalendar
const calendarOptions = computed(() => ({
  plugins: [multiMonthPlugin, interactionPlugin],
  initialView: "multiMonthYear",
  height: "auto", 
  multiMonthMaxColumns: 1, 
  locale: "fr",
  firstDay: 1,
  editable: false, 
  droppable: false,
  events: [], 

  dayCellDidMount: (arg) => {
    const frame = arg.el.querySelector(".fc-daygrid-day-frame");
    const cellDate = arg.date.toISOString().split("T")[0];
    renderCell(frame, cellDate);
  },

  dateClick: (info) => {
    const dayData = calendarDataMap.value[info.dateStr];
    if (dayData) {
      if (confirm("Supprimer cette période du calendrier ?")) {
        store.removeRatePeriod(dayData.type, dayData.entityId, dayData.periodIndex);
      }
    }
  }
}));
</script>

<style scoped>
.pc-rates-calendar-wrapper {
  flex: 1;
  background: #ffffff;
  border-radius: 16px;
  padding: 15px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
  overflow: visible; /* CRUCIAL : "hidden" bloquait totalement le fonctionnement de "position: sticky" */
  position: relative;
  min-height: 600px;
}

/* === FULLCALENDAR OVERRIDES VIA :deep() === */
:deep(.fc) {
  font-family: inherit;
  font-size: 13px;
}
/* === En-tête FullCalendar standard === */
:deep(.fc-header-toolbar) {
  position: relative;
  background: transparent;
  padding: 0 0 15px 0;
  border-bottom: none;
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
/* Suppression totale de l'affichage des événements natifs FullCalendar */
:deep(.fc-daygrid-day-events) {
  display: none !important;
}

/* Événements Saisons (Héritent du positionnement de .pc-injected-cell) */
:deep(.pc-event-season) {
  border: none !important;
  box-shadow: 0 10px 30px -18px rgba(0, 0, 0, 0.35) !important;
  color: #fff !important;
  z-index: 2;
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
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3) !important;
  color: #fff !important;
  z-index: 100;
}

/* === TYPOGRAPHIE COMPACTE POUR LES CASES === */
:deep(.pc-evt-title) {
  font-weight: 700;
  color: #fff;
  margin-bottom: 2px;
  font-size: 11px; /* Réduit pour rentrer dans la case */
  line-height: 1.1;
  display: -webkit-box;
  -webkit-line-clamp: 2; /* Coupe le texte après 2 lignes avec "..." */
  -webkit-box-orient: vertical;
  overflow: hidden;
  word-wrap: break-word;
  width: 100%;
  padding: 0 2px;
}
:deep(.pc-evt-price) {
  font-weight: 800;
  color: #fff;
  font-size: 12px;
  line-height: 1;
}
:deep(.pc-evt-promo-badge) {
  background: #fff;
  color: #ef4444;
  font-weight: 800;
  border-radius: 4px;
  padding: 2px 4px;
  display: inline-block;
  margin-top: 3px;
  font-size: 10px;
  line-height: 1;
}

/* === NOTRE NOUVEAU CONTENEUR INJECTÉ MANUELLEMENT === */
:deep(.pc-injected-cell) {
  position: absolute;
  top: 26px; /* Remonté pour gagner un peu de hauteur */
  bottom: 4px;
  left: 4px;
  right: 4px;
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  z-index: 5;
  box-sizing: border-box;
  padding: 2px;
  overflow: hidden;
  cursor: pointer; /* Indique qu'on peut cliquer pour supprimer */
  transition: transform 0.1s;
}

/* Petit effet au survol pour montrer que c'est cliquable */
:deep(.pc-injected-cell:hover) {
  transform: scale(0.98);
}

:deep(.pc-injected-cell.pc-base-price) {
  top: auto;
  bottom: 12px;
  right: 12px;
  left: auto;
  display: block;
  z-index: 1;
}

/* Optionnel : cacher définitivement le conteneur d'événements natif pour faire propre */
:deep(.fc-daygrid-day-events) {
  display: none !important;
}
</style>
