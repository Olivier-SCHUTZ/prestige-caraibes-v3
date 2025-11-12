// wp-content/mu-plugins/assets/pc-orchestrator.js
// Prestige Caraïbes — Orchestrateur JS (Flatpickr + Devis + Logement)

(function () {
  console.log("[pc-orchestrator] chargé");

  function onDomReady(cb) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", function handler() {
        document.removeEventListener("DOMContentLoaded", handler);
        cb();
      });
    } else {
      cb();
    }
  }

  // Attente centralisée de flatpickr + locale FR
  function waitForFlatpickr(cb, maxTries, delay) {
    maxTries = maxTries || 40; // ~3s max
    delay = delay || 80;
    var tries = 0;

    function check() {
      var hasFpFn =
        (window.flatpickr && typeof window.flatpickr === "function") ||
        (window.Flatpickr && typeof window.Flatpickr === "function");

      var hasLocale =
        window.flatpickr && window.flatpickr.l10ns && window.flatpickr.l10ns.fr;

      if (hasFpFn && hasLocale) {
        try {
          if (
            typeof window.flatpickr === "function" &&
            window.flatpickr.l10ns.fr
          ) {
            window.flatpickr.localize(window.flatpickr.l10ns.fr);
          }
        } catch (e) {
          console.warn(
            "[pc-orchestrator] Impossible de localiser flatpickr en FR:",
            e
          );
        }
        cb();
      } else if (++tries < maxTries) {
        setTimeout(check, delay);
      } else {
        console.error(
          "[pc-orchestrator] flatpickr/fr introuvable après " +
            tries +
            " essais."
        );
      }
    }

    check();
  }

  var devisInitFn = null;
  var logementInitFn = null;

  var orchestrator = {
    onDomReady: onDomReady,

    onFlatpickrReady: function (cb) {
      onDomReady(function () {
        waitForFlatpickr(cb);
      });
    },

    registerDevisInit: function (fn) {
      devisInitFn = fn;
      // Dès qu'on enregistre, on tente une init
      orchestrator.initDevis();
    },

    registerLogementInit: function (fn) {
      logementInitFn = fn;
      orchestrator.initLogement();
    },

    initDevis: function () {
      if (!devisInitFn) return;
      console.log("[pc-orchestrator] initDevis()");
      orchestrator.onFlatpickrReady(function () {
        try {
          devisInitFn();
        } catch (e) {
          console.error("[pc-orchestrator] Erreur init devis:", e);
        }
      });
    },

    initLogement: function () {
      if (!logementInitFn) return;
      console.log("[pc-orchestrator] initLogement()");
      orchestrator.onDomReady(function () {
        try {
          logementInitFn();
        } catch (e) {
          console.error("[pc-orchestrator] Erreur init logement:", e);
        }
      });
    },

    initAll: function () {
      orchestrator.initDevis();
      orchestrator.initLogement();
    },
  };

  window.PCOrchestrator = orchestrator;

  // On lance une première fois (au cas où les modules sont déjà enregistrés)
  orchestrator.initAll();
})();
