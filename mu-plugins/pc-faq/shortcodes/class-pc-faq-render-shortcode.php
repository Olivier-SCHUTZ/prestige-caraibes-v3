<?php

/**
 * Shortcode [pc_faq_render]
 * Affiche un accordéon FAQ générique basé sur le champ ACF "pc_faq_items".
 * Accepte des attributs d'ouverture automatique et de classes CSS custom.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_FAQ_Render_Shortcode extends PC_FAQ_Shortcode_Base
{
    /**
     * Retourne le tag du shortcode pour l'enregistrement WordPress
     *
     * @return string
     */
    protected function get_tag()
    {
        return 'pc_faq_render';
    }

    /**
     * Logique de rendu du shortcode
     *
     * @param array $atts Attributs du shortcode
     * @param string|null $content Contenu
     * @return string HTML généré
     */
    protected function render($atts, $content = null)
    {
        // Plus de blocage strict si ACF est désactivé

        // Fusion avec les paramètres par défaut
        $atts = shortcode_atts([
            'post_id' => 0,
            'open'    => '0',
            'class'   => '',
        ], $atts, $this->get_tag());

        // Récupération de l'ID courant (aucune restriction de post type ici)
        $post_id = $this->get_post_id($atts);

        if (!$post_id) {
            return '';
        }

        // --- 1. DÉCODEUR V3 HYBRIDE (Champs Répéteur FAQ) ---
        $raw_rows = get_post_meta($post_id, 'pc_faq_items', true);
        $rows = [];

        // Scénario A : Ancien format ACF (La valeur est un nombre entier de lignes, ex: 5)
        if (is_numeric($raw_rows) && intval($raw_rows) > 0) {
            $count = intval($raw_rows);
            for ($i = 0; $i < $count; $i++) {
                $q = get_post_meta($post_id, 'pc_faq_items_' . $i . '_question', true);
                $a = get_post_meta($post_id, 'pc_faq_items_' . $i . '_answer', true);

                if (!empty($q) || !empty($a)) {
                    $rows[] = [
                        'question' => $q,
                        'answer'   => $a
                    ];
                }
            }
        }
        // Scénario B : Nouveau format JSON natif Vue.js
        elseif (is_string($raw_rows) && strpos(trim(stripslashes($raw_rows)), '[') === 0) {
            $decoded = json_decode(stripslashes($raw_rows), true);
            if (is_array($decoded)) {
                $rows = $decoded;
            }
        }
        // Scénario C : Fallback WP par défaut (tableau sérialisé) ou si appelé via un bouclier qui a déjà décodé
        else {
            $unserialized = maybe_unserialize($raw_rows);
            if (is_array($unserialized)) {
                $rows = $unserialized;
            }
        }

        if (empty($rows) || !is_array($rows)) {
            return ''; // La FAQ est vraiment vide ou invalide, on ne retourne rien silencieusement
        }

        // Vérification du paramètre d'ouverture automatique
        $open_first = in_array(strtolower((string)$atts['open']), ['1', 'true', 'yes', 'first'], true);

        // Gestion des classes CSS additionnelles
        $classes = 'pc-faq-accordion';
        if (!empty($atts['class'])) {
            $classes .= ' ' . $atts['class'];
        }

        // Délégation du rendu HTML à notre Helper
        return PC_FAQ_Render_Helper::render_accordion($rows, [
            'classes'    => $classes,
            'open_first' => $open_first
        ]);
    }
}
