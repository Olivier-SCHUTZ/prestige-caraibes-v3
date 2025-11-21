(function () {
    'use strict';

    const settings = window.pcDashboardCalendar || {};
    const t = (key, fallback) => {
        if (settings.i18n && Object.prototype.hasOwnProperty.call(settings.i18n, key)) {
            return settings.i18n[key];
        }
        return fallback;
    };

    function injectStyles() {
        const styleId = 'pc-dashboard-calendar-style';
        if (document.getElementById(styleId)) {
            return;
        }
        const css = `
            .pc-dashboard-calendar { display: flex; flex-direction: column; gap: 18px; }
            .pc-dashboard-calendar__header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; }
            .pc-dashboard-calendar__title { max-width: 640px; }
            .pc-dashboard-calendar__eyebrow { text-transform: uppercase; letter-spacing: .05em; font-size: 12px; color: var(--pc-color-muted, #6b7280); margin: 0 0 4px; }
            .pc-dashboard-calendar__subtitle { color: var(--pc-color-muted, #6b7280); margin: 6px 0 0; }
            .pc-dashboard-calendar__actions { display: flex; gap: 10px; align-items: center; }
            .pc-dashboard-calendar__legend { display: flex; align-items: center; gap: 6px 12px; flex-wrap: wrap; }
            .pc-dashboard-calendar__badge { width: 14px; height: 14px; border-radius: var(--pc-radius, 8px); display: inline-block; }
            .pc-dashboard-calendar__badge--primary { background: var(--pc-color-primary, #0f7bff); }
            .pc-dashboard-calendar__badge--secondary { background: var(--pc-color-secondary, #00b8b0); }
            .pc-dashboard-calendar__legend-label { font-size: 13px; color: var(--pc-color-muted, #6b7280); }
            .pc-dashboard-calendar__global { background: #fff; border: 1px solid rgba(0,0,0,.05); border-radius: var(--pc-radius, 10px); padding: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.04); position: relative; overflow: hidden; }
            .pc-dashboard-calendar__loader { padding: 28px; text-align: center; color: var(--pc-color-muted, #6b7280); animation: pc-calendar-pulse 1.2s ease-in-out infinite; }
            .pc-dashboard-calendar__caption { display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; font-weight: 600; color: var(--pc-color-text, #111); }
            .pc-calendar-grid__head { display: grid; grid-template-columns: 210px 1fr; gap: 8px; align-items: center; padding: 6px 10px; border-bottom: 1px solid rgba(0,0,0,.06); }
            .pc-calendar-grid__body { display: flex; flex-direction: column; gap: 6px; padding: 6px 4px 10px; }
            .pc-calendar-grid__cell--label { font-weight: 600; color: var(--pc-color-muted, #6b7280); font-size: 13px; }
            .pc-calendar-grid__days, .pc-calendar-row__days, .pc-calendar-row__bars { display: grid; align-items: stretch; gap: 2px; }
            .pc-calendar-grid__days { font-size: 11px; color: var(--pc-color-muted, #6b7280); text-align: center; }
            .pc-calendar-grid__days span { padding: 4px 0; }
            .pc-calendar-row { display: grid; grid-template-columns: 210px 1fr; gap: 8px; align-items: stretch; padding: 6px 10px; border-radius: var(--pc-radius, 10px); cursor: pointer; position: relative; overflow: hidden; transition: transform .15s ease, box-shadow .15s ease; }
            .pc-calendar-row:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(0,0,0,0.05); }
            .pc-calendar-row__label { display: flex; align-items: center; gap: 8px; font-weight: 600; color: var(--pc-color-text, #111); }
            .pc-calendar-row__days { position: relative; background: var(--pc-color-surface, #f9fafb); border: 1px solid rgba(0,0,0,.04); border-radius: var(--pc-radius, 10px); padding: 4px; min-height: 46px; }
            .pc-calendar-row__day { text-align: center; font-size: 10px; color: rgba(0,0,0,.35); padding: 2px 0; }
            .pc-calendar-row__bars { position: absolute; inset: 4px; pointer-events: none; }
            .pc-calendar-bar { height: 20px; border-radius: var(--pc-radius, 10px); display: flex; align-items: center; padding: 0 8px; font-size: 11px; color: #fff; font-weight: 600; mix-blend-mode: multiply; box-shadow: 0 8px 16px rgba(0,0,0,0.1); transition: transform .2s ease, opacity .2s ease; opacity: 0.95; }
            .pc-calendar-bar.is-reservation { background: var(--pc-color-primary, #0f7bff); }
            .pc-calendar-bar.is-blocking { background: var(--pc-color-secondary, #00b8b0); }
            .pc-calendar-empty { padding: 20px; text-align: center; color: var(--pc-color-muted, #6b7280); }
            .pc-dashboard-calendar__modal { position: fixed; inset: 0; display: grid; place-items: center; z-index: 9999; }
            .pc-dashboard-calendar__modal[hidden] { display: none; }
            .pc-dashboard-calendar__modal-overlay { position: absolute; inset: 0; background: rgba(17,24,39,.45); backdrop-filter: blur(4px); }
            .pc-dashboard-calendar__modal-dialog { position: relative; background: #fff; border-radius: var(--pc-radius, 12px); width: min(960px, 92vw); max-height: 90vh; overflow: hidden; box-shadow: 0 24px 50px rgba(0,0,0,0.16); display: flex; flex-direction: column; animation: pc-calendar-rise .22s ease; }
            .pc-dashboard-calendar__modal-header { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 18px 22px 6px; }
            .pc-dashboard-calendar__modal-title { margin: 2px 0 0; }
            .pc-dashboard-calendar__modal-actions { display: flex; gap: 10px; padding: 0 22px 12px; }
            .pc-dashboard-calendar__modal-body { padding: 0 22px 22px; overflow: auto; }
            .pc-calendar-close { background: transparent; border: none; font-size: 26px; line-height: 1; cursor: pointer; color: var(--pc-color-muted, #6b7280); }
            .pc-calendar-close:hover { color: var(--pc-color-text, #111); }
            .pc-calendar-single { border: 1px solid rgba(0,0,0,.06); border-radius: var(--pc-radius, 12px); padding: 12px; background: var(--pc-color-surface, #f9fafb); }
            .pc-calendar-single__month { display: flex; justify-content: space-between; align-items: center; padding: 0 4px 10px; font-weight: 700; }
            .pc-calendar-single__grid { display: grid; grid-template-columns: repeat(7, minmax(40px, 1fr)); gap: 6px; }
            .pc-calendar-single__day { background: #fff; border-radius: var(--pc-radius, 10px); min-height: 64px; padding: 8px; border: 1px solid rgba(0,0,0,.04); display: flex; flex-direction: column; gap: 5px; transition: transform .15s ease, border-color .15s ease; }
            .pc-calendar-single__day:hover { transform: translateY(-1px); border-color: rgba(0,0,0,.08); }
            .pc-calendar-single__day-number { font-weight: 700; color: var(--pc-color-text, #111); }
            .pc-calendar-single__pill { height: 8px; border-radius: 12px; flex: 0 0 auto; background: var(--pc-color-secondary, #00b8b0); }
            .pc-calendar-single__pill.is-reservation { background: var(--pc-color-primary, #0f7bff); }
            .pc-calendar-single__weekday { font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: var(--pc-color-muted, #6b7280); text-align: center; }
            @keyframes pc-calendar-rise { from { opacity: 0; transform: translateY(8px);} to { opacity: 1; transform: translateY(0);} }
            @keyframes pc-calendar-pulse { 0% { opacity: .65; } 50% { opacity: .95; } 100% { opacity: .65; } }
            @media (max-width: 960px) { .pc-calendar-row, .pc-calendar-grid__head { grid-template-columns: 160px 1fr; } }
            @media (max-width: 720px) { .pc-dashboard-calendar__actions { width: 100%; justify-content: flex-start; } .pc-calendar-grid__head { display: none; } .pc-calendar-row { grid-template-columns: 1fr; } .pc-calendar-row__label { margin-bottom: 6px; } }
        `;
        const style = document.createElement('style');
        style.id = styleId;
        style.textContent = css;
        document.head.appendChild(style);
    }

    function formatMonthLabel(month, year) {
        const date = new Date(year, month - 1, 1);
        return date.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
    }

    function addMonths(month, year, delta) {
        const date = new Date(year, month - 1 + delta, 1);
        return { month: date.getMonth() + 1, year: date.getFullYear() };
    }

    function parseDate(value) {
        if (!value) {
            return null;
        }
        const parts = value.split('-');
        if (parts.length !== 3) {
            return null;
        }
        const year = parseInt(parts[0], 10);
        const month = parseInt(parts[1], 10) - 1;
        const day = parseInt(parts[2], 10);
        return new Date(year, month, day);
    }

    function buildFormData(payload) {
        const data = new FormData();
        Object.keys(payload).forEach((key) => {
            data.append(key, payload[key]);
        });
        return data;
    }

    async function postAjax(payload) {
        const ajaxUrl = settings.ajaxUrl || '';
        if (!ajaxUrl) {
            throw new Error('Ajax URL manquant');
        }
        const response = await fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: buildFormData(payload),
        });
        const json = await response.json();
        if (!json || !json.success) {
            const message = (json && json.data && json.data.message) ? json.data.message : 'Erreur AJAX';
            throw new Error(message);
        }
        return json.data;
    }

    function clampToMonth(date, monthStart, monthEnd) {
        if (!date) {
            return null;
        }
        if (date < monthStart) {
            return new Date(monthStart.getTime());
        }
        if (date > monthEnd) {
            return new Date(monthEnd.getTime());
        }
        return date;
    }

    function dayDiff(dateA, dateB) {
        const one = Date.UTC(dateA.getFullYear(), dateA.getMonth(), dateA.getDate());
        const two = Date.UTC(dateB.getFullYear(), dateB.getMonth(), dateB.getDate());
        return Math.floor((two - one) / (1000 * 60 * 60 * 24));
    }

    function renderGlobal(root, state) {
        const grid = root.querySelector('[data-pc-calendar-grid]');
        const loader = root.querySelector('[data-pc-calendar-loader]');
        if (!grid) {
            return;
        }

        if (loader) {
            loader.remove();
        }

        grid.innerHTML = '';

        const daysInMonth = new Date(state.year, state.month, 0).getDate();
        const monthStart = new Date(state.year, state.month - 1, 1);
        const monthEnd = new Date(state.year, state.month - 1, daysInMonth);

        const caption = document.createElement('div');
        caption.className = 'pc-dashboard-calendar__caption';
        caption.textContent = formatMonthLabel(state.month, state.year);
        grid.appendChild(caption);

        if (!state.logements.length) {
            const empty = document.createElement('div');
            empty.className = 'pc-calendar-empty';
            empty.textContent = t('noLogement', 'Aucun logement actif.');
            grid.appendChild(empty);
            return;
        }

        const head = document.createElement('div');
        head.className = 'pc-calendar-grid__head';
        const headLabel = document.createElement('div');
        headLabel.className = 'pc-calendar-grid__cell pc-calendar-grid__cell--label';
        headLabel.textContent = 'Logement';
        const headDays = document.createElement('div');
        headDays.className = 'pc-calendar-grid__days';
        headDays.style.gridTemplateColumns = `repeat(${daysInMonth}, minmax(28px, 1fr))`;

        for (let d = 1; d <= daysInMonth; d += 1) {
            const span = document.createElement('span');
            span.textContent = d;
            headDays.appendChild(span);
        }
        head.appendChild(headLabel);
        head.appendChild(headDays);
        grid.appendChild(head);

        const body = document.createElement('div');
        body.className = 'pc-calendar-grid__body';

        state.logements.forEach((logement) => {
            const row = document.createElement('div');
            row.className = 'pc-calendar-row';
            row.dataset.logementId = logement.id;

            const label = document.createElement('div');
            label.className = 'pc-calendar-row__label';
            label.textContent = logement.title || `Logement #${logement.id}`;

            const daysWrapper = document.createElement('div');
            daysWrapper.className = 'pc-calendar-row__days';
            daysWrapper.style.gridTemplateColumns = `repeat(${daysInMonth}, minmax(24px, 1fr))`;

            for (let d = 1; d <= daysInMonth; d += 1) {
                const dayCell = document.createElement('div');
                dayCell.className = 'pc-calendar-row__day';
                dayCell.textContent = d;
                daysWrapper.appendChild(dayCell);
            }

            const bars = document.createElement('div');
            bars.className = 'pc-calendar-row__bars';
            bars.style.gridTemplateColumns = `repeat(${daysInMonth}, minmax(24px, 1fr))`;

            const events = state.events.filter((ev) => parseInt(ev.item_id, 10) === parseInt(logement.id, 10));

            events.forEach((event) => {
                const startDate = clampToMonth(parseDate(event.start), monthStart, monthEnd);
                const endDate = clampToMonth(parseDate(event.end), monthStart, monthEnd);
                if (!startDate || !endDate) {
                    return;
                }
                const startDay = startDate.getDate();
                const endDay = endDate.getDate();
                const bar = document.createElement('div');
                bar.className = `pc-calendar-bar ${event.type === 'reservation' ? 'is-reservation' : 'is-blocking'}`;
                bar.style.gridColumn = `${startDay} / ${endDay + 1}`;
                bar.textContent = event.type === 'reservation' ? 'Réservation' : 'Indispo';
                bars.appendChild(bar);
            });

            row.appendChild(label);
            row.appendChild(daysWrapper);
            row.appendChild(bars);

            row.addEventListener('click', () => {
                openModal(root, state, logement);
            });

            body.appendChild(row);
        });

        grid.appendChild(body);
    }

    function renderSingleCalendar(singleContainer, singleState) {
        if (!singleContainer) {
            return;
        }

        const daysInMonth = new Date(singleState.year, singleState.month, 0).getDate();
        const monthStart = new Date(singleState.year, singleState.month - 1, 1);
        const monthEnd = new Date(singleState.year, singleState.month - 1, daysInMonth);
        const weekdayShift = (day) => (day + 6) % 7; // start Monday

        const head = document.createElement('div');
        head.className = 'pc-calendar-single__month';
        head.textContent = formatMonthLabel(singleState.month, singleState.year);

        const grid = document.createElement('div');
        grid.className = 'pc-calendar-single__grid';

        ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'].forEach((wd) => {
            const label = document.createElement('div');
            label.className = 'pc-calendar-single__weekday';
            label.textContent = wd;
            grid.appendChild(label);
        });

        const blanks = weekdayShift(monthStart.getDay());
        for (let i = 0; i < blanks; i += 1) {
            const empty = document.createElement('div');
            grid.appendChild(empty);
        }

        for (let d = 1; d <= daysInMonth; d += 1) {
            const cell = document.createElement('div');
            cell.className = 'pc-calendar-single__day';
            const number = document.createElement('div');
            number.className = 'pc-calendar-single__day-number';
            number.textContent = d;
            cell.appendChild(number);

            const currentDate = new Date(singleState.year, singleState.month - 1, d);
            const relevant = singleState.events.filter((ev) => {
                const start = clampToMonth(parseDate(ev.start), monthStart, monthEnd);
                const end = clampToMonth(parseDate(ev.end), monthStart, monthEnd);
                if (!start || !end) {
                    return false;
                }
                const startDiff = dayDiff(start, currentDate);
                const endDiff = dayDiff(currentDate, end);
                return startDiff <= 0 && endDiff <= 0;
            });

            relevant.forEach((ev) => {
                const pill = document.createElement('div');
                pill.className = `pc-calendar-single__pill ${ev.type === 'reservation' ? 'is-reservation' : ''}`;
                cell.appendChild(pill);
            });

            grid.appendChild(cell);
        }

        singleContainer.innerHTML = '';
        singleContainer.appendChild(head);
        singleContainer.appendChild(grid);
    }

    function closeModal(root) {
        const modal = root.querySelector('[data-pc-calendar-modal]');
        const body = document.body;
        if (modal) {
            modal.hidden = true;
        }
        if (body) {
            body.classList.remove('pc-calendar-modal-open');
        }
    }

    async function openModal(root, state, logement) {
        const modal = root.querySelector('[data-pc-calendar-modal]');
        const singleContainer = root.querySelector('[data-pc-calendar-single]');
        const titleNode = root.querySelector('[data-pc-calendar-modal-title]');

        if (!modal || !singleContainer) {
            return;
        }

        if (titleNode) {
            titleNode.textContent = logement.title || `Logement #${logement.id}`;
        }

        modal.dataset.currentLogement = logement.id;
        modal.dataset.logementTitle = logement.title || '';
        modal.hidden = false;
        document.body.classList.add('pc-calendar-modal-open');

        singleContainer.innerHTML = `<div class="pc-dashboard-calendar__loader">${t('loading', 'Chargement...')}</div>`;

        try {
            const data = await postAjax({
                action: 'pc_get_single_calendar',
                nonce: settings.nonce,
                logement_id: logement.id,
                month: state.month,
                year: state.year,
            });
            const singleState = {
                month: data.month,
                year: data.year,
                events: data.events || [],
            };
            renderSingleCalendar(singleContainer, singleState);
        } catch (err) {
            singleContainer.innerHTML = `<div class="pc-calendar-empty">${err.message || 'Erreur pendant le chargement.'}</div>`;
        }
    }

    async function refreshGlobal(root, state) {
        const loader = root.querySelector('[data-pc-calendar-loader]');
        if (loader) {
            loader.textContent = t('loading', 'Chargement...');
        } else {
            const grid = root.querySelector('[data-pc-calendar-grid]');
            if (grid) {
                const newLoader = document.createElement('div');
                newLoader.className = 'pc-dashboard-calendar__loader';
                newLoader.textContent = t('loading', 'Chargement...');
                grid.innerHTML = '';
                grid.appendChild(newLoader);
            }
        }

        try {
            const data = await postAjax({
                action: 'pc_get_global_calendar',
                nonce: settings.nonce,
                month: state.month,
                year: state.year,
            });
            state.logements = data.logements || [];
            state.events = data.events || [];
            renderGlobal(root, state);
        } catch (err) {
            const grid = root.querySelector('[data-pc-calendar-grid]');
            if (grid) {
                grid.innerHTML = `<div class="pc-calendar-empty">${err.message || 'Erreur de chargement.'}</div>`;
            }
        }
    }

    function bindNavigation(root, state) {
        const buttons = root.querySelectorAll('[data-pc-calendar-nav]');
        buttons.forEach((btn) => {
            btn.addEventListener('click', () => {
                const dir = btn.dataset.pcCalendarNav === 'next' ? 1 : -1;
                const next = addMonths(state.month, state.year, dir);
                state.month = next.month;
                state.year = next.year;
                refreshGlobal(root, state);
            });
        });

        const modalButtons = root.querySelectorAll('[data-pc-calendar-modal-nav]');
        modalButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                const dir = btn.dataset.pcCalendarModalNav === 'next' ? 1 : -1;
                const next = addMonths(state.month, state.year, dir);
                state.month = next.month;
                state.year = next.year;
                refreshGlobal(root, state).then(() => {
                    const modal = root.querySelector('[data-pc-calendar-modal]');
                    if (modal && !modal.hidden && modal.dataset.currentLogement) {
                        openModal(root, state, {
                            id: modal.dataset.currentLogement,
                            title: modal.dataset.logementTitle || '',
                        });
                    }
                });
            });
        });
    }

    function bindModalClose(root) {
        root.querySelectorAll('[data-pc-calendar-close]').forEach((btn) => {
            btn.addEventListener('click', () => closeModal(root));
        });
    }

    function initCalendar(root) {
        const now = new Date();
        const state = {
            month: parseInt(root.dataset.month, 10) || now.getMonth() + 1,
            year: parseInt(root.dataset.year, 10) || now.getFullYear(),
            logements: [],
            events: [],
        };

        bindNavigation(root, state);
        bindModalClose(root);
        refreshGlobal(root, state);
    }

    document.addEventListener('DOMContentLoaded', () => {
        injectStyles();
        const roots = document.querySelectorAll('.pc-dashboard-calendar');
        roots.forEach((root) => initCalendar(root));
    });
})();
