<?php

/**
 * Composant Shortcode : Carte de Localisation (Leaflet) [pc_location_map]
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Location_Map_Shortcode extends PC_Shortcode_Base
{

    protected $tag = 'pc_location_map';

    protected $default_atts = [
        'zoom'  => 13,
        'color' => '#e74c3c', // Couleur du cercle par défaut
        'class' => '',
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

        // 1. Récupération et validation des coordonnées
        $coords = $this->get_coordinates($post->ID);
        if (!$coords) {
            return ''; // Rien ne s'affiche si aucune coordonnée n'est renseignée
        }

        // 2. Récupération du rayon d'affichage
        $radius = (int) get_field('geo_radius_m', $post->ID);
        if ($radius <= 0) {
            $radius = 600; // Rayon par défaut
        }

        // Identifiant unique pour le conteneur de la carte
        $id = 'pcmap-' . wp_rand(1000, 9999);

        // 3. Chargement forcé des bibliothèques Leaflet (Contourne le bug Elementor)
        wp_enqueue_style('leaflet', 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css', [], null);
        wp_enqueue_script('leaflet', 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js', [], null, true);

        // 4. Construction de l'affichage
        ob_start();
        $this->render_html($id, $a['class']);
        $this->render_script($id, $coords['lat'], $coords['lng'], $radius, $a['zoom'], $a['color']);
        return ob_get_clean();
    }

    /**
     * Géré directement dans le render() pour compatibilité constructeurs de pages
     */
    protected function enqueue_assets()
    {
        return;
    }

    /**
     * Helper : Extrait et nettoie les coordonnées (Latitude, Longitude)
     */
    private function get_coordinates($post_id)
    {
        $coords_string = trim((string)get_field('geo_coords', $post_id));

        if (!$coords_string || strpos($coords_string, ',') === false) {
            return false;
        }

        [$lat, $lng] = array_map('trim', explode(',', $coords_string, 2));
        $lat = floatval($lat);
        $lng = floatval($lng);

        if (!$lat || !$lng) {
            return false;
        }

        return ['lat' => $lat, 'lng' => $lng];
    }

    /**
     * Helper : Affiche le conteneur HTML de la carte
     */
    private function render_html($id, $css_class)
    {
?>
        <section class="pc-map-wrap <?php echo esc_attr($css_class); ?>">
            <div id="<?php echo esc_attr($id); ?>" class="pc-map"></div>
        </section>
    <?php
    }

    /**
     * Helper : Affiche le script d'initialisation JavaScript spécifique à cette carte
     */
    private function render_script($id, $lat, $lng, $radius, $zoom, $color)
    {
    ?>
        <script>
            (function() {
                // Fonction d'initialisation avec attente (si le JS de Leaflet charge après)
                function initMap() {
                    if (!window.L) {
                        return setTimeout(initMap, 50);
                    }

                    var map = L.map('<?php echo esc_js($id); ?>', {
                        center: [<?php echo $lat; ?>, <?php echo $lng; ?>],
                        zoom: <?php echo (int)$zoom; ?>,
                        scrollWheelZoom: false
                    });

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap'
                    }).addTo(map);

                    L.circle([<?php echo $lat; ?>, <?php echo $lng; ?>], {
                        radius: <?php echo $radius; ?>,
                        color: '<?php echo esc_js($color); ?>',
                        fillColor: '<?php echo esc_js($color); ?>',
                        fillOpacity: .25,
                        weight: 2
                    }).addTo(map);

                    // Amélioration de l'UX : on n'active le zoom molette que si on clique sur la carte
                    map.on('focus', function() {
                        map.scrollWheelZoom.enable();
                    });
                    map.on('blur', function() {
                        map.scrollWheelZoom.disable();
                    });

                    // Raccourci secret : Ctrl/Cmd + Clic ouvre Google Maps
                    map.on('click', function(e) {
                        if (e.originalEvent.metaKey || e.originalEvent.ctrlKey) {
                            var url = 'https://www.google.com/maps/dir/?api=1&destination=<?php echo $lat; ?>,<?php echo $lng; ?>';
                            window.open(url, '_blank');
                        }
                    });
                }
                initMap();
            })();
        </script>
<?php
    }
}
