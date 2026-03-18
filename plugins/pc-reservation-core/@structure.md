# Structure du Plugin `pc-reservation-core`

> Mise à jour basée sur l’état réel du plugin (hors détails `vendor/` et `node_modules/`).

## 📁 Racine & configuration

```text
pc-reservation-core/
├── pc-reservation-core.php
├── composer.json
├── composer.lock
├── composer.phar
├── composer-setup.php
├── package.json
├── package-lock.json
├── vite.config.js
├── @architecture-refactoring.md
├── @structure.md
├── db/
├── assets/
├── includes/
├── shortcodes/
├── src/
├── templates/
├── vendor/ (généré)
└── node_modules/ (généré)
```

---

## Base de données

```text
db/
└── schema.php
```

---

## 🎨 Assets legacy (CSS/JS)

```text
assets/
├── css/
│   ├── dashboard-base.css
│   ├── dashboard-style.css
│   └── pc-calendar.css
└── js/
    ├── dashboard-core.js
    ├── pc-calendar.js
    └── modules/
        ├── booking-form.js
        ├── documents.js
        ├── messaging.js
        ├── payments.js
        ├── pricing-engine.js
        └── utils.js
```

---

## 🔧 Backend PHP (`includes/`)

### Classes principales

```text
includes/
├── acf-fields.php
├── class-booking-engine.php
├── class-documents.php
├── class-experience-manager.php
├── class-housing-manager.php
├── class-ical-export.php
├── class-messaging.php
├── class-payment.php
├── class-reservation.php
├── class-settings.php
├── class-vite-loader.php
└── controller-forms.php
```

### Contrôleurs AJAX

```text
includes/ajax/controllers/
├── class-ajax-router.php
├── class-base-ajax-controller.php
├── class-calendar-ajax-controller.php
├── class-dashboard-api-controller.php
├── class-document-ajax-controller.php
├── class-experience-ajax-controller.php
├── class-experience-bridge-controller.php
├── class-housing-ajax-controller.php
├── class-messaging-ajax-controller.php
└── class-reservation-ajax-controller.php
```

### API + gateways + partials

```text
includes/api/
└── class-rest-webhook.php

includes/gateways/
├── class-stripe-ajax.php
├── class-stripe-manager.php
└── class-stripe-webhook.php

includes/partials/
└── tab-rates-promo.php
```

### Services métier

```text
includes/services/
├── booking/
│   ├── class-booking-orchestrator.php
│   ├── class-booking-payload-normalizer.php
│   └── class-booking-pricing-calculator.php
├── calendar/
│   └── class-ical-exporter.php
├── document/
│   ├── class-document-financial-calculator.php
│   ├── class-document-repository.php
│   ├── class-document-service.php
│   └── renderers/
│       ├── class-base-renderer.php
│       ├── class-contract-renderer.php
│       ├── class-custom-renderer.php
│       ├── class-deposit-renderer.php
│       ├── class-invoice-renderer.php
│       └── class-voucher-renderer.php
├── experience/
│   ├── class-experience-config.php
│   ├── class-experience-formatter.php
│   ├── class-experience-repository.php
│   └── class-experience-service.php
├── housing/
│   ├── class-housing-config.php
│   ├── class-housing-formatter.php
│   ├── class-housing-pricing-calculator.php
│   ├── class-housing-repository.php
│   └── class-housing-service.php
├── messaging/
│   ├── class-messaging-repository.php
│   ├── class-messaging-service.php
│   ├── class-notification-dispatcher.php
│   └── class-template-manager.php
├── payment/
│   ├── class-payment-repository.php
│   └── class-payment-service.php
├── reservation/
│   ├── class-reservation-repository.php
│   ├── class-reservation-service.php
│   └── class-reservation-validator.php
└── settings/
    ├── class-settings-config.php
    ├── class-settings-controller.php
    └── class-webhook-simulator.php
```

---

## 🖼️ Frontend Vue (`src/`)

### Modules applicatifs

```text
src/modules/
├── calendar/
│   ├── CalendarApp.vue
│   └── main.js
├── dashboard/
│   ├── App.vue
│   └── main.js
├── experience/
│   ├── ExperienceApp.vue
│   └── main.js
└── housing/
    ├── HousingApp.vue
    └── main.js
```

### Composants

```text
src/components/
├── Calendar/
│   ├── CalendarGrid.vue
│   ├── CalendarHeader.vue
│   ├── CalendarModal.vue
│   ├── CalendarModalGrid.vue
│   └── CalendarSelectionBar.vue
├── common/
│   ├── WpGalleryUploader.vue
│   └── WpMediaUploader.vue
├── dashboard/
│   ├── BookingForm.vue
│   ├── MessageCenter.vue
│   ├── ReservationList.vue
│   └── ReservationModal.vue
├── documents/
│   ├── DocumentsList.vue
│   ├── DocumentsManager.vue
│   └── DocumentTemplateSelector.vue
├── experience/
│   ├── ExperienceTabFaq.vue
│   ├── ExperienceTabGalerie.vue
│   ├── ExperienceTabInclusions.vue
│   ├── ExperienceTabMain.vue
│   ├── ExperienceTabPaiement.vue
│   ├── ExperienceTabSeo.vue
│   ├── ExperienceTabServices.vue
│   ├── ExperienceTabSorties.vue
│   └── ExperienceTabTarifs.vue
├── Housing/
│   ├── RateCalendarArea.vue
│   ├── RateEditModal.vue
│   ├── RateSidebar.vue
│   ├── TabAmenities.vue
│   ├── TabBooking.vue
│   ├── TabConfig.vue
│   ├── TabContent.vue
│   ├── TabGeneral.vue
│   ├── TabImages.vue
│   ├── TabLocation.vue
│   └── TabRates.vue
├── payment/
│   ├── CautionActions.vue
│   └── PaymentsList.vue
├── ExperienceModal.vue
├── HousingModal.vue
└── StatCard.vue
```

### Stores & services frontend

```text
src/stores/
├── calendar-store.js
├── dashboard-store.js
├── document-store.js
├── experience-store.js
├── housing-modal-store.js
├── housing-store.js
├── messaging-store.js
├── payments-store.js
└── reservations-store.js

src/services/
├── api-client.js
├── document-api.js
├── messaging-api.js
└── reservation-api.js
```

---

## 📄 Templates & shortcodes

```text
templates/
├── app-shell.php
└── dashboard/
    ├── list.php
    ├── modal-detail.php
    ├── modal-messaging.php
    └── popups.php

shortcodes/
├── shortcode-calendar.php
└── shortcode-dashboard.php
```

---

## 🧭 Domaines fonctionnels couverts

- Réservations (logements + expériences)
- Paiements (Stripe)
- Documents (contrat, facture, voucher, acompte)
- Messagerie
- Calendrier / iCal
- Dashboard propriétaire (Vue + AJAX WordPress)

---

## 🛠️ Stack technique

- **Backend** : PHP 8+, WordPress
- **Frontend** : Vue 3, Pinia
- **Build** : Vite, npm
- **Dépendances PHP** : Composer (DomPDF & co)
