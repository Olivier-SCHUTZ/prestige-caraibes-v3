/**
 * Composant : PC Gallery Manager
 * Rôle : Gérer l'affichage de la grille, le filtrage par catégorie (Mode B)
 * et l'ouverture de la visionneuse GLightbox.
 */
class PCGalleryManager {
  constructor() {
    this.galleries = document.querySelectorAll(".pc-gallery");
    if (this.galleries.length === 0) return;
    this.init();
  }

  init() {
    this.galleries.forEach((gallery) => {
      const mode = gallery.dataset.mode;
      const galleryId = gallery.dataset.galleryId;
      const lightboxSrc = gallery.querySelectorAll(
        ".pc-lightbox-src .pc-glightbox",
      );
      const btnMore = gallery.querySelector(".pc-more");
      const grid = gallery.querySelector(".pc-grid");

      let glightbox = null;

      // Fonction pour (ré)initialiser GLightbox selon la sélection
      const initGLightbox = (selector) => {
        if (typeof GLightbox !== "undefined") {
          if (glightbox) glightbox.destroy();
          glightbox = GLightbox({
            selector: selector,
            loop: true,
            touchNavigation: true,
          });
        }
      };

      if (mode === "external") {
        // ==========================================
        // MODE A : URLs simples
        // ==========================================
        initGLightbox(
          `.pc-gallery[data-gallery-id="${galleryId}"] .pc-lightbox-src .pc-glightbox`,
        );

        // Clic sur une image de la grille
        const gridLinks = gallery.querySelectorAll(".pc-glink");
        gridLinks.forEach((link, index) => {
          link.addEventListener("click", (e) => {
            e.preventDefault();
            if (glightbox) glightbox.openAt(index);
          });
        });

        // Clic sur le bouton "Voir plus"
        if (btnMore) {
          btnMore.addEventListener("click", (e) => {
            e.preventDefault();
            if (glightbox) glightbox.openAt(0);
          });
        }
      } else if (mode === "acf") {
        // ==========================================
        // MODE B : Catégories ACF (Dynamique)
        // ==========================================
        const select = gallery.querySelector(".pc-gallery-select");
        const limit = parseInt(grid.dataset.limit) || 6;

        const updateGrid = () => {
          const cat = select ? select.value : "all";
          grid.innerHTML = ""; // On vide la grille visuelle

          // 1. Filtrer les liens
          let filteredLinks = Array.from(lightboxSrc);
          if (cat !== "all") {
            filteredLinks = filteredLinks.filter((a) => a.dataset.cat === cat);
          }

          // 2. Mettre à jour GLightbox pour ne contenir QUE cette catégorie
          const selector =
            cat === "all"
              ? `.pc-gallery[data-gallery-id="${galleryId}"] .pc-lightbox-src .pc-glightbox`
              : `.pc-gallery[data-gallery-id="${galleryId}"] .pc-lightbox-src .pc-glightbox[data-cat="${cat}"]`;
          initGLightbox(selector);

          // 3. Peupler la grille visible avec la limite
          const visibleLinks = filteredLinks.slice(0, limit);
          visibleLinks.forEach((srcLink, index) => {
            const a = document.createElement("a");
            a.className = "pc-item pc-glink";
            a.href = srcLink.href;

            const img = document.createElement("img");
            img.src = srcLink.href;
            img.loading = "lazy";
            img.alt = srcLink.dataset.title || "";

            a.appendChild(img);

            // Ouvrir GLightbox au bon index relatif
            a.addEventListener("click", (e) => {
              e.preventDefault();
              if (glightbox) glightbox.openAt(index);
            });

            grid.appendChild(a);
          });

          // 4. Mettre à jour le texte du bouton dynamiquement
          if (btnMore) {
            const count = filteredLinks.length;
            if (cat === "all") {
              btnMore.textContent = gallery.dataset.i18nSeeAll.replace(
                "%d",
                count,
              );
            } else {
              const catLabel = select.options[select.selectedIndex].text;
              btnMore.textContent = gallery.dataset.i18nSeeCat
                .replace("%d", count)
                .replace("%s", catLabel);
            }
          }
        };

        // Écoute du changement de catégorie
        if (select) select.addEventListener("change", updateGrid);

        // Écoute du bouton "Voir plus"
        if (btnMore) {
          btnMore.addEventListener("click", (e) => {
            e.preventDefault();
            if (glightbox) glightbox.openAt(0);
          });
        }

        // Initialisation au premier chargement
        updateGrid();
      }
    });
  }
}

// Lancement automatique dès que le DOM est prêt
document.addEventListener("DOMContentLoaded", () => {
  window.pcGalleryManager = new PCGalleryManager();
});
