<?php

/**
 * Shortcode : [experience_gallery]
 * Affiche la galerie photo de l'expérience avec GLightbox.
 *
 * @package PC_Experiences
 */

if (!defined('ABSPATH')) {
    exit; // Sécurité
}

class PC_Experience_Gallery_Shortcode extends PC_Experience_Shortcode_Base
{

    /**
     * Nom du shortcode.
     *
     * @var string
     */
    protected $shortcode_name = 'experience_gallery';

    /**
     * Rendu HTML du shortcode.
     * La classe parente a déjà vérifié qu'on est sur une page "experience" et qu'ACF est actif.
     *
     * @param array $atts Attributs (non utilisés ici, mais requis par la signature).
     */
    protected function render(array $atts): void
    {
        // Récupération des images via notre système natif
        $images = PCR_Fields::get('photos_experience');

        // Si pas d'images, on arrête tout
        if (empty($images)) {
            return;
        }

        $experience_id = $this->get_experience_id();
        $gallery_id = 'exp-gallery-' . $experience_id;

        // --- DÉBUT DU RENDU ---
?>
        <section id="<?php echo esc_attr($gallery_id); ?>" class="exp-gallery">
            <div class="exp-gallery-grid">
                <?php foreach ($images as $img_item) :
                    // Décodeur universel V3 : Gestion hybride des IDs d'images
                    $img_id = is_array($img_item) && isset($img_item['ID']) ? $img_item['ID'] : (int) $img_item;

                    if (!$img_id) continue;

                    $full_url = wp_get_attachment_url($img_id);
                    $large_src = wp_get_attachment_image_src($img_id, 'large');
                    $large_url = $large_src ? $large_src[0] : $full_url;
                    $alt_text = get_post_meta($img_id, '_wp_attachment_image_alt', true) ?: get_the_title($img_id);
                ?>
                    <figure class="exp-gallery-item">
                        <a href="<?php echo esc_url($full_url); ?>"
                            class="glightbox"
                            data-gallery="experience-gallery-<?php echo esc_attr($experience_id); ?>">
                            <img src="<?php echo esc_url($large_url); ?>"
                                alt="<?php echo esc_attr($alt_text); ?>"
                                loading="lazy" />
                        </a>
                    </figure>
                <?php endforeach; ?>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    if (typeof GLightbox !== 'undefined' && document.querySelector('#<?php echo esc_js($gallery_id); ?> .glightbox')) {
                        const lightbox = GLightbox({
                            selector: '#<?php echo esc_js($gallery_id); ?> .glightbox',
                            loop: true,
                            touchNavigation: true,
                        });
                    }
                });
            </script>
        </section>
<?php
        // --- FIN DU RENDU ---
    }
}
