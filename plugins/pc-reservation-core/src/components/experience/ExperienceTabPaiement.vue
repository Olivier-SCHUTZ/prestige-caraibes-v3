<template>
  <div class="pc-tab-paiement">
    <div class="pc-form-grid" style="display: grid; gap: 20px">
      <div
        class="pc-form-group"
        style="
          background: #f8fafc;
          padding: 20px;
          border-radius: 8px;
          border: 1px solid #e2e8f0;
        "
      >
        <h4
          style="
            margin-top: 0;
            margin-bottom: 15px;
            color: #1e293b;
            font-size: 1.1rem;
          "
        >
          💰 Mode de facturation
        </h4>

        <label style="display: block; font-weight: 600; margin-bottom: 5px"
          >Comment le client doit-il payer ?</label
        >
        <select
          v-model="experience.pc_pay_mode"
          class="pc-select"
          style="
            width: 100%;
            max-width: 400px;
            padding: 8px 12px;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
            background: white;
            min-height: 40px;
            box-sizing: border-box;
          "
        >
          <option value="totalite">
            Paiement de la totalité à la réservation
          </option>
          <option value="acompte_plus_solde">
            Acompte en ligne + Solde plus tard
          </option>
          <option value="sur_place">Paiement sur place (100% le jour J)</option>
        </select>
      </div>

      <div
        v-if="experience.pc_pay_mode === 'acompte_plus_solde'"
        style="
          display: grid;
          grid-template-columns: 1fr 1fr;
          gap: 20px;
          background: #fffbeb;
          padding: 20px;
          border-radius: 8px;
          border: 1px solid #fde68a;
        "
      >
        <div class="pc-form-group">
          <label
            style="
              display: block;
              font-weight: 600;
              margin-bottom: 5px;
              color: #92400e;
            "
            >Type d'acompte</label
          >
          <div style="display: flex; gap: 10px">
            <select
              v-model="experience.pc_deposit_type"
              class="pc-select"
              style="
                flex: 1;
                padding: 8px 12px;
                border: 1px solid #fcd34d;
                border-radius: 6px;
                background: white;
                min-height: 40px;
                box-sizing: border-box;
              "
            >
              <option value="pourcentage">Pourcentage (%)</option>
              <option value="fixe">Montant fixe (€)</option>
            </select>
            <input
              type="number"
              v-model="experience.pc_deposit_value"
              class="pc-input"
              placeholder="Ex: 30"
              style="
                width: 100px;
                padding: 8px;
                border: 1px solid #fcd34d;
                border-radius: 6px;
              "
            />
          </div>
        </div>

        <div class="pc-form-group">
          <label
            style="
              display: block;
              font-weight: 600;
              margin-bottom: 5px;
              color: #92400e;
            "
            >Délai de paiement du solde</label
          >
          <div style="display: flex; align-items: center; gap: 10px">
            <input
              type="number"
              v-model="experience.pc_balance_delay_days"
              class="pc-input"
              placeholder="Ex: 7"
              style="
                width: 100px;
                padding: 8px;
                border: 1px solid #fcd34d;
                border-radius: 6px;
              "
            />
            <span style="font-size: 0.9rem; color: #b45309"
              >jours avant l'expérience</span
            >
          </div>
          <small style="color: #d97706; display: block; margin-top: 5px"
            >Mettre 0 pour un paiement du solde le jour J.</small
          >
        </div>
      </div>

      <div
        class="pc-form-group"
        style="
          background: #f8fafc;
          padding: 20px;
          border-radius: 8px;
          border: 1px solid #e2e8f0;
        "
      >
        <h4
          style="
            margin-top: 0;
            margin-bottom: 15px;
            color: #1e293b;
            font-size: 1.1rem;
          "
        >
          🛡️ Dépôt de garantie (Caution)
        </h4>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px">
          <div>
            <label style="display: block; font-weight: 600; margin-bottom: 5px"
              >Mode de gestion</label
            >
            <select
              v-model="experience.pc_caution_mode"
              class="pc-select"
              style="
                width: 100%;
                padding: 8px 12px;
                border: 1px solid #cbd5e0;
                border-radius: 6px;
                background: white;
                min-height: 40px;
                box-sizing: border-box;
              "
            >
              <option value="aucune">Aucune caution</option>
              <option value="empreinte_bancaire">
                Empreinte bancaire (Swikly / Stripe)
              </option>
              <option value="sur_place">Chèque / Espèces sur place</option>
            </select>
          </div>

          <div v-if="experience.pc_caution_mode !== 'aucune'">
            <label style="display: block; font-weight: 600; margin-bottom: 5px"
              >Montant de la caution (€)</label
            >
            <input
              type="number"
              v-model="experience.pc_caution_amount"
              class="pc-input"
              placeholder="Ex: 1500"
              style="
                width: 100%;
                max-width: 200px;
                padding: 8px 12px;
                border: 1px solid #cbd5e0;
                border-radius: 6px;
              "
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { watchEffect } from "vue";
import { storeToRefs } from "pinia";
import { useExperienceStore } from "../../stores/experience-store";

const store = useExperienceStore();
const { currentExperience: experience } = storeToRefs(store);

// S'assurer que les valeurs par défaut existent si l'API renvoie null
watchEffect(() => {
  if (experience.value) {
    if (!experience.value.pc_pay_mode)
      experience.value.pc_pay_mode = "acompte_plus_solde";
    if (!experience.value.pc_deposit_type)
      experience.value.pc_deposit_type = "pourcentage";
    if (!experience.value.pc_caution_mode)
      experience.value.pc_caution_mode = "aucune";
  }
});
</script>
