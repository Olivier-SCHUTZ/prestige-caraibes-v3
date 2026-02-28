<?php

/**
 * Plugin Name: Prestige Caraïbes — Shortcodes Fiche Expérience
 * Description: Déclare les shortcodes et charge les assets pour les pages "Expérience".
 * Version: 2.1 (Refonte Modulaire)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Charge l'architecture modulaire du dossier pc-experiences
$core_file = WP_CONTENT_DIR . '/mu-plugins/pc-experiences/pc-experiences-core.php';

if (file_exists($core_file)) {
    require_once $core_file;
}
