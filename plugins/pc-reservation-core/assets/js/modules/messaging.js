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
   * Module Messagerie - Phase 3 Channel Manager
   */
  window.PCR.Messaging = {
    // Variables d'état
    currentReservationId: null,
    currentClientName: null,
    isChannelManagerOpen: false,
    // ✨ NOUVEAU PHASE 4 : État des pièces jointes
    currentAttachment: null, // {name, filename, path}
    // 🆕 NOUVEAU : Gestion des onglets
    currentContext: "chat", // 'chat', 'email', 'notes'
    allMessages: [], // Stockage de tous les messages pour le filtrage

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

      console.log("[PCR.Messaging] Module Channel Manager initialisé");
    },

    /**
     * Attache tous les écouteurs d'événements liés à la messagerie
     */
    attachEventListeners: function () {
      // NOUVEAU : Ouverture Channel Manager
      document.addEventListener(
        "click",
        this.handleOpenChannelManager.bind(this),
      );

      // LEGACY : Ouverture popup "Envoyer un message" (pour compatibilité)
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

      // NOUVEAU : Gestion Channel Manager
      this.attachChannelManagerEvents();
    },

    /**
     * NOUVEAU : Gestion du Channel Manager
     */
    handleOpenChannelManager: function (event) {
      const openBtn = event.target.closest("#pc-open-messaging");
      if (!openBtn) return;

      event.preventDefault();

      const reservationId = openBtn.dataset.resaId;
      const clientName = openBtn.dataset.client;

      if (!reservationId) {
        console.error("[PCR.Messaging] ID de réservation manquant");
        return;
      }

      this.openChannelManager(reservationId, clientName || "Client");
    },

    /**
     * NOUVEAU : Ouvre l'interface Channel Manager
     */
    openChannelManager: function (reservationId, clientName) {
      this.currentReservationId = reservationId;
      this.currentClientName = clientName;
      this.isChannelManagerOpen = true;

      // Remplir les données du header
      this.populateChannelManagerHeader(reservationId, clientName);

      // Afficher la modale
      const modal = document.getElementById("pc-messaging-modal");
      if (modal) {
        modal.style.display = "flex";
      }

      // Charger la conversation
      this.loadConversation(reservationId);
    },

    /**
     * NOUVEAU : Remplit le header de la modale Channel Manager
     */
    populateChannelManagerHeader: function (reservationId, clientName) {
      // Nom du client
      const titleEl = document.getElementById("pc-chat-client-name");
      if (titleEl) {
        titleEl.textContent = clientName;
      }

      // Initiales pour l'avatar
      const initialEl = document.getElementById("pc-chat-client-initial");
      if (initialEl && clientName) {
        const names = clientName.trim().split(" ");
        let initials = names[0] ? names[0].charAt(0).toUpperCase() : "C";
        if (names.length > 1) {
          initials += names[1].charAt(0).toUpperCase();
        }
        initialEl.textContent = initials;
      }

      // Référence réservation
      const refEl = document.getElementById("pc-chat-reservation-ref");
      if (refEl) {
        refEl.textContent = "#" + reservationId;
      }

      // Cacher les champs cachés pour référence
      const hiddenId = document.getElementById(
        "pc-chat-current-reservation-id",
      );
      const hiddenName = document.getElementById("pc-chat-current-client-name");
      if (hiddenId) hiddenId.value = reservationId;
      if (hiddenName) hiddenName.value = clientName;
    },

    /**
     * NOUVEAU : Attache les événements du Channel Manager
     */
    attachChannelManagerEvents: function () {
      // Fermeture de la modale
      const closeBtn = document.getElementById("pc-messaging-modal-close-btn");
      const backdrop = document.getElementById("pc-messaging-modal-close");

      if (closeBtn) {
        closeBtn.addEventListener("click", () => {
          this.closeChannelManager();
        });
      }

      if (backdrop) {
        backdrop.addEventListener("click", () => {
          this.closeChannelManager();
        });
      }

      // Auto-expand du textarea
      const messageInput = document.getElementById("pc-chat-message-input");
      if (messageInput) {
        messageInput.addEventListener(
          "input",
          this.handleMessageInputChange.bind(this),
        );
        messageInput.addEventListener(
          "keydown",
          this.handleMessageInputKeydown.bind(this),
        );
      }

      // Bouton d'envoi
      const sendBtn = document.getElementById("pc-chat-send-btn");
      if (sendBtn) {
        sendBtn.addEventListener("click", this.handleSendMessage.bind(this));
      }

      // Templates rapides
      const templatesBtn = document.getElementById("pc-chat-templates-btn");
      if (templatesBtn) {
        templatesBtn.addEventListener(
          "click",
          this.loadAndToggleTemplates.bind(this),
        );
      }

      // Bouton WhatsApp
      const whatsappBtn = document.getElementById("pc-chat-whatsapp-btn");
      if (whatsappBtn) {
        whatsappBtn.addEventListener("click", this.openWhatsApp.bind(this));
      }

      // ✨ NOUVEAU PHASE 4 : Bouton pièces jointes
      const attachmentsBtn = document.getElementById("pc-chat-attachments-btn");
      if (attachmentsBtn) {
        attachmentsBtn.addEventListener(
          "click",
          this.toggleAttachments.bind(this),
        );
      }

      // Fermeture du popover des pièces jointes
      const attachmentsClose = document.querySelector(
        ".pc-chat-attachments__close",
      );
      if (attachmentsClose) {
        attachmentsClose.addEventListener("click", () => {
          this.hideAttachmentsPopover();
        });
      }

      // ✨ NOUVEAU : Bouton d'upload local
      const uploadBtn = document.getElementById("pc-attachment-upload-btn");
      const fileInput = document.getElementById("pc-msg-file-upload");

      if (uploadBtn && fileInput) {
        uploadBtn.addEventListener("click", () => {
          fileInput.click();
        });

        fileInput.addEventListener(
          "change",
          this.handleFileSelection.bind(this),
        );
      }

      // Marquer comme lu
      const markReadBtn = document.getElementById("pc-chat-mark-read");
      if (markReadBtn) {
        markReadBtn.addEventListener("click", this.markAllAsRead.bind(this));
      }

      // 🆕 NOUVEAU : Gestion des onglets
      this.attachTabEvents();
    },

    /**
     * NOUVEAU : Ferme le Channel Manager
     */
    closeChannelManager: function () {
      const modal = document.getElementById("pc-messaging-modal");
      if (modal) {
        modal.style.display = "none";
      }

      this.currentReservationId = null;
      this.currentClientName = null;
      this.isChannelManagerOpen = false;

      // Nettoyer le contenu
      const container = document.getElementById("pc-chat-container");
      if (container) {
        container.innerHTML =
          '<div class="pc-chat-loading"><div class="pc-chat-loading__spinner"></div><p>Chargement de la conversation...</p></div>';
      }
    },

    /**
     * NOUVEAU : Charge la conversation via l'API existante
     */
    loadConversation: function (reservationId) {
      const params = window.pcResaParams || {};
      const ajaxUrl = params.ajaxUrl || "";
      const nonce = params.manualNonce || "";

      if (!ajaxUrl || !nonce) {
        console.error("[PCR.Messaging] Paramètres AJAX manquants");
        return;
      }

      const container = document.getElementById("pc-chat-container");
      if (!container) return;

      // Affichage loading
      container.innerHTML =
        '<div class="pc-chat-loading"><div class="pc-chat-loading__spinner"></div><p>Chargement de la conversation...</p></div>';

      const formData = new URLSearchParams();
      formData.append("action", "pc_get_conversation_history");
      formData.append("nonce", nonce);
      formData.append("reservation_id", reservationId);

      fetch(ajaxUrl, {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            this.renderConversation(data.data);
          } else {
            this.showConversationError(
              data.data?.message || "Erreur de chargement",
            );
          }
        })
        .catch((error) => {
          console.error(
            "[PCR.Messaging] Erreur chargement conversation:",
            error,
          );
          this.showConversationError("Erreur technique");
        });
    },

    /**
     * NOUVEAU : Affiche les messages dans le Channel Manager
     */
    renderConversation: function (conversationData) {
      const container = document.getElementById("pc-chat-container");
      if (!container) return;

      const messages = conversationData.messages || [];

      if (messages.length === 0) {
        container.innerHTML = `
          <div class="pc-chat-empty">
            <p>Aucun message échangé avec ce client.</p>
            <p>Commencez la conversation en écrivant votre premier message ci-dessous.</p>
          </div>
        `;
        return;
      }

      let html = "";
      messages.forEach((message) => {
        html += this.renderMessage(message);
      });

      container.innerHTML = html;

      // Scroller vers le bas
      this.scrollToBottom();
    },

    /**
     * NOUVEAU : Render un message individuel
     */
    renderMessage: function (message) {
      const isHost = message.sender_type === "host";
      const bubbleClass = isHost ? "pc-msg--host" : "pc-msg--guest";

      // Formatage de la date
      const dateObj = new Date(message.date_creation);
      const timeStr = dateObj.toLocaleTimeString("fr-FR", {
        hour: "2-digit",
        minute: "2-digit",
      });

      // Status icon
      let statusIcon = "";
      if (isHost) {
        if (message.statut_envoi === "envoye") {
          statusIcon = message.is_read ? "✓✓" : "✓";
        } else {
          statusIcon = "⏳";
        }
      }

      // Contenu avec gestion "voir plus"
      let content = message.corps || "";
      let seeMoreBtn = "";

      if (content.length > 200) {
        const truncated = content.substring(0, 200) + "...";
        seeMoreBtn = `
          <button type="button" class="pc-msg-see-more" 
                  data-action="view-full-message" 
                  data-content="${this.escapeHtml(content)}">
            Voir plus
          </button>
        `;
        content = truncated;
      }

      return `
        <div class="pc-msg-bubble ${bubbleClass}" data-message-id="${message.id}">
          <div class="pc-msg-meta">
            <strong>${this.escapeHtml(message.sujet || "Message")}</strong>
            ${message.canal === "whatsapp" ? "📱" : "📧"}
          </div>
          <div class="pc-msg-content">
            ${this.escapeHtml(content).replace(/\n/g, "<br>")}
            ${seeMoreBtn}
          </div>
          <div class="pc-msg-time">
            ${timeStr} ${statusIcon}
          </div>
        </div>
      `;
    },

    /**
     * NOUVEAU : Gestion des changements dans le textarea
     */
    handleMessageInputChange: function (event) {
      const textarea = event.target;
      const sendBtn = document.getElementById("pc-chat-send-btn");

      // Auto-resize
      textarea.style.height = "auto";
      textarea.style.height = Math.min(textarea.scrollHeight, 120) + "px";

      // Activer/désactiver le bouton d'envoi
      if (sendBtn) {
        sendBtn.disabled = textarea.value.trim().length === 0;
      }
    },

    /**
     * NOUVEAU : Gestion des touches dans le textarea
     */
    handleMessageInputKeydown: function (event) {
      if (event.key === "Enter" && !event.shiftKey) {
        event.preventDefault();
        this.handleSendMessage();
      }
    },

    /**
     * NOUVEAU : Envoie un nouveau message
     */
    handleSendMessage: function () {
      const messageInput = document.getElementById("pc-chat-message-input");
      const sendBtn = document.getElementById("pc-chat-send-btn");

      if (!messageInput || !sendBtn || sendBtn.disabled) return;

      const messageText = messageInput.value.trim();
      if (!messageText) return;

      const params = window.pcResaParams || {};
      const ajaxUrl = params.ajaxUrl || "";
      const nonce = params.manualNonce || "";

      if (!this.currentReservationId) {
        console.error("[PCR.Messaging] ID de réservation manquant");
        return;
      }

      // UI Loading
      const originalText = sendBtn.innerHTML;
      sendBtn.innerHTML =
        '<div class="pc-chat-loading__spinner" style="width:16px;height:16px;"></div>';
      sendBtn.disabled = true;
      messageInput.disabled = true;

      // ✨ NOUVEAU PHASE 4 : Utiliser FormData pour supporter les uploads de fichiers
      let formData;
      let hasFile = this.currentAttachment && this.currentAttachment.file;

      if (hasFile) {
        // FormData pour les uploads de fichiers
        formData = new FormData();
        formData.append("file_upload", this.currentAttachment.file);
      } else {
        // URLSearchParams pour les requêtes normales
        formData = new URLSearchParams();
      }

      formData.append("action", "pc_send_message");
      formData.append("nonce", nonce);
      formData.append("reservation_id", this.currentReservationId);
      formData.append("template_id", "custom");
      const subjectInput = document.getElementById("pc-chat-subject-input");
      // On prend la valeur si l'input existe, sinon on met une valeur par défaut cohérente
      const realSubject =
        subjectInput && subjectInput.value.trim() !== ""
          ? subjectInput.value
          : "Nouveau message";
      formData.append("custom_subject", realSubject);
      formData.append("custom_body", messageText);

      // ✨ NOUVEAU PHASE 4 : Ajouter la pièce jointe système si sélectionnée
      if (this.currentAttachment && this.currentAttachment.path) {
        formData.append("attachment_path", this.currentAttachment.path);
      }

      fetch(ajaxUrl, {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            // Vider le champ
            messageInput.value = "";
            messageInput.style.height = "auto";

            // ✨ NOUVEAU PHASE 4 : Nettoyer les pièces jointes après envoi réussi
            this.removeAttachment();

            // ✨ NOUVEAU UX : Affichage instantané du message au lieu de recharger
            if (data.new_message) {
              this.appendNewMessageToChat(data.new_message);
            } else {
              // Fallback : recharger la conversation si pas de nouveau message
              this.loadConversation(this.currentReservationId);
            }
          } else {
            alert(
              "Erreur : " +
                (data.data?.message || "Impossible d'envoyer le message"),
            );
          }
        })
        .catch((error) => {
          console.error("[PCR.Messaging] Erreur envoi message:", error);
          alert("Erreur technique lors de l'envoi");
        })
        .finally(() => {
          // Restaurer l'UI
          sendBtn.innerHTML = originalText;
          sendBtn.disabled = false;
          messageInput.disabled = false;
          messageInput.focus();
        });
    },

    /**
     * NOUVEAU : Scroll vers le bas du chat
     */
    scrollToBottom: function () {
      const container = document.getElementById("pc-chat-container");
      if (container) {
        setTimeout(() => {
          container.scrollTop = container.scrollHeight;
        }, 100);
      }
    },

    /**
     * ✨ NOUVEAU UX : Ajoute instantanément un nouveau message au chat
     * sans avoir besoin de recharger toute la conversation
     */
    appendNewMessageToChat: function (messageData) {
      const container = document.getElementById("pc-chat-container");
      if (!container || !messageData) return;

      // Si le container est vide (état initial), utiliser renderConversation
      if (
        container.innerHTML.includes("pc-chat-loading") ||
        container.innerHTML.includes("pc-chat-empty")
      ) {
        this.loadConversation(this.currentReservationId);
        return;
      }

      // Créer l'élément du nouveau message avec animation
      const messageHtml = this.renderMessage(messageData);
      const tempDiv = document.createElement("div");
      tempDiv.innerHTML = messageHtml;
      const newMessageElement = tempDiv.firstChild;

      // Ajouter une classe pour l'animation d'entrée
      newMessageElement.style.opacity = "0";
      newMessageElement.style.transform = "translateY(20px)";

      // Ajouter le message au container
      container.appendChild(newMessageElement);

      // Animation d'apparition
      requestAnimationFrame(() => {
        newMessageElement.style.transition =
          "opacity 0.3s ease, transform 0.3s ease";
        newMessageElement.style.opacity = "1";
        newMessageElement.style.transform = "translateY(0)";
      });

      // Scroll vers le bas pour voir le nouveau message
      this.scrollToBottom();

      console.log("[PCR.Messaging] Message ajouté instantanément au chat");
    },

    /**
     * NOUVEAU : Affiche une erreur de conversation
     */
    showConversationError: function (message) {
      const container = document.getElementById("pc-chat-container");
      if (container) {
        container.innerHTML = `
          <div class="pc-chat-error">
            <p>❌ ${this.escapeHtml(message)}</p>
            <button onclick="PCR.Messaging.loadConversation('${this.currentReservationId}')" class="pc-btn pc-btn--secondary">
              Réessayer
            </button>
          </div>
        `;
      }
    },

    /**
     * NOUVEAU : Charge et affiche les réponses rapides
     */
    loadAndToggleTemplates: function () {
      const templatesPanel = document.getElementById("pc-chat-templates");
      const templatesList = document.getElementById("pc-chat-templates-list");

      if (!templatesPanel || !templatesList) return;

      const isVisible = templatesPanel.style.display !== "none";

      if (isVisible) {
        // Fermer le panel
        templatesPanel.style.display = "none";
        return;
      }

      // Afficher le panel avec loading
      templatesPanel.style.display = "block";
      templatesList.innerHTML =
        '<div class="pc-chat-loading"><div class="pc-chat-loading__spinner"></div><p>Chargement des réponses rapides...</p></div>';

      // Charger les templates via AJAX
      const params = window.pcResaParams || {};
      const ajaxUrl = params.ajaxUrl || "";
      const nonce = params.manualNonce || "";

      const formData = new URLSearchParams();
      formData.append("action", "pc_get_quick_replies");
      formData.append("nonce", nonce);
      formData.append("reservation_id", this.currentReservationId || "");

      fetch(ajaxUrl, {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success && data.data && data.data.templates) {
            this.renderQuickReplies(data.data.templates);
          } else {
            templatesList.innerHTML =
              '<p class="pc-templates-empty">Aucune réponse rapide configurée.</p>';
          }
        })
        .catch((error) => {
          console.error(
            "[PCR.Messaging] Erreur chargement réponses rapides:",
            error,
          );
          templatesList.innerHTML =
            '<p class="pc-templates-error">❌ Erreur de chargement</p>';
        });
    },

    /**
     * NOUVEAU : Affiche la liste des réponses rapides
     */
    renderQuickReplies: function (templates) {
      const templatesList = document.getElementById("pc-chat-templates-list");
      if (!templatesList) return;

      if (!templates || templates.length === 0) {
        templatesList.innerHTML =
          '<p class="pc-templates-empty">Aucun modèle disponible.</p>';
        return;
      }

      let html = "";
      templates.forEach((template) => {
        // On stocke TOUTES les infos dans les data-attributes
        html += `
          <div class="pc-template-item" 
               data-template-id="${template.id}" 
               data-template-content="${this.escapeHtml(template.content)}"
               data-template-subject="${this.escapeHtml(template.subject)}"
               data-template-category="${template.category}"
               data-attachment-key="${template.attachment_key || ""}"
               data-attachment-name="${template.attachment_name || ""}"
               title="${this.escapeHtml(template.title)}">
            <div class="pc-template-title">${template.title}</div> <div class="pc-template-preview">${this.escapeHtml(template.preview)}</div>
          </div>
        `;
      });
      // ... reste de la fonction (innerHTML, addEventListener) inchangé ...
      templatesList.innerHTML = html;
      templatesList.addEventListener("click", (event) => {
        const templateItem = event.target.closest(".pc-template-item");
        if (templateItem) this.insertTemplate(templateItem);
      });
    },

    /**
     * NOUVEAU : Insère un template dans le textarea
     */
    insertTemplate: function (templateItem) {
      // 1. Récupérer les données
      const content = templateItem.dataset.templateContent;
      const subject = templateItem.dataset.templateSubject;
      const category = templateItem.dataset.templateCategory;
      const attKey = templateItem.dataset.attachmentKey;
      const attName = templateItem.dataset.attachmentName;

      // 2. Intelligence Artificielle (UX) : Changement d'onglet
      // Si c'est un "Email Système" (Devis, Facture...), on bascule sur l'onglet Email
      // car WhatsApp ne supporte pas les sujets ni les PDF natifs de la même façon.
      if (category === "email_system" && this.currentContext !== "email") {
        this.switchTab("email");
      }

      // 3. Remplir le corps du message (avec remplacement variables)
      const messageInput = document.getElementById("pc-chat-message-input");
      if (messageInput) {
        let finalContent = content;
        if (this.currentReservationId && this.currentClientName) {
          finalContent = this.replaceTemplateVariables(content);
        }
        messageInput.value = finalContent;
        // Trigger pour auto-resize
        messageInput.dispatchEvent(new Event("input"));
      }

      // 4. Remplir le Sujet (si on est dans l'onglet Email)
      const subjectInput = document.getElementById("pc-chat-subject-input");
      if (subjectInput && subject) {
        // Remplacer aussi les variables dans le sujet !
        let finalSubject = subject;
        if (this.currentReservationId && this.currentClientName) {
          finalSubject = this.replaceTemplateVariables(subject);
        }
        subjectInput.value = finalSubject;
      }

      // 5. Gérer la Pièce Jointe (Le Chip)
      if (attKey && attName) {
        // On simule une pièce jointe sélectionnée
        this.currentAttachment = {
          name: attName,
          filename: attName + ".pdf", // Nom fictif pour l'affichage
          path: attKey, // ex: 'native_devis' -> sera traité par le backend
          type: "preset", // Nouveau type pour dire "c'est un preset système"
        };
        this.showAttachmentsChip();
      }

      // 6. Fermer le panneau
      const templatesPanel = document.getElementById("pc-chat-templates");
      if (templatesPanel) templatesPanel.style.display = "none";
    },

    /**
     * NOUVEAU : Remplace les variables dans un template
     */
    replaceTemplateVariables: function (content) {
      if (!this.currentClientName || !this.currentReservationId) {
        return content;
      }

      // Variables de base qu'on peut remplacer côté client
      const names = this.currentClientName.trim().split(" ");
      const prenom = names[0] || "Client";
      const nomComplet = this.currentClientName;

      const vars = {
        "{prenom}": prenom,
        "{prenom_client}": prenom,
        "{nom_client}": nomComplet,
        "{numero_resa}": this.currentReservationId,
      };

      let replacedContent = content;
      Object.keys(vars).forEach((key) => {
        const regex = new RegExp(key.replace(/[{}]/g, "\\$&"), "g");
        replacedContent = replacedContent.replace(regex, vars[key]);
      });

      return replacedContent;
    },

    /**
     * NOUVEAU : Ouvre WhatsApp avec le message pré-rempli
     */
    openWhatsApp: function () {
      if (!this.currentReservationId) {
        console.error(
          "[PCR.Messaging] ID de réservation manquant pour WhatsApp",
        );
        return;
      }

      // Récupérer le numéro de téléphone depuis l'API
      const params = window.pcResaParams || {};
      const ajaxUrl = params.ajaxUrl || "";
      const nonce = params.manualNonce || "";

      const formData = new URLSearchParams();
      formData.append("action", "pc_get_whatsapp_data");
      formData.append("nonce", nonce);
      formData.append("reservation_id", this.currentReservationId);

      fetch(ajaxUrl, {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success && data.data) {
            this.openWhatsAppLink(data.data.phone, data.data.message);
          } else {
            // Fallback: utiliser le contenu du textarea actuel
            const messageInput = document.getElementById(
              "pc-chat-message-input",
            );
            const message = messageInput ? messageInput.value.trim() : "";

            if (message) {
              // Essayer d'ouvrir sans numéro spécifique (l'utilisateur choisira)
              const whatsappUrl = `https://wa.me/?text=${encodeURIComponent(message)}`;
              window.open(whatsappUrl, "_blank");
            } else {
              alert(
                "Veuillez taper un message ou récupérer le numéro de téléphone du client.",
              );
            }
          }
        })
        .catch((error) => {
          console.error(
            "[PCR.Messaging] Erreur récupération données WhatsApp:",
            error,
          );

          // Fallback: utiliser le contenu du textarea
          const messageInput = document.getElementById("pc-chat-message-input");
          const message = messageInput ? messageInput.value.trim() : "";

          if (message) {
            const whatsappUrl = `https://wa.me/?text=${encodeURIComponent(message)}`;
            window.open(whatsappUrl, "_blank");
          } else {
            alert(
              "Impossible de récupérer les données. Veuillez taper un message.",
            );
          }
        });
    },

    /**
     * NOUVEAU : Ouvre le lien WhatsApp avec numéro et message
     */
    openWhatsAppLink: function (phone, message) {
      if (!phone && !message) {
        alert("Données manquantes pour ouvrir WhatsApp.");
        return;
      }

      // Nettoyer le numéro de téléphone
      let cleanPhone = "";
      if (phone) {
        cleanPhone = phone.replace(/[^\d+]/g, ""); // Garde seulement les chiffres et le +

        // S'assurer qu'il commence par +
        if (!cleanPhone.startsWith("+")) {
          // Ajouter le préfixe par défaut si configuré
          cleanPhone = "+590" + cleanPhone; // Fallback pour Guadeloupe
        }
      }

      // Prendre le message du textarea si pas fourni
      if (!message) {
        const messageInput = document.getElementById("pc-chat-message-input");
        message = messageInput ? messageInput.value.trim() : "";
      }

      // Construire l'URL WhatsApp
      let whatsappUrl = "https://wa.me/";

      if (cleanPhone) {
        whatsappUrl += cleanPhone;
      }

      if (message) {
        whatsappUrl += "?text=" + encodeURIComponent(message);
      }

      // Ouvrir dans un nouvel onglet
      window.open(whatsappUrl, "_blank");
    },

    /**
     * NOUVEAU : Toggle templates rapides (méthode simplifiée)
     */
    toggleTemplates: function () {
      const templatesPanel = document.getElementById("pc-chat-templates");
      if (templatesPanel) {
        const isVisible = templatesPanel.style.display !== "none";
        templatesPanel.style.display = isVisible ? "none" : "block";
      }
    },

    /**
     * NOUVEAU : Marquer tous les messages comme lus
     */
    markAllAsRead: function () {
      if (!this.currentReservationId) return;

      const params = window.pcResaParams || {};
      const ajaxUrl = params.ajaxUrl || "";
      const nonce = params.manualNonce || "";

      const formData = new URLSearchParams();
      formData.append("action", "pc_mark_messages_read");
      formData.append("nonce", nonce);
      formData.append("reservation_id", this.currentReservationId);

      fetch(ajaxUrl, {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            // Recharger la conversation pour afficher les status mis à jour
            this.loadConversation(this.currentReservationId);
          }
        })
        .catch((error) => {
          console.error("[PCR.Messaging] Erreur marquage lu:", error);
        });
    },

    /**
     * Utilitaire : Échapper HTML
     */
    escapeHtml: function (text) {
      const div = document.createElement("div");
      div.textContent = text;
      return div.innerHTML;
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

    // ✨ NOUVEAU PHASE 4 : Méthodes de gestion des pièces jointes

    /**
     * NOUVEAU : Toggle le popover des pièces jointes
     */
    toggleAttachments: function () {
      if (!this.currentReservationId) {
        console.error(
          "[PCR.Messaging] ID de réservation manquant pour les pièces jointes",
        );
        return;
      }

      const popover = document.getElementById("pc-chat-attachments-popover");
      if (!popover) return;

      const isVisible = popover.style.display !== "none";

      if (isVisible) {
        this.hideAttachmentsPopover();
      } else {
        this.showAttachmentsPopover();
      }
    },

    /**
     * NOUVEAU : Affiche le popover des pièces jointes avec repositionnement intelligent
     */
    showAttachmentsPopover: function () {
      const popover = document.getElementById("pc-chat-attachments-popover");
      const attachmentsList = document.getElementById(
        "pc-chat-attachments-list",
      );

      if (!popover || !attachmentsList) return;

      // Afficher le popover avec loading
      popover.style.display = "block";
      attachmentsList.innerHTML =
        '<div class="pc-chat-loading"><div class="pc-chat-loading__spinner"></div><p>Chargement des documents...</p></div>';

      // 🔧 NOUVEAU : Repositionnement intelligent pour éviter les débordements
      this.repositionAttachmentsPopover(popover);

      // Charger les fichiers via AJAX
      this.loadAttachments();
    },

    /**
     * 🔧 NOUVEAU : Repositionne intelligemment le popover des pièces jointes
     * pour éviter qu'il soit coupé par les bords de la fenêtre
     */
    repositionAttachmentsPopover: function (popover) {
      if (!popover) return;

      // Reset des classes de positionnement
      popover.classList.remove("pc-popover--top", "pc-popover--right");

      // Attendre un frame pour que les dimensions soient calculées
      requestAnimationFrame(() => {
        const rect = popover.getBoundingClientRect();
        const windowHeight = window.innerHeight;
        const windowWidth = window.innerWidth;

        // Vérifier si le popover est coupé par le bas
        if (rect.bottom > windowHeight - 20) {
          popover.classList.add("pc-popover--top");
        }

        // Vérifier si le popover est coupé par la droite
        if (rect.right > windowWidth - 20) {
          popover.classList.add("pc-popover--right");
        }

        // Re-calculer après l'application des classes si nécessaire
        if (
          popover.classList.contains("pc-popover--top") ||
          popover.classList.contains("pc-popover--right")
        ) {
          setTimeout(() => {
            const newRect = popover.getBoundingClientRect();

            // Si toujours coupé par le haut après repositionnement vers le haut
            if (
              newRect.top < 20 &&
              popover.classList.contains("pc-popover--top")
            ) {
              // Repositionner au centre vertical
              popover.style.top = "50%";
              popover.style.bottom = "auto";
              popover.style.transform = "translateY(-50%)";
            }
          }, 50);
        }
      });
    },

    /**
     * NOUVEAU : Cache le popover des pièces jointes
     */
    hideAttachmentsPopover: function () {
      const popover = document.getElementById("pc-chat-attachments-popover");
      if (popover) {
        popover.style.display = "none";
      }
    },

    /**
     * NOUVEAU : Charge la liste des documents disponibles
     */
    loadAttachments: function () {
      const params = window.pcResaParams || {};
      const ajaxUrl = params.ajaxUrl || "";
      const nonce = params.manualNonce || "";

      const formData = new URLSearchParams();
      formData.append("action", "pc_get_reservation_files");
      formData.append("nonce", nonce);
      formData.append("reservation_id", this.currentReservationId);

      fetch(ajaxUrl, {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success && data.data) {
            this.renderAttachments(data.data.files || []);
          } else {
            this.showAttachmentsError(
              data.data?.message || "Aucun document trouvé",
            );
          }
        })
        .catch((error) => {
          console.error(
            "[PCR.Messaging] Erreur chargement pièces jointes:",
            error,
          );
          this.showAttachmentsError("Erreur technique");
        });
    },

    /**
     * NOUVEAU : Affiche la liste des pièces jointes
     */
    renderAttachments: function (files) {
      const attachmentsList = document.getElementById(
        "pc-chat-attachments-list",
      );
      if (!attachmentsList) return;

      if (!files || files.length === 0) {
        attachmentsList.innerHTML =
          '<p class="pc-templates-empty">Aucun document généré pour cette réservation.</p>';
        return;
      }

      let html = "";
      files.forEach((file) => {
        html += `
          <div class="pc-attachment-item" 
               data-file-name="${this.escapeHtml(file.name)}"
               data-file-path="${this.escapeHtml(file.path)}"
               data-file-filename="${this.escapeHtml(file.filename)}"
               title="Joindre ${this.escapeHtml(file.name)}">
            <div class="pc-attachment-item__icon">${file.icon || "📄"}</div>
            <div class="pc-attachment-item__info">
              <div class="pc-attachment-item__name">${this.escapeHtml(file.name)}</div>
              <div class="pc-attachment-item__filename">${this.escapeHtml(file.filename)}</div>
            </div>
            <div class="pc-attachment-item__size">${this.escapeHtml(file.size_formatted || "")}</div>
          </div>
        `;
      });

      attachmentsList.innerHTML = html;

      // Attacher les écouteurs de clic
      attachmentsList.addEventListener("click", (event) => {
        const attachmentItem = event.target.closest(".pc-attachment-item");
        if (attachmentItem) {
          this.selectAttachment(attachmentItem);
        }
      });
    },

    /**
     * NOUVEAU : Sélectionne un fichier à joindre
     */
    selectAttachment: function (attachmentItem) {
      const fileName = attachmentItem.dataset.fileName;
      const filePath = attachmentItem.dataset.filePath;
      const filename = attachmentItem.dataset.fileFilename;

      if (!fileName || !filePath) {
        console.error("[PCR.Messaging] Données de fichier manquantes");
        return;
      }

      // Stocker la sélection
      this.currentAttachment = {
        name: fileName,
        filename: filename,
        path: filePath,
      };

      // Afficher le chip
      this.showAttachmentsChip();

      // Fermer le popover
      this.hideAttachmentsPopover();
    },

    /**
     * NOUVEAU : Affiche le chip de la pièce jointe sélectionnée
     */
    showAttachmentsChip: function () {
      const chipsContainer = document.getElementById(
        "pc-chat-attachments-chips",
      );
      if (!chipsContainer || !this.currentAttachment) return;

      const chipHtml = `
        <div class="pc-attachment-chip">
          <span class="pc-attachment-chip__icon">📎</span>
          <span class="pc-attachment-chip__name">${this.escapeHtml(this.currentAttachment.name)}</span>
          <button type="button" class="pc-attachment-chip__remove" onclick="PCR.Messaging.removeAttachment()">×</button>
        </div>
      `;

      chipsContainer.innerHTML = chipHtml;
      chipsContainer.style.display = "block";
    },

    /**
     * NOUVEAU : Supprime la pièce jointe sélectionnée
     */
    removeAttachment: function () {
      this.currentAttachment = null;

      const chipsContainer = document.getElementById(
        "pc-chat-attachments-chips",
      );
      if (chipsContainer) {
        chipsContainer.innerHTML = "";
        chipsContainer.style.display = "none";
      }
    },

    /**
     * NOUVEAU : Affiche une erreur pour les pièces jointes
     */
    showAttachmentsError: function (message) {
      const attachmentsList = document.getElementById(
        "pc-chat-attachments-list",
      );
      if (attachmentsList) {
        attachmentsList.innerHTML = `
          <p class="pc-templates-error">❌ ${this.escapeHtml(message)}</p>
          <button onclick="PCR.Messaging.loadAttachments()" class="pc-btn pc-btn--secondary" style="margin-top: 8px;">
            Réessayer
          </button>
        `;
      }
    },

    /**
     * NOUVEAU : Gère la sélection d'un fichier local
     */
    handleFileSelection: function (event) {
      const fileInput = event.target;
      const file = fileInput.files[0];

      if (!file) {
        return;
      }

      // Validation basique du fichier
      const maxSize = 10 * 1024 * 1024; // 10MB
      const allowedTypes = [
        "application/pdf",
        "image/jpeg",
        "image/jpg",
        "image/png",
        "application/msword",
        "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
      ];

      if (file.size > maxSize) {
        alert("Le fichier est trop volumineux. Taille maximum : 10MB");
        fileInput.value = "";
        return;
      }

      if (!allowedTypes.includes(file.type)) {
        alert(
          "Type de fichier non supporté. Formats acceptés : PDF, JPG, PNG, DOC, DOCX",
        );
        fileInput.value = "";
        return;
      }

      // Stocker le fichier sélectionné
      this.currentAttachment = {
        name: file.name,
        filename: file.name,
        file: file,
        type: "upload",
      };

      // Afficher le chip
      this.showAttachmentsChip();

      // Fermer le popover
      this.hideAttachmentsPopover();

      // Réinitialiser l'input pour permettre la re-sélection du même fichier
      fileInput.value = "";
    },

    // 🆕 NOUVEAU : Méthodes de gestion des onglets

    /**
     * 🆕 NOUVEAU : Attache les événements des onglets
     */
    attachTabEvents: function () {
      const tabButtons = document.querySelectorAll(".pc-tab-btn");

      tabButtons.forEach((btn) => {
        btn.addEventListener("click", (e) => {
          e.preventDefault();
          const tabName = btn.dataset.tab;
          if (tabName) {
            this.switchTab(tabName);
          }
        });
      });
    },

    /**
     * 🆕 NOUVEAU : Change d'onglet et met à jour l'interface
     */
    switchTab: function (tabName) {
      if (this.currentContext === tabName) return;

      console.log(`[PCR.Messaging] Changement d'onglet vers: ${tabName}`);

      // Mettre à jour l'état
      this.currentContext = tabName;

      // Mettre à jour les boutons d'onglets
      this.updateTabButtons(tabName);

      // Filtrer les messages selon l'onglet
      this.filterMessagesByTab(tabName);

      // Adapter le footer
      this.updateFooterForTab(tabName);
    },

    /**
     * 🆕 NOUVEAU : Met à jour l'état visuel des boutons d'onglets
     */
    updateTabButtons: function (activeTab) {
      const tabButtons = document.querySelectorAll(".pc-tab-btn");

      tabButtons.forEach((btn) => {
        const tabName = btn.dataset.tab;
        const isActive = tabName === activeTab;

        // Animation de changement
        btn.classList.add("pc-tab--switching");

        setTimeout(() => {
          if (isActive) {
            btn.classList.add("pc-tab-btn--active");
          } else {
            btn.classList.remove("pc-tab-btn--active");
          }
          btn.classList.remove("pc-tab--switching");
        }, 100);
      });
    },

    /**
     * 🆕 NOUVEAU : Filtre les messages selon l'onglet actif
     */
    filterMessagesByTab: function (tabName) {
      const container = document.getElementById("pc-chat-container");
      if (!container) return;

      // Si on n'a pas encore chargé les messages, ne rien faire
      if (!this.allMessages || this.allMessages.length === 0) {
        this.displayTabPlaceholder(tabName);
        return;
      }

      let filteredMessages = [];

      switch (tabName) {
        case "chat":
          // WhatsApp et SMS
          filteredMessages = this.allMessages.filter(
            (msg) => msg.canal === "whatsapp" || msg.canal === "sms",
          );
          break;

        case "email":
          // Emails officiels
          filteredMessages = this.allMessages.filter(
            (msg) => msg.canal === "email",
          );
          break;

        case "notes":
          // Notes internes (nouveau type à créer)
          filteredMessages = this.allMessages.filter(
            (msg) => msg.canal === "note" || msg.canal === "internal",
          );
          break;

        default:
          filteredMessages = this.allMessages;
      }

      // Afficher les messages filtrés
      if (filteredMessages.length === 0) {
        this.displayTabPlaceholder(tabName);
      } else {
        this.renderFilteredMessages(filteredMessages);
      }
    },

    /**
     * 🆕 NOUVEAU : Affiche un placeholder selon l'onglet quand il n'y a pas de messages
     */
    displayTabPlaceholder: function (tabName) {
      const container = document.getElementById("pc-chat-container");
      if (!container) return;

      let placeholderHtml = "";

      switch (tabName) {
        case "chat":
          placeholderHtml = `
            <div class="pc-chat-empty pc-tab-placeholder">
              <div class="pc-placeholder-icon">💬</div>
              <h3>Aucun message WhatsApp</h3>
              <p>Commencez la conversation avec votre client via WhatsApp ou SMS.</p>
              <p>Les messages apparaîtront ici une fois échangés.</p>
            </div>
          `;
          break;

        case "email":
          placeholderHtml = `
            <div class="pc-chat-empty pc-tab-placeholder">
              <div class="pc-placeholder-icon">📧</div>
              <h3>Aucun email envoyé</h3>
              <p>Envoyez des emails officiels à votre client avec pièces jointes.</p>
              <p>Les confirmations de réservation et documents apparaîtront ici.</p>
            </div>
          `;
          break;

        case "notes":
          placeholderHtml = `
            <div class="pc-chat-empty pc-tab-placeholder">
              <div class="pc-placeholder-icon">📝</div>
              <h3>Aucune note interne</h3>
              <p>Ajoutez des notes privées sur cette réservation.</p>
              <p>Ces notes ne sont visibles que par votre équipe.</p>
            </div>
          `;
          break;

        default:
          placeholderHtml = `
            <div class="pc-chat-empty pc-tab-placeholder">
              <div class="pc-placeholder-icon">💭</div>
              <h3>Aucun message</h3>
              <p>Commencez la conversation avec votre client.</p>
            </div>
          `;
      }

      container.innerHTML = placeholderHtml;
    },

    /**
     * 🆕 NOUVEAU : Affiche les messages filtrés
     */
    renderFilteredMessages: function (messages) {
      const container = document.getElementById("pc-chat-container");
      if (!container) return;

      let html = "";
      messages.forEach((message) => {
        html += this.renderMessage(message);
      });

      container.innerHTML = html;
      this.scrollToBottom();
    },

    /**
     * 🆕 NOUVEAU : Adapte le footer selon l'onglet actif
     */
    updateFooterForTab: function (tabName) {
      const subjectInput = document.getElementById("pc-chat-subject-input");
      const messageInput = document.getElementById("pc-chat-message-input");
      const sendBtn = document.getElementById("pc-chat-send-btn");
      const whatsappBtn = document.getElementById("pc-chat-whatsapp-btn");
      const templatesBtn = document.getElementById("pc-chat-templates-btn");
      const attachmentsBtn = document.getElementById("pc-chat-attachments-btn");

      switch (tabName) {
        case "chat":
          // Mode Chat/WhatsApp
          if (subjectInput) subjectInput.style.display = "none";
          if (messageInput)
            messageInput.placeholder = "Tapez votre message WhatsApp...";
          if (sendBtn) {
            sendBtn.innerHTML = `
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="22" y1="2" x2="11" y2="13"></line>
                <polygon points="22,2 15,22 11,13 2,9"></polygon>
              </svg>
              <span class="pc-chat-send-text">Envoyer</span>
            `;
            sendBtn.style.background = "var(--pc-primary)";
          }
          if (whatsappBtn) whatsappBtn.style.display = "flex";
          if (templatesBtn) templatesBtn.title = "Messages rapides WhatsApp";
          if (attachmentsBtn) attachmentsBtn.style.display = "flex";
          break;

        case "email":
          // Mode Email
          if (subjectInput) subjectInput.style.display = "block";
          if (messageInput)
            messageInput.placeholder = "Rédigez votre email officiel...";
          if (sendBtn) {
            sendBtn.innerHTML = `
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="m4 4 4.586 4.586a2 2 0 0 0 2.828 0L16 4"></path>
                <path d="m4 4 16 16"></path>
                <path d="M4 20l3-3"></path>
                <path d="m21 4-3 3"></path>
                <path d="M17 17l3 3"></path>
              </svg>
              <span class="pc-chat-send-text">Envoyer Email</span>
            `;
            sendBtn.style.background =
              "linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%)";
          }
          if (whatsappBtn) whatsappBtn.style.display = "none";
          if (templatesBtn) templatesBtn.title = "Templates d'emails";
          if (attachmentsBtn) attachmentsBtn.style.display = "flex";
          break;

        case "notes":
          // Mode Notes internes
          if (subjectInput) subjectInput.style.display = "none";
          if (messageInput)
            messageInput.placeholder = "Ajoutez une note interne...";
          if (sendBtn) {
            sendBtn.innerHTML = `
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 20h9"></path>
                <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
              </svg>
              <span class="pc-chat-send-text">Ajouter Note</span>
            `;
            sendBtn.style.background =
              "linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%)";
          }
          if (whatsappBtn) whatsappBtn.style.display = "none";
          if (templatesBtn) templatesBtn.title = "Modèles de notes";
          if (attachmentsBtn) attachmentsBtn.style.display = "none";
          break;
      }

      // Mettre à jour le bouton d'envoi selon le contenu
      if (messageInput && sendBtn) {
        const hasContent = messageInput.value.trim().length > 0;
        sendBtn.disabled = !hasContent;
      }

      console.log(`[PCR.Messaging] Footer adapté pour l'onglet: ${tabName}`);
    },

    /**
     * 🆕 NOUVEAU : Modifie renderConversation pour stocker tous les messages
     */
    renderConversation: function (conversationData) {
      const container = document.getElementById("pc-chat-container");
      if (!container) return;

      const messages = conversationData.messages || [];

      // 🆕 Stocker tous les messages pour le filtrage
      this.allMessages = messages;

      if (messages.length === 0) {
        this.displayTabPlaceholder(this.currentContext);
        return;
      }

      // Filtrer selon l'onglet actuel
      this.filterMessagesByTab(this.currentContext);
    },
  };
})();
