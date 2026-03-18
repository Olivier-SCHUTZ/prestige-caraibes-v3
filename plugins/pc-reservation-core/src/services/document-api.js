import apiClient from "./api-client";

export default {
  /**
   * Récupère la liste des templates disponibles pour une réservation (devis, facture, contrat...)
   */
  getTemplates(reservationId) {
    return apiClient.post("", {
      action: "pc_get_documents_templates",
      reservation_id: reservationId,
    });
  },

  /**
   * Récupère la liste des documents PDF déjà générés pour une réservation
   */
  getDocuments(reservationId) {
    return apiClient.post("", {
      action: "pc_get_reservation_files",
      reservation_id: reservationId,
    });
  },

  /**
   * Demande la génération d'un document (DomPDF)
   */
  generateDocument(reservationId, templateId, forceRegenerate = false) {
    return apiClient.post("", {
      action: "pc_generate_document",
      reservation_id: reservationId,
      template_id: templateId,
      force: forceRegenerate,
    });
  },

  /**
   * (Nouveau) Supprime un document existant
   */
  deleteDocument(documentId, reservationId) {
    return apiClient.post("", {
      action: "pc_delete_document",
      document_id: documentId,
      reservation_id: reservationId,
    });
  },

  /**
   * (Nouveau) Construit l'URL sécurisée pour le téléchargement via nonce
   */
  getSecureDownloadUrl(filename, reservationId) {
    // On suppose que window.pcReservationNonce est injecté par le backend
    const ajaxUrl = window.pcAjaxUrl || "/wp-admin/admin-ajax.php";
    const nonce = window.pcReservationNonce || "";

    return `${ajaxUrl}?action=pc_secure_download&filename=${encodeURIComponent(filename)}&reservation_id=${reservationId}&nonce=${nonce}`;
  },
};
