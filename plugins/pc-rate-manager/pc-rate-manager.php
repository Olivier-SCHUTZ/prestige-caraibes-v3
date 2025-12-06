<?php

/**
 * Plugin Name: PC Rate Manager
 * Description: Interface visuelle pour gérer les tarifs et saisons ACF.
 * Version: 1.0
 * Author: Gemini
 */

if (! defined('ABSPATH')) exit;

class PCRateManager
{

    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('add_meta_boxes', [$this, 'add_custom_meta_box']);
    }

    /**
     * Ajoute la Meta Box "Cockpit Tarifaire"
     * On la met en 'high' pour qu'elle soit tout en haut.
     */
    public function add_custom_meta_box()
    {
        // On cible tes Custom Post Types : 'villa', 'appartement'
        $screens = ['villa', 'appartement'];
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

    /**
     * Le contenu HTML du Cockpit
     */
    public function render_meta_box()
    {
?>
        <div id="pc-rate-manager-wrapper">
            <div class="pc-rm-sidebar">
                <h3>Outils</h3>

                <div class="pc-rm-section">
                    <h4>Saisons Rapides</h4>
                    <div class="pc-draggable-event season-type" data-type="season" data-price="100" data-color="#3788d8">
                        Moyenne Saison
                    </div>
                    <div class="pc-draggable-event season-type" data-type="season" data-price="150" data-color="#ff9f89">
                        Haute Saison
                    </div>
                    <div class="pc-draggable-event season-type" data-type="season" data-price="250" data-color="#d9534f">
                        Très Haute Saison
                    </div>
                </div>

                <div class="pc-rm-section">
                    <h4>Manuel</h4>
                    <button type="button" class="button button-secondary" id="btn-add-custom-season">Ajouter Saison</button>
                    <button type="button" class="button button-secondary" id="btn-add-promo">Ajouter Promo</button>
                </div>

                <div class="pc-rm-info">
                    <p><small>Glissez les éléments sur le calendrier ou cliquez sur les dates.</small></p>
                </div>
            </div>

            <div class="pc-rm-calendar-container">
                <div id="pc-calendar"></div>
            </div>
        </div>

        <div id="pc-event-modal" style="display:none;">
        </div>
<?php
    }

    public function enqueue_assets($hook)
    {
        global $post;

        // Charger uniquement sur l'édition de post
        if (! in_array($hook, ['post.php', 'post-new.php'])) return;

        // Vérifier le type de post (optionnel mais recommandé)
        if (! in_array($post->post_type, ['villa', 'appartement'])) return;

        // 1. FullCalendar CSS & JS (CDN pour l'instant)
        wp_enqueue_style('fullcalendar-css', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css');
        wp_enqueue_script('fullcalendar-js', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js', [], '6.1.10', true);

        // 2. Nos assets
        wp_enqueue_style('pc-rm-style', plugin_dir_url(__FILE__) . 'assets/css/style.css', [], '1.0');
        wp_enqueue_script('pc-rm-app', plugin_dir_url(__FILE__) . 'assets/js/app.js', ['jquery', 'fullcalendar-js'], '1.0', true);

        // Récupération du PRIX DE BASE (Couche 1)
        // On cherche la valeur dans le champ ACF 'base_price_from' (nom du champ dans ton JSON)
        $base_price = get_field('base_price_from', $post->ID);
        if (!$base_price) $base_price = 0; // Sécurité

        // 3. Passer des variables PHP vers JS (Clés des champs ACF)
        wp_localize_script('pc-rm-app', 'pcRmConfig', [
            'field_season_repeater' => 'field_pc_season_blocks_20250826', // Clé du répéteur Saisons
            'field_promo_repeater'  => 'field_pc_promo_blocks',         // Clé du répéteur Promos (le nouveau)
            'base_price'            => $base_price, // Prix de base de la location
        ]);
    }
}

new PCRateManager();
