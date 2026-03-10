<template>
  <div class="v2-tab-content">
    <h4 class="pc-section-title">Filtres rapides (Mise en avant)</h4>
    <div class="pc-form-grid" style="margin-bottom: 2rem">
      <div class="pc-checkbox-group">
        <label class="pc-checkbox-label">
          <input type="checkbox" v-model="modalStore.formData.has_piscine" />
          <span>🏊‍♂️ Le logement dispose d'une piscine</span>
        </label>

        <label class="pc-checkbox-label">
          <input type="checkbox" v-model="modalStore.formData.has_jacuzzi" />
          <span>🫧 Le logement dispose d'un jacuzzi</span>
        </label>

        <label class="pc-checkbox-label">
          <input
            type="checkbox"
            v-model="modalStore.formData.has_guide_numerique"
          />
          <span>📱 Guide numérique disponible</span>
        </label>
      </div>
    </div>

    <h4 class="pc-section-title">Équipements détaillés par catégorie</h4>

    <div class="pc-amenities-container">
      <div
        v-for="category in amenityCategories"
        :key="category.id"
        class="pc-amenity-category"
      >
        <h5 class="pc-category-title">{{ category.label }}</h5>
        <div class="pc-checkbox-group">
          <label
            v-for="option in category.options"
            :key="option"
            class="pc-checkbox-label"
          >
            <input
              type="checkbox"
              :value="option"
              v-model="modalStore.formData[category.id]"
            />
            <span>{{ option }}</span>
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
const categoriesIds = [
  "eq_piscine_spa",
  "eq_parking",
  "eq_cuisine",
  "eq_clim",
  "eq_internet",
  "eq_politiques",
  "eq_divertissements",
  "eq_caracteristiques_emplacement",
  "eq_salle_de_bain_blanchisserie",
  "eq_securite_maison",
];

categoriesIds.forEach((id) => {
  if (!modalStore.formData[id] || !Array.isArray(modalStore.formData[id])) {
    modalStore.formData[id] = [];
  }
});

// Conversion des booléens stockés en "1"/"0" par ACF vers vrai/faux
["has_piscine", "has_jacuzzi", "has_guide_numerique"].forEach((key) => {
  modalStore.formData[key] =
    modalStore.formData[key] === "1" || modalStore.formData[key] === true;
});

// --- CONFIGURATION EXACTE (Tirée de ton ancien code) ---
const amenityCategories = [
  {
    id: "eq_piscine_spa",
    label: "🏊 Piscine & Spa",
    options: [
      "Piscine",
      "Piscine au sel",
      "Piscine partagée",
      "Jacuzzi",
      "Sauna",
    ],
  },
  {
    id: "eq_parking",
    label: "🚗 Parking & Installations",
    options: [
      "Propriété clôturée",
      "Parking privé",
      "Parking dans la rue",
      "Jardin privé",
      "Jardin commun",
      "Aire de jeux",
      "Terrain de pétanque",
      "Citerne de réserve d'eau",
      "Groupe électrogène",
    ],
  },
  {
    id: "eq_cuisine",
    label: "🍳 Cuisine & Salle à manger",
    options: [
      "Barbecue à gaz",
      "Barbecue électrique",
      "Barbecue au charbon",
      "Plancha",
      "Mixeur / Blender",
      "Chaise haute bébé",
      "Cafetière filtre",
      "Cafetière à capsules",
      "Grille-pain",
      "Bouilloire",
      "Ustensiles de cuisine",
      "Plaque de cuisson gaz",
      "Plaque de cuisson électrique",
      "Plaque de cuisson mixte",
      "Four micro-ondes",
      "Four",
      "Lave-vaisselle",
      "Cave à vins",
      "Réfrigérateur",
      "Congélateur",
      "Réfrigérateur-congélateur",
      "Frigo américain",
      "Glaçons",
      "Aspirateur",
      "Nécessaire de nettoyage",
      "Vaisselle",
      "Verres",
    ],
  },
  {
    id: "eq_clim",
    label: "❄️ Chauffage & Climatisation",
    options: [
      "Climatisation chambres",
      "Climatisation logement complet",
      "Ventilateur de plafond",
      "Ventilateur sur pied",
    ],
  },
  {
    id: "eq_internet",
    label: "📶 Internet & Bureautique",
    options: [
      "Internet ADSL",
      "Internet Fibre",
      "Bureau séparé",
      "Bureau dans chambre",
      "Bureau dans le salon",
    ],
  },
  {
    id: "eq_politiques",
    label: "📜 Politiques",
    options: [
      "Carte de crédit acceptée",
      "Enfants autorisés",
      "Enfants non autorisés",
      "Animaux non autorisés",
      "Fumeurs non autorisés",
      "Fumeurs autorisés en extérieur",
      "Convient aux personnes âgées ou à mobilité réduite",
      "Ne convient pas aux personnes âgées ou à mobilité réduite",
      "Accessible aux fauteuils roulants",
      "Non accessible aux fauteuils roulants",
      "Services de conciergerie accessibles",
    ],
  },
  {
    id: "eq_divertissements",
    label: "📺 Divertissements",
    options: [
      "Chaises de plage disponibles",
      "Glacière disponible",
      "Parasols disponibles",
      "Billard",
      "Baby-foot",
      "Jeux de société",
      "Livres",
      "Chaîne Hi-Fi",
      "TV",
      "Streaming disponible (avec votre compte)",
    ],
  },
  {
    id: "eq_caracteristiques_emplacement",
    label: "📍 Caractéristiques de l'emplacement",
    options: [
      "Proche de la mer",
      "Bord de plage",
      "Plage accessible à pied",
      "Plages accessibles en voiture",
      "Plage accessible en voiture -15 min",
      "Centre-ville",
      "Vue sur mer",
    ],
  },
  {
    id: "eq_salle_de_bain_blanchisserie",
    label: "🚿 Salle de bain & buanderie",
    options: [
      "Linge de lit fournis",
      "Serviettes de bain",
      "Serviettes de toilette",
      "Serviettes de plage",
      "Foutas",
      "Sèche-cheveux",
      "Machine à laver",
      "Lave-linge dans cuisine",
      "Sèche-linge",
      "Douche",
      "Baignoire",
      "Buanderie séparée",
      "Fer et table à repasser",
      "Toilette invités",
    ],
  },
  {
    id: "eq_securite_maison",
    label: "🛡️ Sécurité à la maison",
    options: [
      "Détecteur de monoxyde de carbone",
      "Détecteur de fumée",
      "Coffre-fort",
      "Extincteur",
      "Sécurité piscine (alarme)",
      "Sécurité piscine (clôture)",
    ],
  },
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

.pc-amenities-container {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  column-gap: 2rem;
  row-gap: 3.5rem;
}

.pc-category-title {
  margin: 0 0 1.2rem 0;
  font-size: 1.1rem;
  color: #475569;
  font-weight: 600;
}

.pc-checkbox-group {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  background: #f8fafc;
  padding: 1rem;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  height: 100%;
}

.pc-checkbox-label {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  cursor: pointer;
  font-weight: normal !important;
  color: #334155;
  font-size: 0.95rem;
  line-height: 1.4;
}

.pc-checkbox-label input[type="checkbox"] {
  margin-top: 3px;
}
</style>
