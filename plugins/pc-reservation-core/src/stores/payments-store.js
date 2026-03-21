import { defineStore } from "pinia";
import apiClient from "../services/api-client";
import { useReservationsStore } from "./reservations-store";

export const usePaymentsStore = defineStore("payments", {
  state: () => ({
    // Stocke l'état de chargement spécifique de chaque action/bouton
    // Ex: { 'link_12': true, 'caution_release_45': false }
    loadingStates: {},
  }),

  actions: {
    // --- UTILITAIRES ---

    setLoading(key, status) {
      this.loadingStates[key] = status;
    },

    isLoading(key) {
      return !!this.loadingStates[key];
    },

    /**
     * Rafraîchit les données de la modale de réservation après une action réussie
     */
    async refreshReservationDetails(reservationId) {
      const resStore = useReservationsStore();

      // On appelle une fonction de rafraîchissement silencieux
      // (Il faudra s'assurer que cette fonction existe dans reservations-store.js)
      if (typeof resStore.refreshCurrentReservation === "function") {
        await resStore.refreshCurrentReservation(reservationId);
      } else {
        console.warn(
          "La méthode refreshCurrentReservation est manquante dans reservations-store.",
        );
        // Fallback temporaire (à éviter car il cause le saut d'UI)
        await resStore.openDetailModal({ id: reservationId });
      }

      // --- NOUVEAU : On force le rafraîchissement de la liste en arrière-plan ---
      if (typeof resStore.fetchList === "function") {
        // On relance la liste en restant sur la page actuelle (sans bloquer l'interface)
        resStore.fetchList(resStore.currentPage);
      }
      // -------------------------------------------------------------------------
    },

    // --- PAIEMENTS CLASSIQUES ---

    /**
     * Génère un lien de paiement Stripe (Acompte, Solde, Total)
     */
    async generatePaymentLink(paymentId, reservationId) {
      const loadingKey = `payment_link_${paymentId}`;
      this.setLoading(loadingKey, true);

      try {
        const response = await apiClient.post("", {
          action: "pc_stripe_get_link",
          payment_id: paymentId,
        });

        if (!response.data.success) {
          throw new Error(response.data.data.message || "Erreur de génération");
        }

        return response.data.data; // Retourne { url: "...", id: "cs_..." }
      } catch (error) {
        throw new Error(error.response?.data?.data?.message || error.message);
      } finally {
        this.setLoading(loadingKey, false);
      }
    },

    /**
     * NOUVEAU : Met à jour manuellement le statut d'un paiement (Virement, Espèces)
     */
    async updatePaymentStatus(paymentId, reservationId, status, method) {
      const loadingKey = `payment_update_${paymentId}`;
      this.setLoading(loadingKey, true);

      try {
        const response = await apiClient.post("", {
          action: "pc_update_payment_status",
          payment_id: paymentId,
          status: status,
          method: method,
        });

        if (!response.data.success) {
          throw new Error(
            response.data.data.message || "Erreur de mise à jour",
          );
        }

        // On rafraîchit silencieusement la réservation pour afficher les nouveaux statuts
        await this.refreshReservationDetails(reservationId);
        return response.data.data;
      } catch (error) {
        throw new Error(error.response?.data?.data?.message || error.message);
      } finally {
        this.setLoading(loadingKey, false);
      }
    },

    /**
     * NOUVEAU : Crée un appel de fond (paiement libre) et génère le lien Stripe
     */
    async createCustomPayment(reservationId, amount, actionType = "stripe") {
      const loadingKey = `custom_payment_${reservationId}`;
      this.setLoading(loadingKey, true);

      try {
        const response = await apiClient.post("", {
          action: "pc_stripe_create_custom_payment",
          reservation_id: reservationId,
          amount: amount,
          action_type: actionType, // NOUVEAU: on envoie le type au backend
        });

        if (!response.data.success) {
          throw new Error(
            response.data.data.message ||
              "Erreur de création du paiement manuel",
          );
        }

        // Succès ! On rafraîchit silencieusement la modale pour voir apparaître la nouvelle ligne
        await this.refreshReservationDetails(reservationId);

        return response.data.data;
      } catch (error) {
        throw new Error(error.response?.data?.data?.message || error.message);
      } finally {
        this.setLoading(loadingKey, false);
      }
    },

    // --- CAUTIONS (EMPREINTES) ---

    /**
     * Génère le lien pour la prise d'empreinte bancaire
     */
    async generateCautionLink(reservationId) {
      const loadingKey = `caution_link_${reservationId}`;
      this.setLoading(loadingKey, true);

      try {
        const response = await apiClient.post("", {
          action: "pc_stripe_get_caution_link",
          reservation_id: reservationId,
        });

        if (!response.data.success) throw new Error(response.data.data.message);

        // Pas de rafraîchissement global ici pour éviter le "saut" de l'onglet
        return response.data.data;
      } catch (error) {
        throw new Error(error.response?.data?.data?.message || error.message);
      } finally {
        this.setLoading(loadingKey, false);
      }
    },

    /**
     * Annule/Libère l'empreinte bancaire du client
     */
    async releaseCaution(reservationId, refStripe) {
      const loadingKey = `caution_release_${reservationId}`;
      this.setLoading(loadingKey, true);

      try {
        const response = await apiClient.post("", {
          action: "pc_stripe_release_caution",
          reservation_id: reservationId,
          ref: refStripe,
        });

        if (!response.data.success) throw new Error(response.data.data.message);

        await this.refreshReservationDetails(reservationId);
        return response.data.data;
      } catch (error) {
        throw new Error(error.response?.data?.data?.message || error.message);
      } finally {
        this.setLoading(loadingKey, false);
      }
    },

    /**
     * Encaisse tout ou une partie de la caution
     */
    async captureCaution(reservationId, refStripe, amount, note = "") {
      const loadingKey = `caution_capture_${reservationId}`;
      this.setLoading(loadingKey, true);

      try {
        const response = await apiClient.post("", {
          action: "pc_stripe_capture_caution",
          reservation_id: reservationId,
          ref: refStripe,
          amount: amount,
          note: note,
        });

        if (!response.data.success) throw new Error(response.data.data.message);

        await this.refreshReservationDetails(reservationId);
        return response.data.data;
      } catch (error) {
        throw new Error(error.response?.data?.data?.message || error.message);
      } finally {
        this.setLoading(loadingKey, false);
      }
    },

    /**
     * Renouvelle l'empreinte (Rotation)
     */
    async rotateCaution(reservationId, oldRefStripe) {
      const loadingKey = `caution_rotate_${reservationId}`;
      this.setLoading(loadingKey, true);

      try {
        const response = await apiClient.post("", {
          action: "pc_stripe_rotate_caution",
          reservation_id: reservationId,
          old_ref: oldRefStripe,
        });

        if (!response.data.success) throw new Error(response.data.data.message);

        await this.refreshReservationDetails(reservationId);
        return response.data.data;
      } catch (error) {
        throw new Error(error.response?.data?.data?.message || error.message);
      } finally {
        this.setLoading(loadingKey, false);
      }
    },
  },
});
