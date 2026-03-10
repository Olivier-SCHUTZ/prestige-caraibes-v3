import { defineStore } from "pinia";
import apiClient from "../services/api-client.js";

export const useExperienceStore = defineStore("experience", {
  state: () => ({
    items: [],
    isLoading: false,
    error: null,
    pagination: {
      currentPage: 1,
      totalPages: 1,
    },
    filters: {
      search: "",
      status: "",
    },
    // --- NOUVEL ÉTAT POUR LA MODALE VUE ---
    currentExperience: null,
    isSaving: false,
    isModalOpen: false,
    defaultExperience: {
      id: null,
      title: "",
      exp_h1_custom: "",
      exp_availability: true,
      exp_exclude_sitemap: false,
      exp_http_410: false,
      exp_meta_titre: "",
      exp_meta_description: "",
      exp_meta_canonical: "",
      exp_meta_robots: "index,follow",
      exp_lieux_horaires_depart: [],
      exp_periodes_fermeture: [],
      exp_faq: [],
      exp_types_de_tarifs: [],
      photos_experience: [],
    },
  }),

  actions: {
    async fetchExperiences(page = 1) {
      this.isLoading = true;
      this.error = null;
      this.pagination.currentPage = page;

      try {
        // On récupère les variables globales pour être sûr d'avoir le nonce
        const wpVars = window.pcReservationVars || { nonce: "" };

        // On utilise URLSearchParams pour simuler EXACTEMENT un formulaire jQuery standard
        const params = new URLSearchParams();
        params.append("action", "pc_experience_get_list");
        params.append("page", this.pagination.currentPage);
        params.append("per_page", 20);
        params.append("search", this.filters.search);
        params.append("status_filter", this.filters.status);
        params.append("orderby", "title");
        params.append("order", "ASC");

        // 🛡️ DOUBLE SÉCURITÉ : On envoie les deux noms de clés
        params.append("nonce", wpVars.nonce);
        params.append("security", wpVars.nonce);

        const response = await apiClient.post("", params);

        if (response.data && response.data.success) {
          this.items = response.data.data.items || [];
          this.pagination.totalPages = response.data.data.pages || 1;
          this.pagination.currentPage = response.data.data.current_page || 1;
        } else {
          throw new Error(response.data?.data?.message || "Erreur serveur");
        }
      } catch (err) {
        console.error("🚨 [Experience Store] Erreur:", err);
        this.error =
          "Erreur de chargement (Status 400). Vérification de sécurité échouée.";
      } finally {
        this.isLoading = false;
      }
    },

    setSearch(query) {
      this.filters.search = query;
      this.fetchExperiences(1);
    },

    setStatus(status) {
      this.filters.status = status;
      this.fetchExperiences(1);
    },

    openLegacyModal(experienceId = null) {
      if (window.pcExperienceDashboard) {
        window.pcExperienceDashboard.openModal(experienceId);
      }
    },

    // --- NOUVELLES ACTIONS POUR LA MODALE VUE ---
    async openModal(id = null) {
      this.error = null;
      this.isModalOpen = true;

      if (id) {
        await this.fetchExperienceDetails(id);
      } else {
        // Copie profonde de l'objet par défaut pour éviter les références
        this.currentExperience = JSON.parse(
          JSON.stringify(this.defaultExperience),
        );
      }
    },

    closeModal() {
      this.isModalOpen = false;
      this.currentExperience = null;
      this.error = null;
    },

    async fetchExperienceDetails(id) {
      this.isLoading = true;
      this.error = null;

      try {
        const wpVars = window.pcReservationVars || { nonce: "" };
        const params = new URLSearchParams();
        params.append("action", "pc_experience_get_details");
        params.append("post_id", id);
        params.append("nonce", wpVars.nonce);
        params.append("security", wpVars.nonce);

        const response = await apiClient.post("", params);

        if (response.data && response.data.success) {
          this.currentExperience = response.data.data.experience;
        } else {
          throw new Error(
            response.data?.data?.message ||
              "Erreur lors du chargement des détails.",
          );
        }
      } catch (err) {
        console.error(
          "🚨 [Experience Store] Erreur fetchExperienceDetails:",
          err,
        );
        this.error = err.message || "Erreur de connexion.";
      } finally {
        this.isLoading = false;
      }
    },

    async saveExperience() {
      if (!this.currentExperience) return;

      this.isSaving = true;
      this.error = null;

      try {
        const wpVars = window.pcReservationVars || { nonce: "" };
        const exp = this.currentExperience; // Alias pour raccourcir

        // 1. Formatage spécifique pour la galerie (WordPress attend une chaîne d'IDs séparés par des virgules)
        const photosIds = Array.isArray(exp.photos_experience)
          ? exp.photos_experience
              .map((p) => p.id)
              .filter((id) => id)
              .join(",")
          : "";

        // 2. Construction du payload EXACTEMENT comme l'ancien fichier jQuery
        const payload = {
          action: "pc_experience_save",
          nonce: wpVars.nonce,
          security: wpVars.nonce,
          post_id: exp.id || "",

          title: exp.exp_h1_custom || exp.title || "",
          acf_exp_h1_custom: exp.exp_h1_custom || "",
          acf_exp_availability: exp.exp_availability ? "1" : "0",

          // --- SEO & Liaisons ---
          acf_exp_exclude_sitemap: exp.exp_exclude_sitemap ? "1" : "0",
          acf_exp_http_410: exp.exp_http_410 ? "1" : "0",
          acf_exp_meta_titre: exp.exp_meta_titre || "",
          acf_exp_meta_description: exp.exp_meta_description || "",
          acf_exp_meta_canonical: exp.exp_meta_canonical || "",
          acf_exp_meta_robots: exp.exp_meta_robots || "index,follow",
          acf_exp_logements_recommandes: Array.isArray(
            exp.exp_logements_recommandes,
          )
            ? exp.exp_logements_recommandes
            : [],

          // --- Sorties ---
          acf_exp_duree: exp.exp_duree || "",
          acf_exp_capacite: exp.exp_capacite || "",
          acf_exp_age_minimum: exp.exp_age_minimum || "",
          acf_exp_accessibilite: exp.exp_accessibilite || [],
          acf_exp_periode: exp.exp_periode || [],
          acf_exp_jour: exp.exp_jour || [],

          // --- Inclusions ---
          acf_exp_prix_comprend: exp.exp_prix_comprend || "",
          acf_exp_prix_ne_comprend_pas: exp.exp_prix_ne_comprend_pas || "",
          acf_exp_a_prevoir: exp.exp_a_prevoir || [],

          // --- Services ---
          acf_exp_delai_de_reservation: exp.exp_delai_de_reservation || [],
          acf_exp_zone_intervention: exp.exp_zone_intervention || [],
          acf_exp_type_de_prestation: exp.exp_type_de_prestation || "",
          acf_exp_heure_limite_de_commande:
            exp.exp_heure_limite_de_commande || "",
          acf_exp_le_service_comprend: exp.exp_le_service_comprend || "",
          acf_exp_service_a_prevoir: exp.exp_service_a_prevoir || "",

          // --- Paiement & TVA ---
          acf_taux_tva: exp.taux_tva || "",
          acf_pc_pay_mode: exp.pc_pay_mode || "acompte_plus_solde",
          acf_pc_deposit_type: exp.pc_deposit_type || "pourcentage",
          acf_pc_deposit_value: exp.pc_deposit_value || "",
          acf_pc_balance_delay_days: exp.pc_balance_delay_days || "",
          acf_pc_caution_amount: exp.pc_caution_amount || "",
          acf_pc_caution_mode: exp.pc_caution_mode || "aucune",

          // --- Médias ---
          acf_exp_hero_desktop: exp.exp_hero_desktop || "",
          acf_exp_hero_mobile: exp.exp_hero_mobile || "",
          acf_photos_experience: photosIds,

          // --- Repeaters ---
          // JSON.parse(JSON.stringify(...)) sert à nettoyer les données des "Proxy" internes de Vue.js
          // pour s'assurer que Axios envoie des tableaux JavaScript purs compréhensibles par PHP
          acf_exp_lieux_horaires_depart: JSON.parse(
            JSON.stringify(exp.exp_lieux_horaires_depart || []),
          ),
          acf_exp_periodes_fermeture: JSON.parse(
            JSON.stringify(exp.exp_periodes_fermeture || []),
          ),
          acf_exp_faq: JSON.parse(JSON.stringify(exp.exp_faq || [])),
          acf_exp_types_de_tarifs: JSON.parse(
            JSON.stringify(exp.exp_types_de_tarifs || []),
          ),
        };

        // Envoi au serveur avec le Content-Type adapté pour l'API WP (admin-ajax.php)
        const response = await apiClient.post("", payload, {
          headers: {
            "Content-Type": "application/x-www-form-urlencoded", // Force le format Formulaire classique attendu par WP
          },
        });

        if (response.data && response.data.success) {
          this.closeModal();
          this.fetchExperiences(this.pagination.currentPage); // Rafraîchit le tableau en fond
          return true;
        } else {
          throw new Error(
            response.data?.data?.message || "Erreur lors de la sauvegarde.",
          );
        }
      } catch (err) {
        console.error("🚨 [Experience Store] Erreur saveExperience:", err);
        this.error =
          err.message || "Erreur de connexion lors de la sauvegarde.";
        return false;
      } finally {
        this.isSaving = false;
      }
    },
  },
});
