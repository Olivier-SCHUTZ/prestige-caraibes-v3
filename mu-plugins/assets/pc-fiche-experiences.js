// /assets/pc-fiche-experiences.js (Version Finale v4.0 - Refonte Bottom-Sheet)

document.addEventListener('DOMContentLoaded', function() {

    /**
     * ===================================================================
     * FONCTION UTILITAIRE GLOBALE
     * ===================================================================
     */
    function formatCurrency(num) {
        num = Number(num) || 0;
        return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(num);
    }

    /**
     * ===================================================================
     * PARTIE 1 : Le calculateur de devis [experience_devis]
     * ===================================================================
     * NOTE : Cette partie est quasi-inchangée. Elle fonctionne
     * parfaitement à l'intérieur de la nouvelle bottom-sheet.
     */
    function initExperienceDevis(devisWrap) {
        const config = JSON.parse(devisWrap.dataset.expDevis || '{}');
        const typeSelect = devisWrap.querySelector('select[name="devis_type"]');
        const adultsInput = devisWrap.querySelector('input[name="devis_adults"]');
        const childrenInput = devisWrap.querySelector('input[name="devis_children"]');
        const bebesInput = devisWrap.querySelector('input[name="devis_bebes"]');
        const optionsDiv = devisWrap.querySelector('.exp-devis-options');
        const resultDiv = devisWrap.querySelector('.exp-devis-result');
        const pdfBtn = devisWrap.querySelector('[id$="-pdf-btn"]');
        const companyInfoEl = devisWrap.querySelector('.exp-devis-company-info');
        const experienceTitleEl = devisWrap.querySelector('.exp-devis-experience-title');
        
        const companyInfo = companyInfoEl ? JSON.parse(companyInfoEl.textContent || '{}') : {};
        const experienceTitle = experienceTitleEl ? experienceTitleEl.textContent || '' : '';
        
        // Variables globales pour le partage entre modules
        window.currentTotal = 0;
        window.currentLines = [];
        window.isSurDevis = false;
        window.hasValidSimulation = false;
        const pendingLabel = (devisWrap.dataset.labelPending || 'En attente de devis');

        function calculate() {
            const selectedType = typeSelect.value;
            const typeConfig = config[selectedType];
            if (!typeConfig) return;

            const adults = parseInt(adultsInput.value, 10) || 0;
            const children = parseInt(childrenInput.value, 10) || 0;
            const bebes = parseInt(bebesInput.value, 10) || 0;

            let total = 0;
            let lines = [];
            let hasError = false;
            let resultHTML = '<h4 class="exp-result-title">Résumé du devis</h4><ul>';

            const isSurDevis = (typeSelect.value === 'sur-devis');
            window.isSurDevis = isSurDevis;

            // Adultes
            if (adults > 0) {
              if (isSurDevis) {
                lines.push({ label: `${adults} Adulte(s)`, price: pendingLabel });
                resultHTML += `<li><span>${adults} Adulte(s)</span><span class="exp-price--pending">${pendingLabel}</span></li>`;
              } else {
                const sub = adults * typeConfig.adulte;
                total += sub;
                lines.push({ label: `${adults} Adulte(s)`, price: formatCurrency(sub) });
                resultHTML += `<li><span>${adults} Adulte(s)</span><span>${formatCurrency(sub)}</span></li>`;
              }
            }

            // Enfants
            if (children > 0) {
              if (isSurDevis) {
                lines.push({ label: `${children} Enfant(s)`, price: pendingLabel });
                resultHTML += `<li><span>${children} Enfant(s)</span><span class="exp-price--pending">${pendingLabel}</span></li>`;
              } else {
                const sub = children * typeConfig.enfant;
                total += sub;
                lines.push({ label: `${children} Enfant(s)`, price: formatCurrency(sub) });
                resultHTML += `<li><span>${children} Enfant(s)</span><span>${formatCurrency(sub)}</span></li>`;
              }
            }

            // Bébés
            if (bebes > 0) {
              if (typeConfig.bebe === 'not_allowed') {
                hasError = true;
                lines.push({ label: `${bebes} Bébé(s)`, price: 'Non autorisé', isError: true });
                resultHTML += `<li class="error"><span>${bebes} Bébé(s)</span><span>Non autorisé</span></li>`;
              } else if (isSurDevis) {
                lines.push({ label: `${bebes} Bébé(s)`, price: pendingLabel });
                resultHTML += `<li><span>${bebes} Bébé(s)</span><span class="exp-price--pending">${pendingLabel}</span></li>`;
              } else {
                const sub = bebes * typeConfig.bebe;
                total += sub;
                const priceText = typeConfig.bebe === 0 ? 'Gratuit' : formatCurrency(sub);
                lines.push({ label: `${bebes} Bébé(s)`, price: priceText });
                resultHTML += `<li><span>${bebes} Bébé(s)</span><span>${priceText}</span></li>`;
              }
            }

            // Options cochées
            const checkedOptions = optionsDiv.querySelectorAll('input:checked');
            if (checkedOptions.length) {
              lines.push({ label: 'Options', price: '', isSeparator: true });
              resultHTML += `<li class="separator"><strong>Options</strong></li>`;
              checkedOptions.forEach(opt => {
                if (isSurDevis) {
                  lines.push({ label: opt.dataset.label, price: pendingLabel });
                  resultHTML += `<li><span>${opt.dataset.label}</span><span class="exp-price--pending">${pendingLabel}</span></li>`;
                } else {
                  const price = parseFloat(opt.dataset.price);
                  total += price;
                  lines.push({ label: opt.dataset.label, price: formatCurrency(price) });
                  resultHTML += `<li><span>${opt.dataset.label}</span><span>${formatCurrency(price)}</span></li>`;
                }
              });
            }

            // Total
            if (isSurDevis) {
              resultHTML += `</ul><div class="exp-result-total"><span>Total</span><span class="exp-price--pending">${pendingLabel}</span></div>`;
            } else {
              resultHTML += `</ul><div class="exp-result-total"><span>Total</span><span>${formatCurrency(total)}</span></div>`;
            }

            if (resultDiv) resultDiv.innerHTML = resultHTML;

            // Expose pour le FAB et la modale de contact
            window.currentTotal = isSurDevis ? 0 : total;
            window.currentLines = lines;
            
            const qty = (adults + children + bebes);
            window.hasValidSimulation = isSurDevis ? (qty > 0 && !hasError) : (total > 0 && !hasError);

            // Trigger pour mettre à jour le FAB et la modale de contact
            devisWrap.dispatchEvent(new CustomEvent('devisUpdated'));
        }
        
        function updateOptions() {
            const selectedType = typeSelect.value;
            const typeConfig = config[selectedType];
            if (optionsDiv) {
                optionsDiv.innerHTML = '';
                if (typeConfig && typeConfig.options && typeConfig.options.length > 0) {
                    let optionsHTML = '<h4 class="exp-options-title">Options disponibles</h4>';
                    typeConfig.options.forEach(function(opt, index) {
                        const id = `opt-${selectedType}-${index}`;
                        optionsHTML += `<div class="exp-devis-checkbox"><input type="checkbox" id="${id}" data-price="${opt.price}" data-label="${opt.label}"><label for="${id}">${opt.label} (+${formatCurrency(opt.price)})</label></div>`;
                    });
                    optionsDiv.innerHTML = optionsHTML;
                    optionsDiv.querySelectorAll('input').forEach(input => input.addEventListener('change', calculate));
                }
            }
            calculate(); // Recalculer après la mise à jour des options (au cas où le type change)
        }

        function generatePDF() {
            if (!window.jspdf) { return alert("La librairie PDF n'est pas chargée."); }
            if (!window.hasValidSimulation) { return alert("Veuillez d'abord effectuer une simulation valide."); }
            
            try {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();
                const date = new Date().toLocaleDateString('fr-FR');
                const a = parseInt(adultsInput.value, 10) || 0;
                const c = parseInt(childrenInput.value, 10) || 0;

                doc.setFontSize(20);
                doc.text(companyInfo.name || 'Estimation', 105, 20, { align: 'center' });
                doc.setFontSize(10);
                doc.text(`${companyInfo.address || ''}\n${companyInfo.city || ''}`, 105, 30, { align: 'center' });
                doc.line(15, 40, 195, 40);
                doc.setFontSize(12);
                doc.text(`Estimation pour : ${experienceTitle}`, 15, 50);
                doc.text(`Date : ${date}`, 195, 50, { align: 'right' });
                doc.setFontSize(10);
                doc.text(`Pour ${a} adulte(s) et ${c} enfant(s)`, 15, 58);
                let y = 70;
                doc.setFont('helvetica', 'bold');
                doc.text('Description', 15, y);
                doc.text('Montant', 195, y, { align: 'right' });
                doc.line(15, y + 2, 195, y + 2);
                y += 8;
                
                window.currentLines.forEach(line => {
                    if (line.isError) return;
                    if (line.isSeparator) {
                        y += 3;
                        doc.setFont('helvetica', 'bold');
                        doc.text(line.label, 15, y);
                        y += 7;
                    } else {
                        doc.setFont('helvetica', 'normal');
                        doc.text(line.label, 15, y);
                        if (line.price) {
                            doc.text(line.price, 195, y, { align: 'right' });
                        }
                        y += 7;
                    }
                });

                y += 5;
                doc.line(15, y, 195, y);
                y += 8;
                doc.setFontSize(14);
                doc.setFont('helvetica', 'bold');
                doc.text('Total Estimé (TTC)', 15, y);
                
                // Gère le cas "Sur devis" dans le PDF
                const totalText = window.isSurDevis ? pendingLabel : formatCurrency(window.currentTotal);
                doc.text(totalText, 195, y, { align: 'right' });
                
                y = 270;
                doc.line(15, y, 195, y);
                y += 8;
                doc.setFontSize(8);
                const footerText = `${companyInfo.legal || companyInfo.name} - ${companyInfo.phone || ''} - ${companyInfo.email || ''}`;
                doc.text(footerText, 105, y, { align: 'center' });
                doc.save(`estimation-${experienceTitle.replace(/[^a-z0-9]/gi, '_').toLowerCase()}.pdf`);
            } catch (e) {
                console.error("Erreur détaillée lors de la création du PDF:", e);
                alert("Une erreur est survenue lors de la génération du PDF.");
            }
        }

        // Écouteurs d'événements pour le calcul
        [typeSelect, adultsInput, childrenInput, bebesInput].forEach(el => el.addEventListener('input', calculate));
        typeSelect.addEventListener('change', updateOptions); // 'change' est mieux pour <select>
        if (pdfBtn) pdfBtn.addEventListener('click', generatePDF);

        // Initialisation
        updateOptions();
    }
    
    // Active le calculateur
    document.querySelectorAll('.exp-devis-wrap[data-exp-devis]').forEach(initExperienceDevis);


    /**
     * ===================================================================
     * PARTIE 2 : La Bottom-Sheet, le FAB et la Modale de Contact (Refonte v4.0)
     * ===================================================================
     */
    
    // --- 1. Sélection des éléments ---
    
    // Calculateur (déjà initialisé, on a juste besoin de la référence)
    const devisWrap = document.querySelector('.exp-devis-wrap[data-exp-devis]');
    
    // Bouton Flottant (FAB)
    const fab = document.getElementById('exp-open-devis-sheet-btn');
    const fabPriceDisplay = document.getElementById('fab-price-display');
    
    // Bottom-Sheet (Panneau Devis)
    const devisSheet = document.getElementById('exp-devis-sheet');
    const closeSheetTriggers = document.querySelectorAll('[data-close-devis-sheet]');
    const openContactModalBtn = document.getElementById('exp-open-modal-btn-local'); // Bouton "Réserver" DANS la sheet
    
    // Modale de Contact (finale)
    const contactModal = document.getElementById('exp-booking-modal');
    const modalSummaryContainer = contactModal.querySelector('.exp-booking-fieldset:first-of-type');
    const modalSummaryContent = document.getElementById('modal-quote-summary');
    const modalHiddenDetails = document.getElementById('modal-quote-details-hidden');
    const closeContactModalTriggers = contactModal.querySelectorAll('[data-close-modal]');
    const form = document.getElementById('experience-booking-form');
    const devisErrorMsg = document.getElementById('exp-devis-error-msg'); // Message d'erreur DANS la sheet

    // Vérification minimale
    if (!devisWrap || !fab || !devisSheet || !contactModal || !openContactModalBtn) {
        console.warn('Certains éléments de réservation sont manquants. Le module de réservation est désactivé.');
        return;
    }
    
    const pendingLabel = (devisWrap.dataset.labelPending || 'En attente de devis');
    const defaultFabText = fabPriceDisplay.textContent || 'Simuler un devis';

    // --- 2. Logique d'apparition du FAB ---
    
    function showFab() {
        if (!fab) return;
        fab.classList.add('is-visible');
    }
    
    // A-t-on déjà ouvert la sheet dans cette session ?
    if (sessionStorage.getItem('devisSheetOpened')) {
        showFab();
    } else {
        // Option 1: Afficher après 2 secondes
        const timer = setTimeout(showFab, 2000);
        
        // Option 2: Afficher après scroll de 30%
        const scrollThreshold = window.innerHeight * 0.3;
        function checkScroll() {
            if (window.scrollY > scrollThreshold) {
                showFab();
                clearTimeout(timer); // Annule le timer si le scroll a suffi
                window.removeEventListener('scroll', checkScroll); // N'écoute qu'une fois
            }
        }
        window.addEventListener('scroll', checkScroll, { passive: true });
    }

    // --- 3. Logique d'ouverture/fermeture des panneaux ---
    
    function openDevisSheet() {
        if (!devisSheet) return;
        if (fabPriceDisplay && fabPriceDisplay.textContent === 'Merci ! 🌴') {
            fabPriceDisplay.textContent = defaultFabText;
        }
        devisSheet.setAttribute('aria-hidden', 'false');
        devisSheet.classList.add('is-open');
        document.body.style.overflow = 'hidden'; // Verrouille le scroll
        sessionStorage.setItem('devisSheetOpened', 'true'); // Mémorise l'ouverture
        // Focus sur le premier élément interactif (le sélecteur de type)
        devisWrap.querySelector('select[name="devis_type"]').focus();
    }
    
    function closeDevisSheet() {
        if (!devisSheet) return;
        devisSheet.setAttribute('aria-hidden', 'true');
        devisSheet.classList.remove('is-open');
        document.body.style.overflow = ''; // Déverrouille le scroll
    }

    function openContactModal() {
        if (!contactModal) return;
        contactModal.setAttribute('aria-hidden', 'false');
        contactModal.classList.remove('is-hidden');
        document.body.style.overflow = 'hidden'; // Garde le scroll verrouillé
        // Focus sur le premier champ
        contactModal.querySelector('input[name="prenom"]').focus();
    }
    
    function closeContactModal() {
        if (!contactModal) return;
        contactModal.setAttribute('aria-hidden', 'true');
        contactModal.classList.add('is-hidden');
        document.body.style.overflow = ''; // Déverrouille le scroll
        
        // Gère la réinitialisation du formulaire après succès
        const successMessage = form.parentNode.querySelector('.form-success-message');
        if (successMessage) {
            successMessage.remove();
            form.style.display = 'block';
        }
    }

    // --- 4. Le "Pont" : Gérer la demande de réservation ---
    
    function handleBookingRequest() {
        // Vérifie si une simulation valide a été faite (logique de `calculate()`)
        const canOpen = !!window.hasValidSimulation;

        if (document.activeElement) document.activeElement.blur();                                           

        if (canOpen) {
            devisErrorMsg.classList.remove('is-visible');
            updateBookingInfo(true); // Force la mise à jour des infos de la modale contact
            closeDevisSheet();
            openContactModal();
        } else {
            // Affiche l'erreur DANS la sheet
            devisErrorMsg.textContent = 'Merci de remplir les champs (Adultes, Enfants, Bébés) pour faire une simulation avant de demander une réservation.';
            devisErrorMsg.classList.add('is-visible');
            
            // Fait vibrer le calculateur pour attirer l'attention
            devisWrap.style.transition = 'transform 0.1s ease-in-out';
            devisWrap.style.transform = 'translateX(-10px)';
            setTimeout(() => { 
                devisWrap.style.transform = 'translateX(10px)';
                setTimeout(() => { devisWrap.style.transform = 'translateX(0px)'; }, 100);
            }, 100);
        }
    }

    // --- 5. Mise à jour des infos (FAB + Modale Contact) ---
    
    function updateBookingInfo(isOpeningContactModal = false) {
        const showPending = (window.isSurDevis === true && window.hasValidSimulation === true);
        const showPriced  = (typeof window.currentTotal !== 'undefined' && window.currentTotal > 0);

        // A. Mise à jour du FAB
        if (showPending) {
            // NOUVEAU : Texte plus clair pour les devis "sur demande"
            fabPriceDisplay.textContent = 'Réserver (' + pendingLabel + ')';
        } else if (showPriced) {
            // NOUVEAU : Texte "Réserver pour : [PRIX]" comme demandé
            fabPriceDisplay.textContent = 'Réserver pour : ' + formatCurrency(window.currentTotal);
        } else {
            fabPriceDisplay.textContent = defaultFabText;
        }

        // B. Mise à jour de la Modale de Contact (seulement si nécessaire)
        if (isOpeningContactModal && (showPending || showPriced)) {
            let summaryHTML = '<ul>';
            let detailsText = '';
            (window.currentLines || []).forEach(line => {
              if (line.isError) return;
              if (line.isSeparator) {
                summaryHTML += `<li class="separator"><strong>${line.label}</strong></li>`;
                detailsText  += `\n--- ${line.label} ---\n`;
              } else {
                const priceTxt = showPending ? pendingLabel : line.price;
                summaryHTML += `<li><span>${line.label}</span><span>${priceTxt}</span></li>`;
                detailsText  += `${line.label}: ${priceTxt}\n`;
              }
            });
            summaryHTML += '</ul>';
            detailsText += `\nTotal: ${showPending ? pendingLabel : fabPriceDisplay.textContent}`;

            modalSummaryContainer.style.display = 'block';
            modalSummaryContent.innerHTML = summaryHTML;
            modalHiddenDetails.value = detailsText;
        } else if (isOpeningContactModal) {
            // Cas où on clique sur "Réserver" sans simulation valide (normalement bloqué par handleBookingRequest)
            modalSummaryContainer.style.display = 'none';
            modalSummaryContent.innerHTML = '';
            modalHiddenDetails.value = 'Aucune simulation de devis n\'a été effectuée.';
        }
    }

    // --- 6. Logique d'envoi du formulaire (Inchangée) ---
    
    form.addEventListener('submit', function(event) {
        event.preventDefault();
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.textContent;
        submitBtn.textContent = 'Envoi en cours...';
        submitBtn.disabled = true;
        const formData = new FormData(form);
        
        fetch(form.getAttribute('action'), { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                form.style.display = 'none';
                    if (fabPriceDisplay) fabPriceDisplay.textContent = 'Merci ! 🌴';
                const successMessage = document.createElement('div');
                successMessage.className = 'form-success-message';
                successMessage.innerHTML = `<h4>Merci ${formData.get('prenom')} !</h4><p>${data.data.message}</p><button type="button" class="pc-btn pc-btn--secondary" data-close-modal>Fermer</button>`;
                form.insertAdjacentElement('afterend', successMessage);
                // Utilise la fonction de fermeture de la modale de contact
                successMessage.querySelector('[data-close-modal]').addEventListener('click', closeContactModal);
            } else {
                alert('Erreur : ' + (data.data.message || 'Veuillez réessayer.'));
                submitBtn.textContent = originalBtnText;
                submitBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Une erreur technique est survenue.');
            submitBtn.textContent = originalBtnText;
            submitBtn.disabled = false;
        });
    });

    // --- 7. Écouteurs d'événements centraux ---

    // Ouvre la Sheet (Devis)
    fab.addEventListener('click', openDevisSheet);
    
    // Ferme la Sheet (Devis)
    closeSheetTriggers.forEach(trigger => trigger.addEventListener('click', closeDevisSheet));
    
    // Ouvre la Modale (Contact) depuis la Sheet
    openContactModalBtn.addEventListener('click', handleBookingRequest);
    
    // Ferme la Modale (Contact)
    closeContactModalTriggers.forEach(trigger => trigger.addEventListener('click', closeContactModal));
    
    // Met à jour le FAB quand le devis change
    devisWrap.addEventListener('devisUpdated', () => updateBookingInfo(false));
    
    // Gère la touche "Echap"
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            if (!contactModal.classList.contains('is-hidden')) {
                closeContactModal(); // Ferme la modale de contact en priorité
            } else if (devisSheet.classList.contains('is-open')) {
                closeDevisSheet(); // Sinon, ferme la sheet de devis
            }
        }
    });
});