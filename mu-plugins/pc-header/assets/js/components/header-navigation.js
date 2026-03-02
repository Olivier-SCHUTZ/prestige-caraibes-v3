/**
 * Gestion de la navigation Desktop (Méga panneaux)
 */
class PCHeaderNavigation {
  constructor(root) {
    this.root = root;
    this.triggers = Array.from(this.root.querySelectorAll("[data-pc-panel]"));
    this.panels = Array.from(this.root.querySelectorAll("[data-pc-mega]"));
    this.openPanelId = null;
    this.hoverTimer = null;

    // Récupérer le breakpoint depuis la conf globale ou utiliser 1025 par défaut
    this.bpDesktop =
      window.PCHeaderGlobal && window.PCHeaderGlobal.bpDesktop
        ? window.PCHeaderGlobal.bpDesktop
        : 1025;

    if (this.triggers.length > 0) {
      this.init();
    }
  }

  isDesktop() {
    return window.innerWidth >= this.bpDesktop;
  }

  setExpanded(btn, expanded) {
    btn.setAttribute("aria-expanded", expanded ? "true" : "false");
  }

  closeAllPanels() {
    this.triggers.forEach((t) => this.setExpanded(t, false));
    this.panels.forEach((p) => {
      p.classList.remove("is-open");
      p.setAttribute("aria-hidden", "true");
    });
    this.openPanelId = null;
  }

  openPanel(panelId, focusPanel = false) {
    this.closeAllPanels();

    const btns = this.triggers.filter(
      (t) => t.getAttribute("data-pc-panel") === panelId,
    );
    const panel = document.getElementById(panelId);

    if (!btns.length || !panel) return;

    btns.forEach((b) => this.setExpanded(b, true));
    panel.classList.add("is-open");
    panel.setAttribute("aria-hidden", "false");
    this.openPanelId = panelId;

    if (focusPanel) panel.focus({ preventScroll: true });
  }

  togglePanel(panelId) {
    if (this.openPanelId === panelId) {
      this.closeAllPanels();
    } else {
      this.openPanel(panelId, false);
    }
  }

  init() {
    // --- Événements sur les boutons (Triggers) ---
    this.triggers.forEach((btn) => {
      btn.addEventListener("click", (e) => {
        if (!this.isDesktop()) return;
        e.preventDefault();
        this.togglePanel(btn.getAttribute("data-pc-panel"));
      });

      btn.addEventListener("keydown", (e) => {
        if (!this.isDesktop()) return;
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          this.togglePanel(btn.getAttribute("data-pc-panel"));
        }
        if (e.key === "Escape") {
          this.closeAllPanels();
          btn.focus();
        }
      });

      btn.addEventListener("mouseenter", () => {
        if (!this.isDesktop()) return;
        clearTimeout(this.hoverTimer);
        const panelId = btn.getAttribute("data-pc-panel");
        this.hoverTimer = setTimeout(() => {
          this.openPanel(panelId, false);
        }, 60);
      });
    });

    // --- Événements sur les panneaux (Mega Menus) ---
    this.panels.forEach((panel) => {
      panel.addEventListener("mouseenter", () => {
        if (!this.isDesktop()) return;
        clearTimeout(this.hoverTimer);
      });

      panel.addEventListener("mouseleave", () => {
        if (!this.isDesktop()) return;
        clearTimeout(this.hoverTimer);
        this.hoverTimer = setTimeout(() => this.closeAllPanels(), 120);
      });
    });

    // --- Événements globaux (Fermeture au clic dehors / Échap) ---
    document.addEventListener("click", (e) => {
      if (!this.isDesktop()) return;
      if (!this.openPanelId) return;
      if (!this.root.contains(e.target)) this.closeAllPanels();
    });

    document.addEventListener("keydown", (e) => {
      if (e.key !== "Escape") return;
      if (this.isDesktop()) this.closeAllPanels();
    });
  }
}
