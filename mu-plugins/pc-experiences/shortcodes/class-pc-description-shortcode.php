<?php

/**
 * Shortcode : [experience_description]
 * Affiche la description de l'expérience avec un bouton "Voir plus".
 *
 * @package PC_Experiences
 */

if (!defined('ABSPATH')) {
    exit; // Sécurité
}

class PC_Experience_Description_Shortcode extends PC_Experience_Shortcode_Base
{

    /**
     * Nom du shortcode.
     *
     * @var string
     */
    protected $shortcode_name = 'experience_description';

    /**
     * Définit les attributs par défaut.
     * La classe parente s'occupe de faire le shortcode_atts().
     *
     * @return array
     */
    protected function get_default_atts(): array
    {
        return [
            'max' => '250px',
            'bg'  => '#F9F9F9',
        ];
    }

    /**
     * Rendu HTML du shortcode.
     * La classe parente gère automatiquement le ob_start() et retourne le contenu.
     *
     * @param array $atts Attributs nettoyés et fusionnés.
     */
    protected function render(array $atts): void
    {
        $content = get_the_content();
        $html = wpautop($content);

        // Si pas de contenu, on arrête tout (la classe parente renverra une chaîne vide)
        if (empty(trim($html))) {
            return;
        }

        // Préparation des variables CSS
        $vars = [];
        if (!empty($atts['max'])) {
            $vars[] = "--exp-desc-max: " . esc_attr($atts['max']);
        }
        if (!empty($atts['bg'])) {
            $vars[] = "--exp-desc-bg: " . esc_attr($atts['bg']);
        }

        $style_attr = $vars ? ' style="' . implode(';', $vars) . '"' : '';
        $id = 'exp-desc-' . wp_rand(1000, 9999);

        // --- DÉBUT DU RENDU ---
?>
        <section id="<?php echo esc_attr($id); ?>" class="exp-desc-box" <?php echo $style_attr; ?>>
            <div class="exp-desc__content" aria-expanded="false">
                <?php echo $html; ?>
            </div>
            <div class="exp-desc__fade" aria-hidden="true"></div>
            <button type="button" class="exp-desc__toggle" data-more="Voir plus" data-less="Voir moins">
                Voir plus
            </button>
            <script>
                (function() {
                    var box = document.getElementById('<?php echo esc_js($id); ?>');
                    if (!box) return;
                    var btn = box.querySelector('.exp-desc__toggle');
                    var content = box.querySelector('.exp-desc__content');
                    if (!btn || !content) return;
                    btn.addEventListener('click', function() {
                        var isOpen = box.classList.toggle('is-open');
                        content.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                        btn.textContent = isOpen ? (btn.getAttribute('data-less') || 'Voir moins') :
                            (btn.getAttribute('data-more') || 'Voir plus');
                    });
                })();
            </script>
        </section>
<?php
        // --- FIN DU RENDU ---
    }
}
