<?php

/**
 * Shortcode [logement_faq]
 * Affiche la FAQ spécifique aux logements (villas, appartements).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Logement_FAQ_Shortcode extends PC_FAQ_Shortcode_Base
{
    /**
     * Retourne le tag du shortcode pour l'enregistrement WordPress
     *
     * @return string
     */
    protected function get_tag()
    {
        return 'logement_faq';
    }

    /**
     * Logique de rendu du shortcode
     *
     * @param array $atts Attributs du shortcode
     * @param string|null $content Contenu entre les balises du shortcode
     * @return string HTML généré
     */
    protected function render($atts, $content = null)
    {
        // Sécurité V3 : On s'assure que notre moteur de champs est actif
        if (!class_exists('PCR_Fields')) {
            return '';
        }

        // Fusion avec les paramètres par défaut
        $atts = shortcode_atts([
            'title'   => 'Prestige Caraïbes vous répond',
            'post_id' => 0,
        ], $atts, $this->get_tag());

        // Récupération contextuelle de l'ID (Méthode héritée de la classe de base)
        $post_id = $this->get_post_id($atts, ['villa', 'appartement', 'logement']);

        // Si on n'a pas d'ID valide ou qu'on n'est pas sur le bon type de page, on coupe
        if (!$post_id) {
            return '';
        }

        // 1. DÉCODEUR V3 : Récupération des données du repeater (Historique ACF + JSON natif Vue.js)
        $raw_rows = PCR_Fields::get('logement_faq', $post_id);
        $rows = is_string($raw_rows) ? json_decode($raw_rows, true) : $raw_rows;

        if (empty($rows) || !is_array($rows)) {
            return '';
        }

        // Délégation du rendu HTML à notre Helper ultra robuste
        return PC_FAQ_Render_Helper::render_accordion($rows, [
            'title' => $atts['title']
        ]);
    }
}
