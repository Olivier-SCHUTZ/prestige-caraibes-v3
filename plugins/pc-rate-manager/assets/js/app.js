jQuery(document).ready(function ($) {
  console.log("🚀 PC Rate Manager v5.0 : Création Robuste & Dates");

  if (typeof pcRmConfig === "undefined") return;

  const calendarEl = document.getElementById("pc-calendar");
  const seasonListEl = document.getElementById("pc-seasons-list");

  // CONFIGURATION EXACTE JSON
  const ACF_KEYS = {
    season: {
      repeater: "field_pc_season_blocks_20250826",
      name: "season_name",
      price: "season_price",
      note: "season_note",
      minNights: "season_min_nights",
      guestFee: "season_extra_guest_fee",
      guestFrom: "season_extra_guest_from",
      periods: "season_periods",
      dateFrom: "date_from",
      dateTo: "date_to",
    },
    promo: {
      repeater: "field_693425b17049d",
      name: "nom_de_la_promotion",
      type: "promo_type",
      value: "promo_value",
      validUntil: "promo_valid_until",
      periods: "promo_periods",
      dateFrom: "date_from",
      dateTo: "date_to",
    },
  };

  let seasonsMap = new Map();
  let promosMap = new Map();
  let calendarInstance = null;

  // --- 1. INITIALISATION CALENDRIER ---
  function initCalendar() {
    const calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: "multiMonthYear",
      multiMonthMaxColumns: 1,
      locale: "fr",
      firstDay: 1,
      editable: true,
      droppable: true,
      eventOverlap: true,
      eventOrder: "zIndex",

      dayCellContent: function (arg) {
        return {
          html: `<span class="pc-day-number fc-daygrid-day-number">${arg.dayNumberText}</span>`,
        };
      },
      dayCellDidMount: function (arg) {
        const frame = arg.el.querySelector(".fc-daygrid-day-frame");
        if (frame && pcRmConfig.base_price > 0) {
          const priceEl = document.createElement("div");
          priceEl.className = "pc-base-price";
          priceEl.textContent = pcRmConfig.base_price + "€";
          frame.appendChild(priceEl);
        }
      },
      eventContent: function (arg) {
        const props = arg.event.extendedProps;
        let html = `<div class="pc-evt-title">${arg.event.title}</div>`;
        if (props.type === "season") {
          html += `<div class="pc-evt-price">${props.price}€</div>`;
        } else if (props.type === "promo") {
          const valDisplay =
            props.promoType === "percent"
              ? `-${props.promoValue}%`
              : `-${props.promoValue}€`;
          html += `<div class="pc-evt-promo-badge">${valDisplay}</div>`;
        }
        return { html: `<div class="pc-event-body">${html}</div>` };
      },
      eventClick: function (info) {
        const props = info.event.extendedProps;
        const s = info.event.start.toLocaleDateString("fr-FR");
        let ed = new Date(info.event.end);
        ed.setDate(ed.getDate() - 1);
        const e = ed.toLocaleDateString("fr-FR");

        if (confirm(`Supprimer la période du ${s} au ${e} ?`)) {
          const keys =
            props.type === "season" ? ACF_KEYS.season : ACF_KEYS.promo;
          removePeriodFromAcf(
            keys.repeater,
            keys.periods,
            props.mainRowId,
            props.periodId
          );
          info.event.remove();
          setTimeout(refreshAllData, 800);
        }
      },
      eventReceive: function (info) {
        const start = info.event.startStr;
        let end = info.event.endStr;
        if (!end) end = start;
        else {
          let d = new Date(end);
          d.setDate(d.getDate() - 1);
          end = d.toISOString().split("T")[0];
        }
        const props = info.event.extendedProps;
        if (confirm(`Ajouter cette période à "${info.event.title}" ?`)) {
          addPeriodToAcf(props.mainRowId, start, end, props.type);
        }
        info.event.remove();
      },
    });
    calendar.render();
    calendarInstance = calendar;
  }

  initCalendar();
  setTimeout(refreshAllData, 1000);

  // --- 2. GESTION POPUP ---
  $("#btn-add-new-season").click(function (e) {
    e.preventDefault();
    openModal("season", null);
  });
  $("#btn-add-promo").click(function (e) {
    e.preventDefault();
    openModal("promo", null);
  });
  $(document).on("click", ".pc-draggable-event", function (e) {
    openModal($(this).data("type"), $(this).data("id"));
  });
  $("#btn-close-cross").on("click", function () {
    $("#pc-season-modal").fadeOut(200);
  });
  $("#pc-season-modal").on("click", function (e) {
    if (e.target === this) $(this).fadeOut(200);
  });

  function openModal(type, rowId) {
    const $modal = $("#pc-season-modal");
    $("#pc-season-form").trigger("reset");
    $("#pc-entity-type").val(type);
    $("#pc-edit-row-id").val(rowId || "");
    $("#pc-periods-list").empty();

    // UI Reset
    $("#btn-add-period-manual").show(); // Toujours visible

    if (type === "season") {
      $("#pc-modal-title").text(rowId ? "Éditer Saison" : "Nouvelle Saison");
      $("#pc-group-season-fields").show();
      $("#pc-group-promo-fields").hide();
      $("#lbl-name").text("Nom de la saison *");
    } else {
      $("#pc-modal-title").text(
        rowId ? "Éditer Promotion" : "Nouvelle Promotion"
      );
      $("#pc-group-season-fields").hide();
      $("#pc-group-promo-fields").show();
      $("#lbl-name").text("Nom de la promotion *");
    }

    if (rowId) {
      // --- MODE ÉDITION ---
      $("#btn-delete-entity").show();
      // On cache le bouton ajouter manuel si on veut forcer l'usage du calendrier,
      // mais ici on le laisse pour permettre l'ajout manuel.
      $("#lbl-period-action").text("Ajouter une période manuelle :");

      const map = type === "season" ? seasonsMap : promosMap;
      const data = map.get(rowId.toString());
      if (data) {
        $("#pc-input-name").val(data.name);
        if (type === "season") {
          $("#pc-input-price").val(data.price);
          $("#pc-input-min-nights").val(data.minNights);
          $("#pc-input-note").val(data.note);
          $("#pc-input-guest-fee").val(data.guestFee);
          $("#pc-input-guest-from").val(data.guestFrom);
        } else {
          $("#pc-input-promo-type").val(data.promoType);
          $("#pc-input-promo-val").val(data.promoValue);
          $("#pc-input-promo-validity").val(data.validUntil);
        }
        if (data.periods) {
          data.periods.forEach((p) => {
            const dArr = p.start.split("-");
            const s = `${dArr[2]}/${dArr[1]}/${dArr[0]}`;
            const eArr = p.end.split("-");
            const e = `${eArr[2]}/${eArr[1]}/${eArr[0]}`;
            $("#pc-periods-list").append(`
                          <li><span>📅 ${s} au ${e}</span><button class="pc-del-period-btn" data-type="${type}" data-main="${rowId}" data-sub="${p.id}" style="color:red;border:none;background:none;cursor:pointer;">&times;</button></li>
                      `);
          });
        }
      }
    } else {
      // --- MODE CRÉATION ---
      $("#btn-delete-entity").hide();
      // On change le texte pour indiquer que ces dates seront utilisées à la création
      $("#lbl-period-action").text("Définir les dates initiales (Optionnel) :");
      // On cache le bouton "Ajouter" car ce sera géré par le bouton "Enregistrer"
      $("#btn-add-period-manual").hide();
    }
    $modal.css("display", "flex").hide().fadeIn(200);
  }

  $("#btn-save-modal-action").click(function (e) {
    e.preventDefault();
    const $btn = $(this);
    const originalText = $btn.text();

    // Feedback visuel
    $btn.text("⏳ Création en cours...").prop("disabled", true);

    // ... (Tes récupérations de variables vals, type, etc. restent identiques) ...
    // ...
    const type = $("#pc-entity-type").val();
    const rowId = $("#pc-edit-row-id").val();
    const vals = { name: $("#pc-input-name").val() };

    // ... (Ta logique de validation reste identique) ...
    // Copie juste les blocs if/else pour récupérer price, note, etc.
    // ...

    if (type === "season") {
      vals.price = $("#pc-input-price").val();
      vals.minNights = $("#pc-input-min-nights").val();
      vals.note = $("#pc-input-note").val();
      vals.guestFee = $("#pc-input-guest-fee").val();
      vals.guestFrom = $("#pc-input-guest-from").val();
      if (!vals.name || !vals.price) {
        $btn.text(originalText).prop("disabled", false);
        return alert("Nom et Prix requis");
      }
    } else {
      vals.promoType = $("#pc-input-promo-type").val();
      vals.promoValue = $("#pc-input-promo-val").val();
      vals.validUntil = $("#pc-input-promo-validity").val();
      if (!vals.name || !vals.promoValue) {
        $btn.text(originalText).prop("disabled", false);
        return alert("Nom et Valeur requis");
      }
    }

    const newStart = $("#pc-period-start").val();
    const newEnd = $("#pc-period-end").val();
    const keys = type === "season" ? ACF_KEYS.season : ACF_KEYS.promo;

    const onComplete = () => {
      $("#pc-season-modal").fadeOut(200);
      $btn.text(originalText).prop("disabled", false);
      setTimeout(refreshAllData, 500);
    };

    if (rowId) {
      updateAcfRow(keys, rowId, vals, type);
      onComplete();
    } else {
      // C'EST ICI QUE ÇA CHANGE : APPEL DE LA VERSION SECURE
      createAcfRowSecure(keys, vals, type, newStart, newEnd, onComplete);
    }
  });

  // --- 4. FONCTION CRÉATION (DEBUG VISUEL) ---
  function createAcfRowSecure(keys, vals, type, startDate, endDate, callback) {
    // 1. On cible le conteneur du répéteur
    const $repeater = $(`div[data-key="${keys.repeater}"]`);

    console.log(`🎯 Cible Répéteur : ${keys.repeater}`);

    // 2. ON CHERCHE LE BOUTON "AJOUTER" (Le plus précis possible)
    // On cherche dans .acf-actions qui est l'enfant direct du wrapper répéteur
    // pour éviter de choper les boutons des sous-répéteurs
    let $btnAdd = $repeater.find("> .acf-actions > .acf-button-add");

    // Fallback si la structure HTML varie légèrement
    if ($btnAdd.length === 0) {
      console.log("⚠️ Bouton direct non trouvé, recherche large...");
      $btnAdd = $repeater.find(".acf-button-add").last(); // Souvent le dernier bouton est le principal
    }

    if ($btnAdd.length === 0) {
      alert(
        "ERREUR CRITIQUE : Impossible de trouver le bouton 'Ajouter' dans ACF. Vérifiez que les champs sont bien affichés."
      );
      console.error("❌ Bouton introuvable pour : ", keys.repeater);
      if (callback) callback();
      return;
    }

    // 3. DEBUG VISUEL : On montre à l'utilisateur ce qu'on va cliquer
    // On scroll jusqu'au bouton
    $("html, body").animate(
      {
        scrollTop: $btnAdd.offset().top - 300,
      },
      300
    );

    // On met une bordure ROUGE CLIGNOTANTE dessus
    $btnAdd.css("border", "5px solid red").css("background", "yellow");

    console.log("👉 CLIC SIMULÉ SUR : ", $btnAdd.text());

    // 4. OBSERVATEUR (On lance l'espion avant de cliquer)
    waitForNewRow($repeater, function ($newRow) {
      console.log("✅ VICTOIRE ! Une nouvelle ligne est apparue.");

      // On remet le bouton normal
      $btnAdd.css("border", "").css("background", "");

      // Remplissage
      fillRowInputs($newRow, keys, vals, type);

      // Gestion des dates (Sous-répéteur)
      if (startDate && endDate) {
        const $subRep = $newRow.find(`[data-name="${keys.periods}"]`);
        // Le bouton ajouter du sous-répéteur
        const $btnSub = $subRep.find(".acf-button-add").first();

        if ($btnSub.length) {
          waitForNewRow($subRep, function ($newSubRow) {
            const fromInput = $newSubRow.find(`[data-name="${keys.dateFrom}"]`);
            fromInput
              .find('input[type="hidden"]')
              .val(startDate)
              .trigger("change");
            fromInput.find("input.input").val(startDate).trigger("change");

            const toInput = $newSubRow.find(`[data-name="${keys.dateTo}"]`);
            toInput.find('input[type="hidden"]').val(endDate).trigger("change");
            toInput.find("input.input").val(endDate).trigger("change");

            if (callback) callback();
          });
          $btnSub.click();
        } else {
          if (callback) callback();
        }
      } else {
        if (callback) callback();
      }
    });

    // 5. ACTION : LE CLIC RÉEL (Après un petit délai pour que tu voies le rouge)
    setTimeout(function () {
      $btnAdd.click();
    }, 500);
  }

  // --- L'OUTIL MAGIQUE (OBSERVER) ---
  // Cette fonction attend que le DOM change réellement, au lieu de deviner le temps
  function waitForNewRow($container, onSuccess) {
    // On compte combien on a de lignes AVANT le clic
    const countBefore = $container.find(
      "> .acf-table > tbody > .acf-row:not(.acf-clone), > .acf-row:not(.acf-clone)"
    ).length;

    const observer = new MutationObserver(function (mutations, obs) {
      const $rows = $container.find(
        "> .acf-table > tbody > .acf-row:not(.acf-clone), > .acf-row:not(.acf-clone)"
      );

      // Si le nombre de lignes a augmenté, c'est que ACF a fini son travail
      if ($rows.length > countBefore) {
        obs.disconnect(); // On arrête de surveiller pour économiser la mémoire
        onSuccess($rows.last()); // On renvoie la nouvelle ligne toute fraîche
      }
    });

    // On lance la surveillance
    observer.observe($container[0], { childList: true, subtree: true });
  }

  // --- ACTIONS SECONDAIRES ---
  $("#btn-add-period-manual").click(function () {
    // Bouton "Ajouter" dans le popup (Mode Édition seulement)
    const s = $("#pc-period-start").val();
    const e = $("#pc-period-end").val();
    const type = $("#pc-entity-type").val();
    const rId = $("#pc-edit-row-id").val();
    if (!rId || !s || !e) return alert("Dates manquantes ou mode création");
    addPeriodToAcf(rId, s, e, type);
    $("#pc-period-start").val("");
    $("#pc-period-end").val("");
    setTimeout(() => {
      refreshAllData();
      setTimeout(() => openModal(type, rId), 500);
    }, 600);
  });

  $(document).on("click", ".pc-del-period-btn", function (e) {
    e.preventDefault();
    const keys =
      $(this).data("type") === "season" ? ACF_KEYS.season : ACF_KEYS.promo;
    removePeriodFromAcf(
      keys.repeater,
      keys.periods,
      $(this).data("main"),
      $(this).data("sub")
    );
    setTimeout(() => {
      refreshAllData();
      setTimeout(
        () => openModal($(this).data("type"), $(this).data("main")),
        500
      );
    }, 600);
  });

  $("#btn-delete-entity").click(function (e) {
    e.preventDefault();
    if (!confirm("Supprimer ?")) return;
    const t = $("#pc-entity-type").val();
    const k = t === "season" ? ACF_KEYS.season : ACF_KEYS.promo;
    const $row = $(
      `div[data-key="${k.repeater}"] .acf-row[data-id="${$(
        "#pc-edit-row-id"
      ).val()}"]`
    );
    $row.find(".acf-icon.-minus").first().click();
    setTimeout(function () {
      $(".acf-tooltip-confirm .acf-button").click();
    }, 100);
    $("#pc-season-modal").fadeOut();
    setTimeout(refreshAllData, 1000);
  });

  // --- HELPERS ---
  function addPeriodToAcf(rowId, start, end, type) {
    const keys = type === "season" ? ACF_KEYS.season : ACF_KEYS.promo;
    const $mainRow = $(
      `div[data-key="${keys.repeater}"] .acf-row[data-id="${rowId}"]`
    );
    const $subRep = $mainRow.find(`[data-name="${keys.periods}"]`);
    $subRep.find(".acf-actions .acf-button-add").first().click();
    setTimeout(function () {
      const $newRow = $subRep.find(".acf-row:not(.acf-clone)").last();
      const fromInput = $newRow.find(`[data-name="${keys.dateFrom}"]`);
      fromInput.find('input[type="hidden"]').val(start).trigger("change");
      fromInput.find("input.input").val(start).trigger("change");
      const toInput = $newRow.find(`[data-name="${keys.dateTo}"]`);
      toInput.find('input[type="hidden"]').val(end).trigger("change");
      toInput.find("input.input").val(end).trigger("change");
    }, 300);
  }
  function removePeriodFromAcf(rKey, subName, mId, sId) {
    const $r = $(`div[data-key="${rKey}"] .acf-row[data-id="${mId}"]`);
    const $sub = $r.find(`[data-name="${subName}"] .acf-row[data-id="${sId}"]`);
    $sub.find(".acf-icon.-minus").first().click();
    setTimeout(function () {
      $(".acf-tooltip-confirm .acf-button").click();
    }, 100);
  }
  function refreshAllData() {
    if (calendarInstance) calendarInstance.removeAllEvents();
    $(seasonListEl).empty();
    readRepeater("season");
    readRepeater("promo");
    initDraggable();
  }
  function readRepeater(type) {
    const keys = type === "season" ? ACF_KEYS.season : ACF_KEYS.promo;
    const $repeater = $(`div[data-key="${keys.repeater}"]`);
    const $rows = $repeater.find(".acf-row:not(.acf-clone)");
    const map = type === "season" ? seasonsMap : promosMap;
    map.clear();
    $rows.each(function () {
      const $row = $(this);
      const rowId = $row.attr("data-id");
      const periods = [];
      $row
        .find(`[data-name="${keys.periods}"] .acf-row:not(.acf-clone)`)
        .each(function () {
          const f = $(this)
            .find(`[data-name="${keys.dateFrom}"] input[type="hidden"]`)
            .val();
          const t = $(this)
            .find(`[data-name="${keys.dateTo}"] input[type="hidden"]`)
            .val();
          if (f && t)
            periods.push({
              id: $(this).attr("data-id"),
              start: normalizeDate(f),
              end: normalizeDate(t),
            });
        });
      let data = { rowId: rowId, periods: periods, type: type };
      if (type === "season") {
        data.name = $row.find(`[data-name="${keys.name}"] input`).val();
        data.price = $row.find(`[data-name="${keys.price}"] input`).val();
        data.note = $row.find(`[data-name="${keys.note}"] input`).val();
        data.minNights = $row
          .find(`[data-name="${keys.minNights}"] input`)
          .val();
        data.guestFee = $row.find(`[data-name="${keys.guestFee}"] input`).val();
        data.guestFrom = $row
          .find(`[data-name="${keys.guestFrom}"] input`)
          .val();
        data.color = stringToColor(data.name || "S");
        data.zIndex = 10;
      } else {
        data.name = $row.find(`[data-name="${keys.name}"] input`).val();
        data.promoType = $row.find(`[data-name="${keys.type}"] select`).val();
        data.promoValue = $row.find(`[data-name="${keys.value}"] input`).val();
        let rv = $row
          .find(
            `[data-name="${keys.validUntil}"] .acf-date-picker input[type="hidden"]`
          )
          .val();
        data.validUntil = normalizeDate(rv);
        data.color = "#ef4444";
        data.zIndex = 50;
      }
      if (!data.name) return;
      map.set(rowId.toString(), data);
      createSidebarButton(data);
      loadPeriodsToCalendar(data);
    });
  }
  function loadPeriodsToCalendar(data) {
    if (!data.periods) return;
    data.periods.forEach((p) => {
      calendarInstance.addEvent({
        title: data.name,
        start: p.start,
        end: addDays(p.end, 1),
        backgroundColor: data.type === "season" ? data.color : "transparent",
        borderColor: data.color,
        className:
          data.type === "season" ? "pc-event-season" : "pc-event-promo",
        zIndex: data.zIndex,
        allDay: true,
        extendedProps: {
          type: data.type,
          mainRowId: data.rowId,
          periodId: p.id,
          price: data.price,
          promoType: data.promoType,
          promoValue: data.promoValue,
        },
      });
    });
  }
  function createSidebarButton(data) {
    const p = data.type === "season" ? data.price + "€" : "PROMO";
    $(seasonListEl).append(
      `<div class="pc-draggable-event" data-id="${data.rowId}" data-type="${data.type}" style="background-color: ${data.color}; border-color: ${data.color};"><span class="pc-season-name">${data.name}</span><span class="pc-price-tag">${p}</span></div>`
    );
  }
  function initDraggable() {
    if (typeof FullCalendar.Draggable === "undefined") return;
    new FullCalendar.Draggable(seasonListEl, {
      itemSelector: ".pc-draggable-event",
      eventData: function (eventEl) {
        const rowId = eventEl.getAttribute("data-id");
        const data =
          eventEl.getAttribute("data-type") === "season"
            ? seasonsMap.get(rowId)
            : promosMap.get(rowId);
        if (!data) return {};
        return {
          title: data.name,
          backgroundColor: data.color,
          borderColor: data.color,
          extendedProps: { type: data.type, mainRowId: rowId },
        };
      },
    });
  }
  function stringToColor(str) {
    let hash = 0;
    for (let i = 0; i < str.length; i++)
      hash = str.charCodeAt(i) + ((hash << 5) - hash);
    return `hsl(${Math.abs(hash % 360)}, 70%, 45%)`;
  }
  function normalizeDate(str) {
    if (!str) return null;
    if (str.indexOf("-") > -1) return str;
    if (str.length === 8)
      return `${str.substring(0, 4)}-${str.substring(4, 6)}-${str.substring(
        6,
        8
      )}`;
    return null;
  }
  function addDays(dateStr, days) {
    if (!dateStr) return null;
    let d = new Date(dateStr);
    d.setDate(d.getDate() + days);
    return d.toISOString().split("T")[0];
  }
});
