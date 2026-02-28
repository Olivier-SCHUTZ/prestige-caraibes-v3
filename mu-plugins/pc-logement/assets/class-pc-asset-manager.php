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
        add_action('wp_enqueue_scripts', [$this, 'enqueue_global_assets'], 20);
        add_action('wp_head',            [$this, 'inject_global_css']);
    }

    /**
     * Charge les scripts JS et le CSS principal
     */
    public function enqueue_global_assets()
    {
        if (!is_singular(['villa', 'appartement', 'logement']) && !is_page(['reserver', 'demande-sejour'])) {
            return;
        }

        // Librairie externe : GLightbox (CSS & JS via CDN)
        wp_enqueue_style('glightbox-css', 'https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css', [], '3.3.0');
        wp_enqueue_script('glightbox-js', 'https://cdn.jsdelivr.net/gh/mcstudios/glightbox/dist/js/glightbox.min.js', [], '3.3.0', true);

        // Composant : Galerie
        $gallery_css_path = PC_LOGEMENT_PATH . 'assets/css/components/pc-gallery.css';
        if (file_exists($gallery_css_path)) {
            wp_enqueue_style('pc-gallery', PC_LOGEMENT_URL . 'assets/css/components/pc-gallery.css', [], filemtime($gallery_css_path));
        }

        // Composant : Points Forts (Pastilles)
        $highlights_css_path = PC_LOGEMENT_PATH . 'assets/css/components/pc-highlights.css';
        if (file_exists($highlights_css_path)) {
            wp_enqueue_style('pc-highlights', PC_LOGEMENT_URL . 'assets/css/components/pc-highlights.css', [], filemtime($highlights_css_path));
        }

        // Composant : Proximités
        $prox_css_path = PC_LOGEMENT_PATH . 'assets/css/components/pc-proximites.css';
        if (file_exists($prox_css_path)) {
            wp_enqueue_style('pc-proximites', PC_LOGEMENT_URL . 'assets/css/components/pc-proximites.css', [], filemtime($prox_css_path));
        }

        // Composant : Tableau des Tarifs
        $tarifs_css_path = PC_LOGEMENT_PATH . 'assets/css/components/pc-tarifs.css';
        if (file_exists($tarifs_css_path)) {
            wp_enqueue_style('pc-tarifs', PC_LOGEMENT_URL . 'assets/css/components/pc-tarifs.css', [], filemtime($tarifs_css_path));
        }

        // Composant : Carte de localisation
        $map_css_path = PC_LOGEMENT_PATH . 'assets/css/components/pc-map.css';
        if (file_exists($map_css_path)) {
            wp_enqueue_style('pc-map', PC_LOGEMENT_URL . 'assets/css/components/pc-map.css', [], filemtime($map_css_path));
        }

        // Composant : SEO Lire la suite
        $seo_css_path = PC_LOGEMENT_PATH . 'assets/css/components/pc-seo-readmore.css';
        if (file_exists($seo_css_path)) {
            wp_enqueue_style('pc-seo-readmore', PC_LOGEMENT_URL . 'assets/css/components/pc-seo-readmore.css', [], filemtime($seo_css_path));
        }

        // Composant : Calendrier iCal
        $calendar_css_path = PC_LOGEMENT_PATH . 'assets/css/components/pc-calendar.css';
        if (file_exists($calendar_css_path)) {
            wp_enqueue_style('pc-calendar', PC_LOGEMENT_URL . 'assets/css/components/pc-calendar.css', [], filemtime($calendar_css_path));
        }

        // Composant : Calculateur de Devis
        $devis_css_path = PC_LOGEMENT_PATH . 'assets/css/components/pc-devis.css';
        if (file_exists($devis_css_path)) {
            wp_enqueue_style('pc-devis', PC_LOGEMENT_URL . 'assets/css/components/pc-devis.css', [], filemtime($devis_css_path));
        }

        // Composant : Modale de réservation
        $modal_css_path = PC_LOGEMENT_PATH . 'assets/css/components/pc-booking-modal.css';
        if (file_exists($modal_css_path)) {
            wp_enqueue_style('pc-booking-modal', PC_LOGEMENT_URL . 'assets/css/components/pc-booking-modal.css', [], filemtime($modal_css_path));
        }

        // Composant : Menu Sticky (Ancres)
        $anchor_css_path = PC_LOGEMENT_PATH . 'assets/css/components/pc-anchor-menu.css';
        if (file_exists($anchor_css_path)) {
            wp_enqueue_style('pc-anchor-menu', PC_LOGEMENT_URL . 'assets/css/components/pc-anchor-menu.css', [], filemtime($anchor_css_path));
        }

        // Composant : Bouton Flottant (FAB)
        $fab_css_path = PC_LOGEMENT_PATH . 'assets/css/components/pc-booking-fab.css';
        if (file_exists($fab_css_path)) {
            wp_enqueue_style('pc-booking-fab', PC_LOGEMENT_URL . 'assets/css/components/pc-booking-fab.css', [], filemtime($fab_css_path));
        }

        // Composant : Bottom-Sheet Mobile
        $sheet_css_path = PC_LOGEMENT_PATH . 'assets/css/components/pc-booking-sheet.css';
        if (file_exists($sheet_css_path)) {
            wp_enqueue_style('pc-booking-sheet', PC_LOGEMENT_URL . 'assets/css/components/pc-booking-sheet.css', [], filemtime($sheet_css_path));
        }

        // Composant : Section Recommandations
        $reco_css_path = PC_LOGEMENT_PATH . 'assets/css/components/pc-recommendations.css';
        if (file_exists($reco_css_path)) {
            wp_enqueue_style('pc-recommendations', PC_LOGEMENT_URL . 'assets/css/components/pc-recommendations.css', [], filemtime($reco_css_path));
        }

        // 2. Dépendances globales (Flatpickr)
        wp_enqueue_style('flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css');
        wp_enqueue_script('flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js', [], null, true);
        wp_enqueue_script('flatpickr-fr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js', ['flatpickr-js'], null, true);

        // Notre gestionnaire de galerie (qui dépend de GLightbox)
        $gallery_js_path = PC_LOGEMENT_PATH . 'assets/js/components/pc-gallery-manager.js';
        if (file_exists($gallery_js_path)) {
            wp_enqueue_script('pc-gallery-manager-js', PC_LOGEMENT_URL . 'assets/js/components/pc-gallery-manager.js', ['glightbox-js'], filemtime($gallery_js_path), true);
        }

        // =========================================================================
        // 3. NOUVEAUX SCRIPTS MODULAIRES (Le début du refactoring JS)
        // =========================================================================

        // Modules
        $state_manager_path = PC_LOGEMENT_PATH . 'assets/js/modules/pc-state-manager.js';
        if (file_exists($state_manager_path)) {
            wp_enqueue_script('pc-state-manager-js', PC_LOGEMENT_URL . 'assets/js/modules/pc-state-manager.js', [], filemtime($state_manager_path), true);
        }

        // Calculateur de Devis (Logique pure)
        $calculator_js_path = PC_LOGEMENT_PATH . 'assets/js/modules/pc-devis-calculator.js';
        if (file_exists($calculator_js_path)) {
            wp_enqueue_script('pc-devis-calculator-js', PC_LOGEMENT_URL . 'assets/js/modules/pc-devis-calculator.js', ['pc-currency-formatter-js'], filemtime($calculator_js_path), true);
        }

        // Générateur PDF
        $pdf_js_path = PC_LOGEMENT_PATH . 'assets/js/modules/pc-pdf-generator.js';
        if (file_exists($pdf_js_path)) {
            wp_enqueue_script('pc-pdf-generator-js', PC_LOGEMENT_URL . 'assets/js/modules/pc-pdf-generator.js', ['pc-currency-formatter-js'], filemtime($pdf_js_path), true);
        }

        // Utils
        $currency_formatter_path = PC_LOGEMENT_PATH . 'assets/js/utils/pc-currency-formatter.js';
        if (file_exists($currency_formatter_path)) {
            wp_enqueue_script('pc-currency-formatter-js', PC_LOGEMENT_URL . 'assets/js/utils/pc-currency-formatter.js', [], filemtime($currency_formatter_path), true);
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

        // On garde l'orchestrateur (seul fichier historique restant !)
        $orchestrator_path = WP_CONTENT_DIR . '/mu-plugins/assets/pc-orchestrator.js';
        if (file_exists($orchestrator_path)) {
            wp_enqueue_script('pc-orchestrator-js', content_url('mu-plugins/assets/pc-orchestrator.js'), [], filemtime($orchestrator_path), true);
        }

        // 🚀 NOTRE CHEF D'ORCHESTRE (Logement Core)
        $core_js_path = PC_LOGEMENT_PATH . 'assets/js/pc-logement-core.js';
        if (file_exists($core_js_path)) {
            $core_dependencies = [
                'pc-calendar-integration-js', // <-- Remplace l'ancien pc-devis-js
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

            // On transfère le Nonce de sécurité à notre Core
            wp_localize_script('pc-logement-core-js', 'pcLogementData', [
                'nonce' => wp_create_nonce('logement_booking_request_nonce')
            ]);
        }
    }

    /**
     * Injecte le CSS inline (correctifs Elementor et layout global)
     */
    public function inject_global_css()
    {
        if (!is_singular(['logement', 'villa', 'appartement'])) {
            return;
        }
?>
        <style>
            html,
            body {
                max-width: 100%;
                overflow-x: hidden;
            }

            img,
            iframe,
            video {
                max-width: 100%;
                height: auto;
            }

            @media (max-width:1024px) {
                body .elementor-section.elementor-section-stretched {
                    width: 100% !important;
                    left: 0 !important;
                    right: 0 !important;
                }

                .elementor-section,
                .elementor-container,
                .elementor-widget,
                .elementor-widget-wrap {
                    max-width: 100%;
                    overflow-x: clip;
                }

                .pc-hero,
                .pc-tabs-wrap,
                .pc-gallery,
                .pc-proximites,
                .pc-location-map,
                .pc-ical,
                .pc-reviews {
                    overflow-x: clip;
                }
            }

            :root {
                --pc-primary: #0e2b5c;
                --pc-accent: #005F73;
                --pc-sticky-top: 68px;
            }

            section[id] {
                scroll-margin-top: calc(var(--pc-sticky-top) + 12px);
            }

            .pc-hero h1 {
                font-size: clamp(1.75rem, 3.5vw + 1rem, 3rem);
                line-height: 1.2;
            }

            @media (min-width:1024px) {
                .pc-tabs-arrow {
                    display: none;
                }
            }

            @media (max-width:480px) {
                .pc-tabs-nav a {
                    padding: 9px 12px;
                    font-size: .95rem;
                }
            }

            .pc-proximites {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            @media (max-width:480px) {
                .pc-proximites {
                    grid-template-columns: 1fr;
                }
            }
        </style>
<?php
    }
}
