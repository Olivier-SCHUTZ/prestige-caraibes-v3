<?php

/**
 * Module : Shortcodes Utilitaires pour la réservation
 * Regroupe : [pc_return_url], [pc_title], [pc_acf], [pc_return_x], [pc_return_button]
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Utils_Shortcodes
{

    /**
     * Enregistre tous les shortcodes utilitaires
     */
    public function register()
    {
        add_shortcode('pc_return_url',    [$this, 'render_return_url']);
        add_shortcode('pc_title',         [$this, 'render_title']);
        add_shortcode('pc_acf',           [$this, 'render_acf']);
        add_shortcode('pc_return_x',      [$this, 'render_return_x']);
        add_shortcode('pc_return_button', [$this, 'render_return_button']);
    }

    /**
     * [pc_return_url] : Retourne l'URL de la fiche logement
     */
    public function render_return_url()
    {
        $id = isset($_GET['l']) ? absint($_GET['l']) : 0;
        return $id ? esc_url(get_permalink($id)) : esc_url(home_url('/'));
    }

    /**
     * [pc_title] : Retourne le titre du logement
     */
    public function render_title()
    {
        $id = isset($_GET['l']) ? absint($_GET['l']) : 0;
        return $id ? esc_html(get_the_title($id)) : '';
    }

    /**
     * [pc_acf field="nom_du_champ"] : Retourne la valeur texte d'un champ du logement
     */
    public function render_acf($atts)
    {
        $a = shortcode_atts(['field' => '', 'default' => ''], $atts);
        $id = isset($_GET['l']) ? absint($_GET['l']) : 0;

        if (!$id || empty($a['field']) || !class_exists('PCR_Fields')) {
            return esc_html($a['default']);
        }

        $val = PCR_Fields::get($a['field'], $id);

        if (is_array($val)) {
            $val = implode(', ', array_map('trim', $val));
        }

        return ($val !== '' && $val !== null) ? esc_html($val) : esc_html($a['default']);
    }

    /**
     * [pc_return_x] : Affiche la croix flottante pour fermer la page de devis/réservation
     */
    public function render_return_x($atts)
    {
        $a = shortcode_atts([
            'fixed' => '1',
            'title' => 'Fermer et revenir à la fiche'
        ], $atts, 'pc_return_x');

        $url = $this->render_return_url();

        ob_start(); ?>
        <a href="<?php echo $url; ?>" class="pcbk-close-x" aria-label="<?php echo esc_attr($a['title']); ?>" title="<?php echo esc_attr($a['title']); ?>">×</a>
        <?php if ($a['fixed'] === '1'): ?>
            <style>
                .pcbk-close-x {
                    position: fixed;
                    top: 12px;
                    right: 12px;
                    width: 40px;
                    height: 40px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: #f9f9f9;
                    color: #0e2b5c;
                    text-decoration: none;
                    border-radius: 50%;
                    box-shadow: 0 6px 18px rgba(0, 0, 0, .12);
                    z-index: 10000;
                    font-size: 24px;
                    line-height: 1;
                }

                .pcbk-close-x:hover {
                    background: #005F73;
                    color: #fff;
                }

                @media (max-width:480px) {
                    .pcbk-close-x {
                        top: 8px;
                        right: 8px;
                        width: 36px;
                        height: 36px;
                        font-size: 22px;
                    }
                }
            </style>
        <?php endif;
        return ob_get_clean();
    }

    /**
     * [pc_return_button] : Affiche un bouton "Retour à la fiche"
     */
    public function render_return_button($atts)
    {
        $a = shortcode_atts([
            'label' => '← Retour à la fiche'
        ], $atts, 'pc_return_button');

        $url = $this->render_return_url();

        ob_start(); ?>
        <a href="<?php echo $url; ?>" class="pcbk-backbtn"><?php echo esc_html($a['label']); ?></a>
        <style>
            .pcbk-backbtn {
                display: inline-block;
                padding: 12px 18px;
                background: #005F73;
                color: #fff !important;
                border-radius: 14px;
                text-decoration: none;
                font-weight: 600;
            }

            .pcbk-backbtn:hover {
                background: #007A92;
                color: #fff !important;
            }
        </style>
<?php return ob_get_clean();
    }
}
