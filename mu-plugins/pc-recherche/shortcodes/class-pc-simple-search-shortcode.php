<?php

/**
 * Shortcode [barre_recherche_simple] pour la barre de recherche (mode GET)
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Simple_Search_Shortcode extends PC_Search_Shortcode_Base
{
    protected function get_shortcode_tag(): string
    {
        return 'barre_recherche_simple';
    }

    public function render($atts = []): string
    {
        // 1. Chargement des assets
        if (class_exists('PC_Search_Asset_Manager')) {
            PC_Search_Asset_Manager::enqueue_assets();
        }

        // 2. Traitement des attributs
        $a = shortcode_atts([
            'target' => '',
            'title'  => '',
        ], $atts, $this->get_shortcode_tag());

        $target = trim($a['target']);
        if ($target === '') {
            $page = get_page_by_path('recherche');
            $target = $page ? get_permalink($page) : home_url('/');
        }

        // Normalisation de l'URL absolue
        if (strpos($target, 'http://') !== 0 && strpos($target, 'https://') !== 0) {
            $target = home_url('/' . ltrim($target, '/'));
        }

        if (get_option('permalink_structure')) {
            $parsed = wp_parse_url($target);
            if (!isset($parsed['query']) && substr($target, -1) !== '/') {
                $target = trailingslashit($target);
            }
        }

        // 3. Données des villes
        $villes = PC_Search_Data_Helper::get_villes();
        $options_villes = '';
        foreach ($villes as $slug => $label) {
            $options_villes .= '<option value="' . esc_attr($slug) . '">' . esc_html($label) . '</option>';
        }

        // 4. Rendu du HTML
        ob_start(); ?>
        <div class="pc-search-wrapper" data-pc-search-mode="get" data-pc-target="<?php echo esc_url($target); ?>" role="search" aria-label="Recherche d'hébergements (simple)">
            <div class="pc-search-shell">
                <form id="pc-filters-form" class="pc-search-form" action="<?php echo esc_url($target); ?>" method="get" autocomplete="off">
                    <div class="pc-search-field pc-search-field--location pc-area-loc">
                        <label for="filter-ville" class="sr-only">Destination</label>
                        <select id="filter-ville" name="ville" class="pc-input" aria-label="Destination">
                            <option value="" selected hidden>Destination</option>
                            <?php echo $options_villes; ?>
                        </select>
                    </div>

                    <div class="pc-search-field pc-search-field--date pc-area-arr">
                        <label for="filter-date-arrivee" class="sr-only">Arrivée</label>
                        <input type="text" id="filter-date-arrivee" class="pc-input" placeholder="Arrivée" readonly aria-label="Date d'arrivée">
                    </div>

                    <div class="pc-search-field pc-search-field--date pc-area-dep">
                        <label for="filter-date-depart" class="sr-only">Départ</label>
                        <input type="text" id="filter-date-depart" class="pc-input" placeholder="Départ" readonly aria-label="Date de départ">
                    </div>

                    <input type="hidden" id="filter-date-arrivee-iso" name="date_arrivee">
                    <input type="hidden" id="filter-date-depart-iso" name="date_depart">

                    <div class="pc-search-field pc-search-field--guests pc-area-gst" style="position:relative;">
                        <button type="button" class="pc-input pc-guests-trigger" id="guests-summary" aria-haspopup="dialog" aria-expanded="false">Invités</button>
                        <input type="hidden" id="filter-invites" name="invites" value="1">
                        <div class="pc-guests-popover" hidden role="dialog" aria-label="Sélection des invités">
                            <div class="pc-guests-row">
                                <div><strong>Adultes</strong><br><span class="muted">13 ans et +</span></div>
                                <div>
                                    <button type="button" class="guest-stepper" data-type="adultes" data-step="-1" aria-label="Moins d'adultes">−</button>
                                    <span data-type="adultes" aria-live="polite">1</span>
                                    <button type="button" class="guest-stepper" data-type="adultes" data-step="1" aria-label="Plus d'adultes">+</button>
                                </div>
                            </div>
                            <div class="pc-guests-row">
                                <div><strong>Enfants</strong><br><span class="muted">2–12 ans</span></div>
                                <div>
                                    <button type="button" class="guest-stepper" data-type="enfants" data-step="-1" aria-label="Moins d'enfants">−</button>
                                    <span data-type="enfants" aria-live="polite">0</span>
                                    <button type="button" class="guest-stepper" data-type="enfants" data-step="1" aria-label="Plus d'enfants">+</button>
                                </div>
                            </div>
                            <div class="pc-guests-row">
                                <div><strong>Bébés</strong><br><span class="muted">−2 ans</span></div>
                                <div>
                                    <button type="button" class="guest-stepper" data-type="bebes" data-step="-1" aria-label="Moins de bébés">−</button>
                                    <span data-type="bebes" aria-live="polite">0</span>
                                    <button type="button" class="guest-stepper" data-type="bebes" data-step="1" aria-label="Plus de bébés">+</button>
                                </div>
                            </div>
                            <div style="text-align:right;"><button type="button" class="pc-btn pc-btn--line pc-guests-close">Fermer</button></div>
                        </div>
                    </div>

                    <button class="pc-search-submit pc-btn pc-btn--primary pc-area-btn" type="submit">Rechercher</button>
                </form>
            </div>
        </div>
<?php
        return ob_get_clean();
    }
}
