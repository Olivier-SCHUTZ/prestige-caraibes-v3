/**
 * PC Experience Data Manager
 * Gestionnaire des données expérience - collecte, validation, formatage AJAX
 *
 * @since 0.2.0
 */

(function ($) {
  "use strict";

  /**
   * Classe PCExperienceData
   * Focus sur la gestion des données, pas l'interface
   */
  class PCExperienceData {
    constructor(formSelector = "#experience-modal") {
      this.$form = $(formSelector);
      this.isDirty = false;
      this.originalData = {};
      this.currentData = {};

      this.bindChangeDetection();
    }

    // === CHANGE DETECTION & DIRTY FLAG ===

    /**
     * Initialise la détection de changements
     */
    init() {
      // Réinitialiser l'état
      this.isDirty = false;
      this.originalData = {};
      this.currentData = {};

      // Rebind la détection de changements
      this.bindChangeDetection();

      console.log("✅ PCExperienceData: Initialisé");
    }

    /**
     * Remet à zéro les données du formulaire
     */
    reset() {
      // Vider tous les champs du formulaire
      this.$form.find("input, textarea, select").each(function () {
        const $field = $(this);
        if ($field.is(":checkbox") || $field.is(":radio")) {
          $field.prop("checked", false);
        } else {
          $field.val("");
        }
      });

      // Réinitialiser l'état internal
      this.isDirty = false;
      this.originalData = {};
      this.currentData = {};

      console.log("🔄 PCExperienceData: Formulaire remis à zéro");
    }

    /**
     * Active la détection automatique des changements
     */
    bindChangeDetection() {
      this.$form.on("change input", "input, textarea, select", () => {
        this.setDirty(true);
      });
    }

    /**
     * Marque les données comme modifiées ou non
     */
    setDirty(dirty) {
      const wasDirty = this.isDirty;
      this.isDirty = dirty;

      // Émettre un événement pour l'UI Manager
      if (wasDirty !== dirty) {
        this.$form.trigger("data:dirty-changed", [dirty]);
      }
    }

    /**
     * Retourne l'état dirty
     */
    getDirtyState() {
      return this.isDirty;
    }

    /**
     * Reset le flag dirty et sauvegarde l'état actuel
     */
    resetDirty() {
      this.originalData = this.collect();
      this.setDirty(false);
    }

    // === COLLECTE DES DONNÉES ===

    /**
     * Collecte toutes les données du formulaire
     * @returns {Object} Données collectées
     */
    collect() {
      const formData = {
        // === ONGLET GÉNÉRAL ===
        title: $("#experience-title").val() || "",
        capacity: $("#experience-capacity").val() || "",
        duration: $("#experience-duration").val() || "",
        availability: $("#experience-availability").val() || "InStock",
        status: $("#experience-status").val() || "draft",
        description: $("#experience-description").val() || "",

        // === ONGLET LOCALISATION ===
        address: $("#experience-address").val() || "",
        city: $("#experience-city").val() || "",
        postal_code: $("#experience-postal-code").val() || "",
        latitude: $("#experience-latitude").val() || "",
        longitude: $("#experience-longitude").val() || "",
        meeting_point: $("#experience-meeting-point").val() || "",

        // === ONGLET DÉTAILS SORTIES ===
        exp_accessibilite: this.collectCheckboxes("exp_accessibilite"),
        exp_periode: this.collectCheckboxes("exp_periode"),
        exp_jour: this.collectCheckboxes("exp_jour"),
        exp_periodes_fermeture: this.collectRepeaterData("fermeture-periodes"),
        exp_lieux_horaires_depart: this.collectRepeaterData("lieux-horaires"),

        // === ONGLET INCLUSIONS & PRÉ-REQUIS ===
        exp_prix_comprend: $("#experience-prix-comprend").val() || "",
        exp_prix_ne_comprend_pas:
          $("#experience-prix-ne-comprend-pas").val() || "",
        exp_a_prevoir: this.collectCheckboxes("exp_a_prevoir"),

        // === ONGLET DÉTAILS SERVICES ===
        exp_delai_de_reservation: this.collectCheckboxes(
          "exp_delai_de_reservation",
        ),
        exp_zone_intervention: this.collectCheckboxes("exp_zone_intervention"),
        exp_type_de_prestation: $("#experience-type-prestation").val() || "",
        exp_heure_limite_de_commande:
          $("#experience-heure-limite-commande").val() || "",
        exp_le_service_comprend: $("#experience-service-comprend").val() || "",
        exp_service_a_prevoir: $("#experience-service-a-prevoir").val() || "",

        // === ONGLET FAQ ===
        exp_faq: this.collectRepeaterData("faq"),

        // === ONGLET TARIFS ===
        taux_tva: $("#experience-taux-tva").val() || "",
        exp_types_de_tarifs: this.collectRepeaterData("types-de-tarifs"),
        regles_de_paiement: this.collectPaymentRules(),

        // === ONGLET MÉDIAS ===
        featured_image: $("#experience-featured-image").val() || "",
        gallery_urls: $("#experience-gallery-urls").val() || "",
        video_urls: $("#experience-video-urls").val() || "",
      };

      this.currentData = formData;
      return formData;
    }

    // === POPULATION DES DONNÉES ===

    /**
     * Remplit le formulaire avec les données fournies
     * @param {Object} data Données à afficher
     */
    populate(data) {
      if (!data || typeof data !== "object") {
        console.warn("PCExperienceData.populate: données invalides", data);
        return;
      }

      // === ONGLET GÉNÉRAL ===
      $("#experience-title").val(data.title || "");
      $("#experience-capacity").val(data.capacity || "");
      $("#experience-duration").val(data.duration || "");
      $("#experience-availability").val(data.availability || "InStock");
      $("#experience-status").val(data.status || "draft");
      $("#experience-description").val(data.description || "");

      // === ONGLET LOCALISATION ===
      $("#experience-address").val(data.address || "");
      $("#experience-city").val(data.city || "");
      $("#experience-postal-code").val(data.postal_code || "");
      $("#experience-latitude").val(data.latitude || "");
      $("#experience-longitude").val(data.longitude || "");
      $("#experience-meeting-point").val(data.meeting_point || "");

      // === ONGLET DÉTAILS SORTIES ===
      this.populateCheckboxes("exp_accessibilite", data.exp_accessibilite);
      this.populateCheckboxes("exp_periode", data.exp_periode);
      this.populateCheckboxes("exp_jour", data.exp_jour);
      this.populateRepeaterData(
        "fermeture-periodes",
        data.exp_periodes_fermeture,
      );
      this.populateRepeaterData(
        "lieux-horaires",
        data.exp_lieux_horaires_depart,
      );

      // === ONGLET INCLUSIONS & PRÉ-REQUIS ===
      $("#experience-prix-comprend").val(data.exp_prix_comprend || "");
      $("#experience-prix-ne-comprend-pas").val(
        data.exp_prix_ne_comprend_pas || "",
      );
      this.populateCheckboxes("exp_a_prevoir", data.exp_a_prevoir);

      // === ONGLET DÉTAILS SERVICES ===
      this.populateCheckboxes(
        "exp_delai_de_reservation",
        data.exp_delai_de_reservation,
      );
      this.populateCheckboxes(
        "exp_zone_intervention",
        data.exp_zone_intervention,
      );
      $("#experience-type-prestation").val(data.exp_type_de_prestation || "");
      $("#experience-heure-limite-commande").val(
        data.exp_heure_limite_de_commande || "",
      );
      $("#experience-service-comprend").val(data.exp_le_service_comprend || "");
      $("#experience-service-a-prevoir").val(data.exp_service_a_prevoir || "");

      // === ONGLET FAQ ===
      this.populateRepeaterData("faq", data.exp_faq);

      // === ONGLET TARIFS ===
      $("#experience-taux-tva").val(data.taux_tva || "");
      this.populateRepeaterData("types-de-tarifs", data.exp_types_de_tarifs);
      this.populatePaymentRules(data.regles_de_paiement);

      // === ONGLET MÉDIAS ===
      $("#experience-featured-image").val(data.featured_image || "");
      $("#experience-gallery-urls").val(data.gallery_urls || "");
      $("#experience-video-urls").val(data.video_urls || "");

      // Sauvegarder l'état initial
      this.resetDirty();
    }

    // === VALIDATION ===

    /**
     * Valide les données collectées
     * @returns {Object} {isValid: boolean, errors: Array}
     */
    validate() {
      const data = this.collect();
      const errors = [];

      // Validation titre obligatoire
      if (!data.title || data.title.trim().length === 0) {
        errors.push({
          field: "title",
          message: "Le titre de l'expérience est obligatoire.",
        });
      }

      // Validation durée numérique
      if (data.duration && isNaN(parseFloat(data.duration))) {
        errors.push({
          field: "duration",
          message: "La durée doit être un nombre valide.",
        });
      }

      // Validation capacité
      if (
        data.capacity &&
        (isNaN(parseInt(data.capacity)) || parseInt(data.capacity) < 1)
      ) {
        errors.push({
          field: "capacity",
          message: "La capacité doit être un nombre entier positif.",
        });
      }

      // Validation TVA
      if (data.taux_tva && isNaN(parseFloat(data.taux_tva))) {
        errors.push({
          field: "taux_tva",
          message: "Le taux de TVA doit être un nombre valide.",
        });
      }

      // Validation tarifs (si présents)
      if (data.exp_types_de_tarifs && Array.isArray(data.exp_types_de_tarifs)) {
        data.exp_types_de_tarifs.forEach((tarif, index) => {
          if (
            tarif.exp_tarifs_lignes &&
            Array.isArray(tarif.exp_tarifs_lignes)
          ) {
            tarif.exp_tarifs_lignes.forEach((ligne, ligneIndex) => {
              if (ligne.tarif_valeur && isNaN(parseFloat(ligne.tarif_valeur))) {
                errors.push({
                  field: `tarif_${index}_ligne_${ligneIndex}_valeur`,
                  message: `La valeur du tarif ligne ${ligneIndex + 1} du type ${index + 1} doit être numérique.`,
                });
              }
            });
          }
        });
      }

      return {
        isValid: errors.length === 0,
        errors: errors,
      };
    }

    // === FORMATAGE AJAX ===

    /**
     * Formate les données pour l'envoi AJAX
     * @returns {Object} Données formatées pour WordPress
     */
    formatForAjax() {
      const data = this.collect();

      return {
        action: "pc_experience_save",
        nonce: pcReservationVars?.nonce || "",
        post_id: this.getCurrentExperienceId(),

        // Données de base
        title: this.sanitizeData(data.title, "text"),
        status: data.status,
        content: this.sanitizeData(data.content, "html"),

        // Préfixer tous les champs ACF avec 'acf_'
        ...this.prefixACFFields(data),
      };
    }

    /**
     * Préfixe tous les champs ACF avec 'acf_' pour WordPress
     */
    prefixACFFields(data) {
      const prefixed = {};

      // Liste des champs à préfixer (tous sauf title, status, content)
      Object.keys(data).forEach((key) => {
        if (!["title", "status", "content"].includes(key)) {
          prefixed[`acf_${key}`] = data[key];
        }
      });

      return prefixed;
    }

    // === TRAITEMENT DES IMAGES ===

    /**
     * Traite les données d'images pour la sauvegarde
     * @param {Object} imageData Données d'image (ID ou URL)
     * @returns {number|string} ID d'attachment ou URL
     */
    processImages(imageData) {
      if (!imageData) return "";

      // Si c'est déjà un ID numérique
      if (typeof imageData === "number" || /^\d+$/.test(String(imageData))) {
        return parseInt(imageData);
      }

      // Si c'est une URL, la laisser telle quelle (le backend gérera la conversion)
      if (
        typeof imageData === "string" &&
        (imageData.includes("wp-content/uploads") ||
          imageData.startsWith("http"))
      ) {
        return imageData;
      }

      // Cas d'objet attachment WordPress
      if (typeof imageData === "object" && imageData.id) {
        return parseInt(imageData.id);
      }

      return "";
    }

    // === HELPERS CHECKBOXES ===

    /**
     * Collecte les valeurs des checkboxes multiples
     */
    collectCheckboxes(name) {
      const values = [];
      $(`input[name="${name}[]"]:checked`).each(function () {
        values.push($(this).val());
      });
      return values;
    }

    /**
     * Collecte la valeur d'une checkbox boolean
     */
    collectBooleanCheckbox(id) {
      return $(`#${id}`).is(":checked") ? "1" : "0";
    }

    /**
     * Remplit des checkboxes multiples
     */
    populateCheckboxes(name, values) {
      // Décocher toutes d'abord
      $(`input[name="${name}[]"]`).prop("checked", false);

      if (!values) return;

      let valuesArray = [];
      if (Array.isArray(values)) {
        valuesArray = values.map((item) => {
          if (typeof item === "object" && item.value) {
            return item.value; // ACF retourne {value: "xxx", label: "xxx"}
          }
          return item;
        });
      } else if (typeof values === "string") {
        valuesArray = values
          .split(",")
          .map((v) => v.trim())
          .filter((v) => v);
      }

      // Cocher les bonnes valeurs
      valuesArray.forEach((value) => {
        $(`input[name="${name}[]"][value="${value}"]`).prop("checked", true);
      });
    }

    /**
     * Remplit une checkbox boolean
     */
    populateBooleanCheckbox(id, value) {
      const isChecked =
        value === "1" || value === true || value === "true" || value === 1;
      $(`#${id}`).prop("checked", isChecked);
    }

    // === SANITISATION DES DONNÉES ===

    /**
     * Sanitise les données selon leur type
     * @param {*} value Valeur à sanitiser
     * @param {string} type Type de sanitisation
     * @returns {*} Valeur sanitisée
     */
    sanitizeData(value, type = "text") {
      if (value === null || value === undefined) {
        return "";
      }

      switch (type) {
        case "html":
          // Garder les balises HTML de base autorisées
          return String(value);

        case "number":
          const num = parseFloat(value);
          return isNaN(num) ? 0 : num;

        case "int":
          const int = parseInt(value);
          return isNaN(int) ? 0 : int;

        case "email":
          return String(value).toLowerCase().trim();

        case "url":
          return String(value).trim();

        case "text":
        default:
          return String(value).trim();
      }
    }

    // === HELPERS AVANCÉS ===

    /**
     * Collecte les données d'un relationship field
     */
    collectRelationshipField(fieldId) {
      const values = $(`#${fieldId}`).val();
      if (!values) return [];

      return values
        .split(",")
        .map((v) => parseInt(v.trim()))
        .filter((v) => !isNaN(v) && v > 0);
    }

    /**
     * Remplit un relationship field
     */
    populateRelationshipField(fieldId, values) {
      if (!values || !Array.isArray(values)) {
        $(`#${fieldId}`).val("");
        return;
      }

      const ids = values
        .map((v) => {
          if (typeof v === "object" && v.ID) return v.ID;
          return parseInt(v);
        })
        .filter((id) => !isNaN(id) && id > 0);

      $(`#${fieldId}`).val(ids.join(","));
    }

    /**
     * Collecte les données d'un repeater
     */
    collectRepeaterData(repeaterName) {
      const data = [];
      // Logique spécifique selon le type de repeater
      // À implémenter selon les besoins spécifiques
      return data;
    }

    /**
     * Remplit un repeater avec des données
     */
    populateRepeaterData(repeaterName, data) {
      // Logique spécifique selon le type de repeater
      // À implémenter selon les besoins spécifiques
    }

    /**
     * Collecte les données de galerie photo
     */
    collectGalleryData(fieldId) {
      const value = $(`#${fieldId}`).val();
      if (!value) return [];

      return value
        .split(",")
        .map((v) => parseInt(v.trim()))
        .filter((v) => !isNaN(v) && v > 0);
    }

    /**
     * Remplit une galerie photo
     */
    populateGalleryData(fieldId, data) {
      if (!data || !Array.isArray(data)) {
        $(`#${fieldId}`).val("");
        return;
      }

      const ids = data
        .map((item) => {
          if (typeof item === "object" && item.ID) return item.ID;
          return parseInt(item);
        })
        .filter((id) => !isNaN(id) && id > 0);

      $(`#${fieldId}`).val(ids.join(","));
    }

    /**
     * Remplit un champ image
     */
    populateImageField(fieldId, value) {
      if (!value) {
        $(`#${fieldId}`).val("");
        return;
      }

      // Si c'est un ID numérique
      if (typeof value === "number" || /^\d+$/.test(String(value))) {
        $(`#${fieldId}`).val(parseInt(value));
      }
      // Si c'est une URL
      else if (typeof value === "string") {
        $(`#${fieldId}`).val(value);
      }
      // Si c'est un objet WordPress
      else if (typeof value === "object" && value.ID) {
        $(`#${fieldId}`).val(parseInt(value.ID));
      }
    }

    /**
     * Collecte les règles de paiement
     */
    collectPaymentRules() {
      return {
        pc_pay_mode: $("#exp-pc-pay-mode").val() || "",
        pc_deposit_type: $("#exp-pc-deposit-type").val() || "",
        pc_deposit_value: $("#exp-pc-deposit-value").val() || "",
        pc_balance_delay_days: $("#exp-pc-balance-delay-days").val() || "",
        pc_caution_amount: $("#exp-pc-caution-amount").val() || "",
        pc_caution_mode: $("#exp-pc-caution-mode").val() || "",
      };
    }

    /**
     * Remplit les règles de paiement
     */
    populatePaymentRules(data) {
      if (!data || typeof data !== "object") return;

      $("#exp-pc-pay-mode").val(data.pc_pay_mode || "");
      $("#exp-pc-deposit-type").val(data.pc_deposit_type || "");
      $("#exp-pc-deposit-value").val(data.pc_deposit_value || "");
      $("#exp-pc-balance-delay-days").val(data.pc_balance_delay_days || "");
      $("#exp-pc-caution-amount").val(data.pc_caution_amount || "");
      $("#exp-pc-caution-mode").val(data.pc_caution_mode || "");
    }

    /**
     * Récupère l'ID de l'expérience courante
     */
    getCurrentExperienceId() {
      return window.currentExperienceId || 0;
    }

    /**
     * Retourne un résumé des changements détectés
     */
    getChangeSummary() {
      if (!this.isDirty) return null;

      const current = this.collect();
      const changes = {};

      Object.keys(current).forEach((key) => {
        if (current[key] !== this.originalData[key]) {
          changes[key] = {
            from: this.originalData[key],
            to: current[key],
          };
        }
      });

      return changes;
    }
  }

  // Export global
  window.PCExperienceData = PCExperienceData;
})(jQuery);
