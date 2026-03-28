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
          // Initialiser le prix de base avec la bonne clé
          this.basePrice = parseFloat(this.formData.base_price_from || 0);
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

        // 🚀 FIX TARIFS : Le contrôleur PHP attend spécifiquement "rate_manager_data"
        if (fullData.rates) {
          // On nettoie les proxys de Vue 3 comme dans experience-store.js
          const cleanSeasons = JSON.parse(
            JSON.stringify(fullData.rates.seasons || []),
          );
          const cleanPromos = JSON.parse(
            JSON.stringify(fullData.rates.promos || []),
          );

          const ratePayload = {
            seasons: cleanSeasons,
            promos: cleanPromos,
          };

          // On injecte la chaîne JSON pour l'ancien PCR_Rate_Manager
          params.append("rate_manager_data", JSON.stringify(ratePayload));

          // On supprime ces clés pour éviter que la boucle ci-dessous ne tente de les sauvegarder comme des champs ACF classiques
          delete fullData.rates;
        }

        // Purge des données de tarifs formatées
        delete fullData.seasons_data;
        delete fullData.promos_data;

        // Purge CRUCIALE des données brutes ACF (pour ne pas écraser les répéteurs)
        delete fullData.pc_season_blocks;
        delete fullData.pc_promo_blocks;
        delete fullData.field_pc_season_blocks_20250826;
        delete fullData.field_693425b17049d;

        for (const key in fullData) {
          if (fullData[key] !== undefined && fullData[key] !== null) {
            // 🛡️ PONT STRANGLER : ACF a besoin du préfixe "acf_" pour reconnaître les champs
            // On l'ajoute automatiquement si la clé ne l'a pas, SAUF pour les champs natifs WP (title, content, status, etc.)
            const isNativeWpField = ["title", "content", "status"].includes(
              key,
            );
            const prefixedKey =
              isNativeWpField || key.startsWith("acf_") ? key : `acf_${key}`;

            // APRÈS
            if (Array.isArray(fullData[key])) {
              // 🚀 CORRECTION : Si c'est un tableau d'objets (comme la FAQ), il FAUT le stringify !
              // Sinon FormData le transforme en "[object Object]"
              if (
                fullData[key].length > 0 &&
                typeof fullData[key][0] === "object" &&
                fullData[key][0] !== null
              ) {
                params.append(prefixedKey, JSON.stringify(fullData[key]));
              } else {
                // Tableaux simples (strings, IDs) on garde le comportement par défaut
                fullData[key].forEach((val) =>
                  params.append(`${prefixedKey}[]`, val),
                );
              }
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
          // 1. Si c'était une création, on récupère le nouvel ID généré
          if (this.housingId === 0 && response.data.data.post_id) {
            this.housingId = response.data.data.post_id;
          }

          // 2. SUPPRESSION TOTALE des affectations manuelles !
          // Le backend ne renvoie pas l'objet housing complet ici.
          // On recharge donc proprement toutes les données via notre méthode dédiée.
          // Cela va repeupler this.formData, this.basePrice, et relancer formatRatesData() sans erreur.
          await this.fetchHousingDetails(this.housingId);
          return true;
        } else {
          this.error =
            response.data?.data?.message || "Erreur lors de la sauvegarde.";
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

      // Récupération des données formatées par PHP ou brutes par ACF
      let rawSeasons =
        this.formData.seasons_data || this.formData.pc_season_blocks || [];
      let rawPromos =
        this.formData.promos_data || this.formData.pc_promo_blocks || [];

      // Sécurité ACF : false devient un tableau vide
      if (rawSeasons === false) rawSeasons = [];
      if (rawPromos === false) rawPromos = [];

      // Mapping des Saisons (Compatible API formattée & ACF brut)
      this.formData.rates.seasons = rawSeasons.map((s) => {
        let mappedPeriods = s.periods || [];
        if (!s.periods && s.season_periods && Array.isArray(s.season_periods)) {
          mappedPeriods = s.season_periods.map((p) => ({
            start: p.date_from,
            end: p.date_to,
          }));
        }

        const name = s.name || s.season_name || "Saison";
        return {
          ...s,
          id: s.id || "season_" + nextId++,
          type: "season",
          name: name,
          price:
            parseFloat(s.price !== undefined ? s.price : s.season_price) || 0,
          note: s.note || s.season_note || "",
          minNights:
            parseInt(
              s.minNights !== undefined ? s.minNights : s.season_min_nights,
            ) || 0,
          guestFee:
            parseFloat(
              s.guestFee !== undefined ? s.guestFee : s.season_extra_guest_fee,
            ) || 0,
          guestFrom:
            parseInt(
              s.guestFrom !== undefined
                ? s.guestFrom
                : s.season_extra_guest_from,
            ) || 0,
          periods: mappedPeriods,
          color: this.stringToColor(name),
        };
      });

      // Mapping des Promotions
      this.formData.rates.promos = rawPromos.map((p) => {
        let mappedPeriods = p.periods || [];
        if (!p.periods && p.promo_periods && Array.isArray(p.promo_periods)) {
          mappedPeriods = p.promo_periods.map((per) => ({
            start: per.date_from,
            end: per.date_to,
          }));
        }

        const name = p.name || p.nom_de_la_promotion || "Promotion";
        return {
          ...p,
          id: p.id || "promo_" + nextId++,
          type: "promo",
          name: name,
          promo_type: p.promo_type || "percent",
          value:
            parseFloat(p.value !== undefined ? p.value : p.promo_value) || 0,
          validUntil: p.validUntil || p.promo_valid_until || "",
          periods: mappedPeriods,
          color: "#ef4444",
        };
      });
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
