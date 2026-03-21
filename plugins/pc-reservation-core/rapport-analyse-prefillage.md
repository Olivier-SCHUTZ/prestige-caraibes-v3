# Rapport d'Analyse - Problème de Pré-remplissage BookingForm.vue

## Résumé Exécutif

**Problème identifié** : La méthode `ajax_get_reservation_details()` ne renvoie pas suffisamment de données structurées pour permettre le pré-remplissage fidèle des quantités d'expériences, options et tarifs dans `BookingForm.vue`.

**Impact** : Impossible d'éditer correctement une réservation d'expérience existante depuis le Dashboard.

---

## 1. Analyse de la Base de Données

### Structure de la table `pc_reservations`

**Colonnes clés pour les expériences :**

- `type` : 'experience' vs 'location'
- `experience_tarif_type` : Type de tarif choisi (ex: 'forfait', 'adulte_enfant', 'custom_label')
- `adultes`, `enfants`, `bebes` : Quantités de base
- `detail_tarif` : **JSON contenant les lignes détaillées du devis** (quote_lines)
- `origine` : 'site' (Front-end) vs 'manuel' (Dashboard)

**Structure type du JSON `detail_tarif` :**

```json
[
  {
    "label": "Location privatisation",
    "clean_label": "Location privatisation",
    "qty": 4,
    "price": "250.00",
    "observation": "Durée : 3h"
  },
  {
    "label": "Option Transport",
    "clean_label": "Option Transport",
    "qty": 2,
    "price": "50.00"
  }
]
```

---

## 2. Analyse de l'Enregistrement (Front vs Manuel)

### 2.1 Sauvegarde Front-end

- **Orchestrateur** : `PCR_Booking_Orchestrator::create()`
- **Normalisation** : `PCR_Booking_Payload_Normalizer::normalize()`
- **Structure payload** :

```php
[
  'item' => [
    'experience_tarif_type' => 'forfait' // Clé technique du tarif ACF
  ],
  'people' => [
    'adultes' => 2,
    'enfants' => 1,
    'bebes' => 0
  ],
  'pricing' => [
    'lines' => [...], // Lignes structurées avec qty
    'manual_adjustments' => [...]
  ]
]
```

### 2.2 Sauvegarde Manuelle (Dashboard)

- **Contrôleur** : `PCR_Reservation_Ajax_Controller::handle_manual_reservation()`
- **Même orchestrateur**, mais payload différent :

```php
[
  'item' => [
    'experience_tarif_type' => 'custom_label' // Peut être un label brut
  ],
  'pricing' => [
    'raw_lines_json' => '...', // JSON brut du calcul
    'manual_adjustments' => [...] // Remises/Plus-values
  ]
]
```

**Différence clé** : Le Front-end envoie des `lines` structurées, le Manuel envoie du `raw_lines_json`.

---

## 3. Analyse du Renvoi (ajax_get_reservation_details)

### 3.1 Données actuellement renvoyées

```php
wp_send_json_success([
  'quote_lines' => $quote_lines, // ✅ JSON décodé du detail_tarif
  'raw_type' => $resa->type,
  'raw_item_id' => (int) $resa->item_id,
  'raw_tarif_type' => $resa->experience_tarif_type, // ✅ Type de tarif
  'raw_adultes' => (int) $resa->adultes, // ✅ Quantités de base
  'raw_enfants' => (int) $resa->enfants,
  'raw_bebes' => (int) $resa->bebes,
  // ... autres champs client
]);
```

### 3.2 Données manquantes pour Vue.js

**❌ PROBLÈME 1 : Pas de mapping customQty**

- Vue.js attend : `formData.customQty = { 'line_0': 4, 'opt_1': 2 }`
- Actuellement : Seulement `quote_lines` avec labels texte

**❌ PROBLÈME 2 : Pas de reconstruction des options**

- Vue.js attend : `formData.options = { 'option_0': { selected: true, qty: 2 } }`
- Actuellement : Aucune donnée structurée sur les options

**❌ PROBLÈME 3 : Mapping tarif_type incomplet**

- Front-end stocke : clé technique ACF ('forfait')
- Manuel peut stocker : label brut ('Forfait groupe')
- Vue.js ne retrouve pas le tarif dans la liste ACF

---

## 4. Analyse de BookingForm.vue

### 4.1 Processus de pré-remplissage actuel

```javascript
// 1. Récupération des données via store.prefillData
watch(
  () => store.isCreateModalOpen,
  async (isOpen) => {
    if (isOpen && store.prefillData) {
      isPrefilling.value = true;

      // 2. Remplissage basique des champs
      Object.keys(store.prefillData).forEach((key) => {
        if (formData.value.hasOwnProperty(key)) {
          formData.value[key] = store.prefillData[key];
        }
      });

      // 3. ✅ Restauration du devis visuel
      if (store.prefillData.quote_lines) {
        store.quotePreview = {
          montant_total: store.prefillData.montant_total,
          lignes_devis: store.prefillData.quote_lines,
        };
      }
    }
  },
);
```

### 4.2 Reconstruction des quantités (currentExperienceConfig watcher)

```javascript
watch(currentExperienceConfig, (newConfig) => {
  const savedLines = store.prefillData?.quote_lines || [];

  // ❌ PROBLÈME : Matching approximatif par label
  newConfig.lines.forEach((line, index) => {
    if (line.enable_qty) {
      const cleanLabel = line.label.trim().toLowerCase();
      const foundSaved = savedLines.find((sl) => {
        return sl.label.toLowerCase().includes(cleanLabel);
      });

      if (foundSaved) {
        // ❌ PROBLÈME : Extraction fragile de la quantité
        let savedQty = Number(foundSaved.qty);
        if (!savedQty) {
          const match = foundSaved.label.match(/^(\d+)/);
          savedQty = match ? parseInt(match[1], 10) : 1;
        }
        formData.value.customQty[key] = savedQty;
      }
    }
  });
});
```

**❌ PROBLÈMES identifiés :**

1. **Matching fragile** : Recherche par label textuel peut échouer
2. **Extraction qty** : Regex `^(\d+)` ne couvre pas tous les formats
3. **Pas de UID stable** : `line_${index}` peut changer selon l'ordre ACF

---

## 5. Cas de Figure et Problèmes Spécifiques

### 5.1 Logement créé depuis le Front-end ✅

- **Origine** : 'site'
- **Sauvegarde** : Orchestrateur standard
- **Pré-remplissage** : Fonctionnel (pas de customQty/options)

### 5.2 Logement créé manuellement ✅

- **Origine** : 'manuel'
- **Sauvegarde** : Via Dashboard
- **Pré-remplissage** : Fonctionnel (pas de customQty/options)

### 5.3 Expérience créée depuis le Front-end ❌

- **Origine** : 'site'
- **Problème** : `experience_tarif_type` stocké comme clé technique
- **Impact** : Vue.js retrouve le tarif, mais customQty/options perdues

### 5.4 Expérience créée manuellement ❌❌

- **Origine** : 'manuel'
- **Problème 1** : `experience_tarif_type` peut être un label brut
- **Problème 2** : customQty/options perdues
- **Impact** : Vue.js ne retrouve même pas le tarif de base

---

## 6. Solutions Recommandées

### 6.1 Enrichir ajax_get_reservation_details()

**Ajouter un nouveau champ `structured_experience_data` :**

```php
public static function ajax_get_reservation_details() {
  // ... code existant ...

  $experience_data = null;
  if ($resa->type === 'experience' && !empty($resa->detail_tarif)) {
    $experience_data = self::reconstruct_experience_structure($resa);
  }

  wp_send_json_success([
    // ... données existantes ...
    'structured_experience_data' => $experience_data
  ]);
}

private static function reconstruct_experience_structure($resa) {
  $quote_lines = json_decode($resa->detail_tarif, true) ?: [];
  $item_id = $resa->item_id;

  // 1. Résoudre le tarif_type (mapping label -> clé technique)
  $resolved_tarif_type = self::resolve_tarif_type($item_id, $resa->experience_tarif_type);

  // 2. Reconstruire customQty et options basé sur quote_lines
  $customQty = [];
  $options = [];

  $acf_config = self::get_acf_experience_config($item_id, $resolved_tarif_type);

  foreach ($quote_lines as $line) {
    // Mapping intelligent vers les UIDs ACF
    $mapped = self::map_quote_line_to_acf($line, $acf_config);
    if ($mapped) {
      if ($mapped['type'] === 'line') {
        $customQty[$mapped['uid']] = $line['qty'] ?? 1;
      } else if ($mapped['type'] === 'option') {
        $options[$mapped['uid']] = [
          'selected' => true,
          'qty' => $line['qty'] ?? 1
        ];
      }
    }
  }

  return [
    'resolved_tarif_type' => $resolved_tarif_type,
    'customQty' => $customQty,
    'options' => $options
  ];
}
```

### 6.2 Améliorer BookingForm.vue

**Utiliser les données structurées pour pré-remplir :**

```javascript
watch(
  () => store.isCreateModalOpen,
  async (isOpen) => {
    if (isOpen && store.prefillData) {
      // ... pré-remplissage existant ...

      // ✅ NOUVEAU : Utiliser les données structurées
      if (store.prefillData.structured_experience_data) {
        const expData = store.prefillData.structured_experience_data;

        // Forcer le bon tarif_type
        formData.value.experience_tarif_type = expData.resolved_tarif_type;

        // Attendre que currentExperienceConfig se mette à jour
        await nextTick();

        // Restaurer directement customQty et options
        formData.value.customQty = { ...expData.customQty };
        formData.value.options = { ...expData.options };
      }
    }
  },
);
```

### 6.3 Standardiser la sauvegarde experience_tarif_type

**Dans PCR_Booking_Payload_Normalizer :**

```php
// Toujours stocker la clé technique, jamais le label
if ($context['type'] === 'experience' && !empty($item['experience_tarif_type'])) {
  $item['experience_tarif_type'] = self::normalize_tarif_type_to_key(
    $item['item_id'],
    $item['experience_tarif_type']
  );
}
```

---

## 7. Colonnes/Clés manquantes identifiées

### 7.1 Dans ajax_get_reservation_details()

**❌ Manque actuellement :**

- `structured_experience_data` : Données reconstruites pour Vue.js
- `resolved_tarif_type` : Clé technique normalisée du tarif
- `customQty_mapping` : Mapping quote_lines → UIDs ACF
- `options_mapping` : Reconstruction des options sélectionnées

### 7.2 Dans la table pc_reservations

**✅ Colonnes existantes suffisantes :**

- `experience_tarif_type` : Stockage du type de tarif
- `detail_tarif` : JSON des lignes détaillées
- `origine` : Différentiation Front/Manuel

**💡 Amélioration suggérée :**

- Ajouter une colonne `experience_config_snapshot` pour stocker la config ACF au moment de la réservation (évite les problèmes si les tarifs ACF changent)

---

## 8. Plan d'Action

### Phase 1 : Correction Immédiate

1. ✅ Enrichir `ajax_get_reservation_details()` avec `structured_experience_data`
2. ✅ Créer les méthodes de reconstruction intelligente
3. ✅ Modifier BookingForm.vue pour utiliser les données structurées

### Phase 2 : Standardisation

1. Normaliser le stockage `experience_tarif_type` (toujours clé technique)
2. Ajouter des tests de régression pour les 4 cas de figure
3. Documentation des formats de données

### Phase 3 : Robustesse

1. Ajouter `experience_config_snapshot` en BDD
2. Système de migration pour corriger les anciennes réservations
3. Interface d'alerte si reconstruction impossible

---

## Conclusion

Le problème de pré-remplissage est dû à un **manque de données structurées** dans la réponse AJAX. Les `quote_lines` contiennent l'information, mais pas dans un format directement exploitable par Vue.js.

La solution principale consiste à **enrichir `ajax_get_reservation_details()`** avec une reconstruction intelligente des données d'expérience, permettant à BookingForm.vue de restaurer fidèlement les quantités et options.

**Complexité estimée** : Moyenne (2-3 jours de développement)
**Impact** : Résolution complète du problème de pré-remplissage des expériences

---

## 9. Audit Post-Phase 1 : Données BDD vs Payload Front-End

### 9.1 État actuel des données transmises

**Situation analysée** : Après implémentation de la Phase 1, analyse du payload complet envoyé par `ajax_get_reservation_details()` au composant Vue pour une **expérience**.

#### Structure actuelle du payload (EXPÉRIENCE) :

```json
{
  // Données de base
  "id": 123,
  "client_email": "client@email.com",
  "client_phone": "+33123456789",
  "client_lang": "fr",
  "client_message": "Commentaire du client",
  "notes_internes": "Notes internes",
  "occupants": "2 adulte(s) - 1 enfant(s)",
  "source": "direct",
  "montant_total": 350.00,
  "total_paye": 100.00,
  "total_du": 250.00,
  "payments": [...],
  "quote_lines": [...],

  // Données de caution (❌ INUTILES pour une expérience)
  "caution": {
    "mode": "aucune",
    "statut": "non_demande",
    "montant": 0,
    "reference": ""
  },

  // 🆕 Nouvelles données structurées (Phase 1)
  "structured_experience_data": {
    "resolved_tarif_type": "forfait",
    "customQty": { "line_0_0": 2 },
    "options": { "opt_0_1": { "selected": true, "qty": 1 } }
  },

  // Données brutes pour formulaire
  "raw_type": "experience",
  "raw_item_id": 456,
  "raw_tarif_type": "forfait",

  // ❌ DONNÉES LOGEMENT INUTILES pour une expérience
  "raw_date_arrivee": "", // Vide mais présent
  "raw_date_depart": "",  // Vide mais présent

  // ✅ DONNÉES EXPÉRIENCE PERTINENTES
  "raw_date_experience": "2024-08-15",
  "raw_adultes": 2,
  "raw_enfants": 1,
  "raw_bebes": 0,

  // Données client
  "raw_prenom": "Jean",
  "raw_nom": "Dupont",
  "raw_numero_devis": "DEV-2024-001",
  "raw_remise_label": "Remise exceptionnelle",
  "raw_remise_montant": 50.00,
  "raw_plus_label": "Plus-value",
  "raw_plus_montant": 0.00
}
```

### 9.2 Problèmes identifiés

#### ❌ **Pollution du Payload - Données Inutiles pour Expériences** :

1. **Caution** : Les expériences n'ont jamais de caution, mais le payload contient :
   - `caution.mode`, `caution.statut`, `caution.montant`, `caution.reference`

2. **Dates Logement** : Pour une expérience, ces champs sont vides mais présents :
   - `raw_date_arrivee`, `raw_date_depart`

#### ✅ **Données Manquantes pour Logements** :

Actuellement, pour un **logement**, le payload ne contient pas :

- `structured_logement_data` : Données spécifiques aux logements
- Informations sur les périodes de prix (haute/basse saison)
- Configuration des frais fixes (ménage, électricité, etc.)

#### 📊 **Données BDD vs Front** :

| Colonne BDD             | Présent dans Payload     | Type Expérience | Type Logement | Remarque  |
| ----------------------- | ------------------------ | --------------- | ------------- | --------- |
| `experience_tarif_type` | ✅ `raw_tarif_type`      | ✅ Nécessaire   | ❌ Inutile    | -         |
| `date_experience`       | ✅ `raw_date_experience` | ✅ Nécessaire   | ❌ Inutile    | -         |
| `date_arrivee`          | ✅ `raw_date_arrivee`    | ❌ Inutile      | ✅ Nécessaire | Pollution |
| `date_depart`           | ✅ `raw_date_depart`     | ❌ Inutile      | ✅ Nécessaire | Pollution |
| `caution_*`             | ✅ `caution.*`           | ❌ Inutile      | ✅ Nécessaire | Pollution |

### 9.3 Impact Performance

**Taille Payload Actuel** : ~2-3KB par réservation
**Pollution Estimée** : 15-20% de données inutiles selon le type

**Problèmes** :

- Bande passante gaspillée
- Parsing JSON inutile côté front
- Confusion développeur (quelle donnée utiliser ?)
- Logique conditionnelle complexe côté Vue

### 9.4 Plan de Nettoyage Recommandé

#### **Phase 2A : Scission des Payloads**

**Objectif** : Créer deux payloads distincts et épurés selon le type de réservation.

**Implémentation** :

```php
// Dans ajax_get_reservation_details()
if ($resa->type === 'experience') {
    $payload = self::build_experience_payload($resa);
} else {
    $payload = self::build_location_payload($resa);
}
```

#### **Payload Expérience Épuré** :

```json
{
  // Données communes
  "id": 123,
  "client_email": "...",
  "client_phone": "...",
  "montant_total": 350.00,
  "payments": [...],
  "quote_lines": [...],

  // Spécifique EXPÉRIENCE
  "structured_experience_data": {...},
  "raw_type": "experience",
  "raw_item_id": 456,
  "raw_tarif_type": "forfait",
  "raw_date_experience": "2024-08-15",
  "raw_adultes": 2,
  "raw_enfants": 1,
  "raw_bebes": 0,

  // Données communes formulaire
  "raw_prenom": "Jean",
  "raw_nom": "Dupont",
  // ... autres champs communs
}
```

#### **Payload Logement Épuré** :

```json
{
  // Données communes (identiques)
  "id": 123,
  "client_email": "...",

  // Spécifique LOGEMENT
  "caution": {
    "mode": "cheque",
    "statut": "demande",
    "montant": 500.0,
    "reference": "CHQ-2024-001"
  },
  "raw_type": "location",
  "raw_item_id": 789,
  "raw_date_arrivee": "2024-08-10",
  "raw_date_depart": "2024-08-17",
  "capacity_info": {
    "max_guests": 6,
    "bedrooms": 3
  },

  // Données communes formulaire
  "raw_prenom": "Marie",
  "raw_nom": "Martin"
  // ... autres champs communs
}
```

#### **Phase 2B : Méthodes Dédiées**

```php
private static function build_experience_payload($resa) {
    $base = self::build_base_payload($resa);

    return array_merge($base, [
        'structured_experience_data' => self::reconstruct_experience_structure($resa),
        'raw_tarif_type' => $resa->experience_tarif_type ?? '',
        'raw_date_experience' => $resa->date_experience ?? '',
        'raw_adultes' => (int)($resa->adultes ?? 1),
        'raw_enfants' => (int)($resa->enfants ?? 0),
        'raw_bebes' => (int)($resa->bebes ?? 0),
    ]);
}

private static function build_location_payload($resa) {
    $base = self::build_base_payload($resa);

    return array_merge($base, [
        'caution' => self::build_caution_data($resa),
        'raw_date_arrivee' => $resa->date_arrivee ?? '',
        'raw_date_depart' => $resa->date_depart ?? '',
        'capacity_info' => self::get_location_capacity($resa->item_id),
    ]);
}

private static function build_base_payload($resa) {
    return [
        'id' => $resa->id,
        'client_email' => $resa->email,
        'client_phone' => $resa->telephone,
        'montant_total' => (float)$resa->montant_total,
        'raw_type' => $resa->type ?? 'location',
        'raw_item_id' => (int)$resa->item_id,
        'raw_prenom' => $resa->prenom ?? '',
        'raw_nom' => $resa->nom ?? '',
        // ... autres champs communs
    ];
}
```

### 9.5 Bénéfices Attendus

**Performance** :

- Réduction 15-20% taille payload
- Parsing plus rapide côté front
- Moins de transfert réseau

**Maintenabilité** :

- Code plus lisible et spécialisé
- Logique métier séparée
- Tests unitaires plus faciles

**Évolutivité** :

- Ajout facile de champs spécifiques
- Versioning API plus simple
- Support de nouveaux types à l'avenir

### 9.6 Plan d'Implémentation Recommandé

1. **Étape 1** : Créer les méthodes `build_*_payload()`
2. **Étape 2** : Modifier `ajax_get_reservation_details()` avec switch
3. **Étape 3** : Tests de régression (expériences + logements)
4. **Étape 4** : Optimisation côté Vue (suppression des conditions inutiles)
5. **Étape 5** : Documentation des nouveaux formats

**Estimation** : 1-2 jours de développement + tests
**Risque** : Faible (pas de breaking change, ajout de fonctionnalité)
