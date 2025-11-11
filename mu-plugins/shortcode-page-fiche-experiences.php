<?php

/**
 * Plugin Name: Prestige Caraïbes — Shortcodes Fiche Expérience
 * Description: Déclare les shortcodes et charge les assets pour les pages "Expérience".
 * Version: 2.0 (Refonte avec Bottom-Sheet)
 */

if (!defined('ABSPATH')) exit;

/**
 * Helper : résout le libellé du type de tarif (supporte "custom")
 */
if (!function_exists('pc_exp_type_label')) {
    /**
     * @param array $row     Ligne du répéteur exp_types_de_tarifs
     * @param array $choices Choices ACF (value => label) du select exp_type
     */
    function pc_exp_type_label(array $row, array $choices = []): string
    {
        $type_value   = isset($row['exp_type']) ? (string)$row['exp_type'] : '';
        $custom_label = isset($row['exp_type_custom']) ? trim((string)$row['exp_type_custom']) : '';

        // Cas "Autre (personnalisé)" avec texte saisi
        if ($type_value === 'custom' && $custom_label !== '') {
            $label = wp_strip_all_tags($custom_label);
            $label = mb_substr($label, 0, 100);
            return $label !== '' ? $label : __('Type personnalisé', 'pc');
        }

        // Libellé depuis choices
        if ($type_value !== '' && isset($choices[$type_value])) {
            return (string)$choices[$type_value];
        }

        // Fallback lisible
        return $type_value !== '' ? ucwords(str_replace(['_', '-'], ' ', $type_value)) : __('Type', 'pc');
    }
}

/**
 * ===================================================================
 * 1. CHARGEMENT DES ASSETS (CSS & JS)
 * ===================================================================
 */
add_action('wp_enqueue_scripts', function () {
    if (is_admin() || !is_singular('experience')) {
        return;
    }

    $css_path = WP_CONTENT_DIR . '/mu-plugins/assets/pc-ui-experiences.css';
    $css_url  = content_url('mu-plugins/assets/pc-ui-experiences.css');

    if (file_exists($css_path)) {
        $css_ver = filemtime($css_path);
        wp_enqueue_style('pc-ui-experiences', $css_url, ['pc-base'], $css_ver);
    }

    $js_path = WP_CONTENT_DIR . '/mu-plugins/assets/pc-fiche-experiences.js';
    if (file_exists($js_path)) {
        $js_ver = filemtime($js_path);
        wp_enqueue_script('pc-fiche-experiences', content_url('mu-plugins/assets/pc-fiche-experiences.js'), [], $js_ver, true);
    }
}, 20);


/**
 * ===================================================================
 * 2. SHORTCODE [experience_description]
 * ===================================================================
 */
add_shortcode('experience_description', function ($atts = []) {
    $a = shortcode_atts([
        'max' => '250px',
        'bg'  => '#F9F9F9',
    ], $atts, 'experience_description');

    $content = get_the_content();
    $html = apply_filters('the_content', $content);

    if (empty(trim($html))) {
        return '';
    }

    $vars = [];
    if (!empty($a['max'])) $vars[] = "--exp-desc-max: " . esc_attr($a['max']);
    if (!empty($a['bg']))  $vars[] = "--exp-desc-bg: " . esc_attr($a['bg']);
    $style_attr = $vars ? ' style="' . implode(';', $vars) . '"' : '';
    $id = 'exp-desc-' . wp_rand(1000, 9999);

    ob_start();
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
    return ob_get_clean();
});

/**
 * ===================================================================
 * 4. SHORTCODE [experience_gallery]
 * ===================================================================
 */
add_shortcode('experience_gallery', function () {
    if (!is_singular('experience') || !function_exists('get_field')) {
        return '';
    }
    $images = get_field('photos_experience');
    if (empty($images)) {
        return '';
    }
    $gallery_id = 'exp-gallery-' . get_the_ID();
    ob_start();
?>
    <section id="<?php echo esc_attr($gallery_id); ?>" class="exp-gallery">
        <div class="exp-gallery-grid">
            <?php foreach ($images as $image) : ?>
                <figure class="exp-gallery-item">
                    <a href="<?php echo esc_url($image['url']); ?>"
                        class="glightbox"
                        data-gallery="experience-gallery-<?php echo get_the_ID(); ?>">
                        <img src="<?php echo esc_url($image['sizes']['large']); ?>"
                            alt="<?php echo esc_attr($image['alt']); ?>"
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
    return ob_get_clean();
});

/**
 * ===================================================================
 * 5. SHORTCODE [experience_map]
 * ===================================================================
 */
add_shortcode('experience_map', function () {
    if (!is_singular('experience') || !function_exists('get_field')) {
        return '';
    }
    $locations = get_field('exp_lieux_horaires_depart');
    if (empty($locations)) {
        return '';
    }
    $locations_data = [];
    foreach ($locations as $loc) {
        $lat = !empty($loc['lat_exp']) ? floatval($loc['lat_exp']) : 0;
        $lon = !empty($loc['longitude']) ? floatval($loc['longitude']) : 0;
        $name = !empty($loc['exp_lieu_depart']) ? $loc['exp_lieu_depart'] : 'Point de départ';

        if ($lat && $lon) {
            $locations_data[] = [
                'lat'  => $lat,
                'lon'  => $lon,
                'name' => $name,
            ];
        }
    }
    if (empty($locations_data)) {
        return '<p>Les coordonnées GPS pour les lieux de départ ne sont pas valides.</p>';
    }
    $map_id = 'exp-map-' . get_the_ID();
    ob_start();
?>
    <section id="emplacement" class="exp-map-section">
        <div class="exp-map-wrap">
            <div id="<?php echo esc_attr($map_id); ?>" class="exp-map"></div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof L === 'undefined') {
                    console.error('Leaflet JS non chargé.');
                    return;
                }
                const mapElement = document.getElementById('<?php echo esc_js($map_id); ?>');
                if (!mapElement) return;
                const locations = <?php echo wp_json_encode($locations_data); ?>;
                const map = L.map(mapElement);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap'
                }).addTo(map);
                const bounds = L.latLngBounds();
                locations.forEach(function(loc) {
                    const marker = L.marker([loc.lat, loc.lon]).addTo(map);
                    marker.bindPopup('<strong>' + loc.name + '</strong>');
                    bounds.extend([loc.lat, loc.lon]);
                });
                if (locations.length > 1) {
                    map.fitBounds(bounds, {
                        padding: [50, 50]
                    });
                } else {
                    map.setView(bounds.getCenter(), 13);
                }
            });
        </script>
    </section>
<?php
    return ob_get_clean();
});

/**
 * ===================================================================
 * 6. SHORTCODE [experience_summary]
 * ===================================================================
 */
add_shortcode('experience_summary', function () {
    if (!is_singular('experience') || !function_exists('get_field')) {
        return '';
    }
    $locations_repeater = get_field('exp_lieux_horaires_depart');
    $location_names = [];
    if (is_array($locations_repeater)) {
        $location_names = wp_list_pluck($locations_repeater, 'exp_lieu_depart');
    }
    $locations_text = !empty($location_names) ? implode(', ', $location_names) : 'Non spécifié';
    $jours_field_object = get_field_object('exp_jour');
    $jours_values = $jours_field_object['value'];
    $jours_choices = $jours_field_object['choices'];
    $jours_labels = [];
    if (is_array($jours_values)) {
        foreach ($jours_values as $value) {
            if (isset($jours_choices[$value])) {
                $jours_labels[] = $jours_choices[$value];
            }
        }
    }
    $jours_depart_text = !empty($jours_labels) ? implode(', ', $jours_labels) : 'jours non spécifiés';
    $horaires_html = '';
    if (is_array($locations_repeater)) {
        foreach ($locations_repeater as $location) {
            $lieu = esc_html($location['exp_lieu_depart']);
            $heure_depart = !empty($location['exp_heure_depart']) ? date('H:i', strtotime($location['exp_heure_depart'])) : '';
            $heure_retour = !empty($location['exp_heure_retour']) ? date('H:i', strtotime($location['exp_heure_retour'])) : '';
            $horaires_html .= '<p>';
            $horaires_html .= 'Départ ' . esc_html($jours_depart_text) . ' de ' . $lieu;
            if ($heure_depart) {
                $horaires_html .= ' à ' . esc_html($heure_depart);
            }
            $horaires_html .= '<br>';
            if ($heure_retour) {
                $horaires_html .= 'Retour vers ' . esc_html($heure_retour);
            }
            $horaires_html .= '</p>';
        }
    }
    $titre_page = get_the_title();
    $periode_field_object = get_field_object('exp_periode');
    $periode_values = $periode_field_object['value'];
    $periode_choices = $periode_field_object['choices'];
    $periode_labels = [];
    if (is_array($periode_values)) {
        foreach ($periode_values as $value) {
            if (isset($periode_choices[$value])) {
                $periode_labels[] = $periode_choices[$value];
            }
        }
    }
    $periode_text = !empty($periode_labels) ? implode(' et ', $periode_labels) : 'période non spécifiée';
    $disponibilite_text = 'Sortie ' . esc_html($titre_page) . ' disponible ' . esc_html($periode_text) . ' (sous réserve d’un nombre de personnes suffisants et des conditions météorologiques favorables)';
    ob_start();
?>
    <section class="exp-summary">
        <div class="exp-summary-row">
            <div class="exp-summary-label">Lieu de départ & de retour</div>
            <div class="exp-summary-data">
                <ul>
                    <li><?php echo esc_html($locations_text); ?></li>
                </ul>
            </div>
        </div>
        <div class="exp-summary-row">
            <div class="exp-summary-label">Heure de départ et retour</div>
            <div class="exp-summary-data"><?php echo $horaires_html; ?></div>
        </div>
        <div class="exp-summary-row">
            <div class="exp-summary-label">Disponibilité</div>
            <div class="exp-summary-data"><?php echo $disponibilite_text; ?></div>
        </div>
    </section>
<?php
    return ob_get_clean();
});

/* ============================================================
 * 7. SHORTCODE [experience_pricing]
 * ============================================================ */
add_shortcode('experience_pricing', function () {
    if (!function_exists('have_rows')) return '';
    if (!have_rows('exp_types_de_tarifs')) return '';

    ob_start(); ?>

    <div class="exp-pricing-grid">
        <?php while (have_rows('exp_types_de_tarifs')) : the_row();
            // Type & label (support "custom")
            $type_field  = get_sub_field_object('exp_type');
            $type_value  = is_array($type_field) ? ($type_field['value'] ?? '') : '';
            $choices     = is_array($type_field) ? ((array)($type_field['choices'] ?? [])) : [];
            $row_payload = [
                'exp_type'        => $type_value,
                'exp_type_custom' => get_sub_field('exp_type_custom'),
            ];
            $type_label = function_exists('pc_exp_type_label')
                ? pc_exp_type_label($row_payload, $choices)
                : (isset($choices[$type_value]) ? $choices[$type_value] : ucfirst((string)$type_value));
        ?>
            <div class="exp-pricing-card">
                <h3 class="exp-pricing-title"><?php echo esc_html($type_label); ?></h3>
                <div class="exp-pricing-body">
                    <?php if ($type_value === 'sur-devis') : ?>
                        <div class="exp-pricing-row on-demand"><?php echo esc_html__('Sur devis', 'pc'); ?></div>
                        <?php else :
                        $lines = (array) get_sub_field('exp_tarifs_lignes');
                        if (!empty($lines)) :
                            foreach ($lines as $ln) :
                                $t     = $ln['type_ligne'] ?? 'personnalise';
                                $price = (float)($ln['tarif_valeur'] ?? 0);
                                $obs   = trim((string)($ln['tarif_observation'] ?? ''));
                                // Libellé par type
                                if ($t === 'adulte') {
                                    $label = __('Adulte', 'pc');
                                } elseif ($t === 'enfant') {
                                    $p = trim((string)($ln['precision_age_enfant'] ?? ''));
                                    $label = $p ? sprintf(__('Enfant (%s)', 'pc'), $p) : __('Enfant', 'pc');
                                } elseif ($t === 'bebe') {
                                    $p = trim((string)($ln['precision_age_bebe'] ?? ''));
                                    $label = $p ? sprintf(__('Bébé (%s)', 'pc'), $p) : __('Bébé', 'pc');
                                } else {
                                    $label = trim((string)($ln['tarif_nom_perso'] ?? '')) ?: __('Forfait', 'pc');
                                }
                                // Prix affiché (bébé=0 => Gratuit)
                                $price_html = ($price == 0 && $t === 'bebe')
                                    ? __('Gratuit', 'pc')
                                    : esc_html(number_format((float)$price, 2, ',', ' ')) . ' €';
                        ?>
                                <div class="exp-pricing-row">
                                    <span class="exp-pricing-label"><?php echo esc_html($label); ?></span>
                                    <span class="exp-pricing-price"><?php echo $price_html; ?></span>
                                </div>
                                <?php if ($obs !== '') : ?>
                                    <div class="exp-pricing-note"><?php echo esc_html($obs); ?></div>
                                <?php endif; ?>
                            <?php endforeach;
                        else : ?>
                            <?php /* Fallback de sécurité : aucune ligne — on n’affiche rien */ ?>
                        <?php endif; ?>

                        <?php // Options tarifaires (inchangé)
                        if (have_rows('exp_options_tarifaires')) : ?>
                            <div class="exp-pricing-options">
                                <div class="exp-pricing-options-title"><?php echo esc_html__('Options', 'pc'); ?></div>
                                <?php while (have_rows('exp_options_tarifaires')) : the_row();
                                    $opt_label = (string) get_sub_field('exp_description_option');
                                    $opt_price = (float) get_sub_field('exp_tarif_option'); ?>
                                    <div class="exp-pricing-row option">
                                        <span class="exp-pricing-label"><?php echo esc_html($opt_label); ?></span>
                                        <span class="exp-pricing-price"><?php echo esc_html(number_format($opt_price, 2, ',', ' ')); ?> €</span>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php endif; ?>

                    <?php endif; // fin sur-devis 
                    ?>
                </div>
            </div>
        <?php endwhile; ?>
    </div>

<?php
    return ob_get_clean();
});

/**
 * ===================================================================
 * 8. SHORTCODE [experience_devis] (Version 3 - Désactivé)
 * ===================================================================
 * NOTE : Ce shortcode est maintenant DÉPLACÉ. 
 * Son contenu est généré par [experience_booking_bar] (N°11)
 * pour être inclus dans la bottom-sheet.
 * On le laisse "vide" pour ne pas casser les pages Elementor existantes.
 */
add_shortcode('experience_devis', function () {
    // Le contenu est maintenant dans le shortcode [experience_booking_bar]
    return '';
});


/**
 * ===================================================================
 * 9. SHORTCODE [experience_inclusions]
 * ===================================================================
 */
add_shortcode('experience_inclusions', function () {
    if (!is_singular('experience') || !function_exists('get_field')) {
        return '';
    }
    if (!wp_style_is('font-awesome-6', 'enqueued')) {
        wp_enqueue_style('font-awesome-6', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css', [], null);
    }
    $prix_comprend = get_field('exp_prix_comprend');
    $prix_ne_comprend_pas = get_field('exp_prix_ne_comprend_pas');
    $a_prevoir_obj = get_field_object('exp_a_prevoir');
    $accessibilite_obj = get_field_object('exp_accessibilite');
    $prix_comprend_html = $prix_comprend ? wpautop($prix_comprend) : '';
    $prix_ne_comprend_pas_html = $prix_ne_comprend_pas ? wpautop($prix_ne_comprend_pas) : '';
    $a_prevoir_html = '';
    if (!empty($a_prevoir_obj['value']) && is_array($a_prevoir_obj['value'])) {
        $a_prevoir_html .= '<ul>';
        foreach ($a_prevoir_obj['value'] as $value) {
            $label = isset($a_prevoir_obj['choices'][$value]) ? $a_prevoir_obj['choices'][$value] : $value;
            $a_prevoir_html .= '<li>' . esc_html($label) . '</li>';
        }
        $a_prevoir_html .= '</ul>';
    }
    $accessibilite_html = '';
    if (!empty($accessibilite_obj['value']) && is_array($accessibilite_obj['value'])) {
        $accessibilite_html .= '<ul>';
        foreach ($accessibilite_obj['value'] as $value) {
            $label = isset($accessibilite_obj['choices'][$value]) ? $accessibilite_obj['choices'][$value] : $value;
            $accessibilite_html .= '<li>' . esc_html($label) . '</li>';
        }
        $accessibilite_html .= '</ul>';
    }
    if (empty($prix_comprend_html) && empty($prix_ne_comprend_pas_html) && empty($a_prevoir_html) && empty($accessibilite_html)) {
        return '';
    }
    ob_start();
?>
    <section class="exp-inclusions-section">
        <div class="exp-inclusions-grid">
            <?php if ($prix_comprend_html) : ?>
                <div class="exp-inclusions-col">
                    <h3 class="exp-inclusions-title" data-icon="comprend"><i class="fas fa-check"></i> Le prix comprend</h3>
                    <div class="exp-inclusions-content"><?php echo $prix_comprend_html; ?></div>
                </div>
            <?php endif; ?>
            <?php if ($prix_ne_comprend_pas_html) : ?>
                <div class="exp-inclusions-col">
                    <h3 class="exp-inclusions-title" data-icon="ne-comprend-pas"><i class="fas fa-times"></i> Le prix ne comprend pas</h3>
                    <div class="exp-inclusions-content"><?php echo $prix_ne_comprend_pas_html; ?></div>
                </div>
            <?php endif; ?>
            <?php if ($accessibilite_html) : ?>
                <div class="exp-inclusions-col">
                    <h3 class="exp-inclusions-title" data-icon="accessibilite"><i class="fas fa-universal-access"></i> Accessibilité</h3>
                    <div class="exp-inclusions-content"><?php echo $accessibilite_html; ?></div>
                </div>
            <?php endif; ?>
            <?php if ($a_prevoir_html) : ?>
                <div class="exp-inclusions-col">
                    <h3 class="exp-inclusions-title" data-icon="a-prevoir"><i class="fas fa-briefcase"></i> À prévoir</h3>
                    <div class="exp-inclusions-content"><?php echo $a_prevoir_html; ?></div>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php
    return ob_get_clean();
});

/**
 * ===================================================================
 * 10. SHORTCODE [experience_logements_recommandes]
 * ===================================================================
 */
add_shortcode('experience_logements_recommandes', function () {
    if (!is_singular('experience') || !function_exists('get_field')) {
        return '';
    }
    $recommended_ids = get_field('exp_logements_recommandes');
    if (empty($recommended_ids)) {
        return '';
    }
    $args = [
        'post_type'      => ['villa', 'appartement'],
        'post__in'       => $recommended_ids,
        'orderby'        => 'post__in',
        'posts_per_page' => -1,
    ];
    $query = new WP_Query($args);
    if (!$query->have_posts()) {
        return '';
    }
    ob_start();
?>
    <section class="exp-reco-section">
        <h2 class="exp-reco-title">Logements recommandés à proximité</h2>
        <div class="exp-reco-grid">
            <?php while ($query->have_posts()) : $query->the_post(); ?>
                <div class="exp-reco-card">
                    <a href="<?php the_permalink(); ?>" class="exp-reco-card-link">
                        <?php if (has_post_thumbnail()) : ?>
                            <div class="exp-reco-card-image">
                                <?php the_post_thumbnail('medium_large'); ?>
                            </div>
                        <?php endif; ?>
                        <div class="exp-reco-card-content">
                            <h3 class="exp-reco-card-title"><?php the_title(); ?></h3>
                            <?php
                            $price_from = get_field('base_price_from', get_the_ID());
                            if ($price_from) :
                            ?>
                                <div class="exp-reco-card-price">
                                    À partir de <?php echo esc_html(number_format_i18n($price_from, 0)); ?>€ / nuit
                                </div>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>
            <?php endwhile; ?>
        </div>
    </section>
<?php
    wp_reset_postdata();
    return ob_get_clean();
});


/**
 * ===================================================================
 * 11. SHORTCODE [experience_booking_bar] (Version 2 - Refonte Bottom-Sheet)
 * ===================================================================
 * Affiche le nouveau conteneur de réservation :
 * 1. Le bouton "Pilule" flottant (déclencheur)
 * 2. La "Bottom-Sheet" (panneau coulissant) qui contient le CALCULATEUR DE DEVIS
 * 3. La "Modale de Contact" (pour finaliser la demande)
 *
 * Ce shortcode remplace et intègre l'ancien [experience_devis]
 */
add_shortcode('experience_booking_bar', function () {
    if (!is_singular('experience') || !function_exists('get_field')) {
        return '';
    }

    $experience_id = get_the_ID();

    // --- Début : On récupère les données pour le calculateur ---
    // (C'est la logique de l'ancien shortcode [experience_devis])

    if (!have_rows('exp_types_de_tarifs')) {
        return '';
    }

    $company_info = [
        'name'    => get_field('pc_org_name', 'option') ?: get_bloginfo('name'),
        'legal'   => get_field('pc_org_legal_name', 'option'),
        'address' => get_field('pc_org_address_street', 'option'),
        'city'    => get_field('pc_org_address_postal', 'option') . ' ' . get_field('pc_org_address_locality', 'option'),
        'phone'   => get_field('pc_org_phone', 'option'),
        'email'   => get_field('pc_org_email', 'option'),
        'vat'     => get_field('pc_org_vat_id', 'option'),
    ];

    // --- Logo PNG “bleu” (URL publique + base64 OPAQUE pour jsPDF) ---
    $uploads  = wp_get_upload_dir();
    $rel_path = '2025/06/Logo-Prestige-Caraibes-bleu.png';
    $company_info['logo'] = trailingslashit($uploads['baseurl']) . $rel_path;

    // Génère un PNG OPAQUE (fond blanc) en base64 pour éviter l'effet "délavé"
    $company_info['logo_data'] = '';
    $abs_path = trailingslashit($uploads['basedir']) . $rel_path;

    if (is_readable($abs_path)) {
        // 1) Charge le PNG source (avec alpha)
        $src = @imagecreatefrompng($abs_path);
        if ($src !== false) {
            $w = imagesx($src);
            $h = imagesy($src);

            // 2) Crée une toile opaque blanche
            $dst = imagecreatetruecolor($w, $h);
            // active le mélange et désactive la conservation d'alpha pour un fond plein
            imagealphablending($dst, true);
            imagesavealpha($dst, false);

            // blanc pur
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefilledrectangle($dst, 0, 0, $w, $h, $white);

            // 3) Copie du logo par-dessus (l'alpha du logo est mixé sur le blanc)
            imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);

            // 4) Encode en PNG (opaque) -> base64
            ob_start();
            imagepng($dst, null, 9);
            $png_data = ob_get_clean();

            if ($png_data !== false) {
                $company_info['logo_data'] = 'data:image/png;base64,' . base64_encode($png_data);
            }

            imagedestroy($dst);
            imagedestroy($src);
        }
    }

    // --- CGV (texte nettoyé) ---
    $terms_raw  = get_field('cgv_experience', 'option'); // WYSIWYG HTML
    $terms_text = $terms_raw ? trim(wp_strip_all_tags(wp_kses_post($terms_raw))) : '';
    $company_info['conditions_generales'] = $terms_text;

    $pricing_data  = [];
    $field_object  = get_field_object('exp_types_de_tarifs');

    // Récup des choices du sous-champ select exp_type (si dispo)
    $exp_type_choices = [];
    if (!empty($field_object['sub_fields'])) {
        foreach ($field_object['sub_fields'] as $sf) {
            if (!empty($sf['name']) && $sf['name'] === 'exp_type' && !empty($sf['choices'])) {
                $exp_type_choices = (array)$sf['choices'];
                break;
            }
        }
    }

    foreach ((array)$field_object['value'] as $row) {
        $type_value = $row['exp_type'] ?? '';
        if (!$type_value) continue;

        // Label résolu (supporte "custom")
        $type_label = pc_exp_type_label($row, $exp_type_choices);

        // Options
        $options = [];
        if (!empty($row['exp_options_tarifaires'])) {
            foreach ($row['exp_options_tarifaires'] as $opt) {
                $options[] = [
                    'label'       => (string)($opt['exp_description_option'] ?? ''),
                    'price'       => (float)($opt['exp_tarif_option'] ?? 0),
                    'enable_qty'  => !empty($opt['option_enable_qty']),
                ];
            }
        }

        // Nouvelles lignes tarifaires
        $lines_raw = isset($row['exp_tarifs_lignes']) ? (array)$row['exp_tarifs_lignes'] : [];
        $lines = [];
        $has_counters = false; // A/E/B visibles si au moins une ligne A/E/B

        foreach ($lines_raw as $ln) {
            $t = $ln['type_ligne'] ?? 'personnalise';
            $entry = [
                'type'        => $t, // adulte | enfant | bebe | personnalise
                'price'       => (float)($ln['tarif_valeur'] ?? 0),
                'label'       => '',
                'observation' => trim((string)($ln['tarif_observation'] ?? '')),
                'precision'   => '',
                'enable_qty'  => false, // par défaut
            ];

            if ($t === 'adulte') {
                $entry['label'] = __('Adulte', 'pc');
                $has_counters = true;
            } elseif ($t === 'enfant') {
                $p = trim((string)($ln['precision_age_enfant'] ?? ''));
                $entry['label'] = $p ? sprintf(__('Enfant (%s)', 'pc'), $p) : __('Enfant', 'pc');
                $entry['precision'] = $p;
                $has_counters = true;
            } elseif ($t === 'bebe') {
                $p = trim((string)($ln['precision_age_bebe'] ?? ''));
                $entry['label'] = $p ? sprintf(__('Bébé (%s)', 'pc'), $p) : __('Bébé', 'pc');
                $entry['precision'] = $p;
                $has_counters = true;
            } else {
                $entry['label'] = trim((string)($ln['tarif_nom_perso'] ?? '')) ?: __('Forfait', 'pc');
                $entry['enable_qty'] = !empty($ln['tarif_enable_qty']);
            }

            $lines[] = $entry;
        }

        $pricing_data[$type_value] = [
            'code'         => $type_value,
            'label'        => $type_label,
            'options'      => $options,
            'lines'        => $lines,
            'has_counters' => $has_counters,
        ];
    }

    $devis_id = 'exp-devis-' . $experience_id;
    // --- Fin : Données pour le calculateur ---


    ob_start();
?>

    <button type="button" class="exp-booking-fab" id="exp-open-devis-sheet-btn">
        <span id="fab-price-display">Simuler un devis</span>
    </button>

    <div class="exp-devis-sheet" id="exp-devis-sheet" role="dialog" aria-modal="true" aria-labelledby="devis-sheet-title" aria-hidden="true">
        <div class="exp-devis-sheet__overlay" data-close-devis-sheet></div>

        <div class="exp-devis-sheet__content" role="document">
            <div class="exp-devis-sheet__header">
                <h3 class="exp-devis-sheet__title" id="devis-sheet-title">Obtenir un devis</h3>
                <button class="exp-devis-sheet__close" aria-label="Fermer" data-close-devis-sheet>×</button>
            </div>

            <div class="exp-devis-sheet__body">

                <div id="<?php echo esc_attr($devis_id); ?>" class="exp-devis-wrap" data-exp-devis='<?php echo esc_attr(wp_json_encode($pricing_data)); ?>' data-label-pending="En attente de devis">

                    <div class="exp-devis-form">
                        <div class="exp-devis-field">
                            <label for="<?php echo esc_attr($devis_id); ?>-type">Type de prestation</label>
                            <select id="<?php echo esc_attr($devis_id); ?>-type" name="devis_type">
                                <?php foreach ($pricing_data as $value => $data): ?>
                                    <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($data['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="exp-devis-counters" id="<?php echo esc_attr($devis_id); ?>-counters">
                            <div class="exp-devis-field">
                                <label for="<?php echo esc_attr($devis_id); ?>-adults">Adultes</label>
                                <input type="number" id="<?php echo esc_attr($devis_id); ?>-adults" name="devis_adults" min="0" value="" placeholder="1">
                            </div>
                            <div class="exp-devis-field">
                                <label for="<?php echo esc_attr($devis_id); ?>-children">Enfants</label>
                                <input type="number" id="<?php echo esc_attr($devis_id); ?>-children" name="devis_children" min="0" placeholder="0">
                            </div>
                            <div class="exp-devis-field">
                                <label for="<?php echo esc_attr($devis_id); ?>-bebes">Bébés</label>
                                <input type="number" id="<?php echo esc_attr($devis_id); ?>-bebes" name="devis_bebes" min="0" placeholder="0">
                            </div>
                        </div>
                    </div>

                    <div class="exp-devis-options" id="<?php echo esc_attr($devis_id); ?>-options"></div>

                    <div class="exp-devis-customqty" id="<?php echo esc_attr($devis_id); ?>-customqty"></div>

                    <div class="exp-devis-result" id="<?php echo esc_attr($devis_id); ?>-result"></div>

                    <div class="exp-devis-error" id="exp-devis-error-msg"></div>

                    <div class="exp-devis-actions">
                        <button type="button" id="<?php echo esc_attr($devis_id); ?>-pdf-btn" class="pc-btn pc-btn--secondary">
                            <i class="fas fa-file-pdf"></i> Télécharger le devis
                        </button>
                        <button type="button" id="exp-open-modal-btn-local" class="pc-btn pc-btn--primary">
                            Faire une demande de réservation
                        </button>
                    </div>

                    <div class="exp-devis-company-info" style="display:none;"><?php echo wp_json_encode($company_info); ?></div>
                    <div class="exp-devis-experience-title" style="display:none;"><?php echo esc_html(get_the_title()); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="exp-booking-modal is-hidden" id="exp-booking-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <div class="exp-booking-modal__overlay" data-close-modal></div>
        <div class="exp-booking-modal__content">
            <button class="exp-booking-modal__close" aria-label="Fermer" data-close-modal>×</button>
            <h3 class="exp-booking-modal__title" id="modal-title">Demande de réservation</h3>

            <form id="experience-booking-form" method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="experience_booking_request">
                <input type="hidden" name="experience_id" value="<?php echo esc_attr($experience_id); ?>">
                <?php wp_nonce_field('experience_booking_request_nonce', 'nonce'); ?>

                <p class="honeypot-field" style="display:none !important; visibility:hidden !important; opacity:0 !important; height:0 !important; width:0 !important; position:absolute !important; left:-9999px !important;" aria-hidden="true">
                    <label for="booking-reason">Motif</label>
                    <input type="text" id="booking-reason" name="booking_reason" tabindex="-1" autocomplete="off">
                </p>
                <fieldset class="exp-booking-fieldset">
                    <legend>Votre simulation</legend>

                    <fieldset class="exp-booking-fieldset">
                        <legend>Votre simulation</legend>
                        <div id="modal-quote-summary">
                            <p>Veuillez d'abord faire une simulation avec le calculateur.</p>
                        </div>
                        <textarea name="quote_details" id="modal-quote-details-hidden" style="display:none;"></textarea>
                    </fieldset>

                    <fieldset class="exp-booking-fieldset">
                        <legend>Vos coordonnées</legend>
                        <div class="exp-booking-form-grid">
                            <div class="exp-booking-field">
                                <label for="booking-prenom">Prénom*</label>
                                <input type="text" id="booking-prenom" name="prenom" required>
                            </div>
                            <div class="exp-booking-field">
                                <label for="booking-nom">Nom*</label>
                                <input type="text" id="booking-nom" name="nom" required>
                            </div>
                            <div class="exp-booking-field">
                                <label for="booking-email">Email*</label>
                                <input type="email" id="booking-email" name="email" required>
                            </div>
                            <div class="exp-booking-field">
                                <label for="booking-tel">Téléphone*</label>
                                <input type="tel" id="booking-tel" name="tel" required>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="exp-booking-fieldset">
                        <legend>Pouvons-nous vous joindre par WhatsApp ?</legend>
                        <div class="exp-booking-radio-group">
                            <label><input type="radio" name="whatsapp" value="Oui" checked> Oui</label>
                            <label><input type="radio" name="whatsapp" value="Non"> Non</label>
                        </div>
                    </fieldset>

                    <fieldset class="exp-booking-fieldset">
                        <legend>Observations ou demandes particulières</legend>
                        <div class="exp-booking-field">
                            <label for="booking-message-experience" class="visually-hidden">Votre message</label>
                            <textarea id="booking-message-experience" name="message" rows="3" placeholder="Avez-vous des questions ou des demandes particulières ?"></textarea>
                        </div>
                    </fieldset>

                    <div class="exp-booking-modal__actions">
                        <p class="exp-booking-disclaimer">Cette demande est sans engagement. Nous vous recontacterons pour confirmer la disponibilité.</p>
                        <button type="submit" class="pc-btn pc-btn--primary">Envoyer la demande</button>
                    </div>
            </form>
        </div>
    </div>
<?php
    return ob_get_clean();
});

/**
 * ===================================================================
 * 12. GESTIONNAIRE D'ENVOI (Version Finale - AJAX & Double Email)
 * ===================================================================
 */
add_action('admin_post_nopriv_experience_booking_request', 'pc_handle_experience_booking_request');
add_action('admin_post_experience_booking_request', 'pc_handle_experience_booking_request');

function pc_handle_experience_booking_request()
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'experience_booking_request_nonce')) {
        wp_send_json_error(['message' => 'La vérification de sécurité a échoué.']);
        return;
    }

    // === DÉBUT: VÉRIFICATION ANTI-BOT ===

    // 1. Vérification du Honeypot
    // Si le champ 'booking_reason' (qui doit être vide) est rempli, c'est un bot.
    if (!empty($_POST['booking_reason'])) {
        // On fait un "silent fail" : on dit au bot que c'est OK, 
        // pour qu'il ne réessaie pas, mais on n'envoie rien.
        wp_send_json_success(['message' => 'Votre demande a bien été envoyée !']);
        return; // Arrête l'exécution ici.
    }

    // 2. Vérification que la simulation de devis n'est PAS vide
    $quote_details_raw = $_POST['quote_details'] ?? '';
    if (empty($quote_details_raw)) {
        // C'est un bot qui n'a pas rempli le devis
        // On fait aussi un "silent fail"
        wp_send_json_success(['message' => 'Votre demande a bien été envoyée !']);
        return; // Arrête l'exécution ici.
    }
    // === FIN: VÉRIFICATION ANTI-BOT ===


    $experience_id = isset($_POST['experience_id']) ? absint($_POST['experience_id']) : 0;
    $prenom = sanitize_text_field($_POST['prenom'] ?? '');
    $nom = sanitize_text_field($_POST['nom'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $tel = sanitize_text_field($_POST['tel'] ?? '');
    $whatsapp = sanitize_text_field($_POST['whatsapp'] ?? 'Non précisé');

    // On utilise la variable $quote_details_raw qu'on vient de vérifier
    // L'ancienne ligne est modifiée pour ne plus mettre de valeur par défaut.
    $quote_details = sanitize_textarea_field($quote_details_raw);

    $message = sanitize_textarea_field($_POST['message'] ?? '');

    if (!$experience_id || empty($prenom) || empty($nom) || !is_email($email)) {
        wp_send_json_error(['message' => 'Veuillez remplir tous les champs obligatoires.']);
        return;
    }

    $experience_title = get_the_title($experience_id);
    $headers = ['Content-Type: text/plain; charset=UTF-8'];
    $admin_to = 'guadeloupe@prestigecaraibes.com';
    $admin_subject = "Nouvelle demande pour l'expérience : " . $experience_title;
    $admin_body  = "Une nouvelle demande de réservation a été effectuée.\n\n";
    $admin_body .= "Expérience : " . $experience_title . " (ID: " . $experience_id . ")\n\n";
    $admin_body .= "CLIENT\n";
    $admin_body .= "--------------------------------\n";
    $admin_body .= "Prénom et Nom : " . $prenom . " " . $nom . "\n";
    $admin_body .= "Email : " . $email . "\n";
    $admin_body .= "Téléphone : " . $tel . "\n";
    $admin_body .= "Contact WhatsApp OK : " . $whatsapp . "\n\n";
    if (!empty($message)) {
        $admin_body .= "MESSAGE / OBSERVATIONS\n";
        $admin_body .= "--------------------------------\n";
        $admin_body .= $message . "\n\n";
    }
    $admin_body .= "DÉTAILS DE LA SIMULATION\n";
    $admin_body .= "--------------------------------\n";
    $admin_body .= $quote_details . "\n"; // Utilisera la valeur vérifiée

    $mail_sent_admin = wp_mail($admin_to, $admin_subject, $admin_body, $headers);

    $client_subject = "Confirmation de votre demande pour : " . $experience_title;
    $client_body  = "Bonjour " . $prenom . ",\n\n";
    $client_body .= "Nous avons bien reçu votre demande de réservation pour l'expérience \"" . $experience_title . "\".\n\n";
    $client_body .= "Nous allons vérifier les disponibilités et nous revenons vers vous dans les plus brefs délais.\n\n";
    $client_body .= "Cordialement,\n";
    $client_body .= "L'équipe Prestige Caraïbes";
    wp_mail($email, $client_subject, $client_body, $headers);

    if ($mail_sent_admin) {
        wp_send_json_success(['message' => 'Votre demande a bien été envoyée ! Nous vous avons également envoyé un email de confirmation.']);
    } else {
        wp_send_json_error(['message' => 'Le serveur n\'a pas pu envoyer l\'email. Veuillez nous contacter directement.']);
    }
    exit;
}
