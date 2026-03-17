# 🎯 Analyse Système Dashboard - Plugin PC Reservation Core

> **Date d'analyse :** 03/12/2026  
> **Contexte :** Migration "Pattern Strangler" jQuery/PHP → Vue 3/Vite/Pinia/Axios  
> **Statut :** Modules Housing, Experience et Calendrier déjà migrés  
> **Cible :** **SYSTÈME DASHBOARD** (le cœur du plugin)

---

## 📋 Vue d'Ensemble

Le **système Dashboard** constitue le **cœur fonctionnel** du plugin pc-reservation-core. Il s'agit d'une interface d'administration complète permettant aux propriétaires de gérer l'intégralité de leurs réservations.

### Fonctionnalités principales

- **🏠 Gestion des réservations** (création, modification, annulation, confirmation)
- **💬 Système de messagerie** complet avec Channel Manager (WhatsApp, Email, Notes internes)
- **💰 Gestion des paiements** et liens Stripe
- **📄 Génération de documents** (devis, factures, contrats, vouchers)
- **📊 Statistiques** et tableaux de bord
- **📅 Intégration calendrier** pour création rapide de réservations
- **🔄 Workflows** de statuts (brouillon → devis → confirmé → payé)

---

## 🏗️ Architecture Actuelle vs Cible

### 📂 Legacy (À "Étrangler")

| **Fichier Legacy**                    | **Fonction**                                   | **Complexité** | **Remplaçant Moderne**                         |
| ------------------------------------- | ---------------------------------------------- | -------------- | ---------------------------------------------- |
| `assets/js/dashboard-core.js`         | Orchestrateur principal (2000+ lignes)         | ⭐⭐⭐⭐⭐     | `src/modules/dashboard/App.vue`                |
| `assets/js/modules/booking-form.js`   | Formulaires de création/édition (1500+ lignes) | ⭐⭐⭐⭐⭐     | `src/components/dashboard/BookingForm.vue`     |
| `assets/js/modules/messaging.js`      | Channel Manager complet (2500+ lignes)         | ⭐⭐⭐⭐⭐     | `src/components/dashboard/MessagingCenter.vue` |
| `assets/js/modules/payments.js`       | Gestion paiements Stripe                       | ⭐⭐⭐         | `src/components/dashboard/PaymentManager.vue`  |
| `assets/js/modules/documents.js`      | Génération PDF/templates                       | ⭐⭐⭐         | `src/components/dashboard/DocumentCenter.vue`  |
| `assets/js/modules/pricing-engine.js` | Moteur de calculs tarifaires                   | ⭐⭐⭐⭐       | `src/services/pricing-service.js`              |
| `assets/js/modules/utils.js`          | Utilitaires génériques                         | ⭐⭐           | `src/utils/` (déjà partiellement créé)         |
| `assets/css/dashboard-*.css`          | Styles modulaires (5 fichiers)                 | ⭐⭐           | CSS Modules dans composants Vue                |
| `shortcodes/shortcode-dashboard.php`  | Rendu et préparation données                   | ⭐⭐⭐⭐       | Contrôleur API REST + Vue Router               |

### 🆕 Moderne (Nouveau Monde)

| **Fichier Moderne**                                            | **Statut**  | **Description**                    |
| -------------------------------------------------------------- | ----------- | ---------------------------------- |
| `src/modules/dashboard/App.vue`                                | ✅ **Créé** | Point d'entrée Vue 3 (basique)     |
| `src/stores/dashboard-store.js`                                | ✅ **Créé** | Store Pinia (statistiques simples) |
| `src/components/StatCard.vue`                                  | ✅ **Créé** | Composant statistiques             |
| `includes/ajax/controllers/class-calendar-ajax-controller.php` | ✅ **Créé** | API calendrier                     |
| `includes/services/calendar/class-ical-exporter.php`           | ✅ **Créé** | Export iCal                        |

### 🚧 À Créer

| **Composant à Créer**                           | **Priorité** | **Remplace**             |
| ----------------------------------------------- | ------------ | ------------------------ |
| `src/components/dashboard/ReservationList.vue`  | 🔴 **P1**    | Tableau des réservations |
| `src/components/dashboard/ReservationModal.vue` | 🔴 **P1**    | Modale de détails        |
| `src/components/dashboard/BookingForm.vue`      | 🔴 **P1**    | `booking-form.js`        |
| `src/components/dashboard/MessagingCenter.vue`  | 🟡 **P2**    | `messaging.js`           |
| `src/components/dashboard/PaymentManager.vue`   | 🟡 **P2**    | `payments.js`            |
| `src/components/dashboard/DocumentCenter.vue`   | 🟡 **P2**    | `documents.js`           |
| `src/stores/reservations-store.js`              | 🔴 **P1**    | État des réservations    |
| `src/stores/messaging-store.js`                 | 🟡 **P2**    | État messagerie          |
| `src/services/reservation-api.js`               | 🔴 **P1**    | API réservations         |
| `src/services/pricing-service.js`               | 🔴 **P1**    | `pricing-engine.js`      |

---

## 📥 Flux de Données

### Architecture Actuelle (Legacy)

```
WordPress Shortcode → PHP Data Preparation → jQuery DOM Manipulation → AJAX Calls → Database
```

### Architecture Cible (Moderne)

```
Vue Router → Pinia Store → Axios API Client → PHP Controllers → Database
Vue Components ← Reactive State ← API Response ← JSON Response ←
```

### Points d'API Identifiés

| **Endpoint**                   | **Méthode** | **Fonction**         | **Contrôleur**                          |
| ------------------------------ | ----------- | -------------------- | --------------------------------------- |
| `pc_manual_reservation_create` | POST        | Création réservation | `class-reservation-ajax-controller.php` |
| `pc_manual_logement_config`    | POST        | Config logement      | À créer                                 |
| `pc_send_message`              | POST        | Envoi message        | `class-messaging-ajax-controller.php`   |
| `pc_get_conversation_history`  | POST        | Historique messages  | `class-messaging-ajax-controller.php`   |
| `pc_get_reservation_files`     | POST        | Documents générés    | `class-document-ajax-controller.php`    |
| `pc_cancel_reservation`        | POST        | Annulation           | À compléter                             |
| `pc_confirm_reservation`       | POST        | Confirmation         | À compléter                             |

---

## 🧱 Structure Vue 3 & Pinia Proposée

### Store Principal (Pinia)

```javascript
// src/stores/dashboard-store.js (à étendre)
export const useDashboardStore = defineStore("dashboard", {
  state: () => ({
    // Réservations
    reservations: [],
    selectedReservation: null,
    reservationFilters: {
      type: null, // 'experience' | 'location'
      status: null,
      itemId: null,
    },

    // UI State
    isLoading: false,
    currentModal: null, // 'create' | 'edit' | 'detail' | 'messaging'

    // Stats
    stats: {
      totalReservations: 0,
      totalRevenue: 0,
      pendingMessages: 0,
    },

    // Pagination
    currentPage: 1,
    totalPages: 0,
    perPage: 25,
  }),

  actions: {
    async fetchReservations() {
      /* ... */
    },
    async createReservation(payload) {
      /* ... */
    },
    async updateReservation(id, payload) {
      /* ... */
    },
    async deleteReservation(id) {
      /* ... */
    },
  },
});
```

### Stores Spécialisés

```javascript
// src/stores/reservations-store.js - État des réservations
// src/stores/messaging-store.js - État messagerie/Channel Manager
// src/stores/pricing-store.js - État moteur tarifaire
// src/stores/documents-store.js - État génération documents
```

### Structure des Composants

```
src/modules/dashboard/
├── App.vue (Point d'entrée principal)
├── DashboardLayout.vue (Layout avec header/sidebar)
├── components/
│   ├── ReservationList.vue (Tableau principal)
│   ├── ReservationFilters.vue (Filtres et recherche)
│   ├── ReservationModal.vue (Modale de détails)
│   ├── BookingForm/ (Formulaire de création/édition)
│   │   ├── BookingForm.vue
│   │   ├── ExperienceFields.vue
│   │   ├── LocationFields.vue
│   │   └── CustomerFields.vue
│   ├── MessagingCenter/ (Channel Manager)
│   │   ├── MessagingModal.vue
│   │   ├── ConversationView.vue
│   │   ├── MessageComposer.vue
│   │   └── TemplatesPanel.vue
│   ├── PaymentManager.vue (Gestion paiements)
│   └── DocumentCenter.vue (Génération documents)
```

---

## 🚀 Plan d'Action (Step-by-Step)

### Phase 1 : Infrastructure de Base (2-3 semaines)

- [ ] **1.1** Créer les stores Pinia manquants (`reservations-store.js`, `messaging-store.js`)
- [ ] **1.2** Développer `src/services/reservation-api.js` (client Axios)
- [ ] **1.3** Créer les contrôleurs PHP manquants pour l'API REST
- [ ] **1.4** Migrer `pricing-engine.js` vers `src/services/pricing-service.js`

### Phase 2 : Interface Principale (3-4 semaines)

- [ ] **2.1** Créer `ReservationList.vue` (tableau avec pagination/filtres)
- [ ] **2.2** Créer `ReservationModal.vue` (modale de détails)
- [ ] **2.3** Migrer les formulaires : `BookingForm.vue` et sous-composants
- [ ] **2.4** Intégrer le moteur tarifaire Vue 3 dans les formulaires
- [ ] **2.5** Tests des fonctionnalités CRUD de base

### Phase 3 : Messagerie (2-3 semaines)

- [ ] **3.1** Créer `MessagingCenter.vue` (Channel Manager Vue 3)
- [ ] **3.2** Composants `ConversationView.vue` et `MessageComposer.vue`
- [ ] **3.3** Système d'onglets (Chat/Email/Notes)
- [ ] **3.4** Gestion des pièces jointes et templates rapides
- [ ] **3.5** Intégration WhatsApp et notifications temps réel

### Phase 4 : Fonctionnalités Avancées (2-3 semaines)

- [ ] **4.1** `PaymentManager.vue` (liens Stripe, statuts paiements)
- [ ] **4.2** `DocumentCenter.vue` (génération PDF, templates)
- [ ] **4.3** Statistiques avancées et tableaux de bord
- [ ] **4.4** Intégration complète avec le calendrier Vue 3 existant

### Phase 5 : Migration et Optimisation (1-2 semaines)

- [ ] **5.1** Configuration du routage Vue (remplacement du shortcode)
- [ ] **5.2** Tests de régression complets
- [ ] **5.3** Optimisation des performances (lazy loading, cache)
- [ ] **5.4** **Débranchement définitif des fichiers Legacy**

---

## 🔧 Considérations Techniques

### Défis Identifiés

1. **Complexité du moteur tarifaire** - Le `pricing-engine.js` contient une logique métier très avancée avec saisons, options, remises
2. **Channel Manager riche** - Le système de messagerie est très complet (3 onglets, pièces jointes, templates)
3. **Intégrations externes** - Stripe, WhatsApp, génération PDF
4. **Volumes de données** - Pagination et performance avec de nombreuses réservations

### Solutions Proposées

1. **Migration progressive par module** - Commencer par les fonctionnalités les plus simples
2. **Dual-mode temporaire** - Faire coexister Legacy et Vue 3 pendant la transition
3. **API REST propre** - Créer des endpoints dédiés plutôt que réutiliser les AJAX WordPress
4. **Tests automatisés** - Couvrir la logique critique (tarification, paiements)

### Performance

- **Lazy Loading** des modales lourdes (Messaging, Documents)
- **Pagination** côté serveur pour les grandes listes
- **Cache Pinia** pour éviter les appels répétitifs
- **Debouncing** sur les filtres et recherches

---

## 📊 Estimation Globale

| **Phase**                          | **Durée**    | **Complexité** | **Risque**    |
| ---------------------------------- | ------------ | -------------- | ------------- |
| Phase 1 - Infrastructure           | 2-3 semaines | ⭐⭐⭐         | 🟡 Moyen      |
| Phase 2 - Interface Principale     | 3-4 semaines | ⭐⭐⭐⭐       | 🔴 Élevé      |
| Phase 3 - Messagerie               | 2-3 semaines | ⭐⭐⭐⭐⭐     | 🔴 Très Élevé |
| Phase 4 - Fonctionnalités Avancées | 2-3 semaines | ⭐⭐⭐⭐       | 🟡 Moyen      |
| Phase 5 - Migration Finale         | 1-2 semaines | ⭐⭐           | 🟢 Faible     |

**Total estimé : 10-15 semaines** pour une migration complète du système Dashboard.

---

## ⚠️ Recommandations Stratégiques

1. **Prioriser les fonctionnalités critiques** - Commencer par les CRUD de réservations avant la messagerie
2. **Conserver le PHP existant** - Les services et repositories sont bien architecturés et peuvent être réutilisés
3. **Tests en environnement de staging** - Le Dashboard est critique pour les propriétaires
4. **Formation utilisateurs** - L'interface Vue 3 sera différente de l'actuelle
5. **Rollback plan** - Pouvoir revenir au Legacy en cas de problème critique

Le système Dashboard représente le **plus gros chantier** de votre refactoring, mais aussi le plus impactant en termes d'expérience utilisateur et de maintenabilité du code.
