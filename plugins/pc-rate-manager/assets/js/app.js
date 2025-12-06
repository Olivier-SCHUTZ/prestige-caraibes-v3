jQuery(document).ready(function ($) {
  console.log("🚀 PC Rate Manager : Démarrage du script complet...");

  // =======================================================
  // 0. CONFIGURATION & SÉCURITÉ
  // =======================================================

  // Vérification de la configuration PHP
  if (typeof pcRmConfig === "undefined") {
    console.error(
      "❌ ERREUR CRITIQUE : La configuration 'pcRmConfig' est manquante."
    );
    return;
  }

  // Éléments du DOM
  const calendarEl = document.getElementById("pc-calendar");
  const seasonListEl = document.getElementById("pc-seasons-list");

  // Clés ACF
  const repeaterKey = pcRmConfig.field_season_repeater;

  // Stockage local des données (RowID -> Data Objet)
  let seasonsMap = new Map();
  let calendarInstance = null;

  // Si le calendrier n'est pas là, on arrête
  if (!calendarEl) {
    console.warn(
      "⚠️ Conteneur #pc-calendar introuvable (peut-être sur une autre page)."
    );
    return;
  }

  // =======================================================
  // 1. INITIALISATION DU CALENDRIER
  // =======================================================

  function initCalendar() {
    console.log("📅 Initialisation de FullCalendar...");

    // On cache l'interface native ACF tout de suite
    hideNativeACF();

    const calendar = new FullCalendar.Calendar(calendarEl, {
      // --- VUE & NAVIGATION ---
      initialView: "multiMonthYear",
      multiMonthMaxColumns: 1, // Une seule colonne (scroll vertical)
      locale: "fr",
      firstDay: 1, // Lundi
      height: "auto",

      headerToolbar: {
        left: "prev,next today",
        center: "title",
        right: "", // Pas de changement de vue nécessaire
      },

      // --- INTERACTIONS ---
      editable: true, // Permet de déplacer/étirer les événements
      droppable: true, // Permet de recevoir des éléments externes (Sidebar)
      eventOverlap: false, // Interdit la superposition
      selectable: true, // Permet de sélectionner des plages vides (optionnel)

      // --- RENDU DES CELLULES (DESIGN) ---

      // A. Contenu de la case jour (Numéro)
      dayCellContent: function (arg) {
        // On garde le numéro du jour propre
        return {
          html: `<span class="pc-day-number fc-daygrid-day-number">${arg.dayNumberText}</span>`,
        };
      },

      // B. Montage de la case (Ajout du Prix de Base)
      dayCellDidMount: function (arg) {
        const frame = arg.el.querySelector(".fc-daygrid-day-frame");
        const basePrice = pcRmConfig.base_price;

        if (frame && basePrice > 0) {
          const priceEl = document.createElement("div");
          priceEl.className = "pc-base-price";
          priceEl.textContent = basePrice + "€";
          frame.appendChild(priceEl);
        }
      },

      // C. Rendu de l'événement (La Saison Colorée)
      eventContent: function (arg) {
        const price = arg.event.extendedProps.price;
        return {
          html: `
                    <div class="pc-event-body">
                        <div class="pc-evt-title">${arg.event.title}</div>
                        ${
                          price
                            ? `<div class="pc-evt-price">${price}€</div>`
                            : ""
                        }
                    </div>`,
        };
      },

      // --- ÉVÉNEMENTS (LOGIQUE) ---

      // D. Réception d'un élément de la sidebar (Drag & Drop)
      drop: function (info) {
        console.log("📥 Élément déposé depuis la sidebar :", info.dateStr);
        // Note : FullCalendar crée l'événement visuel automatiquement via 'eventReceive'
      },

      // E. L'événement est créé (depuis drop ou code)
      eventReceive: function (info) {
        // On s'assure que les props sont bien passées
        console.log("✨ Nouvel événement reçu :", info.event.title);

        // TODO : Ici, on devra déclencher la sauvegarde d'une nouvelle "Période" dans ACF
        // Pour l'instant c'est visuel, la sauvegarde globale se fait via le bouton "Enregistrer" de la page post
      },

      // F. Clic sur un événement (Suppression pour l'instant)
      eventClick: function (info) {
        if (
          confirm(
            `Voulez-vous retirer la période "${info.event.title}" du calendrier ?`
          )
        ) {
          info.event.remove();
          console.log("🗑️ Période retirée visuellement.");
          // Note : Cela ne supprime pas la saison, juste la période sur le calendrier
        }
      },

      // G. Redimensionnement ou Déplacement sur le calendrier
      eventChange: function (info) {
        console.log("↔️ Période modifiée (resize/move).");
      },
    });

    calendar.render();
    calendarInstance = calendar;
  }

  // Lance l'init
  initCalendar();

  // =======================================================
  // 2. GESTION DES DONNÉES (LECTURE ACF)
  // =======================================================

  // On attend un peu que ACF ait fini son rendu JS interne
  setTimeout(refreshData, 600);

  function refreshData() {
    console.log("🔄 Lecture des données ACF (Refresh)...");

    const $repeater = $(`div[data-key="${repeaterKey}"]`);

    // Sécurité si le champ ACF n'existe pas
    if ($repeater.length === 0) {
      console.error("❌ Champ Répéteur ACF introuvable : " + repeaterKey);
      return;
    }

    // 1. Nettoyage
    seasonsMap.clear();
    $(seasonListEl).empty(); // Vide la sidebar
    if (calendarInstance) {
      calendarInstance.getEvents().forEach((e) => e.remove()); // Vide le calendrier
    }

    // 2. Parcours des lignes ACF
    const $rows = $repeater.find(".acf-row:not(.acf-clone)");

    if ($rows.length === 0) {
      $(seasonListEl).html(
        '<p class="pc-rm-empty-state">Aucune saison définie.</p>'
      );
    }

    $rows.each(function () {
      const $row = $(this);
      const rowId = $row.attr("data-id");

      // Lecture des champs
      const name = $row.find('[data-name="season_name"] input').val();
      const price = $row.find('[data-name="season_price"] input').val();

      // Champs secondaires
      const note = $row.find('[data-name="season_note"] input').val();
      const minNights = $row
        .find('[data-name="season_min_nights"] input')
        .val();
      const guestFee = $row
        .find('[data-name="season_extra_guest_fee"] input')
        .val();
      const guestFrom = $row
        .find('[data-name="season_extra_guest_from"] input')
        .val();

      // On ignore les lignes sans nom
      if (!name) return;

      // Génération couleur
      const color = stringToColor(name);

      // Stockage dans la Map
      // --- MODIFICATION START : On lit les périodes pour la modale ---
      const periods = [];
      $row
        .find('[data-name="season_periods"] .acf-row:not(.acf-clone)')
        .each(function () {
          const dFrom = $(this)
            .find('[data-name="date_from"] input[type="hidden"]')
            .val();
          const dTo = $(this)
            .find('[data-name="date_to"] input[type="hidden"]')
            .val();
          const subId = $(this).attr("data-id");
          if (dFrom && dTo) {
            periods.push({ id: subId, start: dFrom, end: dTo });
          }
        });
      // -------------------------------------------------------------

      const seasonData = {
        rowId: rowId,
        name: name,
        price: price,
        note: note,
        minNights: minNights,
        guestFee: guestFee,
        guestFrom: guestFrom,
        color: color,
        periods: periods, // <--- On stocke la liste ici
      };

      seasonsMap.set(rowId.toString(), seasonData);

      createSidebarButton(seasonData);
      loadPeriodsToCalendar($row, seasonData);
    });

    // 5. Initialisation du Drag & Drop sur les nouveaux boutons
    initDraggable();
  }

  function createSidebarButton(data) {
    const btnHtml = `
            <div class="pc-draggable-event" 
                 data-id="${data.rowId}" 
                 style="background-color: ${data.color}; border-color: ${data.color};"
                 title="Cliquez pour éditer, Glissez pour planifier">
                 <span class="pc-season-name">${data.name}</span>
                 <span class="pc-price-tag">${data.price}€</span>
            </div>`;
    $(seasonListEl).append(btnHtml);
  }

  function loadPeriodsToCalendar($row, seasonData) {
    const $periodRows = $row.find(
      '[data-name="season_periods"] .acf-row:not(.acf-clone)'
    );

    $periodRows.each(function () {
      const dateFrom = $(this)
        .find('[data-name="date_from"] .acf-date-picker input[type="hidden"]')
        .val();
      const dateTo = $(this)
        .find('[data-name="date_to"] .acf-date-picker input[type="hidden"]')
        .val();

      if (dateFrom && dateTo && calendarInstance) {
        calendarInstance.addEvent({
          title: seasonData.name,
          start: parseAcfDate(dateFrom),
          // FullCalendar fin exclusive : on ajoute 1 jour
          end: addDays(parseAcfDate(dateTo), 1),
          backgroundColor: seasonData.color,
          borderColor: seasonData.color,
          allDay: true,
          extendedProps: {
            price: seasonData.price,
            seasonRowId: seasonData.rowId,
          },
        });
      }
    });
  }

  function initDraggable() {
    // Vérification de la librairie Draggable
    if (typeof FullCalendar.Draggable === "undefined") return;

    // On nettoie les anciens draggables si besoin (pas d'API destroy simple, on recrée)
    new FullCalendar.Draggable(seasonListEl, {
      itemSelector: ".pc-draggable-event",
      eventData: function (eventEl) {
        const rowId = eventEl.getAttribute("data-id");
        const data = seasonsMap.get(rowId);

        if (!data) return {};

        return {
          title: data.name,
          backgroundColor: data.color,
          borderColor: data.color,
          extendedProps: {
            price: data.price,
            seasonRowId: rowId,
          },
        };
      },
    });
  }

  // =======================================================
  // 3. GESTION DU POPUP (MODALE) & ÉDITION
  // =======================================================

  // A. Ouverture : Nouvelle Saison
  $(document).on("click", "#btn-add-new-season", function (e) {
    e.preventDefault();
    openModal(null);
  });

  // B. Ouverture : Édition Saison (Clic Sidebar)
  $(document).on("click", ".pc-draggable-event", function (e) {
    // On vérifie qu'on ne clique pas juste pour dragger (optionnel)
    const rowId = $(this).data("id");
    if (rowId) openModal(rowId);
  });

  // C. Ouverture : Placeholder Promo
  $(document).on("click", "#btn-add-promo", function (e) {
    e.preventDefault();
    alert("La gestion des Promotions arrive bientôt !");
  });

  // --- NOUVEAU : AJOUT MANUEL DATE ---
  $(document).on("click", "#btn-add-period-manual", function (e) {
    e.preventDefault();
    const rowId = $("#pc-edit-row-id").val();
    const start = $("#pc-period-start").val();
    const end = $("#pc-period-end").val();

    if (!rowId || !start || !end) return alert("Dates manquantes");
    if (start > end) return alert("La fin doit être après le début");

    addPeriodToAcf(rowId, start, end);

    $("#pc-period-start").val("");
    $("#pc-period-end").val("");

    setTimeout(() => {
      refreshData();
      setTimeout(() => openModal(rowId), 200);
    }, 300);
  });

  // --- NOUVEAU : SUPPRESSION DATE (Croix) ---
  $(document).on("click", ".pc-del-period-btn", function (e) {
    e.preventDefault();
    if (!confirm("Supprimer cette période ?")) return;

    const mainId = $(this).data("main-row");
    const subId = $(this).data("sub-row");

    removePeriodFromAcf(mainId, subId);

    setTimeout(() => {
      refreshData();
      setTimeout(() => openModal(mainId), 200);
    }, 300);
  });

  // D. Fermeture Modale
  $(document).on("click", ".pc-close-modal", function () {
    $("#pc-season-modal").fadeOut(200);
  });
  // --- E. SAUVEGARDE (CLIC BOUTON ENREGISTRER) ---
  $(document).on("click", "#btn-save-modal-action", function (e) {
    e.preventDefault();
    console.log("💾 Sauvegarde manuelle déclenchée...");

    const rowId = $("#pc-edit-row-id").val();

    // Validation simple
    const name = $("#pc-input-name").val();
    const price = $("#pc-input-price").val();

    if (!name || !price) {
      alert("Le nom et le prix sont obligatoires !");
      return;
    }

    // Récupération des valeurs
    const vals = {
      name: name,
      price: price,
      note: $("#pc-input-note").val(),
      minNights: $("#pc-input-min-nights").val(),
      guestFee: $("#pc-input-guest-fee").val(),
      guestFrom: $("#pc-input-guest-from").val(),
    };

    if (rowId) {
      updateAcfRow(rowId, vals);
    } else {
      createAcfRow(vals);
    }

    $("#pc-season-modal").fadeOut(200);
    // On attend un peu que ACF mette à jour le DOM avant de rafraîchir
    setTimeout(refreshData, 500);
  });

  // FONCTION PRINCIPALE : Ouvrir le formulaire
  function openModal(rowId = null) {
    const $modal = $("#pc-season-modal");
    const $form = $("#pc-season-form");

    if ($modal.length === 0) {
      // Si tu as bien mis le PHP dans le footer, ça ne devrait plus arriver
      console.error("Popup introuvable (Vérifie pc-rate-manager.php)");
      return;
    }

    $form.trigger("reset");
    $("#pc-edit-row-id").val("");
    $("#pc-periods-list").empty(); // On vide l'ancienne liste

    if (rowId && seasonsMap.has(rowId.toString())) {
      // --- MODE ÉDITION ---
      const data = seasonsMap.get(rowId.toString());

      $("#pc-modal-title").text("Éditer : " + data.name);
      $("#pc-edit-row-id").val(rowId);

      $("#pc-input-name").val(data.name);
      $("#pc-input-price").val(data.price);
      $("#pc-input-note").val(data.note);
      $("#pc-input-min-nights").val(data.minNights);
      $("#pc-input-guest-fee").val(data.guestFee);
      $("#pc-input-guest-from").val(data.guestFrom);

      // AFFICHER LE GESTIONNAIRE DE PÉRIODES
      $("#pc-periods-manager").show();
      $("#btn-delete-season-def").show();

      // Remplir la liste visuelle
      if (data.periods && data.periods.length > 0) {
        data.periods.forEach((p) => {
          // YYYYMMDD -> DD/MM/YYYY
          const s = p.start.replace(/(\d{4})(\d{2})(\d{2})/, "$3/$2/$1");
          const e = p.end.replace(/(\d{4})(\d{2})(\d{2})/, "$3/$2/$1");

          $("#pc-periods-list").append(`
                  <li>
                      <span>📅 ${s} au ${e}</span>
                      <button class="pc-del-period-btn" data-main-row="${rowId}" data-sub-row="${p.id}" style="color:red;border:none;background:none;cursor:pointer;">&times;</button>
                  </li>
              `);
        });
      } else {
        $("#pc-periods-list").html(
          '<li style="color:#999;text-align:center;">Aucune date définie</li>'
        );
      }
    } else {
      // --- MODE CRÉATION ---
      $("#pc-modal-title").text("Nouvelle Saison");
      $("#btn-delete-season-def").hide();
      $("#pc-periods-manager").hide(); // Pas de dates à la création
    }

    $modal.css("display", "flex").hide().fadeIn(200);
  }

  function updateAcfRow(rowId, vals) {
    // On cherche la ligne spécifique dans le répéteur
    const $row = $(
      `div[data-key="${repeaterKey}"] .acf-row[data-id="${rowId}"]`
    );

    if ($row.length) {
      fillRowInputs($row, vals);
      console.log("✅ Ligne ACF mise à jour :", rowId);
    } else {
      console.error(
        "❌ Impossible de trouver la ligne ACF à mettre à jour :",
        rowId
      );
    }
  }

  function createAcfRow(vals) {
    const $repeater = $(`div[data-key="${repeaterKey}"]`);

    // 1. Clic sur le bouton "Ajouter" natif d'ACF
    const $addBtn = $repeater.find(".acf-actions .acf-button-add");
    $addBtn.click();

    // 2. On récupère la NOUVELLE ligne (la dernière non-clone)
    // ACF ajoute la ligne de manière synchrone (généralement)
    const $newRow = $repeater.find(".acf-row:not(.acf-clone)").last();

    if ($newRow.length) {
      fillRowInputs($newRow, vals);
      console.log("✅ Nouvelle ligne ACF créée.");
    } else {
      console.error("❌ Erreur lors de la création de la ligne ACF.");
    }
  }

  function fillRowInputs($row, vals) {
    // Helper pour remplir les champs ACF standards
    $row.find('[data-name="season_name"] input').val(vals.name);
    $row.find('[data-name="season_price"] input').val(vals.price);
    $row.find('[data-name="season_note"] input').val(vals.note);
    $row.find('[data-name="season_min_nights"] input').val(vals.minNights);
    $row.find('[data-name="season_extra_guest_fee"] input').val(vals.guestFee);
    $row
      .find('[data-name="season_extra_guest_from"] input')
      .val(vals.guestFrom);
  }

  // =======================================================
  // 5. UTILITAIRES & ESTHÉTIQUE
  // =======================================================

  function hideNativeACF() {
    // Cache le répéteur Saisons
    $(`div[data-key="${repeaterKey}"]`).hide();

    // Cache le répéteur Promos (si config présente)
    if (pcRmConfig.field_promo_repeater) {
      $(`div[data-key="${pcRmConfig.field_promo_repeater}"]`).hide();
    }

    // Cache les onglets ACF (Top bar)
    $(".acf-tab-wrap li a").each(function () {
      const text = $(this).text();
      if (text.includes("Tarifs saison") || text.includes("Promotions")) {
        $(this).parent().hide();
      }
    });
  }

  // Génère une couleur pastel basée sur le nom (déterministe)
  function stringToColor(str) {
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
      hash = str.charCodeAt(i) + ((hash << 5) - hash);
    }
    const h = Math.abs(hash % 360);
    return `hsl(${h}, 70%, 45%)`;
  }

  // YYYYMMDD -> YYYY-MM-DD
  function parseAcfDate(str) {
    if (!str || str.length !== 8) return null;
    return `${str.substring(
      0,
      4
    )}-${str.substring(4, 6)}-${str.substring(6, 8)}`;
  }

  // Ajout de jours à une date (pour la fin exclusive de FullCalendar)
  function addDays(dateStr, days) {
    if (!dateStr) return null;
    let d = new Date(dateStr);
    d.setDate(d.getDate() + days);
    return d.toISOString().split("T")[0];
  }
  // --- FONCTIONS TECHNIQUES ACF ---

  function addPeriodToAcf(mainRowId, startYMD, endYMD) {
    const $repeater = $(`div[data-key="${repeaterKey}"]`);
    const $mainRow = $repeater.find(`.acf-row[data-id="${mainRowId}"]`);

    // Clic sur "Ajouter période"
    const $subRep = $mainRow.find('[data-name="season_periods"]');
    $subRep.find(".acf-actions .acf-button-add").first().click();

    // Remplissage de la nouvelle ligne
    const $newRow = $subRep.find(".acf-row:not(.acf-clone)").last();

    // Conversion YYYY-MM-DD -> YYYYMMDD pour ACF Hidden
    const sClean = startYMD.replace(/-/g, "");
    const eClean = endYMD.replace(/-/g, "");

    $newRow.find('[data-name="date_from"] input[type="hidden"]').val(sClean);
    $newRow.find('[data-name="date_from"] input.input').val(startYMD); // Visuel

    $newRow.find('[data-name="date_to"] input[type="hidden"]').val(eClean);
    $newRow.find('[data-name="date_to"] input.input').val(endYMD); // Visuel
  }

  function removePeriodFromAcf(mainRowId, subRowId) {
    const $row = $(
      `div[data-key="${repeaterKey}"] .acf-row[data-id="${mainRowId}"]`
    );
    const $subRow = $row.find(
      `[data-name="season_periods"] .acf-row[data-id="${subRowId}"]`
    );

    const $btn = $subRow.find(".acf-icon.-minus");
    if ($btn.length) $btn.click();
    else $subRow.remove();
  }
});
