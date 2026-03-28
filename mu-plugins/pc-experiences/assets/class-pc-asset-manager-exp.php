<?php

/**
 * Gestionnaire des Assets (CSS / JS) pour la fiche Expérience
 * Calqué sur l'architecture de PC_Asset_Manager (Logement)
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Asset_Manager_Exp
{

    /**
     * Enregistre les hooks d'assets
     */
    public function register()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_global_assets'], 20);
    }

    /**
     * Charge les scripts JS et le CSS principal
     */
    public function enqueue_global_assets()
    {
        // 🛡️ Condition stricte : On ne charge QUE sur la fiche 'experience'
        if (!is_singular('experience')) {
            return;
        }

        // =========================================================================
        // 1. LIBRAIRIES EXTERNES (Spécifiques aux expériences)
        // =========================================================================

        // Leaflet (Carte de localisation)
        wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4');
        wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true);

        // GLightbox (Galerie photos)
        wp_enqueue_style('glightbox-css', 'https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css', [], '3.3.0');
        wp_enqueue_script('glightbox-js', 'https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js', [], '3.3.0', true);

        // jsPDF (Génération PDF des devis - requis par pc-pdf-generator)
        wp_enqueue_script('jspdf', 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js', [], '2.5.1', true);

        // =========================================================================
        // 2. COMPOSANTS CSS
        // =========================================================================

        $components_css = [
            'pc-exp-description'     => 'pc-experience-description.css',
            'pc-exp-gallery'         => 'pc-experience-gallery.css',
            'pc-exp-map'             => 'pc-experience-map.css',
            'pc-exp-calculator'      => 'pc-booking-calculator.css',
            'pc-exp-modal'           => 'pc-booking-modal.css',
            'pc-exp-booking-fab'     => 'pc-booking-fab.css',
            'pc-exp-booking-sheet'   => 'pc-booking-sheet.css'
        ];

        foreach ($components_css as $handle => $filename) {
            $css_path = PC_EXP_DIR . 'assets/css/components/' . $filename;
            if (file_exists($css_path)) {
                wp_enqueue_style($handle, PC_EXP_URL . 'assets/css/components/' . $filename, [], filemtime($css_path));
            }
        }

        // =========================================================================
        // 3. COMPOSANTS JS
        // =========================================================================

        // Module : Formateur de devises
        $currency_js_path = PC_EXP_DIR . 'assets/js/modules/pc-currency-formatter.js';
        if (file_exists($currency_js_path)) {
            wp_enqueue_script('pc-exp-currency-formatter', PC_EXP_URL . 'assets/js/modules/pc-currency-formatter.js', [], filemtime($currency_js_path), true);
        }

        // Module : Générateur PDF (Sécurisé avec jspdf et formateur de devises en dépendance)
        $pdf_js_path = PC_EXP_DIR . 'assets/js/modules/pc-pdf-generator.js';
        if (file_exists($pdf_js_path)) {
            wp_enqueue_script('pc-exp-pdf-generator', PC_EXP_URL . 'assets/js/modules/pc-pdf-generator.js', ['pc-exp-currency-formatter', 'jspdf'], filemtime($pdf_js_path), true);
        }

        // Composant : Calculateur de devis
        $calc_js_path = PC_EXP_DIR . 'assets/js/components/pc-booking-calculator.js';
        if (file_exists($calc_js_path)) {
            wp_enqueue_script('pc-exp-calculator-js', PC_EXP_URL . 'assets/js/components/pc-booking-calculator.js', ['pc-exp-currency-formatter'], filemtime($calc_js_path), true);
        }

        // Composant : Bouton Flottant (FAB)
        $fab_js_path = PC_EXP_DIR . 'assets/js/components/pc-booking-fab.js';
        if (file_exists($fab_js_path)) {
            wp_enqueue_script('pc-exp-fab', PC_EXP_URL . 'assets/js/components/pc-booking-fab.js', [], filemtime($fab_js_path), true);
        }

        // Composant : Bottom-Sheet Mobile
        $sheet_js_path = PC_EXP_DIR . 'assets/js/components/pc-booking-sheet.js';
        if (file_exists($sheet_js_path)) {
            wp_enqueue_script('pc-exp-booking-sheet', PC_EXP_URL . 'assets/js/components/pc-booking-sheet.js', [], filemtime($sheet_js_path), true);
        }

        // Composant : Modale de réservation
        $modal_js_path = PC_EXP_DIR . 'assets/js/components/pc-booking-modal.js';
        if (file_exists($modal_js_path)) {
            wp_enqueue_script('pc-exp-booking-modal', PC_EXP_URL . 'assets/js/components/pc-booking-modal.js', [], filemtime($modal_js_path), true);
        }
    }
}
