/**
 * COMPOSANT : Gestionnaire de l'interface du formulaire (UI)
 * Gère les popovers, compteurs, sliders de prix, filtres avancés et Flatpickr.
 * Partagé entre la recherche de Logements et d'Expériences.
 */
(function ($, w, d) {
  "use strict";

  class PCSearchForm {
    constructor(wrapper) {
      this.$wrapper = $(wrapper);
      this.isExp = this.$wrapper.hasClass("pc-exp-search-wrapper");
      this.mode = this.$wrapper.data("pc-search-mode") || "ajax";
      this.initialDates = null;

      this.hydrateFromUrl();
      this.init();
    }

    hydrateFromUrl() {
      const params = new URLSearchParams(w.location.search);

      // 1. Hydratation Destination / Ville
      if (params.has("ville")) {
        this.$wrapper
          .find("#filter-ville, #filter-exp-ville")
          .val(params.get("ville"));
      }

      // 2. Hydratation Invités
      if (params.has("invites")) {
        const inv = Math.max(1, parseInt(params.get("invites"), 10) || 1);
        this.$wrapper.find('span[data-type="adultes"]').text(inv);
        this.$wrapper.find("#filter-invites").val(inv);
        const $summary = this.$wrapper.find("#guests-summary");
        if ($summary.length)
          $summary.text(inv + " adulte" + (inv > 1 ? "s" : ""));
      }

      // 3. Hydratation Dates ultra-robuste
      const dA = params.get("date_arrivee");
      const dD = params.get("date_depart");
      if (dA || dD) {
        const formatFallback = (d) => {
          if (!d) return "";
          const p = d.split("-");
          return p.length === 3 ? `${p[2]}/${p[1]}/${p[0].slice(2)}` : d;
        };

        if (dA) {
          this.$wrapper.find("#filter-date-arrivee-iso").val(dA);
          this.$wrapper.find("#filter-date-arrivee").val(formatFallback(dA));
        }
        if (dD) {
          this.$wrapper.find("#filter-date-depart-iso").val(dD);
          this.$wrapper.find("#filter-date-depart").val(formatFallback(dD));
        }

        this.initialDates = [];
        if (dA) this.initialDates.push(dA);
        if (dD) this.initialDates.push(dD);
      }

      // 4. Hydratation Expériences
      if (params.has("categorie"))
        this.$wrapper.find("#filter-exp-category").val(params.get("categorie"));
      if (params.has("keyword"))
        this.$wrapper.find("#filter-exp-keyword").val(params.get("keyword"));
    }

    init() {
      this.initGuests();
      this.initAdvanced();
      this.initSteppers();
      this.initPrices();
      this.initReset();

      if (!this.isExp) {
        this.initFlatpickrDeferred();
      }

      // Écoute des changements de base pour déclencher l'AJAX
      this.$wrapper.on("change", 'select, input[type="checkbox"]', () =>
        this.triggerUpdate(),
      );
    }

    triggerUpdate() {
      if (this.mode === "ajax") {
        // Déclenche un événement global écouté par le futur gestionnaire AJAX
        this.$wrapper.trigger("pc_search_update_requested");
      }
    }

    // --- INVITES ---
    initGuests() {
      const $pop = this.$wrapper.find(".pc-guests-popover");
      const $summary = this.$wrapper.find("#guests-summary");
      const $hidden = this.$wrapper.find("#filter-invites");

      const updateSummary = () => {
        const a =
          parseInt(
            this.$wrapper.find('span[data-type="adultes"]').text(),
            10,
          ) || 1;
        const e =
          parseInt(
            this.$wrapper.find('span[data-type="enfants"]').text(),
            10,
          ) || 0;
        const b =
          parseInt(this.$wrapper.find('span[data-type="bebes"]').text(), 10) ||
          0;
        const total = Math.max(1, a + e);
        $hidden.val(total);

        if ($summary.length) {
          let txt = a + " adulte" + (a > 1 ? "s" : "");
          if (e > 0) txt += ", " + e + " enfant" + (e > 1 ? "s" : "");
          if (b > 0) txt += ", " + b + " bébé" + (b > 1 ? "s" : "");
          $summary.text(txt);
        }
      };

      this.$wrapper.on("click", ".pc-guests-trigger", (e) => {
        e.stopPropagation();
        $pop.prop("hidden", !$pop.prop("hidden"));
      });

      this.$wrapper.on("click", ".pc-guests-close", (e) => {
        e.stopPropagation();
        $pop.prop("hidden", true);
      });

      this.$wrapper.on("click", ".guest-stepper", (e) => {
        const $btn = $(e.currentTarget);
        const type = $btn.data("type");
        const step = parseInt($btn.data("step"), 10);
        const $span = this.$wrapper.find(`span[data-type="${type}"]`).first();
        let nv = (parseInt($span.text(), 10) || 0) + step;

        if (type === "adultes" && nv < 1) nv = 1;
        if (nv < 0) nv = 0;

        $span.text(nv);
        updateSummary();
        this.triggerUpdate();
      });

      $(d).on("click", (e) => {
        if (!$(e.target).closest(".pc-search-field--guests").length) {
          $(".pc-guests-popover").prop("hidden", true);
        }
      });
    }

    // --- FILTRES AVANCÉS ---
    initAdvanced() {
      this.$wrapper.on("click", ".pc-adv-toggle, .pc-exp-adv-toggle", (e) => {
        e.preventDefault();
        const $panel = this.$wrapper.find(".pc-advanced, .pc-exp-advanced");
        const isHidden = $panel.prop("hidden");
        $panel.prop("hidden", !isHidden);
        $(e.currentTarget).attr("aria-expanded", isHidden);
      });

      this.$wrapper.on("click", ".pc-adv-close", (e) => {
        e.preventDefault();
        this.$wrapper
          .find(".pc-advanced, .pc-exp-advanced")
          .prop("hidden", true);
      });

      $(d).on("click", (e) => {
        if (
          !$(e.target).closest(
            ".pc-advanced, .pc-exp-advanced, .pc-adv-toggle, .pc-exp-adv-toggle",
          ).length
        ) {
          $(".pc-advanced, .pc-exp-advanced").prop("hidden", true);
        }
      });
    }

    // --- STEPPERS NUMÉRIQUES ---
    initSteppers() {
      this.$wrapper.on("click", ".num-stepper", (e) => {
        const targetId = $(e.currentTarget).data("target");
        const step = parseInt($(e.currentTarget).data("step"), 10);
        const $input = this.$wrapper.find(
          `#filter-${targetId}, #filter-exp-${targetId}`,
        );
        const v = parseInt($input.val(), 10) || 0;
        const min = parseInt($input.attr("min"), 10) || 0;
        $input.val(Math.max(min, v + step)).trigger("change");
      });

      this.$wrapper.on("input change", ".pc-num-input", (e) => {
        const $input = $(e.currentTarget);
        let v = parseInt($input.val(), 10);
        const min = parseInt($input.attr("min"), 10) || 0;
        if (isNaN(v) || v < min) v = min;
        $input.val(v);
        this.triggerUpdate();
      });
    }

    // --- SLIDERS DE PRIX ---
    initPrices() {
      const $wrapP = this.$wrapper.find(".pc-price-range");
      if (!$wrapP.length) return;

      const $minR = this.$wrapper.find('input[type="range"]').first();
      const $maxR = this.$wrapper.find('input[type="range"]').last();
      const $minN = this.$wrapper.find('input[type="number"]').first();
      const $maxN = this.$wrapper.find('input[type="number"]').last();

      const rMin = parseInt($wrapP.data("min") || 0, 10);
      const rMax = parseInt($wrapP.data("max") || 2000, 10);

      const clamp = (v, min, max) =>
        Math.max(
          min,
          Math.min(max, isNaN(parseInt(v, 10)) ? min : parseInt(v, 10)),
        );

      const syncRange = () => {
        let a = clamp($minR.val(), rMin, rMax);
        let b = clamp($maxR.val(), rMin, rMax);
        if (a > b) [a, b] = [b, a];
        $minN.val(a || "");
        $maxN.val(b === rMax ? "" : b);
        this.triggerUpdate();
      };

      const syncNumber = () => {
        let a = clamp($minN.val() || rMin, rMin, rMax);
        let b = clamp($maxN.val() || rMax, rMin, rMax);
        if (a > b) [a, b] = [b, a];
        $minR.val(a);
        $maxR.val(b);
        this.triggerUpdate();
      };

      $minR.on("input change", syncRange);
      $maxR.on("input change", syncRange);
      $minN.on("change", syncNumber);
      $maxN.on("change", syncNumber);
    }

    // --- RESET ---
    initReset() {
      this.$wrapper.on("click", ".pc-adv-reset", (e) => {
        e.preventDefault();

        this.$wrapper
          .find('input[type="text"], input[type="hidden"], select')
          .val("");

        this.$wrapper.find('span[data-type="adultes"]').text("1");
        this.$wrapper
          .find('span[data-type="enfants"], span[data-type="bebes"]')
          .text("0");
        this.$wrapper.find("#filter-invites").val("1");
        const $summary = this.$wrapper.find("#guests-summary");
        if ($summary.length) $summary.text("1 adulte");

        this.$wrapper.find(".pc-num-input").val("0");
        this.$wrapper.find('input[type="checkbox"]').prop("checked", false);

        const $wrapP = this.$wrapper.find(".pc-price-range");
        if ($wrapP.length) {
          const rMax = $wrapP.data("max") || 2000;
          this.$wrapper.find('input[type="range"]').first().val(0);
          this.$wrapper.find('input[type="range"]').last().val(rMax);
          this.$wrapper.find('input[type="number"]').val("");
        }

        const fp = this.$wrapper.data("fp");
        if (fp && fp.clear) fp.clear();

        this.triggerUpdate();
      });
    }

    // --- FLATPICKR (Logements) ---
    initFlatpickrDeferred() {
      const initFP = () => {
        const $arrVis = this.$wrapper.find("#filter-date-arrivee");
        if (
          typeof w.flatpickr === "undefined" ||
          !$arrVis.length ||
          this.$wrapper.data("fp")
        )
          return;

        const getMonths = () =>
          w.matchMedia("(min-width:768px)").matches ? 2 : 1;

        const cfg = {
          locale: w.flatpickr.l10ns && w.flatpickr.l10ns.fr ? "fr" : "default",
          minDate: "today",
          mode: "range",
          dateFormat: "d/m/y",
          showMonths: getMonths(),
          allowInput: false,
          // Note: On retire defaultDate d'ici pour éviter le bug de format
          onChange: (sel) => {
            const $depVis = this.$wrapper.find("#filter-date-depart");
            const $arrIso = this.$wrapper.find("#filter-date-arrivee-iso");
            const $depIso = this.$wrapper.find("#filter-date-depart-iso");

            if (!sel || !sel.length) {
              $arrVis.val("");
              $depVis.val("");
              $arrIso.val("");
              $depIso.val("");
            } else if (sel.length === 1) {
              $arrVis.val(w.flatpickr.formatDate(sel[0], "d/m/y"));
              $depVis.val("");
              $arrIso.val(w.flatpickr.formatDate(sel[0], "Y-m-d"));
              $depIso.val("");
            } else {
              $arrVis.val(w.flatpickr.formatDate(sel[0], "d/m/y"));
              $depVis.val(w.flatpickr.formatDate(sel[1], "d/m/y"));
              $arrIso.val(w.flatpickr.formatDate(sel[0], "Y-m-d"));
              $depIso.val(w.flatpickr.formatDate(sel[1], "Y-m-d"));
              this.triggerUpdate();
            }
          },
        };

        if (typeof w.rangePlugin !== "undefined") {
          // Ciblage strict de l'input de CE formulaire spécifique
          const depVis = this.$wrapper.find("#filter-date-depart")[0];
          if (depVis) {
            cfg.plugins = [new w.rangePlugin({ input: depVis })];
          }
        }

        const fp = w.flatpickr($arrVis[0], cfg);
        this.$wrapper.data("fp", fp);

        // --- CORRECTION : Forçage des dates initiales APRES initialisation ---
        if (this.initialDates && this.initialDates.length === 2) {
          // On dit explicitement à Flatpickr de lire le format Y-m-d
          fp.setDate(this.initialDates, false, "Y-m-d");

          // On force l'affichage visuel (le rangePlugin oublie parfois de le faire au chargement)
          const $depVis = this.$wrapper.find("#filter-date-depart");
          if ($depVis.length && fp.selectedDates.length === 2) {
            $arrVis.val(w.flatpickr.formatDate(fp.selectedDates[0], "d/m/y"));
            $depVis.val(w.flatpickr.formatDate(fp.selectedDates[1], "d/m/y"));
          }
        }

        const mq = w.matchMedia("(min-width:768px)");
        const upd = () => {
          const m = mq.matches ? 2 : 1;
          if (fp && fp.config.showMonths !== m) {
            fp.set("showMonths", m);
            if (fp.redraw) fp.redraw();
          }
        };
        if (mq.addEventListener) mq.addEventListener("change", upd);
        else mq.addListener(upd);
      };

      if ("requestIdleCallback" in w) w.requestIdleCallback(initFP);
      else setTimeout(initFP, 300);
    }
  }

  // Auto-initialisation globale
  $(function () {
    $(".pc-search-wrapper, .pc-exp-search-wrapper").each(function () {
      if (!$(this).data("pc-form-instance")) {
        $(this).data("pc-form-instance", new PCSearchForm(this));
      }
    });
  });
})(jQuery, window, document);
