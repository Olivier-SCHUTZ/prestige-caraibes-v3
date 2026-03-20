/**
 * Module Paiements PC Reservation Core
 * Gestion complète des paiements Stripe : liens, cautions, actions, rotation
 */

(function () {
  "use strict";

  // Vérification de l'existence de l'objet global PCR
  if (!window.PCR) {
    window.PCR = {};
  }

  /**
   * Module Paiements
   */
  window.PCR.Payments = {
    /**
     * Initialise le module
     */
    init: function () {
      // Récupération des paramètres au moment de l'exécution
      const params = window.pcResaParams || {};
      const ajaxUrl = params.ajaxUrl || "";
      const nonce = params.manualNonce || "";

      // Initialisation des écouteurs d'événements
      this.attachEventListeners();

      console.log("[PCR.Payments] Module initialisé");
    },

    /**
     * Attache tous les écouteurs d'événements liés aux paiements
     */
    attachEventListeners: function () {
      // Génération de liens de paiement
      document.addEventListener(
        "click",
        this.handlePaymentLinkGeneration.bind(this),
      );

      // Gestion des cautions (empreintes)
      document.addEventListener(
        "click",
        this.handleCautionGeneration.bind(this),
      );

      // Actions sur les cautions (libérer/encaisser)
      document.addEventListener("click", this.handleCautionActions.bind(this));

      // Confirmation d'encaissement
      this.attachCaptureConfirmation();

      // Rotation de caution (renouvellement)
      document.addEventListener("click", this.handleCautionRotation.bind(this));

      // Confirmation de rotation
      this.attachRotateConfirmation();
    },

    /**
     * Gère la génération de liens de paiement Stripe
     * @param {Event} e - Événement de clic
     */
    handlePaymentLinkGeneration: function (e) {
      const btn = e.target.closest(".pc-resa-payment-generate-link");
      if (!btn) return;

      e.preventDefault();

      // Récupération des paramètres au moment de l'exécution
      const params = window.pcResaParams || {};
      const ajaxUrl = params.ajaxUrl || "";
      const nonce = params.manualNonce || "";

      const paymentId = btn.getAttribute("data-payment-id");
      const originalText = btn.textContent;

      if (btn.disabled) return;

      btn.textContent = "⏳ Création...";
      btn.disabled = true;

      const formData = new URLSearchParams();
      formData.append("action", "pc_stripe_get_link");
      formData.append("nonce", nonce);
      formData.append("payment_id", paymentId);

      fetch(ajaxUrl, {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success && data.data.url) {
            navigator.clipboard.writeText(data.data.url).then(() => {
              btn.textContent = "✅ Lien copié !";
              btn.style.color = "#16a34a";
              setTimeout(() => {
                btn.textContent = "🔗 Générer nouveau lien";
                btn.style.color = "#4f46e5";
                btn.disabled = false;
              }, 3000);
            });
          } else {
            alert(
              "Erreur : " +
                (data.data && data.data.message
                  ? data.data.message
                  : "Impossible de créer le lien."),
            );
            btn.textContent = originalText;
            btn.disabled = false;
          }
        })
        .catch((err) => {
          console.error(err);
          alert("Erreur technique.");
          btn.textContent = originalText;
          btn.disabled = false;
        });
    },

    /**
     * Gère la génération de cautions (empreintes bancaires)
     * @param {Event} e - Événement de clic
     */
    handleCautionGeneration: function (e) {
      const btn = e.target.closest(".pc-resa-caution-generate");
      if (!btn) return;

      e.preventDefault();

      // Récupération des paramètres au moment de l'exécution
      const params = window.pcResaParams || {};
      const ajaxUrl = params.ajaxUrl || "";
      const nonce = params.manualNonce || "";

      const resaId = btn.getAttribute("data-resa-id");
      const originalText = btn.textContent;

      if (btn.disabled) return;

      btn.textContent = "⏳ ...";
      btn.disabled = true;

      const formData = new URLSearchParams();
      formData.append("action", "pc_stripe_get_caution_link");
      formData.append("nonce", nonce);
      formData.append("reservation_id", resaId);

      fetch(ajaxUrl, {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success && data.data.url) {
            navigator.clipboard.writeText(data.data.url).then(() => {
              btn.textContent = "✅ Copié !";
              btn.style.color = "#16a34a";
              setTimeout(() => {
                btn.textContent = originalText;
                btn.style.color = "#4f46e5";
                btn.disabled = false;
                // Recharger la page pour voir le changement de statut
                window.location.reload();
              }, 1500);
            });
          } else {
            alert(
              "Erreur : " +
                (data.data && data.data.message
                  ? data.data.message
                  : "Impossible."),
            );
            btn.textContent = originalText;
            btn.disabled = false;
          }
        })
        .catch((err) => {
          console.error(err);
          alert("Erreur technique.");
          btn.textContent = originalText;
          btn.disabled = false;
        });
    },

    /**
     * Gère les actions sur les cautions (libérer/encaisser)
     * @param {Event} e - Événement de clic
     */
    handleCautionActions: function (e) {
      const btn = e.target.closest(".pc-resa-caution-action");
      if (!btn) return;

      e.preventDefault();
      const action = btn.dataset.action;
      const id = btn.dataset.id;
      const ref = btn.dataset.ref;

      if (!ref) {
        alert("Référence Stripe manquante.");
        return;
      }

      // LIBÉRATION - Confirmation simple
      if (action === "release") {
        if (
          !confirm(
            "Voulez-vous libérer (annuler) cette caution maintenant ?\nAction irréversible.",
          )
        )
          return;

        // Récupération des paramètres au moment de l'exécution
        const params = window.pcResaParams || {};
        const ajaxUrl = params.ajaxUrl || "";
        const nonce = params.manualNonce || "";

        btn.disabled = true;
        btn.textContent = "...";

        const formData = new URLSearchParams();
        formData.append("action", "pc_stripe_release_caution");
        formData.append("nonce", nonce);
        formData.append("reservation_id", id);
        formData.append("ref", ref);

        fetch(ajaxUrl, {
          method: "POST",
          body: formData,
        })
          .then((r) => r.json())
          .then((data) => {
            if (data.success) {
              window.location.reload();
            } else {
              alert("Erreur : " + data.data.message);
              btn.disabled = false;
              btn.textContent = "Libérer";
            }
          });
      }

      // ENCAISSEMENT - Ouverture popup
      else if (action === "capture") {
        const max = btn.dataset.max;
        const popup = document.getElementById("pc-capture-caution-popup");

        // Remplissage du popup
        document.getElementById("pc-capture-resa-id").value = id;
        document.getElementById("pc-capture-ref").value = ref;
        document.getElementById("pc-capture-max-display").textContent = max;
        document.getElementById("pc-capture-amount").value = max; // Par défaut tout le montant
        document.getElementById("pc-capture-amount").max = max;
        document.getElementById("pc-capture-note").value = ""; // Reset note

        popup.hidden = false;
      }
    },

    /**
     * Attache l'écouteur pour la confirmation d'encaissement
     */
    attachCaptureConfirmation: function () {
      const captureConfirmBtn = document.getElementById(
        "pc-capture-confirm-btn",
      );
      if (!captureConfirmBtn) return;

      captureConfirmBtn.addEventListener("click", () => {
        // Récupération des paramètres au moment de l'exécution
        const params = window.pcResaParams || {};
        const ajaxUrl = params.ajaxUrl || "";
        const nonce = params.manualNonce || "";

        const id = document.getElementById("pc-capture-resa-id").value;
        const ref = document.getElementById("pc-capture-ref").value;
        const amount = parseFloat(
          document.getElementById("pc-capture-amount").value,
        );
        const note = document.getElementById("pc-capture-note").value;
        const max = parseFloat(
          document.getElementById("pc-capture-amount").max,
        );

        if (isNaN(amount) || amount <= 0 || amount > max) {
          alert("Montant invalide (Max: " + max + "€)");
          return;
        }
        if (!note.trim()) {
          if (!confirm("Voulez-vous vraiment encaisser sans mettre de motif ?"))
            return;
        }

        const originalText = captureConfirmBtn.textContent;
        captureConfirmBtn.disabled = true;
        captureConfirmBtn.textContent = "Encaissement...";

        const formData = new URLSearchParams();
        formData.append("action", "pc_stripe_capture_caution");
        formData.append("nonce", nonce);
        formData.append("reservation_id", id);
        formData.append("ref", ref);
        formData.append("amount", amount);
        formData.append("note", note);

        fetch(ajaxUrl, {
          method: "POST",
          body: formData,
        })
          .then((r) => r.json())
          .then((data) => {
            if (data.success) {
              window.location.reload();
            } else {
              alert("Erreur : " + (data.data.message || "Inconnue"));
              captureConfirmBtn.disabled = false;
              captureConfirmBtn.textContent = originalText;
            }
          })
          .catch((err) => {
            console.error(err);
            alert("Erreur technique.");
            captureConfirmBtn.disabled = false;
            captureConfirmBtn.textContent = originalText;
          });
      });
    },

    /**
     * Gère la rotation (renouvellement) de caution
     * @param {Event} e - Événement de clic
     */
    handleCautionRotation: function (e) {
      const btn = e.target.closest(".pc-resa-caution-rotate");
      if (!btn) return;

      e.preventDefault();
      const id = btn.dataset.id;
      const ref = btn.dataset.ref;

      document.getElementById("pc-rotate-resa-id").value = id;
      document.getElementById("pc-rotate-ref").value = ref;

      document.getElementById("pc-rotate-caution-popup").hidden = false;
    },

    /**
     * Attache l'écouteur pour la confirmation de rotation
     */
    attachRotateConfirmation: function () {
      const rotateConfirmBtn = document.getElementById("pc-rotate-confirm-btn");
      if (!rotateConfirmBtn) return;

      rotateConfirmBtn.addEventListener("click", () => {
        // Récupération des paramètres au moment de l'exécution
        const params = window.pcResaParams || {};
        const ajaxUrl = params.ajaxUrl || "";
        const nonce = params.manualNonce || "";

        const id = document.getElementById("pc-rotate-resa-id").value;
        const ref = document.getElementById("pc-rotate-ref").value;

        const originalText = rotateConfirmBtn.textContent;
        rotateConfirmBtn.disabled = true;
        rotateConfirmBtn.textContent = "Traitement en cours...";

        const formData = new URLSearchParams();
        formData.append("action", "pc_stripe_rotate_caution");
        formData.append("nonce", nonce);
        formData.append("reservation_id", id);
        formData.append("old_ref", ref);

        fetch(ajaxUrl, {
          method: "POST",
          body: formData,
        })
          .then((r) => r.json())
          .then((data) => {
            if (data.success) {
              window.location.reload();
            } else {
              alert("Erreur : " + (data.data.message || "Inconnue"));
              rotateConfirmBtn.disabled = false;
              rotateConfirmBtn.textContent = originalText;
            }
          })
          .catch((err) => {
            console.error(err);
            alert("Erreur technique (voir console).");
            rotateConfirmBtn.disabled = false;
            rotateConfirmBtn.textContent = originalText;
          });
      });
    },
  };
})();
