# Rapport d'Audit Stripe - Plan d'Intégration Vue 3

> **Date :** 17 mars 2026  
> **Projet :** Migration architecture pc-reservation-core  
> **Contexte :** Refonte frontend jQuery → Vue 3 + Pinia avec conservation backend PHP

---

## 1. État des lieux de l'existant

### 📋 Inventaire des fichiers PHP/JS actuels gérant Stripe

**Backend PHP (Architecture moderne en couches) :**

- `includes/class-payment.php` - Classe proxy/façade (deprecated, redirige vers la nouvelle architecture)
- `includes/services/payment/class-payment-service.php` - Service métier (Singleton, logique acomptes/soldes)
- `includes/services/payment/class-payment-repository.php` - Repository d'accès aux données (Singleton)
- `includes/gateways/class-stripe-manager.php` - **⭐ CŒUR DU SYSTÈME** - Manager API Stripe ultra-avancé
- `includes/gateways/class-stripe-webhook.php` - Écouteur webhooks Stripe
- `includes/gateways/class-stripe-ajax.php` - Contrôleurs AJAX (5 endpoints actifs)

**Plugin satellite :**

- `plugins/pc-stripe-caution/` - Plugin séparé pour les cautions/empreintes bancaires

**Frontend JavaScript Legacy :**

- `assets/js/modules/payments.js` - Module jQuery/Vanilla complet et fonctionnel
- `assets/js/dashboard-core.js` - Intégrations dashboard

**Frontend Vue 3 (en cours) :**

- `src/components/dashboard/ReservationModal.vue` - Onglet "Finances & Caution" (statique pour l'instant)

### 🏗 Système utilisé : Stripe Checkout Sessions + PaymentIntents manuels

**Mode de fonctionnement principal :**

- **Stripe Checkout (redirection)** pour les paiements clients
- **PaymentIntents en mode `manual capture`** pour les cautions (empreintes bancaires)
- **Gestion hybride** : Checkout pour les paiements immédiats + PI pour les holds/cautions

### 🗄 Structure de la classe `PCR_Payment` en base de données

**Table `wp_pc_payments` :**

```sql
id, reservation_id, type_paiement ('acompte'|'solde'|'total'|'sur_place'),
montant, statut ('en_attente'|'paye'|'annule'),
gateway_reference (PaymentIntent/Session ID), url_paiement,
raw_response (JSON Stripe complet), date_paiement, etc.
```

**Table `wp_pc_reservations` (champs paiement) :**

```sql
montant_total, statut_paiement ('non_paye'|'partiellement_paye'|'paye'),
caution_montant, caution_statut ('non_demande'|'empreinte_validee'|'liberee'|'encaissee'),
caution_reference (PaymentIntent ID), caution_date_validation, etc.
```

### 📊 Gestion des statuts de paiement actuels

**Statuts de paiement globaux (reservation.statut_paiement) :**

- `non_paye` - Aucun paiement effectué
- `en_attente_paiement` - Liens générés, en attente
- `partiellement_paye` - Acompte réglé, solde dû
- `paye` - Intégralement payé
- `sur_devis` - Mode devis, paiement non demandé
- `sur_place` - À régler physiquement

**Statuts de lignes individuelles (payments.statut) :**

- `en_attente` - Ligne créée, paiement non déclenché
- `paye` - Ligne réglée et confirmée
- `annule` - Ligne annulée

**Statuts de caution spécifiques :**

- `non_demande` - Pas de caution requise
- `demande_envoyee` - Lien envoyé au client
- `empreinte_validee` - Carte bloquée (hold actif)
- `liberee` - Hold annulé/expiré
- `encaissee` - Montant prélevé définitivement

---

## 2. Principe de fonctionnement actuel

### 🔄 Flux complet de paiement

**1. Génération automatique des lignes de paiement :**

```php
PCR_Payment_Service::generate_for_reservation($reservation_id)
```

- Lecture des règles ACF sur la fiche logement/expérience
- Création des lignes selon le mode : `acompte_plus_solde`, `total_a_la_reservation`, `sur_place`
- Calcul automatique des montants et échéances

**2. Génération de lien de paiement par l'admin :**

```javascript
// Frontend: assets/js/modules/payments.js
action: 'pc_stripe_get_link', payment_id: X
```

```php
// Backend: PCR_Stripe_Manager::create_payment_link()
$result = Stripe Checkout Session + metadata
```

**3. Paiement client et confirmation webhook :**

```
URL webhook: ?pc_action=stripe_webhook
Événement écouté: checkout.session.completed
→ PCR_Stripe_Webhook::handle_checkout_session()
```

**4. Mise à jour automatique des statuts :**

- Ligne de paiement → `statut = 'paye'`
- Statut global réservation recalculé (total payé vs dû)

### 🛡️ Flux spécifique des cautions

**1. Génération lien caution :**

```php
PCR_Stripe_Manager::create_caution_link($reservation_id)
// Mode: 'payment' + capture_method: 'manual'
```

**2. Validation empreinte → `caution_statut = 'empreinte_validee'`**

**3. Actions admin disponibles :**

- **Libérer** : `release_caution()` → Cancel du PaymentIntent
- **Encaisser** : `capture_caution()` → Capture partielle/totale
- **Renouveler** : `rotate_caution()` → Création nouvelle empreinte + annulation ancienne

**4. Automatisation CRON :**

- Renouvellement auto à J-1 avant expiration (7 jours Stripe)
- Libération auto 7 jours après le départ client

### 🌐 Communication Webhooks avec WordPress

**Configuration sécurisée :**

- **URL endpoint** : `https://site.com/?pc_action=stripe_webhook`
- **Secret de signature** : Stocké dans ACF options (`pc_stripe_webhook_secret`)
- **Validation de signature** : ⚠️ **PAS IMPLÉMENTÉE ACTUELLEMENT**
- **Événements écoutés** : `checkout.session.completed` uniquement

---

## 3. Stratégie de raccordement (Vue 3 <-> PHP)

### 🏗 Architecture proposée pour la nouvelle modale Vue 3

**Onglet Finances interactif dans `ReservationModal.vue` :**

```vue
<!-- Section Paiements -->
<div class="payments-section">
  <PaymentsList :payments="reservationDetails.payments" @generate-link="handlePaymentLink" />
  <PaymentActions :reservation="selectedReservation" @refresh="refreshDetails" />
</div>

<!-- Section Caution -->
<div
  class="caution-section"
  v-if="reservationDetails.caution.mode !== 'aucune'"
>
  <CautionStatus :caution="reservationDetails.caution" />
  <CautionActions @generate="generateCautionLink" @release="releaseCaution" @capture="captureCaution" />
</div>
```

### 📡 Nouveaux Endpoints AJAX requis dans `class-reservation-ajax-controller.php`

**Endpoints de paiement :**

```php
// DÉJÀ EXISTANTS (dans class-stripe-ajax.php)
- ajax_pc_stripe_get_link()           // ✅ Génération lien paiement
- ajax_pc_stripe_get_caution_link()   // ✅ Génération lien caution
- ajax_pc_stripe_release_caution()    // ✅ Libération caution
- ajax_pc_stripe_capture_caution()    // ✅ Encaissement partiel/total
- ajax_pc_stripe_rotate_caution()     // ✅ Renouvellement caution

// NOUVEAUX À CRÉER (si nécessaires)
- ajax_regenerate_payments()          // Régénération échéancier
- ajax_update_payment_status()        // Mise à jour manuelle statut
- ajax_send_payment_reminder()        // Relance client
```

**Intégration avec les stores Pinia :**

```javascript
// stores/reservations-store.js
actions: {
  async generatePaymentLink(paymentId) {
    const response = await apiClient.post('pc_stripe_get_link', {
      payment_id: paymentId,
      nonce: window.pcResaParams.manualNonce
    })
    return response.data
  },

  async refreshReservationDetails(reservationId) {
    // Recharge les détails après action paiement
    await this.fetchReservationDetails(reservationId)
  }
}
```

### 🔄 Gestion du retour d'information temps réel

**Stratégie de rafraîchissement :**

1. **Action utilisateur** → Appel API backend
2. **Succès backend** → `refreshReservationDetails()`
3. **Store Pinia mis à jour** → Vue réactive
4. **Feedback visuel** : Toasts, statuts mis à jour instantanément

**Gestion des états de chargement :**

```javascript
const isProcessingPayment = ref(false);

const handlePaymentAction = async (action, payload) => {
  isProcessingPayment.value = true;
  try {
    await store[action](payload);
    showSuccessToast(`Action ${action} réussie`);
  } catch (error) {
    showErrorToast(error.message);
  } finally {
    isProcessingPayment.value = false;
  }
};
```

---

## 4. Sécurité Blindée (Critique)

### 🔒 Analyse de la sécurité actuelle

**✅ Points forts identifiés :**

- Nonces WordPress sur toutes les requêtes AJAX (`wp_verify_nonce`)
- Vérification des droits admin (`current_user_can('manage_options')`)
- Sanitisation des données avec `sanitize_text_field()`, `sanitize_email()`
- Clés API Stripe stockées via ACF (champ password, non exposées frontend)
- Base de données : Requêtes préparées (`$wpdb->prepare()`)

**⚠️ Vulnérabilités critiques identifiées :**

1. **WEBHOOK SANS VALIDATION DE SIGNATURE**

```php
// Dans class-stripe-webhook.php - DANGEREUX !
public static function listen() {
    // ❌ Aucune vérification de signature Stripe
    $payload = @file_get_contents('php://input');
    $event = json_decode($payload, true);
    // → N'importe qui peut envoyer de fausses confirmations !
}
```

2. **Pas de validation du montant webhook vs BDD**
3. **Logs d'erreur potentiellement exposés** (`error_log` avec données sensibles)

### 🛡 Améliorations de sécurité strictes proposées

**1. Validation signature webhook (URGENT) :**

```php
// class-stripe-webhook.php - Version sécurisée
private static function verify_webhook_signature($payload, $sig_header) {
    $endpoint_secret = get_field('pc_stripe_webhook_secret', 'option');
    if (empty($endpoint_secret)) {
        throw new Exception('Webhook secret non configuré');
    }

    try {
        return \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
    } catch(\UnexpectedValueException $e) {
        throw new Exception('Payload invalide');
    } catch(\Stripe\Exception\SignatureVerificationException $e) {
        throw new Exception('Signature invalide - tentative de fraude ?');
    }
}
```

**2. Validation montant critique :**

```php
private static function validate_payment_amount($session, $expected_amount) {
    $received_amount = ($session['amount_total'] ?? 0) / 100; // Centimes → Euros
    $tolerance = 0.01; // Tolérance 1 centime

    if (abs($received_amount - $expected_amount) > $tolerance) {
        error_log(sprintf(
            '[SECURITY ALERT] Montant suspect - Attendu: %.2f€, Reçu: %.2f€, Session: %s',
            $expected_amount, $received_amount, $session['id']
        ));
        throw new Exception('Montant de paiement incohérent');
    }
}
```

**3. Routes AJAX ultra-sécurisées :**

```php
// class-base-ajax-controller.php - Méthode renforcée
protected static function verify_access($nonce_action, $nonce_key = 'nonce', $required_capability = 'manage_options') {
    // 1. Vérification nonce
    $nonce = sanitize_text_field(wp_unslash($_REQUEST[$nonce_key] ?? ''));
    if (!wp_verify_nonce($nonce, $nonce_action)) {
        self::security_log('Nonce invalide', $_REQUEST);
        wp_send_json_error(['message' => 'Token de sécurité invalide'], 403);
    }

    // 2. Vérification capacités
    if (!current_user_can($required_capability)) {
        self::security_log('Droits insuffisants', get_current_user_id());
        wp_send_json_error(['message' => 'Action non autorisée'], 403);
    }

    // 3. Rate limiting (prévention spam)
    self::check_rate_limit();
}

private static function security_log($message, $context = []) {
    error_log(sprintf(
        '[PC-SECURITY] %s | IP: %s | User: %s | Context: %s',
        $message,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        get_current_user_id(),
        json_encode($context)
    ));
}
```

**4. Configuration Stripe durcie :**

```php
// Validation configuration au démarrage
private static function validate_stripe_config() {
    $secret_key = self::get_secret_key();

    if (empty($secret_key)) {
        throw new Exception('Clé Stripe non configurée');
    }

    if (strpos($secret_key, 'sk_test_') === 0 && get_field('pc_stripe_mode', 'option') === 'live') {
        throw new Exception('Incohérence config: Clé TEST en mode LIVE !');
    }

    // Test validité clé avec appel API minimal
    self::test_api_key_validity($secret_key);
}
```

---

## 5. Plan de Refactoring détaillé

### 📋 Phase 1 : Sécurisation critique (1-2 jours)

**✅ Fichiers à modifier :**

- `includes/gateways/class-stripe-webhook.php` → Validation signature
- `includes/gateways/class-stripe-manager.php` → Validation config durcie
- `includes/ajax/controllers/class-base-ajax-controller.php` → Méthodes sécurité renforcées

**🗑 Code legacy à nettoyer :**

```php
// À SUPPRIMER de class-stripe-webhook.php
$payload = @file_get_contents('php://input'); // ❌ Pas de validation
$event = json_decode($payload, true);        // ❌ Pas de vérification

// REMPLACER PAR validation Stripe officielle
```

### 📋 Phase 2 : Raccordement Vue 3 (3-4 jours)

**🆕 Nouveaux composants Vue à créer :**

```
src/components/payment/
├── PaymentsList.vue          // Liste des échéances avec statuts
├── PaymentActions.vue        // Boutons génération liens
├── CautionStatus.vue         // Affichage statut caution
├── CautionActions.vue        // Actions caution (libérer/encaisser)
└── PaymentModals.vue         // Modales confirmation
```

**📝 Modifications de fichiers existants :**

- `src/components/dashboard/ReservationModal.vue` → Onglet Finances interactif
- `src/stores/reservations-store.js` → Actions paiement
- `src/services/api-client.js` → Endpoints Stripe

### 📋 Phase 3 : Optimisations et polish (2-3 jours)

**🔧 Améliorations backend :**

```php
// Nouveau service : PaymentLinkService
class PCR_Payment_Link_Service {
    public static function generate_bulk_links($reservation_id) {
        // Génération de tous les liens en une fois
    }

    public static function send_payment_reminder($payment_id, $template_id) {
        // Intégration avec le système de messaging
    }
}
```

**✨ Améliorations frontend :**

- Loading states sur toutes les actions
- Animations de transition des statuts
- Notifications toast en temps réel
- Shortcuts clavier pour actions fréquentes

### 📋 Phase 4 : Tests et validation (1-2 jours)

**🧪 Tests sécurité :**

- [ ] Tentatives de webhook forgés → Doivent être rejetées
- [ ] Actions sans nonces → Erreur 403
- [ ] Montants modifiés → Détection d'anomalie
- [ ] Clés API invalides → Gestion d'erreur propre

**🧪 Tests fonctionnels :**

- [ ] Génération liens → Copie presse-papier
- [ ] Cautions : génération, libération, encaissement
- [ ] Rotation automatique → Logs détaillés
- [ ] Webhooks → Mise à jour statuts temps réel

### 🏗 Snippets de code de base

**Configuration Pinia pour les paiements :**

```javascript
// stores/payments-store.js
export const usePaymentsStore = defineStore("payments", {
  state: () => ({
    processingActions: new Set(),
    paymentLinks: new Map(),
  }),

  actions: {
    async generateLink(paymentId) {
      this.processingActions.add(paymentId);
      try {
        const response = await apiClient.post("pc_stripe_get_link", {
          payment_id: paymentId,
          security: window.pcResaParams.manualNonce,
        });

        await navigator.clipboard.writeText(response.data.url);
        this.showSuccessToast("Lien copié dans le presse-papier");

        return response.data;
      } finally {
        this.processingActions.delete(paymentId);
      }
    },
  },
});
```

**Nouveau contrôleur sécurisé :**

```php
// class-secure-stripe-ajax-controller.php
class PCR_Secure_Stripe_Ajax_Controller extends PCR_Base_Ajax_Controller {

    public static function handle_secure_payment_action() {
        // Sécurité renforcée avec audit trail
        parent::verify_access('pc_stripe_action', 'security', 'manage_options');

        $action = sanitize_text_field($_POST['stripe_action'] ?? '');
        $payment_id = (int) ($_POST['payment_id'] ?? 0);

        // Audit log avant action
        self::log_payment_action($action, $payment_id, get_current_user_id());

        switch ($action) {
            case 'generate_link':
                return self::secure_generate_link($payment_id);
            case 'capture_caution':
                return self::secure_capture_caution($_POST);
            default:
                wp_send_json_error(['message' => 'Action non reconnue']);
        }
    }
}
```

---

## 🎯 Résumé Exécutif

**Système actuel :** Architecture Stripe moderne et avancée, mais avec vulnérabilités critiques de sécurité et interface legacy jQuery.

**Migration requise :**

1. **URGENT** - Sécurisation webhooks (validation signatures)
2. Raccordement Vue 3 pour interface moderne
3. Conservation de toute la logique métier PHP existante

**Estimation :** 8-11 jours de développement total pour une migration complète et sécurisée.

**Priorité absolue :** Phase 1 (sécurisation) avant toute autre évolution.
