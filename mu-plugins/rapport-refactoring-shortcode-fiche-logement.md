# Rapport de Refactoring - Shortcode Page Fiche Logement

## 🎯 Objectif

Diviser le fichier monolithique `shortcode-page-fiche-logement.php` (2000+ lignes) et `pc-ui.css` en modules thématiques pour améliorer la maintenabilité, la réutilisabilité et la lisibilité du code.

## 📊 Analyse des Fichiers Actuels

### shortcode-page-fiche-logement.php (2,054 lignes)

**Problèmes identifiés :**

- Fichier monolithique avec multiples responsabilités
- Mélange de logique métier et de présentation
- Code dupliqué dans plusieurs shortcodes
- Gestion des assets dispersée
- Handlers de formulaires mélangés avec la logique d'affichage

### pc-ui.css (1,987 lignes)

**Problèmes identifiés :**

- Styles de composants mélangés sans organisation claire
- Répétition de règles CSS
- Responsabilité multiple (layout, components, utilities)
- Surcharge de spécificité avec Elementor

### pc-fiche-logement.js (320 lignes)

**Problèmes identifiés :**

- Fonction monolithique `initLogementBooking()` avec multiples responsabilités
- Logique d'interface mélangée avec la logique métier
- Gestion d'état globale dispersée (`window.currentLogementSelection`)
- Code de manipulation DOM répétitif
- Intégrations tierces (Lodgify, Stripe) non modulaires
- Pas de séparation claire entre les composants UI

## 🏗️ Structure de Refactoring Proposée

### 📁 Nouvelle Architecture PHP

```
mu-plugins/
├── pc-logement/
│   ├── pc-logement-core.php                    # Point d'entrée principal
│   ├── shortcodes/
│   │   ├── class-pc-gallery-shortcode.php      # [pc_gallery]
│   │   ├── class-pc-highlights-shortcode.php   # [pc_highlights]
│   │   ├── class-pc-ical-shortcode.php         # [pc_ical_calendar]
│   │   ├── class-pc-location-shortcode.php     # [pc_location_map]
│   │   ├── class-pc-proximites-shortcode.php   # [pc_proximites]
│   │   ├── class-pc-seo-shortcode.php          # [pc_seo_readmore]
│   │   ├── class-pc-tarifs-shortcode.php       # [pc_tarifs_table]
│   │   └── class-pc-devis-shortcode.php        # [pc_devis]
│   ├── booking/
│   │   ├── class-pc-booking-bar.php            # Barre de réservation
│   │   ├── class-pc-booking-router.php         # Router réservation
│   │   ├── class-pc-booking-form.php           # Formulaires
│   │   └── class-pc-booking-handler.php        # Traitement des données
│   ├── assets/
│   │   ├── class-pc-asset-manager.php          # Gestion des assets
│   │   └── class-pc-script-loader.php          # Chargement conditionnel
│   ├── helpers/
│   │   ├── class-pc-ics-parser.php             # Parseur iCal
│   │   ├── class-pc-availability-helper.php    # Disponibilités
│   │   └── class-pc-price-calculator.php       # Calculs de prix
│   └── traits/
│       ├── trait-pc-acf-fields.php             # Gestion champs ACF
│       └── trait-pc-validation.php             # Validation données
```

### 📁 Nouvelle Architecture CSS

```
mu-plugins/assets/css/
├── pc-ui-core.css                              # Variables et base
├── components/
│   ├── pc-gallery.css                         # Styles galerie
│   ├── pc-highlights.css                      # Pastilles points forts
│   ├── pc-calendar.css                        # Calendrier Flatpickr
│   ├── pc-map.css                             # Carte Leaflet
│   ├── pc-proximites.css                      # Proximités
│   ├── pc-seo-readmore.css                    # Lire plus/moins
│   ├── pc-tarifs.css                          # Tableau tarifs
│   ├── pc-devis.css                           # Calculateur devis
│   ├── pc-booking-modal.css                   # Modales réservation
│   ├── pc-booking-fab.css                     # Bouton flottant
│   └── pc-booking-sheet.css                   # Bottom sheet mobile
├── layouts/
│   ├── pc-anchor-menu.css                     # Menu sticky
│   └── pc-responsive-grid.css                 # Grilles responsives
└── utilities/
    ├── pc-buttons.css                          # Styles boutons
    ├── pc-forms.css                            # Formulaires
    └── pc-elementor-overrides.css              # Corrections Elementor
```

### 📁 Nouvelle Architecture JavaScript

```
mu-plugins/assets/js/
├── pc-logement-core.js                         # Point d'entrée principal
├── components/
│   ├── pc-booking-fab.js                      # Bouton flottant FAB
│   ├── pc-booking-sheet.js                    # Bottom sheet mobile
│   ├── pc-booking-modal.js                    # Modale de contact
│   ├── pc-devis-calculator.js                 # Calculateur de devis
│   ├── pc-gallery-manager.js                  # Gestionnaire galerie
│   └── pc-calendar-integration.js             # Intégration Flatpickr
├── modules/
│   ├── pc-state-manager.js                    # Gestion d'état centralisée
│   ├── pc-dom-utils.js                        # Utilitaires DOM
│   ├── pc-form-validator.js                   # Validation formulaires
│   └── pc-currency-formatter.js               # Formatage monétaire
├── integrations/
│   ├── pc-lodgify-connector.js                # Intégration Lodgify
│   ├── pc-stripe-handler.js                   # Gestion paiements Stripe
│   └── pc-orchestrator-bridge.js              # Pont avec l'orchestrateur
└── utils/
    ├── pc-event-emitter.js                    # Système d'événements
    ├── pc-storage-helper.js                   # Gestion localStorage
    └── pc-url-builder.js                      # Construction d'URLs
```

## 🔧 Plan de Refactoring Détaillé

### Phase 1 : Extraction des Shortcodes (Semaine 1-2)

#### 1.1 Classe Base Abstract pour Shortcodes

```php
abstract class PC_Shortcode_Base {
    protected $tag;
    protected $default_atts = [];

    abstract public function render($atts, $content = null);
    abstract protected function enqueue_assets();

    public function register() {
        add_shortcode($this->tag, [$this, 'handle_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'conditional_enqueue']);
    }

    protected function validate_atts($atts) {
        return shortcode_atts($this->default_atts, $atts, $this->tag);
    }
}
```

#### 1.2 PC_Gallery_Shortcode (Priorité Haute)

**Fonctions à extraire :**

- `pc_gallery shortcode handler` (lignes 180-350)
- Logique de filtrage des images ACF
- Support lightbox GLightbox

**Helpers nécessaires :**

```php
class PC_Gallery_Helper {
    public static function get_external_urls($field_value) { /* ... */ }
    public static function get_acf_groups($post_id) { /* ... */ }
    public static function prepare_categories($groups) { /* ... */ }
}
```

#### 1.3 PC_Booking_Bar_Shortcode (Priorité Critique)

**Fonctions à extraire :**

- `pc_sc_booking_bar` (lignes 580-700)
- Logique FAB et bottom sheet
- Gestion modale contact

#### 1.4 PC_Devis_Shortcode (Priorité Haute)

**Fonctions à extraire :**

- `pc_devis shortcode handler` (lignes 1200-1400)
- Calculs de prix et saisons
- Intégration paiement Stripe

### Phase 2 : Gestionnaires de Réservation (Semaine 2-3)

#### 2.1 PC_Booking_Handler

```php
class PC_Booking_Handler {
    public function handle_logement_booking_request() { /* lignes 1600-1750 */ }
    public function handle_booking_request() { /* lignes 850-950 */ }

    private function validate_booking_data($data) { /* ... */ }
    private function create_reservation($data) { /* ... */ }
    private function send_notifications($reservation) { /* ... */ }
}
```

#### 2.2 PC_Availability_Helper

```php
class PC_Availability_Helper {
    public static function get_combined_availability($logement_id) { /* ligne 1850 */ }
    public static function parse_ics_ranges($ics_content) { /* lignes 450-520 */ }
    public static function get_manual_blocks($logement_id) { /* ... */ }
}
```

### Phase 3 : Asset Management (Semaine 3)

#### 3.1 PC_Asset_Manager

```php
class PC_Asset_Manager {
    private $registered_assets = [];

    public function register_logement_assets() { /* lignes 10-60 */ }
    public function enqueue_flatpickr() { /* ... */ }
    public function enqueue_leaflet() { /* ... */ }
    public function render_init_script() { /* lignes 70-140 */ }
}
```

### Phase 4 : Refactoring CSS (Semaine 4)

#### 4.1 Séparation par Composants

**pc-gallery.css** (370 lignes extraites)

```css
/* Variables spécifiques */
:root {
  --pc-gallery-gap: 20px;
  --pc-gallery-radius: var(--pc-radius);
  --pc-gallery-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
}

/* Styles de base */
.pc-gallery {
  /* ... */
}
.pc-gallery .pc-grid {
  /* ... */
}
.pc-gallery .pc-item {
  /* ... */
}
```

#### 4.2 Composants Booking

**pc-booking-modal.css** (180 lignes)
**pc-booking-fab.css** (120 lignes)
**pc-booking-sheet.css** (200 lignes)

### Phase 5 : Refactoring JavaScript (Semaine 4-5)

#### 5.1 Gestionnaire d'État Centralisé

**Fonctions à extraire du JS actuel :**

- Gestion de `window.currentLogementSelection` (lignes 50-80)
- Mise à jour des totaux et FAB (lignes 120-150)
- Synchronisation des données entre composants

```javascript
// pc-state-manager.js
class PCStateManager {
  constructor() {
    this.state = {
      selection: null,
      total: 0,
      lines: [],
      isManualQuote: false,
    };
    this.listeners = new Map();
  }

  updateSelection(selection) {
    this.state.selection = selection;
    this.emit("selectionChanged", selection);
  }

  updateTotal(total, lines) {
    this.state.total = total;
    this.state.lines = lines;
    this.emit("totalUpdated", { total, lines });
  }
}
```

#### 5.2 Composants UI Modulaires

**PC_BookingFAB** (lignes 60-120 du JS actuel)

```javascript
// pc-booking-fab.js
class PCBookingFAB {
  constructor(element, stateManager) {
    this.element = element;
    this.stateManager = stateManager;
    this.init();
  }

  init() {
    this.bindEvents();
    this.stateManager.on("totalUpdated", this.updateText.bind(this));
  }

  updateText(data) {
    // Logique de mise à jour du texte du FAB
  }
}
```

**PC_BookingSheet** (lignes 150-200 du JS actuel)

```javascript
// pc-booking-sheet.js
class PCBookingSheet {
  constructor(element) {
    this.element = element;
    this.isOpen = false;
    this.init();
  }

  open() {
    this.element.classList.add("is-open");
    this.isOpen = true;
    document.body.style.overflow = "hidden";
  }

  close() {
    this.element.classList.remove("is-open");
    this.isOpen = false;
    document.body.style.overflow = "";
  }
}
```

#### 5.3 Intégrations Tierces Modulaires

**PC_LodgifyConnector** (lignes 220-280 du JS actuel)

```javascript
// pc-lodgify-connector.js
class PCLodgifyConnector {
  constructor(config) {
    this.config = config;
  }

  buildReservationURL(selection) {
    const { lodgifyAccount, lodgifyId } = this.config;
    const baseUrl = "https://checkout.lodgify.com/fr/";

    return `${baseUrl}${lodgifyAccount}/${lodgifyId}/contact?currency=EUR&arrival=${selection.arrival}&departure=${selection.departure}&adults=${selection.adults}&children=${selection.children}&infants=${selection.infants}`;
  }

  redirect(selection) {
    const url = this.buildReservationURL(selection);
    const newWindow = window.open(url, "_blank");

    if (!newWindow) {
      throw new Error("Popup bloqué par le navigateur");
    }
  }
}
```

### Phase 6 : Tests et Migration (Semaine 5)

## 📋 Functions et Helpers à Créer

### Helpers Utilitaires

```php
class PC_Form_Validator {
    public static function validate_email($email) { /* ... */ }
    public static function validate_phone($phone) { /* ... */ }
    public static function sanitize_booking_data($data) { /* ... */ }
}

class PC_Price_Calculator {
    public function calculate_stay_price($config, $dates, $guests) { /* ... */ }
    public function apply_seasonal_pricing($base_price, $dates) { /* ... */ }
    public function calculate_taxes($amount, $tax_config) { /* ... */ }
}

class PC_Template_Helper {
    public static function get_company_info() { /* ... */ }
    public static function format_currency($amount) { /* ... */ }
    public static function format_date_range($start, $end) { /* ... */ }
}
```

### Conditions et Logique Métier

```php
class PC_Booking_Rules {
    public static function can_book_direct($logement_id) { /* ... */ }
    public static function has_lodgify_widget($logement_id) { /* ... */ }
    public static function get_payment_schedule($amount, $rules) { /* ... */ }
}

class PC_ACF_Helper {
    public static function get_logement_fields($post_id) { /* ... */ }
    public static function get_pricing_config($post_id) { /* ... */ }
    public static function get_availability_settings($post_id) { /* ... */ }
}
```

## 🎯 Structure Future Détaillée

### Fichier Principal : pc-logement-core.php

```php
<?php
/**
 * Plugin Core : PC Logement System
 * Point d'entrée unique pour tous les composants logement
 */

if (!defined('ABSPATH')) exit;

// Autoloader pour les classes
spl_autoload_register('pc_logement_autoloader');

function pc_logement_autoloader($class) {
    $prefix = 'PC_';
    if (strpos($class, $prefix) !== 0) return;

    $file = str_replace(['PC_', '_'], ['', '-'], $class);
    $file = strtolower($file) . '.php';

    $paths = [
        PC_LOGEMENT_PATH . 'shortcodes/class-' . $file,
        PC_LOGEMENT_PATH . 'booking/class-' . $file,
        PC_LOGEMENT_PATH . 'helpers/class-' . $file,
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }
}

// Initialisation du système
function pc_logement_init() {
    // Enregistrement des shortcodes
    $shortcodes = [
        'PC_Gallery_Shortcode',
        'PC_Highlights_Shortcode',
        'PC_Devis_Shortcode',
        // ... autres
    ];

    foreach ($shortcodes as $shortcode_class) {
        if (class_exists($shortcode_class)) {
            (new $shortcode_class())->register();
        }
    }

    // Gestionnaires de réservation
    new PC_Booking_Handler();
    new PC_Asset_Manager();
}

add_action('init', 'pc_logement_init', 20);
```

### Exemple de Shortcode Refactorisé

```php
<?php
class PC_Gallery_Shortcode extends PC_Shortcode_Base {
    protected $tag = 'pc_gallery';
    protected $default_atts = [
        'limit' => 6,
        'class' => '',
        'field' => 'gallery_urls',
    ];

    public function render($atts, $content = null) {
        $atts = $this->validate_atts($atts);

        // Mode A : URLs externes
        $external_urls = PC_Gallery_Helper::get_external_urls(
            get_field($atts['field'])
        );

        if (!empty($external_urls)) {
            return $this->render_external_gallery($external_urls, $atts);
        }

        // Mode B : Groupes ACF
        $acf_groups = PC_Gallery_Helper::get_acf_groups(get_the_ID());
        if (!empty($acf_groups)) {
            return $this->render_acf_gallery($acf_groups, $atts);
        }

        return '<div class="pc-gallery"><p class="pc-empty">Aucune photo disponible</p></div>';
    }

    protected function enqueue_assets() {
        wp_enqueue_style('pc-gallery', PC_LOGEMENT_URL . 'assets/css/components/pc-gallery.css');
        wp_enqueue_script('pc-gallery-js', PC_LOGEMENT_URL . 'assets/js/pc-gallery.js', ['glightbox-js']);
    }
}
```

## 📈 Bénéfices Attendus

### Maintenabilité

- **Séparation des responsabilités** : chaque classe a un rôle précis
- **Code réutilisable** : helpers partagés entre composants
- **Tests unitaires** : classes isolées = tests plus faciles

### Performance

- **Chargement conditionnel** : assets chargés uniquement si nécessaires
- **Cache optimisé** : helpers statiques avec mise en cache
- **CSS modulaire** : chargement uniquement des composants utilisés

### Développement

- **Debugging facilité** : erreurs localisées par composant
- **Extensions simples** : nouveau shortcode = nouvelle classe
- **Documentation** : chaque classe documentée individuellement

## 🚀 Roadmap d'Implémentation

### ✅ Sprint 1 : Refactoring PHP (TERMINÉ)

- [x] Analyse complète du code existant
- [x] Création de la structure de dossiers `pc-logement/`
- [x] Implémentation du core avec autoloader et singleton
- [x] Migration de tous les shortcodes vers classes individuelles :
  - [x] `PC_Gallery_Shortcode`
  - [x] `PC_Highlights_Shortcode`
  - [x] `PC_Tarifs_Shortcode`
  - [x] `PC_Location_Map_Shortcode`
  - [x] `PC_Proximites_Shortcode`
  - [x] `PC_SEO_Shortcode`
  - [x] `PC_ICal_Shortcode`
  - [x] `PC_Experiences_Shortcode`
  - [x] `PC_Utils_Shortcodes`
  - [x] `PC_Devis_Shortcode`
  - [x] `PC_Booking_Bar_Shortcode`
  - [x] `PC_Booking_Router_Shortcode`
- [x] Migration des gestionnaires :
  - [x] `PC_Booking_Handler`
  - [x] `PC_Asset_Manager`
  - [x] `PC_Availability_Helper`
- [x] Fichier original sauvegardé en `.php.off`

### 🎯 Sprint 2 : Refactoring CSS (EN COURS)

#### Étapes détaillées pour le refactoring CSS :

1. **Création de la structure de dossiers CSS**

   ```
   mu-plugins/assets/css/
   ├── components/     # À créer
   ├── layouts/        # À créer
   └── utilities/      # À créer
   ```

2. **Extraction des composants depuis `pc-ui.css` (1,987 lignes)**
   - [ ] **pc-gallery.css** (~370 lignes) - Lignes 125-495 de pc-ui.css
   - [ ] **pc-highlights.css** (~85 lignes) - Lignes 25-110 de pc-ui.css
   - [ ] **pc-calendar.css** (~200 lignes) - Lignes 780-980 de pc-ui.css
   - [ ] **pc-proximites.css** (~120 lignes) - Lignes 580-700 de pc-ui.css
   - [ ] **pc-devis.css** (~250 lignes) - Lignes 1200-1450 de pc-ui.css
   - [ ] **pc-booking-modal.css** (~180 lignes) - Lignes 1450-1630 de pc-ui.css
   - [ ] **pc-booking-fab.css** (~120 lignes) - Lignes 1630-1750 de pc-ui.css
   - [ ] **pc-booking-sheet.css** (~200 lignes) - Lignes 1750-1950 de pc-ui.css
   - [ ] **pc-seo-readmore.css** (~80 lignes) - Lignes 1100-1180 de pc-ui.css
   - [ ] **pc-tarifs.css** (~100 lignes) - Lignes 1050-1150 de pc-ui.css
   - [ ] **pc-map.css** (~60 lignes) - Lignes 520-580 de pc-ui.css

3. **Création des fichiers utilitaires**
   - [ ] **pc-ui-core.css** - Variables CSS globales et base
   - [ ] **pc-buttons.css** - Styles des boutons réutilisables
   - [ ] **pc-forms.css** - Styles des formulaires
   - [ ] **pc-elementor-overrides.css** - Corrections spécifiques Elementor

4. **Mise à jour du chargement CSS dans PC_Asset_Manager**
   - [ ] Chargement conditionnel des composants CSS
   - [ ] Dépendances entre fichiers CSS
   - [ ] Optimisation pour la production

**Prochaine action recommandée :** Commencer par créer la structure de dossiers puis extraire le composant le plus volumineux (`pc-gallery.css`) comme test pilote.

### Sprint 3 : Refactoring JavaScript

- [ ] Migration du JavaScript modulaire
- [ ] Gestionnaire d'état centralisé
- [ ] Composants UI modulaires
- [ ] Intégrations tierces isolées

### Sprint 4 : Tests & Optimisation

- [ ] Tests de non-régression
- [ ] Optimisation des performances
- [ ] Documentation finale

## ⚠️ Risques et Mitigation

### Risque 1 : Rupture de Compatibilité

**Mitigation :** Migration progressive avec ancien code en fallback

### Risque 2 : Performance CSS

**Mitigation :** Build process pour concaténer les CSS en production

### Risque 3 : Complexité des Dépendances

**Mitigation :** Autoloader intelligent avec gestion des erreurs

## ✅ Checklist de Validation

### Tests Fonctionnels

- [ ] Tous les shortcodes fonctionnent après migration
- [ ] Formulaires de réservation opérationnels
- [ ] Intégrations tierces (Lodgify, Stripe) maintenues
- [ ] Responsive design préservé

### Tests Techniques

- [ ] Pas d'erreurs PHP/JS en console
- [ ] Performance CSS/JS maintenue
- [ ] Compatibilité Elementor préservée
- [ ] Accessibilité WCAG maintenue

---

_Rapport généré le 25/02/2026 - Version 1.0_
