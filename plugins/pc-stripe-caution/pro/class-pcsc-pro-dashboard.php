<?php
if (!defined('ABSPATH')) exit;

class PCSC_Pro_Dashboard
{
    public static function init(): void
    {
        add_shortcode('pc_deposit_dashboard', [__CLASS__, 'render']);
        // On écoute les actions POST sur le front pour la création rapide
        add_action('init', [__CLASS__, 'handle_frontend_actions']);
    }

    /**
     * Traitement du formulaire de création rapide (Front-end)
     */
    public static function handle_frontend_actions(): void
    {
        if (!isset($_POST['pcsc_mobile_action']) || $_POST['pcsc_mobile_action'] !== 'create_quick_case') {
            return;
        }

        if (!current_user_can('manage_options')) return;
        if (!isset($_POST['pcsc_mobile_nonce']) || !wp_verify_nonce($_POST['pcsc_mobile_nonce'], 'pcsc_mobile_create')) {
            return;
        }

        $ref = sanitize_text_field($_POST['m_ref']);
        $email = sanitize_email($_POST['m_email']);
        $amount = (int)($_POST['m_amount'] * 100); // Conversion en centimes

        if ($ref && $email && $amount > 0) {
            $id = PCSC_DB::insert_case([
                'booking_ref' => $ref,
                'customer_email' => $email,
                'amount' => $amount
            ]);

            // Redirection pour éviter la resoumission + feedback
            wp_redirect(remove_query_arg(['pc_msg'], add_query_arg('pc_msg', 'created')));
            exit;
        }
    }

    /**
     * Rendu du Shortcode
     */
    public static function render($atts): string
    {
        // 1. SÉCURITÉ : Admin seulement
        if (!current_user_can('manage_options')) {
            return '<p style="color:red; text-align:center;">' . __('Access Denied. Admins only.', 'pc-stripe-caution') . '</p>';
        }

        // 2. Récupération des données
        global $wpdb;
        $table = PCSC_DB::table();
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT 20", ARRAY_A);

        $msg = '';
        if (isset($_GET['pc_msg']) && $_GET['pc_msg'] === 'created') {
            $msg = '<div class="pc-m-alert">✅ Caution créée avec succès !</div>';
        }

        ob_start();
?>
        <style>
            /* CSS Mobile Dashboard */
            .pc-m-dash {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                max-width: 600px;
                margin: 0 auto;
                background: #f3f4f6;
                /* Fond gris clair app */
                padding: 15px;
                border-radius: 8px;
            }

            .pc-m-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
            }

            .pc-m-title {
                margin: 0;
                font-size: 20px;
                color: #111827;
                font-weight: 700;
            }

            /* Bouton Flottant (Nouveau) */
            .pc-fab {
                background: #2563eb;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 30px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                box-shadow: 0 4px 6px rgba(37, 99, 235, 0.3);
            }

            /* Formulaire "Tiroir" */
            .pc-m-form-box {
                background: white;
                padding: 20px;
                border-radius: 12px;
                margin-bottom: 20px;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
                display: none;
            }

            .pc-m-form-box.open {
                display: block;
                animation: slideDown 0.3s ease-out;
            }

            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }

                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            /* Inputs bien larges (1 colonne) */
            .pc-m-input {
                display: block;
                width: 100% !important;
                /* Force la largeur */
                padding: 14px;
                margin-bottom: 15px;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                font-size: 16px;
                /* Empêche le zoom sur iPhone */
                box-sizing: border-box;
                background: #f9fafb;
            }

            .pc-m-input:focus {
                border-color: #2563eb;
                background: #fff;
                outline: none;
            }

            /* Boutons d'action */
            .pc-m-btn {
                display: block;
                width: 100%;
                padding: 14px;
                border: none;
                border-radius: 8px;
                font-weight: bold;
                cursor: pointer;
                text-align: center;
                font-size: 16px;
            }

            .pc-btn-primary {
                background: #2563eb;
                color: white;
                margin-bottom: 10px;
            }

            .pc-btn-cancel {
                background: transparent;
                color: #6b7280;
            }

            /* Cartes */
            .pc-m-card {
                background: white;
                padding: 16px;
                border-radius: 12px;
                margin-bottom: 12px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
                border: 1px solid #f3f4f6;
                display: flex;
                justify-content: space-between;
                align-items: center;
                transition: transform 0.1s;
            }

            .pc-m-card:active {
                transform: scale(0.98);
                background: #f9fafb;
            }

            .pc-m-info h4 {
                margin: 0 0 6px 0;
                font-size: 16px;
                color: #1f2937;
            }

            .pc-m-info p {
                margin: 0;
                font-size: 14px;
                color: #6b7280;
            }

            /* Statuts */
            .pc-m-status {
                font-size: 11px;
                font-weight: 700;
                padding: 6px 10px;
                border-radius: 20px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .st-draft {
                background: #f3f4f6;
                color: #6b7280;
            }

            .st-setup_link_created {
                background: #fff7ed;
                color: #c2410c;
            }

            .st-setup_ok {
                background: #fef3c7;
                color: #b45309;
            }

            .st-authorized {
                background: #eff6ff;
                color: #1d4ed8;
                border: 1px solid #bfdbfe;
            }

            .st-released {
                background: #f0fdf4;
                color: #15803d;
                text-decoration: line-through;
                opacity: 0.7;
            }

            .st-captured {
                background: #fef2f2;
                color: #b91c1c;
            }

            .pc-m-alert {
                background: #dcfce7;
                color: #166534;
                padding: 15px;
                border-radius: 8px;
                margin-bottom: 20px;
                text-align: center;
                font-weight: bold;
            }
        </style>

        <div class="pc-m-dash">
            <div class="pc-m-header">
                <h3 class="pc-m-title"><?php _e('Mobile Dashboard', 'pc-stripe-caution'); ?></h3>
                <button class="pc-fab" onclick="document.getElementById('pcForm').classList.toggle('open')">+ <?php _e('New', 'pc-stripe-caution'); ?></button>
            </div>

            <?php echo $msg; ?>

            <div id="pcForm" class="pc-m-form-box">
                <form method="post">
                    <h4 style="margin-top:0;"><?php _e('New Deposit Request', 'pc-stripe-caution'); ?></h4>
                    <input type="hidden" name="pcsc_mobile_action" value="create_quick_case">
                    <?php wp_nonce_field('pcsc_mobile_create', 'pcsc_mobile_nonce'); ?>

                    <input type="text" name="m_ref" class="pc-m-input" placeholder="Ref (ex: Resa-2024)" required>
                    <input type="email" name="m_email" class="pc-m-input" placeholder="Email Client" required>
                    <input type="number" name="m_amount" class="pc-m-input" placeholder="Montant (€)" required>

                    <button type="submit" class="pc-m-btn pc-btn-primary"><?php _e('Create', 'pc-stripe-caution'); ?></button>
                    <button type="button" class="pc-m-btn pc-btn-cancel" onclick="document.getElementById('pcForm').classList.remove('open')"><?php _e('Cancel', 'pc-stripe-caution'); ?></button>
                </form>
            </div>

            <?php if (empty($rows)): ?>
                <p style="text-align:center; color:#9ca3af;"><?php _e('No deposits found.', 'pc-stripe-caution'); ?></p>
            <?php else: ?>
                <?php foreach ($rows as $row):
                    $st = $row['status'];
                    $amt = number_format($row['amount'] / 100, 0, ',', ' ') . '€';
                    // Traduction simple des statuts pour mobile
                    $st_label = $st;
                    if ($st == 'draft') $st_label = 'Brouillon';
                    if ($st == 'setup_link_created') $st_label = 'Lien Env.';
                    if ($st == 'setup_ok') $st_label = 'Carte OK';
                    if ($st == 'authorized') $st_label = 'ACTIVE';
                    if ($st == 'released') $st_label = 'Libérée';
                    if ($st == 'captured') $st_label = 'Encaissée';
                ?>
                    <div class="pc-m-card" onclick="window.location.href='<?php echo admin_url('admin.php?page=pc-stripe-caution&case_id=' . $row['id']); ?>'">
                        <div class="pc-m-info">
                            <h4><?php echo esc_html($row['booking_ref']); ?></h4>
                            <p><?php echo esc_html($row['customer_email']); ?> • <strong><?php echo $amt; ?></strong></p>
                        </div>
                        <span class="pc-m-status st-<?php echo $st; ?>"><?php echo $st_label; ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <p style="text-align:center; font-size:11px; color:#9ca3af; margin-top:20px;">
                <?php _e('Tap a card to manage details in Admin.', 'pc-stripe-caution'); ?>
            </p>
        </div>
<?php
        return ob_get_clean();
    }
}
