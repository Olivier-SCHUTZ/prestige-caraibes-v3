<template>
  <div class="pc-rates-sidebar" ref="sidebarRef">
    <div class="pc-sidebar-header">
      <h4>Tarifs & Promos</h4>
      <div class="pc-sidebar-actions">
        <button
          class="pc-btn-add pc-btn-season"
          @click.stop.prevent="$emit('create', 'season')"
        >
          + Saison
        </button>
        <button
          class="pc-btn-add pc-btn-promo"
          @click.stop.prevent="$emit('create', 'promo')"
        >
          + Promo
        </button>
      </div>
    </div>

    <div class="pc-draggable-list">
      <div
        v-for="season in store.formData.rates.seasons"
        :key="season.id"
        class="pc-draggable-event"
        :data-id="season.id"
        data-type="season"
        :style="{ backgroundColor: season.color }"
      >
        <span class="pc-evt-label">{{ season.name }}</span>
        <span class="pc-price-tag">{{ season.price }}€</span>
        <span
          class="pc-edit-icon"
          @click.stop.prevent="$emit('edit', 'season', season)"
          title="Modifier"
          >✏️</span
        >
      </div>

      <div
        v-for="promo in store.formData.rates.promos"
        :key="promo.id"
        class="pc-draggable-event"
        :data-id="promo.id"
        data-type="promo"
        :style="{ backgroundColor: promo.color }"
      >
        <span class="pc-evt-label">{{ promo.name }}</span>
        <span class="pc-price-tag">
          -{{
            promo.promo_type === "percent"
              ? promo.value + "%"
              : promo.value + "€"
          }}
        </span>
        <span
          class="pc-edit-icon"
          @click.stop.prevent="$emit('edit', 'promo', promo)"
          title="Modifier"
          >✏️</span
        >
      </div>

      <div
        v-if="
          store.formData.rates.seasons.length === 0 &&
          store.formData.rates.promos.length === 0
        "
        class="pc-empty-state"
      >
        Créez une saison ou une promotion pour commencer.
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from "vue";
import { useHousingModalStore } from "@/stores/housing-modal-store"; // Adaptez le chemin '@' si nécessaire
import { Draggable } from "@fullcalendar/interaction";

const store = useHousingModalStore();
const sidebarRef = ref(null);
let draggableInstance = null;

defineEmits(["create", "edit"]);

onMounted(() => {
  // Initialisation du Drag & Drop natif de FullCalendar
  if (sidebarRef.value) {
    draggableInstance = new Draggable(sidebarRef.value, {
      itemSelector: ".pc-draggable-event",
      eventData: (eventEl) => {
        const id = eventEl.getAttribute("data-id");
        const type = eventEl.getAttribute("data-type");

        const list =
          type === "season"
            ? store.formData.rates.seasons
            : store.formData.rates.promos;
        const item = list.find((i) => i.id === id);

        if (!item) return false;

        return {
          title: item.name,
          backgroundColor: item.color,
          borderColor: item.color,
          extendedProps: {
            type: type,
            entityId: id,
          },
        };
      },
    });
  }
});

onBeforeUnmount(() => {
  if (draggableInstance) {
    draggableInstance.destroy();
  }
});
</script>

<style scoped>
.pc-rates-sidebar {
  width: 280px;
  background: rgba(255, 255, 255, 0.5);
  border: 1px solid rgba(255, 255, 255, 0.3);
  border-radius: 16px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  backdrop-filter: blur(10px);
  min-height: 600px; /* Aligné sur la hauteur du calendrier */
}

.pc-sidebar-header h4 {
  margin: 0 0 15px 0;
  color: #1e293b;
  font-size: 1.1rem;
}

.pc-sidebar-actions {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
}

.pc-btn-add {
  flex: 1;
  padding: 8px 12px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.85rem;
  border: none;
  cursor: pointer;
  color: white;
  transition:
    transform 0.2s,
    box-shadow 0.2s;
}

.pc-btn-season {
  background: #3b82f6;
  box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
}
.pc-btn-season:hover {
  background: #2563eb;
  transform: translateY(-1px);
}

.pc-btn-promo {
  background: #ef4444;
  box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
}
.pc-btn-promo:hover {
  background: #dc2626;
  transform: translateY(-1px);
}

.pc-draggable-list {
  flex: 1;
  overflow-y: auto;
  padding-right: 5px;
}

.pc-draggable-event {
  padding: 12px 16px;
  margin-bottom: 12px;
  color: white;
  border-radius: 12px;
  cursor: grab;
  font-weight: 600;
  font-size: 13px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  transition:
    transform 0.2s,
    box-shadow 0.2s;
  border: 1px solid rgba(255, 255, 255, 0.2);
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.pc-draggable-event:active {
  cursor: grabbing;
}

.pc-draggable-event:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
}

.pc-evt-label {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-right: 8px;
}

.pc-price-tag {
  background: rgba(255, 255, 255, 0.2);
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 11px;
}

.pc-edit-icon {
  margin-left: 8px;
  cursor: pointer;
  font-size: 14px;
  opacity: 0.8;
  transition: opacity 0.2s;
}

.pc-edit-icon:hover {
  opacity: 1;
}

.pc-empty-state {
  text-align: center;
  color: #64748b;
  font-size: 0.875rem;
  font-style: italic;
  margin-top: 20px;
}
</style>
