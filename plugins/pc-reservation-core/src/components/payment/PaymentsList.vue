<template>
  <div class="pc-payments-list">
    <h4 class="section-title">Échéancier de paiement</h4>

    <div v-if="!payments || payments.length === 0" class="no-data">
      Aucun paiement configuré pour cette réservation.
    </div>

    <table class="pc-table w-full text-left border-collapse">
      <thead>
        <tr>
          <th
            class="p-3 border-b text-gray-500 font-semibold text-sm uppercase"
          >
            Type
          </th>
          <th
            class="p-3 border-b text-gray-500 font-semibold text-sm uppercase text-center"
          >
            Montant
          </th>
          <th
            class="p-3 border-b text-gray-500 font-semibold text-sm uppercase text-right"
          >
            Statut
          </th>
        </tr>
      </thead>
      <template v-for="payment in payments" :key="payment.id">
        <tr class="group">
          <td class="p-3 capitalize text-gray-800 font-medium text-base">
            {{ formatType(payment.type_paiement) }}
            <div
              v-if="payment.methode && payment.methode !== 'stripe'"
              class="text-[11px] text-gray-400 uppercase mt-1 tracking-wider"
            >
              Via {{ payment.methode }}
            </div>
          </td>
          <td class="p-3 font-bold text-gray-900 text-lg text-center">
            {{ formatCurrency(payment.montant) }}
          </td>
          <td class="p-3 text-right">
            <span :class="getBadgeClass(payment.statut)">
              {{ formatStatut(payment.statut) }}
            </span>
          </td>
        </tr>

        <tr class="border-b last:border-b-0 border-gray-200">
          <td colspan="3" class="px-3 pb-5 pt-1">
            <div
              v-if="
                payment.statut !== 'paye' &&
                payment.statut !== 'annule' &&
                payment.statut !== 'sur_place'
              "
              class="action-panel"
            >
              <div class="stripe-action">
                <div
                  v-if="payment.url_paiement || generatedUrls[payment.id]"
                  class="url-group"
                >
                  <input
                    type="text"
                    readonly
                    :value="payment.url_paiement || generatedUrls[payment.id]"
                    class="input-url"
                    title="Lien de paiement"
                  />
                  <button
                    @click="
                      copyToClipboard(
                        payment.id,
                        payment.url_paiement || generatedUrls[payment.id],
                      )
                    "
                    class="btn-copy"
                    :class="copiedPayments[payment.id] ? 'copied' : ''"
                  >
                    <span v-if="copiedPayments[payment.id]">✅ Copié</span>
                    <span v-else>📋 Copier</span>
                  </button>
                </div>
                <button
                  v-else
                  @click="handleGenerateLink(payment)"
                  :disabled="
                    paymentsStore.isLoading(`payment_link_${payment.id}`)
                  "
                  class="btn-stripe"
                >
                  <span
                    v-if="paymentsStore.isLoading(`payment_link_${payment.id}`)"
                    >⏳ Création...</span
                  >
                  <span v-else>🔗 Générer le lien Stripe</span>
                </button>
              </div>

              <div class="divider"></div>

              <div class="manual-actions">
                <button
                  @click.prevent="
                    handleManualPayment(payment, 'paye', 'virement')
                  "
                  :disabled="
                    paymentsStore.isLoading(`payment_update_${payment.id}`)
                  "
                  class="btn-virement"
                >
                  🏦 Virement reçu
                </button>
                <button
                  @click.prevent="
                    handleManualPayment(payment, 'sur_place', 'especes')
                  "
                  :disabled="
                    paymentsStore.isLoading(`payment_update_${payment.id}`)
                  "
                  class="btn-surplace"
                >
                  🤝 Prévu sur place
                </button>
              </div>
            </div>

            <div
              v-else-if="payment.statut === 'sur_place'"
              class="action-panel-alert"
            >
              <span class="alert-text"
                >⚠️ Reste à régler à l'arrivée du client</span
              >
              <button
                @click.prevent="handleManualPayment(payment, 'paye', 'especes')"
                :disabled="
                  paymentsStore.isLoading(`payment_update_${payment.id}`)
                "
                class="btn-encaisser"
              >
                ✅ Confirmer l'encaissement
              </button>
            </div>

            <div
              v-else-if="payment.statut === 'paye'"
              class="action-success-text"
            >
              ✔ Réglé le {{ formatDate(payment.date_paiement) }}
            </div>
          </td>
        </tr>
      </template>
    </table>
  </div>
</template>

<script setup>
import { ref } from "vue";
import { usePaymentsStore } from "../../stores/payments-store";

const copiedPayments = ref({});
const generatedUrls = ref({});

const props = defineProps({
  payments: {
    type: Array,
    required: true,
    default: () => [],
  },
  reservationId: {
    type: [Number, String],
    required: true,
  },
});

const paymentsStore = usePaymentsStore();

// --- FORMATTERS ---
const formatCurrency = (val) => {
  return new Intl.NumberFormat("fr-FR", {
    style: "currency",
    currency: "EUR",
  }).format(val || 0);
};

const formatType = (type) => {
  if (!type) return "";
  return type.replace(/_/g, " ");
};

const formatStatut = (statut) => {
  const map = {
    en_attente: "En attente",
    sur_place: "À régler sur place", // Nouveau statut
    paye: "Payé",
    annule: "Annulé",
  };
  return map[statut] || statut;
};

const formatDate = (dateString) => {
  if (!dateString) return "";
  return new Date(dateString).toLocaleDateString("fr-FR");
};

const getBadgeClass = (statut) => {
  const baseClass = "px-2 py-1 text-xs rounded-full font-semibold ";
  if (statut === "paye") return baseClass + "bg-green-100 text-green-800";
  if (statut === "en_attente") return baseClass + "bg-blue-100 text-blue-800";
  if (statut === "sur_place")
    return baseClass + "bg-orange-100 text-orange-800 border border-orange-200";
  if (statut === "annule") return baseClass + "bg-red-100 text-red-800";
  return baseClass + "bg-gray-100 text-gray-800";
};

// --- ACTIONS ---

// Action Stripe classique
const handleGenerateLink = async (payment) => {
  try {
    const result = await paymentsStore.generatePaymentLink(
      payment.id,
      props.reservationId,
    );
    generatedUrls.value[payment.id] = result.url;
    await copyToClipboard(payment.id, result.url);
  } catch (error) {
    alert("Erreur: " + error.message);
  }
};

// NOUVELLE Action : Paiement Manuel
const handleManualPayment = async (payment, status, method) => {
  let message = "";
  if (status === "paye" && method === "virement")
    message = "Confirmez-vous avoir reçu ce virement bancaire ?";
  else if (status === "sur_place")
    message =
      "Passer ce règlement en 'À payer sur place' annulera le lien Stripe. Continuer ?";
  else if (status === "paye" && method === "especes")
    message = "Confirmez-vous avoir encaissé cet argent sur place ?";

  if (!confirm(message)) return;

  try {
    await paymentsStore.updatePaymentStatus(
      payment.id,
      props.reservationId,
      status,
      method,
    );

    // Purger l'URL locale si elle était stockée
    if (generatedUrls.value[payment.id]) {
      delete generatedUrls.value[payment.id];
    }
  } catch (e) {
    alert("Erreur lors de la mise à jour : " + e.message);
  }
};

const copyToClipboard = async (paymentId, url) => {
  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(url);
    } else {
      throw new Error("HTTPS requis");
    }
  } catch (err) {
    prompt("Voici le lien (Ctrl+C pour copier) :", url);
  }
  copiedPayments.value[paymentId] = true;
  setTimeout(() => {
    copiedPayments.value[paymentId] = false;
  }, 3000);
};
</script>

<style scoped>
.pc-table {
  width: 100%;
  border-collapse: collapse;
}

/* --- PANNEAUX D'ACTIONS --- */
.action-panel {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 20px;
  background-color: #f8fafc;
  padding: 15px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.action-panel-alert {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background-color: #fff7ed;
  padding: 15px;
  border-radius: 8px;
  border: 1px solid #fed7aa;
}

.action-success-text {
  text-align: right;
  color: #16a34a;
  font-size: 0.85rem;
  font-weight: 600;
  padding-right: 10px;
}

/* --- SÉPARATEUR VISUEL --- */
.divider {
  width: 1px;
  height: 40px;
  background-color: #cbd5e1;
  display: none;
}
@media (min-width: 640px) {
  .divider {
    display: block;
  }
}

/* --- CONTENEURS HORIZONTAUX --- */
.stripe-action {
  display: flex;
  flex: 1;
  min-width: 250px;
}

.manual-actions {
  display: flex;
  gap: 12px;
  flex: 1;
  min-width: 300px;
}

.url-group {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
}

.input-url {
  flex: 1;
  padding: 8px 12px;
  font-size: 0.8rem;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  background-color: #ffffff;
  color: #475569;
}

/* --- BOUTONS --- */
.btn-stripe,
.btn-encaisser {
  width: 100%;
  padding: 10px 16px;
  font-size: 0.85rem;
  font-weight: 600;
  border-radius: 6px;
  color: white;
  transition: all 0.2s;
  border: none;
  cursor: pointer;
}
.btn-stripe {
  background-color: #4f46e5;
}
.btn-stripe:hover:not(:disabled) {
  background-color: #4338ca;
}

.btn-encaisser {
  background-color: #16a34a;
  width: auto;
}
.btn-encaisser:hover:not(:disabled) {
  background-color: #15803d;
}

.btn-copy {
  background-color: #ffffff;
  color: #334155;
  border: 1px solid #cbd5e1;
  padding: 8px 12px;
  font-size: 0.8rem;
  font-weight: 600;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-copy:hover {
  background-color: #f1f5f9;
}
.btn-copy.copied {
  background-color: #dcfce7;
  color: #166534;
  border-color: #bbf7d0;
}

.btn-virement,
.btn-surplace {
  flex: 1;
  padding: 10px;
  font-size: 0.85rem;
  font-weight: 600;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-virement {
  background-color: #f0fdf4;
  color: #15803d;
  border: 1px solid #bbf7d0;
}
.btn-virement:hover:not(:disabled) {
  background-color: #dcfce7;
}

.btn-surplace {
  background-color: #fff7ed;
  color: #c2410c;
  border: 1px solid #fed7aa;
}
.btn-surplace:hover:not(:disabled) {
  background-color: #ffedd5;
}

button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.alert-text {
  color: #c2410c;
  font-weight: 700;
  font-size: 0.95rem;
}
</style>
