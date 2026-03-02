<?php

/**
 * Helper statique pour le rendu HTML des composants du Header
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Header_Render_Helper
{
    /**
     * Rendu du logo
     */
    public static function render_logo(array $cfg = []): string
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

    /**
     * Rendu de la navigation de base
     */
    public static function render_navigation(array $tree): string
    {
        $out = '';
        foreach ($tree as $it) {
            $title = trim((string)$it->title);
            $slug  = PC_Header_Menu_Helper::slugify($title);
            $url   = (string)$it->url;
            $has_children = !empty($it->children);

            $out .= '<li class="pc-nav__item" role="none">';

            if ($has_children) {
                $panel_id = 'pc-panel-' . $slug;

                if (!PC_Header_Menu_Helper::is_hash_url($url)) {
                    $out .= '<a class="pc-nav__link pc-nav__link--haspanel" role="menuitem" aria-haspopup="true" aria-expanded="false" aria-controls="' . esc_attr($panel_id) . '" data-pc-panel="' . esc_attr($panel_id) . '" href="' . esc_url($url) . '">' . esc_html($title) . '</a>';
                    $out .= '<button class="pc-nav__trigger" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="' . esc_attr($panel_id) . '" data-pc-panel="' . esc_attr($panel_id) . '">' . PC_Header_SVG_Helper::get('chev-down') . '<span class="sr-only">Ouvrir ' . esc_html($title) . '</span></button>';
                } else {
                    $out .= '<button class="pc-nav__btn" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="' . esc_attr($panel_id) . '" data-pc-panel="' . esc_attr($panel_id) . '"><span class="pc-nav__text">' . esc_html($title) . '</span>' . PC_Header_SVG_Helper::get('chev-down') . '</button>';
                }
            } else {
                $out .= '<a class="pc-nav__link" role="menuitem" href="' . esc_url($url) . '">' . esc_html($title) . '</a>';
            }

            $out .= '</li>';
        }
        return $out;
    }

    /**
     * Rendu des panneaux du méga menu
     */
    public static function render_mega_panels(array $tree, array $cfg): string
    {
        $out = '';
        foreach ($tree as $it) {
            if (empty($it->children)) continue;

            $title = trim((string)$it->title);
            $slug  = PC_Header_Menu_Helper::slugify($title);
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
                    $col_slug  = PC_Header_Menu_Helper::slugify($col_title);

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
            }

            $out .= '</div>';
            $out .= '</section>';
        }
        return $out;
    }

    /**
     * Rendu du menu mobile (offcanvas)
     */
    public static function render_offcanvas(array $tree, array $cfg, array $services_tree = []): string
    {
        $out  = '<div class="pc-offcanvas" id="pc-offcanvas" aria-hidden="true" tabindex="-1">';
        $out .= '  <div class="pc-offcanvas__overlay" data-pc-oc-close tabindex="-1"></div>';
        $out .= '  <div class="pc-offcanvas__panel" role="dialog" aria-modal="true" aria-label="Menu">';
        $out .= '    <div class="pc-offcanvas__top">';
        $out .= '      <a class="pc-offcanvas__logo" href="' . esc_url(home_url('/')) . '">' . esc_html(get_bloginfo('name')) . '</a>';
        $out .= '      <div class="pc-offcanvas__meta" aria-label="Contact">';
        $out .= '        <a class="pc-offcanvas__tel" href="' . esc_url($cfg['tel_href']) . '">';
        $out .= '          <span class="pc-offcanvas__tel-ico" aria-hidden="true">' . PC_Header_SVG_Helper::get('phone') . '</span>';
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
                $out .=                PC_Header_SVG_Helper::get($s['key']);
                $out .= '          </a>';
                break;
            }
        }
        $out .= '        </div>';
        $out .= '      </div>';
        $out .= '      <button class="pc-offcanvas__close" type="button" data-pc-oc-close aria-label="Fermer">' . PC_Header_SVG_Helper::get('close') . '</button>';
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
            $slug = PC_Header_Menu_Helper::slugify($title);
            $acc_id = 'pc-oc-' . $slug;

            $out .= '<li class="pc-oc-nav__item">';
            if ($has_children) {
                $out .= '<button class="pc-oc-nav__btn" type="button" aria-expanded="false" aria-controls="' . esc_attr($acc_id) . '" data-pc-oc-acc>';
                $out .= '<span>' . esc_html($title) . '</span>' . PC_Header_SVG_Helper::get('chev-down');
                $out .= '</button>';
                $out .= '<div class="pc-oc-nav__panel" id="' . esc_attr($acc_id) . '" hidden>';

                if (!PC_Header_Menu_Helper::is_hash_url($url)) {
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

        /* --- ÉDITION DU MENU SERVICES MOBILE AVEC RELIEF --- */
        if (!empty($services_tree)) {
            $out .= '<li class="pc-oc-divider" style="margin: 20px 16px; border-top: 1px solid rgba(0,0,0,0.08); list-style:none;"></li>';

            foreach ($services_tree as $it) {
                $title = trim((string)$it->title);
                $url   = (string)$it->url;
                $has_children = !empty($it->children);
                $slug = PC_Header_Menu_Helper::slugify($title);
                $acc_id = 'pc-oc-' . $slug;

                $out .= '<li class="pc-oc-nav__item">';
                if ($has_children) {
                    $out .= '<button class="pc-oc-nav__btn" type="button" aria-expanded="false" aria-controls="' . esc_attr($acc_id) . '" data-pc-oc-acc>';
                    $out .= '<span>' . esc_html($title) . '</span>' . PC_Header_SVG_Helper::get('chev-down');
                    $out .= '</button>';

                    // On ajoute la classe "pc-oc-nav__panel--relief" pour le CSS
                    $out .= '<div class="pc-oc-nav__panel pc-oc-nav__panel--relief" id="' . esc_attr($acc_id) . '" hidden>';
                    // Cette div crée la "carte blanche" opaque
                    $out .= '<div class="pc-oc-nav__card">';
                    foreach ($it->children as $link) {
                        $out .= '<a class="pc-oc-nav__link" href="' . esc_url($link->url) . '">' . esc_html($link->title) . '</a>';
                    }
                    $out .= '</div>';
                    $out .= '</div>';
                } else {
                    $out .= '<a class="pc-oc-nav__link" href="' . esc_url($url) . '">' . esc_html($title) . '</a>';
                }
                $out .= '</li>';
            }
        }

        $out .= '      </ul>';
        $out .= '    </nav>';
        $out .= '  </div>';
        $out .= '</div>';
        return $out;
    }
}
