<?php

/**
 * Composant Shortcode : Proximités (Distances) [pc_proximites]
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Proximites_Shortcode extends PC_Shortcode_Base
{

    protected $tag = 'pc_proximites';

    /**
     * Rendu principal du shortcode
     */
    public function render($atts, $content = null)
    {
        $post = get_post();

        if (!$post || !function_exists('get_field')) {
            return '';
        }

        // 1. Récupération et formatage des données
        $proximites = $this->get_proximites_data($post->ID);

        if (empty($proximites)) {
            return '';
        }

        // 2. Affichage HTML
        ob_start(); ?>
        <div class="pc-prox-grid">
            <?php foreach ($proximites as $item): ?>
                <div class="pc-prox" data-prox="<?php echo esc_attr($item['slug']); ?>">
                    <span class="pc-prox-ico" aria-hidden="true"><?php echo $item['svg']; ?></span>
                    <span class="pc-prox-val"><?php echo esc_html($item['formatted_val']); ?></span>
                    <span class="pc-prox-lab"><?php echo esc_html($item['label']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
<?php return ob_get_clean();
    }

    /**
     * Pas de script ou de style externe spécifique à charger pour ce module.
     */
    protected function enqueue_assets()
    {
        return;
    }

    /**
     * Helper : Récupère les champs ACF et prépare le tableau de données
     */
    private function get_proximites_data($post_id)
    {
        // Définition des éléments avec leurs SVGs bruts
        $raw_data = [
            [
                'slug'  => 'airport',
                'label' => 'Aéroport',
                'val'   => get_field('prox_airport_km', $post_id),
                'svg'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 13l20 0" stroke="currentColor" stroke-width="2"/><path d="M3 10l6 3-1 6 3-3 5 3 1-4-5-5z" fill="none" stroke="currentColor" stroke-width="2"/></svg>'
            ],
            [
                'slug'  => 'bus',
                'label' => 'Autobus',
                'val'   => get_field('prox_bus_km', $post_id),
                'svg'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="12" rx="2" stroke="currentColor" fill="none" stroke-width="2"/><circle cx="7" cy="17" r="1.5" fill="currentColor"/><circle cx="17" cy="17" r="1.5" fill="currentColor"/></svg>'
            ],
            [
                'slug'  => 'port',
                'label' => 'Port',
                'val'   => get_field('prox_port_km', $post_id),
                'svg'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h16v3a8 8 0 01-16 0z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 3v9" stroke="currentColor" stroke-width="2"/></svg>'
            ],
            [
                'slug'  => 'beach',
                'label' => 'Plage',
                'val'   => get_field('prox_beach_km', $post_id),
                'svg'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17c2 0 2-2 4-2s2 2 4 2 2-2 4-2 2 2 4 2" stroke="currentColor" stroke-width="2" fill="none"/><path d="M8 7c3-3 5-3 8 0" stroke="currentColor" stroke-width="2" fill="none"/></svg>'
            ],
        ];

        $valid_data = [];

        // Filtrage et formatage
        foreach ($raw_data as $item) {
            if ($item['val'] !== '' && $item['val'] !== null) {
                $item['formatted_val'] = $this->format_distance($item['val']);

                // On n'ajoute que si la distance finale est valide
                if ($item['formatted_val'] !== '') {
                    $valid_data[] = $item;
                }
            }
        }

        return $valid_data;
    }

    /**
     * Helper : Formate la distance (ex: 5.0 -> 5 Km / 4.5 -> 4,5 Km)
     */
    private function format_distance($val)
    {
        if (!is_numeric($val)) {
            return '';
        }
        $km = (float)$val;
        // Supprime les zéros inutiles après la virgule
        $formatted = rtrim(rtrim(number_format($km, 1, ',', ' '), '0'), ',');
        return $formatted . ' Km';
    }
}
