<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Charge les assets compilés par Vite.js via le manifest.json
 * Fait partie de la Phase 2 : Architecture V2
 */
class PCR_Vite_Loader
{
    private static $manifest = null;

    /**
     * Charge et met en cache le manifest JSON généré par Vite
     */
    private static function load_manifest()
    {
        if (self::$manifest !== null) {
            return;
        }

        // Depuis Vite 5, le manifest est stocké dans dist/.vite/manifest.json
        $manifest_path = PC_RES_CORE_PATH . 'dist/.vite/manifest.json';

        if (file_exists($manifest_path)) {
            self::$manifest = json_decode(file_get_contents($manifest_path), true);
        } else {
            self::$manifest = [];
            error_log('[PC RESERVATION] Erreur : manifest.json de Vite introuvable.');
        }
    }

    /**
     * Enqueue un point d'entrée Vite (JS + CSS) dynamiquement
     */
    public static function enqueue_entry($entry_path)
    {
        self::load_manifest();

        if (empty(self::$manifest) || !isset(self::$manifest[$entry_path])) {
            error_log('[PC RESERVATION] Vite Loader : Entrée introuvable dans le manifest -> ' . $entry_path);
            return;
        }

        $entry = self::$manifest[$entry_path];
        $handle = 'pcr-vite-' . md5($entry_path);

        // 1. Enqueue le fichier JS principal (sans dépendance jQuery !)
        $js_file = PC_RES_CORE_URL . 'dist/' . $entry['file'];
        wp_enqueue_script($handle, $js_file, [], null, true);

        // 2. Enqueue les fichiers CSS associés (si présents)
        if (!empty($entry['css'])) {
            foreach ($entry['css'] as $index => $css_file) {
                wp_enqueue_style($handle . '-css-' . $index, PC_RES_CORE_URL . 'dist/' . $css_file, [], null);
            }
        }
    }
}

/**
 * ⚠️ CRUCIAL POUR VITE : Les scripts compilés doivent être chargés en tant que modules ES.
 * Ce filtre intercepte nos scripts Vite et leur ajoute l'attribut type="module".
 */
add_filter('script_loader_tag', function ($tag, $handle, $src) {
    if (strpos($handle, 'pcr-vite-') === 0) {
        return '<script type="module" src="' . esc_url($src) . '"></script>' . "\n";
    }
    return $tag;
}, 10, 3);
