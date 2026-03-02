<?php

/**
 * Shortcode [barre_recherche_experiences] pour la recherche d'expériences
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Experience_Search_Shortcode extends PC_Search_Shortcode_Base
{
    protected function get_shortcode_tag(): string
    {
        return 'barre_recherche_experiences';
    }

    public function render($atts = []): string
    {
        // 1. Chargement des assets
        if (class_exists('PC_Search_Asset_Manager')) {
            PC_Search_Asset_Manager::enqueue_experience_assets();
        }

        // 2. Récupération des données transversales
        $categories = PC_Search_Data_Helper::get_experience_categories();
        $villes     = PC_Search_Data_Helper::get_experience_villes();

        // 3. Récupération des filtres initiaux (URL)
        $initial_category = isset($_GET['categorie']) ? sanitize_text_field($_GET['categorie']) : '';

        // 4. Préparation des options pour le HTML
        $options_categories = '';
        foreach ($categories as $slug => $label) {
            $selected = selected($initial_category, $slug, false);
            $options_categories .= '<option value="' . esc_attr($slug) . '"' . $selected . '>' . esc_html($label) . '</option>';
        }

        $options_villes = '';
        foreach ($villes as $ville_name) {
            $options_villes .= '<option value="' . esc_attr($ville_name) . '">' . esc_html($ville_name) . '</option>';
        }

        // 5. Recherche SSR Initiale
        $engine = new PC_Experience_Search_Engine();
        $initial_filters = ['category' => $initial_category];
        $initial_results = $engine->search($initial_filters);

        // Transmission des données de la carte au JS
        wp_localize_script('pc-experience-search-js', 'pc_exp_initial_data', ['map_data' => $initial_results['map_data']]);

        // HTML des résultats
        $initial_vignettes_html  = PC_Search_Render_Helper::render_experience_results_grid($initial_results['vignettes'], true);
        $initial_pagination_html = PC_Search_Render_Helper::render_pagination($initial_results['pagination']);

        // 6. Rendu HTML complet du composant
        ob_start();
?>
        <div class="pc-exp-search-wrapper" role="search" aria-label="Recherche d'expériences">
            <form id="pc-exp-filters-form" class="pc-exp-search-shell" action="#" method="post" autocomplete="off">
                <div class="pc-exp-search-form">
                    <div class="pc-exp-search-field pc-exp-area-cat">
                        <label for="filter-exp-category" class="sr-only">Catégorie</label>
                        <select id="filter-exp-category" name="exp_category" class="pc-input">
                            <option value="">Toutes les catégories</option><?php echo $options_categories; ?>
                        </select>
                    </div>
                    <div class="pc-exp-search-field pc-exp-area-loc">
                        <label for="filter-exp-ville" class="sr-only">Destination</label>
                        <select id="filter-exp-ville" name="exp_ville" class="pc-input">
                            <option value="">Toute la Guadeloupe</option><?php echo $options_villes; ?>
                        </select>
                    </div>
                    <div class="pc-exp-search-field pc-exp-search-field--keyword pc-exp-area-key">
                        <label for="filter-exp-keyword" class="sr-only">Mot-clé</label>
                        <input type="text" id="filter-exp-keyword" name="exp_keyword" class="pc-input" placeholder="Ex: kayak, randonnée...">
                    </div>
                    <button class="pc-exp-search-submit pc-btn pc-btn--primary pc-exp-area-btn" type="submit">Rechercher</button>
                </div>
                <div class="pc-exp-row-adv-toggle">
                    <button type="button" class="pc-exp-adv-toggle pc-btn pc-btn--line" aria-controls="pc-exp-advanced" aria-expanded="false">Plus de filtres</button>
                </div>
                <div id="pc-exp-advanced" class="pc-exp-advanced" hidden>
                    <div class="pc-exp-advanced__grid">
                        <div class="pc-exp-adv-field">
                            <div class="pc-exp-adv-label">Participants (min)</div>
                            <div class="pc-num-step">
                                <button type="button" class="num-stepper" data-target="participants" data-step="-1" aria-label="Moins de participants">−</button>
                                <input type="number" class="pc-input pc-num-input" id="filter-exp-participants" name="exp_participants" min="1" step="1" value="1">
                                <button type="button" class="num-stepper" data-target="participants" data-step="1" aria-label="Plus de participants">+</button>
                            </div>
                        </div>
                        <div class="pc-exp-adv-field">
                            <div class="pc-exp-adv-label">Prix (€ / personne)</div>
                            <div class="pc-price-range" data-min="0" data-max="500" data-step="10">
                                <input type="range" id="filter-exp-prix-min-range" min="0" max="500" step="10" value="0">
                                <input type="range" id="filter-exp-prix-max-range" min="0" max="500" step="10" value="500">
                            </div>
                            <div class="pc-price-values">
                                <input type="number" id="filter-exp-prix-min" name="exp_prix_min" class="pc-input" placeholder="Min" min="0" step="10">
                                <input type="number" id="filter-exp-prix-max" name="exp_prix_max" class="pc-input" placeholder="Max" min="0" step="10">
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <div id="pc-exp-results-container" class="flow" style="margin-top:2rem;">
                <?php echo $initial_vignettes_html; ?>
                <?php echo $initial_pagination_html; ?>
            </div>
        </div>
<?php
        return ob_get_clean();
    }
}
