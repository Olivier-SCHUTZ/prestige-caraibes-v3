/**
 * Gestion du Menu Mobile (Offcanvas) et Accordéons
 */
class PCHeaderOffcanvas {
  constructor(root) {
    this.root = root;
    this.oc = this.root.querySelector("#pc-offcanvas");
    this.ocOpenBtn = this.root.querySelector("[data-pc-oc-open]");
    this.ocCloseEls = Array.from(
      this.root.querySelectorAll("[data-pc-oc-close]"),
    );
    this.ocPanel = this.oc
      ? this.oc.querySelector(".pc-offcanvas__panel")
      : null;
    this.accordions = Array.from(
      this.root.querySelectorAll("[data-pc-oc-acc]"),
    );

    this.lastFocus = null;
    // On bind la fonction pour pouvoir l'ajouter et la retirer proprement des eventListeners
    this.boundTrapFocus = this.trapFocus.bind(this);

    if (this.oc && this.ocPanel) {
      this.init();
    }
  }

  getFocusable(container) {
    if (!container) return [];
    return Array.from(
      container.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
      ),
    ).filter(
      (el) =>
        !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length),
    );
  }

  trapFocus(e) {
    if (!document.body.classList.contains("pc-oc-open")) return;
    if (e.key !== "Tab") return;

    const focusables = this.getFocusable(this.ocPanel);
    if (!focusables.length) return;

    const first = focusables[0];
    const last = focusables[focusables.length - 1];

    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  }

  openOffcanvas() {
    if (!this.oc || !this.ocPanel) return;

    this.lastFocus = document.activeElement;
    document.body.classList.add("pc-oc-open");
    this.oc.setAttribute("aria-hidden", "false");
    if (this.ocOpenBtn) this.ocOpenBtn.setAttribute("aria-expanded", "true");

    document.body.style.overflow = "hidden"; // Bloque le scroll de la page en arrière-plan

    const focusables = this.getFocusable(this.ocPanel);
    (focusables[0] || this.ocPanel).focus({ preventScroll: true });

    document.addEventListener("keydown", this.boundTrapFocus);
  }

  closeOffcanvas() {
    if (!this.oc || !this.ocPanel) return;

    document.body.classList.remove("pc-oc-open");
    this.oc.setAttribute("aria-hidden", "true");
    if (this.ocOpenBtn) this.ocOpenBtn.setAttribute("aria-expanded", "false");

    document.body.style.overflow = ""; // Rétablit le scroll

    document.removeEventListener("keydown", this.boundTrapFocus);

    // Redonne le focus à l'élément qui avait ouvert le menu (accessibilité)
    if (this.lastFocus && typeof this.lastFocus.focus === "function") {
      this.lastFocus.focus();
    }
  }

  init() {
    // --- Bouton d'ouverture ---
    if (this.ocOpenBtn) {
      this.ocOpenBtn.addEventListener("click", () => this.openOffcanvas());
    }

    // --- Boutons de fermeture (croix et overlay) ---
    this.ocCloseEls.forEach((el) => {
      el.addEventListener("click", () => this.closeOffcanvas());
    });

    // --- Fermeture avec Échap ---
    document.addEventListener("keydown", (e) => {
      if (
        e.key === "Escape" &&
        document.body.classList.contains("pc-oc-open")
      ) {
        this.closeOffcanvas();
      }
    });

    // --- Accordéons des sous-menus ---
    this.accordions.forEach((btn) => {
      btn.addEventListener("click", () => {
        const expanded = btn.getAttribute("aria-expanded") === "true";
        const panelId = btn.getAttribute("aria-controls");
        const panel = panelId ? document.getElementById(panelId) : null;

        btn.setAttribute("aria-expanded", expanded ? "false" : "true");
        if (panel) panel.hidden = expanded;
      });
    });
  }
}
