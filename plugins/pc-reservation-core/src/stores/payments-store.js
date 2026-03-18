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
      // On simule l'objet attendu par openDetailModal
      await resStore.openDetailModal({ id: reservationId });
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

        await this.refreshReservationDetails(reservationId);
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
