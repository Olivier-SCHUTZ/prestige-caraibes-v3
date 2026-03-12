<template>
  <div v-if="store.selection" class="pc-cal-selection-bar">
    <div class="selection-info">
      <span class="selection-icon">
        {{ store.selection.mode === "edit" ? "✏️" : "📅" }}
      </span>
      <div class="selection-details">
        <span class="dates-text">
          Du <strong>{{ formatDate(store.selection.start) }}</strong> au
          <strong>{{ formatDate(store.selection.end) }}</strong>
        </span>
        <input
          type="text"
          v-model="blockReason"
          class="reason-input"
          placeholder="Motif du blocage..."
          maxlength="50"
        />
      </div>
    </div>
    <div class="selection-actions">
      <button
        class="btn-cancel"
        @click="cancelSelection"
        :disabled="store.isCreatingBlock"
      >
        Annuler
      </button>

      <template v-if="store.selection.mode === 'create'">
        <button
          class="btn-create-resa"
          @click="goToCreateReservation"
          :disabled="!store.selection.start || store.isCreatingBlock"
        >
          + Nouvelle réservation
        </button>

        <button
          class="btn-submit"
          @click="submitBlock"
          :disabled="!store.selection.start || store.isCreatingBlock"
        >
          {{ store.isCreatingBlock ? "⏳..." : "Bloquer ces dates" }}
        </button>
      </template>

      <template v-else>
        <button
          class="btn-delete"
          @click="deleteBlock"
          :disabled="store.isCreatingBlock"
        >
          🗑️ Supprimer
        </button>
        <button
          class="btn-submit"
          @click="updateBlock"
          :disabled="store.isCreatingBlock"
        >
          {{ store.isCreatingBlock ? "⏳..." : "Enregistrer" }}
        </button>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from "vue";
import { useCalendarStore } from "../../stores/calendar-store";

const store = useCalendarStore();
const blockReason = ref("");

// Synchronise le champ texte quand on clique sur un événement existant
watch(
  () => store.selection,
  (newVal) => {
    if (newVal) {
      blockReason.value = newVal.reason || "";
    }
  },
  { immediate: true },
);

const formatDate = (dateStr) => {
  if (!dateStr) return "";
  const [y, m, d] = dateStr.split("-");
  return `${d}/${m}/${y}`;
};

const cancelSelection = () => {
  blockReason.value = "";
  store.clearSelection();
};

const submitBlock = async () => {
  const motifFinal = blockReason.value.trim() || "Blocage Manuel";
  const result = await store.createManualBlock({
    logementId: store.selection.logementId,
    start: store.selection.start,
    end: store.selection.end,
    reason: motifFinal,
  });
  if (!result.success) alert("❌ Erreur : " + result.message);
};

const goToCreateReservation = () => {
  const { logementId, start, end } = store.selection;

  // 1. On récupère la base de ton site (ex: https://prestige-caraibes.local)
  const siteUrl = window.location.origin;

  // 2. On construit le bon lien vers l'Espace Propriétaire
  // ⚠️ Remplace "#reservations" par le vrai mot utilisé pour ton onglet réservation si c'est différent
  const url = `${siteUrl}/espace-proprietaire/?auto_create=1&logement_id=${logementId}&start=${start}&end=${end}#dashboard`;

  // 3. Redirection !
  window.location.href = url;
};

const updateBlock = async () => {
  const motifFinal = blockReason.value.trim() || "Blocage Manuel";
  const result = await store.updateManualBlock(
    store.selection.blockId,
    motifFinal,
  );
  if (!result.success) alert("❌ Erreur : " + result.message);
};

const deleteBlock = async () => {
  if (!confirm("Supprimer définitivement ce blocage ?")) return;
  const result = await store.deleteManualBlock(store.selection.blockId);
  if (!result.success) alert("❌ Erreur : " + result.message);
  else store.clearSelection(); // Ferme la barre
};
</script>

<style scoped>
.pc-cal-selection-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #1e293b;
  color: white;
  padding: 12px 20px;
  border-radius: 8px;
  margin-top: 15px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  animation: slideUp 0.3s ease-out;
}

.selection-info {
  display: flex;
  align-items: center;
  gap: 15px;
}
.selection-icon {
  font-size: 1.5rem;
}
.selection-details {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.dates-text {
  font-size: 0.95rem;
}

.reason-input {
  background: #334155;
  border: 1px solid #475569;
  color: white;
  padding: 6px 10px;
  border-radius: 4px;
  font-size: 0.85rem;
  width: 250px;
  outline: none;
  transition: border-color 0.2s;
}
.reason-input:focus {
  border-color: #3b82f6;
}

.selection-actions {
  display: flex;
  gap: 10px;
  align-items: center;
}

.btn-cancel {
  background: transparent;
  color: #cbd5e1;
  border: 1px solid #64748b;
  padding: 8px 16px;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-cancel:hover:not(:disabled) {
  background: #334155;
  color: white;
}

.btn-delete {
  background: transparent;
  color: #fca5a5;
  border: 1px solid #ef4444;
  padding: 8px 16px;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-delete:hover:not(:disabled) {
  background: #ef4444;
  color: white;
}

.btn-submit {
  background: #3b82f6;
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: 6px;
  font-weight: bold;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-submit:hover:not(:disabled) {
  background: #2563eb;
}
.btn-submit:disabled,
.btn-cancel:disabled,
.btn-delete:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.btn-create-resa {
  background: #16a34a; /* Vert */
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: 6px;
  font-weight: bold;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-create-resa:hover:not(:disabled) {
  background: #15803d;
}
.btn-create-resa:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
