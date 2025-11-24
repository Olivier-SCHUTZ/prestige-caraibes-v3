(() => {
  "use strict";

  const CONFIG = {
    labelWidth: 200,
    dayWidth: 68,
    cellHeight: 54,
  };

  const texts = (window.pcCalendarData && window.pcCalendarData.i18n) || {};

  class PcDashboardCalendar {
    constructor(root, settings) {
      this.root = root;
      this.settings = settings || {};
      this.gridEl = root.querySelector("[data-pc-cal-grid]");
      this.scrollEl = root.querySelector("[data-pc-cal-scroll]");
      this.errorEl = root.querySelector("[data-pc-cal-error]");
      this.periodEl = root.querySelector("[data-pc-cal-period]");
      this.monthSelect = root.querySelector("[data-pc-cal-month]");
      this.yearSelect = root.querySelector("[data-pc-cal-year]");
      this.todayBtn = root.querySelector("[data-pc-cal-today]");
      this.modalEl = root.querySelector("[data-pc-cal-modal]");
      this.modalGridEl = root.querySelector("[data-pc-cal-modal-grid]");
      this.modalTitleEl = root.querySelector("[data-pc-cal-modal-title]");
      this.modalSubtitleEl = root.querySelector("[data-pc-cal-modal-subtitle]");
      this.modalBarsEl = root.querySelector("[data-pc-cal-modal-bars]");

      // AJOUT : contrôles de navigation dans la modale
      this.modalMonthSelect = root.querySelector("[data-pc-cal-modal-month]");
      this.modalYearSelect = root.querySelector("[data-pc-cal-modal-year]");
      this.modalTodayBtn = root.querySelector("[data-pc-cal-modal-today]");
      this.modalPrevBtn = root.querySelector("[data-pc-cal-modal-prev]");
      this.modalNextBtn = root.querySelector("[data-pc-cal-modal-next]");

      // AJOUT : barre de sélection dans la modale
      this.modalSelectionBarEl = root.querySelector(
        "[data-pc-cal-modal-selection]"
      );
      this.modalSelectionLabelEl = root.querySelector(
        "[data-pc-cal-modal-selection-label]"
      );
      this.modalSelectionCreateReservationBtn = root.querySelector(
        "[data-pc-cal-modal-create-reservation]"
      );
      this.modalSelectionCreateBlockBtn = root.querySelector(
        "[data-pc-cal-modal-create-block]"
      );

      this.currentModalLogementId = null;
      this.modalSelection = null;

      this.currentMonth =
        parseInt(root.getAttribute("data-initial-month"), 10) ||
        new Date().getMonth() + 1;
      this.currentYear =
        parseInt(root.getAttribute("data-initial-year"), 10) ||
        new Date().getFullYear();
      this.labelWidth = this.readCssNumber(
        "--pc-cal-label-width",
        CONFIG.labelWidth
      );
      this.dayWidth = this.readCssNumber("--pc-cal-day-width", CONFIG.dayWidth);
      this.cellHeight = this.readCssNumber(
        "--pc-cal-cell-height",
        CONFIG.cellHeight
      );
      this.currentRange = null;
      this.logements = [];
      this.events = [];
    }

    init() {
      if (!this.gridEl) {
        return;
      }
      this.bindNavigation();
      this.bindModal();
      this.fetchAndRender(this.currentMonth, this.currentYear);
    }

    bindNavigation() {
      if (!this.monthSelect || !this.yearSelect) {
        return;
      }

      // Période d'années : N-2 à N+4
      const now = new Date();
      const currentYear = now.getUTCFullYear();
      const minYear = currentYear - 2;
      const maxYear = currentYear + 4;

      // Remplir les mois 1..12
      this.monthSelect.innerHTML = "";
      const monthFormatter = new Intl.DateTimeFormat("fr-FR", {
        month: "short",
        timeZone: "UTC",
      });

      for (let m = 1; m <= 12; m += 1) {
        const d = new Date(Date.UTC(2000, m - 1, 1));
        const opt = document.createElement("option");
        opt.value = String(m);
        opt.textContent = monthFormatter.format(d);
        this.monthSelect.appendChild(opt);
      }

      // Remplir les années minYear..maxYear
      this.yearSelect.innerHTML = "";
      for (let y = minYear; y <= maxYear; y += 1) {
        const opt = document.createElement("option");
        opt.value = String(y);
        opt.textContent = String(y);
        this.yearSelect.appendChild(opt);
      }

      // Valeurs initiales basées sur le mois/année courants du calendrier
      this.monthSelect.value = String(this.currentMonth);
      this.yearSelect.value = String(this.currentYear);

      const onChange = () => {
        const month = parseInt(this.monthSelect.value, 10);
        const year = parseInt(this.yearSelect.value, 10);
        if (!Number.isNaN(month) && !Number.isNaN(year)) {
          this.fetchAndRender(month, year);
        }
      };

      this.monthSelect.addEventListener("change", onChange);
      this.yearSelect.addEventListener("change", onChange);

      // Bouton "Aujourd'hui"
      if (this.todayBtn) {
        this.todayBtn.addEventListener("click", () => {
          const today = new Date();
          const month = today.getUTCMonth() + 1;
          const year = today.getUTCFullYear();

          // Si l'année n'est pas dans la liste, on l'ajoute
          if (!this.yearSelect.querySelector(`option[value="${year}"]`)) {
            const opt = document.createElement("option");
            opt.value = String(year);
            opt.textContent = String(year);
            this.yearSelect.appendChild(opt);
          }

          this.monthSelect.value = String(month);
          this.yearSelect.value = String(year);
          this.fetchAndRender(month, year);
        });
      }
    }

    bindModal() {
      if (!this.modalEl) {
        return;
      }
      const closeBtn = this.modalEl.querySelector("[data-pc-cal-close]");
      if (closeBtn) {
        closeBtn.addEventListener("click", () => this.closeModal());
      }
      this.modalEl.addEventListener("click", (event) => {
        if (event.target === this.modalEl) {
          this.closeModal();
        }
      });

      // AJOUT : navigation mois/année dans la modale
      if (this.modalMonthSelect && this.modalYearSelect) {
        // même plage : N-2 à N+4
        const now = new Date();
        const currentYear = now.getUTCFullYear();
        const minYear = currentYear - 2;
        const maxYear = currentYear + 4;

        // Mois
        this.modalMonthSelect.innerHTML = "";
        const monthFormatter = new Intl.DateTimeFormat("fr-FR", {
          month: "short",
          timeZone: "UTC",
        });
        for (let m = 1; m <= 12; m += 1) {
          const d = new Date(Date.UTC(2000, m - 1, 1));
          const opt = document.createElement("option");
          opt.value = String(m);
          opt.textContent = monthFormatter.format(d);
          this.modalMonthSelect.appendChild(opt);
        }

        // Années
        this.modalYearSelect.innerHTML = "";
        for (let y = minYear; y <= maxYear; y += 1) {
          const opt = document.createElement("option");
          opt.value = String(y);
          opt.textContent = String(y);
          this.modalYearSelect.appendChild(opt);
        }

        // valeurs initiales
        this.modalMonthSelect.value = String(this.currentMonth);
        this.modalYearSelect.value = String(this.currentYear);

        const onChangeModalNav = () => {
          const month = parseInt(this.modalMonthSelect.value, 10);
          const year = parseInt(this.modalYearSelect.value, 10);
          if (!Number.isNaN(month) && !Number.isNaN(year)) {
            this.fetchAndRender(month, year);
          }
        };

        this.modalMonthSelect.addEventListener("change", onChangeModalNav);
        this.modalYearSelect.addEventListener("change", onChangeModalNav);
      }

      if (this.modalTodayBtn && this.modalMonthSelect && this.modalYearSelect) {
        this.modalTodayBtn.addEventListener("click", () => {
          const today = new Date();
          const month = today.getUTCMonth() + 1;
          const year = today.getUTCFullYear();

          // s'assurer que l'année existe dans la liste
          if (!this.modalYearSelect.querySelector(`option[value="${year}"]`)) {
            const opt = document.createElement("option");
            opt.value = String(year);
            opt.textContent = String(year);
            this.modalYearSelect.appendChild(opt);
          }

          this.modalMonthSelect.value = String(month);
          this.modalYearSelect.value = String(year);
          this.fetchAndRender(month, year);
        });
      }

      // Flèche "<" dans la zone scrollable
      if (this.modalPrevBtn && this.modalMonthSelect && this.modalYearSelect) {
        this.modalPrevBtn.addEventListener("click", () => {
          const { month, year } = this.getNextMonthYear(-1);

          if (!this.modalYearSelect.querySelector(`option[value="${year}"]`)) {
            const opt = document.createElement("option");
            opt.value = String(year);
            opt.textContent = String(year);
            this.modalYearSelect.appendChild(opt);
          }

          this.modalMonthSelect.value = String(month);
          this.modalYearSelect.value = String(year);
          this.fetchAndRender(month, year);
        });
      }

      // Flèche ">" dans la zone scrollable
      if (this.modalNextBtn && this.modalMonthSelect && this.modalYearSelect) {
        this.modalNextBtn.addEventListener("click", () => {
          const { month, year } = this.getNextMonthYear(1);

          if (!this.modalYearSelect.querySelector(`option[value="${year}"]`)) {
            const opt = document.createElement("option");
            opt.value = String(year);
            opt.textContent = String(year);
            this.modalYearSelect.appendChild(opt);
          }

          this.modalMonthSelect.value = String(month);
          this.modalYearSelect.value = String(year);
          this.fetchAndRender(month, year);
        });
      }

      // AJOUT : actions de sélection (pour l'instant, simple log)
      if (this.modalSelectionCreateReservationBtn) {
        this.modalSelectionCreateReservationBtn.addEventListener(
          "click",
          () => {
            if (!this.modalSelection) return;
            // eslint-disable-next-line no-console
            console.log(
              "[pc-calendar] create reservation from selection",
              this.modalSelection
            );
          }
        );
      }

      if (this.modalSelectionCreateBlockBtn) {
        this.modalSelectionCreateBlockBtn.addEventListener("click", () => {
          if (!this.modalSelection) return;
          // eslint-disable-next-line no-console
          console.log(
            "[pc-calendar] create manual block from selection",
            this.modalSelection
          );
        });
      }
    }

    async fetchAndRender(month, year) {
      this.setError("");
      this.setLoading(true);

      try {
        const payload = await this.fetchCalendar(month, year);
        if (!payload) {
          throw new Error(texts.error || "Erreur lors du chargement.");
        }
        this.currentMonth = payload.month;
        this.currentYear = payload.year;
        this.currentRange = {
          start: payload.start_date,
          end: payload.end_date,
          extendedEnd: payload.extended_end,
        };
        this.logements = payload.logements || [];
        this.events = payload.events || [];
        this.syncSelectors();
        this.render();

        // AJOUT : si la modale est ouverte, on met à jour le planning unique aussi
        if (
          this.modalEl &&
          this.modalEl.classList.contains("is-open") &&
          this.currentModalLogementId
        ) {
          const logement = this.logements.find(
            (lg) =>
              parseInt(lg.id, 10) === parseInt(this.currentModalLogementId, 10)
          );
          if (logement) {
            this.buildModalCalendar(logement);
          }
        }
      } catch (err) {
        this.setError(err.message || texts.error || "Erreur AJAX");
        this.gridEl.innerHTML = "";
      } finally {
        this.setLoading(false);
      }
    }

    async fetchCalendar(month, year) {
      const ajaxUrl =
        (this.settings && this.settings.ajaxUrl) ||
        (window.pcCalendarData && window.pcCalendarData.ajaxUrl);
      const nonce =
        (this.settings && this.settings.nonce) ||
        (window.pcCalendarData && window.pcCalendarData.nonce);
      const body = new URLSearchParams();
      body.append("action", "pc_get_calendar_global");
      body.append("nonce", nonce || "");
      body.append("month", month);
      body.append("year", year);

      const response = await fetch(ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: body.toString(),
      });

      if (!response.ok) {
        throw new Error(texts.error || "Requête impossible.");
      }

      const json = await response.json();
      if (!json || !json.success) {
        const message =
          json && json.data && json.data.message
            ? json.data.message
            : texts.error || "Requête échouée.";
        throw new Error(message);
      }

      return json.data;
    }

    render() {
      if (!Array.isArray(this.logements) || this.logements.length === 0) {
        this.gridEl.innerHTML = `<p class="pc-cal-empty">${
          texts.empty || "Aucun logement actif."
        }</p>`;
        this.updatePeriodLabel();
        return;
      }

      const dates = this.buildDateArray(
        this.currentRange.start,
        this.currentRange.extendedEnd
      );
      this.dates = dates;
      this.dateIndex = new Map(dates.map((d, idx) => [d, idx]));
      const columnTemplate = ` ${this.labelWidth}px repeat(${dates.length}, ${this.dayWidth}px) `;
      this.gridEl.innerHTML = "";
      this.gridEl.style.minWidth = `${
        this.labelWidth + dates.length * this.dayWidth
      }px`;

      this.renderHeaderRow(dates, columnTemplate);
      this.logements.forEach((lg) => {
        this.renderLogementRow(lg, dates, columnTemplate);
      });

      this.updatePeriodLabel();
    }

    renderHeaderRow(dates, template) {
      const row = document.createElement("div");
      row.className = "pc-cal-row pc-cal-row--header";
      row.style.gridTemplateColumns = template;

      const corner = document.createElement("div");
      corner.className = "pc-cal-corner";
      corner.textContent = "Logement";
      row.appendChild(corner);

      dates.forEach((dateStr) => {
        const dateObj = this.parseDate(dateStr);
        const cell = document.createElement("div");
        cell.className = "pc-cal-header-cell";
        cell.dataset.date = dateStr;

        // AJOUT : gestion aujourd'hui et jours passés
        const todayISO = this.toISO(new Date());
        if (dateStr === todayISO) {
          cell.classList.add("pc-cal-day--today");
        } else if (dateStr < todayISO) {
          cell.classList.add("pc-cal-day--past");
        }
        cell.innerHTML = `
    <span class="pc-cal-header-cell__month">${this.formatMonthShort(
      dateObj
    )}</span>
    <span class="pc-cal-header-cell__dow">${this.formatDayOfWeek(
      dateObj
    )}</span>
    <span class="pc-cal-header-cell__day">${this.formatDay(dateObj)}</span>
`;
        row.appendChild(cell);
      });

      this.gridEl.appendChild(row);
    }

    renderLogementRow(logement, dates, template) {
      const row = document.createElement("div");
      row.className = "pc-cal-row";
      row.style.gridTemplateColumns = template;
      row.dataset.logementId = logement.id;
      row.style.setProperty("--pc-cal-label-width", `${this.labelWidth}px`);
      row.style.setProperty("--pc-cal-cell-height", `${this.cellHeight}px`);

      const label = document.createElement("div");
      label.className = "pc-cal-row-label";
      label.setAttribute("role", "button");
      label.setAttribute("tabindex", "0");
      label.dataset.logementId = logement.id;
      label.textContent = logement.title || `#${logement.id}`;
      label.addEventListener("click", () => this.openModal(logement.id));
      label.addEventListener("keydown", (event) => {
        if (event.key === "Enter" || event.key === " ") {
          event.preventDefault();
          this.openModal(logement.id);
        }
      });
      row.appendChild(label);

      dates.forEach((dateStr) => {
        const cell = document.createElement("div");
        cell.className = "pc-cal-cell";
        cell.dataset.logementId = logement.id;
        cell.dataset.date = dateStr;

        // AJOUT : gestion aujourd'hui et jours passés
        const todayISO = this.toISO(new Date());
        if (dateStr === todayISO) {
          cell.classList.add("pc-cal-day--today");
        } else if (dateStr < todayISO) {
          cell.classList.add("pc-cal-day--past");
        }
        cell.addEventListener("click", () => {
          // Préparation pour la sélection future : on logge simplement.
          // eslint-disable-next-line no-console
          console.log("[pc-calendar] cell", logement.id, dateStr);
        });
        row.appendChild(cell);
      });

      const eventsLayer = document.createElement("div");
      eventsLayer.className = "pc-cal-row-events";
      eventsLayer.style.left = `${this.labelWidth}px`;
      eventsLayer.style.right = "0";
      eventsLayer.style.height = `${this.cellHeight - 12}px`;
      row.appendChild(eventsLayer);

      this.gridEl.appendChild(row);
      this.renderEventsForRow(logement.id, eventsLayer);
    }

    renderEventsForRow(logementId, layer) {
      if (!this.events || this.events.length === 0) {
        return;
      }
      const events = this.events.filter(
        (evt) => parseInt(evt.logement_id, 10) === parseInt(logementId, 10)
      );
      events.forEach((evt) => {
        const startIdx = this.computeIndexForDate(evt.start_date, true);
        const endIdx = this.computeIndexForDate(evt.end_date, false);
        if (
          startIdx === null ||
          endIdx === null ||
          endIdx < 0 ||
          startIdx > this.dates.length - 1
        ) {
          return;
        }
        const clampedStart = Math.max(0, startIdx);
        const clampedEnd = Math.min(this.dates.length - 1, endIdx);
        if (clampedEnd < clampedStart) {
          return;
        }

        const bar = document.createElement("div");
        const source = evt.source || "reservation";
        bar.className = `pc-cal-event pc-cal-event--${source}`;
        bar.style.left = `${clampedStart * this.dayWidth}px`;
        bar.style.width = `${
          (clampedEnd - clampedStart + 1) * this.dayWidth - 8
        }px`;
        bar.title = `${source || ""} : ${evt.start_date} → ${evt.end_date}`;

        // 🔹 Texte affiché dans la barre (aligné à gauche)
        let labelText = "";
        if (source === "ical") {
          labelText = "Réservation iCal propriétaire";
        } else if (source === "manual") {
          labelText = "Blocage manuel";
        } else if (source === "reservation" && evt.label) {
          // Réservation interne : on utilisera evt.label plus tard (nom client, ref…)
          labelText = evt.label;
        }

        if (labelText) {
          const span = document.createElement("span");
          span.className = "pc-cal-event__label";
          span.textContent = labelText;
          bar.appendChild(span);
        }

        layer.appendChild(bar);
      });
    }

    openModal(logementId) {
      const logement = this.logements.find(
        (lg) => parseInt(lg.id, 10) === parseInt(logementId, 10)
      );
      if (!logement || !this.modalEl) {
        return;
      }

      // AJOUT : mémoriser le logement courant pour le planning unique
      this.currentModalLogementId = logement.id;

      // synchroniser les selects de la modale sur le mois courant
      if (this.modalMonthSelect && this.modalYearSelect) {
        this.modalMonthSelect.value = String(this.currentMonth);
        this.modalYearSelect.value = String(this.currentYear);
      }

      // 🔹 Rendre la modale visible AVANT de mesurer la grille
      this.modalEl.hidden = false;
      this.modalEl.classList.add("is-open");

      // Reset sélection
      this.modalSelection = null;
      this.updateModalSelectionPanel();
      this.updateModalSelectionUI();

      // 🔹 Maintenant on peut construire le planning unique (barres calculées correctement)
      this.buildModalCalendar(logement);
    }

    closeModal() {
      if (!this.modalEl) {
        return;
      }
      this.modalEl.classList.remove("is-open");
      this.modalEl.hidden = true;
      this.currentModalLogementId = null;
      this.modalSelection = null;
      this.updateModalSelectionPanel();
      if (this.modalGridEl) {
        this.modalGridEl.innerHTML = "";
      }
    }

    buildModalCalendar(logement) {
      if (!this.modalGridEl) {
        return;
      }

      const rangeStart = this.parseDate(this.currentRange.start);
      const rangeEnd = this.parseDate(this.currentRange.extendedEnd);
      if (!rangeStart || !rangeEnd) {
        return;
      }

      // nombre de jours entre start et extendedEnd (1 mois + 15 jours)
      const MS_PER_DAY = 24 * 60 * 60 * 1000;
      const daysInRange =
        Math.round((rangeEnd.getTime() - rangeStart.getTime()) / MS_PER_DAY) +
        1;

      if (this.modalTitleEl) {
        this.modalTitleEl.textContent = logement.title || `#${logement.id}`;
      }
      if (this.modalSubtitleEl) {
        const formatter = new Intl.DateTimeFormat("fr-FR", {
          month: "long",
          year: "numeric",
          timeZone: "UTC",
        });
        this.modalSubtitleEl.textContent = formatter.format(rangeStart);
      }

      const busyDates = this.collectBusyDates(
        logement.id,
        this.currentRange.start,
        this.currentRange.extendedEnd
      );

      // 🔹 Timeline linéaire : 1 colonne = 1 jour sur toute la période
      this.modalGridEl.innerHTML = "";
      this.modalGridEl.style.setProperty("--pc-cal-modal-columns", daysInRange);

      const todayISO = this.toISO(new Date());

      for (let offset = 0; offset < daysInRange; offset += 1) {
        const dateObj = new Date(rangeStart);
        dateObj.setUTCDate(rangeStart.getUTCDate() + offset);

        const iso = this.toISO(dateObj);
        const cell = document.createElement("div");
        cell.className = "pc-cal-modal__cell";
        cell.dataset.logementId = logement.id;
        cell.dataset.date = iso;
        cell.innerHTML = `<span class="pc-cal-modal__day">${dateObj.getUTCDate()}</span>`;

        // AJOUT : couleurs aujourd'hui / jours passés
        if (iso === todayISO) {
          cell.classList.add("pc-cal-day--today");
        } else if (iso < todayISO) {
          cell.classList.add("pc-cal-day--past");
        }

        if (busyDates.has(iso)) {
          cell.classList.add("is-busy");
        }

        cell.addEventListener("click", () => {
          this.handleModalCellClick(logement.id, iso, busyDates);
        });

        this.modalGridEl.appendChild(cell);
      }

      // Après avoir regénéré la grille, on met à jour le visuel de sélection
      this.updateModalSelectionUI();
      this.updateModalSelectionPanel();

      // 🔹 Barres continues par réservation
      this.renderModalBars(logement.id, rangeStart, rangeEnd);
    }

    collectBusyDates(logementId, start, end) {
      const busy = new Set();
      const events = this.events.filter(
        (evt) => parseInt(evt.logement_id, 10) === parseInt(logementId, 10)
      );
      const monthStart = this.parseDate(start);
      const monthEnd = this.parseDate(end);

      events.forEach((evt) => {
        const eventStart = this.parseDate(evt.start_date);
        const eventEnd = this.parseDate(evt.end_date);
        if (!eventStart || !eventEnd) {
          return;
        }
        const clampedStart = eventStart < monthStart ? monthStart : eventStart;
        const clampedEnd = eventEnd > monthEnd ? monthEnd : eventEnd;

        let cursor = new Date(clampedStart);
        while (cursor <= clampedEnd) {
          busy.add(this.toISO(cursor));
          cursor.setUTCDate(cursor.getUTCDate() + 1);
        }
      });

      return busy;
    }

    /**
     * Planning unique : dessine 1 barre <div> par réservation
     * sur la timeline (rangeStart → rangeEnd).
     */
    renderModalBars(logementId, rangeStart, rangeEnd) {
      if (!this.modalBarsEl || !this.modalGridEl) {
        return;
      }

      this.modalBarsEl.innerHTML = "";

      // Filtrer les événements du logement
      const events = (this.events || []).filter(
        (evt) => parseInt(evt.logement_id, 10) === parseInt(logementId, 10)
      );
      if (!events.length) {
        return;
      }

      const containerRect = this.modalGridEl.getBoundingClientRect();
      const todayISO = this.toISO(new Date());

      events.forEach((evt) => {
        const eventStart = this.parseDate(evt.start_date);
        const eventEnd = this.parseDate(evt.end_date);
        if (!eventStart || !eventEnd) {
          return;
        }

        // On recadre la réservation sur la plage visible
        const clampedStart = eventStart < rangeStart ? rangeStart : eventStart;
        const clampedEnd = eventEnd > rangeEnd ? rangeEnd : eventEnd;
        if (clampedEnd < clampedStart) {
          return;
        }

        const startIso = this.toISO(clampedStart);
        const endIso = this.toISO(clampedEnd);

        const startCell = this.modalGridEl.querySelector(
          `.pc-cal-modal__cell[data-date="${startIso}"]`
        );
        const endCell = this.modalGridEl.querySelector(
          `.pc-cal-modal__cell[data-date="${endIso}"]`
        );

        if (!startCell || !endCell) {
          return;
        }

        const startRect = startCell.getBoundingClientRect();
        const endRect = endCell.getBoundingClientRect();

        const left = startRect.left - containerRect.left + 4;
        const right = endRect.right - containerRect.left - 4;
        const width = right - left;

        if (width <= 0) {
          return;
        }

        // On place la barre au milieu vertical des cellules
        const top =
          startRect.top - containerRect.top + startRect.height / 2 - 12 + 8;

        const bar = document.createElement("div");
        const source = evt.source || "reservation";
        bar.className = `pc-cal-modal-bar pc-cal-modal-bar--${source}`;
        bar.style.left = `${left}px`;
        bar.style.top = `${top}px`;
        bar.style.width = `${width}px`;

        // Préparation future : on pourra mettre du texte dans la barre
        bar.title = `${evt.start_date} → ${evt.end_date}`;

        // 🔹 Texte affiché dans la barre (aligné à gauche)
        let labelText = "";
        if (source === "ical") {
          labelText = "Réservation iCal propriétaire";
        } else if (source === "manual") {
          labelText = "Blocage manuel";
        } else if (source === "reservation" && evt.label) {
          labelText = evt.label;
        }

        if (labelText) {
          const span = document.createElement("span");
          span.className = "pc-cal-modal-bar__label";
          span.textContent = labelText;
          bar.appendChild(span);
        }

        this.modalBarsEl.appendChild(bar);
      });
    }

    /**
     * Gestion du clic sur une cellule de la modale (sélection de période).
     * - Uniquement sur des cases libres (non is-busy)
     * - 1er clic = start
     * - 2e clic = étend la sélection, sans traverser des jours occupés
     * - clic sur le même jour = annule la sélection
     */
    handleModalCellClick(logementId, iso, busyDates) {
      // On ne sélectionne jamais un jour occupé
      if (busyDates.has(iso)) {
        return;
      }

      // Si pas de sélection ou logement différent -> nouvelle sélection
      if (
        !this.modalSelection ||
        this.modalSelection.logementId !== logementId ||
        !this.modalSelection.start
      ) {
        this.modalSelection = { logementId, start: iso, end: iso };
        this.updateModalSelectionUI();
        this.updateModalSelectionPanel();
        return;
      }

      const currentStart = this.modalSelection.start;
      const currentEnd = this.modalSelection.end;

      // Clic sur le même jour que la sélection simple -> reset
      if (currentStart === iso && currentEnd === iso) {
        this.modalSelection = null;
        this.updateModalSelectionUI();
        this.updateModalSelectionPanel();
        return;
      }

      const startDate = this.parseDate(currentStart);
      const endDate = this.parseDate(currentEnd);
      const clickedDate = this.parseDate(iso);
      if (!startDate || !clickedDate) {
        return;
      }

      // Calcule le nouvel intervalle [newStart, newEnd]
      let newStart = currentStart;
      let newEnd = currentEnd;

      if (clickedDate < startDate) {
        newStart = iso;
        newEnd = currentEnd;
      } else {
        newStart = currentStart;
        newEnd = iso;
      }

      const sDate = this.parseDate(newStart);
      const eDate = this.parseDate(newEnd);
      if (!sDate || !eDate) {
        return;
      }

      // Vérifie qu'il n'y a pas de jour occupé dans l'intervalle
      let hasBusy = false;
      const cursor = new Date(sDate);
      while (cursor <= eDate) {
        const dIso = this.toISO(cursor);
        if (busyDates.has(dIso)) {
          hasBusy = true;
          break;
        }
        cursor.setUTCDate(cursor.getUTCDate() + 1);
      }

      // Si la plage traverse un jour occupé -> on ignore l'extension
      if (hasBusy) {
        return;
      }

      this.modalSelection = { logementId, start: newStart, end: newEnd };
      this.updateModalSelectionUI();
      this.updateModalSelectionPanel();
    }

    /**
     * Met à jour les classes CSS des cellules de la modale
     * en fonction de this.modalSelection.
     */
    updateModalSelectionUI() {
      if (!this.modalGridEl) {
        return;
      }
      const cells = this.modalGridEl.querySelectorAll(".pc-cal-modal__cell");
      cells.forEach((cell) => {
        cell.classList.remove("pc-cal-modal__cell--selected");
      });

      if (!this.modalSelection) {
        return;
      }

      const { logementId, start, end } = this.modalSelection;
      const startDate = this.parseDate(start);
      const endDate = this.parseDate(end);
      if (!startDate || !endDate) {
        return;
      }

      cells.forEach((cell) => {
        const cellLogementId = parseInt(cell.dataset.logementId || "0", 10);
        const cellDateStr = cell.dataset.date;
        if (!cellDateStr || cellLogementId !== parseInt(logementId, 10)) {
          return;
        }
        const cellDate = this.parseDate(cellDateStr);
        if (!cellDate) {
          return;
        }
        if (cellDate >= startDate && cellDate <= endDate) {
          cell.classList.add("pc-cal-modal__cell--selected");
        }
      });
    }

    /**
     * Met à jour la barre de sélection dans le header de la modale.
     */
    updateModalSelectionPanel() {
      if (!this.modalSelectionBarEl || !this.modalSelectionLabelEl) {
        return;
      }

      if (!this.modalSelection) {
        this.modalSelectionBarEl.hidden = true;
        this.modalSelectionLabelEl.textContent = "";
        return;
      }

      const { start, end } = this.modalSelection;
      const startDate = this.parseDate(start);
      const endDate = this.parseDate(end);
      if (!startDate || !endDate) {
        this.modalSelectionBarEl.hidden = true;
        this.modalSelectionLabelEl.textContent = "";
        return;
      }

      const formatter = new Intl.DateTimeFormat("fr-FR", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        timeZone: "UTC",
      });

      const labelText =
        start === end
          ? `Jour sélectionné : ${formatter.format(startDate)}`
          : `Période sélectionnée : ${formatter.format(
              startDate
            )} → ${formatter.format(endDate)}`;

      this.modalSelectionLabelEl.textContent = labelText;
      this.modalSelectionBarEl.hidden = false;
    }

    updatePeriodLabel() {
      if (!this.periodEl || !this.currentRange) {
        return;
      }
      const formatter = new Intl.DateTimeFormat("fr-FR", {
        month: "long",
        year: "numeric",
      });
      const startDate = this.parseDate(this.currentRange.start);
      const endDate = this.parseDate(this.currentRange.extendedEnd);
      this.periodEl.textContent = `${formatter.format(
        startDate
      )} → ${formatter.format(endDate)}`;
    }

    buildDateArray(start, end) {
      const dates = [];
      const cursor = this.parseDate(start);
      const endDate = this.parseDate(end);
      if (!cursor || !endDate) {
        return dates;
      }
      while (cursor <= endDate) {
        dates.push(this.toISO(cursor));
        cursor.setUTCDate(cursor.getUTCDate() + 1);
      }
      return dates;
    }

    computeIndexForDate(dateStr, clampToStart) {
      if (this.dateIndex && this.dateIndex.has(dateStr)) {
        return this.dateIndex.get(dateStr);
      }
      if (!this.dates || this.dates.length === 0) {
        return null;
      }
      const target = this.parseDate(dateStr);
      const first = this.parseDate(this.dates[0]);
      if (!target || !first) {
        return null;
      }
      const diff = Math.floor((target - first) / (1000 * 60 * 60 * 24));
      if (clampToStart && diff < 0) {
        return 0;
      }
      return diff;
    }

    getNextMonthYear(delta) {
      const current = new Date(this.currentYear, this.currentMonth - 1, 1);
      current.setMonth(current.getMonth() + delta);
      return {
        month: current.getMonth() + 1,
        year: current.getFullYear(),
      };
    }

    parseDate(dateStr) {
      if (!dateStr) return null;
      const parts = dateStr.split("-");
      if (parts.length !== 3) return null;
      return new Date(
        Date.UTC(
          parseInt(parts[0], 10),
          parseInt(parts[1], 10) - 1,
          parseInt(parts[2], 10)
        )
      );
    }

    toISO(dateObj) {
      return dateObj.toISOString().slice(0, 10);
    }

    formatDayOfWeek(date) {
      const formatter = new Intl.DateTimeFormat("fr-FR", {
        weekday: "short",
        timeZone: "UTC",
      });
      return formatter.format(date);
    }

    formatDay(date) {
      return String(date.getUTCDate()).padStart(2, "0");
    }

    formatMonthShort(date) {
      const f = new Intl.DateTimeFormat("fr-FR", {
        month: "short",
        timeZone: "UTC",
      });
      return f.format(date);
    }

    getMondayBasedDay(dateObj) {
      // getUTCDay: 0 (dimanche) -> 6 (samedi) ; on veut lundi = 0
      const day = dateObj.getUTCDay();
      return (day + 6) % 7;
    }

    readCssNumber(varName, fallback) {
      const value = getComputedStyle(document.documentElement).getPropertyValue(
        varName
      );
      const parsed = parseFloat(value);
      if (Number.isNaN(parsed)) {
        return fallback;
      }
      return parsed;
    }

    syncSelectors() {
      if (!this.monthSelect || !this.yearSelect) {
        return;
      }

      // Si l'année courante n'est pas dans la liste (ex : hors N-2..N+4), on l'ajoute
      if (
        !this.yearSelect.querySelector(`option[value="${this.currentYear}"]`)
      ) {
        const opt = document.createElement("option");
        opt.value = String(this.currentYear);
        opt.textContent = String(this.currentYear);
        this.yearSelect.appendChild(opt);
      }

      this.monthSelect.value = String(this.currentMonth);
      this.yearSelect.value = String(this.currentYear);
    }

    setLoading(isLoading) {
      if (!this.gridEl) return;
      this.gridEl.classList.toggle("is-loading", Boolean(isLoading));
      if (isLoading) {
        this.gridEl.innerHTML = `<p class="pc-cal-loading">${
          texts.loading || "Chargement..."
        }</p>`;
      }
    }

    setError(message) {
      if (!this.errorEl) {
        return;
      }
      if (!message) {
        this.errorEl.hidden = true;
        this.errorEl.textContent = "";
        return;
      }
      this.errorEl.hidden = false;
      this.errorEl.textContent = message;
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    const root = document.querySelector("[data-pc-calendar]");
    if (!root) {
      return;
    }
    const instance = new PcDashboardCalendar(root, window.pcCalendarData || {});
    instance.init();
  });
})();
