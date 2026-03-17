# Rapport complet — Modale de création de réservation (ancien vs nouveau)

**Plugin analysé :** `plugins/pc-reservation-core`  
**Date :** 2026-03-14  
**Objectif :** cartographier précisément la modale de création (legacy + refacto), identifier les connexions avec `mu-plugins`, et préparer une reprise du refactoring « from scratch ».

---

## 1) Résumé exécutif (important)

Tu as **deux systèmes en parallèle** sur la même page :

1. **Système legacy (actif en prod pour la création réelle)**
   - Modale HTML/PHP + JS vanilla modulaire (`dashboard-core.js` + `booking-form.js`)
   - Endpoint principal : `pc_manual_reservation_create`
   - C’est celui qui **crée/vraiment met à jour** les réservations.

2. **Système Vue V2 (partiellement branché, incomplet pour création)**
   - `BookingForm.vue` + store Pinia
   - Le calcul de devis est branché, mais la création finale est encore un `alert(...)`
   - Donc V2 est **fonctionnel en UI partielle**, pas finalisé pour la création.

👉 **Cause principale du “ça part en cacahouète”** : coexistence de deux fronts qui manipulent les mêmes concepts (création, détails, actions), avec conventions différentes (DOM, nonce, cycle UI).

---

## 2) Fichiers qui concernent la modale de création — **ANCIEN système (legacy actif)**

## 2.1 Templates / structure modale

- `templates/dashboard/list.php`
  - Contient :
    - bouton `+ Créer une réservation` (`.pc-resa-create-btn`)
    - template caché `#pc-resa-create-template` (le **formulaire de création complet**)
- `templates/dashboard/modal-detail.php`
  - Conteneur global de modale :
    - `#pc-resa-modal`
    - `#pc-resa-modal-content`
    - backdrop + bouton fermeture
- `shortcodes/shortcode-dashboard.php`
  - Charge les templates ci-dessus
  - Enqueue scripts/styles legacy nécessaires à la modale
  - Localize `window.pcResaParams`

## 2.2 JavaScript legacy (pilotage modale)

- `assets/js/dashboard-core.js`
  - Ouvre/ferme la modale (`openResaModal`, `closeResaModal`)
  - Injecte le HTML de `#pc-resa-create-template` dans `#pc-resa-modal-content`
  - Initialise `window.PCR.BookingForm.init(...)`
  - Gère aussi l’édition via prefill (`data-prefill`)

- `assets/js/modules/booking-form.js`
  - Cœur métier front de la création legacy
  - Gère type `experience/location`, item, dates, participants, remise/plus-value
  - Appels AJAX :
    - `pc_manual_logement_config`
    - `pc_manual_reservation_create`

- `assets/js/modules/pricing-engine.js`
  - Calcul devis expérience + délégation devis logement
  - Utilise `window.PCLogementDevis` (si présent)

- `assets/js/modules/utils.js`
  - Helpers utilisés par `booking-form`/`pricing-engine`

## 2.3 Backend legacy (endpoints création)

- `includes/ajax/controllers/class-ajax-router.php`
  - Route les actions AJAX de création :
    - `pc_manual_reservation_create`
    - `pc_manual_logement_config`

- `includes/ajax/controllers/class-reservation-ajax-controller.php`
  - `handle_manual_reservation()` : validation + create/update via `PCR_Booking_Engine`
  - `handle_logement_config()` : renvoie config logement pour le calendrier/devis

- `includes/class-booking-engine.php` et services booking/housing
  - Exécution métier côté serveur (create/update/cancel/confirm, pricing housing…)

## 2.4 CSS legacy modale/form

- `assets/css/dashboard-modals.css` (overlay, dialog, close, popups)
- `assets/css/dashboard-forms.css` (formulaire création, champs, sections, résumé)
- `assets/css/dashboard-style.css` (agrégation styles dashboard)

---

## 3) Fichiers qui concernent la modale de création — **NOUVEAU système (Vue V2 en cours)**

## 3.1 Entrée Vue + shell

- `src/modules/dashboard/main.js`
  - Monte Vue sur `#pc-reservation-dashboard-app`

- `src/modules/dashboard/App.vue`
  - Compose `ReservationList`, `ReservationModal`, `BookingForm`

## 3.2 Modale de création Vue

- `src/components/dashboard/BookingForm.vue`
  - Modale V2 “Nouvelle Réservation / Devis”
  - Récupère items (`locations/experiences`) + calcul devis via store
  - ⚠️ `handleCreate()` = **stub (alert)**, pas de persistance finale

- `src/stores/reservations-store.js`
  - `openCreateModal()`, `fetchBookingItems()`, `calculateQuote()`
  - `create` final non branché dans le composant

- `src/services/reservation-api.js`
  - API côté Vue : `getBookingItems`, `calculatePrice`, `createManual`, etc.

- `src/services/api-client.js`
  - Axios + injection nonce en champ `security`

## 3.3 Modale détails Vue (pas création, mais même domaine)

- `src/components/dashboard/ReservationModal.vue`
  - Modale détails V2
  - Actions confirm/cancel branchées

---

## 4) Flux réel actuel de la création (ce qui marche vraiment)

1. Clic bouton legacy `.pc-resa-create-btn` (`list.php`)
2. `dashboard-core.js` ouvre `#pc-resa-modal`
3. Injection du template `#pc-resa-create-template`
4. Initialisation `PCR.BookingForm`
5. `booking-form.js` prépare payload et POST sur `admin-ajax.php` (action `pc_manual_reservation_create`, nonce `nonce`)
6. `PCR_Reservation_Ajax_Controller::handle_manual_reservation()`
7. `PCR_Booking_Engine::create/update()`

✅ Ce flux est opérationnel.

---

## 5) Connexions vers **mu-plugins** (revalidation post-refacto)

## ⚠️ Correctif important

Tu as raison : **`mu-plugins/assets/pc-devis.js` n’existe plus** dans l’état actuel.

Le rapport initial mentionnait encore ce fichier historique. Après revalidation, la liaison réelle est différente (voir ci-dessous).

## 5.1 Ancienne connexion devenue obsolète ❌

- `shortcodes/shortcode-dashboard.php`
  - Référence encore l’ancien chemin :
    - `WP_CONTENT_DIR . '/mu-plugins/assets/pc-devis.js'`
  - Comme ce fichier n’existe plus, l’enqueue conditionnel ne charge rien.

👉 C’est donc un **reste legacy à nettoyer** (dette technique explicite).

## 5.2 Connexion effective actuelle (bridge de compatibilité) ✅

- `mu-plugins/pc-logement/assets/js/components/pc-calendar-integration.js`
  - Expose un pont rétrocompatible :
    - `window.PCLogementDevis = window.PCLogementDevis || {};`
    - `window.PCLogementDevis.waitForFlatpickr = ...`
  - Utilise le nouveau moteur : `window.PCDevisCalculator.calculateQuote(...)`

- `mu-plugins/assets/pc-orchestrator.js`
  - Orchestrateur global JS
  - Fournit `registerDevisInit(...)` / `initDevis(...)`

👉 Donc la compatibilité ne passe plus par `pc-devis.js`, mais par **PCDevisCalculator + bridge PCLogementDevis + orchestrateur**.

## 5.3 Connexions indirectes (dépendances fonctionnelles) ✅

- **CPT utilisés par la modale** : `villa`, `appartement`, `experience`
  - Définis dans `mu-plugins/pc-custom-typesV3.php`
  - Le formulaire de création les consomme (sélecteurs d’items)

- **ACF / options / champs métier**
  - Le plugin utilise beaucoup `get_field(...)` (tarifs, config logement, options dashboard)
  - Champs ACF et JSON de groupes présents sous `mu-plugins/pc-acf*` / `pc-acf-json`

## 5.4 Connexion inexistante (pas de require direct) ℹ️

- Pas de `require_once` direct vers fichiers PHP de `mu-plugins` dans le flux modale création.
- Le couplage passe surtout par **WordPress runtime** (CPT/ACF) et des **globals JS de compatibilité**.

---

## 6) Incohérences/risques qui cassent le refactoring

1. **Double front simultané** (legacy + Vue) sur la même page.
2. **Création V2 incomplète** (`BookingForm.vue` ne persiste pas).
3. **Convention nonce différente** :
   - Legacy création attend `nonce`
   - API client Vue injecte `security`
   - Risque de 400/nonce invalide si branchement direct sans adaptation.
4. **Deux UX de création concurrentes** (bouton legacy + bouton Vue), sources de confusion produit/dev.
5. **Référence obsolète à `pc-devis.js`** dans `shortcode-dashboard.php` (fichier supprimé côté mu-plugins).
6. **Couplage implicite via globals (`PCLogementDevis`, `PCOrchestrator`)** : difficile à tracer et fragile.

---

## 7) Recommandation “repartir propre” (plan court)

1. **Choisir une seule UI source of truth** : Vue V2 ou legacy (pas les deux).
2. Si objectif “nouvelle génération” :
   - Finaliser `BookingForm.vue` → brancher `reservationApi.createManual(...)`
   - Harmoniser backend pour accepter `security` (ou envoyer `nonce` côté V2)
3. **Isoler/feature flag** la modale legacy (OFF en prod dès que V2 prête).
4. **Supprimer la référence morte `pc-devis.js`** dans `shortcode-dashboard.php`.
5. **Documenter officiellement le bridge actuel** (`PCDevisCalculator` / `PCLogementDevis` / `PCOrchestrator`) et décider si on le garde ou non.
6. **Découpler progressivement le dashboard réservation** des globals mu-plugins au profit d’un contrat d’API clair (`calculate_price` backend + store Vue).
7. Documenter explicitement le contrat payload unique (create/update) pour éviter la dérive.

---

## 8) Inventaire final “modale création” (checklist rapide)

### Legacy (actif)

- `templates/dashboard/list.php`
- `templates/dashboard/modal-detail.php`
- `shortcodes/shortcode-dashboard.php`
- `assets/js/dashboard-core.js`
- `assets/js/modules/booking-form.js`
- `assets/js/modules/pricing-engine.js`
- `assets/js/modules/utils.js`
- `assets/css/dashboard-modals.css`
- `assets/css/dashboard-forms.css`
- `includes/ajax/controllers/class-ajax-router.php`
- `includes/ajax/controllers/class-reservation-ajax-controller.php`
- `includes/class-booking-engine.php` (+ services booking/housing)

### Nouveau (en cours)

- `src/modules/dashboard/main.js`
- `src/modules/dashboard/App.vue`
- `src/components/dashboard/ReservationList.vue`
- `src/components/dashboard/BookingForm.vue`
- `src/stores/reservations-store.js`
- `src/services/reservation-api.js`
- `src/services/api-client.js`

### Connexions mu-plugins liées à la modale

- `shortcodes/shortcode-dashboard.php` (référence legacy vers `pc-devis.js`, désormais obsolète)
- `mu-plugins/assets/pc-orchestrator.js` (orchestration JS)
- `mu-plugins/pc-logement/assets/js/components/pc-calendar-integration.js` (bridge `PCLogementDevis`)
- `mu-plugins/pc-logement/assets/js/modules/pc-devis-calculator.js` (moteur devis logement)
- `mu-plugins/pc-custom-typesV3.php` (CPT consommés)
- ACF/Options via `mu-plugins/pc-acf*` / `pc-acf-json` (couplage fonctionnel)

---

## 9) Conclusion

La modale de création “qui marche” est encore clairement **legacy**. La version Vue est engagée mais **pas terminée** sur le chemin critique de persistance. Le refactoring déraille surtout parce que les deux mondes cohabitent sans frontière nette. Pour repartir à zéro proprement, il faut d’abord trancher l’owner du flux création, puis verrouiller un contrat API/nonce unique.

---

## 10) Prompt prêt à coller dans Gemini (reprise du chantier)

Copie-colle ce prompt tel quel dans Gemini, avec les fichiers listés en section 12 :

```text
Contexte projet
Tu es mon pair-programmer senior WordPress/PHP/JS/Vue.
Je travaille sur le plugin `pc-reservation-core` d’un projet WordPress complexe.
Je dois reprendre le refactoring de la modale de création de réservation, qui mélange legacy + Vue V2.

Objectif principal
1) Stabiliser le flux de création de réservation.
2) Supprimer les liaisons legacy obsolètes.
3) Préparer une migration propre vers une seule source de vérité (idéalement Vue V2).

Constats importants déjà validés
- Le flux de création réellement opérationnel est legacy (dashboard-core.js + booking-form.js + endpoint pc_manual_reservation_create).
- Le flux Vue V2 de création (BookingForm.vue) est incomplet (handleCreate = stub/alert).
- Une référence legacy vers `mu-plugins/assets/pc-devis.js` existe encore dans `shortcode-dashboard.php`, mais ce fichier n’existe plus.
- La compatibilité devis logement passe aujourd’hui par un bridge: `PCDevisCalculator` + `PCLogementDevis` + `PCOrchestrator`.

Ta mission
- Produire un plan d’exécution concret en petites étapes sûres.
- Proposer des patches minimaux, testables, et réversibles.
- Pour chaque étape, indiquer:
  - Pourquoi on le fait
  - Risque
  - Comment tester
  - Rollback rapide

Contraintes de style de correction (obligatoire)
Quand tu proposes une modif, utilise ce format strict:
1) AVANT (extrait exact)
2) APRÈS (extrait exact)
3) REMPLACER PAR (diff ou bloc final)
4) IMPACT (fichiers / comportement)
5) TESTS MANUELS

Priorités techniques
P1. Nettoyer les références mortes (`pc-devis.js`) dans le dashboard réservation.
P2. Uniformiser la convention de nonce entre frontend et backend (`nonce` vs `security`).
P3. Finaliser `BookingForm.vue` pour persister réellement via `reservationApi.createManual(...)`.
P4. Réduire le couplage aux globals mu-plugins côté `pc-reservation-core`.
P5. Garder un mode de fallback temporaire (feature flag) tant que la V2 n’est pas totalement validée.

Livrables attendus de ta réponse
1) Plan détaillé (phase 0 à phase 3)
2) Première PR logique (petit scope)
3) Patchs exacts “avant/après/remplacer”
4) Checklist de validation fonctionnelle
5) Liste des régressions possibles à surveiller

Important
Ne réécris pas tout d’un coup. Je veux des changements incrémentaux, robustes, avec validation à chaque étape.
```

---

## 11) Méthode de travail recommandée pour corrections de code

## 11.1 Convention d’édition (ta méthode “avant / après / remplacer”)

Pour chaque correction, suivre strictement ce template :

1. **AVANT**
   - Coller uniquement le bloc concerné (minimal, exact)
2. **APRÈS**
   - Montrer uniquement le bloc corrigé
3. **REMPLACER PAR**
   - Donner le patch concret à appliquer
4. **REMPLACER LA FONCTION** (si applicable)
   - Mentionner le nom exact de la fonction
   - Fournir la version complète finale de cette fonction
5. **TESTS**
   - 3 à 5 tests manuels max, orientés métier

## 11.2 Séquence de correction recommandée

1. **Correction de dette technique sans impact fonctionnel**
   - Ex: retirer références script mort
2. **Harmonisation contrat front/backend**
   - Nonce, payload, noms d’actions
3. **Activation progressive de la création V2**
   - Brancher createManual + gestion succès/erreur
4. **Bascule de responsabilité**
   - Désactiver entrée legacy une fois V2 validée

## 11.3 Règle d’or

Une PR = un objectif unique = un risque limité.

---

## 12) Fichiers à joindre à Gemini pour démarrer sereinement

## 12.1 Noyau modale création (obligatoire)

- `plugins/pc-reservation-core/templates/dashboard/list.php`
- `plugins/pc-reservation-core/templates/dashboard/modal-detail.php`
- `plugins/pc-reservation-core/assets/js/dashboard-core.js`
- `plugins/pc-reservation-core/assets/js/modules/booking-form.js`
- `plugins/pc-reservation-core/assets/js/modules/pricing-engine.js`
- `plugins/pc-reservation-core/shortcodes/shortcode-dashboard.php`
- `plugins/pc-reservation-core/includes/ajax/controllers/class-ajax-router.php`
- `plugins/pc-reservation-core/includes/ajax/controllers/class-reservation-ajax-controller.php`

## 12.2 Partie Vue V2 (obligatoire)

- `plugins/pc-reservation-core/src/components/dashboard/BookingForm.vue`
- `plugins/pc-reservation-core/src/components/dashboard/ReservationList.vue`
- `plugins/pc-reservation-core/src/modules/dashboard/App.vue`
- `plugins/pc-reservation-core/src/stores/reservations-store.js`
- `plugins/pc-reservation-core/src/services/reservation-api.js`
- `plugins/pc-reservation-core/src/services/api-client.js`

## 12.3 Liaisons mu-plugins (obligatoire pour comprendre le bridge)

- `mu-plugins/assets/pc-orchestrator.js`
- `mu-plugins/pc-logement/assets/js/components/pc-calendar-integration.js`
- `mu-plugins/pc-logement/assets/js/modules/pc-devis-calculator.js`
- `mu-plugins/pc-custom-typesV3.php`

## 12.4 Fichiers de contexte (recommandé)

- `plugins/pc-reservation-core/pc-reservation-core.php`
- `plugins/pc-reservation-core/fichier-md/rapport-modale-creation-reservation-refactoring-2026-03-14.md`
- `plugins/pc-reservation-core/@architecture.md`
- `plugins/pc-reservation-core/@architecture-refactoring.md`
