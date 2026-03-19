<template>
  <div class="pc-doc-list pcr-card-section">
    <h3>📁 Documents enregistrés</h3>

    <div v-if="loading" class="pcr-loader-container" style="min-height: 100px">
      <div class="pcr-spinner"></div>
    </div>

    <div v-else-if="documents.length === 0" class="text-muted text-center p-30">
      Aucun document généré pour le moment.
    </div>

    <table v-else class="pcr-table-minimal">
      <thead>
        <tr>
          <th>Nom du fichier</th>
          <th>Type</th>
          <th>Date de création</th>
          <th style="text-align: right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="doc in documents" :key="doc.nom_fichier">
          <td>
            <strong>{{ doc.nom_fichier || doc.name }}</strong>
          </td>
          <td>
            <span class="badge-source">{{ doc.type_doc || "PDF" }}</span>

            <span
              v-if="getDocumentStatus(doc)"
              :class="['pc-doc-status', getDocumentStatus(doc).class]"
            >
              {{ getDocumentStatus(doc).label }}
            </span>
          </td>
          <td>
            {{ formatDate(doc.created || doc.date_creation || doc.date) }}
          </td>
          <td style="text-align: right">
            <div class="action-buttons">
              <a
                :href="doc.secure_download_url || doc.url"
                target="_blank"
                class="pc-btn-outline"
                title="Aperçu dans le navigateur"
              >
                👁️ Ouvrir
              </a>
              <a
                :href="(doc.secure_download_url || doc.url) + '&download=1'"
                :download="doc.filename"
                class="pc-btn-outline"
                title="Télécharger le fichier"
              >
                ⬇️ Télécharger
              </a>

              <button
                v-if="!isProtectedDocument(doc)"
                class="pc-btn-outline text-danger"
                @click="$emit('delete', doc.id)"
                title="Supprimer"
              >
                🗑️
              </button>

              <span
                v-else
                class="pc-protected-badge"
                title="Document comptable protégé (Non supprimable)"
              >
                🔒 Légal
              </span>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
const props = defineProps({
  documents: { type: Array, required: true },
  loading: { type: Boolean, default: false },
});

defineEmits(["delete"]);

// Vérification légale (Version Blindée) : empêche la suppression des factures/avoirs
const isProtectedDocument = (doc) => {
  const type = String(doc.type_doc || doc.type || "").toLowerCase();
  const name = String(doc.nom_fichier || doc.name || "").toLowerCase();
  return (
    type.includes("facture") ||
    type.includes("avoir") ||
    name.includes("facture") ||
    name.includes("avoir") ||
    name.includes("fac-")
  );
};

// Préparation du terrain : Gestion des statuts de paiement pour les documents comptables
const getDocumentStatus = (doc) => {
  if (!isProtectedDocument(doc)) return null;

  const type = String(doc.type_doc || doc.type || "").toLowerCase();
  const name = String(doc.nom_fichier || doc.name || "").toLowerCase();
  const status = doc.statut_paiement || doc.status || "en_cours";

  // 1. Détection des Avoirs
  if (
    type.includes("avoir") ||
    name.includes("avoir") ||
    name.includes("avo-")
  ) {
    return { label: "Émis", class: "badge-status-info" };
  }

  // 2. Famille du document (Acompte vs Finale)
  const getDocumentFamily = (document) => {
    const t = String(document.type_doc || document.type || "").toLowerCase();
    const n = String(document.nom_fichier || document.name || "").toLowerCase();
    if (t.includes("acompte") || n.includes("acompte"))
      return "famille_acompte";
    if (t.includes("facture") || n.includes("fac-"))
      return "famille_facture_finale";
    return "autre";
  };

  const myFamily = getDocumentFamily(doc);
  let isCanceled = false;

  // 3. Détection mathématique absolue par les Dates (format FR)
  if (type.includes("archive") || type.includes("archived")) {
    isCanceled = true;
  } else if (myFamily !== "autre" && props.documents) {
    // Fonction magique pour transformer "19/03/2026 08:15" en vrai Timestamp mesurable
    const parseDateFR = (dateStr) => {
      if (!dateStr) return 0;
      const parts = dateStr.split(/[\s/:]+/); // Coupe la chaîne sur les espaces, / et :
      if (parts.length >= 5) {
        // parts = [Jour, Mois, Année, Heure, Minute] -> Mois commence à 0 en JS
        return new Date(
          parts[2],
          parts[1] - 1,
          parts[0],
          parts[3],
          parts[4],
        ).getTime();
      }
      return new Date(dateStr).getTime() || 0;
    };

    const myTimestamp = parseDateFR(
      doc.created || doc.date_creation || doc.date,
    );

    // On regarde si un autre document de la MÊME FAMILLE a un Timestamp PLUS GRAND
    const hasNewer = props.documents.some((otherDoc) => {
      if (otherDoc === doc) return false; // On ne se compare pas à soi-même
      if (getDocumentFamily(otherDoc) !== myFamily) return false; // Uniquement la même famille

      const otherTimestamp = parseDateFR(
        otherDoc.created || otherDoc.date_creation || otherDoc.date,
      );
      return otherTimestamp > myTimestamp;
    });

    if (hasNewer) {
      isCanceled = true;
    }
  }

  if (isCanceled) {
    return { label: "Annulée (Avoir)", class: "badge-status-danger" };
  }

  // 4. Statuts normaux
  switch (status) {
    case "regle_stripe":
    case "paid_stripe":
      return { label: "Réglé (Stripe)", class: "badge-status-success" };
    case "regle_virement":
    case "paid_bank":
      return { label: "Réglé (Virement)", class: "badge-status-success" };
    case "en_cours":
    case "pending":
    default:
      return { label: "En cours", class: "badge-status-warning" };
  }
};

const formatDate = (dateString) => {
  if (!dateString) return "-";
  return new Date(dateString).toLocaleDateString("fr-FR", {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};
</script>

<style scoped>
.action-buttons {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
}
.text-danger {
  color: #dc3545;
  border-color: #dc3545;
}
.text-danger:hover {
  background: #f8d7da;
}
a.pc-btn-outline {
  text-decoration: none;
  display: inline-block;
  color: #333;
}
.pc-protected-badge {
  display: inline-flex;
  align-items: center;
  padding: 6px 10px;
  background: #f8f9fa;
  color: #64748b;
  border: 1px solid #e2e8f0;
  border-radius: 4px;
  font-size: 0.85em;
  font-weight: bold;
  cursor: not-allowed;
}
/* Styles pour les statuts comptables */
.pc-doc-status {
  font-size: 0.75em;
  padding: 4px 8px;
  border-radius: 12px;
  margin-left: 8px;
  font-weight: 600;
  display: inline-block;
  white-space: nowrap;
}
.badge-status-success {
  background-color: #dcfce7;
  color: #166534;
  border: 1px solid #bbf7d0;
}
.badge-status-warning {
  background-color: #fef08a;
  color: #854d0e;
  border: 1px solid #fde047;
}
.badge-status-danger {
  background-color: #fee2e2;
  color: #991b1b;
  border: 1px solid #fecaca;
}
.badge-status-info {
  background-color: #e0f2fe;
  color: #075985;
  border: 1px solid #bae6fd;
}
</style>
