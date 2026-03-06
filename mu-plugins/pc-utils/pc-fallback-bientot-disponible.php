<?php
/**
 * Fallback "Bientôt disponible" pour expériences & destinations inexistantes.
 * - Redirige en 302 vers /bientot-disponible/ si une URL commence par
 *   /experience(s)/ ou /destination(s)/ et retourne 404.
 * - Journalise les redirections dans /uploads/pc-fallback.log (si possible).
 */

if (!defined('ABSPATH')) exit;

/** === CONFIG MINIMALE === */
const PC_FALLBACK_TARGET_SLUG = 'bientot-disponible'; // page Elementor
$pc_fallback_prefixes = ['experience', 'experiences', 'destination', 'destinations'];

/** === LOG helper === */
function pc_fallback_log($from, $to) {
    // Dossier uploads
    $uploads = wp_upload_dir();
    if (!empty($uploads['basedir']) && is_dir($uploads['basedir']) && is_writable($uploads['basedir'])) {
        $file = trailingslashit($uploads['basedir']) . 'pc-fallback.log';
        $line = sprintf("[%s] from=\"/%s\" to=\"%s\" ref=\"%s\" ip=%s\n",
            gmdate('Y-m-d H:i:s'),
            ltrim($from, '/'),
            $to,
            isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '-',
            isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '-'
        );
        @file_put_contents($file, $line, FILE_APPEND);
    } else {
        // Secours : error_log
        error_log('[PC Fallback] ' . $from . ' -> ' . $to);
    }
}

/** === REDIRECTION === */
add_action('template_redirect', function() use ($pc_fallback_prefixes) {

    // Bypass manuel si besoin : ajouter ?no-fallback=1 à l’URL
    if (!empty($_GET['no-fallback'])) return;

    // On n'agit QUE sur les 404
    if (!is_404()) return;

    global $wp;
    $request = isset($wp->request) ? trim($wp->request, '/') : '';

    if ($request === '') return;

    // Ne jamais rediriger la cible elle-même
    if (stripos($request, PC_FALLBACK_TARGET_SLUG) === 0) return;

    // Détection des préfixes "experience(s)" / "destination(s)"
    foreach ($pc_fallback_prefixes as $prefix) {
        if (stripos($request, $prefix . '/') === 0) {
            $target = home_url('/' . PC_FALLBACK_TARGET_SLUG . '/');

            // 302 temporaire (on créera la vraie fiche plus tard)
            pc_fallback_log($request, $target);
            wp_redirect($target, 302);
            exit;
        }
    }
}, 1);
