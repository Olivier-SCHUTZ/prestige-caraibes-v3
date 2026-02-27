<?php

/**
 * Composant Shortcode : Router de Réservation [pc_booking_router]
 * Aiguille vers Lodgify ou redirige vers le formulaire manuel.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Booking_Router_Shortcode extends PC_Shortcode_Base
{

    protected $tag = 'pc_booking_router';

    protected $default_atts = [
        'header' => '0',
    ];

    /**
     * Rendu principal du shortcode
     */
    public function render($atts, $content = null)
    {
        $a = $this->validate_atts($atts);
        $id = isset($_GET['l']) ? absint($_GET['l']) : 0;

        if (!$id || !get_post($id)) {
            return '<p>Référence logement manquante.</p>';
        }

        // Variable globale conservée pour rétrocompatibilité éventuelle avec d'autres vieux scripts
        $GLOBALS['pc_current_logement_id'] = $id;

        $embed = $this->get_lodgify_embed($id);
        $force_form = (isset($_GET['mode']) && $_GET['mode'] === 'form');

        // Aiguillage : Redirection ou Affichage
        if ($this->should_redirect($embed, $force_form)) {
            return $this->execute_redirect($id);
        }

        return $this->render_lodgify_widget($id, $embed, $a['header']);
    }

    /**
     * Assets spécifiques gérés directement dans le rendu HTML
     */
    protected function enqueue_assets()
    {
        return;
    }

    /**
     * Helper : Récupère le code d'intégration Lodgify depuis ACF
     */
    private function get_lodgify_embed($post_id)
    {
        if (!function_exists('get_field')) {
            return '';
        }
        $embed_raw = get_field('lodgify_widget_embed', $post_id);
        return is_string($embed_raw) ? trim($embed_raw) : '';
    }

    /**
     * Helper : Détermine si une redirection vers le formulaire manuel est nécessaire
     */
    private function should_redirect($embed, $force_form)
    {
        $has_div    = (stripos($embed, '<div') !== false);
        $has_marker = (stripos($embed, 'lodgify-book-now-box') !== false) || (stripos($embed, 'data-rental-id') !== false);

        $has_valid_embed = (!$force_form) && ($embed !== '') && $has_div && $has_marker;

        return !$has_valid_embed;
    }

    /**
     * Helper : Exécute la redirection (PHP ou Fallback JS si headers déjà envoyés)
     */
    private function execute_redirect($post_id)
    {
        $url = home_url('/demande-sejour/?l=' . $post_id);

        if (!headers_sent()) {
            wp_safe_redirect($url);
            exit;
        }

        // Fallback si la page a déjà commencé à s'afficher
        return '<script>location.replace(' . wp_json_encode($url) . ');</script><noscript><meta http-equiv="refresh" content="0;url=' . esc_attr($url) . '"></noscript>';
    }

    /**
     * Helper : Génère l'affichage du widget Lodgify
     */
    private function render_lodgify_widget($post_id, $embed, $show_header)
    {
        $title = get_the_title($post_id);
        $price = function_exists('get_field') ? get_field('base_price_from', $post_id) : '';
        $unite = function_exists('get_field') ? get_field('unite_de_prix', $post_id) : 'par nuit';
        if (!$unite) $unite = 'par nuit';

        ob_start();

        // En-tête optionnel
        if ($show_header === '1') {
            echo '<h1 class="pcbk-h1">Réserver — ' . esc_html($title) . '</h1>';
            if ($price) {
                echo '<p class="pcbk-meta">À partir de <strong>' . esc_html(number_format_i18n($price, 0)) . '€</strong> ' . esc_html($unite) . '</p>';
            }
            echo '<style>.pcbk-h1{font:500 1.5rem/1.3 "Poppins",system-ui}.pcbk-meta{opacity:.85;margin:.25rem 0 1rem}</style>';
        }

        // Conteneur du widget
        echo '<div id="pcbk-lodgify">' . $embed . '</div>';
?>

        <script src="https://app.lodgify.com/book-now-box/stable/renderBookNowBox.js" defer></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                function tryInit() {
                    var el = document.querySelector('#pcbk-lodgify #lodgify-book-now-box,[data-rental-id]');
                    if (!el) return;

                    if (!el.id) el.id = 'lodgify-book-now-box';

                    if (window.renderBookNowBox) {
                        try {
                            window.renderBookNowBox(el.id);
                        } catch (e) {
                            try {
                                window.renderBookNowBox(el.id, {});
                            } catch (e2) {}
                        }
                    } else {
                        setTimeout(tryInit, 60);
                    }
                }
                tryInit();
            });
        </script>
<?php
        return ob_get_clean();
    }
}
