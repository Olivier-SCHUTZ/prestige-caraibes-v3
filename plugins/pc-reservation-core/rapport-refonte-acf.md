# 📋 Rapport de Refonte ACF - Migration vers PC Reservation Core

**Date :** 19/03/2026  
**Version :** 1.0  
**Objectif :** Remplacer ACF Pro par une solution native dans pc-reservation-core

---

## 🔍 État des lieux actuel

### Problèmes identifiés

1. **Dépendance critique à ACF Pro**
   - Licence payante obligatoire
   - Plus de 200 occurrences de `get_field()` dans le code
   - Point de défaillance unique pour tout le système

2. **Architecture dispersée et complexe**
   - Champs répartis entre `mu-plugins/pc-acf-json/` et `pc-reservation-core`
   - Doublons et incohérences dans les noms de champs
   - Maintenance difficile avec les fichiers JSON

3. **Gestion des champs problématique**
   - Export/import JSON fragile
   - Pas de versioning des structures
   - Difficile de déboguer les problèmes de champs

### Groupes ACF actuels analysés

#### 📊 Groupe Principal : `group_pc_fiche_logement` (87 champs)

- **Onglets :** 13 sections (Média, SEO, Détails, Équipements, etc.)
- **Complexité :** Très élevée avec des répéteurs imbriqués
- **Usage :** Villas et appartements (types les plus critiques)

#### 🏖️ Groupe Destinations : `group_pc_destination` (25 champs)

- **Sections :** Infos principales, textes, SEO
- **Complexité :** Moyenne
- **Répéteurs :** FAQ, infos pratiques, recommandations

#### 🎯 Groupe Expériences : `group_66dcc7e9c5a16` (30+ champs)

- **Sections :** SEO, détails, inclusions, tarifs, FAQ
- **Complexité :** Élevée avec tarifs et horaires

#### 📄 Autres groupes

- Pages SEO : `group_pc-pages-seo-structure`
- Reviews : `group_pc_reviews`
- Configuration globale : `group_pc_seo_global`

---

## 💡 Solution Proposée : Migration Progressive

### Phase 1 : Infrastructure Native (2-3 semaines)

#### Création du système de champs natifs

```php
// Nouvelle architecture dans pc-reservation-core
includes/
├── fields/
│   ├── class-field-manager.php          // Gestionnaire principal
│   ├── class-field-registry.php         // Registre des champs
│   ├── class-field-renderer.php         // Interface d'affichage
│   ├── types/
│   │   ├── class-text-field.php         // Champ texte
│   │   ├── class-textarea-field.php     // Zone de texte
│   │   ├── class-select-field.php       // Liste déroulante
│   │   ├── class-checkbox-field.php     // Cases à cocher
│   │   ├── class-repeater-field.php     // Répéteur
│   │   ├── class-image-field.php        // Images
│   │   └── class-date-field.php         // Dates
│   └── definitions/
│       ├── housing-fields.php           // Définitions logements
│       ├── experience-fields.php        // Définitions expériences
│       └── destination-fields.php       // Définitions destinations
```

#### API unifiée de récupération

```php
// Remplacement progressif de get_field()
PCR_Fields::get('hero_desktop_url', $post_id);
PCR_Fields::get('base_price_from', $post_id, 0); // avec défaut
```

### Phase 2 : Migration Logements (3-4 semaines)

#### Priorité aux champs critiques

1. **Champs pricing** (base_price_from, unite_de_prix, etc.)
2. **Champs media** (hero_desktop_url, hero_mobile_url, gallery_urls)
3. **Champs réservation** (identifiant_lodgify, ical_url)
4. **Champs détails** (capacite, superficie, nombre_de_chambres)

#### Migration progressive avec compatibilité

```php
class PCR_Field_Migration {
    public static function get_field($key, $post_id, $fallback_acf = true) {
        // 1. Essayer le nouveau système
        $value = self::get_native_field($key, $post_id);

        // 2. Fallback ACF si activé et valeur vide
        if ($value === null && $fallback_acf && function_exists('get_field')) {
            $value = get_field($key, $post_id);
        }

        return $value;
    }
}
```

### Phase 3 : Expériences & Destinations (2-3 semaines)

#### Extension du système aux autres types

- Migration des champs expériences (tarifs, lieux, FAQ)
- Migration des champs destinations (infos, recommandations)
- Interface d'administration native intégrée au dashboard

### Phase 4 : Finalisation (1-2 semaines)

#### Suppression d'ACF

- Migration complète de tous les `get_field()`
- Tests approfondis
- Désactivation d'ACF Pro
- Documentation pour l'équipe

---

## ⚡ Avantages de cette solution

### 1. Indépendance totale

- ✅ Plus de dépendance à ACF Pro
- ✅ Contrôle total sur les champs
- ✅ Économie de licence

### 2. Performance améliorée

- ✅ Queries optimisées pour vos besoins
- ✅ Moins d'overhead que ACF
- ✅ Cache native possible

### 3. Interface unifiée

- ✅ Intégration parfaite avec votre dashboard Vue.js
- ✅ UX cohérente avec le reste du système
- ✅ API REST native pour le frontend

### 4. Maintenance simplifiée

- ✅ Versioning des structures de champs
- ✅ Migration de données automatisée
- ✅ Debugging facilité

---

## 🛠️ Plan d'implémentation technique

### Étape 1 : Créer le Field Manager

```php
// includes/fields/class-field-manager.php
class PCR_Field_Manager {
    private static $instance = null;
    private $fields = [];

    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->setup_hooks();
        }
        return self::$instance;
    }

    public function register_field_group($group_id, $config) {
        $this->fields[$group_id] = $config;
    }

    public function get_field($key, $post_id, $default = null) {
        // Logique de récupération optimisée
    }

    public function save_field($key, $post_id, $value) {
        // Logique de sauvegarde optimisée
    }
}
```

### Étape 2 : Définir les champs logements

```php
// includes/fields/definitions/housing-fields.php
PCR_Field_Manager::register_field_group('housing_media', [
    'title' => 'Média & Galerie',
    'post_types' => ['villa', 'appartement'],
    'position' => 'after_title',
    'fields' => [
        'hero_desktop_url' => [
            'type' => 'image',
            'label' => 'Photo principale (Desktop)',
            'return_format' => 'url',
            'width' => '50%'
        ],
        'hero_mobile_url' => [
            'type' => 'image',
            'label' => 'Photo principale (Mobile)',
            'return_format' => 'url',
            'width' => '50%'
        ],
        // ... autres champs
    ]
]);
```

### Étape 3 : Interface d'administration

```php
// Integration avec votre dashboard Vue.js existant
class PCR_Field_Admin_Controller extends PCR_Base_Ajax_Controller {
    public function get_housing_fields() {
        $post_id = $this->get_post('post_id');
        $fields = PCR_Field_Manager::get_all_fields($post_id);

        return $this->json_response([
            'success' => true,
            'fields' => $fields
        ]);
    }

    public function save_housing_fields() {
        $post_id = $this->get_post('post_id');
        $fields = $this->get_post('fields', []);

        foreach ($fields as $key => $value) {
            PCR_Field_Manager::save_field($key, $post_id, $value);
        }

        return $this->json_response(['success' => true]);
    }
}
```

---

## 📋 Planning détaillé

### Semaine 1-2 : Architecture

- [ ] Créer le système de champs natifs
- [ ] Définir l'API unifiée
- [ ] Tests unitaires de base

### Semaine 3-4 : Migration Logements (Partie 1)

- [ ] Migrer les champs pricing critiques
- [ ] Migrer les champs media
- [ ] Compatibilité avec existant

### Semaine 5-6 : Migration Logements (Partie 2)

- [ ] Migrer les équipements et détails
- [ ] Migrer les champs de réservation
- [ ] Interface d'admin native

### Semaine 7-8 : Expériences

- [ ] Migrer les champs expériences
- [ ] Système de tarifs complexe
- [ ] FAQ et répéteurs

### Semaine 9-10 : Destinations & Pages

- [ ] Migrer destinations
- [ ] Migrer pages SEO
- [ ] Configuration globale

### Semaine 11-12 : Finalisation

- [ ] Migration complète des `get_field()`
- [ ] Tests complets
- [ ] Documentation
- [ ] Désactivation ACF

---

## ⚠️ Risques et mitigation

### Risques identifiés

1. **Perte de données** during migration
   - **Mitigation :** Sauvegardes complètes avant chaque étape
2. **Compatibilité** avec les shortcodes existants
   - **Mitigation :** Fonction wrapper `PCR_Fields::get()` avec fallback ACF

3. **Performance** du nouveau système
   - **Mitigation :** Benchmarks et optimisations dès le début

4. **Temps de développement** sous-estimé
   - **Mitigation :** Découpage en petites phases testables

---

## 💰 Estimation budgétaire

### Temps de développement : 60-80 heures

- **Phase 1** (Infrastructure) : 20h
- **Phase 2** (Logements) : 25h
- **Phase 3** (Expériences/Destinations) : 20h
- **Phase 4** (Finalisation) : 15h

### ROI attendu

- **Économie licence ACF** : ~200€/an
- **Maintenance simplifiée** : ~30% de temps en moins
- **Performance améliorée** : ~15-20% plus rapide
- **Flexibilité totale** : Inestimable

---

## ✅ Recommandations

### Immédiat (cette semaine)

1. **Valider l'approche** avec votre équipe
2. **Prioriser les champs** les plus critiques
3. **Faire un backup complet** de la base de données

### Court terme (1 mois)

1. **Commencer par Phase 1** (infrastructure)
2. **Tester sur un environnement de staging**
3. **Documenter chaque étape**

### Moyen terme (2-3 mois)

1. **Migration progressive** par type de contenu
2. **Formation équipe** sur le nouveau système
3. **Désactivation définitive** d'ACF Pro

---

## 🎯 Conclusion

Cette refonte représente un investissement stratégique majeur qui vous donnera :

- **Indépendance technique** totale
- **Performance optimisée** pour vos besoins
- **Maintenance simplifiée** long terme
- **Évolutivité parfaite** avec votre roadmap

Le système pc-reservation-core est déjà bien structuré et cette migration s'intègre parfaitement dans votre architecture existante. C'est le moment idéal pour franchir le cap !

---

**Contact :** [Votre équipe de développement]  
**Dernière mise à jour :** 19/03/2026
