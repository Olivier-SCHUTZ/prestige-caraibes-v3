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
        // add_action('wp_head', [$this, 'inject_global_css']); // Décommenter si besoin
    }

    /**
     * Charge les scripts JS et le CSS principal
     */
    public function enqueue_global_assets()
    {
        // On ne charge que sur la page de type 'experience'
        if (!is_singular('experience')) {
            return;
        }

        // =========================================================================
        // 1. COMPOSANTS CSS
        // =========================================================================

        // Composant : Description (Voir plus)
        $desc_css_path = PC_EXP_DIR . 'assets/css/components/pc-experience-description.css';
        if (file_exists($desc_css_path)) {
            wp_enqueue_style('pc-exp-description', PC_EXP_URL . 'assets/css/components/pc-experience-description.css', [], filemtime($desc_css_path));
        }

        // Composant : Galerie
        $gallery_css_path = PC_EXP_DIR . 'assets/css/components/pc-experience-gallery.css';
        if (file_exists($gallery_css_path)) {
            wp_enqueue_style('pc-exp-gallery', PC_EXP_URL . 'assets/css/components/pc-experience-gallery.css', [], filemtime($gallery_css_path));
        }

        // Composant : Map
        $map_css_path = PC_EXP_DIR . 'assets/css/components/pc-experience-map.css';
        if (file_exists($map_css_path)) {
            wp_enqueue_style('pc-exp-map', PC_EXP_URL . 'assets/css/components/pc-experience-map.css', [], filemtime($map_css_path));
        }

        // Composant : Summary (Résumé)
        $summary_css_path = PC_EXP_DIR . 'assets/css/components/pc-experience-summary.css';
        if (file_exists($summary_css_path)) {
            wp_enqueue_style('pc-exp-summary', PC_EXP_URL . 'assets/css/components/pc-experience-summary.css', [], filemtime($summary_css_path));
        }

        // Composant : Pricing (Cartes "Ticket")
        $pricing_css_path = PC_EXP_DIR . 'assets/css/components/pc-experience-pricing.css';
        if (file_exists($pricing_css_path)) {
            wp_enqueue_style('pc-exp-pricing', PC_EXP_URL . 'assets/css/components/pc-experience-pricing.css', [], filemtime($pricing_css_path));
        }

        // Composant : Calculateur de Devis
        $devis_css_path = PC_EXP_DIR . 'assets/css/components/pc-booking-calculator.css';
        if (file_exists($devis_css_path)) {
            wp_enqueue_style('pc-exp-calculator', PC_EXP_URL . 'assets/css/components/pc-booking-calculator.css', [], filemtime($devis_css_path));
        }

        // Composant : Inclusions (Inclus / Non inclus)
        $inclusions_css_path = PC_EXP_DIR . 'assets/css/components/pc-experience-inclusions.css';
        if (file_exists($inclusions_css_path)) {
            wp_enqueue_style('pc-exp-inclusions', PC_EXP_URL . 'assets/css/components/pc-experience-inclusions.css', [], filemtime($inclusions_css_path));
        }

        // Composant : Logements Recommandés
        $reco_css_path = PC_EXP_DIR . 'assets/css/components/pc-experience-recommendations.css';
        if (file_exists($reco_css_path)) {
            wp_enqueue_style('pc-exp-recommendations', PC_EXP_URL . 'assets/css/components/pc-experience-recommendations.css', [], filemtime($reco_css_path));
        }

        // Composant : Modale de réservation
        $modal_css_path = PC_EXP_DIR . 'assets/css/components/pc-booking-modal.css';
        if (file_exists($modal_css_path)) {
            wp_enqueue_style('pc-exp-modal', PC_EXP_URL . 'assets/css/components/pc-booking-modal.css', [], filemtime($modal_css_path));
        }

        // Composant : Menu Sticky (Ancres)
        $anchor_css_path = PC_EXP_DIR . 'assets/css/components/pc-anchor-menu.css';
        if (file_exists($anchor_css_path)) {
            wp_enqueue_style('pc-exp-anchor-menu', PC_EXP_URL . 'assets/css/components/pc-anchor-menu.css', [], filemtime($anchor_css_path));
        }

        // Composant : Bouton Flottant (FAB)
        $fab_css_path = PC_EXP_DIR . 'assets/css/components/pc-booking-fab.css';
        if (file_exists($fab_css_path)) {
            wp_enqueue_style('pc-exp-booking-fab', PC_EXP_URL . 'assets/css/components/pc-booking-fab.css', [], filemtime($fab_css_path));
        }

        // Composant : Bottom-Sheet Mobile
        $sheet_css_path = PC_EXP_DIR . 'assets/css/components/pc-booking-sheet.css';
        if (file_exists($sheet_css_path)) {
            wp_enqueue_style('pc-exp-booking-sheet', PC_EXP_URL . 'assets/css/components/pc-booking-sheet.css', [], filemtime($sheet_css_path));
        }

        // =========================================================================
        // 2. COMPOSANTS JS
        // =========================================================================

        // Module : Formateur de devises
        $currency_js_path = PC_EXP_DIR . 'assets/js/modules/pc-currency-formatter.js';
        if (file_exists($currency_js_path)) {
            wp_enqueue_script('pc-exp-currency-formatter', PC_EXP_URL . 'assets/js/modules/pc-currency-formatter.js', [], filemtime($currency_js_path), true);
        }

        // Module : Générateur PDF
        $pdf_js_path = PC_EXP_DIR . 'assets/js/modules/pc-pdf-generator.js';
        if (file_exists($pdf_js_path)) {
            wp_enqueue_script('pc-exp-pdf-generator', PC_EXP_URL . 'assets/js/modules/pc-pdf-generator.js', [], filemtime($pdf_js_path), true);
        }

        // Composant : Calculateur de devis
        $calc_js_path = PC_EXP_DIR . 'assets/js/components/pc-booking-calculator.js';
        if (file_exists($calc_js_path)) {
            wp_enqueue_script('pc-exp-calculator', PC_EXP_URL . 'assets/js/components/pc-booking-calculator.js', [], filemtime($calc_js_path), true);
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
