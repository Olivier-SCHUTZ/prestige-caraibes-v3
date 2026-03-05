<?php

/**
 * PC UI Shortcode Base
 * Classe abstraite pour uniformiser la création des shortcodes du module UI
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class PC_UI_Shortcode_Base
{

    /**
     * Constructeur : enregistre automatiquement le shortcode
     */
    public function __construct()
    {
        add_shortcode($this->get_tag(), [$this, 'render']);
    }

    /**
     * Définit le tag du shortcode (ex: pc_loop_lodging_card)
     * * @return string
     */
    abstract protected function get_tag();

    /**
     * Logique de rendu du shortcode
     * * @param array $atts Attributs du shortcode
     * @param string|null $content Contenu inclus entre les balises
     * @return string Le code HTML final
     */
    abstract public function render($atts = [], $content = null);
}
