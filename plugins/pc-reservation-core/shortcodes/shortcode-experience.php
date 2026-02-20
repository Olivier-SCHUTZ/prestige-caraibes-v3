<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PC Experience Dashboard Shortcode
 * Interface de gestion des expériences pour l'App Shell
 * 
 * @since 0.1.4
 */

/**
 * Shortcode principal pour le dashboard des expériences
 */
function pc_shortcode_experience_dashboard($atts = [])
{
    // Vérification des permissions
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        return '<div class="pc-error">Accès non autorisé</div>';
    }

    // Parser les attributs
    $atts = shortcode_atts([
        'per_page' => 20,
    ], $atts, 'pc_experience_dashboard');

    // Enqueue les assets nécessaires
    pc_experience_enqueue_assets();

    // Enqueue WordPress Media Library
    wp_enqueue_media();

    // Générer le HTML
    ob_start();
?>

    <!-- Experience Manager Interface -->
    <div class="pc-experience-manager">
        <div class="pc-experience-header">
            <h1 class="pc-experience-title">
                <span class="pc-experience-icon">🎯</span>
                Gestion des Expériences
            </h1>
            <p class="pc-experience-subtitle">
                Gérez vos expériences, leurs informations et paramètres de réservation
            </p>
        </div>

        <!-- Barre de recherche et filtres -->
        <div class="pc-experience-controls">
            <div class="pc-search-wrapper">
                <input type="text" id="pc-experience-search" placeholder="🔍 Rechercher une expérience..." class="pc-input pc-search-input">
            </div>

            <div class="pc-action-wrapper">
                <button type="button" class="pc-btn pc-btn-primary" id="pc-new-experience-btn">
                    <span class="pc-btn-icon">➕</span>
                    Nouvelle Expérience
                </button>
            </div>

            <div class="pc-filters-wrapper">
                <select id="pc-experience-status-filter" class="pc-select pc-filter-select">
                    <option value="">Tous les statuts</option>
                    <option value="publish">Publié</option>
                    <option value="pending">En attente</option>
                    <option value="draft">Brouillon</option>
                    <option value="private">Privé</option>
                </select>
            </div>
        </div>

        <!-- Tableau des expériences -->
        <div class="pc-experience-table-wrapper">
            <div class="pc-loading" id="pc-experience-loading">
                <div class="pc-spinner"></div>
                <span>Chargement des expériences...</span>
            </div>

            <table class="pc-table pc-experience-table" id="pc-experience-table" style="display: none;">
                <thead>
                    <tr>
                        <th class="pc-col-image">Image</th>
                        <th class="pc-col-name">Nom de l'expérience</th>
                        <th class="pc-col-duration">Durée</th>
                        <th class="pc-col-capacity">Capacité</th>
                        <th class="pc-col-location">Lieu de départ</th>
                        <th class="pc-col-tva">TVA (%)</th>
                        <th class="pc-col-status">Statut</th>
                        <th class="pc-col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="pc-experience-table-body">
                    <!-- Contenu généré dynamiquement par JavaScript -->
                </tbody>
            </table>

            <div class="pc-empty-state" id="pc-experience-empty" style="display: none;">
                <div class="pc-empty-icon">🎯</div>
                <h3>Aucune expérience trouvée</h3>
                <p>Aucune expérience ne correspond aux critères de recherche.</p>
            </div>
        </div>

        <!-- Pagination -->
        <div class="pc-pagination-wrapper">
            <div class="pc-pagination" id="pc-experience-pagination">
                <!-- Pagination générée dynamiquement -->
            </div>
        </div>
    </div>

    <!-- Modale de détail expérience -->
    <div id="experience-modal" class="pc-modal hidden">
        <div class="pc-modal-overlay"></div>
        <div class="pc-modal-container">
            <div class="pc-modal-header">
                <h2 class="pc-modal-title">
                    <span class="pc-modal-icon">🎯</span>
                    <span id="pc-experience-modal-title">Détails de l'expérience</span>
                </h2>
                <button class="pc-modal-close">×</button>
            </div>

            <div class="pc-modal-content">
                <div class="pc-experience-loading" id="pc-experience-modal-loading">
                    <div class="pc-spinner"></div>
                    <span>Chargement des détails...</span>
                </div>

                <div class="pc-experience-details" id="pc-experience-modal-details" style="display: none;">
                    <!-- Navigation par onglets -->
                    <div class="pc-tabs-nav">
                        <button class="pc-tab-btn active" data-tab="seo-liaisons">
                            <span class="pc-tab-icon">🔗</span>
                            SEO & Liaisons
                        </button>
                        <button class="pc-tab-btn" data-tab="main">
                            <span class="pc-tab-icon">📝</span>
                            Détails principaux
                        </button>
                        <button class="pc-tab-btn" data-tab="details">
                            <span class="pc-tab-icon">⏱️</span>
                            Détails sorties
                        </button>
                        <button class="pc-tab-btn" data-tab="inclusions">
                            <span class="pc-tab-icon">📋</span>
                            Inclusions
                        </button>
                        <button class="pc-tab-btn" data-tab="services">
                            <span class="pc-tab-icon">🛎️</span>
                            Services
                        </button>
                        <button class="pc-tab-btn" data-tab="gallery">
                            <span class="pc-tab-icon">🖼️</span>
                            Galerie
                        </button>
                        <button class="pc-tab-btn" data-tab="faq">
                            <span class="pc-tab-icon">❓</span>
                            FAQ
                        </button>
                        <button class="pc-tab-btn" data-tab="exp-rates">
                            <span class="pc-tab-icon">💰</span>
                            Tarifs
                        </button>
                        <button class="pc-tab-btn" data-tab="rules">
                            <span class="pc-tab-icon">⚙️</span>
                            Règles Channel Manager
                        </button>
                    </div>

                    <!-- Contenu des onglets -->
                    <div class="pc-tabs-content">
                        <!-- Onglet 1 : SEO & Liaisons -->
                        <div class="pc-tab-content active" id="tab-seo-liaisons">
                            <div class="pc-form-grid">
                                <div class="pc-form-group">
                                    <label for="exp_exclude_sitemap">Exclure du sitemap</label>
                                    <label class="pc-checkbox-container">
                                        <input type="checkbox" id="exp_exclude_sitemap" name="exp_exclude_sitemap" value="1" class="pc-checkbox-field pc-checkbox-boolean">
                                        <span class="pc-checkbox-label">Cochez pour retirer cette page du sitemap</span>
                                    </label>
                                </div>

                                <div class="pc-form-group">
                                    <label for="exp_http_410">Servir un 410 Gone</label>
                                    <label class="pc-checkbox-container">
                                        <input type="checkbox" id="exp_http_410" name="exp_http_410" value="1" class="pc-checkbox-field pc-checkbox-boolean">
                                        <span class="pc-checkbox-label">Cochez si le contenu est définitivement supprimé</span>
                                    </label>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="exp_meta_titre">Méta Titre</label>
                                    <input type="text" id="exp_meta_titre" class="pc-input" placeholder="Le titre qui apparaîtra dans les résultats de recherche Google" maxlength="65">
                                    <small class="pc-field-help">Environ 60 caractères. S'il est vide, le titre de l'expérience sera utilisé.</small>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="exp_meta_description">Méta Description</label>
                                    <textarea id="exp_meta_description" class="pc-textarea" rows="3" placeholder="Le texte descriptif qui apparaîtra sous le titre dans Google" maxlength="160"></textarea>
                                    <small class="pc-field-help">Environ 160 caractères.</small>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="exp_meta_canonical">URL canonique (facultatif)</label>
                                    <input type="url" id="exp_meta_canonical" class="pc-input" placeholder="https://example.com/canonical-url">
                                    <small class="pc-field-help">Laissez vide pour utiliser l'URL de cette page.</small>
                                </div>

                                <div class="pc-form-group">
                                    <label for="exp_meta_robots">Meta robots</label>
                                    <select id="exp_meta_robots" class="pc-select">
                                        <option value="index,follow">index,follow</option>
                                        <option value="noindex,follow">noindex,follow</option>
                                        <option value="noindex,nofollow">noindex,nofollow</option>
                                    </select>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="exp_logements_recommandes">Logements Recommandés (IDs)</label>
                                    <input type="text" id="exp_logements_recommandes" class="pc-input" placeholder="123,456,789">
                                    <small class="pc-field-help">IDs des villas/appartements recommandés séparés par des virgules</small>
                                </div>

                                <div class="pc-form-group">
                                    <label for="exp_availability">Situation de l'expérience</label>
                                    <select id="exp_availability" class="pc-select">
                                        <option value="InStock">Réservable actuellement</option>
                                        <option value="SoldOut">Complet / Plus de places</option>
                                        <option value="PreOrder">Bientôt disponible</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Onglet 2 : Détails principaux -->
                        <div class="pc-tab-content" id="tab-main" style="display: none;">
                            <div class="pc-form-grid">
                                <div class="pc-form-group pc-form-group--full">
                                    <label for="exp_h1_custom">H1 (optionnel)</label>
                                    <input type="text" id="exp_h1_custom" class="pc-input" placeholder="Titre principal (h1) de la page pour le SEO">
                                </div>

                                <div class="pc-form-group">
                                    <label for="exp_hero_desktop">Photo principale (Desktop)</label>
                                    <div class="pc-image-uploader" data-field="hero-desktop">
                                        <div class="pc-image-preview" id="preview-exp-hero-desktop">
                                            <div class="pc-image-placeholder">
                                                📷 Aucune image sélectionnée
                                            </div>
                                        </div>
                                        <input type="hidden" id="exp_hero_desktop" name="exp_hero_desktop" value="">
                                        <div class="pc-image-actions">
                                            <button type="button" class="pc-btn pc-btn-select-image" data-target="exp-hero-desktop">
                                                <span>🖼️</span> Choisir une image
                                            </button>
                                            <button type="button" class="pc-btn pc-btn-remove-image" data-target="exp-hero-desktop" style="display: none;">
                                                <span>❌</span> Supprimer
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="pc-form-group">
                                    <label for="exp_hero_mobile">Photo principale (Mobile)</label>
                                    <div class="pc-image-uploader" data-field="hero-mobile">
                                        <div class="pc-image-preview" id="preview-exp-hero-mobile">
                                            <div class="pc-image-placeholder">
                                                📱 Aucune image sélectionnée
                                            </div>
                                        </div>
                                        <input type="hidden" id="exp_hero_mobile" name="exp_hero_mobile" value="">
                                        <div class="pc-image-actions">
                                            <button type="button" class="pc-btn pc-btn-select-image" data-target="exp-hero-mobile">
                                                <span>🖼️</span> Choisir une image
                                            </button>
                                            <button type="button" class="pc-btn pc-btn-remove-image" data-target="exp-hero-mobile" style="display: none;">
                                                <span>❌</span> Supprimer
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Onglet 3 : Détails sorties -->
                        <div class="pc-tab-content" id="tab-details" style="display: none;">
                            <div class="pc-form-grid">
                                <div class="pc-form-group">
                                    <label for="exp_duree">Durée (heures)</label>
                                    <input type="number" id="exp_duree" class="pc-input" min="0" step="0.5" placeholder="2.5">
                                    <small class="pc-field-help">Durée de l'expérience en heures</small>
                                </div>

                                <div class="pc-form-group">
                                    <label for="exp_capacite">Capacité</label>
                                    <input type="number" id="exp_capacite" class="pc-input" min="1" step="1" placeholder="8">
                                    <small class="pc-field-help">Nombre maximum de participants</small>
                                </div>

                                <div class="pc-form-group">
                                    <label for="exp_age_minimum">Âge minimum</label>
                                    <input type="number" id="exp_age_minimum" class="pc-input" min="0" step="1" placeholder="12">
                                    <small class="pc-field-help">Âge minimum requis (0 si aucun)</small>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label>Accessibilité</label>
                                    <div class="pc-checkbox-group">
                                        <label><input type="checkbox" name="exp_accessibilite[]" value="accessible_pmr" class="pc-checkbox-field"> Accessible aux personnes âgées ou mobilité réduite</label>
                                        <label><input type="checkbox" name="exp_accessibilite[]" value="accessible_pmr_f" class="pc-checkbox-field"> Non accessible aux fauteuils roulants</label>
                                        <label><input type="checkbox" name="exp_accessibilite[]" value="poussettes" class="pc-checkbox-field"> Accessible en poussette</label>
                                        <label><input type="checkbox" name="exp_accessibilite[]" value="animaux_admis" class="pc-checkbox-field"> Animaux de compagnie admis</label>
                                        <label><input type="checkbox" name="exp_accessibilite[]" value="accessible_enfants" class="pc-checkbox-field"> Accessible aux enfants</label>
                                        <label><input type="checkbox" name="exp_accessibilite[]" value="activité_physique" class="pc-checkbox-field"> Activité physique ou sportive intense</label>
                                        <label><input type="checkbox" name="exp_accessibilite[]" value="activité_physique_m" class="pc-checkbox-field"> Activité physique ou sportive moyenne</label>
                                        <label><input type="checkbox" name="exp_accessibilite[]" value="activité_physique_l" class="pc-checkbox-field"> Activité physique ou sportive légère</label>
                                    </div>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label>Période</label>
                                    <div class="pc-checkbox-group">
                                        <label><input type="checkbox" name="exp_periode[]" value="année" class="pc-checkbox-field"> Toute l'année</label>
                                        <label><input type="checkbox" name="exp_periode[]" value="saison" class="pc-checkbox-field"> En saison</label>
                                        <label><input type="checkbox" name="exp_periode[]" value="réservation" class="pc-checkbox-field"> Sur réservation</label>
                                    </div>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label>Jour de départ</label>
                                    <div class="pc-checkbox-group">
                                        <label><input type="checkbox" name="exp_jour[]" value="tous" class="pc-checkbox-field"> Tous les jours</label>
                                        <label><input type="checkbox" name="exp_jour[]" value="lundi" class="pc-checkbox-field"> Lundi</label>
                                        <label><input type="checkbox" name="exp_jour[]" value="mardi" class="pc-checkbox-field"> Mardi</label>
                                        <label><input type="checkbox" name="exp_jour[]" value="mercredi" class="pc-checkbox-field"> Mercredi</label>
                                        <label><input type="checkbox" name="exp_jour[]" value="jeudi" class="pc-checkbox-field"> Jeudi</label>
                                        <label><input type="checkbox" name="exp_jour[]" value="vendredi" class="pc-checkbox-field"> Vendredi</label>
                                        <label><input type="checkbox" name="exp_jour[]" value="samedi" class="pc-checkbox-field"> Samedi</label>
                                        <label><input type="checkbox" name="exp_jour[]" value="dimanche" class="pc-checkbox-field"> Dimanche</label>
                                    </div>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label>Périodes de fermeture</label>
                                    <div id="wrapper-exp_periodes_fermeture">
                                        <p class="pc-repeater-placeholder">Container Repeater pour les périodes de fermeture - À implémenter via JavaScript</p>
                                    </div>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label>Lieux et Horaires de départ</label>
                                    <div id="wrapper-exp_lieux_horaires_depart">
                                        <p class="pc-repeater-placeholder">Container Repeater pour les lieux et horaires - À implémenter via JavaScript</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Onglet 4 : Inclusions -->
                        <div class="pc-tab-content" id="tab-inclusions" style="display: none;">
                            <div class="pc-form-grid">
                                <div class="pc-form-group pc-form-group--full">
                                    <label for="exp_prix_comprend">Le prix comprend</label>
                                    <textarea id="exp_prix_comprend" class="pc-textarea" rows="6" placeholder="Utilisez des listes à puces pour plus de clarté (ex: Repas du midi, Boissons, Matériel de snorkeling...)"></textarea>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="exp_prix_ne_comprend_pas">Le prix ne comprend pas</label>
                                    <textarea id="exp_prix_ne_comprend_pas" class="pc-textarea" rows="6" placeholder="Listez ce qui n'est pas inclus (ex: Pourboires, Dépenses personnelles...)"></textarea>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label>À prévoir</label>
                                    <div class="pc-checkbox-group">
                                        <label><input type="checkbox" name="exp_a_prevoir[]" value="creme_solaire" class="pc-checkbox-field"> Crème solaire minérale</label>
                                        <label><input type="checkbox" name="exp_a_prevoir[]" value="serviette" class="pc-checkbox-field"> Serviette de bain</label>
                                        <label><input type="checkbox" name="exp_a_prevoir[]" value="maillot_de_bain" class="pc-checkbox-field"> Maillot de bain</label>
                                        <label><input type="checkbox" name="exp_a_prevoir[]" value="eau_collations" class="pc-checkbox-field"> Une bouteille d'eau et collations</label>
                                        <label><input type="checkbox" name="exp_a_prevoir[]" value="appareil_photo" class="pc-checkbox-field"> Appareil photo</label>
                                        <label><input type="checkbox" name="exp_a_prevoir[]" value="chaussures_marche" class="pc-checkbox-field"> Chaussures de marche</label>
                                        <label><input type="checkbox" name="exp_a_prevoir[]" value="Chaussons_eau" class="pc-checkbox-field"> Chaussons d'eau</label>
                                        <label><input type="checkbox" name="exp_a_prevoir[]" value="Imperméable" class="pc-checkbox-field"> Imperméable/coupe vent</label>
                                        <label><input type="checkbox" name="exp_a_prevoir[]" value="Vêtements" class="pc-checkbox-field"> Vêtements de rechange</label>
                                        <label><input type="checkbox" name="exp_a_prevoir[]" value="Casquette" class="pc-checkbox-field"> Casquette/Bob</label>
                                        <label><input type="checkbox" name="exp_a_prevoir[]" value="Snorkeling" class="pc-checkbox-field"> Équipement de snorkeling</label>
                                        <label><input type="checkbox" name="exp_a_prevoir[]" value="Teeshirt" class="pc-checkbox-field"> Tee-shirts anti UV</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Onglet 5 : Services -->
                        <div class="pc-tab-content" id="tab-services" style="display: none;">
                            <div class="pc-form-grid">
                                <div class="pc-form-group">
                                    <label>Délai de réservation</label>
                                    <div class="pc-checkbox-group">
                                        <label><input type="checkbox" name="exp_delai_de_reservation[]" value="24h" class="pc-checkbox-field"> 24h à l'avance</label>
                                        <label><input type="checkbox" name="exp_delai_de_reservation[]" value="48h" class="pc-checkbox-field"> 48h à l'avance</label>
                                        <label><input type="checkbox" name="exp_delai_de_reservation[]" value="72h" class="pc-checkbox-field"> 72h à l'avance</label>
                                        <label><input type="checkbox" name="exp_delai_de_reservation[]" value="1 semaine" class="pc-checkbox-field"> 1 semaine à l'avance</label>
                                        <label><input type="checkbox" name="exp_delai_de_reservation[]" value="Avant départ" class="pc-checkbox-field"> Avant le départ</label>
                                    </div>
                                </div>

                                <div class="pc-form-group">
                                    <label>Zone intervention</label>
                                    <div class="pc-checkbox-group">
                                        <label><input type="checkbox" name="exp_zone_intervention[]" value="Guadeloupe" class="pc-checkbox-field"> Guadeloupe</label>
                                        <label><input type="checkbox" name="exp_zone_intervention[]" value="Grande-Terre" class="pc-checkbox-field"> Grande-Terre</label>
                                        <label><input type="checkbox" name="exp_zone_intervention[]" value="Basse-Terre" class="pc-checkbox-field"> Basse-Terre</label>
                                        <label><input type="checkbox" name="exp_zone_intervention[]" value="Saint-François, alentours" class="pc-checkbox-field"> Saint-François, alentours</label>
                                        <label><input type="checkbox" name="exp_zone_intervention[]" value="Sainte-Anne, alentours" class="pc-checkbox-field"> Sainte-Anne, alentours</label>
                                        <label><input type="checkbox" name="exp_zone_intervention[]" value="Le Gosier, alentours" class="pc-checkbox-field"> Le Gosier, alentours</label>
                                        <label><input type="checkbox" name="exp_zone_intervention[]" value="Morne à l'Eau, alentours" class="pc-checkbox-field"> Morne à l'Eau, alentours</label>
                                        <label><input type="checkbox" name="exp_zone_intervention[]" value="Baie-Mahault, alentours" class="pc-checkbox-field"> Baie-Mahault, alentours</label>
                                        <label><input type="checkbox" name="exp_zone_intervention[]" value="Deshaies, alentours" class="pc-checkbox-field"> Deshaies, alentours</label>
                                        <label><input type="checkbox" name="exp_zone_intervention[]" value="Bouillante, alentours" class="pc-checkbox-field"> Bouillante, alentours</label>
                                        <label><input type="checkbox" name="exp_zone_intervention[]" value="Basse-Terre, alentours" class="pc-checkbox-field"> Basse-Terre, alentours</label>
                                    </div>
                                </div>

                                <div class="pc-form-group">
                                    <label for="exp_type_de_prestation">Type de prestation</label>
                                    <input type="text" id="exp_type_de_prestation" class="pc-input" placeholder="Ex: Créole, Française pour un chef / Massage relaxant pour un spa">
                                    <small class="pc-field-help">Pour un chef à domicile, massage, petit-déjeuner, etc.</small>
                                </div>

                                <div class="pc-form-group">
                                    <label for="exp_heure_limite_de_commande">Heure limite de commande</label>
                                    <input type="number" id="exp_heure_limite_de_commande" class="pc-input" min="0" max="23" step="1" placeholder="18">
                                    <small class="pc-field-help">Idéal pour les livraisons (ex: Commande avant 18h)</small>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="exp_le_service_comprend">Le service comprend</label>
                                    <textarea id="exp_le_service_comprend" class="pc-textarea" rows="8" placeholder="Détail du service - Copier/Coller fichier format Texte"></textarea>
                                    <small class="pc-field-help">WYSIWYG simulé par Textarea</small>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="exp_service_a_prevoir">À prévoir</label>
                                    <textarea id="exp_service_a_prevoir" class="pc-textarea" rows="6" placeholder="Indiquez ce que le client doit prévoir pour ce service"></textarea>
                                    <small class="pc-field-help">WYSIWYG simulé par Textarea</small>
                                </div>
                            </div>
                        </div>

                        <!-- Onglet 6 : Galerie -->
                        <div class="pc-tab-content" id="tab-gallery" style="display: none;">
                            <div class="pc-form-grid">
                                <div class="pc-form-group pc-form-group--full">
                                    <label for="photos_experience">Photos de l'expérience</label>
                                    <input type="hidden" id="photos_experience" name="photos_experience" value="">
                                    <div id="photos-experience-preview" class="pc-gallery-preview">
                                        <div class="pc-gallery-placeholder">
                                            📷 Aucune photo sélectionnée - Ajoutez jusqu'à 5 photos
                                        </div>
                                    </div>
                                    <div class="pc-gallery-actions">
                                        <button type="button" class="pc-btn pc-btn-primary" id="btn-select-gallery-photos">
                                            <span>🖼️</span> Choisir des photos
                                        </button>
                                        <button type="button" class="pc-btn pc-btn-secondary" id="btn-clear-gallery-photos" style="display: none;">
                                            <span>❌</span> Vider la galerie
                                        </button>
                                    </div>
                                    <small class="pc-field-help">Ajoutez jusqu'à 5 photos pour illustrer l'expérience. L'image principale doit être définie dans "Détails principaux".</small>
                                </div>
                            </div>
                        </div>

                        <!-- Onglet 7 : FAQ -->
                        <div class="pc-tab-content" id="tab-faq" style="display: none;">
                            <div class="pc-form-grid">
                                <div class="pc-form-group pc-form-group--full">
                                    <label>Questions / Réponses</label>
                                    <div id="wrapper-exp_faq">
                                        <p class="pc-repeater-placeholder">Container Repeater pour FAQ - À implémenter via JavaScript</p>
                                    </div>
                                    <small class="pc-field-help">Ajoutez les questions fréquemment posées pour cette expérience.</small>
                                </div>
                            </div>
                        </div>

                        <!-- Onglet 8 : Tarifs -->
                        <div class="pc-tab-content" id="tab-exp-rates" style="display: none;">
                            <div class="pc-form-grid">
                                <!-- Section Types de tarifs (Repeater ACF) -->
                                <div class="pc-form-group pc-form-group--full">
                                    <label>Types de tarifs</label>
                                    <div id="wrapper-exp_types_de_tarifs">
                                        <!-- Container Repeater dynamique généré par JavaScript -->
                                    </div>
                                    <small class="pc-field-help">Définissez un ou plusieurs types de tarifs (ex: un tarif pour la journée, un autre pour la demi-journée).</small>
                                </div>

                                <!-- Champ Taux de TVA -->
                                <div class="pc-form-group">
                                    <label for="exp_taux_tva">Taux de TVA applicable (%)</label>
                                    <input type="number" id="exp_taux_tva" class="pc-input" min="0" max="100" step="0.01" placeholder="20">
                                    <small class="pc-field-help">Saisissez le pourcentage de TVA (ex: 20 pour 20%, 8.5 pour 8.5%). Laissez à 0 si non assujetti.</small>
                                </div>

                                <!-- Rate Manager (Calendrier) -->
                                <div class="pc-form-group pc-form-group--full" style="margin-top: 2rem;">
                                    <h3 style="font-size: 1.2rem; font-weight: 700; color: #1e293b; margin: 0 0 1.5rem 0; display: flex; align-items: center; gap: 0.8rem; padding-bottom: 1rem; border-bottom: 2px solid rgba(148, 163, 184, 0.3);">
                                        📅 Calendrier des Tarifs & Disponibilités
                                    </h3>
                                    <div id="pc-experience-rates-calendar">
                                        <!-- Calendrier Rate Manager injecté ici -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Onglet 9 : Règles Channel Manager -->
                        <div class="pc-tab-content" id="tab-rules" style="display: none;">
                            <div class="pc-form-grid">
                                <div class="pc-form-group">
                                    <label for="exp_rules_taux_tva">Taux de TVA applicable (%)</label>
                                    <input type="number" id="exp_rules_taux_tva" class="pc-input" min="0" max="100" step="0.01" placeholder="20">
                                    <small class="pc-field-help">Saisissez le pourcentage de TVA (ex: 20 pour 20%, 8.5 pour 8.5%). Laissez à 0 si non assujetti.</small>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label>Règles de paiement</label>
                                </div>

                                <div class="pc-form-group">
                                    <label for="exp_pay_mode">Mode de paiement</label>
                                    <select id="exp_pay_mode" class="pc-select">
                                        <option value="acompte_plus_solde">Acompte plus solde</option>
                                        <option value="total_a_la_reservation">Total à la réservation</option>
                                        <option value="sur_place">Sur place</option>
                                        <option value="sur_devis">Sur devis</option>
                                    </select>
                                </div>

                                <div class="pc-form-group">
                                    <label for="exp_deposit_type">Acompte</label>
                                    <select id="exp_deposit_type" class="pc-select">
                                        <option value="pourcentage">Pourcentage</option>
                                        <option value="montant_fixe">Montant fixe</option>
                                    </select>
                                </div>

                                <div class="pc-form-group">
                                    <label for="exp_deposit_value">Sommes ou %</label>
                                    <input type="number" id="exp_deposit_value" class="pc-input" placeholder="30">
                                    <small class="pc-field-help">valeur numérique (ex : 30 ou 500)</small>
                                </div>

                                <div class="pc-form-group">
                                    <label for="exp_balance_delay_days">Solde</label>
                                    <input type="number" id="exp_balance_delay_days" class="pc-input" placeholder="30">
                                    <small class="pc-field-help">ex : 30 (= X jours avant arrivée / expérience)</small>
                                </div>

                                <div class="pc-form-group">
                                    <label for="exp_caution_amount">Montant de la caution</label>
                                    <input type="number" id="exp_caution_amount" class="pc-input" min="0" step="1" placeholder="0">
                                </div>

                                <div class="pc-form-group">
                                    <label for="exp_caution_mode">Méthode de caution</label>
                                    <select id="exp_caution_mode" class="pc-select">
                                        <option value="aucune">Aucune caution</option>
                                        <option value="empreinte">Empreinte bancaire</option>
                                        <option value="encaissement">Caution encaisser</option>
                                    </select>
                                    <small class="pc-field-help">Aucune caution = Le propriétaire s'en occupe</small>
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
                <div class="pc-experience-modal-actions">
                    <button type="button" class="pc-btn pc-btn-danger hidden" id="pc-experience-delete-btn">
                        <span class="pc-exp-btn-text">🗑️ Supprimer</span>
                        <span class="pc-exp-btn-spinner" style="display: none;">
                            <div class="pc-spinner-sm"></div>
                        </span>
                    </button>

                    <div class="pc-modal-actions-right">
                        <button type="button" class="pc-btn pc-exp-btn-secondary" id="pc-experience-cancel-btn">
                            <span class="pc-exp-btn-text">Annuler</span>
                        </button>
                        <button type="button" class="pc-btn pc-btn-primary" id="pc-experience-save-btn">
                            <span class="pc-exp-btn-text">💾 Enregistrer l'expérience</span>
                            <span class="pc-exp-btn-spinner" style="display: none;">
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
 * Enqueue les assets nécessaires pour le Experience Manager
 */
function pc_experience_enqueue_assets()
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

    // CSS pour le Experience Manager  
    wp_enqueue_style(
        'pc-experience-dashboard-css',
        PC_RES_CORE_URL . 'assets/css/dashboard-experience.css',
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

    // JavaScript pour le Experience Manager
    wp_enqueue_script(
        'pc-experience-dashboard-js',
        PC_RES_CORE_URL . 'assets/js/dashboard-experience.js',
        ['jquery', 'pc-rates-js'], // Dépendance ajoutée
        PC_RES_CORE_VERSION,
        true
    );

    // Variables JS critiques pour AJAX
    wp_localize_script('pc-experience-dashboard-js', 'pc_experience_vars', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pc_resa_manual_create'),
        'current_user_can_manage' => current_user_can('manage_options'),
        'debug' => WP_DEBUG,
    ]);

    // Compatibilité avec l'ancien nom de variable
    wp_localize_script('pc-experience-dashboard-js', 'pcReservationVars', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pc_resa_manual_create'),
        'current_user_can_manage' => current_user_can('manage_options'),
    ]);
}

/**
 * Hook pour charger les assets dans l'App Shell
 */
add_action('pc_resa_app_enqueue_assets', 'pc_experience_conditional_assets');
function pc_experience_conditional_assets()
{
    // Charger les assets seulement si on est sur la page Experience ou si on utilise le shortcode
    global $post;

    if (
        // Si on est dans l'App Shell (détecté par query_var)
        get_query_var('pc_app_dashboard') ||
        // Ou si la page contient le shortcode
        (is_object($post) && has_shortcode($post->post_content, 'pc_experience_dashboard'))
    ) {
        pc_experience_enqueue_assets();
    }
}

// Enregistrement du shortcode
add_shortcode('pc_experience_dashboard', 'pc_shortcode_experience_dashboard');
