/**
 * Core : PC Logement System
 * Rôle : Point d'entrée principal. Gère les manipulations DOM globales
 * et connecte les boutons de l'interface aux modules (La "Glue").
 */
class PCLogementCore {
  constructor() {
    this.initDOMManipulations();
    this.bindGlobalEvents();
  }

  /**
   * Gère les déplacements d'éléments dans le DOM au chargement
   */
  initDOMManipulations() {
    // --- LE DÉMÉNAGEMENT ---
    // Déplace le formulaire de devis dans la bottom sheet sur mobile
    const devisSource = document.querySelector(".pc-devis-section");
    const devisTarget = document.getElementById("logement-devis-sheet-body");

    if (devisSource && devisTarget) {
      devisTarget.appendChild(devisSource);
      console.log("📦 Devis section déplacée avec succès.");
    }
  }

  /**
   * Écouteurs d'événements globaux (La "Colle" entre le HTML et nos modules)
   */
  bindGlobalEvents() {
    // 1. Touche Échap du clavier -> Ferme tout
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        if (window.pcBookingModal && window.pcBookingModal.isOpen)
          window.pcBookingModal.close();
        else if (window.pcBookingSheet && window.pcBookingSheet.isOpen)
          window.pcBookingSheet.close();
      }
    });

    // 2. Clic sur le Bouton Flottant (FAB) -> Ouvre la Bottom Sheet
    const fab = document.getElementById("logement-open-devis-sheet-btn");
    if (fab) {
      fab.addEventListener("click", () => {
        if (window.pcBookingSheet) window.pcBookingSheet.open();
      });
    }

    // 3. Clic sur les boutons "Fermer la Sheet" (Croix, overlay...)
    const closeSheetTriggers = document.querySelectorAll(
      "[data-close-devis-sheet]",
    );
    closeSheetTriggers.forEach((trigger) => {
      trigger.addEventListener("click", () => {
        if (window.pcBookingSheet) window.pcBookingSheet.close();
      });
    });

    // 4. Clic sur "Demander un devis" ou "Réserver" -> Déclenche le Formulaire / Ouvre la Modale
    const openContactBtn = document.getElementById(
      "logement-open-modal-btn-local",
    );
    if (openContactBtn) {
      openContactBtn.addEventListener("click", (ev) => {
        if (window.pcBookingForm) window.pcBookingForm.handleBookingClick(ev);
      });
    }

    // 5. Clic sur les boutons "Fermer la Modale"
    const closeModalTriggers = document.querySelectorAll("[data-close-modal]");
    closeModalTriggers.forEach((trigger) => {
      trigger.addEventListener("click", () => {
        if (window.pcBookingModal) window.pcBookingModal.close();
      });
    });

    // 6. Clic sur le bouton Lodgify -> Déclenche la logique de redirection
    const openLodgifyBtn = document.getElementById(
      "logement-lodgify-reserve-btn",
    );
    if (openLodgifyBtn) {
      openLodgifyBtn.addEventListener(
        "click",
        this.handleLodgifyClick.bind(this),
      );
    }
  }

  /**
   * Gère le clic spécifique à Lodgify (Pont entre le bouton et notre connecteur Lodgify)
   */
  handleLodgifyClick(ev) {
    if (ev) ev.preventDefault();

    const devisSource = document.querySelector(".pc-devis-section");
    const isManualQuote =
      devisSource && devisSource.dataset.manualQuote === "1";

    if (isManualQuote) {
      if (window.pcBookingSheet) window.pcBookingSheet.close();
      if (window.pcBookingModal) window.pcBookingModal.open();
      return;
    }

    const selection = window.currentLogementSelection;
    const cfg = devisSource
      ? JSON.parse(devisSource.dataset.pcDevis || "{}")
      : {};
    const lodgify = window.PCLodgifyConnector
      ? new window.PCLodgifyConnector(cfg)
      : null;
    const devisErrorMsg = document.getElementById("logement-devis-error-msg");

    if (!lodgify) {
      alert("Erreur technique : connecteur Lodgify introuvable.");
      return;
    }

    try {
      // Tentative de redirection via notre module isolé
      lodgify.redirectToCheckout(selection);

      // Si succès visuel : on ferme la sheet et on cache les erreurs
      if (devisErrorMsg) devisErrorMsg.classList.remove("is-visible");
      if (window.pcBookingSheet) window.pcBookingSheet.close();

      const fabPriceDisplay = document.getElementById(
        "fab-logement-price-display",
      );
      if (fabPriceDisplay) {
        const initialText = fabPriceDisplay.innerHTML;
        fabPriceDisplay.textContent = "Redirection...";
        setTimeout(() => {
          if (fabPriceDisplay.textContent === "Redirection...")
            fabPriceDisplay.innerHTML = initialText;
        }, 4000);
      }
    } catch (error) {
      // Si erreur (ex: Popup bloqué ou dates manquantes)
      if (error.message === "PopupBlocked") {
        alert(
          "Votre navigateur a bloqué l'ouverture de la réservation. Veuillez autoriser les popups.",
        );
        if (window.pcBookingSheet) window.pcBookingSheet.close();
        return;
      }

      // Animation d'erreur
      if (devisErrorMsg) {
        devisErrorMsg.textContent = error.message;
        devisErrorMsg.classList.add("is-visible");
        if (devisSource) {
          devisSource.style.transition = "transform 0.1s ease-in-out";
          devisSource.style.transform = "translateX(-10px)";
          setTimeout(() => {
            devisSource.style.transform = "translateX(10px)";
            setTimeout(() => {
              devisSource.style.transform = "translateX(0px)";
            }, 100);
          }, 100);
        }
      } else {
        alert(error.message);
      }
    }
  }
}

// Initialisation globale
const initLogementSystem = () => {
  window.pcLogementCore = new PCLogementCore();
  console.log("🚀 PC Logement Core initialisé et événements liés !");
};

// Branche orchestrateur
if (
  window.PCOrchestrator &&
  typeof window.PCOrchestrator.registerLogementInit === "function"
) {
  window.PCOrchestrator.registerLogementInit(initLogementSystem);
} else {
  // Fallback natif
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initLogementSystem);
  } else {
    initLogementSystem();
  }
}
