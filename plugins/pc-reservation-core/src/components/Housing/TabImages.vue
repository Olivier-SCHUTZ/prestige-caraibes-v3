<template>
  <div class="v2-tab-content">
    <h4 class="pc-section-title">Images Principales (Hero)</h4>
    <div class="pc-form-grid">
      <div class="pc-form-group pc-hero-wrapper">
        <label>Image Desktop (Haute résolution)</label>
        <WpMediaUploader
          v-model="modalStore.formData.hero_desktop_url"
          buttonText="Choisir l'image Desktop"
        />
      </div>

      <div class="pc-form-group pc-hero-wrapper">
        <label>Image Mobile (Format vertical)</label>
        <WpMediaUploader
          v-model="modalStore.formData.hero_mobile_url"
          buttonText="Choisir l'image Mobile"
        />
      </div>
    </div>

    <h4 class="pc-section-title">Liens Médias Externes</h4>
    <div class="pc-form-grid">
      <div class="pc-form-group pc-form-group--full">
        <label>URLs de la galerie (Une URL par ligne)</label>
        <textarea
          v-model="modalStore.formData.gallery_urls"
          class="pc-textarea"
          rows="4"
          placeholder="https://..."
        ></textarea>
      </div>

      <div class="pc-form-group pc-form-group--full">
        <label>URLs des vidéos (Une URL par ligne)</label>
        <textarea
          v-model="modalStore.formData.video_urls"
          class="pc-textarea"
          rows="3"
          placeholder="https://youtube.com/..."
        ></textarea>
      </div>

      <div class="pc-form-group pc-form-group--full">
        <label>URLs Galerie SEO (Une URL par ligne)</label>
        <textarea
          v-model="modalStore.formData.seo_gallery_urls"
          class="pc-textarea"
          rows="3"
          placeholder="https://..."
        ></textarea>
      </div>
    </div>

    <h4 class="pc-section-title">
      Groupes d'images (Pièces)
      <button
        class="pc-btn pc-btn-sm pc-btn-secondary"
        style="float: right"
        @click.prevent="addGroup"
      >
        <span>➕</span> Ajouter une pièce
      </button>
    </h4>

    <div class="pc-repeater-container">
      <div
        v-if="
          !modalStore.formData.groupes_images ||
          modalStore.formData.groupes_images.length === 0
        "
        class="pc-empty-state"
      >
        <p>Aucun groupe d'images défini pour ce logement.</p>
      </div>

      <div
        v-for="(group, index) in modalStore.formData.groupes_images"
        :key="index"
        class="pc-repeater-item"
      >
        <div class="pc-repeater-header">
          <h5>Groupe #{{ index + 1 }}</h5>
          <button
            class="pc-btn-icon-danger"
            @click.prevent="removeGroup(index)"
            title="Supprimer ce groupe"
          >
            🗑️
          </button>
        </div>

        <div class="pc-form-grid">
          <div class="pc-form-group">
            <label>Catégorie</label>
            <select v-model="group.categorie" class="pc-select">
              <option value="salon">Salon</option>
              <option value="cuisine">Cuisine</option>
              <option value="chambre_1">Chambre 1</option>
              <option value="salle_de_bain">Salle de Bain</option>
              <option value="terrasse">Terrasse</option>
              <option value="piscine">Piscine</option>
              <option value="exterieur">Extérieur</option>
              <option value="vue">Vue</option>
              <option value="autre">Autre...</option>
            </select>
          </div>

          <div class="pc-form-group" v-if="group.categorie === 'autre'">
            <label>Titre personnalisé</label>
            <input
              type="text"
              v-model="group.categorie_personnalisee"
              class="pc-input"
              placeholder="Ex: Salle de sport"
            />
          </div>

          <div class="pc-form-group pc-form-group--full">
            <label>Images de cette pièce</label>
            <WpGalleryUploader v-model="group.images_du_groupe" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useHousingModalStore } from "../../stores/housing-modal-store.js";
// Importation de ton composant Media (Ajuste le chemin si nécessaire)
import WpMediaUploader from "../../components/common/WpMediaUploader.vue";
import WpGalleryUploader from "../../components/common/WpGalleryUploader.vue";

const modalStore = useHousingModalStore();

// Initialiser le tableau du repeater s'il n'existe pas
if (
  !modalStore.formData.groupes_images ||
  !Array.isArray(modalStore.formData.groupes_images)
) {
  modalStore.formData.groupes_images = [];
}

// Fonction pour ajouter un groupe vide
const addGroup = () => {
  modalStore.formData.groupes_images.push({
    categorie: "salon",
    categorie_personnalisee: "",
    images_du_groupe: "", // Sera un array d'IDs ou un string d'IDs séparés par virgules selon ton WpMediaUploader
  });
};

// Fonction pour supprimer un groupe
const removeGroup = (index) => {
  if (confirm("Supprimer ce groupe d'images ?")) {
    modalStore.formData.groupes_images.splice(index, 1);
  }
};
</script>

<style scoped>
.pc-section-title {
  margin-top: 1.5rem;
  margin-bottom: 1rem;
  font-size: 1.1rem;
  color: #1e293b;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 0.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
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
.pc-select,
.pc-textarea {
  padding: 0.75rem 1rem;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  background: white;
  font-size: 0.95rem;
  font-family: inherit;
  box-sizing: border-box;
  height: auto !important;
  line-height: 1.5;
}

.pc-textarea {
  resize: vertical;
}

/* Styles du Repeater */
.pc-repeater-container {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.pc-repeater-item {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 1.5rem;
}

.pc-repeater-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 0.5rem;
}

.pc-repeater-header h5 {
  margin: 0;
  font-size: 1rem;
  color: #334155;
}

.pc-btn-icon-danger {
  background: transparent;
  border: none;
  cursor: pointer;
  font-size: 1.1rem;
  color: #ef4444;
}

.pc-empty-state {
  text-align: center;
  padding: 2rem;
  background: #f1f5f9;
  border-radius: 8px;
  color: #64748b;
  border: 2px dashed #cbd5e1;
}

.pc-btn-sm {
  padding: 0.4rem 0.8rem;
  font-size: 0.85rem;
  border-radius: 6px;
}

/* Limiter la taille des composants Media Hero */
.pc-hero-wrapper {
  max-width: 300px; /* Réduit la largeur du conteneur parent */
}

/* Si tu veux cibler l'image interne de ton WpMediaUploader : */
.pc-hero-wrapper :deep(img) {
  max-height: 150px;
  object-fit: cover;
}
</style>
