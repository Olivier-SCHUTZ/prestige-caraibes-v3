<?php

/**
 * PC Cache Helper
 * Fonctions utilitaires génériques et système de journalisation (Logs)
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Cache_Helper
{

    /**
     * Système de log sécurisé spécifique au module Cache.
     * Conforme aux directives de l'architecture pour le debugging.
     *
     * @param string $message Le message d'erreur ou d'information
     * @param string $level Le niveau de criticité (INFO, WARNING, ERROR, CRITICAL)
     */
    public static function log($message, $level = 'INFO')
    {
        // On ne loggue que si le mode debug de WordPress est activé
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            $prefix = '[PC CACHE ' . strtoupper($level) . '] ';
            error_log($prefix . $message);
        }
    }
}
