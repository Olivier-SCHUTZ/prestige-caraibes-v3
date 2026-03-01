<?php

/**
 * Module : Helper pour les requêtes (Query Helper)
 * Centralise la logique de récupération des posts liés aux destinations.
 */

if (!defined('ABSPATH')) {
    exit; // Sécurité
}

class PC_Destination_Query_Helper
{
    /**
     * Sécurise une valeur entière.
     *
     * @param mixed $v La valeur à nettoyer.
     * @param int $default Valeur par défaut.
     * @return int
     */
    public static function safe_int($v, $default = 0)
    {
        $v = is_numeric($v) ? intval($v) : $default;
        return max(0, $v);
    }

    /**
     * Récupère les posts (logements, expériences) liés à une destination via ACF (rel_destination).
     *
     * @param string $post_type Type de post (ex: 'logement', 'experience').
     * @param int $dest_id ID de la destination.
     * @param array $args Arguments WP_Query supplémentaires.
     * @return WP_Query
     */
    public static function get_posts_by_rel_destination($post_type, $dest_id, $args = [])
    {
        $defaults = [
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => 12,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                [
                    'key'     => 'rel_destination',
                    // ACF relationship est stocké en array sérialisé
                    'value'   => sprintf('"%d"', $dest_id),
                    'compare' => 'LIKE',
                ]
            ],
        ];

        $qargs = wp_parse_args($args, $defaults);
        return new WP_Query($qargs);
    }
}
