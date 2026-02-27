<?php

/**
 * Composant Shortcode : Texte SEO expansible [pc_seo_readmore]
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_SEO_Shortcode extends PC_Shortcode_Base
{

    protected $tag = 'pc_seo_readmore';

    protected $default_atts = [
        'bg'      => '',
        'max'     => '220px',
        'variant' => '',
        'fsize'   => '',
        'lheight' => '',
    ];

    /**
     * Rendu principal du shortcode
     */
    public function render($atts, $content = null)
    {
        $a = $this->validate_atts($atts);
        $post = get_post();

        if (!$post || !function_exists('get_field')) {
            return '';
        }

        // 1. Récupération du contenu
        $html_content = (string) get_field('seo_long_html', $post->ID);
        if ($html_content === '') {
            return '';
        }

        // 2. Construction des styles dynamiques (Variables CSS)
        $vars = $this->build_css_vars($a);
        $style_attr = !empty($vars) ? ' style="' . esc_attr(implode(';', $vars)) . '"' : '';

        // Identifiant unique
        $id = 'pc-seo-' . wp_rand(1000, 999999);

        // 3. Affichage HTML
        ob_start(); ?>
        <section id="<?php echo esc_attr($id); ?>" class="pc-seo-box" <?php echo $style_attr; ?>>
            <div class="pc-seo__content" aria-expanded="false"><?php echo $html_content; ?></div>
            <div class="pc-seo__fade" aria-hidden="true"></div>
            <button type="button" class="pc-seo__toggle" data-more="Voir plus" data-less="Voir moins">Voir plus</button>
            <?php $this->render_inline_script($id); ?>
        </section>
    <?php return ob_get_clean();
    }

    /**
     * Pas d'assets externes globaux pour ce composant
     */
    protected function enqueue_assets()
    {
        return;
    }

    /**
     * Helper : Construit les variables CSS en validant les unités saisies
     */
    private function build_css_vars($a)
    {
        $vars = [];
        $allow_unit = '(?:px|rem|em|%)';

        if ($a['bg'] !== '') {
            $vars[] = "--pc-seo-bg: {$a['bg']}";
        }

        if ($a['max'] !== '') {
            $vars[] = "--pc-seo-max: {$a['max']}";
        }

        if ($a['variant'] === 'sm') {
            $vars[] = "--pc-seo-fsize: 1rem";
            $vars[] = "--pc-seo-lh: 1.7em";
        }

        // Validation stricte des tailles et hauteurs
        $fsize = ($a['fsize'] && preg_match("/^\s*\d*\.?\d+\s*$allow_unit\s*$/", $a['fsize'])) ? trim($a['fsize']) : '';
        if ($fsize) {
            $vars[] = "--pc-seo-fsize: {$fsize}";
        }

        $lheight = ($a['lheight'] && preg_match("/^\s*\d*\.?\d+\s*$allow_unit\s*$/", $a['lheight'])) ? trim($a['lheight']) : '';
        if ($lheight) {
            $vars[] = "--pc-seo-lh: {$lheight}";
        }

        return $vars;
    }

    /**
     * Helper : Injecte le script d'interaction spécifique à ce bloc
     */
    private function render_inline_script($id)
    {
    ?>
        <script>
            (function() {
                var box = document.getElementById('<?php echo esc_js($id); ?>');
                if (!box) return;

                var btn = box.querySelector('.pc-seo__toggle');
                var cnt = box.querySelector('.pc-seo__content');
                if (!btn || !cnt) return;

                btn.addEventListener('click', function() {
                    var open = box.classList.toggle('is-open');
                    cnt.setAttribute('aria-expanded', open ? 'true' : 'false');
                    btn.textContent = open ? (btn.getAttribute('data-less') || 'Voir moins') : (btn.getAttribute('data-more') || 'Voir plus');
                });
            })();
        </script>
<?php
    }
}
