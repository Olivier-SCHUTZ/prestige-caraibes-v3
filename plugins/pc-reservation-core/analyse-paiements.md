# 📊 Analyse Complète du Système de Paiement - Prestige Caraïbes

_Rapport généré le 20/03/2026 - Analyse de la migration vers Vue.js_

---

## 🏗️ Architecture Générale

### Tables de Base de Données

**wp_pc_reservations** (Table principale)

- `statut_reservation`: `en_attente_traitement`, `devis_envoye`, `reservee`, `annule`
- `statut_paiement`: `sur_devis`, `en_attente_paiement`, `non_paye`, `partiellement_paye`, `paye`, `sur_place`
- `montant_total`, `montant_acompte`, `montant_solde`
- `caution_montant`, `caution_statut`, `caution_reference`

**wp_pc_payments** (Table des paiements détaillés)

- `type_paiement`: `acompte`, `solde`, `total`, `sur_place`
- `statut`: `en_attente`, `paye`, `annule`
- `methode`: `stripe`, `virement`, `especes`, `cheque`
- `gateway_reference`, `gateway_status`

---

## 🎯 1. Déclencheurs de Statuts de Paiement

### A. Système Automatique (Webhooks Stripe)

**Fichier**: `includes/gateways/class-stripe-webhook.php`

```php
// Logique automatique de mise à jour statut
private static function update_reservation_status($resa_id) {
    $paid_amount = // Somme des paiements avec statut='paye'
    $total = // Montant total réservation

    if ($paid >= ($total - 1) && $total > 0) {
        $new_status = 'paye';
    } elseif ($paid > 0) {
        $new_status = 'partiellement_paye';
    } else {
        $new_status = 'non_paye';
    }
}
```

**Déclencheurs automatiques identifiés :**

- ✅ `checkout.session.completed` → Passage `en_attente` → `paye`
- ✅ Calcul automatique `partiellement_paye` vs `paye` basé sur montant total
- ✅ Vérification sécurisée des montants (tolérance 1€)

### B. Système Manuel (Actions Dashboard)

**Fichier**: `src/stores/payments-store.js` (Nouveau système Vue.js)

```javascript
// Nouvelle méthode pour Virement/Espèces
async updatePaymentStatus(paymentId, reservationId, status, method) {
    // Appel: pc_update_payment_status
    // Statuts manuels: 'paye' pour Virement/Espèces
}
```

**CONTRÔLEUR MANQUANT CRITIQUE** ⚠️

- L'action `pc_update_payment_status` est appelée depuis Vue.js
- **MAIS**: Aucun contrôleur PHP correspondant trouvé dans les fichiers AJAX
- **IMPACT**: Les paiements manuels (Virement, Espèces) ne peuvent pas être traités

### C. Génération Automatique des Lignes

**Fichier**: `includes/services/payment/class-payment-service.php`

```php
public function generate_for_reservation($resa_id) {
    $payment_rules = $this->get_item_payment_rules($item_id);

    switch ($mode_pay) {
        case 'sur_devis':
            $new_payment_state = 'non_demande';
        case 'total_a_la_reservation':
            // Ligne unique 100%
            $new_payment_state = 'en_attente_paiement';
        case 'acompte_plus_solde':
            // 2 lignes: acompte + solde avec échéances
            $new_payment_state = 'en_attente_paiement';
        case 'sur_place':
            $new_payment_state = 'sur_place';
    }
}
```

---

## 🧮 2. Logique Mathématique de Calcul

### A. Calcul Acompte/Solde (Service Principal)

```php
// Dans PCR_Payment_Service::generate_for_reservation()

if ($deposit_type === 'montant_fixe') {
    $acompte = round(min($total, max(0, $deposit_value)), 2);
} else {
    // Pourcentage
    $acompte = round(max(0, $total * ($deposit_value / 100)), 2);
}

$solde = round(max(0, $total - $acompte), 2);
```

### B. Logique de Forçage Total Immédiat

```php
// Si date d'arrivée trop proche ou dépassée
if ($interval->invert === 1 || $interval->days <= $delay_days) {
    $force_total = true;
    // → Une seule ligne 'total' au lieu de acompte+solde
}
```

### C. Détection Statut Final (Webhook)

```php
// Tolérance de 1€ pour éviter les problèmes d'arrondi
if ($paid >= ($total - 1) && $total > 0) {
    return 'paye';
} elseif ($paid > 0) {
    return 'partiellement_paye';
}
```

**RÈGLES IDENTIFIÉES :**

- ✅ Acomptes arrondis à 2 décimales pour éviter les centimes perdus
- ✅ Basculement automatique vers paiement total si échéance dépassée
- ✅ Tolérance 1€ dans les comparaisons de montants payés vs dus

---

## 📱 3. Ancien Dashboard vs Nouveau Vue.js

### A. Ancien Système (shortcode-dashboard.php.off)

**FONCTIONNALITÉS PERDUES identifiées :**

```php
// Ancien mapping des statuts lisibles
function pc_resa_format_status_label($status) {
    $map = [
        'sur_devis'             => 'Sur devis',
        'en_attente_paiement'   => 'En attente de paiement',
        'partiellement_paye'    => 'Partiellement payé',
        'paye'                  => 'Payé',
        'acompte_paye'          => 'Acompte réglé',
        'solde_paye'            => 'Solde réglé',
    ];
}
```

**Actions manuelles de l'ancien dashboard :**

- Changement manuel de statut réservation
- Modification montants acompte/solde
- Régénération des lignes de paiement
- Actions en lot sur plusieurs réservations

### B. Nouveau Système Vue.js

**AMÉLIORATIONS apportées :**

- ✅ Interface réactive temps réel
- ✅ Gestion cautions avec empreintes bancaires
- ✅ Liens de paiement Stripe sécurisés
- ✅ Store Pinia pour état centralisé

**RÉGRESSIONS détectées :**

- ❌ Pas de contrôleur pour paiements manuels (Virement/Espèces)
- ❌ Logique de changement manuel de statut non migrée
- ❌ Interface de régénération des paiements absente

---

## 💳 4. Méthodes de Paiement Actuelles

### A. Méthodes Automatiques (Opérationnelles)

**Stripe** - `includes/gateways/class-stripe-*`

- ✅ Paiements en ligne via checkout session
- ✅ Webhooks sécurisés avec vérification signature
- ✅ Cautions par empreinte bancaire
- ✅ Actions: Générer lien, Libérer caution, Encaisser caution

### B. Méthodes Manuelles (NON Opérationnelles)

**Dans le Store Vue.js** (`src/stores/payments-store.js`):

```javascript
async updatePaymentStatus(paymentId, reservationId, status, method) {
    // Appel action: 'pc_update_payment_status'
    // Méthodes: 'virement', 'especes', 'cheque'
}
```

**⚠️ PROBLÈME CRITIQUE :**

- L'interface Vue.js propose les options Virement/Espèces
- **MAIS** le contrôleur PHP correspondant n'existe pas
- Les utilisateurs voient les boutons mais rien ne fonctionne

### C. Configuration Règles de Paiement

**Migration ACF → Meta Natives :**

```php
// Nouveau système dans PCR_Payment_Service
$vue_rules = get_post_meta($item_id, '_pc_payment_rules', true);
// Fallback legacy ACF si meta Vue non présente
```

**Règles par défaut :**

- `mode_pay`: `acompte_plus_solde` (30% acompte)
- `deposit_type`: `pourcentage`
- `delay_days`: 30 jours avant arrivée
- `caution_type`: `empreinte`

---

## 🔧 5. Actions Correctives Recommandées

### PRIORITÉ 1 - Critique (Blocant)

1. **Créer le contrôleur manquant pour paiements manuels**

   ```php
   // Dans class-dashboard-api-controller.php
   public function handle_update_payment_status() {
       // Traiter action 'pc_update_payment_status'
       // Mettre à jour table wp_pc_payments
       // Recalculer statut_paiement global
   }
   ```

2. **Ajouter l'action au routeur AJAX**
   ```php
   // Dans class-ajax-router.php
   'pc_update_payment_status' => 'PCR_Dashboard_Api_Controller@handle_update_payment_status'
   ```

### PRIORITÉ 2 - Importantes (Améliorations UX)

1. **Migrer logique changement statut manuel**
2. **Ajouter interface régénération paiements**
3. **Créer actions en lot**
4. **Interface de gestion des échéances**

### PRIORITÉ 3 - Cosmétiques (Labels)

1. **Harmoniser labels statuts** entre ancien/nouveau système
2. **Ajouter tooltips explicatives** sur statuts complexes
3. **Notifications temps réel** après actions paiement

---

## 🔍 6. Points de Surveillance

### Sécurité

- ✅ Vérification signature Stripe webhook
- ✅ Validation montants avec tolérance
- ❌ Validation insuffisante sur actions manuelles (à implémenter)

### Performance

- ✅ Requêtes optimisées avec préparation SQL
- ✅ Chargement différé des données de paiement
- ⚠️ Risque de n+1 queries sur listes importantes

### Intégrité Données

- ✅ Arrondi cohérent à 2 décimales
- ✅ Contraintes de cohérence acompte+solde=total
- ❌ Pas de logs d'audit sur changements manuels

---

## 📈 Conclusion & Recommandations

### État Actuel : ⚠️ **PARTIELLEMENT FONCTIONNEL**

Le système automatique Stripe fonctionne parfaitement, mais les **paiements manuels sont complètement cassés** depuis la migration Vue.js.

### Actions Immédiates Requises :

1. **Implémenter le contrôleur manquant** (`pc_update_payment_status`)
2. **Tester les workflows Virement/Espèces**
3. **Migrer les actions manuelles de l'ancien dashboard**
4. **Documenter les nouveaux processus** pour les utilisateurs

### Potentiel du Système Modernisé :

Avec les corrections, le nouveau système Vue.js sera **supérieur** à l'ancien :

- Interface plus réactive
- Meilleure séparation des responsabilités
- Gestion cautions intégrée
- Extensibilité pour nouvelles méthodes de paiement

---

_Rapport généré par analyse des fichiers source - Version PC Reservation Core_
