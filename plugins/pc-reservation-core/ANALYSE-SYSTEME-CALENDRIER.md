# 📅 ANALYSE SYSTÈME CALENDRIER - Migration Vue 3/Vite/Pinia

**Date d'analyse :** 11 mars 2026  
**Contexte :** Pattern Strangler - Migration progressive vers Vue 3/Vite/Pinia  
**Statut modules :** Housing ✅ | Experience ✅ | **Calendrier** 🔄 (En cours)

---

## 📋 Vue d'ensemble

Le système de calendrier de `pc-reservation-core` sert actuellement à :

### 🎯 **Fonctionnalités principales**

- **Dashboard Calendrier Global** : Vue multi-logements avec grille mensuelle + 15 jours
- **Planning Unique par Logement** : Modale détaillée avec sélection de périodes
- **Gestion des Blocages Manuels** : Création/suppression via interface
- **Export iCal** : Flux ICS pour synchronisation OTAs (Airbnb, Booking.com...)
- **Import iCal** : Récupération calendriers externes des propriétaires
- **Gestion des Réservations** : Affichage des réservations confirmées avec statuts de paiement

### 📊 **Sources de données**

- `wp_pc_reservations` : Réservations internes (statut : réservée)
- `wp_pc_unavailabilities` : Blocages manuels (type_source : manuel)
- `_booked_dates_cache` (meta) : Dates importées via iCal externe
- Calendriers iCal des propriétaires (import temps réel)

---

## 🏗️ Architecture Actuelle vs Cible

### **LEGACY (À Étrangler)** ❌

| Fichier                             | Taille       | Description                                                                                | État                         |
| ----------------------------------- | ------------ | ------------------------------------------------------------------------------------------ | ---------------------------- |
| `assets/js/pc-calendar.js`          | ~830 lignes  | **JavaScript Vanilla** - Classe `PcDashboardCalendar`, manipulation DOM directe, fetch API | 🔴 **À migrer**              |
| `assets/css/pc-calendar.css`        | ~1046 lignes | **Styles complets** - Variables CSS, thème violet moderne                                  | 🔴 **À migrer**              |
| `shortcodes/shortcode-calendar.php` | ~180 lignes  | **Shortcode PHP** - Génération HTML, enqueue assets legacy                                 | 🔴 **À migrer**              |
| `includes/class-ical-export.php`    | ~50 lignes   | **Proxy Legacy** - Redirige vers nouveau service (OK temporaire)                           | 🟡 **À supprimer plus tard** |

### **NOUVEAU MONDE (Déjà Prêt)** ✅

| Fichier                                                        | Taille      | Description                                                              | État             |
| -------------------------------------------------------------- | ----------- | ------------------------------------------------------------------------ | ---------------- |
| `includes/ajax/controllers/class-calendar-ajax-controller.php` | ~400 lignes | **Contrôleur AJAX moderne** - Endpoints structurés, validation, sécurité | ✅ **Prêt**      |
| `includes/services/calendar/class-ical-exporter.php`           | ~180 lignes | **Service iCal moderne** - Pattern Singleton, méthodes propres           | ✅ **Prêt**      |
| `src/components/Housing/RateCalendarArea.vue`                  | ~250 lignes | **Composant Vue 3** - FullCalendar, gestion tarifs saisonniers           | ✅ **Référence** |

---

## 📥 Flux de Données

### **API Endpoints (Déjà Disponibles)**

```php
// Contrôleur : PCR_Calendar_Ajax_Controller
wp_ajax_pc_get_calendar_global       // Vue multi-logements
wp_ajax_pc_get_single_calendar       // Planning logement unique
wp_ajax_pc_calendar_create_block     // Création blocage manuel
wp_ajax_pc_calendar_delete_block     // Suppression blocage manuel
```

### **Structure de Données (Format API)**

```javascript
// Réponse GET calendar_global
{
  month: 3, year: 2026,
  start_date: "2026-03-01",
  end_date: "2026-03-31",
  extended_end: "2026-04-15",
  logements: [
    { id: 123, title: "Villa Paradise" }
  ],
  events: [
    {
      logement_id: 123,
      start_date: "2026-03-15", end_date: "2026-03-18",
      source: "reservation|manual|ical",
      payment_status: "paye|partiel|pending",
      label: "Dupont J. (#456)"
    }
  ]
}
```

### **Mapping Couleurs par Statut**

```css
/* Réservations internes */
--paye: linear-gradient(135deg, #059669, #047857) /* Vert foncé - Soldé */
  --partiel: linear-gradient(135deg, #0284c7, #0369a1) /* Bleu - Acompte */
  --pending: linear-gradient(135deg, #ea580c, #c2410c) /* Orange - En attente */
  /* Autres sources */ --manual: linear-gradient(135deg, #f0b429, #d97706)
  /* Jaune - Blocage */ --ical: linear-gradient(135deg, #5c6f82, #475569)
  /* Gris - Import */;
```

---

## 🧱 Structure Vue 3 & Pinia Proposée

### **1. Store Pinia : `calendar-store.js`**

```javascript
// src/stores/calendar-store.js
export const useCalendarStore = defineStore('calendar', {
  state: () => ({
    // Navigation
    currentMonth: new Date().getMonth() + 1,
    currentYear: new Date().getFullYear(),

    // Données
    logements: [],
    events: [],
    selectedLogement: null,

    // UI States
    loading: false,
    error: null,
    modalOpen: false,
    selection: null, // { logementId, start, end }
  }),

  actions: {
    async fetchGlobalCalendar(month, year),
    async fetchSingleCalendar(logementId, month, year),
    async createManualBlock(logementId, startDate, endDate),
    async deleteManualBlock(blockId),

    // UI Actions
    openModal(logementId),
    closeModal(),
    setSelection(logementId, start, end),
    clearSelection(),
  }
})
```

### **2. Composant Principal : `CalendarDashboard.vue`**

```vue
<!-- src/components/Calendar/CalendarDashboard.vue -->
<template>
  <div class="pc-calendar-dashboard">
    <CalendarHeader
      v-model:month="store.currentMonth"
      v-model:year="store.currentYear"
      @refresh="store.fetchGlobalCalendar"
    />

    <CalendarGrid
      :logements="store.logements"
      :events="store.events"
      :loading="store.loading"
      @open-modal="store.openModal"
    />

    <CalendarModal
      v-if="store.modalOpen"
      :logement="store.selectedLogement"
      @close="store.closeModal"
    />
  </div>
</template>
```

### **3. Composants Enfants**

```
src/components/Calendar/
├── CalendarDashboard.vue       # Composant racine
├── CalendarHeader.vue          # Navigation (mois/année/today)
├── CalendarGrid.vue            # Grille multi-logements
├── CalendarRow.vue             # Ligne logement + événements
├── CalendarCell.vue            # Cellule jour
├── CalendarEvent.vue           # Barre événement (réservation/blocage)
├── CalendarModal.vue           # Modale planning unique
├── CalendarModalGrid.vue       # Grille planning unique
├── CalendarModalCell.vue       # Cellule sélectionnable
├── CalendarSelectionBar.vue    # Actions sélection (créer résa/blocage)
└── CalendarLegend.vue          # Légende couleurs
```

### **4. Module Principal**

```javascript
// src/modules/calendar/main.js
import { createApp } from "vue";
import { createPinia } from "pinia";
import CalendarApp from "./CalendarApp.vue";

const pinia = createPinia();
const app = createApp(CalendarApp);

app.use(pinia);
app.mount("[data-pc-calendar-vue]");
```

---

## 🚀 Plan d'Action (Step-by-Step)

### **📍 Phase 1 : Préparation (1-2 jours)**

- [ ] Créer la structure de dossiers `src/modules/calendar/`
- [ ] Créer le store Pinia `calendar-store.js` avec actions de base
- [ ] Créer l'app shell `CalendarApp.vue` et `main.js`
- [ ] Configurer Vite pour le build du module calendar

### **📍 Phase 2 : Composants Core (2-3 jours)**

- [ ] Développer `CalendarHeader.vue` (navigation mois/année)
- [ ] Développer `CalendarGrid.vue` (structure grille principale)
- [ ] Développer `CalendarRow.vue` et `CalendarCell.vue`
- [ ] Implémenter la logique de rendu des événements
- [ ] Intégrer les couleurs/styles existants depuis le CSS legacy

### **📍 Phase 3 : Modale Planning Unique (2-3 jours)**

- [ ] Développer `CalendarModal.vue` avec navigation interne
- [ ] Créer `CalendarModalGrid.vue` pour le planning timeline
- [ ] Implémenter la sélection de périodes (drag & clic)
- [ ] Ajouter `CalendarSelectionBar.vue` pour les actions

### **📍 Phase 4 : Actions & États (1-2 jours)**

- [ ] Connecter toutes les actions du store aux API endpoints
- [ ] Implémenter la création/suppression de blocages manuels
- [ ] Ajouter la gestion d'erreurs et loading states
- [ ] Tester les cas edge (logements vides, erreurs API)

### **📍 Phase 5 : Intégration & Bascule (1 jour)**

- [ ] Modifier `shortcode-calendar.php` pour charger Vue au lieu de jQuery
- [ ] Ajouter le flag de feature toggle pour rollback si nécessaire
- [ ] Tests fonctionnels complets (création, suppression, navigation)
- [ ] Monitoring des erreurs pendant la bascule

### **📍 Phase 6 : Nettoyage (1 jour)**

- [ ] Supprimer `assets/js/pc-calendar.js` (.off)
- [ ] Supprimer `assets/css/pc-calendar.css` (.off)
- [ ] Nettoyer le shortcode PHP (garder que l'essentiel)
- [ ] Documenter les changements dans `@architecture.md`

---

## ⚠️ Points d'Attention

### **🔒 Sécurité**

- **Nonces AJAX** : Le contrôleur utilise déjà `verify_access('pc_dashboard_calendar')`
- **Validation** : Sanitization des dates et IDs logements OK
- **Permissions** : Vérifier `manage_options` côté Vue également

### **🎨 Design System**

- **Variables CSS** : Réutiliser les variables du legacy (`--pc-cal-primary`, etc.)
- **Responsivité** : Le CSS actuel gère déjà mobile (min-width breakpoints)
- **Accessibilité** : Conserver les attributs ARIA et focus management

### **⚡ Performance**

- **Lazy Loading** : Charger la modale uniquement à l'ouverture
- **Caching** : Le contrôleur utilise déjà des transients pour iCal
- **Bundle Size** : Éviter d'importer des lib lourdes (FullCalendar OK pour tarifs)

### **🔄 Rétrocompatibilité**

- **iCal Export** : Le service moderne est déjà en place ✅
- **URLs d'export** : Pas de changement nécessaire ✅
- **Shortcode** : L'attribut `reservation_url` doit être préservé

---

## 📈 Bénéfices de la Migration

### **🚀 Technique**

- **Réactivité** : Mises à jour automatiques sans reload page
- **Maintenabilité** : Code modulaire, composants réutilisables
- **Performance** : Bundle optimisé Vite, tree-shaking automatique
- **Type Safety** : Possibilité d'ajouter TypeScript plus tard

### **👤 Utilisateur**

- **UX Fluide** : Transitions, loading states, feedback immédiat
- **Sélection Intuitive** : Drag & drop pour sélectionner des périodes
- **Mobile First** : Interface responsive native Vue 3
- **Accessibilité** : Gestion focus et navigation clavier améliorée

### **🔧 Développement**

- **Hot Reload** : Développement plus rapide avec Vite
- **Debugging** : Vue DevTools, meilleure traçabilité des erreurs
- **Testing** : Tests unitaires possibles avec Vitest
- **Documentation** : Code auto-documenté via composants

---

## 🎯 Critères de Succès

- [ ] **Zero Downtime** : Bascule transparente pour les utilisateurs
- [ ] **Parité Fonctionnelle** : Toutes les features legacy préservées
- [ ] **Performance** : Temps de chargement ≤ 2s (identique ou mieux)
- [ ] **Mobile** : Interface utilisable sur tablette/smartphone
- [ ] **Accessibilité** : Navigation clavier et screen readers OK
- [ ] **Monitoring** : Aucune erreur JS côté production pendant 48h post-déploiement

---

_🏁 **Migration estimée : 7-10 jours** | **Complexité : Moyenne** | **Risque : Faible** (infrastructure moderne déjà en place)_
