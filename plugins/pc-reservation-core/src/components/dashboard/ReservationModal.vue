<template>
  <Teleport to="body">
    <div
      v-if="store.isDetailModalOpen || forceOpen"
      class="pcr-modal-overlay"
      @click.self="closeModal"
    >
      <div class="pcr-modal-content pcr-modal-large">
        <header class="pcr-modal-header">
          <div class="header-titles">
            <h2 v-if="isCreateMode">✨ Nouvelle Réservation</h2>
            <h2 v-else>Dossier #{{ store.selectedReservation?.id }}</h2>

            <div v-if="!isCreateMode" class="pcr-status-row">
              <span
                class="badge-status"
                :class="
                  'status-resa-' + store.selectedReservation?.statut_reservation
                "
              >
                {{
                  store.selectedReservation?.statut_reservation?.replace(
                    /_/g,
                    " ",
                  )
                }}
              </span>
              <span
                class="badge-status"
                :class="
                  'status-pay-' + store.selectedReservation?.statut_paiement
                "
              >
                {{
                  store.selectedReservation?.statut_paiement?.replace(/_/g, " ")
                }}
              </span>
            </div>
          </div>
          <div class="header-actions">
            <button v-if="!isCreateMode" class="pc-btn-outline">
              ✏️ Modifier
            </button>
            <button class="btn-close" @click="closeModal">&times;</button>
          </div>
        </header>

        <div v-if="isCreateMode" class="pcr-modal-body">
          <div class="pcr-card-section">
            <h3>Formulaire de création (En construction 🚧)</h3>
            <p>
              La communication fonctionne ! Voici les données reçues du
              calendrier :
            </p>
            <div
              style="
                background: #f1f5f9;
                padding: 15px;
                border-radius: 6px;
                margin-top: 10px;
              "
            >
              <p>
                <strong>🏠 ID du Logement :</strong> {{ createForm.logementId }}
              </p>
              <p><strong>📅 Date d'arrivée :</strong> {{ createForm.start }}</p>
              <p><strong>📅 Date de départ :</strong> {{ createForm.end }}</p>
            </div>
            <p class="text-muted mt-15">
              C'est ici que tu pourras ajouter tes
              <code>&lt;input&gt;</code> pour le nom du client, le prix, etc.
            </p>
          </div>
        </div>

        <template v-else>
          <div class="pcr-modal-tabs">
            <button
              :class="{ active: activeTab === 'details' }"
              @click="activeTab = 'details'"
            >
              📝 Détails & Séjour
            </button>
            <button
              :class="{ active: activeTab === 'finances' }"
              @click="activeTab = 'finances'"
            >
              💰 Finances & Caution
            </button>
            <button
              :class="{ active: activeTab === 'documents' }"
              @click="activeTab = 'documents'"
            >
              📄 Documents
            </button>
            <button
              :class="{ active: activeTab === 'messages' }"
              @click="activeTab = 'messages'"
            >
              💬 Messagerie
            </button>
          </div>

          <div
            v-if="store.isLoadingDetails"
            class="pcr-modal-body pcr-loader-container"
          >
            <div class="pcr-spinner"></div>
            <p>Chargement du dossier complet...</p>
          </div>

          <div v-else-if="store.reservationDetails" class="pcr-modal-body">
            <div v-if="activeTab === 'details'" class="pcr-tab-content">
              <div class="pcr-grid-2">
                <div class="pcr-card-section">
                  <h3>👤 Informations Client</h3>
                  <p>
                    <strong>Nom :</strong>
                    {{ store.selectedReservation.client }}
                  </p>
                  <p>
                    <strong>Email :</strong>
                    <a
                      :href="'mailto:' + store.reservationDetails.client_email"
                      >{{
                        store.reservationDetails.client_email || "Non renseigné"
                      }}</a
                    >
                  </p>
                  <p>
                    <strong>Téléphone :</strong>
                    <a :href="'tel:' + store.reservationDetails.client_phone">{{
                      store.reservationDetails.client_phone || "Non renseigné"
                    }}</a>
                  </p>
                  <p>
                    <strong>Langue :</strong>
                    {{
                      store.reservationDetails.client_lang?.toUpperCase() ||
                      "FR"
                    }}
                  </p>
                </div>

                <div class="pcr-card-section">
                  <h3>
                    {{
                      store.selectedReservation.type === "location"
                        ? "🏠 Logement"
                        : "🌴 Expérience"
                    }}
                  </h3>
                  <p>
                    <strong>Nom :</strong>
                    {{ store.selectedReservation.item_name }}
                  </p>
                  <p>
                    <strong>Dates :</strong>
                    {{ store.selectedReservation.dates }}
                  </p>
                  <p>
                    <strong>Occupants :</strong>
                    {{ store.reservationDetails.occupants }}
                  </p>
                  <p>
                    <strong>Source :</strong>
                    <span class="badge-source">{{
                      store.reservationDetails.source || "Direct"
                    }}</span>
                  </p>
                </div>
              </div>

              <div class="pcr-card-section mt-15">
                <h3>📝 Notes & Commentaires</h3>
                <p>
                  <strong>Message client :</strong><br />
                  <span class="text-block">{{
                    store.reservationDetails.client_message ||
                    "Aucun message du client."
                  }}</span>
                </p>
                <hr />
                <p>
                  <strong>Notes internes :</strong><br />
                  <span class="text-block">{{
                    store.reservationDetails.notes_internes ||
                    "Aucune note interne."
                  }}</span>
                </p>
              </div>
            </div>

            <div v-if="activeTab === 'finances'" class="pcr-tab-content">
              <div class="pcr-card-section alert-info">
                <strong
                  >Montant Total :
                  {{
                    formatPrice(store.reservationDetails.montant_total)
                  }}</strong
                >
                | Payé :
                {{ formatPrice(store.reservationDetails.total_paye) }} | Dû :
                <strong>{{
                  formatPrice(store.reservationDetails.total_du)
                }}</strong>
              </div>

              <div class="pcr-grid-2 mt-15">
                <div class="pcr-card-section">
                  <h3>Détail du Devis</h3>
                  <ul
                    v-if="
                      store.reservationDetails.quote_lines &&
                      store.reservationDetails.quote_lines.length > 0
                    "
                    class="pcr-quote-list"
                  >
                    <li
                      v-for="(line, index) in store.reservationDetails
                        .quote_lines"
                      :key="index"
                      :class="{ 'quote-separator': isSeparator(line) }"
                    >
                      <span v-if="isSeparator(line)" class="separator-text">{{
                        line.label
                      }}</span>
                      <template v-else>
                        <span class="quote-label">{{ line.label }}</span>
                        <strong class="quote-price">{{
                          line.price || (line.amount ? line.amount + " €" : "")
                        }}</strong>
                      </template>
                    </li>
                  </ul>
                  <p v-else class="text-muted">
                    Aucun détail de devis enregistré.
                  </p>
                </div>

                <div class="pcr-card-section">
                  <h3>Échéancier de paiements</h3>
                  <table
                    v-if="
                      store.reservationDetails.payments &&
                      store.reservationDetails.payments.length > 0
                    "
                    class="pcr-table-minimal"
                  >
                    <thead>
                      <tr>
                        <th>Type</th>
                        <th>Montant</th>
                        <th>Échéance</th>
                        <th>Statut</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="pay in store.reservationDetails.payments"
                        :key="pay.id"
                      >
                        <td>{{ pay.type_paiement }}</td>
                        <td>{{ formatPrice(pay.montant) }}</td>
                        <td>{{ formatDate(pay.date_echeance) }}</td>
                        <td>
                          <span
                            class="badge-status"
                            :class="'status-pay-' + pay.statut"
                            >{{ pay.statut.replace(/_/g, " ") }}</span
                          >
                        </td>
                      </tr>
                    </tbody>
                  </table>
                  <p v-else class="text-muted">Aucun paiement enregistré.</p>
                </div>
              </div>

              <div
                v-if="store.selectedReservation.type === 'location'"
                class="pcr-card-section mt-15"
              >
                <h3>🛡️ Caution (Empreinte Bancaire)</h3>
                <div
                  v-if="store.reservationDetails.caution.mode !== 'aucune'"
                  class="caution-flex"
                >
                  <div>
                    <strong>Montant :</strong>
                    {{ formatPrice(store.reservationDetails.caution.montant)
                    }}<br />
                    <strong>Statut :</strong>
                    <span
                      class="badge-status"
                      style="background: #f3f4f6; color: #4b5563"
                      >{{
                        store.reservationDetails.caution.statut.replace(
                          /_/g,
                          " ",
                        )
                      }}</span
                    >
                  </div>
                  <div class="caution-actions">
                    <button class="pc-btn-outline" style="color: blue">
                      🔗 Lien caution
                    </button>
                    <button class="pc-btn-outline" style="color: green">
                      Libérer
                    </button>
                    <button class="pc-btn-outline" style="color: red">
                      Encaisser
                    </button>
                  </div>
                </div>
                <p v-else class="text-muted">
                  Pas de gestion de caution pour cette réservation.
                </p>
              </div>
            </div>

            <div v-if="activeTab === 'documents'" class="pcr-tab-content">
              <div class="pcr-card-section text-center p-30">
                <h3>Génération PDF</h3>
                <p class="text-muted">
                  Ce module sera migré lors de la Phase 4 !
                </p>
              </div>
            </div>

            <div v-if="activeTab === 'messages'" class="pcr-tab-content">
              <div class="pcr-card-section text-center p-30">
                <h3>Channel Manager</h3>
                <p class="text-muted">
                  Ce module sera migré lors de la Phase 3 !
                </p>
              </div>
            </div>
          </div>
        </template>

        <footer class="pcr-modal-footer">
          <div class="footer-actions-left">
            <template v-if="!isCreateMode">
              <button
                v-if="
                  [
                    'devis',
                    'en_attente_traitement',
                    'brouillon',
                    'sur_devis',
                  ].includes(store.selectedReservation?.statut_reservation)
                "
                class="btn-success"
                @click="handleConfirm(store.selectedReservation?.id)"
                :disabled="store.isLoading"
              >
                {{ store.isLoading ? "..." : "✅ Confirmer" }}
              </button>

              <button
                v-if="
                  !['annulée', 'annulee', 'refusee'].includes(
                    store.selectedReservation?.statut_reservation,
                  )
                "
                class="btn-danger"
                @click="handleCancel(store.selectedReservation?.id)"
                :disabled="store.isLoading"
              >
                {{ store.isLoading ? "..." : "❌ Annuler" }}
              </button>
            </template>

            <button v-if="isCreateMode" class="btn-success">
              💾 Enregistrer la réservation
            </button>
          </div>
          <button class="btn-secondary" @click="closeModal">Fermer</button>
        </footer>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from "vue";
import { useReservationsStore } from "../../stores/reservations-store";

const store = useReservationsStore();
const activeTab = ref("details");

// Les variables de mode création ne sont plus utiles car BookingForm.vue prend le relais !
const isCreateMode = ref(false);
const forceOpen = ref(false);

const closeModal = () => {
  store.closeDetailModal();
  forceOpen.value = false;
};

// Helpers de formatage existants
const formatPrice = (val) => {
  return parseFloat(val || 0).toLocaleString("fr-FR", {
    style: "currency",
    currency: "EUR",
  });
};

const formatDate = (dateString) => {
  if (!dateString) return "-";
  const d = new Date(dateString);
  return d.toLocaleDateString("fr-FR");
};

const isSeparator = (line) => {
  return line.is_separator || line.isSeparator || line.type === "separator";
};

// Actions API
const handleConfirm = async (id) => {
  if (window.confirm("Voulez-vous vraiment confirmer cette réservation ?")) {
    await store.confirmReservation(id);
  }
};

const handleCancel = async (id) => {
  if (
    window.confirm(
      "Êtes-vous sûr de vouloir annuler cette réservation ? Cette action est irréversible.",
    )
  ) {
    await store.cancelReservation(id);
  }
};
</script>

<style scoped>
/* Base Overlay */
.pcr-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(2px);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 9999;
}
.pcr-modal-large {
  width: 95%;
  max-width: 900px;
  max-height: 90vh;
}
.pcr-modal-content {
  background: #f8f9fa;
  border-radius: 8px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* Header & Tabs */
.pcr-modal-header {
  background: white;
  padding: 20px 25px;
  border-bottom: 1px solid #eee;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}
.header-titles h2 {
  margin: 0 0 10px 0;
  font-size: 1.4rem;
  color: #333;
}
.pcr-status-row {
  display: flex;
  gap: 8px;
}
.header-actions {
  display: flex;
  align-items: center;
  gap: 15px;
}

.pcr-modal-tabs {
  display: flex;
  background: white;
  border-bottom: 1px solid #ddd;
  padding: 0 25px;
}
.pcr-modal-tabs button {
  background: none;
  border: none;
  padding: 15px 20px;
  font-weight: 600;
  color: #666;
  cursor: pointer;
  border-bottom: 3px solid transparent;
  transition: all 0.2s;
}
.pcr-modal-tabs button:hover {
  color: #000;
}
.pcr-modal-tabs button.active {
  color: #004085;
  border-bottom-color: #004085;
}

/* Loader */
.pcr-loader-container {
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  min-height: 300px;
}
.pcr-spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #cbd5e1;
  border-top-color: #2563eb;
  border-radius: 50%;
  animation: pcr-spin 1s linear infinite;
  margin-bottom: 15px;
}
@keyframes pcr-spin {
  to {
    transform: rotate(360deg);
  }
}

/* Body & Sections */
.pcr-modal-body {
  padding: 25px;
  overflow-y: auto;
  background: #f4f6f8;
  flex-grow: 1;
}
.pcr-grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}
.pcr-card-section {
  background: white;
  padding: 20px;
  border-radius: 6px;
  border: 1px solid #e2e8f0;
}
.pcr-card-section h3 {
  margin: 0 0 15px 0;
  font-size: 1.1rem;
  color: #1e293b;
  border-bottom: 1px solid #eee;
  padding-bottom: 8px;
}
.pcr-card-section p {
  margin: 0 0 8px 0;
  font-size: 0.95rem;
  color: #334155;
}
.pcr-card-section a {
  color: #2563eb;
  text-decoration: none;
}
.pcr-card-section a:hover {
  text-decoration: underline;
}
.text-muted {
  color: #94a3b8;
  font-style: italic;
}
.text-block {
  display: block;
  padding: 10px;
  background: #f8fafc;
  border-radius: 4px;
  margin-top: 5px;
  white-space: pre-wrap;
  font-size: 0.9em;
}
.mt-15 {
  margin-top: 15px;
}
.p-30 {
  padding: 30px;
}
.text-center {
  text-align: center;
}
hr {
  border: 0;
  border-top: 1px solid #eee;
  margin: 15px 0;
}

/* Utilitaires visuels */
.alert-info {
  background: #e0f2fe;
  border-color: #bae6fd;
  color: #0369a1;
  font-size: 1.1rem;
  text-align: center;
}
.pc-btn-outline {
  background: white;
  border: 1px solid #cbd5e1;
  padding: 6px 12px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.85em;
  font-weight: bold;
}
.badge-source {
  background: #f1f5f9;
  padding: 3px 8px;
  border-radius: 4px;
  font-weight: bold;
  font-size: 0.85em;
  text-transform: capitalize;
}

/* Tableaux (Paiements) & Devis */
.pcr-table-minimal {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9em;
}
.pcr-table-minimal th,
.pcr-table-minimal td {
  padding: 8px;
  text-align: left;
  border-bottom: 1px solid #eee;
}
.pcr-table-minimal th {
  color: #64748b;
  font-weight: 600;
}

.pcr-quote-list {
  list-style: none;
  padding: 0;
  margin: 0;
}
.pcr-quote-list li {
  display: flex;
  justify-content: space-between;
  padding: 6px 0;
  border-bottom: 1px dashed #eee;
  font-size: 0.9em;
}
.quote-separator {
  background: #f8fafc;
  font-weight: bold;
  color: #0f172a;
  margin-top: 10px;
  padding: 8px !important;
  border-bottom: none !important;
}
.quote-price {
  font-weight: bold;
  color: #334155;
}

.caution-flex {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.caution-actions {
  display: flex;
  gap: 8px;
}

/* Footer */
.pcr-modal-footer {
  padding: 15px 25px;
  border-top: 1px solid #eee;
  background: white;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.footer-actions-left {
  display: flex;
  gap: 10px;
}
.btn-success {
  padding: 8px 15px;
  background: #28a745;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-weight: 500;
}
.btn-danger {
  padding: 8px 15px;
  background: #dc3545;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-weight: 500;
}
.btn-secondary {
  padding: 8px 15px;
  background: #6c757d;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}
.btn-close {
  background: none;
  border: none;
  font-size: 1.8rem;
  cursor: pointer;
  color: #999;
  line-height: 1;
}

/* STATUTS */
.badge-status {
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 0.75em;
  font-weight: bold;
  text-transform: capitalize;
}
.status-resa-confirmée,
.status-resa-confirmee {
  background: #d4edda;
  color: #155724;
  border: 1px solid #c3e6cb;
}
.status-resa-validee {
  background: #e2f3eb;
  color: #1e7e34;
  border: 1px solid #d4edda;
}
.status-resa-terminee {
  background: #e9ecef;
  color: #495057;
  border: 1px solid #dee2e6;
}
.status-resa-en_attente_traitement {
  background: #ffeeba;
  color: #856404;
  border: 1px solid #ffe8a1;
}
.status-resa-brouillon {
  background: #f8f9fa;
  color: #6c757d;
  border: 1px dashed #ced4da;
}
.status-resa-sur_devis {
  background: #e2d9f3;
  color: #4a2b82;
  border: 1px solid #d1c4e9;
}
.status-resa-annulée,
.status-resa-annulee {
  background: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
}
.status-resa-refusee {
  background: #dc3545;
  color: #ffffff;
  border: 1px solid #bd2130;
}
.status-pay-paye,
.status-pay-solde {
  background: #d4edda;
  color: #155724;
  border: 1px solid #c3e6cb;
}
.status-pay-partiellement_paye,
.status-pay-acompte_paye,
.status-pay-acompte {
  background: #cce5ff;
  color: #004085;
  border: 1px solid #b8daff;
}
.status-pay-sur_devis {
  background: #ffeeba;
  color: #856404;
  border: 1px solid #ffe8a1;
}
.status-pay-non_paye {
  background: #ffdada;
  color: #900000;
  border: 1px solid #ffc0c0;
}
.status-pay-en_attente_paiement,
.status-pay-en_attente {
  background: #fff3cd;
  color: #856404;
  border: 1px solid #ffeeba;
}
.status-pay-rembourse {
  background: #fcf0f2;
  color: #a71d2a;
  border: 1px solid #f9e1e5;
}
.status-pay-echoue {
  background: #9c1a1c;
  color: #ffffff;
  border: 1px solid #7a1516;
}
</style>
