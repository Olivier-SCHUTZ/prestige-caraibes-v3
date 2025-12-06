<?php

/**
 * Plugin Name: PC Rate Manager
 * Description: Interface visuelle pour gérer les tarifs et saisons ACF.
 * Version: 1.2
 * Author: Gemini
 */

if (! defined('ABSPATH')) exit;

class PCRateManager
{
    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('add_meta_boxes', [$this, 'add_custom_meta_box']);
        // NOUVEAU : On injecte le popup dans le footer pour qu'il soit toujours visible
        add_action('admin_footer', [$this, 'render_modal_footer']);
    }

    public function add_custom_meta_box()
    {
        $screens = ['villa', 'appartement']; // Tes CPT
        foreach ($screens as $screen) {
            add_meta_box(
                'pc_rate_manager_box',
                '📅 Calendrier des Tarifs & Saisons',
                [$this, 'render_meta_box'],
                $screen,
                'normal',
                'high'
            );
        }
    }

    public function render_meta_box()
    {
?>
        <div id="pc-rate-manager-wrapper">
            <div class="pc-rm-sidebar">
                <h3>Gestion des Saisons</h3>

                <div class="pc-rm-section">
                    <h4>Mes Saisons</h4>
                    <div id="pc-seasons-list">
                        <p class="pc-rm-empty-state">Chargement...</p>
                    </div>
                </div>

                <div class="pc-rm-section pc-rm-actions">
                    <button type="button" class="button button-primary pc-btn-full" id="btn-add-new-season">
                        <span class="dashicons dashicons-plus"></span> Nouvelle Saison
                    </button>

                    <button type="button" class="button button-secondary pc-btn-full" id="btn-add-promo">
                        <span class="dashicons dashicons-tag"></span> Ajouter Promo
                    </button>
                </div>

                <div class="pc-rm-info">
                    <p><small>ℹ️ Cliquez sur une saison pour l'éditer. Glissez-la sur le calendrier pour l'appliquer.</small></p>
                </div>
            </div>

            <div class="pc-rm-calendar-container">
                <div id="pc-calendar"></div>
            </div>
        </div>
    <?php
    }

    // --- NOUVELLE FONCTION : Le popup est généré ici, hors de la boite ---
    public function render_modal_footer()
    {
        $screen = get_current_screen();
        if (!in_array($screen->post_type, ['villa', 'appartement'])) return;
    ?>

        <div id="pc-season-modal" class="pc-modal" style="display:none;">
            <div class="pc-modal-content">
                <div class="pc-modal-header">
                    <h2 id="pc-modal-title">Éditer la Saison</h2>
                    <span class="pc-close-modal">&times;</span>
                </div>
                <div class="pc-modal-body">
                    <form id="pc-season-form">
                        <input type="hidden" id="pc-edit-row-id" value="">

                        <div class="pc-form-group">
                            <label>Nom de la saison *</label>
                            <input type="text" id="pc-input-name" placeholder="ex: Haute Saison" required>
                        </div>

                        <div class="pc-form-row">
                            <div class="pc-form-group">
                                <label>Tarif (€/nuit) *</label>
                                <input type="number" id="pc-input-price" step="0.01" required>
                            </div>
                            <div class="pc-form-group">
                                <label>Nuits minimum</label>
                                <input type="number" id="pc-input-min-nights" placeholder="Défaut">
                            </div>
                        </div>

                        <fieldset id="pc-periods-manager" style="display:none;">
                            <legend>Périodes planifiées</legend>

                            <ul id="pc-periods-list"></ul>

                            <div class="pc-add-period-box">
                                <label>Ajouter des dates :</label>
                                <div class="pc-form-row" style="margin-bottom:0;">
                                    <input type="date" id="pc-period-start">
                                    <span style="align-self:center;">au</span>
                                    <input type="date" id="pc-period-end">
                                    <button type="button" id="btn-add-period-manual" class="button button-small">OK</button>
                                </div>
                            </div>
                        </fieldset>

                        <div class="pc-form-group">
                            <label>Note interne</label>
                            <input type="text" id="pc-input-note" placeholder="Infos pour vous...">
                        </div>

                        <fieldset>
                            <legend>Frais Invités Supplémentaires</legend>
                            <div class="pc-form-row">
                                <div class="pc-form-group">
                                    <label>Coût (€/nuit)</label>
                                    <input type="number" id="pc-input-guest-fee" step="0.01">
                                </div>
                                <div class="pc-form-group">
                                    <label>À partir de (pers.)</label>
                                    <input type="number" id="pc-input-guest-from">
                                </div>
                            </div>
                        </fieldset>

                        <div class="pc-modal-footer">
                            <button type="button" class="button button-link-delete" id="btn-delete-season-def" style="display:none; color: #b32d2e; border-color: #b32d2e;">Supprimer saison</button>
                            <button type="button" id="btn-save-modal-action" class="button button-primary button-large">Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
<?php
    }

    public function enqueue_assets($hook)
    {
        global $post;
        if (! in_array($hook, ['post.php', 'post-new.php'])) return;

        // FullCalendar
        wp_enqueue_style('fullcalendar-css', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css');
        wp_enqueue_script('fullcalendar-js', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js', [], '6.1.10', true);

        // Assets Locaux
        wp_enqueue_style('pc-rm-style', plugin_dir_url(__FILE__) . 'assets/css/style.css', [], '1.2');
        wp_enqueue_script('pc-rm-app', plugin_dir_url(__FILE__) . 'assets/js/app.js', ['jquery', 'fullcalendar-js'], '1.2', true);

        // Config JS
        $base_price = get_field('base_price_from', $post->ID) ?: 0;
        wp_localize_script('pc-rm-app', 'pcRmConfig', [
            'field_season_repeater' => 'field_pc_season_blocks_20250826',
            'field_promo_repeater'  => 'field_pc_promo_blocks',
            'base_price'            => $base_price,
        ]);
    }
}

new PCRateManager();
