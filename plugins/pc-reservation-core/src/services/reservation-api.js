import apiClient from "./api-client";

export default {
  /**
   * Récupère la liste des réservations paginée et filtrée
   */
  getList(page = 1, filters = {}) {
    return apiClient.post("", {
      action: "pc_get_reservations_list",
      page: page,
      type: filters.type !== "all" ? filters.type : "", // On envoie le type si ce n'est pas "all"
    });
  },

  /**
   * NOUVEAU : Récupère les détails complets d'une réservation
   */
  getDetails(id) {
    return apiClient.post("", {
      action: "pc_get_reservation_details",
      reservation_id: id,
    });
  },

  /**
   * Récupère les logements et expériences pour les listes déroulantes
   */
  getBookingItems() {
    return apiClient.post("", {
      action: "pc_get_booking_items",
    });
  },

  /**
   * Récupère la configuration tarifaire et les dates bloquées d'un logement
   */
  getHousingConfig(logementId) {
    return apiClient.post("", {
      action: "pc_manual_logement_config",
      logement_id: logementId,
    });
  },

  /**
   * Création ou mise à jour d'une réservation manuelle/devis
   */
  createManual(payload) {
    return apiClient.post("", {
      action: "pc_manual_reservation_create",
      ...payload,
    });
  },

  /**
   * Annule une réservation
   */
  cancel(reservationId) {
    return apiClient.post("", {
      action: "pc_cancel_reservation",
      reservation_id: reservationId,
    });
  },

  /**
   * Confirme une réservation (devis -> confirmée)
   */
  confirm(reservationId) {
    return apiClient.post("", {
      action: "pc_confirm_reservation",
      reservation_id: reservationId,
    });
  },

  /**
   * Demande le calcul du devis au backend
   */
  calculatePrice(formData) {
    return apiClient.post("", {
      action: "pc_calculate_price",
      ...formData, // On envoie tout le contenu du formulaire (dates, type, item_id...)
    });
  },
};
