<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Pont de sécurité (Bridge) pour le module Expérience V2
 * Harmonise les nonces entre Axios (V2) et jQuery (Legacy)
 */
class PCR_Experience_Bridge_Controller
{

    public static function init()
    {
        // Priorité 1 pour passer AVANT l'ancien traitement
        add_action('wp_ajax_pc_experience_get_list', [__CLASS__, 'fix_ajax_security'], 1);
    }

    public static function fix_ajax_security()
    {
        // 1. Si Axios envoie 'security', on le copie dans 'nonce' pour l'ancien code
        if (isset($_POST['security']) && !isset($_POST['nonce'])) {
            $_POST['nonce'] = $_POST['security'];
        }

        // 2. Vérification de sécurité avec la clé attendue par l'ancien script
        // On utilise le nonce 'pc_resa_manual_create' généré par WordPress
        if (! check_ajax_referer('pc_resa_manual_create', 'nonce', false)) {
            wp_send_json_error(['message' => 'Erreur de sécurité Bridge : Nonce invalide.'], 400);
            wp_die();
        }

        // 3. On laisse maintenant l'ancien contrôleur s'exécuter normalement
    }
}

PCR_Experience_Bridge_Controller::init();
