<?php
if (!defined('ABSPATH')) {
    exit; // Sécurité : Empêche l'accès direct au fichier
}

/**
 * Classe parente abstraite pour tous les contrôleurs AJAX.
 * Centralise la sécurité (Nonces, Permissions) et les réponses JSON.
 */
abstract class PCR_Base_Ajax_Controller
{
    /**
     * Vérifie le nonce, la connexion de l'utilisateur et ses droits.
     * Stoppe l'exécution et renvoie une erreur JSON si une vérification échoue.
     *
     * @param string $nonce_action L'action du nonce à vérifier.
     * @param string $nonce_key    La clé dans $_REQUEST (souvent 'nonce' ou '_wpnonce').
     */
    protected static function verify_access($nonce_action = 'pc_resa_manual_create', $nonce_key = 'nonce')
    {
        $nonce = isset($_REQUEST[$nonce_key]) ? sanitize_text_field(wp_unslash($_REQUEST[$nonce_key])) : '';

        if (!$nonce || !wp_verify_nonce($nonce, $nonce_action)) {
            self::send_error('Nonce invalide - veuillez actualiser la page.', 400);
        }

        if (!is_user_logged_in()) {
            self::send_error('Veuillez vous connecter.', 403);
        }

        if (!self::current_user_can_manage()) {
            self::send_error('Action non autorisée.', 403);
        }
    }

    /**
     * Vérifie si l'utilisateur actuel a les droits de gestion.
     * Gère le filtre dynamique existant pour la rétrocompatibilité.
     *
     * @return bool
     */
    protected static function current_user_can_manage()
    {
        $capability = apply_filters('pc_resa_manual_creation_capability', 'manage_options');
        return current_user_can($capability);
    }

    /**
     * Envoie une réponse JSON de succès et stoppe l'exécution.
     *
     * @param mixed $data Les données à renvoyer au frontend.
     */
    protected static function send_success($data = [])
    {
        wp_send_json_success($data);
    }

    /**
     * Envoie une réponse JSON d'erreur avec un code HTTP et stoppe l'exécution.
     *
     * @param string|array $message Le message d'erreur ou le tableau d'erreurs.
     * @param int          $status_code Le code statut HTTP (ex: 400, 403, 404, 500).
     */
    protected static function send_error($message, $status_code = 400)
    {
        $response = is_array($message) ? $message : ['message' => $message];
        wp_send_json_error($response, $status_code);
    }
}
