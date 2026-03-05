<?php

/**
 * Plugin Name: Prestige Caraïbes - FAQ Loader
 * Description: Loader pour le module mu-plugin pc-faq.
 * Version: 2.5.0
 * Author: Prestige Caraïbes
 */

if (!defined('ABSPATH')) {
    exit;
}

// Charge le cœur du module qui se trouve dans le sous-dossier
$pc_faq_path = trailingslashit(WPMU_PLUGIN_DIR) . 'pc-faq/pc-faq-core.php';

if (file_exists($pc_faq_path)) {
    require_once $pc_faq_path;
}
