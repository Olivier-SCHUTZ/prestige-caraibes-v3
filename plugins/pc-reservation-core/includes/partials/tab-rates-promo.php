<?php

/**
 * Partial : Onglet Tarifs & Promotions
 * Interface adaptée du plugin PC Rate Manager pour le Dashboard Housing
 */

if (!defined('ABSPATH')) exit;
?>

<div id="pc-rates-tab-content" class="pc-tab-content" style="display:none;">
    <div class="pc-rates-wrapper">
        <div class="pc-rates-sidebar">
            <h3><i class="fas fa-calendar-alt"></i> Gestion des Tarifs</h3>

            <div class="pc-rates-section">
                <h4>Mes Saisons</h4>
                <div id="pc-seasons-list" class="pc-seasons-container">
                    <p class="pc-rates-empty-state">Chargement...</p>
                </div>
            </div>

            <div class="pc-rates-section pc-rates-actions">
                <button type="button" class="pc-btn pc-btn-primary" id="btn-add-new-season">
                    <i class="fas fa-plus"></i> Nouvelle Saison
                </button>

                <button type="button" class="pc-btn pc-btn-secondary" id="btn-add-promo">
                    <i class="fas fa-tag"></i> Ajouter Promo
                </button>
            </div>

            <div class="pc-rates-info">
                <p><small><i class="fas fa-info-circle"></i> Cliquez sur une saison pour l'éditer. Glissez-la sur le calendrier pour l'appliquer.</small></p>
            </div>
        </div>

        <div class="pc-rates-calendar-container">
            <div id="pc-rates-calendar" class="pc-rates-calendar"></div>
        </div>
    </div>
</div>

<!-- Modal Rates & Promos -->
<div id="pc-rates-modal" class="pc-modal pc-rates-modal" style="display:none;">
    <div class="pc-modal-content">
        <div class="pc-modal-header">
            <h2 id="pc-rates-modal-title">Éditer</h2>
            <button class="pc-close-modal" id="btn-rates-close-cross" type="button">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="pc-modal-body">
            <form id="pc-rates-form" class="pc-form">
                <input type="hidden" id="pc-rates-entity-type" value="season">
                <input type="hidden" id="pc-rates-edit-row-id" value="">

                <div class="pc-form-group">
                    <label id="lbl-rates-name" class="pc-form-label">Nom *</label>
                    <input type="text" id="pc-rates-input-name" class="pc-form-input" placeholder="Ex: Été 2025" required>
                </div>

                <div id="pc-rates-group-season-fields">
                    <div class="pc-form-row">
                        <div class="pc-form-group">
                            <label class="pc-form-label">Tarif (€/nuit) *</label>
                            <input type="number" id="pc-rates-input-price" class="pc-form-input" step="0.01">
                        </div>
                        <div class="pc-form-group">
                            <label class="pc-form-label">Nuits minimum</label>
                            <input type="number" id="pc-rates-input-min-nights" class="pc-form-input" placeholder="Défaut">
                        </div>
                    </div>
                    <div class="pc-form-group">
                        <label class="pc-form-label">Note interne</label>
                        <input type="text" id="pc-rates-input-note" class="pc-form-input">
                    </div>
                    <fieldset class="pc-fieldset">
                        <legend>Frais Invités Supp.</legend>
                        <div class="pc-form-row">
                            <div class="pc-form-group">
                                <label class="pc-form-label">Coût (€)</label>
                                <input type="number" id="pc-rates-input-guest-fee" class="pc-form-input" step="0.01">
                            </div>
                            <div class="pc-form-group">
                                <label class="pc-form-label">À partir de</label>
                                <input type="number" id="pc-rates-input-guest-from" class="pc-form-input">
                            </div>
                        </div>
                    </fieldset>
                </div>

                <div id="pc-rates-group-promo-fields" style="display:none;">
                    <div class="pc-form-row">
                        <div class="pc-form-group">
                            <label class="pc-form-label">Type</label>
                            <select id="pc-rates-input-promo-type" class="pc-form-select">
                                <option value="percent">Pourcentage (%)</option>
                                <option value="fixed">Montant fixe (€)</option>
                            </select>
                        </div>
                        <div class="pc-form-group">
                            <label class="pc-form-label">Valeur *</label>
                            <input type="number" id="pc-rates-input-promo-val" class="pc-form-input" step="0.01">
                        </div>
                    </div>
                    <div class="pc-form-group">
                        <label class="pc-form-label">Valable jusqu'au</label>
                        <input type="date" id="pc-rates-input-promo-validity" class="pc-form-input">
                    </div>
                </div>

                <fieldset id="pc-rates-periods-manager" class="pc-fieldset">
                    <legend>Période(s) d'application</legend>

                    <ul id="pc-rates-periods-list" class="pc-periods-list"></ul>

                    <div class="pc-add-period-box">
                        <label id="lbl-rates-period-action" class="pc-form-label">Ajouter une période :</label>
                        <div class="pc-form-row" style="margin-bottom:0;">
                            <input type="date" id="pc-rates-period-start" class="pc-form-input">
                            <span class="pc-period-separator">au</span>
                            <input type="date" id="pc-rates-period-end" class="pc-form-input">
                            <button type="button" id="btn-add-period-manual" class="pc-btn pc-btn-small">Ajouter</button>
                        </div>
                        <p class="pc-form-help">
                            * Pour une nouvelle saison, remplissez ces dates, elles seront ajoutées automatiquement.
                        </p>
                    </div>
                </fieldset>

                <div class="pc-modal-footer">
                    <button type="button" class="pc-btn pc-btn-danger" id="btn-delete-rates-entity" style="display:none;">
                        <i class="fas fa-trash"></i> Supprimer
                    </button>
                    <button type="button" id="btn-save-rates-modal-action" class="pc-btn pc-btn-primary pc-btn-large">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Confirmation -->
<div id="pc-rates-confirm-modal" class="pc-modal pc-modal-center" style="display:none;">
    <div class="pc-modal-content pc-modal-small">
        <div class="pc-modal-header">
            <h2>Confirmer la suppression</h2>
            <button class="pc-close-modal pc-rates-confirm-close" type="button">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="pc-modal-body">
            <p id="pc-rates-confirm-message">Êtes-vous sûr de vouloir supprimer cet élément ?</p>
        </div>
        <div class="pc-modal-footer">
            <button type="button" class="pc-btn pc-btn-secondary" id="pc-rates-confirm-cancel">Annuler</button>
            <button type="button" class="pc-btn pc-btn-danger" id="pc-rates-confirm-ok">
                <i class="fas fa-trash"></i> Supprimer
            </button>
        </div>
    </div>
</div>