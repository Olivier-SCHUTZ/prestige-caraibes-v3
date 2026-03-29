<?php

/**
 * Shortcode [destination_faq]
 * Affiche la FAQ spécifique aux destinations.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Destination_FAQ_Shortcode extends PC_FAQ_Shortcode_Base
{
    /**
     * Retourne le tag du shortcode pour l'enregistrement WordPress
     *
     * @return string
     */
    protected function get_tag()
    {
        return 'destination_faq';
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
            'title'   => 'Prestige Caraïbes vous répond', // Orthographe corrigée ("répond" sans "s")
            'post_id' => 0,
        ], $atts, $this->get_tag());

        // Récupération contextuelle de l'ID (limité au type de post 'destination')
        $post_id = $this->get_post_id($atts, ['destination']);

        // Si on n'a pas d'ID valide ou qu'on n'est pas sur une page destination, on coupe
        if (!$post_id) {
            return '';
        }

        // --- 1. DÉCODEUR V3 HYBRIDE (Champs Répéteur FAQ) ---
        // Récupère via la classe V3 ou tape dans le meta natif en secours
        $raw_rows = class_exists('PCR_Fields') ? PCR_Fields::get('dest_faq', $post_id) : null;
        if (empty($raw_rows)) {
            $raw_rows = get_post_meta($post_id, 'dest_faq', true);
        }

        $raw_rows = maybe_unserialize($raw_rows);
        $rows = [];

        // Traitement du résultat (Tableau natif WP ou JSON échappé Vue.js)
        if (is_array($raw_rows)) {
            $rows = $raw_rows;
        } elseif (is_string($raw_rows)) {
            $clean_str = stripslashes(trim($raw_rows));

            if (strpos($clean_str, '[') === 0) {
                $decoded = json_decode($clean_str, true);
                if (is_array($decoded)) {
                    $rows = $decoded;
                }
            }
        }

        if (empty($rows) || !is_array($rows)) {
            return '';
        }

        // Délégation du rendu HTML à notre Helper ultra robuste
        return PC_FAQ_Render_Helper::render_accordion($rows, [
            'title' => $atts['title']
        ]);
    }
}
