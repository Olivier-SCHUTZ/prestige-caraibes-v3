<?php
if (!defined('ABSPATH')) exit;

class PCSC_Pro_Dashboard
{
    public static function init(): void
    {
        add_shortcode('pc_deposit_dashboard', [__CLASS__, 'render']);
        add_action('init', [__CLASS__, 'handle_frontend_actions']);
    }

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
        $amount = (int)($_POST['m_amount'] * 100);
        // Ajout de la récupération des dates
        $arr = sanitize_text_field($_POST['m_arrivee']);
        $dep = sanitize_text_field($_POST['m_depart']);

        if ($ref && $email && $amount > 0) {
            PCSC_DB::insert_case([
                'booking_ref' => $ref,
                'customer_email' => $email,
                'amount' => $amount,
                // Insertion en base (null si vide)
                'date_arrivee' => $arr ?: null,
                'date_depart' => $dep ?: null
            ]);
            wp_redirect(remove_query_arg(['pc_msg'], add_query_arg('pc_msg', 'created')));
            exit;
        }
    }

    public static function render($atts): string
    {
        if (!current_user_can('manage_options')) {
            return '<p style="color:red; text-align:center;">' . __('Access Denied. Admins only.', 'pc-stripe-caution') . '</p>';
        }

        global $wpdb;
        $table = PCSC_DB::table();
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT 20", ARRAY_A);

        $msg = '';
        if (isset($_GET['pc_msg']) && $_GET['pc_msg'] === 'created') {
            $msg = '<div class="pc-m-alert">✅ ' . __('Deposit created successfully!', 'pc-stripe-caution') . '</div>';
        }

        ob_start();
?>
        <style>
            /* --- CSS MOBILE DASHBOARD --- */

            /* Reset pour éviter les conflits avec le thème */
            .pc-m-dash * {
                box-sizing: border-box !important;
                /* CRUCIAL : Empêche le débordement */
            }

            .pc-m-dash {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                width: 100%;
                max-width: 600px;
                margin: 0 auto;
                background: #f3f4f6;
                padding: 15px;
                border-radius: 8px;
                overflow: hidden;
                /* Sécurité supplémentaire */
            }

            .pc-m-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
            }

            .pc-m-title {
                margin: 0 !important;
                font-size: 18px !important;
                color: #111827;
                font-weight: 700;
                line-height: 1.2;
            }

            /* Bouton "+ Nouveau" */
            .pc-fab {
                background: #2563eb;
                color: white !important;
                border: none;
                padding: 8px 16px;
                border-radius: 30px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                text-decoration: none;
                box-shadow: 0 2px 4px rgba(37, 99, 235, 0.3);
                white-space: nowrap;
            }

            /* Formulaire Tiroir */
            .pc-m-form-box {
                background: white;
                padding: 20px;
                border-radius: 12px;
                margin-bottom: 20px;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
                display: none;
            }

            .pc-m-form-box.open {
                display: block;
            }

            /* Inputs : 1 colonne stricte */
            .pc-m-input {
                display: block;
                width: 100% !important;
                max-width: 100%;
                padding: 12px;
                margin: 0 0 15px 0;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                font-size: 16px;
                /* Évite le zoom auto sur iPhone */
                background: #f9fafb;
                height: auto;
            }

            /* Boutons actions */
            .pc-m-btn {
                display: block;
                width: 100% !important;
                padding: 14px;
                border: none;
                border-radius: 8px;
                font-weight: bold;
                cursor: pointer;
                text-align: center;
                font-size: 16px;
                margin-bottom: 10px;
            }

            .pc-btn-primary {
                background: #2563eb;
                color: white !important;
            }

            .pc-btn-cancel {
                background: transparent;
                color: #6b7280;
                border: 1px solid #e5e7eb;
            }

            /* Liste des Cartes */
            .pc-m-card {
                background: white;
                padding: 15px;
                border-radius: 10px;
                margin-bottom: 12px;
                border: 1px solid #f3f4f6;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
                display: flex;
                justify-content: space-between;
                align-items: center;
                cursor: pointer;
                text-decoration: none !important;
                /* Enlève le soulignement des liens */
            }

            .pc-m-info {
                flex: 1;
                /* Prend toute la place dispo */
                padding-right: 10px;
            }

            .pc-m-info h4 {
                margin: 0 0 5px 0 !important;
                font-size: 15px;
                color: #111827;
                font-weight: 600;
            }

            .pc-m-info p {
                margin: 0 !important;
                font-size: 13px;
                color: #6b7280;
            }

            /* Badges Statuts */
            .pc-m-status {
                font-size: 10px;
                font-weight: 700;
                padding: 5px 8px;
                border-radius: 12px;
                text-transform: uppercase;
                white-space: nowrap;
                flex-shrink: 0;
                /* Empêche le badge de s'écraser */
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
                opacity: 0.6;
            }

            .st-captured {
                background: #fef2f2;
                color: #b91c1c;
            }

            .pc-m-alert {
                background: #dcfce7;
                color: #166534;
                padding: 12px;
                border-radius: 8px;
                margin-bottom: 15px;
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
                    <h4 style="margin: 0 0 15px 0;"><?php _e('New Deposit Request', 'pc-stripe-caution'); ?></h4>
                    <input type="hidden" name="pcsc_mobile_action" value="create_quick_case">
                    <?php wp_nonce_field('pcsc_mobile_create', 'pcsc_mobile_nonce'); ?>

                    <label style="display:block; margin-bottom:5px; font-weight:600; font-size:13px;"><?php _e('Reference', 'pc-stripe-caution'); ?></label>
                    <input type="text" name="m_ref" class="pc-m-input" placeholder="ex: Booking-2024" required>

                    <label style="display:block; margin-bottom:5px; font-weight:600; font-size:13px;"><?php _e('Client Email', 'pc-stripe-caution'); ?></label>
                    <input type="email" name="m_email" class="pc-m-input" placeholder="client@email.com" required>

                    <label style="display:block; margin-bottom:5px; font-weight:600; font-size:13px;"><?php _e('Amount (€)', 'pc-stripe-caution'); ?></label>
                    <input type="number" name="m_amount" class="pc-m-input" placeholder="500" required>

                    <label style="display:block; margin-bottom:5px; font-weight:600; font-size:13px;"><?php _e('Arrival Date', 'pc-stripe-caution'); ?></label>
                    <input type="date" name="m_arrivee" class="pc-m-input">

                    <label style="display:block; margin-bottom:5px; font-weight:600; font-size:13px;"><?php _e('Departure Date', 'pc-stripe-caution'); ?></label>
                    <input type="date" name="m_depart" class="pc-m-input">

                    <div style="margin-top:20px;">
                        <button type="submit" class="pc-m-btn pc-btn-primary"><?php _e('Create', 'pc-stripe-caution'); ?></button>
                        <button type="button" class="pc-m-btn pc-btn-cancel" onclick="document.getElementById('pcForm').classList.remove('open')"><?php _e('Cancel', 'pc-stripe-caution'); ?></button>
                    </div>
                </form>
            </div>

            <?php if (empty($rows)): ?>
                <p style="text-align:center; color:#9ca3af; padding: 20px;"><?php _e('No deposits found.', 'pc-stripe-caution'); ?></p>
            <?php else: ?>
                <?php foreach ($rows as $row):
                    $st = $row['status'];
                    $amt = number_format($row['amount'] / 100, 0, ',', ' ') . '€';

                    // Labels courts pour mobile
                    $st_label = $st;
                    if ($st == 'draft') $st_label = 'Draft';
                    if ($st == 'setup_link_created') $st_label = 'Link Sent';
                    if ($st == 'setup_ok') $st_label = 'Card OK';
                    if ($st == 'authorized') $st_label = 'ACTIVE';
                    if ($st == 'released') $st_label = 'Released';
                    if ($st == 'captured') $st_label = 'Charged';
                ?>
                    <a href="<?php echo admin_url('admin.php?page=pc-stripe-caution&case_id=' . $row['id'] . '&source=mobile'); ?>" class="pc-m-card">
                        <div class="pc-m-info">
                            <h4><?php echo esc_html($row['booking_ref']); ?></h4>
                            <p><?php echo esc_html($row['customer_email']); ?> • <strong><?php echo $amt; ?></strong></p>
                        </div>
                        <span class="pc-m-status st-<?php echo $st; ?>"><?php echo $st_label; ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>

            <p style="text-align:center; font-size:11px; color:#9ca3af; margin-top:20px;">
                <?php _e('Tap a card to manage details.', 'pc-stripe-caution'); ?>
            </p>
        </div>
<?php
        return ob_get_clean();
    }
}
