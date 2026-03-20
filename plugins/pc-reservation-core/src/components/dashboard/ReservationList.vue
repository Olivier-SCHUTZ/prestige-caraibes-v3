<template>
  <div class="pcr-reservation-list-container">
    <div
      style="
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
      "
    >
      <h2 style="margin: 0">Dernières Réservations</h2>
      <button
        @click="store.openCreateModal()"
        style="
          padding: 10px 20px;
          background: #000;
          color: #fff;
          border: none;
          border-radius: 4px;
          cursor: pointer;
          font-weight: bold;
        "
      >
        + Créer une réservation
      </button>
    </div>

    <div class="pcr-filters-bar">
      <div class="filter-group">
        <span class="filter-label">Filtrer par type :</span>
        <button
          :class="['filter-btn', { active: store.filters.type === 'all' }]"
          @click="store.setFilter('type', 'all')"
        >
          Tout
        </button>
        <button
          :class="['filter-btn', { active: store.filters.type === 'location' }]"
          @click="store.setFilter('type', 'location')"
        >
          🏠 Logements
        </button>
        <button
          :class="[
            'filter-btn',
            { active: store.filters.type === 'experience' },
          ]"
          @click="store.setFilter('type', 'experience')"
        >
          🌴 Expériences
        </button>
      </div>
    </div>

    <div v-if="store.isLoading" class="pcr-loader">
      Chargement des réservations...
    </div>

    <div
      v-else-if="store.error"
      style="
        color: red;
        padding: 15px;
        background: #ffe6e6;
        border-radius: 6px;
        margin-bottom: 15px;
        font-weight: bold;
      "
    >
      🚨 Erreur API : {{ store.error }}
    </div>

    <table v-else class="pcr-table">
      <thead>
        <tr>
          <th style="width: 5%;">ID</th>
          <th style="width: 16%;">Client</th>
          <th style="width: 12%;">Type</th>
          <th style="width: 22%;">Logement / Expérience</th> <th style="width: 22%;">Dates</th>                 <th style="width: 13%;">Montant</th>               <th style="width: 10%;" class="text-right">Actions</th>
        </tr>
      </thead>
      
      <tbody
        v-for="(resa, index) in store.items"
        :key="resa.id"
        class="resa-group"
        :style="index % 2 === 0 ? 'background-color: #ffffff;' : 'background-color: #f8fafc;'"
      >
        <tr class="info-row">
          <td style="font-weight: 600; color: #64748b;">#{{ resa.id }}</td>
          <td style="font-weight: bold; color: #1e293b;">{{ resa.client }}</td>
          <td>
            <span class="badge-type" :title="resa.type === 'location' ? 'Location' : 'Expérience'">
              {{ resa.type === "location" ? "🏠 Location" : "🌴 Expérience" }}
            </span>
          </td>
          <td style="color: #334155;">{{ resa.item_name }}</td>
          <td style="color: #475569;">{{ resa.dates }}</td>
          <td style="font-weight: bold; color: #0f172a;">{{ resa.montant }} €</td>
          
          <td rowspan="2" style="text-align: right; vertical-align: middle; padding-right: 15px;">
            <button class="btn-action" @click="store.openDetailModal(resa)">
              Voir
            </button>
          </td>
        </tr>

        <tr class="status-row">
          <td colspan="6" style="padding-top: 0; padding-bottom: 16px; border-bottom: none;">
            <div class="pcr-status-group-horizontal">
              <span v-if="resa.statut_reservation" class="badge-status" :class="'status-resa-' + resa.statut_reservation">
                <span class="status-icon">📅</span> {{ resa.statut_reservation.replace(/_/g, " ") }}
              </span>
              <span v-if="resa.statut_paiement" class="badge-status" :class="'status-pay-' + resa.statut_paiement">
                <span class="status-icon">{{ (resa.statut_paiement === 'partiellement_paye_sur_place' || resa.statut_paiement === 'sur_place') ? '⚠️' : '💳' }}</span> {{ resa.statut_paiement.replace(/_/g, " ") }}
              </span>
              <span v-if="resa.caution_statut" class="badge-status" :class="'status-caution-' + resa.caution_statut">
                <span class="status-icon">🔒</span> {{ resa.caution_statut.replace(/_/g, " ") }}
              </span>
            </div>
          </td>
        </tr>
      </tbody>

      <tbody v-if="store.items.length === 0">
        <tr>
          <td colspan="7" class="text-center" style="padding: 30px;">Aucune réservation trouvée.</td>
        </tr>
      </tbody>
    </table>

    <div v-if="store.totalPages > 1" class="pcr-pagination">
      <button
        :disabled="store.currentPage === 1 || store.isLoading"
        @click="store.fetchList(store.currentPage - 1)"
        class="btn-page"
      >
        &laquo; Précédent
      </button>

      <span class="page-info">
        Page {{ store.currentPage }} sur {{ store.totalPages }}
        <small>({{ store.totalItems }} réservations)</small>
      </span>

      <button
        :disabled="store.currentPage === store.totalPages || store.isLoading"
        @click="store.fetchList(store.currentPage + 1)"
        class="btn-page"
      >
        Suivant &raquo;
      </button>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from "vue";
import { useReservationsStore } from "../../stores/reservations-store";

const store = useReservationsStore();

onMounted(() => {
  // On charge la liste au montage du composant
  store.fetchList();
});
</script>

<style scoped>
.pcr-reservation-list-container {
  margin-top: 30px;
  background: #fff;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
.pcr-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 15px;
  background: white;
}
.pcr-table th {
  padding: 16px 12px;
  text-align: left;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #64748b;
  border-bottom: 2px solid #e2e8f0;
  background-color: #f8fafc;
}
.pcr-table td {
  padding: 16px 12px;
  vertical-align: middle;
  border-bottom: 1px solid #f1f5f9;
  color: #334155;
}
/* --- DESIGN ZÉBRÉ & SURVOL --- */
.resa-group {
  transition: all 0.2s ease;
  border-bottom: 2px solid #e2e8f0;
}
.resa-group:hover {
  box-shadow: inset 4px 0 0 0 #3b82f6;
}
.info-row td {
  border-bottom: none !important;
  padding-bottom: 6px !important;
}
.status-row td {
  border-bottom: none !important;
}

/* --- DESIGN DES STATUTS HORIZONTAUX --- */
.pcr-status-group-horizontal {
  display: flex;
  flex-direction: row;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
  margin-top: 4px;
}
.badge-status {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 9999px;
  font-size: 0.7rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  line-height: 1;
  white-space: nowrap;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
  transition: transform 0.2s;
}
.badge-status:hover {
  transform: translateY(-1px);
}
.status-icon {
  font-style: normal;
  font-size: 1.1em;
}
/* =========================================
   🎨 PALETTE DES STATUTS (Hautement distincte)
   ========================================= */

/* === STATUTS RÉSERVATION === */
/* Confirmée (Vert émeraude) */
.status-resa-confirmée,
.status-resa-confirmee {
  background: #d4edda;
  color: #155724;
  border: 1px solid #c3e6cb;
}
/* Validée (Vert menthe clair) */
.status-resa-validee {
  background: #e2f3eb;
  color: #1e7e34;
  border: 1px solid #d4edda;
}
/* Terminée (Gris neutre) */
.status-resa-terminee {
  background: #e9ecef;
  color: #495057;
  border: 1px solid #dee2e6;
}
/* En Attente Traitement (Orange vif) */
.status-resa-en_attente_traitement {
  background: #ffeeba;
  color: #856404;
  border: 1px solid #ffe8a1;
}
/* Brouillon (Gris clair rayé ou pointillé) */
.status-resa-brouillon {
  background: #f8f9fa;
  color: #6c757d;
  border: 1px dashed #ced4da;
}
/* Sur Devis (Violet pastel) */
.status-resa-sur_devis {
  background: #e2d9f3;
  color: #4a2b82;
  border: 1px solid #d1c4e9;
}
/* Annulée (Rouge clair) */
.status-resa-annulée,
.status-resa-annulee {
  background: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
}
/* Refusée (Rouge sombre) */
.status-resa-refusee {
  background: #dc3545;
  color: #ffffff;
  border: 1px solid #bd2130;
}

/* === STATUTS PAIEMENT === */
/* Payé (Vert) */
.status-pay-paye,
.status-pay-solde {
  background: #d4edda;
  color: #155724;
  border: 1px solid #c3e6cb;
}

/* Partiellement Payé / Acompte (Bleu) */
.status-pay-partiellement_paye,
.status-pay-acompte_paye,
.status-pay-acompte {
  background: #cce5ff;
  color: #004085;
  border: 1px solid #b8daff;
}

/* Sur Devis (Orange) */
.status-pay-sur_devis {
  background: #ffeeba;
  color: #856404;
  border: 1px solid #ffe8a1;
}

/* Non Payé (Rose alerte) */
.status-pay-non_paye {
  background: #ffdada;
  color: #900000;
  border: 1px solid #ffc0c0;
}

/* En Attente Paiement (Jaune clair) */
.status-pay-en_attente_paiement,
.status-pay-en_attente {
  background: #fff3cd;
  color: #856404;
  border: 1px solid #ffeeba;
}

/* Remboursé (Magenta clair) */
.status-pay-rembourse {
  background: #fcf0f2;
  color: #a71d2a;
  border: 1px solid #f9e1e5;
}

/* Échoué (Rouge sang) */
.status-pay-echoue {
  background: #9c1a1c;
  color: #ffffff;
  border: 1px solid #7a1516;
}

/* === STATUTS CAUTION === */
/* Demande envoyée (Bleu info) */
.status-caution-demande_envoyee {
  background: #e0f2fe;
  color: #0369a1;
  border: 1px solid #bae6fd;
}

/* Empreinte validée (Vert rassurant) */
.status-caution-empreinte_validee {
  background: #dcfce7;
  color: #15803d;
  border: 1px solid #bbf7d0;
}

/* Libérée (Gris neutre, c'est terminé) */
.status-caution-liberee {
  background: #f3f4f6;
  color: #4b5563;
  border: 1px solid #e5e7eb;
}

/* Encaissée (Rouge vif, cas problématique) */
.status-caution-encaissee {
  background: #fee2e2;
  color: #b91c1c;
  border: 1px solid #fecaca;
  font-weight: 900;
}
/* ========================================= */
/* 🚨 ALERTES PAIEMENT SUR PLACE             */
/* ========================================= */

/* ALERTE : Acompte payé + Solde prévu sur place (Orange vif / ombré) */
.status-pay-partiellement_paye_sur_place {
  background: #ffebd6;
  color: #cc5500;
  border: 2px solid #ff8c00;
  font-weight: 900;
  box-shadow: 0 0 5px rgba(255, 140, 0, 0.4);
}

/* 100% sur place (Jaune/Orange plus classique) */
.status-pay-sur_place {
  background: #fff3cd;
  color: #856404;
  border: 2px solid #ffc107;
  font-weight: bold;
}
.badge-type {
  margin-right: 5px;
}
.btn-action {
  padding: 5px 10px;
  background: #007bff;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}
.btn-action:hover {
  background: #0056b3;
}
.text-center {
  text-align: center;
}
/* Styles pour la pagination */
.pcr-pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 20px;
  padding-top: 15px;
  border-top: 1px solid #eee;
}
.btn-page {
  padding: 8px 15px;
  background: #f8f9fa;
  border: 1px solid #ddd;
  border-radius: 4px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.2s;
}
.btn-page:hover:not(:disabled) {
  background: #e9ecef;
}
.btn-page:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.page-info {
  font-weight: 600;
  color: #555;
}
.page-info small {
  font-weight: normal;
  color: #888;
}
/* Barre de filtres */
.pcr-filters-bar {
  background: #f8f9fa;
  padding: 12px 15px;
  border-radius: 6px;
  border: 1px solid #e2e8f0;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
}
.filter-group {
  display: flex;
  align-items: center;
  gap: 10px;
}
.filter-label {
  font-weight: 600;
  color: #475569;
  margin-right: 5px;
}
.filter-btn {
  padding: 6px 12px;
  background: white;
  border: 1px solid #cbd5e1;
  border-radius: 20px;
  cursor: pointer;
  font-size: 0.9em;
  color: #334155;
  transition: all 0.2s;
}
.filter-btn:hover {
  background: #f1f5f9;
}
.filter-btn.active {
  background: #004085;
  color: white;
  border-color: #004085;
  font-weight: bold;
}
</style>
