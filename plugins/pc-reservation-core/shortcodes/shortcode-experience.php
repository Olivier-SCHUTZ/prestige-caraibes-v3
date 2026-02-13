<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PC Experience Dashboard Shortcode
 * Interface de gestion des expériences pour l'App Shell
 * 
 * @since 0.2.0
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
                <button type="button" class="pc-btn pc-btn-primary" id="pc-btn-add-experience">
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

                <select id="pc-experience-availability-filter" class="pc-select pc-filter-select">
                    <option value="">Toutes disponibilités</option>
                    <option value="InStock">Réservable actuellement</option>
                    <option value="SoldOut">Complet / Sold out</option>
                    <option value="PreOrder">Réservable prochainement</option>
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
                        <th class="pc-col-name">Titre de l'expérience</th>
                        <th class="pc-col-price">Prix</th>
                        <th class="pc-col-duration">Durée</th>
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
                <button class="pc-modal-close" id="pc-experience-modal-close">×</button>
            </div>

            <div class="pc-modal-content">
                <div class="pc-experience-loading" id="pc-experience-modal-loading">
                    <div class="pc-spinner"></div>
                    <span>Chargement des détails...</span>
                </div>

                <div class="pc-experience-details" id="pc-experience-modal-details" style="display: none;">
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
                            Tarifs
                        </button>
                        <button class="pc-tab-btn" data-tab="media">
                            <span class="pc-tab-icon">🖼️</span>
                            Médias
                        </button>
                        <button class="pc-tab-btn" data-tab="faq">
                            <span class="pc-tab-icon">❓</span>
                            FAQ
                        </button>
                        <button class="pc-tab-btn" data-tab="details-services">
                            <span class="pc-tab-icon">🔧</span>
                            Services
                        </button>
                        <button class="pc-tab-btn" data-tab="details-sorties">
                            <span class="pc-tab-icon">🚀</span>
                            Détails sorties
                        </button>
                        <button class="pc-tab-btn" data-tab="inclusions-prerequis">
                            <span class="pc-tab-icon">📋</span>
                            Inclusions
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
                                    <label for="experience-title">Titre de l'expérience *</label>
                                    <input type="text" id="experience-title" class="pc-input" placeholder="Nom de l'expérience" required>
                                </div>

                                <div class="pc-form-group">
                                    <label for="experience-capacity">Capacité (personnes)</label>
                                    <input type="number" id="experience-capacity" class="pc-input" min="1" max="100">
                                </div>

                                <div class="pc-form-group">
                                    <label for="experience-duration">Durée (heures)</label>
                                    <input type="number" id="experience-duration" class="pc-input" min="0.5" step="0.5">
                                </div>

                                <div class="pc-form-group">
                                    <label for="experience-availability">Disponibilité</label>
                                    <select id="experience-availability" class="pc-select">
                                        <option value="InStock">Réservable actuellement</option>
                                        <option value="SoldOut">Complet / Sold out</option>
                                        <option value="PreOrder">Réservable prochainement</option>
                                    </select>
                                </div>

                                <div class="pc-form-group">
                                    <label for="experience-status">Statut de publication</label>
                                    <select id="experience-status" class="pc-select">
                                        <option value="publish">Publié</option>
                                        <option value="pending">En attente</option>
                                        <option value="draft">Brouillon</option>
                                        <option value="private">Privé</option>
                                    </select>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="experience-description">Description</label>
                                    <textarea id="experience-description" class="pc-textarea" rows="4" placeholder="Description de l'expérience..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Onglet Localisation -->
                        <div class="pc-tab-content" id="tab-location">
                            <div class="pc-form-grid">
                                <div class="pc-form-group pc-form-group--full">
                                    <label for="experience-address">Adresse du point de rendez-vous</label>
                                    <textarea id="experience-address" class="pc-textarea" rows="2" placeholder="Adresse complète du lieu de rendez-vous..."></textarea>
                                </div>

                                <div class="pc-form-group">
                                    <label for="experience-city">Ville</label>
                                    <input type="text" id="experience-city" class="pc-input" placeholder="Ville">
                                </div>

                                <div class="pc-form-group">
                                    <label for="experience-postal-code">Code postal</label>
                                    <input type="text" id="experience-postal-code" class="pc-input" placeholder="Code postal">
                                </div>

                                <div class="pc-form-group">
                                    <label for="experience-latitude">Latitude</label>
                                    <input type="text" id="experience-latitude" class="pc-input" placeholder="16.2650">
                                </div>

                                <div class="pc-form-group">
                                    <label for="experience-longitude">Longitude</label>
                                    <input type="text" id="experience-longitude" class="pc-input" placeholder="-61.5510">
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="experience-meeting-point">Instructions point de rendez-vous</label>
                                    <textarea id="experience-meeting-point" class="pc-textarea" rows="3" placeholder="Instructions détaillées pour trouver le point de rendez-vous..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Onglet Détails sorties -->
                        <div class="pc-tab-content" id="tab-details-sorties" style="display: none;">
                            <div class="pc-form-grid">
                                <div class="pc-form-group">
                                    <label for="experience-accessibility">Accessibilité</label>
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

                                <div class="pc-form-group">
                                    <label for="experience-periode">Période</label>
                                    <div class="pc-checkbox-group">
                                        <label><input type="checkbox" name="exp_periode[]" value="année" class="pc-checkbox-field"> Toute l'année</label>
                                        <label><input type="checkbox" name="exp_periode[]" value="saison" class="pc-checkbox-field"> En saison</label>
                                        <label><input type="checkbox" name="exp_periode[]" value="réservation" class="pc-checkbox-field"> Sur réservation</label>
                                    </div>
                                </div>

                                <div class="pc-form-group">
                                    <label for="experience-jour">Jours de départ</label>
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
                                    <label for="experience-fermeture-periodes">Périodes de fermeture</label>
                                    <div class="pc-repeater-field">
                                        <div class="pc-repeater-items" id="fermeture-periodes-list">
                                            <!-- Items générés dynamiquement -->
                                        </div>
                                        <button type="button" class="pc-btn pc-btn-secondary pc-btn-add-repeater" data-repeater="fermeture-periodes">
                                            ➕ Ajouter une période
                                        </button>
                                    </div>
                                    <template id="fermeture-periode-template">
                                        <div class="pc-repeater-item">
                                            <div class="pc-form-grid pc-form-grid--inline">
                                                <div class="pc-form-group">
                                                    <label>Début de fermeture</label>
                                                    <input type="date" class="pc-input" name="debut_fermeture">
                                                </div>
                                                <div class="pc-form-group">
                                                    <label>Fin de fermeture</label>
                                                    <input type="date" class="pc-input" name="fin_fermeture">
                                                </div>
                                                <div class="pc-form-group">
                                                    <button type="button" class="pc-btn pc-btn-danger pc-btn-remove-repeater">❌</button>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="experience-lieux-horaires">Lieux et horaires de départ</label>
                                    <div class="pc-repeater-field">
                                        <div class="pc-repeater-items" id="lieux-horaires-list">
                                            <!-- Items générés dynamiquement -->
                                        </div>
                                        <button type="button" class="pc-btn pc-btn-secondary pc-btn-add-repeater" data-repeater="lieux-horaires">
                                            ➕ Ajouter un lieu
                                        </button>
                                    </div>
                                    <template id="lieu-horaire-template">
                                        <div class="pc-repeater-item">
                                            <div class="pc-form-grid">
                                                <div class="pc-form-group pc-form-group--full">
                                                    <label>Lieu de départ</label>
                                                    <input type="text" class="pc-input" name="lieu_depart" placeholder="Ex: Marina de Saint-François">
                                                </div>
                                                <div class="pc-form-group">
                                                    <label>Latitude</label>
                                                    <input type="number" class="pc-input" name="lat_exp" step="any">
                                                </div>
                                                <div class="pc-form-group">
                                                    <label>Longitude</label>
                                                    <input type="number" class="pc-input" name="longitude" step="any">
                                                </div>
                                                <div class="pc-form-group">
                                                    <label>Heure de départ</label>
                                                    <input type="time" class="pc-input" name="heure_depart">
                                                </div>
                                                <div class="pc-form-group">
                                                    <label>Heure de retour</label>
                                                    <input type="time" class="pc-input" name="heure_retour">
                                                </div>
                                                <div class="pc-form-group">
                                                    <button type="button" class="pc-btn pc-btn-danger pc-btn-remove-repeater">❌ Supprimer</button>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Onglet Inclusions & Pré-requis -->
                        <div class="pc-tab-content" id="tab-inclusions-prerequis" style="display: none;">
                            <div class="pc-form-grid">
                                <div class="pc-form-group pc-form-group--full">
                                    <label for="experience-prix-comprend">Le prix comprend</label>
                                    <textarea id="experience-prix-comprend" class="pc-textarea" rows="6" placeholder="Utilisez des listes à puces pour plus de clarté (ex: Repas du midi, Boissons, Matériel de snorkeling...)"></textarea>
                                    <small class="pc-field-help">Utilisez des listes à puces pour plus de clarté</small>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="experience-prix-ne-comprend-pas">Le prix ne comprend pas</label>
                                    <textarea id="experience-prix-ne-comprend-pas" class="pc-textarea" rows="6" placeholder="Listez ce qui n'est pas inclus (ex: Pourboires, Dépenses personnelles...)"></textarea>
                                    <small class="pc-field-help">Listez ce qui n'est pas inclus</small>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="experience-a-prevoir">À prévoir</label>
                                    <div class="pc-checkbox-group" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                                        <label><input type="checkbox" name="exp_a_prevoir[]" value="creme_solaire" class="pc-checkbox-field"> Crème solaire minérale</label>
                                        <label><input type="checkbox" name="exp_a_prevoir[]" value="serviette" class="pc-checkbox-field"> Serviette de bain</label>
                                        <label><input type="checkbox" name="exp_a_prevoir[]" value="maillot_de_bain" class="pc-checkbox-field"> Maillot de bain</label>
                                        <label><input type="checkbox" name="exp_a_prevoir[]" value="eau_collations" class="pc-checkbox-field"> Une bouteille d'eau et collations</label>
                                        <label><input type="checkbox" name="exp_a_prevoir[]" value="appareil_photo" class="pc-checkbox-field"> Appareil photo</label>
                                        <label><input type="checkbox" name="exp_a_prevoir[]" value="chaussures_marche" class="pc-checkbox-field"> Chaussures de marche</label>
                                        <label><input type="checkbox" name="exp_a_prevoir[]" value="Chaussons_eau" class="pc-checkbox-field"> Chaussons d'eau</label>
                                        <label><input type="checkbox" name="exp_a_prevoir[]" value="Imperméable" class="pc-checkbox-field"> Imperméable / coupe vent</label>
                                        <label><input type="checkbox" name="exp_a_prevoir[]" value="Vêtements" class="pc-checkbox-field"> Vêtements de rechange</label>
                                        <label><input type="checkbox" name="exp_a_prevoir[]" value="Casquette" class="pc-checkbox-field"> Casquette / Bob</label>
                                        <label><input type="checkbox" name="exp_a_prevoir[]" value="Snorkeling" class="pc-checkbox-field"> Équipement de snorkeling</label>
                                        <label><input type="checkbox" name="exp_a_prevoir[]" value="Teeshirt" class="pc-checkbox-field"> Tee-shirts anti UV</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Onglet Détails services -->
                        <div class="pc-tab-content" id="tab-details-services" style="display: none;">
                            <div class="pc-form-grid">
                                <div class="pc-form-group">
                                    <label for="experience-delai-reservation">Délai de réservation</label>
                                    <div class="pc-checkbox-group">
                                        <label><input type="checkbox" name="exp_delai_de_reservation[]" value="24h" class="pc-checkbox-field"> 24h à l'avance</label>
                                        <label><input type="checkbox" name="exp_delai_de_reservation[]" value="48h" class="pc-checkbox-field"> 48h à l'avance</label>
                                        <label><input type="checkbox" name="exp_delai_de_reservation[]" value="72h" class="pc-checkbox-field"> 72h à l'avance</label>
                                        <label><input type="checkbox" name="exp_delai_de_reservation[]" value="1 semaine" class="pc-checkbox-field"> 1 semaine à l'avance</label>
                                        <label><input type="checkbox" name="exp_delai_de_reservation[]" value="Avant départ" class="pc-checkbox-field"> Avant le départ</label>
                                    </div>
                                </div>

                                <div class="pc-form-group">
                                    <label for="experience-zone-intervention">Zone intervention</label>
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
                                    <label for="experience-type-prestation">Type de prestation</label>
                                    <input type="text" id="experience-type-prestation" class="pc-input" placeholder="Ex: Créole, Française pour un chef à domicile">
                                    <small class="pc-field-help">Pour un chef à domicile (ex: "Créole, Française"), Pour un massage (ex: "Massage relaxant", "Soin du visage"), etc.</small>
                                </div>

                                <div class="pc-form-group">
                                    <label for="experience-heure-limite-commande">Heure limite de commande</label>
                                    <input type="number" id="experience-heure-limite-commande" class="pc-input" min="0" max="24" step="1" value="18">
                                    <small class="pc-field-help">Idéal pour les livraisons (ex: "Commande avant 18h")</small>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="experience-service-comprend">Le service comprend</label>
                                    <textarea id="experience-service-comprend" class="pc-textarea" rows="8" placeholder="Détail du service - Copiez/collez votre texte formaté et sélectionnez 'outils d'écriture' - 'Créer des points clés'"></textarea>
                                    <small class="pc-field-help">Détail du service. Copier/Coller fichier format Texte, sélectionnez 'outils d'écriture' - 'Créer des points clés'</small>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="experience-service-a-prevoir">À prévoir pour ce service</label>
                                    <textarea id="experience-service-a-prevoir" class="pc-textarea" rows="6" placeholder="Indiquez ce que le client doit prévoir pour ce service"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Onglet FAQ -->
                        <div class="pc-tab-content" id="tab-faq" style="display: none;">
                            <div class="pc-form-grid">
                                <div class="pc-form-group pc-form-group--full">
                                    <label for="experience-faq">Questions / Réponses</label>
                                    <div class="pc-repeater-field">
                                        <div class="pc-repeater-items" id="faq-list">
                                            <!-- Items générés dynamiquement -->
                                        </div>
                                        <button type="button" class="pc-btn pc-btn-secondary pc-btn-add-repeater" data-repeater="faq">
                                            ➕ Ajouter une question
                                        </button>
                                    </div>
                                    <template id="faq-template">
                                        <div class="pc-repeater-item">
                                            <div class="pc-form-grid">
                                                <div class="pc-form-group pc-form-group--full">
                                                    <label>Question</label>
                                                    <input type="text" class="pc-input" name="exp_question" placeholder="Posez votre question..." required>
                                                </div>
                                                <div class="pc-form-group pc-form-group--full">
                                                    <label>Réponse</label>
                                                    <textarea class="pc-textarea" name="exp_reponse" rows="4" placeholder="Réponse détaillée..." required></textarea>
                                                </div>
                                                <div class="pc-form-group">
                                                    <button type="button" class="pc-btn pc-btn-danger pc-btn-remove-repeater">❌ Supprimer</button>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                    <small class="pc-field-help">Ajoutez les questions fréquemment posées pour cette expérience.</small>
                                </div>
                            </div>
                        </div>

                        <!-- Onglet Tarifs -->
                        <div class="pc-tab-content" id="tab-pricing">
                            <div class="pc-form-grid">
                                <!-- Taux de TVA -->
                                <div class="pc-form-group">
                                    <label for="experience-taux-tva">Taux de TVA applicable (%)</label>
                                    <input type="number" id="experience-taux-tva" class="pc-input" min="0" max="100" step="0.01" placeholder="0">
                                    <small class="pc-field-help">Saisissez le pourcentage de TVA (ex: 20 pour 20%, 8.5 pour 8.5%). Laissez à 0 si non assujetti.</small>
                                </div>

                                <!-- Types de tarifs - Repeater principal -->
                                <div class="pc-form-group pc-form-group--full">
                                    <label>💰 Types de tarifs</label>
                                    <div class="pc-repeater-field" id="exp-types-de-tarifs">
                                        <div class="pc-repeater-header">
                                            <span class="pc-repeater-title">Définissez un ou plusieurs types de tarifs (ex: un tarif pour la journée, un autre pour la demi-journée)</span>
                                            <button type="button" class="pc-btn pc-btn-secondary pc-btn-add-repeater" data-repeater="type-tarif">
                                                ➕ Ajouter un type de tarif
                                            </button>
                                        </div>

                                        <div class="pc-repeater-items" id="types-de-tarifs-list">
                                            <!-- Items générés dynamiquement -->
                                        </div>

                                        <div class="pc-repeater-empty">
                                            <div class="pc-empty-icon">💰</div>
                                            <p>Aucun type de tarif défini. Cliquez sur "Ajouter un type de tarif" pour commencer.</p>
                                        </div>
                                    </div>

                                    <!-- Template pour un type de tarif -->
                                    <template id="type-tarif-template">
                                        <div class="pc-repeater-item pc-tarif-type-item">
                                            <div class="pc-repeater-item-header">
                                                <h4 class="pc-repeater-item-title">🎯 Type de tarif</h4>
                                                <div class="pc-repeater-item-actions">
                                                    <button type="button" class="pc-btn pc-btn-sm pc-btn-secondary pc-btn-toggle-content">
                                                        <span class="toggle-expand">👁️ Voir</span>
                                                        <span class="toggle-collapse" style="display: none;">👁️ Masquer</span>
                                                    </button>
                                                    <button type="button" class="pc-btn pc-btn-sm pc-btn-danger pc-btn-remove-repeater">❌</button>
                                                </div>
                                            </div>

                                            <div class="pc-repeater-item-content">
                                                <div class="pc-form-grid">
                                                    <!-- Sélecteur de type -->
                                                    <div class="pc-form-group">
                                                        <label>Type *</label>
                                                        <select class="pc-select exp-type-selector" name="exp_type" required>
                                                            <option value="">-- Choisir le format du tarif --</option>
                                                            <option value="journee">Journée</option>
                                                            <option value="demi-journee">Demi-journée</option>
                                                            <option value="unique">Unique / Forfaitaire</option>
                                                            <option value="sur-devis">Sur devis</option>
                                                            <option value="custom">Autre (personnalisé)</option>
                                                        </select>
                                                    </div>

                                                    <!-- Libellé personnalisé (si custom) -->
                                                    <div class="pc-form-group exp-type-custom-field" style="display: none;">
                                                        <label>Libellé personnalisé</label>
                                                        <input type="text" class="pc-input" name="exp_type_custom" maxlength="60" placeholder="Nom personnalisé pour ce type de tarif">
                                                    </div>
                                                </div>

                                                <!-- Sub-repeater 1 : Options Tarifaires -->
                                                <div class="pc-sub-repeater-section">
                                                    <h5 class="pc-sub-repeater-title">🔧 Options Tarifaires</h5>
                                                    <div class="pc-sub-repeater-field">
                                                        <div class="pc-repeater-items options-tarifaires-list">
                                                            <!-- Items d'options générés dynamiquement -->
                                                        </div>
                                                        <button type="button" class="pc-btn pc-btn-sm pc-btn-secondary pc-btn-add-sub-repeater" data-sub-repeater="option-tarifaire">
                                                            ➕ Ajouter une option
                                                        </button>
                                                        <small class="pc-field-help">Ajoutez des options payantes (ex: privatisation, assurance annulation, location de matériel spécifique...).</small>
                                                    </div>
                                                </div>

                                                <!-- Template pour option tarifaire -->
                                                <template class="option-tarifaire-template">
                                                    <div class="pc-sub-repeater-item">
                                                        <div class="pc-form-grid pc-form-grid--inline">
                                                            <div class="pc-form-group" style="flex: 2;">
                                                                <label>Description de l'option *</label>
                                                                <input type="text" class="pc-input" name="exp_description_option" placeholder="Ex: Skipper professionnel" required>
                                                            </div>
                                                            <div class="pc-form-group">
                                                                <label>Tarif (€) *</label>
                                                                <input type="number" class="pc-input" name="exp_tarif_option" min="0" step="0.01" required>
                                                            </div>
                                                            <div class="pc-form-group">
                                                                <label>
                                                                    <input type="checkbox" name="option_enable_qty" value="1"> Quantité ?
                                                                </label>
                                                                <small class="pc-field-help">Cochez pour ajouter un champ quantité</small>
                                                            </div>
                                                            <div class="pc-form-group">
                                                                <button type="button" class="pc-btn pc-btn-sm pc-btn-danger pc-btn-remove-sub-repeater">❌</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>

                                                <!-- Sub-repeater 2 : Frais fixes -->
                                                <div class="pc-sub-repeater-section">
                                                    <h5 class="pc-sub-repeater-title">💳 Frais fixes</h5>
                                                    <div class="pc-sub-repeater-field">
                                                        <div class="pc-repeater-items frais-fixes-list">
                                                            <!-- Items de frais fixes générés dynamiquement -->
                                                        </div>
                                                        <button type="button" class="pc-btn pc-btn-sm pc-btn-secondary pc-btn-add-sub-repeater" data-sub-repeater="frais-fixe">
                                                            ➕ Ajouter un frais fixe
                                                        </button>
                                                        <small class="pc-field-help">Ajoutez des frais fixes (ex: privatisation, assurance annulation, déplacement...).</small>
                                                    </div>
                                                </div>

                                                <!-- Template pour frais fixe -->
                                                <template class="frais-fixe-template">
                                                    <div class="pc-sub-repeater-item">
                                                        <div class="pc-form-grid pc-form-grid--inline">
                                                            <div class="pc-form-group" style="flex: 2;">
                                                                <label>Description du frais fixe *</label>
                                                                <input type="text" class="pc-input" name="exp_description_frais_fixe" placeholder="ex : Déplacement" required>
                                                            </div>
                                                            <div class="pc-form-group">
                                                                <label>Tarif (€) *</label>
                                                                <input type="number" class="pc-input" name="exp_tarif_frais_fixe" min="0" step="0.01" required>
                                                            </div>
                                                            <div class="pc-form-group">
                                                                <button type="button" class="pc-btn pc-btn-sm pc-btn-danger pc-btn-remove-sub-repeater">❌</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>

                                                <!-- Sub-repeater 3 : Lignes de tarifs -->
                                                <div class="pc-sub-repeater-section">
                                                    <h5 class="pc-sub-repeater-title">📊 Lignes de tarifs</h5>
                                                    <div class="pc-sub-repeater-field">
                                                        <div class="pc-repeater-items tarifs-lignes-list">
                                                            <!-- Items de lignes de tarifs générés dynamiquement -->
                                                        </div>
                                                        <button type="button" class="pc-btn pc-btn-sm pc-btn-secondary pc-btn-add-sub-repeater" data-sub-repeater="tarif-ligne">
                                                            ➕ Ajouter une ligne
                                                        </button>
                                                        <small class="pc-field-help">Définissez les lignes de tarifs pour ce type (ordre = affichage).</small>
                                                    </div>
                                                </div>

                                                <!-- Template pour ligne de tarif -->
                                                <template class="tarif-ligne-template">
                                                    <div class="pc-sub-repeater-item">
                                                        <div class="pc-form-grid">
                                                            <div class="pc-form-group">
                                                                <label>Type de ligne</label>
                                                                <select class="pc-select tarif-type-ligne" name="type_ligne">
                                                                    <option value="adulte">Adulte</option>
                                                                    <option value="enfant">Enfant</option>
                                                                    <option value="bebe">Bébé</option>
                                                                    <option value="personnalise" selected>Personnalisé / Forfait</option>
                                                                </select>
                                                            </div>

                                                            <div class="pc-form-group">
                                                                <label>Tarif (€) *</label>
                                                                <input type="number" class="pc-input" name="tarif_valeur" min="0" step="0.01" placeholder="0 = Gratuit" required>
                                                                <small class="pc-field-help">Montant en € (0 = Gratuit)</small>
                                                            </div>

                                                            <!-- Champ Quantité pour personnalisé -->
                                                            <div class="pc-form-group tarif-qty-field" style="display: none;">
                                                                <label>
                                                                    <input type="checkbox" name="tarif_enable_qty" value="1"> Champ Quantité ?
                                                                </label>
                                                                <small class="pc-field-help">Cochez pour permettre de choisir une quantité</small>
                                                            </div>

                                                            <!-- Précision âge enfant -->
                                                            <div class="pc-form-group tarif-enfant-field" style="display: none;">
                                                                <label>Précision Âge Enfant</label>
                                                                <input type="text" class="pc-input" name="precision_age_enfant" placeholder="Ex: 3 à 12 ans">
                                                            </div>

                                                            <!-- Précision âge bébé -->
                                                            <div class="pc-form-group tarif-bebe-field" style="display: none;">
                                                                <label>Précision Âge Bébé</label>
                                                                <input type="text" class="pc-input" name="precision_age_bebe" placeholder="Ex: Moins de 3 ans">
                                                            </div>

                                                            <!-- Nom personnalisé -->
                                                            <div class="pc-form-group tarif-perso-field" style="display: none;">
                                                                <label>Nom du tarif (perso)</label>
                                                                <input type="text" class="pc-input" name="tarif_nom_perso" maxlength="120" placeholder="Ex : Privatisation, Photographe, etc.">
                                                            </div>

                                                            <!-- Observation -->
                                                            <div class="pc-form-group pc-form-group--full">
                                                                <label>Observation</label>
                                                                <textarea class="pc-textarea" name="tarif_observation" rows="2" placeholder="Note affichée sous la ligne (ex : jusqu'à 12 pers)"></textarea>
                                                                <small class="pc-field-help">Note affichée sous la ligne (ex : jusqu'à 12 pers)</small>
                                                            </div>

                                                            <!-- Actions -->
                                                            <div class="pc-form-group">
                                                                <button type="button" class="pc-btn pc-btn-sm pc-btn-danger pc-btn-remove-sub-repeater">❌ Supprimer</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <!-- Séparateur visuel pour les Règles de Paiement -->
                                <div class="pc-form-group pc-form-group--full" style="margin-top: 2rem;">
                                    <h3 style="font-size: 1.2rem; font-weight: 700; color: #1e293b; margin: 0 0 1.5rem 0; display: flex; align-items: center; gap: 0.8rem; padding-bottom: 1rem; border-bottom: 2px solid rgba(148, 163, 184, 0.3);">
                                        💳 Règles de Paiement
                                    </h3>
                                </div>

                                <!-- Champs Règles de Paiement -->
                                <div class="pc-form-group">
                                    <label for="exp-pc-pay-mode">Mode de paiement</label>
                                    <select id="exp-pc-pay-mode" class="pc-select" name="pc_pay_mode">
                                        <option value="acompte_plus_solde">Acompte plus solde</option>
                                        <option value="total_a_la_reservation">Total à la réservation</option>
                                        <option value="sur_place">Sur place</option>
                                        <option value="sur_devis">Sur devis</option>
                                    </select>
                                </div>

                                <div class="pc-form-group">
                                    <label for="exp-pc-deposit-type">Acompte</label>
                                    <select id="exp-pc-deposit-type" class="pc-select" name="pc_deposit_type">
                                        <option value="pourcentage">Pourcentage</option>
                                        <option value="montant_fixe">Montant fixe</option>
                                    </select>
                                </div>

                                <div class="pc-form-group">
                                    <label for="exp-pc-deposit-value">Sommes ou %</label>
                                    <input type="number" id="exp-pc-deposit-value" class="pc-input" name="pc_deposit_value" placeholder="30">
                                    <small class="pc-field-help">valeur numérique (ex : 30 ou 500)</small>
                                </div>

                                <div class="pc-form-group">
                                    <label for="exp-pc-balance-delay-days">Solde / Jours avant expérience</label>
                                    <input type="number" id="exp-pc-balance-delay-days" class="pc-input" name="pc_balance_delay_days" placeholder="30">
                                    <small class="pc-field-help">ex : 30 (= X jours avant arrivée / expérience)</small>
                                </div>

                                <div class="pc-form-group">
                                    <label for="exp-pc-caution-amount">Montant de la caution</label>
                                    <input type="number" id="exp-pc-caution-amount" class="pc-input" name="pc_caution_amount" min="0" step="1" placeholder="500">
                                </div>

                                <div class="pc-form-group">
                                    <label for="exp-pc-caution-mode">Méthode de caution</label>
                                    <select id="exp-pc-caution-mode" class="pc-select" name="pc_caution_mode">
                                        <option value="aucune">Aucune caution</option>
                                        <option value="empreinte">Empreinte bancaire</option>
                                        <option value="encaissement">Caution encaisser</option>
                                    </select>
                                    <small class="pc-field-help">Aucune caution = Le propriétaire s'en occupe</small>
                                </div>
                            </div>
                        </div>

                        <!-- Onglet Médias -->
                        <div class="pc-tab-content" id="tab-media">
                            <div class="pc-form-grid">
                                <div class="pc-form-group">
                                    <label for="experience-featured-image">Image principale</label>
                                    <div class="pc-image-uploader" data-field="featured-image">
                                        <div class="pc-image-preview" id="preview-featured-image">
                                            <div class="pc-image-placeholder">
                                                📷 Aucune image sélectionnée
                                            </div>
                                        </div>
                                        <input type="hidden" id="experience-featured-image" name="featured_image_url" value="">
                                        <div class="pc-image-actions">
                                            <button type="button" class="pc-btn pc-btn-select-image" data-target="featured-image">
                                                <span>🖼️</span> Choisir une image
                                            </button>
                                            <button type="button" class="pc-btn pc-btn-remove-image" data-target="featured-image" style="display: none;">
                                                <span>❌</span> Supprimer
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="experience-gallery-urls">Galerie (URLs) — 1 par ligne</label>
                                    <textarea id="experience-gallery-urls" class="pc-textarea" rows="8" placeholder="https://example.com/image1.jpg&#10;https://example.com/image2.jpg&#10;https://example.com/image3.jpg"></textarea>
                                    <small class="pc-field-help">1 URL d'image par ligne.</small>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="experience-video-urls">Vidéos YouTube — 1 URL par ligne</label>
                                    <textarea id="experience-video-urls" class="pc-textarea" rows="3" placeholder="https://youtube.com/watch?v=..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Onglet Configuration -->
                        <div class="pc-tab-content" id="tab-advanced" style="display: none;">
                            <div class="pc-form-grid">
                                <div class="pc-form-group">
                                    <label for="experience-status-advanced">Statut de publication</label>
                                    <select id="experience-status-advanced" class="pc-select">
                                        <option value="publish">Publié</option>
                                        <option value="pending">En attente</option>
                                        <option value="draft">Brouillon</option>
                                        <option value="private">Privé</option>
                                    </select>
                                </div>

                                <div class="pc-form-group">
                                    <label for="experience-exclude-sitemap">Exclure du sitemap</label>
                                    <label class="pc-checkbox-container">
                                        <input type="checkbox" id="experience-exclude-sitemap" name="exp_exclude_sitemap" value="1" class="pc-checkbox-field pc-checkbox-boolean">
                                        <span class="pc-checkbox-label">Retire cette expérience du sitemap</span>
                                    </label>
                                </div>

                                <div class="pc-form-group">
                                    <label for="experience-http-410">Servir un 410 Gone</label>
                                    <label class="pc-checkbox-container">
                                        <input type="checkbox" id="experience-http-410" name="exp_http_410" value="1" class="pc-checkbox-field pc-checkbox-boolean">
                                        <span class="pc-checkbox-label">Cochez si l'expérience est définitivement supprimée</span>
                                    </label>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="experience-meta-titre">Titre SEO (override)</label>
                                    <input type="text" id="experience-meta-titre" class="pc-input" placeholder="Titre optimisé pour le référencement">
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="experience-meta-description">Description SEO (override)</label>
                                    <textarea id="experience-meta-description" class="pc-textarea" rows="3" placeholder="Description optimisée pour le référencement (max 155 caractères)"></textarea>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="experience-url-canonique">URL canonique (override)</label>
                                    <input type="url" id="experience-url-canonique" class="pc-input" placeholder="https://example.com/canonical-url">
                                </div>

                                <div class="pc-form-group">
                                    <label for="experience-meta-robots">Meta robots</label>
                                    <select id="experience-meta-robots" class="pc-select">
                                        <option value="index,follow">index,follow</option>
                                        <option value="noindex,follow">noindex,follow</option>
                                        <option value="noindex,nofollow">noindex,nofollow</option>
                                    </select>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="experience-content-seo">Contenu SEO (HTML)</label>
                                    <textarea id="experience-content-seo" class="pc-textarea" rows="4" placeholder="Contenu HTML pour le référencement..."></textarea>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="experience-h1-custom">H1 (optionnel)</label>
                                    <input type="text" id="experience-h1-custom" class="pc-input" placeholder="Titre H1 personnalisé">
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label for="experience-highlights-custom">Points forts personnalisés (1 par ligne)</label>
                                    <textarea id="experience-highlights-custom" class="pc-textarea" rows="4" placeholder="Ajoutez des points forts personnalisés..."></textarea>
                                </div>

                                <!-- Séparateur visuel pour Google/Schema.org -->
                                <div class="pc-form-group pc-form-group--full" style="margin-top: 2rem;">
                                    <h3 style="font-size: 1.2rem; font-weight: 700; color: #1e293b; margin: 0 0 1.5rem 0; display: flex; align-items: center; gap: 0.8rem; padding-bottom: 1rem; border-bottom: 2px solid rgba(148, 163, 184, 0.3);">
                                        🌐 Données Structurées Google
                                    </h3>
                                </div>

                                <div class="pc-form-group">
                                    <label for="experience-google-type">Type d'expérience (Google)</label>
                                    <select id="experience-google-type" class="pc-select">
                                        <option value="Event">Événement (Event)</option>
                                        <option value="Tour">Circuit / Tour</option>
                                        <option value="Activity">Activité</option>
                                        <option value="Service">Service</option>
                                    </select>
                                </div>

                                <div class="pc-form-group pc-form-group--full">
                                    <label>Catégories d'expérience</label>
                                    <div class="pc-checkbox-group">
                                        <label><input type="checkbox" name="google_exp_categories[]" value="adventure" class="pc-checkbox-field"> Aventure</label>
                                        <label><input type="checkbox" name="google_exp_categories[]" value="cultural" class="pc-checkbox-field"> Culturel</label>
                                        <label><input type="checkbox" name="google_exp_categories[]" value="culinary" class="pc-checkbox-field"> Culinaire</label>
                                        <label><input type="checkbox" name="google_exp_categories[]" value="nature" class="pc-checkbox-field"> Nature</label>
                                        <label><input type="checkbox" name="google_exp_categories[]" value="aquatic" class="pc-checkbox-field"> Aquatique</label>
                                        <label><input type="checkbox" name="google_exp_categories[]" value="relaxation" class="pc-checkbox-field"> Détente</label>
                                        <label><input type="checkbox" name="google_exp_categories[]" value="sports" class="pc-checkbox-field"> Sports</label>
                                        <label><input type="checkbox" name="google_exp_categories[]" value="wellness" class="pc-checkbox-field"> Bien-être</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pc-modal-footer">
                <div class="pc-modal-actions">
                    <button type="button" class="pc-btn pc-btn-danger" id="pc-experience-delete-btn">
                        <span class="pc-btn-text">Supprimer</span>
                        <span class="pc-btn-spinner" style="display: none;">
                            <div class="pc-spinner-sm"></div>
                        </span>
                    </button>
                    <div class="pc-modal-actions-right">
                        <button type="button" class="pc-btn pc-btn-secondary" id="pc-experience-cancel-btn">
                            Annuler
                        </button>
                        <button type="button" class="pc-btn pc-btn-primary" id="pc-experience-save-btn">
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
 * Enqueue les assets nécessaires pour l'Experience Manager
 */
function pc_experience_enqueue_assets()
{
    // Éviter les enqueues multiples
    static $assets_loaded = false;
    if ($assets_loaded) {
        return;
    }
    $assets_loaded = true;

    // Réutiliser le CSS du Housing Manager (compatible)
    wp_enqueue_style(
        'pc-experience-dashboard-css',
        PC_RES_CORE_URL . 'assets/css/dashboard-experience.css',
        [],
        PC_RES_CORE_VERSION
    );

    // JavaScript pour l'Experience Manager
    wp_enqueue_script(
        'pc-experience-dashboard-js',
        PC_RES_CORE_URL . 'assets/js/dashboard-experience.js',
        ['jquery'],
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
