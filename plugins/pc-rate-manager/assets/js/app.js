jQuery(document).ready(function ($) {
  // --- 0. NETTOYAGE UI ---
  // Masquer les onglets et champs ACF natifs
  $(".acf-tab-wrap li a")
    .filter(function () {
      return $(this).text().trim() === "Promotions";
    })
    .parent("li")
    .hide();
  $(`div[data-key="${pcRmConfig.field_season_repeater}"]`).hide();
  $(`div[data-key="${pcRmConfig.field_promo_blocks}"]`).hide();

  const calendarEl = document.getElementById("pc-calendar");
  if (!calendarEl) return;

  // --- 1. CONFIGURATION CALENDRIER ---
  const calendar = new FullCalendar.Calendar(calendarEl, {
    // Vue par défaut : Liste des mois (Scroll)
    initialView: "multiMonthYear",
    multiMonthMaxColumns: 1, // Force 1 colonne pour avoir les mois les uns sous les autres

    locale: "fr",
    firstDay: 1, // Lundi

    // --- CORRECTION BARRE D'OUTILS ---
    headerToolbar: {
      left: "prev,next today", // Remet les flèches de navigation
      center: "title",
      right: "multiMonthYear,dayGridMonth", // Remet le choix : Liste (Année) ou Mois simple
    },

    // Textes des boutons traduits si besoin
    buttonText: {
      today: "Auj.",
      month: "Mois",
      year: "Année (Scroll)", // On renomme pour que ce soit clair
      list: "Liste",
    },

    // IMPORTANT : 'auto' permet au calendrier de grandir à l'infini dans le conteneur scrollable
    height: "auto",

    editable: true,
    droppable: true,
    selectable: true,
    selectMirror: true,

    // --- LA CORRECTION DU GLISSÉ ---
    selectMinDistance: 10,
    eventOverlap: false,

    // --- SÉLECTION GLISSÉE ---
    select: function (info) {
      setTimeout(function () {
        let title = prompt(
          "Nom de la saison (ex: Promo, Bloqué...) ?",
          "Nouvelle Saison"
        );

        if (title) {
          calendar.addEvent({
            title: title,
            start: info.startStr,
            end: info.endStr,
            backgroundColor: "#9b59b6", // Violet
            borderColor: "#8e44ad",
            allDay: true,
            extendedProps: { type: "custom", price: "" },
          });
        }
        calendar.unselect();
      }, 100);
    },

    // --- CLIC POUR SUPPRIMER ---
    eventClick: function (info) {
      if (confirm("Supprimer : " + info.event.title + " ?")) {
        info.event.remove();
      }
    },

    drop: function (info) {
      /* Géré auto */
    },
  });

  calendar.render();

  // --- 2. DRAG & DROP SIDEBAR ---
  const draggableContainer = document.querySelector(".pc-rm-sidebar");
  if (draggableContainer) {
    new FullCalendar.Draggable(draggableContainer, {
      itemSelector: ".pc-draggable-event",
      eventData: function (eventEl) {
        return {
          title: eventEl.innerText.trim(),
          backgroundColor: eventEl.getAttribute("data-color"),
          borderColor: eventEl.getAttribute("data-color"),
          extendedProps: {
            type: "season_template",
            price: eventEl.getAttribute("data-price"),
          },
        };
      },
    });
  }

  // --- 3. CHARGEMENT DONNÉES ACF ---
  function loadExistingACFData() {
    console.log("Lecture des données...");
    const $repeaterContainer = $(
      `div[data-key="${pcRmConfig.field_season_repeater}"]`
    );
    const seasonRows = $repeaterContainer.find(".acf-row:not(.acf-clone)");

    seasonRows.each(function () {
      const $row = $(this);
      const seasonName = $row.find('[data-name="season_name"] input').val();
      const seasonPrice = $row.find('[data-name="season_price"] input').val();

      let eventColor = "#9b59b6";
      if (seasonName) {
        const lowerName = seasonName.toLowerCase();
        if (lowerName.includes("moyenne")) eventColor = "#3788d8";
        else if (lowerName.includes("très") || lowerName.includes("tres"))
          eventColor = "#d9534f";
        else if (lowerName.includes("haute")) eventColor = "#e67e22";
      }

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

        if (dateFromBrut && dateToBrut) {
          const startDate = parseACFDate(dateFromBrut);
          let endDateObj = new Date(parseACFDate(dateToBrut));
          endDateObj.setDate(endDateObj.getDate() + 1);
          const endDate = endDateObj.toISOString().split("T")[0];

          calendar.addEvent({
            title: seasonName + " (" + (seasonPrice || "?") + "€)",
            start: startDate,
            end: endDate,
            backgroundColor: eventColor,
            borderColor: eventColor,
            allDay: true,
            extendedProps: {
              acfSeasonRowId: $row.attr("data-id"),
              price: seasonPrice,
            },
          });
        }
      });
    });
  }

  function parseACFDate(dateStr) {
    if (!dateStr || dateStr.length !== 8) return dateStr;
    return `${dateStr.substring(
      0,
      4
    )}-${dateStr.substring(4, 6)}-${dateStr.substring(6, 8)}`;
  }

  setTimeout(loadExistingACFData, 500);
});
