<?php

/**
 * Composant Shortcode : Description de la Destination [pc_destination_description]
 * Design : Texte extensible avec dégradé (Connecté à pc-base.css)
 *
 * @package PC_Destination
 */

if (!defined('ABSPATH')) {
    exit; // Sécurité
}

class PC_Destination_Description_Shortcode
{
    public function __construct()
    {
        add_shortcode('pc_destination_description', [$this, 'render']);
    }

    /**
     * Rendu HTML du shortcode.
     */
    public function render($atts = [])
    {
        $post_id = get_the_ID();
        if (!$post_id) return '';

        // Définition des attributs par défaut
        $atts = shortcode_atts([
            'max' => '250px',
            'bg'  => '#f9f9f9', // Couleur de fond par défaut qui matche avec var(--pc-bg-page)
        ], $atts);

        // Récupération intelligente du texte : Custom Field d'abord, puis le Content classique en fallback
        $content = class_exists('PCR_Fields') ? PCR_Fields::get('dest_intro', $post_id) : get_field('dest_intro', $post_id);

        if (empty(trim((string)$content))) {
            $content = get_the_content();
        }

        $html = wpautop($content);

        // Si pas de contenu, on n'affiche rien
        if (empty(trim($html))) {
            return '';
        }

        // Préparation des variables CSS
        $vars = [];
        if (!empty($atts['max'])) {
            $vars[] = "--dest-desc-max: " . esc_attr($atts['max']);
        }
        if (!empty($atts['bg'])) {
            $vars[] = "--dest-desc-bg: " . esc_attr($atts['bg']);
        }

        $style_attr = $vars ? ' style="' . implode(';', $vars) . '"' : '';
        $id = 'dest-desc-' . wp_rand(1000, 9999);

        ob_start(); ?>

        <section id="<?php echo esc_attr($id); ?>" class="dest-desc-box" <?php echo $style_attr; ?>>
            <div class="dest-desc__content" aria-expanded="false">
                <?php echo $html; ?>
            </div>
            <div class="dest-desc__fade" aria-hidden="true"></div>
            <button type="button" class="dest-desc__toggle" data-more="Voir plus" data-less="Voir moins">
                Voir plus
            </button>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var box = document.getElementById('<?php echo esc_js($id); ?>');
                    if (!box) return;
                    var btn = box.querySelector('.dest-desc__toggle');
                    var content = box.querySelector('.dest-desc__content');
                    if (!btn || !content) return;

                    btn.addEventListener('click', function() {
                        var isOpen = box.classList.toggle('is-open');
                        content.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                        btn.textContent = isOpen ? (btn.getAttribute('data-less') || 'Voir moins') :
                            (btn.getAttribute('data-more') || 'Voir plus');
                    });
                });
            </script>
        </section>

        <style>
            /* Variables par défaut et Raccordement à pc-base.css */
            .dest-desc-box {
                --dest-desc-max: 250px;
                --dest-desc-bg: var(--pc-bg-page, #f9f9f9);
                /* Raccordé à pc-base.css */
                --dest-desc-fsize: var(--pc-text-size, 1.125rem);
                --dest-desc-lh: var(--pc-text-lh, 1.6em);
                --dest-desc-btn-h: 40px;
                --dest-desc-btn-gap: 12px;

                position: relative;
                font-family: var(--pc-font-body, "Lora", serif);
                /* Raccordé à pc-base.css */
                color: var(--pc-color-text, #3a3a3a);
                /* Raccordé à pc-base.css */
                margin-bottom: 2rem;
            }

            .dest-desc__content {
                max-height: var(--dest-desc-max);
                overflow: hidden;
                font-size: var(--dest-desc-fsize);
                line-height: var(--dest-desc-lh);
                transition: max-height 0.4s ease-in-out;
            }

            .dest-desc__content>*:first-child {
                margin-top: 0;
            }

            .dest-desc__fade {
                position: absolute;
                left: 0;
                right: 0;
                bottom: calc(var(--dest-desc-btn-h) + var(--dest-desc-btn-gap));
                height: 72px;
                background: linear-gradient(to bottom,
                        transparent 0%,
                        var(--dest-desc-bg) 95%);
                pointer-events: none;
                transition: opacity 0.3s;
            }

            .dest-desc__toggle {
                display: inline-flex;
                align-items: center;
                min-height: var(--dest-desc-btn-h);
                margin-top: var(--dest-desc-btn-gap);
                padding: 0;
                background: none;
                border: 0;
                cursor: pointer;
                font-weight: 700;
                font-family: var(--pc-font-title, "Poppins", sans-serif);
                /* Raccordé à pc-base.css */
                color: var(--pc-color-primary, #007a92);
                /* Raccordé à pc-base.css */
                text-decoration: underline;
                text-underline-offset: 4px;
                text-decoration-thickness: 2px;
                transition: color 0.2s;
            }

            .dest-desc__toggle:hover {
                color: var(--pc-color-primary-hover, #005f73);
                /* Raccordé à pc-base.css */
            }

            /* États "Ouvert" */
            .dest-desc-box.is-open .dest-desc__content {
                max-height: 10000px;
            }

            .dest-desc-box.is-open .dest-desc__fade {
                opacity: 0;
            }

            .dest-desc-box.is-open .dest-desc__toggle {
                text-decoration: none;
            }
        </style>

<?php return ob_get_clean();
    }
}
