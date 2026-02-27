<?php

/**
 * Composant Shortcode : Calendrier de disponibilités [pc_ical_calendar]
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_ICal_Shortcode extends PC_Shortcode_Base
{

    protected $tag = 'pc_ical_calendar';

    protected $default_atts = [
        'url'        => '',
        'max_months' => 24,
        'min'        => 'today',
        'class'      => '',
    ];

    /**
     * Rendu principal du shortcode
     */
    public function render($atts, $content = null)
    {
        $a = $this->validate_atts($atts);
        $post = get_post();

        if (!$post) {
            return '';
        }

        // 1. Détermination de l'URL iCal (si non fournie en attribut)
        if (!$a['url'] && function_exists('get_field')) {
            $a['url'] = (string) get_field('ical_url', $post->ID);
        }

        // Sécurité propriétaire : Si pas d'iCal configuré, on cache le calendrier
        if (!$a['url']) {
            $message = 'Faites votre demande, nous vous renseignerons sur les disponibilités de ce logement.';
            return '<div class="pc-cal-missing">' . esc_html($message) . '</div>';
        }

        // 2. Récupération des disponibilités via notre nouveau Helper !
        $json_ranges = PC_Availability_Helper::get_combined_availability($post->ID);

        $id = 'pc-cal-input-' . $post->ID . '-' . wp_rand(100, 999);

        // 3. Chargement forcé des librairies Flatpickr (Contourne le bug Elementor)
        wp_enqueue_style('flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css');
        wp_enqueue_script('flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js', [], null, true);
        wp_enqueue_script('flatpickr-fr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js', ['flatpickr-js'], null, true);

        // 4. Affichage HTML et initialisation JS du composant
        ob_start(); ?>
        <section class="pc-cal <?php echo esc_attr($a['class']); ?>"
            data-max-months="<?php echo esc_attr((int)$a['max_months']); ?>"
            data-min-date="<?php echo esc_attr($a['min']); ?>">
            <div class="pc-cal-row">
                <input id="<?php echo esc_attr($id); ?>" class="pc-cal-input-modular" type="text" style="display:none;" />
                <script type="application/json" id="<?php echo esc_attr($id); ?>-json">
                    <?php echo $json_ranges; ?>
                </script>
            </div>
        </section>

        <script>
            (function() {
                function initCalendar() {
                    // On attend que Flatpickr et la langue FR soient chargés
                    if (typeof flatpickr === 'undefined' || !window.flatpickr.l10ns || !window.flatpickr.l10ns.fr) {
                        return setTimeout(initCalendar, 50);
                    }

                    flatpickr.localize(flatpickr.l10ns.fr);

                    var inputEl = document.getElementById('<?php echo esc_js($id); ?>');
                    var jsonEl = document.getElementById('<?php echo esc_js($id); ?>-json');

                    if (!inputEl || !jsonEl) return;

                    var rawRanges = JSON.parse(jsonEl.textContent || '[]');

                    // Conversion du format [début, fin] vers {from: début, to: fin}
                    var disabledRanges = rawRanges.map(function(r) {
                        return (Array.isArray(r) && r.length >= 2) ? {
                            from: r[0],
                            to: r[1]
                        } : r;
                    });

                    flatpickr(inputEl, {
                        inline: true,
                        mode: 'range',
                        dateFormat: 'Y-m-d',
                        locale: 'fr',
                        minDate: '<?php echo esc_js($a['min']); ?>',
                        showMonths: window.innerWidth >= 1025 ? 3 : (window.innerWidth >= 641 ? 2 : 1),
                        disable: disabledRanges,
                        clickOpens: false,
                        allowInput: false
                    });
                }

                // Lancement dès que le DOM est prêt
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initCalendar);
                } else {
                    initCalendar();
                }
            })();
        </script>
<?php return ob_get_clean();
    }

    protected function enqueue_assets()
    {
        return; // Géré dans render()
    }
}
