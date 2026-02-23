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

      this.init();
    }

    init() {
      this.bindEvents();

      // 🔥 GUARD CLAUSE : Ne charger QUE si l'onglet Experience est visible
      if (!$("#view-experience").hasClass("active")) {
        console.log("🚫 Onglet Experience caché - Chargement différé");
        return;
      }

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

      // Action de suppression depuis la modale
      $(document).on("click", "#pc-experience-delete-btn", (e) => {
        e.preventDefault();
        if (currentExperienceId) {
          this.handleDelete(currentExperienceId);
        }
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
        "#experience-modal .pc-modal-overlay, #experience-modal .pc-modal-close, #pc-experience-cancel-btn",
        () => {
          this.closeModal();
        },
      );

      // Sauvegarder (Utilisation de .off() pour éviter le double déclenchement)
      $(document)
        .off("click", "#pc-experience-save-btn")
        .on("click", "#pc-experience-save-btn", () => {
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

      // === GESTION DES ONGLETS ===
      $(document).on("click", ".pc-tab-btn", (e) => {
        e.preventDefault();
        const $btn = $(e.currentTarget);
        const tabId = $btn.data("tab");

        console.log(`🔄 Clic sur onglet: ${tabId}`);

        // Activer le bon onglet
        $(".pc-tab-btn").removeClass("active");
        $btn.addClass("active");

        // Afficher le bon contenu
        $(".pc-tab-content").removeClass("active").hide();
        const $targetTab = $(`#tab-${tabId}`);

        if ($targetTab.length === 0) {
          console.error(`❌ Onglet #tab-${tabId} introuvable dans le DOM`);
          return;
        }

        $targetTab.addClass("active").show();

        // FORCE BRUTALE CSS pour l'onglet tarifs Experience
        if (tabId === "exp-rates") {
          $targetTab.css({
            display: "block !important",
            visibility: "visible !important",
            opacity: "1 !important",
          });
          console.log("🔧 CSS FORCE appliqué à #tab-exp-rates");
        }

        console.log(
          `✅ Onglet ${tabId} activé - Visible: ${$targetTab.is(":visible")}`,
        );

        // Si c'est l'onglet tarifs Experience, forcer le rendu du contenu
        if (tabId === "exp-rates") {
          console.log("🎯 Onglet Tarifs Experience activé - Force rendu");

          // Force immédiate de la visibilité CSS
          $targetTab.css({
            display: "block !important",
            visibility: "visible !important",
            opacity: "1 !important",
          });

          setTimeout(() => {
            const $wrapper = $("#wrapper-exp_types_de_tarifs");
            console.log("🔍 Wrapper tarifs trouvé:", $wrapper.length);

            // Force la visibilité du wrapper
            $wrapper.css({
              display: "block !important",
              visibility: "visible !important",
              opacity: "1 !important",
            });

            if ($wrapper.children().length === 0) {
              console.warn("⚠️ Contenu tarifs vide, rendu par défaut...");
              // Instance directe au lieu de window reference
              this.renderTarifsRepeater([
                {
                  exp_type: "unique",
                  exp_type_custom: "",
                  exp_tarifs_lignes: [],
                  exp_options_tarifaires: [],
                  "exp-frais-fixes": [],
                },
              ]);

              // Initialiser les événements tarifs
              this.bindTarifsEvents();
            } else {
              console.log(
                "✅ Contenu tarifs déjà présent:",
                $wrapper.children().length,
                "éléments",
              );
            }
          }, 100);
        }
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

      // === GESTION GALERIE ===
      $(document).on("click", "#btn-select-gallery-photos", (e) => {
        e.preventDefault();
        this.openGalleryUploader();
      });

      $(document).on("click", "#btn-clear-gallery-photos", (e) => {
        e.preventDefault();
        if (confirm("Voulez-vous vraiment vider la galerie ?")) {
          this.renderGalleryPreview([]);
        }
      });

      // Suppression d'une seule image de la galerie
      $(document).on("click", ".pc-btn-remove-gallery-item", (e) => {
        e.preventDefault();
        $(e.currentTarget).closest(".pc-gallery-item").remove();

        // Re-collecter les IDs restants et mettre à jour le champ caché
        const imageIds = [];
        $("#photos-experience-preview .pc-gallery-item").each(function () {
          const id = $(this).data("image-id");
          if (id) imageIds.push(id);
        });

        // Si on a tout supprimé un par un, on relance le rendu vide pour faire réapparaître le placeholder
        if (imageIds.length === 0) {
          this.renderGalleryPreview([]);
        } else {
          $("#photos_experience").val(imageIds.join(","));
          // Met à jour le compteur visuel
          $("#photos-experience-preview .pc-gallery-status strong").text(
            `${imageIds.length} image${imageIds.length > 1 ? "s" : ""}`,
          );
        }
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
      const lieuDepart = item.lieu_depart
        ? this.escapeHtml(item.lieu_depart)
        : "Non défini";
      const tva = item.taux_tva !== "" ? `${item.taux_tva}%` : "Non définie";

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
          <td class="pc-col-location">
            <span class="pc-experience-location" style="font-size:0.9rem;">${lieuDepart}</span>
          </td>
          <td class="pc-col-tva" style="text-align:center; font-weight:600; color:#475569;">
            ${tva}
          </td>
          <td class="pc-col-status">
            ${statusBadge}
          </td>
          <td class="pc-col-actions">
            <button class="pc-btn pc-btn-sm pc-action-edit" data-experience-id="${item.id}">
              <span>✏️</span>
              Éditer
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
        $("#pc-experience-delete-btn").addClass("hidden"); // Cacher le bouton supprimer
        $("#pc-experience-modal-loading").hide();
        $("#pc-experience-modal-details").show();
      } else {
        // Mode édition : charger les détails
        $("#pc-experience-modal-title").text("Chargement...");
        $("#pc-experience-delete-btn").removeClass("hidden"); // Afficher le bouton supprimer
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
      console.log("🔍 Données pour peuplement :", experience);

      // 🔧 MISE À JOUR DU TITRE DE LA MODALE
      const experienceTitle =
        experience.title ||
        experience.post_title ||
        experience.exp_h1_custom ||
        "Expérience";
      $("#pc-experience-modal-title").text(experienceTitle);

      // === 1. ONGLET SEO & LIAISONS ===
      $("#exp_exclude_sitemap").prop(
        "checked",
        experience.exp_exclude_sitemap == 1,
      );
      $("#exp_http_410").prop("checked", experience.exp_http_410 == 1);
      $("#exp_meta_titre").val(experience.exp_meta_titre || "");
      $("#exp_meta_description").val(experience.exp_meta_description || "");
      $("#exp_meta_canonical").val(experience.exp_meta_canonical || "");
      $("#exp_meta_robots").val(experience.exp_meta_robots || "index,follow");

      // Liaisons (IDs séparés par virgule)
      let logements = experience.exp_logements_recommandes || [];
      if (Array.isArray(logements)) logements = logements.join(",");
      $("#exp_logements_recommandes").val(logements);

      const isAvailable =
        experience.exp_availability == 1 ||
        experience.exp_availability === "true";
      $("#exp_availability").prop("checked", isAvailable);

      // === 2. ONGLET DÉTAILS PRINCIPAUX ===
      $("#exp_h1_custom").val(experience.exp_h1_custom || "");

      // Images Hero
      this.populateImageField("exp_hero_desktop", experience.exp_hero_desktop);
      this.populateImageField("exp_hero_mobile", experience.exp_hero_mobile);

      // === 3. ONGLET DÉTAILS SORTIES ===
      $("#exp_duree").val(experience.exp_duree || "");
      $("#exp_capacite").val(experience.exp_capacite || "");
      $("#exp_age_minimum").val(experience.exp_age_minimum || "");

      // Checkboxes (Arrays)
      this.populateCheckboxArray(
        "exp_accessibilite",
        experience.exp_accessibilite,
      );
      this.populateCheckboxArray("exp_periode", experience.exp_periode);
      this.populateCheckboxArray("exp_jour", experience.exp_jour);

      // Repeaters Sorties
      this.renderFermetureRepeater(experience.exp_periodes_fermeture || []);
      this.renderLieuxRepeater(experience.exp_lieux_horaires_depart || []);

      // === 4. ONGLET INCLUSIONS ===
      $("#exp_prix_comprend").val(experience.exp_prix_comprend || "");
      $("#exp_prix_ne_comprend_pas").val(
        experience.exp_prix_ne_comprend_pas || "",
      );
      this.populateCheckboxArray("exp_a_prevoir", experience.exp_a_prevoir);

      // === 5. ONGLET SERVICES ===
      this.populateCheckboxArray(
        "exp_delai_de_reservation",
        experience.exp_delai_de_reservation,
      );
      this.populateCheckboxArray(
        "exp_zone_intervention",
        experience.exp_zone_intervention,
      );

      $("#exp_type_de_prestation").val(experience.exp_type_de_prestation || "");
      $("#exp_heure_limite_de_commande").val(
        experience.exp_heure_limite_de_commande || "",
      );

      // Textareas Services (souvent oubliés)
      $("#exp_le_service_comprend").val(
        experience.exp_le_service_comprend || "",
      );
      $("#exp_service_a_prevoir").val(experience.exp_service_a_prevoir || "");

      // === 6. ONGLET GALERIE (Affichage des miniatures) ===
      this.renderGalleryPreview(experience.photos_experience || []);

      // === 7. ONGLET FAQ ===
      this.renderFaqRepeater(experience.exp_faq || []);

      // === 8. 🛡️ ONGLET TARIFS (BLOC SÉCURISÉ) ===
      try {
        // 🔧 SÉCURITÉ ANTI-CRASH : Force les données à être des tableaux
        const tarifsData = experience.exp_types_de_tarifs || [];
        console.log("🔍 Rendu Tarifs avec", tarifsData.length, "éléments");

        this.renderTarifsRepeater(tarifsData);

        // 🔧 Champ TVA (hérité du JSON ACF)
        $("#exp_taux_tva").val(experience.taux_tva || "");
      } catch (error) {
        console.error("❌ Erreur onglet Tarifs:", error);
        // Fallback : Interface minimale
        $("#wrapper-exp_types_de_tarifs").html(
          '<p class="pc-error">Erreur lors du chargement des tarifs. Veuillez recharger la page.</p>',
        );
      }

      // === 9. ONGLET RÈGLES & PAIEMENT ===
      $("#exp_pay_mode").val(experience.pc_pay_mode || "acompte_plus_solde");
      $("#exp_deposit_type").val(experience.pc_deposit_type || "pourcentage");
      $("#exp_deposit_value").val(experience.pc_deposit_value || "");
      $("#exp_balance_delay_days").val(experience.pc_balance_delay_days || "");
      $("#exp_caution_amount").val(experience.pc_caution_amount || "");
      $("#exp_caution_mode").val(experience.pc_caution_mode || "aucune");
    }

    resetForm() {
      // Reset tous les inputs text/textarea
      $(
        "#experience-modal input[type='text'], #experience-modal input[type='number'], #experience-modal textarea",
      ).val("");

      // Reset checkboxes
      $("#experience-modal input[type='checkbox']").prop("checked", false);

      // Reset selects aux valeurs par défaut (nouveaux IDs)
      $("#exp_pay_mode").val("acompte_plus_solde");
      $("#exp_deposit_type").val("pourcentage");

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

      const title = $("#exp_h1_custom").val().trim();
      const $btn = $("#pc-experience-save-btn");
      $btn.addClass("loading").prop("disabled", true);

      // Collecte des données
      const formData = {
        action: "pc_experience_save",
        nonce: this.nonce,
        post_id: currentExperienceId,

        title: $("#exp_h1_custom").val(),
        acf_exp_h1_custom: $("#exp_h1_custom").val(),
        acf_exp_availability: $("#exp_availability").is(":checked") ? "1" : "0",

        // Champs SEO & Général manquants
        acf_exp_exclude_sitemap: $("#exp_exclude_sitemap").is(":checked")
          ? "1"
          : "0",
        acf_exp_http_410: $("#exp_http_410").is(":checked") ? "1" : "0",
        acf_exp_meta_titre: $("#exp_meta_titre").val(),
        acf_exp_meta_description: $("#exp_meta_description").val(),
        acf_exp_meta_canonical: $("#exp_meta_canonical").val(),
        acf_exp_meta_robots: $("#exp_meta_robots").val(),
        acf_exp_logements_recommandes: $("#exp_logements_recommandes").val()
          ? $("#exp_logements_recommandes")
              .val()
              .split(",")
              .map((s) => s.trim())
          : [],

        acf_exp_duree: $("#exp_duree").val(),
        acf_exp_capacite: $("#exp_capacite").val(),
        acf_exp_age_minimum: $("#exp_age_minimum").val(),

        acf_exp_accessibilite: this.collectCheckboxArray("exp_accessibilite"),
        acf_exp_periode: this.collectCheckboxArray("exp_periode"),
        acf_exp_jour: this.collectCheckboxArray("exp_jour"),

        acf_exp_prix_comprend: $("#exp_prix_comprend").val(),
        acf_exp_prix_ne_comprend_pas: $("#exp_prix_ne_comprend_pas").val(),
        acf_exp_a_prevoir: this.collectCheckboxArray("exp_a_prevoir"),

        acf_exp_delai_de_reservation: this.collectCheckboxArray(
          "exp_delai_de_reservation",
        ),
        acf_exp_zone_intervention: this.collectCheckboxArray(
          "exp_zone_intervention",
        ),
        acf_exp_type_de_prestation: $("#exp_type_de_prestation").val(),
        acf_exp_heure_limite_de_commande: $(
          "#exp_heure_limite_de_commande",
        ).val(),
        acf_exp_le_service_comprend: $("#exp_le_service_comprend").val(),
        acf_exp_service_a_prevoir: $("#exp_service_a_prevoir").val(),

        acf_taux_tva: $("#exp_taux_tva").val(),
        acf_pc_pay_mode: $("#exp_pay_mode").val(),
        acf_pc_deposit_type: $("#exp_deposit_type").val(),
        acf_pc_deposit_value: $("#exp_deposit_value").val(),
        acf_pc_balance_delay_days: $("#exp_balance_delay_days").val(),
        acf_pc_caution_amount: $("#exp_caution_amount").val(),
        acf_pc_caution_mode: $("#exp_caution_mode").val(),

        acf_exp_hero_desktop: $("#exp_hero_desktop").val(),
        acf_exp_hero_mobile: $("#exp_hero_mobile").val(),
        acf_photos_experience: $("#photos_experience").val(),

        acf_exp_lieux_horaires_depart: this.collectLieuxData(),
        acf_exp_periodes_fermeture: this.collectFermetureData(),
        acf_exp_faq: this.collectFaqData(),
        acf_exp_types_de_tarifs: this.collectTarifsData(),
      };

      $.ajax({
        url: this.ajaxUrl,
        type: "POST",
        data: formData,
        success: (response) => {
          $btn.removeClass("loading").prop("disabled", false);

          if (response.success) {
            this.showSuccess("Expérience sauvegardée avec succès!");
            this.closeModal(); // Fermeture de la modale automatique
            this.loadList(currentPage); // Rechargement propre du tableau
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
        // On sécurise les valeurs pour éviter les undefined
        const lieu = this.escapeHtml(item.exp_lieu_depart || "");
        const lat = item.lat_exp || "";
        const lon = item.longitude || ""; // Attention: clé 'longitude' dans ton JSON
        const dep = item.exp_heure_depart || "";
        const ret = item.exp_heure_retour || "";

        const html = `
          <div class="pc-repeater-row" data-index="${index}">
            <div class="pc-form-grid">
              <div class="pc-form-group pc-form-group--full">
                <label>Lieu de départ</label>
                <input type="text" class="pc-input lieu-depart" value="${lieu}" placeholder="Ex: Marina de Pointe-à-Pitre">
              </div>
              
              <div class="pc-form-group">
                <label>Latitude</label>
                <input type="text" class="pc-input lat-exp" value="${lat}" placeholder="Ex: 16.24">
              </div>
              <div class="pc-form-group">
                <label>Longitude</label>
                <input type="text" class="pc-input longitude" value="${lon}" placeholder="Ex: -61.53">
              </div>

              <div class="pc-form-group">
                <label>Heure Départ</label>
                <input type="time" class="pc-input heure-depart" value="${dep}">
              </div>
              <div class="pc-form-group">
                <label>Heure Retour</label>
                <input type="time" class="pc-input heure-retour" value="${ret}">
              </div>

              <div class="pc-form-group">
                <button type="button" class="pc-btn pc-btn-danger remove-lieu-row" style="margin-top: 25px;">
                  <span>🗑️</span>
                </button>
              </div>
            </div>
          </div>
        `;
        $wrapper.append(html);
      });

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

      if (!items) items = [];

      items.forEach((item, index) => {
        // Supporte les clés avec ou sans préfixe
        const quest = this.escapeHtml(item.exp_question || item.question || "");
        const rep = this.escapeHtml(item.exp_reponse || item.reponse || "");

        const html = `
          <div class="pc-repeater-row" data-index="${index}">
            <div class="pc-form-grid">
              <div class="pc-form-group pc-form-group--full">
                <label>Question</label>
                <input type="text" class="pc-input question" value="${quest}" placeholder="Question posée">
              </div>
              <div class="pc-form-group pc-form-group--full">
                <label>Réponse</label>
                <textarea class="pc-textarea reponse" rows="2" placeholder="Réponse apportée">${rep}</textarea>
              </div>
              <div class="pc-form-group">
                <button type="button" class="pc-btn pc-btn-danger remove-faq-row" style="margin-top:5px;">
                  <span>🗑️</span> Supprimer
                </button>
              </div>
            </div>
          </div>
        `;
        $wrapper.append(html);
      });

      $wrapper.append(`
        <button type="button" class="pc-btn pc-btn-secondary add-faq-row">
          <span>➕</span> Ajouter une question
        </button>
      `);

      // Réattacher l'événement d'ajout
      $(".add-faq-row")
        .off("click")
        .on("click", () => {
          const index = Date.now();
          const html = `
          <div class="pc-repeater-row" data-index="${index}">
            <div class="pc-form-grid">
              <div class="pc-form-group pc-form-group--full">
                <label>Question</label>
                <input type="text" class="pc-input question" placeholder="Question posée">
              </div>
              <div class="pc-form-group pc-form-group--full">
                <label>Réponse</label>
                <textarea class="pc-textarea reponse" rows="2" placeholder="Réponse apportée"></textarea>
              </div>
              <div class="pc-form-group">
                <button type="button" class="pc-btn pc-btn-danger remove-faq-row"><span>🗑️</span> Supprimer</button>
              </div>
            </div>
          </div>`;
          $(".add-faq-row").before(html);
        });

      // Délégation pour suppression
      $(document)
        .off("click", ".remove-faq-row")
        .on("click", ".remove-faq-row", function () {
          $(this).closest(".pc-repeater-row").remove();
        });
    }

    renderTarifsRepeater(items) {
      console.log("🔄 renderTarifsRepeater appelé avec:", items);

      const $wrapper = $("#wrapper-exp_types_de_tarifs");

      // SÉCURITÉ : Vérifier que l'élément existe
      if ($wrapper.length === 0) {
        console.error("❌ Élément #wrapper-exp_types_de_tarifs introuvable");
        return;
      }

      $wrapper.empty();

      // Initialiser les items si undefined
      if (!items || !Array.isArray(items)) {
        items = [];
      }

      // Si aucun item, ajouter un item par défaut
      if (items.length === 0) {
        items = [
          {
            exp_type: "unique",
            exp_type_custom: "",
            exp_options_tarifaires: [],
            "exp-frais-fixes": [],
            exp_tarifs_lignes: [],
          },
        ];
      }

      console.log(`📝 Rendu de ${items.length} tarifs`);

      items.forEach((item, index) => {
        const type = item.exp_type || "unique";
        const labelCustom = this.escapeHtml(item.exp_type_custom || "");
        const showCustomField = type === "custom";

        const html = `
          <div class="pc-repeater-row" data-index="${index}" style="border:2px solid #e2e8f0; padding:20px; margin-bottom:20px; border-radius:12px; background:#fff;">
            <div class="pc-form-grid">
              <div class="pc-form-group">
                <label><strong>Type de tarif</strong></label>
                <select class="pc-select tarif-type">
                    <option value="unique" ${type === "unique" ? "selected" : ""}>Unique / Forfaitaire</option>
                    <option value="journee" ${type === "journee" ? "selected" : ""}>Journée</option>
                    <option value="demi-journee" ${type === "demi-journee" ? "selected" : ""}>Demi-journée</option>
                    <option value="sur-devis" ${type === "sur-devis" ? "selected" : ""}>Sur Devis</option>
                    <option value="custom" ${type === "custom" ? "selected" : ""}>Personnalisé</option>
                </select>
              </div>
              
              <div class="pc-form-group tarif-custom-field" style="display: ${showCustomField ? "block" : "none"};">
                 <label><strong>Nom personnalisé</strong></label>
                 <input type="text" class="pc-input tarif-custom" value="${labelCustom}" placeholder="Ex: Soirée VIP" maxlength="60">
              </div>

              <!-- Lignes de tarifs selon le JSON ACF -->
              <div class="pc-form-group pc-form-group--full" style="margin-top: 20px;">
                <label><strong>💰 Lignes de tarifs</strong></label>
                <div class="tarifs-lignes-container" data-tarif-index="${index}">
                  <!-- Généré dynamiquement -->
                </div>
                <button type="button" class="pc-btn pc-btn-sm pc-btn-secondary add-ligne-tarif" data-tarif-index="${index}">
                  <span>➕</span> Ajouter une ligne de tarif
                </button>
              </div>

              <!-- Options tarifaires selon le JSON ACF -->
              <div class="pc-form-group pc-form-group--full" style="margin-top: 20px;">
                <label><strong>⭐ Options tarifaires</strong></label>
                <div class="options-tarifaires-container" data-tarif-index="${index}">
                  <!-- Généré dynamiquement -->
                </div>
                <button type="button" class="pc-btn pc-btn-sm pc-btn-secondary add-option-tarifaire" data-tarif-index="${index}">
                  <span>➕</span> Ajouter une option
                </button>
              </div>

              <!-- Frais fixes selon le JSON ACF -->
              <div class="pc-form-group pc-form-group--full" style="margin-top: 20px;">
                <label><strong>🏷️ Frais fixes</strong></label>
                <div class="frais-fixes-container" data-tarif-index="${index}">
                  <!-- Généré dynamiquement -->
                </div>
                <button type="button" class="pc-btn pc-btn-sm pc-btn-secondary add-frais-fixe" data-tarif-index="${index}">
                  <span>➕</span> Ajouter un frais fixe
                </button>
              </div>

              <div class="pc-form-group" style="text-align: right; margin-top: 20px;">
                <button type="button" class="pc-btn pc-btn-danger remove-tarif-row">
                  <span>🗑️</span> Supprimer ce type de tarif
                </button>
              </div>
            </div>
          </div>
        `;
        $wrapper.append(html);

        // Remplir les sous-repeaters
        this.renderLignesTarifs(index, item.exp_tarifs_lignes || []);
        this.renderOptionsTarifaires(index, item.exp_options_tarifaires || []);
        this.renderFraisFixes(index, item["exp-frais-fixes"] || []);
      });

      // Bouton d'ajout général
      $wrapper.append(`
        <button type="button" class="pc-btn pc-btn-primary add-tarif-row">
          <span>➕</span> Ajouter un type de tarif
        </button>
      `);

      // Attacher les gestionnaires d'événements
      this.bindTarifsEvents();

      console.log("✅ renderTarifsRepeater terminé avec succès");
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

          // On construit l'objet avec les clés exactes attendues par ACF (selon le JSON fourni)
          const item = {
            exp_lieu_depart: $row.find(".lieu-depart").val() || "",
            lat_exp: $row.find(".lat-exp").val() || "",
            longitude: $row.find(".longitude").val() || "",
            exp_heure_depart: $row.find(".heure-depart").val() || "",
            exp_heure_retour: $row.find(".heure-retour").val() || "",
          };

          // On n'ajoute la ligne que si au moins un champ principal est rempli
          // pour éviter d'enregistrer des lignes vides
          if (item.exp_lieu_depart || item.lat_exp || item.longitude) {
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
            debut_fermeture: $row.find(".date-debut").val() || "",
            fin_fermeture: $row.find(".date-fin").val() || "",
          };
          if (item.debut_fermeture || item.fin_fermeture) {
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
          exp_question: $row.find(".question").val() || "",
          exp_reponse: $row.find(".reponse").val() || "",
        };
        if (item.exp_question || item.exp_reponse) {
          data.push(item);
        }
      });
      return data;
    }

    // === 🔧 FONCTION CRITIQUE : COLLECTE DES TARIFS ===
    collectTarifsData() {
      const data = [];
      $("#wrapper-exp_types_de_tarifs .pc-repeater-row").each((index, row) => {
        const $row = $(row);
        const tarifIndex = $row.data("index");

        // 🔧 Clés exactes du JSON ACF avec TOUS les sous-repeaters
        const item = {
          exp_type: $row.find(".tarif-type").val() || "unique",
          exp_type_custom: $row.find(".tarif-custom").val() || "",
          // Collecte des sous-repeaters
          exp_tarifs_lignes: this.collectLignesTarifs(tarifIndex),
          exp_options_tarifaires: this.collectOptionsTarifaires(tarifIndex),
          "exp-frais-fixes": this.collectFraisFixes(tarifIndex),
        };

        // On ajoute toujours l'item (même vide) pour maintenir la structure ACF
        data.push(item);
      });

      console.log("🔍 Collecte Tarifs complète:", data);
      return data;
    }

    // === 🔧 COLLECTE DES SOUS-REPEATERS TARIFS ===

    /**
     * Collecte les lignes de tarifs pour un type de tarif donné
     */
    collectLignesTarifs(tarifIndex) {
      const data = [];
      $(
        `.tarifs-lignes-container[data-tarif-index="${tarifIndex}"] .ligne-tarif-row`,
      ).each((index, row) => {
        const $row = $(row);
        const item = {
          type_ligne: $row.find(".ligne-type").val() || "personnalise",
          tarif_valeur: parseFloat($row.find(".ligne-tarif-valeur").val()) || 0,
          tarif_enable_qty: $row.find(".ligne-enable-qty").is(":checked"),
          tarif_nom_perso: $row.find(".ligne-nom-precision").val() || "",
          tarif_observation: $row.find(".ligne-observation").val() || "",
        };

        // Ajouter seulement si au moins un champ est rempli
        if (item.type_ligne || item.tarif_valeur || item.tarif_nom_perso) {
          data.push(item);
        }
      });
      return data;
    }

    /**
     * Collecte les options tarifaires pour un type de tarif donné
     */
    collectOptionsTarifaires(tarifIndex) {
      const data = [];
      $(
        `.options-tarifaires-container[data-tarif-index="${tarifIndex}"] .option-tarifaire-row`,
      ).each((index, row) => {
        const $row = $(row);
        const item = {
          exp_description_option: $row.find(".option-description").val() || "",
          exp_tarif_option: parseFloat($row.find(".option-tarif").val()) || 0,
          option_enable_qty: $row.find(".option-enable-qty").is(":checked"),
        };

        // Ajouter seulement si au moins un champ est rempli
        if (item.exp_description_option || item.exp_tarif_option) {
          data.push(item);
        }
      });
      return data;
    }

    /**
     * Collecte les frais fixes pour un type de tarif donné
     */
    collectFraisFixes(tarifIndex) {
      const data = [];
      $(
        `.frais-fixes-container[data-tarif-index="${tarifIndex}"] .frais-fixe-row`,
      ).each((index, row) => {
        const $row = $(row);
        const item = {
          exp_description_frais_fixe:
            $row.find(".frais-description").val() || "",
          exp_tarif_frais_fixe:
            parseFloat($row.find(".frais-tarif").val()) || 0,
        };

        // Ajouter seulement si au moins un champ est rempli
        if (item.exp_description_frais_fixe || item.exp_tarif_frais_fixe) {
          data.push(item);
        }
      });
      return data;
    }

    /**
     * Ajoute un nouveau type de tarif
     */
    addTarifType() {
      const $wrapper = $("#wrapper-exp_types_de_tarifs");
      const newIndex = Date.now();

      const html = `
        <div class="pc-repeater-row" data-index="${newIndex}" style="border:2px solid #e2e8f0; padding:20px; margin-bottom:20px; border-radius:12px; background:#fff;">
          <div class="pc-form-grid">
            <div class="pc-form-group">
              <label><strong>Type de tarif</strong></label>
              <select class="pc-select tarif-type">
                  <option value="unique" selected>Unique / Forfaitaire</option>
                  <option value="journee">Journée</option>
                  <option value="demi-journee">Demi-journée</option>
                  <option value="sur-devis">Sur Devis</option>
                  <option value="custom">Personnalisé</option>
              </select>
            </div>
            
            <div class="pc-form-group tarif-custom-field" style="display: none;">
               <label><strong>Nom personnalisé</strong></label>
               <input type="text" class="pc-input tarif-custom" placeholder="Ex: Soirée VIP" maxlength="60">
            </div>

            <!-- Lignes de tarifs selon le JSON ACF -->
            <div class="pc-form-group pc-form-group--full" style="margin-top: 20px;">
              <label><strong>💰 Lignes de tarifs</strong></label>
              <div class="tarifs-lignes-container" data-tarif-index="${newIndex}">
                <!-- Généré dynamiquement -->
              </div>
              <button type="button" class="pc-btn pc-btn-sm pc-btn-secondary add-ligne-tarif" data-tarif-index="${newIndex}">
                <span>➕</span> Ajouter une ligne de tarif
              </button>
            </div>

            <!-- Options tarifaires selon le JSON ACF -->
            <div class="pc-form-group pc-form-group--full" style="margin-top: 20px;">
              <label><strong>⭐ Options tarifaires</strong></label>
              <div class="options-tarifaires-container" data-tarif-index="${newIndex}">
                <!-- Généré dynamiquement -->
              </div>
              <button type="button" class="pc-btn pc-btn-sm pc-btn-secondary add-option-tarifaire" data-tarif-index="${newIndex}">
                <span>➕</span> Ajouter une option
              </button>
            </div>

            <!-- Frais fixes selon le JSON ACF -->
            <div class="pc-form-group pc-form-group--full" style="margin-top: 20px;">
              <label><strong>🏷️ Frais fixes</strong></label>
              <div class="frais-fixes-container" data-tarif-index="${newIndex}">
                <!-- Généré dynamiquement -->
              </div>
              <button type="button" class="pc-btn pc-btn-sm pc-btn-secondary add-frais-fixe" data-tarif-index="${newIndex}">
                <span>➕</span> Ajouter un frais fixe
              </button>
            </div>

            <div class="pc-form-group" style="text-align: right; margin-top: 20px;">
              <button type="button" class="pc-btn pc-btn-danger remove-tarif-row">
                <span>🗑️</span> Supprimer ce type de tarif
              </button>
            </div>
          </div>
        </div>
      `;

      // Insérer avant le bouton d'ajout général
      $(".add-tarif-row").last().before(html);

      // Re-attacher les événements pour le nouveau row
      this.bindTarifsEvents();
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

    /**
     * Affiche les miniatures de la galerie dans l'interface
     * @param {Array} galleryImages - Tableau d'objets images {id, url, thumbnail}
     */
    renderGalleryPreview(galleryImages) {
      const $preview = $("#photos-experience-preview");
      const $input = $("#photos_experience");
      const $clearBtn = $("#btn-clear-gallery-photos");

      console.log("🖼️ renderGalleryPreview appelé avec:", galleryImages);

      if (
        !galleryImages ||
        !Array.isArray(galleryImages) ||
        galleryImages.length === 0
      ) {
        // Aucune image : afficher le placeholder
        $preview.html(`
          <div class="pc-gallery-placeholder">
            📷 Aucune photo sélectionnée - Ajoutez jusqu'à 5 photos
          </div>
        `);
        $input.val("");
        $clearBtn.hide(); // Cacher le bouton Vider
        return;
      }

      // Construire le HTML des miniatures
      let galleryHtml =
        '<div class="pc-gallery-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 10px; margin-bottom: 15px;">';
      const imageIds = [];

      galleryImages.forEach((image, index) => {
        const imageId = image.id;
        const imageUrl = image.url || image.thumbnail || "";
        const thumbnailUrl = image.thumbnail || image.url || "";

        if (imageId) {
          imageIds.push(imageId);
        }

        galleryHtml += `
          <div class="pc-gallery-item" data-image-id="${imageId || ""}" style="position: relative;">
            <div class="pc-gallery-thumb">
              <img src="${thumbnailUrl}" alt="Photo ${index + 1}" 
                   style="width: 100%; height: 80px; object-fit: cover; border-radius: 4px;"
                   onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHZpZXdCb3g9IjAgMCA4MCA4MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjgwIiBoZWlnaHQ9IjgwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0yNSAyNUg1NUM0OC4zNzMzIDQxLjY2NjcgMzcuNjI2NyA0MS42NjY3IDMwIDQxLjY2NjdWNTVIMjVWMjVaIiBmaWxsPSIjOTRBM0I4Ii8+Cjwvc3ZnPgo='">
            </div>
            <button type="button" class="pc-btn-remove-gallery-item" title="Retirer cette image" style="position: absolute; top: 4px; right: 4px; background: rgba(239, 68, 68, 0.95); color: white; border: none; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 11px; box-shadow: 0 2px 4px rgba(0,0,0,0.2); transition: all 0.2s;">✕</button>
          </div>
        `;
      });

      galleryHtml += "</div>";

      // Ajouter le message de statut
      galleryHtml += `
        <div class="pc-gallery-status" style="font-size: 0.9rem; color: #475569;">
          ✅ <strong>${galleryImages.length} image${galleryImages.length > 1 ? "s" : ""}</strong> dans la galerie
          ${galleryImages.length >= 5 ? " (Maximum recommandé atteint)" : ""}
        </div>
      `;

      // Injecter dans le DOM
      $preview.html(galleryHtml);

      // Stocker les IDs dans l'input caché pour la sauvegarde (ACF gère ça comme une string d'IDs séparés par des virgules)
      $input.val(imageIds.join(","));
      $clearBtn.show(); // Afficher le bouton Vider

      console.log(
        "✅ Galerie rendue avec",
        galleryImages.length,
        "images, IDs:",
        imageIds.join(","),
      );
    }

    // === GESTION DES IMAGES ===
    populateImageField(target, value) {
      if (!value) return;

      // 🔧 FIX : Conversion des underscores en tirets pour les IDs HTML
      const targetForHtml = target.replace(/_/g, "-");
      const $preview = $(`#preview-${targetForHtml}`);
      const $input = $(`#${target}`);
      const $removeBtn = $(
        `.pc-btn-remove-image[data-target="${targetForHtml}"]`,
      );

      console.log(`🖼️ populateImageField pour ${target}:`, value);

      // 🔧 NOUVEAU : Gestion des objets {id, url, type} retournés par le PHP
      if (
        typeof value === "object" &&
        value !== null &&
        !Array.isArray(value)
      ) {
        const imageId = value.id;
        const imageUrl = value.url;

        // Stocker l'ID dans l'input caché (priorité à l'ID si disponible)
        if (imageId) {
          $input.val(imageId);
        } else if (imageUrl) {
          $input.val(imageUrl);
        }

        // Afficher l'aperçu avec l'URL
        if (imageUrl) {
          $preview.html(
            `<img src="${imageUrl}" alt="Image" style="width: 100%; height: 120px; object-fit: cover; border-radius: 8px;">`,
          );
          $removeBtn.show();
        } else {
          $preview.html(
            `<div class="pc-image-placeholder">📷 Image #${imageId} (URL non disponible)</div>`,
          );
        }
        return;
      }

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
      // 🔧 FIX : Même logique de conversion que dans populateImageField
      const targetForHtml = target.replace(/_/g, "-");
      const $preview = $(`#preview-${targetForHtml}`);
      const $input = $(`#${target}`);
      const $removeBtn = $(
        `.pc-btn-remove-image[data-target="${targetForHtml}"]`,
      );

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

      // Initialiser un cache pour les modales uniques si non existant
      if (!this.mediaFrames) this.mediaFrames = {};

      // 🛡️ SÉCURITÉ ANTI-FREEZE
      if (this.mediaFrames[target]) {
        this.mediaFrames[target].open();
        return;
      }

      this.mediaFrames[target] = wp.media({
        title: "Sélectionner une image",
        button: { text: "Utiliser cette image" },
        multiple: false,
        library: { type: "image" },
      });

      this.mediaFrames[target].on("select", () => {
        const attachment = this.mediaFrames[target]
          .state()
          .get("selection")
          .first()
          .toJSON();
        this.setImageFromUploader(target, attachment);

        // 🛡️ FORCER LA FERMETURE DE LA MODALE
        this.mediaFrames[target].close();
      });

      this.mediaFrames[target].open();
    }

    setImageFromUploader(target, attachment) {
      // 🔧 FIX CRITIQUE : Convertir les tirets en underscores pour cibler l'ID du input caché !
      // ex: 'exp-hero-desktop' (target) -> 'exp_hero_desktop' (input ID)
      const inputId = target.replace(/-/g, "_");

      const $preview = $(`#preview-${target}`);
      const $input = $(`#${inputId}`);
      const $removeBtn = $(`.pc-btn-remove-image[data-target="${target}"]`);

      // Mettre à jour l'aperçu
      $preview.html(
        `<img src="${attachment.url}" alt="${attachment.alt || "Image"}" style="width: 100%; height: 120px; object-fit: cover; border-radius: 8px;">`,
      );

      // Stocker l'ID de l'attachment dans le BON input caché
      $input.val(attachment.id);
      $input.data("image-url", attachment.url);

      // Afficher le bouton de suppression
      $removeBtn.show();
    }

    // === NOUVEAU : UPLOADER MULTIPLE POUR LA GALERIE ===
    openGalleryUploader() {
      if (typeof wp === "undefined" || !wp.media) {
        this.showError("WordPress Media Library n'est pas disponible.");
        return;
      }

      // 🛡️ SÉCURITÉ ANTI-FREEZE : On réutilise l'instance si elle existe déjà
      if (this.galleryFrame) {
        this.galleryFrame.open();
        return;
      }

      this.galleryFrame = wp.media({
        title: "Sélectionner des photos pour la galerie",
        button: { text: "Ajouter à la galerie" },
        multiple: true,
        library: { type: "image" },
      });

      this.galleryFrame.on("select", () => {
        try {
          const selection = this.galleryFrame.state().get("selection");

          // ✨ UX : Récupérer les images déjà présentes dans l'interface
          const currentImages = [];
          $("#photos-experience-preview .pc-gallery-item").each(function () {
            const id = $(this).data("image-id");
            const url = $(this).find("img").attr("src");
            if (id) {
              currentImages.push({
                id: parseInt(id),
                url: url,
                thumbnail: url,
              });
            }
          });

          // Parcourir la sélection WP Media de manière sécurisée (Backbone Models)
          selection.models.forEach((model) => {
            const att = model.toJSON();

            // Éviter d'ajouter une image qui est déjà dans la galerie
            if (!currentImages.find((img) => img.id === att.id)) {
              currentImages.push({
                id: att.id,
                url: att.url,
                thumbnail:
                  att.sizes && att.sizes.thumbnail
                    ? att.sizes.thumbnail.url
                    : att.url,
              });
            }
          });

          // Envoyer les images (anciennes + nouvelles) au rendu
          this.renderGalleryPreview(currentImages);

          // 🛡️ CORRECTION DU BUG : Forcer la fermeture de la modale WP Media
          this.galleryFrame.close();
        } catch (error) {
          console.error("Erreur lors du traitement de la galerie :", error);
        }
      });

      this.galleryFrame.open();
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

    // === 🔧 FONCTIONS TARIFS - SOUS-REPEATERS SELON JSON ACF ===

    /**
     * Rend les lignes de tarifs pour un type de tarif donné
     * Basé sur le sous-repeater exp_tarifs_lignes du JSON ACF
     */
    renderLignesTarifs(tarifIndex, lignes) {
      const $container = $(
        `.tarifs-lignes-container[data-tarif-index="${tarifIndex}"]`,
      );
      $container.empty();

      if (!lignes || !Array.isArray(lignes)) lignes = [];

      lignes.forEach((ligne, index) => {
        const typeLigne = ligne.type_ligne || "personnalise";
        const tarifValeur = ligne.tarif_valeur || "";
        const enableQty = ligne.tarif_enable_qty || false;
        const precisionAge =
          ligne.precision_age_enfant || ligne.precision_age_bebe || "";
        const nomPerso = ligne.tarif_nom_perso || "";
        const observation = ligne.tarif_observation || "";

        const html = `
          <div class="ligne-tarif-row" data-ligne-index="${index}" style="border:1px solid #cbd5e0; padding:10px; margin-bottom:10px; border-radius:6px; background:#f8fafc;">
            <div class="pc-form-grid" style="grid-template-columns: 1fr 1fr 1fr 1fr; gap:10px;">
              <div class="pc-form-group">
                <label>Type</label>
                <select class="pc-select ligne-type">
                  <option value="adulte" ${typeLigne === "adulte" ? "selected" : ""}>Adulte</option>
                  <option value="enfant" ${typeLigne === "enfant" ? "selected" : ""}>Enfant</option>
                  <option value="bebe" ${typeLigne === "bebe" ? "selected" : ""}>Bébé</option>
                  <option value="personnalise" ${typeLigne === "personnalise" ? "selected" : ""}>Personnalisé</option>
                </select>
              </div>
              
              <div class="pc-form-group">
                <label>Tarif (€)</label>
                <input type="number" class="pc-input ligne-tarif-valeur" value="${tarifValeur}" min="0" step="0.01" placeholder="0.00">
              </div>
              
              <div class="pc-form-group">
                <label>
                  <input type="checkbox" class="ligne-enable-qty" ${enableQty ? "checked" : ""}> 
                  Quantité?
                </label>
              </div>
              
              <div class="pc-form-group">
                <button type="button" class="pc-btn pc-btn-sm pc-btn-danger remove-ligne-tarif">
                  <span>🗑️</span>
                </button>
              </div>
              
              <div class="pc-form-group pc-form-group--full">
                <label>Nom/Précision</label>
                <input type="text" class="pc-input ligne-nom-precision" value="${this.escapeHtml(nomPerso || precisionAge)}" placeholder="Ex: 3-12 ans, Privatisation, etc.">
              </div>
              
              <div class="pc-form-group pc-form-group--full">
                <label>Observation</label>
                <input type="text" class="pc-input ligne-observation" value="${this.escapeHtml(observation)}" placeholder="Ex: jusqu'à 12 pers">
              </div>
            </div>
          </div>
        `;
        $container.append(html);
      });
    }

    /**
     * Rend les options tarifaires pour un type de tarif donné
     * Basé sur le sous-repeater exp_options_tarifaires du JSON ACF
     */
    renderOptionsTarifaires(tarifIndex, options) {
      const $container = $(
        `.options-tarifaires-container[data-tarif-index="${tarifIndex}"]`,
      );
      $container.empty();

      if (!options || !Array.isArray(options)) options = [];

      options.forEach((option, index) => {
        const description = option.exp_description_option || "";
        const tarif = option.exp_tarif_option || "";
        const enableQty = option.option_enable_qty || false;

        const html = `
          <div class="option-tarifaire-row" data-option-index="${index}" style="border:1px solid #cbd5e0; padding:10px; margin-bottom:10px; border-radius:6px; background:#fffbeb;">
            <div class="pc-form-grid" style="grid-template-columns: 2fr 1fr 1fr 1fr; gap:10px;">
              <div class="pc-form-group">
                <label>Description</label>
                <input type="text" class="pc-input option-description" value="${this.escapeHtml(description)}" placeholder="Ex: Skipper professionnel">
              </div>
              
              <div class="pc-form-group">
                <label>Tarif (€)</label>
                <input type="number" class="pc-input option-tarif" value="${tarif}" min="0" step="0.01" placeholder="0.00">
              </div>
              
              <div class="pc-form-group">
                <label>
                  <input type="checkbox" class="option-enable-qty" ${enableQty ? "checked" : ""}> 
                  Quantité?
                </label>
              </div>
              
              <div class="pc-form-group">
                <button type="button" class="pc-btn pc-btn-sm pc-btn-danger remove-option-tarifaire">
                  <span>🗑️</span>
                </button>
              </div>
            </div>
          </div>
        `;
        $container.append(html);
      });
    }

    /**
     * Rend les frais fixes pour un type de tarif donné
     * Basé sur le sous-repeater exp-frais-fixes du JSON ACF
     */
    renderFraisFixes(tarifIndex, fraisFixes) {
      const $container = $(
        `.frais-fixes-container[data-tarif-index="${tarifIndex}"]`,
      );
      $container.empty();

      if (!fraisFixes || !Array.isArray(fraisFixes)) fraisFixes = [];

      fraisFixes.forEach((frais, index) => {
        const description = frais.exp_description_frais_fixe || "";
        const tarif = frais.exp_tarif_frais_fixe || "";

        const html = `
          <div class="frais-fixe-row" data-frais-index="${index}" style="border:1px solid #cbd5e0; padding:10px; margin-bottom:10px; border-radius:6px; background:#f0f9ff;">
            <div class="pc-form-grid" style="grid-template-columns: 2fr 1fr 1fr; gap:10px;">
              <div class="pc-form-group">
                <label>Description</label>
                <input type="text" class="pc-input frais-description" value="${this.escapeHtml(description)}" placeholder="Ex: Déplacement">
              </div>
              
              <div class="pc-form-group">
                <label>Montant (€)</label>
                <input type="number" class="pc-input frais-tarif" value="${tarif}" min="0" step="0.01" placeholder="0.00">
              </div>
              
              <div class="pc-form-group">
                <button type="button" class="pc-btn pc-btn-sm pc-btn-danger remove-frais-fixe">
                  <span>🗑️</span>
                </button>
              </div>
            </div>
          </div>
        `;
        $container.append(html);
      });
    }

    /**
     * Attache tous les gestionnaires d'événements pour les tarifs
     */
    bindTarifsEvents() {
      // Affichage conditionnel du champ personnalisé
      $(document)
        .off("change", ".tarif-type")
        .on("change", ".tarif-type", function () {
          const $row = $(this).closest(".pc-repeater-row");
          const showCustom = $(this).val() === "custom";
          $row.find(".tarif-custom-field").toggle(showCustom);
        });

      // Ajouter/supprimer type de tarif principal
      $(document)
        .off("click", ".add-tarif-row")
        .on("click", ".add-tarif-row", () => {
          this.addTarifType();
        });

      $(document)
        .off("click", ".remove-tarif-row")
        .on("click", ".remove-tarif-row", function () {
          if (
            confirm(
              "Êtes-vous sûr de vouloir supprimer ce type de tarif ? Toutes les lignes et options associées seront définitivement perdues.",
            )
          ) {
            $(this).closest(".pc-repeater-row").remove();
          }
        });

      // Ajouter ligne de tarif
      $(document)
        .off("click", ".add-ligne-tarif")
        .on("click", ".add-ligne-tarif", (e) => {
          const tarifIndex = $(e.currentTarget).data("tarif-index");
          this.addLigneTarif(tarifIndex);
        });

      // Supprimer ligne de tarif
      $(document)
        .off("click", ".remove-ligne-tarif")
        .on("click", ".remove-ligne-tarif", function () {
          $(this).closest(".ligne-tarif-row").remove();
        });

      // Ajouter option tarifaire
      $(document)
        .off("click", ".add-option-tarifaire")
        .on("click", ".add-option-tarifaire", (e) => {
          const tarifIndex = $(e.currentTarget).data("tarif-index");
          this.addOptionTarifaire(tarifIndex);
        });

      // Supprimer option tarifaire
      $(document)
        .off("click", ".remove-option-tarifaire")
        .on("click", ".remove-option-tarifaire", function () {
          $(this).closest(".option-tarifaire-row").remove();
        });

      // Ajouter frais fixe
      $(document)
        .off("click", ".add-frais-fixe")
        .on("click", ".add-frais-fixe", (e) => {
          const tarifIndex = $(e.currentTarget).data("tarif-index");
          this.addFraisFixe(tarifIndex);
        });

      // Supprimer frais fixe
      $(document)
        .off("click", ".remove-frais-fixe")
        .on("click", ".remove-frais-fixe", function () {
          $(this).closest(".frais-fixe-row").remove();
        });
    }

    /**
     * Ajoute une nouvelle ligne de tarif
     */
    addLigneTarif(tarifIndex) {
      const $container = $(
        `.tarifs-lignes-container[data-tarif-index="${tarifIndex}"]`,
      );
      const newIndex = Date.now();

      const html = `
        <div class="ligne-tarif-row" data-ligne-index="${newIndex}" style="border:1px solid #cbd5e0; padding:10px; margin-bottom:10px; border-radius:6px; background:#f8fafc;">
          <div class="pc-form-grid" style="grid-template-columns: 1fr 1fr 1fr 1fr; gap:10px;">
            <div class="pc-form-group">
              <label>Type</label>
              <select class="pc-select ligne-type">
                <option value="adulte">Adulte</option>
                <option value="enfant">Enfant</option>
                <option value="bebe">Bébé</option>
                <option value="personnalise" selected>Personnalisé</option>
              </select>
            </div>
            <div class="pc-form-group">
              <label>Tarif (€)</label>
              <input type="number" class="pc-input ligne-tarif-valeur" min="0" step="0.01" placeholder="0.00">
            </div>
            <div class="pc-form-group">
              <label>
                <input type="checkbox" class="ligne-enable-qty"> 
                Quantité?
              </label>
            </div>
            <div class="pc-form-group">
              <button type="button" class="pc-btn pc-btn-sm pc-btn-danger remove-ligne-tarif">
                <span>🗑️</span>
              </button>
            </div>
            <div class="pc-form-group pc-form-group--full">
              <label>Nom/Précision</label>
              <input type="text" class="pc-input ligne-nom-precision" placeholder="Ex: 3-12 ans, Privatisation, etc.">
            </div>
            <div class="pc-form-group pc-form-group--full">
              <label>Observation</label>
              <input type="text" class="pc-input ligne-observation" placeholder="Ex: jusqu'à 12 pers">
            </div>
          </div>
        </div>
      `;
      $container.append(html);
    }

    /**
     * Ajoute une nouvelle option tarifaire
     */
    addOptionTarifaire(tarifIndex) {
      const $container = $(
        `.options-tarifaires-container[data-tarif-index="${tarifIndex}"]`,
      );
      const newIndex = Date.now();

      const html = `
        <div class="option-tarifaire-row" data-option-index="${newIndex}" style="border:1px solid #cbd5e0; padding:10px; margin-bottom:10px; border-radius:6px; background:#fffbeb;">
          <div class="pc-form-grid" style="grid-template-columns: 2fr 1fr 1fr 1fr; gap:10px;">
            <div class="pc-form-group">
              <label>Description</label>
              <input type="text" class="pc-input option-description" placeholder="Ex: Skipper professionnel">
            </div>
            <div class="pc-form-group">
              <label>Tarif (€)</label>
              <input type="number" class="pc-input option-tarif" min="0" step="0.01" placeholder="0.00">
            </div>
            <div class="pc-form-group">
              <label>
                <input type="checkbox" class="option-enable-qty"> 
                Quantité?
              </label>
            </div>
            <div class="pc-form-group">
              <button type="button" class="pc-btn pc-btn-sm pc-btn-danger remove-option-tarifaire">
                <span>🗑️</span>
              </button>
            </div>
          </div>
        </div>
      `;
      $container.append(html);
    }

    /**
     * Ajoute un nouveau frais fixe
     */
    addFraisFixe(tarifIndex) {
      const $container = $(
        `.frais-fixes-container[data-tarif-index="${tarifIndex}"]`,
      );
      const newIndex = Date.now();

      const html = `
        <div class="frais-fixe-row" data-frais-index="${newIndex}" style="border:1px solid #cbd5e0; padding:10px; margin-bottom:10px; border-radius:6px; background:#f0f9ff;">
          <div class="pc-form-grid" style="grid-template-columns: 2fr 1fr 1fr; gap:10px;">
            <div class="pc-form-group">
              <label>Description</label>
              <input type="text" class="pc-input frais-description" placeholder="Ex: Déplacement">
            </div>
            <div class="pc-form-group">
              <label>Montant (€)</label>
              <input type="number" class="pc-input frais-tarif" min="0" step="0.01" placeholder="0.00">
            </div>
            <div class="pc-form-group">
              <button type="button" class="pc-btn pc-btn-sm pc-btn-danger remove-frais-fixe">
                <span>🗑️</span>
              </button>
            </div>
          </div>
        </div>
      `;
      $container.append(html);
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
