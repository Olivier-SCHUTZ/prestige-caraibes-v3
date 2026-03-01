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
        if (!is_singular('destination') || !function_exists('get_field')) {
            return '';
        }

        $a = shortcode_atts([
            'title'        => 'Informations pratiques',
            'cols_desktop' => '3',
            'cols_tablet'  => '2',
            'cols_mobile'  => '1',
            'max'          => '0',
            'order'        => 'as_entered', // alpha pour tri par titre
            'anchor'       => '',
        ], $atts, 'destination_infos');

        $pid   = get_queried_object_id();
        $rows  = get_field('dest_infos', $pid);

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

        // Normalisation (accents/espaces) pour la détection de mots-clés
        $normalize = function ($s) {
            $s = remove_accents(wp_strip_all_tags((string)$s));
            $s = strtolower($s);
            return $s;
        };

        // Attributs de grille (data-*)
        $cols_mobile  = max(1, intval($a['cols_mobile']));
        $cols_tablet  = max(1, intval($a['cols_tablet']));
        $cols_desktop = max(1, intval($a['cols_desktop']));

        $section_id   = sanitize_title($a['anchor']);
        $title        = $a['title'];

        ob_start();
?>
        <section class="dest-infos-section" role="region" <?php echo $section_id ? 'id="' . esc_attr($section_id) . '"' : ''; ?> aria-labelledby="dest-infos-title">
            <h3 id="dest-infos-title" class="dest-infos-title"><?php echo esc_html($title); ?></h3>

            <div class="dest-infos-grid"
                data-cols-mobile="<?php echo esc_attr($cols_mobile); ?>"
                data-cols-tablet="<?php echo esc_attr($cols_tablet); ?>"
                data-cols-desktop="<?php echo esc_attr($cols_desktop); ?>">

                <?php foreach ($rows as $row):
                    $titre   = isset($row['titre']) ? trim($row['titre']) : '';
                    $contenu = isset($row['contenu']) ? $row['contenu'] : '';
                    $icone   = isset($row['icone']) ? trim($row['icone']) : '';

                    // Ignore si tout est vide
                    if ($titre === '' && trim(wp_strip_all_tags($contenu)) === '') continue;

                    // Icône: ACF > mapping par mots-clés > fallback
                    if ($icone === '') {
                        $t = $normalize($titre);
                        $picked = '';
                        foreach ($map_icons as $pattern => $fa) {
                            $found = false;
                            foreach (explode('|', $pattern) as $needle) {
                                if ($needle !== '' && strpos($t, $needle) !== false) {
                                    $found = true;
                                    break;
                                }
                            }
                            if ($found) {
                                $picked = $fa;
                                break;
                            }
                        }
                        $icone = $picked ?: $fallback_icon;
                    }
                ?>
                    <article class="dest-infos-card">
                        <div class="dest-infos-icon" aria-hidden="true">
                            <i class="<?php echo esc_attr($icone); ?>"></i>
                        </div>

                        <?php if ($titre !== ''): ?>
                            <h4 class="dest-infos-card-title"><?php echo esc_html($titre); ?></h4>
                        <?php endif; ?>

                        <?php if ($contenu !== ''): ?>
                            <div class="dest-infos-card-content">
                                <?php echo wp_kses_post($contenu); ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>

            </div>
        </section>
<?php
        return ob_get_clean();
    }
}
