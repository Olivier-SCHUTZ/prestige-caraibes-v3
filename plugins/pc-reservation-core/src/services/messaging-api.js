import apiClient from "./api-client";

export default {
  /**
   * Récupère l'historique d'une conversation pour une réservation
   */
  getHistory(reservationId) {
    return apiClient.post("", {
      action: "pc_get_conversation_history",
      reservation_id: reservationId,
    });
  },

  /**
   * Envoie un nouveau message (gère aussi les templates et pièces jointes)
   */
  sendMessage(payload) {
    // Transformation dynamique en FormData pour supporter l'upload de fichiers vers wp_ajax
    const formData = new FormData();
    formData.append("action", "pc_send_message");

    Object.keys(payload).forEach((key) => {
      if (payload[key] !== null && payload[key] !== undefined) {
        formData.append(key, payload[key]);
      }
    });

    return apiClient.post("", formData, {
      headers: {
        "Content-Type": "multipart/form-data",
      },
    });
  },

  /**
   * Marque les messages comme lus
   */
  markAsRead(messageIds) {
    return apiClient.post("", {
      action: "pc_mark_messages_read",
      message_ids: messageIds,
    });
  },

  /**
   * Récupère les réponses rapides (templates)
   */
  getQuickReplies(reservationId = 0) {
    return apiClient.post("", {
      action: "pc_get_quick_replies",
      reservation_id: reservationId,
    });
  },

  /**
   * NOUVEAU : Récupère le résumé des conversations pour le dashboard principal
   */
  getConversationsDashboard() {
    return apiClient.post("", {
      action: "pc_get_conversations_dashboard",
    });
  },

  /**
   * NOUVEAU : Recherche avancée dans les messages
   */
  searchMessages(query, filters = {}) {
    return apiClient.post("", {
      action: "pc_search_messages",
      query: query,
      channel: filters.channel || "",
      date_from: filters.date_from || "",
      date_to: filters.date_to || "",
    });
  },
};
