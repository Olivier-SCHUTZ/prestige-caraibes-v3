<?php

/**
 * Plugin Name: Prestige Caraïbes - Header Loader
 * Description: Loader pour le module mu-plugin pc-header.
 * Version: 2.0.0
 * Author: Prestige Caraïbes
 */

if (!defined('ABSPATH')) {
    exit;
}

// Charge le cœur du module qui se trouve dans le sous-dossier
$pc_header_path = trailingslashit(WPMU_PLUGIN_DIR) . 'pc-header/pc-header-core.php';

if (file_exists($pc_header_path)) {
    require_once $pc_header_path;
}
