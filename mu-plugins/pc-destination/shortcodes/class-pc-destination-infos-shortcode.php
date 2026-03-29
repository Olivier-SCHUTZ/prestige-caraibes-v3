<?php

/**
 * Module : Shortcode Informations Pratiques
 * Affiche les blocs "Informations pratiques" d'une fiche Destination.
 */

if (!defined('ABSPATH')) {
    exit; // Sécurité
}

class PC_Destination_Infos_Shortcode
{
    /**
     * Enregistre le shortcode
     */
    public function register()
    {
        add_shortcode('destination_infos', [$this, 'render']);
    }

    /**
     * Rendu du shortcode [destination_infos]
     */
    public function render($atts)
    {
        // Plus de dépendance forcée à ACF
        if (!is_singular('destination')) {
            return '';
        }

        $a = shortcode_atts([
            'title'        => 'Informations pratiques',
            'cols_desktop' => '3', // Conservé pour la rétrocompatibilité mais géré en CSS désormais
            'cols_tablet'  => '2',
            'cols_mobile'  => '1',
            'max'          => '0',
            'order'        => 'as_entered', // alpha pour tri par titre
            'anchor'       => '',
        ], $atts, 'destination_infos');

        $pid = get_queried_object_id();

        // --- 1. DÉCODEUR V3 HYBRIDE (Champs Répéteur) ---
        $rows = class_exists('PCR_Fields') ? PCR_Fields::get('dest_infos', $pid) : null;
        if (empty($rows)) {
            $rows = get_post_meta($pid, 'dest_infos', true);
        }

        // Nettoyage et désérialisation du champ répéteur Vue.js / WP
        if (is_string($rows)) {
            $clean_str = stripslashes(trim($rows));
            if (strpos($clean_str, '[') === 0) {
                $decoded = json_decode($clean_str, true);
                if (is_array($decoded)) {
                    $rows = $decoded;
                }
            } else {
                $rows = maybe_unserialize($rows);
            }
        }

        if (empty($rows) || !is_array($rows)) {
            return '';
        }

        // Tri optionnel "alpha" par titre
        if (strtolower($a['order']) === 'alpha') {
            usort($rows, function ($x, $y) {
                $tx = isset($x['titre']) ? remove_accents(wp_strip_all_tags($x['titre'])) : '';
                $ty = isset($y['titre']) ? remove_accents(wp_strip_all_tags($y['titre'])) : '';
                return strcasecmp($tx, $ty);
            });
        }

        // Limite optionnelle
        $max = intval($a['max']);
        if ($max > 0 && count($rows) > $max) {
            $rows = array_slice($rows, 0, $max);
        }

        // Mapping sémantique → icônes FA (fallback si vide)
        $map_icons = [
            'plage|bord de mer|plages'     => 'fa-solid fa-umbrella-beach',
            'restaurant|resto|bars'        => 'fa-solid fa-utensils',
            'marché|marche|marches'        => 'fa-solid fa-basket-shopping',
            'golf'                          => 'fa-solid fa-golf-ball-tee',
            'marina|port'                   => 'fa-solid fa-anchor',
            'activite|activites|loisir'     => 'fa-solid fa-person-swimming',
            'randonnee|rando|sentier'       => 'fa-solid fa-person-hiking',
            'transport|bus|taxi|aeroport'   => 'fa-solid fa-bus',
            'famille|enfant'                => 'fa-solid fa-children',
            'commerce|supermarche|boutique' => 'fa-solid fa-store',
            'securite|sante|pharmacie'      => 'fa-solid fa-shield-heart',
            'parking|stationnement'         => 'fa-solid fa-square-parking',
            'meteo|saison|periode'          => 'fa-solid fa-sun',
            'distance|acces|localisation'   => 'fa-solid fa-location-dot',
        ];
        $fallback_icon = 'fa-solid fa-circle-info';

        // Normalisation pour la détection de mots-clés
        $normalize = function ($s) {
            $s = remove_accents(wp_strip_all_tags((string)$s));
            $s = strtolower($s);
            return $s;
        };

        $section_id = sanitize_title($a['anchor']);
        $title      = $a['title'];

        ob_start();
?>
        <section class="pc-dest-infos-wrapper pc-equipements-wrapper" <?php echo $section_id ? 'id="' . esc_attr($section_id) . '"' : ''; ?> aria-labelledby="dest-infos-title">
            <h3 id="dest-infos-title" class="dest-infos-title"><?php echo esc_html($title); ?></h3>

            <div class="pc-equipements-grid">

                <?php foreach ($rows as $row):
                    $titre   = isset($row['titre']) ? trim($row['titre']) : '';
                    $contenu = isset($row['contenu']) ? $row['contenu'] : '';
                    $icone   = isset($row['icone']) ? trim($row['icone']) : '';

                    // Ignore si tout est vide
                    if ($titre === '' && trim(wp_strip_all_tags($contenu)) === '') continue;

                    // Icône: Vue.js/ACF > mapping par mots-clés > fallback
                    if ($icone === '') {
                        $t = $normalize($titre);
                        $picked = '';
                        foreach ($map_icons as $pattern => $fa) {
                            foreach (explode('|', $pattern) as $needle) {
                                if ($needle !== '' && strpos($t, $needle) !== false) {
                                    $picked = $fa;
                                    break 2;
                                }
                            }
                        }
                        $icone = $picked ?: $fallback_icon;
                    }
                ?>
                    <div class="pc-equip-box">
                        <div class="pc-equip-header">
                            <span class="pc-equip-icon" aria-hidden="true">
                                <i class="<?php echo esc_attr($icone); ?>"></i>
                            </span>
                            <?php if ($titre !== ''): ?>
                                <h4 class="pc-equip-title"><?php echo esc_html($titre); ?></h4>
                            <?php endif; ?>
                        </div>

                        <?php if ($contenu !== ''): ?>
                            <div class="pc-equip-content pc-v3-content-raw pc-list-standard">
                                <?php echo wpautop(wp_kses_post($contenu)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

            </div>
        </section>

        <style>
            /* Base enveloppe & Titre */
            .pc-dest-infos-wrapper {
                margin: 3rem 0;
                scroll-margin-top: 100px;
            }

            .dest-infos-title {
                font-family: var(--pc-font-title, inherit);
                font-size: clamp(1.6rem, 1.2rem + 1vw, 2rem);
                text-align: left;
                margin: 0 0 1.5rem 0;
                color: var(--pc-color-heading, #1b3b5f);
            }

            /* Grille responsive V3 */
            .pc-equipements-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            @media (min-width: 768px) {
                .pc-equipements-grid {
                    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                }
            }

            /* Design de la carte (Box) */
            .pc-equip-box {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: var(--pc-border-radius, 16px);
                padding: 1.5rem;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
                height: 100%;
                transition: transform 0.22s ease, box-shadow 0.22s ease;
                will-change: transform;
            }

            @media (hover: hover) and (pointer: fine) {
                .pc-equip-box:hover {
                    transform: translateY(-4px);
                    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
                }
            }

            /* Header (Icône + Titre) */
            .pc-equip-header {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                margin-bottom: 1rem;
            }

            .pc-equip-icon {
                color: var(--pc-color-primary, #007a92);
                width: 24px;
                height: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 22px;
                /* Adapté pour FontAwesome */
            }

            .pc-equip-title {
                font-family: var(--pc-font-heading, system-ui);
                margin: 0;
                font-size: 1.15rem;
                font-weight: 600;
                color: var(--pc-color-heading, #1b3b5f);
            }

            /* Contenu texte et listes */
            .pc-equip-content {
                line-height: 1.6;
                color: var(--pc-color-text, #3a3a3a);
            }

            .pc-equip-content p {
                margin-bottom: 0.75rem;
            }

            .pc-equip-content ul {
                list-style: none;
                padding: 0;
                margin: 0;
            }

            .pc-equip-content li {
                position: relative;
                padding-left: 1.5rem;
                margin-bottom: 0.5rem;
            }

            .pc-list-standard li::before {
                content: "•";
                position: absolute;
                left: 0;
                top: 0;
                color: var(--pc-color-primary, #007a92);
                font-weight: bold;
                font-size: 1.2rem;
            }
        </style>
<?php
        return ob_get_clean();
    }
}
