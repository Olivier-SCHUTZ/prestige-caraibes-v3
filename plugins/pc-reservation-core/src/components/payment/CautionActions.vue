<template>
  <div class="pc-caution-actions mt-4 p-5 border rounded-lg bg-white shadow-sm">
    <div class="flex justify-between items-center mb-5 pb-3 border-b border-gray-100">
      <h4 class="font-bold text-lg text-gray-800 m-0 flex items-center gap-2">
        🔒 Gestion de la Caution
        <span class="text-gray-500 font-normal text-sm"
          >({{ formatCurrency(caution.montant) }})</span
        >
      </h4>
      <span :class="getBadgeClass(caution.statut)">
        {{ formatStatut(caution.statut) }}
      </span>
    </div>

    <div v-if="['non_demande', 'demande_envoyee'].includes(caution.statut)" class="flex flex-col gap-3 max-w-[400px]">
      
      <div v-if="generatedCautionUrl" class="flex items-center justify-end gap-2 w-full">
        <input
          type="text"
          readonly
          :value="generatedCautionUrl"
          class="text-xs p-2 border border-gray-300 rounded-md bg-gray-50 text-gray-600 w-full focus:outline-none focus:ring-1 focus:ring-indigo-500"
        />
        <button
          type="button"
          @click.prevent="copyCautionLink(generatedCautionUrl)"
          class="flex-shrink-0 px-3 py-2 text-xs font-semibold rounded-md transition-colors shadow-sm border"
          :class="isLinkCopied ? 'bg-green-50 text-green-700 border-green-200' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
        >
          <span v-if="isLinkCopied">✅ Copié</span>
          <span v-else>📋 Copier</span>
        </button>
      </div>

      <button
        v-else
        type="button"
        @click.prevent="handleGenerateLink"
        :disabled="isLoading('link')"
        class="w-full flex items-center justify-center gap-1.5 px-3 py-2.5 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-md shadow-sm transition-colors disabled:opacity-50"
      >
        <span v-if="isLoading('link')">⏳ Génération en cours...</span>
        <span v-else>🔗 Générer le lien d'empreinte Stripe</span>
      </button>

      <div
        v-if="caution.statut === 'demande_envoyee'"
        class="text-[11px] text-orange-700 bg-orange-50 border border-orange-200 p-2 rounded-md font-medium mt-1"
      >
        ⚠️ Un lien a été généré le {{ formatDate(caution.date_demande) }}. En attente de l'action du client.
      </div>
    </div>

    <div v-else-if="caution.statut === 'empreinte_validee'" class="flex flex-col gap-3">
      <div class="flex gap-2 w-full max-w-[500px]">
        
        <button
          type="button"
          @click.prevent="handleRelease"
          :disabled="isAnyLoading"
          class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 text-[10px] uppercase tracking-wide font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-md transition-colors shadow-sm disabled:opacity-50"
        >
          <span v-if="isLoading('release')">⏳</span>
          <span v-else class="text-sm">🟢</span> Libérer
        </button>

        <button
          type="button"
          @click.prevent="handleRotate"
          :disabled="isAnyLoading"
          class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 text-[10px] uppercase tracking-wide font-bold text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-md transition-colors shadow-sm disabled:opacity-50"
          title="Renouvelle la pré-autorisation pour 7 jours de plus"
        >
          <span v-if="isLoading('rotate')">⏳</span>
          <span v-else class="text-sm">🔄</span> Renouveler (7j)
        </button>

        <button
          type="button"
          @click.prevent="promptCapture"
          :disabled="isAnyLoading"
          class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 text-[10px] uppercase tracking-wide font-bold text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 rounded-md transition-colors shadow-sm disabled:opacity-50"
        >
          <span v-if="isLoading('capture')">⏳</span>
          <span v-else class="text-sm">🔴</span> Encaisser
        </button>
      </div>

      <p class="text-[11px] text-gray-500 m-0 mt-1">
        Ref Stripe :
        <code class="bg-gray-50 border border-gray-200 text-gray-600 px-1.5 py-0.5 rounded ml-1">{{ caution.reference || caution.caution_reference }}</code>
      </p>
    </div>

    <div
      v-else-if="caution.statut === 'encaissee'"
      class="bg-red-50 border border-red-200 p-4 rounded-md mt-2"
    >
      <div class="flex items-center gap-2 mb-2">
        <span class="text-xl">🔴</span>
        <strong class="text-red-800 font-bold">Caution Encaissée</strong>
      </div>
      <p class="text-sm text-red-700 mb-3">
        Un prélèvement a été effectué avec succès sur la carte du client suite à
        des dégâts ou frais supplémentaires.
      </p>
      <div
        class="text-xs text-red-800 bg-white p-3 rounded border border-red-100 flex items-start gap-2"
      >
        <span class="text-lg">📁</span>
        <p>
          <strong>Pour vos archives :</strong> Les détails exacts de ce
          prélèvement (Montant, Date, et Motif) ont été automatiquement
          enregistrés dans l'onglet <strong>"Notes Internes"</strong> de cette
          réservation.
        </p>
      </div>
    </div>

    <div v-else class="text-gray-500 italic p-3 bg-gray-50 rounded mt-2">
      Aucune action requise. La caution est actuellement :
      {{ formatStatut(caution.statut) }}.
    </div>

    <Teleport to="body">
      <div
        v-if="isCaptureModalOpen"
        style="
          position: fixed;
          top: 0;
          left: 0;
          width: 100vw;
          height: 100vh;
          background-color: rgba(0, 0, 0, 0.75);
          display: flex;
          align-items: center;
          justify-content: center;
          z-index: 999999;
        "
      >
        <div
          class="bg-white rounded-xl shadow-2xl p-8 max-w-lg w-full relative"
          style="
            background-color: #ffffff;
            color: #111827;
            border-radius: 1rem;
            padding: 2rem;
            max-width: 32rem;
            width: 100%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            box-sizing: border-box;
          "
        >
          <div
            class="flex items-center gap-4 mb-6 pb-4 border-b border-gray-100"
            style="
              border-bottom: 1px solid #f3f4f6;
              margin-bottom: 1.5rem;
              padding-bottom: 1rem;
              display: flex;
              align-items: center;
              gap: 1rem;
            "
          >
            <div
              class="bg-blue-50 text-blue-600 p-3 rounded-full text-xl"
              style="
                background-color: #eff6ff;
                color: #2563eb;
                padding: 0.75rem;
                border-radius: 9999px;
              "
            >
              💳
            </div>
            <h3
              class="text-2xl font-bold text-gray-900 m-0"
              style="margin: 0; font-size: 1.5rem; font-weight: 700"
            >
              Encaisser la caution
            </h3>
          </div>

          <div
            class="space-y-5"
            style="display: flex; flex-direction: column; gap: 1.25rem"
          >
            <div
              class="flex flex-col gap-1.5"
              style="display: flex; flex-direction: column; gap: 0.375rem"
            >
              <label
                for="captureAmount"
                class="text-sm font-semibold text-gray-700"
                style="font-size: 0.875rem; font-weight: 600"
              >
                Montant à encaisser
              </label>
              <div class="relative" style="position: relative">
                <input
                  type="number"
                  id="captureAmount"
                  v-model="captureAmount"
                  :max="caution?.montant"
                  step="0.01"
                  class="w-full px-4 py-3 border border-gray-300 rounded-lg"
                  style="
                    width: 100%;
                    padding: 0.75rem 1rem;
                    border: 1px solid #d1d5db;
                    border-radius: 0.5rem;
                    background-color: #ffffff;
                    color: #111827;
                    box-sizing: border-box;
                  "
                  placeholder="0.00"
                />
                <span
                  class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 font-medium"
                  style="
                    position: absolute;
                    right: 1rem;
                    top: 50%;
                    transform: translateY(-50%);
                    color: #9ca3af;
                  "
                  >€</span
                >
              </div>
              <p
                class="text-xs text-gray-500 m-0"
                style="margin: 0; font-size: 0.75rem; color: #6b7280"
              >
                Maximum autorisé : {{ caution?.montant }} €
              </p>
            </div>

            <div
              class="flex flex-col gap-1.5"
              style="display: flex; flex-direction: column; gap: 0.375rem"
            >
              <label
                for="captureNote"
                class="text-sm font-semibold text-gray-700"
                style="font-size: 0.875rem; font-weight: 600"
              >
                Motif de l'encaissement
              </label>
              <input
                type="text"
                id="captureNote"
                v-model="captureNote"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg"
                style="
                  width: 100%;
                  padding: 0.75rem 1rem;
                  border: 1px solid #d1d5db;
                  border-radius: 0.5rem;
                  background-color: #ffffff;
                  color: #111827;
                  box-sizing: border-box;
                "
                placeholder="ex: Dégâts vaisselle"
              />
              <p
                class="text-xs text-gray-500 m-0"
                style="margin: 0; font-size: 0.75rem; color: #6b7280"
              >
                (Sera enregistré dans les notes internes)
              </p>
            </div>
          </div>

          <div
            class="flex justify-end gap-3 mt-8 pt-6 border-t border-gray-100"
            style="
              display: flex;
              justify-content: flex-end;
              gap: 0.75rem;
              margin-top: 2rem;
              padding-top: 1.5rem;
              border-top: 1px solid #f3f4f6;
            "
          >
            <button
              type="button"
              @click.prevent="closeCaptureModal"
              class="pc-btn pc-btn-secondary pc-btn-lg text-gray-700 bg-gray-100 hover:bg-gray-200 border-none px-6"
              style="
                padding: 0.5rem 1.5rem;
                border-radius: 0.5rem;
                background-color: #f3f4f6;
                color: #374151;
                border: none;
                cursor: pointer;
                font-weight: 600;
              "
            >
              Annuler
            </button>
            <button
              type="button"
              @click.prevent="confirmCapture"
              :disabled="isCapturing"
              class="pc-btn pc-btn-danger pc-btn-lg bg-red-600 hover:bg-red-700 text-white flex items-center gap-2 px-6 disabled:opacity-50 disabled:cursor-not-allowed"
              style="
                padding: 0.5rem 1.5rem;
                border-radius: 0.5rem;
                background-color: #dc2626;
                color: #ffffff;
                border: none;
                cursor: pointer;
                font-weight: 600;
                display: flex;
                align-items: center;
                gap: 0.5rem;
              "
            >
              <span v-if="isCapturing">⏳</span>
              <span v-else>⚡</span>
              {{
                isCapturing
                  ? "Traitement en cours..."
                  : "Confirmer le prélèvement"
              }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, ref } from "vue";
import { usePaymentsStore } from "../../stores/payments-store";

const isLinkCopied = ref(false);
const generatedCautionUrl = ref("");

const props = defineProps({
  reservationId: {
    type: [Number, String],
    required: true,
  },
  caution: {
    type: Object,
    required: true,
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
  // Nouveau style "Pilule" (identique au tableau principal)
  const base = "px-3 py-1 text-[10px] rounded-full font-bold uppercase tracking-wide border ";
  if (statut === "empreinte_validee") return base + "bg-emerald-50 text-emerald-700 border-emerald-200";
  if (statut === "demande_envoyee") return base + "bg-orange-50 text-orange-700 border-orange-200";
  if (statut === "liberee") return base + "bg-slate-50 text-slate-600 border-slate-200";
  if (statut === "encaissee") return base + "bg-red-50 text-red-700 border-red-200 shadow-sm";
  return base + "bg-gray-50 text-gray-500 border-gray-200";
};

// --- ACTIONS API ---
const copyCautionLink = async (url) => {
  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(url);
    } else {
      throw new Error("HTTPS requis");
    }
  } catch (err) {
    prompt(
      "Voici le lien pour l'empreinte bancaire (Ctrl+C pour copier) :",
      url,
    );
  }

  isLinkCopied.value = true;
  setTimeout(() => {
    isLinkCopied.value = false;
  }, 3000);
};

const handleGenerateLink = async () => {
  try {
    const res = await store.generateCautionLink(props.reservationId);
    generatedCautionUrl.value = res.url;
    await copyCautionLink(res.url);
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
    const refStripe =
      props.caution?.reference || props.caution?.caution_reference;
    await store.releaseCaution(props.reservationId, refStripe);
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
    const refStripe =
      props.caution?.reference || props.caution?.caution_reference;
    await store.rotateCaution(props.reservationId, refStripe);
    alert("Caution renouvelée avec succès !");
  } catch (e) {
    alert("Erreur : " + e.message);
  }
};

// --- GESTION DE LA MODALE D'ENCAISSEMENT ---
const isCaptureModalOpen = ref(false);
const captureAmount = ref(0);
const captureNote = ref("");
const isCapturing = ref(false); // État de chargement local pour la modale

const promptCapture = () => {
  captureAmount.value = parseFloat(props.caution?.montant || 0);
  captureNote.value = "";
  isCaptureModalOpen.value = true;
};

const closeCaptureModal = () => {
  isCaptureModalOpen.value = false;
};

const confirmCapture = async () => {
  const maxAmount = parseFloat(props.caution?.montant || 0);
  const amount = parseFloat(captureAmount.value);

  if (isNaN(amount) || amount <= 0 || amount > maxAmount) {
    alert(`Montant invalide. Le maximum autorisé est de ${maxAmount} €.`);
    return;
  }

  try {
    const refStripe =
      props.caution?.reference || props.caution?.caution_reference;

    if (!refStripe) {
      alert("ERREUR CRITIQUE : La référence Stripe est introuvable.");
      return;
    }

    isCapturing.value = true;

    await store.captureCaution(
      props.reservationId,
      refStripe,
      amount,
      captureNote.value,
    );

    closeCaptureModal();

    setTimeout(() => {
      alert(`Encaissement de ${amount} € validé avec succès !`);
    }, 150);
  } catch (e) {
    alert("Erreur lors du prélèvement : " + e.message);
  } finally {
    isCapturing.value = false;
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
