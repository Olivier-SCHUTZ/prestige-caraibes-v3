<template>
  <div class="v2-tab-content">
    <h4 class="pc-section-title">Tarification de base</h4>
    <div class="pc-form-grid">
      <div class="pc-form-group">
        <label>Prix de base (Dès) € <span class="text-red-500">*</span></label>
        <input
          type="number"
          v-model="modalStore.formData.base_price_from"
          class="pc-input"
          min="0"
          step="1"
        />
      </div>

      <div class="pc-form-group">
        <label>Unité de prix</label>
        <select v-model="modalStore.formData.unite_de_prix" class="pc-select">
          <option value="par nuit">Par nuit</option>
          <option value="par semaine">Par semaine</option>
          <option value="par mois">Par mois</option>
        </select>
      </div>

      <div class="pc-form-group">
        <label>Nuits minimum</label>
        <input
          type="number"
          v-model="modalStore.formData.min_nights"
          class="pc-input"
          min="1"
        />
      </div>

      <div class="pc-form-group">
        <label>Nuits maximum</label>
        <input
          type="number"
          v-model="modalStore.formData.max_nights"
          class="pc-input"
          min="1"
        />
      </div>

      <div class="pc-form-group pc-form-group--full">
        <label class="pc-checkbox-label">
          <input type="checkbox" v-model="modalStore.formData.pc_promo_log" />
          <span>Activer le badge "Promo en cours" sur ce logement</span>
        </label>
      </div>
    </div>

    <h4 class="pc-section-title">Frais supplémentaires</h4>
    <div class="pc-form-grid">
      <div class="pc-form-group">
        <label>Frais ménage (€)</label>
        <input
          type="number"
          v-model="modalStore.formData.frais_menage"
          class="pc-input"
          min="0"
          step="1"
        />
      </div>

      <div class="pc-form-group">
        <label>TVA sur ménage (%)</label>
        <input
          type="number"
          v-model="modalStore.formData.taux_tva_menage"
          class="pc-input"
          min="0"
          step="0.1"
        />
      </div>

      <div class="pc-form-group">
        <label>Frais pers. suppl. (€)</label>
        <input
          type="number"
          v-model="modalStore.formData.extra_guest_fee"
          class="pc-input"
          min="0"
          step="1"
        />
      </div>

      <div class="pc-form-group">
        <label>À partir de (x pers.)</label>
        <input
          type="number"
          v-model="modalStore.formData.extra_guest_from"
          class="pc-input"
          min="1"
        />
      </div>

      <div class="pc-form-group">
        <label>Autres frais (€)</label>
        <input
          type="number"
          v-model="modalStore.formData.autres_frais"
          class="pc-input"
          min="0"
          step="1"
        />
      </div>

      <div class="pc-form-group">
        <label>Type autres frais</label>
        <input
          type="text"
          v-model="modalStore.formData.autres_frais_type"
          class="pc-input"
          placeholder="Ex: Frais de dossier"
        />
      </div>
    </div>

    <h4 class="pc-section-title">Taxes & Mode de réservation</h4>
    <div class="pc-form-grid">
      <div class="pc-form-group">
        <label>Taux TVA général (%)</label>
        <input
          type="number"
          v-model="modalStore.formData.taux_tva"
          class="pc-input"
          min="0"
          step="0.1"
        />
      </div>

      <div class="pc-form-group">
        <label>Mode de réservation</label>
        <select
          v-model="modalStore.formData.mode_reservation"
          class="pc-select"
        >
          <option value="log_directe">Réservation Directe (Immédiate)</option>
          <option value="log_demande">Sur Demande (Devis)</option>
        </select>
      </div>

      <div class="pc-form-group pc-form-group--full">
        <label>Catégorie Taxe de séjour</label>
        <div class="pc-checkbox-group">
          <label class="pc-checkbox-label">
            <input
              type="checkbox"
              value="non_classe"
              v-model="modalStore.formData.taxe_sejour"
            />
            Non classé
          </label>
          <label class="pc-checkbox-label">
            <input
              type="checkbox"
              value="1_etoile"
              v-model="modalStore.formData.taxe_sejour"
            />
            1 étoile
          </label>
          <label class="pc-checkbox-label">
            <input
              type="checkbox"
              value="2_etoiles"
              v-model="modalStore.formData.taxe_sejour"
            />
            2 étoiles
          </label>
          <label class="pc-checkbox-label">
            <input
              type="checkbox"
              value="3_etoiles"
              v-model="modalStore.formData.taxe_sejour"
            />
            3 étoiles
          </label>
          <label class="pc-checkbox-label">
            <input
              type="checkbox"
              value="4_etoiles"
              v-model="modalStore.formData.taxe_sejour"
            />
            4 étoiles
          </label>
          <label class="pc-checkbox-label">
            <input
              type="checkbox"
              value="5_etoiles"
              v-model="modalStore.formData.taxe_sejour"
            />
            5 étoiles
          </label>
        </div>
      </div>
    </div>

    <h4 class="pc-section-title">Règles de paiement (Nouveau Système)</h4>
    <div class="pc-form-grid">
      <div class="pc-form-group">
        <label>Mode de paiement</label>
        <select
          v-model="modalStore.formData.payment_rules.mode_pay"
          class="pc-select"
        >
          <option value="acompte_plus_solde">Acompte + Solde</option>
          <option value="totalite">Totalité à la réservation</option>
        </select>
      </div>

      <div
        class="pc-form-group"
        v-if="
          modalStore.formData.payment_rules.mode_pay === 'acompte_plus_solde'
        "
      >
        <label>Délai paiement solde (jours avant arrivée)</label>
        <input
          type="number"
          v-model="modalStore.formData.payment_rules.delay_days"
          class="pc-input"
          min="0"
        />
      </div>

      <div
        class="pc-form-group"
        v-if="
          modalStore.formData.payment_rules.mode_pay === 'acompte_plus_solde'
        "
      >
        <label>Type d'acompte</label>
        <select
          v-model="modalStore.formData.payment_rules.deposit_type"
          class="pc-select"
        >
          <option value="pourcentage">Pourcentage (%)</option>
          <option value="montant_fixe">Montant fixe (€)</option>
        </select>
      </div>

      <div
        class="pc-form-group"
        v-if="
          modalStore.formData.payment_rules.mode_pay === 'acompte_plus_solde'
        "
      >
        <label>Valeur acompte</label>
        <input
          type="number"
          v-model="modalStore.formData.payment_rules.deposit_value"
          class="pc-input"
          min="0"
        />
      </div>

      <div class="pc-form-group">
        <label>Type de caution</label>
        <select
          v-model="modalStore.formData.payment_rules.caution_type"
          class="pc-select"
        >
          <option value="aucune">Aucune</option>
          <option value="empreinte">Empreinte bancaire (Stripe)</option>
          <option value="virement">Virement</option>
          <option value="cheque">Chèque</option>
          <option value="especes">Espèces</option>
        </select>
      </div>

      <div
        class="pc-form-group"
        v-if="modalStore.formData.payment_rules.caution_type !== 'aucune'"
      >
        <label>Montant de la caution (€)</label>
        <input
          type="number"
          v-model="modalStore.formData.payment_rules.caution_amount"
          class="pc-input"
          min="0"
          step="1"
        />
      </div>
    </div>

    <h4 class="pc-section-title">Calendrier des prix dynamiques</h4>

    <div class="pc-rates-container">
      <RateSidebar @create="openRateModal" @edit="openRateModal" />
      <RateCalendarArea />
    </div>

    <RateEditModal
      v-model="isRateModalOpen"
      :mode="rateModalMode"
      :editItem="rateEditItem"
      @save="handleRateSave"
      @delete="handleRateDelete"
    />
  </div>
</template>

<script setup>
import { ref } from "vue";
import { useHousingModalStore } from "../../stores/housing-modal-store.js";
import RateSidebar from "./RateSidebar.vue";
import RateCalendarArea from "./RateCalendarArea.vue";
import RateEditModal from "./RateEditModal.vue";

const modalStore = useHousingModalStore();

// Gestion de la modale d'édition des tarifs
const isRateModalOpen = ref(false);
const rateModalMode = ref("season"); // 'season' ou 'promo'
const rateEditItem = ref(null);

const openRateModal = (mode, item = null) => {
  rateModalMode.value = mode;
  rateEditItem.value = item; // null si création, objet si édition
  isRateModalOpen.value = true;
};

const handleRateSave = ({ type, data }) => {
  // Correction ici : on utilise modalStore au lieu de store
  modalStore.saveRateItem(type, data);
};

const handleRateDelete = ({ type, id }) => {
  // Correction ici : on utilise modalStore au lieu de store
  modalStore.deleteRateItem(type, id);
};

// Initialisations de sécurité pour éviter les erreurs v-model sur des propriétés nulles
if (!modalStore.formData.unite_de_prix)
  modalStore.formData.unite_de_prix = "par nuit";
if (!modalStore.formData.mode_reservation)
  modalStore.formData.mode_reservation = "log_directe";

// NOUVEAU SYSTÈME : Initialisation de l'objet propre et migration automatique du legacy ACF
if (!modalStore.formData.payment_rules) {
  modalStore.formData.payment_rules = {
    mode_pay: modalStore.formData.pc_pay_mode || "acompte_plus_solde",
    delay_days: modalStore.formData.pc_balance_delay_days || 30,
    deposit_type: modalStore.formData.pc_deposit_type || "pourcentage",
    deposit_value: modalStore.formData.pc_deposit_value || 30,
    caution_type: modalStore.formData.pc_caution_type || "aucune",
    caution_amount: modalStore.formData.pc_caution_amount || 0,
  };
}

// Conversion du booléen
modalStore.formData.pc_promo_log =
  modalStore.formData.pc_promo_log === "1" ||
  modalStore.formData.pc_promo_log === true;

// Taxe de séjour doit être un tableau pour les checkboxes multiples
if (!Array.isArray(modalStore.formData.taxe_sejour)) {
  modalStore.formData.taxe_sejour = [];
}
</script>

<style scoped>
.pc-section-title {
  margin-top: 1.5rem;
  margin-bottom: 1rem;
  font-size: 1.1rem;
  color: #1e293b;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 0.5rem;
}

.pc-section-title:first-child {
  margin-top: 0;
}

.pc-form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.5rem;
}

.pc-form-group {
  display: flex;
  flex-direction: column;
}

.pc-form-group--full {
  grid-column: 1 / -1;
}

.pc-form-group label {
  font-weight: 500;
  color: #334155;
  margin-bottom: 0.5rem;
  font-size: 0.95rem;
}

.pc-input,
.pc-select {
  padding: 0.75rem 1rem;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  background: white;
  font-size: 0.95rem;
  font-family: inherit;
  transition: border-color 0.2s;
  /* CORRECTIONS WORDPRESS */
  box-sizing: border-box;
  height: auto !important; /* Force WP à ignorer sa hauteur par défaut */
  line-height: 1.5;
}

.pc-input:focus,
.pc-select:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.pc-checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  font-weight: normal !important;
}

.pc-checkbox-group {
  display: flex;
  flex-wrap: wrap;
  gap: 15px;
  background: #f8fafc;
  padding: 15px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.text-red-500 {
  color: #ef4444;
}

.pc-calendar-placeholder {
  background: #f1f5f9;
  border: 2px dashed #cbd5e1;
  border-radius: 8px;
  padding: 3rem;
  text-align: center;
  color: #64748b;
  font-weight: 500;
}

.pc-rates-container {
  display: flex;
  gap: 20px;
  margin-top: 20px;
  align-items: flex-start; /* Permet à la sidebar de ne pas s'étirer si le calendrier grandit */
}

/* Fallback responsive */
@media (max-width: 992px) {
  .pc-rates-container {
    flex-direction: column;
  }

  .pc-rates-container > * {
    width: 100% !important;
  }
}
</style>
