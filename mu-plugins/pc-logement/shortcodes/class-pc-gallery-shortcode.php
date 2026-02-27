<?php

/**
 * Composant Shortcode : Galerie de la Fiche Logement [pc_gallery]
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Gallery_Shortcode extends PC_Shortcode_Base
{

    protected $tag = 'pc_gallery';

    protected $default_atts = [
        'limit'   => 6,                 // n images visibles (grille)
        'class'   => '',
        'field'   => 'gallery_urls',    // champ texte (Mode A existant)
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

        // === MODE A : URLs externes ===
        $urls = $this->get_external_urls($post->ID, $a['field']);
        if (!empty($urls)) {
            return $this->render_mode_a($urls, $a, $post->ID);
        }

        // === MODE B : Groupes ACF (Catégories + Images) ===
        $cats = $this->get_acf_groups($post->ID);
        if (!empty($cats)) {
            return $this->render_mode_b($cats, $a, $post->ID);
        }

        // Fallback si aucune image
        return '<div class="pc-gallery"><p class="pc-empty">Aucune photo pour ce logement</p></div>';
    }

    /**
     * Chargement conditionnel des assets (JS / CSS)
     * Vide car géré par le PC_Asset_Manager global.
     */
    protected function enqueue_assets() {}

    /**
     * Logique du Mode A : Récupération des URLs brutes
     */
    private function get_external_urls($post_id, $field_name)
    {
        $raw = get_field($field_name, $post_id, false);
        $urls = [];

        if (!empty($raw)) {
            if (is_string($raw)) {
                $parts = preg_split('~[\r\n,]+~', trim($raw));
                foreach ($parts as $p) {
                    $p = trim($p);
                    if ($p) $urls[] = esc_url_raw($p);
                }
            } elseif (is_array($raw)) {
                foreach ($raw as $row) {
                    if (is_array($row) && !empty($row['url'])) {
                        $urls[] = esc_url_raw($row['url']);
                    } elseif (is_string($row)) {
                        $urls[] = esc_url_raw($row);
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($urls)));
    }

    /**
     * Logique du Mode B : Récupération et formatage des groupes ACF
     */
    private function get_acf_groups($post_id)
    {
        $groups = get_field('groupes_images', $post_id);
        $cats = [];

        if (is_array($groups)) {
            foreach ($groups as $g) {
                if (empty($g)) continue;

                if (!empty($g['categorie']) && $g['categorie'] !== 'autre') {
                    $label = $this->humanize($g['categorie']);
                } else {
                    $label = trim((string)($g['categorie_personnalisee'] ?? ''));
                }

                if ($label === '') $label = 'Autre';
                $slug = $this->slugify($label);

                $images = [];
                if (!empty($g['images_du_groupe']) && is_array($g['images_du_groupe'])) {
                    foreach ($g['images_du_groupe'] as $img) {
                        $id = is_array($img) && isset($img['ID']) ? (int)$img['ID'] : (is_numeric($img) ? (int)$img : 0);
                        if (!$id) continue;

                        $src = wp_get_attachment_image_url($id, 'large');
                        if (!$src) continue;

                        $alt   = get_post_meta($id, '_wp_attachment_image_alt', true);
                        $title = get_the_title($id);
                        $images[] = ['id' => $id, 'src' => $src, 'alt' => $alt ?: $title, 'title' => $title];
                    }
                }

                if (empty($images)) continue;

                $cats[] = ['label' => $label, 'slug' => $slug, 'items' => $images];
            }
        }

        return $cats;
    }

    /**
     * Rendu HTML du Mode A (URLs externes)
     */
    private function render_mode_a($urls, $a, $post_id)
    {
        $gallery_id = 'pcg-extern-' . $post_id;
        $visible = $a['limit'] ? array_slice($urls, 0, (int)$a['limit']) : $urls;

        ob_start(); ?>
        <section class="pc-gallery <?php echo esc_attr($a['class']); ?>"
            data-mode="external"
            data-gallery-id="<?php echo esc_attr($gallery_id); ?>">
            <div class="pc-grid">
                <?php foreach ($visible as $i => $href): ?>
                    <a class="pc-item pc-glink" href="<?php echo esc_url($href); ?>"
                        data-gallery="<?php echo esc_attr($gallery_id); ?>"
                        aria-label="<?php echo esc_attr(sprintf('Voir la photo %d', $i + 1)); ?>">
                        <img src="<?php echo esc_url($href); ?>" loading="lazy" decoding="async" alt="" />
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="pc-morewrap">
                <button class="pc-more" type="button"
                    data-gallery-id="<?php echo esc_attr($gallery_id); ?>"
                    data-mode="external"
                    data-total="<?php echo esc_attr(count($urls)); ?>">
                    <?php echo esc_html(sprintf('Voir les %d photos', count($urls))); ?>
                </button>
            </div>

            <div class="pc-lightbox-src" hidden>
                <?php foreach ($urls as $href): ?>
                    <a href="<?php echo esc_url($href); ?>"
                        class="pc-glightbox"
                        data-group="<?php echo esc_attr($gallery_id); ?>"></a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php return ob_get_clean();
    }

    /**
     * Rendu HTML du Mode B (ACF Groupes)
     */
    private function render_mode_b($cats, $a, $post_id)
    {
        $total_all = 0;
        foreach ($cats as $c) {
            $total_all += count($c['items']);
        }

        if ($total_all === 0) {
            return '<div class="pc-gallery"><p class="pc-empty">Aucune photo pour ce logement</p></div>';
        }

        $gallery_id = 'pcg-acf-' . $post_id;

        ob_start(); ?>
        <section class="pc-gallery <?php echo esc_attr($a['class']); ?>"
            data-mode="acf"
            data-gallery-id="<?php echo esc_attr($gallery_id); ?>"
            data-i18n-all="<?php echo esc_attr('Toutes les photos'); ?>"
            data-i18n-see-all="<?php echo esc_attr('Voir les %d photos'); ?>"
            data-i18n-see-cat="<?php echo esc_attr('Voir les %d photos (%s)'); ?>">

            <div class="pc-gallery-toolbar">
                <label class="pc-gallery-label" for="<?php echo esc_attr($gallery_id . '-select'); ?>">Catégorie</label>
                <select id="<?php echo esc_attr($gallery_id . '-select'); ?>"
                    class="pc-gallery-select"
                    aria-label="Filtrer les photos par catégorie">
                    <option value="all" selected>Toutes les photos</option>
                    <?php foreach ($cats as $c): ?>
                        <option value="<?php echo esc_attr($c['slug']); ?>"><?php echo esc_html($c['label']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pc-grid" data-limit="<?php echo esc_attr((int)$a['limit']); ?>">
            </div>

            <div class="pc-morewrap">
                <button class="pc-more" type="button"
                    data-gallery-id="<?php echo esc_attr($gallery_id); ?>"
                    data-mode="acf"
                    data-total-all="<?php echo esc_attr($total_all); ?>"
                    data-cats="<?php echo esc_attr(wp_json_encode(array_map(function ($c) {
                                    return ['slug' => $c['slug'], 'label' => $c['label'], 'count' => count($c['items'])];
                                }, $cats))); ?>">
                    <?php echo esc_html(sprintf('Voir les %d photos', $total_all)); ?>
                </button>
            </div>

            <div class="pc-lightbox-src" hidden>
                <?php foreach ($cats as $c): ?>
                    <?php foreach ($c['items'] as $img): ?>
                        <a href="<?php echo esc_url($img['src']); ?>"
                            class="pc-glightbox"
                            data-group="<?php echo esc_attr($gallery_id); ?>"
                            data-cat="<?php echo esc_attr($c['slug']); ?>"
                            data-title="<?php echo esc_attr($img['title']); ?>"></a>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </section>
<?php return ob_get_clean();
    }

    /**
     * Utilitaire : Transforme un texte en slug
     */
    private function slugify($s)
    {
        $s = remove_accents(mb_strtolower((string)$s, 'UTF-8'));
        $s = preg_replace('~[^a-z0-9]+~u', '-', $s);
        return trim($s, '-') ?: 'autre';
    }

    /**
     * Utilitaire : Rend un slug lisible (ex: chambre_1 -> Chambre 1)
     */
    private function humanize($v)
    {
        $v = trim((string)$v);
        if ($v === '') return '';
        $v = str_replace('_', ' ', $v);
        return mb_convert_case($v, MB_CASE_TITLE, 'UTF-8');
    }
}
