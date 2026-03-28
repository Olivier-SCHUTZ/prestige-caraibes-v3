# Plan de Migration - Destinations vers Dashboard Vue.js V2

## 📋 Contexte et Objectif

Ce document détaille la migration de l'entité "Destinations" d'ACF Pro vers un Dashboard sur mesure en Vue.js 3, suivant exactement la même méthodologie que pour les entités "Logements" et "Expériences".

**Objectifs clés :**

- ✅ **Zéro Régression** : Fonctionnement hybride avec pont PCR_Fields::get()
- ✅ **Sauvegarde 100% native** WordPress dans wp_postmeta
- ✅ **Architecture identique** aux Dashboards existants
- ✅ **Prévention du bug "[object Object]"** via JSON.stringify des répéteurs

---

## 🗂️ Phase 1 : Analyse des Champs ACF Existants

### Champs extraits du fichier `group_pc_destination.json`

#### **Onglet 1 : Infos principales destination**

| Champ ACF                  | Type   | Clé normalisée             | Description                                       |
| -------------------------- | ------ | -------------------------- | ------------------------------------------------- |
| `dest_hero_desktop`        | image  | `dest_hero_desktop`        | Image principale Desktop (ID)                     |
| `dest_hero_mobile`         | image  | `dest_hero_mobile`         | Image principale Mobile (ID)                      |
| `dest_region`              | select | `dest_region`              | Région (Grande-Terre, Basse-Terre, Îles voisines) |
| `dest_geo_lat`             | number | `dest_geo_lat`             | Latitude ville                                    |
| `dest_geo_lng`             | number | `dest_geo_lng`             | Longitude ville                                   |
| `dest_population`          | number | `dest_population`          | Population                                        |
| `dest_surface_km2`         | number | `dest_surface_km2`         | Surface en km²                                    |
| `dest_airport_distance_km` | number | `dest_airport_distance_km` | Distance aéroport                                 |
| `dest_sea_side`            | radio  | `dest_sea_side`            | Type de plage (Caraïbes/Atlantique)               |

#### **Onglet 2 : Infos textes destination**

| Champ ACF                    | Type         | Clé normalisée               | Description                               |
| ---------------------------- | ------------ | ---------------------------- | ----------------------------------------- |
| `dest_h1`                    | text         | `dest_h1`                    | Titre H1 personnalisé                     |
| `dest_intro`                 | wysiwyg      | `dest_intro`                 | Introduction (300-600 mots)               |
| `dest_slogan`                | text         | `dest_slogan`                | Slogan (vignette Hub/menu)                |
| `dest_infos`                 | **repeater** | `dest_infos`                 | **[JSON]** Informations pratiques (blocs) |
| `dest_faq`                   | **repeater** | `dest_faq`                   | **[JSON]** FAQ                            |
| `dest_exp_featured`          | relationship | `dest_exp_featured`          | Expériences mises en avant (max 3)        |
| `dest_logements_recommandes` | relationship | `dest_logements_recommandes` | Logements recommandés (max 3)             |
| `dest_featured`              | true_false   | `dest_featured`              | Mettre en avant (Hub/menu)                |
| `dest_order`                 | number       | `dest_order`                 | Ordre de tri                              |

#### **Onglet 3 : SEO destination**

| Champ ACF               | Type       | Clé normalisée          | Description          |
| ----------------------- | ---------- | ----------------------- | -------------------- |
| `dest_exclude_sitemap`  | true_false | `dest_exclude_sitemap`  | Exclure du sitemap   |
| `dest_http_410`         | true_false | `dest_http_410`         | Servir un 410 Gone   |
| `dest_meta_title`       | text       | `dest_meta_title`       | Titre SEO            |
| `dest_meta_description` | textarea   | `dest_meta_description` | Meta description SEO |
| `dest_meta_canonical`   | url        | `dest_meta_canonical`   | URL canonique        |
| `dest_meta_robots`      | select     | `dest_meta_robots`      | Meta robots          |

### Répéteurs complexes à convertir en JSON

#### **1. `dest_infos` (Informations pratiques)**

```json
[
  {
    "titre": "Transport",
    "contenu": "<p>Informations sur les transports...</p>",
    "icone": "fa-solid fa-bus"
  },
  {
    "titre": "Hébergements",
    "contenu": "<p>Options d'hébergement...</p>",
    "icone": "fa-solid fa-bed"
  }
]
```

#### **2. `dest_faq` (Questions fréquentes)**

```json
[
  {
    "question": "Comment se rendre à cette destination ?",
    "reponse": "<p>Réponse détaillée...</p>"
  },
  {
    "question": "Quels sont les meilleurs sites à visiter ?",
    "reponse": "<p>Liste des sites recommandés...</p>"
  }
]
```

---

## 🏗️ Phase 2 : Architecture des Fichiers

### **Backend PHP (4 fichiers principaux)**

```
plugins/pc-reservation-core/includes/services/destination/
├── class-destination-config.php       # Dictionnaire de mapping ACF
├── destination-fields.php             # Configuration des groupes de champs
├── class-destination-repository.php   # Lecture des données (PCR_Fields::get)
└── class-destination-service.php      # Sauvegarde native (wp_postmeta)
```

### **Frontend Vue.js (Module et Composants)**

```
plugins/pc-reservation-core/src/
├── modules/destination/
│   └── DestinationApp.vue            # App principale (liste + filtres)
├── components/destination/
│   ├── DestinationModal.vue          # Modale principale
│   ├── DestinationTabMain.vue        # Onglet 1: Infos principales
│   ├── DestinationTabTextes.vue      # Onglet 2: Contenus textuels
│   ├── DestinationTabInfos.vue       # Gestion du répéteur dest_infos
│   ├── DestinationTabFaq.vue         # Gestion du répéteur dest_faq
│   └── DestinationTabSeo.vue         # Onglet 3: SEO
└── stores/
    └── destination-store.js           # Store Pinia pour état global
```

### **Intégration WordPress**

```
mu-plugins/pc-destination/
├── admin/
│   ├── class-destination-admin-page.php    # Page admin WP
│   └── destination-admin-enqueue.php       # Scripts/styles
└── hooks/
    └── destination-admin-hooks.php          # Hooks WordPress
```

---

## 🗃️ Phase 3 : Dictionnaire de Données Complet

### **Mapping ACF Field Keys → Clés normalisées**

Le tableau suivant sera implémenté dans `class-destination-config.php` :

| **Field Key ACF**         | **Clé normalisée**           | **Type**          | **Onglet Vue.js** |
| ------------------------- | ---------------------------- | ----------------- | ----------------- |
| `field_dest_hero_desktop` | `dest_hero_desktop`          | image             | Main              |
| `field_dest_hero_mobile`  | `dest_hero_mobile`           | image             | Main              |
| `field_dest_region`       | `dest_region`                | select            | Main              |
| `field_68d508744e3cb`     | `dest_geo_lat`               | number            | Main              |
| `field_68d5092e4e3cc`     | `dest_geo_lng`               | number            | Main              |
| `field_68cd4838fb4cd`     | `dest_population`            | number            | Main              |
| `field_68cd491bfb4ce`     | `dest_surface_km2`           | number            | Main              |
| `field_68cd4972fb4cf`     | `dest_airport_distance_km`   | number            | Main              |
| `field_68cd49e7fb4d0`     | `dest_sea_side`              | radio             | Main              |
| `field_dest_h1`           | `dest_h1`                    | text              | Textes            |
| `field_dest_intro`        | `dest_intro`                 | wysiwyg           | Textes            |
| `field_dest_slogan`       | `dest_slogan`                | text              | Textes            |
| `field_dest_infos`        | `dest_infos`                 | **repeater→json** | Infos             |
| `field_dest_faq`          | `dest_faq`                   | **repeater→json** | FAQ               |
| `field_dest_exp_featured` | `dest_exp_featured`          | relationship      | Textes            |
| `field_68ced86ebdcac`     | `dest_logements_recommandes` | relationship      | Textes            |
| `field_dest_featured`     | `dest_featured`              | true_false        | Textes            |
| `field_dest_order`        | `dest_order`                 | number            | Textes            |
| `field_68dac244a9b47`     | `dest_exclude_sitemap`       | true_false        | SEO               |
| `field_68db743b9753c`     | `dest_http_410`              | true_false        | SEO               |
| `field_68db71b0120c5`     | `dest_meta_title`            | text              | SEO               |
| `field_68db7212120c6`     | `dest_meta_description`      | textarea          | SEO               |
| `field_68db728b120c7`     | `dest_meta_canonical`        | url               | SEO               |
| `field_68db72e5120c8`     | `dest_meta_robots`           | select            | SEO               |

---

## ⚙️ Phase 4 : Points de Vigilance Techniques

### **1. Gestion des Répéteurs → JSON**

- **Problème :** ACF stocke les répéteurs en plusieurs meta_key
- **Solution :** Conversion en JSON côté Vue.js avec `JSON.stringify()`
- **Côté PHP :** Décodage avec `json_decode()` dans `class-destination-service.php`

### **2. Gestion des Images**

- **ACF :** Retourne un ID d'attachement
- **Vue.js :** Récupération de l'URL via `wp.media.attachment(id)`
- **Fallback :** Image placeholder si ID inexistant

### **3. Gestion des Relations**

- **Expériences featured :** Tableau d'IDs de posts type `experience`
- **Logements recommandés :** Tableau d'IDs de posts types `villa` + `appartement`
- **Validation :** Vérifier l'existence des posts référencés

### **4. Validation Front-End**

- **Coordonnées géo :** Format décimal (ex: 16.123456)
- **Meta description :** 140-160 caractères
- **Slogan :** Maximum 140 caractères
- **Relations :** Maximum 3 éléments chacune

### **5. Nature de l'entité Destination (CPT vs Taxonomie)**

- **Vérification critique :** Déterminer si 'Destination' est enregistré comme Custom Post Type (CPT) ou Taxonomie
- **Selon le JSON ACF :** L'entité semble être un CPT (`"param": "post_type", "value": "destination"`)
- **Si CPT :** Utilisation standard de `get_post_meta()` et `update_post_meta()`
- **Si Taxonomie :** **OBLIGATOIRE** de remplacer par `get_term_meta()` et `update_term_meta()`
- **Impact sur les fichiers :**
  - `class-destination-repository.php` : Adaptation des méthodes de lecture
  - `class-destination-service.php` : Adaptation des méthodes de sauvegarde
  - Endpoints REST API : Adaptation des paramètres (`post_id` vs `term_id`)
- **Validation finale :** Vérifier dans `mu-plugins/pc-custom-typesV3.php` ou rechercher `register_post_type('destination')` vs `register_taxonomy('destination')`

---

## 🚀 Phase 5 : Phases de Développement

### **Étape 1 : Configuration et Mapping (2h)**

1. Créer `class-destination-config.php` avec le mapping complet
2. Créer `destination-fields.php` pour la définition des groupes
3. Tests du mapping avec quelques destinations existantes

### **Étape 2 : Services Backend (3h)**

1. Implémenter `class-destination-repository.php` (lecture via PCR_Fields)
2. Implémenter `class-destination-service.php` (sauvegarde native)
3. Créer les endpoints REST API pour CRUD

### **Étape 3 : Interface Vue.js Base (4h)**

1. Créer `DestinationApp.vue` (liste + filtres)
2. Créer `destination-store.js` (Pinia)
3. Intégration avec les services backend
4. Tests de la liste des destinations

### **Étape 4 : Composants d'Édition (6h)**

1. `DestinationModal.vue` (structure principale)
2. `DestinationTabMain.vue` (infos géo + images)
3. `DestinationTabTextes.vue` (contenus + relations)
4. `DestinationTabSeo.vue` (méta données)

### **Étape 5 : Gestion des Répéteurs (4h)**

1. `DestinationTabInfos.vue` (blocs d'informations pratiques)
2. `DestinationTabFaq.vue` (questions-réponses)
3. Tests de sauvegarde JSON

### **Étape 6 : Intégration WordPress (2h)**

1. Page d'admin WordPress
2. Hooks et permissions
3. Tests de migration sur données réelles

### **Étape 7 : Tests et Documentation (2h)**

1. Tests de régression sur le site public
2. Tests du pont PCR_Fields::get()
3. Documentation utilisateur

**Durée totale estimée : 23 heures**

---

## 💻 Phase 6 : Code de Base - `class-destination-config.php`

```php
<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCR Destination Config - Dictionnaire et Configuration des Destinations
 * Centralise tous les mappings ACF et les statuts.
 * Pattern Singleton.
 *
 * @since 2.0.0
 */
class PCR_Destination_Config
{
    /**
     * Instance unique de la classe.
     * @var PCR_Destination_Config|null
     */
    private static $instance = null;

    /**
     * Constructeur privé (Singleton).
     */
    private function __construct() {}

    /**
     * Récupère l'instance unique.
     * @return PCR_Destination_Config
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Retourne les FIELD KEYS ACF réels pour chaque champ de destination.
     * Ces clés sont nécessaires pour que update_field() fonctionne correctement.
     *
     * @return array Mapping [normalized_key => field_key_acf]
     */
    public function get_acf_field_keys()
    {
        return [
            // === INFOS PRINCIPALES ===
            'dest_hero_desktop' => 'field_dest_hero_desktop',
            'dest_hero_mobile' => 'field_dest_hero_mobile',
            'dest_region' => 'field_dest_region',
            'dest_geo_lat' => 'field_68d508744e3cb',
            'dest_geo_lng' => 'field_68d5092e4e3cc',
            'dest_population' => 'field_68cd4838fb4cd',
            'dest_surface_km2' => 'field_68cd491bfb4ce',
            'dest_airport_distance_km' => 'field_68cd4972fb4cf',
            'dest_sea_side' => 'field_68cd49e7fb4d0',

            // === CONTENUS TEXTUELS ===
            'dest_h1' => 'field_dest_h1',
            'dest_intro' => 'field_dest_intro',
            'dest_slogan' => 'field_dest_slogan',
            'dest_infos' => 'field_dest_infos',
            'dest_faq' => 'field_dest_faq',
            'dest_exp_featured' => 'field_dest_exp_featured',
            'dest_logements_recommandes' => 'field_68ced86ebdcac',
            'dest_featured' => 'field_dest_featured',
            'dest_order' => 'field_dest_order',

            // === SEO ===
            'dest_exclude_sitemap' => 'field_68dac244a9b47',
            'dest_http_410' => 'field_68db743b9753c',
            'dest_meta_title' => 'field_68db71b0120c5',
            'dest_meta_description' => 'field_68db7212120c6',
            'dest_meta_canonical' => 'field_68db728b120c7',
            'dest_meta_robots' => 'field_68db72e5120c8',
        ];
    }

    /**
     * Retourne le mapping complet des champs ACF de destination vers des clés normalisées.
     *
     * @return array Mapping [cle_normalisee => meta_key_acf]
     */
    public function get_mapped_fields()
    {
        return [
            // === GÉNÉRAL & GÉOGRAPHIE ===
            'dest_region' => 'dest_region',
            'dest_geo_lat' => 'dest_geo_lat',
            'dest_geo_lng' => 'dest_geo_lng',
            'dest_population' => 'dest_population',
            'dest_surface_km2' => 'dest_surface_km2',
            'dest_airport_distance_km' => 'dest_airport_distance_km',
            'dest_sea_side' => 'dest_sea_side',

            // === CONTENUS ===
            'dest_h1' => 'dest_h1',
            'dest_intro' => 'dest_intro',
            'dest_slogan' => 'dest_slogan',
            'dest_featured' => 'dest_featured',
            'dest_order' => 'dest_order',

            // === IMAGES ===
            'dest_hero_desktop' => 'dest_hero_desktop',
            'dest_hero_mobile' => 'dest_hero_mobile',

            // === RELATIONS ===
            'dest_exp_featured' => 'dest_exp_featured',
            'dest_logements_recommandes' => 'dest_logements_recommandes',

            // === RÉPÉTEURS (CONVERTIS EN JSON) ===
            'dest_infos' => 'dest_infos',
            'dest_faq' => 'dest_faq',

            // === SEO ===
            'dest_exclude_sitemap' => 'dest_exclude_sitemap',
            'dest_http_410' => 'dest_http_410',
            'dest_meta_title' => 'dest_meta_title',
            'dest_meta_description' => 'dest_meta_description',
            'dest_meta_canonical' => 'dest_meta_canonical',
            'dest_meta_robots' => 'dest_meta_robots',
        ];
    }

    /**
     * Trouve la configuration ACF d'un champ par son slug.
     *
     * @param string $slug Le slug normalisé du champ
     * @return array|false Configuration du champ ou false si non trouvé
     */
    public function get_field_config_by_slug($slug)
    {
        $field_keys = $this->get_acf_field_keys();
        $mapped_fields = $this->get_mapped_fields();

        $config = [
            'slug' => $slug,
            'key' => null,
            'meta_key' => null,
        ];

        // 1. Vérifier d'abord les field keys ACF
        if (isset($field_keys[$slug])) {
            $config['key'] = $field_keys[$slug];
            $config['meta_key'] = $field_keys[$slug];
            return $config;
        }

        // 2. Fallback vers le mapping standard
        if (isset($mapped_fields[$slug])) {
            $config['meta_key'] = $mapped_fields[$slug];
            return $config;
        }

        return false;
    }

    /**
     * Retourne les choix pour le champ région.
     *
     * @return array
     */
    public function get_region_choices()
    {
        return [
            'grande-terre' => 'Grande-Terre',
            'basse-terre' => 'Basse-Terre',
            'iles-voisines' => 'Îles voisines'
        ];
    }

    /**
     * Retourne les choix pour le champ type de plage.
     *
     * @return array
     */
    public function get_sea_side_choices()
    {
        return [
            'caraibes' => 'Mer des Caraïbes',
            'atlantique' => 'Océan Atlantique'
        ];
    }

    /**
     * Retourne les choix pour meta robots.
     *
     * @return array
     */
    public function get_meta_robots_choices()
    {
        return [
            'index,follow' => 'index,follow',
            'noindex,follow' => 'noindex,follow',
            'noindex,nofollow' => 'noindex,nofollow'
        ];
    }

    /**
     * Retourne le label d'affichage pour un statut de post.
     *
     * @param string $status
     * @return string
     */
    public function get_status_label($status)
    {
        $labels = [
            'publish' => 'Publié',
            'pending' => 'En attente',
            'draft' => 'Brouillon',
            'private' => 'Privé',
            'trash' => 'Corbeille',
        ];

        return $labels[$status] ?? ucfirst($status);
    }

    /**
     * Retourne la classe CSS pour un statut de post.
     *
     * @param string $status
     * @return string
     */
    public function get_status_class($status)
    {
        $classes = [
            'publish' => 'pc-status--published',
            'pending' => 'pc-status--pending',
            'draft' => 'pc-status--draft',
            'private' => 'pc-status--private',
            'trash' => 'pc-status--trash',
        ];

        return $classes[$status] ?? 'pc-status--unknown';
    }

    /**
     * Validation des champs spécifiques aux destinations.
     *
     * @param array $data Données à valider
     * @return array Erreurs de validation
     */
    public function validate_destination_data($data)
    {
        $errors = [];

        // Validation des coordonnées géographiques
        if (!empty($data['dest_geo_lat']) && !is_numeric($data['dest_geo_lat'])) {
            $errors['dest_geo_lat'] = 'La latitude doit être un nombre décimal valide';
        }

        if (!empty($data['dest_geo_lng']) && !is_numeric($data['dest_geo_lng'])) {
            $errors['dest_geo_lng'] = 'La longitude doit être un nombre décimal valide';
        }

        // Validation du slogan
        if (!empty($data['dest_slogan']) && strlen($data['dest_slogan']) > 140) {
            $errors['dest_slogan'] = 'Le slogan ne peut pas dépasser 140 caractères';
        }

        // Validation de la meta description
        if (!empty($data['dest_meta_description'])) {
            $len = strlen($data['dest_meta_description']);
            if ($len > 0 && ($len < 140 || $len > 160)) {
                $errors['dest_meta_description'] = 'La meta description doit faire entre 140 et 160 caractères';
            }
        }

        // Validation des expériences featured (max 3)
        if (!empty($data['dest_exp_featured']) && is_array($data['dest_exp_featured'])) {
            if (count($data['dest_exp_featured']) > 3) {
                $errors['dest_exp_featured'] = 'Maximum 3 expériences peuvent être mises en avant';
            }
        }

        // Validation des logements recommandés (max 3)
        if (!empty($data['dest_logements_recommandes']) && is_array($data['dest_logements_recommandes'])) {
            if (count($data['dest_logements_recommandes']) > 3) {
                $errors['dest_logements_recommandes'] = 'Maximum 3 logements peuvent être recommandés';
            }
        }

        return $errors;
    }
}
```

---

## 🎯 Conclusion

Ce plan de migration suit exactement la même méthodologie que pour les entités Expériences et Logements, garantissant :

1. **Cohérence architecturale** avec l'existant
2. **Migration sans régression** grâce au pont PCR_Fields::get()
3. **Performance optimisée** avec sauvegarde native WordPress
4. **Interface moderne** avec Vue.js 3 + Pinia

La migration peut être réalisée par phases, permettant un déploiement progressif et des tests continus.

**Prochaine étape :** Validation de ce plan et démarrage de l'implémentation par la Phase 1 (Configuration et Mapping).
