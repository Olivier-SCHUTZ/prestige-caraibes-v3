# Architecture - Mu-Plugins Prestige Caraïbes

> Documentation de référence générée par reverse engineering  
> **Date :** 29/01/2026  
> **Version analysée :** V2.3+

---

## 📂 Arborescence complète du projet

```
mu-plugins/
├── .DS_Store
├── @architecture.md                           # Ce fichier
├── hostinger-preview-domain.php               # Plugin Hostinger
├── mu-global-prestige-caraibesV2_3.php       # 🔥 Moteur SEO principal
├── pc-acf.php                                 # Configuration ACF Local JSON
├── pc-ajax-search.php                         # Recherche AJAX
├── pc-assets-registry.php.off                # Registre assets (désactivé)
├── pc-base.css                                # 🎨 Fondations CSS globales
├── pc-custom-typesV3.php                     # 📋 CPT & Taxonomies
├── pc-destination-shortcodes.php             # Shortcodes destinations
├── pc-experience-search.php                  # Recherche expériences
├── pc-fallback-bientot-disponible.php        # Page fallback
├── pc-faq-capture.php                         # Capture FAQ
├── pc-header-global.php                      # 🔧 Header unifié
├── pc-header-loader.off                       # Loader header (désactivé)
├── pc-ical-cache.php                          # Cache iCal
├── pc-loader.php                              # Loader environnement
├── pc-loop-components.php                     # Composants de boucle
├── pc-maintenance.php                         # Mode maintenance
├── pc-perf-hints.php                          # Optimisations performances
├── pc-reviews.php                             # Système d'avis
├── pc-sandbox-menu-prefix.php                # Menu sandbox
├── pc-search-shortcodes.php                  # Shortcodes recherche
├── shortcode-liste-logement.php              # Liste logements
├── shortcode-page-fiche-experiences.php      # Fiche expérience
├── shortcode-page-fiche-logement.php         # Fiche logement
├── assets/                                    # 🎨 Assets frontend
│   ├── experience-search.css
│   ├── pc-destination.css
│   ├── pc-devis.js                           # Calculateur de devis
│   ├── pc-experience-search.js
│   ├── pc-faq-capture.css
│   ├── pc-fiche-experiences.js
│   ├── pc-fiche-logement.js
│   ├── pc-gallerie.js
│   ├── pc-header-global.css                 # Styles header
│   ├── pc-header-global.js                  # JavaScript header
│   ├── pc-header-smart.js                   # Header intelligent
│   ├── pc-header.off                        # Header legacy (off)
│   ├── pc-loop-card.css                     # Styles cartes
│   ├── pc-orchestrator.js                   # 🎼 Orchestrateur JS
│   ├── pc-search-shortcodes.css
│   ├── pc-search-shortcodes.js
│   ├── pc-ui-experiences.css
│   ├── pc-ui.css                            # 🎨 UI Kit principal
│   ├── shortcode-liste-logement-v2.js
│   └── shortcode-liste-logement.css
└── pc-acf-json/                              # 📋 Champs ACF
    ├── acf-export-2025-09-09.json
    ├── group_66dcc7e9c5a16.json
    ├── group_68d50d744fe8b.json
    ├── group_69121c9f90922.json
    ├── group_pc_destination.json
    ├── group_pc_fiche_logement.json
    ├── group_pc_reviews.json
    ├── group_pc_seo_global.json
    ├── group_pc-pages-seo-structure.json
    └── ui_options_page_69121da6846af.json
```

---

## 📋 Description des composants

### 🔥 Fichiers Principaux (Core)

#### `mu-global-prestige-caraibesV2_3.php` - **Moteur SEO Principal**

- **Rôle :** Cœur du système SEO dynamique
- **Fonctionnalités :**
  - Moteur SEO 100% dynamique (sitemaps, robots.txt, meta robots)
  - JSON-LD Schema.org (VacationRental, Product, Article, FAQ, Organization)
  - Canonical Guard avec gestion pagination Elementor
  - Social Cards (Open Graph, Twitter)
  - HTML Sitemap avec ItemList JSON-LD
  - Gestion 404/410 avec templates dédiés
  - Audit SEO intégré avec export CSV
  - Optimisations performances (suppression CSS Gutenberg)
- **Namespace :** Global, fonctions préfixées `pcseo_*`
- **Hooks :** Intégration wp_head, wp_footer, template_redirect
- **Configuration :** Multi-CPT (villa, appartement, experience, destination, page, post)

#### `pc-custom-typesV3.php` - **Types de Contenu**

- **Rôle :** Déclaration des CPT et taxonomies
- **Types créés :**
  - `villa` (menu principal "Logements")
  - `appartement` (sous-menu de villa)
  - `experience` (menu indépendant)
  - `destination` (menu indépendant)
- **Taxonomies :**
  - `categorie_logement` (villa/appartement)
  - `categorie_experience` (experience)
- **Configuration :** Supports complets, REST API, archives configurables

#### `pc-header-global.php` - **Header Unifié**

- **Rôle :** Système de header responsive complet
- **Fonctionnalités :**
  - Méga-menu desktop avec ARIA
  - Off-canvas mobile avec focus trap
  - Recherche unifiée (suggest REST API)
  - Gestion des menus WordPress (principal + services)
  - Social links configurables
  - Logo et branding centralisé
- **Shortcode :** `[pc_header_global]`
- **API REST :** `/wp-json/pc/v1/search-suggest`
- **Assets :** CSS + JS avec localisation

#### `pc-acf.php` - **Configuration ACF**

- **Rôle :** Gestion centralisée des champs ACF
- **Fonctionnalités :**
  - Local JSON dans `/mu-plugins/pc-acf-json/`
  - Évite les doublons avec le thème
  - Chargement optimisé des groupes de champs
- **Structure :** 10 groupes de champs définis

### 🎨 Assets Frontend

#### `pc-orchestrator.js` - **Orchestrateur JavaScript**

- **Rôle :** Coordination des modules JS
- **Fonctionnalités :**
  - Gestion centralisée de Flatpickr (calendrier)
  - Coordination devis + logements
  - Attente des dépendances externes
  - Pattern d'initialisation unifié
- **API :** `window.PCOrchestrator`

#### `pc-ui.css` - **UI Kit Principal**

- **Rôle :** Système de design complet
- **Composants :**
  - Forms & formulaires de réservation
  - Points forts (`[pc_highlights]`)
  - Calculateur de devis (`[pc_devis]`)
  - Système d'avis (`[pc_reviews]`)
  - Proximités (`[pc_proximites]`)
  - Cartes et galeries
  - Calendriers iCal
  - Tables de tarifs
  - Modales et bottom-sheets
  - Boutons flottants (FAB)
- **Variables CSS :** Intégré avec `pc-base.css`
- **Responsive :** Mobile-first avec breakpoints

#### `pc-header-global.js` - **Header Interactif**

- **Rôle :** Interactions du header responsive
- **Fonctionnalités :**
  - Méga-menus desktop (hover + click)
  - Off-canvas mobile avec accordéons
  - Recherche en temps réel (debounced)
  - Navigation clavier complète (ARIA)
  - Focus trap et gestion ESC
- **Dépendances :** API REST pour suggestions

### 🔧 Modules Spécialisés

#### `pc-reviews.php` - **Système d'Avis**

- **Rôle :** Gestion complète des avis clients
- **Fonctionnalités :**
  - CPT `pc_review` avec métadonnées
  - Sources multiples (internal, booking, airbnb)
  - Affichage par shortcode avec pagination
  - Formulaire de soumission AJAX
  - Intégration JSON-LD Schema.org
- **Shortcodes :** `[pc_reviews]`, `[pc_reviews_form]`

#### `pc-ical-cache.php` - **Cache iCal**

- **Rôle :** Gestion des calendriers de disponibilité
- **Fonctionnalités :**
  - Synchronisation automatique iCal
  - Cache optimisé avec transients
  - Intégration Flatpickr pour affichage
  - Gestion des erreurs et timeouts

#### Shortcodes Spécialisés

- `shortcode-liste-logement.php` : Grilles de logements filtrables
- `shortcode-page-fiche-logement.php` : Pages détail logement
- `shortcode-page-fiche-experiences.php` : Pages détail expérience
- `pc-destination-shortcodes.php` : Hub et grilles destinations
- `pc-search-shortcodes.php` : Formulaires de recherche

### 📋 Configuration ACF

#### Groupes de Champs Principaux

- **Logements :** `group_pc_fiche_logement.json`
- **Destinations :** `group_pc_destination.json`
- **SEO Global :** `group_pc_seo_global.json`
- **Avis :** `group_pc_reviews.json`
- **Structure SEO Pages :** `group_pc-pages-seo-structure.json`
- **Options UI :** `ui_options_page_69121da6846af.json`

---

## 🔍 Audit de Conformité (Gap Analysis)

### ✅ Points Forts

#### **Conformité PHP 8.0+**

- ✅ Syntaxe moderne utilisée (arrow functions, null coalescing)
- ✅ Fonctions PHP 8+ présentes (`str_contains`, etc.)
- ✅ Gestion d'erreurs avec try/catch appropriée

#### **Programmation Orientée Objet**

- ⚠️ **PARTIEL** : Mélange de procédural et OOP
- ✅ Utilisation d'objets WordPress (`WP_Query`, `WP_REST_Request`)
- ✅ Namespaces absents mais préfixage strict (`pc_`, `pcseo_`)

#### **Sécurité WordPress**

- ✅ Sanitisation systématique (`sanitize_text_field`, `esc_url`, `esc_html`)
- ✅ Nonces présents pour les formulaires AJAX
- ✅ Capabilities checking (`manage_options`, `manage_categories`)
- ✅ Validation des entrées utilisateur
- ✅ Protection ABSPATH sur tous les fichiers

#### **Performance & Qualité**

- ✅ Chargement conditionnel des assets
- ✅ Transients pour le cache
- ✅ Lazy loading des composants JS
- ✅ CSS critique intégré
- ✅ Versioning automatique des assets (filemtime)

#### **SEO & Accessibilité**

- ✅ Schema.org JSON-LD complet et valide
- ✅ ARIA labels et navigation clavier
- ✅ Meta robots et canonical tags avancés
- ✅ HTML sémantique respecté
- ✅ Focus trap et gestion des modales

### ❌ Points d'Amélioration

#### **Architecture Générale**

- ❌ **Code procédural dominant** : Manque de classes et d'organisation OOP
- ❌ **Fichiers volumineux** : `mu-global-prestige-caraibesV2_3.php` fait 2000+ lignes
- ❌ **Séparation des responsabilités** : Mélange SEO + UI + fonctionnalités
- ❌ **Pas de typage strict** : Absence de déclarations de types PHP 8+

#### **Standards de Code**

- ❌ **Pas de PSR-4** : Autoloading et namespaces absents
- ❌ **Documentation limitée** : DocBlocks incomplets ou absents
- ❌ **Tests unitaires** : Aucun test automatisé présent
- ❌ **Lint/Formatage** : Styles de code inconsistants

#### **Gestion d'Erreurs**

- ⚠️ **Logging** : Utilisation sporadique d'`error_log`
- ❌ **Exceptions** : Peu d'utilisation des exceptions PHP modernes
- ❌ **Debug WordPress** : Pas d'intégration avec `WP_DEBUG`

#### **Maintenance & Évolutivité**

- ❌ **Configuration externalisée** : Beaucoup de valeurs hardcodées
- ❌ **Hooks personnalisés** : Peu de filtres/actions pour extensibilité
- ❌ **Versionning du code** : Pas de gestion des migrations

### 🎯 Recommandations Prioritaires

#### **Court terme (1-2 sprints)**

1. **Refactoring modulaire** : Séparer le moteur SEO en classes distinctes
2. **Typage PHP 8+** : Ajouter les déclarations de types sur les fonctions publiques
3. **Documentation** : Compléter les DocBlocks des fonctions principales
4. **Configuration** : Externaliser les constantes et options hardcodées

#### **Moyen terme (3-6 mois)**

1. **Architecture OOP** : Migrer vers un système de classes avec namespaces
2. **Tests automatisés** : Implémenter PHPUnit pour les fonctions critiques
3. **Performance** : Audit approfondi avec profiling des requêtes
4. **CI/CD** : Intégration continue avec linting automatique

#### **Long terme (6-12 mois)**

1. **Framework Pattern** : Migration vers une architecture MVC légère
2. **API REST complète** : Étendre les endpoints pour une SPA future
3. **Microservices** : Séparer SEO, recherche, et avis en modules indépendants
4. **Monitoring** : Observabilité et métriques de performance

---

## 📊 Métriques Techniques

- **Lignes de code PHP :** ~8,000 lignes
- **Lignes de code CSS :** ~3,000 lignes
- **Lignes de code JS :** ~2,000 lignes
- **Nombre de shortcodes :** 15+
- **Endpoints REST API :** 3
- **Composants UI :** 20+
- **Champs ACF :** 100+ champs
- **Types de contenu :** 4 CPT + 2 taxonomies

---

## 🔗 Dépendances Externes

- **WordPress :** 6.0+ (REST API, Customizer)
- **Advanced Custom Fields Pro :** 6.0+ (Local JSON, Options Pages)
- **Elementor :** Compatible mais pas requis
- **PHP :** 8.0+ recommandé, 7.4+ minimum
- **JavaScript Libraries :**
  - Flatpickr (calendriers)
  - Leaflet (cartes)
  - GLightbox (galeries)
  - jsPDF (génération PDF)

---

**Dernière mise à jour :** 29/01/2026  
**Analysé par :** IA Senior Developer & Architecte Logiciel  
**Version du code :** V2.3+ (branche principale)
