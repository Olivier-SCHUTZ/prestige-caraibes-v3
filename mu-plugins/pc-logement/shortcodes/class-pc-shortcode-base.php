<?php

/**
 * Classe abstraite de base pour tous les shortcodes PC Logement.
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class PC_Shortcode_Base
{

    /**
     * Le tag du shortcode (ex: 'pc_gallery')
     * @var string
     */
    protected $tag;

    /**
     * Les attributs par défaut du shortcode
     * @var array
     */
    protected $default_atts = [];

    /**
     * Enregistre le shortcode et ses hooks associés.
     */
    public function register()
    {
        if (empty($this->tag)) {
            return;
        }

        add_shortcode($this->tag, [$this, 'handle_shortcode']);

        // Hook pour charger les assets conditionnellement
        add_action('wp_enqueue_scripts', [$this, 'conditional_enqueue'], 20);
    }

    /**
     * Le handler principal appelé par WordPress lors de la lecture du shortcode.
     */
    public function handle_shortcode($atts, $content = null)
    {
        // Bufferisation pour éviter les erreurs d'affichage (headers already sent)
        ob_start();
        $result = $this->render($atts, $content);
        $output = ob_get_clean();

        // Si la méthode render retourne directement une string, on la prend, sinon on prend le buffer.
        return !empty($result) ? $result : $output;
    }

    /**
     * Vérifie si le shortcode est présent pour charger ses assets spécifiques.
     */
    public function conditional_enqueue()
    {
        global $post;

        // Si nous ne sommes pas sur un post valide, on annule
        if (!is_a($post, 'WP_Post')) {
            return;
        }

        // Si le contenu possède le shortcode, on charge les assets
        if (has_shortcode($post->post_content, $this->tag)) {
            $this->enqueue_assets();
        }
    }

    /**
     * Fusionne et sécurise les attributs saisis par l'utilisateur avec ceux par défaut.
     */
    protected function validate_atts($atts)
    {
        return shortcode_atts($this->default_atts, $atts, $this->tag);
    }

    /**
     * Méthode abstraite : Chaque shortcode enfant DOIT définir son affichage.
     */
    abstract public function render($atts, $content = null);

    /**
     * Méthode abstraite : Chaque shortcode enfant DOIT définir ses scripts/styles.
     */
    abstract protected function enqueue_assets();
}
