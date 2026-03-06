<?php

/**
 * PC — Autoloader Centralisé des Modules Métier
 * Remplace l'ancienne multitude de loaders individuels.
 * Utilise require_once pour garantir une architecture "Zéro Doublon".
 */

if (!defined('ABSPATH')) {
    exit;
}

// ==========================================
// LISTE STRICTE DES COEURS DE MODULES
// ==========================================
$pc_core_modules = [
    'pc-cache/pc-cache-core.php',
    'pc-destination/pc-destination-core.php',
    'pc-experiences/pc-experiences-core.php',
    'pc-faq/pc-faq-core.php',
    'pc-header/pc-header-core.php',
    'pc-logement/pc-logement-core.php',
    'pc-performance/pc-performance-core.php',
    'pc-recherche/pc-recherche-core.php',
    'pc-ui-components/pc-ui-components-core.php'
];

// ==========================================
// CHARGEMENT STANDARDISÉ
// ==========================================
foreach ($pc_core_modules as $module_path) {
    // Utilisation stricte de WPMU_PLUGIN_DIR pour une fiabilité absolue
    $full_path = WPMU_PLUGIN_DIR . '/' . $module_path;

    if (file_exists($full_path)) {
        require_once $full_path;
    } else {
        // Log silencieux en cas de module désactivé ou manquant (debugging)
        error_log('[PC Architecture] Module introuvable au chargement : ' . $module_path);
    }
}
