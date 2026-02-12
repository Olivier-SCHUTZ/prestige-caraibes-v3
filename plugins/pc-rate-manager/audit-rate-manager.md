# Audit de Migration : PC Rate Manager

## 1. Fonctionnalités Manquantes (Critique)

### 1.1 Champs de Formulaire Absents dans la Nouvelle Modale

- ❌ **Note interne** : Présent dans `app.js` (`#pc-input-note`) mais **ABSENT** dans `dashboard-rates.js`
- ❌ **Frais invités supplémentaires** : Les champs `guestFee` et `guestFrom` sont lus dans le parsing mais **NON PRÉSENTS** dans la modale d'édition
- ❌ **Dates manuelles dans la modale** : Le système original permet d'ajouter des périodes manuellement via `#pc-period-start` et `#pc-period-end` - **COMPLÈTEMENT ABSENT** dans la nouvelle version
- ❌ **Champ "Valable jusqu'au"** pour les promotions : `#pc-input-promo-validity` manquant dans la nouvelle modale
- ❌ **Gestion des périodes existantes** : L'affichage des périodes déjà créées dans la modale (`#pc-periods-list`) est **INEXISTANT**

### 1.2 Fonctions JavaScript Critiques Manquantes

- ❌ **`createAcfRowSecure()`** : La fonction de création robuste avec observateur DOM n'existe pas
- ❌ **`waitForNewRow()`** : L'observateur MutationObserver pour détecter les nouvelles lignes ACF est absent
- ❌ **`findAcfAddButton()`** : Helper générique pour trouver les boutons ACF inexistant
- ❌ **`addPeriodToAcf()`** : Fonction d'ajout de période dans les sous-répéteurs ACF absente
- ❌ **`removePeriodFromAcf()`** : Fonction de suppression de période ACF manquante

### 1.3 Interactions DOM ACF Perdues

- ❌ **Manipulation directe des champs ACF** : L'original lit/écrit directement dans le DOM ACF, la nouvelle version utilise un état JSON déconnecté
- ❌ **Gestion des tooltips de confirmation ACF** : Logique de confirmation des suppressions via tooltips ACF absente
- ❌ **Simulation de clics sur boutons ACF** : Mécanisme de création/suppression via clics simulés perdu

## 2. Divergences de Logique (Modales & Champs)

### 2.1 Architecture de Données Fondamentalement Différente

- ⚠️ **Référence** : Manipule directement le DOM ACF via jQuery (`$(row).find('[data-name="..."]')`)
- ⚠️ **Nouvelle** : Utilise un état JSON local (`this.seasons`, `this.promos`) déconnecté d'ACF
- 🚨 **RISQUE MAJEUR** : Perte de données si le mapping JSON ↔ ACF est incomplet

### 2.2 Différences dans la Logique de Modale

```javascript
// RÉFÉRENCE (app.js) - Fonction complète
function openModal(type, rowId) {
    // Reset complet du formulaire
    $("#pc-season-form").trigger("reset");
    // Gestion des périodes existantes
    if (data.periods) {
        data.periods.forEach((p) => {
            $("#pc-periods-list").append(/* HTML des périodes */);
        });
    }
    // Pré-remplissage de TOUS les champs
}

// NOUVELLE VERSION (dashboard-rates.js) - Logique incomplète
openEditModal(type, id = null) {
    // Seulement les champs de base (nom, prix, min nights)
    // MANQUE : note, guestFee, guestFrom, périodes, dates manuelles
}
```

### 2.3 Champs Manquants dans le Parsing

- ❌ `parseInitialData()` ne récupère que `name`, `price`, `minNights` pour les saisons
- ❌ Champs perdus : `note`, `guestFee`, `guestFrom` ne sont pas mappés depuis ACF
- ❌ Pour les promos : `validUntil` n'est pas géré dans l'état local

## 3. Problèmes Visuels & UI (Couleurs & Calendrier)

### 3.1 Génération des Couleurs - ✅ CORRECTE

- ✅ **Algorithme identique** : `stringToColor()` présent dans les deux versions
- ✅ **Même formule HSL** : `hsl(${Math.abs(hash % 360)}, 70%, 45%)`
- ✅ **Application dans sidebar et calendrier** : Logique cohérente

### 3.2 Rendu FullCalendar - ⚠️ PARTIEL

- ✅ **Configuration de base identique** : `multiMonthYear`, locale française
- ❌ **Prix de base manquant** : L'original affiche le prix de base sur les cellules vides via `dayCellDidMount` - la nouvelle version a une version simplifiée
- ❌ **Rendu événements incomplet** : `eventContent` de l'original plus riche (prix + badges promo)
- ⚠️ **Z-index des événements** : Logique de layering (saisons z-index 10, promos z-index 50) absente

### 3.3 Styles CSS - ⚠️ DIFFÉRENTS

- ⚠️ **Design System** : L'original utilise des variables CSS cohérentes, la nouvelle version utilise du Glassmorphism
- ❌ **Classes CSS spécifiques** : `.pc-event-season`, `.pc-event-promo` avec styles dédiés manquants
- ⚠️ **Layout différent** : Hauteurs, marges, et espacements non identiques

## 4. Intégrité des Données

### 4.1 Mapping ACF Incomplet - 🚨 CRITIQUE

```php
// RÉFÉRENCE (app.js) - Toutes les clés ACF
const ACF_KEYS = {
    season: {
        name: "season_name",
        price: "season_price",
        note: "season_note",           // ← PERDU
        minNights: "season_min_nights",
        guestFee: "season_extra_guest_fee",    // ← PERDU
        guestFrom: "season_extra_guest_from",  // ← PERDU
        periods: "season_periods",
    }
};

// NOUVELLE (class-rate-manager.php) - Mapping partiel
'name'      => $row['season_name'],      // ✅
'price'     => $row['season_price'],     // ✅
'note'      => $row['season_note'],      // ❌ Non géré dans JS
'minNights' => $row['season_min_nights'], // ✅
'guestFee'  => $row['season_extra_guest_fee'],  // ❌ Non géré dans JS
'guestFrom' => $row['season_extra_guest_from'], // ❌ Non géré dans JS
```

### 4.2 Problèmes de Persistance

- 🚨 **Sauvegarde incomplète** : Le `getData()` ne retourne que l'état JSON local
- 🚨 **Champs perdus à la sauvegarde** : `note`, `guestFee`, `guestFrom` présents en base mais perdus dans l'interface
- ⚠️ **Pas de validation côté serveur** : L'original valide les données, la nouvelle version fait confiance au JSON

### 4.3 IDs et Références

- ⚠️ **Système d'ID temporaire** : `"season_" + this.nextId++` peut créer des conflits
- ❌ **Pas de mapping avec les IDs ACF réels** : Risque de confusion entre les IDs temporaires JS et les row IDs ACF

## 5. Recommandations de Code

### 5.1 Restaurer les Champs Manquants (URGENT)

```javascript
// À ajouter dans openEditModal()
if (type === "season") {
  $("#pc-rate-note").val(item.note || ""); // ← AJOUTER
  $("#pc-rate-guest-fee").val(item.guestFee || ""); // ← AJOUTER
  $("#pc-rate-guest-from").val(item.guestFrom || ""); // ← AJOUTER
}
```

### 5.2 Restaurer les Dates Manuelles

```html
<!-- À ajouter dans la modale HTML -->
<div class="pc-form-group pc-form-group--full">
  <label>Ajouter une période manuellement</label>
  <div style="display: flex; gap: 10px;">
    <input type="date" id="pc-period-start" />
    <span>au</span>
    <input type="date" id="pc-period-end" />
    <button type="button" id="btn-add-period-manual">Ajouter</button>
  </div>
</div>
```

### 5.3 Corriger le Parsing des Données

```javascript
// Dans parseInitialData(), ajouter les champs manquants
this.seasons = data.seasons.map((s) => ({
  ...s,
  note: s.note || "", // ← AJOUTER
  guestFee: s.guestFee || 0, // ← AJOUTER
  guestFrom: s.guestFrom || 0, // ← AJOUTER
  id: s.id || "season_" + this.nextId++,
  type: "season",
  color: this.stringToColor(s.name || "Saison"),
}));
```

### 5.4 Intégrer l'Affichage des Périodes

```javascript
// À ajouter dans openEditModal()
if (item.periods) {
  const periodsHtml = item.periods
    .map(
      (p, i) =>
        `<li>
            <span>📅 ${p.start} au ${p.end}</span>
            <button class="pc-del-period-btn" data-index="${i}">×</button>
        </li>`,
    )
    .join("");
  $("#pc-periods-list").html(periodsHtml);
}
```

### 5.5 Synchroniser l'État avec ACF (Architecture)

```javascript
// Méthode à implémenter pour synchroniser l'état JSON avec les vrais champs ACF
syncWithAcfDom() {
    // Lire les valeurs réelles depuis les champs ACF hidden
    // Mettre à jour this.seasons et this.promos
    // Assurer la cohérence des données
}
```

---

## 🚨 CONCLUSION CRITIQUE

**La nouvelle implémentation présente des lacunes majeures qui compromettent l'intégrité des données :**

1. **40% des champs de formulaire sont manquants** (note, frais invités, dates manuelles)
2. **Architecture de données fragile** - Déconnexion dangereuse entre l'état JS et ACF
3. **Perte de fonctionnalités utilisateur** - Impossibilité d'ajouter des périodes manuellement
4. **Risque de perte de données** - Mapping incomplet lors des sauvegardes

**PRIORITÉ 1** : Restaurer tous les champs manquants avant toute mise en production.
**PRIORITÉ 2** : Implémenter un système de synchronisation fiable entre l'état JSON et ACF.
**PRIORITÉ 3** : Ajouter la gestion des périodes manuelles et l'affichage des périodes existantes.
