<?php

/**
 * Plugin Name: PC Cache Loader
 * Description: Initialise le module de gestion des caches critiques (iCal, API) - Architecture V2.5
 * Version: 2.5
 */

if (!defined('ABSPATH')) {
    exit;
}

// Chargement du Core du module
require_once __DIR__ . '/pc-cache/pc-cache-core.php';

/**
 * Initialisation du Singleton
 */
add_action('plugins_loaded', ['PC_Cache_Core', 'get_instance']);
