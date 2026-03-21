import { defineStore } from "pinia";
import reservationApi from "../services/reservation-api";

export const useReservationsStore = defineStore("reservations", {
  state: () => ({
    items: [],
    selectedReservation: null,
    reservationDetails: null,
    isDetailModalOpen: false,
    isCreateModalOpen: false,
    isLoading: false,
    isLoadingDetails: false,
    bookingItems: { locations: [], experiences: [] },
    error: null,
    currentPage: 1,
    totalPages: 0,
    perPage: 30,
    totalItems: 0,
    filters: {
      type: "all",
    },
    // NOUVEAU : État du moteur de prix
    quotePreview: null,
    isCalculating: false,
    currentLogementConfig: null,
    prefillData: null,
  }),

  actions: {
    /**
     * Modifie un filtre et recharge la liste à la page 1
     */
    setFilter(key, value) {
      this.filters[key] = value;
      this.fetchList(1); // Retour à la page 1 pour éviter les pages vides
    },

    /**
     * Récupère la VRAIE liste des réservations depuis la base de données
     */
    async fetchList(page = 1) {
      this.isLoading = true;
      this.error = null;

      try {
        // On passe les filtres à l'API
        const response = await reservationApi.getList(page, this.filters);

        if (response.data.success) {
          this.items = response.data.data.reservations;

          // NOUVEAU : Mise à jour des compteurs de pagination
          this.totalItems = response.data.data.total;
          this.currentPage = page;
          this.totalPages = Math.ceil(this.totalItems / this.perPage);
        } else {
          throw new Error(
            response.data.data?.message ||
              "Erreur lors du chargement des réservations",
          );
        }
      } catch (err) {
        this.error =
          err.message || "Impossible de charger la liste des réservations.";
        console.error("Reservations Store Error:", err);
      } finally {
        this.isLoading = false;
      }
    },

    /**
     * Charge les logements et expériences depuis l'API
     */
    async fetchBookingItems() {
      try {
        const response = await reservationApi.getBookingItems();
        if (response.data.success) {
          this.bookingItems = response.data.data;
        }
      } catch (err) {
        console.error(
          "Erreur lors du chargement des items de réservation",
          err,
        );
      }
    },

    /**
     * Ferme la modale de création
     */
    closeCreateModal() {
      this.isCreateModalOpen = false;
    },

    /**
     * Calcule le devis (Localement pour Expérience, via API pour Logement)
     */
    async calculateQuote(formData) {
      this.isCalculating = true;
      this.error = null;

      try {
        // 🌴 1. MOTEUR LOCAL POUR LES EXPÉRIENCES (Reproduction 100% fidèle du legacy)
        if (formData.type === "experience") {
          const expId = String(formData.item_id);
          const tarifKey = formData.experience_tarif_type;

          // 🚀 FIX : On lit les tarifs depuis Pinia et non plus depuis l'ancienne variable globale
          const allTariffs = this.bookingItems?.experienceTarifs || {};
          const expTariffs = allTariffs[expId] || [];
          const config = expTariffs.find((t) => t.key === tarifKey);

          if (!config) {
            this.quotePreview = null;
            this.isCalculating = false;
            return;
          }

          // Cas Spécial : Tarif sur devis
          if (config.code === "sur-devis") {
            this.quotePreview = {
              montant_total: 0,
              lignes_devis: [
                {
                  label: "Tarif sur devis (prix à définir)",
                  price: "Sur devis",
                },
              ],
            };
            this.isCalculating = false;
            return;
          }

          let total = 0;
          const lignes_devis = [];
          const customQtyMap = formData.customQty || {};
          const selectedOptions = formData.options || {};

          // A. LIGNES PRINCIPALES & PRIVATISATION
          (config.lines || []).forEach((line, index) => {
            const type = String(line.type || "personnalise").toLowerCase();
            const unit = parseFloat(line.price) || 0;
            let qty = 1; // Par défaut à 1 pour les forfaits

            if (type === "adulte") qty = Number(formData.adultes) || 0;
            else if (type === "enfant") qty = Number(formData.enfants) || 0;
            else if (type === "bebe") qty = Number(formData.bebes) || 0;
            else if (line.enable_qty) {
              const mapKey = line.uid || `line_${index}`;
              if (typeof customQtyMap[mapKey] !== "undefined") {
                qty = Number(customQtyMap[mapKey]) || 0;
              } else if (line.default_qty) {
                qty = Number(line.default_qty) || 0;
              } else {
                qty = 0;
              }
            }

            // 1. Si la quantité est 0, on ignore totalement la ligne (plus de lignes parasites !)
            if (qty <= 0) {
              return;
            }

            // 2. Si la quantité est > 0, on ajoute la ligne ET on colle son observation directement avec
            const amount = qty * unit;
            let prefix = qty > 1 && type !== "personnalise" ? `${qty} ` : "";
            const label = `${prefix}${line.label || type}`.trim();
            const cleanLabel = (line.label || type).trim();

            lignes_devis.push({
              label: label,
              clean_label: cleanLabel,
              qty: qty,
              amount:
                type === "bebe" && unit === 0 && config.code !== "sur-devis"
                  ? 0
                  : amount,
              price:
                type === "bebe" && unit === 0 && config.code !== "sur-devis"
                  ? "Gratuit"
                  : amount + " €",
              observation: line.observation || null, // L'observation est toujours attachée à la ligne facturée
            });

            if (
              !(type === "bebe" && unit === 0 && config.code !== "sur-devis")
            ) {
              total += amount;
            }
          });

          // C. OPTIONS SUPPLÉMENTAIRES (Repas, Matériel...)
          if (config.options && config.options.length > 0) {
            config.options.forEach((opt, index) => {
              const optId = opt.uid || `option_${index}`;
              const selOpt = selectedOptions[optId];

              if (selOpt && selOpt.selected) {
                const optQty = opt.enable_qty ? Number(selOpt.qty) || 1 : 1;
                const amount = (parseFloat(opt.price) || 0) * optQty;
                const label =
                  optQty > 1 ? `${opt.label} × ${optQty}` : opt.label;

                lignes_devis.push({
                  label: label,
                  clean_label: opt.label,
                  qty: optQty,
                  amount: amount,
                  price: amount + " €",
                });
                total += amount;
              }
            });
          }

          this.quotePreview = {
            montant_total: total,
            lignes_devis:
              lignes_devis.length > 0
                ? lignes_devis
                : [{ label: "Aucune ligne facturable", price: "0 €" }],
          };
          this.isCalculating = false;
          return;
        }

        // 🏠 2. CALCUL API POUR LES LOGEMENTS (Le serveur gère toujours les logements)
        const response = await reservationApi.calculatePrice(formData);
        if (response.data.success) {
          this.quotePreview = response.data.data;
        } else {
          console.warn("Erreur API Logement :", response.data.data?.message);
        }
      } catch (err) {
        console.error("Erreur Moteur de prix :", err);
      } finally {
        this.isCalculating = false;
      }
    },

    /**
     * Crée une nouvelle réservation / devis
     */
    async createReservation(formData) {
      this.isLoading = true;
      this.error = null;

      try {
        // On construit le payload attendu par le backend (qui accepte un format hybride en attendant)
        // Le backend legacy attend 'lines_json' et 'montant_total'
        const payload = {
          ...formData,
          // 🚀 On utilise en priorité le total complet calculé par BookingForm.vue (qui inclut les remises !)
          montant_total:
            formData.montant_total !== undefined
              ? formData.montant_total
              : this.quotePreview
                ? this.quotePreview.montant_total
                : 0,
          lines_json:
            this.quotePreview && this.quotePreview.lignes_devis
              ? JSON.stringify(this.quotePreview.lignes_devis)
              : "[]",
        };

        const response = await reservationApi.createManual(payload);

        if (response.data.success) {
          // On recharge la liste pour voir la nouvelle réservation
          await this.fetchList(1);
          // On ferme la modale et on purge le devis
          this.closeCreateModal();
          this.quotePreview = null;
          return { success: true, message: response.data.data.message };
        } else {
          // On throw pour que le composant puisse attraper l'erreur et l'afficher
          throw new Error(
            response.data.data?.message || "Erreur lors de la création.",
          );
        }
      } catch (err) {
        console.error("Erreur de création :", err);
        throw err; // On relance l'erreur pour la gérer dans le composant Vue
      } finally {
        this.isLoading = false;
      }
    },

    /**
     * Ouvre la modale et lance la récupération des détails
     */
    async openDetailModal(reservation) {
      this.selectedReservation = reservation;
      this.reservationDetails = null; // On purge l'ancienne donnée
      this.isDetailModalOpen = true;
      this.isLoadingDetails = true;

      try {
        const response = await reservationApi.getDetails(reservation.id);
        if (response.data.success) {
          let data = response.data.data;

          // 🚀 Nettoyage de l'encodage Unicode legacy (ex: "H\u00e9bergement" -> "Hébergement")
          const fixUnicode = (obj) => {
            if (typeof obj === "string") {
              // 1. Corrige le format valide (avec antislash)
              let str = obj.replace(/\\u([\dA-F]{4})/gi, (match, grp) =>
                String.fromCharCode(parseInt(grp, 16)),
              );
              // 2. Corrige le format cassé par la BDD WordPress (antislash manquant)
              const brokenMap = {
                u00e9: "é",
                u00e8: "è",
                u00ea: "ê",
                u00eb: "ë",
                u00e0: "à",
                u00e2: "â",
                u00e4: "ä",
                u00ee: "î",
                u00ef: "ï",
                u00f4: "ô",
                u00f6: "ö",
                u00f9: "ù",
                u00fb: "û",
                u00fc: "ü",
                u00e7: "ç",
                u20ac: "€",
                u0027: "'",
                u00a0: " ",
              };
              for (const [broken, fixed] of Object.entries(brokenMap)) {
                str = str.split(broken).join(fixed);
              }
              return str;
            } else if (Array.isArray(obj)) {
              return obj.map(fixUnicode);
            } else if (obj !== null && typeof obj === "object") {
              const newObj = {};
              for (const key in obj) {
                newObj[key] = fixUnicode(obj[key]);
              }
              return newObj;
            }
            return obj;
          };

          data = fixUnicode(data);

          // 🚀 FIX LECTURE : Nettoyage des lignes du front-end à la volée pour un affichage parfait dans ReservationModal
          if (data.quote_lines && Array.isArray(data.quote_lines)) {
            data.quote_lines = data.quote_lines.map((line) => {
              let fixedLine = { ...line };

              // 1. On extrait la quantité du vieux texte "2 x Location"
              let qty = Number(fixedLine.qty);
              if (!fixedLine.qty || isNaN(qty) || qty === 0) {
                const match = (fixedLine.label || "").match(
                  /(?:^(\d+)\s*[x×X])|(?:[x×X]\s*(\d+))/,
                );
                fixedLine.qty = match ? parseInt(match[1] || match[2], 10) : 1;
              }

              // 2. On utilise le label propre sans les "x 2"
              if (fixedLine.clean_label) {
                fixedLine.label = fixedLine.clean_label;
              }

              // 3. On répare les prix "undefined" de l'ancien système
              if (fixedLine.amount === undefined)
                fixedLine.amount = parseFloat(fixedLine.price) || 0;
              if (
                fixedLine.price === undefined ||
                fixedLine.price === null ||
                String(fixedLine.price).includes("undefined")
              ) {
                fixedLine.price = fixedLine.amount + " €";
              }

              // 4. On cache la vieille ligne titre "Options" à 0€ (ou on la marque comme observation)
              if (
                (fixedLine.label || "").toLowerCase().trim() === "options" &&
                fixedLine.amount === 0
              ) {
                fixedLine.is_observation_only = true;
                fixedLine.label = "Options sélectionnées";
                fixedLine.price = "";
              }

              return fixedLine;
            });
          }

          this.reservationDetails = data;
          console.log("Détails chargés et nettoyés :", this.reservationDetails); // <-- TRÈS IMPORTANT POUR NOTRE TEST
        }
      } catch (err) {
        console.error("Erreur chargement détails :", err);
      } finally {
        this.isLoadingDetails = false;
      }
    },

    /**
     * 🚀 NOUVEAU : Met à jour silencieusement les détails d'une réservation
     * sans déclencher d'état de chargement ni modifier l'état de la modale.
     * Utilisé principalement après une action de paiement/caution réussie.
     */
    async refreshCurrentReservation(reservationId) {
      try {
        const response = await reservationApi.getDetails(reservationId);

        if (response.data.success) {
          let data = response.data.data;

          // Réutilisation de ta logique de nettoyage Unicode
          // 🚀 Nettoyage de l'encodage Unicode legacy (ex: "H\u00e9bergement" -> "Hébergement")
          const fixUnicode = (obj) => {
            if (typeof obj === "string") {
              // 1. Corrige le format valide (avec antislash)
              let str = obj.replace(/\\u([\dA-F]{4})/gi, (match, grp) =>
                String.fromCharCode(parseInt(grp, 16)),
              );
              // 2. Corrige le format cassé par la BDD WordPress (antislash manquant)
              const brokenMap = {
                u00e9: "é",
                u00e8: "è",
                u00ea: "ê",
                u00eb: "ë",
                u00e0: "à",
                u00e2: "â",
                u00e4: "ä",
                u00ee: "î",
                u00ef: "ï",
                u00f4: "ô",
                u00f6: "ö",
                u00f9: "ù",
                u00fb: "û",
                u00fc: "ü",
                u00e7: "ç",
                u20ac: "€",
                u0027: "'",
                u00a0: " ",
              };
              for (const [broken, fixed] of Object.entries(brokenMap)) {
                str = str.split(broken).join(fixed);
              }
              return str;
            } else if (Array.isArray(obj)) {
              return obj.map(fixUnicode);
            } else if (obj !== null && typeof obj === "object") {
              const newObj = {};
              for (const key in obj) {
                newObj[key] = fixUnicode(obj[key]);
              }
              return newObj;
            }
            return obj;
          };

          data = fixUnicode(data);

          // 🚀 FIX LECTURE : On applique le même nettoyage lors du rafraîchissement
          if (data.quote_lines && Array.isArray(data.quote_lines)) {
            data.quote_lines = data.quote_lines.map((line) => {
              let fixedLine = { ...line };
              let qty = Number(fixedLine.qty);
              if (!fixedLine.qty || isNaN(qty) || qty === 0) {
                const match = (fixedLine.label || "").match(
                  /(?:^(\d+)\s*[x×X])|(?:[x×X]\s*(\d+))/,
                );
                fixedLine.qty = match ? parseInt(match[1] || match[2], 10) : 1;
              }
              if (fixedLine.clean_label)
                fixedLine.label = fixedLine.clean_label;
              if (fixedLine.amount === undefined)
                fixedLine.amount = parseFloat(fixedLine.price) || 0;
              if (
                fixedLine.price === undefined ||
                fixedLine.price === null ||
                String(fixedLine.price).includes("undefined")
              ) {
                fixedLine.price = fixedLine.amount + " €";
              }
              if (
                (fixedLine.label || "").toLowerCase().trim() === "options" &&
                fixedLine.amount === 0
              ) {
                fixedLine.is_observation_only = true;
                fixedLine.label = "Options sélectionnées";
                fixedLine.price = "";
              }
              return fixedLine;
            });
          }

          // On met à jour l'objet directement pour déclencher la réactivité
          // SANS toucher à this.isDetailModalOpen ou this.isLoadingDetails
          this.reservationDetails = data;

          // Optionnel : on met aussi à jour l'objet résumé dans la liste (si présent)
          const index = this.items.findIndex(
            (item) => item.id == reservationId,
          );
          if (index !== -1) {
            // Fusionne les données essentielles pour que le tableau de bord soit à jour
            this.items[index] = { ...this.items[index], ...data };
          }

          console.log(
            "Détails rafraîchis silencieusement :",
            this.reservationDetails,
          );
        }
      } catch (err) {
        console.error("Erreur lors du rafraîchissement silencieux :", err);
      }
    },

    /**
     * Charge la configuration d'un logement (Capacité, iCal...)
     */
    async fetchHousingConfig(logementId) {
      if (!logementId) {
        this.currentLogementConfig = null;
        return;
      }
      try {
        const response = await reservationApi.getHousingConfig(logementId);
        if (response.data.success) {
          this.currentLogementConfig = response.data.data.config;
        }
      } catch (err) {
        console.error("Erreur chargement config logement :", err);
        this.currentLogementConfig = null;
      }
    },

    /**
     * Ferme la modale de détails
     */
    closeDetailModal() {
      this.isDetailModalOpen = false;
      // On peut garder ou purger selectedReservation selon le besoin
    },

    /**
     * Action d'annulation via l'API
     */
    async cancelReservation(id) {
      this.isLoading = true;
      this.error = null;

      try {
        const response = await reservationApi.cancel(id);

        if (response.data.success) {
          // On recharge la liste actuelle pour voir le nouveau statut
          await this.fetchList(this.currentPage);
          this.closeDetailModal(); // On ferme la modale
          // 🚀 On "crie" dans toute l'app qu'il faut rafraîchir le calendrier !
          window.dispatchEvent(new CustomEvent("pc-refresh-calendar"));
          return response.data.data;
        } else {
          throw new Error(
            response.data.data?.message || "Erreur lors de l'annulation",
          );
        }
      } catch (err) {
        this.error = err.message || "Erreur réseau";
        console.error("Reservations Store Error:", err);
        throw err;
      } finally {
        this.isLoading = false;
      }
    },

    /**
     * Action de confirmation via l'API
     */
    async confirmReservation(id) {
      this.isLoading = true;
      this.error = null;

      try {
        const response = await reservationApi.confirm(id);

        if (response.data.success) {
          await this.fetchList(this.currentPage);
          this.closeDetailModal();
          // 🚀 On "crie" dans toute l'app qu'il faut rafraîchir le grand calendrier !
          window.dispatchEvent(new CustomEvent("pc-refresh-calendar"));
          return response.data.data;
        } else {
          throw new Error(
            response.data.data?.message || "Erreur lors de la confirmation",
          );
        }
      } catch (err) {
        this.error = err.message || "Erreur réseau";
        console.error("Reservations Store Error:", err);
        throw err;
      } finally {
        this.isLoading = false;
      }
    },

    /**
     * 🚀 NOUVEAU : Ouvre la modale en mode Édition avec les données du dossier
     */
    async openEditModal() {
      if (!this.reservationDetails || !this.selectedReservation) return;

      const details = this.reservationDetails;

      // On prépare le paquet de données pour BookingForm
      // 🚀 Extraction super-robuste des plus/moins values sauvegardées
      let extractedRemiseLabel = details.raw_remise_label || "";
      let extractedRemiseMontant = parseFloat(details.raw_remise_montant) || 0;
      let extractedPlusLabel = details.raw_plus_label || "";
      let extractedPlusMontant = parseFloat(details.raw_plus_montant) || 0;

      let baseTotal = parseFloat(details.montant_total || 0);

      // 🚀 FIX : On reconstitue le sous-total de base en inversant les ajustements connus de la BDD (colonnes)
      baseTotal = baseTotal + extractedRemiseMontant - extractedPlusMontant;

      const cleanQuoteLines = [];

      (details.quote_lines || []).forEach((line) => {
        const rawAmount =
          line.amount !== undefined
            ? parseFloat(line.amount)
            : parseFloat(line.price || 0);
        const label = (line.label || "").toLowerCase();

        // Détection de la remise (par son type, un montant négatif, ou un mot clé)
        if (
          line.type === "remise" ||
          rawAmount < 0 ||
          label.includes("remise") ||
          label.includes("réduction")
        ) {
          // Si on n'a pas trouvé la remise en BDD, on l'extrait de la ligne
          if (extractedRemiseMontant === 0) {
            extractedRemiseLabel = line.label || "Remise exceptionnelle";
            extractedRemiseMontant = Math.abs(rawAmount);
            baseTotal += extractedRemiseMontant; // On l'ajoute au sous-total car la BDD ne l'avait pas
          }
        }
        // Détection de la plus-value
        else if (
          line.type === "plus_value" ||
          label.includes("plus-value") ||
          label.includes("plus value")
        ) {
          extractedPlusLabel = line.label || "Plus-value";
          extractedPlusMontant = Math.abs(rawAmount);
          baseTotal -= extractedPlusMontant; // On déduit la plus-value du total de base
        }
        // Ligne normale
        else {
          // 🚀 FIX : On répare les vieilles lignes cassées (prix undefined, etc.)
          let fixedLine = { ...line };
          if (fixedLine.amount === undefined)
            fixedLine.amount = parseFloat(fixedLine.price) || 0;
          if (
            fixedLine.price === undefined ||
            fixedLine.price === null ||
            String(fixedLine.price).includes("undefined")
          ) {
            fixedLine.price = fixedLine.amount + " €";
          }
          // Si c'est juste un titre "Options" à 0€, on le passe en observation pour nettoyer le tableau
          if (label === "options" && fixedLine.amount === 0) {
            fixedLine.is_observation_only = true;
            fixedLine.clean_label = "Options sélectionnées";
          }
          cleanQuoteLines.push(fixedLine);
        }
      });

      // Valeurs par défaut si rien n'est trouvé, ou on vide ("") pour nettoyer l'affichage UI
      extractedRemiseLabel = extractedRemiseLabel || "Remise exceptionnelle";
      extractedPlusLabel = extractedPlusLabel || "Plus-value";
      extractedRemiseMontant =
        extractedRemiseMontant > 0 ? extractedRemiseMontant : "";
      extractedPlusMontant =
        extractedPlusMontant > 0 ? extractedPlusMontant : "";

      const prefill = {
        id: details.id, // Présence de l'ID = Mode Mise à jour !
        type: details.raw_type,
        item_id: details.raw_item_id,
        experience_tarif_type: details.raw_tarif_type,
        date_arrivee: details.raw_date_arrivee,
        date_depart: details.raw_date_depart,
        date_experience: details.raw_date_experience,
        adultes: details.raw_adultes,
        enfants: details.raw_enfants,
        bebes: details.raw_bebes,
        prenom: details.raw_prenom,
        nom: details.raw_nom,
        email: details.client_email,
        telephone: details.client_phone,
        commentaire_client: details.client_message,
        notes_internes: details.notes_internes,
        numero_devis: details.raw_numero_devis,
        remise_label: extractedRemiseLabel,
        remise_montant: extractedRemiseMontant,
        plus_label: extractedPlusLabel,
        plus_montant: extractedPlusMontant,
        source: details.source || "direct",
        type_flux: ["devis", "demande", "en_attente_traitement"].includes(
          this.selectedReservation.statut_reservation,
        )
          ? "devis"
          : "reservation",
        // 🚀 On transmet le devis recalculé à l'état brut (sans les ajustements)
        structured_experience_data: details.structured_experience_data || null,
        // 🚀 On transmet le devis recalculé à l'état brut (sans les ajustements)
        montant_total: baseTotal,
        quote_lines: cleanQuoteLines,
      };

      await this.openCreateModal(prefill);
    },

    /**
     * Ouvre la modale de création et charge les listes
     * @param {Object} prefill - Données optionnelles (ex: { type: 'location', item_id: 12, date_arrivee: '2024-08-10' })
     */
    async openCreateModal(prefill = null) {
      // 🚀 FIX : On s'assure que l'API a répondu AVANT d'ouvrir et de pré-remplir la modale
      if (
        this.bookingItems.locations.length === 0 &&
        this.bookingItems.experiences.length === 0
      ) {
        await this.fetchBookingItems();
      }

      this.prefillData = prefill;
      this.isCreateModalOpen = true;
    },

    /**
     * Ferme la modale de création et nettoie le pré-remplissage
     */
    closeCreateModal() {
      this.isCreateModalOpen = false;
      this.prefillData = null;
      this.quotePreview = null;
    },
  },
});
