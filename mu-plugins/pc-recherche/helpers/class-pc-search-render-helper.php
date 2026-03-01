<?php

/**
 * Helper centralisant la génération du HTML (Vignettes, Pagination, Prix, etc.)
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Search_Render_Helper
{
    /** =========================================================
     * COMMUN
     * ========================================================= */

    /**
     * Génère le HTML de la pagination
     */
    public static function render_pagination(array $pagination_data): string
    {
        $current = isset($pagination_data['current_page']) ? intval($pagination_data['current_page']) : 1;
        $total   = isset($pagination_data['total_pages']) ? intval($pagination_data['total_pages']) : 1;

        if ($total <= 1) return '';

        $html = '<div class="pc-pagination">';
        for ($i = 1; $i <= $total; $i++) {
            if ($i === $current) {
                $html .= '<span class="current">' . $i . '</span>';
            } else {
                $html .= '<a href="#" data-page="' . $i . '">' . $i . '</a>';
            }
        }
        $html .= '</div>';
        return $html;
    }

    /** =========================================================
     * LOGEMENTS
     * ========================================================= */

    public static function render_logement_vignette(array $data, bool $is_lcp = false): string
    {
        $url        = isset($data['link']) ? esc_url($data['link']) : '#';
        $thumb_url  = isset($data['thumb']) ? esc_url($data['thumb']) : '';
        $title      = isset($data['title']) ? esc_html($data['title']) : 'Titre non disponible';
        $city       = isset($data['city']) ? esc_html($data['city']) : 'Lieu non disponible';
        $price      = isset($data['price']) ? esc_html($data['price']) : '';
        $rating_avg = isset($data['rating_avg']) ? floatval($data['rating_avg']) : 0;
        $rating_count = isset($data['rating_count']) ? intval($data['rating_count']) : 0;

        $img_attrs = ['src' => $thumb_url, 'alt' => esc_attr($title), 'width' => '376', 'height' => '230'];
        if ($is_lcp) {
            $img_attrs['loading'] = 'eager';
            $img_attrs['fetchpriority'] = 'high';
            $img_attrs['decoding'] = 'async';
        } else {
            $img_attrs['loading'] = 'lazy';
            $img_attrs['decoding'] = 'async';
        }

        $img_attrs_str = '';
        foreach ($img_attrs as $key => $val) {
            $img_attrs_str .= $key . '="' . esc_attr($val) . '" ';
        }

        $stars_html = '';
        if ($rating_count > 0) {
            $rounded_rating = round($rating_avg);
            for ($i = 1; $i <= 5; $i++) {
                $star_class = ($i <= $rounded_rating) ? 'star filled' : 'star';
                $stars_html .= "<span class='{$star_class}'><svg width='18' height='18' viewBox='0 0 24 24'><path d='M12 17.3l-6.18 3.75 1.64-7.03L2 9.77l7.19-.61L12 2.5l2.81 6.66 7.19.61-5.46 4.25 1.64 7.03z'/></svg></span>";
            }
        } else {
            $stars_html = 'N.C';
        }

        $price_html = $price ? "À partir de <strong>{$price}€ par nuit</strong>" : '';

        ob_start();
?>
        <a href="<?php echo $url; ?>" class="pc-vignette" target="_blank" rel="noopener">
            <div class="pc-vignette__image"><?php if ($thumb_url): ?><img <?php echo trim($img_attrs_str); ?>><?php endif; ?></div>
            <div class="pc-vignette__content">
                <h3 class="pc-vignette__title"><?php echo $title; ?></h3>
                <div class="pc-vignette__location"><?php echo $city; ?>, Guadeloupe</div>
                <div class="pc-vignette__rating"><?php echo $stars_html; ?></div>
                <div class="pc-vignette__price"><?php echo $price_html; ?></div>
            </div>
        </a>
<?php
        return ob_get_clean();
    }

    /** =========================================================
     * EXPÉRIENCES
     * ========================================================= */

    /**
     * Génère le label de prix d'une expérience à partir des grilles ACF
     */
    public static function get_experience_price_label($tarifs): string
    {
        if (empty($tarifs) || !is_array($tarifs)) return '';

        foreach ($tarifs as $tarif) {
            if (($tarif['exp_type'] ?? '') === 'sur-devis') return 'Tarif sur devis';
        }

        $format_price = function ($value): string {
            return rtrim(rtrim(number_format(floatval($value), 2, ',', ' '), '0'), ',') . ' €';
        };

        foreach (['demi-journee', 'journee'] as $type) {
            foreach ($tarifs as $tarif) {
                if (($tarif['exp_type'] ?? '') !== $type) continue;
                if (empty($tarif['exp_tarifs_lignes']) || !is_array($tarif['exp_tarifs_lignes'])) continue;
                foreach ($tarif['exp_tarifs_lignes'] as $ligne) {
                    if (($ligne['type_ligne'] ?? '') === 'adulte' && isset($ligne['tarif_valeur']) && is_numeric($ligne['tarif_valeur'])) {
                        return 'À partir de ' . $format_price($ligne['tarif_valeur']);
                    }
                }
            }
        }

        foreach ($tarifs as $tarif) {
            if (($tarif['exp_type'] ?? '') !== 'unique') continue;
            if (empty($tarif['exp_tarifs_lignes']) || !is_array($tarif['exp_tarifs_lignes'])) continue;
            foreach ($tarif['exp_tarifs_lignes'] as $ligne) {
                if (isset($ligne['tarif_valeur']) && is_numeric($ligne['tarif_valeur'])) {
                    return 'Tarif unique de ' . $format_price($ligne['tarif_valeur']);
                }
            }
        }

        foreach ($tarifs as $tarif) {
            if (($tarif['exp_type'] ?? '') !== 'custom') continue;
            if (empty($tarif['exp_tarifs_lignes']) || !is_array($tarif['exp_tarifs_lignes'])) continue;
            foreach ($tarif['exp_tarifs_lignes'] as $ligne) {
                if (($ligne['type_ligne'] ?? '') === 'personnalise' && isset($ligne['tarif_valeur']) && is_numeric($ligne['tarif_valeur'])) {
                    return 'À partir de ' . $format_price($ligne['tarif_valeur']);
                }
            }
        }

        return '';
    }

    /**
     * Génère la grille de résultats HTML pour les expériences
     */
    public static function render_experience_results_grid(array $vignettes, bool $is_first_load = false): string
    {
        if (empty($vignettes)) {
            return '<div class="pc-no-results"><h3>Aucune expérience ne correspond à votre recherche.</h3><p>Essayez d\'ajuster vos filtres.</p></div>';
        }

        $html = '<div class="pc-exp-results-grid pc-results-grid">';
        $first = true;

        foreach ($vignettes as $item) {
            $price_html = !empty($item['price']) ? '<div class="pc-vignette__price">' . esc_html($item['price']) . '</div>' : '';
            $location_html = !empty($item['city']) ? '<div class="pc-vignette__location">' . esc_html($item['city']) . '</div>' : '';

            $image_attrs = 'src="' . esc_url($item['thumb'] ?? '') . '" alt="' . esc_attr($item['title'] ?? '') . '" width="300" height="200"';

            if ($is_first_load && $first) {
                $image_attrs .= ' fetchpriority="high" loading="eager" decoding="async"';
                $first = false;
            } else {
                $image_attrs .= ' loading="lazy" decoding="async"';
            }

            $image_html = !empty($item['thumb']) ? '<img ' . $image_attrs . '>' : '';

            $html .= sprintf(
                '<a href="%s" class="pc-vignette" target="_blank" rel="noopener">
                    <div class="pc-vignette__image">%s</div>
                    <div class="pc-vignette__content">
                        <h3 class="pc-vignette__title">%s</h3>
                        %s
                        %s
                    </div>
                </a>',
                esc_url($item['link'] ?? '#'),
                $image_html,
                esc_html($item['title'] ?? ''),
                $location_html,
                $price_html
            );
        }
        $html .= '</div>';
        return $html;
    }
}
