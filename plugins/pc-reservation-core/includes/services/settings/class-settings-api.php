<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCR Settings API - API Native pour la configuration globale
 * Remplace la dépendance aux options ACF.
 * Pattern Singleton.
 * @since 3.0.0 (Refactoring Vue.js)
 */
class PCR_Settings_API
{
    /**
     * Instance unique.
     * @var PCR_Settings_API|null
     */
    private static $instance = null;

    /**
     * Liste exhaustive de toutes les clés de configuration (identiques aux anciens noms ACF).
     */
    private $settings_keys = [
        // Paiements (Stripe)
        'pc_stripe_mode',
        'pc_stripe_test_pk',
        'pc_stripe_test_sk',
        'pc_stripe_live_pk',
        'pc_stripe_live_sk',
        'pc_stripe_webhook_secret',
        // Messagerie
        'pc_default_phone_prefix',
        'pc_whatsapp_template',
        'pc_email_signature',
        // API / Connectivité
        'pc_api_provider',
        'pc_api_key',
        'pc_webhook_secret',
        'pc_inbound_email_enabled',
        // Identité / Divers
        'pc_general_logo',
        'pc_dashboard_slug',
        'pc_dashboard_menu_item',
        'pc_dashboard_logo',
        // Documents & Légal
        'pc_pdf_logo',
        'pc_pdf_primary_color',
        'pc_legal_name',
        'pc_legal_address',
        'pc_legal_email',
        'pc_legal_phone',
        'pc_legal_siret',
        'pc_legal_tva',
        'pc_legal_rcs',
        'pc_legal_capital',
        // Banque
        'pc_bank_name',
        'pc_bank_iban',
        'pc_bank_bic',
        // Numérotation
        'pc_invoice_prefix',
        'pc_invoice_next',
        'pc_quote_prefix',
        'pc_credit_note_prefix',
        'pc_credit_note_next',
        // CGV
        'cgv_location',
        'cgv_experience',
        'cgv_sejour',
        'cgv_custom'
    ];

    private function __construct()
    {
        $this->init_hooks();
    }

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function init_hooks()
    {
        add_action('wp_ajax_pc_get_global_settings', [$this, 'ajax_get_settings']);
        add_action('wp_ajax_pc_save_global_settings', [$this, 'ajax_save_settings']);
    }

    /**
     * Récupère toutes les configurations et les renvoie à Vue.js
     */
    public function ajax_get_settings()
    {
        // Vérification du nonce (à adapter avec la variable globale de ton app Vue)
        check_ajax_referer('pc_resa_manual_create', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Accès refusé.']);
        }

        $settings = [];
        foreach ($this->settings_keys as $key) {
            // Le préfixe 'options_' est crucial pour la rétrocompatibilité avec ACF
            $settings[$key] = get_option('options_' . $key, '');
        }

        wp_send_json_success(['data' => $settings]);
    }

    /**
     * Sauvegarde les configurations reçues depuis Vue.js
     */
    public function ajax_save_settings()
    {
        check_ajax_referer('pc_resa_manual_create', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Accès refusé.']);
        }

        $payload_json = isset($_POST['payload']) ? stripslashes($_POST['payload']) : '';
        $payload = json_decode($payload_json, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
            wp_send_json_error(['message' => 'Données invalides.']);
        }

        foreach ($this->settings_keys as $key) {
            if (isset($payload[$key])) {
                $value = $payload[$key];

                // Nettoyage basique selon le type de champ (à affiner si besoin)
                if (in_array($key, ['cgv_location', 'cgv_experience', 'cgv_sejour', 'cgv_custom', 'pc_email_signature'])) {
                    // Champs WYSIWYG
                    $value = wp_kses_post($value);
                } else {
                    // Champs standards
                    $value = is_string($value) ? sanitize_text_field($value) : $value;
                }

                // Sauvegarde native WordPress (en gardant le préfixe ACF)
                update_option('options_' . $key, $value);
            }
        }

        wp_send_json_success(['message' => 'Configuration sauvegardée avec succès !']);
    }
}

// Initialisation (à placer idéalement dans ton orchestrateur principal, ex: pc-reservation-core.php)
PCR_Settings_API::get_instance();
