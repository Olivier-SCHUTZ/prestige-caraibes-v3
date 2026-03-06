<?php

/**
 * PC — Loader Global
 * Point d'entrée pour charger les utilitaires isolés et les modules d'environnement.
 */

if (!defined('ABSPATH')) {
    exit;
}

// ==========================================
// 1. UTILITAIRES GLOBAUX (Toujours actifs)
// ==========================================
$pc_utils = [
    'pc-fallback-bientot-disponible.php',
    'pc-maintenance.php'
];

foreach ($pc_utils as $util) {
    $util_path = WPMU_PLUGIN_DIR . '/pc-utils/' . $util;
    if (file_exists($util_path)) {
        require_once $util_path;
    }
}

// ==========================================
// 2. UTILITAIRES DE STAGING (Environnement de dev)
// ==========================================
if (defined('PC_ENV') && PC_ENV === 'staging') {
    $sandbox_path = WPMU_PLUGIN_DIR . '/pc-utils/pc-sandbox-menu-prefix.php';
    if (file_exists($sandbox_path)) {
        require_once $sandbox_path;
    }
}
