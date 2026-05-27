# 📊 Architecture Détaillée du WP-Content - Prestige Caraïbes

_Documentation créée le 24 mai 2026_

---

## 📋 Table des matières

1. [Vue d'ensemble](#vue-densemble)
2. [Structure racine](#structure-racine)
3. [MU-Plugins (détail complet)](#mu-plugins-détail-complet)
4. [Plugins spécialisés](#plugins-spécialisés)
5. [Autres dossiers](#autres-dossiers)
6. [Recommandations](#recommandations)

---

## Vue d'ensemble

```
wp-content/
├── MU-PLUGINS (21 éléments)         ← Plugins obligatoires + modules métier
│   ├── Fichiers PHP racine (6)
│   ├── core-modules/ (7 classes)
│   ├── Modules métier (11 dossiers)
│   └── Assets & Configuration
│
├── PLUGINS (18 plugins)             ← Plugins externes + custom
│   ├── Plugins de cache: WP-Rocket
│   ├── Plugins de sauvegarde: UpdraftPlus
│   ├── Plugins custom (3): pc-rate-manager, pc-reservation-core, pc-stripe-caution
│   └── Autres plugins (13)
│
├── LANGUAGES (77+ fichiers)         ← Traductions multilingues (fr_FR)
├── UPLOADS (9 dossiers)             ← Médias et fichiers uploadés
├── CACHE (5 dossiers)               ← Cache WP-Rocket
├── THEMES (6 thèmes)                ← Thèmes WordPress
├── UPDRAFT (30 fichiers)            ← Sauvegardes UpdraftPlus
└── Fichiers de configuration (8)
```

---

## Structure racine

### Fichiers principaux

| Fichier                          | Taille | Description     | Fonction                        |
| -------------------------------- | ------ | --------------- | ------------------------------- |
| `index.php`                      | 28 B   | Fichier d'accès | Sécurité (empêche accès direct) |
| `debug.log`                      | 405 KB | Logs débogage   | À nettoyer régulièrement        |
| `advanced-cache.php`             | 0 B    | Hook cache      | Utilisé par WP-Rocket           |
| `@structure-wp-content.md`       | 41 KB  | Documentation   | Structure antérieure            |
| `rapport-migration.md`           | 5.3 KB | Documentation   | Rapport de migration            |
| `plan-migration-destinations.md` | 22 KB  | Documentation   | Plan de migration               |

---

## 🔧 MU-PLUGINS (Détail Complet)

Le dossier mu-plugins contient 21 éléments : des plugins must-use (toujours actifs) et des modules métier personnalisés.

### 📄 Fichiers PHP racine (6 fichiers)

```
mu-plugins/
├── hostinger-preview-domain.php          (14 KB)  - Gestion domaine preview Hostinger
├── mu-global-prestige-caraibesV2_3.php   (2.1 KB) - Configuration globale Prestige
├── pc-acf.php                            (872 B)  - Intégration ACF (Advanced Custom Fields)
├── pc-custom-typesV3.php                 (8.5 KB) - Types personnalisés (CPT/Taxonomies)
├── pc-modules-loader.php                 (1.3 KB) - Chargeur de modules
└── pc-utils-loader.php                   (907 B)  - Chargeur utilitaires
```

### 📁 Dossier: core-modules/ (Classes de base système)

```
core-modules/
├── class-pc-admin-metaboxes.php      - Métaboxes personnalisées admin
├── class-pc-assets.php                - Gestion des assets (CSS/JS)
├── class-pc-jsonld-manager.php        - Gestion schema JSON-LD (SEO)
├── class-pc-performance.php           - Optimisations de performance
├── class-pc-seo-helpers.php          - Helpers SEO
├── class-pc-seo-manager.php          - Gestionnaire SEO principal
└── class-pc-social-manager.php       - Gestion réseaux sociaux
```

### 📁 Dossier: assets/ (Ressources système)

```
assets/
├── js/                           - Fichiers JavaScript
│   ├── [scripts de base]
│   └── ...
├── src/                          - Fichiers sources
└── pc-orchestrator.js            - Orchestrateur principal JS
```

### 📁 Dossier: pc-acf-json/ (Configuration ACF)

```
pc-acf-json/
├── acf-export-2025-09-09.json    - Export ACF du 9 sept 2025
├── group_66dcc7e9c5a16.json      - Groupe ACF 1
├── group_68d50d744fe8b.json      - Groupe ACF 2
├── group_69121c9f90922.json      - Groupe ACF 3
├── group_pc-pages-seo-structure.json  - Structure SEO pages
├── group_pc_destination.json     - Champs destinations
├── group_pc_fiche_logement.json  - Champs fiches logement
├── group_pc_reviews.json         - Champs avis/reviews
├── group_pc_seo_global.json      - Configuration SEO globale
└── ui_options_page_69121da6846af.json - Options page UI
```

### 📁 MODULES MÉTIER (11 dossiers)

#### 1️⃣ **pc-destination/** - Gestion des destinations

```
pc-destination/
├── pc-destination-core.php           - Fichier principal du module
├── assets/                           - Ressources (CSS/JS)
│   ├── css/
│   ├── js/
│   └── images/
├── helpers/                          - Fonctions utilitaires
│   ├── [helpers spécifiques]
│   └── ...
├── schema/                           - Schémas JSON-LD pour destinations
│   └── [schemas]
└── shortcodes/                       - Shortcodes pour destinations (5+ shortcodes)
    ├── [shortcode-1.php]
    ├── [shortcode-2.php]
    └── ...
```

**Rôle**: Gestion complète du contenu destinations (fiches, taxonomies, affichage)

#### 2️⃣ **pc-experiences/** - Gestion des expériences

```
pc-experiences/
├── pc-experiences-core.php           - Fichier principal du module
├── assets/                           - Ressources (CSS/JS)
│   └── [stylesheets, scripts]
├── booking/                          - Logique de réservation expériences
│   └── [booking classes]
├── helpers/                          - Fonctions utilitaires
│   └── [helpers spécifiques]
└── shortcodes/                       - Shortcodes expériences (15+ shortcodes)
    ├── [shortcode-1.php]
    ├── [shortcode-2.php]
    └── ...
```

**Rôle**: Gestion complète des expériences, réservations, affichages

#### 3️⃣ **pc-faq/** - Gestion FAQ

```
pc-faq/
├── pc-faq-core.php                   - Fichier principal du module
├── assets/                           - Ressources (CSS/JS)
├── helpers/                          - Fonctions utilitaires
└── shortcodes/                       - Shortcodes FAQ (7+ shortcodes)
    ├── [shortcode-1.php]
    ├── [shortcode-2.php]
    └── ...
```

**Rôle**: Gestion des questions fréquemment posées

#### 4️⃣ **pc-header/** - Gestion en-tête du site

```
pc-header/
├── pc-header-core.php                - Fichier principal du module
├── api/                              - Points d'accès API
│   ├── [api routes]
│   └── ...
├── assets/                           - Ressources header (CSS/JS)
├── config/                           - Configuration header
│   ├── [config files]
│   └── ...
├── helpers/                          - Fonctions utilitaires
├── shortcodes/                       - Shortcodes header (4+ shortcodes)
    └── ...
```

**Rôle**: Gestion complète de l'en-tête du site (navigation, logo, etc.)

#### 5️⃣ **pc-logement/** - Gestion des logements/hébergements

```
pc-logement/
├── pc-logement-core.php              - Fichier principal du module
├── assets/                           - Ressources (CSS/JS)
├── booking/                          - Logique de réservation logements
│   └── [booking classes]
├── helpers/                          - Fonctions utilitaires
└── shortcodes/                       - Shortcodes logements (21+ shortcodes)
    ├── [shortcode-1.php]
    ├── [shortcode-2.php]
    ├── [shortcode-calendrier.php]
    ├── [shortcode-galerie.php]
    └── ...
```

**Rôle**: Gestion complète des fiches logement, calendriers, réservations

#### 6️⃣ **pc-recherche/** - Moteur de recherche

```
pc-recherche/
├── pc-recherche-core.php             - Fichier principal du module
├── ajax/                             - Endpoints AJAX
│   ├── [ajax handlers]
│   └── ...
├── assets/                           - Ressources (CSS/JS)
├── engines/                          - Moteurs de recherche (5 engines)
│   ├── [engine-1.php]
│   ├── [engine-2.php]
│   └── ...
├── helpers/                          - Fonctions utilitaires
└── shortcodes/                       - Shortcodes recherche (6+ shortcodes)
    ├── [shortcode-1.php]
    ├── [shortcode-2.php]
    └── ...
```

**Rôle**: Moteur de recherche avancé (filtres, critères, résultats)

#### 7️⃣ **pc-reviews/** - Système d'avis

```
pc-reviews/
├── pc-reviews.php                    - Fichier principal (17 KB)
└── assets/                           - Ressources (CSS/JS)
    ├── css/
    ├── js/
    └── images/
```

**Rôle**: Gestion des avis clients, évaluations, critiques

#### 8️⃣ **pc-ui-components/** - Composants UI réutilisables

```
pc-ui-components/
├── pc-ui-components-core.php         - Fichier principal du module
├── assets/                           - Ressources (CSS/JS)
│   └── [stylesheets, scripts UI]
├── helpers/                          - Fonctions utilitaires UI
├── shortcodes/                       - Shortcodes UI (4+ shortcodes)
    ├── [button.php]
    ├── [card.php]
    ├── [modal.php]
    └── ...
```

**Rôle**: Composants UI réutilisables à travers le site

#### 9️⃣ **pc-utils/** - Utilitaires métier

```
pc-utils/
├── pc-fallback-bientot-disponible.php  (2.3 KB) - Page "bientôt disponible"
├── pc-maintenance.php                   (10.3 KB) - Mode maintenance
└── pc-sandbox-menu-prefix.php           (1.2 KB) - Préfixe menu sandbox
```

**Rôle**: Utilitaires métier spécifiques (fallback, maintenance, etc.)

#### 🔟 **pc-cache/** - Gestion cache personnalisée

```
pc-cache/
├── pc-cache-core.php                 - Fichier principal du module
├── handlers/                         - Gestionnaires cache
│   ├── [handler-1.php]
│   └── ...
├── helpers/                          - Fonctions utilitaires cache
├── providers/                        - Fournisseurs cache
    ├── [provider-1.php]
    └── ...
```

**Rôle**: Gestion personnalisée du cache en complément WP-Rocket

#### 1️⃣1️⃣ **pc-performance/** - Optimisations performance

```
pc-performance/
├── pc-performance-core.php           - Fichier principal du module
├── config/                           - Configuration performance
│   ├── [config files]
│   └── ...
├── helpers/                          - Fonctions utilitaires
├── managers/                         - Gestionnaires performance (6 managers)
    ├── [manager-1.php]
    ├── [manager-2.php]
    └── ...
```

**Rôle**: Optimisations de performance avancées

---

## 🎯 PLUGINS SPÉCIALISÉS (3 plugins custom)

### 1️⃣ **pc-rate-manager** (Gestionnaire de tarifs)

```
plugins/pc-rate-manager/
├── pc-rate-manager.php               - Fichier principal du plugin
├── assets/
│   ├── css/
│   │   └── style.css                 - Styles du plugin
│   └── js/
│       └── app.js                    - Scripts du plugin
├── audit-rate-manager.md             - Audit du système de tarifs
└── .DS_Store
```

**Fonctionnalités principales**:

- Gestion des tarifs (création, modification, suppression)
- Saisonnalité des tarifs
- Calcul dynamique des prix
- Interface admin personnalisée
- Intégration avec le système de réservation

**Taille**: Léger (~1-2 MB avec assets)

---

### 2️⃣ **pc-reservation-core** (Cœur système de réservation) ⭐ MAJEUR

```
plugins/pc-reservation-core/
├── 📄 Fichiers de configuration
│   ├── pc-reservation-core.php       - Fichier principal du plugin (22 KB)
│   ├── composer.json                 - Configuration Composer
│   ├── composer.lock                 - Dépendances Composer
│   ├── composer.phar                 - Outil Composer (3.1 MB)
│   ├── composer-setup.php            - Script setup Composer (58 KB)
│   ├── package.json                  - Configuration NPM
│   ├── package-lock.json             - Dépendances NPM (113 KB)
│   ├── vite.config.js                - Configuration Vite
│   ├── @structure.md                 - Documentation structure (7.2 KB)
│   ├── @architecture-refactoring.md  - Refactoring architecture (12 KB)
│   ├── analyse-paiements.md          - Analyse système paiements (9 KB)
│   ├── rapport-analyse-prefillage.md - Rapport prefillage (18 KB)
│   ├── rapport-refonte-acf.md        - Rapport ACF refonte (10 KB)
│   └── uninstall.php                 - Désinstallation du plugin
│
├── 📂 api/ (Points d'accès API)
│   ├── [routes API REST]
│   └── [webhooks]
│
├── 📂 db/ (Gestion base de données)
│   ├── [migrations]
│   └── [tables personnalisées]
│
├── 📂 includes/ (Code métier - 25 fichiers)
│   ├── acf-fields.php               - Définition des champs ACF (25.6 KB)
│   ├── class-booking-engine.php     - Moteur de réservation
│   ├── class-documents.php          - Gestion des documents (7.6 KB)
│   ├── class-elementor-pcr-tags.php - Tags Elementor personnalisés (3 KB)
│   ├── class-experience-manager.php - Gestionnaire expériences (2 KB)
│   ├── class-housing-manager.php    - Gestionnaire logements (1.9 KB)
│   ├── class-ical-export.php        - Export iCal (1.8 KB)
│   ├── class-messaging.php          - Système messaging (2.2 KB)
│   ├── class-payment.php            - Gestion paiements (1.3 KB)
│   ├── class-reservation.php        - Classe réservation (3.8 KB)
│   ├── class-settings.php           - Paramètres du plugin (1.7 KB)
│   ├── class-vite-loader.php        - Loader Vite (2.3 KB)
│   ├── controller-forms.php         - Contrôleur formulaires (9.7 KB)
│   │
│   ├── 📂 ajax/ (Endpoints AJAX)
│   │   ├── [handlers AJAX]
│   │   └── ...
│   │
│   ├── 📂 api/ (Routes API REST)
│   │   ├── [endpoints API]
│   │   └── ...
│   │
│   ├── 📂 fields/ (Champs personnalisés)
│   │   ├── [classes fields]
│   │   └── ...
│   │
│   ├── 📂 gateways/ (Passerelles paiement - 5 gateways)
│   │   ├── [gateway-stripe.php]
│   │   ├── [gateway-paypal.php]
│   │   ├── [gateway-virement.php]
│   │   ├── [gateway-carte.php]
│   │   └── [gateway-autre.php]
│   │
│   ├── 📂 services/ (Services métier - 13 services)
│   │   ├── [service-1.php]
│   │   ├── [service-2.php]
│   │   ├── [service-booking.php]
│   │   ├── [service-calendar.php]
│   │   ├── [service-payment.php]
│   │   └── ... (13 services)
│   │
│   ├── 📂 partials/ (Vues partielles)
│   │   ├── [partial-1.php]
│   │   └── ...
│   │
│   ├── migration-destination.php    - Migration destinations (3.3 KB)
│   ├── migration-experience.php     - Migration expériences (3.3 KB)
│   └── migration-logements.php      - Migration logements (4.6 KB)
│
├── 📂 shortcodes/ (Shortcodes réservation)
│   ├── [booking-form.php]
│   ├── [calendar.php]
│   ├── [summary.php]
│   └── ...
│
├── 📂 templates/ (Templates HTML)
│   ├── [template-1.php]
│   ├── [template-2.php]
│   └── ...
│
├── 📂 src/ (Code source Vue/JS - 7 dossiers)
│   ├── components/      - Composants Vue
│   ├── views/           - Vues principales
│   ├── stores/          - Stores Pinia/Vuex
│   ├── api/             - Clients API
│   ├── utils/           - Utilitaires JS
│   ├── styles/          - Stylesheets
│   └── main.js          - Point d'entrée
│
├── 📂 dist/ (Fichiers compilés)
│   ├── assets/          - Assets compilés (JS, CSS, images)
│   ├── .vite/
│   │   └── manifest.json - Manifest Vite
│   └── [HTML générés]
│
├── 📂 assets/ (Ressources statiques)
│   ├── css/
│   ├── js/
│   ├── images/
│   └── fonts/
│
├── 📂 vendor/ (Dépendances Composer)
│   ├── [packages PHP]
│   └── ...
│
└── 📂 node_modules/ (Dépendances NPM - 163 dossiers)
    ├── vue/             - Framework Vue.js
    ├── vite/            - Build tool Vite
    ├── [autres packages]
    └── ...
```

**Caractéristiques techniques**:

- Framework frontend: **Vue.js** (src/)
- Build tool: **Vite** (compilation optimisée)
- Gestion state: **Pinia/Vuex**
- Gestionnaire dépendances: **Composer + NPM**
- Base de données: Tables personnalisées + ACF
- API REST complète
- Support multi-gateway paiement

**Taille totale**: ~50 MB (dont 40 MB node_modules + vendor)

**Services principaux** (13):

1. Service de réservation
2. Service calendrier
3. Service paiement
4. Service notification
5. Service validation
6. Service tarification
7. Service utilisateur
8. Service document
9. Service export
10. Service sync
11. Service cache
12. Service logging
13. Service analytics

---

### 3️⃣ **pc-stripe-caution** (Gestion caution Stripe) ⭐

```
plugins/pc-stripe-caution/
├── 📄 Fichiers de configuration
│   ├── pc-stripe-caution.php        - Fichier principal du plugin
│   └── uninstall.php                - Désinstallation
│
├── 📂 admin/ (Interface administrateur)
│   ├── class-pcsc-admin.php         - Page admin principale
│   ├── class-pcsc-settings.php      - Page paramètres
│   └── class-pcsc-help.php          - Page aide
│
├── 📂 includes/ (Code métier)
│   ├── class-pcsc-db.php            - Gestion base de données
│   │   ├── Tables personnalisées
│   │   ├── Migrations
│   │   └── Requêtes SQL
│   │
│   ├── class-pcsc-stripe.php        - Intégration Stripe (API)
│   │   ├── Création charges
│   │   ├── Gestion paiements
│   │   ├── Gestion remboursements
│   │   └── Webhooks
│   │
│   ├── class-pcsc-webhooks.php      - Gestion webhooks Stripe
│   │   ├── Événements paiement
│   │   ├── Événements remboursement
│   │   └── Événements charge
│   │
│   └── class-pcsc-mailer.php        - Système email
│       ├── Notifications paiement
│       ├── Notifications remboursement
│       └── Notifications administrateur
│
├── 📂 public/ (Partie publique)
│   └── class-pcsc-public.php        - Logique frontend
│       ├── Affichage form
│       ├── Validation
│       └── Traitement
│
├── 📂 pro/ (Features Pro)
│   ├── class-pcsc-pro-loader.php    - Loader des features pro
│   ├── class-pcsc-pro-dashboard.php - Dashboard avancé
│   └── class-pcsc-pro-cron.php      - Cron jobs pro
│
├── 📂 languages/ (Traductions)
│   ├── pc-stripe-caution.pot        - Template traduction
│   ├── pc-stripe-caution-fr_FR.po   - Source français
│   ├── pc-stripe-caution-fr_FR.mo   - Compilé français
│   └── pc-stripe-caution-fr_FR.l10n.php - Localization PHP
│
└── .DS_Store
```

**Fonctionnalités principales**:

- Intégration Stripe complète (API v3)
- Gestion des cautions (charges, remboursements)
- System webhooks Stripe
- Emails automatiques
- Dashboard admin
- Features Pro (cron, notifications avancées)
- Support multilingue (français)

**Taille**: ~500 KB

---

## 📁 AUTRES DOSSIERS

### 🌍 languages/ (77+ fichiers)

```
languages/
├── 📄 Core WordPress (6 fichiers)
│   ├── admin-fr_FR.l10n.php         - Traduction admin WordPress
│   ├── admin-fr_FR.mo               - Compilé (571 KB)
│   ├── admin-fr_FR.po               - Source (804 KB)
│   ├── admin-network-fr_FR.l10n.php - Traduction multisite
│   ├── admin-network-fr_FR.mo       - Compilé (51 KB)
│   └── admin-network-fr_FR.po       - Source (68 KB)
│
├── 📄 Continents/Villes (3 fichiers)
│   ├── continents-cities-fr_FR.l10n.php
│   ├── continents-cities-fr_FR.mo   - Compilé (21 KB)
│   └── continents-cities-fr_FR.po   - Source (43 KB)
│
└── 📄 Fichiers JSON (70+ fichiers)
    ├── fr_FR-0cc31205f20441b3df1d1b46100f6b8d.json
    ├── fr_FR-0ce75ad2f775d1cac9696967d484808c.json
    ├── fr_FR-0eebe503220d4a00341eb011b92769b4.json
    ├── ... (70+ fichiers de traduction JSON)
    └── [Traductions JSON pour plugins/thèmes]
```

### 📸 uploads/ (9 dossiers)

Contient tous les médias uploadés (images, documents, etc.)

### ⚡ cache/ (Cache WP-Rocket)

```
cache/
├── background-css/     - CSS de fond en cache
├── busting/           - Cache busting
├── critical-css/      - CSS critique
├── min/               - Fichiers minifiés
└── wp-rocket/         - Cache général
```

### 🎨 themes/ (6 thèmes)

Thèmes WordPress actifs et inactifs

### 💾 updraft/ (Sauvegardes)

Sauvegardes complètes UpdraftPlus (30 fichiers)

### 🔌 AUTRES PLUGINS

| Plugin                         | Type          | Rôle                           |
| ------------------------------ | ------------- | ------------------------------ |
| **WP-Rocket**                  | Cache         | Optimisation cache/performance |
| **UpdraftPlus**                | Backup        | Sauvegardes automatiques       |
| **Elementor Pro**              | Page Builder  | Éditeur pages visuel           |
| **Advanced Custom Fields Pro** | Custom Fields | Champs personnalisés avancés   |
| **Imagify**                    | Optimization  | Optimisation images            |
| **Better Search Replace**      | Util          | Recherche/remplacement BD      |
| **Loco Translate**             | Translation   | Gestion traductions            |
| **Broken Link Checker**        | SEO           | Vérification liens cassés      |
| **Redirection**                | Util          | Gestion redirections           |
| **FileIrd**                    | Media         | Organisation médias            |
| **Hostinger**                  | Util          | Outils Hostinger               |
| **Temporary Login**            | Security      | Connexion temporaire           |
| **WP Mail Logging**            | Debug         | Logging emails                 |

---

## 🏗️ Architecture générale du système

```
┌─────────────────────────────────────────────────────────────────┐
│                    WORDPRESS PRESTIGE CARAÏBES                   │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │              MU-PLUGINS (Cœur métier)                        │ │
│  │  ┌──────────────────────────────────────────────────────┐  │ │
│  │  │  Core Modules  │ Modules Métier  │ Assets & Config  │  │ │
│  │  │  (SEO, Assets, │ (Destination,   │ (ACF, Custom     │  │ │
│  │  │  Performance,  │ Experiences,    │ Types, Utils)    │  │ │
│  │  │  Social)       │ Logement, etc.)  │                  │  │ │
│  │  └──────────────────────────────────────────────────────┘  │ │
│  └─────────────────────────────────────────────────────────────┘ │
│                                                                   │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │              PLUGINS CUSTOM (Réservation & Paiement)         │ │
│  │  ┌──────────────────────────────────────────────────────┐  │ │
│  │  │  Rate Manager │ Reservation Core │ Stripe Caution   │  │ │
│  │  │  (Tarifs)     │ (Réservation)    │ (Paiement)       │  │ │
│  │  └──────────────────────────────────────────────────────┘  │ │
│  └─────────────────────────────────────────────────────────────┘ │
│                                                                   │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │              PLUGINS TIERS (Support)                        │ │
│  │  WP-Rocket │ UpdraftPlus │ ACF Pro │ Elementor Pro │ etc.  │ │
│  └─────────────────────────────────────────────────────────────┘ │
│                                                                   │
│  ┌──────────────┬──────────────┬────────────────┬─────────────┐ │
│  │ LANGUAGES    │ UPLOADS      │ CACHE          │ THEMES      │ │
│  │ (77+ fr_FR)  │ (9 dossiers) │ (WP-Rocket)    │ (6 thèmes)  │ │
│  └──────────────┴──────────────┴────────────────┴─────────────┘ │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
```

---

## 📊 Statistiques

| Catégorie        | Nombre       | Taille      |
| ---------------- | ------------ | ----------- |
| **MU-Plugins**   | 21 éléments  | ~10-15 MB   |
| **Plugins**      | 18 plugins   | ~150-200 MB |
| **Languages**    | 77+ fichiers | ~2-3 MB     |
| **Uploads**      | Plusieurs GB | Variable    |
| **Thèmes**       | 6 thèmes     | ~20-30 MB   |
| **Sauvegardes**  | 30 fichiers  | Variable    |
| **Cache**        | 5 dossiers   | ~100-200 MB |
| **Total estimé** | -            | **2-5 GB**  |

---

## ✅ Recommandations de maintenance

### 🧹 Nettoyage régulier

- Supprimer `debug.log` chaque mois
- Vider cache `cache/` après déploiements
- Archiver anciennes sauvegardes `updraft/`
- Nettoyer fichiers temporaires `upgrade/`

### 🔐 Sécurité

- Vérifier permissions dossiers (755 dossiers, 644 fichiers)
- Auditer pc-stripe-caution pour sécurité Stripe
- Vérifier logs pour erreurs/tentatives accès

### 📈 Performance

- Monitorer taille cache (limite à 500 MB idéalement)
- Vérifier poids uploads (limiter à 100 MB par fichier)
- Auditer services pc-reservation-core

### 🔄 Mises à jour

- Mettre à jour dépendances npm de pc-reservation-core régulièrement
- Vérifier compatibilité plugins avec nouvelles versions WordPress
- Tester après chaque mise à jour

### 📚 Documentation

- Maintenir fichiers @structure.md et @architecture-refactoring.md
- Documenter modifications sur modules métier
- Keeper traces migrations (migration-\*.php)

---

## 🔗 Flux de données principaux

```
1. RÉSERVATION
   Utilisateur → pc-logement/shortcodes → pc-reservation-core (booking)
   → Paiement → pc-stripe-caution → Confirmation

2. TARIFICATION
   Admin → pc-rate-manager (admin) → ACF → pc-reservation-core
   → Calcul dynamique → Affichage frontend

3. RECHERCHE
   Utilisateur → pc-recherche (shortcode) → AJAX → Moteur recherche
   → Résultats (logement/experience/destination)

4. SEO
   Contenu → pc-destination/experience/logement → Core modules
   → JSON-LD → Affichage Meta/OG
```

---

_Documentation créée le 24 mai 2026 - Mise à jour recommandée trimestriellement_
