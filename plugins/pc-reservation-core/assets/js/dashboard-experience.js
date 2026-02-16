/**
 * PC Reservation Core - Experience Dashboard JavaScript
 * Gestionnaire des expériences avec interface Table + Modale
 *
 * @since 0.1.4
 */

(function ($) {
  "use strict";

  // === VARIABLES GLOBALES ===
  let currentPage = 1;
  let totalPages = 1;
  let currentExperienceId = null;
  let currentFilters = {
    search: "",
    status: "",
  };

  // === CLASSE PRINCIPALE ===
  class PCExperienceDashboard {
    constructor() {
      // Configuration AJAX
      this.ajaxUrl = pcReservationVars.ajax_url;
      this.nonce = pcReservationVars.nonce;

      // Sélecteurs DOM
      this.selectors = {
        table: "#pc-experience-table",
        tbody: "#pc-experience-table-body",
        empty: "#pc-experience-empty",
        loading: "#pc-experience-loading",
        search: "#pc-experience-search",
        statusFilter: "#pc-experience-status-filter",
        pagination: "#pc-experience-pagination",
        newBtn: "#pc-new-experience-btn",
      };

      // Initialiser le Rate Manager pour la suite
      this.rateManager = new PCRateManager();

      this.init();
    }

    init() {
      this.bindEvents();
      this.loadList();
    }

    // === GESTION DES ÉVÉNEMENTS ===
    bindEvents() {
      // Recherche avec debounce
      $(this.selectors.search).on(
        "input",
        this.debounce(() => {
          currentFilters.search = $(this.selectors.search).val();
          currentPage = 1;
          this.loadList();
        }, 500),
      );

      // Filtre statut
      $(this.selectors.statusFilter).on("change", () => {
        currentFilters.status = $(this.selectors.statusFilter).val();
        currentPage = 1;
        this.loadList();
      });

      // Pagination
      $(document).on("click", ".pc-pagination a[data-page]", (e) => {
        e.preventDefault();
        const page = parseInt($(e.currentTarget).data("page"));
        if (page !== currentPage && page >= 1 && page <= totalPages) {
          currentPage = page;
          this.loadList();
        }
      });

      // Bouton "Nouvelle Expérience"
      $(document).on("click", this.selectors.newBtn, (e) => {
        e.preventDefault();
        this.openModal();
      });

      // Actions sur les lignes
      $(document).on("click", ".pc-action-edit", (e) => {
        e.preventDefault();
        const experienceId = $(e.currentTarget).data("experience-id");
        this.openModal(experienceId);
      });

      $(document).on("click", ".pc-action-delete", (e) => {
        e.preventDefault();
        const experienceId = $(e.currentTarget).data("experience-id");
        this.handleDelete(experienceId);
      });

      // Événement personnalisé pour le switch d'onglet
      $(document).on("pc:tab-switched", (e, tabId) => {
        if (tabId === "experience") {
          this.loadList();
        }
      });

      // === GESTION MODALE ===
      // Fermer la modale
      $(document).on(
        "click",
        "#experience-modal .pc-modal-overlay, #experience-modal .pc-modal-close",
        () => {
          this.closeModal();
        },
      );

      // Sauvegarder
      $(document).on("click", "#pc-experience-save-btn", () => {
        this.handleSave();
      });

      // Empêcher la fermeture accidentelle
      $(document).on("keydown", (e) => {
        if (e.key === "Escape" && !$("#experience-modal").hasClass("hidden")) {
          this.closeModal();
        }
      });

      // === GESTION REPEATERS ===
      // Lieux - Ajouter
      $(document).on("click", ".add-lieu-row", () => {
        this.addLieuRow();
      });

      // Lieux - Supprimer
      $(document).on("click", ".remove-lieu-row", (e) => {
        $(e.currentTarget).closest(".pc-repeater-row").remove();
      });

      // Fermeture - Ajouter
      $(document).on("click", ".add-fermeture-row", () => {
        this.addFermetureRow();
      });

      // Fermeture - Supprimer
      $(document).on("click", ".remove-fermeture-row", (e) => {
        $(e.currentTarget).closest(".pc-repeater-row").remove();
      });

      // FAQ - Ajouter
      $(document).on("click", ".add-faq-row", () => {
        this.addFaqRow();
      });

      // FAQ - Supprimer
      $(document).on("click", ".remove-faq-row", (e) => {
        $(e.currentTarget).closest(".pc-repeater-row").remove();
      });

      // === GESTION IMAGES ===
      $(document).on("click", ".pc-btn-select-image", (e) => {
        e.preventDefault();
        const target = $(e.currentTarget).data("target");
        this.openMediaUploader(target);
      });

      $(document).on("click", ".pc-btn-remove-image", (e) => {
        e.preventDefault();
        const target = $(e.currentTarget).data("target");
        this.resetImageField(target);
      });
    }

    // === CHARGEMENT DE LA LISTE ===
    loadList(page = 1) {
      currentPage = page;
      this.showLoading(true);

      const requestData = {
        action: "pc_experience_get_list",
        nonce: this.nonce,
        page: currentPage,
        per_page: 20,
        search: currentFilters.search,
        status_filter: currentFilters.status,
        orderby: "title",
        order: "ASC",
      };

      $.ajax({
        url: this.ajaxUrl,
        type: "POST",
        data: requestData,
        success: (response) => {
          this.showLoading(false);

          if (response.success) {
            this.renderList(response.data.items || []);
            this.renderPagination(response.data);
          } else {
            this.showError(
              "Erreur lors du chargement des expériences: " +
                (response.data?.message || "Erreur inconnue"),
            );
          }
        },
        error: (xhr, status, error) => {
          this.showLoading(false);
          console.error("Erreur AJAX:", error);
          this.showError(
            "Erreur de connexion lors du chargement des expériences.",
          );
        },
      });
    }

    // === RENDU DU TABLEAU ===
    renderList(items) {
      const $table = $(this.selectors.table);
      const $tbody = $(this.selectors.tbody);
      const $empty = $(this.selectors.empty);

      if (!items || items.length === 0) {
        $table.hide();
        $empty.show();
        return;
      }

      $empty.hide();
      $tbody.empty();

      items.forEach((item) => {
        const $row = this.createExperienceRow(item);
        $tbody.append($row);
      });

      $table.show();
    }

    createExperienceRow(item) {
      // Image avec fallback
      const imageUrl =
        item.image && item.image.thumbnail
          ? item.image.thumbnail
          : "data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjYwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0yMCAyMEg0MEMzNS41ODE3IDMzLjMzMzMgMjguNDE4MyAzMy4zMzMzIDI0IDMzLjMzMzNWNDBIMjBWMjBaIiBmaWxsPSIjOTRBM0I4Ci8+Cjwvc3ZnPgo=";

      // Données formatées
      const duree = item.duree ? `${item.duree}h` : "Non définie";
      const capacite = item.capacite ? `${item.capacite} pers` : "Non définie";
      const price =
        item.prix_base > 0
          ? `${parseFloat(item.prix_base).toFixed(0)}€`
          : "Non défini";

      // Badge de statut
      const statusBadge = `<span class="pc-status-badge ${item.status_class || "pc-status-draft"}">${item.status_label || "Brouillon"}</span>`;

      return $(`
        <tr data-experience-id="${item.id}">
          <td class="pc-col-image">
            <img src="${imageUrl}" alt="${this.escapeHtml(item.title)}" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjYwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjRjNGNEY2Ci8+CjxwYXRoIGQ9Ik0yMCAyMEg0MEMzNS41ODE3IDMzLjMzMzMgMjguNDE4MyAzMy4zMzMzIDI0IDMzLjMzMzNWNDBIMjBWMjBaIiBmaWxsPSIjOTRBM0I4Ci8+Cjwvc3ZnPgo='">
          </td>
          <td class="pc-col-name">
            <div class="pc-experience-name">${this.escapeHtml(item.title)}</div>
          </td>
          <td class="pc-col-duree">
            <span class="pc-experience-duree">${duree}</span>
          </td>
          <td class="pc-col-capacity">
            <span class="pc-experience-capacity">${capacite}</span>
          </td>
          <td class="pc-col-price">
            <span class="pc-experience-price">${price}</span>
          </td>
          <td class="pc-col-status">
            ${statusBadge}
          </td>
          <td class="pc-col-actions">
            <button class="pc-btn pc-btn-sm pc-action-edit" data-experience-id="${item.id}">
              <span>✏️</span>
              Éditer
            </button>
            <button class="pc-btn pc-btn-sm pc-btn-danger pc-action-delete" data-experience-id="${item.id}">
              <span>🗑️</span>
              Supprimer
            </button>
          </td>
        </tr>
      `);
    }

    // === PAGINATION ===
    renderPagination(data) {
      const $pagination = $(this.selectors.pagination);
      totalPages = data.pages || 1;
      currentPage = data.current_page || 1;

      if (totalPages <= 1) {
        $pagination.empty();
        return;
      }

      let html = '<div class="pc-pagination">';

      // Bouton précédent
      const prevDisabled = currentPage <= 1 ? "pc-disabled" : "";
      html += `<a href="#" class="${prevDisabled}" data-page="${currentPage - 1}">‹</a>`;

      // Pages
      for (let i = 1; i <= totalPages; i++) {
        if (
          i === 1 ||
          i === totalPages ||
          (i >= currentPage - 2 && i <= currentPage + 2)
        ) {
          const active = i === currentPage ? "pc-active" : "";
          html += `<a href="#" class="${active}" data-page="${i}">${i}</a>`;
        } else if (i === currentPage - 3 || i === currentPage + 3) {
          html += '<span class="pc-pagination-dots">...</span>';
        }
      }

      // Bouton suivant
      const nextDisabled = currentPage >= totalPages ? "pc-disabled" : "";
      html += `<a href="#" class="${nextDisabled}" data-page="${currentPage + 1}">›</a>`;

      html += "</div>";
      $pagination.html(html);
    }

    // === GESTION MODALE ===
    openModal(id = null) {
      currentExperienceId = id;

      // Afficher la modale
      $("#experience-modal").removeClass("hidden").addClass("active");

      if (id === null) {
        // Mode création : réinitialiser le formulaire
        $("#pc-experience-modal-title").text("Nouvelle expérience");
        this.resetForm();
        $("#pc-experience-modal-loading").hide();
        $("#pc-experience-modal-details").show();
      } else {
        // Mode édition : charger les détails
        $("#pc-experience-modal-title").text("Édition expérience");
        $("#pc-experience-modal-loading").show();
        $("#pc-experience-modal-details").hide();
        this.loadDetails(id);
      }
    }

    loadDetails(id) {
      $.ajax({
        url: this.ajaxUrl,
        type: "POST",
        data: {
          action: "pc_experience_get_details",
          nonce: this.nonce,
          post_id: id,
        },
        success: (response) => {
          $("#pc-experience-modal-loading").hide();

          if (response.success) {
            this.populateForm(response.data.experience);
            $("#pc-experience-modal-details").show();
          } else {
            this.showError(
              "Erreur lors du chargement: " +
                (response.data?.message || "Erreur inconnue"),
            );
            this.closeModal();
          }
        },
        error: (xhr, status, error) => {
          $("#pc-experience-modal-loading").hide();
          console.error("Erreur AJAX:", error);
          this.showError("Erreur de connexion lors du chargement des détails.");
          this.closeModal();
        },
      });
    }

    populateForm(experience) {
      // === GÉNÉRAL ===
      $("#exp_h1_custom").val(experience.h1_custom || "");
      $("#exp_availability").prop("checked", experience.availability === "1");

      // === DÉTAILS ===
      $("#exp_duree").val(experience.duree || "");
      $("#exp_capacite").val(experience.capacite || "");
      $("#exp_age_minimum").val(experience.age_minimum || "");

      // === CHECKBOXES (Arrays) ===
      this.populateCheckboxArray("exp_accessibilite", experience.accessibilite);
      this.populateCheckboxArray("exp_periode", experience.periode);
      this.populateCheckboxArray("exp_jour", experience.jour);

      // === INCLUSIONS ===
      $("#exp_prix_comprend").val(experience.prix_comprend || "");
      $("#exp_prix_ne_comprend_pas").val(experience.prix_ne_comprend_pas || "");

      // === SERVICES ===
      $("#exp_delai_de_reservation").val(experience.delai_de_reservation || "");
      $("#exp_zone_intervention").val(experience.zone_intervention || "");

      // === PAIEMENT ===
      $("#taux_tva").val(experience.taux_tva || "");
      $("#pc_pay_mode").val(experience.pc_pay_mode || "acompte_plus_solde");
      $("#pc_deposit_type").val(experience.pc_deposit_type || "pourcentage");
      $("#pc_deposit_value").val(experience.pc_deposit_value || "");

      // === IMAGES ===
      this.populateImageField("exp_hero_desktop", experience.hero_desktop_url);
      this.populateImageField("exp_hero_mobile", experience.hero_mobile_url);

      // === REPEATERS ===
      this.renderLieuxRepeater(experience.lieux_horaires_depart || []);
      this.renderFermetureRepeater(experience.periodes_fermeture || []);
      this.renderFaqRepeater(experience.faq || []);

      // === RATE MANAGER ===
      if (this.rateManager) {
        this.rateManager.init(
          "pc-rates-calendar",
          {
            seasons: experience.seasons_data || [],
            promos: experience.promos_data || [],
          },
          experience.prix_base || 0,
        );
      }
    }

    resetForm() {
      // Reset tous les inputs text/textarea
      $(
        "#experience-modal input[type='text'], #experience-modal input[type='number'], #experience-modal textarea",
      ).val("");

      // Reset checkboxes
      $("#experience-modal input[type='checkbox']").prop("checked", false);

      // Reset selects aux valeurs par défaut
      $("#pc_pay_mode").val("acompte_plus_solde");
      $("#pc_deposit_type").val("pourcentage");

      // Reset images
      this.resetImageField("exp_hero_desktop");
      this.resetImageField("exp_hero_mobile");

      // Reset repeaters
      this.renderLieuxRepeater([]);
      this.renderFermetureRepeater([]);
      this.renderFaqRepeater([]);

      console.log("✅ Formulaire réinitialisé");
    }

    closeModal() {
      $("#experience-modal").addClass("hidden").removeClass("active");
      currentExperienceId = null;
    }

    handleDelete(id) {
      if (
        !confirm(
          "Êtes-vous sûr de vouloir supprimer cette expérience ? Cette action est irréversible.",
        )
      ) {
        return;
      }

      $.ajax({
        url: this.ajaxUrl,
        type: "POST",
        data: {
          action: "pc_experience_delete",
          nonce: this.nonce,
          post_id: id,
        },
        success: (response) => {
          if (response.success) {
            this.showSuccess("Expérience supprimée avec succès!");
            this.loadList(); // Recharger la liste
          } else {
            this.showError(
              "Erreur lors de la suppression: " +
                (response.data?.message || "Erreur inconnue"),
            );
          }
        },
        error: (xhr, status, error) => {
          console.error("Erreur AJAX:", error);
          this.showError("Erreur de connexion lors de la suppression.");
        },
      });
    }

    handleSave() {
      if (currentExperienceId === null) {
        this.showError("Aucune expérience sélectionnée.");
        return;
      }

      // Validation basique
      const title = $("#exp_h1_custom").val().trim();
      if (!title) {
        this.showError("Le titre de l'expérience est obligatoire.");
        return;
      }

      const $btn = $("#pc-experience-save-btn");
      $btn.addClass("loading").prop("disabled", true);

      // Collecte des données
      const formData = {
        action: "pc_experience_save",
        nonce: this.nonce,
        post_id: currentExperienceId,

        // Général
        acf_h1_custom: $("#exp_h1_custom").val(),
        acf_availability: $("#exp_availability").is(":checked") ? "1" : "0",

        // Détails
        acf_duree: $("#exp_duree").val(),
        acf_capacite: $("#exp_capacite").val(),
        acf_age_minimum: $("#exp_age_minimum").val(),

        // Checkboxes Arrays
        acf_accessibilite: this.collectCheckboxArray("exp_accessibilite"),
        acf_periode: this.collectCheckboxArray("exp_periode"),
        acf_jour: this.collectCheckboxArray("exp_jour"),

        // Inclusions
        acf_prix_comprend: $("#exp_prix_comprend").val(),
        acf_prix_ne_comprend_pas: $("#exp_prix_ne_comprend_pas").val(),

        // Services
        acf_delai_de_reservation: $("#exp_delai_de_reservation").val(),
        acf_zone_intervention: $("#exp_zone_intervention").val(),

        // Paiement
        acf_taux_tva: $("#taux_tva").val(),
        acf_pc_pay_mode: $("#pc_pay_mode").val(),
        acf_pc_deposit_type: $("#pc_deposit_type").val(),
        acf_pc_deposit_value: $("#pc_deposit_value").val(),

        // Images
        acf_hero_desktop_url: $("#exp_hero_desktop").val(),
        acf_hero_mobile_url: $("#exp_hero_mobile").val(),

        // Repeaters
        acf_lieux_horaires_depart: this.collectLieuxData(),
        acf_periodes_fermeture: this.collectFermetureData(),
        acf_faq: this.collectFaqData(),

        // Rate Manager
        rate_manager_data: this.rateManager
          ? JSON.stringify(this.rateManager.getData())
          : "{}",
      };

      $.ajax({
        url: this.ajaxUrl,
        type: "POST",
        data: formData,
        success: (response) => {
          $btn.removeClass("loading").prop("disabled", false);

          if (response.success) {
            this.showSuccess("Expérience sauvegardée avec succès!");
            this.closeModal();
            this.loadList(); // Recharger la liste
          } else {
            this.showError(
              "Erreur lors de la sauvegarde: " +
                (response.data?.message || "Erreur inconnue"),
            );
          }
        },
        error: (xhr, status, error) => {
          $btn.removeClass("loading").prop("disabled", false);
          console.error("Erreur AJAX:", error);
          this.showError("Erreur de connexion lors de la sauvegarde.");
        },
      });
    }

    // === GESTION DES REPEATERS ===
    renderLieuxRepeater(items) {
      const $wrapper = $("#wrapper-exp_lieux_horaires_depart");
      $wrapper.empty();

      items.forEach((item, index) => {
        const html = `
          <div class="pc-repeater-row" data-index="${index}">
            <div class="pc-form-grid">
              <div class="pc-form-group">
                <label>Lieu de départ</label>
                <input type="text" class="pc-input lieu-depart" value="${this.escapeHtml(item.lieu_depart || "")}" placeholder="Ex: Marina de Pointe-à-Pitre">
              </div>
              <div class="pc-form-group">
                <label>Horaires</label>
                <input type="text" class="pc-input horaires" value="${this.escapeHtml(item.horaires || "")}" placeholder="Ex: 9h00 - 17h00">
              </div>
              <div class="pc-form-group">
                <button type="button" class="pc-btn pc-btn-danger remove-lieu-row">
                  <span>🗑️</span> Supprimer
                </button>
              </div>
            </div>
          </div>
        `;
        $wrapper.append(html);
      });

      // Bouton ajouter
      $wrapper.append(`
        <button type="button" class="pc-btn pc-btn-secondary add-lieu-row">
          <span>➕</span> Ajouter un lieu
        </button>
      `);
    }

    renderFermetureRepeater(items) {
      const $wrapper = $("#wrapper-exp_periodes_fermeture");
      $wrapper.empty();

      items.forEach((item, index) => {
        const html = `
          <div class="pc-repeater-row" data-index="${index}">
            <div class="pc-form-grid">
              <div class="pc-form-group">
                <label>Date début</label>
                <input type="date" class="pc-input date-debut" value="${item.date_debut || ""}">
              </div>
              <div class="pc-form-group">
                <label>Date fin</label>
                <input type="date" class="pc-input date-fin" value="${item.date_fin || ""}">
              </div>
              <div class="pc-form-group">
                <label>Raison</label>
                <input type="text" class="pc-input raison" value="${this.escapeHtml(item.raison || "")}" placeholder="Ex: Maintenance">
              </div>
              <div class="pc-form-group">
                <button type="button" class="pc-btn pc-btn-danger remove-fermeture-row">
                  <span>🗑️</span> Supprimer
                </button>
              </div>
            </div>
          </div>
        `;
        $wrapper.append(html);
      });

      // Bouton ajouter
      $wrapper.append(`
        <button type="button" class="pc-btn pc-btn-secondary add-fermeture-row">
          <span>➕</span> Ajouter une période
        </button>
      `);
    }

    renderFaqRepeater(items) {
      const $wrapper = $("#wrapper-exp_faq");
      $wrapper.empty();

      items.forEach((item, index) => {
        const html = `
          <div class="pc-repeater-row" data-index="${index}">
            <div class="pc-form-grid">
              <div class="pc-form-group pc-form-group--full">
                <label>Question</label>
                <input type="text" class="pc-input question" value="${this.escapeHtml(item.question || "")}" placeholder="Entrez votre question">
              </div>
              <div class="pc-form-group pc-form-group--full">
                <label>Réponse</label>
                <textarea class="pc-textarea reponse" rows="3" placeholder="Entrez la réponse">${this.escapeHtml(item.reponse || "")}</textarea>
              </div>
              <div class="pc-form-group">
                <button type="button" class="pc-btn pc-btn-danger remove-faq-row">
                  <span>🗑️</span> Supprimer
                </button>
              </div>
            </div>
          </div>
        `;
        $wrapper.append(html);
      });

      // Bouton ajouter
      $wrapper.append(`
        <button type="button" class="pc-btn pc-btn-secondary add-faq-row">
          <span>➕</span> Ajouter une FAQ
        </button>
      `);
    }

    // === HELPERS REPEATERS ===
    addLieuRow() {
      const index = Date.now(); // ID unique
      const html = `
        <div class="pc-repeater-row" data-index="${index}">
          <div class="pc-form-grid">
            <div class="pc-form-group">
              <label>Lieu de départ</label>
              <input type="text" class="pc-input lieu-depart" value="" placeholder="Ex: Marina de Pointe-à-Pitre">
            </div>
            <div class="pc-form-group">
              <label>Horaires</label>
              <input type="text" class="pc-input horaires" value="" placeholder="Ex: 9h00 - 17h00">
            </div>
            <div class="pc-form-group">
              <button type="button" class="pc-btn pc-btn-danger remove-lieu-row">
                <span>🗑️</span> Supprimer
              </button>
            </div>
          </div>
        </div>
      `;
      $("#wrapper-exp_lieux_horaires_depart .add-lieu-row").before(html);
    }

    addFermetureRow() {
      const index = Date.now(); // ID unique
      const html = `
        <div class="pc-repeater-row" data-index="${index}">
          <div class="pc-form-grid">
            <div class="pc-form-group">
              <label>Date début</label>
              <input type="date" class="pc-input date-debut" value="">
            </div>
            <div class="pc-form-group">
              <label>Date fin</label>
              <input type="date" class="pc-input date-fin" value="">
            </div>
            <div class="pc-form-group">
              <label>Raison</label>
              <input type="text" class="pc-input raison" value="" placeholder="Ex: Maintenance">
            </div>
            <div class="pc-form-group">
              <button type="button" class="pc-btn pc-btn-danger remove-fermeture-row">
                <span>🗑️</span> Supprimer
              </button>
            </div>
          </div>
        </div>
      `;
      $("#wrapper-exp_periodes_fermeture .add-fermeture-row").before(html);
    }

    addFaqRow() {
      const index = Date.now(); // ID unique
      const html = `
        <div class="pc-repeater-row" data-index="${index}">
          <div class="pc-form-grid">
            <div class="pc-form-group pc-form-group--full">
              <label>Question</label>
              <input type="text" class="pc-input question" value="" placeholder="Entrez votre question">
            </div>
            <div class="pc-form-group pc-form-group--full">
              <label>Réponse</label>
              <textarea class="pc-textarea reponse" rows="3" placeholder="Entrez la réponse"></textarea>
            </div>
            <div class="pc-form-group">
              <button type="button" class="pc-btn pc-btn-danger remove-faq-row">
                <span>🗑️</span> Supprimer
              </button>
            </div>
          </div>
        </div>
      `;
      $("#wrapper-exp_faq .add-faq-row").before(html);
    }

    // === COLLECTE DES DONNÉES REPEATERS ===
    collectLieuxData() {
      const data = [];
      $("#wrapper-exp_lieux_horaires_depart .pc-repeater-row").each(
        (index, row) => {
          const $row = $(row);
          const item = {
            lieu_depart: $row.find(".lieu-depart").val() || "",
            horaires: $row.find(".horaires").val() || "",
          };
          if (item.lieu_depart || item.horaires) {
            data.push(item);
          }
        },
      );
      return data;
    }

    collectFermetureData() {
      const data = [];
      $("#wrapper-exp_periodes_fermeture .pc-repeater-row").each(
        (index, row) => {
          const $row = $(row);
          const item = {
            date_debut: $row.find(".date-debut").val() || "",
            date_fin: $row.find(".date-fin").val() || "",
            raison: $row.find(".raison").val() || "",
          };
          if (item.date_debut || item.date_fin || item.raison) {
            data.push(item);
          }
        },
      );
      return data;
    }

    collectFaqData() {
      const data = [];
      $("#wrapper-exp_faq .pc-repeater-row").each((index, row) => {
        const $row = $(row);
        const item = {
          question: $row.find(".question").val() || "",
          reponse: $row.find(".reponse").val() || "",
        };
        if (item.question || item.reponse) {
          data.push(item);
        }
      });
      return data;
    }

    // === GESTION DES CHECKBOXES ARRAY ===
    populateCheckboxArray(name, values) {
      // Décocher toutes les checkboxes d'abord
      $(`input[name="${name}[]"]`).prop("checked", false);

      if (values && Array.isArray(values)) {
        values.forEach((value) => {
          // Gestion des objets ACF {value: "key", label: "Label"}
          const actualValue =
            typeof value === "object" && value.value ? value.value : value;
          $(`input[name="${name}[]"][value="${actualValue}"]`).prop(
            "checked",
            true,
          );
        });
      }
    }

    collectCheckboxArray(name) {
      const values = [];
      $(`input[name="${name}[]"]:checked`).each(function () {
        values.push($(this).val());
      });
      return values;
    }

    // === GESTION DES IMAGES ===
    populateImageField(target, value) {
      if (!value) return;

      const $preview = $(`#preview-${target}`);
      const $input = $(`#${target}`);
      const $removeBtn = $(`.pc-btn-remove-image[data-target="${target}"]`);

      // Si c'est un ID numérique, récupérer l'URL via WordPress
      if (/^\d+$/.test(value.toString().trim())) {
        const imageId = parseInt(value);
        $input.val(imageId);

        if (typeof wp !== "undefined" && wp.media && wp.media.attachment) {
          const attachment = wp.media.attachment(imageId);
          attachment
            .fetch()
            .then(() => {
              const attachmentData = attachment.toJSON();
              if (attachmentData && attachmentData.url) {
                $preview.html(
                  `<img src="${attachmentData.url}" alt="Image" style="width: 100%; height: 120px; object-fit: cover; border-radius: 8px;">`,
                );
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
      } else {
        // C'est une URL
        $input.val(value);
        $preview.html(
          `<img src="${value}" alt="Image" style="width: 100%; height: 120px; object-fit: cover; border-radius: 8px;">`,
        );
      }

      $removeBtn.show();
    }

    resetImageField(target) {
      const $preview = $(`#preview-${target}`);
      const $input = $(`#${target}`);
      const $removeBtn = $(`.pc-btn-remove-image[data-target="${target}"]`);

      const defaultText = target.includes("mobile")
        ? "📱 Aucune image sélectionnée"
        : "📷 Aucune image sélectionnée";

      $preview.html(`<div class="pc-image-placeholder">${defaultText}</div>`);
      $input.val("");
      $removeBtn.hide();
    }

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
        this.setImageFromUploader(target, attachment);
      });

      mediaUploader.open();
    }

    setImageFromUploader(target, attachment) {
      const $preview = $(`#preview-${target}`);
      const $input = $(`#${target}`);
      const $removeBtn = $(`.pc-btn-remove-image[data-target="${target}"]`);

      // Mettre à jour l'aperçu
      $preview.html(
        `<img src="${attachment.url}" alt="${attachment.alt || "Image"}" style="width: 100%; height: 120px; object-fit: cover; border-radius: 8px;">`,
      );

      // Stocker l'ID de l'attachment (pas l'URL)
      $input.val(attachment.id);
      $input.data("image-url", attachment.url); // Garder l'URL pour référence

      // Afficher le bouton de suppression
      $removeBtn.show();
    }

    // === UTILITAIRES ===
    showLoading(show) {
      const $loading = $(this.selectors.loading);
      const $table = $(this.selectors.table);
      const $empty = $(this.selectors.empty);

      if (show) {
        $loading.show();
        $table.hide();
        $empty.hide();
      } else {
        $loading.hide();
      }
    }

    showError(message) {
      console.error(message);
      // TODO: Système de notifications dans la PARTIE 2
      alert(message); // Temporaire
    }

    showSuccess(message) {
      console.log(message);
      // TODO: Système de notifications dans la PARTIE 2
      alert(message); // Temporaire
    }

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
  }

  // === INITIALISATION ===
  $(document).ready(function () {
    // Vérifier que nous sommes dans l'environnement correct
    if (typeof pcReservationVars === "undefined") {
      console.error("PCR Experience Dashboard: Variables AJAX manquantes");
      return;
    }

    // Initialiser le gestionnaire
    window.pcExperienceDashboard = new PCExperienceDashboard();

    // Hook personnalisé pour l'integration avec app-shell.php
    $(window).on("pc-tab-switch", function (e, tabId) {
      if (tabId === "experience" && window.pcExperienceDashboard) {
        window.pcExperienceDashboard.loadList();
      }
    });
  });
})(jQuery);
