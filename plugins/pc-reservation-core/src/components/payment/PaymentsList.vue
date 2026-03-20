<template>
  <div class="pc-payments-list">
    <h4 class="section-title">Échéancier de paiement</h4>

    <div v-if="!payments || payments.length === 0" class="no-data">
      Aucun paiement configuré pour cette réservation.
    </div>

    <table v-else class="pc-table w-full text-left border-collapse">
      <thead>
        <tr>
          <th class="p-2 border-b">Type</th>
          <th class="p-2 border-b">Montant</th>
          <th class="p-2 border-b">Statut</th>
          <th class="p-2 border-b text-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="payment in payments"
          :key="payment.id"
          class="hover:bg-gray-50"
        >
          <td class="p-2 border-b capitalize">
            {{ formatType(payment.type_paiement) }}
          </td>
          <td class="p-2 border-b font-medium">
            {{ formatCurrency(payment.montant) }}
          </td>
          <td class="p-2 border-b">
            <span :class="getBadgeClass(payment.statut)">
              {{ formatStatut(payment.statut) }}
            </span>
          </td>
          <td class="p-2 border-b text-right min-w-[250px]">
            <template
              v-if="payment.statut !== 'paye' && payment.statut !== 'annule'"
            >
              <div
                v-if="payment.url_paiement || generatedUrls[payment.id]"
                class="flex items-center justify-end gap-1"
              >
                <input
                  type="text"
                  readonly
                  :value="payment.url_paiement || generatedUrls[payment.id]"
                  class="text-xs p-1 border border-gray-200 rounded bg-gray-50 text-gray-500 w-32 focus:outline-none"
                  title="Lien de paiement"
                />
                <button
                  @click="
                    copyToClipboard(
                      payment.id,
                      payment.url_paiement || generatedUrls[payment.id],
                    )
                  "
                  class="pc-btn pc-btn-sm"
                  :class="
                    copiedPayments[payment.id]
                      ? 'pc-btn-success text-green-700 bg-green-100'
                      : 'pc-btn-secondary'
                  "
                  title="Copier le lien"
                >
                  <span v-if="copiedPayments[payment.id]">✅</span>
                  <span v-else>📋 Copier</span>
                </button>
              </div>

              <button
                v-else
                @click="handleGenerateLink(payment)"
                :disabled="
                  paymentsStore.isLoading(`payment_link_${payment.id}`)
                "
                class="pc-btn pc-btn-sm pc-btn-primary"
              >
                <span
                  v-if="paymentsStore.isLoading(`payment_link_${payment.id}`)"
                  >⏳ Création...</span
                >
                <span v-else>🔗 Générer le lien</span>
              </button>
            </template>

            <span
              v-else-if="payment.statut === 'paye'"
              class="text-green-600 text-sm"
            >
              ✔ Réglé le {{ formatDate(payment.date_paiement) }}
            </span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { ref } from "vue";
import { usePaymentsStore } from "../../stores/payments-store";

const copiedPayments = ref({});
const generatedUrls = ref({}); // Stocke les URLs générées sans recharger la page

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
  // À adapter selon tes classes CSS existantes (dashboard-style.css)
  const baseClass = "px-2 py-1 text-xs rounded-full font-semibold ";
  if (statut === "paye") return baseClass + "bg-green-100 text-green-800";
  if (statut === "en_attente")
    return baseClass + "bg-orange-100 text-orange-800";
  if (statut === "annule") return baseClass + "bg-red-100 text-red-800";
  return baseClass + "bg-gray-100 text-gray-800";
};

// --- ACTIONS ---
const handleGenerateLink = async (payment) => {
  try {
    const result = await paymentsStore.generatePaymentLink(
      payment.id,
      props.reservationId,
    );

    // On sauvegarde l'URL pour l'afficher dans l'interface de façon permanente
    generatedUrls.value[payment.id] = result.url;

    // On copie automatiquement la première fois
    await copyToClipboard(payment.id, result.url);
  } catch (error) {
    alert("Erreur: " + error.message);
  }
};

const copyToClipboard = async (paymentId, url) => {
  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(url);
    } else {
      throw new Error("HTTPS requis pour la copie auto");
    }
  } catch (err) {
    prompt("Voici le lien (Ctrl+C pour copier) :", url);
  }

  // Feedback visuel du bouton de copie
  copiedPayments.value[paymentId] = true;
  setTimeout(() => {
    copiedPayments.value[paymentId] = false;
  }, 3000);
};
</script>

<style scoped>
/* Ajustements mineurs si besoin, le reste devrait hériter de ton dashboard-style.css */
.pc-table {
  width: 100%;
  border-collapse: collapse;
}

.pc-btn-sm {
  padding: 4px 10px !important;
  font-size: 0.85rem !important;
  height: auto !important;
  line-height: 1.2 !important;
}
</style>
