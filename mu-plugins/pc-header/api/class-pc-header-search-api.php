<?php

/**
 * Gestion de l'API REST pour la recherche du Header
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Header_Search_API
{
    /**
     * Initialise les hooks de l'API
     */
    public function register()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Déclare la route REST
     */
    public function register_routes()
    {
        register_rest_route('pc/v1', '/search-suggest', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handle_request'],
            'permission_callback' => '__return_true', // public
            'args'                => [
                'q' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    /**
     * Traite la requête de recherche
     */
    public function handle_request(\WP_REST_Request $req): \WP_REST_Response
    {
        $q = trim((string)$req->get_param('q'));
        if (mb_strlen($q) < 2) {
            return new \WP_REST_Response([], 200);
        }

        // Utilisation de notre nouvelle classe de configuration !
        $cfg = PC_Header_Config::get();
        $pts = $cfg['search_post_types'] ?? ['villa', 'appartement', 'destination', 'experience'];
        $max = (int)($cfg['search_max_results'] ?? 8);

        $query = new \WP_Query([
            'post_type'              => (array)$pts,
            'post_status'            => 'publish',
            'posts_per_page'         => max(1, min(20, $max)),
            's'                      => $q,
            'ignore_sticky_posts'    => true,
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'fields'                 => 'ids',
        ]);

        $type_labels = [
            'villa'       => 'Villa',
            'appartement' => 'Logement',
            'destination' => 'Destination',
            'experience'  => 'Expérience',
        ];

        $out = [];
        foreach ((array)$query->posts as $pid) {
            $pt = get_post_type($pid);
            $out[] = [
                'id'    => (int)$pid,
                'title' => get_the_title($pid),
                'type'  => $type_labels[$pt] ?? ucfirst((string)$pt),
                'url'   => get_permalink($pid),
            ];
        }
        return new \WP_REST_Response($out, 200);
    }
}
