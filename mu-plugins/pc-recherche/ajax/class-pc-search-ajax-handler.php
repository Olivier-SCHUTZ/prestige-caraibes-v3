<?php

/**
 * Gestionnaire unifié des requêtes AJAX pour le module de recherche
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Search_Ajax_Handler
{
    public function register()
    {
        // Recherche de logements
        add_action('wp_ajax_pc_filter_logements', [$this, 'handle_logement_search']);
        add_action('wp_ajax_nopriv_pc_filter_logements', [$this, 'handle_logement_search']);

        // Recherche d'expériences
        add_action('wp_ajax_pc_filter_experiences', [$this, 'handle_experience_search']);
        add_action('wp_ajax_nopriv_pc_filter_experiences', [$this, 'handle_experience_search']);
    }

    /**
     * Callback pour la recherche de logements
     */
    public function handle_logement_search()
    {
        check_ajax_referer('pc_search_nonce', 'security');

        $filters = [
            'page'         => isset($_POST['page']) ? intval($_POST['page']) : 1,
            'ville'        => isset($_POST['ville']) ? sanitize_text_field($_POST['ville']) : '',
            'date_arrivee' => isset($_POST['date_arrivee']) ? sanitize_text_field($_POST['date_arrivee']) : '',
            'date_depart'  => isset($_POST['date_depart']) ? sanitize_text_field($_POST['date_depart']) : '',
            'invites'      => isset($_POST['invites']) ? intval($_POST['invites']) : 1,
            'chambres'     => isset($_POST['chambres']) ? intval($_POST['chambres']) : 0,
            'sdb'          => isset($_POST['sdb']) ? intval($_POST['sdb']) : 0,
            'prix_min'     => isset($_POST['prix_min']) ? sanitize_text_field($_POST['prix_min']) : '',
            'prix_max'     => isset($_POST['prix_max']) ? sanitize_text_field($_POST['prix_max']) : '',
            'theme'        => isset($_POST['theme']) ? sanitize_text_field($_POST['theme']) : '',
        ];

        $engine = new PC_Logement_Search_Engine();
        $results = $engine->search($filters);

        $vignettes_html = '';
        if (!empty($results['vignettes'])) {
            $vignettes_html .= '<div class="pc-results-grid">';
            foreach ($results['vignettes'] as $vignette_data) {
                $vignettes_html .= PC_Search_Render_Helper::render_logement_vignette($vignette_data);
            }
            $vignettes_html .= '</div>';
        } else {
            $vignettes_html = '<div class="pc-no-results"><h3>Aucun logement ne correspond à votre recherche.</h3><p>Essayez d\'ajuster vos filtres.</p></div>';
        }

        wp_send_json_success([
            'vignettes_html'  => $vignettes_html,
            'pagination_html' => PC_Search_Render_Helper::render_pagination($results['pagination']),
            'map_data'        => $results['map_data'],
        ]);
    }

    /**
     * Callback pour la recherche d'expériences
     */
    public function handle_experience_search()
    {
        check_ajax_referer('pc_experience_search_nonce', 'security');

        $filters = [
            'category'     => isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '',
            'ville'        => isset($_POST['ville']) ? sanitize_text_field($_POST['ville']) : '',
            'keyword'      => isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '',
            'participants' => isset($_POST['participants']) ? intval($_POST['participants']) : 1,
            'prix_min'     => isset($_POST['prix_min']) ? sanitize_text_field($_POST['prix_min']) : '',
            'prix_max'     => isset($_POST['prix_max']) ? sanitize_text_field($_POST['prix_max']) : '',
            'page'         => isset($_POST['page']) ? intval($_POST['page']) : 1,
        ];

        $engine = new PC_Experience_Search_Engine();
        $results = $engine->search($filters);

        wp_send_json_success([
            'vignettes_html'  => PC_Search_Render_Helper::render_experience_results_grid($results['vignettes']),
            'pagination_html' => PC_Search_Render_Helper::render_pagination($results['pagination']),
            'map_data'        => $results['map_data']
        ]);
    }
}
