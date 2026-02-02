/**
 * Module Messagerie PC Reservation Core
 * Gestion complète de la messagerie : templates, messages libres, historique
 */

(function () {
  "use strict";

  // Vérification de l'existence de l'objet global PCR
  if (!window.PCR) {
    window.PCR = {};
  }

  /**
   * Module Messagerie
   */
  window.PCR.Messaging = {
    /**
     * Initialise le module
     */
    init: function () {
      // Récupération des paramètres au moment de l'exécution
      const params = window.pcResaParams || {};
      const ajaxUrl = params.ajaxUrl || "";
      const nonce = params.manualNonce || "";

      // Initialisation des écouteurs d'événements
      this.attachEventListeners();

      console.log("[PCR.Messaging] Module initialisé");
    },

    /**
     * Attache tous les écouteurs d'événements liés à la messagerie
     */
    attachEventListeners: function () {
      // Ouverture popup "Envoyer un message"
      document.addEventListener(
        "click",
        this.handleOpenMessageModal.bind(this),
      );

      // Ouverture popup "Voir plus" (lecture)
      document.addEventListener("click", this.handleViewFullMessage.bind(this));

      // Gestion du switch templates/custom
      this.attachTemplateSwitch();

      // Envoi de messages
      this.attachMessageSending();
    },

    /**
     * Gère l'ouverture du popup "Envoyer un message"
     * @param {Event} event - Événement de clic
     */
    handleOpenMessageModal: function (event) {
      const msgBtn = event.target.closest(".pc-resa-open-msg-modal");
      if (!msgBtn) return;

      event.preventDefault();

      // Remplissage des champs cachés
      document.getElementById("pc-msg-resa-id").value = msgBtn.dataset.resaId;
      document.getElementById("pc-msg-client-name").textContent =
        msgBtn.dataset.client;

      // Réinitialisation de l'interface
      const tplSelect = document.getElementById("pc-msg-template");
      tplSelect.value = "";
      document.getElementById("pc-msg-custom-area").style.display = "none";
      document.getElementById("pc-msg-template-hint").style.display = "block";

      const feedback = document.getElementById("pc-msg-feedback");
      feedback.style.display = "none";
      feedback.className = "";

      // Affichage Popup
      document.getElementById("pc-send-message-popup").hidden = false;
    },

    /**
     * Gère l'ouverture du popup "Voir plus" pour la lecture des messages
     * @param {Event} event - Événement de clic
     */
    handleViewFullMessage: function (event) {
      const seeMoreBtn = event.target.closest(
        '[data-action="view-full-message"]',
      );
      if (!seeMoreBtn) return;

      event.preventDefault();
      const content = seeMoreBtn.getAttribute("data-content");

      // Injection du contenu dans la modale de lecture
      const viewer = document.getElementById("pc-read-message-content");
      viewer.innerHTML = content; // Affiche le HTML (br, p...)

      document.getElementById("pc-read-message-popup").hidden = false;
    },

    /**
     * Attache l'écouteur pour le switch "Template" vs "Message Libre"
     */
    attachTemplateSwitch: function () {
      const tplSelect = document.getElementById("pc-msg-template");
      if (!tplSelect) return;

      tplSelect.addEventListener("change", function () {
        const isCustom = this.value === "custom";
        document.getElementById("pc-msg-custom-area").style.display = isCustom
          ? "block"
          : "none";
        document.getElementById("pc-msg-template-hint").style.display = isCustom
          ? "none"
          : "block";
      });
    },

    /**
     * Attache l'écouteur pour l'envoi de messages avec protection anti-doublon
     */
    attachMessageSending: function () {
      const msgSendBtn = document.getElementById("pc-msg-send-btn");
      const feedbackBox = document.getElementById("pc-msg-feedback");

      if (!msgSendBtn) return;

      // Astuce : On clone le bouton pour tuer tous les anciens écouteurs parasites
      const newBtn = msgSendBtn.cloneNode(true);
      msgSendBtn.parentNode.replaceChild(newBtn, msgSendBtn);

      newBtn.addEventListener("click", (e) => {
        e.preventDefault();

        // Protection visuelle
        if (newBtn.disabled) return;

        // Récupération des paramètres au moment de l'exécution
        const params = window.pcResaParams || {};
        const ajaxUrl = params.ajaxUrl || "";
        const nonce = params.manualNonce || "";

        // Récupération des valeurs
        const id = document.getElementById("pc-msg-resa-id").value;
        const templateId = document.getElementById("pc-msg-template").value;
        const customSubject = document.getElementById(
          "pc-msg-custom-subject",
        ).value;
        const customBody = document.getElementById("pc-msg-custom-body").value;

        // Validation
        if (!templateId) {
          this.showFeedback(
            "⚠️ Veuillez choisir un modèle ou 'Nouveau message'.",
            false,
          );
          return;
        }
        if (
          templateId === "custom" &&
          (!customSubject.trim() || !customBody.trim())
        ) {
          this.showFeedback(
            "⚠️ Le sujet et le message sont obligatoires.",
            false,
          );
          return;
        }

        // UI Loading
        const originalText = newBtn.textContent;
        newBtn.textContent = "Envoi en cours...";
        newBtn.disabled = true;
        feedbackBox.style.display = "none";

        // Préparation Données
        const formData = new URLSearchParams();
        formData.append("action", "pc_send_message");
        formData.append("nonce", nonce);
        formData.append("reservation_id", id);
        formData.append("template_id", templateId);

        if (templateId === "custom") {
          formData.append("custom_subject", customSubject);
          formData.append("custom_body", customBody);
        }

        // Envoi AJAX
        fetch(ajaxUrl, {
          method: "POST",
          body: formData,
        })
          .then((r) => r.json())
          .then((data) => {
            if (data.success) {
              this.showFeedback("✅ Message envoyé !", true);
              setTimeout(() => {
                document.getElementById("pc-send-message-popup").hidden = true;
                window.location.reload();
              }, 1000);
            } else {
              throw new Error(data.data.message || "Erreur inconnue");
            }
          })
          .catch((err) => {
            console.error(err);
            this.showFeedback(
              "❌ Erreur : " + (err.message || "Technique"),
              false,
            );
            newBtn.textContent = originalText;
            newBtn.disabled = false;
          });
      });
    },

    /**
     * Affiche un message de feedback dans la popup de messagerie
     * @param {string} msg - Le message à afficher
     * @param {boolean} isSuccess - Indique si c'est un succès ou une erreur
     */
    showFeedback: function (msg, isSuccess) {
      const feedbackBox = document.getElementById("pc-msg-feedback");
      if (!feedbackBox) return;

      feedbackBox.textContent = msg;
      feedbackBox.style.background = isSuccess ? "#dcfce7" : "#fee2e2";
      feedbackBox.style.color = isSuccess ? "#15803d" : "#b91c1c";
      feedbackBox.style.display = "block";
    },

    /**
     * Charge les templates disponibles pour une réservation
     * @param {string} reservationId - ID de la réservation
     * @returns {Promise} - Promesse de chargement des templates
     */
    loadTemplates: function (reservationId) {
      // Récupération des paramètres au moment de l'exécution
      const params = window.pcResaParams || {};
      const ajaxUrl = params.ajaxUrl || "";
      const nonce = params.manualNonce || "";

      if (!reservationId) {
        return Promise.resolve(null);
      }

      const templateSelect = document.getElementById("pc-msg-template");
      if (!templateSelect) {
        return Promise.resolve(null);
      }

      // État de chargement
      templateSelect.innerHTML =
        '<option value="">Chargement des modèles...</option>';
      templateSelect.disabled = true;

      const formData = new URLSearchParams();
      formData.append("action", "pc_get_message_templates");
      formData.append("reservation_id", reservationId);
      formData.append("nonce", nonce);

      return fetch(ajaxUrl, {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (!data || !data.success) {
            templateSelect.innerHTML =
              '<option value="">❌ Erreur de chargement</option>';
            templateSelect.disabled = false;
            return null;
          }

          // Construction des options
          let html = '<option value="">-- Choisir un modèle --</option>';
          if (data.data && Array.isArray(data.data.templates)) {
            data.data.templates.forEach((template) => {
              const safeId = PCR.Utils.escapeHtml(template.id || "");
              const safeLabel = PCR.Utils.escapeHtml(template.label || "");
              html += `<option value="${safeId}">${safeLabel}</option>`;
            });
          }
          html += '<option value="custom">📝 Nouveau message libre</option>';

          templateSelect.innerHTML = html;
          templateSelect.disabled = false;

          return data.data;
        })
        .catch((error) => {
          console.error("[PCR.Messaging] Erreur chargement templates :", error);
          templateSelect.innerHTML =
            '<option value="">❌ Erreur technique</option>';
          templateSelect.disabled = false;
          return null;
        });
    },
  };
})();
