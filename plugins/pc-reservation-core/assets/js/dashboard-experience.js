/**
 * PC Experience Dashboard - Fichier principal coordinateur
 * Gère l'orchestration des classes UI et Data, les événements et les appels AJAX
 *
 * @since 0.2.0
 */
(function ($) {
  class PCExperienceManager {
    constructor() {
      this.ui = null;
      this.data = null;
      this.isLoading = false;
      this.currentExperienceId = null;

      // Configuration AJAX
      this.ajaxUrl = pcReservationVars.ajax_url || "/wp-admin/admin-ajax.php";
      this.nonce = pcReservationVars.nonce || "";
      this.debug = pcReservationVars.debug || false;
    }

    /**
     * Initialise le manager principal
     */
    init() {
      console.log("🚀 PCExperienceManager: Initialisation complète");

      // Initialiser les modules
      this.ui = new PCExperienceUI();
      this.data = new PCExperienceData();

      this.ui.init();
      this.data.init();

      // Bind les événements principaux
      this.bindEvents();

      // Charger la liste initiale
      this.loadExperiencesList();

      console.log("✅ PCExperienceManager: Prêt");
    }

    /**
     * Gère les événements principaux
     */
    bindEvents() {
      // Boutons d'action principale
      $(document).on("click", "#pc-btn-add-experience", (e) => {
        e.preventDefault();
        this.openNewExperienceModal();
      });

      $(document).on("click", ".pc-btn-edit-experience", (e) => {
        e.preventDefault();
        const experienceId = $(e.currentTarget).data("id");
        this.openEditExperienceModal(experienceId);
      });

      // Boutons de sauvegarde
      $(document).on("click", "#btn-save-experience", (e) => {
        e.preventDefault();
        this.saveExperience();
      });

      $(document).on("click", "#btn-save-experience-draft", (e) => {
        e.preventDefault();
        this.saveExperience("draft");
      });

      // Boutons de suppression
      $(document).on("click", ".pc-btn-delete-experience", (e) => {
        e.preventDefault();
        const experienceId = $(e.currentTarget).data("id");
        this.confirmDeleteExperience(experienceId);
      });

      // Refresh de la liste
      $(document).on("click", "#btn-refresh-experiences", (e) => {
        e.preventDefault();
        this.loadExperiencesList();
      });

      // Filtres et recherche
      $(document).on(
        "input",
        "#pc-experience-search",
        this.debounce(() => {
          this.loadExperiencesList();
        }, 500),
      );

      $(document).on("change", "#pc-experience-status-filter", () => {
        this.loadExperiencesList();
      });

      // Gestion des raccourcis clavier
      $(document).on("keydown", (e) => {
        this.handleKeyboardShortcuts(e);
      });
    }

    /**
     * Ouvre la modale pour une nouvelle expérience
     */
    openNewExperienceModal() {
      console.log("🆕 Ouverture nouvelle expérience");

      this.currentExperienceId = 0;
      this.data.reset();
      this.ui.openNewModal();
      this.ui.setModalTitle("Nouvelle expérience");
    }

    /**
     * Ouvre la modale pour éditer une expérience existante
     * @param {number} experienceId ID de l'expérience
     */
    openEditExperienceModal(experienceId) {
      if (!experienceId || experienceId <= 0) {
        this.ui.showError("ID expérience invalide");
        return;
      }

      console.log(`✏️ Ouverture édition expérience #${experienceId}`);

      this.currentExperienceId = experienceId;
      this.ui.openModal(experienceId);

      this.loadExperienceDetails(experienceId);
    }

    /**
     * Charge les détails d'une expérience
     * @param {number} experienceId ID de l'expérience
     */
    loadExperienceDetails(experienceId) {
      this.setLoading(true);

      const requestData = {
        action: "pc_get_experience_details",
        nonce: this.nonce,
        experience_id: experienceId,
      };

      $.ajax({
        url: this.ajaxUrl,
        type: "POST",
        data: requestData,
        timeout: 30000,
      })
        .done((response) => {
          if (response.success && response.data) {
            this.ui.setModalTitle(
              `Édition: ${response.data.title || "Expérience"}`,
            );
            this.data.populate(response.data);
            this.ui.showContent();
            this.ui.showSuccess("Expérience chargée avec succès");
          } else {
            this.ui.showError(
              response.data?.message || "Erreur lors du chargement",
            );
            this.ui.closeModal();
          }
        })
        .fail((xhr, textStatus, errorThrown) => {
          console.error("Erreur AJAX chargement expérience:", {
            xhr,
            textStatus,
            errorThrown,
          });
          this.ui.showError("Erreur de communication avec le serveur");
          this.ui.closeModal();
        })
        .always(() => {
          this.setLoading(false);
        });
    }

    /**
     * Sauvegarde une expérience (création ou mise à jour)
     * @param {string} status Statut de publication ('publish', 'draft')
     */
    saveExperience(status = "publish") {
      console.log(`💾 Sauvegarde expérience (${status})`);

      // Validation
      const validationErrors = this.data.validate();
      if (validationErrors.length > 0) {
        this.ui.showError(
          "Erreurs de validation:<br>• " + validationErrors.join("<br>• "),
        );
        return;
      }

      this.setLoading(true, "save");

      // Collecte des données
      const formData = this.data.collect();
      formData.status = status;

      const requestData = {
        action:
          this.currentExperienceId === 0
            ? "pc_create_experience"
            : "pc_update_experience",
        nonce: this.nonce,
        experience_id: this.currentExperienceId,
        ...formData,
      };

      $.ajax({
        url: this.ajaxUrl,
        type: "POST",
        data: requestData,
        timeout: 45000,
      })
        .done((response) => {
          if (response.success) {
            const isCreation = this.currentExperienceId === 0;
            const message = isCreation
              ? `Expérience créée avec succès (${status})`
              : `Expérience mise à jour avec succès (${status})`;

            this.ui.showSuccess(message);

            // Mettre à jour l'ID si création
            if (isCreation && response.data?.experience_id) {
              this.currentExperienceId = response.data.experience_id;
              this.ui.setModalTitle(
                `Édition: ${formData.title || "Expérience"}`,
              );
            }

            // Marquer comme propre
            this.data.isDirty = false;

            // Rafraîchir la liste
            this.loadExperiencesList();
          } else {
            this.ui.showError(
              response.data?.message || "Erreur lors de la sauvegarde",
            );
          }
        })
        .fail((xhr, textStatus, errorThrown) => {
          console.error("Erreur AJAX sauvegarde:", {
            xhr,
            textStatus,
            errorThrown,
          });
          this.ui.showError("Erreur de communication lors de la sauvegarde");
        })
        .always(() => {
          this.setLoading(false, "save");
        });
    }

    /**
     * Confirme et supprime une expérience
     * @param {number} experienceId ID de l'expérience
     */
    confirmDeleteExperience(experienceId) {
      if (!experienceId || experienceId <= 0) return;

      const confirmed = confirm(
        "Êtes-vous sûr de vouloir supprimer cette expérience ?\n\nCette action est irréversible.",
      );

      if (confirmed) {
        this.deleteExperience(experienceId);
      }
    }

    /**
     * Supprime une expérience
     * @param {number} experienceId ID de l'expérience
     */
    deleteExperience(experienceId) {
      console.log(`🗑️ Suppression expérience #${experienceId}`);

      const requestData = {
        action: "pc_delete_experience",
        nonce: this.nonce,
        experience_id: experienceId,
      };

      // Désactiver le bouton de suppression
      $(`.pc-btn-delete-experience[data-id="${experienceId}"]`).prop(
        "disabled",
        true,
      );

      $.ajax({
        url: this.ajaxUrl,
        type: "POST",
        data: requestData,
        timeout: 15000,
      })
        .done((response) => {
          if (response.success) {
            this.ui.showSuccess("Expérience supprimée avec succès");
            this.loadExperiencesList();
          } else {
            this.ui.showError(
              response.data?.message || "Erreur lors de la suppression",
            );
          }
        })
        .fail((xhr, textStatus, errorThrown) => {
          console.error("Erreur AJAX suppression:", {
            xhr,
            textStatus,
            errorThrown,
          });
          this.ui.showError("Erreur de communication lors de la suppression");
        })
        .always(() => {
          $(`.pc-btn-delete-experience[data-id="${experienceId}"]`).prop(
            "disabled",
            false,
          );
        });
    }

    /**
     * Charge la liste des expériences avec filtres
     */
    loadExperiencesList() {
      console.log("📋 Chargement liste expériences");

      const requestData = {
        action: "pc_get_experiences_list",
        nonce: this.nonce,
        search: $("#pc-experience-search").val() || "",
        status: $("#pc-experience-status-filter").val() || "all",
        page: $("#pc-experience-pagination").data("current-page") || 1,
        per_page: 20,
      };

      // Afficher loading sur la liste
      this.setListLoading(true);

      $.ajax({
        url: this.ajaxUrl,
        type: "POST",
        data: requestData,
        timeout: 20000,
      })
        .done((response) => {
          if (response.success && response.data) {
            this.renderExperiencesList(response.data);
          } else {
            this.ui.showError(
              response.data?.message || "Erreur lors du chargement de la liste",
            );
          }
        })
        .fail((xhr, textStatus, errorThrown) => {
          console.error("Erreur AJAX liste:", { xhr, textStatus, errorThrown });
          this.ui.showError("Erreur de chargement de la liste");
        })
        .always(() => {
          this.setListLoading(false);
        });
    }

    /**
     * Rendu de la liste des expériences
     * @param {Object} data Données de la liste
     */
    renderExperiencesList(data) {
      // Cible le CORPS du tableau, pas un conteneur générique
      const $tbody = $("#pc-experience-table-body");
      const $table = $("#pc-experience-table");
      const $pagination = $("#pc-experience-pagination"); // ID corrigé

      // Si pas de données
      if (!data.experiences || data.experiences.length === 0) {
        $("#pc-experience-empty").show();
        $table.hide();
        $pagination.hide();
        return;
      }

      // On affiche le tableau et cache le message vide
      $("#pc-experience-empty").hide();
      $table.show();

      let html = "";

      data.experiences.forEach((experience) => {
        // Sécurisation des valeurs
        const imgHtml = experience.image?.thumbnail
          ? `<img src="${experience.image.thumbnail}" alt="Img">`
          : '<span class="no-img">📷</span>';

        const statusLabel =
          experience.status === "publish"
            ? "Publié"
            : experience.status === "draft"
              ? "Brouillon"
              : experience.status;

        // Génération des LIGNES DE TABLEAU (TR) pour matcher le CSS
        html += `
        <tr data-id="${experience.id}">
          <td class="pc-col-image">${imgHtml}</td>
          <td class="pc-col-name">
            <strong>${this.escapeHtml(experience.title)}</strong>
          </td>
          <td class="pc-col-price">${experience.prix_base ? experience.prix_base + " €" : "-"}</td>
          <td class="pc-col-duration">${experience.duree ? experience.duree + "h" : "-"}</td>
          <td class="pc-col-status">
            <span class="pc-status-badge status-${experience.status}">${statusLabel}</span>
          </td>
          <td class="pc-col-actions">
            <div class="row-actions">
                <button type="button" class="pc-btn pc-btn-secondary pc-btn-edit-experience" data-id="${experience.id}">✏️</button>
                <button type="button" class="pc-btn pc-btn-danger pc-btn-delete-experience" data-id="${experience.id}">🗑️</button>
            </div>
          </td>
        </tr>
      `;
      });

      $tbody.html(html);

      // Mise à jour pagination
      if (data.pagination && data.pagination.total_pages > 1) {
        this.renderPagination(data.pagination);
        $pagination.show();
      } else {
        $pagination.hide();
      }
    }

    /**
     * Rendu de la pagination
     * @param {Object} paginationData Données de pagination
     */
    renderPagination(paginationData) {
      const $pagination = $("#pc-experience-pagination");
      const currentPage = paginationData.current_page;
      const totalPages = paginationData.total_pages;

      let html = '<div class="pc-pagination">';

      // Bouton précédent
      if (currentPage > 1) {
        html += `<button type="button" class="pc-pagination-btn" data-page="${currentPage - 1}">← Précédent</button>`;
      }

      // Numéros de pages
      for (
        let i = Math.max(1, currentPage - 2);
        i <= Math.min(totalPages, currentPage + 2);
        i++
      ) {
        const activeClass =
          i === currentPage ? "pc-pagination-btn--active" : "";
        html += `<button type="button" class="pc-pagination-btn ${activeClass}" data-page="${i}">${i}</button>`;
      }

      // Bouton suivant
      if (currentPage < totalPages) {
        html += `<button type="button" class="pc-pagination-btn" data-page="${currentPage + 1}">Suivant →</button>`;
      }

      html += "</div>";

      $pagination.html(html).data("current-page", currentPage);
    }

    /**
     * Gère les raccourcis clavier
     * @param {Event} e Événement clavier
     */
    handleKeyboardShortcuts(e) {
      if (!this.ui.isModalCurrentlyOpen()) return;

      // Ctrl+S : Sauvegarder
      if ((e.ctrlKey || e.metaKey) && e.key === "s") {
        e.preventDefault();
        this.saveExperience();
      }

      // Ctrl+Shift+S : Sauvegarder en brouillon
      if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === "S") {
        e.preventDefault();
        this.saveExperience("draft");
      }
    }

    /**
     * Gère l'état de loading
     * @param {boolean} isLoading État de loading
     * @param {string} context Contexte ('save', 'load', etc.)
     */
    setLoading(isLoading, context = "load") {
      this.isLoading = isLoading;

      if (context === "save") {
        this.ui.setButtonState(
          "btn-save-experience",
          isLoading ? "loading" : "normal",
        );
        this.ui.setButtonState(
          "btn-save-experience-draft",
          isLoading ? "disabled" : "normal",
        );
      } else {
        this.ui.showLoading(isLoading);
      }
    }

    /**
     * Gère l'état de loading de la liste
     * @param {boolean} isLoading État de loading
     */
    setListLoading(isLoading) {
      // On utilise le loader déjà présent dans le HTML shortcode
      const $loading = $("#pc-experience-loading");
      const $table = $("#pc-experience-table");
      const $empty = $("#pc-experience-empty");

      if (isLoading) {
        $loading.show();
        $table.hide();
        $empty.hide();
      } else {
        $loading.hide();
        // Le show() du tableau est géré par renderExperiencesList s'il y a des résultats
      }
    }

    /**
     * Utilitaire debounce
     * @param {Function} func Fonction à débouncer
     * @param {number} wait Délai d'attente en ms
     * @return {Function} Fonction débouncée
     */
    debounce(func, wait) {
      let timeout;
      return function executedFunction(...args) {
        const later = () => {
          clearTimeout(timeout);
          func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
      };
    }

    /**
     * Utilitaire pour échapper le HTML
     * @param {string} text Texte à échapper
     * @return {string} Texte échappé
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
     * Formate une date
     * @param {string} dateString Date string
     * @return {string} Date formatée
     */
    formatDate(dateString) {
      const date = new Date(dateString);
      return date.toLocaleDateString("fr-FR", {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      });
    }

    /**
     * Getters pour l'état actuel
     */
    getCurrentExperienceId() {
      return this.currentExperienceId;
    }

    isCurrentlyLoading() {
      return this.isLoading;
    }

    getUI() {
      return this.ui;
    }

    getData() {
      return this.data;
    }
  }

  // ========================================
  // INITIALISATION GLOBALE
  // ========================================

  let pcExperienceManager = null;

  // Initialisation au chargement du DOM
  $(document).ready(() => {
    // Vérifier que les dépendances sont disponibles
    if (typeof PCExperienceUI === "undefined") {
      console.error(
        "❌ PCExperienceUI non trouvé. Vérifiez que ui-manager.js est chargé.",
      );
      return;
    }

    if (typeof PCExperienceData === "undefined") {
      console.error(
        "❌ PCExperienceData non trouvé. Vérifiez que data-manager.js est chargé.",
      );
      return;
    }

    if (typeof pcReservationVars === "undefined") {
      console.error(
        "❌ pcReservationVars non trouvé. Vérifiez la localisation des variables.",
      );
      return;
    }

    // Initialiser le manager principal
    pcExperienceManager = new PCExperienceManager();
    pcExperienceManager.init();

    // Exposer globalement pour debug
    if (window.pcReservationVars?.debug) {
      window.pcExperienceManager = pcExperienceManager;
      console.log("🐞 Debug mode: pcExperienceManager exposé globalement");
    }
  });

  // Gérer la pagination (événement délégué global)
  $(document).on("click", ".pc-pagination-btn", function (e) {
    e.preventDefault();
    const page = $(this).data("page");
    if (page && pcExperienceManager) {
      $("#pc-experience-pagination").data("current-page", page);
      pcExperienceManager.loadExperiencesList();
    }
  });
})(jQuery);
