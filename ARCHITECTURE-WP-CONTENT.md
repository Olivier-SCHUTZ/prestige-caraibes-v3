# Architecture du dossier WP-Content
*Documentation créée le 24 mai 2026*

## Vue d'ensemble
Ce document décrit la structure complète du répertoire wp-content du site Prestige Caraïbes.

---

## Structure racine

```
wp-content/
├── Fichiers de configuration
│   ├── @structure-wp-content.md (41 KB)
│   ├── advanced-cache-backup.php (3.8 KB)
│   ├── advanced-cache.php (0 B - vide)
│   ├── index.php (28 B)
│   ├── debug.log (405 KB)
│   ├── deploy-proof.txt (33 B)
│   ├── plan-migration-destinations.md (22 KB)
│   └── rapport-migration.md (5.3 KB)
│
├── cache/                      # Dossier cache WP-Rocket
│   ├── background-css/
│   ├── busting/
│   │   └── index.html
│   ├── critical-css/
│   │   └── index.html
│   ├── min/
│   │   └── index.html
│   └── wp-rocket/
│       └── index.html
│
├── languages/                  # Fichiers de traduction (77 fichiers)
│   ├── admin-fr_FR.l10n.php (512 KB)
│   ├── admin-fr_FR.mo (571 KB)
│   ├── admin-fr_FR.po (804 KB)
│   ├── admin-network-fr_FR.l10n.php (45 KB)
│   ├── admin-network-fr_FR.mo (51 KB)
│   ├── admin-network-fr_FR.po (68 KB)
│   ├── continents-cities-fr_FR.l10n.php (12 KB)
│   ├── continents-cities-fr_FR.mo (21 KB)
│   ├── continents-cities-fr_FR.po (43 KB)
│   └── [70+ fichiers JSON fr_FR] (fichiers de traduction JSON)
│
├── mu-plugins/                 # Plugins must-use (25 fichiers)
│   └── [Plugins chargés automatiquement]
│
├── plugins/                    # Plugins externes (21 fichiers)
│   └── [Plugins WordPress installés]
│
├── themes/                     # Thèmes WordPress (6 thèmes)
│   └── [Thèmes actifs et inactifs]
│
├── updraft/                    # Sauvegardes UpdraftPlus (30 fichiers)
│   └── [Sauvegardes et métadonnées]
│
├── upgrade/                    # Dossier temporaire de mise à jour
│   └── [Fichiers temporaires de mise à jour]
│
├── upgrade-temp-backup/        # Sauvegarde temporaire
│   └── [Fichiers de sauvegarde]
│
├── uploads/                    # Médias et contenus téléchargés (9 sous-dossiers)
│   └── [Images, documents, fichiers uploadés]
│
└── wp-rocket-config/           # Configuration WP-Rocket (6 fichiers)
    └── [Fichiers de configuration du cache]
```

---

## Détails par section

### 📁 Cache (`cache/`)
- **Objectif**: Stockage des fichiers en cache générés par WP-Rocket
- **Sous-dossiers**:
  - `background-css/` : CSS de fond
  - `busting/` : Cache busting
  - `critical-css/` : CSS critique
  - `min/` : Fichiers minifiés
  - `wp-rocket/` : Cache général WP-Rocket

### 📁 Languages (`languages/`)
- **Objectif**: Fichiers de traduction multilingues (principalement français)
- **Types de fichiers**:
  - `.l10n.php` : Fichiers de localisation PHP
  - `.mo` : Fichiers compilés de traduction
  - `.po` : Fichiers sources de traduction
  - `.json` : Fichiers de traduction JSON

### 📁 MU-Plugins (`mu-plugins/`)
- **Objectif**: Plugins must-use (chargés automatiquement sans interface)
- **Nombre de fichiers**: 25
- **Caractéristique**: Ces plugins sont obligatoires et toujours actifs

### 📁 Plugins (`plugins/`)
- **Objectif**: Plugins WordPress externes
- **Nombre**: 21 dossiers/fichiers
- **Exemples possibles**:
  - WP-Rocket (cache)
  - UpdraftPlus (sauvegardes)
  - Plugins de sécurité
  - Plugins de SEO

### 📁 Themes (`themes/`)
- **Objectif**: Thèmes WordPress (apparence du site)
- **Nombre**: 6 thèmes
- **Structure typique**: 
  - Un thème actif
  - Thèmes inactifs/alternatifs

### 📁 UpdraftPlus (`updraft/`)
- **Objectif**: Sauvegardes du site
- **Nombre de fichiers**: 30
- **Contenu**: Sauvegardes complètes et métadonnées

### 📁 Upgrade (`upgrade/`)
- **Objectif**: Dossier temporaire pour les mises à jour WordPress
- **Caractéristique**: Fichiers temporaires, nettoyé après mise à jour

### 📁 Upgrade-Temp-Backup (`upgrade-temp-backup/`)
- **Objectif**: Sauvegarde temporaire lors des upgrades
- **Caractéristique**: À supprimer après confirmation de mise à jour réussie

### 📁 Uploads (`uploads/`)
- **Objectif**: Tous les fichiers média et contenus uploadés
- **Nombre de sous-dossiers**: 9
- **Contenu**: Images, PDFs, documents, etc.
- **Permission**: `drwx------` (accès restreint)

### 📁 WP-Rocket-Config (`wp-rocket-config/`)
- **Objectif**: Fichiers de configuration du plugin WP-Rocket
- **Nombre**: 6 fichiers
- **Rôle**: Stockage des paramètres d'optimisation et cache

---

## Fichiers racine importants

| Fichier | Taille | Description |
|---------|--------|-------------|
| `index.php` | 28 B | Fichier d'entrée (sécurité) |
| `advanced-cache.php` | 0 B | Hook de cache WordPress (vide) |
| `debug.log` | 405 KB | Logs de débogage (peut être supprimé) |
| `@structure-wp-content.md` | 41 KB | Documentation existante |
| `rapport-migration.md` | 5.3 KB | Rapport de migration |
| `plan-migration-destinations.md` | 22 KB | Plan de migration |
| `deploy-proof.txt` | 33 B | Preuve de déploiement |

---

## Recommandations de maintenance

1. **Nettoyage**:
   - Supprimer `debug.log` si non nécessaire
   - Vider les dossiers `upgrade/` et `upgrade-temp-backup/`
   - Archiver/supprimer anciennes sauvegardes dans `updraft/`

2. **Permissions** (Sécurité):
   - `uploads/` : permissions `755` pour dossiers, `644` pour fichiers
   - Autres dossiers: vérifier les permissions appropriées

3. **Sauvegarde**:
   - Vérifier régulièrement les sauvegardes UpdraftPlus
   - Conserver une copie externe des sauvegardes

4. **Optimisation**:
   - Vérifier les fichiers cache ne deviennent trop volumineux
   - Nettoyer les fichiers de traduction inutilisés

---

## Statistiques

- **Fichiers de configuration racine**: 8
- **Dossiers principaux**: 8
- **Fichiers de traduction**: 77+
- **Taille total approximative**: ~2-3 GB (dépend des uploads)
- **Langue primaire**: Français (fr_FR)

---

*Note: Ce document peut être mises à jour pour refléter les changements structurels du site.*
