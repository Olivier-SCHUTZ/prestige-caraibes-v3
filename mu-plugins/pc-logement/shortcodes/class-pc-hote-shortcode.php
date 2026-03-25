<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode d'affichage du profil de l'hôte.
 * Utilise la donnée native via PCR_Fields.
 * Shortcode : [pc_fiche_hote]
 */
class PC_Hote_Shortcode
{
    /**
     * Enregistrement du shortcode (Format standard V3).
     */
    public function register()
    {
        add_shortcode('pc_fiche_hote', [$this, 'render']);
    }

    /**
     * Rendu du shortcode.
     */
    public function render($atts)
    {
        // Permet de passer un ID spécifique, sinon prend la page courante
        $atts = shortcode_atts([
            'id' => 0,
        ], $atts);

        $post_id = !empty($atts['id']) ? (int) $atts['id'] : get_the_ID();

        // RÈGLE D'OR : On s'assure que notre classe d'abstraction est bien là
        if (!$post_id || !class_exists('PCR_Fields')) {
            return '';
        }

        // 1. Récupération des données 100% compatibles V3
        $hote_nom         = PCR_Fields::get('hote_nom', $post_id);
        $hote_description = PCR_Fields::get('hote_description', $post_id);
        $hote_photo_id    = PCR_Fields::get('hote_photo', $post_id);

        // Si on n'a ni nom ni description, inutile d'afficher la carte
        if (empty($hote_nom) && empty($hote_description)) {
            return '';
        }

        // 2. Traitement de l'image (Native WordPress)
        $photo_html = '';
        if ($hote_photo_id) {
            // wp_get_attachment_image gère tout : alt, srcset, sizes, lazy loading !
            $photo_html = wp_get_attachment_image($hote_photo_id, 'medium', false, ['class' => 'pc-host-avatar']);
        } else {
            // Avatar SVG par défaut si l'hôte n'a pas mis de photo
            $photo_html = '<div class="pc-host-avatar-placeholder">
                <svg fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"></path>
                </svg>
            </div>';
        }

        // 3. Rendu HTML & CSS
        ob_start();
?>
        <div class="pc-host-profile-wrapper">
            <div class="pc-host-card">

                <div class="pc-host-image-col">
                    <div class="pc-host-avatar-wrapper">
                        <?php echo $photo_html; ?>
                    </div>
                </div>

                <div class="pc-host-content-col">
                    <h3 class="pc-host-title">
                        Votre hôte sur place, <span class="pc-host-name"><?php echo esc_html($hote_nom); ?></span>
                    </h3>

                    <div class="pc-host-description">
                        <?php
                        // wpautop ajoute automatiquement des balises <p> pour les sauts de ligne de la description
                        echo wp_kses_post(wpautop($hote_description));
                        ?>
                    </div>
                </div>

            </div>
        </div>

        <style>
            /* Utilisation des variables globales de pc-base.css pour une intégration parfaite */
            .pc-host-profile-wrapper {
                margin: 2rem 0;
                font-family: var(--pc-font-family-body, system-ui, -apple-system, sans-serif);
            }

            .pc-host-card {
                display: flex;
                flex-direction: column;
                background: #ffffff;
                border-radius: var(--pc-border-radius, 16px);
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
                overflow: hidden;
                border: 1px solid #e2e8f0;
                transition: transform 0.3s ease, box-shadow 0.3s ease;
            }

            .pc-host-card:hover {
                box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08);
            }

            /* Responsive : passe en ligne sur tablette/bureau */
            @media (min-width: 768px) {
                .pc-host-card {
                    flex-direction: row;
                    align-items: flex-start;
                    padding: 2.5rem;
                    gap: 2.5rem;
                }
            }

            .pc-host-image-col {
                padding: 2rem 2rem 0 2rem;
                display: flex;
                justify-content: center;
            }

            @media (min-width: 768px) {
                .pc-host-image-col {
                    padding: 0;
                    flex-shrink: 0;
                }
            }

            .pc-host-avatar-wrapper {
                position: relative;
            }

            .pc-host-avatar {
                width: 140px;
                height: 140px;
                border-radius: 50%;
                object-fit: cover;
                border: 4px solid #ffffff;
                box-shadow: 0 8px 20px rgba(14, 43, 92, 0.12);
                /* Utilise un ton de ton bleu corporate */
            }

            .pc-host-avatar-placeholder {
                width: 140px;
                height: 140px;
                border-radius: 50%;
                background: #f1f5f9;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #94a3b8;
                border: 4px solid #ffffff;
                box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            }

            .pc-host-avatar-placeholder svg {
                width: 60px;
                height: 60px;
            }

            .pc-host-content-col {
                padding: 2rem;
            }

            @media (min-width: 768px) {
                .pc-host-content-col {
                    padding: 0;
                }
            }

            .pc-host-title {
                font-size: 1.4rem;
                color: #334155;
                margin-top: 0;
                margin-bottom: 1rem;
                font-family: var(--pc-font-family-heading, inherit);
                font-weight: 500;
            }

            .pc-host-name {
                color: var(--pc-primary, #0e2b5c);
                font-weight: 700;
                display: block;
                /* Le nom passe en dessous pour plus d'impact */
                font-size: 1.8rem;
                margin-top: 0.2rem;
            }

            .pc-host-description {
                color: #475569;
                line-height: 1.7;
                font-size: 1rem;
            }

            .pc-host-description p {
                margin-top: 0;
                margin-bottom: 1rem;
            }

            .pc-host-description p:last-child {
                margin-bottom: 0;
            }
        </style>
<?php
        return ob_get_clean();
    }
}
