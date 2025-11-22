(() => {
  'use strict';

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
      this.gridEl = root.querySelector('[data-pc-cal-grid]');
      this.scrollEl = root.querySelector('[data-pc-cal-scroll]');
      this.errorEl = root.querySelector('[data-pc-cal-error]');
      this.periodEl = root.querySelector('[data-pc-cal-period]');
      this.modalEl = root.querySelector('[data-pc-cal-modal]');
      this.modalGridEl = root.querySelector('[data-pc-cal-modal-grid]');
      this.modalTitleEl = root.querySelector('[data-pc-cal-modal-title]');
      this.modalSubtitleEl = root.querySelector('[data-pc-cal-modal-subtitle]');
      this.currentMonth = parseInt(root.getAttribute('data-initial-month'), 10) || new Date().getMonth() + 1;
      this.currentYear = parseInt(root.getAttribute('data-initial-year'), 10) || new Date().getFullYear();
      this.labelWidth = this.readCssNumber('--pc-cal-label-width', CONFIG.labelWidth);
      this.dayWidth = this.readCssNumber('--pc-cal-day-width', CONFIG.dayWidth);
      this.cellHeight = this.readCssNumber('--pc-cal-cell-height', CONFIG.cellHeight);
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
      const navButtons = this.root.querySelectorAll('[data-pc-cal-nav]');
      navButtons.forEach((btn) => {
        btn.addEventListener('click', (event) => {
          const dir = event.currentTarget.getAttribute('data-pc-cal-nav');
          const next = this.getNextMonthYear(dir === 'next' ? 1 : -1);
          this.fetchAndRender(next.month, next.year);
        });
      });
    }

    bindModal() {
      if (!this.modalEl) {
        return;
      }
      const closeBtn = this.modalEl.querySelector('[data-pc-cal-close]');
      if (closeBtn) {
        closeBtn.addEventListener('click', () => this.closeModal());
      }
      this.modalEl.addEventListener('click', (event) => {
        if (event.target === this.modalEl) {
          this.closeModal();
        }
      });
    }

    async fetchAndRender(month, year) {
      this.setError('');
      this.setLoading(true);

      try {
        const payload = await this.fetchCalendar(month, year);
        if (!payload) {
          throw new Error(texts.error || 'Erreur lors du chargement.');
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
        this.render();
      } catch (err) {
        this.setError(err.message || texts.error || 'Erreur AJAX');
        this.gridEl.innerHTML = '';
      } finally {
        this.setLoading(false);
      }
    }

    async fetchCalendar(month, year) {
      const ajaxUrl = (this.settings && this.settings.ajaxUrl) || (window.pcCalendarData && window.pcCalendarData.ajaxUrl);
      const nonce = (this.settings && this.settings.nonce) || (window.pcCalendarData && window.pcCalendarData.nonce);
      const body = new URLSearchParams();
      body.append('action', 'pc_get_calendar_global');
      body.append('nonce', nonce || '');
      body.append('month', month);
      body.append('year', year);

      const response = await fetch(ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
      });

      if (!response.ok) {
        throw new Error(texts.error || 'Requête impossible.');
      }

      const json = await response.json();
      if (!json || !json.success) {
        const message = json && json.data && json.data.message ? json.data.message : (texts.error || 'Requête échouée.');
        throw new Error(message);
      }

      return json.data;
    }

    render() {
      if (!Array.isArray(this.logements) || this.logements.length === 0) {
        this.gridEl.innerHTML = `<p class="pc-cal-empty">${texts.empty || 'Aucun logement actif.'}</p>`;
        this.updatePeriodLabel();
        return;
      }

      const dates = this.buildDateArray(this.currentRange.start, this.currentRange.extendedEnd);
      this.dates = dates;
      this.dateIndex = new Map(dates.map((d, idx) => [d, idx]));
      const columnTemplate = ` ${this.labelWidth}px repeat(${dates.length}, ${this.dayWidth}px) `;
      this.gridEl.innerHTML = '';
      this.gridEl.style.minWidth = `${this.labelWidth + dates.length * this.dayWidth}px`;

      this.renderHeaderRow(dates, columnTemplate);
      this.logements.forEach((lg) => {
        this.renderLogementRow(lg, dates, columnTemplate);
      });

      this.updatePeriodLabel();
    }

    renderHeaderRow(dates, template) {
      const row = document.createElement('div');
      row.className = 'pc-cal-row pc-cal-row--header';
      row.style.gridTemplateColumns = template;

      const corner = document.createElement('div');
      corner.className = 'pc-cal-corner';
      corner.textContent = 'Logement';
      row.appendChild(corner);

      dates.forEach((dateStr) => {
        const dateObj = this.parseDate(dateStr);
        const cell = document.createElement('div');
        cell.className = 'pc-cal-header-cell';
        cell.dataset.date = dateStr;
        cell.innerHTML = `<span class="pc-cal-header-cell__dow">${this.formatDayOfWeek(dateObj)}</span><span class="pc-cal-header-cell__day">${this.formatDay(dateObj)}</span>`;
        row.appendChild(cell);
      });

      this.gridEl.appendChild(row);
    }

    renderLogementRow(logement, dates, template) {
      const row = document.createElement('div');
      row.className = 'pc-cal-row';
      row.style.gridTemplateColumns = template;
      row.dataset.logementId = logement.id;
      row.style.setProperty('--pc-cal-label-width', `${this.labelWidth}px`);
      row.style.setProperty('--pc-cal-cell-height', `${this.cellHeight}px`);

      const label = document.createElement('div');
      label.className = 'pc-cal-row-label';
      label.setAttribute('role', 'button');
      label.setAttribute('tabindex', '0');
      label.dataset.logementId = logement.id;
      label.textContent = logement.title || `#${logement.id}`;
      label.addEventListener('click', () => this.openModal(logement.id));
      label.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          this.openModal(logement.id);
        }
      });
      row.appendChild(label);

      dates.forEach((dateStr) => {
        const cell = document.createElement('div');
        cell.className = 'pc-cal-cell';
        cell.dataset.logementId = logement.id;
        cell.dataset.date = dateStr;
        cell.addEventListener('click', () => {
          // Préparation pour la sélection future : on logge simplement.
          // eslint-disable-next-line no-console
          console.log('[pc-calendar] cell', logement.id, dateStr);
        });
        row.appendChild(cell);
      });

      const eventsLayer = document.createElement('div');
      eventsLayer.className = 'pc-cal-row-events';
      eventsLayer.style.left = `${this.labelWidth}px`;
      eventsLayer.style.right = '0';
      eventsLayer.style.height = `${this.cellHeight - 12}px`;
      row.appendChild(eventsLayer);

      this.gridEl.appendChild(row);
      this.renderEventsForRow(logement.id, eventsLayer);
    }

    renderEventsForRow(logementId, layer) {
      if (!this.events || this.events.length === 0) {
        return;
      }
      const events = this.events.filter((evt) => parseInt(evt.logement_id, 10) === parseInt(logementId, 10));
      events.forEach((evt) => {
        const startIdx = this.computeIndexForDate(evt.start_date, true);
        const endIdx = this.computeIndexForDate(evt.end_date, false);
        if (startIdx === null || endIdx === null || endIdx < 0 || startIdx > this.dates.length - 1) {
          return;
        }
        const clampedStart = Math.max(0, startIdx);
        const clampedEnd = Math.min(this.dates.length - 1, endIdx);
        if (clampedEnd < clampedStart) {
          return;
        }

        const bar = document.createElement('div');
        bar.className = `pc-cal-event pc-cal-event--${evt.source || 'default'}`;
        bar.style.left = `${clampedStart * this.dayWidth}px`;
        bar.style.width = `${(clampedEnd - clampedStart + 1) * this.dayWidth - 8}px`;
        bar.title = `${evt.source || ''} : ${evt.start_date} → ${evt.end_date}`;
        layer.appendChild(bar);
      });
    }

    openModal(logementId) {
      const logement = this.logements.find((lg) => parseInt(lg.id, 10) === parseInt(logementId, 10));
      if (!logement || !this.modalEl) {
        return;
      }
      this.buildModalCalendar(logement);
      this.modalEl.hidden = false;
      this.modalEl.classList.add('is-open');
    }

    closeModal() {
      if (!this.modalEl) {
        return;
      }
      this.modalEl.classList.remove('is-open');
      this.modalEl.hidden = true;
      if (this.modalGridEl) {
        this.modalGridEl.innerHTML = '';
      }
    }

    buildModalCalendar(logement) {
      if (!this.modalGridEl) {
        return;
      }

      const monthStart = this.parseDate(this.currentRange.start);
      const monthEnd = this.parseDate(this.currentRange.end);
      const daysInMonth = monthEnd.getDate();
      const firstWeekday = this.getMondayBasedDay(monthStart);

      if (this.modalTitleEl) {
        this.modalTitleEl.textContent = logement.title || `#${logement.id}`;
      }
      if (this.modalSubtitleEl) {
        const formatter = new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric' });
        this.modalSubtitleEl.textContent = formatter.format(monthStart);
      }

      const busyDates = this.collectBusyDates(logement.id, this.currentRange.start, this.currentRange.extendedEnd);
      this.modalGridEl.innerHTML = '';
      this.modalGridEl.style.setProperty('--pc-cal-modal-columns', 7);

      for (let i = 0; i < firstWeekday; i += 1) {
        const filler = document.createElement('div');
        filler.className = 'pc-cal-modal__cell pc-cal-modal__cell--pad';
        this.modalGridEl.appendChild(filler);
      }

      for (let day = 1; day <= daysInMonth; day += 1) {
        const dateObj = new Date(monthStart);
        dateObj.setDate(day);
        const iso = this.toISO(dateObj);
        const cell = document.createElement('div');
        cell.className = 'pc-cal-modal__cell';
        cell.dataset.logementId = logement.id;
        cell.dataset.date = iso;
        cell.innerHTML = `<span class="pc-cal-modal__day">${day}</span>`;
        if (busyDates.has(iso)) {
          cell.classList.add('is-busy');
        }
        cell.addEventListener('click', () => {
          // eslint-disable-next-line no-console
          console.log('[pc-calendar] modal-cell', logement.id, iso);
        });
        this.modalGridEl.appendChild(cell);
      }
    }

    collectBusyDates(logementId, start, end) {
      const busy = new Set();
      const events = this.events.filter((evt) => parseInt(evt.logement_id, 10) === parseInt(logementId, 10));
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
          cursor.setDate(cursor.getDate() + 1);
        }
      });

      return busy;
    }

    updatePeriodLabel() {
      if (!this.periodEl || !this.currentRange) {
        return;
      }
      const formatter = new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric' });
      const startDate = this.parseDate(this.currentRange.start);
      const endDate = this.parseDate(this.currentRange.extendedEnd);
      this.periodEl.textContent = `${formatter.format(startDate)} → ${formatter.format(endDate)}`;
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
        cursor.setDate(cursor.getDate() + 1);
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
      const parts = dateStr.split('-');
      if (parts.length !== 3) return null;
      return new Date(Date.UTC(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10)));
    }

    toISO(dateObj) {
      return dateObj.toISOString().slice(0, 10);
    }

    formatDayOfWeek(date) {
      const formatter = new Intl.DateTimeFormat('fr-FR', { weekday: 'short' });
      return formatter.format(date);
    }

    formatDay(date) {
      return String(date.getUTCDate()).padStart(2, '0');
    }

    getMondayBasedDay(dateObj) {
      // getUTCDay: 0 (dimanche) -> 6 (samedi) ; on veut lundi = 0
      const day = dateObj.getUTCDay();
      return (day + 6) % 7;
    }

    readCssNumber(varName, fallback) {
      const value = getComputedStyle(document.documentElement).getPropertyValue(varName);
      const parsed = parseFloat(value);
      if (Number.isNaN(parsed)) {
        return fallback;
      }
      return parsed;
    }

    setLoading(isLoading) {
      if (!this.gridEl) return;
      this.gridEl.classList.toggle('is-loading', Boolean(isLoading));
      if (isLoading) {
        this.gridEl.innerHTML = `<p class="pc-cal-loading">${texts.loading || 'Chargement...'}</p>`;
      }
    }

    setError(message) {
      if (!this.errorEl) {
        return;
      }
      if (!message) {
        this.errorEl.hidden = true;
        this.errorEl.textContent = '';
        return;
      }
      this.errorEl.hidden = false;
      this.errorEl.textContent = message;
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-pc-calendar]');
    if (!root) {
      return;
    }
    const instance = new PcDashboardCalendar(root, window.pcCalendarData || {});
    instance.init();
  });
})();
