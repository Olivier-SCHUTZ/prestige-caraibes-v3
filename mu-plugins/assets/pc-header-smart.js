(function () {
  "use strict";

  // Fonction pour trouver le conteneur racine du header de manière robuste
  function findHeaderRoot() {
    const hg = document.querySelector("[data-pc-hg]");
    const elHeader =
      document.querySelector("header.elementor-location-header") ||
      document.querySelector(".elementor-location-header");

    // Si Elementor encapsule le shortcode, on pilote le wrapper (le vrai “bloc header”)
    if (elHeader && hg && elHeader.contains(hg)) {
      return elHeader;
    }

    // Fallbacks
    return (
      hg ||
      document.querySelector("#pc-header.pc-hg") ||
      document.querySelector("#pc-header") ||
      elHeader ||
      document.querySelector("header#site-header") || // Cible plus spécifique
      document.querySelector("header")
    );
  }

  // S'exécute quand le DOM est prêt
  function onReady(fn) {
    if (document.readyState !== "loading") {
      fn();
    } else {
      document.addEventListener("DOMContentLoaded", fn, { once: true });
    }
  }

  onReady(function () {
    const header = findHeaderRoot();
    if (!header) {
      console.warn(
        "[PC Header Smart] Aucun conteneur de header principal n'a été trouvé."
      );
      return;
    }

    // Désactiver en mode édition Elementor
    if (document.body.classList.contains("elementor-editor-active")) {
      console.log(
        "[PC Header Smart] Mode éditeur Elementor détecté, script désactivé."
      );
      return;
    }

    // Marquer explicitement la cible pilotée par le smart header (CSS stable)
    header.classList.add("pc-hg-smart");

    // --- Paramètres ---
    let lastY = window.scrollY;
    const solidOffset = 24; // Seuil en pixels pour passer le header en mode "solide" (fond blanc)
    const hideOffset = 150; // Seuil en pixels après lequel le header peut commencer à se cacher
    const delta = 10; // Sensibilité du scroll (hystérésis) pour éviter les changements saccadés
    let ticking = false;
    let idleTimer = null;
    const idleMs = 3000; // Temps d'inactivité en millisecondes avant de cacher le header
    // Détection mobile
    const isMobile = window.matchMedia("(max-width: 767.98px)").matches;

    // --- Fonctions d'état ---
    function setSolidState() {
      if (window.scrollY > solidOffset) {
        header.classList.add("pc-solid");
      } else {
        header.classList.remove("pc-solid");
      }
    }

    function setHiddenState(isHidden) {
      // En mobile : pas de logique hover / focus
      if (isMobile) {
        header.classList.toggle("pc-hidden", !!isHidden);
        return;
      }

      // Desktop : logique actuelle conservée
      if (isHidden) {
        if (
          header.matches(":hover") ||
          header.contains(document.activeElement)
        ) {
          return;
        }
        header.classList.add("pc-hidden");
      } else {
        header.classList.remove("pc-hidden");
      }
    }

    // Planifie le masquage du header après inactivité
    function scheduleIdleHide() {
      if (isMobile) return; // pas d’idle hide en mobile
      clearTimeout(idleTimer);
      if (window.scrollY > hideOffset) {
        idleTimer = setTimeout(() => setHiddenState(true), idleMs);
      }
    }

    // --- Le gestionnaire de scroll principal ---
    function onScroll() {
      if (!ticking) {
        window.requestAnimationFrame(() => {
          const y = window.scrollY;

          // 1. Gérer l'état solide/transparent
          setSolidState();

          // 2. Gérer l'affichage/masquage
          // Ne jamais cacher si on est près du haut de la page
          if (y <= hideOffset) {
            setHiddenState(false);
          } else {
            // Cacher en descendant, montrer en remontant
            if (y > lastY + delta) {
              setHiddenState(true);
            } else if (y < lastY - delta) {
              setHiddenState(false);
            }
          }

          lastY = y;
          ticking = false;
          scheduleIdleHide(); // Relance le timer d'inactivité à chaque scroll
        });
        ticking = true;
      }
    }

    // --- Initialisation et écouteurs d'événements ---

    // Appliquer l'état initial au chargement
    setSolidState();
    scheduleIdleHide();

    window.addEventListener("scroll", onScroll, { passive: true });

    // Le header doit toujours réapparaître lors d'une interaction directe
    if (!isMobile) {
      header.addEventListener(
        "mouseenter",
        () => {
          clearTimeout(idleTimer);
          setHiddenState(false);
        },
        { passive: true }
      );
    }

    if (!isMobile) {
      header.addEventListener("focusin", () => {
        clearTimeout(idleTimer);
        setHiddenState(false);
      });
    }

    console.log("[PC Header Smart] Initialisé avec succès.");
  });
})();
