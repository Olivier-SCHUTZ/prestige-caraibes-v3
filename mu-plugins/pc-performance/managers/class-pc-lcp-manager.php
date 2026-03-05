<?php

/**
 * PC LCP Manager
 * Optimisation du Largest Contentful Paint avec cache intelligent
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_LCP_Manager
{

    private $lcp_urls = ['mobile' => '', 'desktop' => ''];

    public function __construct()
    {
        // On calcule les URLs avant l'affichage du header
        add_action('wp', [$this, 'calculate_lcp_urls']);
        // On injecte le fetchpriority sur la bonne image
        add_filter('wp_get_attachment_image_attributes', [$this, 'add_fetchpriority'], 20);
    }

    /**
     * Calcule l'URL de l'image LCP pour le contexte actuel
     */
    public function calculate_lcp_urls()
    {
        $m = '';
        $d = '';

        $mapping = PC_Performance_Config::get_key('lcp_acf_mapping');

        // Fiches standards (Logement, Expérience, Destination, Page)
        if (is_singular(array_keys($mapping))) {
            $type = get_post_type();
            $d = PC_Resource_Helper::get_acf_image_url($mapping[$type]['desktop']);
            $m = PC_Resource_Helper::get_acf_image_url($mapping[$type]['mobile']);

            // Fallback spécifique pour les destinations
            if (empty($d) && is_singular('destination')) {
                $thumb = get_the_post_thumbnail_url(get_queried_object_id(), 'full');
                if ($thumb) $d = PC_Url_Helper::resolve_url($thumb);
            }
        }
        // Page de recherche Logements (AVEC MISE EN CACHE TRANSIENT)
        elseif (is_page('recherche-de-logements')) {
            $ville = !empty($_GET['ville']) ? sanitize_text_field($_GET['ville']) : 'all';
            $transient_key = 'pc_lcp_log_thumb_' . md5($ville);
            $thumb_url = get_transient($transient_key);

            if ($thumb_url === false) {
                $args = [
                    'post_type'      => ['logement', 'villa', 'appartement'],
                    'posts_per_page' => 1,
                    'post_status'    => 'publish',
                    'fields'         => 'ids',
                    'meta_query'     => [],
                    'tax_query'      => [],
                ];
                if ($ville !== 'all') {
                    $args['tax_query'][] = ['taxonomy' => 'ville', 'field' => 'slug', 'terms' => $ville];
                }
                $q = new WP_Query($args);

                $thumb_url = '';
                if ($q->have_posts() && has_post_thumbnail($q->posts[0])) {
                    $thumb_url = get_the_post_thumbnail_url($q->posts[0], 'large');
                }
                wp_reset_postdata();

                // On met en cache pour 12 heures
                set_transient($transient_key, $thumb_url, 12 * HOUR_IN_SECONDS);
            }

            if ($thumb_url) {
                $d = PC_Url_Helper::resolve_url($thumb_url);
                $m = $d;
            }
        }
        // Page de recherche Expériences (AVEC MISE EN CACHE TRANSIENT)
        elseif (is_page('recherche-dexperiences')) {
            $cat = !empty($_GET['categorie']) ? sanitize_text_field($_GET['categorie']) : 'all';
            $transient_key = 'pc_lcp_exp_thumb_' . md5($cat);
            $thumb_url = get_transient($transient_key);

            if ($thumb_url === false) {
                $args = [
                    'post_type'      => 'experience',
                    'posts_per_page' => 1,
                    'post_status'    => 'publish',
                    'fields'         => 'ids',
                ];
                if ($cat !== 'all') {
                    $args['tax_query'] = [['taxonomy' => 'categorie_experience', 'field' => 'slug', 'terms' => $cat]];
                }
                $q = new WP_Query($args);

                $thumb_url = '';
                if ($q->have_posts() && has_post_thumbnail($q->posts[0])) {
                    $thumb_url = get_the_post_thumbnail_url($q->posts[0], 'medium_large');
                }
                wp_reset_postdata();

                set_transient($transient_key, $thumb_url, 12 * HOUR_IN_SECONDS);
            }

            if ($thumb_url) {
                $d = PC_Url_Helper::resolve_url($thumb_url);
                $m = $d;
            }
        }
        // Articles de blog
        elseif (is_single()) {
            $thumb = get_the_post_thumbnail_url(get_queried_object_id(), 'full');
            $d = $thumb ? PC_Url_Helper::resolve_url($thumb) : '';
        }

        // Fallback Mobile -> Desktop
        if (empty($m) && !empty($d) && PC_Performance_Config::get_key('fallback_mobile_to_desktop')) {
            $m = $d;
        }

        $this->lcp_urls = ['mobile' => $m, 'desktop' => $d];
    }

    /**
     * Retourne les URLs LCP calculées
     */
    public function get_urls()
    {
        return $this->lcp_urls;
    }

    /**
     * Injecte l'attribut fetchpriority="high" sur la bonne balise <img>
     */
    public function add_fetchpriority($attr)
    {
        if (empty($attr['src']) || (empty($this->lcp_urls['mobile']) && empty($this->lcp_urls['desktop']))) return $attr;

        $u = $attr['src'];
        $is_lcp = false;

        foreach (['mobile', 'desktop'] as $k) {
            if (!empty($this->lcp_urls[$k]) && basename(parse_url($this->lcp_urls[$k], PHP_URL_PATH)) === basename(parse_url($u, PHP_URL_PATH))) {
                $is_lcp = true;
                break;
            }
        }

        if ($is_lcp) {
            $attr['loading']       = 'eager';
            $attr['decoding']      = 'async';
            $attr['fetchpriority'] = 'high';
        }

        return $attr;
    }
}
