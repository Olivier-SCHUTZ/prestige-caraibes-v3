# Structure du Plugin PC-Reservation-Core

## 📁 Fichiers Racine & Configuration

```
pc-reservation-core/
├── pc-reservation-core.php           # Fichier principal du plugin
├── composer.json                     # Dépendances PHP
├── composer.lock                     # Verrouillage des versions
├── composer.phar                     # Exécutable Composer
├── composer-setup.php                # Installation Composer
├── package.json                      # Dépendances Node.js
├── package-lock.json                 # Verrouillage npm
├── vite.config.js                    # Configuration Vite
├── @architecture*.md                 # Documentation architecture
├── vendor/                          # Dépendances Composer
└── node_modules/                    # Dépendances npm
```

## 📁 Base de Données

```
db/
└── schema.php                        # Schéma de la base de données
```

## 🎨 Assets (Ressources Statiques)

```
assets/
├── css/                             # Styles CSS
│   ├── dashboard-base.css           # Styles de base dashboard
│   ├── dashboard-experience.css     # Styles expériences
│   ├── dashboard-forms.css          # Styles formulaires
│   ├── dashboard-housing.css        # Styles logements
│   ├── dashboard-messaging.css      # Styles messagerie
│   ├── dashboard-modals.css         # Styles modales
│   ├── dashboard-rates.css          # Styles tarification
│   ├── dashboard-style.css          # Styles généraux
│   └── pc-calendar.css              # Styles calendrier
└── js/                              # JavaScript Legacy
    ├── dashboard-core.js            # Core dashboard
    ├── dashboard-experience.js      # Gestion expériences
    ├── dashboard-housing.js         # Gestion logements
    ├── dashboard-rates.js           # Gestion tarifs
    ├── pc-calendar.js               # Calendrier
    └── modules/                     # Modules JS
        ├── booking-form.js          # Formulaire de réservation
        ├── documents.js             # Gestion documents
        ├── messaging.js             # Messagerie
        ├── payments.js              # Paiements
        ├── pricing-engine.js        # Moteur de prix
        └── utils.js                 # Utilitaires
```

## 🔧 Backend PHP (Includes)

### Classes Principales

```
includes/
├── class-booking-engine.php         # Moteur de réservation
├── class-documents.php              # Gestion documents
├── class-experience-manager.php     # Manager expériences
├── class-housing-manager.php        # Manager logements
├── class-ical-export.php            # Export iCal
├── class-messaging.php              # Messagerie
├── class-payment.php                # Paiements
├── class-rate-manager.php           # Gestion des tarifs
├── class-reservation.php            # Réservations
├── class-settings.php               # Paramètres
├── class-vite-loader.php            # Chargeur Vite
├── acf-fields.php                   # Champs ACF
└── controller-forms.php             # Contrôleur formulaires
```

### Services Architecture

```
includes/services/
├── booking/                         # Services de réservation
│   ├── class-booking-orchestrator.php
│   ├── class-booking-payload-normalizer.php
│   └── class-booking-pricing-calculator.php
├── calendar/                        # Services calendrier
│   └── class-ical-exporter.php
├── document/                        # Services documents
│   ├── class-document-financial-calculator.php
│   ├── class-document-repository.php
│   ├── class-document-service.php
│   └── renderers/                   # Générateurs documents
│       ├── class-base-renderer.php
│       ├── class-contract-renderer.php
│       ├── class-custom-renderer.php
│       ├── class-deposit-renderer.php
│       ├── class-invoice-renderer.php
│       └── class-voucher-renderer.php
├── experience/                      # Services expériences
│   ├── class-experience-config.php
│   ├── class-experience-formatter.php
│   ├── class-experience-repository.php
│   └── class-experience-service.php
├── housing/                         # Services logements
│   ├── class-housing-config.php
│   ├── class-housing-formatter.php
│   ├── class-housing-repository.php
│   └── class-housing-service.php
├── messaging/                       # Services messagerie
│   ├── class-messaging-repository.php
│   ├── class-messaging-service.php
│   ├── class-notification-dispatcher.php
│   └── class-template-manager.php
├── payment/                         # Services paiement
│   ├── class-payment-repository.php
│   └── class-payment-service.php
├── reservation/                     # Services réservation
│   ├── class-reservation-repository.php
│   ├── class-reservation-service.php
│   └── class-reservation-validator.php
└── settings/                        # Services paramètres
    ├── class-settings-config.php
    ├── class-settings-controller.php
    └── class-webhook-simulator.php
```

### Contrôleurs AJAX

```
includes/ajax/controllers/
├── class-ajax-router.php            # Routeur AJAX
├── class-base-ajax-controller.php   # Contrôleur de base
├── class-calendar-ajax-controller.php
├── class-dashboard-api-controller.php
├── class-document-ajax-controller.php
├── class-experience-ajax-controller.php
├── class-experience-bridge-controller.php
├── class-housing-ajax-controller.php
├── class-messaging-ajax-controller.php
└── class-reservation-ajax-controller.php
```

### API & Gateways

```
includes/api/
└── class-rest-webhook.php           # Webhooks REST API

includes/gateways/
├── class-stripe-ajax.php            # AJAX Stripe
├── class-stripe-manager.php         # Manager Stripe
└── class-stripe-webhook.php         # Webhooks Stripe

includes/partials/
└── tab-rates-promo.php              # Interface tarifs promo
```

## 🖼️ Frontend Vue.js (SRC)

### Applications Principales

```
src/modules/
├── dashboard/                       # Module dashboard
│   ├── App.vue                     # App principale dashboard
│   └── main.js                     # Point d'entrée
├── experience/                      # Module expériences
│   ├── ExperienceApp.vue           # App expériences
│   └── main.js                     # Point d'entrée
└── housing/                         # Module logements
    ├── HousingApp.vue              # App logements
    └── main.js                     # Point d'entrée
```

### Composants Vue

```
src/components/
├── ExperienceModal.vue              # Modale expérience
├── HousingModal.vue                 # Modale logement
├── StatCard.vue                     # Carte statistiques
├── common/                          # Composants communs
│   ├── WpGalleryUploader.vue       # Upload galerie WP
│   └── WpMediaUploader.vue         # Upload média WP
├── experience/                      # Composants expériences
│   ├── ExperienceTabFaq.vue        # Onglet FAQ
│   ├── ExperienceTabGalerie.vue    # Onglet Galerie
│   ├── ExperienceTabInclusions.vue # Onglet Inclusions
│   ├── ExperienceTabMain.vue       # Onglet Principal
│   ├── ExperienceTabPaiement.vue   # Onglet Paiement
│   ├── ExperienceTabSeo.vue        # Onglet SEO
│   ├── ExperienceTabServices.vue   # Onglet Services
│   ├── ExperienceTabSorties.vue    # Onglet Sorties
│   └── ExperienceTabTarifs.vue     # Onglet Tarifs
└── Housing/                         # Composants logements
    ├── RateCalendarArea.vue        # Zone calendrier des tarifs
    ├── RateEditModal.vue           # Modale d'édition des tarifs
    ├── RateSidebar.vue            # Barre latérale des tarifs
    ├── TabAmenities.vue            # Onglet Équipements
    ├── TabBooking.vue              # Onglet Réservation
    ├── TabConfig.vue               # Onglet Configuration
    ├── TabContent.vue              # Onglet Contenu
    ├── TabGeneral.vue              # Onglet Général
    ├── TabImages.vue               # Onglet Images
    ├── TabLocation.vue             # Onglet Localisation
    └── TabRates.vue                # Onglet Tarifs
```

### Stores & Services

```
src/stores/                          # Stores Pinia
├── dashboard-store.js              # Store dashboard
├── experience-store.js             # Store expériences
├── housing-modal-store.js          # Store modale logement
└── housing-store.js                # Store logements

src/services/
└── api-client.js                   # Client API
```

## 📄 Templates & Shortcodes

```
templates/
├── app-shell.php                   # Template shell application
└── dashboard/                      # Templates dashboard
    ├── list.php                   # Liste
    ├── modal-detail.php           # Modale détail
    ├── modal-messaging.php        # Modale messagerie
    └── popups.php                 # Popups

shortcodes/
├── shortcode-calendar.php         # Shortcode calendrier
├── shortcode-dashboard.php        # Shortcode dashboard
├── shortcode-experience.php       # Shortcode expérience
└── shortcode-housing.php          # Shortcode logement
```

## 🏗️ Architecture Générale

### Couches Architecture

1. **Frontend (Vue.js)** : Interface utilisateur moderne et réactive
2. **API Layer** : Contrôleurs AJAX pour communication frontend/backend
3. **Service Layer** : Logique métier organisée par domaine
4. **Repository Layer** : Accès aux données
5. **Gateway Layer** : Intégrations externes (Stripe, etc.)

### Domaines Métier

- **Housing** : Gestion des logements
- **Experience** : Gestion des expériences
- **Booking** : Moteur de réservation
- **Payment** : Gestion des paiements
- **Document** : Génération documents (factures, contrats, etc.)
- **Messaging** : Système de messagerie
- **Calendar** : Gestion calendrier et disponibilités

### Technologies

- **Backend** : PHP 8+, WordPress, Composer
- **Frontend** : Vue.js 3, Vite, Pinia
- **Base de données** : MySQL (WordPress)
- **Paiements** : Stripe
- **Build** : Vite, npm
