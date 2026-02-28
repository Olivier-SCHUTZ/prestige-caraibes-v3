/**
 * Module : Générateur PDF (Design Logement adapté pour Expériences)
 * Fichier : assets/js/modules/pc-pdf-generator.js
 */
(function (window) {
  "use strict";

  window.PCPdfGenerator = {
    generate: function (data) {
      if (!window.jspdf || !window.jspdf.jsPDF) {
        return alert("La librairie PDF n'est pas chargée.");
      }
      if (!data.hasValidSimulation) {
        return alert("Veuillez d'abord effectuer une simulation valide.");
      }

      try {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ unit: "mm", format: "a4" });
        const date = new Date().toLocaleDateString("fr-FR");
        const pageH = doc.internal.pageSize.getHeight();
        const bottomLimit = pageH - 22;

        const a = parseInt(data.adultsCount) || 0;
        const c = parseInt(data.childrenCount) || 0;

        // --- 1. Pied de page légal (Footer) ---
        const footerLines = [
          "SAS PRESTIGE CARAÏBES - 166C LOT. LES HAUTS DE JABRUN - 97111 MORNE A L'EAU, Guadeloupe",
          "N°TVA FR74948081351 - SIREN 948 081 351 00017 - RCS 948 081 351 R.C.S. Pointe-a-pitre",
          "Capital de 1 000,00 € - APE 96.09Z",
        ];

        const drawFooter = (docInstance) => {
          docInstance.setFont("helvetica", "normal");
          docInstance.setFontSize(8);
          const yStart = pageH - 14;
          footerLines.forEach((txt, i) => {
            docInstance.text(txt, 105, yStart + i * 4.5, { align: "center" });
          });
        };

        // --- 2. Encart d'en-tête (Header) ---
        const drawHeaderBox = () => {
          const frameX = 12,
            frameY = 12,
            frameW = 90,
            radius = 3;
          doc.setFontSize(10);

          const rows = [];
          if (data.companyInfo.address)
            rows.push(String(data.companyInfo.address));
          if (data.companyInfo.city) rows.push(String(data.companyInfo.city));
          if (data.companyInfo.phone) rows.push(String(data.companyInfo.phone));
          if (data.companyInfo.email) rows.push(String(data.companyInfo.email));

          let wrapped = doc.splitTextToSize(rows.join("\n").trim(), 90);
          const textLineH = 4.5;
          const textBlockH = wrapped.length * textLineH;

          const hasLogo = !!data.companyInfo.logo_data;
          let logoW = 40,
            logoH = 0;

          if (hasLogo) {
            try {
              const props = doc.getImageProperties(data.companyInfo.logo_data);
              if (props && props.width && props.height) {
                logoH = (logoW * props.height) / props.width;
              } else {
                logoH = 12;
              }
            } catch (e) {
              logoH = 12;
            }
          }

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
              data.companyInfo.logo_data,
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

        // --- 3. Titre et Informations (CORRIGÉ POUR ÉVITER LE CHEVAUCHEMENT) ---
        const sepY = headerBottomY + 6;
        doc.setDrawColor(0, 0, 0);
        doc.setLineWidth(0.2);
        doc.line(15, sepY, 195, sepY);

        let yCursor = sepY + 8;
        doc.setFont("helvetica", "normal");
        doc.setFontSize(12);

        // On place la date à droite
        doc.text(`Date : ${date}`, 195, yCursor, { align: "right" });

        // On coupe le titre s'il dépasse 135mm (pour laisser la place à la date)
        const titleText = `Estimation pour : ${data.experienceTitle}`;
        const wrappedTitle = doc.splitTextToSize(titleText, 135);

        // On dessine le titre qui passera à la ligne tout seul
        doc.text(wrappedTitle, 15, yCursor);

        // On calcule la nouvelle position Y en fonction du nombre de lignes prises par le titre
        const peopleY = yCursor + wrappedTitle.length * 6 + 2;

        doc.setFontSize(10);
        let subText = `Pour ${a} adulte(s)`;
        if (c > 0) subText += ` et ${c} enfant(s)`;
        doc.text(subText, 15, peopleY);

        // --- 4. En-tête du Tableau ---
        let y = peopleY + 10;
        doc.setFontSize(11);
        doc.setFont("helvetica", "bold");
        doc.text("Description", 15, y);
        doc.text("Montant", 195, y, { align: "right" });

        doc.line(15, y + 2, 195, y + 2);
        y += 6;

        // --- 5. Lignes du devis ---
        doc.setFontSize(10);
        data.lines.forEach((line) => {
          if (line.isError) return;

          if (line.isSeparator) {
            if (y > bottomLimit) {
              drawFooter(doc);
              doc.addPage();
              y = 20;
            }
            y += 2;
            doc.setFont("helvetica", "bold");
            doc.text(line.label, 15, y);
            y += 6;
          } else {
            doc.setFont("helvetica", "normal");
            const price =
              data.isSurDevis && line.price === data.pendingLabel
                ? data.pendingLabel
                : window.formatCurrencyPDF(line.price);

            const descWrapped = doc.splitTextToSize(line.label || "—", 150);

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
          }
        });

        // --- 6. Total ---
        if (y + 4 > bottomLimit) {
          drawFooter(doc);
          doc.addPage();
          y = 20;
        }
        doc.line(15, y + 2, 195, y + 2);
        y += 8;

        if (y + 10 > bottomLimit) {
          drawFooter(doc);
          doc.addPage();
          y = 20;
        }
        doc.setFont("helvetica", "bold");
        doc.setFontSize(14);
        doc.text("Total Estimé (TTC)", 15, y);

        const finalTotal = data.isSurDevis
          ? data.pendingLabel
          : window.formatCurrencyPDF(data.total);
        doc.text(finalTotal, 195, y, { align: "right" });

        drawFooter(doc);

        // --- 7. Conditions Générales d'Expérience ---
        const termsRaw =
          data.companyInfo.conditions_generales ||
          data.companyInfo.cgv_experience ||
          data.companyInfo.cgv ||
          "";
        const cgv = String(termsRaw).trim();

        if (cgv) {
          doc.addPage();
          doc.setFontSize(12);
          doc.setFont("helvetica", "bold");
          doc.text("Conditions Générales de l'Expérience", 15, 20);

          let yy = 28;
          doc.setFontSize(10);
          doc.setFont("helvetica", "normal");

          cgv.split(/\n{2,}/).forEach((p) => {
            doc.splitTextToSize(p, 180).forEach((ln) => {
              if (yy > bottomLimit) {
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

        // --- 8. Sauvegarde (J'ai retiré le "-NOUVEAU" du nom de fichier) ---
        const safeTitle = (data.experienceTitle || "experience")
          .replace(/[^a-z0-9\u00C0-\u024F]+/gi, "_")
          .toLowerCase();
        doc.save(`estimation-${safeTitle}.pdf`);
      } catch (e) {
        alert("Erreur lors de la génération du PDF : " + e.message);
        console.error(e);
      }
    },
  };
})(window);
