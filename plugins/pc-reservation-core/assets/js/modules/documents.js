/**
 * Module Documents - Gestion des documents PDF et templates
 *
 * @author Développeur Senior JS
 * @since v0.1.1
 */

(function () {
  "use strict";

  // Initialisation du namespace si nécessaire
  if (!window.PCR) {
    window.PCR = {};
  }

  /**
   * Module Documents - Gestion des PDF et templates
   */
  window.PCR.Documents = {
    // État d'initialisation
    _initialized: false,

    // Helper de sécurité local (évite le crash si PCR.Utils manque)
    escapeHtml: function (text) {
      if (!text) return "";
      return String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    },

    /**
     * Initialise le module Documents
     */
    init: function () {
      // DIAGNOSTIC : Vérification immédiate des données
      const params = window.pcResaParams;
      if (!params) {
        console.warn(
          "[PCR.Documents] ⚠️ window.pcResaParams est introuvable ! Le script PHP n'a pas envoyé les données.",
        );
      } else {
        console.log("[PCR.Documents] ✅ Params trouvés. URL:", params.ajaxUrl);
      }

      if (this._initialized) return;

      this._attachEventListeners();
      this._initialized = true;

      // Exposition des fonctions globales pour compatibilité
      window.pc_reload_documents = this.reloadDocuments.bind(this);
      window.pc_load_templates = this.loadTemplates.bind(this);

      console.log("[PCR.Documents] Module initialisé");
    },

    /**
     * Attache tous les écouteurs d'événements liés aux documents
     * @private
     */
    _attachEventListeners: function () {
      // Génération de PDF
      document.addEventListener("click", this._handleGeneratePDF.bind(this));

      // Fermeture de la modale de préview PDF
      this._attachPdfModalEvents();
    },

    /**
     * Attache les événements de la modale PDF
     * @private
     */
    _attachPdfModalEvents: function () {
      const closePdfBtn = document.getElementById("pc-close-pdf-modal");
      if (closePdfBtn) {
        closePdfBtn.addEventListener("click", this.closePdfPreview.bind(this));
      }

      const pdfModal = document.getElementById("pc-pdf-preview-modal");
      if (pdfModal) {
        pdfModal.addEventListener("click", (e) => {
          if (e.target === pdfModal) {
            this.closePdfPreview();
          }
        });
      }
    },

    /**
     * Parse une réponse JSON du serveur avec nettoyage
     * @param {string} rawText - Texte brut reçu du serveur
     * @returns {Object|null} Objet JSON parsé ou null
     * @private
     */
    _parseServerJson: function (rawText) {
      if (!rawText) {
        return null;
      }
      const jsonStart = rawText.indexOf("{");
      const cleanText = jsonStart >= 0 ? rawText.slice(jsonStart) : rawText;
      try {
        return JSON.parse(cleanText);
      } catch (error) {
        console.error("[PCR.Documents] JSON invalide", error, rawText);
        return null;
      }
    },

    /**
     * Met la liste des documents en état de chargement
     * @param {HTMLElement} container - Container des documents
     * @private
     */
    _setDocumentsLoading: function (container) {
      const tbody = container.querySelector(".pc-docs-tbody");
      if (!tbody) {
        return;
      }
      tbody.innerHTML =
        '<tr><td colspan="4" style="text-align:center; padding:15px; color:#2271b1;">Chargement...</td></tr>';
    },

    /**
     * Affiche les lignes de documents dans le tableau
     * @param {HTMLElement} container - Container des documents
     * @param {Array} documents - Liste des documents
     * @private
     */
    _renderDocumentsRows: function (container, documents) {
      const tbody = container.querySelector(".pc-docs-tbody");
      if (!tbody) {
        return;
      }

      if (!Array.isArray(documents) || documents.length === 0) {
        tbody.innerHTML =
          '<tr><td colspan="4" style="text-align:center; padding:15px; color:#999;">Aucun document.</td></tr>';
        return;
      }

      const rows = documents
        .map(
          (doc) =>
            `<tr>
                    <td style="padding:8px;">${doc.type_doc || ""}</td>
                    <td style="padding:8px;">${doc.nom_fichier || ""}</td>
                    <td style="padding:8px;">${doc.date_creation || ""}</td>
                    <td style="padding:8px; text-align:right;"><a href="${doc.url_fichier}" target="_blank" rel="noopener">👁️ Voir</a></td>
                </tr>`,
        )
        .join("");
      tbody.innerHTML = rows;
    },

    /**
     * Affiche une erreur dans la liste des documents
     * @param {HTMLElement} container - Container des documents
     * @param {string} message - Message d'erreur
     * @private
     */
    _showDocumentsError: function (container, message) {
      const tbody = container.querySelector(".pc-docs-tbody");
      // Utilisation de this.escapeHtml (local) pour la sécurité
      if (tbody) {
        tbody.innerHTML = `<tr><td colspan="4" style="color:red; text-align:center;">${this.escapeHtml(message || "Erreur serveur")}</td></tr>`;
      }
    },

    /**
     * Recharge la liste des documents pour une réservation
     * @param {string|number} reservationId - ID de la réservation
     * @returns {Promise} Promise de chargement
     */
    reloadDocuments: function (reservationId) {
      // Récupération des paramètres au moment de l'exécution
      const params = window.pcResaParams || {};
      const ajaxUrl = params.ajaxUrl || "";
      const nonce = params.manualNonce || "";

      if (!reservationId) {
        return Promise.resolve(null);
      }

      const modalContent = document.getElementById("pc-resa-modal-content");
      if (!modalContent) {
        return Promise.resolve(null);
      }

      const container = modalContent.querySelector(
        '.pc-documents-list-container[data-resa-id="' + reservationId + '"]',
      );
      if (!container) {
        return Promise.resolve(null);
      }

      this._setDocumentsLoading(container);

      const formData = new URLSearchParams();
      formData.append("action", "pc_get_documents_list");
      formData.append("reservation_id", reservationId);
      formData.append("nonce", nonce);

      return fetch(ajaxUrl, {
        method: "POST",
        body: formData,
      })
        .then((response) => response.text())
        .then((rawText) => {
          const payload = this._parseServerJson(rawText);
          if (!payload || !payload.success) {
            const message =
              (payload &&
                payload.data &&
                (payload.data.message || payload.data.error)) ||
              "Erreur lors du chargement des documents.";
            this._showDocumentsError(container, message);
            return null;
          }

          this._renderDocumentsRows(container, payload.data);
          return payload;
        })
        .catch((error) => {
          console.error("[PCR.Documents] Erreur de chargement", error);
          this._showDocumentsError(
            container,
            "Erreur technique pendant le chargement des documents.",
          );
          return null;
        });
    },

    /**
     * Charge les templates disponibles pour une réservation
     * @param {string|number} reservationId - ID de la réservation
     * @returns {Promise} Promise de chargement
     */
    loadTemplates: function (reservationId) {
      // Récupération des paramètres au moment de l'exécution
      const params = window.pcResaParams || {};
      const ajaxUrl = params.ajaxUrl || "";
      const nonce = params.manualNonce || "";

      if (!reservationId) {
        return Promise.resolve(null);
      }

      const modalContent = document.getElementById("pc-resa-modal-content");
      if (!modalContent) {
        return Promise.resolve(null);
      }

      const templateSelect = modalContent.querySelector(
        ".pc-doc-template-select",
      );
      if (!templateSelect) {
        return Promise.resolve(null);
      }

      // État de chargement
      templateSelect.innerHTML =
        '<option value="">Chargement des modèles...</option>';
      templateSelect.disabled = true;

      // Appel AJAX
      const formData = new URLSearchParams();
      formData.append("action", "pc_get_documents_templates");
      formData.append("reservation_id", reservationId);
      formData.append("nonce", nonce);

      return fetch(ajaxUrl, {
        method: "POST",
        body: formData,
      })
        .then((response) => response.text())
        .then((rawText) => {
          const payload = this._parseServerJson(rawText);

          // Vérification que la réponse est valide
          if (!payload || !payload.success || !payload.data) {
            const message =
              (payload && payload.data && payload.data.message) ||
              "Erreur lors du chargement.";
            templateSelect.innerHTML = `<option value="">❌ ${PCR.Utils.escapeHtml(message)}</option>`;
            templateSelect.disabled = false;
            return null;
          }

          const response = payload.data;
          let html = '<option value="">-- Choisir un modèle --</option>';

          // SÉCURITÉ : On vérifie que l'objet documents existe bien
          const docs = response.documents || {};

          // Groupe A : Documents Natifs (Sécurisé)
          if (
            docs.native &&
            Array.isArray(docs.native.items) &&
            docs.native.items.length > 0
          ) {
            html += `<optgroup label="${this.escapeHtml(docs.native.label || "Documents Natifs")}">`;
            docs.native.items.forEach((item) => {
              html += `<option value="${this.escapeHtml(item.id)}">${this.escapeHtml(item.label)}</option>`;
            });
            html += "</optgroup>";
          }

          // Groupe B : Modèles Personnalisés
          if (
            docs.custom &&
            Array.isArray(docs.custom.items) &&
            docs.custom.items.length > 0
          ) {
            html += `<optgroup label="${this.escapeHtml(docs.custom.label || "Modèles Personnalisés")}">`;

            docs.custom.items.forEach((item) => {
              html += `<option value="${this.escapeHtml(item.id)}">${this.escapeHtml(item.label)}</option>`;
            });
            html += "</optgroup>";
          }

          // Cas où aucun document n'est disponible
          if ((response.total_count || 0) === 0) {
            html = '<option value="">Aucun modèle disponible</option>';
          }

          // Injection du HTML
          templateSelect.innerHTML = html;
          templateSelect.disabled = false;

          // Sélection automatique (Smart Select)
          const firstValidOption = templateSelect.querySelector(
            'option[value]:not([value=""])',
          );
          // Si on a un seul choix, on le sélectionne direct
          if (firstValidOption && (response.total_count || 0) === 1) {
            templateSelect.value = firstValidOption.value;
          }

          return response;
        })
        .catch((error) => {
          console.error("[PCR.Documents] Erreur critique :", error);
          templateSelect.innerHTML =
            '<option value="">❌ Erreur technique</option>';
          templateSelect.disabled = false;
          return null;
        });
    },

    /**
     * Ouvre la modale de préview PDF
     * @param {string} url - URL du PDF à afficher
     */
    openPdfPreview: function (url) {
      const modal = document.getElementById("pc-pdf-preview-modal");
      const iframe = document.getElementById("pc-pdf-iframe");

      if (modal && iframe) {
        iframe.src = url;
        modal.style.display = "flex";
        return;
      }

      // Fallback : ouvre dans un nouvel onglet
      window.open(url, "_blank");
    },

    /**
     * Ferme la modale de préview PDF
     */
    closePdfPreview: function () {
      const modal = document.getElementById("pc-pdf-preview-modal");
      const iframe = document.getElementById("pc-pdf-iframe");

      if (modal) {
        modal.style.display = "none";
      }
      if (iframe) {
        iframe.src = "";
      }
    },

    /**
     * Gère le clic sur le bouton "Générer PDF"
     * @param {Event} e - Événement de clic
     * @private
     */
    _handleGeneratePDF: function (e) {
      const btn = e.target.closest(".pc-btn-generate-doc");
      if (!btn || btn.disabled) {
        return;
      }

      e.preventDefault();

      // Récupération des paramètres au moment de l'exécution
      const params = window.pcResaParams || {};
      const ajaxUrl = params.ajaxUrl || "";
      const nonce = params.manualNonce || "";

      const wrapper = btn.closest(".pc-doc-actions");
      const reservationId = btn.getAttribute("data-resa-id");
      const templateSelect = wrapper
        ? wrapper.querySelector(".pc-doc-template-select")
        : null;
      const forceCheckbox = wrapper
        ? wrapper.querySelector(".pc-doc-force-regen")
        : null;

      if (!reservationId || !templateSelect) {
        console.error("[PCR.Documents] Contexte génération incomplet");
        return;
      }

      if (!templateSelect.value) {
        alert("⚠️ Veuillez sélectionner un modèle.");
        templateSelect.focus();
        templateSelect.style.borderColor = "#ef4444";
        return;
      }
      templateSelect.style.borderColor = "#ccc";

      const originalContent = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML =
        '<span class="spinner is-active" style="float:none;margin:0;"></span>';

      const formData = new FormData();
      formData.append("action", "pc_generate_document");
      formData.append("reservation_id", reservationId);
      formData.append("template_id", templateSelect.value);
      formData.append(
        "force",
        forceCheckbox && forceCheckbox.checked ? "true" : "false",
      );
      formData.append("nonce", nonce);

      fetch(ajaxUrl, {
        method: "POST",
        body: formData,
      })
        .then((response) => response.text())
        .then((rawText) => {
          const payload = this._parseServerJson(rawText);

          // Gestion des popups d'erreur (blocage)
          if (
            payload &&
            !payload.success &&
            payload.data &&
            (payload.data.error_code === "missing_deposit" ||
              payload.data.error_code === "document_exists")
          ) {
            const popup = document.getElementById("pc-invoice-blocked-popup");
            const msgEl = document.getElementById("pc-invoice-blocked-msg");

            if (popup && msgEl) {
              msgEl.innerHTML = payload.data.message; // innerHTML pour permettre des sauts de ligne
              popup.hidden = false;
            } else {
              alert(payload.data.message);
            }

            // Réinitialisation du bouton
            btn.disabled = false;
            btn.innerHTML = originalContent;
            return;
          }

          if (
            !payload ||
            !payload.success ||
            !payload.data ||
            !payload.data.url
          ) {
            const message =
              (payload &&
                payload.data &&
                (payload.data.message || payload.data.error)) ||
              "Impossible de générer le document.";
            throw new Error(message);
          }

          // Succès : rechargement de la liste et préview
          this.reloadDocuments(reservationId);
          this.openPdfPreview(payload.data.url);
        })
        .catch((error) => {
          console.error("[PCR.Documents] Erreur génération", error);
          // Si c'est une erreur technique, on garde l'alerte simple
          if (error.message !== "missing_deposit") {
            alert("❌ " + (error.message || "Erreur technique."));
          }
        })
        .finally(() => {
          btn.disabled = false;
          btn.innerHTML = originalContent;
        });
    },
  };

  console.log("[PCR.Documents] Module chargé");
})();
