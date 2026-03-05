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
        // Sécurité : On s'assure qu'ACF est bien actif
        if (!function_exists('get_field')) {
            return '';
        }

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

        // Récupération des données du repeater ACF (clé spécifique : pc_faq_items)
        $rows = get_field('pc_faq_items', $post_id);

        if (empty($rows) || !is_array($rows)) {
            return '';
        }

        // Vérification du paramètre d'ouverture automatique
        $open_first = in_array(strtolower((string)$atts['open']), ['1', 'true', 'yes', 'first'], true);

        // Gestion des classes CSS additionnelles
        $classes = 'pc-faq-accordion';
        if (!empty($atts['class'])) {
            // Le nettoyage regex (sanitize) est géré de manière sécurisée par notre Helper
            $classes .= ' ' . $atts['class'];
        }

        // Délégation du rendu HTML à notre Helper
        return PC_FAQ_Render_Helper::render_accordion($rows, [
            'classes'    => $classes,
            'open_first' => $open_first
        ]);
    }
}
