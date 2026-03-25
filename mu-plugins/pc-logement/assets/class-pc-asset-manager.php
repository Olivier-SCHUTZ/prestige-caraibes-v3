<?php

/**
 * Gestionnaire des Assets (CSS / JS) pour la fiche logement
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Asset_Manager
{

    /**
     * Enregistre les hooks d'assets
     */
    public function register()
    {
        // Plus de hook wp_head, uniquement l'enqueue propre !
        add_action('wp_enqueue_scripts', [$this, 'enqueue_global_assets'], 20);
    }

    /**
     * Charge les scripts JS et le CSS principal
     */
    public function enqueue_global_assets()
    {
        // 🛡️ Condition stricte : Fiches logements ou pages de réservation UNIQUEMENT
        if (!is_singular(['villa', 'appartement', 'logement']) && !is_page(['reserver', 'demande-sejour'])) {
            return;
        }

        // =========================================================================
        // 1. LIBRAIRIES EXTERNES (Rapatriées du global)
        // =========================================================================

        // Leaflet (Carte de localisation)
        wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4');
        wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true);

        // GLightbox (Galerie photos)
        wp_enqueue_style('glightbox-css', 'https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css', [], '3.3.0');
        wp_enqueue_script('glightbox-js', 'https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js', [], '3.3.0', true);

        // Flatpickr (Calendrier de réservation)
        wp_enqueue_style('flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], '4.6.13');
        wp_enqueue_script('flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js', [], '4.6.13', true);
        wp_enqueue_script('flatpickr-fr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js', ['flatpickr-js'], '4.6.13', true);

        // jsPDF (Génération PDF des devis)
        wp_enqueue_script('jspdf', 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js', [], '2.5.1', true);

        // =========================================================================
        // 2. COMPOSANTS CSS
        // =========================================================================

        $components_css = [
            'pc-logement-layout' => '../pc-logement-layout.css', // Notre nouveau fichier racine CSS
            'pc-gallery'         => 'components/pc-gallery.css',
            'pc-highlights'      => 'components/pc-highlights.css',
            'pc-proximites'      => 'components/pc-proximites.css',
            'pc-tarifs'          => 'components/pc-tarifs.css',
            'pc-map'             => 'components/pc-map.css',
            'pc-seo-readmore'    => 'components/pc-seo-readmore.css',
            'pc-calendar'        => 'components/pc-calendar.css',
            'pc-devis'           => 'components/pc-devis.css',
            'pc-booking-modal'   => 'components/pc-booking-modal.css',
            'pc-anchor-menu'     => 'components/pc-anchor-menu.css',
            'pc-booking-fab'     => 'components/pc-booking-fab.css',
            'pc-booking-sheet'   => 'components/pc-booking-sheet.css',
            'pc-recommendations' => 'components/pc-recommendations.css',
            'pc-equipements'     => 'components/pc-equipements.css',
            'pc-regles'          => 'components/pc-regles.css'
        ];

        foreach ($components_css as $handle => $relative_path) {
            $css_path = PC_LOGEMENT_PATH . 'assets/css/' . $relative_path;
            if (file_exists($css_path)) {
                wp_enqueue_style($handle, PC_LOGEMENT_URL . 'assets/css/' . $relative_path, [], filemtime($css_path));
            }
        }

        // =========================================================================
        // 3. NOUVEAUX SCRIPTS MODULAIRES
        // =========================================================================

        // Gestionnaire de galerie
        $gallery_js_path = PC_LOGEMENT_PATH . 'assets/js/components/pc-gallery-manager.js';
        if (file_exists($gallery_js_path)) {
            wp_enqueue_script('pc-gallery-manager-js', PC_LOGEMENT_URL . 'assets/js/components/pc-gallery-manager.js', ['glightbox-js'], filemtime($gallery_js_path), true);
        }

        // Modules
        $state_manager_path = PC_LOGEMENT_PATH . 'assets/js/modules/pc-state-manager.js';
        if (file_exists($state_manager_path)) {
            wp_enqueue_script('pc-state-manager-js', PC_LOGEMENT_URL . 'assets/js/modules/pc-state-manager.js', [], filemtime($state_manager_path), true);
        }

        // Utils (Currency Formatter)
        $currency_formatter_path = PC_LOGEMENT_PATH . 'assets/js/utils/pc-currency-formatter.js';
        if (file_exists($currency_formatter_path)) {
            wp_enqueue_script('pc-currency-formatter-js', PC_LOGEMENT_URL . 'assets/js/utils/pc-currency-formatter.js', [], filemtime($currency_formatter_path), true);
        }

        // Calculateur de Devis (Logique pure)
        $calculator_js_path = PC_LOGEMENT_PATH . 'assets/js/modules/pc-devis-calculator.js';
        if (file_exists($calculator_js_path)) {
            wp_enqueue_script('pc-devis-calculator-js', PC_LOGEMENT_URL . 'assets/js/modules/pc-devis-calculator.js', ['pc-currency-formatter-js'], filemtime($calculator_js_path), true);
        }

        // Générateur PDF (Sécurisé avec jspdf en dépendance)
        $pdf_js_path = PC_LOGEMENT_PATH . 'assets/js/modules/pc-pdf-generator.js';
        if (file_exists($pdf_js_path)) {
            wp_enqueue_script('pc-pdf-generator-js', PC_LOGEMENT_URL . 'assets/js/modules/pc-pdf-generator.js', ['pc-currency-formatter-js', 'jspdf'], filemtime($pdf_js_path), true);
        }

        // Components
        $fab_js_path = PC_LOGEMENT_PATH . 'assets/js/components/pc-booking-fab.js';
        if (file_exists($fab_js_path)) {
            wp_enqueue_script('pc-booking-fab-js', PC_LOGEMENT_URL . 'assets/js/components/pc-booking-fab.js', ['pc-state-manager-js', 'pc-currency-formatter-js'], filemtime($fab_js_path), true);
        }

        // La Bottom Sheet
        $sheet_js_path = PC_LOGEMENT_PATH . 'assets/js/components/pc-booking-sheet.js';
        if (file_exists($sheet_js_path)) {
            wp_enqueue_script('pc-booking-sheet-js', PC_LOGEMENT_URL . 'assets/js/components/pc-booking-sheet.js', [], filemtime($sheet_js_path), true);
        }

        // La Modale de réservation
        $modal_js_path = PC_LOGEMENT_PATH . 'assets/js/components/pc-booking-modal.js';
        if (file_exists($modal_js_path)) {
            wp_enqueue_script('pc-booking-modal-js', PC_LOGEMENT_URL . 'assets/js/components/pc-booking-modal.js', [], filemtime($modal_js_path), true);
        }

        // Integrations
        $lodgify_js_path = PC_LOGEMENT_PATH . 'assets/js/integrations/pc-lodgify-connector.js';
        if (file_exists($lodgify_js_path)) {
            wp_enqueue_script('pc-lodgify-connector-js', PC_LOGEMENT_URL . 'assets/js/integrations/pc-lodgify-connector.js', [], filemtime($lodgify_js_path), true);
        }

        // Formulaire et traitement des réservations
        $form_js_path = PC_LOGEMENT_PATH . 'assets/js/components/pc-booking-form.js';
        if (file_exists($form_js_path)) {
            wp_enqueue_script('pc-booking-form-js', PC_LOGEMENT_URL . 'assets/js/components/pc-booking-form.js', [], filemtime($form_js_path), true);
        }

        // Composant : Interface Calendrier & UI Devis
        $calendar_js_path = PC_LOGEMENT_PATH . 'assets/js/components/pc-calendar-integration.js';
        if (file_exists($calendar_js_path)) {
            wp_enqueue_script(
                'pc-calendar-integration-js',
                PC_LOGEMENT_URL . 'assets/js/components/pc-calendar-integration.js',
                ['flatpickr-fr', 'pc-devis-calculator-js', 'pc-pdf-generator-js'],
                filemtime($calendar_js_path),
                true
            );
        }

        // =========================================================================
        // 4. SCRIPTS MÉTIER RESTANTS & NOUVEAU CORE
        // =========================================================================

        // Orchestrateur historique
        $orchestrator_path = WP_CONTENT_DIR . '/mu-plugins/assets/pc-orchestrator.js';
        if (file_exists($orchestrator_path)) {
            wp_enqueue_script('pc-orchestrator-js', content_url('mu-plugins/assets/pc-orchestrator.js'), [], filemtime($orchestrator_path), true);
        }

        // 🚀 NOTRE CHEF D'ORCHESTRE (Logement Core)
        $core_js_path = PC_LOGEMENT_PATH . 'assets/js/pc-logement-core.js';
        if (file_exists($core_js_path)) {
            $core_dependencies = [
                'pc-calendar-integration-js',
                'pc-state-manager-js',
                'pc-currency-formatter-js',
                'pc-booking-fab-js',
                'pc-booking-sheet-js',
                'pc-booking-modal-js',
                'pc-lodgify-connector-js',
                'pc-booking-form-js'
            ];

            wp_enqueue_script(
                'pc-logement-core-js',
                PC_LOGEMENT_URL . 'assets/js/pc-logement-core.js',
                $core_dependencies,
                filemtime($core_js_path),
                true
            );

            // Transfert du Nonce de sécurité
            wp_localize_script('pc-logement-core-js', 'pcLogementData', [
                'nonce' => wp_create_nonce('logement_booking_request_nonce')
            ]);
        }
    }
}
