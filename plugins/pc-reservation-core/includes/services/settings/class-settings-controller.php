<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCR Settings Controller - Gère l'interface AJAX et les Webhooks
 * Injecte le JS dans l'admin et intercepte les requêtes du simulateur.
 * Pattern Singleton.
 * * @since 2.0.0 (Refactoring)
 */
class PCR_Settings_Controller
{
    /**
     * Instance unique de la classe.
     * @var PCR_Settings_Controller|null
     */
    private static $instance = null;

    /**
     * Constructeur privé (Singleton).
     */
    private function __construct() {}

    /**
     * Récupère l'instance unique.
     * @return PCR_Settings_Controller
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialise les hooks WordPress.
     */
    public function init_hooks()
    {
        add_action('wp_ajax_pc_simulate_webhook', [$this, 'ajax_handle_simulation']);
        add_action('admin_footer', [$this, 'print_admin_scripts']);
    }

    /**
     * Gère la simulation de webhook depuis la page de configuration (non-AJAX fallback).
     */
    public function handle_webhook_simulation()
    {
        if (!isset($_POST['pc_simulate_webhook']) || !isset($_POST['pc_webhook_simulation_payload'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die('Accès refusé');
        }

        $payload_json = trim(stripslashes($_POST['pc_webhook_simulation_payload']));
        $payload = json_decode($payload_json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_die('❌ <strong>Erreur JSON :</strong> ' . json_last_error_msg());
        }

        try {
            // Appel au nouveau service de simulation
            $result = PCR_Webhook_Simulator::get_instance()->process_simulation($payload);

            if ($result['success']) {
                $html = '<div style="font-family:sans-serif; padding:20px; background:#dcfce7; border:1px solid #22c55e; color:#14532d; max-width:600px; margin:50px auto; border-radius:8px;">';
                $html .= '<h2>✅ Simulation Réussie !</h2>';
                $html .= '<p>' . esc_html($result['message']) . '</p>';
                $html .= '<ul>';
                if (isset($result['reservation_id'])) $html .= '<li><strong>Réservation :</strong> #' . intval($result['reservation_id']) . '</li>';
                $html .= '</ul>';
                $html .= '<a href="javascript:history.back()" style="display:inline-block; margin-top:10px; padding:10px 20px; background:#166534; color:white; text-decoration:none; border-radius:4px;">⬅️ Retour</a>';
                $html .= '</div>';
                wp_die($html, 'Simulation OK', ['response' => 200]);
            } else {
                $html = '<div style="font-family:sans-serif; padding:20px; background:#fee2e2; border:1px solid #ef4444; color:#7f1d1d; max-width:600px; margin:50px auto; border-radius:8px;">';
                $html .= '<h2>❌ Échec de la Simulation</h2>';
                $html .= '<p><strong>Erreur :</strong> ' . esc_html($result['message']) . '</p>';
                $html .= '<a href="javascript:history.back()" style="display:inline-block; margin-top:10px; padding:10px 20px; background:#991b1b; color:white; text-decoration:none; border-radius:4px;">⬅️ Retour & Corriger</a>';
                $html .= '</div>';
                wp_die($html, 'Simulation Erreur', ['response' => 200]);
            }
        } catch (Exception $e) {
            wp_die('❌ <strong>Exception :</strong> ' . $e->getMessage());
        }
    }

    /**
     * Injecte le Javascript pour le bouton de simulation.
     */
    public function print_admin_scripts()
    {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'pc-reservation-config') === false) {
            return;
        }
?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#pc_trigger_simulation').on('click', function(e) {
                    e.preventDefault();

                    var $btn = $(this);
                    var $resultBox = $('#pc_simulation_results');

                    var jsonPayload = $('textarea[name="acf[field_pc_webhook_simulation_payload]"]').val();

                    if (!jsonPayload) {
                        alert('Le champ JSON est vide !');
                        return;
                    }

                    $btn.prop('disabled', true).text('⏳ Traitement...');
                    $resultBox.hide().removeClass().text('');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'pc_simulate_webhook',
                            payload: jsonPayload,
                            security: '<?php echo wp_create_nonce("pc_sim_nonce"); ?>'
                        },
                        success: function(response) {
                            $resultBox.show();
                            if (response.success) {
                                $resultBox.css({
                                    'background': '#dcfce7',
                                    'color': '#166534',
                                    'border': '1px solid #22c55e'
                                });
                                $resultBox.html('<strong>✅ Succès :</strong> ' + response.data.message);
                            } else {
                                $resultBox.css({
                                    'background': '#fee2e2',
                                    'color': '#991b1b',
                                    'border': '1px solid #ef4444'
                                });
                                $resultBox.html('<strong>❌ Erreur :</strong> ' + (response.data.message || 'Erreur inconnue'));
                            }
                        },
                        error: function() {
                            $resultBox.show().css({
                                'background': '#fee2e2',
                                'color': '#991b1b'
                            });
                            $resultBox.text('❌ Erreur serveur (500)');
                        },
                        complete: function() {
                            $btn.prop('disabled', false).text('🚀 Lancer la simulation (AJAX)');
                        }
                    });
                });
            });
        </script>
<?php
    }

    /**
     * Traite la simulation via AJAX.
     */
    public function ajax_handle_simulation()
    {
        check_ajax_referer('pc_sim_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Accès refusé']);
        }

        $payload_json = isset($_POST['payload']) ? stripslashes($_POST['payload']) : '';
        $payload = json_decode($payload_json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(['message' => 'JSON Invalide : ' . json_last_error_msg()]);
        }

        try {
            // Appel au nouveau service de simulation
            $result = PCR_Webhook_Simulator::get_instance()->process_simulation($payload);

            if ($result['success']) {
                $msg = $result['message'];
                if (isset($result['reservation_id'])) $msg .= " (Résa #{$result['reservation_id']})";
                wp_send_json_success(['message' => $msg]);
            } else {
                wp_send_json_error(['message' => $result['message']]);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Exception : ' . $e->getMessage()]);
        }
    }
}
