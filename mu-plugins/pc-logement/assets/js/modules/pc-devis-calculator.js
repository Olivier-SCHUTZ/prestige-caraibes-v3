/**
 * Module : PC Devis Calculator
 * Rôle : Moteur mathématique pur. Ne manipule JAMAIS le DOM.
 * Prend une configuration et des dates, retourne les totaux et lignes de devis.
 */
class PCDevisCalculator {
  // --- Utilitaires de Dates et Clés ---
  static ymd(d) {
    if (!d || !(d instanceof Date)) return null;
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const dd = String(d.getDate()).padStart(2, "0");
    return d.getFullYear() + "-" + m + "-" + dd;
  }

  static addDays(d, n) {
    const x = new Date(d.getTime());
    x.setDate(x.getDate() + n);
    return x;
  }

  static inRange(ymdStr, from, to) {
    return ymdStr >= from && ymdStr <= to;
  }

  static normKey(x) {
    if (x == null) return "";
    try {
      x = String(x);
    } catch (e) {
      return "";
    }
    x = x.normalize ? x.normalize("NFKD").replace(/[\u0300-\u036f]/g, "") : x;
    return x
      .toLowerCase()
      .replace(/[^a-z0-9%]+/g, "_")
      .replace(/^_+|_+$/g, "");
  }

  static normalizeDate(value) {
    if (!value) return null;
    if (value instanceof Date) return isNaN(value.getTime()) ? null : value;
    const parsed = new Date(value);
    return isNaN(parsed.getTime()) ? null : parsed;
  }

  // --- Règles Métier ---
  static nightPrice(cfg, day) {
    if (cfg && Array.isArray(cfg.seasons)) {
      for (let s of cfg.seasons) {
        if (!s || !Array.isArray(s.periods)) continue;
        for (let p of s.periods) {
          if (p && p.from && p.to && this.inRange(day, p.from, p.to)) {
            return Number(s.price) > 0
              ? Number(s.price)
              : Number(cfg.basePrice) || 0;
          }
        }
      }
    }
    return Number(cfg && cfg.basePrice) || 0;
  }

  static requiredMinNights(cfg, nights) {
    let req = Number(cfg && (cfg.minNights || cfg.min)) || 0;
    if (!cfg || !Array.isArray(cfg.seasons) || nights.length === 0)
      return req > 0 ? req : 1;

    for (let d of nights) {
      for (let S of cfg.seasons) {
        if (!S || !Array.isArray(S.periods)) continue;
        for (let P of S.periods) {
          if (P && P.from && P.to && this.inRange(d, P.from, P.to)) {
            if (Number(S.min_nights) > req) req = Number(S.min_nights);
          }
        }
      }
    }
    return req > 0 ? req : 1;
  }

  static extraParamsFor(cfg, day) {
    if (cfg && Array.isArray(cfg.seasons)) {
      for (let s of cfg.seasons) {
        if (!s || !Array.isArray(s.periods)) continue;
        for (let p of s.periods) {
          if (p && p.from && p.to && this.inRange(day, p.from, p.to)) {
            return {
              fee:
                (s.extra_fee !== null && s.extra_fee !== ""
                  ? Number(s.extra_fee)
                  : Number(cfg.extraFee)) || 0,
              from:
                (s.extra_from !== null && s.extra_from !== ""
                  ? Number(s.extra_from)
                  : Number(cfg.extraFrom)) || 0,
            };
          }
        }
      }
    }
    return {
      fee: Number(cfg && cfg.extraFee) || 0,
      from: Number(cfg && cfg.extraFrom) || 0,
    };
  }

  // --- Fonction Principale de Calcul ---
  static calculateQuote(cfg, args = {}) {
    const pendingLabel = "En attente de devis";
    const response = {
      success: false,
      lines: [],
      html: "",
      total: 0,
      isSurDevis: false,
      pendingLabel,
      selection: null,
      message: "",
      code: "",
    };

    if (!cfg) {
      response.code = "missing_config";
      response.message = "Sélectionnez un logement.";
      return response;
    }

    const manualQuote =
      typeof args.manualQuote !== "undefined"
        ? !!args.manualQuote
        : !!cfg.manualQuote;
    const start = this.normalizeDate(
      args.startDate || args.arrival || args.date_arrivee,
    );
    const end = this.normalizeDate(
      args.endDate || args.departure || args.date_depart,
    );

    if (!start || !end || end <= start) {
      response.code = "missing_dates";
      response.message = "Choisissez vos dates";
      return response;
    }

    const adults =
      isFinite(parseInt(args.adults, 10)) && parseInt(args.adults, 10) >= 0
        ? parseInt(args.adults, 10)
        : 0;
    const children =
      isFinite(parseInt(args.children, 10)) && parseInt(args.children, 10) >= 0
        ? parseInt(args.children, 10)
        : 0;
    const infants =
      isFinite(parseInt(args.infants, 10)) && parseInt(args.infants, 10) >= 0
        ? parseInt(args.infants, 10)
        : 0;
    const guestsForExtras = adults + children + infants;
    const guestsForCapacity = adults + children;
    const cap = Number(cfg.cap) || 0;

    if (cap > 0 && guestsForCapacity > cap) {
      response.code = "over_capacity";
      response.message = `Capacité max : ${cap} personnes (adultes + enfants).`;
      return response;
    }

    const nights = [];
    for (let d = new Date(start); d < end; d = this.addDays(d, 1)) {
      nights.push(this.ymd(d));
    }
    const nN = nights.length;

    if (nN <= 0) {
      response.code = "invalid_range";
      response.message = "Choisissez vos dates";
      return response;
    }

    const reqMin = this.requiredMinNights(cfg, nights);
    if (reqMin && nN < reqMin) {
      response.code = "min_nights";
      response.message = `Séjour minimum : ${reqMin} nuit${reqMin > 1 ? "s" : ""}.`;
      return response;
    }

    const maxN = Number(cfg.maxNights) || 0;
    if (maxN && nN > maxN) {
      response.code = "max_nights";
      response.message = `Séjour maximum : ${maxN} nuit${maxN > 1 ? "s" : ""}.`;
      return response;
    }

    response.selection = {
      arrival: this.ymd(start),
      departure: this.ymd(end),
      adults,
      children,
      infants,
    };

    if (manualQuote) {
      response.success = true;
      response.isSurDevis = true;
      response.lines = [
        { label: "En attente de devis personnalisé", price: "" },
      ];
      response.html =
        "<ul><li><span>En attente de devis personnalisé</span><span></span></li></ul>";
      return response;
    }

    let lodging = 0;
    for (let ni = 0; ni < nN; ni++) lodging += this.nightPrice(cfg, nights[ni]);

    let extras = 0;
    for (let k = 0; k < nN; k++) {
      const ep = this.extraParamsFor(cfg, nights[k]);
      if (ep.fee > 0 && ep.from > 0 && guestsForExtras >= ep.from) {
        extras += (guestsForExtras - (ep.from - 1)) * ep.fee;
      }
    }

    const cleaning = Number(cfg.cleaning) || 0;
    const other = Number(cfg.otherFee) || 0;
    let taxe = 0;

    // 🚀 CORRECTION : Scanner de taxe (Gère Vue.js "object Object" et le "non_classe")
    let taxRawArray = Array.isArray(cfg.taxe_sejour)
      ? cfg.taxe_sejour
      : [cfg.taxe_sejour];
    let isPct5 = false;
    let stars = null;

    for (let item of taxRawArray) {
      if (!item) continue;

      let val = item;
      if (typeof val === "object" && val !== null && val.value) val = val.value;

      // On ignore la scorie Vue.js si elle est présente
      if (typeof val === "string" && val.includes("object Object")) continue;

      const taxKey = this.normKey(val);

      // Test 5% OU Non Classé (qui déclenche la taxe à 5%)
      const is5Percent =
        taxKey &&
        (/\b5\b/.test(taxKey) || taxKey.includes("5")) &&
        (taxKey.includes("%") ||
          taxKey.includes("pourcent") ||
          taxKey.includes("pct"));
      const isNonClasse = taxKey && taxKey.includes("non_classe");

      if (is5Percent || isNonClasse) {
        isPct5 = true;
        break; // On a trouvé la règle des 5%, on arrête de chercher
      }

      // Test Étoiles (1_etoiles, 2_etoiles, etc.)
      const m = taxKey.match(/([1-5])_?etoile/);
      if (m) {
        stars = parseInt(m[1], 10);
        break; // On a trouvé le nombre d'étoiles, on arrête de chercher
      }
    }
    const classRates = { 1: 0.8, 2: 0.9, 3: 1.5, 4: 2.3, 5: 3.0 };

    if (isPct5 && nN > 0 && guestsForExtras > 0 && adults > 0) {
      taxe = 0.05 * (lodging / nN / guestsForExtras) * nN * adults;
    } else if (stars && classRates[stars] && adults > 0) {
      taxe = classRates[stars] * adults * nN;
    }

    const grand = lodging + extras + cleaning + other + taxe;
    const formatter = window.PCCurrencyFormatter
      ? window.PCCurrencyFormatter.format
      : (n) => n + " €";

    let html = "<ul>";
    const lines = [];
    const addLine = (label, priceValue) => {
      const display = formatter(priceValue);
      html += `<li><span>${label}</span><span>${display}</span></li>`;
      lines.push({ label, price: display });
    };

    const dateFR = (d) =>
      d.toLocaleDateString("fr-FR", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
      });

    addLine(
      `Hébergement du ${dateFR(start)} au ${dateFR(end)} (${nN} nuits)`,
      lodging,
    );
    if (extras > 0) addLine("Invités supplémentaires", extras);
    if (cleaning > 0) addLine("Frais de ménage", cleaning);
    if (other > 0) addLine(cfg.otherLabel || "Autres frais", other);
    if (taxe > 0) addLine("Taxe de séjour", taxe);
    html += "</ul>";

    response.success = true;
    response.lines = lines;
    response.html = html;
    response.total = grand;
    return response;
  }
}

window.PCDevisCalculator = PCDevisCalculator;
