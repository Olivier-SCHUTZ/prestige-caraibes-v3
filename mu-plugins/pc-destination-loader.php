<?php

/**
 * Plugin Name: Prestige Caraïbes — Shortcodes Fiche Destination
 * Description: Déclare les shortcodes et charge les assets pour les pages "Destination".
 * Version: 2.0 (Refonte Modulaire)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Charge l'architecture modulaire du dossier pc-destination
$core_file = WP_CONTENT_DIR . '/mu-plugins/pc-destination/pc-destination-core.php';

if (file_exists($core_file)) {
    require_once $core_file;
}
