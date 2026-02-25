<?php

/**
 * Plugin Name: Prestige Caraïbes — Hub Global (V3 - Refactored)
 * Description: Hub principal - Orchestrateur ultra-léger qui charge les modules thématiques.
 * Author: PC SEO
 * Version: 3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// ==========================================
// 1. CSS GLOBAL DE BASE
// ==========================================
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('pc-base', content_url('mu-plugins/pc-base.css'), [], '1.0');
}, 5);

// ==========================================
// 2. CHARGEMENT DES MODULES
// ==========================================
$modules = [
    'class-pc-assets.php',
    'class-pc-performance.php',
    'class-pc-seo-helpers.php',       // Chargé en premier parmi les SEO car il contient les outils
    'class-pc-seo-manager.php',
    'class-pc-jsonld-manager.php',
    'class-pc-social-manager.php',
];

foreach ($modules as $module) {
    $path = WPMU_PLUGIN_DIR . '/core-modules/' . $module;
    if (file_exists($path)) {
        require_once $path;
    }
}

// ==========================================
// 3. INITIALISATION DES MOTEURS
// ==========================================
add_action('plugins_loaded', function () {

    // Module Assets (Méthode statique)
    if (class_exists('PC_Assets_Manager')) {
        PC_Assets_Manager::init();
    }

    // Modules Singletons
    if (class_exists('PC_Performance')) {
        PC_Performance::instance();
    }
    if (class_exists('PC_SEO_Manager')) {
        PC_SEO_Manager::instance();
    }
    if (class_exists('PC_JsonLD_Manager')) {
        PC_JsonLD_Manager::instance();
    }
    if (class_exists('PC_Social_Manager')) {
        PC_Social_Manager::instance();
    }
}, 1);

// ==========================================
// 4. CHARGEMENT DESTINATION SHORTCODES
// ==========================================
add_action('plugins_loaded', function () {
    $pc_dest_sc = WPMU_PLUGIN_DIR . '/pc-destination-shortcodes.php';
    if (file_exists($pc_dest_sc)) {
        require_once $pc_dest_sc;
    }
}, 1);
