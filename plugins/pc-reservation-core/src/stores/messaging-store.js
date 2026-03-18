import { defineStore } from "pinia";
import messagingApi from "../services/messaging-api";

export const useMessagingStore = defineStore("messaging", {
  state: () => ({
    // États de base
    currentConversation: [],
    reservationContext: null, // Les infos de la résa liées à la conversation
    quickReplies: [],

    // Nouveaux états d'interface avancée
    activeTab: "email", // Onglet actif (email, whatsapp, notes)
    conversationCache: new Map(), // Cache frontend
    sendQueue: [], // File d'attente d'envoi

    // États UX
    unreadCount: 0,
    isLoading: false,
    isSending: false,
    isTyping: false,
    connectionStatus: "connected",
    error: null,
  }),

  getters: {
    // Filtrer les messages en fonction de l'onglet actif
    filteredMessages: (state) => {
      return state.currentConversation.filter(
        (message) => message.channel_source === state.activeTab,
      );
    },
    hasUnsent: (state) => state.sendQueue.length > 0,
  },

  actions: {
    switchTab(tab) {
      this.activeTab = tab;
    },
    /**
     * Charge l'historique d'une conversation (avec Cache intelligent)
     */
    async fetchConversation(reservationId, fromCache = true) {
      const cacheKey = `conversation_${reservationId}`;

      // 1. Vérification du cache frontend (5 minutes max)
      if (fromCache && this.conversationCache.has(cacheKey)) {
        const cached = this.conversationCache.get(cacheKey);
        if (Date.now() - cached.timestamp < 300000) {
          this.currentConversation = cached.data.messages;
          this.reservationContext = cached.data.reservation;
          this.unreadCount = cached.data.unreadCount;
          return; // On coupe court, pas d'appel réseau !
        }
      }

      this.isLoading = true;
      this.error = null;

      try {
        // Rétrocompatibilité avec ta méthode api.getHistory()
        const response = await messagingApi.getHistory(reservationId);
        const payload = response.data.data || response.data; // Sécurise la structure de réponse WP

        if (response.data.success || payload.success) {
          this.currentConversation = payload.messages || [];
          this.reservationContext = payload.reservation || null;
          this.unreadCount = payload.unread_count || 0;

          // 2. Mise en cache des résultats
          this.conversationCache.set(cacheKey, {
            data: {
              messages: this.currentConversation,
              reservation: this.reservationContext,
              unreadCount: this.unreadCount,
            },
            timestamp: Date.now(),
          });
        } else {
          throw new Error(
            payload.message || "Erreur lors du chargement de la conversation",
          );
        }
      } catch (err) {
        this.error = err.message || "Erreur réseau";
        console.error("Messaging Store Error:", err);
      } finally {
        this.isLoading = false;
      }
    },

    /**
     * Envoi d'un message avec mise à jour optimiste (Feedback instantané)
     */
    async sendMessage(payload) {
      this.isSending = true;
      this.error = null;

      // 1. Ajout optimiste à l'interface
      const tempId = "temp_" + Date.now();
      const optimisticMessage = {
        id: tempId,
        corps: payload.custom_body || "Message envoyé...",
        channel_source: payload.channel_source || this.activeTab,
        direction: "sortant",
        sender_type: "host",
        statut_envoi: "brouillon", // Statut temporaire
        date_creation: new Date().toISOString(),
        formatted_date: "À l'instant",
        css_classes: `pc-msg-bubble pc-msg--host pc-msg--outgoing pc-msg--${this.activeTab}`,
        sender_avatar: "🏠",
        sender_name: "Envoi en cours...",
        status_badge: {
          text: "Envoi...",
          class: "pc-badge--draft",
          icon: "⏳",
        },
      };

      this.currentConversation.push(optimisticMessage);

      try {
        const response = await messagingApi.sendMessage(payload);
        const responseData = response.data.data || response.data;

        if (response.data.success || responseData.success) {
          // 2. Remplacer le message temporaire par le vrai message confirmé par le backend PHP
          const index = this.currentConversation.findIndex(
            (m) => m.id === tempId,
          );
          if (index !== -1 && responseData.new_message) {
            this.currentConversation[index] = responseData.new_message;
          } else if (index !== -1) {
            // Fallback
            this.currentConversation[index].statut_envoi = "envoye";
            this.currentConversation[index].status_badge = {
              text: "Envoyé",
              class: "pc-badge--success",
              icon: "✅",
            };
            this.currentConversation[index].sender_name = "Équipe";
          }

          // Invalider le cache pour forcer un rechargement frais la prochaine fois
          this.conversationCache.delete(
            `conversation_${payload.reservation_id}`,
          );
        } else {
          throw new Error(
            responseData.message || "Erreur lors de l'envoi du message.",
          );
        }
      } catch (err) {
        this.error = err.message || "Erreur réseau";
        // 3. Marquer le message optimiste comme "Échec" visuellement
        const index = this.currentConversation.findIndex(
          (m) => m.id === tempId,
        );
        if (index !== -1) {
          this.currentConversation[index].statut_envoi = "echec";
          this.currentConversation[index].status_badge = {
            text: "Échec",
            class: "pc-badge--error",
            icon: "❌",
          };
        }
      } finally {
        this.isSending = false;
      }
    },

    /**
     * Marquer les messages comme lus
     */
    async markAsRead(messageIds) {
      try {
        const response = await messagingApi.markAsRead(messageIds);
        const responseData = response.data.data || response.data;

        if (response.data.success || responseData.success) {
          // Mettre à jour les messages localement
          messageIds.forEach((id) => {
            const msg = this.currentConversation.find((m) => m.id === id);
            if (msg && !msg.is_read) {
              msg.is_read = true;
              if (msg.status_badge) {
                msg.status_badge.text = "Lu";
                msg.status_badge.icon = "👁️";
              }
            }
          });

          // Diminuer le compteur global d'onglets
          this.unreadCount = Math.max(0, this.unreadCount - messageIds.length);
        }
      } catch (err) {
        console.error("Messaging Store Error (markAsRead):", err);
      }
    },
  },
});
