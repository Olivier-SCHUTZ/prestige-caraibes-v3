<?php

/**
 * Classe de base commune pour tous les shortcodes de recherche
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class PC_Search_Shortcode_Base
{
    /**
     * Doit retourner le nom du shortcode (ex: 'barre_recherche_precise')
     * @return string
     */
    abstract protected function get_shortcode_tag(): string;

    /**
     * La fonction principale qui génère le HTML du shortcode
     * @param array|string $atts Attributs du shortcode
     * @return string
     */
    abstract public function render($atts = []): string;

    /**
     * Enregistre le shortcode auprès de WordPress
     */
    public function register(): void
    {
        add_shortcode($this->get_shortcode_tag(), [$this, 'render']);
    }
}
