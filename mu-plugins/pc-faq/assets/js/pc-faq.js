/**
 * Module FAQ - Interactivité & Animations (Prestige Caraïbes)
 * Gestion fluide de l'ouverture/fermeture et du comportement "Accordéon".
 */

document.addEventListener("DOMContentLoaded", () => {
  "use strict";

  /**
   * Classe gérant une seule question/réponse (Item)
   */
  class PC_FAQ_Accordion {
    constructor(el, controller) {
      this.el = el;
      this.controller = controller;
      this.summary = el.querySelector(".pc-faq-q");
      this.content = el.querySelector(".pc-faq-a");

      this.animation = null;
      this.isClosing = false;
      this.isExpanding = false;

      // Attache l'événement au clic sur la question
      if (this.summary && this.content) {
        this.summary.addEventListener("click", (e) => this.onClick(e));
      }
    }

    onClick(e) {
      // Empêche le comportement natif instantané du navigateur
      e.preventDefault();
      this.el.style.overflow = "hidden";

      // Gère les états pour permettre l'inversion d'animation en cours de route
      if (this.isClosing || !this.el.open) {
        this.open();
      } else if (this.isExpanding || this.el.open) {
        this.shrink();
      }
    }

    shrink() {
      this.isClosing = true;
      const startHeight = `${this.el.offsetHeight}px`;
      const endHeight = `${this.summary.offsetHeight}px`;

      if (this.animation) {
        this.animation.cancel();
      }

      // Web Animations API (Native & Performante)
      this.animation = this.el.animate(
        {
          height: [startHeight, endHeight],
        },
        {
          duration: 350,
          easing: "cubic-bezier(0.4, 0, 0.2, 1)", // Courbe d'accélération fluide
        },
      );

      this.animation.onfinish = () => this.onAnimationFinish(false);
      this.animation.oncancel = () => (this.isClosing = false);
    }

    open() {
      this.el.style.height = `${this.el.offsetHeight}px`;
      this.el.open = true;
      window.requestAnimationFrame(() => this.expand());
    }

    expand() {
      this.isExpanding = true;

      // Signale au contrôleur qu'on s'ouvre pour fermer les autres (Effet Accordéon)
      if (this.controller) {
        this.controller.closeOthers(this);
      }

      const startHeight = `${this.el.offsetHeight}px`;
      const endHeight = `${this.summary.offsetHeight + this.content.offsetHeight}px`;

      if (this.animation) {
        this.animation.cancel();
      }

      this.animation = this.el.animate(
        {
          height: [startHeight, endHeight],
        },
        {
          duration: 350,
          easing: "cubic-bezier(0.4, 0, 0.2, 1)",
        },
      );

      this.animation.onfinish = () => this.onAnimationFinish(true);
      this.animation.oncancel = () => (this.isExpanding = false);
    }

    onAnimationFinish(isOpen) {
      this.el.open = isOpen;
      this.animation = null;
      this.isClosing = false;
      this.isExpanding = false;
      // Nettoyage des styles en ligne une fois l'animation terminée
      this.el.style.height = this.el.style.overflow = "";
    }
  }

  /**
   * Contrôleur global pour coordonner tous les accordéons
   */
  class PC_FAQ_Controller {
    constructor() {
      this.accordions = [];

      // On initialise tous les accordéons de la page
      const items = document.querySelectorAll(".pc-faq-item");
      items.forEach((item) => {
        this.accordions.push(new PC_FAQ_Accordion(item, this));
      });
    }

    /**
     * Ferme tous les autres accordéons ouverts dans le même conteneur
     */
    closeOthers(currentAccordion) {
      // On identifie le conteneur parent pour ne pas fermer une FAQ d'une autre section
      const currentContainer = currentAccordion.el.closest(".pc-faq-accordion");

      if (!currentContainer) return;

      this.accordions.forEach((acc) => {
        if (acc !== currentAccordion && acc.el.open && !acc.isClosing) {
          const accContainer = acc.el.closest(".pc-faq-accordion");
          // Si l'accordéon fait partie du même groupe, on le ferme doucement
          if (currentContainer === accContainer) {
            acc.shrink();
          }
        }
      });
    }
  }

  // Lancement de l'application FAQ
  new PC_FAQ_Controller();
});
