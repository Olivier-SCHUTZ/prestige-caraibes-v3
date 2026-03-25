# RAPPORT DE MIGRATION : D'ACF PRO VERS SYSTÈME NATIF (VUE.JS / PHP)

_Basé sur l'audit des logements finalisés - Mars 2025_

## 1. Architecture Technique Validée

### A. Le Cœur du Système : La Lecture Native

- ✅ **Interdiction formelle** d'utiliser `get_field()`, `have_rows()`, ou toute fonction native d'ACF
- ✅ **Méthode unique** : `$valeur = PCR_Fields::get('nom_du_champ', $post_id);`
- ✅ **Performance** : ACF désactivé sur le front-end

### B. Définition des Champs : PCR_Field_Manager

- ✅ **Fichier de référence** : `plugins/pc-reservation-core/includes/fields/definitions/housing-fields.php`
- ✅ **Méthode** : `$manager = PCR_Field_Manager::init();` puis `$manager->register_field_group()`
- ✅ **Organisation** : Champs groupés par thématique (média, SEO, détails, équipements, etc.)

## 2. Le Décodeur Universel (Problématique Critique)

### A. Triple Format Hybride

Les données dans `wp_postmeta.meta_value` peuvent être :

1. **Chaîne de texte simple**
2. **Tableau sérialisé PHP** (héritage ACF)
3. **Chaîne JSON** (nouvelle méthode Vue.js)

### B. Règle d'Or : Le Décodeur Obligatoire

```php
// BLOC DE SÉCURITÉ ABSOLUE - À utiliser systématiquement
$raw_data = PCR_Fields::get('nom_du_champ', $post_id);
if (is_string($raw_data)) {
    $decoded = json_decode($raw_data, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $raw_data = $decoded; // C'était du JSON
    } else {
        $raw_data = maybe_unserialize($raw_data); // C'était du sérialisé
    }
}
// Sécurisation finale : forcer en tableau propre
$final_array = is_array($raw_data) ? $raw_data : (empty($raw_data) ? [] : [$raw_data]);
```

### C. Exemple Concret : Traitement des Saisons

**Fichier** : `mu-plugins/pc-logement/shortcodes/class-pc-devis-shortcode.php` ligne ~200+

```php
// RECONSTRUCTEUR ACF (ancien format numérique)
if (is_numeric($raw_seasons) && $raw_seasons > 0) {
    $reconstructed = [];
    for ($i = 0; $i < intval($raw_seasons); $i++) {
        $prefix = "pc_season_blocks_{$i}_";
        // Reconstruction manuelle des sous-champs...
    }
    $raw_seasons = $reconstructed;
}
// PUIS Décodeur JSON/Sérialisé
elseif (is_string($raw_seasons)) {
    $decoded = json_decode($raw_seasons, true);
    $raw_seasons = (json_last_error() === JSON_ERROR_NONE) ? $decoded : maybe_unserialize($raw_seasons);
}
```

## 3. Cas Spécifiques Validés

### A. Format Vue.js "Objets avec Label"

- **Problème** : `[{'value' => 'adulte', 'label' => 'Adulte'}]`
- **Solution** : Extraire `$item['value']` avant envoi au JS
- **Localisation** : Fonction `get_devis_config()` dans le shortcode devis

### B. Aspirateur Multi-Niveaux

**Exemple** : Taxe de séjour avec extraction récursive

```php
$taxe_choices = [];
if (is_array($taxe_raw)) {
    array_walk_recursive($taxe_raw, function ($value, $key) use (&$taxe_choices) {
        // Récupération agressive des valeurs enfouies
        if (is_string($key) && !is_numeric($key)) {
            $taxe_choices[] = $key;
        }
        if (is_string($value) && !empty(trim($value))) {
            $taxe_choices[] = trim($value);
        }
    });
}
```

## 4. Gestion des "Groupes" ACF Explosés

### A. Principe de Base

- **Ancien ACF** : Stockage éclaté (`regles_de_paiement_pc_pay_mode`, `regles_de_paiement_pc_deposit_type`)
- **Nouveau Vue.js** : Objet groupé envoyé au backend

### B. Sauvegarde Backend (Déballage d'Objets)

**Fichier** : `plugins/pc-reservation-core/includes/ajax/controllers/class-housing-ajax-controller.php`

```php
// Réception Vue.js : champs préfixés 'acf_'
foreach ($_POST as $key => $value) {
    if (strpos($key, 'acf_') === 0) {
        $clean_key = substr($key, 4);
        $data[$clean_key] = $value;
    }
}
```

### C. Lecture des Règles de Paiement

```php
private function get_payment_rules($post_id)
{
    // Tentative de lecture groupée
    $pay_rules = PCR_Fields::get('regles_de_paiement', $post_id);
    if (is_string($pay_rules)) $pay_rules = json_decode($pay_rules, true) ?: [];

    // Retour avec clés individuelles (compatibilité)
    return [
        'mode'         => $pay_rules['pc_pay_mode'] ?? 'acompte_plus_solde',
        'deposit_type' => $pay_rules['pc_deposit_type'] ?? 'pourcentage',
        'deposit_val'  => floatval($pay_rules['pc_deposit_value'] ?? 30),
        'delay_days'   => intval($pay_rules['pc_balance_delay_days'] ?? 30),
    ];
}
```

## 5. Whitelist Obligatoire

- **Fichier de référence** : `housing-fields.php` (champs autorisés)
- **Règle** : Tous les champs doivent être déclarés dans le Field Manager
- **Sous-champs** : Les clés enfants des groupes ACF doivent être listées individuellement

## 6. Architecture JavaScript

**Fichier** : `mu-plugins/pc-logement/assets/js/modules/pc-devis-calculator.js`

- ✅ **Classe pure** : `PCDevisCalculator` (moteur mathématique)
- ✅ **Séparation** : Ne manipule jamais le DOM
- ✅ **Scanner intelligent** : Gère les formats hybrides Vue.js ("object Object", "non_classe", étoiles)

## 7. Points de Vigilance pour les Expériences

1. **Répéteurs ACF** → Reconstruction numérique obligatoire
2. **Champs complexes** → Décodeur universel systématique
3. **Groupes explosés** → Lecture individuelle des sous-clés
4. **Sauvegarde Vue.js** → Déballage d'objets côté backend
5. **Whitelist** → Déclaration complète dans experience-fields.php
