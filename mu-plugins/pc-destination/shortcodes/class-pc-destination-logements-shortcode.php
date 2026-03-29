<?php

/**
 * Module : Shortcode Logements [pc_destination_logements]
 * Affiche la grille des logements rattachés à une destination.
 */

if (!defined('ABSPATH')) {
    exit; // Sécurité
}

class PC_Destination_Logements_Shortcode
{
    /**
     * Enregistre le shortcode
     */
    public function register()
    {
        add_shortcode('pc_destination_logements', [$this, 'render']);
    }

    /**
     * Rendu du shortcode
     */
    public function render($atts)
    {
        $a = shortcode_atts([
            'id'       => get_queried_object_id(),
            'per_page' => 12,
            'orderby'  => 'date',
            'order'    => 'DESC',
        ], $atts, 'pc_destination_logements');

        $id = PC_Destination_Query_Helper::safe_int($a['id'], get_queried_object_id());
        if (!$id) {
            return '';
        }

        // --- 1. DÉCODEUR V3 HYBRIDE (PCR_Fields + Fallback Natif) ---
        $raw_ids = class_exists('PCR_Fields') ? PCR_Fields::get('dest_logements_recommandes', $id) : null;

        // Fallback sur le champ natif si PCR_Fields est vide ou ACF désactivé
        if (empty($raw_ids)) {
            $raw_ids = get_post_meta($id, 'dest_logements_recommandes', true);
        }

        // Désérialisation au cas où WP l'aurait stocké en serialized array
        $raw_ids = maybe_unserialize($raw_ids);

        $recommended_ids = [];

        if (is_array($raw_ids)) {
            $recommended_ids = $raw_ids;
        } elseif (is_string($raw_ids)) {
            $clean_str = stripslashes(trim($raw_ids));

            if (strpos($clean_str, '[') === 0) {
                $decoded = json_decode($clean_str, true);
                if (is_array($decoded)) {
                    $recommended_ids = $decoded;
                }
            } else {
                $recommended_ids = explode(',', $clean_str);
            }
        }

        // Sécurisation : on ne garde que les entiers valides supérieurs à 0
        $recommended_ids = array_filter(array_map('intval', $recommended_ids), function ($val) {
            return $val > 0;
        });

        if (empty($recommended_ids)) {
            return ''; // Stop ici si aucun ID valide
        }

        // --- 2. REQUÊTE WORDPRESS ---
        // 💡 CORRECTION DU POST_TYPE : Utilisation de 'villa' et 'appartement' comme dans les Expériences !
        $args = [
            'post_type'      => ['villa', 'appartement'],
            'post__in'       => $recommended_ids,
            'orderby'        => 'post__in',
            'posts_per_page' => class_exists('PC_Destination_Query_Helper') ? PC_Destination_Query_Helper::safe_int($a['per_page'], 12) : 12,
            'post_status'    => 'publish'
        ];

        $query = new WP_Query($args);

        if (!$query->have_posts()) {
            return '';
        }

        // --- 3. RENDU HTML ET CSS EMBARQUÉ ---
        ob_start(); ?>

        <section id="logements" class="dest-reco-section pc-dest-logements">
            <div class="dest-reco-grid">
                <?php while ($query->have_posts()) : $query->the_post(); ?>
                    <div class="dest-reco-card">
                        <a href="<?php the_permalink(); ?>" class="dest-reco-card-link">

                            <?php if (has_post_thumbnail()) : ?>
                                <div class="dest-reco-card-image">
                                    <?php the_post_thumbnail('medium_large'); ?>
                                </div>
                            <?php endif; ?>

                            <div class="dest-reco-card-content">
                                <h3 class="dest-reco-card-title"><?php the_title(); ?></h3>
                                <?php
                                $price_from = class_exists('PCR_Fields') ? PCR_Fields::get('base_price_from', get_the_ID()) : false;
                                if ($price_from) :
                                ?>
                                    <div class="dest-reco-card-price">
                                        À partir de <strong><?php echo esc_html(number_format_i18n($price_from, 0)); ?> €</strong> / nuit
                                    </div>
                                <?php endif; ?>
                            </div>

                        </a>
                    </div>
                <?php endwhile; ?>
            </div>
        </section>

        <style>
            /* CSS Embarqué avec les Tokens Globaux (Prestige Caraïbes V3) */
            .dest-reco-section {
                padding: 3rem 0;
                font-family: var(--pc-font-body, system-ui, sans-serif);
                scroll-margin-top: 100px;
                /* Conserve l'ancre du menu collant */
            }

            .dest-reco-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 2rem;
            }

            .dest-reco-card {
                background: #ffffff;
                border-radius: var(--pc-border-radius, 12px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
                border: 1px solid #e2e8f0;
                transition: transform 0.3s ease, box-shadow 0.3s ease;
                overflow: hidden;
                height: 100%;
                display: flex;
                flex-direction: column;
            }

            .dest-reco-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
            }

            .dest-reco-card-link {
                display: flex;
                flex-direction: column;
                height: 100%;
                text-decoration: none;
                color: inherit;
            }

            .dest-reco-card-image {
                aspect-ratio: 16/10;
                overflow: hidden;
                position: relative;
            }

            .dest-reco-card-image img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                transition: transform 0.5s ease;
            }

            .dest-reco-card:hover .dest-reco-card-image img {
                transform: scale(1.05);
            }

            .dest-reco-card-content {
                padding: 1.5rem;
                display: flex;
                flex-direction: column;
                flex-grow: 1;
                justify-content: space-between;
            }

            .dest-reco-card-title {
                font-family: var(--pc-font-heading, system-ui);
                font-size: 1.15rem;
                font-weight: 600;
                margin: 0 0 1rem 0;
                color: var(--pc-color-heading, #1b3b5f);
                line-height: 1.3;
            }

            .dest-reco-card-price {
                font-size: 1rem;
                color: var(--pc-color-text, #475569);
            }

            .dest-reco-card-price strong {
                color: var(--pc-color-primary, #007a92);
                font-size: 1.1rem;
            }
        </style>

<?php
        wp_reset_postdata();
        return ob_get_clean();
    }
}
