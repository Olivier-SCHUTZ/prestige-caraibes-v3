// /assets/pc-fiche-logement.js (Version 4.2 - Nettoyée)

document.addEventListener('DOMContentLoaded', function() {

    // --- PARTIE 1 : "LE DÉMÉNAGEMENT" ---
    const devisSource = document.querySelector('.pc-devis-section');
    const devisTarget = document.getElementById('logement-devis-sheet-body');
    let devisMoved = false;

    if (devisSource && devisTarget) {
        devisTarget.appendChild(devisSource);
        devisMoved = true;
    } else {
        // Optionnel: Afficher un message si le devis n'est pas trouvé
        // console.warn('[Logement JS] Calculateur [pc_devis] ou cible introuvable.');
    }

    // --- Fonction d'initialisation (appelée après délai si déménagement) ---
    function initializeBookingLogic() {

        // --- PARTIE 2 : SÉLECTION DES ÉLÉMENTS ---
        const fab = document.getElementById('logement-open-devis-sheet-btn');
        const fabPriceDisplay = document.getElementById('fab-logement-price-display');
        const devisSheet = document.getElementById('logement-devis-sheet');
        const closeSheetTriggers = document.querySelectorAll('[data-close-devis-sheet]');
        const openContactModalBtn = document.getElementById('logement-open-modal-btn-local');
        const openLodgifyBtn = document.getElementById('logement-lodgify-reserve-btn');
        const contactModal = document.getElementById('logement-booking-modal');
        const closeContactModalTriggers = document.querySelectorAll('[data-close-modal]');
        const form = document.getElementById('logement-booking-form');
        const devisErrorMsg = devisSource ? devisSource.querySelector('#logement-devis-error-msg') : null;

        if (!fab || !devisSheet) return; // Sécurité minimale

        const initialFabHTML = fabPriceDisplay ? fabPriceDisplay.innerHTML : 'Estimer le séjour';

        // --- PARTIE 3 : LOGIQUE D'APPARITION DU FAB ---
        function showFab() {
            if (fab) fab.classList.add('is-visible');
        }
        if (sessionStorage.getItem('logementSheetOpened')) {
            showFab();
        } else {
            const timer = setTimeout(showFab, 2000);
            const scrollThreshold = window.innerHeight * 0.3;
            function checkScroll() {
                if (window.scrollY > scrollThreshold) {
                    showFab();
                    clearTimeout(timer);
                    window.removeEventListener('scroll', checkScroll);
                }
            }
            window.addEventListener('scroll', checkScroll, { passive: true });
        }

        // --- PARTIE 4 : OUVERTURE/FERMETURE DES PANNEAUX ---
        function openDevisSheet() {
             if (!devisSheet) return;
             devisSheet.setAttribute('aria-hidden', 'false');
             devisSheet.classList.add('is-open');
             document.body.style.overflow = 'hidden';
             sessionStorage.setItem('logementSheetOpened', 'true');

             if (fabPriceDisplay && (fabPriceDisplay.textContent === 'Merci ! 🌴' || fabPriceDisplay.textContent === 'Redirection...')) {
                 fabPriceDisplay.innerHTML = initialFabHTML;
             }

            if (devisSource) {
                 const dateInput = devisSource.querySelector('input[name="dates"]');
                 if (dateInput) {
                     setTimeout(() => dateInput.focus(), 50);
                 }
            }
        }
        function closeDevisSheet() {
             if (!devisSheet) return;
             devisSheet.setAttribute('aria-hidden', 'true');
             devisSheet.classList.remove('is-open');
             if (!contactModal || contactModal.classList.contains('is-hidden')) {
                 document.body.style.overflow = '';
             }
        }
        function openContactModal() {
             if (!contactModal) return;
             contactModal.setAttribute('aria-hidden', 'false');
             contactModal.classList.remove('is-hidden');
             document.body.style.overflow = 'hidden';
             const prenomInput = contactModal.querySelector('input[name="prenom"]');
             if (prenomInput) {
                setTimeout(() => prenomInput.focus(), 50);
             }
        }
        function closeContactModal() {
            if (!contactModal || !form) return;
            contactModal.setAttribute('aria-hidden', 'true');
            contactModal.classList.add('is-hidden');
            document.body.style.overflow = '';
            const successMessage = form.parentNode.querySelector('.form-success-message');
            if (successMessage) {
                successMessage.remove();
                form.style.display = 'block';
            }
        }

        // --- PARTIE 5 : MISE À JOUR DES PRIX ET INFOS ---
         function formatCurrency(num) {
             num = Number(num) || 0;
             const formatted = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(num);
             return formatted.endsWith(',00') ? formatted.slice(0, -3) + ' €' : formatted;
         }
         function updateFabText() {
             if (!fabPriceDisplay) return;
             if (fabPriceDisplay.textContent === 'Merci ! 🌴' || fabPriceDisplay.textContent === 'Redirection...') return;
             const total = window.currentLogementTotal;
             if (typeof total !== 'undefined' && total > 0) {
                 fabPriceDisplay.textContent = 'Réserver pour : ' + formatCurrency(total);
             } else {
                 fabPriceDisplay.innerHTML = initialFabHTML;
             }
         }
         function updateModalInfo() {
             if (!contactModal) return;
             const modalSummary = document.getElementById('modal-quote-summary-logement');
             const modalHiddenDetails = document.getElementById('modal-quote-details-hidden-logement');
             if (!modalSummary || !modalHiddenDetails) return;
             const total = window.currentLogementTotal;
             const lines = window.currentLogementLines;
             if (typeof total !== 'undefined' && total > 0 && lines && lines.length > 0) {
                 let summaryHTML = '<ul>';
                 let detailsText = '';
                 lines.forEach(line => {
                     const label = line.label || '';
                     const price = line.price || '';
                     summaryHTML += `<li><span>${label}</span><span>${price}</span></li>`;
                     detailsText += `${label}: ${price}\n`;
                 });
                 summaryHTML += '</ul>';
                 detailsText += `\nTotal: ${formatCurrency(total)}`;
                 modalSummary.innerHTML = summaryHTML;
                 modalHiddenDetails.value = detailsText;
             } else {
                 modalSummary.innerHTML = '<p>Veuillez d\'abord faire une simulation avec le calculateur.</p>';
                 modalHiddenDetails.value = 'Aucune simulation de devis n\'a été effectuée.';
             }
         }

        // --- PARTIE 6 : GESTION DES FLUX DE RÉSERVATION ---
        function handleLodgifyRedirect() {
            const selection = window.currentLogementSelection;
            const cfg = devisSource ? JSON.parse(devisSource.dataset.pcDevis || '{}') : {};
            let errorMessage = null;

            if (!selection) errorMessage = 'Veuillez d\'abord sélectionner vos dates et le nombre d\'invités.';
            else if (!selection.arrival || !selection.departure) errorMessage = 'Veuillez sélectionner vos dates d\'arrivée et de départ.';
            else if (!selection.adults || selection.adults < 1) errorMessage = 'Veuillez indiquer au moins 1 adulte.';
            else if (!cfg.lodgifyId || !cfg.lodgifyAccount) errorMessage = 'Erreur de configuration, impossible de générer le lien de réservation.';

            if (errorMessage) {
                if (devisErrorMsg) {
                    devisErrorMsg.textContent = errorMessage;
                    devisErrorMsg.classList.add('is-visible');
                    if (devisSource) { /* Vibreur */
                         devisSource.style.transition = 'transform 0.1s ease-in-out';
                         devisSource.style.transform = 'translateX(-10px)';
                         setTimeout(() => { devisSource.style.transform = 'translateX(10px)'; setTimeout(() => { devisSource.style.transform = 'translateX(0px)'; }, 100); }, 100);
                    }
                } else { alert(errorMessage); }
                return;
            }
            if(devisErrorMsg) devisErrorMsg.classList.remove('is-visible');

            try {
                const baseUrl = 'https://checkout.lodgify.com/fr/';
                const adults = parseInt(selection.adults, 10) || 0;
                const children = parseInt(selection.children, 10) || 0;
                // --- CORRECTION BUG BÉBÉ ---
                // Assure-toi que selection.infants existe et est un nombre avant parseInt
                const infants = (selection.infants !== null && typeof selection.infants !== 'undefined') ? (parseInt(selection.infants, 10) || 0) : 0;

                const url = `${baseUrl}${cfg.lodgifyAccount}/${cfg.lodgifyId}/contact?currency=EUR&arrival=${selection.arrival}&departure=${selection.departure}&adults=${adults}&children=${children}&infants=${infants}`;

                const newWindow = window.open(url, '_blank');
                 if (!newWindow || newWindow.closed || typeof newWindow.closed == 'undefined') {
                    alert("Votre navigateur a peut-être bloqué l'ouverture de la page de réservation. Veuillez autoriser les popups pour ce site.");
                 }
                closeDevisSheet();
                if (fabPriceDisplay) {
                     fabPriceDisplay.textContent = 'Redirection...';
                     setTimeout(() => {
                         if (fabPriceDisplay && fabPriceDisplay.textContent === 'Redirection...') {
                            fabPriceDisplay.innerHTML = initialFabHTML;
                         }
                     }, 4000);
                }
            } catch (e) {
                console.error('[Logement JS] Erreur URL Lodgify:', e); // Garde ce log en cas d'erreur
                alert('Une erreur est survenue lors de la tentative de redirection vers la réservation.');
            }
        }

        function handleBookingRequest() {
            const hasValidSimulation = typeof window.currentLogementTotal !== 'undefined' && window.currentLogementTotal > 0;
            if (hasValidSimulation) {
                if(devisErrorMsg) devisErrorMsg.classList.remove('is-visible');
                updateModalInfo();
                closeDevisSheet();
                openContactModal();
            } else {
                 let errorMessage = 'Veuillez remplir le simulateur ci-dessus avant de faire une demande.';
                 if (devisErrorMsg) {
                     devisErrorMsg.textContent = errorMessage;
                     devisErrorMsg.classList.add('is-visible');
                 } else { alert(errorMessage); }
                 if (devisSource) { /* Vibreur */
                     devisSource.style.transition = 'transform 0.1s ease-in-out';
                     devisSource.style.transform = 'translateX(-10px)';
                     setTimeout(() => { devisSource.style.transform = 'translateX(10px)'; setTimeout(() => { devisSource.style.transform = 'translateX(0px)'; }, 100); }, 100);
                 }
            }
        }

        if (form && contactModal) {
            form.addEventListener('submit', function(event) {
                event.preventDefault();
                const nonceValue = (typeof pcLogementData !== 'undefined' && pcLogementData.nonce) ? pcLogementData.nonce : null;
                const nonceInput = form.querySelector('input[name="nonce"]');
                if (nonceInput && nonceValue) nonceInput.value = nonceValue;

                const submitBtn = form.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.textContent;
                submitBtn.textContent = 'Envoi en cours...';
                submitBtn.disabled = true;
                const formData = new FormData(form);

                if (devisSource) {
                    const adultsInput = devisSource.querySelector('input[name="devis_adults"]');
                    const childrenInput = devisSource.querySelector('input[name="devis_children"]');
                    const infantsInput = devisSource.querySelector('input[name="devis_infants"]');
                    formData.append('adultes', adultsInput ? adultsInput.value : '0');
                    formData.append('enfants', childrenInput ? childrenInput.value : '0');
                    formData.append('bebes', infantsInput ? infantsInput.value : '0');
                }

                fetch(form.getAttribute('action'), { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        form.style.display = 'none';
                        if(fabPriceDisplay) fabPriceDisplay.textContent = 'Merci ! 🌴';
                        const successMessage = document.createElement('div');
                        successMessage.className = 'form-success-message';
                        const prenom = formData.get('prenom');
                        const prenomSanitized = prenom ? prenom.replace(/</g, "&lt;").replace(/>/g, "&gt;") : '';
                        successMessage.innerHTML = `<h4>Merci ${prenomSanitized} !</h4><p>${data.data.message}</p><button type="button" class="pc-btn pc-btn--secondary" data-close-modal>Fermer</button>`;
                        form.insertAdjacentElement('afterend', successMessage);
                        const closeBtn = successMessage.querySelector('[data-close-modal]');
                        if(closeBtn) closeBtn.addEventListener('click', closeContactModal);
                    } else {
                        alert('Erreur : ' + (data.data ? data.data.message : 'Veuillez réessayer.'));
                        submitBtn.textContent = originalBtnText;
                        submitBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Erreur Fetch:', error); // Garde ce log en cas d'erreur
                    alert('Une erreur technique est survenue.');
                    submitBtn.textContent = originalBtnText;
                    submitBtn.disabled = false;
                });
            });
             if (closeContactModalTriggers) {
                 closeContactModalTriggers.forEach(trigger => trigger.addEventListener('click', closeContactModal));
             }
        }

        // --- PARTIE 7 : ÉCOUTEURS D'ÉVÉNEMENTS GLOBAUX ---
        if (fab) fab.addEventListener('click', openDevisSheet);
        if (closeSheetTriggers) closeSheetTriggers.forEach(trigger => trigger.addEventListener('click', closeDevisSheet));
        if (openContactModalBtn) openContactModalBtn.addEventListener('click', handleBookingRequest);
        if (openLodgifyBtn) openLodgifyBtn.addEventListener('click', handleLodgifyRedirect);

        document.addEventListener('devisLogementUpdated', function() {
            updateFabText();
            updateModalInfo();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                if (contactModal && !contactModal.classList.contains('is-hidden')) closeContactModal();
                else if (devisSheet && devisSheet.classList.contains('is-open')) closeDevisSheet();
            }
        });

    } // Fin de initializeBookingLogic

    // --- Lancer l'initialisation ---
    if (devisMoved) {
        setTimeout(initializeBookingLogic, 50);
    } else {
        initializeBookingLogic();
    }

}); // Fin de DOMContentLoaded