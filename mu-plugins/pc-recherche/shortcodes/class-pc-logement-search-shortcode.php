<?php

/**
 * Shortcode [barre_recherche_precise] pour la recherche avancée de logements
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Logement_Search_Shortcode extends PC_Search_Shortcode_Base
{
    protected function get_shortcode_tag(): string
    {
        return 'barre_recherche_precise';
    }

    public function render($atts = []): string
    {
        // 1. Chargement des assets
        if (class_exists('PC_Search_Asset_Manager')) {
            PC_Search_Asset_Manager::enqueue_assets();
        }

        // 2. Récupération des données transversales
        $villes = PC_Search_Data_Helper::get_villes();
        $eqs    = PC_Search_Data_Helper::get_equipements();

        // 3. Logique de rendu côté serveur (SSR)
        $initial_vignettes_html = '';
        $initial_pagination_html = '';
        $is_initial_ssr_load = true;

        if (defined('DOING_AJAX') && DOING_AJAX) {
            $is_initial_ssr_load = false;
        }

        if ($is_initial_ssr_load) {
            $filters = [
                'page'         => max(1, intval(get_query_var('paged', 1))),
                'ville'        => sanitize_text_field($_GET['ville'] ?? ''),
                'date_arrivee' => sanitize_text_field($_GET['date_arrivee'] ?? ''),
                'date_depart'  => sanitize_text_field($_GET['date_depart'] ?? ''),
                'invites'      => intval($_GET['invites'] ?? 1),
                'theme'        => sanitize_text_field($_GET['theme'] ?? ''),
                'prix_min'     => isset($_GET['prix_min']) && $_GET['prix_min'] !== '' ? intval($_GET['prix_min']) : '',
                'prix_max'     => isset($_GET['prix_max']) && $_GET['prix_max'] !== '' ? intval($_GET['prix_max']) : '',
                'chambres'     => intval($_GET['chambres'] ?? 0),
                'sdb'          => intval($_GET['sdb'] ?? 0),
                'equipements'  => isset($_GET['equipements']) ? array_map('sanitize_text_field', (array) $_GET['equipements']) : [],
            ];

            // Appel direct à notre nouveau moteur
            $engine = new PC_Logement_Search_Engine();
            $initial_results = $engine->search($filters);

            if (!empty($initial_results['vignettes'])) {
                $result_ids = wp_list_pluck($initial_results['vignettes'], 'id');
                $GLOBALS['pc_itemlist_ids'] = $result_ids;

                $vignette_count = 0;
                $initial_vignettes_html .= '<div class="pc-results-grid">';
                foreach ($initial_results['vignettes'] as $vignette_data) {
                    $is_lcp_candidate = ($vignette_count === 0 && $filters['page'] <= 1);
                    // Appel direct à notre nouveau Helper de rendu
                    $initial_vignettes_html .= PC_Search_Render_Helper::render_logement_vignette($vignette_data, $is_lcp_candidate);
                    $vignette_count++;
                }
                $initial_vignettes_html .= '</div>';
            } else {
                $initial_vignettes_html = '<div class="pc-no-results"><h3>Aucun logement ne correspond à votre recherche.</h3><p>Essayez d\'ajuster vos filtres.</p></div>';
            }

            $initial_pagination_html = PC_Search_Render_Helper::render_pagination($initial_results['pagination']);
        }

        // 4. Préparation du HTML des options (Villes et Équipements)
        $options_villes = '';
        foreach ($villes as $slug => $label) {
            $options_villes .= '<option value="' . esc_attr($slug) . '">' . esc_html($label) . '</option>';
        }

        $equip_group = '';
        if (!empty($eqs)) {
            $chips = '';
            foreach ($eqs as $e) {
                $chips .= '<label class="pc-chip"><input type="checkbox" class="filter-eq" name="equipements[]" value="' . esc_attr($e['slug']) . '" data-tax="' . esc_attr($e['tax']) . '"><span>' . esc_html($e['name']) . '</span></label>';
            }
            $equip_group = '<div class="pc-adv-group"><div class="pc-adv-label" style="margin-bottom:6px;">Équipements</div><div class="pc-adv-chips">' . $chips . '</div></div>';
        }

        // 5. Rendu du formulaire complet
        $html = <<<HTML
<div class="pc-search-wrapper" data-pc-search-mode="ajax" role="search" aria-label="Recherche d'hébergements">
  <form id="pc-filters-form" class="pc-search-shell" action="#" method="post" autocomplete="off">
    <div class="pc-search-form">
      <div class="pc-search-field pc-search-field--location pc-area-loc"><label for="filter-ville" class="sr-only">Destination</label><select id="filter-ville" name="ville" class="pc-input" aria-label="Destination"><option value="" selected hidden>Destination</option>$options_villes</select></div>
      <div class="pc-search-field pc-search-field--date pc-area-arr"><label for="filter-date-arrivee" class="sr-only">Arrivée</label><input type="text" id="filter-date-arrivee" class="pc-input" placeholder="Arrivée" readonly aria-label="Date d'arrivée"></div>
      <div class="pc-search-field pc-search-field--date pc-area-dep"><label for="filter-date-depart" class="sr-only">Départ</label><input type="text" id="filter-date-depart" class="pc-input" placeholder="Départ" readonly aria-label="Date de départ"></div>
      <input type="hidden" id="filter-date-arrivee-iso" name="date_arrivee"><input type="hidden" id="filter-date-depart-iso"  name="date_depart">
      <div class="pc-search-field pc-search-field--guests pc-area-gst" style="position:relative;"><button type="button" class="pc-input pc-guests-trigger" id="guests-summary" aria-haspopup="dialog" aria-expanded="false">Invités</button><input type="hidden" id="filter-invites" name="invites" value="1"><div class="pc-guests-popover" hidden role="dialog" aria-label="Sélection des invités"><div class="pc-guests-row"><div><strong>Adultes</strong><br><span class="muted">13 ans et +</span></div><div><button type="button" class="guest-stepper" data-type="adultes" data-step="-1" aria-label="Moins d'adultes">−</button><span data-type="adultes" aria-live="polite">1</span><button type="button" class="guest-stepper" data-type="adultes" data-step="1" aria-label="Plus d'adultes">+</button></div></div><div class="pc-guests-row"><div><strong>Enfants</strong><br><span class="muted">2–12 ans</span></div><div><button type="button" class="guest-stepper" data-type="enfants" data-step="-1" aria-label="Moins d'enfants">−</button><span data-type="enfants" aria-live="polite">0</span><button type="button" class="guest-stepper" data-type="enfants" data-step="1" aria-label="Plus d'enfants">+</button></div></div><div class="pc-guests-row"><div><strong>Bébés</strong><br><span class="muted">−2 ans</span></div><div><button type="button" class="guest-stepper" data-type="bebes" data-step="-1" aria-label="Moins de bébés">−</button><span data-type="bebes" aria-live="polite">0</span><button type="button" class="guest-stepper" data-type="bebes" data-step="1" aria-label="Plus de bébés">+</button></div></div><div style="text-align:right;"><button type="button" class="pc-btn pc-btn--line pc-guests-close">Fermer</button></div></div></div>
      <button class="pc-search-submit pc-btn pc-btn--primary pc-area-btn" type="submit">Rechercher</button>
    </div>
    <div class="pc-row-adv-toggle" aria-label="Filtres avancés"><div><button type="button" class="pc-adv-toggle pc-btn pc-btn--line" aria-controls="pc-advanced">Plus de filtres</button></div><div><button type="button" class="pc-adv-reset pc-btn pc-btn--line">Réinitialiser</button></div></div>
    <div id="pc-advanced" class="pc-advanced" hidden><div class="pc-advanced__grid"><div class="pc-adv-field"><div class="pc-adv-label">Prix (€ / nuit)</div><div class="pc-price-range" data-min="0" data-max="2000" data-step="10"><input type="range" id="filter-prix-min-range" min="0" max="2000" step="10" value="0"><input type="range" id="filter-prix-max-range" min="0" max="2000" step="10" value="2000"></div><div class="pc-price-values"><input type="number" id="filter-prix-min" name="prix_min" class="pc-input" placeholder="Min" min="0" step="10"><input type="number" id="filter-prix-max" name="prix_max" class="pc-input" placeholder="Max" min="0" step="10"></div></div><div class="pc-adv-field"><div class="pc-adv-label">Chambres (min)</div><div class="pc-num-step"><button type="button" class="num-stepper" data-target="chambres" data-step="-1">−</button><input type="number" class="pc-input pc-num-input" id="filter-chambres" name="chambres" min="0" step="1" value="0"><button type="button" class="num-stepper" data-target="chambres" data-step="1">+</button></div></div><div class="pc-adv-field"><div class="pc-adv-label">Salles de bain (min)</div><div class="pc-num-step"><button type="button" class="num-stepper" data-target="sdb" data-step="-1">−</button><input type="number" class="pc-input pc-num-input" id="filter-sdb" name="sdb" min="0" step="1" value="0"><button type="button" class="num-stepper" data-target="sdb" data-step="1">+</button></div></div></div>$equip_group<div class="pc-adv-actions"><button type="button" class="pc-btn pc-btn--line pc-adv-close">Fermer</button></div></div>
  </form>
  <div id="pc-results-container" class="flow" style="margin-top:1rem;">
    $initial_vignettes_html
  </div>
  $initial_pagination_html
</div>
HTML;

        return $html;
    }
}
