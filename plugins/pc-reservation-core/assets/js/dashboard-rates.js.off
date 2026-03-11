(function ($) {
  "use strict";

  class PCRateManager {
    constructor() {
      this.calendar = null;
      this.calendarEl = null;
      this.seasons = []; // État local des saisons
      this.promos = []; // État local des promos
      this.basePrice = 0;
      this.nextId = 1; // ID temporaire pour les nouveaux items
      this.editingPeriods = []; // État temporaire pour corriger le bug création
      this.flatpickrInstance = null; // Instance Flatpickr
    }

    init(containerId, initialData, basePrice) {
      console.log("🚀 Init Rate Manager", initialData);
      this.calendarEl = document.getElementById(containerId);
      this.basePrice = parseFloat(basePrice) || 0;

      // Parsing des données initiales (depuis ACF via PHP)
      this.parseInitialData(initialData);

      // 🔧 FIX: Déplacer la modale vers body pour éviter les conflits CSS
      $("#pc-rate-internal-modal").appendTo("body");

      this.renderSidebar();
      this.initCalendar();
      this.bindEvents();
    }

    // --- GESTION DES DONNÉES ---

    parseInitialData(data) {
      // Transforme les données brutes ACF en objets manipulables
      if (data && data.seasons) {
        this.seasons = data.seasons.map((s) => ({
          ...s,
          id: s.id || "season_" + this.nextId++, // Assurer un ID unique
          type: "season",
          // 🔧 FIX AUDIT: Assurer l'initialisation de TOUS les champs critiques
          name: s.name || "Saison",
          price: s.price || 0,
          note: s.note || "", // Note interne
          minNights: s.minNights || 0,
          guestFee: s.guestFee || 0, // Frais par invité supplémentaire
          guestFrom: s.guestFrom || 0, // À partir de X invités
          periods: s.periods || [], // Périodes déjà définies
          // On recalcule toujours la couleur basée sur le nom pour garantir la cohérence
          color: this.stringToColor(s.name || "Saison"),
        }));
      }
      if (data && data.promos) {
        this.promos = data.promos.map((p) => ({
          ...p,
          id: p.id || "promo_" + this.nextId++,
          type: "promo",
          // 🔧 FIX AUDIT: Assurer l'initialisation de TOUS les champs critiques pour promos
          name: p.name || "Promotion",
          promo_type: p.promo_type || "percent",
          value: p.value || 0,
          validUntil: p.validUntil || "", // Date de validité
          periods: p.periods || [], // Périodes déjà définies
          color: "#ef4444",
        }));
      }
    }

    getData() {
      // Cette méthode sera appelée par PCHousingManager lors du SAVE
      return {
        seasons: this.seasons,
        promos: this.promos,
      };
    }

    // --- INTERFACE ---

    renderSidebar() {
      console.log("🔧 DEBUG: Rendering sidebar...");
      const listEl = document.getElementById("pc-rates-list");
      if (!listEl) {
        console.error(
          "🚨 ERREUR: Conteneur #pc-rates-list introuvable dans le DOM",
        );
        return;
      }
      listEl.innerHTML = "";

      // Rendu Saisons
      this.seasons.forEach((s) => this.createSidebarItem(listEl, s));

      // Rendu Promos
      this.promos.forEach((p) => this.createSidebarItem(listEl, p));

      this.initDraggable(listEl);
    }

    createSidebarItem(container, item) {
      const priceDisplay =
        item.type === "season"
          ? `${item.price}€`
          : item.promo_type === "percent"
            ? `-${item.value}%`
            : `-${item.value}€`;
      const div = document.createElement("div");
      div.className = "pc-draggable-event";
      div.setAttribute("data-id", item.id);
      div.setAttribute("data-type", item.type);
      div.style.backgroundColor = item.color;
      div.innerHTML = `
                <span>${item.name}</span>
                <span class="pc-price-tag">${priceDisplay}</span>
                <span class="pc-edit-icon" style="margin-left:8px;cursor:pointer;">✏️</span>
            `;

      // 🔧 FIX: Ne plus attacher d'événements ici, utiliser la délégation globale dans bindEvents()

      container.appendChild(div);
    }

    initDraggable(container) {
      if (typeof FullCalendar.Draggable === "undefined") return;
      new FullCalendar.Draggable(container, {
        itemSelector: ".pc-draggable-event",
        eventData: (eventEl) => {
          const id = eventEl.getAttribute("data-id");
          const type = eventEl.getAttribute("data-type");
          const item =
            type === "season"
              ? this.seasons.find((s) => s.id == id)
              : this.promos.find((p) => p.id == id);

          return {
            title: item.name,
            backgroundColor: item.color,
            borderColor: item.color,
            extendedProps: {
              type: type,
              entityId: id,
            },
          };
        },
      });
    }

    // --- CALENDRIER ---

    initCalendar() {
      if (this.calendar) this.calendar.destroy();

      this.calendar = new FullCalendar.Calendar(this.calendarEl, {
        initialView: "multiMonthYear",
        multiMonthMaxColumns: 1, // Vue verticale comme Airbnb
        locale: "fr",
        firstDay: 1,
        editable: true,
        droppable: true,
        eventOverlap: true, // Autoriser overlap pour promo sur saison

        // Events Source
        events: this.getCalendarEvents(),

        // Configuration pour ordre des événements
        eventOrder: "zIndex",

        // Rendu du contenu des événements
        eventContent: (arg) => {
          const props = arg.event.extendedProps;
          let html = `<div class="pc-evt-title">${arg.event.title}</div>`;

          if (props.type === "season") {
            html += `<div class="pc-evt-price">${props.price}€</div>`;
          } else if (props.type === "promo") {
            const valDisplay =
              props.promoType === "percent"
                ? `-${props.value}%`
                : `-${props.value}€`;
            html += `<div class="pc-evt-promo-badge">${valDisplay}</div>`;
          }

          return { html: `<div class="pc-event-body">${html}</div>` };
        },

        // Rendu Cellule avec prix de base
        dayCellDidMount: (arg) => {
          const frame = arg.el.querySelector(".fc-daygrid-day-frame");
          if (frame && this.basePrice > 0) {
            // Vérifier s'il y a une saison sur cette cellule
            const hasSeasonEvent = this.seasons.some((season) => {
              return (
                season.periods &&
                season.periods.some((period) => {
                  const cellDate = arg.date.toISOString().split("T")[0];
                  return cellDate >= period.start && cellDate <= period.end;
                })
              );
            });

            // Afficher le prix de base seulement s'il n'y a pas de saison
            // (une promo seule ne cache pas le prix de base)
            if (!hasSeasonEvent) {
              const priceEl = document.createElement("div");
              priceEl.className = "pc-base-price";
              priceEl.textContent = this.basePrice + "€";
              frame.appendChild(priceEl);
            }
          }
        },

        // Drop depuis sidebar
        eventReceive: (info) => {
          const type = info.event.extendedProps.type;
          const entityId = info.event.extendedProps.entityId;
          const start = info.event.startStr;
          // FullCalendar end est exclusif, on ajuste si nécessaire
          let end = info.event.endStr;
          if (!end) end = start;
          else {
            let d = new Date(end);
            d.setDate(d.getDate() - 1);
            end = d.toISOString().split("T")[0];
          }

          this.addPeriod(type, entityId, start, end);
          info.event.remove(); // On supprime l'event temporaire visuel, le refresh le recréera proprement
        },

        // Resize / Move sur le calendrier
        eventDrop: (info) => this.handleEventChange(info),
        eventResize: (info) => this.handleEventChange(info),

        // Clic pour supprimer une période
        eventClick: (info) => {
          if (confirm("Supprimer cette période ?")) {
            this.removePeriod(
              info.event.extendedProps.type,
              info.event.extendedProps.entityId,
              info.event.extendedProps.periodIndex,
            );
          }
        },
      });

      this.calendar.render();
    }

    getCalendarEvents() {
      let events = [];

      // Saisons
      this.seasons.forEach((s) => {
        if (s.periods && Array.isArray(s.periods)) {
          s.periods.forEach((p, index) => {
            events.push({
              title: s.name,
              start: p.start,
              end: this.addDays(p.end, 1), // FullCalendar exclusif
              backgroundColor: s.color,
              borderColor: s.color, // Même couleur que le fond pour éviter les bordures
              className: "pc-event-season", // Classe spécifique pour les saisons
              zIndex: 10, // Saisons en arrière-plan
              extendedProps: {
                type: "season",
                entityId: s.id,
                periodIndex: index,
                price: s.price,
              },
            });
          });
        }
      });

      // Promos
      this.promos.forEach((p) => {
        if (p.periods && Array.isArray(p.periods)) {
          p.periods.forEach((period, index) => {
            events.push({
              title: p.name,
              start: period.start,
              end: this.addDays(period.end, 1),
              backgroundColor: "transparent", // Promos utilisent un fond hachuré via CSS
              borderColor: p.color,
              className: "pc-event-promo", // Classe spécifique pour les promos
              zIndex: 50, // Promos au-dessus des saisons
              extendedProps: {
                type: "promo",
                entityId: p.id,
                periodIndex: index,
                value: p.value,
                promoType: p.promo_type || "percent", // Ajouter le type de promo pour l'affichage
              },
            });
          });
        }
      });

      return events;
    }

    refreshCalendar() {
      if (!this.calendar) return;
      this.calendar.removeAllEvents();
      this.calendar.addEventSource(this.getCalendarEvents());
    }

    // --- LOGIQUE METIER ---

    addPeriod(type, entityId, start, end) {
      const list = type === "season" ? this.seasons : this.promos;
      const item = list.find((i) => i.id == entityId);
      if (item) {
        if (!item.periods) item.periods = [];
        item.periods.push({ start, end });
        this.refreshCalendar();
      }
    }

    handleEventChange(info) {
      const props = info.event.extendedProps;
      const start = info.event.startStr;
      let end = info.event.endStr;
      if (!end) end = start;
      else {
        let d = new Date(end);
        d.setDate(d.getDate() - 1);
        end = d.toISOString().split("T")[0];
      }

      const list = props.type === "season" ? this.seasons : this.promos;
      const item = list.find((i) => i.id == props.entityId);

      if (item && item.periods[props.periodIndex]) {
        item.periods[props.periodIndex] = { start, end };
      }
    }

    removePeriod(type, entityId, index) {
      const list = type === "season" ? this.seasons : this.promos;
      const item = list.find((i) => i.id == entityId);
      if (item && item.periods) {
        item.periods.splice(index, 1);
        this.refreshCalendar();
      }
    }

    // --- GESTION DES MODALES INTERNES (Création/Edition Saison) ---

    openEditModal(type, id = null) {
      console.log("🔧 DEBUG: Opening modal for", type, "ID:", id);
      // 🔧 FIX CRITIQUE: IDs corrigés pour correspondre au HTML du shortcode-housing.php
      const isEdit = !!id;
      const item = isEdit
        ? type === "season"
          ? this.seasons.find((s) => s.id == id)
          : this.promos.find((p) => p.id == id)
        : {};

      // 🔧 CORRECTION BUG CRÉATION: Gestion état temporaire
      if (!isEdit) {
        // Mode Création : initialise un tableau vide
        this.editingPeriods = [];
      } else {
        // Mode Édition : clone profond pour ne pas modifier l'original en temps réel
        this.editingPeriods = JSON.parse(JSON.stringify(item.periods || []));
      }

      $("#pc-rate-modal-type").val(type);
      $("#pc-rate-modal-id").val(id || "");
      $("#pc-rate-name").val(item.name || "");

      if (type === "season") {
        $("#pc-rate-season-fields").show();
        $("#pc-rate-promo-fields").hide();
        $("#pc-rate-price").val(item.price || "");
        $("#pc-rate-min-nights").val(item.minNights || "");
        // 🔧 FIX AUDIT: Tous les champs pour les saisons
        $("#pc-rate-note").val(item.note || "");
        $("#pc-rate-guest-fee").val(item.guestFee || "");
        $("#pc-rate-guest-from").val(item.guestFrom || "");
      } else {
        $("#pc-rate-season-fields").hide();
        $("#pc-rate-promo-fields").show();
        $("#pc-rate-promo-val").val(item.value || "");
        $("#pc-rate-promo-type").val(item.promo_type || "percent");
      }

      // Affichage des périodes depuis l'état temporaire
      this.renderPeriodsListFromTemp();

      // Initialiser Flatpickr pour le sélecteur de période
      this.initFlatpickr();

      $("#pc-rate-internal-modal").fadeIn().show();
    }

    /**
     * Initialise Flatpickr pour la sélection de plage de dates
     */
    initFlatpickr() {
      // Détruire l'instance précédente si elle existe
      if (this.flatpickrInstance) {
        this.flatpickrInstance.destroy();
      }

      const inputElement = document.getElementById("pc-rate-period-range");
      if (!inputElement) {
        console.error("Element #pc-rate-period-range introuvable");
        return;
      }

      this.flatpickrInstance = flatpickr(inputElement, {
        mode: "range",
        dateFormat: "Y-m-d",
        locale: "fr",
        minDate: "today",
        showMonths: 2,
        static: false,
        // Configuration visuelle
        prevArrow:
          '<svg width="24" height="24" viewBox="0 0 24 24"><path d="M15.41 16.59L10.83 12l4.58-4.59L14 6l-6 6 6 6z"/></svg>',
        nextArrow:
          '<svg width="24" height="24" viewBox="0 0 24 24"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6z"/></svg>',
        onChange: (selectedDates, dateStr, instance) => {
          console.log("📅 Flatpickr selection:", selectedDates, dateStr);
        },
      });

      console.log("✅ Flatpickr initialisé", this.flatpickrInstance);
    }

    // --- GESTION DES PÉRIODES ---

    /**
     * Affiche les périodes existantes dans la liste de la modale
     * @param {Object} item - L'objet saison ou promo
     */
    renderPeriodsList(item) {
      const listEl = $("#pc-rate-periods-list");
      listEl.empty();

      if (
        !item.periods ||
        !Array.isArray(item.periods) ||
        item.periods.length === 0
      ) {
        listEl.append(
          '<li style="color: #64748b; font-style: italic; text-align: center; padding: 15px;">Aucune période définie</li>',
        );
        return;
      }

      item.periods.forEach((period, index) => {
        const startFormatted = this.formatDateFR(period.start);
        const endFormatted = this.formatDateFR(period.end);

        const listItem = $(`
          <li style="display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; margin-bottom: 5px; background: #f1f5f9; border-radius: 6px; border-left: 3px solid ${item.color || "#3b82f6"};">
            <span style="font-size: 13px;">
              📅 <strong>${startFormatted}</strong> au <strong>${endFormatted}</strong>
            </span>
            <button type="button" class="pc-remove-period-btn" data-period-index="${index}" style="background: #ef4444; color: white; border: none; border-radius: 4px; width: 24px; height: 24px; cursor: pointer; font-size: 12px; line-height: 1;">×</button>
          </li>
        `);

        listEl.append(listItem);
      });
    }

    /**
     * Affiche les périodes depuis l'état temporaire (pour éviter le bug création)
     */
    renderPeriodsListFromTemp() {
      const listEl = $("#pc-rate-periods-list");
      listEl.empty();

      if (!this.editingPeriods || this.editingPeriods.length === 0) {
        listEl.append(
          '<li style="color: #64748b; font-style: italic; text-align: center; padding: 15px;">Aucune période définie</li>',
        );
        return;
      }

      this.editingPeriods.forEach((period, index) => {
        const startFormatted = this.formatDateFR(period.start);
        const endFormatted = this.formatDateFR(period.end);

        const listItem = $(`
          <li style="display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; margin-bottom: 5px; background: #f1f5f9; border-radius: 6px; border-left: 3px solid #3b82f6;">
            <span style="font-size: 13px;">
              📅 <strong>${startFormatted}</strong> au <strong>${endFormatted}</strong>
            </span>
            <button type="button" class="pc-remove-temp-period-btn" data-period-index="${index}" style="background: #ef4444; color: white; border: none; border-radius: 4px; width: 24px; height: 24px; cursor: pointer; font-size: 12px; line-height: 1;">×</button>
          </li>
        `);

        listEl.append(listItem);
      });
    }

    /**
     * Ajoute une période depuis le sélecteur Flatpickr
     */
    addPeriodFromRange() {
      if (!this.flatpickrInstance) {
        console.error("Flatpickr non initialisé");
        return;
      }

      const selectedDates = this.flatpickrInstance.selectedDates;

      // Validation
      if (!selectedDates || selectedDates.length !== 2) {
        alert(
          "Veuillez sélectionner une plage de dates complète (début et fin)",
        );
        return;
      }

      const startDate = selectedDates[0].toISOString().split("T")[0];
      const endDate = selectedDates[1].toISOString().split("T")[0];

      // Vérifier s'il n'y a pas de conflit de dates dans l'état temporaire
      const hasConflict = this.editingPeriods.some((existingPeriod) => {
        return (
          startDate <= existingPeriod.end && endDate >= existingPeriod.start
        );
      });

      if (hasConflict) {
        if (
          !confirm(
            "Cette période chevauche avec une période existante. Continuer ?",
          )
        ) {
          return;
        }
      }

      // Ajouter la nouvelle période à l'état temporaire
      this.editingPeriods.push({
        start: startDate,
        end: endDate,
      });

      console.log("✅ Période ajoutée à l'état temporaire:", {
        start: startDate,
        end: endDate,
      });

      // Afficher feedback
      const startFormatted = this.formatDateFR(startDate);
      const endFormatted = this.formatDateFR(endDate);
      this.showFeedbackMessage(
        `✅ Période du ${startFormatted} au ${endFormatted} ajoutée`,
        "success",
      );

      // Rafraîchir l'affichage
      this.renderPeriodsListFromTemp();

      // Clear Flatpickr
      this.flatpickrInstance.clear();
    }

    /**
     * Supprime une période de l'état temporaire
     * @param {number} periodIndex - L'index de la période à supprimer
     */
    removeTempPeriodByIndex(periodIndex) {
      if (!confirm("Supprimer cette période ?")) {
        return;
      }

      if (this.editingPeriods && this.editingPeriods[periodIndex]) {
        this.editingPeriods.splice(periodIndex, 1);
        console.log("🗑️ Période supprimée de l'état temporaire");

        // Rafraîchir l'affichage
        this.renderPeriodsListFromTemp();
      }
    }

    /**
     * Affiche un message de feedback à l'utilisateur
     * @param {string} message - Le message à afficher
     * @param {string} type - Le type de message (success, error, info)
     */
    showFeedbackMessage(message, type = "info") {
      const feedbackEl = $("#pc-period-feedback");
      feedbackEl
        .removeClass("pc-feedback-success pc-feedback-error pc-feedback-info")
        .addClass(`pc-feedback-${type}`)
        .html(message)
        .fadeIn();

      // Masquer après 3 secondes
      setTimeout(() => {
        feedbackEl.fadeOut();
      }, 3000);
    }

    /**
     * Ajoute une période manuellement via les champs de dates (Fonction obsolète - gardée pour compatibilité)
     */
    addPeriodManually() {
      const type = $("#pc-rate-modal-type").val();
      const id = $("#pc-rate-modal-id").val();
      const startDate = $("#pc-rate-period-start").val();
      const endDate = $("#pc-rate-period-end").val();

      // Validation
      if (!startDate || !endDate) {
        alert("Veuillez remplir les deux dates (début et fin)");
        return;
      }

      if (startDate > endDate) {
        alert("La date de début doit être antérieure à la date de fin");
        return;
      }

      // Trouver l'élément en cours d'édition
      const list = type === "season" ? this.seasons : this.promos;
      const item = list.find((i) => i.id == id);

      if (!item) {
        console.error("Élément non trouvé pour l'ajout de période");
        return;
      }

      // Initialiser le tableau periods si nécessaire
      if (!item.periods) {
        item.periods = [];
      }

      // Vérifier s'il n'y a pas de conflit de dates
      const hasConflict = item.periods.some((existingPeriod) => {
        return (
          startDate <= existingPeriod.end && endDate >= existingPeriod.start
        );
      });

      if (hasConflict) {
        if (
          !confirm(
            "Cette période chevauche avec une période existante. Continuer ?",
          )
        ) {
          return;
        }
      }

      // Ajouter la nouvelle période
      item.periods.push({
        start: startDate,
        end: endDate,
      });

      console.log(`✅ Période ajoutée à ${item.name}:`, {
        start: startDate,
        end: endDate,
      });

      // Rafraîchir l'affichage
      this.renderPeriodsList(item);
      this.refreshCalendar();

      // Vider les champs d'ajout
      $("#pc-rates-period-start").val("");
      $("#pc-rates-period-end").val("");
    }

    /**
     * Supprime une période par son index
     * @param {number} periodIndex - L'index de la période à supprimer
     */
    removePeriodByIndex(periodIndex) {
      const type = $("#pc-rate-modal-type").val();
      const id = $("#pc-rate-modal-id").val();

      if (!confirm("Supprimer cette période ?")) {
        return;
      }

      const list = type === "season" ? this.seasons : this.promos;
      const item = list.find((i) => i.id == id);

      if (item && item.periods && item.periods[periodIndex]) {
        item.periods.splice(periodIndex, 1);
        console.log(`🗑️ Période supprimée de ${item.name}`);

        // Rafraîchir l'affichage
        this.renderPeriodsList(item);
        this.refreshCalendar();
      }
    }

    saveInternalModal() {
      const type = $("#pc-rate-modal-type").val();
      const id = $("#pc-rate-modal-id").val();
      const name = $("#pc-rate-name").val();

      if (!name) return alert("Nom requis");

      const newData = {
        name: name,
        // 🔧 CORRECTION BUG CRÉATION: Injecter this.editingPeriods dans l'objet final
        periods: this.editingPeriods || [],
      };

      let list = type === "season" ? this.seasons : this.promos;

      if (type === "season") {
        newData.price = parseFloat($("#pc-rate-price").val()) || 0;
        newData.minNights = parseInt($("#pc-rate-min-nights").val()) || 0;
        // 🔧 FIX AUDIT: Tous les champs critiques pour les saisons
        newData.note = $("#pc-rate-note").val() || "";
        newData.guestFee = parseFloat($("#pc-rate-guest-fee").val()) || 0;
        newData.guestFrom = parseInt($("#pc-rate-guest-from").val()) || 0;
        // Génération de couleur unique basée sur le nom (Algorithme HSL)
        newData.color = this.stringToColor(name);
      } else {
        newData.value = parseFloat($("#pc-rate-promo-val").val()) || 0;
        newData.promo_type = $("#pc-rate-promo-type").val() || "percent";
        newData.color = "#ef4444";
      }

      if (id) {
        // Update - Remplacer complètement avec les nouvelles données ET les périodes temporaires
        const index = list.findIndex((i) => i.id == id);
        if (index > -1) {
          list[index] = {
            ...list[index],
            ...newData, // Inclut les périodes temporaires
          };
        }
      } else {
        // Create - Initialisation avec valeurs par défaut (Audit 5.3)
        newData.id = type + "_" + this.nextId++;
        newData.type = type;
        list.push(newData);
      }

      console.log(`💾 Sauvegarde ${type}:`, newData);

      $("#pc-rate-internal-modal").fadeOut();

      // Nettoyer l'état temporaire
      this.editingPeriods = [];

      this.renderSidebar();
      this.refreshCalendar();
    }

    deleteInternalItem() {
      const type = $("#pc-rate-modal-type").val();
      const id = $("#pc-rate-modal-id").val();
      if (!id) return;

      if (confirm("Supprimer définitivement ?")) {
        if (type === "season") {
          this.seasons = this.seasons.filter((s) => s.id != id);
        } else {
          this.promos = this.promos.filter((p) => p.id != id);
        }
        $("#pc-rate-internal-modal").fadeOut();
        this.renderSidebar();
        this.refreshCalendar();
      }
    }

    bindEvents() {
      console.log("🔧 DEBUG: Binding events...");
      $("#btn-add-season")
        .off("click")
        .on("click", () => {
          console.log("🔧 DEBUG: Bouton 'Ajouter une saison' cliqué");
          this.openEditModal("season");
        });
      $("#btn-add-promo")
        .off("click")
        .on("click", () => {
          console.log("🔧 DEBUG: Bouton 'Ajouter une promo' cliqué");
          this.openEditModal("promo");
        });

      // 🔧 FIX CRITIQUE: Corriger les IDs des boutons pour correspondre au HTML
      $("#btn-save-rate-internal")
        .off("click")
        .on("click", () => this.saveInternalModal());
      $("#btn-cancel-rate-internal")
        .off("click")
        .on("click", () => $("#pc-rate-internal-modal").fadeOut());
      $("#btn-delete-rate-internal")
        .off("click")
        .on("click", () => this.deleteInternalItem());

      // 🔧 NOUVEAU: Événement pour l'ajout de période avec Flatpickr
      $("#btn-add-period-range")
        .off("click")
        .on("click", () => this.addPeriodFromRange());

      // Événement pour l'ajout manuel de période (obsolète mais gardé pour compatibilité)
      $("#btn-add-period-manual")
        .off("click")
        .on("click", () => this.addPeriodManually());

      // 🔧 FIX: Délégation d'événements pour les icônes d'édition (éléments dynamiques)
      const self = this; // Stockage de la référence à this pour éviter les problèmes de contexte
      $(document)
        .off("click", ".pc-edit-icon")
        .on("click", ".pc-edit-icon", function (e) {
          e.stopPropagation();
          const parentItem = $(e.target).closest(".pc-draggable-event");
          const itemId = parentItem.attr("data-id");
          const itemType = parentItem.attr("data-type");
          console.log(
            "🔧 DEBUG: Icône d'édition cliquée pour",
            itemType,
            itemId,
          );
          self.openEditModal(itemType, itemId);
        });

      // 🔧 FIX: Délégation d'événements pour cliquer directement sur les éléments de saison/promo
      $(document)
        .off("click", ".pc-draggable-event")
        .on("click", ".pc-draggable-event", function (e) {
          // Ne pas déclencher si on clique sur l'icône d'édition (elle a son propre handler)
          if ($(e.target).hasClass("pc-edit-icon")) {
            return;
          }

          const itemId = $(this).attr("data-id");
          const itemType = $(this).attr("data-type");
          console.log(
            "🔧 DEBUG: Élément cliqué pour édition:",
            itemType,
            itemId,
          );
          self.openEditModal(itemType, itemId);
        });

      // Événement délégué pour la suppression de périodes (boutons générés dynamiquement)
      $(document)
        .off("click", ".pc-remove-period-btn")
        .on("click", ".pc-remove-period-btn", (e) => {
          const periodIndex = parseInt($(e.target).data("period-index"));
          this.removePeriodByIndex(periodIndex);
        });

      // 🔧 NOUVEAU: Événement délégué pour la suppression de périodes temporaires
      $(document)
        .off("click", ".pc-remove-temp-period-btn")
        .on("click", ".pc-remove-temp-period-btn", (e) => {
          const periodIndex = parseInt($(e.target).data("period-index"));
          this.removeTempPeriodByIndex(periodIndex);
        });
    }

    // --- UTILS ---
    stringToColor(str) {
      let hash = 0;
      for (let i = 0; i < str.length; i++)
        hash = str.charCodeAt(i) + ((hash << 5) - hash);
      return `hsl(${Math.abs(hash % 360)}, 70%, 45%)`;
    }

    addDays(dateStr, days) {
      let d = new Date(dateStr);
      d.setDate(d.getDate() + days);
      return d.toISOString().split("T")[0];
    }

    /**
     * Formate une date ISO (YYYY-MM-DD) vers le format français (DD/MM/YYYY)
     * @param {string} dateStr - Date au format ISO
     * @returns {string} Date formatée au format français
     */
    formatDateFR(dateStr) {
      if (!dateStr) return "";

      try {
        const date = new Date(dateStr + "T00:00:00"); // Ajouter l'heure pour éviter les problèmes de timezone
        const day = date.getDate().toString().padStart(2, "0");
        const month = (date.getMonth() + 1).toString().padStart(2, "0");
        const year = date.getFullYear();
        return `${day}/${month}/${year}`;
      } catch (error) {
        console.warn("Erreur lors du formatage de la date:", dateStr, error);
        return dateStr; // Retourner la date originale en cas d'erreur
      }
    }
  }

  // Export global
  window.PCRateManager = PCRateManager;
})(jQuery);
