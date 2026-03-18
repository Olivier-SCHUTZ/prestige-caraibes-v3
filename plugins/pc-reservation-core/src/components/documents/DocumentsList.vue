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
        <tr v-for="doc in documents" :key="doc.id">
          <td>
            <strong>{{ doc.nom_fichier || doc.name }}</strong>
          </td>
          <td>
            <span class="badge-source">{{ doc.type_doc || "PDF" }}</span>
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
                class="pc-btn-outline text-danger"
                @click="$emit('delete', doc.id)"
                title="Supprimer"
              >
                🗑️
              </button>
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
</style>
