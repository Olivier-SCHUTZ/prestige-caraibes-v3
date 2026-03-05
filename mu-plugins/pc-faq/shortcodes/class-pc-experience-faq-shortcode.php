<?php

/**
 * Shortcode [experience_faq]
 * Affiche la FAQ spécifique aux expériences.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Experience_FAQ_Shortcode extends PC_FAQ_Shortcode_Base
{
    /**
     * Retourne le tag du shortcode pour l'enregistrement WordPress
     *
     * @return string
     */
    protected function get_tag()
    {
        return 'experience_faq';
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
            'title'   => 'Prestige Caraïbes vous réponds',
            'post_id' => 0,
        ], $atts, $this->get_tag());

        // Récupération contextuelle de l'ID (limité au type de post 'experience')
        $post_id = $this->get_post_id($atts, ['experience']);

        // Si on n'a pas d'ID valide ou qu'on n'est pas sur une page expérience, on coupe
        if (!$post_id) {
            return '';
        }

        // Récupération des données du repeater ACF (clé spécifique : exp_faq)
        $rows = get_field('exp_faq', $post_id);

        if (empty($rows) || !is_array($rows)) {
            return '';
        }

        // Délégation du rendu HTML à notre Helper
        return PC_FAQ_Render_Helper::render_accordion($rows, [
            'title' => $atts['title']
        ]);
    }
}
