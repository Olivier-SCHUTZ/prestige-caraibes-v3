/**
 * Module : PC PDF Generator
 * Rôle : Générer et télécharger le devis au format PDF en utilisant jsPDF.
 */
class PCPDFGenerator {
  /**
   * Normalisation des montants pour jsPDF (supprime les caractères non supportés)
   */
  static formatCurrency(input) {
    if (typeof input !== "string") {
      input = window.PCCurrencyFormatter
        ? window.PCCurrencyFormatter.format(input)
        : input + " €";
    }
    return String(input)
      .replace(/\//g, " ")
      .replace(/\u00A0|\u202F/g, " ")
      .replace(/\s{2,}/g, " ")
      .trim();
  }

  /**
   * Génère et télécharge le fichier PDF
   */
  static generate() {
    if (!window.jspdf || !window.jspdf.jsPDF) {
      alert("La librairie PDF n'est pas chargée.");
      return;
    }

    // --- Récupération des données ---
    const lines = Array.isArray(window.currentLogementLines)
      ? window.currentLogementLines
      : [];
    const total =
      typeof window.currentLogementTotal === "number"
        ? window.currentLogementTotal
        : 0;

    if (!lines || !lines.length || total <= 0) {
      alert("Veuillez d'abord effectuer une simulation valide.");
      return;
    }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ unit: "mm", format: "a4" });

    // Lecture des infos entreprise
    const readCompanyInfo = () => {
      try {
        const el = document.querySelector(".exp-devis-company-info");
        return el ? JSON.parse(el.textContent) : {};
      } catch (e) {
        return {};
      }
    };
    const companyInfo = readCompanyInfo();

    const logementTitle = (
      document.querySelector(".exp-devis-experience-title")?.textContent ||
      document.title ||
      "Logement"
    ).trim();
    const date = new Date().toLocaleDateString("fr-FR");

    const parseIntSafe = (v) => {
      const n = parseInt(String(v ?? "").replace(/\D+/g, ""), 10);
      return isNaN(n) ? 0 : n;
    };
    const a = parseIntSafe(
      document.querySelector('[name="devis_adults"]')?.value,
    );
    const c = parseIntSafe(
      document.querySelector('[name="devis_children"]')?.value,
    );

    // --- Pied légal ---
    const footerLines = [
      "SAS PRESTIGE CARAÏBES - 166C LOT. LES HAUTS DE JABRUN - 97111 MORNE A L'EAU, Guadeloupe",
      "N°TVA FR74948081351 - SIREN 948 081 351 00017 - RCS 948 081 351 R.C.S. Pointe-a-pitre",
      "Capital de 1 000,00 € - APE 96.09Z",
    ];
    const drawFooter = (docInstance) => {
      const pageH = docInstance.internal.pageSize.getHeight();
      docInstance.setFontSize(8);
      const yStart = pageH - 14;
      footerLines.forEach((txt, i) => {
        docInstance.text(txt, 105, yStart + i * 4.5, { align: "center" });
      });
    };

    // --- Header ---
    const drawHeaderBox = () => {
      const frameX = 12,
        frameY = 12,
        frameW = 90,
        radius = 3;
      doc.setFontSize(10);
      const rows = [];
      if (companyInfo.address) rows.push(String(companyInfo.address));
      if (companyInfo.city) rows.push(String(companyInfo.city));
      if (companyInfo.phone) rows.push(String(companyInfo.phone));
      if (companyInfo.email) rows.push(String(companyInfo.email));
      let wrapped = doc.splitTextToSize(rows.join("\n").trim(), 90);
      const textLineH = 4.5;
      const textBlockH = wrapped.length * textLineH;

      const hasLogo = !!companyInfo.logo_data;
      const logoW = 40,
        logoH = hasLogo ? 12 : 0;
      const pad = 5,
        gapLogoText = hasLogo ? 4 : 0;
      const frameH = pad + logoH + gapLogoText + textBlockH + pad;

      doc.setDrawColor(180, 180, 180);
      doc.roundedRect(frameX, frameY, frameW, frameH, radius, radius);
      doc.setDrawColor(210, 210, 210);
      doc.line(
        frameX + 1.5,
        frameY + frameH,
        frameX + frameW - 1.5,
        frameY + frameH,
      );

      if (hasLogo) {
        doc.addImage(
          companyInfo.logo_data,
          "PNG",
          frameX + 3,
          frameY + 2,
          logoW,
          logoH,
          undefined,
          "NONE",
        );
      }

      let textY = frameY + (hasLogo ? 2 + logoH + gapLogoText : 6);
      doc.setFontSize(10);
      wrapped.forEach((line, idx) => {
        doc.text(line, frameX + 4, textY + idx * textLineH);
      });

      return frameY + frameH;
    };

    const headerBottomY = drawHeaderBox();

    // --- Séparateur + titres ---
    const sepY = headerBottomY + 6;
    doc.setDrawColor(0, 0, 0);
    doc.line(15, sepY, 195, sepY);

    let yCursor = sepY + 8;
    doc.setFontSize(12);
    doc.text(`Estimation pour : ${logementTitle}`, 15, yCursor);
    doc.text(`Date : ${date}`, 195, yCursor, { align: "right" });

    const peopleY = yCursor + 6;
    doc.setFontSize(10);
    doc.text(`Pour ${a} adulte(s) et ${c} enfant(s)`, 15, peopleY);

    // --- Tableau devis ---
    let y = peopleY + 12;
    const pageH = doc.internal.pageSize.getHeight();
    const bottomLimit = pageH - 22;

    doc.setFontSize(11);
    doc.text("Description", 15, y);
    doc.text("Montant", 195, y, { align: "right" });

    doc.setLineWidth(0.2);
    doc.line(15, y + 2, 195, y + 2);
    y += 6;

    doc.setFontSize(10);
    lines.forEach((line) => {
      const description = (line?.label || line?.name || line?.title || "")
        .toString()
        .trim();
      const price = this.formatCurrency(line?.price ?? line?.amount ?? 0);
      const descWrapped = doc.splitTextToSize(description || "—", 150);

      descWrapped.forEach((dLine, i) => {
        if (y > bottomLimit) {
          drawFooter(doc);
          doc.addPage();
          y = 20;
        }
        if (i === 0) {
          doc.text(dLine, 15, y);
          doc.text(price, 195, y, { align: "right" });
        } else {
          doc.text(dLine, 15, y);
        }
        y += 6;
      });
    });

    if (y + 4 > bottomLimit) {
      drawFooter(doc);
      doc.addPage();
      y = 20;
    }
    doc.line(15, y + 2, 195, y + 2);
    y += 8;

    // Total
    if (y + 10 > bottomLimit) {
      drawFooter(doc);
      doc.addPage();
      y = 20;
    }
    doc.setFont(undefined, "bold");
    doc.setFontSize(14);
    doc.text("Total Estimé (TTC)", 15, y);
    doc.text(this.formatCurrency(total), 195, y, { align: "right" });

    doc.setFont(undefined, "normal");
    doc.setFontSize(10);
    drawFooter(doc);

    // --- CGV ---
    const termsRaw =
      (companyInfo &&
        (companyInfo.cgv_location ||
          companyInfo.cgv_experience ||
          companyInfo.cgv ||
          companyInfo.terms ||
          companyInfo.terms_text ||
          companyInfo.conditions_generales)) ||
      "";
    const cgv = String(termsRaw).trim();

    if (cgv) {
      doc.addPage();
      doc.setFontSize(12);
      doc.text("Conditions Générales de Location", 15, 20);
      let yy = 28;
      doc.setFontSize(10);

      cgv.split(/\n{2,}/).forEach((p) => {
        doc.splitTextToSize(p, 180).forEach((ln) => {
          if (yy > pageH - 22) {
            drawFooter(doc);
            doc.addPage();
            yy = 20;
          }
          doc.text(ln, 15, yy);
          yy += 5;
        });
        yy += 2;
      });
      drawFooter(doc);
    }

    const file = `estimation-${(logementTitle || "logement").replace(/[^a-z0-9\u00C0-\u024F]+/gi, "_").toLowerCase()}.pdf`;
    doc.save(file);
  }
}

window.PCPDFGenerator = PCPDFGenerator;
