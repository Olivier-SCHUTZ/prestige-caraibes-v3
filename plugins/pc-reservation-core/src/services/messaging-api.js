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
    // payload contient reservation_id, template_id, custom_subject, custom_body, etc.
    return apiClient.post(
      "",
      {
        action: "pc_send_message",
        ...payload,
      },
      {
        // Configuration spécifique si on envoie des fichiers (FormData) plus tard
        headers: {
          "Content-Type": "multipart/form-data",
        },
      },
    );
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
};
