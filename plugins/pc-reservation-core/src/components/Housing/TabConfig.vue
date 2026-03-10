<template>
  <div class="v2-tab-content">
    <h4 class="pc-section-title">Publication & Synchronisation</h4>
    <div class="pc-form-grid">
      <div class="pc-form-group">
        <label>Statut du logement</label>
        <select v-model="modalStore.formData.status" class="pc-select">
          <option value="publish">✅ Publié (Visible)</option>
          <option value="draft">📝 Brouillon (Caché)</option>
        </select>
      </div>

      <div class="pc-form-group">
        <label>URL de synchronisation iCal</label>
        <input
          type="url"
          v-model="modalStore.formData.ical_url"
          class="pc-input"
          placeholder="https://..."
        />
      </div>
    </div>

    <h4 class="pc-section-title">Informations Légales & Contrat</h4>
    <div class="pc-form-grid">
      <div class="pc-form-group">
        <label>Identité du propriétaire</label>
        <input
          type="text"
          v-model="modalStore.formData.log_proprietaire_identite"
          class="pc-input"
          placeholder="Nom et Prénom ou Société"
        />
      </div>

      <div class="pc-form-group">
        <label>Capacité Max (Personnes au contrat)</label>
        <input
          type="text"
          v-model="modalStore.formData.personne_logement"
          class="pc-input"
          placeholder="Ex: 4 adultes et 2 enfants"
        />
      </div>

      <div class="pc-form-group pc-form-group--full">
        <label>Adresse du propriétaire</label>
        <textarea
          v-model="modalStore.formData.proprietaire_adresse"
          class="pc-textarea"
          rows="2"
        ></textarea>
      </div>

      <div class="pc-form-group pc-form-group--full">
        <label>Description au contrat</label>
        <textarea
          v-model="modalStore.formData.description_contrat"
          class="pc-textarea"
          rows="3"
        ></textarea>
      </div>

      <div class="pc-form-group pc-form-group--full">
        <label>Équipements listés au contrat</label>
        <textarea
          v-model="modalStore.formData.equipements_contrat"
          class="pc-textarea"
          rows="3"
        ></textarea>
      </div>
    </div>

    <h4 class="pc-section-title">SEO Avancé & Google VR</h4>
    <div class="pc-form-grid">
      <div class="pc-form-group pc-form-group--full">
        <div class="pc-checkbox-group">
          <label class="pc-checkbox-label">
            <input
              type="checkbox"
              v-model="modalStore.formData.log_exclude_sitemap"
            />
            <span>🚫 Exclure du Sitemap XML</span>
          </label>
          <label class="pc-checkbox-label">
            <input type="checkbox" v-model="modalStore.formData.log_http_410" />
            <span
              >🗑️ Forcer l'erreur 410 (Logement définitivement supprimé pour
              Google)</span
            >
          </label>
        </div>
      </div>

      <div class="pc-form-group">
        <label>Balise Meta Title</label>
        <input
          type="text"
          v-model="modalStore.formData.meta_titre"
          class="pc-input"
          placeholder="Titre SEO optimisé"
        />
      </div>

      <div class="pc-form-group">
        <label>Balise Meta Robots</label>
        <select v-model="modalStore.formData.log_meta_robots" class="pc-select">
          <option value="index,follow">Index, Follow (Recommandé)</option>
          <option value="noindex,nofollow">Noindex, Nofollow (Caché)</option>
          <option value="noindex,follow">Noindex, Follow</option>
        </select>
      </div>

      <div class="pc-form-group pc-form-group--full">
        <label>Balise Meta Description</label>
        <textarea
          v-model="modalStore.formData.meta_description"
          class="pc-textarea"
          rows="2"
        ></textarea>
      </div>

      <div class="pc-form-group pc-form-group--full">
        <label>URL Canonique personnalisée</label>
        <input
          type="url"
          v-model="modalStore.formData.url_canonique"
          class="pc-input"
          placeholder="https://..."
        />
      </div>

      <div class="pc-form-group">
        <label>Type de logement (Google VR)</label>
        <select
          v-model="modalStore.formData.google_vr_accommodation_type"
          class="pc-select"
        >
          <option value="EntirePlace">Logement entier (EntirePlace)</option>
          <option value="PrivateRoom">Chambre privée (PrivateRoom)</option>
          <option value="SharedRoom">Chambre partagée (SharedRoom)</option>
        </select>
      </div>

      <div class="pc-form-group pc-form-group--full">
        <label>Équipements Google VR (Checkboxes)</label>
        <div class="pc-checkbox-group-grid">
          <label
            class="pc-checkbox-label"
            v-for="amenity in googleVrAmenities"
            :key="amenity.value"
          >
            <input
              type="checkbox"
              :value="amenity.value"
              v-model="modalStore.formData.google_vr_amenities"
            />
            <span>{{ amenity.label }}</span>
          </label>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useHousingModalStore } from "../../stores/housing-modal-store.js";

const modalStore = useHousingModalStore();

// --- SÉCURITÉ DES DONNÉES ---
// Initialisation des booléens
["log_exclude_sitemap", "log_http_410"].forEach((key) => {
  modalStore.formData[key] =
    modalStore.formData[key] === "1" || modalStore.formData[key] === true;
});

// Initialisation des tableaux de checkboxes
if (
  !modalStore.formData.google_vr_amenities ||
  !Array.isArray(modalStore.formData.google_vr_amenities)
) {
  modalStore.formData.google_vr_amenities = [];
}

// Initialisation des selects par défaut
if (!modalStore.formData.status) modalStore.formData.status = "draft";
if (!modalStore.formData.log_meta_robots)
  modalStore.formData.log_meta_robots = "index,follow";
if (!modalStore.formData.google_vr_accommodation_type)
  modalStore.formData.google_vr_accommodation_type = "EntirePlace";

// Liste des équipements Google VR standards (à ajuster selon tes clés ACF exactes si besoin)
const googleVrAmenities = [
  { value: "hasFreeWifi", label: "Wi-Fi gratuit" },
  { value: "hasAirConditioning", label: "Climatisation" },
  { value: "hasPool", label: "Piscine" },
  { value: "hasParking", label: "Parking" },
  { value: "hasHotTub", label: "Bain à remous / Jacuzzi" },
  { value: "petsAllowed", label: "Animaux acceptés" },
  { value: "smokingAllowed", label: "Fumeurs autorisés" },
  { value: "hasKitchen", label: "Cuisine" },
  { value: "hasWashingMachine", label: "Lave-linge" },
];
</script>

<style scoped>
.pc-section-title {
  margin-top: 3rem;
  margin-bottom: 1.5rem;
  font-size: 1.1rem;
  color: #1e293b;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 0.5rem;
}

.pc-section-title:first-child {
  margin-top: 0;
}

.pc-form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  column-gap: 2rem;
  row-gap: 1.5rem;
  margin-bottom: 2rem;
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

.pc-checkbox-group {
  display: flex;
  gap: 2rem;
  background: #f8fafc;
  padding: 1rem 1.5rem;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.pc-checkbox-group-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  background: #f8fafc;
  padding: 1.5rem;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.pc-checkbox-label {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  cursor: pointer;
  font-weight: normal !important;
  color: #334155;
  font-size: 0.95rem;
}

.pc-checkbox-label input[type="checkbox"] {
  margin-top: 3px;
}
</style>
