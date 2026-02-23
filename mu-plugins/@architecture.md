# 🏗️ Architecture MU-Plugins Prestige Caraïbes

**Version :** V2.3  
**Auteur :** PC SEO & Développement  
**Date d'analyse :** 23/02/2026  
**Type :** Must-Use Plugins WordPress - Écosystème fonctionnel global

---

## 📋 Vue d'ensemble

Les **MU-Plugins** (Must-Use Plugins) constituent l'écosystème fonctionnel principal de Prestige Caraïbes. Ces plugins se chargent automatiquement avant tous les autres plugins et gèrent les fonctionnalités critiques du site : SEO technique, schémas JSON-LD, shortcodes métier, et composants UI.

### 🎯 Responsabilités principales

- **SEO technique avancé** : Meta robots, sitemaps, canonicals, schémas JSON-LD
- **Shortcodes métier** : Fiches logements, expériences, recherches
- **Composants UI** : Galeries, calendriers, formulaires de réservation
- **Intégrations externes** : Lodgify, Stripe, APIs cartographiques
- **Performance** : Optimisations CSS/JS conditionnelles

---

## 📊 Statistiques du projet

### 📈 Lignes de code par catégorie

| **Catégorie**          | **Fichiers** | **Lignes**        | **% du total** |
| ---------------------- | ------------ | ----------------- | -------------- |
| **Core PHP**           | 28 fichiers  | **11,327 lignes** | **59.6%**      |
| **Assets CSS**         | 9 fichiers   | **5,611 lignes**  | **29.5%**      |
| **Assets JS**          | 10 fichiers  | **2,074 lignes**  | **10.9%**      |
| **Configuration JSON** | 10 fichiers  |                   | **Metadata**   |

**Total mu-plugins : 19,012 lignes**  
**Fichier le plus volumineux : mu-global-prestige-caraibesV2_3.php (3,061 lignes)**

---

## 🗂️ Structure détaillée

```
mu-plugins/
├── 📄 mu-global-prestige-caraibesV2_3.php    (3,061 lignes) ⭐ Hub principal
├── 📄 pc-custom-typesV3.php                  (207 lignes)    - CPTs & Taxonomies
├── 📄 pc-acf.php                             (33 lignes)     - Configuration ACF
├── 📂 shortcodes/                            - Pages métier (4,640 lignes)
│   ├── shortcode-page-fiche-logement.php     (2,281 lignes) ⭐ Fiches logements
│   ├── shortcode-page-fiche-experiences.php  (1,053 lignes) ⭐ Fiches expériences
│   ├── shortcode-liste-logement.php          (108 lignes)    - Listes/cartes
│   └── pc-destination-shortcodes.php         (499 lignes)    - Pages destinations
├── 📂 composants/                            - Fonctionnalités (3,619 lignes)
│   ├── pc-header-global.php                  (688 lignes)    - Header unifié
│   ├── pc-reviews.php                        (495 lignes)    - Système d'avis
│   ├── pc-experience-search.php              (452 lignes)    - Moteur de recherche
│   ├── pc-search-shortcodes.php              (416 lignes)    - Recherche logements
│   ├── pc-perf-hints.php                     (422 lignes)    - Optimisations
│   ├── pc-maintenance.php                    (267 lignes)    - Mode maintenance
│   ├── pc-faq-capture.php                    (263 lignes)    - FAQ dynamiques
│   ├── pc-ajax-search.php                    (179 lignes)    - Recherche AJAX
│   ├── pc-ical-cache.php                     (119 lignes)    - Cache iCal
│   └── pc-loop-components.php                (85 lignes)     - Vignettes grilles
├── 📂 assets/                                - Frontend (7,685 lignes)
│   ├── css/                                  (5,611 lignes)
│   │   ├── pc-ui.css                         (2,288 lignes) ⭐ Framework UI principal
│   │   ├── pc-ui-experiences.css             (1,370 lignes) ⭐ UI expériences
│   │   ├── pc-header-global.css              (1,011 lignes)  - Header responsive
│   │   ├── pc-search-shortcodes.css          (539 lignes)    - Recherche/filtres
│   │   ├── experience-search.css             (295 lignes)    - Moteur expériences
│   │   ├── pc-destination.css                (214 lignes)    - Pages destinations
│   │   ├── shortcode-liste-logement.css      (148 lignes)    - Grilles logements
│   │   ├── pc-loop-card.css                  (139 lignes)    - Cards/vignettes
│   │   └── pc-base.css                       (224 lignes)    - Variables CSS globales
│   └── js/                                   (2,074 lignes)
│       ├── pc-devis.js                       (950 lignes)  ⭐ Calculateur de prix
│       ├── pc-fiche-experiences.js           (939 lignes)  ⭐ Interactions expériences
│       ├── pc-fiche-logement.js              (728 lignes)    - Interactions logements
│       ├── pc-header-global.js               (416 lignes)    - Navigation/mobile
│       ├── pc-search-shortcodes.js           (232 lignes)    - Filtres dynamiques
│       ├── pc-gallerie.js                    (208 lignes)    - Galeries photos
│       ├── pc-header-smart.js                (169 lignes)    - Header intelligent
│       ├── shortcode-liste-logement-v2.js    (161 lignes)    - Grilles v2
│       ├── pc-experience-search.js           (149 lignes)    - Recherche expériences
│       └── pc-orchestrator.js                (118 lignes)    - Coordinateur scripts
└── 📂 pc-acf-json/                          - Métadonnées ACF
    ├── group_pc_fiche_logement.json          - Champs logements
    ├── group_pc_reviews.json                 - Champs avis
    ├── group_pc_destination.json             - Champs destinations
    ├── group_pc_seo_global.json              - Champs SEO
    └── ui_options_page_*.json                - Pages d'options
```

⭐ _= Composants les plus critiques_

---

## 🏛️ Architecture fonctionnelle

### 🎯 Pattern architectural : **Modular Monolith + Event-Driven**

Les MU-plugins suivent une architecture modulaire avec spécialisation par domaine métier :

```mermaid
graph TB
    A[WordPress Core] --> B[MU-Plugins Auto-Load]
    B --> C[mu-global-V2_3.php Hub]
    C --> D[SEO Engine]
    C --> E[Schema Generator]
    C --> F[Performance Layer]
    D --> G[Shortcodes Métier]
    G --> H[Assets Conditionnels]
    H --> I[Frontend Components]
    E --> J[JSON-LD Output]
```

### 🔧 Modules principaux

#### 1. **Hub Central** (`mu-global-prestige-caraibesV2_3.php`)

**Responsabilités :** Architecture SEO complète, schémas JSON-LD, optimisations

- **SEO technique** : Meta robots, sitemaps XML, canonicals intelligents
- **Schémas JSON-LD** : Organization, WebSite, FAQPage, Product, VacationRental
- **Performance** : Suppression assets inutiles, préchargement polices
- **3,061 lignes** - Le cerveau du système

#### 2. **Shortcodes Métier** (4,640 lignes)

- **Fiches Logements** (`shortcode-page-fiche-logement.php` - 2,281 lignes)
  - Galeries filtrable, calendrier iCal, calculateur de prix
  - Intégration Lodgify, formulaires de réservation
  - Gestion cautions Stripe, modes de paiement
- **Fiches Expériences** (`shortcode-page-fiche-experiences.php` - 1,053 lignes)
  - Réservation directe, tarifs complexes, recommandations
- **Destinations** (`pc-destination-shortcodes.php` - 499 lignes)
  - Hubs, grilles, logements/expériences recommandés

#### 3. **Framework UI** (`assets/css/`)

- **pc-ui.css** (2,288 lignes) : Framework CSS principal avec variables
- **pc-ui-experiences.css** (1,370 lignes) : Composants spécialisés expériences
- **pc-header-global.css** (1,011 lignes) : Navigation responsive uniforme

#### 4. **Moteurs de Recherche** (1,047 lignes)

- **Recherche Expériences** (`pc-experience-search.php` + assets)
- **Recherche Logements** (`pc-search-shortcodes.php` + assets)
- **AJAX avancé** avec filtres dynamiques et géolocalisation

---

## 🔄 Flux de données et intégrations

### 📍 Cycle de rendu d'une page produit

```
1. [WordPress] Chargement MU-plugins automatique
   ↓
2. [mu-global] Initialisation SEO + schémas
   ↓
3. [shortcode-fiche] Rendu contenu métier
   ↓
4. [Assets JS] Chargement conditionnel composants
   ↓
5. [API externes] Lodgify, Stripe, Leaflet, Flatpickr
   ↓
6. [JSON-LD] Injection schémas dans <head>
```

### 🎛️ Points d'intégration externes

| **Service**     | **Usage**                   | **Fichiers concernés**              |
| --------------- | --------------------------- | ----------------------------------- |
| **Lodgify**     | Réservations directes       | `shortcode-page-fiche-logement.php` |
| **Stripe**      | Paiements + cautions        | `pc-devis.js`                       |
| **iCal/ICS**    | Synchronisation calendriers | `pc-ical-cache.php`                 |
| **Leaflet**     | Cartes interactives         | `shortcode-*.php`                   |
| **Flatpickr**   | Sélecteurs de dates         | Assets JS                           |
| **GLightbox**   | Galeries photos             | `pc-gallerie.js`                    |
| **FontAwesome** | Iconographie                | Chargement conditionnel             |

---

## 🎨 Patterns et conventions

### 1. **Chargement conditionnel des assets**

```php
// Pattern répété : chargement intelligent selon le contexte
add_action('wp_enqueue_scripts', function () {
  if (! is_singular(['villa', 'appartement', 'logement'])) {
    return; // Pas de chargement inutile
  }
  wp_enqueue_style(...);
});
```

### 2. **Shortcodes avec configuration avancée**

```php
// Configuration data-driven pour composants complexes
add_shortcode('pc_devis', function ($atts = []) {
  $cfg = [
    'basePrice'   => $base_price,
    'seasons'     => $seasons_data,
    'icsDisable'  => $availability_ranges,
    'payment'     => $payment_config
  ];
  return '<section data-pc-devis="' . esc_attr(wp_json_encode($cfg)) . '">';
});
```

### 3. **SEO modulaire et extensible**

```php
// Système de schémas JSON-LD avec helpers réutilisables
function pcseo_get_meta($post_id, $suffix) {
  // Lecture multi-format (ACF group/subfield + fallbacks)
}
```

---

## ⚡ Performance et optimisations

### 🚀 Stratégies d'optimisation

| **Technique**                   | **Implémentation**           | **Gain estimé**   |
| ------------------------------- | ---------------------------- | ----------------- |
| **Chargement conditionnel**     | Assets par contexte page     | **-60% JS/CSS**   |
| **Variables CSS natives**       | `pc-base.css` avec `:root`   | **-30% taille**   |
| **Préchargement polices**       | `mu-global` anticipé         | **+200ms LCP**    |
| **Suppression bloat Gutenberg** | Désactivation conditionnelle | **-150KB**        |
| **Cache iCal intelligent**      | `pc-ical-cache.php` avec TTL | **-2s loading**   |
| **Minification runtime**        | Assets avec filemtime()      | **Cache optimal** |

### 🐌 Points d'amélioration identifiés

| **Problème**               | **Impact**         | **Solution recommandée**   |
| -------------------------- | ------------------ | -------------------------- |
| Fichiers monolithiques     | Maintenance        | Refactoring en modules     |
| Dépendances CDN multiples  | SPOF + latence     | Self-hosting critique      |
| JS inline volumineux       | Caching impossible | Externalisation            |
| Schémas JSON-LD redondants | DOM pollution      | Déduplication intelligente |

---

## 🔐 Sécurité et bonnes pratiques

### ✅ Mesures implémentées

- **Nonces WordPress** : Protection CSRF sur tous les formulaires
- **Sanitization complète** : `sanitize_text_field()`, `wp_kses_post()`
- **Capability checks** : Vérification permissions administrateur
- **Anti-bot honeypots** : Champs cachés dans formulaires
- **Rate limiting** : Via plugins externes + validation côté serveur
- **Échappement output** : `esc_html()`, `esc_attr()`, `esc_url()`

### 🛡️ Recommandations sécurité

- [ ] **Audit XSS** : Scanner tous les shortcodes pour injections
- [ ] **CSRF avancé** : Implémenter tokens rotatifs
- [ ] **Input validation** : Whitelist stricte champs ACF
- [ ] **SQL injection** : Audit requêtes custom avec `$wpdb`

---

## 📦 Dépendances et intégrations

### 🔌 Plugins WordPress requis

- **Advanced Custom Fields Pro** : Gestion des métadonnées
- **Elementor Pro** : Constructeur de pages
- **WP Rocket** : Cache + optimisations (optionnel)

### 🌐 Services externes (CDN)

```javascript
// Dépendances critiques chargées via CDN
"https://unpkg.com/leaflet@1.9.4/dist/leaflet.css";
"https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css";
"https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css";
"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css";
```

### 🛠️ APIs et Webhooks

- **Stripe API** : Paiements, cautions, webhooks
- **Lodgify API** : Synchronisation réservations
- **OpenStreetMap** : Tiles cartographiques
- **Email providers** : SMTP pour notifications

---

## 🧪 Qualité du code

### 📊 Métriques de complexité

| **Fichier**                            | **Lignes** | **Fonctions** | **Complexité** |
| -------------------------------------- | ---------- | ------------- | -------------- |
| `mu-global-prestige-caraibesV2_3.php`  | 3,061      | ~80           | **Élevée**     |
| `shortcode-page-fiche-logement.php`    | 2,281      | ~25           | **Élevée**     |
| `pc-ui.css`                            | 2,288      | N/A           | **Moyenne**    |
| `shortcode-page-fiche-experiences.php` | 1,053      | ~15           | **Moyenne**    |

### ❌ Dette technique identifiée

1. **Fonctions gigantesques** : Certaines fonctions dépassent 200 lignes
2. **Code dupliqué** : Patterns similaires entre logements/expériences
3. **Configuration hardcodée** : Magic numbers et strings
4. **Absence de tests** : Aucun test automatisé
5. **Documentation manquante** : Peu de docblocks PHP

---

## 🚀 Roadmap d'évolution

### 📈 Court terme (Q1 2026)

1. **Refactoring mu-global** : Diviser en modules thématiques
2. **Optimisation CSS** : Audit unused + purge automatique
3. **Tests unitaires** : Couverture 40% fonctions critiques
4. **Documentation** : Docblocks + guide développeur

### 🎯 Moyen terme (Q2-Q3 2026)

1. **Migration CDN → Self-hosted** : Vendor assets localement
2. **Module système** : Architecture PSR-4 + autoloading
3. **Cache avancé** : Redis pour données API/iCal
4. **A/B Testing** : Variants composants UI

### 🌟 Long terme (2027+)

1. **Micro-frontend** : Composants JS indépendants
2. **SSR/Hydration** : Rendu server-side critique
3. **Progressive Web App** : Service workers + cache
4. **Headless WordPress** : API-first pour mobile

---

## 📝 Configuration et personnalisation

### 🎛️ Variables CSS principales (`pc-base.css`)

```css
:root {
  --pc-primary: #0e2b5c; /* Bleu corporate */
  --pc-accent: #005f73; /* Accent interactions */
  --pc-sticky-top: 68px; /* Hauteur header fixe */
  --pc-border-radius: 12px; /* Rayon uniformisé */
  --pc-font-family-heading: "Poppins", system-ui;
  --pc-font-family-body: system-ui, -apple-system, "Segoe UI";
}
```

### ⚙️ Points de configuration ACF

- **Options générales** : Infos entreprise, SEO, logos
- **Règles de paiement** : Acomptes, cautions, délais
- **Modes de réservation** : Directe vs demande
- **Intégrations** : Clés API Stripe, Lodgify, etc.

---

## 🔍 Monitoring et debugging

### 📊 Logs disponibles

```php
// Pattern de logging utilisé dans le code
error_log('[PC COMPONENT] Message de debug');
```

### 🛠️ Outils de diagnostic

- **WordPress Debug** : `WP_DEBUG` + `WP_DEBUG_LOG`
- **Query Monitor** : Plugin pour analyser performances
- **Browser DevTools** : Console erreurs JS + Network

### 📈 KPIs à surveiller

- **Temps de chargement** pages fiches (< 3s)
- **Taux d'erreur** formulaires réservation (< 1%)
- **Performance** requêtes base de données
- **Disponibilité** services externes (Stripe, Lodgify)

---

## 👥 Équipe et maintenance

**Architecture :** PC SEO + Équipe dev  
**Maintenance :** Mensuelle + corrections bugs  
**Stack technique :** PHP 8.0+, CSS3 moderne, ES6+, WordPress 6.0+  
**Outils :** ACF Pro, Elementor Pro, Git, Local by Flywheel

---

## 🔗 Relations avec pc-reservation-core

Les MU-plugins et le plugin pc-reservation-core sont **complémentaires** :

- **MU-plugins** : Interface utilisateur, SEO, affichage
- **pc-reservation-core** : Logique métier, base de données, API

```mermaid
graph LR
    A[MU-Plugins Frontend] --> B[Shortcodes Fiches]
    B --> C[Formulaires Réservation]
    C --> D[pc-reservation-core Backend]
    D --> E[Base de données]
    D --> F[Paiements Stripe]
```

---

_Document généré automatiquement le 23/02/2026_  
_Dernière mise à jour : Version V2.3_
