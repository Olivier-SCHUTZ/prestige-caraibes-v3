<template>
  <div class="v2-tab-content">
    <h4 class="pc-section-title">Publication & Synchronisation (Channel Manager)</h4>
    
    <div class="pc-form-group" style="margin-bottom: 1.5rem;">
      <label>Statut du logement</label>
      <select v-model="modalStore.formData.status" class="pc-select" style="max-width: 300px;">
        <option value="publish">✅ Publié (Visible)</option>
        <option value="draft">📝 Brouillon (Caché)</option>
      </select>
    </div>

    <div class="pc-ical-repeater">
      <label class="pc-repeater-label">Flux iCal entrants (Importation)</label>
      <p class="pc-repeater-help">Ajoutez ici les iCals de vos différentes plateformes (Airbnb, Booking...) pour bloquer les dates sur votre site.</p>
      
      <div class="pc-ical-item" v-for="(ical, index) in modalStore.formData.icals_sync" :key="index">
        <div class="pc-ical-inputs">
          <input
            type="text"
            v-model="ical.name"
            class="pc-input"
            placeholder="Nom (ex: Airbnb, Booking...)"
          />
          <input
            type="url"
            v-model="ical.url"
            class="pc-input"
            placeholder="URL du flux iCal (https://...)"
          />
        </div>
        <button type="button" class="pc-btn-remove" @click="removeIcal(index)" title="Supprimer ce flux">
          ❌
        </button>
      </div>
      
      <button type="button" class="pc-btn-add" @click="addIcal">
        ➕ Ajouter un flux iCal
      </button>
    </div>

    <!-- 🚀 NOUVEAU BLOC : EXPORT ICAL -->
    <div class="pc-ical-export-box" v-if="modalStore.formData.id">
      <label class="pc-repeater-label">Flux iCal de sortie (Exportation)</label>
      <p class="pc-repeater-help">Copiez ces liens pour synchroniser votre site vers les autres plateformes. Cliquez sur le lien pour le copier.</p>
      
      <div class="pc-export-item">
        <div class="pc-export-info">
          <strong>🔒 iCal Strict (Interne)</strong>
          <span class="pc-badge pc-badge-airbnb">Pour Airbnb / Booking</span>
          <p>Ne contient <strong>QUE</strong> les réservations de votre site et vos blocages manuels. Empêche les boucles infinies.</p>
        </div>
        <input type="text" readonly :value="getIcalExportUrl('interne')" class="pc-input pc-input-readonly" @click="copyToClipboard($event)" title="Cliquez pour copier" />
      </div>

      <div class="pc-export-item mt-3">
        <div class="pc-export-info">
          <strong>🌍 iCal Global</strong>
          <span class="pc-badge pc-badge-proprio">Pour Propriétaires</span>
          <p>Contient <strong>TOUTES</strong> les dates bloquées (Site + Airbnb + Booking...). Idéal pour un Google Agenda personnel.</p>
        </div>
        <input type="text" readonly :value="getIcalExportUrl('global')" class="pc-input pc-input-readonly" @click="copyToClipboard($event)" title="Cliquez pour copier" />
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

// --- GESTION ICALS ---
if (!modalStore.formData.icals_sync || !Array.isArray(modalStore.formData.icals_sync)) {
  modalStore.formData.icals_sync = [];
}

const addIcal = () => {
  modalStore.formData.icals_sync.push({ name: "", url: "" });
};

const removeIcal = (index) => {
  modalStore.formData.icals_sync.splice(index, 1);
};

// Fonction pour générer l'URL de sortie à la volée sécurisée
const getIcalExportUrl = (type) => {
  if (!modalStore.formData.ical_export_token) {
    return "⚠️ Sauvegardez le logement une fois pour générer le lien sécurisé.";
  }
  const baseUrl = window.location.origin;
  return `${baseUrl}/wp-json/pc-resa/v1/ical/${modalStore.formData.id}/${type}?token=${modalStore.formData.ical_export_token}`;
};

// Fonction moderne pour copier dans le presse-papier avec effet visuel
const copyToClipboard = async (event) => {
  const input = event.target;
  input.select();
  
  // Si c'est le message d'avertissement, on ne copie pas
  if (input.value.startsWith("⚠️")) return;

  try {
    // API moderne du presse-papier
    await navigator.clipboard.writeText(input.value);
    
    // Petit feedback visuel (clignotement vert)
    const originalBg = input.style.backgroundColor;
    input.style.backgroundColor = "#dcfce7"; 
    
    setTimeout(() => {
      input.style.backgroundColor = originalBg;
    }, 400);
    
  } catch (err) {
    console.error('Erreur lors de la copie (fallback utilisé) :', err);
    // Fallback ancienne méthode au cas où
    document.execCommand('copy'); 
  }
};

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

/* Styles pour le répéteur iCal */
.pc-ical-repeater {
  background: #f8fafc;
  padding: 1.5rem;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  margin-bottom: 2rem;
}

.pc-repeater-label {
  display: block;
  font-weight: 600;
  color: #0f172a;
  margin-bottom: 0.25rem;
}

.pc-repeater-help {
  font-size: 0.85rem;
  color: #64748b;
  margin-bottom: 1rem;
}

.pc-ical-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1rem;
  background: white;
  padding: 1rem;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
}

.pc-ical-inputs {
  display: grid;
  grid-template-columns: 1fr 2fr;
  gap: 1rem;
  flex-grow: 1;
}

.pc-btn-remove {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 1.2rem;
  padding: 0.5rem;
  opacity: 0.7;
  transition: opacity 0.2s;
}

.pc-btn-remove:hover {
  opacity: 1;
}

.pc-btn-add {
  background: white;
  border: 1px dashed #cbd5e1;
  color: #3b82f6;
  font-weight: 500;
  padding: 0.75rem 1.5rem;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
  width: 100%;
}

.pc-btn-add:hover {
  border-color: #3b82f6;
  background: #eff6ff;
}

/* Styles pour l'export iCal */
.pc-ical-export-box {
  background: #f0fdf4;
  padding: 1.5rem;
  border-radius: 8px;
  border: 1px solid #bbf7d0;
  margin-bottom: 2rem;
}

.pc-export-item {
  background: white;
  padding: 1rem;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
}

.mt-3 {
  margin-top: 1rem;
}

.pc-export-info p {
  margin: 0.5rem 0;
  font-size: 0.85rem;
  color: #64748b;
}

.pc-badge {
  font-size: 0.7rem;
  padding: 0.2rem 0.5rem;
  border-radius: 12px;
  margin-left: 0.5rem;
  font-weight: bold;
}

.pc-badge-airbnb {
  background: #fee2e2;
  color: #b91c1c;
}

.pc-badge-proprio {
  background: #e0e7ff;
  color: #4338ca;
}

.pc-input-readonly {
  background: #f8fafc;
  color: #475569;
  cursor: pointer;
  width: 100%;
  font-family: monospace;
  font-size: 0.9rem;
}

.pc-input-readonly:focus {
  outline: 2px solid #22c55e;
}
</style>
