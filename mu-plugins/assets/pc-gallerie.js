/* pc-gallerie.js
 * Gère [pc_gallery] en Mode A (hérité) et Mode B (ACF filtrable).
 * - Multi-instances par page
 * - Utilise GLightbox si présent (fallback = ouvrir la 1ère image)
 */
(function () {
  "use strict";

  function initAcfGallery(wrap) {
    const select = wrap.querySelector(".pc-gallery-select");
    const grid = wrap.querySelector(".pc-grid");
    const button = wrap.querySelector(".pc-more");
    const limit = parseInt(grid?.dataset.limit || "6", 10);

    const i18nAll = wrap.dataset.i18nAll || "Toutes les photos";
    const i18nSeeAll = wrap.dataset.i18nSeeAll || "Voir les %d photos";
    const i18nSeeCat = wrap.dataset.i18nSeeCat || "Voir les %d photos (%s)";

    // ancres lightbox cachées (ordre ACF garanti)
    const anchors = Array.from(
      wrap.querySelectorAll(".pc-lightbox-src .pc-glightbox")
    );
    wrap.dataset.debugCountAll = anchors.length; // debug optionnel

    const counts = { all: parseInt(button.dataset.totalAll || "0", 10) };
    const catsData = (() => {
      try {
        return JSON.parse(button.dataset.cats || "[]");
      } catch (e) {
        return [];
      }
    })();
    catsData.forEach((o) => (counts[o.slug] = o.count));
    const labels = { all: i18nAll };
    catsData.forEach((o) => (labels[o.slug] = o.label));

    function renderGrid(filter) {
      const src = anchors
        .filter((a) => (filter === "all" ? true : a.dataset.cat === filter))
        .slice(0, limit > 0 ? limit : undefined);
      grid.innerHTML = "";
      src.forEach((a) => {
        const href = a.getAttribute("href");
        const cat = a.dataset.cat;
        const alt = a.getAttribute("data-title") || "";
        const el = document.createElement("a");
        el.className = "pc-item pc-glink";
        el.href = href;
        el.setAttribute("data-gallery", wrap.dataset.galleryId || "");
        el.setAttribute("data-cat", cat);
        el.setAttribute("aria-label", "Voir la photo – " + (labels[cat] || ""));
        const img = document.createElement("img");
        img.src = href;
        img.loading = "lazy";
        img.decoding = "async";
        img.alt = alt;
        el.appendChild(img);
        grid.appendChild(el);
      });
    }

    function updateButton(filter) {
      const count = counts[filter] ?? 0;
      if (filter === "all") {
        button.textContent = i18nSeeAll.replace("%d", count);
      } else {
        button.textContent = i18nSeeCat
          .replace("%d", count)
          .replace("%s", labels[filter] || "");
      }
    }

    function openLightbox(filter) {
      const sources = anchors
        .filter((a) => (filter === "all" ? true : a.dataset.cat === filter))
        .map((a) => ({ href: a.getAttribute("href"), type: "image" })) // pas de titre
        .filter((v, i, arr) => arr.findIndex((o) => o.href === v.href) === i);

      if (!sources.length) return;
      if (typeof GLightbox === "undefined") {
        window.location.href = sources[0].href;
        return;
      }

      const lb = GLightbox({
        elements: sources,
        loop: true,
        touchNavigation: true,
        closeButton: true,
      });
      lb.open();
    }

    // Init défaut
    renderGrid("all");
    updateButton("all");

    // Changement du filtre
    select?.addEventListener("change", function () {
      const filter = this.value || "all";
      renderGrid(filter);
      updateButton(filter);
    });

    // Clic sur le bouton
    button?.addEventListener("click", function () {
      const filter = select?.value || "all";
      openLightbox(filter);
    });

    // >>> CLIC SUR UNE VIGNETTE (AJOUT ICI)
    grid?.addEventListener("click", function (e) {
      const link = e.target.closest(".pc-item");
      if (!link) return;
      e.preventDefault();

      const filter = select?.value || "all";
      const playlist = anchors
        .filter((a) => (filter === "all" ? true : a.dataset.cat === filter))
        .map((a) => ({ href: a.getAttribute("href"), type: "image" }))
        .filter((v, i, arr) => arr.findIndex((o) => o.href === v.href) === i);

      if (!playlist.length) return;

      const href = link.getAttribute("href");
      const idx = Math.max(
        0,
        playlist.findIndex((x) => x.href === href)
      );

      if (typeof GLightbox === "undefined") {
        window.location.href = playlist[idx].href;
        return;
      }

      const lb = GLightbox({
        elements: playlist,
        loop: true,
        touchNavigation: true,
        closeButton: true,
        startAt: idx,
      });
      lb.open();
    });
  }

  function initExternalGallery(wrap) {
    const button = wrap.querySelector(".pc-more");
    const galleryId = wrap.dataset.galleryId || "";
    const anchors = Array.from(
      wrap.querySelectorAll('.pc-lightbox-src [data-group="' + galleryId + '"]')
    );
    const gridLinks = Array.from(wrap.querySelectorAll(".pc-grid .pc-item")); // <a> des vignettes

    // Playlist commune (ordre garanti par les ancres cachées)
    const playlist = anchors
      .map((a) => ({ href: a.getAttribute("href"), type: "image" }))
      .filter((v, i, arr) => arr.findIndex((o) => o.href === v.href) === i);

    function openAt(startIdx) {
      if (!playlist.length) return;
      if (typeof GLightbox === "undefined") {
        window.location.href = playlist[startIdx]?.href || playlist[0].href;
        return;
      }
      const lb = GLightbox({
        elements: playlist,
        loop: true,
        touchNavigation: true,
        closeButton: true,
        startAt: Math.max(0, startIdx | 0), // démarrer sur la vignette cliquée
      });
      lb.open();
    }

    // Clic sur le BOUTON → ouvre la playlist depuis le début
    button?.addEventListener("click", function (e) {
      e.preventDefault();
      openAt(0);
    });

    // Clic sur une VIGNETTE → ouvre la même playlist, à l'index de l'image cliquée
    gridLinks.forEach((link) => {
      link.addEventListener("click", function (e) {
        e.preventDefault();
        const href = link.getAttribute("href");
        const idx = playlist.findIndex((x) => x.href === href);
        openAt(idx >= 0 ? idx : 0);
      });
    });
  }

  function boot() {
    document.querySelectorAll(".pc-gallery").forEach((wrap) => {
      if (wrap.dataset.mode === "acf") {
        initAcfGallery(wrap);
      } else if (wrap.dataset.mode === "external") {
        initExternalGallery(wrap);
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
