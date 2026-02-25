<?php
// Fichier : mu-plugins/core-modules/class-pc-social-manager.php

if (!defined('ABSPATH')) {
    exit;
}

class PC_Social_Manager
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
        $this->define_legacy_helpers();
    }

    public function init_hooks()
    {
        // 1. Balises Open Graph & Twitter (priorité 48)
        add_action('wp_head', [$this, 'output_social_cards'], 48);

        // 2. Balise <title> du document (priorité 20)
        add_filter('pre_get_document_title', [$this, 'filter_document_title'], 20);

        // 3. Balise <meta name="description"> (priorité 7)
        add_action('wp_head', [$this, 'output_meta_description'], 7);

        // 4. Audit SEO (Administration)
        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_audit_menu']);
        }
    }

    // ==========================================
    // HELPERS INTERNES
    // ==========================================
    private function get_option($key)
    {
        if (function_exists('get_field')) {
            $v = get_field($key, 'option');
            if ($v !== null && $v !== false && $v !== '') return $v;
        }
        return get_option($key, '');
    }

    private function make_absolute_url($url)
    {
        if (!$url) return '';
        if (strpos($url, '//') === 0) return (is_ssl() ? 'https:' : 'http:') . $url;
        if (parse_url($url, PHP_URL_SCHEME)) return $url;
        if ($url[0] === '/') return home_url($url);
        return home_url('/' . $url);
    }

    private function plain_text($html, $len = 180)
    {
        $t = trim(wp_strip_all_tags((string)$html));
        $t = preg_replace('/\s+/', ' ', $t);
        if ($len && mb_strlen($t) > $len) $t = rtrim(mb_substr($t, 0, $len - 1)) . '…';
        return $t;
    }

    private function get_seo_title($post_id)
    {
        $pt = get_post_type($post_id);
        $acf_key = '';

        switch ($pt) {
            case 'page':
                $acf_key = 'pc_meta_title';
                break;
            case 'villa':
            case 'appartement':
                $acf_key = 'meta_titre';
                break;
            case 'experience':
                $acf_key = 'exp_meta_titre';
                break;
            case 'destination':
                $acf_key = 'dest_meta_title';
                break;
        }

        if ($acf_key && function_exists('get_field')) {
            $v = get_field($acf_key, $post_id);
            if (!empty($v)) return $this->plain_text($v, 300);
        }

        if ($acf_key && function_exists('pcseo_get_meta')) {
            $suffix = preg_replace('~^(pc_|exp_|dest_|post_)~', '', $acf_key);
            $v = pcseo_get_meta($post_id, $suffix);
            if (!empty($v)) return $this->plain_text($v, 300);
        }
        return '';
    }

    private function get_seo_description($post_id)
    {
        $pt = get_post_type($post_id);
        $acf_key = '';

        switch ($pt) {
            case 'page':
                $acf_key = 'pc_meta_description';
                break;
            case 'villa':
            case 'appartement':
                $acf_key = 'meta_description';
                break;
            case 'experience':
                $acf_key = 'exp_meta_description';
                break;
            case 'destination':
                $acf_key = 'dest_meta_description';
                break;
            case 'post':
                $acf_key = 'post_og_description';
                break;
        }

        if ($acf_key && function_exists('get_field')) {
            $v = get_field($acf_key, $post_id);
            if (!empty($v)) return $this->plain_text($v, 180);
        }

        if ($acf_key && function_exists('pcseo_get_meta')) {
            $suffix = preg_replace('~^(pc_|exp_|dest_|post_)~', '', $acf_key);
            $v = pcseo_get_meta($post_id, $suffix);
            if (!empty($v)) return $this->plain_text($v, 180);
        }

        $ex = get_post_field('post_excerpt', $post_id, 'raw');
        if (!empty($ex)) return $this->plain_text($ex, 180);

        return $this->plain_text(get_the_title($post_id), 180);
    }

    private function pick_og_image($post_id)
    {
        $pt = get_post_type($post_id);
        $candidates = [];

        if ($pt === 'villa' || $pt === 'appartement') {
            if (function_exists('pcseo_get_meta')) {
                if ($hero = pcseo_get_meta($post_id, 'hero_desktop_url')) $candidates[] = $hero;
                if ($gallery = pcseo_get_meta($post_id, 'seo_gallery_urls')) {
                    foreach (preg_split('/\r\n|\r|\n/', (string)$gallery) as $u) {
                        if (trim($u)) $candidates[] = trim($u);
                    }
                }
            }
        } elseif ($pt === 'experience') {
            $img = function_exists('get_field') ? get_field('exp_hero_desktop', $post_id) : (function_exists('pcseo_get_meta') ? pcseo_get_meta($post_id, 'hero_desktop') : '');
            if (is_array($img) && !empty($img['url'])) $candidates[] = $img['url'];
            elseif (is_numeric($img)) $candidates[] = wp_get_attachment_url($img);
        } elseif ($pt === 'destination') {
            $img = function_exists('get_field') ? get_field('dest_hero_desktop', $post_id) : (function_exists('pcseo_get_meta') ? pcseo_get_meta($post_id, 'hero_desktop') : '');
            if (is_array($img) && !empty($img['url'])) $candidates[] = $img['url'];
            elseif (is_numeric($img)) $candidates[] = wp_get_attachment_url($img);
        }

        if ($thumb = get_the_post_thumbnail_url($post_id, 'full')) $candidates[] = $thumb;

        $org_logo = function_exists('get_field') ? get_field('pc_org_logo', 'option') : '';
        if (is_array($org_logo) && !empty($org_logo['url'])) $candidates[] = $org_logo['url'];
        elseif (is_numeric($org_logo)) $candidates[] = wp_get_attachment_url($org_logo);

        foreach ($candidates as $u) {
            $abs = $this->make_absolute_url($u);
            if ($abs) return $abs;
        }
        return '';
    }

    // ==========================================
    // 1. OPEN GRAPH & TWITTER CARDS
    // ==========================================
    public function output_social_cards()
    {
        if (is_admin()) return;
        if (!is_singular() && !is_home() && !is_front_page() && !is_post_type_archive() && !is_tax() && !is_category() && !is_tag() && !is_search()) return;

        $title = $desc = $image = $url = $type = '';
        $site_name = $this->get_option('pc_org_name') ?: get_bloginfo('name');

        if (is_front_page()) {
            $type = 'website';
            $url  = home_url('/');
            if ($id = get_queried_object_id()) {
                $title = $this->get_seo_title($id) ?: trim(wp_get_document_title());
                $desc  = $this->plain_text($this->get_seo_description($id), 200);
            } else {
                $title = trim(wp_get_document_title());
                $desc  = $this->plain_text(get_bloginfo('description'), 200);
            }
            $image = $this->pick_og_image(get_queried_object_id() ?: 0);
        } elseif (is_home() && !is_front_page()) {
            $type = 'website';
            $pid  = get_option('page_for_posts');
            $url  = get_permalink($pid);
            $title = "Vacances en Guadeloupe - votre Magazine Prestige Caraïbes";
            $desc = "Retrouvez tous les Conseils pratiques de professionnels pour bien préparer et réussir vos vacances en Guadeloupe";
            $image = $pid ? $this->pick_og_image($pid) : '';
        } elseif (is_singular()) {
            $id = get_queried_object_id();
            $type = 'article';
            $title = $this->get_seo_title($id) ?: get_the_title($id);
            $desc  = $this->plain_text($this->get_seo_description($id), 200);
            $image = $this->pick_og_image($id);
            $url   = get_permalink($id);
        } elseif (is_search()) {
            $type  = 'website';
            $title = 'Recherche';
            $desc  = $this->plain_text(get_bloginfo('description'), 200);
            $url   = $this->make_absolute_url(remove_query_arg(['s', 'paged'], home_url(parse_url(add_query_arg(null, null), PHP_URL_PATH))));
        } else {
            $type  = 'website';
            $title = trim(wp_get_document_title());
            $desc  = $this->plain_text(get_bloginfo('description'), 200);
            $url   = $this->make_absolute_url(home_url(add_query_arg(null, null)));
        }

        static $pcseo_og_done = false;
        if ($pcseo_og_done) return;
        $pcseo_og_done = true;

        echo "\n\n";
        $locale = str_replace('-', '_', get_locale() ?: 'fr_FR');
        echo '<meta property="og:locale" content="' . esc_attr($locale) . '" />' . "\n";
        if ($site_name) echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '" />' . "\n";
        echo '<meta property="og:type" content="' . esc_attr($type ?: 'website') . '" />' . "\n";
        if ($url)   echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
        if ($title) echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
        if ($desc)  echo '<meta property="og:description" content="' . esc_attr($desc) . '" />' . "\n";
        if ($image) echo '<meta property="og:image" content="' . esc_url($image) . '" />' . "\n";

        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        if ($title) echo '<meta name="twitter:title" content="' . esc_attr($title) . '" />' . "\n";
        if ($desc)  echo '<meta name="twitter:description" content="' . esc_attr($desc) . '" />' . "\n";
        if ($image) echo '<meta name="twitter:image" content="' . esc_url($image) . '" />' . "\n";
    }

    // ==========================================
    // 2. DOCUMENT TITLE
    // ==========================================
    public function filter_document_title($title)
    {
        if (is_home() && !is_front_page()) {
            return "Vacances en Guadeloupe - votre Magazine Prestige Caraïbes";
        }
        if (is_singular()) {
            $id = get_queried_object_id();
            if ($id && ($custom = $this->get_seo_title($id))) {
                return $custom;
            }
        }
        return $title;
    }

    // ==========================================
    // 3. META DESCRIPTION
    // ==========================================
    public function output_meta_description()
    {
        if (is_home() && !is_front_page()) {
            $desc = "Retrouvez tous les Conseils pratiques de professionnels pour bien préparer et réussir vos vacances en Guadeloupe";
            echo "\n<meta name=\"description\" content=\"" . esc_attr($desc) . "\" />\n";
            return;
        }
        if (is_singular()) {
            $id = get_queried_object_id();
            if (!$id || is_404() || get_query_var('pcseo_is_410')) return;

            $desc = $this->get_seo_description($id);
            if (!$desc) return;

            static $pcseo_desc_done = false;
            if ($pcseo_desc_done) return;
            $pcseo_desc_done = true;

            echo "\n<meta name=\"description\" content=\"" . esc_attr($desc) . "\" />\n";
        }
    }

    // ==========================================
    // 4. AUDIT SEO (ADMIN)
    // ==========================================
    public function add_audit_menu()
    {
        add_menu_page('SEO Audit PC', 'SEO Audit PC', 'manage_options', 'pcseo-audit', [$this, 'render_audit_page'], 'dashicons-visibility', 80);
    }

    public function render_audit_page()
    {
        if (!current_user_can('manage_options')) return;

        if (!empty($_GET['pcseo_export']) && check_admin_referer('pcseo_export_csv')) {
            $this->export_audit_csv();
            exit;
        }

        $types = ['page', 'post', 'villa', 'appartement', 'experience', 'destination'];
        $stats = [];
        $rows  = [];

        foreach ($types as $pt) {
            $q = new WP_Query(['post_type' => $pt, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true]);
            $noindex = $exclude = $gone = 0;

            foreach ($q->posts as $pid) {
                $robots  = function_exists('pcseo_get_meta') ? (string) pcseo_get_meta($pid, 'meta_robots') : '';
                $exclude_b = function_exists('pcseo_truthy') && function_exists('pcseo_get_meta') ? pcseo_truthy(pcseo_get_meta($pid, 'exclude_sitemap')) : false;
                $gone_b    = function_exists('pcseo_truthy') && function_exists('pcseo_get_meta') ? pcseo_truthy(pcseo_get_meta($pid, 'http_410')) : false;

                $robots_norm = strtolower(str_replace(' ', '', $robots));
                $has_robots_set = function_exists('pcseo_meta_exists') ? pcseo_meta_exists($pid, 'meta_robots') : false;

                $is_noindex_fn = function_exists('pcseo_is_noindex') ? 'pcseo_is_noindex' : function ($val) {
                    return is_string($val) && stripos($val, 'noindex') !== false;
                };

                if ($exclude_b && !$is_noindex_fn($robots_norm)) {
                    $effective = 'noindex,follow';
                } elseif ($has_robots_set && $robots_norm !== '') {
                    $effective = $robots_norm;
                } else {
                    $effective = 'index,follow';
                }

                if ($is_noindex_fn($effective)) $noindex++;
                if ($exclude_b) $exclude++;
                if ($gone_b)    $gone++;

                $rows[] = ['type' => $pt, 'id' => $pid, 'title' => get_the_title($pid), 'url' => get_permalink($pid), 'robots' => $effective, 'exclude' => $exclude_b ? '1' : '', '410' => $gone_b ? '1' : ''];
            }
            $stats[$pt] = ['noindex' => $noindex, 'exclude' => $exclude, 'gone' => $gone, 'total' => count($q->posts)];
        }

        echo '<div class="wrap"><h1>SEO Audit — Prestige Caraïbes</h1>';
        echo '<h2>Compteurs par type</h2>';
        echo '<table class="widefat striped"><thead><tr><th>Type</th><th>Total</th><th>Noindex</th><th>Excl. sitemap</th><th>410</th></tr></thead><tbody>';
        foreach ($stats as $pt => $s) {
            printf('<tr><td>%s</td><td>%d</td><td>%d</td><td>%d</td><td>%d</td></tr>', esc_html($pt), $s['total'], $s['noindex'], $s['exclude'], $s['gone']);
        }
        echo '</tbody></table>';

        $export_url = wp_nonce_url(admin_url('admin.php?page=pcseo-audit&pcseo_export=1'), 'pcseo_export_csv');
        echo '<p><a href="' . esc_url($export_url) . '" class="button button-primary">Exporter CSV</a></p>';

        echo '<h2>Détails (premiers éléments)</h2>';
        echo '<table class="widefat striped"><thead><tr><th>Type</th><th>ID</th><th>Titre</th><th>URL</th><th>Robots</th><th>Excl.</th><th>410</th></tr></thead><tbody>';
        $i = 0;
        foreach ($rows as $r) {
            if (++$i > 200) break;
            printf('<tr><td>%s</td><td>%d</td><td>%s</td><td><a href="%s" target="_blank">%s</a></td><td>%s</td><td>%s</td><td>%s</td></tr>', esc_html($r['type']), $r['id'], esc_html($r['title']), esc_url($r['url']), esc_html($r['url']), esc_html($r['robots']), esc_html($r['exclude']), esc_html($r['410']));
        }
        echo '</tbody></table></div>';
    }

    private function export_audit_csv()
    {
        $types = ['page', 'post', 'villa', 'appartement', 'experience', 'destination'];
        $out = fopen('php://output', 'w');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=pcseo-audit.csv');

        fputcsv($out, ['type', 'id', 'title', 'url', 'robots', 'exclude', '410']);

        foreach ($types as $pt) {
            $q = new WP_Query(['post_type' => $pt, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true]);
            foreach ($q->posts as $pid) {
                $robots  = function_exists('pcseo_get_meta') ? (string) pcseo_get_meta($pid, 'meta_robots') : '';
                $exclude_b = function_exists('pcseo_truthy') && function_exists('pcseo_get_meta') ? pcseo_truthy(pcseo_get_meta($pid, 'exclude_sitemap')) : false;
                $gone_b    = function_exists('pcseo_truthy') && function_exists('pcseo_get_meta') ? pcseo_truthy(pcseo_get_meta($pid, 'http_410')) : false;

                $robots_norm = strtolower(str_replace(' ', '', $robots));
                $has_robots_set = function_exists('pcseo_meta_exists') ? pcseo_meta_exists($pid, 'meta_robots') : false;

                $is_noindex_fn = function_exists('pcseo_is_noindex') ? 'pcseo_is_noindex' : function ($val) {
                    return is_string($val) && stripos($val, 'noindex') !== false;
                };

                if ($exclude_b && !$is_noindex_fn($robots_norm)) {
                    $effective = 'noindex,follow';
                } elseif ($has_robots_set && $robots_norm !== '') {
                    $effective = $robots_norm;
                } else {
                    $effective = 'index,follow';
                }

                fputcsv($out, [$pt, $pid, get_the_title($pid), get_permalink($pid), $effective, $exclude_b ? '1' : '', $gone_b ? '1' : '']);
            }
        }
        fclose($out);
    }

    // ==========================================
    // PONTS DE RÉTROCOMPATIBILITÉ (HORS CLASSE)
    // ==========================================
    private function define_legacy_helpers()
    {
        if (!function_exists('pcseo_get_option')) {
            function pcseo_get_option($key)
            {
                return PC_Social_Manager::instance()->get_option($key);
            }
        }
        if (!function_exists('pcseo_abs_url')) {
            function pcseo_abs_url($url)
            {
                return PC_Social_Manager::instance()->make_absolute_url($url);
            }
        }
        if (!function_exists('pcseo_plain')) {
            function pcseo_plain($html, $len = 180)
            {
                return PC_Social_Manager::instance()->plain_text($html, $len);
            }
        }
    }
}
