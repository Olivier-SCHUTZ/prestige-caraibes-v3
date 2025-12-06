jQuery(function ($) {
  const calendarEl = document.getElementById("pc-calendar");
  if (!calendarEl) return;

  const basePriceValue = parseInt(pcRmConfig?.base_price, 10);
  const basePrice = Number.isFinite(basePriceValue) ? basePriceValue : 0;
  const cellEvents = new Map(); // date -> Set(eventIds) for cleanup
  const palette = buildPaletteFromSidebar();
  const fallbackColor = "#8b5cf6";

  hideNativeACF();

  const calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: "multiMonthYear",
    multiMonthMaxColumns: 1,
    locale: "fr",
    firstDay: 1,
    height: "auto",
    expandRows: true,
    headerToolbar: {
      left: "prev,next today",
      center: "title",
      right: "",
    },
    buttonText: {
      today: "Auj.",
    },
    editable: true,
    droppable: true,
    selectable: true,
    eventOverlap: false,
    selectOverlap: false,
    eventDisplay: "block",

    dayCellContent: function (arg) {
      return {
        html: `<span class="pc-day-number fc-daygrid-day-number">${arg.dayNumberText}</span>`,
      };
    },

    dayCellDidMount: function (arg) {
      const frame = arg.el.querySelector(".fc-daygrid-day-frame");
      if (!frame) return;
      const baseLabel = basePrice > 0 ? `${basePrice}€` : "—";
      const baseEl = document.createElement("div");
      baseEl.className = "pc-base-price";
      baseEl.textContent = baseLabel;
      frame.appendChild(baseEl);
    },

    eventContent: function (arg) {
      const price = arg.event.extendedProps.price;
      const label = [arg.event.title, price ? `${price}€` : null]
        .filter(Boolean)
        .join(" ");

      return {
        html: `<div class="pc-event-body">${label}</div>`,
      };
    },

    eventClassNames: function (arg) {
      const type = (arg.event.extendedProps.type || "").toLowerCase();
      if (type.includes("promo")) return ["pc-layer-promo"];
      return ["pc-layer-season"];
    },

    eventDidMount: function (info) {
      paintEventRange(info.event);
    },

    eventWillUnmount: function (info) {
      clearPaintedRange(info.event);
    },

    select: function (info) {
      const title = window.prompt("Nom de la saison ?", "Saison");
      if (!title) {
        calendar.unselect();
        return;
      }

      const price = window.prompt("Prix ?", basePrice || "");
      const entry = ensurePaletteEntry(title, price);

      calendar.addEvent({
        title: title,
        start: info.startStr,
        end: info.endStr,
        allDay: true,
        backgroundColor: entry.color,
        borderColor: entry.color,
        extendedProps: { type: "manual", price: price || "" },
      });

      calendar.unselect();
    },

    eventClick: function (info) {
      const rangeLabel = formatEventRange(info.event);
      if (
        window.confirm(`Supprimer "${info.event.title}" ?\n${rangeLabel}`)
      ) {
        info.event.remove();
      }
    },
  });

  calendar.render();

  initSidebarDrag();
  loadSeasonsFromACF();

  function hideNativeACF() {
    $(".acf-tab-wrap li a")
      .filter(function () {
        const text = $(this).text().toLowerCase();
        return text.includes("promotion") || text.includes("promo");
      })
      .parent("li")
      .hide();

    if (pcRmConfig?.field_season_repeater) {
      $(`div[data-key="${pcRmConfig.field_season_repeater}"]`).hide();
    }
    if (pcRmConfig?.field_promo_repeater) {
      $(`div[data-key="${pcRmConfig.field_promo_repeater}"]`).hide();
    }
  }

  function initSidebarDrag() {
    const sidebar = document.querySelector(".pc-rm-sidebar");
    if (!sidebar) return;

    new FullCalendar.Draggable(sidebar, {
      itemSelector: ".pc-draggable-event",
      eventData: function (eventEl) {
        const price = eventEl.getAttribute("data-price");
        const color = eventEl.getAttribute("data-color") || "#1e88e5";

        return {
          title: eventEl.innerText.trim(),
          backgroundColor: color,
          borderColor: color,
          allDay: true,
          extendedProps: {
            type: eventEl.getAttribute("data-type") || "season_template",
            price: price || "",
          },
        };
      },
    });
  }

  function loadSeasonsFromACF() {
    const repeaterKey = pcRmConfig?.field_season_repeater;
    if (!repeaterKey) return;

    const $repeaterContainer = $(`div[data-key="${repeaterKey}"]`);
    const seasonRows = $repeaterContainer.find(".acf-row:not(.acf-clone)");

    seasonRows.each(function () {
      const $row = $(this);
      const seasonName = $row.find('[data-name="season_name"] input').val();
      const seasonPrice = $row.find('[data-name="season_price"] input').val();
      if (!seasonName || !seasonName.trim()) return;
      const entry = ensurePaletteEntry(seasonName, seasonPrice);

      const periodRows = $row.find(
        '[data-name="season_periods"] .acf-row:not(.acf-clone)'
      );

      periodRows.each(function () {
        const $pRow = $(this);
        const dateFromBrut = $pRow
          .find('[data-name="date_from"] .acf-date-picker input[type="hidden"]')
          .val();
        const dateToBrut = $pRow
          .find('[data-name="date_to"] .acf-date-picker input[type="hidden"]')
          .val();

        if (!dateFromBrut || !dateToBrut) return;

        const startDate = parseACFDate(dateFromBrut);
        const endDate = addOneDay(parseACFDate(dateToBrut));

        calendar.addEvent({
          title: seasonName || "Saison",
          start: startDate,
          end: endDate,
          allDay: true,
          backgroundColor: entry.color,
          borderColor: entry.color,
          extendedProps: {
            acfSeasonRowId: $row.attr("data-id"),
            price: seasonPrice || entry.price || "",
            type: "season",
          },
        });
      });
    });
  }

  function parseACFDate(dateStr) {
    if (!dateStr || dateStr.length !== 8) return dateStr;
    return `${dateStr.substring(0, 4)}-${dateStr.substring(
      4,
      6
    )}-${dateStr.substring(6, 8)}`;
  }

  function addOneDay(dateStr) {
    const d = new Date(dateStr);
    d.setDate(d.getDate() + 1);
    return d.toISOString().split("T")[0];
  }

  function paintEventRange(event) {
    const dates = expandDates(event.start, event.end);
    const eventId = getEventId(event);

    dates.forEach((dateStr, idx) => {
      const cell = document.querySelector(`.fc-daygrid-day[data-date="${dateStr}"]`);
      if (!cell) return;
      const frame = cell.querySelector(".fc-daygrid-day-frame");
      if (!frame) return;

      const set = cellEvents.get(dateStr) || new Set();
      set.add(eventId);
      cellEvents.set(dateStr, set);

      cell.classList.add("pc-has-event");

      const dayNumber = frame.querySelector(".pc-day-number");
      if (dayNumber) {
        dayNumber.classList.add("pc-day-on-event");
      }

      const base = frame.querySelector(".pc-base-price");
      if (base) base.style.display = "none";
    });
  }

  function clearPaintedRange(event) {
    const dates = expandDates(event.start, event.end);
    const eventId = getEventId(event);

    dates.forEach((dateStr) => {
      const cell = document.querySelector(`.fc-daygrid-day[data-date="${dateStr}"]`);
      if (!cell) return;
      const frame = cell.querySelector(".fc-daygrid-day-frame");
      if (!frame) return;

      const set = cellEvents.get(dateStr);
      if (set) {
        set.delete(eventId);
        if (set.size === 0) {
          cellEvents.delete(dateStr);
        } else {
          cellEvents.set(dateStr, set);
        }
      }

      if (!cellEvents.has(dateStr)) {
        cell.classList.remove("pc-has-event");
        const base = frame.querySelector(".pc-base-price");
        if (base) base.style.display = "";
        const dayNumber = frame.querySelector(".pc-day-number");
        if (dayNumber) dayNumber.classList.remove("pc-day-on-event");
      }
    });
  }

  function expandDates(start, end) {
    const dates = [];
    const cursor = new Date(start);
    const last = end ? new Date(end) : addDay(new Date(start));

    while (cursor < last) {
      dates.push(cursor.toISOString().split("T")[0]);
      cursor.setDate(cursor.getDate() + 1);
    }
    return dates;
  }

  function addDay(dateObj) {
    const d = new Date(dateObj);
    d.setDate(d.getDate() + 1);
    return d;
  }

  function getEventId(event) {
    return (
      event.id ||
      event._def?.publicId ||
      String(event._def?.defId || Math.random().toString(36).slice(2))
    );
  }

  function formatEventRange(event) {
    const start = event.start;
    const end = event.end ? new Date(event.end) : addDay(event.start);
    // End is exclusive in FullCalendar; show the previous day for all-day
    end.setDate(end.getDate() - 1);
    return `Du ${formatDate(start)} au ${formatDate(end)}`;
  }

  function formatDate(dateObj) {
    if (!dateObj) return "";
    return dateObj.toLocaleDateString("fr-FR", {
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
    });
  }

  function buildPaletteFromSidebar() {
    const map = new Map();
    document
      .querySelectorAll(".pc-draggable-event")
      .forEach((el) => registerPaletteEntryFromButton(map, el));
    return map;
  }

  function registerPaletteEntryFromButton(map, el) {
    const name = el.innerText.trim();
    const key = normalizeName(name);
    const color = el.getAttribute("data-color") || fallbackColor;
    const price = el.getAttribute("data-price") || "";
    el.dataset.seasonKey = key;
    map.set(key, { color, price, label: name });
  }

  function ensurePaletteEntry(name, price) {
    const cleanName = (name || "").trim();
    if (!cleanName) {
      return { color: fallbackColor, price: price || "", label: "Saison" };
    }
    const key = normalizeName(cleanName);
    const existing = palette.get(key);
    if (existing) {
      if (price && price !== existing.price) {
        existing.price = price;
        palette.set(key, existing);
        updateSidebarButton(key, existing);
      }
      return existing;
    }

    const entry = {
      color: colorFromName(cleanName),
      price: price || "",
      label: cleanName,
    };
    palette.set(key, entry);
    addSidebarButton(cleanName, entry, key);
    return entry;
  }

  function addSidebarButton(name, entry, key) {
    const sidebarSection = document.querySelector(".pc-rm-sidebar .pc-rm-section");
    if (!sidebarSection) return;

    const btn = document.createElement("div");
    btn.className = "pc-draggable-event season-type";
    btn.dataset.type = "season";
    btn.dataset.price = entry.price || "";
    btn.dataset.color = entry.color;
    btn.dataset.seasonKey = key;
    btn.textContent = name;
    btn.style.backgroundColor = entry.color;

    sidebarSection.appendChild(btn);
  }

  function updateSidebarButton(key, entry) {
    const btn = document.querySelector(
      `.pc-draggable-event[data-season-key="${key}"]`
    );
    if (!btn) return;
    btn.dataset.price = entry.price || "";
    btn.dataset.color = entry.color;
    btn.style.backgroundColor = entry.color;
  }

  function normalizeName(str = "") {
    return str.trim().toLowerCase();
  }

  function colorFromName(name = "") {
    // Simple hash to generate a hue for custom seasons, staying vivid but distinct.
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
      hash = (hash << 5) - hash + name.charCodeAt(i);
      hash |= 0;
    }
    const hue = Math.abs(hash) % 360;
    return `hsl(${hue}, 68%, 58%)`;
  }
});
