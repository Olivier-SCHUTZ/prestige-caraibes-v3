<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PC Housing Dashboard Shortcode
 * Interface de gestion des logements pour l'App Shell
 * 
 * @since 0.1.4
 */

/**
 * Shortcode principal pour le dashboard des logements
 */
function pc_shortcode_housing_dashboard($atts = [])
{
    // Vérification des permissions
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        return '<div class="pc-error">Accès non autorisé</div>';
    }

    // Parser les attributs
    $atts = shortcode_atts([
        'per_page' => 20,
    ], $atts, 'pc_housing_dashboard');

    // Enqueue les assets nécessaires
    pc_housing_enqueue_assets();

    // Enqueue WordPress Media Library
    wp_enqueue_media();

    // Générer le HTML
    ob_start();
?>

    <!-- Housing Manager Interface -->
    <div class="pc-housing-manager">
        <div class="pc-housing-header">
            <h1 class="pc-housing-title">
                <span class="pc-housing-icon">🏘️</span>
                Gestion des Logements
            </h1>
            <p class="pc-housing-subtitle">
                Gérez vos logements, leurs informations et paramètres de réservation
            </p>
        </div>

        <!-- Barre de recherche et filtres -->
        <div class="pc-housing-controls">
            <div class="pc-search-wrapper">
                <input type="text" id="pc-housing-search" placeholder="🔍 Rechercher un logement..." class="pc-input pc-search-input">
            </div>

            <div class="pc-filters-wrapper">
                <select id="pc-housing-status-filter" class="pc-select pc-filter-select">
                    <option value="">Tous les statuts</option>
                    <option value="publish">Publié</option>
                    <option value="pending">En attente</option>
                    <option value="draft">Brouillon</option>
                    <option value="private">Privé</option>
                </select>

                <select id="pc-housing-mode-filter" class="pc-select pc-filter-select">
                    <option value="">Tous les modes</option>
                    <option value="log_directe">Réservation directe</option>
                    <option value="log_demande">Sur demande</option>
                    <option value="log_channel">Channel Manager</option>
                </select>

                <select id="pc-housing-type-filter" class="pc-select pc-filter-select">
                    <option value="">Tous les types</option>
                    <option value="villa">Villa</option>
                    <option value="appartement">Appartement</option>
                    <option value="logement">Logement</option>
                </select>
            </div>
        </div>

        <!-- Tableau des logements -->
        <div class="pc-housing-table-wrapper">
            <div class="pc-loading" id="pc-housing-loading">
                <div class="pc-spinner"></div>
                <span>Chargement des logements...</span>
            </div>

            <table class="pc-table pc-housing-table" id="pc-housing-table" style="display: none;">
                <thead>
                    <tr>
                        <th class="pc-col-image">Image</th>
                        <th class="pc-col-name">Nom du logement</th>
                        <th class="pc-col-capacity">Capacité</th>
                        <th class="pc-col-price">Prix/nuit</th>
                        <th class="pc-col-location">Localisation</th>
                        <th class="pc-col-status">Statut</th>
                        <th class="pc-col-mode">Mode</th>
                        <th class="pc-col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="pc-housing-table-body">
                    <!-- Contenu généré dynamiquement par JavaScript -->
                </tbody>
            </table>

            <div class="pc-empty-state" id="pc-housing-empty" style="display: none;">
                <div class="pc-empty-icon">🏘️</div>
                <h3>Aucun logement trouvé</h3>
                <p>Aucun logement ne correspond aux critères de recherche.</p>
            </div>
        </div>

        <!-- Pagination -->
        <div class="pc-pagination-wrapper">
            <div class="pc-pagination" id="pc-housing-pagination">
                <!-- Pagination générée dynamiquement -->
            </div>
        </div>
    </div>

    <!-- Modale de détail logement -->
    <div id="housing-modal" class="pc-modal hidden">
        <div class="pc-modal-overlay"></div>
        <div class="pc-modal-container">
            <div class="pc-modal-header">
                <h2 class="pc-modal-title">
                    <span class="pc-modal-icon">🏘️</span>
                    <span id="pc-housing-modal-title">Détails du logement</span>
                </h2>
                <button class="pc-modal-close" onclick="closeHousingModal()">×</button>
            </div>

            <div class="pc-modal-content">
                <div class="pc-housing-loading" id="pc-housing-modal-loading">
                    <div class="pc-spinner"></div>
                    <span>Chargement des détails...</span>
                </div>

                <div class="pc-housing-details" id="pc-housing-modal-details" style="display: none;">
                    <!-- Navigation par onglets -->
                    <div class="pc-tabs-nav">
                        <button class="pc-tab-btn active" data-tab="general">
                            <span class="pc-tab-icon">📝</span>
                            Général
                        </button>
                        <button class="pc-tab-btn" data-tab="location">
                            <span class="pc-tab-icon">📍</span>
                            Localisation
                        </button>
                        <button class="pc-tab-btn" data-tab="pricing">
                            <span class="pc-tab-icon">💰</span>
                            Tarifs & Paiement
                        </button>
                        <button class="pc-tab-btn" data-tab="media">
                            <span class="pc-tab-icon">🖼️</span>
                            Images & Galerie
                        </button>
                        <button class="pc-tab-btn" data-tab="equipements">
                            <span class="pc-tab-icon">🏠</span>
                            Équipements
                        </button>
                        <button class="pc-tab-btn" data-tab="seo">
                            <span class="pc-tab-icon">🔍</span>
                            Contenu & SEO
                        </button>
                        <button class="pc-tab-btn" data-tab="booking">
                            <span class="pc-tab-icon">📋</span>
                            Réservation & Hôte
                        </button>
                        <button class="pc-tab-btn" data-tab="advanced">
                            <span class="pc-tab-icon">⚙️</span>
                            Configuration
                        </button>
                    </div>

                    <!-- Contenu des onglets -->
                    <div class="pc-tabs-content">
                        <!-- Onglet Général -->
                        <div class="pc-tab-content" id="tab-general">
                            <div class="pc-form-grid">
                                <div class="pc-form-group pc-form-group--full">
                                    <label for="housing-title">Nom du logement</label>
                                    <input type="text" id="housing-title" class="pc-input" placeholder="Nom du logement">
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-identifiant-lodgify">Identifiant Lodgify</label>
                                    <input type="text" id="housing-identifiant-lodgify" class="pc-input" placeholder="ID Lodgify">
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-capacity">Capacité (personnes)</label>
                                    <input type="number" id="housing-capacity" class="pc-input" min="1" max="50">
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-superficie">Superficie (m²)</label>
                                    <input type="number" id="housing-superficie" class="pc-input" min="1">
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-chambres">Nombre de chambres</label>
                                    <input type="number" id="housing-chambres" class="pc-input" min="0">
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-sdb">Salles de bain</label>
                                    <input type="number" id="housing-sdb" class="pc-input" min="0">
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-lits">Nombre de lits</label>
                                    <input type="number" id="housing-lits" class="pc-input" min="0">
                                </div>

                            </div>
                        </div>

                        <!-- Onglet Localisation -->
                        <div class="pc-tab-content" id="tab-location" style="display: none;">
                            <div class="pc-form-grid">
                                <div class="pc-form-group pc-form-group--full">
                                    <label for="housing-geo-coords">Géolocalisation (lat,lon)</label>
                                    <input type="text" id="housing-geo-coords" class="pc-input" placeholder="16.2561,-61.2795">
                                    <small class="pc-field-help">ex : 16.2561,-61.2795</small>
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-geo-radius">Rayon (m)</label>
                                    <input type="number" id="housing-geo-radius" class="pc-input" placeholder="600">
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-prox-airport">Aéroport (km)</label>
                                    <input type="number" id="housing-prox-airport" class="pc-input" min="0" step="0.1">
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-prox-bus">Autobus (km)</label>
                                    <input type="number" id="housing-prox-bus" class="pc-input" min="0" step="0.1">
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-prox-port">Port (km)</label>
                                    <input type="number" id="housing-prox-port" class="pc-input" min="0" step="0.1">
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-prox-beach">Plage (km)</label>
                                    <input type="number" id="housing-prox-beach" class="pc-input" min="0" step="0.1">
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-adresse">Adresse (Rue)</label>
                                    <input type="text" id="housing-adresse" class="pc-input" placeholder="Adresse complète">
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-ville">Ville</label>
                                    <input type="text" id="housing-ville" class="pc-input" placeholder="Ville">
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-code-postal">Code postal</label>
                                    <input type="text" id="housing-code-postal" class="pc-input" placeholder="Code postal">
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-latitude">Latitude</label>
                                    <input type="text" id="housing-latitude" class="pc-input" placeholder="16.2650">
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-longitude">Longitude</label>
                                    <input type="text" id="housing-longitude" class="pc-input" placeholder="-61.5510">
                                </div>
                            </div>
                        </div>

                        <!-- Onglet Tarifs & Paiement -->
                        <div class="pc-tab-content" id="tab-pricing" style="display: none;">
                            <div class="pc-form-grid">
                                <div class="pc-form-group">
                                    <label for="housing-prix-base">Prix « à partir de » (€/nuit)</label>
                                    <input type="number" id="housing-prix-base" class="pc-input" min="0" step="10">
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-promo">Promotion</label>
                                    <label class="pc-checkbox-container">
                                        <input type="checkbox" id="housing-promo" name="pc_promo_log" value="1" class="pc-checkbox-field pc-checkbox-boolean">
                                        <span class="pc-checkbox-label">Afficher le ruban "Promotion"</span>
                                    </label>
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-min-nights">Nuits minimum</label>
                                    <input type="number" id="housing-min-nights" class="pc-input" min="1" step="1">
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-max-nights">Nuits maximum</label>
                                    <input type="number" id="housing-max-nights" class="pc-input" min="1" step="1">
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-unite-prix">Unité de prix</label>
                                    <select id="housing-unite-prix" class="pc-select">
                                        <option value="par nuit">par nuit</option>
                                        <option value="par semaine">par semaine</option>
                                    </select>
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-extra-guest-fee">Frais par invité supplémentaire (€/nuit)</label>
                                    <input type="number" id="housing-extra-guest-fee" class="pc-input" min="0" step="0.01">
                                    <small class="pc-field-help">Activez un supplément par nuit pour chaque invité supplémentaire.</small>
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-extra-guest-from">À partir de ... invités</label>
                                    <input type="number" id="housing-extra-guest-from" class="pc-input" min="1" step="1">
                                    <small class="pc-field-help">À partir de combien d'invités appliquer le supplément (par nuit et par invité).</small>
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-caution">Caution (€)</label>
                                    <input type="number" id="housing-caution" class="pc-input" min="0" step="50">
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-frais-menage">Frais de ménage (€)</label>
                                    <input type="number" id="housing-frais-menage" class="pc-input" min="0" step="0.01">
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-autres-frais">Autres frais (€)</label>
                                    <input type="number" id="housing-autres-frais" class="pc-input" min="0" step="0.01">
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-autres-frais-type">Type de frais</label>
                                    <input type="text" id="housing-autres-frais-type" class="pc-input" placeholder="Kit draps, Service conciergerie...">
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="housing-taxe-sejour">Taxe de séjour</label>
                                    <div class="pc-checkbox-group">
                                        <label><input type="checkbox" name="taxe_sejour[]" value="5%" class="pc-checkbox-field"> 5% du montant total du séjour</label>
                                        <label><input type="checkbox" name="taxe_sejour[]" value="1_etoile" class="pc-checkbox-field"> Logement classé 1 étoile</label>
                                        <label><input type="checkbox" name="taxe_sejour[]" value="2_etoiles" class="pc-checkbox-field"> Logement classé 2 étoiles</label>
                                        <label><input type="checkbox" name="taxe_sejour[]" value="3_etoiles" class="pc-checkbox-field"> Logement classé 3 étoiles</label>
                                        <label><input type="checkbox" name="taxe_sejour[]" value="4_etoiles" class="pc-checkbox-field"> Logement classé 4 étoiles</label>
                                        <label><input type="checkbox" name="taxe_sejour[]" value="5_etoiles" class="pc-checkbox-field"> Logement classé 5 étoiles</label>
                                    </div>
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-taux-tva">Taux de TVA applicable (%)</label>
                                    <input type="number" id="housing-taux-tva" class="pc-input" min="0" max="100" step="0.01">
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-taux-tva-menage">Taux de TVA ménage (%)</label>
                                    <input type="number" id="housing-taux-tva-menage" class="pc-input" min="0" max="100" step="0.01">
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-mode-reservation">Mode de réservation</label>
                                    <select id="housing-mode-reservation" class="pc-select">
                                        <option value="log_demande">Logement sur demande avec accord au préalable</option>
                                        <option value="log_directe">Logement en réservation directe</option>
                                        <option value="log_channel">Logement géré par un autre Channel Manager</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Onglet Images & Galerie -->
                        <div class="pc-tab-content" id="tab-media" style="display: none;">
                            <div class="pc-form-grid">
                                <div class="pc-form-group">
                                    <label for="housing-hero-desktop">Photo principale (Desktop)</label>
                                    <div class="pc-image-uploader" data-field="hero-desktop">
                                        <div class="pc-image-preview" id="preview-hero-desktop">
                                            <div class="pc-image-placeholder">
                                                📷 Aucune image sélectionnée
                                            </div>
                                        </div>
                                        <input type="hidden" id="housing-hero-desktop" name="hero_desktop_url" value="">
                                        <div class="pc-image-actions">
                                            <button type="button" class="pc-btn pc-btn-select-image" data-target="hero-desktop">
                                                <span>🖼️</span> Choisir une image
                                            </button>
                                            <button type="button" class="pc-btn pc-btn-remove-image" data-target="hero-desktop" style="display: none;">
                                                <span>❌</span> Supprimer
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-hero-mobile">Photo principale (Mobile)</label>
                                    <div class="pc-image-uploader" data-field="hero-mobile">
                                        <div class="pc-image-preview" id="preview-hero-mobile">
                                            <div class="pc-image-placeholder">
                                                📱 Aucune image sélectionnée
                                            </div>
                                        </div>
                                        <input type="hidden" id="housing-hero-mobile" name="hero_mobile_url" value="">
                                        <div class="pc-image-actions">
                                            <button type="button" class="pc-btn pc-btn-select-image" data-target="hero-mobile">
                                                <span>🖼️</span> Choisir une image
                                            </button>
                                            <button type="button" class="pc-btn pc-btn-remove-image" data-target="hero-mobile" style="display: none;">
                                                <span>❌</span> Supprimer
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="housing-gallery-urls">Galerie (URLs) — 1 par ligne</label>
                                    <textarea id="housing-gallery-urls" class="pc-textarea" rows="8" placeholder="https://example.com/image1.jpg&#10;https://example.com/image2.jpg&#10;https://example.com/image3.jpg"></textarea>
                                    <small class="pc-field-help">1 URL d'image par ligne (utilisé par [pc_gallery]).</small>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="housing-video-urls">Vidéos YouTube — 1 URL par ligne</label>
                                    <textarea id="housing-video-urls" class="pc-textarea" rows="3" placeholder="https://youtube.com/watch?v=..."></textarea>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="housing-seo-gallery-urls">Galerie SEO (URLs) — 1 par ligne (8 min / 12 max)</label>
                                    <textarea id="housing-seo-gallery-urls" class="pc-textarea" rows="10" placeholder="https://exemple.com/photo1.jpg&#10;https://exemple.com/photo2.jpg"></textarea>
                                    <small class="pc-field-help">1 URL d'image SEO par ligne — 8 minimum / 12 maximum.</small>
                                </div>

                                <!-- Séparateur -->
                                <hr class="pc-section-divider">

                                <!-- Repeater Galerie par Catégorie -->
                                <div class="pc-form-group pc-form-group--full">
                                    <label>🖼️ Galerie par Catégorie</label>
                                    <div class="pc-repeater" id="housing-gallery-categories">
                                        <div class="pc-repeater-header">
                                            <span class="pc-repeater-title">Groupes d'images par catégorie</span>
                                            <button type="button" class="pc-btn pc-btn-add-group" id="add-gallery-group">
                                                <span>➕</span> Ajouter un groupe
                                            </button>
                                        </div>

                                        <div class="pc-repeater-items" id="gallery-categories-container">
                                            <!-- Les éléments seront générés dynamiquement -->
                                        </div>

                                        <div class="pc-repeater-empty" id="gallery-categories-empty">
                                            <div class="pc-empty-icon">📁</div>
                                            <p>Aucun groupe d'images. Cliquez sur "Ajouter un groupe" pour commencer.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Onglet Équipements -->
                        <div class="pc-tab-content" id="tab-equipements" style="display: none;">
                            <div class="pc-form-grid">
                                <div class="pc-form-group pc-form-group--full">
                                    <label>Piscine & Spa</label>
                                    <div class="pc-checkbox-group">
                                        <label><input type="checkbox" name="eq_piscine_spa[]" value="Piscine" class="pc-checkbox-field"> Piscine</label>
                                        <label><input type="checkbox" name="eq_piscine_spa[]" value="Piscine au sel" class="pc-checkbox-field"> Piscine au sel</label>
                                        <label><input type="checkbox" name="eq_piscine_spa[]" value="Piscine partagée" class="pc-checkbox-field"> Piscine partagée</label>
                                        <label><input type="checkbox" name="eq_piscine_spa[]" value="Jacuzzi" class="pc-checkbox-field"> Jacuzzi</label>
                                        <label><input type="checkbox" name="eq_piscine_spa[]" value="Sauna" class="pc-checkbox-field"> Sauna</label>
                                    </div>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label>Parking & Installations</label>
                                    <div class="pc-checkbox-group">
                                        <label><input type="checkbox" name="eq_parking[]" value="Propriété clôturée" class="pc-checkbox-field"> Propriété clôturée</label>
                                        <label><input type="checkbox" name="eq_parking[]" value="Parking privé" class="pc-checkbox-field"> Parking privé</label>
                                        <label><input type="checkbox" name="eq_parking[]" value="Parking dans la rue" class="pc-checkbox-field"> Parking dans la rue</label>
                                        <label><input type="checkbox" name="eq_parking[]" value="Jardin privé" class="pc-checkbox-field"> Jardin privé</label>
                                        <label><input type="checkbox" name="eq_parking[]" value="Jardin commun" class="pc-checkbox-field"> Jardin commun</label>
                                        <label><input type="checkbox" name="eq_parking[]" value="Aire de jeux" class="pc-checkbox-field"> Aire de jeux</label>
                                        <label><input type="checkbox" name="eq_parking[]" value="Terrain de pétanque" class="pc-checkbox-field"> Terrain de pétanque</label>
                                        <label><input type="checkbox" name="eq_parking[]" value="Citerne de réserve d'eau" class="pc-checkbox-field"> Citerne de réserve d'eau</label>
                                        <label><input type="checkbox" name="eq_parking[]" value="Groupe électrogène" class="pc-checkbox-field"> Groupe électrogène</label>
                                    </div>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label>Cuisine & Salle à manger</label>
                                    <div class="pc-checkbox-group">
                                        <label><input type="checkbox" name="eq_cuisine[]" value="Barbecue à gaz" class="pc-checkbox-field"> Barbecue à gaz</label>
                                        <label><input type="checkbox" name="eq_cuisine[]" value="Barbecue électrique" class="pc-checkbox-field"> Barbecue électrique</label>
                                        <label><input type="checkbox" name="eq_cuisine[]" value="Barbecue au charbon" class="pc-checkbox-field"> Barbecue au charbon</label>
                                        <label><input type="checkbox" name="eq_cuisine[]" value="Plancha" class="pc-checkbox-field"> Plancha</label>
                                        <label><input type="checkbox" name="eq_cuisine[]" value="Mixeur / Blender" class="pc-checkbox-field"> Mixeur / Blender</label>
                                        <label><input type="checkbox" name="eq_cuisine[]" value="Chaise haute bébé" class="pc-checkbox-field"> Chaise haute bébé</label>
                                        <label><input type="checkbox" name="eq_cuisine[]" value="Cafetière filtre" class="pc-checkbox-field"> Cafetière filtre</label>
                                        <label><input type="checkbox" name="eq_cuisine[]" value="Cafetière à capsules" class="pc-checkbox-field"> Cafetière à capsules</label>
                                        <label><input type="checkbox" name="eq_cuisine[]" value="Grille-pain" class="pc-checkbox-field"> Grille-pain</label>
                                        <label><input type="checkbox" name="eq_cuisine[]" value="Bouilloire" class="pc-checkbox-field"> Bouilloire</label>
                                        <label><input type="checkbox" name="eq_cuisine[]" value="Ustensiles de cuisine" class="pc-checkbox-field"> Ustensiles de cuisine</label>
                                        <label><input type="checkbox" name="eq_cuisine[]" value="Plaque de cuisson gaz" class="pc-checkbox-field"> Plaque de cuisson gaz</label>
                                        <label><input type="checkbox" name="eq_cuisine[]" value="Plaque de cuisson électrique" class="pc-checkbox-field"> Plaque de cuisson électrique</label>
                                        <label><input type="checkbox" name="eq_cuisine[]" value="Plaque de cuisson mixte" class="pc-checkbox-field"> Plaque de cuisson mixte</label>
                                        <label><input type="checkbox" name="eq_cuisine[]" value="Four micro-ondes" class="pc-checkbox-field"> Four micro-ondes</label>
                                        <label><input type="checkbox" name="eq_cuisine[]" value="Four" class="pc-checkbox-field"> Four</label>
                                        <label><input type="checkbox" name="eq_cuisine[]" value="Lave-vaisselle" class="pc-checkbox-field"> Lave-vaisselle</label>
                                        <label><input type="checkbox" name="eq_cuisine[]" value="Cave à vins" class="pc-checkbox-field"> Cave à vins</label>
                                        <label><input type="checkbox" name="eq_cuisine[]" value="Réfrigérateur" class="pc-checkbox-field"> Réfrigérateur</label>
                                        <label><input type="checkbox" name="eq_cuisine[]" value="Congélateur" class="pc-checkbox-field"> Congélateur</label>
                                        <label><input type="checkbox" name="eq_cuisine[]" value="Réfrigérateur-congélateur" class="pc-checkbox-field"> Réfrigérateur-congélateur</label>
                                        <label><input type="checkbox" name="eq_cuisine[]" value="Frigo américain" class="pc-checkbox-field"> Frigo américain</label>
                                        <label><input type="checkbox" name="eq_cuisine[]" value="Glaçons" class="pc-checkbox-field"> Glaçons</label>
                                        <label><input type="checkbox" name="eq_cuisine[]" value="Aspirateur" class="pc-checkbox-field"> Aspirateur</label>
                                        <label><input type="checkbox" name="eq_cuisine[]" value="Nécessaire de nettoyage" class="pc-checkbox-field"> Nécessaire de nettoyage</label>
                                        <label><input type="checkbox" name="eq_cuisine[]" value="Vaisselle" class="pc-checkbox-field"> Vaisselle</label>
                                        <label><input type="checkbox" name="eq_cuisine[]" value="Verres" class="pc-checkbox-field"> Verres</label>
                                    </div>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label>Chauffage & Climatisation</label>
                                    <div class="pc-checkbox-group">
                                        <label><input type="checkbox" name="eq_clim[]" value="Climatisation chambres" class="pc-checkbox-field"> Climatisation chambres</label>
                                        <label><input type="checkbox" name="eq_clim[]" value="Climatisation logement complet" class="pc-checkbox-field"> Climatisation logement complet</label>
                                        <label><input type="checkbox" name="eq_clim[]" value="Ventilateur de plafond" class="pc-checkbox-field"> Ventilateur de plafond</label>
                                        <label><input type="checkbox" name="eq_clim[]" value="Ventilateur sur pied" class="pc-checkbox-field"> Ventilateur sur pied</label>
                                    </div>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label>Internet & Bureautique</label>
                                    <div class="pc-checkbox-group">
                                        <label><input type="checkbox" name="eq_internet[]" value="Internet ADSL" class="pc-checkbox-field"> Internet ADSL</label>
                                        <label><input type="checkbox" name="eq_internet[]" value="Internet Fibre" class="pc-checkbox-field"> Internet Fibre</label>
                                        <label><input type="checkbox" name="eq_internet[]" value="Bureau séparé" class="pc-checkbox-field"> Bureau séparé</label>
                                        <label><input type="checkbox" name="eq_internet[]" value="Bureau dans chambre" class="pc-checkbox-field"> Bureau dans chambre</label>
                                        <label><input type="checkbox" name="eq_internet[]" value="Bureau dans le salon" class="pc-checkbox-field"> Bureau dans le salon</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Onglet Contenu & SEO -->
                        <div class="pc-tab-content" id="tab-seo" style="display: none;">
                            <div class="pc-form-grid">
                                <div class="pc-form-group pc-form-group--full">
                                    <label for="housing-h1-custom">H1 (optionnel)</label>
                                    <input type="text" id="housing-h1-custom" class="pc-input" placeholder="Titre H1 personnalisé">
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="housing-seo-long-html">Contenu SEO (HTML)</label>
                                    <textarea id="housing-seo-long-html" class="pc-textarea" rows="8" placeholder="Contenu HTML pour le référencement..."></textarea>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label>Caractéristiques principales</label>
                                    <div class="pc-checkbox-group">
                                        <label><input type="checkbox" name="highlights[]" value="piscine"> Piscine</label>
                                        <label><input type="checkbox" name="highlights[]" value="clim"> Climatisation</label>
                                        <label><input type="checkbox" name="highlights[]" value="internet"> Internet / Wifi</label>
                                        <label><input type="checkbox" name="highlights[]" value="parking"> Parking</label>
                                        <label><input type="checkbox" name="highlights[]" value="vue_mer"> Vue Mer</label>
                                        <label><input type="checkbox" name="highlights[]" value="front_mer"> Proche de la plage</label>
                                        <label><input type="checkbox" name="highlights[]" value="jacuzzi"> Jacuzzi / Spa</label>
                                        <label><input type="checkbox" name="highlights[]" value="barbecue"> Barbecue</label>
                                        <label><input type="checkbox" name="highlights[]" value="classement"> Logement classé</label>
                                    </div>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="housing-highlights-custom">Autres points forts (1 par ligne)</label>
                                    <textarea id="housing-highlights-custom" class="pc-textarea" rows="4" placeholder="Ajoutez des points forts personnalisés qui n'ont pas d'icône..."></textarea>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="housing-experiences">Expériences recommandées (IDs - séparés par des virgules)</label>
                                    <input type="text" id="housing-experiences" class="pc-input" placeholder="123,456,789">
                                </div>
                            </div>
                        </div>

                        <!-- Onglet Réservation & Hôte -->
                        <div class="pc-tab-content" id="tab-booking" style="display: none;">
                            <div class="pc-form-grid">
                                <div class="pc-form-group pc-form-group--full">
                                    <label for="housing-politique-annulation">Politique d'annulation</label>
                                    <textarea id="housing-politique-annulation" class="pc-textarea" rows="6" placeholder="Politique d'annulation du logement..."></textarea>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="housing-regles-maison">Règles de la maison</label>
                                    <textarea id="housing-regles-maison" class="pc-textarea" rows="6" placeholder="Règles de la maison..."></textarea>
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-checkin-time">Horaire d'arrivée (AM/PM)</label>
                                    <input type="text" id="housing-checkin-time" class="pc-input" placeholder="3:00 PM">
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-checkout-time">Horaire de départ (AM/PM)</label>
                                    <input type="text" id="housing-checkout-time" class="pc-input" placeholder="11:00 AM">
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="housing-lodgify-widget">Widget Lodgify (embed)</label>
                                    <textarea id="housing-lodgify-widget" class="pc-textarea" rows="8" placeholder="Colle ici le code Lodgify (div + init JS). Laisse vide pour afficher le formulaire de demande."></textarea>
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-hote-nom">Nom de l'hôte</label>
                                    <input type="text" id="housing-hote-nom" class="pc-input" placeholder="Nom de l'hôte">
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="housing-hote-description">Descriptif hôte</label>
                                    <textarea id="housing-hote-description" class="pc-textarea" rows="4" placeholder="Description de l'hôte..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Onglet Configuration -->
                        <div class="pc-tab-content" id="tab-advanced" style="display: none;">
                            <div class="pc-form-grid">
                                <div class="pc-form-group">
                                    <label for="housing-status">Statut de publication</label>
                                    <select id="housing-status" class="pc-select">
                                        <option value="publish">Publié</option>
                                        <option value="pending">En attente</option>
                                        <option value="draft">Brouillon</option>
                                        <option value="private">Privé</option>
                                    </select>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="housing-ical-url">URL iCal (synchronisation calendrier)</label>
                                    <input type="url" id="housing-ical-url" class="pc-input" placeholder="https://airbnb.com/calendar/ical/...">
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-exclude-sitemap">Exclure du sitemap</label>
                                    <label class="pc-checkbox-container">
                                        <input type="checkbox" id="housing-exclude-sitemap" name="log_exclude_sitemap" value="1" class="pc-checkbox-field pc-checkbox-boolean">
                                        <span class="pc-checkbox-label">Retire cette fiche du sitemap</span>
                                    </label>
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-http-410">Servir un 410 Gone</label>
                                    <label class="pc-checkbox-container">
                                        <input type="checkbox" id="housing-http-410" name="log_http_410" value="1" class="pc-checkbox-field pc-checkbox-boolean">
                                        <span class="pc-checkbox-label">Cochez si la fiche est définitivement supprimée</span>
                                    </label>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="housing-meta-titre">Titre SEO (override)</label>
                                    <input type="text" id="housing-meta-titre" class="pc-input" placeholder="Titre optimisé pour le référencement">
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="housing-meta-description">Description SEO (override)</label>
                                    <textarea id="housing-meta-description" class="pc-textarea" rows="3" placeholder="Description optimisée pour le référencement (max 155 caractères)"></textarea>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="housing-url-canonique">URL canonique (override)</label>
                                    <input type="url" id="housing-url-canonique" class="pc-input" placeholder="https://example.com/canonical-url">
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-meta-robots">Meta robots</label>
                                    <select id="housing-meta-robots" class="pc-select">
                                        <option value="index,follow">index,follow</option>
                                        <option value="noindex,follow">noindex,follow</option>
                                        <option value="noindex,nofollow">noindex,nofollow</option>
                                    </select>
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-google-accommodation-type">Type de location (Google)</label>
                                    <select id="housing-google-accommodation-type" class="pc-select">
                                        <option value="EntirePlace">Logement entier (EntirePlace)</option>
                                        <option value="PrivateRoom">Chambre privée (PrivateRoom)</option>
                                        <option value="SharedRoom">Chambre partagée (SharedRoom)</option>
                                    </select>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label>Équipements (pour Google)</label>
                                    <div class="pc-checkbox-group">
                                        <label><input type="checkbox" name="google_amenities[]" value="ac"> Climatisation</label>
                                        <label><input type="checkbox" name="google_amenities[]" value="wifi"> Wi-Fi</label>
                                        <label><input type="checkbox" name="google_amenities[]" value="pool"> Piscine</label>
                                        <label><input type="checkbox" name="google_amenities[]" value="kitchen"> Cuisine</label>
                                        <label><input type="checkbox" name="google_amenities[]" value="heating"> Chauffage</label>
                                        <label><input type="checkbox" name="google_amenities[]" value="hotTub"> Jacuzzi / Bain à remous</label>
                                        <label><input type="checkbox" name="google_amenities[]" value="outdoorGrill"> Barbecue / Grill extérieur</label>
                                        <label><input type="checkbox" name="google_amenities[]" value="petsAllowed"> Animaux autorisés</label>
                                        <label><input type="checkbox" name="google_amenities[]" value="beachAccess"> Accès à la plage</label>
                                        <label><input type="checkbox" name="google_amenities[]" value="childFriendly"> Adapté aux enfants</label>
                                        <label><input type="checkbox" name="google_amenities[]" value="tv"> Télévision</label>
                                        <label><input type="checkbox" name="google_amenities[]" value="washerDryer"> Lave-linge / Sèche-linge</label>
                                        <label><input type="checkbox" name="google_amenities[]" value="balcony"> Balcon</label>
                                        <label><input type="checkbox" name="google_amenities[]" value="elevator"> Ascenseur</label>
                                    </div>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="housing-content">Description (Google)</label>
                                    <textarea id="housing-content" class="pc-textarea" rows="4" placeholder="Description du logement..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pc-modal-footer">
                <div class="pc-modal-actions">
                    <button type="button" class="pc-btn pc-btn-secondary" onclick="closeHousingModal()">
                        Annuler
                    </button>
                    <button type="button" class="pc-btn pc-btn-primary" id="pc-housing-save-btn" onclick="saveHousingDetails()">
                        <span class="pc-btn-text">Enregistrer</span>
                        <span class="pc-btn-spinner" style="display: none;">
                            <div class="pc-spinner-sm"></div>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

<?php
    return ob_get_clean();
}

/**
 * Enqueue les assets nécessaires pour le Housing Manager
 */
function pc_housing_enqueue_assets()
{
    // Éviter les enqueues multiples
    static $assets_loaded = false;
    if ($assets_loaded) {
        return;
    }
    $assets_loaded = true;

    // CSS pour le Housing Manager  
    wp_enqueue_style(
        'pc-housing-dashboard-css',
        PC_RES_CORE_URL . 'assets/css/dashboard-housing.css',
        [], // Pas de dépendance pour éviter les blocages
        PC_RES_CORE_VERSION
    );

    // JavaScript pour le Housing Manager
    wp_enqueue_script(
        'pc-housing-dashboard-js',
        PC_RES_CORE_URL . 'assets/js/dashboard-housing.js',
        ['jquery'],
        PC_RES_CORE_VERSION,
        true
    );

    // Variables JS critiques pour AJAX
    wp_localize_script('pc-housing-dashboard-js', 'pc_housing_vars', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pc_resa_manual_create'),
        'current_user_can_manage' => current_user_can('manage_options'),
        'debug' => WP_DEBUG,
    ]);

    // Compatibilité avec l'ancien nom de variable
    wp_localize_script('pc-housing-dashboard-js', 'pcReservationVars', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pc_resa_manual_create'),
        'current_user_can_manage' => current_user_can('manage_options'),
    ]);
}

/**
 * Hook pour charger les assets dans l'App Shell
 */
add_action('pc_resa_app_enqueue_assets', 'pc_housing_conditional_assets');
function pc_housing_conditional_assets()
{
    // Charger les assets seulement si on est sur la page Housing ou si on utilise le shortcode
    global $post;

    if (
        // Si on est dans l'App Shell (détecté par query_var)
        get_query_var('pc_app_dashboard') ||
        // Ou si la page contient le shortcode
        (is_object($post) && has_shortcode($post->post_content, 'pc_housing_dashboard'))
    ) {
        pc_housing_enqueue_assets();
    }
}

// Enregistrement du shortcode
add_shortcode('pc_housing_dashboard', 'pc_shortcode_housing_dashboard');
