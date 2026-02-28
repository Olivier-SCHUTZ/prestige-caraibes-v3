<?php

/**
 * Classe de base abstraite pour tous les shortcodes d'expériences.
 * Fournit la structure commune pour éviter la duplication de code.
 *
 * @package PC_Experiences
 */

if (!defined('ABSPATH')) {
    exit; // Sécurité
}

abstract class PC_Experience_Shortcode_Base
{

    /**
     * Nom du shortcode (ex: 'experience_description').
     * Doit être défini obligatoirement dans la classe enfant.
     *
     * @var string
     */
    protected $shortcode_name;

    /**
     * Constructeur : enregistre automatiquement le shortcode auprès de WordPress.
     */
    public function __construct()
    {
        if (!empty($this->shortcode_name)) {
            add_shortcode($this->shortcode_name, [$this, 'render_shortcode']);
        }
    }

    /**
     * Wrapper principal appelé par WordPress.
     * Gère les vérifications, les attributs et la bufferisation.
     *
     * @param array|string $atts Attributs saisis par l'utilisateur.
     * @return string Le code HTML généré.
     */
    public function render_shortcode($atts): string
    {
        // Validation stricte du contexte (sécurité et performance)
        if (!$this->validate_experience_context()) {
            return '';
        }

        // Nettoyage et fusion des attributs
        $atts_array = is_array($atts) ? $atts : [];
        $clean_atts = $this->sanitize_atts($atts_array);

        // Bufferisation automatique du rendu
        ob_start();
        $this->render($clean_atts);
        return ob_get_clean() ?: '';
    }

    /**
     * Méthode de rendu à implémenter dans chaque classe enfant.
     * C'est ici que tu feras tes "echo" de HTML.
     *
     * @param array $atts Attributs propres et validés.
     */
    abstract protected function render(array $atts): void;

    /**
     * Vérifie si l'on se trouve dans le bon contexte pour exécuter le shortcode.
     * Peut être surchargé si un shortcode spécifique a des besoins différents.
     *
     * @return bool
     */
    protected function validate_experience_context(): bool
    {
        return is_singular('experience') && function_exists('get_field');
    }

    /**
     * Récupère l'ID de l'expérience courante en toute sécurité.
     *
     * @return int|null
     */
    protected function get_experience_id(): ?int
    {
        return is_singular('experience') ? get_the_ID() : null;
    }

    /**
     * Définit les attributs par défaut du shortcode.
     * À surcharger dans les classes enfants si nécessaire.
     *
     * @return array
     */
    protected function get_default_atts(): array
    {
        return [];
    }

    /**
     * Fusionne les attributs utilisateurs avec les valeurs par défaut.
     *
     * @param array $atts
     * @return array
     */
    protected function sanitize_atts(array $atts): array
    {
        $defaults = $this->get_default_atts();
        if (empty($defaults)) {
            return $atts;
        }
        return shortcode_atts($defaults, $atts, $this->shortcode_name);
    }
}
