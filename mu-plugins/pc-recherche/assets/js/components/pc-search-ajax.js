/**
 * COMPOSANT : Gestionnaire des requêtes AJAX
 * Écoute les événements du formulaire, envoie la requête et met à jour le DOM.
 */
(function ($, w, d) {
  "use strict";

  class PCSearchAjax {
    constructor(wrapper) {
      this.$wrapper = $(wrapper);
      this.$form = this.$wrapper.find("form").first();
      this.$resultsContainer = this.$wrapper.find(".flow").first(); // Le conteneur des résultats

      this.isExp = this.$wrapper.hasClass("pc-exp-search-wrapper");
      this.mode = this.$wrapper.data("pc-search-mode") || "ajax";

      // Configuration de l'endpoint et de la sécurité selon le contexte
      this.config = this.isExp ? w.pc_exp_ajax || {} : w.pc_search_params || {};
      this.action = this.isExp
        ? "pc_filter_experiences"
        : "pc_filter_logements";
      this.themeFromUrl =
        new URLSearchParams(w.location.search).get("theme") || "";

      this.initListeners();
      this.checkInitialMapHydration();
    }

    initListeners() {
      if (this.mode !== "ajax") return;

      // Écoute l'événement déclenché par pc-search-form.js
      this.$wrapper.on("pc_search_update_requested", () => {
        this.themeFromUrl = ""; // On vide le thème si l'utilisateur modifie ses filtres
        this.performSearch(1);
      });

      // Soumission native du formulaire (Touche Entrée ou Bouton)
      this.$form.on("submit", (e) => {
        e.preventDefault();
        this.themeFromUrl = "";
        this.performSearch(1);
      });

      // Clics sur la pagination
      $(d).on("click", ".pc-pagination a", (e) => {
        e.preventDefault();
        const page = parseInt($(e.currentTarget).data("page"), 10) || 1;
        this.performSearch(page);

        // Remonte en haut de la liste
        $("html, body").animate(
          { scrollTop: this.$wrapper.offset().top - 50 },
          500,
        );
      });
    }

    checkInitialMapHydration() {
      // Si c'est une expérience, on regarde s'il y a des données de carte initiales passées par le PHP
      if (
        this.isExp &&
        typeof w.pc_exp_initial_data !== "undefined" &&
        w.pc_exp_initial_data.map_data
      ) {
        // On attend que la carte soit prête
        const checkMap = setInterval(() => {
          if (w.pcSearchMap && w.pcSearchMap.map) {
            w.pcSearchMap.updateMarkers(w.pc_exp_initial_data.map_data);
            clearInterval(checkMap);
          }
        }, 100);
      }
      // Si c'est un logement en mode AJAX avec des résultats déjà affichés, on lance une recherche "silencieuse" pour la carte
      else if (
        !this.isExp &&
        this.mode === "ajax" &&
        this.$resultsContainer.find(".pc-vignette").length > 0
      ) {
        // On met un petit délai pour laisser la carte s'initialiser
        setTimeout(() => this.performSearch(1, true), 500);
      }
    }

    performSearch(page = 1, silent = false) {
      if (!this.config.ajax_url) return;

      // Récupération automatique de TOUS les champs du formulaire !
      const formData = new FormData(this.$form[0]);
      formData.append("action", this.action);
      formData.append("security", this.config.nonce || "");
      formData.append("page", page);

      if (this.themeFromUrl) {
        formData.append("theme", this.themeFromUrl);
      }

      // Gestion spéciale des cases à cocher multiples (équipements)
      const equipements = [];
      this.$form.find(".filter-eq:checked").each(function () {
        equipements.push($(this).val());
      });
      if (equipements.length) {
        formData.delete("equipements[]");
        equipements.forEach((eq) => formData.append("equipements[]", eq));
      }

      if (!silent) {
        this.$resultsContainer.addClass("is-loading");
        $(".pc-pagination").remove(); // Retire la pagination pendant le chargement
      }

      // Envoi de la requête avec jQuery
      $.ajax({
        url: this.config.ajax_url,
        type: "POST",
        data: new URLSearchParams(formData).toString(),
        contentType: "application/x-www-form-urlencoded",
        success: (res) => {
          if (res && res.success && res.data) {
            if (!silent) {
              this.$resultsContainer.html(
                res.data.vignettes_html ||
                  '<div class="pc-no-results"><h3>Aucun résultat.</h3></div>',
              );
              if (res.data.pagination_html) {
                this.$resultsContainer.after(res.data.pagination_html);
              }
            }
            // Mise à jour de la carte
            if (w.pcSearchMap) {
              w.pcSearchMap.updateMarkers(res.data.map_data || []);
            }
          } else if (!silent) {
            this.$resultsContainer.html(
              '<div class="pc-no-results"><h3>Une erreur est survenue.</h3></div>',
            );
          }
        },
        error: () => {
          if (!silent) {
            this.$resultsContainer.html(
              '<div class="pc-no-results"><h3>Erreur de communication.</h3></div>',
            );
          }
        },
        complete: () => {
          if (!silent) this.$resultsContainer.removeClass("is-loading");
        },
      });
    }
  }

  // Auto-initialisation
  $(function () {
    $(".pc-search-wrapper, .pc-exp-search-wrapper").each(function () {
      if (!$(this).data("pc-ajax-instance")) {
        $(this).data("pc-ajax-instance", new PCSearchAjax(this));
      }
    });
  });
})(jQuery, window, document);
