<?php

/**
 * PC Header Dropdown Helper
 * Gère la logique métier et le rendu HTML du menu déroulant des logements
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Header_Dropdown_Helper
{

    /**
     * Génère le HTML du composant Dropdown des logements
     *
     * @param array $atts Attributs du shortcode
     * @return string Le code HTML complet
     */
    public static function render_dropdown($atts = [])
    {
        $a = shortcode_atts([
            'label'  => 'Nos logements',
            'max'    => '5', // Nombre de logements visibles avant scroll
            'search' => '#', // Lien du bouton "Rechercher"
        ], $atts, 'liste_logements_dropdown');

        // Requête pour récupérer les logements (villas et appartements)
        $lis = '';
        $q = new WP_Query([
            'post_type'      => ['villa', 'appartement'],
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ]);

        if ($q->have_posts()) {
            foreach ($q->posts as $pid) {
                $title = get_the_title($pid);
                $url   = get_permalink($pid);
                $lis  .= '<li class="pc-dd__item" role="none"><a role="menuitem" href="' . esc_url($url) . '">' . esc_html($title) . '</a></li>';
            }
        }
        wp_reset_postdata();

        if ($lis === '') {
            $lis = '<li class="pc-dd__item" role="none"><span role="menuitem" aria-disabled="true">Aucun logement</span></li>';
        }

        // Icône chevron SVG
        $chev = '<svg class="pc-dd__chev" width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M7.41 8.58 12 13.17l4.59-4.59L18 10l-6 6-6-6z"/></svg>';

        ob_start();
?>
        <div class="pc-dd pc-dd--lodgings" data-pc-dd-max="<?php echo esc_attr((int)$a['max']); ?>">
            <button type="button" class="pc-dd__btn">
                <span><?php echo esc_html($a['label']); ?></span><?php echo $chev; ?>
            </button>

            <div class="pc-dd__panel" hidden role="menu">
                <div class="pc-dd__search">
                    <input type="text" class="pc-dd__filter" placeholder="Rechercher un logement par nom…" inputmode="search" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" name="sll-no-autofill" />
                </div>
                <ul class="pc-dd__list" role="none"><?php echo $lis; ?></ul>
                <div class="pc-dd__footer">
                    <a class="pc-dd__search" href="<?php echo esc_url($a['search']); ?>">
                        <span>Rechercher</span><?php echo $chev; ?>
                    </a>
                </div>
            </div>
        </div>
<?php
        return ob_get_clean();
    }
}
