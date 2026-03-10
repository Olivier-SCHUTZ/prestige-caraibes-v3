/**
 * PC Reservation Core - Housing Manager JavaScript
 * Gestionnaire des logements avec interface Table + Modale
 *
 * @since 0.1.4
 */

(function ($) {
  "use strict";

  // === VARIABLES GLOBALES ===
  let currentPage = 1;
  let totalPages = 1;
  let currentHousingId = null;
  let currentFilters = {
    search: "",
    status: "",
    mode: "",
    type: "",
  };

  // === CLASSE PRINCIPALE ===
  class PCHousingManager {
    constructor() {
      this.rateManager = new PCRateManager(); // Initialisation de l'instance
      this.init();
    }

    init() {
      this.bindEvents();

      // Charger la liste si on est sur l'onglet housing
      if (window.location.hash === "#housing") {
        // this.loadHousingList(); // Désactivé : La V2 (Vue.js) gère maintenant l'affichage de la liste
      }
    }

    // === GESTION DES ÉVÉNEMENTS ===
    bindEvents() {
      // Événement personnalisé pour le switch d'onglet
      $(document).on("pc:tab-switched", (e, tabId) => {
        if (tabId === "housing") {
          this.loadHousingList();
        }
      });

      // Recherche et filtres
      $("#pc-housing-search").on(
        "input",
        this.debounce(() => {
          currentFilters.search = $("#pc-housing-search").val();
          currentPage = 1;
          this.loadHousingList();
        }, 500),
      );

      $(
        "#pc-housing-status-filter, #pc-housing-mode-filter, #pc-housing-type-filter",
      ).on("change", () => {
        currentFilters.status = $("#pc-housing-status-filter").val();
        currentFilters.mode = $("#pc-housing-mode-filter").val();
        currentFilters.type = $("#pc-housing-type-filter").val();
        currentPage = 1;
        this.loadHousingList();
      });

      // Boutons d'édition
      $(document).on("click", ".pc-btn-edit[data-housing-id]", (e) => {
        e.preventDefault();
        const housingId = $(e.currentTarget).data("housing-id");
        this.openHousingModal(housingId);
      });

      // NOUVEAU : Bouton "Nouveau Logement"
      $(document).on("click", "#pc-new-housing-btn", (e) => {
        e.preventDefault();
        this.openNewHousingModal();
      });

      // Pagination
      $(document).on("click", ".pc-pagination button[data-page]", (e) => {
        e.preventDefault();
        const page = parseInt($(e.currentTarget).data("page"));
        if (page !== currentPage && page >= 1 && page <= totalPages) {
          currentPage = page;
          this.loadHousingList();
        }
      });

      // Gestion modale
      $(document).on(
        "click",
        "#housing-modal .pc-modal-overlay, #housing-modal .pc-modal-close",
        () => {
          this.closeHousingModal();
        },
      );

      // Gestion des onglets dans la modale
      $(document).on("click", ".pc-tab-btn", (e) => {
        e.preventDefault();
        const tabId = $(e.currentTarget).data("tab");
        this.switchModalTab(tabId);
      });

      // Sauvegarde
      $("#pc-housing-save-btn").on("click", () => {
        this.saveHousingDetails();
      });

      // Suppression
      $("#pc-housing-delete-btn").on("click", () => {
        this.deleteHousingDetails();
      });

      // Empêcher la fermeture accidentelle
      $(document).on("keydown", (e) => {
        if (e.key === "Escape" && !$("#housing-modal").hasClass("hidden")) {
          this.closeHousingModal();
        }
      });

      // === GESTION DES MEDIA UPLOADERS ===
      $(document).on("click", ".pc-btn-select-image", (e) => {
        e.preventDefault();
        const target = $(e.currentTarget).data("target");
        this.openMediaUploader(target);
      });

      $(document).on("click", ".pc-btn-remove-image", (e) => {
        e.preventDefault();
        const target = $(e.currentTarget).data("target");
        this.removeImage(target);
      });

      // === GESTION DU REPEATER ===
      $(document).on("click", "#add-gallery-group", (e) => {
        e.preventDefault();
        this.addGalleryGroup();
      });

      $(document).on("click", ".pc-remove-group", (e) => {
        e.preventDefault();
        const groupId = $(e.currentTarget).data("group");
        this.removeGalleryGroup(groupId);
      });

      $(document).on("click", ".pc-select-gallery-images", (e) => {
        e.preventDefault();
        const groupId = $(e.currentTarget).data("group");
        this.openGalleryUploader(groupId);
      });

      $(document).on("change", ".pc-category-select", (e) => {
        const $select = $(e.currentTarget);
        const value = $select.val();
        const groupId = $select.data("group");
        const $customField = $(`#custom-title-${groupId}`);

        if (value === "autre") {
          $customField.show().focus();
        } else {
          $customField.hide().val("");
        }
      });
    }

    // === CHARGEMENT DE LA LISTE ===
    loadHousingList() {
      this.showLoading(true);

      const requestData = {
        action: "pc_housing_get_list",
        nonce: pcReservationVars.nonce,
        page: currentPage,
        per_page: 20,
        search: currentFilters.search,
        status_filter: currentFilters.status,
        mode_filter: currentFilters.mode,
        type_filter: currentFilters.type,
        orderby: "title",
        order: "ASC",
      };

      $.ajax({
        url: pcReservationVars.ajax_url,
        type: "POST",
        data: requestData,
        success: (response) => {
          this.showLoading(false);

          if (response.success) {
            this.renderHousingTable(response.data);
            this.renderPagination(response.data);
          } else {
            this.showError(
              "Erreur lors du chargement des logements: " +
                response.data.message,
            );
          }
        },
        error: (xhr, status, error) => {
          this.showLoading(false);
          console.error("Erreur AJAX:", error);
          this.showError(
            "Erreur de connexion lors du chargement des logements.",
          );
        },
      });
    }

    // === RENDU DU TABLEAU ===
    renderHousingTable(data) {
      const $table = $("#pc-housing-table");
      const $tbody = $("#pc-housing-table-body");
      const $empty = $("#pc-housing-empty");

      if (!data.items || data.items.length === 0) {
        $table.hide();
        $empty.show();
        return;
      }

      $empty.hide();
      $tbody.empty();

      data.items.forEach((item) => {
        const $row = this.createHousingRow(item);
        $tbody.append($row);
      });

      $table.show();
    }

    createHousingRow(item) {
      // Image avec fallback
      const imageUrl =
        item.image && item.image.thumbnail
          ? item.image.thumbnail
          : "data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjYwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0yMCAyMEg0MEMzNS41ODE3IDMzLjMzMzMgMjguNDE4MyAzMy4zMzMzIDI0IDMzLjMzMzNWNDBIMjBWMjBaIiBmaWxsPSIjOTRBM0I4Ii8+Cjwvc3ZnPgo=";

      // Prix formaté
      const price =
        item.base_price_from > 0
          ? `${parseFloat(item.base_price_from).toFixed(0)}€`
          : "Non défini";

      // Badges de statut
      const statusBadge = `<span class="pc-status-badge ${item.status_class}">${item.status_label}</span>`;
      const modeBadge = `<span class="pc-mode-badge ${item.mode_class}">${item.mode_label}</span>`;

      return $(`
                <tr data-housing-id="${item.id}">
                    <td class="pc-col-image">
                        <img src="${imageUrl}" alt="${item.title}" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjYwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0yMCAyMEg0MEMzNS41ODE3IDMzLjMzMzMgMjguNDE4MyAzMy4zMzMzIDI0IDMzLjMzMzNWNDBIMjBWMjBaIiBmaWxsPSIjOTRBM0I4Ii8+Cjwvc3ZnPgo='">
                    </td>
                    <td class="pc-col-name">
                        <div class="pc-housing-name">${this.escapeHtml(item.title)}</div>
                        <div class="pc-housing-type">${this.escapeHtml(item.type)}</div>
                    </td>
                    <td class="pc-col-capacity">
                        <span class="pc-housing-capacity">${item.capacite || 0} pers.</span>
                    </td>
                    <td class="pc-col-price">
                        <span class="pc-housing-price">${price}</span>
                    </td>
                    <td class="pc-col-location">
                        <span class="pc-housing-location">${this.escapeHtml(item.ville || "Non définie")}</span>
                    </td>
                    <td class="pc-col-status">
                        ${statusBadge}
                    </td>
                    <td class="pc-col-mode">
                        ${modeBadge}
                    </td>
                    <td class="pc-col-actions">
                        <button class="pc-btn-edit" data-housing-id="${item.id}">
                            <span>✏️</span>
                            Éditer
                        </button>
                    </td>
                </tr>
            `);
    }

    // === PAGINATION ===
    renderPagination(data) {
      const $pagination = $("#pc-housing-pagination");
      totalPages = data.pages || 1;
      currentPage = data.current_page || 1;

      if (totalPages <= 1) {
        $pagination.empty();
        return;
      }

      let html = "";

      // Bouton précédent
      const prevDisabled = currentPage <= 1 ? "disabled" : "";
      html += `<button ${prevDisabled} data-page="${currentPage - 1}">‹</button>`;

      // Pages
      for (let i = 1; i <= totalPages; i++) {
        if (
          i === 1 ||
          i === totalPages ||
          (i >= currentPage - 2 && i <= currentPage + 2)
        ) {
          const active = i === currentPage ? "active" : "";
          html += `<button class="${active}" data-page="${i}">${i}</button>`;
        } else if (i === currentPage - 3 || i === currentPage + 3) {
          html += "<span>...</span>";
        }
      }

      // Bouton suivant
      const nextDisabled = currentPage >= totalPages ? "disabled" : "";
      html += `<button ${nextDisabled} data-page="${currentPage + 1}">›</button>`;

      $pagination.html(html);
    }

    // === GESTION MODALE ===
    openHousingModal(housingId) {
      currentHousingId = housingId;

      // Afficher la modale avec les nouvelles classes
      $("#housing-modal").removeClass("hidden").addClass("active");

      // Afficher le loading
      $("#pc-housing-modal-loading").show();
      $("#pc-housing-modal-details").hide();

      // Charger les détails
      this.loadHousingDetails(housingId);
    }

    // NOUVEAU : Ouvrir la modale pour créer un nouveau logement
    openNewHousingModal() {
      currentHousingId = 0; // Signal de création

      // Changer le titre de la modale
      $("#pc-housing-modal-title").text("Nouveau logement");

      // Afficher la modale
      $("#housing-modal").removeClass("hidden").addClass("active");

      // Masquer le loading et afficher les détails directement
      $("#pc-housing-modal-loading").hide();
      $("#pc-housing-modal-details").show();

      // Réinitialiser complètement le formulaire
      this.resetModalFields();

      // Activer le sélecteur de type pour la création
      this.toggleHousingTypeSelector(true);

      // Aller à l'onglet général
      this.switchModalTab("general");
    }

    loadHousingDetails(housingId) {
      $.ajax({
        url: pcReservationVars.ajax_url,
        type: "POST",
        data: {
          action: "pc_housing_get_details",
          nonce: pcReservationVars.nonce,
          post_id: housingId,
        },
        success: (response) => {
          $("#pc-housing-modal-loading").hide();

          if (response.success) {
            this.populateModalFields(response.data.housing);
            $("#pc-housing-modal-title").text(response.data.housing.title);
            $("#pc-housing-modal-details").show();

            // Réinitialiser au premier onglet
            this.switchModalTab("general");
          } else {
            this.showError(
              "Erreur lors du chargement: " + response.data.message,
            );
            this.closeHousingModal();
          }
        },
        error: (xhr, status, error) => {
          console.error("Erreur AJAX:", error);
          this.showError("Erreur de connexion lors du chargement des détails.");
          this.closeHousingModal();
        },
      });
    }

    populateModalFields(housing) {
      // Remplir tous les champs avec les données du logement

      // NOUVEAU : Gérer le sélecteur de type pour l'édition
      $("#housing-type-selector").val(housing.type || "");
      this.toggleHousingTypeSelector(false); // Désactiver en mode édition

      // Onglet Général
      $("#housing-title").val(housing.title || "");
      $("#housing-identifiant-lodgify").val(housing.identifiant_lodgify || "");
      $("#housing-capacity").val(housing.capacite || "");
      $("#housing-superficie").val(housing.superficie || "");
      $("#housing-chambres").val(housing.nombre_de_chambres || "");
      $("#housing-sdb").val(housing.nombre_sdb || "");
      $("#housing-lits").val(housing.nombre_lits || "");
      $("#housing-content").val(housing.content || "");

      // Onglet Localisation
      $("#housing-geo-coords").val(housing.geo_coords || "");
      $("#housing-geo-radius").val(housing.geo_radius_m || "600");
      $("#housing-prox-airport").val(housing.prox_airport_km || "");
      $("#housing-prox-bus").val(housing.prox_bus_km || "");
      $("#housing-prox-port").val(housing.prox_port_km || "");
      $("#housing-prox-beach").val(housing.prox_beach_km || "");
      $("#housing-adresse").val(housing.adresse_rue || "");
      $("#housing-ville").val(housing.ville || "");
      $("#housing-code-postal").val(housing.code_postal || "");
      $("#housing-latitude").val(housing.latitude || "");
      $("#housing-longitude").val(housing.longitude || "");

      // Onglet Tarifs & Paiement
      $("#housing-prix-base").val(housing.base_price_from || "");
      // 🔧 FIX CHECKBOX: Utilisation de la nouvelle méthode pour les checkboxes boolean
      this.populateBooleanCheckbox("housing-promo", housing.pc_promo_log);
      $("#housing-min-nights").val(housing.min_nights || "");
      $("#housing-max-nights").val(housing.max_nights || "");
      $("#housing-unite-prix").val(housing.unite_de_prix || "par nuit");
      $("#housing-extra-guest-fee").val(housing.extra_guest_fee || "");
      $("#housing-extra-guest-from").val(housing.extra_guest_from || "");
      $("#housing-caution").val(housing.caution || "");
      $("#housing-frais-menage").val(housing.frais_menage || "");
      $("#housing-autres-frais").val(housing.autres_frais || "");
      $("#housing-autres-frais-type").val(housing.autres_frais_type || "");
      $("#housing-taux-tva").val(housing.taux_tva || "");
      $("#housing-taux-tva-menage").val(housing.taux_tva_menage || "");
      $("#housing-mode-reservation").val(
        housing.mode_reservation || "log_directe",
      );

      // Taxe de séjour (checkboxes multiples - même logique que les équipements)
      this.populateCheckboxes("taxe_sejour", housing.taxe_sejour);

      // === RÈGLES DE PAIEMENT ===
      $("#pc_pay_mode").val(housing.pc_pay_mode || "acompte_plus_solde");
      $("#pc_deposit_type").val(housing.pc_deposit_type || "pourcentage");
      $("#pc_deposit_value").val(housing.pc_deposit_value || "");
      $("#pc_balance_delay_days").val(housing.pc_balance_delay_days || "");
      $("#pc_caution_amount").val(housing.pc_caution_amount || "");
      $("#pc_caution_type").val(housing.pc_caution_type || "aucune");

      // Onglet Images & Galerie - 🔧 FIX HERO IMAGES: Utiliser la méthode populateImageUploaders
      this.populateImageUploaders(housing);

      let galleryUrls = "";
      if (housing.gallery_urls && typeof housing.gallery_urls === "string") {
        galleryUrls = housing.gallery_urls
          .split("\n")
          .filter((url) => url.trim())
          .join("\n");
      } else if (Array.isArray(housing.gallery_urls)) {
        galleryUrls = housing.gallery_urls.join("\n");
      }
      $("#housing-gallery-urls").val(galleryUrls);

      let videoUrls = "";
      if (housing.video_urls && typeof housing.video_urls === "string") {
        videoUrls = housing.video_urls
          .split("\n")
          .filter((url) => url.trim())
          .join("\n");
      } else if (Array.isArray(housing.video_urls)) {
        videoUrls = housing.video_urls.join("\n");
      }
      $("#housing-video-urls").val(videoUrls);

      let seoGalleryUrls = "";
      if (
        housing.seo_gallery_urls &&
        typeof housing.seo_gallery_urls === "string"
      ) {
        seoGalleryUrls = housing.seo_gallery_urls
          .split("\n")
          .filter((url) => url.trim())
          .join("\n");
      } else if (Array.isArray(housing.seo_gallery_urls)) {
        seoGalleryUrls = housing.seo_gallery_urls.join("\n");
      }
      $("#housing-seo-gallery-urls").val(seoGalleryUrls);

      // Onglet Équipements
      this.populateCheckboxes("eq_piscine_spa", housing.eq_piscine_spa);
      this.populateCheckboxes("eq_parking", housing.eq_parking_installations);
      this.populateCheckboxes("eq_politiques", housing.eq_politiques);
      this.populateCheckboxes("eq_divertissements", housing.eq_divertissements);
      this.populateCheckboxes("eq_cuisine", housing.eq_cuisine_salle_a_manger);
      this.populateCheckboxes(
        "eq_caracteristiques_emplacement",
        housing.eq_caracteristiques_emplacement,
      );
      this.populateCheckboxes(
        "eq_salle_de_bain_blanchisserie",
        housing.eq_salle_de_bain_blanchisserie,
      );
      this.populateCheckboxes("eq_clim", housing.eq_chauffage_climatisation);
      this.populateCheckboxes("eq_internet", housing.eq_internet_bureautique);
      this.populateCheckboxes("eq_securite_maison", housing.eq_securite_maison);

      // Onglet Contenu & SEO
      $("#housing-h1-custom").val(housing.contenu_seo_titre_h1 || "");
      $("#housing-seo-long-html").val(housing.seo_long_html || "");
      $("#housing-highlights-custom").val(housing.highlights_custom || "");
      $("#housing-experiences").val(
        housing.logement_experiences_recommandees
          ? housing.logement_experiences_recommandees.join(",")
          : "",
      );

      // Highlights (checkboxes)
      this.populateCheckboxes("highlights", housing.highlights);

      // Onglet Réservation & Hôte
      $("#housing-politique-annulation").val(
        housing.politique_dannulation || "",
      );
      $("#housing-regles-maison").val(housing.regles_maison || "");
      $("#housing-checkin-time").val(housing.horaire_arrivee || "");
      $("#housing-checkout-time").val(housing.horaire_depart || "");
      $("#housing-lodgify-widget").val(housing.lodgify_widget_embed || "");
      $("#housing-hote-nom").val(housing.hote_nom || "");
      $("#housing-hote-description").val(housing.hote_description || "");

      // Onglet Configuration
      $("#housing-status").val(housing.status || "publish");
      $("#housing-ical-url").val(housing.ical_url || "");
      // 🔧 FIX CHECKBOX: Utilisation de la nouvelle méthode pour les checkboxes boolean
      this.populateBooleanCheckbox(
        "housing-exclude-sitemap",
        housing.log_exclude_sitemap,
      );
      this.populateBooleanCheckbox("housing-http-410", housing.log_http_410);
      $("#housing-meta-titre").val(housing.meta_titre || "");
      $("#housing-meta-description").val(housing.meta_description || "");
      $("#housing-url-canonique").val(housing.url_canonique || "");
      $("#housing-meta-robots").val(housing.log_meta_robots || "index,follow");
      $("#housing-google-accommodation-type").val(
        housing.google_vr_accommodation_type || "EntirePlace",
      );

      // Section Infos Contrat (Onglet Configuration)
      $("#housing-proprietaire-identite").val(
        housing.log_proprietaire_identite || "",
      );
      $("#housing-personne-logement").val(housing.personne_logement || "");
      $("#housing-proprietaire-adresse").val(
        housing.proprietaire_adresse || "",
      );
      $("#housing-description-contrat").val(housing.description_contrat || "");
      $("#housing-equipements-contrat").val(housing.equipements_contrat || "");

      // Switchs
      this.populateBooleanCheckbox("housing-has-piscine", housing.has_piscine);
      this.populateBooleanCheckbox("housing-has-jacuzzi", housing.has_jacuzzi);
      this.populateBooleanCheckbox(
        "housing-has-guide",
        housing.has_guide_numerique,
      );

      // Google amenities (checkboxes)
      this.populateCheckboxes("google_amenities", housing.google_vr_amenities);
      this.populateGalleryRepeater(housing.groupes_images);

      // --- INITIALISATION RATE MANAGER ---
      // On passe l'ID du container, les données brutes (saisons/promos stockées en JSON ou ACF) et le prix de base
      this.rateManager.init(
        "pc-rates-calendar",
        {
          seasons: housing.seasons_data, // Le backend devra renvoyer ça
          promos: housing.promos_data,
        },
        housing.base_price_from,
      );
    }

    // 🔧 FIX CRITIQUE: Fonction utilitaire pour peupler les checkboxes (multi-sélection)
    populateCheckboxes(name, values) {
      // Décocher toutes les checkboxes d'abord
      $(`input[name="${name}[]"]`).prop("checked", false);

      if (values) {
        let valuesArray = [];

        // Conversion selon le type de données reçues
        if (Array.isArray(values)) {
          // 🔧 FIX TAXE SEJOUR: Extraire la propriété 'value' des objets ACF
          valuesArray = values.map((item) => {
            if (typeof item === "object" && item.value) {
              return item.value; // ACF retourne {value: "1_etoile", label: "Logement classé 1 étoile"}
            }
            return item; // Garder tel quel si c'est déjà une string
          });
        } else if (typeof values === "string") {
          // Si c'est une chaîne délimitée par des virgules ou des sauts de ligne
          valuesArray = values
            .split(/[,\n]/)
            .map((v) => v.trim())
            .filter((v) => v);
        } else {
          console.warn(
            "Format de données non reconnu pour les checkboxes:",
            name,
            values,
          );
          return;
        }

        // Cocher les bonnes checkboxes
        valuesArray.forEach((value) => {
          $(`input[name="${name}[]"][value="${value}"]`).prop("checked", true);
        });
      }
    }

    // 🔧 FIX CRITIQUE: Fonction utilitaire pour peupler les checkboxes boolean (true/false)
    populateBooleanCheckbox(id, value) {
      const $checkbox = $(`#${id}`);
      if ($checkbox.length) {
        // Conversion stricte : '1', true, 'true' = checked
        const isChecked =
          value === "1" || value === true || value === "true" || value === 1;
        $checkbox.prop("checked", isChecked);
      }
    }

    // 🔧 FIX CRITIQUE: Fonction utilitaire pour collecter les valeurs des checkboxes
    collectCheckboxes(name) {
      const values = [];
      $(`input[name="${name}[]"]:checked`).each(function () {
        values.push($(this).val());
      });
      return values;
    }

    // 🔧 FIX CRITIQUE: Fonction utilitaire pour collecter les valeurs des checkboxes boolean
    collectBooleanCheckbox(id) {
      return $(`#${id}`).is(":checked") ? "1" : "0";
    }

    switchModalTab(tabId) {
      // Mettre à jour la navigation
      $(".pc-tab-btn").removeClass("active");
      $(`.pc-tab-btn[data-tab="${tabId}"]`).addClass("active");

      // Mettre à jour le contenu - utiliser les nouveaux IDs et classes
      $(".pc-tab-content").hide();
      $(`#tab-${tabId}`).show();

      // HACK: FullCalendar a besoin d'être rafraîchi s'il était caché
      if (tabId === "rates" && this.rateManager && this.rateManager.calendar) {
        setTimeout(() => {
          this.rateManager.calendar.render();
        }, 50);
      }
    }

    closeHousingModal() {
      $("#housing-modal").addClass("hidden").removeClass("active");
      currentHousingId = null;
    }

    // NOUVEAU : Réinitialiser complètement le formulaire pour la création
    resetModalFields() {
      console.log("🔧 Réinitialisation complète du formulaire");

      // Reset tous les inputs text
      $(
        "#housing-modal input[type='text'], #housing-modal input[type='number'], #housing-modal input[type='email'], #housing-modal input[type='url'], #housing-modal textarea",
      ).val("");

      // Reset tous les selects aux valeurs par défaut
      $("#housing-status").val("draft"); // Nouveau logement en brouillon par défaut
      $("#housing-unite-prix").val("par nuit");
      $("#housing-mode-reservation").val("log_directe");
      $("#housing-google-accommodation-type").val("EntirePlace");
      $("#housing-meta-robots").val("index,follow");
      $("#pc_pay_mode").val("acompte_plus_solde");
      $("#pc_deposit_type").val("pourcentage");
      $("#pc_caution_type").val("aucune");

      // Reset valeurs par défaut spéciales
      $("#housing-geo-radius").val("600");

      // Reset toutes les checkboxes
      $("#housing-modal input[type='checkbox']").prop("checked", false);

      // Reset les images
      this.removeImage("hero-desktop");
      this.removeImage("hero-mobile");

      // Reset le repeater galerie
      $("#gallery-categories-container").empty();
      $("#gallery-categories-empty").show();

      // Reset le sélecteur de type (vide par défaut pour forcer le choix)
      $("#housing-type-selector").val("");

      console.log("✅ Formulaire réinitialisé");
    }

    // NOUVEAU : Gérer l'état du sélecteur de type de logement
    toggleHousingTypeSelector(isCreation) {
      const $selector = $("#housing-type-selector");
      const $helpCreation = $("#housing-type-help-creation");
      const $helpEdit = $("#housing-type-help-edit");

      if (isCreation) {
        // Mode création : sélecteur actif
        $selector.prop("disabled", false);
        $helpCreation.show();
        $helpEdit.hide();
      } else {
        // Mode édition : sélecteur verrouillé
        $selector.prop("disabled", true);
        $helpCreation.hide();
        $helpEdit.show();
      }
    }

    // === SUPPRESSION ===
    deleteHousingDetails() {
      if (currentHousingId === null || currentHousingId === 0) {
        this.showError("Impossible de supprimer un logement non enregistré.");
        return;
      }

      // Demander confirmation
      if (
        !confirm(
          "Êtes-vous sûr de vouloir supprimer ce logement ? Cette action est irréversible.",
        )
      ) {
        return;
      }

      const $btn = $("#pc-housing-delete-btn");
      $btn.addClass("loading").prop("disabled", true);

      $.ajax({
        url: pcReservationVars.ajax_url,
        type: "POST",
        data: {
          action: "pc_housing_delete",
          nonce: pcReservationVars.nonce,
          post_id: currentHousingId,
        },
        success: (response) => {
          $btn.removeClass("loading").prop("disabled", false);

          if (response.success) {
            this.showSuccess("Logement supprimé avec succès!");
            this.closeHousingModal();
            this.loadHousingList(); // Recharger la liste
          } else {
            this.showError(
              "Erreur lors de la suppression: " + response.data.message,
            );
          }
        },
        error: (xhr, status, error) => {
          $btn.removeClass("loading").prop("disabled", false);
          console.error("Erreur AJAX:", error);
          this.showError("Erreur de connexion lors de la suppression.");
        },
      });
    }

    // === SAUVEGARDE ===
    saveHousingDetails() {
      // NOUVEAU : Validation pour la création
      if (currentHousingId === 0) {
        // Mode création : validation du type obligatoire
        const selectedType = $("#housing-type-selector").val();
        if (!selectedType) {
          this.showError(
            "Veuillez sélectionner le type de logement (Villa ou Appartement).",
          );
          return;
        }

        const title = $("#housing-title").val().trim();
        if (!title) {
          this.showError("Le nom du logement est obligatoire.");
          return;
        }
      }

      if (currentHousingId === null) {
        this.showError("Aucun logement sélectionné.");
        return;
      }

      const $btn = $("#pc-housing-save-btn");
      $btn.addClass("loading").prop("disabled", true);

      // 🔧 FIX CRITIQUE: Collecte des données du Repeater "Groupes d'images"
      const groupesImages = this.collectRepeaterData();

      // 👇 AJOUTE LE DEBUG ICI (AVANT le const formData) 👇
      console.log("DEBUG PAIEMENT - Valeurs du formulaire HTML :", {
        mode: $("#pc_pay_mode").val(),
        acompte: $("#pc_deposit_type").val(),
        valeur: $("#pc_deposit_value").val(),
        solde: $("#pc_balance_delay_days").val(),
        caution: $("#pc_caution_amount").val(),
        type_caution: $("#pc_caution_type").val(),
      });
      // 👆 FIN DU DEBUG 👆

      console.log("🔍 DEBUG - Groupes d'images collectés:", groupesImages);

      // Collecter toutes les données du formulaire
      const formData = {
        action: "pc_housing_save",
        nonce: pcReservationVars.nonce,
        post_id: currentHousingId,

        // Données de base
        title: $("#housing-title").val(),
        status: $("#housing-status").val(),
        content: $("#housing-content").val(),

        // NOUVEAU : Type de logement (pour la création)
        post_type:
          currentHousingId === 0
            ? $("#housing-type-selector").val()
            : undefined,

        // Onglet Général
        acf_identifiant_lodgify: $("#housing-identifiant-lodgify").val(),
        acf_capacite: $("#housing-capacity").val(),
        acf_superficie: $("#housing-superficie").val(),
        acf_nombre_de_chambres: $("#housing-chambres").val(),
        acf_nombre_sdb: $("#housing-sdb").val(),
        acf_nombre_lits: $("#housing-lits").val(),

        // Onglet Localisation
        acf_geo_coords: $("#housing-geo-coords").val(),
        acf_geo_radius_m: $("#housing-geo-radius").val(),
        acf_prox_airport_km: $("#housing-prox-airport").val(),
        acf_prox_bus_km: $("#housing-prox-bus").val(),
        acf_prox_port_km: $("#housing-prox-port").val(),
        acf_prox_beach_km: $("#housing-prox-beach").val(),
        acf_adresse_rue: $("#housing-adresse").val(),
        acf_ville: $("#housing-ville").val(),
        acf_code_postal: $("#housing-code-postal").val(),
        acf_latitude: $("#housing-latitude").val(),
        acf_longitude: $("#housing-longitude").val(),

        // Onglet Tarifs & Paiement
        acf_base_price_from: $("#housing-prix-base").val(),
        acf_pc_promo_log: this.collectBooleanCheckbox("housing-promo"),
        acf_min_nights: $("#housing-min-nights").val(),
        acf_max_nights: $("#housing-max-nights").val(),
        acf_unite_de_prix: $("#housing-unite-prix").val(),
        acf_extra_guest_fee: $("#housing-extra-guest-fee").val(),
        acf_extra_guest_from: $("#housing-extra-guest-from").val(),
        acf_caution: $("#housing-caution").val(),
        acf_frais_menage: $("#housing-frais-menage").val(),
        acf_autres_frais: $("#housing-autres-frais").val(),
        acf_autres_frais_type: $("#housing-autres-frais-type").val(),
        acf_taux_tva: $("#housing-taux-tva").val(),
        acf_taux_tva_menage: $("#housing-taux-tva-menage").val(),
        acf_mode_reservation: $("#housing-mode-reservation").val(),
        acf_taxe_sejour: this.collectCheckboxes("taxe_sejour"),

        // === RÈGLES DE PAIEMENT (Correction IDs) ===
        acf_pc_pay_mode: $("#pc_pay_mode").val(),
        acf_pc_deposit_type: $("#pc_deposit_type").val(),
        acf_pc_deposit_value: $("#pc_deposit_value").val(),
        acf_pc_balance_delay_days: $("#pc_balance_delay_days").val(),
        acf_pc_caution_amount: $("#pc_caution_amount").val(),
        acf_pc_caution_type: $("#pc_caution_type").val(),

        // Onglet Images & Galerie - On envoie la valeur brute (ID ou URL), le PHP gérera la conversion
        acf_hero_desktop_url: $("#housing-hero-desktop").val(),
        acf_hero_mobile_url: $("#housing-hero-mobile").val(),
        acf_gallery_urls: $("#housing-gallery-urls").val(),
        acf_video_urls: $("#housing-video-urls").val(),
        acf_seo_gallery_urls: $("#housing-seo-gallery-urls").val(),

        // 🔧 FIX CRITIQUE: Ajouter les données du Repeater
        acf_groupes_images: groupesImages,

        // Onglet Équipements
        acf_eq_piscine_spa: this.collectCheckboxes("eq_piscine_spa"),
        acf_eq_parking_installations: this.collectCheckboxes("eq_parking"),
        acf_eq_politiques: this.collectCheckboxes("eq_politiques"),
        acf_eq_divertissements: this.collectCheckboxes("eq_divertissements"),
        acf_eq_cuisine_salle_a_manger: this.collectCheckboxes("eq_cuisine"),
        acf_eq_caracteristiques_emplacement: this.collectCheckboxes(
          "eq_caracteristiques_emplacement",
        ),
        acf_eq_salle_de_bain_blanchisserie: this.collectCheckboxes(
          "eq_salle_de_bain_blanchisserie",
        ),
        acf_eq_chauffage_climatisation: this.collectCheckboxes("eq_clim"),
        acf_eq_internet_bureautique: this.collectCheckboxes("eq_internet"),
        acf_eq_securite_maison: this.collectCheckboxes("eq_securite_maison"),

        // Onglet Contenu & SEO
        acf_contenu_seo_titre_h1: $("#housing-h1-custom").val(),
        acf_seo_long_html: $("#housing-seo-long-html").val(),
        acf_highlights: this.collectCheckboxes("highlights"),
        acf_highlights_custom: $("#housing-highlights-custom").val(),
        acf_logement_experiences_recommandees: $("#housing-experiences")
          .val()
          .split(",")
          .filter((id) => id.trim()),

        // Onglet Réservation & Hôte
        acf_politique_dannulation: $("#housing-politique-annulation").val(),
        acf_regles_maison: $("#housing-regles-maison").val(),
        acf_horaire_arrivee: $("#housing-checkin-time").val(),
        acf_horaire_depart: $("#housing-checkout-time").val(),
        acf_lodgify_widget_embed: $("#housing-lodgify-widget").val(),
        acf_hote_nom: $("#housing-hote-nom").val(),
        acf_hote_description: $("#housing-hote-description").val(),

        // Onglet Configuration
        acf_ical_url: $("#housing-ical-url").val(),
        acf_log_exclude_sitemap: this.collectBooleanCheckbox(
          "housing-exclude-sitemap",
        ),
        acf_log_http_410: this.collectBooleanCheckbox("housing-http-410"),
        acf_meta_titre: $("#housing-meta-titre").val(),
        acf_meta_description: $("#housing-meta-description").val(),
        acf_url_canonique: $("#housing-url-canonique").val(),
        acf_log_meta_robots: $("#housing-meta-robots").val(),
        acf_google_vr_accommodation_type: $(
          "#housing-google-accommodation-type",
        ).val(),
        acf_google_vr_amenities: this.collectCheckboxes("google_amenities"),

        // Section Infos Contrat
        acf_log_proprietaire_identite: $(
          "#housing-proprietaire-identite",
        ).val(),
        acf_personne_logement: $("#housing-personne-logement").val(),
        acf_proprietaire_adresse: $("#housing-proprietaire-adresse").val(),
        acf_description_contrat: $("#housing-description-contrat").val(),
        acf_equipements_contrat: $("#housing-equipements-contrat").val(),

        acf_has_piscine: this.collectBooleanCheckbox("housing-has-piscine"),
        acf_has_jacuzzi: this.collectBooleanCheckbox("housing-has-jacuzzi"),
        acf_has_guide_numerique:
          this.collectBooleanCheckbox("housing-has-guide"),

        // --- DONNÉES RATE MANAGER ---
        rate_manager_data: JSON.stringify(this.rateManager.getData()), // On envoie le JSON complet
      };

      $.ajax({
        url: pcReservationVars.ajax_url,
        type: "POST",
        data: formData,
        success: (response) => {
          $btn.removeClass("loading").prop("disabled", false);

          if (response.success) {
            this.showSuccess("Logement mis à jour avec succès!");
            this.closeHousingModal();
            this.loadHousingList(); // Recharger la liste
          } else {
            this.showError(
              "Erreur lors de la sauvegarde: " + response.data.message,
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

    // === UTILITAIRES ===
    showLoading(show) {
      if (show) {
        $("#pc-housing-loading").show();
        $("#pc-housing-table").hide();
        $("#pc-housing-empty").hide();
      } else {
        $("#pc-housing-loading").hide();
      }
    }

    showSuccess(message) {
      // Notification de succès simple
      this.showNotification(message, "success");
    }

    showError(message) {
      // Notification d'erreur simple
      this.showNotification(message, "error");
    }

    showNotification(message, type) {
      // 🔧 FIX: Utilisation des classes CSS pour les notifications
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

      // Disparition automatique (plus long pour les erreurs)
      const delay = type === "error" ? 8000 : 4000;
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

      // 🔧 FIX: Permettre de fermer manuellement en cliquant
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

    // === MEDIA UPLOADERS ===
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

    setImage(target, attachment) {
      const $preview = $(`#preview-${target}`);
      const $input = $(`#housing-${target}`);
      const $removeBtn = $(`.pc-btn-remove-image[data-target="${target}"]`);

      // Mettre à jour l'aperçu
      $preview.html(
        `<img src="${attachment.url}" alt="${attachment.alt || "Image"}" style="width: 100%; height: 120px; object-fit: cover; border-radius: 8px;">`,
      );

      // 🔧 FIX CRITIQUE: Stocker l'ID de l'attachment, pas l'URL
      $input.val(attachment.id);
      $input.data("image-url", attachment.url); // Garder l'URL pour l'affichage

      // Afficher le bouton de suppression
      $removeBtn.show();
    }

    removeImage(target) {
      const $preview = $(`#preview-${target}`);
      const $input = $(`#housing-${target}`);
      const $removeBtn = $(`.pc-btn-remove-image[data-target="${target}"]`);

      // Emoji par défaut selon le type
      const defaultText =
        target === "hero-mobile"
          ? "📱 Aucune image sélectionnée"
          : "📷 Aucune image sélectionnée";

      // Réinitialiser l'aperçu
      $preview.html(`<div class="pc-image-placeholder">${defaultText}</div>`);

      // Vider l'input caché
      $input.val("");

      // Masquer le bouton de suppression
      $removeBtn.hide();
    }

    // 🔧 NOUVELLE MÉTHODE : Reconstruit le Repeater à partir des données BDD
    populateGalleryRepeater(groupes) {
      const $container = $("#gallery-categories-container");
      const $emptyMsg = $("#gallery-categories-empty");

      // 1. Nettoyage
      $container.empty();

      if (!groupes || !Array.isArray(groupes) || groupes.length === 0) {
        $emptyMsg.show();
        return;
      }

      $emptyMsg.hide();

      // 2. Reconstruction
      groupes.forEach((groupe) => {
        // Générer un ID unique pour le DOM
        const groupId = Date.now() + Math.floor(Math.random() * 1000);

        // Créer le HTML vide
        const groupHtml = this.createGalleryGroupHTML(groupId);
        $container.append(groupHtml);

        // 3. Remplissage des valeurs (Select & Titre)
        const $select = $(`#category-${groupId}`);
        const $customTitle = $(`#custom-title-${groupId}`);

        // Gérer le cas où 'categorie' est un objet (si ACF return object) ou string
        let catValue =
          typeof groupe.categorie === "object" && groupe.categorie
            ? groupe.categorie.value
            : groupe.categorie;

        $select.val(catValue);

        // Afficher/Masquer le champ titre perso
        if (catValue === "autre") {
          $customTitle.val(groupe.categorie_personnalisee || "").show();
        } else {
          $customTitle.hide();
        }

        // 4. Remplissage des Images (Galerie)
        // On passe les IDs (ou objets) à une fonction d'affichage
        if (groupe.images_du_groupe) {
          this.renderSavedGalleryImages(groupId, groupe.images_du_groupe);
        }
      });

      // Renuméroter les titres (Groupe #1, #2...)
      this.renumberGalleryGroups();
    }

    // 🔧 NOUVELLE MÉTHODE : Affiche les miniatures des images existantes
    renderSavedGalleryImages(groupId, imagesData) {
      const $preview = $(`#gallery-preview-${groupId}`);
      const $input = $(`#gallery-images-${groupId}`);

      let imageIds = [];
      let html = '<div class="pc-gallery-grid">';

      // Normalisation des données (ACF peut renvoyer des Objets ou des IDs)
      const imagesArray = Array.isArray(imagesData) ? imagesData : [];

      if (imagesArray.length === 0) return;

      imagesArray.forEach((img) => {
        let imgId, imgUrl;

        if (typeof img === "object" && img !== null) {
          // Cas où ACF renvoie l'objet complet
          imgId = img.ID || img.id;
          imgUrl = img.sizes?.thumbnail?.url || img.url; // Fallback URL
        } else {
          // Cas où ACF renvoie juste l'ID (int ou string)
          imgId = parseInt(img);
          // On ne connaît pas l'URL tout de suite, on met un placeholder ou on tente de la charger
          // Pour l'instant, on met une icône générique si on n'a pas l'URL
          imgUrl = "";
        }

        if (imgId) {
          imageIds.push(imgId);

          // Si on a l'URL, on l'affiche, sinon on essaie de la charger via WP Media
          if (imgUrl) {
            html += `
                  <div class="pc-gallery-thumb" id="thumb-${imgId}">
                    <img src="${imgUrl}" alt="Image ${imgId}" />
                  </div>`;
          } else {
            // Placeholder en attendant le chargement AJAX (optionnel)
            html += `
                  <div class="pc-gallery-thumb loading-thumb" data-id="${imgId}">
                    <div class="pc-spinner-small"></div>
                  </div>`;

            // Tentative de chargement asynchrone de l'URL
            this.fetchImageUrl(imgId, (fetchedUrl) => {
              const $thumb = $preview.find(
                `.loading-thumb[data-id="${imgId}"]`,
              );
              if (fetchedUrl) {
                $thumb
                  .html(`<img src="${fetchedUrl}" alt="Image" />`)
                  .removeClass("loading-thumb");
              } else {
                $thumb.html(`<span class="error">❌</span>`);
              }
            });
          }
        }
      });

      html += "</div>";
      html += `<p class="pc-gallery-count">${imageIds.length} image(s)</p>`;

      $preview.html(html);

      // IMPORTANT: Remplir l'input caché avec les IDs pour que la sauvegarde fonctionne si on ne touche à rien
      $input.val(imageIds.join(","));
    }

    // Petit utilitaire pour récupérer l'URL via WP Media JS si dispo
    fetchImageUrl(id, callback) {
      if (typeof wp !== "undefined" && wp.media && wp.media.attachment) {
        const attachment = wp.media.attachment(id);
        attachment.fetch().then(() => {
          const data = attachment.toJSON();
          callback(data.sizes?.thumbnail?.url || data.url);
        });
      } else {
        callback(null);
      }
    }

    // === REPEATER GALLERY ===
    addGalleryGroup() {
      const groupId = Date.now(); // ID unique basé sur timestamp
      const groupHtml = this.createGalleryGroupHTML(groupId);

      $("#gallery-categories-container").append(groupHtml);
      $("#gallery-categories-empty").hide();

      // Animation d'ajout
      const $newGroup = $(`#gallery-group-${groupId}`);
      $newGroup.hide().slideDown(300);
    }

    createGalleryGroupHTML(groupId) {
      return `
        <div class="pc-repeater-item" id="gallery-group-${groupId}">
          <div class="pc-repeater-item-header">
            <h4 class="pc-repeater-item-title">Groupe d'images #${$("#gallery-categories-container .pc-repeater-item").length + 1}</h4>
            <button type="button" class="pc-btn pc-btn-danger pc-remove-group" data-group="${groupId}">
              <span>🗑️</span> Supprimer
            </button>
          </div>
          
          <div class="pc-repeater-item-content">
            <div class="pc-form-grid">
              <div class="pc-form-group">
                <label for="category-${groupId}">Catégorie</label>
                <select id="category-${groupId}" class="pc-select pc-category-select" data-group="${groupId}">
                  <option value="salon">Salon</option>
                  <option value="cuisine">Cuisine</option>
                  <option value="chambre_1">Chambre 1</option>
                  <option value="salle_de_bain">SDB</option>
                  <option value="terrasse">Terrasse</option>
                  <option value="piscine">Piscine</option>
                  <option value="exterieur">Extérieur</option>
                  <option value="vue">Vue</option>
                  <option value="autre">Autre</option>
                </select>
              </div>

              <div class="pc-form-group">
                <label for="custom-title-${groupId}">Titre personnalisé</label>
                <input type="text" id="custom-title-${groupId}" class="pc-input" placeholder="Titre si 'Autre' sélectionné" style="display: none;">
              </div>

              <div class="pc-form-group pc-form-group--full">
                <label>Images du groupe</label>
                <div class="pc-gallery-uploader">
                  <div class="pc-gallery-preview" id="gallery-preview-${groupId}">
                    <div class="pc-gallery-placeholder">
                      <div class="pc-gallery-placeholder-icon">🖼️</div>
                      <p>Aucune image sélectionnée</p>
                    </div>
                  </div>
                  <button type="button" class="pc-btn pc-btn-select pc-select-gallery-images" data-group="${groupId}">
                    <span>📷</span> Sélectionner des images
                  </button>
                  <input type="hidden" id="gallery-images-${groupId}" name="gallery_images_${groupId}" value="">
                </div>
              </div>
            </div>
          </div>
        </div>
      `;
    }

    removeGalleryGroup(groupId) {
      const $group = $(`#gallery-group-${groupId}`);

      $group.slideUp(300, () => {
        $group.remove();

        // Afficher le message vide si plus de groupes
        if ($("#gallery-categories-container .pc-repeater-item").length === 0) {
          $("#gallery-categories-empty").show();
        }

        // Renuméroter les titres
        this.renumberGalleryGroups();
      });
    }

    renumberGalleryGroups() {
      $("#gallery-categories-container .pc-repeater-item").each(
        (index, element) => {
          $(element)
            .find(".pc-repeater-item-title")
            .text(`Groupe d'images #${index + 1}`);
        },
      );
    }

    openGalleryUploader(groupId) {
      if (typeof wp === "undefined" || !wp.media) {
        this.showError("WordPress Media Library n'est pas disponible.");
        return;
      }

      const galleryUploader = wp.media({
        title: "Sélectionner des images",
        button: {
          text: "Utiliser ces images",
        },
        multiple: true,
        library: {
          type: "image",
        },
      });

      galleryUploader.on("select", () => {
        const attachments = galleryUploader.state().get("selection").toJSON();
        this.setGalleryImages(groupId, attachments);
      });

      galleryUploader.open();
    }

    setGalleryImages(groupId, attachments) {
      const $preview = $(`#gallery-preview-${groupId}`);
      const $input = $(`#gallery-images-${groupId}`);

      if (attachments.length === 0) return;

      // Créer l'aperçu des images
      let previewHTML = '<div class="pc-gallery-grid">';
      const imageIds = []; // 🔧 FIX: Stocker les IDs au lieu des URLs

      attachments.forEach((attachment) => {
        previewHTML += `
          <div class="pc-gallery-thumb">
            <img src="${attachment.sizes?.thumbnail?.url || attachment.url}" alt="${attachment.alt || "Image"}" />
          </div>
        `;
        imageIds.push(attachment.id); // 🔧 FIX: Utiliser l'ID de l'attachment
      });

      previewHTML += "</div>";
      previewHTML += `<p class="pc-gallery-count">${attachments.length} image(s) sélectionnée(s)</p>`;

      $preview.html(previewHTML);

      // 🔧 FIX CRITIQUE: Stocker les IDs dans l'input caché
      $input.val(imageIds.join(","));
    }

    // 🔧 FIX CRITIQUE: Méthode pour peupler les images existantes (gère ID et URL)
    populateImageUploaders(housing) {
      // Hero Desktop
      if (housing.hero_desktop_url) {
        this.setImageFromData("hero-desktop", housing.hero_desktop_url);
      }

      // Hero Mobile
      if (housing.hero_mobile_url) {
        this.setImageFromData("hero-mobile", housing.hero_mobile_url);
      }
    }

    // 🔧 FIX CRITIQUE: Méthode intelligente qui gère ID et URL
    setImageFromData(target, value) {
      const $preview = $(`#preview-${target}`);
      const $input = $(`#housing-${target}`);
      const $removeBtn = $(`.pc-btn-remove-image[data-target="${target}"]`);

      if (!value) {
        return;
      }

      let imageUrl = "";
      let imageId = "";

      // 🔧 DÉTECTION : ID numérique ou URL?
      if (/^\d+$/.test(value.toString().trim())) {
        // C'est un ID d'attachment
        imageId = parseInt(value);

        // Construire l'URL depuis l'ID (approximation pour l'affichage)
        // Note: L'URL exacte sera récupérée côté serveur, ici on fait une approximation
        imageUrl = value; // On garde l'ID comme valeur temporaire

        console.log(`🔧 Image trouvée (ID): ${target} -> ID #${imageId}`);

        // 🔧 AJAX CALL: Récupérer l'URL réelle depuis l'ID pour l'affichage
        if (typeof wp !== "undefined" && wp.media && wp.media.attachment) {
          // Si WordPress Media API est disponible
          const attachment = wp.media.attachment(imageId);
          attachment
            .fetch()
            .then(() => {
              const attachmentData = attachment.toJSON();
              if (attachmentData && attachmentData.url) {
                console.log(
                  `✅ URL récupérée pour ID #${imageId}: ${attachmentData.url}`,
                );
                $preview.html(
                  `<img src="${attachmentData.url}" alt="${attachmentData.alt || "Image"}" style="width: 100%; height: 120px; object-fit: cover; border-radius: 8px;">`,
                );
                $input.data("image-url", attachmentData.url); // Garder l'URL pour référence
              }
            })
            .catch(() => {
              console.warn(
                `⚠️ Impossible de récupérer l'URL pour l'ID #${imageId}`,
              );
              // Fallback : afficher un placeholder
              $preview.html(
                `<div class="pc-image-placeholder">📷 Image #${imageId} (aperçu indisponible)</div>`,
              );
            });
        } else {
          // Fallback si l'API n'est pas disponible
          console.log(
            `⚠️ API WordPress indisponible, affichage placeholder pour ID #${imageId}`,
          );
          $preview.html(
            `<div class="pc-image-placeholder">📷 Image #${imageId} chargée</div>`,
          );
        }

        // Stocker l'ID dans l'input
        $input.val(imageId);
      } else if (
        typeof value === "string" &&
        (value.startsWith("http") || value.includes("/wp-content/uploads"))
      ) {
        // C'est une URL d'image
        imageUrl = value;

        console.log(`🔧 Image trouvée (URL): ${target} -> ${imageUrl}`);

        // Afficher directement l'image
        $preview.html(
          `<img src="${imageUrl}" alt="Image" style="width: 100%; height: 120px; object-fit: cover; border-radius: 8px;">`,
        );

        // Stocker l'URL dans l'input (sera convertie en ID côté serveur)
        $input.val(imageUrl);
        $input.data("image-url", imageUrl);
      } else {
        console.warn(
          `🔧 setImageFromData: Format non reconnu pour ${target}:`,
          value,
        );
        return;
      }

      // Afficher le bouton de suppression
      $removeBtn.show();
    }

    // 🔧 LEGACY SUPPORT: Méthode conservée pour compatibilité
    setImageFromUrl(target, url) {
      this.setImageFromData(target, url);
    }

    // 🔧 FIX CRITIQUE: Méthode pour collecter les données du Repeater "Groupes d'images"
    collectRepeaterData() {
      const groupesImages = [];

      $("#gallery-categories-container .pc-repeater-item").each(
        (index, element) => {
          const $group = $(element);
          const groupId = $group.attr("id").replace("gallery-group-", "");

          const category = $group.find(".pc-category-select").val();
          const customTitle = $group.find(`#custom-title-${groupId}`).val();
          const imagesString = $group.find(`#gallery-images-${groupId}`).val();

          if (category) {
            // 🔧 FIX CRITIQUE: Les IDs sont déjà stockés correctement par setGalleryImages()
            let imageIds = [];
            if (imagesString && imagesString.trim()) {
              imageIds = imagesString
                .split(",")
                .map((id) => parseInt(id.trim()))
                .filter((id) => !isNaN(id) && id > 0);
            }

            const groupe = {
              categorie: category,
              categorie_personnalisee: customTitle || "",
              images_du_groupe: imageIds, // Array d'IDs numériques
            };

            console.log(`🔧 Groupe collecté #${index}:`, groupe);
            groupesImages.push(groupe);
          }
        },
      );

      return groupesImages;
    }

    // 🔧 FIX CRITIQUE: Méthode pour extraire l'ID d'une image à partir de son URL
    extractImageId(imageUrl) {
      if (!imageUrl || typeof imageUrl !== "string") {
        return 0;
      }

      // Si c'est déjà un ID numérique, le retourner
      if (/^\d+$/.test(imageUrl.toString().trim())) {
        return parseInt(imageUrl);
      }

      // 🔧 FIX CRITIQUE: Pour les URLs, retourner l'URL telle quelle
      // Le backend PHP gérera la conversion via attachment_url_to_postid()
      if (
        imageUrl.includes("wp-content/uploads") ||
        imageUrl.startsWith("http")
      ) {
        console.log("🔧 Envoi d'URL vers backend pour conversion:", imageUrl);
        return imageUrl; // Laisser le backend gérer la conversion
      }

      console.warn("🔧 extractImageId: Format non reconnu:", imageUrl);
      return 0;
    }

    // Méthode pour collecter les données du repeater (alias pour rétrocompatibilité)
    collectGalleryData() {
      return this.collectRepeaterData();
    }
  }

  // === INITIALISATION ===
  $(document).ready(function () {
    // Vérifier que nous sommes dans l'environnement correct
    if (typeof pcReservationVars === "undefined") {
      console.error("PCR Housing Manager: Variables AJAX manquantes");
      return;
    }

    // Initialiser le gestionnaire
    window.pcHousingManager = new PCHousingManager();

    // Hook personnalisé pour l'integration avec app-shell.php
    $(window).on("pc-tab-switch", function (e, tabId) {
      if (tabId === "housing" && window.pcHousingManager) {
        window.pcHousingManager.loadHousingList();
      }
    });
  });

  // === FONCTIONS GLOBALES (pour compatibilité avec app-shell.php) ===
  window.openHousingModal = function (housingId) {
    if (window.pcHousingManager) {
      window.pcHousingManager.openHousingModal(housingId);
    }
  };

  window.closeHousingModal = function () {
    if (window.pcHousingManager) {
      window.pcHousingManager.closeHousingModal();
    }
  };

  window.saveHousingDetails = function () {
    if (window.pcHousingManager) {
      window.pcHousingManager.saveHousingDetails();
    }
  };
})(jQuery);
