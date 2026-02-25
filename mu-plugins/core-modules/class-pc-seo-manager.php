<?php
// Fichier : mu-plugins/core-modules/class-pc-seo-manager.php

if (!defined('ABSPATH')) {
    exit;
}

class PC_SEO_Manager
{

    // === PROPRIÉTÉS ===
    private static $instance = null;

    // === INITIALISATION (Singleton) ===
    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->init_hooks();
    }

    public function init_hooks()
    {
        // --- 1. Admin : Colonne Indexation ---
        if (is_admin()) {
            $this->setup_admin_columns();
        }

        // --- 2. Front-end : Meta Robots ---
        add_filter('wp_robots', [$this, 'filter_wp_robots'], 999);

        // --- 3. Sitemaps XML ---
        add_filter('wp_sitemaps_enabled', '__return_true', 99);
        add_filter('wp_sitemaps_add_provider', [$this, 'remove_core_sitemap_providers'], 10, 2);
        add_filter('wp_sitemaps_taxonomies', '__return_empty_array', 10, 1);
        add_filter('wp_sitemaps_post_types', [$this, 'filter_sitemap_post_types'], 10, 1);
        add_filter('wp_sitemaps_posts_query_args', [$this, 'exclude_posts_from_sitemap'], 10, 2);

        // --- 4. HTTP 410 ---
        add_action('template_redirect', [$this, 'handle_410_redirects'], 2);

        // --- 5. Canonical Guard ---
        add_action('init', [$this, 'remove_plugin_canonicals'], 20);
        add_action('wp_head', [$this, 'output_safe_canonical'], 999);

        // --- 6. HTML Sitemap Shortcode ---
        add_shortcode('pc_html_sitemap', [$this, 'render_html_sitemap_shortcode']);
        add_action('save_post', [$this, 'clear_html_sitemap_cache']);

        // Bonus JSON-LD ItemList sur /plan-du-site/
        add_action('wp_head', [$this, 'output_sitemap_jsonld'], 90);
    }

    // ==========================================
    // 1. ADMIN : COLONNE INDEXATION
    // ==========================================
    private function setup_admin_columns()
    {
        $post_types = ['page', 'post', 'villa', 'appartement', 'experience', 'destination'];

        foreach ($post_types as $pt) {
            add_filter("manage_{$pt}_posts_columns", [$this, 'add_indexation_column']);
            add_action("manage_{$pt}_posts_custom_column", [$this, 'render_indexation_column'], 10, 2);
            add_filter("manage_edit-{$pt}_sortable_columns", [$this, 'make_column_sortable']);
            add_action('pre_get_posts', [$this, 'handle_column_sorting']);
        }
    }

    public function add_indexation_column($cols)
    {
        $out = array();
        $inserted = false;
        foreach ($cols as $k => $v) {
            $out[$k] = $v;
            if ($k === 'title') {
                $out['pc_indexation'] = 'Indexation';
                $inserted = true;
            }
        }
        if (!$inserted) {
            $out['pc_indexation'] = 'Indexation';
        }
        return $out;
    }

    public function render_indexation_column($col, $post_id)
    {
        if ($col !== 'pc_indexation') return;

        $robots = function_exists('pcseo_get_meta') ? (string) pcseo_get_meta($post_id, 'meta_robots') : '';
        $exclude = function_exists('pcseo_get_meta') ? pcseo_get_meta($post_id, 'exclude_sitemap') : '';
        $gone  = function_exists('pcseo_get_meta') ? pcseo_get_meta($post_id, 'http_410') : '';

        $robots_norm = strtolower(str_replace(' ', '', $robots));
        $has_robots_set = function_exists('pcseo_meta_exists') ? pcseo_meta_exists($post_id, 'meta_robots') : false;

        $is_noindex_fn = function_exists('pcseo_is_noindex') ? 'pcseo_is_noindex' : function ($val) {
            return is_string($val) && stripos($val, 'noindex') !== false;
        };

        if (pcseo_truthy($exclude) && !$is_noindex_fn($robots_norm)) {
            $effective = 'noindex,follow';
        } elseif ($has_robots_set && $robots_norm !== '') {
            $effective = $robots_norm;
        } else {
            $effective = 'index,follow';
        }

        $badge = function ($txt, $type = 'muted') {
            $bg = ($type === 'ok') ? '#E6FFE8' : (($type === 'warn') ? '#FFF7E6' : (($type === 'err') ? '#FFECEC' : '#F3F4F6'));
            $fg = ($type === 'ok') ? '#0E7A2E' : (($type === 'warn') ? '#8A5100' : (($type === 'err') ? '#9B1C1C' : '#374151'));
            return '<span style="display:inline-block;padding:.2em .5em;border-radius:6px;background:' . $bg . ';color:' . $fg . ';font-size:12px;">' . $txt . '</span>';
        };

        $is_noindex = (strpos($effective, 'noindex') === 0);
        echo $badge($effective, $is_noindex ? 'warn' : 'ok') . ' ';
        if (pcseo_truthy($exclude)) echo $badge('excl. sitemap', 'warn') . ' ';
        if (pcseo_truthy($gone))    echo $badge('410', 'err') . ' ';
    }

    public function make_column_sortable($cols)
    {
        $cols['pc_indexation'] = 'pc_indexation';
        return $cols;
    }

    public function handle_column_sorting($q)
    {
        if (!is_admin() || !$q->is_main_query()) return;
        if ($q->get('orderby') !== 'pc_indexation') return;
    }

    // ==========================================
    // 2. FRONT-END : META ROBOTS
    // ==========================================
    public function filter_wp_robots(array $robots)
    {
        $set_exact = function (array &$robots, string $dir) {
            $robots['max-image-preview'] = null;
            $robots['max-snippet']       = null;
            $robots['max-video-preview'] = null;
            $robots['noarchive']         = null;
            $robots['nosnippet']         = null;

            $dir = strtolower(str_replace(' ', '', $dir));
            $noindex  = strpos($dir, 'noindex')  !== false;
            $nofollow = strpos($dir, 'nofollow') !== false;

            $robots['noindex']  = $noindex ?: null;
            $robots['index']    = $noindex ? null : true;
            $robots['nofollow'] = $nofollow ?: null;
            $robots['follow']   = $nofollow ? null : true;
        };

        if (is_search()) {
            $set_exact($robots, 'noindex,follow');
            return $robots;
        }

        if (is_singular()) {
            $id = get_queried_object_id();
            if ($id) {
                $robots_val = function_exists('pcseo_get_meta') ? (string) pcseo_get_meta($id, 'meta_robots') : '';
                $robots_val = strtolower(str_replace(' ', '', trim($robots_val)));
                $has_robots_set = function_exists('pcseo_meta_exists') ? pcseo_meta_exists($id, 'meta_robots') : false;

                $exclude_raw = function_exists('pcseo_get_meta') ? pcseo_get_meta($id, 'exclude_sitemap') : '';
                $exclude     = function_exists('pcseo_truthy') ? pcseo_truthy($exclude_raw) : false;

                $is_noindex_fn = function_exists('pcseo_is_noindex') ? 'pcseo_is_noindex' : function ($val) {
                    return is_string($val) && stripos($val, 'noindex') !== false;
                };

                if ($exclude && !$is_noindex_fn($robots_val)) {
                    $set_exact($robots, 'noindex,follow');
                    return $robots;
                }

                if ($has_robots_set && $robots_val !== '') {
                    $set_exact($robots, $robots_val);
                    return $robots;
                }

                $set_exact($robots, 'index,follow');
                return $robots;
            }
        }
        return $robots;
    }

    // ==========================================
    // 3. SITEMAPS XML
    // ==========================================
    public function remove_core_sitemap_providers($provider, $name)
    {
        if ($name === 'users' || $name === 'taxonomies') return false;
        return $provider;
    }

    public function filter_sitemap_post_types($post_types)
    {
        $whitelist = ['page', 'post', 'villa', 'appartement', 'experience', 'destination'];
        $keep = array_intersect_key($post_types, array_flip($whitelist));
        return !empty($keep) ? $keep : $post_types;
    }

    public function exclude_posts_from_sitemap($args, $post_type)
    {
        if (!function_exists('pcseo_field_prefix_for')) return $args;

        $prefix = pcseo_field_prefix_for($post_type);
        if (!$prefix) return $args;

        $truthy = ['1', 'yes', 'on', 'true', 'vrai', 'oui'];
        $keys = ["{$prefix}_exclude_sitemap", 'pc_exclude_sitemap'];

        $mq = ['relation' => 'AND'];
        foreach ($keys as $key) {
            $mq[] = [
                'relation' => 'OR',
                ['key' => $key, 'compare' => 'NOT EXISTS'],
                ['key' => $key, 'value' => $truthy, 'compare' => 'NOT IN'],
            ];
        }

        if (!empty($args['meta_query']) && is_array($args['meta_query'])) {
            $args['meta_query'] = array_merge(['relation' => 'AND'], $args['meta_query'], [$mq]);
        } else {
            $args['meta_query'] = $mq;
        }

        return $args;
    }

    // ==========================================
    // 4. HTTP 410
    // ==========================================
    public function handle_410_redirects()
    {
        if (is_admin() || is_preview() || is_customize_preview()) return;
        if (!is_singular()) return;

        $id = get_queried_object_id();
        if (!$id) return;

        $gone = function_exists('pcseo_get_meta') ? pcseo_get_meta($id, 'http_410') : '';
        if (!function_exists('pcseo_truthy') || !pcseo_truthy($gone)) return;

        status_header(410);
        header('X-Robots-Tag: noindex, follow', true);
        nocache_headers();

        add_filter('wp_robots', function (array $robots) {
            $robots['max-image-preview'] = null;
            $robots['max-snippet']       = null;
            $robots['max-video-preview'] = null;
            $robots['noarchive']         = null;
            $robots['nosnippet']         = null;
            $robots['noindex']  = true;
            $robots['index']    = null;
            $robots['nofollow'] = null;
            $robots['follow']   = true;
            return $robots;
        }, 999);

        set_query_var('pcseo_is_410', true);
        set_query_var('pcseo_410_message', 'Cette page n’existe plus ou a été supprimée.');

        $tpl = '';
        if (function_exists('locate_template')) {
            $tpl = locate_template(['410.php', 'gone.php', '404.php'], false, false);
        }

        if ($tpl) {
            include $tpl;
        } else {
            $this->render_fallback_410();
        }
        exit;
    }

    private function render_fallback_410()
    {
        header('Content-Type: text/html; charset=utf-8');
?>
        <!doctype html>
        <html lang="fr">

        <head>
            <meta charset="utf-8">
            <title>410 — Cette page n’existe plus</title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <style>
                body {
                    font-family: system-ui, -apple-system, Segoe UI, sans-serif;
                    margin: 0;
                    background: #fff;
                    color: #111;
                }

                .wrap {
                    max-width: 740px;
                    margin: 12vh auto;
                    padding: 0 24px;
                    text-align: center
                }

                h1 {
                    font-size: clamp(28px, 4vw, 36px);
                    margin: 0 0 .5em
                }

                p {
                    font-size: clamp(16px, 2.2vw, 18px);
                    color: #444;
                    margin: .5em 0
                }

                a.btn {
                    display: inline-block;
                    margin-top: 1em;
                    padding: .7em 1.1em;
                    border-radius: 8px;
                    border: 1px solid #e5e7eb;
                    text-decoration: none
                }

                a.btn:hover {
                    background: #f9fafb
                }
            </style>
        </head>

        <body>
            <main class="wrap">
                <h1>Cette page n’existe plus</h1>
                <p>Le contenu demandé a été retiré ou n’est plus disponible.</p>
                <p><a class="btn" href="<?php echo esc_url(home_url('/')); ?>">← Retour à l’accueil</a></p>
            </main>
        </body>

        </html>
<?php
    }

    // ==========================================
    // 5. CANONICAL GUARD
    // ==========================================
    private function cano_should_run()
    {
        if (is_admin()) return false;
        if (defined('REST_REQUEST') && REST_REQUEST) return false;
        if (defined('DOING_AJAX')   && DOING_AJAX)   return false;

        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        if (is_feed()) return false;
        if ($uri && (strpos($uri, '/wp-sitemap') !== false)) return false;
        if ($uri && (strpos($uri, '/robots.txt') !== false)) return false;

        return true;
    }

    private function is_search_like()
    {
        if (is_search()) return true;

        $paths = ['/recherche-experiences/', '/recherche-logements/', '/recherche/'];
        $req = parse_url(home_url(add_query_arg(null, null)), PHP_URL_PATH);
        foreach ($paths as $p) {
            if (stripos($req, rtrim($p, '/') . '/') !== false || rtrim($req, '/') === rtrim($p, '/')) {
                return true;
            }
        }
        return false;
    }

    private function compute_canonical_url($strip_query = false)
    {
        if (is_404()) return '';
        if (get_query_var('pcseo_is_410')) return '';

        if (is_singular()) {
            $url = get_permalink();
        } elseif (is_home() && !is_front_page()) {
            $url = get_permalink(get_option('page_for_posts'));
        } elseif (is_front_page()) {
            $url = home_url('/');
        } elseif (is_post_type_archive()) {
            $url = get_post_type_archive_link(get_query_var('post_type') ?: get_post_type());
        } elseif (is_tax() || is_category() || is_tag()) {
            $term = get_queried_object();
            $url  = ($term && !is_wp_error($term)) ? get_term_link($term) : '';
        } else {
            $url = home_url(add_query_arg(null, null));
        }
        if (empty($url) || is_wp_error($url)) return '';

        $paged = max(1, (int)get_query_var('paged'), (int)get_query_var('page'));
        if (!empty($_GET)) {
            foreach ($_GET as $key => $value) {
                if (strpos($key, 'e-page-') === 0 && is_numeric($value) && (int)$value > 1) {
                    $paged = (int) $value;
                    $url = add_query_arg($key, $paged, $url);
                    break;
                }
            }
        }

        if ($paged > 1 && strpos($url, 'e-page-') === false) {
            $url = get_pagenum_link($paged);
        }

        $kill = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id', 'gclid', 'fbclid', 'msclkid', 'dclid', 'igshid', '_ga', '_gl', '_ke', 'vero_id', 'mkt_tok', 'mc_cid', 'mc_eid', 'ref', 'ref_', 'aff', 'affiliate', 'utm_referrer', 'spm', 'si', 'li_fat_id'];

        $url = remove_query_arg($kill, $url);
        if ($strip_query) {
            $url = strtok($url, '?');
        }

        $url = set_url_scheme($url);
        $url = user_trailingslashit($url);

        $home_host = parse_url(home_url(), PHP_URL_HOST);
        $url_host  = parse_url($url, PHP_URL_HOST);
        if ($home_host && $url_host && strcasecmp($home_host, $url_host) !== 0) {
            $parts = wp_parse_url($url);
            $path  = $parts['path'] ?? '/';
            $query = $parts['query'] ?? '';
            $url = set_url_scheme('http://' . $home_host . $path . ($query ? '?' . $query : ''));
            $url = user_trailingslashit($url);
        }
        return $url;
    }

    public function remove_plugin_canonicals()
    {
        if (!$this->cano_should_run()) return;

        remove_action('wp_head', 'rel_canonical');

        if (has_action('wp_head', 'rank_math/frontend/canonical')) {
            remove_action('wp_head', 'rank_math/frontend/canonical', 20);
        }
        if (has_action('seopress_pro_head', 'seopress_advanced_advanced_robots_canonical')) {
            remove_action('seopress_pro_head', 'seopress_advanced_advanced_robots_canonical', 10);
        }
    }

    public function output_safe_canonical()
    {
        if (!$this->cano_should_run()) return;

        if ($this->is_search_like()) {
            add_filter('wp_robots', function (array $r) {
                $r['max-image-preview'] = null;
                $r['max-snippet']       = null;
                $r['max-video-preview'] = null;
                $r['noarchive']         = null;
                $r['nosnippet']         = null;
                $r['noindex']  = true;
                $r['index']    = null;
                $r['nofollow'] = null;
                $r['follow']   = true;
                return $r;
            }, 999);
            $url = $this->compute_canonical_url(true);
            if ($url) echo "\n<link rel=\"canonical\" href=\"" . esc_url($url) . "\" />\n";
            return;
        }

        $url = $this->compute_canonical_url(false);
        if ($url) {
            echo "\n<link rel=\"canonical\" href=\"" . esc_url($url) . "\" />\n";
        }
    }

    // ==========================================
    // 6. HTML SITEMAP SHORTCODE
    // ==========================================
    public function render_html_sitemap_shortcode($atts = [])
    {
        if (function_exists('pcseo_build_html_sitemap')) {
            $res = pcseo_build_html_sitemap($atts);
            return $res['html'];
        }
        return '';
    }

    public function clear_html_sitemap_cache()
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_pcseo_html_sitemap_%' OR option_name LIKE '_transient_timeout_pcseo_html_sitemap_%'");
    }

    public function output_sitemap_jsonld()
    {
        if (!is_page('plan-du-site')) return;
        if (!function_exists('pcseo_build_html_sitemap')) return;

        $res  = pcseo_build_html_sitemap([
            'show'           => 'pages,destinations,logements,experiences,articles',
            'depth'          => 2,
            'limit_posts'    => 100,
        ]);
        $urls = $res['urls'];
        if (empty($urls)) return;

        $itemList = [
            '@context'        => 'https://schema.org',
            '@type'           => 'ItemList',
            'name'            => 'Plan du site',
            'itemListOrder'   => 'https://schema.org/ItemListOrderAscending',
            'numberOfItems'   => count($urls),
            'itemListElement' => [],
        ];
        $pos = 1;
        foreach ($urls as $u) {
            $itemList['itemListElement'][] = [
                '@type'    => 'ListItem',
                'position' => $pos++,
                'url'      => $u,
            ];
        }
        echo '<script type="application/ld+json">' . wp_json_encode($itemList, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
    }
}
