/**
 * Gestion du comportement Smart Header (Apparition / Disparition au scroll)
 */
class PCHeaderSmart {
  constructor() {
    this.header = this.findHeaderRoot();
    if (!this.header) {
      console.warn(
        "[PC Header Smart] Aucun conteneur de header principal n'a été trouvé.",
      );
      return;
    }

    // Désactiver en mode édition Elementor
    if (document.body.classList.contains("elementor-editor-active")) {
      console.log(
        "[PC Header Smart] Mode éditeur Elementor détecté, script désactivé.",
      );
      return;
    }

    // Paramètres
    this.lastY = window.scrollY;
    this.solidOffset = 24;
    this.hideOffset = 150;
    this.delta = 10;
    this.ticking = false;
    this.idleTimer = null;
    this.idleMs = 3000;
    this.isMobile = window.matchMedia("(max-width: 767.98px)").matches;

    this.init();
  }

  findHeaderRoot() {
    const hg = document.querySelector("[data-pc-hg]");
    const elHeader =
      document.querySelector("header.elementor-location-header") ||
      document.querySelector(".elementor-location-header");

    if (elHeader && hg && elHeader.contains(hg)) {
      return elHeader;
    }

    return (
      hg ||
      document.querySelector("#pc-header.pc-hg") ||
      document.querySelector("#pc-header") ||
      elHeader ||
      document.querySelector("header#site-header") ||
      document.querySelector("header")
    );
  }

  setSolidState() {
    if (window.scrollY > this.solidOffset) {
      this.header.classList.add("pc-solid");
    } else {
      this.header.classList.remove("pc-solid");
    }
  }

  setHiddenState(isHidden) {
    if (this.isMobile) {
      this.header.classList.toggle("pc-hidden", !!isHidden);
      return;
    }

    if (isHidden) {
      if (
        this.header.matches(":hover") ||
        this.header.contains(document.activeElement)
      ) {
        return;
      }
      this.header.classList.add("pc-hidden");
    } else {
      this.header.classList.remove("pc-hidden");
    }
  }

  scheduleIdleHide() {
    if (this.isMobile) return;
    clearTimeout(this.idleTimer);
    if (window.scrollY > this.hideOffset) {
      this.idleTimer = setTimeout(() => this.setHiddenState(true), this.idleMs);
    }
  }

  onScroll() {
    if (!this.ticking) {
      window.requestAnimationFrame(() => {
        const y = window.scrollY;

        this.setSolidState();

        if (y <= this.hideOffset) {
          this.setHiddenState(false);
        } else {
          if (y > this.lastY + this.delta) {
            this.setHiddenState(true);
          } else if (y < this.lastY - this.delta) {
            this.setHiddenState(false);
          }
        }

        this.lastY = y;
        this.ticking = false;
        this.scheduleIdleHide();
      });
      this.ticking = true;
    }
  }

  init() {
    this.header.classList.add("pc-hg-smart");

    // Active les transitions seulement après le 1er rendu
    requestAnimationFrame(() => {
      this.header.classList.add("pc-hg-ready");
    });

    this.setSolidState();
    this.scheduleIdleHide();

    window.addEventListener("scroll", () => this.onScroll(), { passive: true });

    if (!this.isMobile) {
      this.header.addEventListener(
        "mouseenter",
        () => {
          clearTimeout(this.idleTimer);
          this.setHiddenState(false);
        },
        { passive: true },
      );

      this.header.addEventListener("focusin", () => {
        clearTimeout(this.idleTimer);
        this.setHiddenState(false);
      });
    }
  }
}
