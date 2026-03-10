<template>
  <Teleport to="body">
    <div
      v-if="modelValue"
      class="pc-rate-modal-overlay"
      @click.stop.prevent="closeModal"
    >
      <div class="pc-rate-modal-content" @click.stop.prevent>
        <div class="pc-rate-modal-header">
          <h3>
            {{ isEditing ? "Modifier" : "Ajouter" }}
            {{ mode === "season" ? "une Saison" : "une Promotion" }}
          </h3>
          <button class="pc-close-btn" @click.stop.prevent="closeModal">
            &times;
          </button>
        </div>

        <div class="pc-rate-modal-body">
          <div class="pc-form-group">
            <label>Nom</label>
            <input
              type="text"
              v-model="formData.name"
              placeholder="Ex: Haute Saison / Promo Été"
            />
          </div>

          <div v-if="mode === 'season'" class="pc-rate-form-grid">
            <div class="pc-form-group">
              <label>Prix par nuit (€)</label>
              <input
                type="number"
                step="0.01"
                v-model.number="formData.price"
              />
            </div>
            <div class="pc-form-group">
              <label>Nuits minimum</label>
              <input type="number" v-model.number="formData.minNights" />
            </div>
            <div class="pc-form-group">
              <label>Frais par invité sup. (€)</label>
              <input
                type="number"
                step="0.01"
                v-model.number="formData.guestFee"
              />
            </div>
            <div class="pc-form-group">
              <label>À partir de X invités</label>
              <input type="number" v-model.number="formData.guestFrom" />
            </div>
            <div class="pc-form-group pc-rate-full-width">
              <label>Note interne (optionnel)</label>
              <textarea v-model="formData.note" rows="2"></textarea>
            </div>
          </div>

          <div v-if="mode === 'promo'" class="pc-rate-form-grid">
            <div class="pc-form-group">
              <label>Type de promotion</label>
              <select v-model="formData.promo_type">
                <option value="percent">Pourcentage (%)</option>
                <option value="fixed">Montant fixe (€)</option>
              </select>
            </div>
            <div class="pc-form-group">
              <label>Valeur de la promotion</label>
              <input
                type="number"
                step="0.01"
                v-model.number="formData.value"
              />
            </div>
          </div>

          <div class="pc-rate-periods-section">
            <h4>Périodes d'application</h4>

            <div class="pc-add-period-box">
              <div class="pc-date-inputs">
                <div class="pc-form-group">
                  <label>Début</label>
                  <input type="date" v-model="newPeriod.start" />
                </div>
                <div class="pc-form-group">
                  <label>Fin</label>
                  <input
                    type="date"
                    v-model="newPeriod.end"
                    :min="newPeriod.start"
                  />
                </div>
              </div>
              <button
                class="pc-btn pc-btn-secondary"
                @click.stop.prevent="addPeriod"
              >
                + Ajouter la période
              </button>
            </div>

            <div
              v-if="feedbackMsg"
              :class="[
                'pc-period-feedback',
                `pc-feedback-${feedbackType}`,
                'show',
              ]"
            >
              {{ feedbackMsg }}
            </div>

            <ul class="pc-rate-periods-list">
              <li v-if="tempPeriods.length === 0" class="pc-empty-periods">
                Aucune période définie
              </li>
              <li
                v-for="(period, index) in tempPeriods"
                :key="index"
                :style="{
                  borderLeftColor: mode === 'season' ? '#3b82f6' : '#ef4444',
                }"
              >
                <span>
                  📅 <strong>{{ formatDateFR(period.start) }}</strong> au
                  <strong>{{ formatDateFR(period.end) }}</strong>
                </span>
                <button
                  class="pc-remove-period-btn"
                  @click.stop.prevent="removePeriod(index)"
                  title="Supprimer"
                >
                  &times;
                </button>
              </li>
            </ul>
          </div>
        </div>

        <div class="pc-rate-modal-footer">
          <button
            v-if="isEditing"
            class="pc-btn pc-btn-danger pc-pull-left"
            @click.stop.prevent="handleDelete"
          >
            Supprimer
          </button>
          <div>
            <button
              class="pc-btn pc-btn-secondary"
              @click.stop.prevent="closeModal"
            >
              Annuler
            </button>
            <button
              class="pc-btn pc-btn-primary"
              @click.stop.prevent="handleSave"
            >
              Enregistrer
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, watch, computed } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  mode: { type: String, default: "season" }, // 'season' ou 'promo'
  editItem: { type: Object, default: null }, // L'item à éditer, ou null si création
});

const emit = defineEmits(["update:modelValue", "save", "delete"]);

// États du formulaire
const isEditing = computed(() => !!props.editItem);
const formData = ref({});
const tempPeriods = ref([]);

// État pour la nouvelle période
const newPeriod = ref({ start: "", end: "" });

// États pour les feedbacks
const feedbackMsg = ref("");
const feedbackType = ref("info");

// Initialisation au montage/changement de la prop
watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen) {
      if (props.editItem) {
        // Deep copy pour ne pas affecter le store avant de sauvegarder
        formData.value = JSON.parse(JSON.stringify(props.editItem));
        tempPeriods.value = JSON.parse(
          JSON.stringify(props.editItem.periods || []),
        );
      } else {
        // Reset
        formData.value = {
          name: "",
          price: 0,
          minNights: 1,
          guestFee: 0,
          guestFrom: 1,
          note: "",
          promo_type: "percent",
          value: 0,
        };
        tempPeriods.value = [];
      }
      newPeriod.value = { start: "", end: "" };
      feedbackMsg.value = "";
    }
  },
);

const closeModal = () => {
  emit("update:modelValue", false);
};

const showFeedback = (msg, type = "info") => {
  feedbackMsg.value = msg;
  feedbackType.value = type;
  setTimeout(() => {
    feedbackMsg.value = "";
  }, 3000);
};

// Gestion des périodes
const addPeriod = () => {
  if (!newPeriod.value.start || !newPeriod.value.end) {
    showFeedback("Veuillez remplir les deux dates", "error");
    return;
  }
  if (newPeriod.value.start > newPeriod.value.end) {
    showFeedback(
      "La date de début doit être antérieure à la date de fin",
      "error",
    );
    return;
  }

  // Vérifier les chevauchements
  const hasConflict = tempPeriods.value.some((p) => {
    return newPeriod.value.start <= p.end && newPeriod.value.end >= p.start;
  });

  if (
    hasConflict &&
    !confirm("Cette période chevauche une période existante. Continuer ?")
  ) {
    return;
  }

  tempPeriods.value.push({ ...newPeriod.value });
  showFeedback("Période ajoutée", "success");
  newPeriod.value = { start: "", end: "" };
};

const removePeriod = (index) => {
  if (confirm("Supprimer cette période ?")) {
    tempPeriods.value.splice(index, 1);
  }
};

const formatDateFR = (dateStr) => {
  if (!dateStr) return "";
  const [y, m, d] = dateStr.split("-");
  return `${d}/${m}/${y}`;
};

// Actions finales
const handleSave = () => {
  if (!formData.value.name) {
    showFeedback("Le nom est requis", "error");
    return;
  }

  const payload = {
    ...formData.value,
    periods: tempPeriods.value,
  };

  emit("save", { type: props.mode, data: payload });
  closeModal();
};

const handleDelete = () => {
  if (
    confirm(
      `Voulez-vous vraiment supprimer cette ${props.mode === "season" ? "saison" : "promotion"} ?`,
    )
  ) {
    emit("delete", { type: props.mode, id: formData.value.id });
    closeModal();
  }
};
</script>

<style scoped>
/* Isolation de la modale avec z-index 150000 (standard V2) */
.pc-rate-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(15, 23, 42, 0.75);
  backdrop-filter: blur(4px);
  z-index: 150000;
  display: flex;
  align-items: center;
  justify-content: center;
}

.pc-rate-modal-content {
  background: #ffffff;
  border-radius: 16px;
  width: 100%;
  max-width: 700px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  padding: 30px;
}

.pc-rate-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 15px;
}

.pc-rate-modal-header h3 {
  margin: 0;
  font-size: 1.25rem;
  color: #1e293b;
}

.pc-close-btn {
  background: none;
  border: none;
  font-size: 1.5rem;
  color: #64748b;
  cursor: pointer;
}

.pc-form-group {
  margin-bottom: 15px;
  display: flex;
  flex-direction: column;
}

.pc-form-group label {
  font-size: 0.875rem;
  font-weight: 600;
  color: #475569;
  margin-bottom: 6px;
}

.pc-form-group input,
.pc-form-group select,
.pc-form-group textarea {
  padding: 10px 14px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 0.875rem;
  transition: border-color 0.2s;
}

.pc-form-group input:focus,
.pc-form-group select:focus,
.pc-form-group textarea:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.pc-rate-form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 15px;
}

.pc-rate-full-width {
  grid-column: span 2;
}

.pc-rate-periods-section {
  margin-top: 25px;
  background: #f8fafc;
  padding: 20px;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
}

.pc-rate-periods-section h4 {
  margin: 0 0 15px 0;
  font-size: 1rem;
  color: #1e293b;
}

.pc-add-period-box {
  display: flex;
  align-items: flex-end;
  gap: 15px;
  margin-bottom: 15px;
}

.pc-date-inputs {
  display: flex;
  gap: 15px;
  flex: 1;
}

.pc-rate-periods-list {
  list-style: none;
  padding: 0;
  margin: 0;
  max-height: 200px;
  overflow-y: auto;
}

.pc-empty-periods {
  color: #64748b;
  font-style: italic;
  text-align: center;
  padding: 15px;
  background: white;
  border-radius: 8px;
  border: 1px dashed #cbd5e1;
}

.pc-rate-periods-list li {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 14px;
  margin-bottom: 8px;
  background: #ffffff;
  border-radius: 8px;
  border-left: 4px solid #3b82f6;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.pc-remove-period-btn {
  background: #ef4444;
  color: white;
  border: none;
  border-radius: 4px;
  width: 24px;
  height: 24px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.pc-rate-modal-footer {
  margin-top: 25px;
  display: flex;
  justify-content: space-between;
  border-top: 1px solid #e2e8f0;
  padding-top: 20px;
}

.pc-btn {
  padding: 10px 20px;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  margin-left: 10px;
}

.pc-btn-primary {
  background: #3b82f6;
  color: white;
}
.pc-btn-secondary {
  background: #e2e8f0;
  color: #475569;
}
.pc-btn-danger {
  background: #ef4444;
  color: white;
  margin-left: 0;
}
.pc-pull-left {
  margin-right: auto;
}

.pc-period-feedback {
  margin-bottom: 15px;
  padding: 10px 14px;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 600;
}
.pc-feedback-success {
  background: #dcfce7;
  color: #166534;
}
.pc-feedback-error {
  background: #fef2f2;
  color: #dc2626;
}
</style>
