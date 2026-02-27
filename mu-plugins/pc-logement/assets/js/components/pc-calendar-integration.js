/**
 * Composant : PC Calendar Integration
 * Rôle : Gérer l'interface utilisateur du simulateur de devis
 * (Flatpickr, compteurs de voyageurs, règles de capacité).
 */
class PCCalendarIntegration {
  constructor() {
    // Lancement automatique via orchestrateur ou standalone
    this.boot = this.boot.bind(this);
    this.waitForFlatpickr = this.waitForFlatpickr.bind(this);
  }

  boot() {
    const sections = document.querySelectorAll(
      ".pc-devis-section[data-pc-devis]",
    );
    if (!sections.length) return;
    sections.forEach(this.initOne.bind(this));
  }

  waitForFlatpickr() {
    if (
      window.flatpickr &&
      (typeof window.flatpickr === "function" ||
        typeof window.Flatpickr === "function")
    ) {
      this.boot();
    } else {
      setTimeout(this.waitForFlatpickr, 50);
    }
  }

  initOne(section) {
    if (!section || section.__pcqInit) return;

    const id = section.id;
    const cfg = JSON.parse(section.getAttribute("data-pc-devis") || "{}");
    const isManual = !!cfg.manualQuote;

    const input = document.getElementById(id + "-dates");
    const adults = document.getElementById(id + "-adults");
    const children = document.getElementById(id + "-children");
    const infants = document.getElementById(id + "-infants");
    const msgBox = document.getElementById(id + "-msg");
    const out = document.getElementById(id + "-result");
    const lines = document.getElementById(id + "-lines");
    const total = document.getElementById(id + "-total");
    const pdfBtn = document.getElementById(id + "-pdf-btn");

    if (isManual && pdfBtn) pdfBtn.style.display = "none";
    if (!input) {
      console.warn("[PC Calendar] Élément #" + id + "-dates introuvable");
      return;
    }

    const CAP = Number(cfg.cap || 0);

    // Helpers internes branchés sur nos nouveaux modules
    const ymd = (d) =>
      window.PCDevisCalculator ? window.PCDevisCalculator.ymd(d) : "";
    const eur = (n) =>
      window.PCCurrencyFormatter
        ? window.PCCurrencyFormatter.format(n)
        : n + " €";

    // Règles de dates désactivées
    const ranges = Array.isArray(cfg.icsDisable)
      ? cfg.icsDisable.filter((r) => r && r.from && r.to)
      : [];
    const disableRules = ranges.length
      ? [
          (date) => {
            const s = ymd(date);
            return ranges.some((r) => s >= r.from && s <= r.to);
          },
        ]
      : [];

    // 1. ON DÉFINIT LES FONCTIONS DE CALCUL EN PREMIER
    const parseIntSafe = (el) => {
      const v = parseInt(el && el.value, 10);
      return isFinite(v) && v > 0 ? v : 0;
    };

    const clampCapacity = (sourceField) => {
      if (!CAP || CAP <= 0) return;
      const a = parseIntSafe(adults);
      const c = parseIntSafe(children);
      let totalGuests = a + c;

      if (totalGuests > CAP) {
        if (msgBox)
          msgBox.textContent =
            "Capacité max : " + CAP + " personnes (adultes + enfants).";
        let currentVal = 0;
        let inputElement = null;

        if (sourceField === "adults") {
          inputElement = adults;
          currentVal = a;
        } else if (sourceField === "children") {
          inputElement = children;
          currentVal = c;
        }

        if (inputElement && currentVal > 0) {
          const reductionNeeded = totalGuests - CAP;
          let newValue = currentVal - reductionNeeded;
          newValue = Math.max(parseInt(inputElement.min) || 0, newValue);
          inputElement.value = newValue;
          if (msgBox)
            msgBox.textContent =
              "Capacité max atteinte : " + CAP + " personnes.";
        }
      } else {
        if (msgBox && msgBox.textContent.startsWith("Capacité max"))
          msgBox.textContent = "";
      }
    };

    const compute = () => {
      try {
        const fpi = input._flatpickr;
        if (!fpi) return;

        const a = parseIntSafe(adults);
        const c = parseIntSafe(children);
        const i = parseIntSafe(infants);

        if (fpi.selectedDates.length < 2) {
          if (msgBox) msgBox.textContent = "Choisissez vos dates";
          if (out) out.hidden = !isManual ? true : false;
          if (lines)
            lines.innerHTML = isManual
              ? '<li class="pcq-line"><span>En attente de devis personnalisé</span><span></span></li>'
              : "";
          if (total) total.hidden = true;

          window.currentLogementTotal = 0;
          window.currentLogementLines = [];
          window.currentLogementSelection = null;
          section.dispatchEvent(
            new CustomEvent("devisLogementUpdated", {
              bubbles: true,
              detail: { manual: isManual },
            }),
          );
          return;
        }

        const start = fpi.selectedDates[0];
        const end = fpi.selectedDates[1];
        if (msgBox) msgBox.textContent = "";

        // --- APPEL AU MOTEUR DE CALCUL ISOLÉ ---
        const calc = window.PCDevisCalculator.calculateQuote(cfg, {
          startDate: start,
          endDate: end,
          adults: a,
          children: c,
          infants: i,
          manualQuote: isManual,
        });

        if (!calc.success) {
          if (out) out.hidden = true;
          if (msgBox)
            msgBox.textContent = calc.message || "Choisissez vos dates";
          window.currentLogementTotal = 0;
          window.currentLogementLines = [];
          window.currentLogementSelection = null;
          section.dispatchEvent(
            new CustomEvent("devisLogementUpdated", { bubbles: true }),
          );
          return;
        }

        if (lines) {
          lines.innerHTML = "";
          calc.lines.forEach((line) => {
            const li = document.createElement("li");
            li.classList.add("pcq-line");
            const s1 = document.createElement("span");
            s1.textContent = line.label;
            const s2 = document.createElement("span");
            s2.textContent = line.price;
            li.appendChild(s1);
            li.appendChild(s2);
            lines.appendChild(li);
          });
        }
        if (total) {
          if (calc.isSurDevis) {
            total.hidden = true;
          } else {
            total.textContent = eur(calc.total);
            total.hidden = false;
          }
        }
        if (out) out.hidden = false;

        // Mise à jour de l'état global
        window.currentLogementTotal = calc.isSurDevis ? 0 : calc.total;
        window.currentLogementLines = calc.lines;
        window.currentLogementSelection = calc.selection;

        section.dispatchEvent(
          new CustomEvent("devisLogementUpdated", {
            bubbles: true,
            detail: { manual: calc.isSurDevis },
          }),
        );
      } catch (e) {
        console.error("[PC Calendar] Erreur de calcul:", e);
        if (msgBox) msgBox.textContent = "Erreur lors du calcul.";
        if (out) out.hidden = true;
        window.currentLogementTotal = 0;
        window.currentLogementLines = [];
        window.currentLogementSelection = null;
        section.dispatchEvent(
          new CustomEvent("devisLogementUpdated", { bubbles: true }),
        );
      }
    };

    // 2. ENSUITE, ON INITIALISE FLATPICKR (qui peut maintenant utiliser 'compute')
    const FP =
      window.flatpickr && typeof window.flatpickr === "function"
        ? window.flatpickr
        : window.Flatpickr && typeof window.Flatpickr === "function"
          ? window.Flatpickr
          : null;

    if (!FP) return;

    section.__pcqInit = true; // Marqueur d'initialisation

    const fp = FP(input, {
      mode: "range",
      dateFormat: "d/m/Y",
      altInput: true,
      altFormat: "j M Y",
      minDate: "today",
      locale:
        window.flatpickr && window.flatpickr.l10ns && window.flatpickr.l10ns.fr
          ? "fr"
          : undefined,
      disable: disableRules,
      appendTo: document.body,
      onReady: (sel, str, inst) => {
        try {
          inst.calendarContainer.classList.add("pcq-cal");
        } catch (e) {}
        compute(); // Premier calcul
      },
      onChange: compute,
      conjunction: " au ",
    });

    // 3. ON AJOUTE LES ÉCOUTEURS D'ÉVÉNEMENTS
    section.querySelectorAll(".exp-stepper-btn").forEach((btn) => {
      btn.addEventListener("click", function () {
        const stepInput = this.parentElement.querySelector(".pcq-input");
        if (!stepInput) return;

        const stepDirection = this.dataset.step;
        const currentVal = parseInt(stepInput.value, 10) || 0;
        const min = parseInt(stepInput.min, 10);
        let newVal = stepDirection === "plus" ? currentVal + 1 : currentVal - 1;

        if (!isNaN(min) && newVal < min) newVal = min;
        stepInput.value = newVal;
        stepInput.dispatchEvent(new Event("input", { bubbles: true }));
      });
    });

    if (adults)
      adults.addEventListener("input", () => {
        clampCapacity("adults");
        compute();
      });
    if (children)
      children.addEventListener("input", () => {
        clampCapacity("children");
        compute();
      });
    if (infants)
      infants.addEventListener("input", () => {
        clampCapacity("infants");
        compute();
      });

    if (pdfBtn)
      pdfBtn.addEventListener("click", () => {
        if (window.PCPDFGenerator) window.PCPDFGenerator.generate();
      });
  }
}

// Initialisation globale
window.pcCalendarIntegration = new PCCalendarIntegration();

// Pont de rétrocompatibilité pour l'Orchestrateur
window.PCLogementDevis = window.PCLogementDevis || {};
window.PCLogementDevis.waitForFlatpickr =
  window.pcCalendarIntegration.waitForFlatpickr;

if (
  window.PCOrchestrator &&
  typeof window.PCOrchestrator.registerDevisInit === "function"
) {
  window.PCOrchestrator.registerDevisInit(window.pcCalendarIntegration.boot);
} else {
  window.pcCalendarIntegration.waitForFlatpickr();
}
