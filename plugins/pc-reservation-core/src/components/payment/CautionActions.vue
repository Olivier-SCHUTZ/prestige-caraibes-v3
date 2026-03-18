<template>
  <div class="pc-caution-actions mt-4 p-5 border rounded-lg bg-white shadow-sm">
    <div class="flex justify-between items-center mb-4">
      <h4 class="font-bold text-lg m-0">
        Gestion de la Caution
        <span class="text-gray-500 font-normal"
          >({{ formatCurrency(caution.montant) }})</span
        >
      </h4>
      <span :class="getBadgeClass(caution.statut)">
        {{ formatStatut(caution.statut) }}
      </span>
    </div>

    <div v-if="['non_demande', 'demande_envoyee'].includes(caution.statut)">
      <button
        @click="handleGenerateLink"
        :disabled="isLoading('link')"
        class="pc-btn pc-btn-sm pc-btn-primary flex items-center gap-2"
      >
        <span v-if="isLoading('link')">⏳ Génération...</span>
        <span v-else>🔗 Obtenir le lien d'empreinte bancaire</span>
      </button>
      <p
        v-if="caution.statut === 'demande_envoyee'"
        class="text-sm text-orange-600 mt-2"
      >
        ⚠️ Un lien a déjà été généré le {{ formatDate(caution.date_demande) }}.
        Toujours en attente du client.
      </p>
    </div>

    <div
      v-else-if="caution.statut === 'empreinte_validee'"
      class="flex flex-wrap gap-2"
    >
      <button
        @click="handleRelease"
        :disabled="isAnyLoading"
        class="pc-btn pc-btn-sm pc-btn-success"
      >
        <span v-if="isLoading('release')">⏳ Libération...</span>
        <span v-else>🟢 Libérer</span>
      </button>

      <button
        @click="handleRotate"
        :disabled="isAnyLoading"
        class="pc-btn pc-btn-sm pc-btn-secondary"
        title="Renouvelle la pré-autorisation pour 7 jours de plus"
      >
        <span v-if="isLoading('rotate')">⏳ Renouvellement...</span>
        <span v-else>🔄 Renouveler (7j)</span>
      </button>

      <button
        @click="promptCapture"
        :disabled="isAnyLoading"
        class="pc-btn pc-btn-sm pc-btn-danger"
      >
        <span v-if="isLoading('capture')">⏳ Encaissement...</span>
        <span v-else>🔴 Encaisser (Dégâts)</span>
      </button>

      <p class="text-xs text-gray-500 w-full mt-2">
        Ref Stripe :
        <code class="bg-gray-100 p-1 rounded">{{ caution.reference }}</code>
      </p>
    </div>

    <div v-else class="text-gray-500 italic p-3 bg-gray-50 rounded">
      Aucune action requise. La caution est actuellement :
      {{ formatStatut(caution.statut) }}.
    </div>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { usePaymentsStore } from "../../stores/payments-store";

const props = defineProps({
  reservationId: {
    type: [Number, String],
    required: true,
  },
  caution: {
    type: Object,
    required: true,
    // Structure attendue : { montant: 500, statut: 'empreinte_validee', reference: 'pi_xxx', date_demande: '...' }
  },
});

const store = usePaymentsStore();

// --- GESTION DU CHARGEMENT ---
const isLoading = (action) => {
  const keys = {
    link: `caution_link_${props.reservationId}`,
    release: `caution_release_${props.reservationId}`,
    capture: `caution_capture_${props.reservationId}`,
    rotate: `caution_rotate_${props.reservationId}`,
  };
  return store.isLoading(keys[action]);
};

// Désactive tous les boutons si une action est en cours
const isAnyLoading = computed(() => {
  return (
    isLoading("link") ||
    isLoading("release") ||
    isLoading("capture") ||
    isLoading("rotate")
  );
});

// --- FORMATTEURS ---
const formatCurrency = (val) =>
  new Intl.NumberFormat("fr-FR", { style: "currency", currency: "EUR" }).format(
    val || 0,
  );
const formatDate = (date) =>
  date ? new Date(date).toLocaleDateString("fr-FR") : "";

const formatStatut = (statut) => {
  const map = {
    non_demande: "Non demandée",
    demande_envoyee: "En attente du client",
    empreinte_validee: "Empreinte Sécurisée",
    liberee: "Libérée",
    encaissee: "Encaissée",
  };
  return map[statut] || statut.replace(/_/g, " ");
};

const getBadgeClass = (statut) => {
  const base = "px-3 py-1 text-sm rounded-full font-semibold ";
  if (statut === "empreinte_validee")
    return base + "bg-green-100 text-green-800";
  if (statut === "demande_envoyee")
    return base + "bg-orange-100 text-orange-800";
  if (statut === "liberee") return base + "bg-gray-200 text-gray-700";
  if (statut === "encaissee") return base + "bg-red-100 text-red-800";
  return base + "bg-gray-100 text-gray-500";
};

// --- ACTIONS API ---

const handleGenerateLink = async () => {
  try {
    const res = await store.generateCautionLink(props.reservationId);
    await navigator.clipboard.writeText(res.url);
    alert("Lien de caution généré et copié dans le presse-papier !");
  } catch (e) {
    alert("Erreur : " + e.message);
  }
};

const handleRelease = async () => {
  if (
    !confirm(
      "Êtes-vous sûr de vouloir libérer cette empreinte ? L'argent ne pourra plus être prélevé.",
    )
  )
    return;

  try {
    await store.releaseCaution(props.reservationId, props.caution.reference);
    alert("Caution libérée avec succès !");
  } catch (e) {
    alert("Erreur : " + e.message);
  }
};

const handleRotate = async () => {
  if (
    !confirm(
      "Renouveler cette empreinte annulera la précédente et en créera une nouvelle (valable 7 jours). Continuer ?",
    )
  )
    return;

  try {
    await store.rotateCaution(props.reservationId, props.caution.reference);
    alert("Caution renouvelée avec succès !");
  } catch (e) {
    alert("Erreur : " + e.message);
  }
};

const promptCapture = async () => {
  const maxAmount = parseFloat(props.caution.montant);

  // 1. Demande du montant
  const inputAmount = prompt(
    `Montant à encaisser (Maximum ${maxAmount} €) :`,
    maxAmount,
  );
  if (inputAmount === null) return; // L'utilisateur a cliqué sur "Annuler"

  const amount = parseFloat(inputAmount.replace(",", "."));
  if (isNaN(amount) || amount <= 0 || amount > maxAmount) {
    alert("Montant invalide. Le prélèvement est annulé.");
    return;
  }

  // 2. Demande du motif (important pour les notes internes et Stripe)
  const note = prompt(
    "Saisissez le motif de l'encaissement (ex: Casse vaisselle) :\nCe motif sera ajouté à vos notes internes.",
  );
  if (note === null) return;

  // 3. Confirmation finale
  if (
    !confirm(
      `⚠️ ATTENTION : Vous allez prélever définitivement ${amount} € sur la carte du client.\nConfirmer ?`,
    )
  )
    return;

  try {
    await store.captureCaution(
      props.reservationId,
      props.caution.reference,
      amount,
      note,
    );
    alert(`Encaissement de ${amount} € validé avec succès !`);
  } catch (e) {
    alert("Erreur lors du prélèvement : " + e.message);
  }
};
</script>

<style scoped>
.pc-btn-sm {
  padding: 4px 10px !important;
  font-size: 0.85rem !important;
  height: auto !important;
  line-height: 1.2 !important;
}
</style>
