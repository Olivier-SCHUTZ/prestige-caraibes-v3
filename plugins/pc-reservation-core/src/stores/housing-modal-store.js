import { defineStore } from "pinia";
import axios from "axios"; // À adapter si tu utilises une instance spécifique ex: import api from '@/services/api-client'

// État initial vide pour la création ou le reset
const defaultFormData = {
  title: "",
  type: "",
  status: "draft",
  content: "",
  rates: { seasons: [], promos: [] }, // Ajout de la structure pour les tarifs
  // L'objet sera peuplé dynamiquement par le retour de l'API pour les champs ACF.
  // La réactivité de Vue 3 gérera l'ajout des nouvelles propriétés.
};

export const useHousingModalStore = defineStore("housingModal", {
  state: () => ({
    isOpen: false,
    isLoading: false,
    isSaving: false,
    error: null,
    activeTab: "general", //
    housingId: null,
    basePrice: 0, // Ajout du prix de base pour le calendrier
    formData: JSON.parse(JSON.stringify(defaultFormData)), // Deep copy pour éviter les références croisées
  }),

  actions: {
    openModal(id) {
      this.isOpen = true;
      this.housingId = id;
      this.activeTab = "general";
      this.error = null;

      if (id === 0) {
        // Mode Création : on reset les champs
        this.formData = { ...defaultFormData };
      } else {
        // Mode Édition : on charge les détails
        this.fetchHousingDetails(id);
      }
    },

    closeModal() {
      this.isOpen = false;
      this.housingId = null;
      this.formData = { ...defaultFormData };
      this.error = null;
    },

    setTab(tabId) {
      this.activeTab = tabId;
    },

    async fetchHousingDetails(id) {
      this.isLoading = true;
      this.error = null;

      try {
        const wpVars = window.pcReservationVars || { nonce: "", ajax_url: "" };

        // On utilise FormData pour que WP reçoive bien du $_POST classique
        const params = new FormData();
        params.append("action", "pc_housing_get_details");
        params.append("post_id", id);
        params.append("nonce", wpVars.nonce); // Sécurité WP explicite

        const response = await axios.post(wpVars.ajax_url, params);

        if (response.data && response.data.success) {
          this.formData = response.data.data.housing;
          // Initialiser le prix de base (à adapter selon la clé exacte remontée par votre API WP)
          this.basePrice = parseFloat(this.formData.base_price || 0);
          // Formater les tarifs avec les IDs et couleurs générées
          this.formatRatesData();
        } else {
          this.error =
            response.data?.data?.message ||
            "Erreur lors du chargement des détails.";
        }
      } catch (err) {
        console.error("Erreur AJAX fetchHousingDetails:", err);
        this.error = "Erreur de connexion lors du chargement des détails.";
      } finally {
        this.isLoading = false;
      }
    },

    async saveHousing(customPayload = {}) {
      this.isSaving = true;
      this.error = null;

      try {
        const wpVars = window.pcReservationVars || { nonce: "", ajax_url: "" };
        const params = new FormData();

        params.append("action", "pc_housing_save");
        params.append("post_id", this.housingId);
        params.append("nonce", wpVars.nonce);

        if (this.housingId === 0 && this.formData.type) {
          params.append("post_type", this.formData.type);
        }

        // Fusion des données et ajout au FormData
        const fullData = { ...this.formData, ...customPayload };

        for (const key in fullData) {
          if (fullData[key] !== undefined && fullData[key] !== null) {
            // 🛡️ PONT STRANGLER : ACF a besoin du préfixe "acf_" pour reconnaître les champs
            // On l'ajoute automatiquement si la clé ne l'a pas, SAUF pour les champs natifs WP (title, content, status, etc.)
            const isNativeWpField = ["title", "content", "status"].includes(
              key,
            );
            const prefixedKey =
              isNativeWpField || key.startsWith("acf_") ? key : `acf_${key}`;

            if (Array.isArray(fullData[key])) {
              // WP comprend les arrays si on ajoute [] au nom de la clé
              fullData[key].forEach((val) =>
                params.append(`${prefixedKey}[]`, val),
              );
            } else if (typeof fullData[key] === "object") {
              params.append(prefixedKey, JSON.stringify(fullData[key]));
            } else {
              // Gestion spécifique des booléens (ACF attend "1" ou "0")
              let finalValue = fullData[key];
              if (typeof finalValue === "boolean") {
                finalValue = finalValue ? "1" : "0";
              }
              params.append(prefixedKey, finalValue);
            }
          }
        }

        const response = await axios.post(wpVars.ajax_url, params);

        if (response.data && response.data.success) {
          this.closeModal();
          return true; // Succès
        } else {
          this.error =
            response.data?.data?.message || "Erreur lors de la sauvegarde.";
          return false;
        }
      } catch (err) {
        console.error("Erreur AJAX saveHousing:", err);
        this.error = "Erreur de connexion lors de la sauvegarde.";
        return false;
      } finally {
        this.isSaving = false;
      }
    },

    // ====================================================
    // 💰 GESTION DES TARIFS (Ex-PCRateManager)
    // ====================================================

    formatRatesData() {
      let nextId = 1;

      if (!this.formData.rates) {
        this.formData.rates = { seasons: [], promos: [] };
      }

      // Formatage des Saisons
      if (
        this.formData.rates.seasons &&
        Array.isArray(this.formData.rates.seasons)
      ) {
        this.formData.rates.seasons = this.formData.rates.seasons.map((s) => ({
          ...s,
          id: s.id || "season_" + nextId++,
          type: "season",
          name: s.name || "Saison",
          price: parseFloat(s.price) || 0,
          note: s.note || "",
          minNights: parseInt(s.minNights) || 0,
          guestFee: parseFloat(s.guestFee) || 0,
          guestFrom: parseInt(s.guestFrom) || 0,
          periods: s.periods || [],
          color: this.stringToColor(s.name || "Saison"),
        }));
      } else {
        this.formData.rates.seasons = [];
      }

      // Formatage des Promos
      if (
        this.formData.rates.promos &&
        Array.isArray(this.formData.rates.promos)
      ) {
        this.formData.rates.promos = this.formData.rates.promos.map((p) => ({
          ...p,
          id: p.id || "promo_" + nextId++,
          type: "promo",
          name: p.name || "Promotion",
          promo_type: p.promo_type || "percent",
          value: parseFloat(p.value) || 0,
          validUntil: p.validUntil || "",
          periods: p.periods || [],
          color: "#ef4444", // Rouge constant pour les promos
        }));
      } else {
        this.formData.rates.promos = [];
      }
    },

    stringToColor(str) {
      let hash = 0;
      for (let i = 0; i < str.length; i++) {
        hash = str.charCodeAt(i) + ((hash << 5) - hash);
      }
      return `hsl(${Math.abs(hash % 360)}, 70%, 45%)`;
    },

    saveRateItem(type, itemData) {
      const list =
        type === "season"
          ? this.formData.rates.seasons
          : this.formData.rates.promos;

      if (itemData.id) {
        // Mise à jour
        const index = list.findIndex((i) => i.id === itemData.id);
        if (index > -1) {
          list[index] = { ...list[index], ...itemData };
          if (type === "season")
            list[index].color = this.stringToColor(list[index].name);
        }
      } else {
        // Création
        const newItem = {
          ...itemData,
          id: `${type}_${Date.now()}`,
          type: type,
          color:
            type === "season"
              ? this.stringToColor(itemData.name || "Saison")
              : "#ef4444",
          periods: itemData.periods || [],
        };
        list.push(newItem);
      }
    },

    deleteRateItem(type, id) {
      if (type === "season") {
        this.formData.rates.seasons = this.formData.rates.seasons.filter(
          (s) => s.id !== id,
        );
      } else {
        this.formData.rates.promos = this.formData.rates.promos.filter(
          (p) => p.id !== id,
        );
      }
    },

    addRatePeriod(type, id, start, end) {
      const list =
        type === "season"
          ? this.formData.rates.seasons
          : this.formData.rates.promos;
      const item = list.find((i) => i.id === id);
      if (item) {
        if (!item.periods) item.periods = [];
        item.periods.push({ start, end });
      }
    },

    updateRatePeriod(type, id, periodIndex, start, end) {
      const list =
        type === "season"
          ? this.formData.rates.seasons
          : this.formData.rates.promos;
      const item = list.find((i) => i.id === id);
      if (item && item.periods && item.periods[periodIndex]) {
        item.periods[periodIndex] = { start, end };
      }
    },

    removeRatePeriod(type, id, periodIndex) {
      const list =
        type === "season"
          ? this.formData.rates.seasons
          : this.formData.rates.promos;
      const item = list.find((i) => i.id === id);
      if (item && item.periods && item.periods[periodIndex]) {
        item.periods.splice(periodIndex, 1);
      }
    },
  },
});
