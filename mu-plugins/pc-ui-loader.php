<?php

/**
 * Plugin Name: PC UI Components Loader
 * Description: Charge le module des composants UI (Vignettes, Grilles, etc.) - Architecture V2.5
 * Version: 2.5
 */

if (!defined('ABSPATH')) {
    exit;
}

// Chargement du fichier Core principal
require_once __DIR__ . '/pc-ui-components/pc-ui-components-core.php';

// Initialisation du Singleton au hook 'plugins_loaded'
add_action('plugins_loaded', ['PC_UI_Components_Core', 'get_instance'], 10);
