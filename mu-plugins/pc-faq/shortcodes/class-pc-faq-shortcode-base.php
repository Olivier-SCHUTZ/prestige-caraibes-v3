<?php

/**
 * Classe de base (abstraite) pour tous les shortcodes de FAQ.
 * Centralise l'enregistrement et les méthodes utilitaires communes.
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class PC_FAQ_Shortcode_Base
{
    /**
     * Enregistre le shortcode dans WordPress
     */
    public function register()
    {
        add_shortcode($this->get_tag(), [$this, 'render_shortcode']);
    }

    /**
     * Doit retourner le nom du shortcode (ex: 'logement_faq')
     *
     * @return string
     */
    abstract protected function get_tag();

    /**
     * Logique principale du shortcode à implémenter par les classes enfants
     *
     * @param array $atts
     * @param string|null $content
     * @return string
     */
    abstract protected function render($atts, $content = null);

    /**
     * Wrapper de rendu pour faire le lien avec l'API WordPress.
     *
     * @param array|string $atts
     * @param string|null $content
     * @return string
     */
    public function render_shortcode($atts, $content = null)
    {
        // Normalisation des attributs (WordPress peut envoyer une string vide au lieu d'un array)
        $atts = is_array($atts) ? $atts : [];

        return $this->render($atts, $content);
    }

    /**
     * Helper pour récupérer le Post ID de manière sécurisée et contextuelle.
     * Cette logique était dupliquée dans chaque ancien shortcode.
     *
     * @param array $atts Attributs du shortcode
     * @param array|string $allowed_post_types Types de post autorisés (ex: ['villa', 'appartement'])
     * @return int|false Retourne le Post ID valide ou false
     */
    protected function get_post_id($atts, $allowed_post_types = [])
    {
        $post_id = isset($atts['post_id']) ? (int) $atts['post_id'] : 0;

        if (!$post_id) {
            // Si on force un type de post et qu'on n'y est pas, on annule
            if (!empty($allowed_post_types) && !is_singular($allowed_post_types)) {
                return false;
            }
            $post_id = get_queried_object_id();
        }

        return $post_id ?: false;
    }
}
