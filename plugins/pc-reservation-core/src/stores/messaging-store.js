import { defineStore } from "pinia";
import messagingApi from "../services/messaging-api";

export const useMessagingStore = defineStore("messaging", {
  state: () => ({
    currentConversation: [],
    reservationContext: null, // Les infos de la résa liées à la conversation
    quickReplies: [],
    unreadCount: 0,
    isLoading: false,
    isSending: false,
    error: null,
  }),

  actions: {
    /**
     * Charge l'historique d'une conversation
     */
    async fetchHistory(reservationId) {
      this.isLoading = true;
      this.error = null;

      try {
        const response = await messagingApi.getHistory(reservationId);

        if (response.data.success) {
          this.currentConversation = response.data.data.messages || [];
          this.reservationContext = response.data.data.reservation || null;
          this.unreadCount = response.data.data.unread_count || 0;
        } else {
          throw new Error(
            response.data.data?.message ||
              "Erreur lors du chargement de la conversation",
          );
        }
      } catch (err) {
        this.error = err.message || "Erreur réseau";
        console.error("Messaging Store Error:", err);
      } finally {
        this.isLoading = false;
      }
    },

    // Nous ajouterons sendMessage et markAsRead ici lors de la Phase 3
  },
});
