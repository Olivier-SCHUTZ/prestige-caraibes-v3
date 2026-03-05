<?php

/**
 * Plugin Name: PC Performance Loader
 * Description: Initialise le module de Performance avancé (LCP, Preload, Preconnect) - Architecture V2.5
 * Version: 2.5
 */

if (!defined('ABSPATH')) {
    exit;
}

// Chargement du Core du module
require_once __DIR__ . '/pc-performance/pc-performance-core.php';

/**
 * Initialisation du Singleton
 */
add_action('plugins_loaded', function () {
    PC_Performance_Core::get_instance();
});
