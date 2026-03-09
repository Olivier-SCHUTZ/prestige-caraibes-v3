<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ancienne classe d'export iCal (Facade / Proxy).
 * @deprecated Cette classe redirige désormais vers le nouveau service PCR_Ical_Exporter.
 * Conservée pour garantir le "zéro régression" des URL d'export existantes.
 */
class PCR_Ical_Export
{
    /**
     * Initialise l'écouteur de requête iCal
     */
    public static function init()
    {
        add_action('init', [__CLASS__, 'listen_for_export_request']);
    }

    /**
     * Intercepte la requête et délègue au nouveau service
     */
    public static function listen_for_export_request()
    {
        if (!isset($_GET['pc_action']) || $_GET['pc_action'] !== 'ical_export') {
            return;
        }

        $logement_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $token       = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        $mode        = isset($_GET['mode']) && $_GET['mode'] === 'full' ? 'full' : 'simple';

        if (!$logement_id || !$token) {
            wp_die('Paramètres manquants.', 'Erreur iCal', ['response' => 400]);
        }

        $exporter = PCR_Ical_Exporter::get_instance();

        if (!$exporter->verify_token($logement_id, $token)) {
            wp_die('Accès refusé. Token invalide.', 'Erreur iCal', ['response' => 403]);
        }

        $exporter->render_ical($logement_id, $mode);
        exit;
    }

    /**
     * Proxy pour la génération de token
     */
    public static function get_token($logement_id)
    {
        return PCR_Ical_Exporter::get_instance()->get_token($logement_id);
    }

    /**
     * Proxy pour la génération de l'URL
     */
    public static function get_export_url($logement_id, $mode = 'simple')
    {
        return PCR_Ical_Exporter::get_instance()->get_export_url($logement_id, $mode);
    }
}
