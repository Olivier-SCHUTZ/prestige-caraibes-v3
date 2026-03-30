<?php
// Fichier : mu-plugins/core-modules/class-pc-seo-helpers.php

if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
 * 1. HELPERS DE LECTURE DE DONNÉES (ACF & METAS)
 * ============================================================ */

if (!function_exists('pcseo_get_field')) {
    function pcseo_get_field($key, $post_id = null, $default = '')
    {
        if (class_exists('PCR_Fields')) {
            $val = PCR_Fields::get($key, $post_id);
            return ($val === null || $val === '') ? $default : $val;
        }
        if ($post_id && is_numeric($post_id)) {
            $val = get_post_meta($post_id, $key, true);
            return ($val === null || $val === '') ? $default : $val;
        }
        return $default;
    }
}

if (!function_exists('pcseo_truthy')) {
    function pcseo_truthy($v)
    {
        $v = strtolower(trim((string)$v));
        return in_array($v, ['1', 'on', 'yes', 'true', 'vrai', 'oui'], true) || $v === '1';
    }
}

if (!function_exists('pcseo_field_prefix_for')) {
    function pcseo_field_prefix_for($post_type)
    {
        switch ($post_type) {
            case 'post':
                return 'post';
            case 'villa':
            case 'appartement':
                return 'log';
            case 'experience':
                return 'exp';
            case 'destination':
            case 'pc_destination':
            case 'destinations':
                return 'dest';
            case 'page':
            default:
                return 'pc';
        }
    }
}

if (!function_exists('pcseo_get_meta')) {
    function pcseo_get_meta($post_id, $suffix)
    {
        $post_id = (int) $post_id;
        if (!$post_id) return '';

        $pt     = get_post_type($post_id);
        $prefix = function_exists('pcseo_field_prefix_for') ? pcseo_field_prefix_for($pt) : 'pc';

        $key_sub        = "{$prefix}_{$suffix}";
        $group          = "{$prefix}_seo_overrides";
        $key_group_sub1 = "{$group}_{$prefix}_{$suffix}";
        $key_group_sub2 = "{$group}_{$suffix}";

        $candidates = [$key_sub, $key_group_sub1, $key_group_sub2];

        foreach ($candidates as $k) {
            if (class_exists('PCR_Fields')) {
                $v = PCR_Fields::get($k, $post_id);
                if ($v !== null && $v !== false && $v !== '') return $v;
            }
            $v = get_post_meta($post_id, $k, true);
            if ($v !== '' && $v !== null) return $v;
        }

        if (class_exists('PCR_Fields')) {
            $grp = PCR_Fields::get($group, $post_id);
            if (is_array($grp)) {
                $sub_name = "{$prefix}_{$suffix}";
                if (array_key_exists($sub_name, $grp) && $grp[$sub_name] !== '') return $grp[$sub_name];
                if (array_key_exists($suffix, $grp) && $grp[$suffix] !== '') return $grp[$suffix];
            }
        }
        return '';
    }
}

if (!function_exists('pcseo_meta_exists')) {
    function pcseo_meta_exists($post_id, $suffix)
    {
        $pt     = get_post_type($post_id);
        $prefix = function_exists('pcseo_field_prefix_for') ? pcseo_field_prefix_for($pt) : 'pc';

        $key_sub        = "{$prefix}_{$suffix}";
        $group          = "{$prefix}_seo_overrides";
        $key_group_sub  = "{$group}_{$prefix}_{$suffix}";
        $key_group_sub2 = "{$group}_{$suffix}";

        foreach ([$key_sub, $key_group_sub, $key_group_sub2] as $k) {
            if (metadata_exists('post', $post_id, $k)) return true;
        }
        return false;
    }
}

/* ============================================================
 * 2. HELPERS D'INDEXATION (ROBOTS)
 * ============================================================ */

if (!function_exists('pcseo_is_noindex')) {
    function pcseo_is_noindex($robots_value)
    {
        return is_string($robots_value) && stripos($robots_value, 'noindex') !== false;
    }
}

// Ponts de compatibilité pour tes anciens shortcodes
if (!function_exists('pc_get_meta_robots')) {
    function pc_get_meta_robots($post_id, $post_type = null)
    {
        return (string) pcseo_get_meta($post_id, 'meta_robots');
    }
}
if (!function_exists('pc_is_excluded_from_sitemap')) {
    function pc_is_excluded_from_sitemap($post_id, $post_type = null)
    {
        return pcseo_truthy(pcseo_get_meta($post_id, 'exclude_sitemap'));
    }
}
if (!function_exists('pc_is_http_410')) {
    function pc_is_http_410($post_id, $post_type = null)
    {
        return pcseo_truthy(pcseo_get_meta($post_id, 'http_410'));
    }
}

/* ============================================================
 * 3. HELPERS ANTI-DOUBLONS (FAQ)
 * ============================================================ */

if (!function_exists('pc_faq_already_printed')) {
    function pc_faq_already_printed()
    {
        return !empty($GLOBALS['pc_faq_printed']);
    }
}
if (!function_exists('pc_mark_faq_printed')) {
    function pc_mark_faq_printed()
    {
        $GLOBALS['pc_faq_printed'] = true;
    }
}

/* ============================================================
 * 4. HELPERS SITEMAP HTML
 * ============================================================ */

if (!function_exists('pcseo_post_is_excluded_from_sitemap')) {
    function pcseo_post_is_excluded_from_sitemap($post)
    {
        if (!$post) return true;
        if ($post->post_status !== 'publish') return true;
        if (!empty($post->post_password)) return true;

        $pid = $post->ID;
        if (pcseo_truthy(pcseo_get_meta($pid, 'http_410'))) return true;
        if (pcseo_is_noindex(pcseo_get_meta($pid, 'meta_robots'))) return true;
        if (pcseo_truthy(pcseo_get_meta($pid, 'exclude_sitemap'))) return true;

        return false;
    }
}

if (!function_exists('pcseo_detect_post_type')) {
    function pcseo_detect_post_type($candidates)
    {
        foreach ($candidates as $pt) if (post_type_exists($pt)) return $pt;
        return null;
    }
}

if (!function_exists('pcseo_get_posts_by_slugs')) {
    function pcseo_get_posts_by_slugs($slugs = [])
    {
        if (empty($slugs)) return [];
        return get_posts([
            'post_type'      => 'page',
            'name__in'       => $slugs,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'post__in',
        ]);
    }
}

// Le générateur principal du Sitemap HTML
if (!function_exists('pcseo_build_html_sitemap')) {
    function pcseo_build_html_sitemap($atts = [])
    {
        $defaults = [
            'show'           => 'pages,destinations,logements,experiences,articles',
            'depth'          => 2,
            'limit_posts'    => 50,
            'exclude_ids'    => '',
            'include_slugs'  => '',
            'exclude_slugs'  => '',
        ];
        $a = shortcode_atts($defaults, $atts, 'pc_html_sitemap');

        $sections      = array_filter(array_map('trim', explode(',', strtolower($a['show']))));
        $depth         = max(1, (int)$a['depth']);
        $limit         = max(1, (int)$a['limit_posts']);
        $exclude_ids   = array_filter(array_map('intval', explode(',', $a['exclude_ids'])));
        $exclude_ids   = array_combine($exclude_ids, $exclude_ids);
        $include_slugs = array_values(array_unique(array_filter(array_map('trim', explode(',', strtolower($a['include_slugs']))))));
        $exclude_slugs = array_values(array_unique(array_filter(array_map('trim', explode(',', strtolower($a['exclude_slugs']))))));

        $key    = 'pcseo_html_sitemap_' . md5(json_encode([$sections, $depth, $limit, $exclude_ids, $include_slugs, $exclude_slugs]));
        $cached = get_transient($key);
        if (is_array($cached) && isset($cached['html'], $cached['urls'])) return $cached;

        $html = [];
        $urls = [];
        $already_listed_ids   = [];
        $already_listed_slugs = [];

        $html[] = '<nav class="pc-html-sitemap" aria-label="Plan du site"><div class="pc-html-sitemap__grid">';

        /* ---- PAGES ---- */
        if (in_array('pages', $sections, true)) {
            $all_pages = get_pages(['sort_column' => 'menu_order,post_title', 'sort_order' => 'ASC', 'post_status' => ['publish']]);
            $by_id = [];
            $childs = [];
            foreach ($all_pages as $p) {
                if (isset($exclude_ids[$p->ID])) continue;
                $slug = sanitize_title($p->post_name);
                if (in_array($slug, $exclude_slugs, true)) continue;
                $by_id[$p->ID] = $p;
                $parent = (int)$p->post_parent;
                $childs[$parent][] = $p->ID;
            }

            $print_tree = function ($parent_id, $level) use (&$print_tree, $childs, $by_id, $depth, &$urls, $exclude_ids, &$already_listed_ids, &$already_listed_slugs, $exclude_slugs) {
                if ($level > $depth || empty($childs[$parent_id])) return '';
                $out = '<ul>';
                foreach ($childs[$parent_id] as $cid) {
                    $p = $by_id[$cid] ?? null;
                    if (!$p) continue;

                    $slug = sanitize_title($p->post_name);
                    if (in_array($slug, $exclude_slugs, true)) continue;
                    if (isset($exclude_ids[$p->ID])) continue;
                    if (pcseo_post_is_excluded_from_sitemap($p)) continue;

                    $link  = get_permalink($p);
                    $title = esc_html(get_the_title($p));
                    $out  .= '<li><a href="' . esc_url($link) . '">' . $title . '</a>';

                    $urls[] = $link;
                    $already_listed_ids[$p->ID]  = true;
                    $already_listed_slugs[$slug] = true;

                    if (!empty($childs[$cid])) $out .= $print_tree($cid, $level + 1);
                    $out .= '</li>';
                }
                $out .= '</ul>';
                return $out;
            };

            $tree = $print_tree(0, 1);
            if ($tree !== '' && $tree !== '<ul></ul>') {
                $html[] = '<section class="pc-html-sitemap__section"><h2>Pages</h2>' . $tree . '</section>';
            }
        }

        /* ---- PAGES MANUELLES ---- */
        if (!empty($include_slugs)) {
            $manual_pages = pcseo_get_posts_by_slugs($include_slugs);
            if (!empty($manual_pages)) {
                $items = [];
                foreach ($manual_pages as $p) {
                    if (!$p || $p->post_type !== 'page') continue;
                    $slug = sanitize_title($p->post_name);
                    if (in_array($slug, $exclude_slugs, true)) continue;
                    if (!empty($already_listed_ids[$p->ID]) || !empty($already_listed_slugs[$slug])) continue;

                    $link = get_permalink($p);
                    $items[$slug] = '<li><a href="' . esc_url($link) . '">' . esc_html(get_the_title($p)) . '</a></li>';
                    $urls[] = $link;
                    $already_listed_ids[$p->ID]  = true;
                    $already_listed_slugs[$slug] = true;
                }
                if (!empty($items)) {
                    $ordered = [];
                    foreach ($include_slugs as $s) if (isset($items[$s])) $ordered[] = $items[$s];
                    if (!empty($ordered)) {
                        $html[] = '<section class="pc-html-sitemap__section"><h2>À propos & infos pratiques</h2><ul>' . implode('', $ordered) . '</ul></section>';
                    }
                }
            }
        }

        /* ---- LOGEMENTS / DESTINATIONS / EXPERIENCES / ARTICLES ---- */
        $taxonomies_to_render = [
            'destinations' => ['type' => 'destination', 'title' => 'Destinations', 'candidates' => ['pc_destination', 'destination']],
            'logements'    => ['type' => 'logement', 'title' => 'Logements', 'candidates' => ['villa', 'appartement', 'logement', 'pc_logement']],
            'experiences'  => ['type' => 'experience', 'title' => 'Expériences', 'candidates' => ['pc_experience', 'experience']],
            'articles'     => ['type' => 'post', 'title' => 'Magazine', 'candidates' => ['post'], 'limit' => $limit]
        ];

        foreach ($taxonomies_to_render as $key => $config) {
            if (in_array($key, $sections, true)) {
                $pts = [];
                foreach ($config['candidates'] as $c) {
                    if (post_type_exists($c)) $pts[] = $c;
                }

                if (!empty($pts)) {
                    $q_args = [
                        'post_type'      => $pts,
                        'posts_per_page' => $config['limit'] ?? -1,
                        'post_status'    => 'publish',
                        'orderby'        => ($key === 'articles') ? 'date' : 'title',
                        'order'          => ($key === 'articles') ? 'DESC' : 'ASC',
                        'no_found_rows'  => true,
                    ];

                    $q = new WP_Query($q_args);
                    $list = [];
                    if ($q->have_posts()) {
                        $list[] = '<ul>';
                        while ($q->have_posts()) {
                            $q->the_post();
                            $p = get_post();
                            $slug = sanitize_title($p->post_name);
                            if (in_array($slug, $exclude_slugs, true)) continue;
                            if (pcseo_post_is_excluded_from_sitemap($p)) continue;
                            if (!empty($already_listed_ids[$p->ID]) || !empty($already_listed_slugs[$slug])) continue;

                            $link = get_permalink($p);
                            $list[] = '<li><a href="' . esc_url($link) . '">' . esc_html(get_the_title()) . '</a></li>';
                            $urls[] = $link;
                            $already_listed_ids[$p->ID]  = true;
                            $already_listed_slugs[$slug] = true;
                        }
                        $list[] = '</ul>';
                        wp_reset_postdata();
                    }
                    if (count($list) > 1) {
                        $html[] = '<section class="pc-html-sitemap__section"><h2>' . $config['title'] . '</h2>' . implode('', $list) . '</section>';
                    }
                }
            }
        }

        $html[] = '</div></nav>';

        $out = ['html' => implode('', $html), 'urls' => array_values(array_unique($urls))];
        set_transient($key, $out, 12 * HOUR_IN_SECONDS);
        return $out;
    }
}
