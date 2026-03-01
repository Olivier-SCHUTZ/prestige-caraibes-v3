<?php

/**
 * Plugin Name: Prestige Caraïbes — Module Recherche
 * Description: Charge l'architecture modulaire du système de recherche unifié (logements et expériences).
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Charge l'architecture modulaire du dossier pc-recherche
$core_file = WP_CONTENT_DIR . '/mu-plugins/pc-recherche/pc-recherche-core.php';

if (file_exists($core_file)) {
    require_once $core_file;
}
