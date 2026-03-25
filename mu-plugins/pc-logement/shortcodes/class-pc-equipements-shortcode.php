<?php

/**
 * Composant Shortcode : Équipements du Logement [pc_equipements]
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Equipements_Shortcode extends PC_Shortcode_Base
{
    protected $tag = 'pc_equipements';

    /**
     * Rendu principal du shortcode
     */
    public function render($atts, $content = null)
    {
        $post = get_post();

        if (!$post) {
            return '';
        }

        // 1. Récupération des catégories et de leurs équipements cochés
        $equipements = $this->get_active_equipements($post->ID);

        if (empty($equipements)) {
            return '';
        }

        // 2. Affichage HTML Façon Airbnb
        ob_start(); ?>
        <div class="pc-equipements-wrapper">
            <div class="pc-equipements-grid">
                <?php foreach ($equipements as $cat): ?>
                    <div class="pc-equip-box">
                        <div class="pc-equip-header">
                            <span class="pc-equip-icon" aria-hidden="true"><?php echo $cat['svg']; ?></span>
                            <h4 class="pc-equip-title"><?php echo esc_html($cat['label']); ?></h4>
                        </div>
                        <ul class="pc-equip-list">
                            <?php foreach ($cat['items'] as $item): ?>
                                <li><?php echo esc_html($item); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
<?php return ob_get_clean();
    }

    protected function enqueue_assets()
    {
        return;
    }

    /**
     * Helper : Lit les catégories et extrait les cases cochées
     */
    private function get_active_equipements($post_id)
    {
        // 📚 TES 10 VRAIES CATÉGORIES AVEC LEURS ICÔNES
        $categories = [
            'eq_piscine_spa'                  => ['label' => 'Piscine & Spa', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 6c.6.5 1.2 1 2.5 1C5.8 7 7 5.6 8.5 5.5c1.5-.1 2.8 1.3 4 1.5s2.5-1.4 4-1.5c1.5-.1 2.8 1.3 4 1.5 1.3 0 1.9-.5 2.5-1M2 12c.6.5 1.2 1 2.5 1 1.3 0 2.5-1.4 4-1.5 1.5-.1 2.8 1.3 4 1.5s2.5-1.4 4-1.5c1.5-.1 2.8 1.3 4 1.5 1.3 0 1.9-.5 2.5-1M2 18c.6.5 1.2 1 2.5 1 1.3 0 2.5-1.4 4-1.5 1.5-.1 2.8 1.3 4 1.5s2.5-1.4 4-1.5c1.5-.1 2.8 1.3 4 1.5 1.3 0 1.9-.5 2.5-1"/></svg>'],
            'eq_parking_installations'        => ['label' => 'Parking & Installations', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9 17V7h4a3 3 0 0 1 0 6H9"/></svg>'],
            'eq_politiques'                   => ['label' => 'Politiques du logement', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>'],
            'eq_divertissements'              => ['label' => 'Divertissements', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="15" rx="2" ry="2"/><polyline points="17 2 12 7 7 2"/></svg>'],
            'eq_cuisine_salle_a_manger' => ['label' => 'Cuisine & Repas', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 13.5v7c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2v-7"/><path d="M2 10.5h20"/><path d="M12 2.5a5 5 0 0 0-5 5h10a5 5 0 0 0-5-5Z"/></svg>'],
            'eq_caracteristiques_emplacement' => ['label' => 'Emplacement', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>'],
            'eq_salle_de_bain_blanchisserie'  => ['label' => 'Bain & Blanchisserie', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>'],
            'eq_chauffage_climatisation'      => ['label' => 'Climatisation & Chauffage', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="2" x2="12" y2="22"/><line x1="2" y1="12" x2="22" y2="12"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/><line x1="4.93" y1="19.07" x2="19.07" y2="4.93"/></svg>'],
            'eq_internet_bureautique'         => ['label' => 'Internet & Bureau', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>'],
            'eq_securite_maison'              => ['label' => 'Sécurité', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>'],
        ];

        $results = [];

        foreach ($categories as $key => $cat_data) {
            $val = PCR_Fields::get($key, $post_id);

            // 🛡️ DÉCODEUR UNIVERSEL (Gère JSON, Serialized, et Array pur)
            if (is_string($val)) {
                $decoded = json_decode($val, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $val = $decoded;
                } else {
                    $val = maybe_unserialize($val);
                }
            }

            // Si c'est bien un tableau et qu'il n'est pas vide (il y a des cases cochées)
            if (is_array($val) && !empty($val)) {
                $items = [];

                // On nettoie les slugs pour les rendre lisibles (ex: "seche_cheveux" -> "Seche cheveux")
                foreach ($val as $item) {
                    $clean_item = str_replace(['_', '-'], ' ', $item);
                    $clean_item = ucfirst(mb_strtolower($clean_item, 'UTF-8'));
                    $items[] = $clean_item;
                }

                $results[] = [
                    'label' => $cat_data['label'],
                    'svg'   => $cat_data['svg'],
                    'items' => $items // La liste des sous-équipements
                ];
            }
        }

        return $results;
    }
}
