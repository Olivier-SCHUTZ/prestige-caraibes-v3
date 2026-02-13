/**
 * PC Experience UI Manager
 * Gestionnaire d'interface utilisateur pour le module Experience
 *
 * @since 0.2.0
 */

(function ($) {
  "use strict";

  /**
   * Classe PCExperienceUI
   * Gère uniquement l'interface utilisateur (DOM, modales, notifications)
   */
  class PCExperienceUI {
    constructor() {
      this.currentExperienceId = null;
      this.activeTab = "general";
      this.modalSelector = "#experience-modal";
      this.init();
    }

    /**
     * Initialisation de l'interface
     */
    init() {
      this.bindModalEvents();
      this.bindTabEvents();
      this.bindImageUploaderEvents();

      // Écouter les événements personnalisés
      $(document).on("pc:experience-tab-switched", (e, tabId) => {
        if (tabId === "experience") {
          this.resetLoadingStates();
        }
      });
    }

    /**
     * Gestion des événements de la modale
     */
    bindModalEvents() {
      // Fermeture de la modale
      $(document).on(
        "click",
        `${this.modalSelector} .pc-modal-overlay, ${this.modalSelector} .pc-modal-close, #pc-experience-modal-close, #pc-experience-cancel-btn`,
        (e) => {
          e.preventDefault();
          this.closeModal();
        },
      );

      // Touche Échap pour fermer
      $(document).on("keydown", (e) => {
        if (e.key === "Escape" && !$(this.modalSelector).hasClass("hidden")) {
          this.closeModal();
        }
      });

      // Empêcher la propagation sur le contenu de la modale
      $(document).on(
        "click",
        `${this.modalSelector} .pc-modal-container`,
        (e) => {
          e.stopPropagation();
        },
      );
    }

    /**
     * Gestion des événements des onglets
     */
    bindTabEvents() {
      $(document).on("click", ".pc-tab-btn", (e) => {
        e.preventDefault();
        const tabId = $(e.currentTarget).data("tab");
        this.switchTab(tabId);
      });
    }

    /**
     * Gestion des événements des uploaders d'images
     */
    bindImageUploaderEvents() {
      // Sélectionner une image
      $(document).on("click", ".pc-btn-select-image", (e) => {
        e.preventDefault();
        const target = $(e.currentTarget).data("target");
        this.openMediaUploader(target);
      });

      // Supprimer une image
      $(document).on("click", ".pc-btn-remove-image", (e) => {
        e.preventDefault();
        const target = $(e.currentTarget).data("target");
        this.removeImage(target);
      });
    }

    /**
     * Ouvrir la modale d'expérience
     */
    openModal(experienceId = null) {
      this.currentExperienceId = experienceId;

      // Afficher la modale
      $(this.modalSelector).removeClass("hidden").addClass("active");

      // Titre de la modale
      const title = experienceId
        ? "Modifier l'expérience"
        : "Nouvelle expérience";
      $("#pc-experience-modal-title").text(title);

      if (experienceId) {
        // Mode édition : afficher le loading et charger les données
        this.showModalLoading(true);
        this.showModalDetails(false);
      } else {
        // Mode création : afficher directement le formulaire vide
        this.showModalLoading(false);
        this.showModalDetails(true);
        this.resetForm();
      }

      // Initialiser l'affichage des onglets
      this.initializeTabs();

      // Focus sur le premier champ
      setTimeout(() => {
        $("#experience-title").focus();
      }, 100);
    }

    /**
     * Fermer la modale d'expérience
     */
    closeModal() {
      $(this.modalSelector).addClass("hidden").removeClass("active");
      this.currentExperienceId = null;
      this.activeTab = "general";
    }

    /**
     * Initialise l'affichage des onglets
     */
    initializeTabs() {
      // Masquer tous les onglets
      $(".pc-tab-content").hide();

      // Réinitialiser les boutons d'onglet
      $(".pc-tab-btn").removeClass("active");

      // Afficher le premier onglet (général) et activer son bouton
      $(".pc-tab-btn[data-tab='general']").addClass("active");
      $("#tab-general").show();

      this.activeTab = "general";
    }

    /**
     * Changer d'onglet dans la modale
     */
    switchTab(tabId) {
      if (!tabId || this.activeTab === tabId) return;

      // Mettre à jour la navigation
      $(".pc-tab-btn").removeClass("active");
      $(`.pc-tab-btn[data-tab="${tabId}"]`).addClass("active");

      // Mettre à jour le contenu
      $(".pc-tab-content").hide();
      $(`#tab-${tabId}`).show();

      this.activeTab = tabId;

      // Animation douce
      $(`#tab-${tabId}`).css("opacity", 0).animate({ opacity: 1 }, 200);
    }

    /**
     * Afficher/masquer le loading principal
     */
    showLoading(show = true) {
      if (show) {
        $("#pc-experience-loading").show();
        $("#pc-experience-table").hide();
        $("#pc-experience-empty").hide();
      } else {
        $("#pc-experience-loading").hide();
      }
    }

    /**
     * Masquer le loading principal
     */
    hideLoading() {
      this.showLoading(false);
    }

    /**
     * Afficher/masquer le loading de la modale
     */
    showModalLoading(show = true) {
      if (show) {
        $("#pc-experience-modal-loading").show();
      } else {
        $("#pc-experience-modal-loading").hide();
      }
    }

    /**
     * Afficher/masquer les détails de la modale
     */
    showModalDetails(show = true) {
      if (show) {
        $("#pc-experience-modal-details").show();
      } else {
        $("#pc-experience-modal-details").hide();
      }
    }

    /**
     * Afficher un toast de succès
     */
    showSuccess(message) {
      this.showToast(message, "success");
    }

    /**
     * Afficher un toast d'erreur
     */
    showError(message) {
      this.showToast(message, "error");
    }

    /**
     * Afficher une notification toast
     */
    showToast(message, type = "info") {
      const $toast = $(
        `<div class="pc-toast pc-toast--${type}">${this.escapeHtml(message)}</div>`,
      );

      $("body").append($toast);

      // Animation d'entrée
      $toast
        .css({
          opacity: 0,
          transform: "translateX(-50%) translateY(-20px)",
        })
        .animate(
          {
            opacity: 1,
            transform: "translateX(-50%) translateY(0)",
          },
          300,
        );

      // Disparition automatique
      const delay = type === "error" ? 6000 : 3000;
      setTimeout(() => {
        $toast.animate(
          {
            opacity: 0,
            transform: "translateX(-50%) translateY(-20px)",
          },
          300,
          function () {
            $toast.remove();
          },
        );
      }, delay);

      // Permettre de fermer manuellement
      $toast.on("click", function () {
        $(this).animate(
          {
            opacity: 0,
            transform: "translateX(-50%) translateY(-20px)",
          },
          200,
          function () {
            $(this).remove();
          },
        );
      });
    }

    /**
     * Peupler le formulaire avec des données
     */
    populateForm(experienceData) {
      if (!experienceData) return;

      // Onglet Général
      $("#experience-title").val(experienceData.title || "");
      $("#experience-capacity").val(experienceData.capacity || "");
      $("#experience-duration").val(experienceData.duration || "");
      $("#experience-availability").val(
        experienceData.availability || "InStock",
      );
      $("#experience-status").val(experienceData.status || "draft");
      $("#experience-description").val(experienceData.description || "");

      // Onglet Localisation
      $("#experience-address").val(experienceData.address || "");
      $("#experience-city").val(experienceData.city || "");
      $("#experience-postal-code").val(experienceData.postal_code || "");
      $("#experience-latitude").val(experienceData.latitude || "");
      $("#experience-longitude").val(experienceData.longitude || "");
      $("#experience-meeting-point").val(experienceData.meeting_point || "");

      // Onglet Tarifs
      $("#experience-price").val(experienceData.price || "");
      $("#experience-price-unit").val(
        experienceData.price_unit || "par_personne",
      );
      $("#experience-min-participants").val(
        experienceData.min_participants || "",
      );
      $("#experience-max-participants").val(
        experienceData.max_participants || "",
      );
      $("#experience-child-discount").val(experienceData.child_discount || "");
      $("#experience-child-age-limit").val(
        experienceData.child_age_limit || "",
      );
      $("#experience-included").val(experienceData.included || "");
      $("#experience-not-included").val(experienceData.not_included || "");

      // Onglet Médias
      if (experienceData.featured_image_url) {
        this.setImageFromData(
          "featured-image",
          experienceData.featured_image_url,
        );
      }

      $("#experience-gallery-urls").val(experienceData.gallery_urls || "");
      $("#experience-video-urls").val(experienceData.video_urls || "");
    }

    /**
     * Réinitialiser le formulaire
     */
    resetForm() {
      // Reset tous les inputs text et number
      $(
        `${this.modalSelector} input[type='text'], ${this.modalSelector} input[type='number'], ${this.modalSelector} textarea`,
      ).val("");

      // Reset des selects aux valeurs par défaut
      $("#experience-availability").val("InStock");
      $("#experience-status").val("draft");
      $("#experience-price-unit").val("par_personne");

      // Reset des images
      this.removeImage("featured-image");

      // Reset des états de loading des boutons
      this.resetButtonStates();
    }

    /**
     * Reset des états de loading des boutons
     */
    resetButtonStates() {
      $("#pc-experience-save-btn, #pc-experience-delete-btn").each(function () {
        $(this).removeClass("loading").prop("disabled", false);
        $(this).find(".pc-btn-text").show();
        $(this).find(".pc-btn-spinner").hide();
      });
    }

    /**
     * Reset des états de loading généraux
     */
    resetLoadingStates() {
      this.hideLoading();
      this.showModalLoading(false);
      this.resetButtonStates();
    }

    /**
     * Gestion du Media Uploader WordPress
     */
    openMediaUploader(target) {
      if (typeof wp === "undefined" || !wp.media) {
        this.showError("WordPress Media Library n'est pas disponible.");
        return;
      }

      const mediaUploader = wp.media({
        title: "Sélectionner une image",
        button: {
          text: "Utiliser cette image",
        },
        multiple: false,
        library: {
          type: "image",
        },
      });

      mediaUploader.on("select", () => {
        const attachment = mediaUploader
          .state()
          .get("selection")
          .first()
          .toJSON();
        this.setImage(target, attachment);
      });

      mediaUploader.open();
    }

    /**
     * Définir une image dans le formulaire
     */
    setImage(target, attachment) {
      const $preview = $(`#preview-${target}`);
      const $input = $(`#experience-${target}`);
      const $removeBtn = $(`.pc-btn-remove-image[data-target="${target}"]`);

      // Mettre à jour l'aperçu
      $preview.html(
        `<img src="${attachment.url}" alt="${attachment.alt || "Image"}" style="width: 100%; height: 120px; object-fit: cover; border-radius: 8px;">`,
      );

      // Stocker l'ID de l'attachment
      $input.val(attachment.id);
      $input.data("image-url", attachment.url);

      // Afficher le bouton de suppression
      $removeBtn.show();
    }

    /**
     * Définir une image à partir de données (ID ou URL)
     */
    setImageFromData(target, value) {
      if (!value) return;

      const $preview = $(`#preview-${target}`);
      const $input = $(`#experience-${target}`);
      const $removeBtn = $(`.pc-btn-remove-image[data-target="${target}"]`);

      let imageUrl = "";
      let imageId = "";

      // Détection : ID numérique ou URL?
      if (/^\d+$/.test(value.toString().trim())) {
        // C'est un ID d'attachment
        imageId = parseInt(value);

        // Récupérer l'URL via WordPress Media API si disponible
        if (typeof wp !== "undefined" && wp.media && wp.media.attachment) {
          const attachment = wp.media.attachment(imageId);
          attachment
            .fetch()
            .then(() => {
              const attachmentData = attachment.toJSON();
              if (attachmentData && attachmentData.url) {
                $preview.html(
                  `<img src="${attachmentData.url}" alt="${attachmentData.alt || "Image"}" style="width: 100%; height: 120px; object-fit: cover; border-radius: 8px;">`,
                );
                $input.data("image-url", attachmentData.url);
              }
            })
            .catch(() => {
              $preview.html(
                `<div class="pc-image-placeholder">📷 Image #${imageId} (aperçu indisponible)</div>`,
              );
            });
        } else {
          $preview.html(
            `<div class="pc-image-placeholder">📷 Image #${imageId} chargée</div>`,
          );
        }

        $input.val(imageId);
      } else if (
        typeof value === "string" &&
        (value.startsWith("http") || value.includes("/wp-content/uploads"))
      ) {
        // C'est une URL d'image
        imageUrl = value;
        $preview.html(
          `<img src="${imageUrl}" alt="Image" style="width: 100%; height: 120px; object-fit: cover; border-radius: 8px;">`,
        );
        $input.val(imageUrl);
        $input.data("image-url", imageUrl);
      }

      // Afficher le bouton de suppression
      $removeBtn.show();
    }

    /**
     * Supprimer une image
     */
    removeImage(target) {
      const $preview = $(`#preview-${target}`);
      const $input = $(`#experience-${target}`);
      const $removeBtn = $(`.pc-btn-remove-image[data-target="${target}"]`);

      // Réinitialiser l'aperçu
      $preview.html(
        `<div class="pc-image-placeholder">📷 Aucune image sélectionnée</div>`,
      );

      // Vider l'input caché
      $input.val("").removeData("image-url");

      // Masquer le bouton de suppression
      $removeBtn.hide();
    }

    /**
     * Collecter les données du formulaire
     */
    collectFormData() {
      return {
        // Données de base
        post_id: this.currentExperienceId || 0,
        title: $("#experience-title").val().trim(),
        status: $("#experience-status").val(),
        description: $("#experience-description").val().trim(),

        // Onglet Général
        capacity: $("#experience-capacity").val(),
        duration: $("#experience-duration").val(),
        availability: $("#experience-availability").val(),

        // Onglet Localisation
        address: $("#experience-address").val().trim(),
        city: $("#experience-city").val().trim(),
        postal_code: $("#experience-postal-code").val().trim(),
        latitude: $("#experience-latitude").val().trim(),
        longitude: $("#experience-longitude").val().trim(),
        meeting_point: $("#experience-meeting-point").val().trim(),

        // Onglet Tarifs
        price: $("#experience-price").val(),
        price_unit: $("#experience-price-unit").val(),
        min_participants: $("#experience-min-participants").val(),
        max_participants: $("#experience-max-participants").val(),
        child_discount: $("#experience-child-discount").val(),
        child_age_limit: $("#experience-child-age-limit").val(),
        included: $("#experience-included").val().trim(),
        not_included: $("#experience-not-included").val().trim(),

        // Onglet Médias
        featured_image_url: $("#experience-featured-image").val(),
        gallery_urls: $("#experience-gallery-urls").val().trim(),
        video_urls: $("#experience-video-urls").val().trim(),
      };
    }

    /**
     * Échapper le HTML pour éviter les injections
     */
    escapeHtml(text) {
      if (!text) return "";
      const map = {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;",
      };
      return text.toString().replace(/[&<>"']/g, (m) => map[m]);
    }

    /**
     * Valider les données du formulaire
     */
    validateForm() {
      const errors = [];

      // Titre obligatoire
      const title = $("#experience-title").val().trim();
      if (!title) {
        errors.push("Le titre de l'expérience est obligatoire.");
      }

      // Validation du prix si renseigné
      const price = $("#experience-price").val();
      if (price && (isNaN(price) || price < 0)) {
        errors.push("Le prix doit être un nombre positif.");
      }

      // Validation des participants
      const minParticipants = $("#experience-min-participants").val();
      const maxParticipants = $("#experience-max-participants").val();

      if (minParticipants && maxParticipants) {
        if (parseInt(minParticipants) > parseInt(maxParticipants)) {
          errors.push(
            "Le nombre minimum de participants ne peut pas être supérieur au maximum.",
          );
        }
      }

      return errors;
    }

    /**
     * Obtenir l'ID de l'expérience courante
     */
    getCurrentExperienceId() {
      return this.currentExperienceId;
    }

    /**
     * Définir l'ID de l'expérience courante
     */
    setCurrentExperienceId(id) {
      this.currentExperienceId = id;
    }

    /**
     * Met à jour le titre de la modale
     */
    setModalTitle(title) {
      if (!title) return;
      $("#pc-experience-modal-title").text(title);
    }

    /**
     * Affiche le contenu de la modale
     */
    showContent() {
      this.showModalLoading(false);
      this.showModalDetails(true);
    }

    /**
     * Gère les états loading des boutons
     */
    setButtonState(buttonId, state) {
      const $button = $(`#${buttonId}`);
      if (!$button.length) return;

      if (state === "loading") {
        $button.addClass("loading").prop("disabled", true);
        $button.find(".pc-btn-text").hide();
        $button.find(".pc-btn-spinner").show();
      } else if (state === "disabled") {
        $button.prop("disabled", true).removeClass("loading");
      } else {
        $button.removeClass("loading").prop("disabled", false);
        $button.find(".pc-btn-text").show();
        $button.find(".pc-btn-spinner").hide();
      }
    }

    /**
     * Ouvre la modale en mode création
     */
    openNewModal() {
      this.openModal(null);
    }

    /**
     * Vérifie si la modale est ouverte
     */
    isModalCurrentlyOpen() {
      return !$(this.modalSelector).hasClass("hidden");
    }
  }

  // Rendre la classe disponible globalement
  window.PCExperienceUI = PCExperienceUI;
})(jQuery);
