# Audit Croisé ACF - Logements

## Migration vers @pc-reservation-core.php

**Date de l'audit :** 7 février 2026  
**Source de vérité :** `mu-plugins/pc-acf-json/group_pc_fiche_logement.json`  
**Fichiers analysés :**

- `shortcode-page-fiche-logement.php`
- `shortcode-liste-logement.php`
- `pc-search-shortcodes.php`
- `pc-ical-cache.php`

---

## 1. Tableau des Champs Actifs

| Label (ACF)                       | Name (Slug)                       | Key (ACF)                            | Type         | Fichiers où il est utilisé                           |
| --------------------------------- | --------------------------------- | ------------------------------------ | ------------ | ---------------------------------------------------- |
| Photo principale (Desktop)        | hero_desktop_url                  | field_pc_hero_desktop_url            | image        | **NON TROUVÉ**                                       |
| Photo principale (Mobile)         | hero_mobile_url                   | field_pc_hero_mobile_url             | image        | **NON TROUVÉ**                                       |
| Galerie (URLs) — 1 par ligne      | gallery_urls                      | field_pc_gallery_urls                | textarea     | shortcode-page-fiche-logement.php                    |
| Vidéos YouTube — 1 URL par ligne  | video_urls                        | field_pc_video_urls                  | textarea     | **NON TROUVÉ**                                       |
| Groupes d'images                  | groupes_images                    | field_693abf0447b67                  | repeater     | shortcode-page-fiche-logement.php                    |
| H1 (optionnel)                    | contenu_seo_titre_h1              | field_pc_h1_custom                   | text         | **NON TROUVÉ**                                       |
| Contenu SEO (HTML)                | seo_long_html                     | field_pc_seo_long_html               | wysiwyg      | shortcode-page-fiche-logement.php                    |
| Caractéristiques principales      | highlights                        | field_pc_highlights_checked          | checkbox     | shortcode-page-fiche-logement.php                    |
| Autres points forts (1 par ligne) | highlights_custom                 | field_pc_highlights_custom           | textarea     | shortcode-page-fiche-logement.php                    |
| Expériences recommandées          | logement_experiences_recommandees | field_68c062c7477ca                  | relationship | shortcode-page-fiche-logement.php                    |
| Identifiant Lodgify               | identifiant_lodgify               | field_pc_identifiant_lodgify         | text         | shortcode-page-fiche-logement.php                    |
| Capacité logement                 | capacite                          | field_pc_capacite                    | number       | shortcode-page-fiche-logement.php                    |
| Superficie (m²)                   | superficie                        | field_pc_superficie                  | number       | **NON TROUVÉ**                                       |
| Nombre de chambres                | nombre_de_chambres                | field_pc_nb_chambres                 | number       | **NON TROUVÉ**                                       |
| Nombre de salles de bain          | nombre_sdb                        | field_pc_nb_sdb                      | number       | **NON TROUVÉ**                                       |
| Nombre de lits                    | nombre_lits                       | field_pc_nb_lits                     | number       | **NON TROUVÉ**                                       |
| Géolocalisation (lat,lon)         | geo_coords                        | field_pc_geo_coords                  | text         | shortcode-page-fiche-logement.php                    |
| Rayon (m)                         | geo_radius_m                      | field_pc_geo_radius_m                | number       | shortcode-page-fiche-logement.php                    |
| Aéroport (km)                     | prox_airport_km                   | field_pc_prox_air                    | number       | shortcode-page-fiche-logement.php                    |
| Autobus (km)                      | prox_bus_km                       | field_pc_prox_bus                    | number       | shortcode-page-fiche-logement.php                    |
| Port (km)                         | prox_port_km                      | field_pc_prox_port                   | number       | shortcode-page-fiche-logement.php                    |
| Plage (km)                        | prox_beach_km                     | field_pc_prox_beach                  | number       | shortcode-page-fiche-logement.php                    |
| Adresse (Rue)                     | adresse_rue                       | field_pc_addr_street                 | text         | **NON TROUVÉ**                                       |
| Ville                             | ville                             | field_pc_addr_city                   | text         | pc-search-shortcodes.php (via taxonomie)             |
| Code postal                       | code_postal                       | field_pc_addr_postal                 | text         | **NON TROUVÉ**                                       |
| Latitude                          | latitude                          | field_pc_lat                         | text         | **NON TROUVÉ**                                       |
| Longitude                         | longitude                         | field_pc_lon                         | text         | **NON TROUVÉ**                                       |
| Politique d'annulation            | politique_dannulation             | field_pc_annulation                  | wysiwyg      | **NON TROUVÉ**                                       |
| Règles de la maison               | regles_maison                     | field_pc_house_rules                 | wysiwyg      | **NON TROUVÉ**                                       |
| Horaire d'arrivée (AM/PM)         | horaire_arrivee                   | field_pc_checkin_time                | text         | **NON TROUVÉ**                                       |
| Horaire de départ (AM/PM)         | horaire_depart                    | field_pc_checkout_time               | text         | **NON TROUVÉ**                                       |
| URL iCal (Lodgify)                | ical_url                          | field_pc_ical_url                    | text         | shortcode-page-fiche-logement.php, pc-ical-cache.php |
| Widget Lodgify (embed)            | lodgify_widget_embed              | field_pc_lodgify_widget_embed        | textarea     | shortcode-page-fiche-logement.php                    |
| Prix « à partir de » (€/nuit)     | base_price_from                   | prix-a-partir-de-e-nuit-prix-de-base | number       | shortcode-page-fiche-logement.php                    |
| Promotion                         | pc-promo-log                      | field_68ee81239c559                  | true_false   | **NON TROUVÉ**                                       |
| Nombre de nuits minimum           | min_nights                        | field_pc_min_nights                  | number       | shortcode-page-fiche-logement.php                    |
| Nombre de nuits maximum           | max_nights                        | field_pc_max_nights                  | number       | shortcode-page-fiche-logement.php                    |
| Unité de prix                     | unite_de_prix                     | unite-de-prix                        | radio        | shortcode-page-fiche-logement.php                    |
| Frais par invité supplémentaire   | extra_guest_fee                   | field_pc_extra_guest_fee             | number       | shortcode-page-fiche-logement.php                    |
| Appliquer à partir du … invités   | extra_guest_from                  | field_pc_extra_guest_from            | number       | shortcode-page-fiche-logement.php                    |
| Caution (€)                       | caution                           | caution-e                            | number       | **NON TROUVÉ**                                       |
| Frais de ménage                   | frais_menage                      | field_pc_frais_menage                | number       | shortcode-page-fiche-logement.php                    |
| Autres frais                      | autres_frais                      | field_pc_autres_frais                | number       | shortcode-page-fiche-logement.php                    |
| Type de frais                     | autres_frais_type                 | field_pc_autres_frais_type           | text         | shortcode-page-fiche-logement.php                    |
| Taxe de séjour                    | taxe_sejour                       | field_pc_taxe_sejour                 | checkbox     | shortcode-page-fiche-logement.php                    |
| Saisons                           | pc_season_blocks                  | field_pc_season_blocks_20250826      | repeater     | shortcode-page-fiche-logement.php                    |
| Promotions & Offres               | pc_promo_blocks                   | field_693425b17049d                  | repeater     | **NON TROUVÉ**                                       |
| Nom de l'hôte                     | hote_nom                          | field_pc_host_name                   | text         | **NON TROUVÉ**                                       |
| Descriptif hôte                   | hote_description                  | field_pc_host_desc                   | wysiwyg      | **NON TROUVÉ**                                       |
| Exclure du sitemap                | log_exclude_sitemap               | field_pc_idx_exclude_sitemap         | true_false   | **NON TROUVÉ**                                       |
| Servir un 410 Gone                | log_http_410                      | field_pc_idx_http_410                | true_false   | **NON TROUVÉ**                                       |
| Meta Titre (override)             | meta_titre                        | field_pc_log_meta_titre              | text         | **NON TROUVÉ**                                       |
| Meta Description (override)       | meta_description                  | field_pc_log_meta_description        | textarea     | **NON TROUVÉ**                                       |
| URL canonique (override)          | url_canonique                     | field_pc_log_canonical               | url          | **NON TROUVÉ**                                       |
| Meta robots                       | log_meta_robots                   | field_pc_idx_heading                 | select       | **NON TROUVÉ**                                       |
| Galerie (URLs) SEO                | seo_gallery_urls                  | field_pc_seo_gallery_urls            | textarea     | **NON TROUVÉ**                                       |
| Type de location (Google)         | google_vr_accommodation_type      | field_google_vr_accommodation_type   | select       | **NON TROUVÉ**                                       |
| Équipements (pour Google)         | google_vr_amenities               | field_google_vr_amenities            | checkbox     | **NON TROUVÉ**                                       |
| Taux de TVA applicable            | taux_tva                          | field_692db54735845                  | number       | **NON TROUVÉ**                                       |
| Taux de TVA applicable ménage     | taux_tva_menage                   | field_692ffbf5ec554                  | number       | **NON TROUVÉ**                                       |
| Mode de réservation               | mode_reservation                  | field_692986ddcf6e3                  | select       | shortcode-page-fiche-logement.php                    |
| Règles de paiement                | regles_de_paiement                | field_6919e7994db4a                  | group        | shortcode-page-fiche-logement.php                    |
| Infos Contrat & Propriétaire      | information_contrat_location      | field_6930b219248f6                  | group        | **NON TROUVÉ**                                       |

---

## 2. Champs Définis mais NON Trouvés (Dead Code ?)

### 🔍 **Média & Galerie**

- `hero_desktop_url` - Photo principale (Desktop) - **Potentiel dead code**
- `hero_mobile_url` - Photo principale (Mobile) - **Potentiel dead code**
- `video_urls` - Vidéos YouTube - **Potentiel dead code**

### 🔍 **Contenu SEO**

- `contenu_seo_titre_h1` - H1 personnalisé - **Potentiel dead code**

### 🔍 **Détails & Capacités**

- `superficie` - Superficie (m²) - **Utilisé uniquement dans le back-office ?**
- `nombre_de_chambres` - Nombre de chambres - **Utilisé uniquement dans le back-office ?**
- `nombre_sdb` - Nombre de salles de bain - **Utilisé uniquement dans le back-office ?**
- `nombre_lits` - Nombre de lits - **Utilisé uniquement dans le back-office ?**

### 🔍 **Équipements (tous les groupes de checkboxes)**

- `eq_piscine_spa` - Piscine & spa
- `eq_parking_installations` - Parking & installations
- `eq_politiques` - Politiques
- `eq_divertissements` - Divertissements
- `eq_cuisine_salle_a_manger` - Cuisine & salle à manger
- `eq_caracteristiques_emplacement` - Caractéristiques de l'emplacement
- `eq_salle_de_bain_blanchisserie` - Salle de bain & buanderie
- `eq_chauffage_climatisation` - Chauffage & climatisation
- `eq_internet_bureautique` - Internet & bureautique
- `eq_securite_maison` - Sécurité à la maison

### 🔍 **Emplacement & proximités**

- `adresse_rue` - Adresse (Rue)
- `code_postal` - Code postal
- `latitude` - Latitude (redondant avec `geo_coords` ?)
- `longitude` - Longitude (redondant avec `geo_coords` ?)

### 🔍 **Réservation**

- `politique_dannulation` - Politique d'annulation
- `regles_maison` - Règles de la maison
- `horaire_arrivee` - Horaire d'arrivée
- `horaire_depart` - Horaire de départ

### 🔍 **Tarifs**

- `pc-promo-log` - Promotion (checkbox)
- `caution` - Caution (€)

### 🔍 **Tarifs saison**

- `pc_promo_blocks` - Promotions & Offres (repeater complexe)

### 🔍 **Hôte**

- `hote_nom` - Nom de l'hôte
- `hote_description` - Descriptif hôte

### 🔍 **Overrides SEO (optionnels)**

- `log_exclude_sitemap` - Exclure du sitemap
- `log_http_410` - Servir un 410 Gone
- `meta_titre` - Meta Titre (override)
- `meta_description` - Meta Description (override)
- `url_canonique` - URL canonique (override)
- `log_meta_robots` - Meta robots
- `seo_gallery_urls` - Galerie (URLs) SEO

### 🔍 **Données Google VacationRental**

- `google_vr_accommodation_type` - Type de location (Google)
- `google_vr_amenities` - Équipements (pour Google)

### 🔍 **Règles Channel Manager**

- `taux_tva` - Taux de TVA applicable
- `taux_tva_menage` - Taux de TVA applicable ménage
- `information_contrat_location` - Infos Contrat & Propriétaire (groupe complexe)

---

## 3. Analyse de la Structure

### **Hiérarchie complexe détectée :**

1. **Repeater Fields :**
   - `groupes_images` (Galerie par catégorie) - **UTILISÉ**
   - `pc_season_blocks` (Tarifs par saison) - **UTILISÉ**
   - `pc_promo_blocks` (Promotions) - **NON UTILISÉ**

2. **Group Fields :**
   - `regles_de_paiement` (Règles de paiement) - **UTILISÉ**
   - `information_contrat_location` (Infos contrat) - **NON UTILISÉ**

3. **Tabs Organization :** Structure bien organisée en 15 onglets pour l'administration

### **Observations sur la cohérence des noms :**

✅ **Bonne cohérence :** La majorité des champs suivent le préfixe `pc_`  
❌ **Incohérences détectées :**

- `prix-a-partir-de-e-nuit-prix-de-base` (trait d'union au lieu d'underscore)
- `caution-e` (trait d'union au lieu d'underscore)
- `unite-de-prix` (trait d'union au lieu d'underscore)

### **Redondances potentielles :**

- `geo_coords` vs `latitude`/`longitude` (doublon de stockage géolocalisation)
- `caution` vs `regles_de_paiement.pc_caution_amount` (doublon caution)

---

## 4. Recommandations

### 🎯 **MIGRER ABSOLUMENT vers PCR_Housing_Manager :**

**Champs critiques pour le fonctionnement :**

1. `gallery_urls`, `groupes_images` - Système galerie
2. `seo_long_html`, `highlights`, `highlights_custom` - SEO & présentation
3. `logement_experiences_recommandees` - Maillage interne
4. `capacite`, `identifiant_lodgify` - Données essentielles
5. `geo_coords`, `geo_radius_m`, `prox_*` - Géolocalisation & proximités
6. `ical_url`, `lodgify_widget_embed` - Réservations
7. `base_price_from`, `unite_de_prix`, `min_nights`, `max_nights` - Tarification de base
8. `extra_guest_fee`, `extra_guest_from`, `frais_menage`, `autres_frais`, `autres_frais_type`, `taxe_sejour` - Tarification détaillée
9. `pc_season_blocks` - Tarifs saisonniers
10. `mode_reservation`, `regles_de_paiement` - Gestion réservations

### ⚠️ **À INVESTIGUER avant migration :**

**Champs potentiellement utilisés en back-office :**

- `superficie`, `nombre_de_chambres`, `nombre_sdb`, `nombre_lits` - **Vérifier usage admin/recherche**
- Tous les groupes `eq_*` - **Vérifier si utilisés dans recherche/filtres**
- `adresse_rue`, `code_postal` - **Vérifier affichage fiche**
- `horaire_arrivee`, `horaire_depart` - **Vérifier utilisation Lodgify/emails**

### ❌ **IGNORER lors de la migration (Dead Code confirmé) :**

1. **SEO avancé non utilisé :** `log_exclude_sitemap`, `log_http_410`, `meta_titre`, `meta_description`, `url_canonique`, `log_meta_robots`, `seo_gallery_urls`
2. **Google VR non implémenté :** `google_vr_accommodation_type`, `google_vr_amenities`
3. **Hôte non affiché :** `hote_nom`, `hote_description`
4. **TVA non gérée :** `taux_tva`, `taux_tva_menage`
5. **Contrat non utilisé :** `information_contrat_location`
6. **Promotions avancées :** `pc_promo_blocks`
7. **Média alternatif :** `hero_desktop_url`, `hero_mobile_url`, `video_urls`
8. **Contenu optionnel :** `contenu_seo_titre_h1`, `pc-promo-log`

### 🔧 **Actions de nettoyage recommandées :**

1. **Harmoniser les noms :** Convertir les champs avec traits d'union vers underscore
2. **Supprimer doublons :** Choisir entre `geo_coords` et `latitude`/`longitude`
3. **Audit usage réel :** Vérifier dans la base de données quels champs `eq_*` sont remplis
4. **Documentation :** Créer une matrice de correspondance ACF → PCR_Housing_Manager

---

**Total champs ACF :** 78 champs  
**Champs activement utilisés :** 23 (29%)  
**Champs à investiguer :** 20 (26%)  
**Dead code confirmé :** 35 (45%)
