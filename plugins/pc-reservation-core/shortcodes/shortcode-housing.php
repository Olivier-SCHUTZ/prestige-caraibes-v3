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

            <div class="pc-action-wrapper">
                <button type="button" class="pc-btn pc-btn-primary" id="pc-new-housing-btn">
                    <span class="pc-btn-icon">➕</span>
                    Nouveau Logement
                </button>
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
                        <button class="pc-tab-btn" data-tab="rates">
                            <span class="pc-tab-icon">📅</span>
                            Saisons & Promos
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
                                <!-- NOUVEAU : Sélecteur de type de logement -->
                                <div class="pc-form-group">
                                    <label for="housing-type-selector">Type de logement *</label>
                                    <select id="housing-type-selector" class="pc-select" required>
                                        <option value="">-- Choisir le type --</option>
                                        <option value="villa">Villa</option>
                                        <option value="appartement">Appartement</option>
                                    </select>
                                    <small class="pc-field-help">
                                        <span id="housing-type-help-creation">⚠️ Ce choix est définitif et ne pourra pas être modifié après création.</span>
                                        <span id="housing-type-help-edit" style="display: none;">ℹ️ Le type ne peut pas être modifié pour un logement existant.</span>
                                    </small>
                                </div>

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

                                <!-- Séparateur visuel pour les Règles de Paiement -->
                                <div class="pc-form-group pc-form-group--full" style="margin-top: 2rem;">
                                    <h3 style="font-size: 1.2rem; font-weight: 700; color: #1e293b; margin: 0 0 1.5rem 0; display: flex; align-items: center; gap: 0.8rem; padding-bottom: 1rem; border-bottom: 2px solid rgba(148, 163, 184, 0.3);">
                                        💳 Règles de Paiement
                                    </h3>
                                </div>

                                <!-- Champs Règles de Paiement - Intégrés dans la grille standard 2 colonnes -->
                                <div class="pc-form-group">
                                    <label for="pc_pay_mode">Mode de paiement</label>
                                    <select id="pc_pay_mode" class="pc-select">
                                        <option value="acompte_plus_solde">Acompte plus solde</option>
                                        <option value="total_a_la_reservation">Total à la réservation</option>
                                        <option value="sur_place">Sur place</option>
                                        <option value="sur_devis">Sur devis</option>
                                    </select>
                                </div>

                                <div class="pc-form-group">
                                    <label for="pc_deposit_type">Type d'acompte</label>
                                    <select id="pc_deposit_type" class="pc-select">
                                        <option value="pourcentage">Pourcentage</option>
                                        <option value="montant_fixe">Montant fixe</option>
                                    </select>
                                </div>

                                <div class="pc-form-group">
                                    <label for="pc_deposit_value">Somme ou %</label>
                                    <input type="number" id="pc_deposit_value" class="pc-input" placeholder="30">
                                    <small class="pc-field-help">valeur numérique (ex : 30 ou 500)</small>
                                </div>

                                <div class="pc-form-group">
                                    <label for="pc_balance_delay_days">Solde / Jours</label>
                                    <input type="number" id="pc_balance_delay_days" class="pc-input" placeholder="30">
                                    <small class="pc-field-help">ex : 30 (= X jours avant arrivée / expérience)</small>
                                </div>

                                <div class="pc-form-group">
                                    <label for="pc_caution_amount">Montant Caution</label>
                                    <input type="number" id="pc_caution_amount" class="pc-input" min="0" step="1" placeholder="500">
                                </div>

                                <div class="pc-form-group">
                                    <label for="pc_caution_type">Méthode Caution</label>
                                    <select id="pc_caution_type" class="pc-select">
                                        <option value="aucune">Aucune caution</option>
                                        <option value="empreinte">Empreinte bancaire</option>
                                        <option value="encaissement">Caution encaisser</option>
                                    </select>
                                    <small class="pc-field-help">Aucune caution = Le propriétaire s'en occupe</small>
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

                                <!-- 🔧 NOUVELLES CATÉGORIES D'ÉQUIPEMENTS AJOUTÉES -->

                                <div class="pc-form-group pc-form-group--full">
                                    <label>Politiques</label>
                                    <div class="pc-checkbox-group">
                                        <label><input type="checkbox" name="eq_politiques[]" value="Carte de crédit acceptée" class="pc-checkbox-field"> Carte de crédit acceptée</label>
                                        <label><input type="checkbox" name="eq_politiques[]" value="Enfants autorisés" class="pc-checkbox-field"> Enfants autorisés</label>
                                        <label><input type="checkbox" name="eq_politiques[]" value="Enfants non autorisés" class="pc-checkbox-field"> Enfants non autorisés</label>
                                        <label><input type="checkbox" name="eq_politiques[]" value="Animaux non autorisés" class="pc-checkbox-field"> Animaux non autorisés</label>
                                        <label><input type="checkbox" name="eq_politiques[]" value="Fumeurs non autorisés" class="pc-checkbox-field"> Fumeurs non autorisés</label>
                                        <label><input type="checkbox" name="eq_politiques[]" value="Fumeurs autorisés en extérieur" class="pc-checkbox-field"> Fumeurs autorisés en extérieur</label>
                                        <label><input type="checkbox" name="eq_politiques[]" value="Convient aux personnes âgées ou à mobilité réduite" class="pc-checkbox-field"> Convient aux personnes âgées ou à mobilité réduite</label>
                                        <label><input type="checkbox" name="eq_politiques[]" value="Ne convient pas aux personnes âgées ou à mobilité réduite" class="pc-checkbox-field"> Ne convient pas aux personnes âgées ou à mobilité réduite</label>
                                        <label><input type="checkbox" name="eq_politiques[]" value="Accessible aux fauteuils roulants" class="pc-checkbox-field"> Accessible aux fauteuils roulants</label>
                                        <label><input type="checkbox" name="eq_politiques[]" value="Non accessible aux fauteuils roulants" class="pc-checkbox-field"> Non accessible aux fauteuils roulants</label>
                                        <label><input type="checkbox" name="eq_politiques[]" value="Services de conciergerie accessibles" class="pc-checkbox-field"> Services de conciergerie accessibles</label>
                                    </div>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label>Divertissements</label>
                                    <div class="pc-checkbox-group">
                                        <label><input type="checkbox" name="eq_divertissements[]" value="Chaises de plage disponibles" class="pc-checkbox-field"> Chaises de plage disponibles</label>
                                        <label><input type="checkbox" name="eq_divertissements[]" value="Glacière disponible" class="pc-checkbox-field"> Glacière disponible</label>
                                        <label><input type="checkbox" name="eq_divertissements[]" value="Parasols disponibles" class="pc-checkbox-field"> Parasols disponibles</label>
                                        <label><input type="checkbox" name="eq_divertissements[]" value="Billard" class="pc-checkbox-field"> Billard</label>
                                        <label><input type="checkbox" name="eq_divertissements[]" value="Baby-foot" class="pc-checkbox-field"> Baby-foot</label>
                                        <label><input type="checkbox" name="eq_divertissements[]" value="Jeux de société" class="pc-checkbox-field"> Jeux de société</label>
                                        <label><input type="checkbox" name="eq_divertissements[]" value="Livres" class="pc-checkbox-field"> Livres</label>
                                        <label><input type="checkbox" name="eq_divertissements[]" value="Chaîne Hi-Fi" class="pc-checkbox-field"> Chaîne Hi-Fi</label>
                                        <label><input type="checkbox" name="eq_divertissements[]" value="TV" class="pc-checkbox-field"> TV</label>
                                        <label><input type="checkbox" name="eq_divertissements[]" value="Streaming disponible (avec votre compte)" class="pc-checkbox-field"> Streaming disponible (avec votre compte)</label>
                                    </div>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label>Caractéristiques de l'emplacement</label>
                                    <div class="pc-checkbox-group">
                                        <label><input type="checkbox" name="eq_caracteristiques_emplacement[]" value="Proche de la mer" class="pc-checkbox-field"> Proche de la mer</label>
                                        <label><input type="checkbox" name="eq_caracteristiques_emplacement[]" value="Bord de plage" class="pc-checkbox-field"> Bord de plage</label>
                                        <label><input type="checkbox" name="eq_caracteristiques_emplacement[]" value="Plage accessible à pied" class="pc-checkbox-field"> Plage accessible à pied</label>
                                        <label><input type="checkbox" name="eq_caracteristiques_emplacement[]" value="Plages accessibles en voiture" class="pc-checkbox-field"> Plages accessibles en voiture</label>
                                        <label><input type="checkbox" name="eq_caracteristiques_emplacement[]" value="Plage accessible en voiture -15 min" class="pc-checkbox-field"> Plage accessible en voiture -15 min</label>
                                        <label><input type="checkbox" name="eq_caracteristiques_emplacement[]" value="Centre-ville" class="pc-checkbox-field"> Centre-ville</label>
                                        <label><input type="checkbox" name="eq_caracteristiques_emplacement[]" value="Vue sur mer" class="pc-checkbox-field"> Vue sur mer</label>
                                    </div>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label>Salle de bain & buanderie</label>
                                    <div class="pc-checkbox-group">
                                        <label><input type="checkbox" name="eq_salle_de_bain_blanchisserie[]" value="Linge de lit fournis" class="pc-checkbox-field"> Linge de lit fournis</label>
                                        <label><input type="checkbox" name="eq_salle_de_bain_blanchisserie[]" value="Serviettes de bain" class="pc-checkbox-field"> Serviettes de bain</label>
                                        <label><input type="checkbox" name="eq_salle_de_bain_blanchisserie[]" value="Serviettes de toilette" class="pc-checkbox-field"> Serviettes de toilette</label>
                                        <label><input type="checkbox" name="eq_salle_de_bain_blanchisserie[]" value="Serviettes de plage" class="pc-checkbox-field"> Serviettes de plage</label>
                                        <label><input type="checkbox" name="eq_salle_de_bain_blanchisserie[]" value="Foutas" class="pc-checkbox-field"> Foutas</label>
                                        <label><input type="checkbox" name="eq_salle_de_bain_blanchisserie[]" value="Sèche-cheveux" class="pc-checkbox-field"> Sèche-cheveux</label>
                                        <label><input type="checkbox" name="eq_salle_de_bain_blanchisserie[]" value="Machine à laver" class="pc-checkbox-field"> Machine à laver</label>
                                        <label><input type="checkbox" name="eq_salle_de_bain_blanchisserie[]" value="Lave-linge dans cuisine" class="pc-checkbox-field"> Lave-linge dans cuisine</label>
                                        <label><input type="checkbox" name="eq_salle_de_bain_blanchisserie[]" value="Sèche-linge" class="pc-checkbox-field"> Sèche-linge</label>
                                        <label><input type="checkbox" name="eq_salle_de_bain_blanchisserie[]" value="Douche" class="pc-checkbox-field"> Douche</label>
                                        <label><input type="checkbox" name="eq_salle_de_bain_blanchisserie[]" value="Baignoire" class="pc-checkbox-field"> Baignoire</label>
                                        <label><input type="checkbox" name="eq_salle_de_bain_blanchisserie[]" value="Buanderie séparée" class="pc-checkbox-field"> Buanderie séparée</label>
                                        <label><input type="checkbox" name="eq_salle_de_bain_blanchisserie[]" value="Fer et table à repasser" class="pc-checkbox-field"> Fer et table à repasser</label>
                                        <label><input type="checkbox" name="eq_salle_de_bain_blanchisserie[]" value="Toilette invités" class="pc-checkbox-field"> Toilette invités</label>
                                    </div>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label>Sécurité à la maison</label>
                                    <div class="pc-checkbox-group">
                                        <label><input type="checkbox" name="eq_securite_maison[]" value="Détecteur de monoxyde de carbone" class="pc-checkbox-field"> Détecteur de monoxyde de carbone</label>
                                        <label><input type="checkbox" name="eq_securite_maison[]" value="Détecteur de fumée" class="pc-checkbox-field"> Détecteur de fumée</label>
                                        <label><input type="checkbox" name="eq_securite_maison[]" value="Coffre-fort" class="pc-checkbox-field"> Coffre-fort</label>
                                        <label><input type="checkbox" name="eq_securite_maison[]" value="Extincteur" class="pc-checkbox-field"> Extincteur</label>
                                        <label><input type="checkbox" name="eq_securite_maison[]" value="Sécurité piscine (alarme)" class="pc-checkbox-field"> Sécurité piscine (alarme)</label>
                                        <label><input type="checkbox" name="eq_securite_maison[]" value="Sécurité piscine (clôture)" class="pc-checkbox-field"> Sécurité piscine (clôture)</label>
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

                        <!-- Onglet Saisons & Promos -->
                        <div class="pc-tab-content" id="tab-rates" style="display: none;">
                            <div class="pc-rates-container">
                                <div class="pc-rates-sidebar">
                                    <h3>Gestion Saisons</h3>
                                    <div class="pc-draggable-list" id="pc-rates-list">
                                    </div>
                                    <div class="pc-rates-actions">
                                        <button type="button" class="pc-btn pc-btn-primary pc-btn-full" id="btn-add-season" style="margin-bottom:10px;">
                                            <span style="color: #fff;">➕</span> Ajouter une saison
                                        </button>
                                        <button type="button" class="pc-btn pc-btn-secondary pc-btn-full" id="btn-add-promo">
                                            <span>🏷️</span> Ajouter une promo
                                        </button>
                                    </div>
                                    <p style="font-size:11px;color:#666;margin-top:15px;text-align:center;">
                                        Glissez les éléments sur le calendrier pour créer des périodes.
                                    </p>
                                </div>

                                <div class="pc-rates-calendar-wrapper">
                                    <div id="pc-rates-calendar"></div>
                                </div>
                            </div>

                            <div id="pc-rate-internal-modal" style="display:none;">
                                <div class="pc-modal-content">
                                    <h3 style="margin-top:0;">Éditer</h3>
                                    <input type="hidden" id="pc-rate-modal-type">
                                    <input type="hidden" id="pc-rate-modal-id">

                                    <div class="pc-form-group">
                                        <label>Nom</label>
                                        <input type="text" id="pc-rate-name" class="pc-input">
                                    </div>

                                    <div id="pc-rate-season-fields">
                                        <div class="pc-rate-form-grid">
                                            <div class="pc-form-group">
                                                <label>Prix (€)</label>
                                                <input type="number" id="pc-rate-price" class="pc-input">
                                            </div>
                                            <div class="pc-form-group">
                                                <label>Min. Nuits</label>
                                                <input type="number" id="pc-rate-min-nights" class="pc-input">
                                            </div>
                                        </div>

                                        <div class="pc-form-group">
                                            <label>Note interne</label>
                                            <input type="text" id="pc-rate-note" class="pc-input" placeholder="Note privée pour cette saison">
                                        </div>

                                        <div class="pc-rate-form-grid">
                                            <div class="pc-form-group">
                                                <label>Frais invités supp. (€)</label>
                                                <input type="number" id="pc-rate-guest-fee" class="pc-input" step="0.01" min="0" placeholder="0.00">
                                            </div>
                                            <div class="pc-form-group">
                                                <label>À partir de ... invités</label>
                                                <input type="number" id="pc-rate-guest-from" class="pc-input" min="1" placeholder="0">
                                            </div>
                                        </div>
                                    </div>

                                    <div id="pc-rate-promo-fields" style="display:none;">
                                        <div class="pc-rate-form-grid">
                                            <div class="pc-form-group">
                                                <label>Type</label>
                                                <select id="pc-rate-promo-type" class="pc-select">
                                                    <option value="percent">%</option>
                                                    <option value="fixed">€</option>
                                                </select>
                                            </div>
                                            <div class="pc-form-group">
                                                <label>Valeur</label>
                                                <input type="number" id="pc-rate-promo-val" class="pc-input">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Section Périodes -->
                                    <div class="pc-form-group pc-form-group--full" style="margin-top: 20px;">
                                        <label>Période(s) d'application</label>

                                        <!-- Message de feedback -->
                                        <div id="pc-period-feedback" class="pc-period-feedback" style="display: none;">
                                            <!-- Message de confirmation généré dynamiquement -->
                                        </div>

                                        <!-- Liste des périodes existantes -->
                                        <ul id="pc-rate-periods-list" style="list-style: none; padding: 0; margin: 10px 0; max-height: 150px; overflow-y: auto;">
                                            <!-- Périodes générées dynamiquement -->
                                        </ul>

                                        <!-- Zone d'ajout avec Flatpickr -->
                                        <div class="pc-form-group" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; background: #f8fafc;">
                                            <label style="font-size: 13px; color: #64748b; margin-bottom: 8px; display: block;">Sélectionner une période :</label>

                                            <div class="pc-flatpickr-container" style="margin-bottom: 15px;">
                                                <input type="text" id="pc-rate-period-range" class="pc-input" placeholder="Sélectionnez une période..." readonly style="cursor: pointer; background: white;">
                                            </div>

                                            <button type="button" id="btn-add-period-range" class="pc-btn pc-btn-primary" style="width: 100%; font-size: 13px;">
                                                <span>➕</span> Ajouter cette période
                                            </button>

                                            <p style="font-size: 11px; color: #64748b; margin: 8px 0 0 0; font-style: italic;">
                                                * Cliquez sur le champ pour ouvrir le calendrier et sélectionner une plage de dates.
                                            </p>
                                        </div>
                                    </div>

                                    <div style="display:flex; justify-content:space-between; margin-top:20px;">
                                        <button type="button" class="pc-btn pc-btn-danger" id="btn-delete-rate-internal">Supprimer</button>
                                        <div style="display:flex; gap:10px;">
                                            <button type="button" class="pc-btn pc-btn-secondary" id="btn-cancel-rate-internal">Annuler</button>
                                            <button type="button" class="pc-btn pc-btn-primary" id="btn-save-rate-internal">Valider</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <!-- Onglet Configuration & SEO -->
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

                                <!-- Séparateur visuel pour les Infos Contrat & Propriétaire -->
                                <div class="pc-form-group pc-form-group--full" style="margin-top: 3rem;">
                                    <h3 style="font-size: 1.3rem; font-weight: 700; color: #1e293b; margin: 0 0 1.5rem 0; display: flex; align-items: center; gap: 0.8rem; padding-bottom: 1rem; border-bottom: 2px solid rgba(148, 163, 184, 0.3);">
                                        📋 Informations Contrat & Propriétaire
                                    </h3>
                                    <p style="color: #64748b; font-size: 0.9rem; margin: 0 0 1.5rem 0;">Ces informations apparaissent sur le contrat de location PDF.</p>
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-proprietaire-identite">Identité du propriétaire (Société ou Nom)</label>
                                    <input type="text" id="housing-proprietaire-identite" class="pc-input" placeholder="ex: EI VILLA TREZEL">
                                </div>

                                <div class="pc-form-group">
                                    <label for="housing-personne-logement">Capacité Max (Assurance)</label>
                                    <input type="number" id="housing-personne-logement" class="pc-input" min="1" max="50" placeholder="6">
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="housing-proprietaire-adresse">Adresse complète du bien loué</label>
                                    <textarea id="housing-proprietaire-adresse" class="pc-textarea" rows="3" placeholder="Adresse complète du bien en location..."></textarea>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="housing-description-contrat">Descriptif succinct du logement</label>
                                    <textarea id="housing-description-contrat" class="pc-textarea" rows="3" placeholder="Ex: Villa T4 avec piscine..."></textarea>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="housing-equipements-contrat">Liste des équipements (Contrat)</label>
                                    <textarea id="housing-equipements-contrat" class="pc-textarea" rows="3" placeholder="Clim, Wifi, TV..."></textarea>
                                </div>

                                <!-- Options Booléennes (Checkboxes) - Alignées sur une ligne -->
                                <div class="pc-form-group pc-form-group--full">
                                    <label>Équipements spéciaux</label>
                                    <div class="pc-checkbox-group" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                                        <label class="pc-checkbox-container">
                                            <input type="checkbox" id="housing-has-piscine" name="has_piscine" value="1" class="pc-checkbox-field pc-checkbox-boolean">
                                            <span class="pc-checkbox-label">Piscine</span>
                                        </label>
                                        <label class="pc-checkbox-container">
                                            <input type="checkbox" id="housing-has-jacuzzi" name="has_jacuzzi" value="1" class="pc-checkbox-field pc-checkbox-boolean">
                                            <span class="pc-checkbox-label">Jacuzzi</span>
                                        </label>
                                        <label class="pc-checkbox-container">
                                            <input type="checkbox" id="housing-has-guide" name="has_guide_numerique" value="1" class="pc-checkbox-field pc-checkbox-boolean">
                                            <span class="pc-checkbox-label">Livret d'accueil numérique</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pc-modal-footer">
                <div class="pc-modal-actions">
                    <button type="button" class="pc-btn pc-btn-danger" id="pc-housing-delete-btn">
                        <span class="pc-btn-text">Supprimer</span>
                        <span class="pc-btn-spinner" style="display: none;">
                            <div class="pc-spinner-sm"></div>
                        </span>
                    </button>
                    <div class="pc-modal-actions-right">
                        <button type="button" class="pc-btn pc-btn-secondary" onclick="closeHousingModal()">
                            Annuler
                        </button>
                        <button type="button" class="pc-btn pc-btn-primary" id="pc-housing-save-btn">
                            <span class="pc-btn-text">Enregistrer</span>
                            <span class="pc-btn-spinner" style="display: none;">
                                <div class="pc-spinner-sm"></div>
                            </span>
                        </button>
                    </div>
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
    // FullCalendar (Requis pour Rate Manager)
    wp_enqueue_style('fullcalendar-css', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css');
    wp_enqueue_script('fullcalendar-js', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js', [], '6.1.10', true);

    // Flatpickr (pour le sélecteur de dates moderne)
    wp_enqueue_style('flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css');
    wp_enqueue_script('flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js', [], '4.6.13', true);
    wp_enqueue_script('flatpickr-fr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js', ['flatpickr-js'], '4.6.13', true);

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

    // Rate Manager Logic & CSS
    wp_enqueue_style(
        'pc-rates-css',
        PC_RES_CORE_URL . 'assets/css/dashboard-rates.css',
        [],
        PC_RES_CORE_VERSION
    );
    wp_enqueue_script(
        'pc-rates-js',
        PC_RES_CORE_URL . 'assets/js/dashboard-rates.js',
        ['jquery', 'fullcalendar-js'],
        PC_RES_CORE_VERSION,
        true
    );

    // JavaScript pour le Housing Manager
    wp_enqueue_script(
        'pc-housing-dashboard-js',
        PC_RES_CORE_URL . 'assets/js/dashboard-housing.js',
        ['jquery', 'pc-rates-js'], // Dépendance ajoutée
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
