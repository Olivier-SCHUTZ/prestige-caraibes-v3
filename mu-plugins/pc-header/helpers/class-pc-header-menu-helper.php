<?php

/**
 * Helper statique pour la gestion et la construction des menus du Header
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Header_Menu_Helper
{
    /**
     * Récupère les éléments bruts d'un menu WordPress par son nom
     *
     * @param string $menu_name Le nom du menu (ex: 'Menu Principal V3')
     * @return array La liste des éléments
     */
    public static function get_items(string $menu_name): array
    {
        $menu_obj = wp_get_nav_menu_object($menu_name);
        if (!$menu_obj) return [];

        $items = wp_get_nav_menu_items($menu_obj->term_id, ['update_post_term_cache' => false]);
        return is_array($items) ? $items : [];
    }

    /**
     * Construit une arborescence (parent/enfant) à partir d'une liste plate d'éléments de menu
     *
     * @param array $items Les éléments bruts
     * @return array L'arbre du menu
     */
    public static function build_tree(array $items): array
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

    /**
     * Transforme une chaîne en slug propre (sans accents, minuscules, tirets)
     *
     * @param string $s La chaîne à nettoyer
     * @return string Le slug généré
     */
    public static function slugify(string $s): string
    {
        $s = remove_accents($s);
        $s = strtolower($s);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s);
        $s = trim($s, '-');
        return $s ?: 'item';
    }

    /**
     * Vérifie si une URL est un simple ancre (#) ou une action javascript vide
     *
     * @param mixed $url L'URL à vérifier
     * @return bool
     */
    public static function is_hash_url($url): bool
    {
        if (!is_string($url)) return true;
        $u = trim($url);
        return ($u === '' || $u === '#' || $u === 'javascript:void(0)');
    }
}
