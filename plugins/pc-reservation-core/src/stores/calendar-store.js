import { defineStore } from "pinia";
import apiClient from "../services/api-client";

export const useCalendarStore = defineStore("calendar", {
  state: () => ({
    currentMonth: new Date().getMonth() + 1,
    currentYear: new Date().getFullYear(),

    logements: [],
    events: [],
    selectedLogement: null,

    startDate: null,
    extendedEndDate: null,

    loading: false,
    error: null,

    // Nouveaux états pour la modale
    modalOpen: false,
    selectedLogement: null,
    singleEvents: [],
    singleLoading: false,

    selection: null,
    isCreatingBlock: false,

    isResaModalOpen: false,
  }),

  actions: {
    openResaModal() {
      this.isResaModalOpen = true;
    },
    closeResaModal() {
      this.isResaModalOpen = false;
    },

    // --- ACTIONS DE LA MODALE ---
    openModal(logement) {
      this.selectedLogement = logement;
      this.modalOpen = true;
      // On charge immédiatement les données de ce logement
      this.fetchSingleCalendar(
        logement.id,
        this.currentMonth,
        this.currentYear,
      );
    },

    closeModal() {
      this.modalOpen = false;
      this.selectedLogement = null;
      this.singleEvents = [];
      this.selection = null; // 🚀 On efface la sélection en fermant
    },

    // 🚀 --- ACTIONS DE SÉLECTION ---
    setSelection(start, end) {
      this.selection = {
        mode: "create", // Mode création
        logementId: this.selectedLogement.id,
        start: start,
        end: end,
        reason: "",
      };
    },

    setEditSelection(event) {
      this.selection = {
        mode: "edit", // Mode édition
        logementId: event.logement_id,
        start: event.start_date,
        end: event.end_date,
        blockId: event.block_id,
        reason: event.label, // On pré-remplit avec le motif existant
      };
    },

    clearSelection() {
      this.selection = null;
    },

    // 🚀 --- ACTION DE SAUVEGARDE DU BLOCAGE ---
    async createManualBlock(payload) {
      this.isCreatingBlock = true;
      try {
        const ajaxUrl =
          window.pcCalendarData?.ajaxUrl || "/wp-admin/admin-ajax.php";

        const formData = new FormData();
        formData.append("action", "pc_calendar_create_block");

        // 🚀 LE FIX EST LÀ : On force les clés de sécurité avec le bon nonce du calendrier !
        formData.append("security", window.pcCalendarData?.nonce);
        formData.append("nonce", window.pcCalendarData?.nonce);

        formData.append("logement_id", payload.logementId);
        formData.append("start_date", payload.start);
        formData.append("end_date", payload.end);
        formData.append("motif", payload.reason);

        const response = await apiClient.post(ajaxUrl, formData);

        if (response.data && response.data.success) {
          // 1. On rafraîchit la modale pour afficher le nouveau blocage
          await this.fetchSingleCalendar(
            payload.logementId,
            this.currentMonth,
            this.currentYear,
          );
          // 2. On rafraîchit le calendrier global en arrière-plan
          this.fetchGlobalCalendar(this.currentMonth, this.currentYear);
          // 3. On ferme la barre de sélection
          this.clearSelection();
          return { success: true };
        } else {
          return {
            success: false,
            message:
              response.data?.data?.message ||
              "Erreur lors de la création du blocage.",
          };
        }
      } catch (err) {
        // 🚀 NOUVEAU : On récupère la VRAIE erreur envoyée par WordPress au lieu du message générique !
        const trueError =
          err.response?.data?.data?.message ||
          "Action PHP introuvable ou erreur serveur.";
        console.error("[Calendar Store] Erreur:", err.response || err);
        return { success: false, message: trueError };
      } finally {
        this.isCreatingBlock = false;
      }
    },

    // 🚀 --- ACTION DE CRÉATION DE RÉSERVATION ---
    async createReservation(payload) {
      this.isCreatingBlock = true; // On réutilise ce loader pour bloquer les boutons pendant l'envoi
      try {
        const ajaxUrl =
          window.pcCalendarData?.ajaxUrl || "/wp-admin/admin-ajax.php";

        const formData = new FormData();
        formData.append("action", "pc_calendar_create_resa"); // Le nom de notre future fonction PHP !
        formData.append("security", window.pcCalendarData?.nonce);
        formData.append("nonce", window.pcCalendarData?.nonce);

        // On envoie les données de la modale
        formData.append("logement_id", payload.logementId);
        formData.append("start_date", payload.start);
        formData.append("end_date", payload.end);
        formData.append("type", payload.type);

        const response = await apiClient.post(ajaxUrl, formData);

        if (response.data && response.data.success) {
          // Si c'est un succès, on rafraîchit le grand calendrier
          await this.fetchGlobalCalendar(this.currentMonth, this.currentYear);
          // On ferme la modale
          this.closeResaModal();
          this.clearSelection();
          return { success: true };
        } else {
          return {
            success: false,
            message:
              response.data?.data?.message ||
              "Erreur lors de la création de la réservation.",
          };
        }
      } catch (err) {
        console.error("[Calendar Store] Erreur création résa:", err);
        return { success: false, message: "Erreur serveur." };
      } finally {
        this.isCreatingBlock = false;
      }
    },

    // 🚀 --- ACTION DE MISE À JOUR D'UN BLOCAGE ---
    async updateManualBlock(blockId, newReason) {
      this.isCreatingBlock = true;
      try {
        const ajaxUrl =
          window.pcCalendarData?.ajaxUrl || "/wp-admin/admin-ajax.php";
        const formData = new FormData();
        formData.append("action", "pc_calendar_update_block");
        formData.append("security", window.pcCalendarData?.nonce);
        formData.append("nonce", window.pcCalendarData?.nonce);
        formData.append("block_id", blockId);
        formData.append("motif", newReason);

        const response = await apiClient.post(ajaxUrl, formData);

        if (response.data && response.data.success) {
          await this.fetchSingleCalendar(
            this.selectedLogement.id,
            this.currentMonth,
            this.currentYear,
          );
          this.fetchGlobalCalendar(this.currentMonth, this.currentYear);
          this.clearSelection();
          return { success: true };
        } else {
          return {
            success: false,
            message: response.data?.data?.message || "Erreur de mise à jour.",
          };
        }
      } catch (err) {
        return {
          success: false,
          message: err.response?.data?.data?.message || "Erreur serveur.",
        };
      } finally {
        this.isCreatingBlock = false;
      }
    },

    // 🚀 --- ACTION DE SUPPRESSION D'UN BLOCAGE ---
    async deleteManualBlock(blockId) {
      this.isCreatingBlock = true; // On bloque l'interface pendant le chargement
      try {
        const ajaxUrl =
          window.pcCalendarData?.ajaxUrl || "/wp-admin/admin-ajax.php";

        const formData = new FormData();
        formData.append("action", "pc_calendar_delete_block"); // Le nom de l'action PHP
        formData.append("security", window.pcCalendarData?.nonce);
        formData.append("nonce", window.pcCalendarData?.nonce);
        formData.append("block_id", blockId);

        const response = await apiClient.post(ajaxUrl, formData);

        if (response.data && response.data.success) {
          // On rafraîchit la modale et la grande grille en arrière-plan
          await this.fetchSingleCalendar(
            this.selectedLogement.id,
            this.currentMonth,
            this.currentYear,
          );
          this.fetchGlobalCalendar(this.currentMonth, this.currentYear);
          return { success: true };
        } else {
          return {
            success: false,
            message: response.data?.data?.message || "Erreur de suppression.",
          };
        }
      } catch (err) {
        console.error("[Calendar Store] Erreur suppression:", err);
        return {
          success: false,
          message: err.response?.data?.data?.message || "Erreur serveur.",
        };
      } finally {
        this.isCreatingBlock = false;
      }
    },

    async fetchSingleCalendar(logementId, month, year) {
      this.singleLoading = true;
      try {
        const ajaxUrl =
          window.pcCalendarData?.ajaxUrl || "/wp-admin/admin-ajax.php";
        const response = await apiClient.get(ajaxUrl, {
          params: {
            action: "pc_get_single_calendar",
            security: window.pcCalendarData?.nonce,
            nonce: window.pcCalendarData?.nonce,
            logement_id: logementId,
            month: month,
            year: year,
          },
        });

        if (response.data && response.data.success) {
          // 🚀 Filtrage : on ignore aussi les résas annulées dans la vue détaillée (modale)
          this.singleEvents = (response.data.data.events || []).filter(
            (e) =>
              !["annulée", "annulee", "refusee"].includes(
                e.status?.toLowerCase(),
              ),
          );
        }
      } catch (err) {
        console.error("[Calendar Store] Erreur single calendar:", err);
      } finally {
        this.singleLoading = false;
      }
    },

    // --- FIN ACTIONS MODALE ---

    async fetchGlobalCalendar(month, year) {
      this.loading = true;
      this.error = null;

      try {
        // 🚀 On force l'URL exacte de WP et on envoie les deux clés de sécurité possibles
        const ajaxUrl =
          window.pcCalendarData?.ajaxUrl || "/wp-admin/admin-ajax.php";

        const response = await apiClient.get(ajaxUrl, {
          params: {
            action: "pc_get_calendar_global",
            security: window.pcCalendarData?.nonce,
            nonce: window.pcCalendarData?.nonce, // Ta classe PHP cherche probablement "nonce"
            month: month,
            year: year,
          },
        });

        if (response.data && response.data.success) {
          const payload = response.data.data;
          this.logements = payload.logements || [];
          // 🚀 Filtrage : on ignore les résas annulées ou refusées pour libérer les dates
          this.events = (payload.events || []).filter(
            (e) =>
              !["annulée", "annulee", "refusee"].includes(
                e.status?.toLowerCase(),
              ),
          );
          this.currentMonth = payload.month;
          this.currentYear = payload.year;
          this.startDate = payload.start_date;
          this.extendedEndDate = payload.extended_end;
        } else {
          this.error =
            response.data?.data?.message ||
            "Erreur lors du chargement des données du calendrier.";
        }
      } catch (err) {
        console.error("[Calendar Store] Erreur réseau:", err);
        this.error = "Impossible de contacter le serveur.";
      } finally {
        this.loading = false;
      }
    },
  },
});
