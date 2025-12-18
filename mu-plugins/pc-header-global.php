<?php

/**
 * Plugin Name: PC - Header Global
 * Description: Shortcode unique [pc_header_global] pour rendre un header + méga-menu responsive (desktop/tablette/mobile) sans duplication.
 * Version: 1.0.0
 * Author: Prestige Caraïbes
 */

if (!defined('ABSPATH')) exit;

/**
 * -----------------------------------------------------------------------------
 * Config (modifiable via filtres)
 * -----------------------------------------------------------------------------
 */
function pc_hg_config(): array
{
    $cfg = [
        'menu_name'      => 'Menu Principal V3',

        // Page “Recherche” existante (CTA)
        'search_url'     => '/recherche-de-logements/',

        // Logo fallback si aucun “Logo du site” n’est défini dans WP
        'logo_src'       => '/wp-content/uploads/2025/06/Logo-Prestige-Caraibes-bleu.png',

        // Recherche unifiée : post types cibles
        // (On inclut aussi “appartement” car votre shortcode logements l’utilise déjà.)
        // reminder: '',
        'search_post_types' => ['villa', 'appartement', 'destination', 'experience'],

        // UI search
        'search_min_chars'  => 2,
        'search_max_results' => 8,
        'search_placeholder' => 'Rechercher une villa, une destination, une expérience…',

        'tel_label'      => '+590 690 63 11 81',
        'tel_href'       => 'tel:+590690631181',

        'social'         => [
            ['key' => 'facebook',  'label' => 'Facebook',  'href' => 'https://facebook.com/prestigecaraibes'],
            ['key' => 'youtube',   'label' => 'YouTube',   'href' => 'https://www.youtube.com/@prestigecaraibes'],
            ['key' => 'instagram', 'label' => 'Instagram', 'href' => 'https://instagram.com/prestigecaraibes'],
            ['key' => 'whatsapp',  'label' => 'WhatsApp',  'href' => 'https://api.whatsapp.com/send?phone=590690631181'],
        ],
    ];
    return apply_filters('pc_hg_config', $cfg);
}

/**
 * -----------------------------------------------------------------------------
 * Assets (enqueued uniquement quand le shortcode est rendu)
 * -----------------------------------------------------------------------------
 */
function pc_hg_enqueue_assets(): void
{
    static $done = false;
    if ($done || is_admin()) return;
    $done = true;

    $dir_url  = plugin_dir_url(__FILE__);
    $dir_path = __DIR__;

    $css_rel = 'assets/pc-header-global.css';
    $js_rel  = 'assets/pc-header-global.js';

    $css_path = $dir_path . '/' . $css_rel;
    $js_path  = $dir_path . '/' . $js_rel;

    $css_url = $dir_url . $css_rel;
    $js_url  = $dir_url . $js_rel;

    $css_ver = file_exists($css_path) ? filemtime($css_path) : '1.0.0';
    $js_ver  = file_exists($js_path)  ? filemtime($js_path)  : '1.0.0';

    // Dépendances styles existantes (si déjà chargées par un autre loader)
    $style_deps = [];
    if (wp_style_is('pc-base', 'enqueued')) $style_deps[] = 'pc-base';
    if (wp_style_is('pc-header', 'enqueued')) $style_deps[] = 'pc-header';

    if (file_exists($css_path)) {
        wp_enqueue_style('pc-header-global', $css_url, $style_deps, $css_ver);
    }
    if (file_exists($js_path)) {
        wp_enqueue_script('pc-header-global', $js_url, [], $js_ver, true);
        wp_script_add_data('pc-header-global', 'defer', true);
        $cfg = pc_hg_config();

        wp_localize_script('pc-header-global', 'PCHeaderGlobal', [
            'bpDesktop'      => 1025,
            'restUrl'        => esc_url_raw(rest_url('pc/v1/search-suggest')),
            'minChars'       => (int)($cfg['search_min_chars'] ?? 2),
            'maxResults'     => (int)($cfg['search_max_results'] ?? 8),
        ]);
    }
    // Smart header (scroll hide/show) : on le recharge même si le legacy est OFF
    $smart_rel  = 'assets/pc-header-smart.js';
    $smart_path = $dir_path . '/' . $smart_rel;
    $smart_url  = $dir_url  . $smart_rel;
    $smart_ver  = file_exists($smart_path) ? filemtime($smart_path) : '1.0.0';

    if (file_exists($smart_path)) {
        wp_enqueue_script('pc-header-smart', $smart_url, [], $smart_ver, true);
        wp_script_add_data('pc-header-smart', 'defer', true);
    }
}

/**
 * -----------------------------------------------------------------------------
 * REST — Search suggest (villa / destination / experience)
 * -----------------------------------------------------------------------------
 */
add_action('rest_api_init', function () {
    register_rest_route('pc/v1', '/search-suggest', [
        'methods'             => 'GET',
        'callback'            => 'pc_hg_rest_search_suggest',
        'permission_callback' => '__return_true', // public
        'args'                => [
            'q' => [
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ],
    ]);
});

function pc_hg_rest_search_suggest(\WP_REST_Request $req): \WP_REST_Response
{
    $q = trim((string)$req->get_param('q'));
    if (mb_strlen($q) < 2) {
        return new \WP_REST_Response([], 200);
    }

    $cfg = pc_hg_config();
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

/**
 * -----------------------------------------------------------------------------
 * Menu helpers (tree builder)
 * -----------------------------------------------------------------------------
 */
function pc_hg_get_menu_items(string $menu_name): array
{
    $menu_obj = wp_get_nav_menu_object($menu_name);
    if (!$menu_obj) return [];
    $items = wp_get_nav_menu_items($menu_obj->term_id, ['update_post_term_cache' => false]);
    return is_array($items) ? $items : [];
}

function pc_hg_build_tree(array $items): array
{
    $by_id = [];
    foreach ($items as $it) {
        $it->children = [];
        $by_id[(int)$it->ID] = $it;
    }
    $root = [];
    foreach ($by_id as $id => $it) {
        $pid = (int)$it->menu_item_parent;
        if ($pid && isset($by_id[$pid])) {
            $by_id[$pid]->children[] = $it;
        } else {
            $root[] = $it;
        }
    }
    return $root;
}

function pc_hg_slugify(string $s): string
{
    $s = remove_accents($s);
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');
    return $s ?: 'item';
}

function pc_hg_is_hash_url($url): bool
{
    if (!is_string($url)) return true;
    $u = trim($url);
    return ($u === '' || $u === '#' || $u === 'javascript:void(0)');
}

/**
 * -----------------------------------------------------------------------------
 * Render pieces
 * -----------------------------------------------------------------------------
 */
function pc_hg_render_logo(array $cfg = []): string
{
    $home = esc_url(home_url('/'));

    // Source de vérité du header : on ignore toujours le "Logo du site" WP
    if (!empty($cfg['logo_src'])) {
        $src = (string)$cfg['logo_src'];

        // Si c'est une URL absolue, on la garde. Sinon, on la construit depuis home_url().
        if (!preg_match('#^https?://#i', $src)) {
            $src = home_url($src);
        }

        $src = esc_url($src);

        return '<a class="pc-hg__logo pc-hg__logo--img" href="' . $home . '" aria-label="Accueil"><img src="' . $src . '" alt="Prestige Caraïbes" loading="eager" decoding="async"></a>';
    }

    // Dernier fallback
    return '<a class="pc-hg__logo" href="' . $home . '" aria-label="Accueil">' . esc_html(get_bloginfo('name')) . '</a>';
}

function pc_hg_svg(string $key): string
{
    switch ($key) {
        case 'facebook':
            return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor"d="M22 12a10 10 0 1 0-11.5 9.9v-7H8v-2.9h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.4H15.8c-1.2 0-1.6.7-1.6 1.5v1.8H17l-.4 2.9h-2.4v7A10 10 0 0 0 22 12z"/></svg>';

        case 'instagram':
            return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor"d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5zm10 2H7a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3z"/><path d="M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10zm0 2a3 3 0 1 1 0 6 3 3 0 0 1 0-6z"/><path d="M17.5 6.5a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/></svg>';

        case 'youtube':
            return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor"d="M21.6 7.2a3 3 0 0 0-2.1-2.1C17.8 4.6 12 4.6 12 4.6s-5.8 0-7.5.5A3 3 0 0 0 2.4 7.2 31 31 0 0 0 2 12a31 31 0 0 0 .4 4.8 3 3 0 0 0 2.1 2.1c1.7.5 7.5.5 7.5.5s5.8 0 7.5-.5a3 3 0 0 0 2.1-2.1A31 31 0 0 0 22 12a31 31 0 0 0-.4-4.8zM10 15.2V8.8L15.5 12 10 15.2z"/></svg>';

        case 'whatsapp':
            return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor"d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.5A10 10 0 1 0 12 2zm0 18a8 8 0 0 1-4.1-1.1l-.3-.2-2.8.9.9-2.7-.2-.3A8 8 0 1 1 20 12a8 8 0 0 1-8 8z"/><path d="M16.8 14.5c-.2-.1-1.3-.6-1.5-.7s-.4-.1-.6.1-.7.7-.8.8-.3.2-.5.1a6.6 6.6 0 0 1-2-1.2 7.5 7.5 0 0 1-1.4-1.8c-.1-.2 0-.4.1-.5l.4-.4.3-.4c.1-.1.1-.3 0-.5s-.6-1.4-.8-1.9c-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.5.1-.7.3s-1 1-1 2.4 1 2.8 1.2 3 .1.2 0 0a10.6 10.6 0 0 0 4.1 3.6c.6.3 1 .5 1.4.6.6.2 1.1.2 1.5.1.5-.1 1.3-.5 1.5-1 .2-.5.2-.9.1-1s-.2-.2-.4-.3z"/></svg>';
        case 'phone':
            return '<svg aria-hidden="true" viewBox="0 0 512 512"><path fill="currentColor" d="M497.39 361.8l-112-48a24 24 0 0 0-28 6.9l-49.6 60.6A370.66 370.66 0 0 1 130.6 204.11l60.6-49.6a23.94 23.94 0 0 0 6.9-28l-48-112A24.16 24.16 0 0 0 122.6.61l-104 24A24 24 0 0 0 0 48c0 256.5 207.9 464 464 464a24 24 0 0 0 23.4-18.6l24-104a24.29 24.29 0 0 0-14.01-27.6z"/></svg>';
        case 'chev-down':
            return '<svg aria-hidden="true" viewBox="0 0 320 512"><path fill="currentColor" d="M31.3 192h257.3c17.8 0 26.7 21.5 14.1 34.1L174.1 354.8c-7.8 7.8-20.5 7.8-28.3 0L17.2 226.1C4.6 213.5 13.5 192 31.3 192z"/></svg>';
        case 'menu':
            return '<svg aria-hidden="true" viewBox="0 0 1000 1000"><path fill="currentColor" d="M104 333H896C929 333 958 304 958 271S929 208 896 208H104C71 208 42 237 42 271S71 333 104 333ZM104 583H896C929 583 958 554 958 521S929 458 896 458H104C71 458 42 487 42 521S71 583 104 583ZM104 833H896C929 833 958 804 958 771S929 708 896 708H104C71 708 42 737 42 771S71 833 104 833Z"/></svg>';
        case 'close':
            return '<svg aria-hidden="true" viewBox="0 0 1000 1000"><path fill="currentColor" d="M742 167L500 408 258 167C246 154 233 150 217 150 196 150 179 158 167 167 154 179 150 196 150 212 150 229 154 242 171 254L408 500 167 742C138 771 138 800 167 829 196 858 225 858 254 829L496 587 738 829C750 842 767 846 783 846 800 846 817 842 829 829 842 817 846 804 846 783 846 767 842 750 829 737L588 500 833 258C863 229 863 200 833 171 804 137 775 137 742 167Z"/></svg>';
    }
    return '';
}

function pc_hg_render_nav(array $tree): string
{
    $out = '';
    foreach ($tree as $it) {
        $title = trim((string)$it->title);
        $slug  = pc_hg_slugify($title);
        $url   = (string)$it->url;
        $has_children = !empty($it->children);

        $out .= '<li class="pc-nav__item" role="none">';

        if ($has_children) {
            $panel_id = 'pc-panel-' . $slug;

            if (!pc_hg_is_hash_url($url)) {
                $out .= '<a class="pc-nav__link pc-nav__link--haspanel" role="menuitem" aria-haspopup="true" aria-expanded="false" aria-controls="' . esc_attr($panel_id) . '" data-pc-panel="' . esc_attr($panel_id) . '" href="' . esc_url($url) . '">' . esc_html($title) . '</a>';
                $out .= '<button class="pc-nav__trigger" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="' . esc_attr($panel_id) . '" data-pc-panel="' . esc_attr($panel_id) . '">' . pc_hg_svg('chev-down') . '<span class="sr-only">Ouvrir ' . esc_html($title) . '</span></button>';
            } else {
                $out .= '<button class="pc-nav__btn" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="' . esc_attr($panel_id) . '" data-pc-panel="' . esc_attr($panel_id) . '"><span class="pc-nav__text">' . esc_html($title) . '</span>' . pc_hg_svg('chev-down') . '</button>';
            }
        } else {
            $out .= '<a class="pc-nav__link" role="menuitem" href="' . esc_url($url) . '">' . esc_html($title) . '</a>';
        }

        $out .= '</li>';
    }
    return $out;
}

function pc_hg_render_mega_panels(array $tree, array $cfg): string
{
    $out = '';
    foreach ($tree as $it) {
        if (empty($it->children)) continue;

        $title = trim((string)$it->title);
        $slug  = pc_hg_slugify($title);
        $panel_id = 'pc-panel-' . $slug;

        $key = mb_strtolower($title);

        $is_locations = ($key === 'locations');
        $is_magazine  = ($key === 'magazine');

        $panel_class =
            $is_locations ? 'pc-mega pc-mega--locations' : ($is_magazine ? 'pc-mega pc-mega--magazine' : 'pc-mega pc-mega--default');

        $out .= '<section id="' . esc_attr($panel_id) . '" class="' . esc_attr($panel_class) . '" aria-hidden="true" tabindex="-1" data-pc-mega>';
        $out .= '<div class="pc-container pc-mega__inner">';

        // Detecte si on a des "colonnes" (niveau 3) ou juste une liste (niveau 2)
        $has_grandchildren = false;
        foreach ($it->children as $col_check) {
            if (!empty($col_check->children)) {
                $has_grandchildren = true;
                break;
            }
        }

        if (!$has_grandchildren) {
            // Cas Destinations / Expériences : pas de niveau 3 => on découpe en colonnes "blocs"
            $links = is_array($it->children) ? $it->children : [];
            $count = count($links);

            // Heuristique simple: 1 col <= 10 liens, 2 cols <= 18, sinon 3 cols
            $cols = ($count > 18) ? 3 : (($count > 10) ? 2 : 1);
            $per_col = ($cols > 0) ? (int)ceil($count / $cols) : $count;
            $chunks = array_chunk($links, max(1, $per_col));

            foreach ($chunks as $idx => $chunk) {
                $out .= '<div class="pc-mega__col pc-mega__col--chunk">';
                // Titre visible seulement sur le 1er bloc, les autres gardent une structure propre
                if ($idx === 0) {
                    $out .= '<div class="pc-mega__title">' . esc_html($title) . '</div>';
                } else {
                    $out .= '<div class="pc-mega__title sr-only">' . esc_html($title) . '</div>';
                }

                $out .= '<ul class="pc-mega__list">';
                foreach ($chunk as $link) {
                    $out .= '<li><a href="' . esc_url($link->url) . '">' . esc_html($link->title) . '</a></li>';
                }
                $out .= '</ul>';
                $out .= '</div>';
            }
        } else {
            // Cas Locations : vraies colonnes (niveau 3)
            foreach ($it->children as $col) {
                $col_title = trim((string)$col->title);
                $col_slug  = pc_hg_slugify($col_title);

                $out .= '<div class="pc-mega__col pc-mega__col--' . esc_attr($col_slug) . '">';
                $out .= '<div class="pc-mega__title">' . esc_html($col_title) . '</div>';

                if (mb_strtolower($col_title) === 'par nom du logement') {
                    if (shortcode_exists('liste_logements_dropdown')) {
                        $out .= do_shortcode('[liste_logements_dropdown label="Nos logements" max="5" search="' . esc_url($cfg['search_url']) . '"]');
                    } else {
                        $out .= '<div class="pc-mega__placeholder">(Shortcode logements manquant)</div>';
                    }
                } else {
                    $out .= '<ul class="pc-mega__list">';
                    if (!empty($col->children)) {
                        foreach ($col->children as $link) {
                            $out .= '<li><a href="' . esc_url($link->url) . '">' . esc_html($link->title) . '</a></li>';
                        }
                    }
                    $out .= '</ul>';
                }

                $out .= '</div>';
            }
        } // end has_grandchildren

        $out .= '</div>';
        $out .= '</section>';
    }
    return $out;
}

function pc_hg_render_offcanvas(array $tree, array $cfg): string
{
    $out  = '<div class="pc-offcanvas" id="pc-offcanvas" aria-hidden="true" tabindex="-1">';
    $out .= '  <div class="pc-offcanvas__overlay" data-pc-oc-close tabindex="-1"></div>';
    $out .= '  <div class="pc-offcanvas__panel" role="dialog" aria-modal="true" aria-label="Menu">';
    $out .= '    <div class="pc-offcanvas__top">';
    $out .= '      <a class="pc-offcanvas__logo" href="' . esc_url(home_url('/')) . '">' . esc_html(get_bloginfo('name')) . '</a>';
    $out .= '      <div class="pc-offcanvas__meta" aria-label="Contact">';
    $out .= '        <a class="pc-offcanvas__tel" href="' . esc_url($cfg['tel_href']) . '">';
    $out .= '          <span class="pc-offcanvas__tel-ico" aria-hidden="true">' . pc_hg_svg('phone') . '</span>';
    $out .= '          <span class="pc-offcanvas__tel-txt">' . esc_html($cfg['tel_label']) . '</span>';
    $out .= '        </a>';

    $out .= '        <div class="pc-offcanvas__social" aria-label="Réseaux sociaux">';
    $wanted = ['whatsapp', 'instagram', 'facebook'];
    foreach ($wanted as $k) {
        foreach ($cfg['social'] as $s) {
            if (!isset($s['key'], $s['href'])) continue;
            if ($s['key'] !== $k) continue;

            $label = isset($s['label']) ? $s['label'] : ucfirst($s['key']);
            $out .= '          <a class="pc-offcanvas__social-link" href="' . esc_url($s['href']) . '" target="_blank" rel="noopener" aria-label="' . esc_attr($label) . '">';
            $out .=                pc_hg_svg($s['key']);
            $out .= '          </a>';
            break;
        }
    }
    $out .= '        </div>';
    $out .= '      </div>';
    $out .= '      <button class="pc-offcanvas__close" type="button" data-pc-oc-close aria-label="Fermer">' . pc_hg_svg('close') . '</button>';
    $out .= '    </div>';
    $out .= '    <div class="pc-offcanvas__search" aria-label="Rechercher">';
    $out .= '      <div class="pc-hg__searchbox pc-hg__searchbox--oc" data-pc-hg-searchbox>';
    $out .= '        <input class="pc-hg__searchinput" type="search" placeholder="Rechercher une villa, destination, expérience…" autocomplete="off" inputmode="search" />';
    $out .= '        <div class="pc-hg__searchlist" hidden></div>';
    $out .= '      </div>';
    $out .= '    </div>';

    $out .= '    <nav class="pc-oc-nav" aria-label="Navigation principale">';
    $out .= '      <ul class="pc-oc-nav__list">';

    foreach ($tree as $it) {
        $title = trim((string)$it->title);
        $url   = (string)$it->url;
        $has_children = !empty($it->children);
        $slug = pc_hg_slugify($title);
        $acc_id = 'pc-oc-' . $slug;

        $out .= '<li class="pc-oc-nav__item">';
        if ($has_children) {
            $out .= '<button class="pc-oc-nav__btn" type="button" aria-expanded="false" aria-controls="' . esc_attr($acc_id) . '" data-pc-oc-acc>';
            $out .= '<span>' . esc_html($title) . '</span>' . pc_hg_svg('chev-down');
            $out .= '</button>';
            $out .= '<div class="pc-oc-nav__panel" id="' . esc_attr($acc_id) . '" hidden>';

            if (!pc_hg_is_hash_url($url)) {
                $out .= '<a class="pc-oc-nav__link pc-oc-nav__link--parent" href="' . esc_url($url) . '">Voir "' . esc_html($title) . '"</a>';
            }
            $has_grandchildren = false;
            foreach ($it->children as $col_check) {
                if (!empty($col_check->children)) {
                    $has_grandchildren = true;
                    break;
                }
            }

            if (!$has_grandchildren) {
                $out .= '<div class="pc-oc-nav__group pc-oc-nav__group--flat">';
                $out .= '<div class="pc-oc-nav__group-title">' . esc_html($title) . '</div>';
                $out .= '<ul class="pc-oc-nav__sublinks">';
                foreach ($it->children as $link) {
                    $out .= '<li><a class="pc-oc-nav__link" href="' . esc_url($link->url) . '">' . esc_html($link->title) . '</a></li>';
                }
                $out .= '</ul>';
                $out .= '</div>';
            } else {
                foreach ($it->children as $col) {
                    $col_title = trim((string)$col->title);
                    $out .= '<div class="pc-oc-nav__group">';
                    $out .= '<div class="pc-oc-nav__group-title">' . esc_html($col_title) . '</div>';

                    if (mb_strtolower($col_title) === 'par nom du logement') {
                        if (shortcode_exists('liste_logements_dropdown')) {
                            $out .= do_shortcode('[liste_logements_dropdown label="Nos logements" max="6" search="' . esc_url($cfg['search_url']) . '"]');
                        }
                    } else {
                        $out .= '<ul class="pc-oc-nav__sublinks">';
                        if (!empty($col->children)) {
                            foreach ($col->children as $link) {
                                $out .= '<li><a class="pc-oc-nav__link" href="' . esc_url($link->url) . '">' . esc_html($link->title) . '</a></li>';
                            }
                        }
                        $out .= '</ul>';
                    }
                    $out .= '</div>';
                }
            }

            $out .= '</div>';
        } else {
            $out .= '<a class="pc-oc-nav__link" href="' . esc_url($url) . '">' . esc_html($title) . '</a>';
        }
        $out .= '</li>';
    }

    $out .= '      </ul>';
    $out .= '    </nav>';
    $out .= '  </div>';
    $out .= '</div>';
    return $out;
}

/**
 * -----------------------------------------------------------------------------
 * Shortcode
 * -----------------------------------------------------------------------------
 */
function pc_header_global_shortcode($atts = []): string
{
    // Éviter les fatals dans l'éditeur Elementor (preview/iframe/admin render)
    if (class_exists('\Elementor\Plugin')) {
        try {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                return '<div style="padding:10px;border:1px dashed #ccc;font-size:13px;">PC Header Global — rendu désactivé dans l’éditeur Elementor.</div>';
            }
        } catch (\Throwable $e) {
            // Si Elementor change son API, on reste safe et on continue le rendu normal en front.
        }
    }
    pc_hg_enqueue_assets();
    $cfg = pc_hg_config();

    $items = pc_hg_get_menu_items($cfg['menu_name']);
    $tree  = pc_hg_build_tree($items);

    if (!$tree) {
        return '<div id="pc-header" class="pc-hg"><div class="pc-container"><p style="margin:0;padding:12px">Menu introuvable : ' . esc_html($cfg['menu_name']) . '</p></div></div>';
    }

    $search_url = esc_url(home_url($cfg['search_url']));

    ob_start();
?>
    <div id="pc-header" class="pc-hg pc-hg-smart" data-pc-hg>
        <div class="pc-hg__bar" aria-hidden="false">
            <div class="pc-hg__container pc-hg__bar-inner">

                <div class="pc-hg__social" aria-label="Réseaux sociaux">
                    <?php foreach ($cfg['social'] as $s): ?>
                        <a class="pc-hg__social-link" href="<?php echo esc_url($s['href']); ?>" target="_blank" rel="noopener">
                            <span class="sr-only"><?php echo esc_html($s['label']); ?></span>
                            <span class="pc-hg__social-ico" aria-hidden="true"><?php echo pc_hg_svg($s['key']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="pc-hg__topsearch">
                    <label class="sr-only" for="pc-hg-search">Recherche</label>
                    <div class="pc-hg__searchbox" role="combobox" aria-haspopup="listbox" aria-expanded="false">
                        <input
                            id="pc-hg-search"
                            class="pc-hg__searchinput"
                            type="search"
                            inputmode="search"
                            autocomplete="off"
                            autocorrect="off"
                            autocapitalize="off"
                            spellcheck="false"
                            placeholder="<?php echo esc_attr($cfg['search_placeholder']); ?>"
                            aria-autocomplete="list"
                            aria-controls="pc-hg-search-list"
                            aria-expanded="false" />
                        <div id="pc-hg-search-list" class="pc-hg__searchlist" role="listbox" hidden></div>
                    </div>
                </div>

                <a class="pc-hg__tel" href="<?php echo esc_url($cfg['tel_href']); ?>">
                    <span class="pc-hg__icon" aria-hidden="true"><?php echo pc_hg_svg('phone'); ?></span>
                    <span class="pc-hg__tel-text"><?php echo esc_html($cfg['tel_label']); ?></span>
                </a>

            </div>
        </div>

        <div class="pc-hg__main">
            <div class="pc-container pc-hg__main-inner">
                <?php echo pc_hg_render_logo($cfg); ?>

                <nav class="pc-nav" aria-label="Navigation principale">
                    <ul class="pc-nav__list" role="menubar">
                        <?php echo pc_hg_render_nav($tree); ?>
                    </ul>
                </nav>

                <div class="pc-hg__actions">
                    <a class="pc-btn pc-btn--primary pc-hg__search" href="<?php echo $search_url; ?>">Rechercher</a>
                    <button class="pc-hg__burger" type="button" aria-label="Ouvrir le menu" aria-controls="pc-offcanvas" aria-expanded="false" data-pc-oc-open>
                        <span class="pc-hg__burger-ico" aria-hidden="true"><?php echo pc_hg_svg('menu'); ?></span>
                    </button>
                </div>
            </div>
        </div>

        <div class="pc-hg__panels" aria-hidden="false">
            <?php echo pc_hg_render_mega_panels($tree, $cfg); ?>
        </div>

        <?php echo pc_hg_render_offcanvas($tree, $cfg); ?>
    </div>
<?php
    return ob_get_clean();
}
add_shortcode('pc_header_global', 'pc_header_global_shortcode');
