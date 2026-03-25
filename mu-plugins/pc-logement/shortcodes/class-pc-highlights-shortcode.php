<?php

/**
 * Composant Shortcode : Points Forts [pc_highlights]
 * Design : Grille Premium dans une Card
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Highlights_Shortcode extends PC_Shortcode_Base
{
    protected $tag = 'pc_highlights';

    protected $default_atts = [
        'limit' => '',
        'icons' => '1'
    ];

    /**
     * Mappage des labels lisibles pour les slugs ACF
     */
    private $label_map = [
        'parking'    => 'Parking',
        'internet'   => 'Wi-Fi',
        'wifi'       => 'Wi-Fi',
        'piscine'    => 'Piscine',
        'clim'       => 'Climatisation',
        'vue_mer'    => 'Vue mer',
        'front_mer'  => 'Front de mer',
        'jacuzzi'    => 'Jacuzzi',
        'spa'        => 'Spa',
        'barbecue'   => 'Barbecue',
        'classement' => 'Classement'
    ];

    /**
     * Mappage des classes CSS FontAwesome pour les slugs ACF
     */
    private $fa_map = [
        'parking'    => 'fa-solid fa-square-parking fas fa-parking',
        'internet'   => 'fa-solid fa-wifi fas fa-wifi',
        'wifi'       => 'fa-solid fa-wifi fas fa-wifi',
        'piscine'    => 'fa-solid fa-water fas fa-water',
        'clim'       => 'fa-regular fa-snowflake far fa-snowflake fas fa-snowflake',
        'vue_mer'    => 'fa-solid fa-water fas fa-water',
        'front_mer'  => 'fa-solid fa-umbrella-beach fas fa-umbrella-beach',
        'jacuzzi'    => 'fa-solid fa-bath fas fa-bath',
        'spa'        => 'fa-solid fa-bath fas fa-bath',
        'barbecue'   => 'fa-solid fa-fire fas fa-fire',
        'classement' => 'fa-solid fa-star fas fa-star'
    ];

    /**
     * Rendu principal du shortcode
     */
    public function render($atts, $content = null)
    {
        $a = $this->validate_atts($atts);

        $post_id = get_the_ID();
        if (!$post_id) {
            return '';
        }

        $entries = $this->get_entries($post_id);

        if ($a['limit'] !== '' && is_numeric($a['limit'])) {
            $entries = array_slice($entries, 0, (int) $a['limit']);
        }

        if (empty($entries)) {
            return '';
        }

        // CHARGEMENT FORCÉ ICI : Contourne le bug Elementor !
        if ($a['icons'] !== '0') {
            wp_enqueue_style(
                'font-awesome',
                'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css',
                [],
                '6.5.2'
            );
        }

        ob_start(); ?>

        <div class="pc-hl-container">
            <?php foreach ($entries as $e): ?>
                <div class="pc-hl-pill">

                    <?php if ($a['icons'] !== '0'): ?>
                        <div class="pc-hl-icon-circle" aria-hidden="true">
                            <?php if (!$e['custom'] && !empty($e['fa'])): ?>
                                <i class="<?php echo esc_attr($e['fa']); ?>"></i>
                            <?php else: ?>
                                <i class="fa-solid fa-check"></i>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <span class="pc-hl-text"><?php echo esc_html($e['label']); ?></span>

                </div>
            <?php endforeach; ?>
        </div>

<?php return ob_get_clean();
    }

    /**
     * Géré directement dans le render() pour compatibilité constructeurs de pages
     */
    protected function enqueue_assets()
    {
        return;
    }

    /**
     * Récupère et fusionne les points forts classiques et personnalisés (ACF)
     */
    private function get_entries($post_id)
    {
        $checked = (array) PCR_Fields::get('highlights', $post_id);
        $custom  = (string) PCR_Fields::get('highlights_custom', $post_id);
        $entries = [];

        // Traitement des cases à cocher standard
        foreach ($checked as $slug) {
            $label = $this->label_map[$slug] ?? ucfirst(str_replace('_', ' ', $slug));
            if ($label) {
                $entries[] = [
                    'slug'   => $slug,
                    'label'  => $label,
                    'fa'     => $this->fa_map[$slug] ?? '',
                    'custom' => false
                ];
            }
        }

        // Traitement du champ texte libre (séparé par des retours à la ligne)
        if ($custom) {
            foreach (preg_split('/\r\n|\r|\n/', $custom) as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $entries[] = [
                        'slug'   => null,
                        'label'  => $line,
                        'fa'     => '',
                        'custom' => true
                    ];
                }
            }
        }

        return $entries;
    }
}
