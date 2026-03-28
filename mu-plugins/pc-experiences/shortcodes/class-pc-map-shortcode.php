<?php

/**
 * Shortcode : [experience_map]
 * Affiche la carte interactive avec les points de départ (Leaflet).
 *
 * @package PC_Experiences
 */

if (!defined('ABSPATH')) {
    exit; // Sécurité
}

class PC_Experience_Map_Shortcode extends PC_Experience_Shortcode_Base
{

    /**
     * Nom du shortcode.
     *
     * @var string
     */
    protected $shortcode_name = 'experience_map';

    /**
     * Rendu HTML du shortcode.
     *
     * @param array $atts Attributs.
     */
    protected function render(array $atts = []): void
    {
        // 1. Décodeur Universel V3 pour Répéteur (Gère le JSON natif Vue.js et l'historique ACF)
        $raw_locations = PCR_Fields::get('exp_lieux_horaires_depart');
        $locations = is_string($raw_locations) ? json_decode($raw_locations, true) : $raw_locations;

        // Si le champ est vide ou mal formaté, on arrête
        if (empty($locations) || !is_array($locations)) {
            return;
        }

        $locations_data = [];
        foreach ($locations as $loc) {
            // Extraction sécurisée des données de chaque ligne du répéteur
            $lat  = !empty($loc['lat_exp']) ? floatval($loc['lat_exp']) : 0;
            $lon  = !empty($loc['longitude']) ? floatval($loc['longitude']) : 0;
            $name = !empty($loc['exp_lieu_depart']) ? trim(wp_strip_all_tags($loc['exp_lieu_depart'])) : 'Point de départ';

            if ($lat && $lon) {
                $locations_data[] = [
                    'lat'  => $lat,
                    'lon'  => $lon,
                    'name' => $name,
                ];
            }
        }

        if (empty($locations_data)) {
            echo '<p>Les coordonnées GPS pour les lieux de départ ne sont pas valides.</p>';
            return;
        }

        $map_id = 'exp-map-' . $this->get_experience_id();

        // --- DÉBUT DU RENDU ---
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
        // --- FIN DU RENDU ---
    }
}
