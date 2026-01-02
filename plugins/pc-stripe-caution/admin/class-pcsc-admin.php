<?php
if (!defined('ABSPATH')) exit;

class PCSC_Admin
{
    public static function init(): void
    {
        // 1. Charger la classe d'aide
        require_once PCSC_PLUGIN_DIR . 'admin/class-pcsc-help.php';

        // 2. Enregistrer les menus
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_menu', ['PCSC_Help', 'init']); // <-- Ajoute le sous-menu Aide
    }

    public static function register_menu(): void
    {
        add_menu_page(
            __('Deposit Management', 'pc-stripe-caution'),
            __('Stripe Deposits', 'pc-stripe-caution'),
            'manage_options',
            'pc-stripe-caution',
            [__CLASS__, 'render_dashboard_page'],
            'dashicons-shield',
            56
        );
    }

    public static function render_dashboard_page(): void
    {
        echo self::render_admin_logic();
    }

    // --- UTILS ---
    private static function check_access(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Access denied.', 'pc-stripe-caution'));
        }
    }

    private static function prevent_cache(): void
    {
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        nocache_headers();
    }

    private static function get_url(array $params = []): string
    {
        $base = admin_url('admin.php?page=pc-stripe-caution');
        return add_query_arg($params, $base);
    }

    private static function safe_redirect(string $url): void
    {
        if (!headers_sent()) {
            wp_safe_redirect($url);
            exit;
        } else {
            echo "<script>window.location.href = '" . esc_url_raw($url) . "';</script>";
            exit;
        }
    }

    private static function eur_to_cents($input): int
    {
        $clean = str_replace([',', ' '], ['.', ''], (string)$input);
        return (int) round((float)$clean * 100);
    }

    private static function get_status_label(string $status): string
    {
        $labels = [
            'draft'              => __('Waiting for Input', 'pc-stripe-caution'), // En attente
            'setup_link_created' => __('Link Sent', 'pc-stripe-caution'),       // Lien envoyé
            'setup_ok'           => __('Card Saved', 'pc-stripe-caution'),      // Carte enregistrée
            'authorized'         => __('Hold Active', 'pc-stripe-caution'),     // Empreinte active
            'captured'           => __('Charged', 'pc-stripe-caution'),         // Encaissée
            'capture_partial'    => __('Partially Charged', 'pc-stripe-caution'), // Partiellement encaissée
            'released'           => __('Released', 'pc-stripe-caution'),        // Libérée
            'rotated'            => __('Rotated (Extended)', 'pc-stripe-caution'), // Renouvelée
            'rotation_failed'    => __('Rotation Failed', 'pc-stripe-caution'),  // Échec rotation
        ];

        return isset($labels[$status]) ? $labels[$status] : $status;
    }

    // --- LOGIQUE PRINCIPALE ---
    public static function render_admin_logic(): string
    {
        self::check_access();
        self::prevent_cache();

        $message = '';
        $error = '';

        // --- TRAITEMENT FORMULAIRES ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['pcsc_action'])) {
            if (!check_admin_referer('pcsc_admin_action', 'pcsc_nonce')) {
                $error = __('Security expired.', 'pc-stripe-caution');
            } else {
                try {
                    $action = sanitize_text_field($_POST['pcsc_action']);
                    $case_id = isset($_POST['case_id']) ? (int)$_POST['case_id'] : 0;

                    if ($action === 'create_case') {
                        // >> VERIFICATION LIMITES (LITE VS PRO) <<
                        if (!PCSC_IS_PRO) {
                            $count = PCSC_DB::count_this_month();
                            if ($count >= 3) {
                                throw new Exception(__('Free limit reached (3/month). Upgrade to PRO for unlimited deposits.', 'pc-stripe-caution'));
                            }
                        }
                        // ----------------------------------------

                        $ref = sanitize_text_field($_POST['booking_ref']);
                        $email = sanitize_email($_POST['customer_email']);
                        $amount = self::eur_to_cents($_POST['amount_eur']);
                        $arr = sanitize_text_field($_POST['date_arrivee']);
                        $dep = sanitize_text_field($_POST['date_depart']);

                        if (!$ref || !$email || $amount <= 0) throw new Exception(__('Missing fields.', 'pc-stripe-caution'));

                        $new_id = PCSC_DB::insert_case([
                            'booking_ref' => $ref,
                            'customer_email' => $email,
                            'amount' => $amount,
                            'date_arrivee' => $arr ?: null,
                            'date_depart' => $dep ?: null,
                        ]);
                        self::safe_redirect(self::get_url(['case_id' => $new_id]));
                    }

                    // Actions sur dossier existant
                    if ($action === 'delete_case') {
                        PCSC_DB::delete_case($case_id);
                        self::safe_redirect(self::get_url(['msg' => 'done']));
                    }

                    $case = PCSC_DB::get_case($case_id);
                    if ($case) {
                        if ($action === 'send_setup_link_email') {
                            if (class_exists('PCSC_Mailer') && !empty($case['stripe_setup_url'])) {
                                PCSC_Mailer::send_setup_link($case['customer_email'], $case['booking_ref'], (int)$case['amount'], $case['stripe_setup_url']);
                                PCSC_DB::append_note($case_id, __('Link sent to customer.', 'pc-stripe-caution'));
                                self::safe_redirect(self::get_url(['case_id' => $case_id, 'msg' => 'email_sent']));
                            }
                        }

                        if ($action === 'create_setup_link') {
                            // Création Stripe Customer si besoin
                            $stripe_cust_id = $case['stripe_customer_id'];
                            if (empty($stripe_cust_id)) {
                                $c_res = PCSC_Stripe::create_customer($case['customer_email'], $case['booking_ref']);
                                if (!$c_res['ok']) throw new Exception($c_res['error']);
                                $stripe_cust_id = $c_res['id'];
                                PCSC_DB::update_case($case_id, ['stripe_customer_id' => $stripe_cust_id]);
                            }

                            // Création Session Setup
                            $fmt_amount = number_format($case['amount'] / 100, 2, ',', ' ');
                            $fmt_date = $case['date_depart'] ? date_i18n(get_option('date_format'), strtotime($case['date_depart'])) : '-';
                            $msg_client = sprintf(__('Deposit ref. %s (%s €). Releasable after %s.', 'pc-stripe-caution'), $case['booking_ref'], $fmt_amount, $fmt_date);

                            // Logique redirection PRO
                            $success_url = home_url('/caution-merci/?case_id=' . $case_id);

                            if (defined('PCSC_IS_PRO') && PCSC_IS_PRO) {
                                $page_id = (int) PCSC_Settings::get_option('success_page_id');
                                if ($page_id > 0) {
                                    // On ajoute le param case_id même sur la page custom
                                    $success_url = add_query_arg('case_id', $case_id, get_permalink($page_id));
                                }
                            }

                            $res = PCSC_Stripe::create_checkout_setup_session([
                                'success_url' => $success_url,
                                'cancel_url'  => home_url('/caution-annulee/?case_id=' . $case_id),
                                'customer_email' => $case['customer_email'],
                                'customer_id' => $stripe_cust_id,
                                'metadata' => ['pc_case_id' => $case_id, 'booking_ref' => $case['booking_ref']],
                                'checkout_message' => $msg_client
                            ]);

                            if (!$res['ok']) throw new Exception($res['error']);

                            PCSC_DB::update_case($case_id, [
                                'status' => 'setup_link_created',
                                'stripe_setup_session_id' => $res['session_id'],
                                'stripe_setup_url' => $res['url'],
                                'last_error' => null
                            ]);
                            PCSC_DB::append_note($case_id, __('Link generated.', 'pc-stripe-caution'));
                            self::safe_redirect(self::get_url(['case_id' => $case_id, 'msg' => 'done']));
                        }

                        if ($action === 'take_hold') {
                            $res = PCSC_Stripe::create_manual_hold_off_session([
                                'amount' => (int)$case['amount'],
                                'customer_id' => $case['stripe_customer_id'],
                                'payment_method_id' => $case['stripe_payment_method_id'],
                                'metadata' => ['pc_case_id' => $case_id, 'booking_ref' => $case['booking_ref']]
                            ]);
                            if (!$res['ok']) throw new Exception($res['error']);

                            PCSC_DB::update_case($case_id, [
                                'status' => 'authorized',
                                'stripe_payment_intent_id' => $res['payment_intent_id'],
                                'last_error' => null
                            ]);
                            PCSC_DB::append_note($case_id, __('Hold active.', 'pc-stripe-caution'));
                            PCSC_DB::schedule_release($case_id); // Fallback planif

                            if (class_exists('PCSC_Mailer')) {
                                $d_txt = $case['date_depart'] ? date_i18n(get_option('date_format'), strtotime($case['date_depart']) + (7 * DAY_IN_SECONDS)) : 'D+7';
                                PCSC_Mailer::send_hold_confirmation($case['customer_email'], $case['booking_ref'], (int)$case['amount'], $d_txt);
                            }
                            self::safe_redirect(self::get_url(['case_id' => $case_id, 'msg' => 'done']));
                        }

                        if ($action === 'capture') {
                            $amt = self::eur_to_cents($_POST['capture_amount_eur']);
                            $note = sanitize_textarea_field($_POST['capture_note']);
                            $res = PCSC_Stripe::capture_payment_intent($case['stripe_payment_intent_id'], $amt);
                            if (!$res['ok']) throw new Exception($res['error']);

                            $st = ($amt === (int)$case['amount']) ? 'captured' : 'capture_partial';
                            PCSC_DB::update_case($case_id, ['status' => $st]);
                            // Note interne : on traduit juste le préfixe, le reste est dynamique
                            PCSC_DB::append_note($case_id, __('Captured:', 'pc-stripe-caution') . ' ' . ($amt / 100) . "€ ($note)");

                            if (class_exists('PCSC_Mailer')) {
                                PCSC_Mailer::send_capture_confirmation($case['customer_email'], $case['booking_ref'], $amt, $note);
                            }
                            self::safe_redirect(self::get_url(['case_id' => $case_id, 'msg' => 'done']));
                        }

                        if ($action === 'release') {
                            $res = PCSC_Stripe::cancel_payment_intent($case['stripe_payment_intent_id']);
                            if (!$res['ok']) throw new Exception($res['error']);
                            PCSC_DB::update_case($case_id, ['status' => 'released']);
                            PCSC_DB::append_note($case_id, "Released manually.");
                            if (class_exists('PCSC_Mailer')) PCSC_Mailer::send_release_confirmation($case['customer_email'], $case['booking_ref']);
                            self::safe_redirect(self::get_url(['case_id' => $case_id, 'msg' => 'done']));
                        }

                        if ($action === 'rotate') {
                            $res = PCSC_DB::rotate_silent($case_id, "Manual");
                            if (!$res['ok']) throw new Exception($res['error']);
                            self::safe_redirect(self::get_url(['case_id' => $case_id, 'msg' => 'done']));
                        }
                    }
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            }
        }

        if (isset($_GET['msg']) && $_GET['msg'] === 'done') $message = __('Action successful.', 'pc-stripe-caution');
        if (isset($_GET['msg']) && $_GET['msg'] === 'email_sent') $message = __('Email sent.', 'pc-stripe-caution');

        // --- VUE ---
        ob_start();
        $case_id_view = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;
?>
        <style>
            #pc-admin-root {
                font-family: -apple-system, sans-serif;
                background: #f0f2f5;
                padding: 10px;
            }

            #pc-admin-root .pc-wrap {
                max-width: 900px;
                margin: 0 auto;
                background: #fff;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            }

            #pc-admin-root .pc-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                border-bottom: 2px solid #f3f4f6;
                padding-bottom: 15px;
                flex-wrap: wrap;
                /* Permet le retour à la ligne sur mobile */
                gap: 10px;
            }

            #pc-admin-root .pc-header h2 {
                font-size: 1.2rem;
                /* Titre un peu plus petit sur mobile */
            }

            /* --- GRILLE RESPONSIVE (Le cœur de ta demande) --- */
            #pc-admin-root .pc-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                /* Par défaut : 2 colonnes */
                gap: 20px;
                align-items: start;
            }

            /* Si l'écran fait moins de 768px (Tablettes & Mobiles) */
            @media (max-width: 768px) {
                #pc-admin-root .pc-grid {
                    grid-template-columns: 1fr !important;
                    /* FORCE 1 SEULE COLONNE */
                }

                #pc-admin-root .pc-header {
                    flex-direction: column;
                    /* Header vertical sur mobile */
                    align-items: flex-start;
                }

                #pc-admin-root .pc-btn {
                    width: 100%;
                    /* Boutons pleine largeur sur mobile */
                    margin-bottom: 5px;
                    text-align: center;
                }

                /* Ajustement tableau */
                #pc-admin-root table.pc-table th,
                #pc-admin-root table.pc-table td {
                    padding: 8px 5px;
                    font-size: 13px;
                }
            }

            /* ------------------------------------------------ */

            #pc-admin-root .pc-btn {
                display: inline-block;
                padding: 10px 16px;
                border-radius: 6px;
                font-weight: 600;
                text-decoration: none;
                border: none;
                cursor: pointer;
                font-size: 14px;
                text-align: center;
            }

            #pc-admin-root .pc-btn-primary {
                background: #2563eb;
                color: white;
            }

            #pc-admin-root .pc-btn-success {
                background: #059669;
                color: white;
            }

            #pc-admin-root .pc-btn-danger {
                background: #dc2626;
                color: white;
            }

            #pc-admin-root .pc-btn-outline {
                background: transparent;
                border: 1px solid #d1d5db;
                color: #374151;
            }

            #pc-admin-root .pc-alert {
                padding: 12px;
                border-radius: 6px;
                margin-bottom: 20px;
                font-weight: 500;
            }

            #pc-admin-root .pc-alert-success {
                background: #d1fae5;
                color: #065f46;
                border: 1px solid #a7f3d0;
            }

            #pc-admin-root .pc-alert-error {
                background: #fee2e2;
                color: #991b1b;
                border: 1px solid #fecaca;
            }

            #pc-admin-root .pc-input {
                width: 100%;
                padding: 12px;
                border: 1px solid #d1d5db;
                border-radius: 6px;
                font-size: 16px;
                box-sizing: border-box;
                margin-bottom: 10px;
            }

            #pc-admin-root .pc-label {
                display: block;
                font-weight: 600;
                margin-bottom: 5px;
                color: #374151;
            }

            #pc-admin-root .pc-card {
                background: #f9fafb;
                padding: 15px;
                border-radius: 6px;
                border: 1px solid #e5e7eb;
                margin-bottom: 15px;
            }

            #pc-admin-root table.pc-table {
                width: 100%;
                border-collapse: collapse;
                margin: 0;
            }

            #pc-admin-root table.pc-table th {
                text-align: left;
                padding: 12px 10px;
                border-bottom: 2px solid #e5e7eb;
                color: #6b7280;
                font-size: 12px;
                text-transform: uppercase;
            }

            #pc-admin-root table.pc-table td {
                padding: 12px 10px;
                border-bottom: 1px solid #f3f4f6;
            }

            #pc-admin-root .pc-badge {
                padding: 4px 8px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                display: inline-block;
            }

            .status-draft {
                background: #e5e7eb;
                color: #374151;
            }

            .status-setup_ok {
                background: #ffedd5;
                color: #9a3412;
                border: 1px solid #fed7aa;
            }

            .status-authorized {
                background: #dbeafe;
                color: #1e40af;
                border: 1px solid #bfdbfe;
            }

            .status-released {
                background: #f3f4f6;
                color: #9ca3af;
                text-decoration: line-through;
            }

            .status-captured {
                background: #d1fae5;
                color: #065f46;
            }

            .status-setup_link_created {
                background: #fff7ed;
                color: #9a3412;
            }

            /* BANNIERE UPSELL */
            .pc-upsell {
                background: linear-gradient(90deg, #4f46e5, #9333ea);
                color: white;
                padding: 15px;
                border-radius: 8px;
                margin-bottom: 20px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .pc-upsell-btn {
                background: white;
                color: #4f46e5;
                padding: 8px 16px;
                border-radius: 20px;
                text-decoration: none;
                font-weight: bold;
                font-size: 12px;
            }

            .pc-upsell-btn:hover {
                background: #f3f4f6;
            }
        </style>

        <div id="pc-admin-root">
            <div class="pc-wrap">
                <?php if ($message): ?><div class="pc-alert pc-alert-success"><?php echo esc_html($message); ?></div><?php endif; ?>
                <?php if ($error): ?><div class="pc-alert pc-alert-error"><?php echo esc_html($error); ?></div><?php endif; ?>

                <?php
                if ($case_id_view) self::render_case_detail($case_id_view);
                else self::render_dashboard_list();
                ?>
            </div>
        </div>
    <?php
        return ob_get_clean();
    }

    private static function render_dashboard_list(): void
    {
        global $wpdb;
        $table = PCSC_DB::table();
        $cases = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT 50", ARRAY_A);

        // Stats usage pour LITE
        $is_pro = PCSC_IS_PRO;
        $count_month = PCSC_DB::count_this_month();
        $limit = 3;
        $blocked = (!$is_pro && $count_month >= $limit);
    ?>

        <div class="pc-header">
            <h2 style="margin:0;"><?php _e('Deposit Management', 'pc-stripe-caution'); ?></h2>
            <?php if ($is_pro): ?>
                <span class="pc-badge" style="background:#dcfce7; color:#166534; border:1px solid #bbf7d0;">
                    <?php _e('PRO ACTIVATED', 'pc-stripe-caution'); ?>
                </span>
            <?php else: ?>
                <span class="pc-badge" style="background:#f3f4f6; color:#6b7280;">
                    <?php _e('LITE VERSION', 'pc-stripe-caution'); ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if (!$is_pro): ?>
            <div class="pc-upsell">
                <div>
                    <strong>⚡ <?php _e('Usage Limit:', 'pc-stripe-caution'); ?> <?php echo $count_month; ?> / <?php echo $limit; ?></strong><br>
                    <span style="font-size:12px; opacity:0.9;"><?php _e('Upgrade to PRO for unlimited deposits & auto-rotation.', 'pc-stripe-caution'); ?></span>
                </div>
                <a href="#" class="pc-upsell-btn"><?php _e('UPGRADE NOW', 'pc-stripe-caution'); ?></a>
            </div>
        <?php endif; ?>

        <div class="pc-card">
            <h3 style="margin-top:0;"><?php _e('New Case', 'pc-stripe-caution'); ?></h3>

            <?php if ($blocked): ?>
                <div class="pc-alert pc-alert-error" style="margin-bottom:0;">
                    <strong>⛔ <?php _e('Limit Reached', 'pc-stripe-caution'); ?></strong><br>
                    <?php _e('You have reached your monthly limit of 3 deposits. Please upgrade to create more.', 'pc-stripe-caution'); ?>
                </div>
            <?php else: ?>
                <form method="post" class="pc-grid">
                    <?php wp_nonce_field('pcsc_admin_action', 'pcsc_nonce'); ?>
                    <input type="hidden" name="pcsc_action" value="create_case">
                    <div><label class="pc-label"><?php _e('Booking Ref', 'pc-stripe-caution'); ?></label><input type="text" name="booking_ref" class="pc-input" required placeholder="ex: 2024-ABC"></div>
                    <div><label class="pc-label"><?php _e('Customer Email', 'pc-stripe-caution'); ?></label><input type="email" name="customer_email" class="pc-input" required placeholder="client@email.com"></div>
                    <div><label class="pc-label"><?php _e('Deposit Amount (€)', 'pc-stripe-caution'); ?></label><input type="text" inputmode="decimal" name="amount_eur" class="pc-input" required placeholder="ex: 500"></div>
                    <div>
                        <div class="pc-grid" style="gap:5px;">
                            <div><label class="pc-label"><?php _e('Arrival', 'pc-stripe-caution'); ?></label><input type="date" name="date_arrivee" class="pc-input"></div>
                            <div><label class="pc-label"><?php _e('Departure', 'pc-stripe-caution'); ?></label><input type="date" name="date_depart" class="pc-input"></div>
                        </div>
                    </div>
                    <div style="grid-column: 1 / -1;"><button type="submit" class="pc-btn pc-btn-primary" style="width:100%;"><?php _e('Create Case', 'pc-stripe-caution'); ?></button></div>
                </form>
            <?php endif; ?>
        </div>

        <div style="overflow-x:auto;">
            <table class="pc-table">
                <thead>
                    <tr>
                        <th><?php _e('Ref', 'pc-stripe-caution'); ?></th>
                        <th><?php _e('Customer', 'pc-stripe-caution'); ?></th>
                        <th><?php _e('Amount', 'pc-stripe-caution'); ?></th>
                        <th><?php _e('Status', 'pc-stripe-caution'); ?></th>
                        <th><?php _e('Departure', 'pc-stripe-caution'); ?></th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cases as $c): ?>
                        <tr>
                            <td><b><?php echo esc_html($c['booking_ref']); ?></b></td>
                            <td><?php echo esc_html($c['customer_email']); ?></td>
                            <td><?php echo number_format($c['amount'] / 100, 2, ',', ' '); ?> €</td>
                            <td><span class="pc-badge status-<?php echo $c['status']; ?>"><?php echo esc_html(self::get_status_label($c['status'])); ?></span></td>
                            <td><?php echo $c['date_depart'] ? date('d/m', strtotime($c['date_depart'])) : '-'; ?></td>
                            <td style="white-space: nowrap;">
                                <a href="<?php echo esc_url(self::get_url(['case_id' => $c['id']])); ?>" class="pc-btn pc-btn-outline" style="padding:6px 10px; font-size:12px;"><?php _e('Open', 'pc-stripe-caution'); ?></a>
                                <form method="post" style="display:inline-block; margin:0; margin-left:5px;" onsubmit="return confirm('<?php _e('Delete?', 'pc-stripe-caution'); ?>');">
                                    <?php wp_nonce_field('pcsc_admin_action', 'pcsc_nonce'); ?>
                                    <input type="hidden" name="pcsc_action" value="delete_case">
                                    <input type="hidden" name="case_id" value="<?php echo $c['id']; ?>">
                                    <button type="submit" style="background:none; border:none; cursor:pointer;" title="<?php _e('Delete', 'pc-stripe-caution'); ?>">❌</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php
    }

    private static function render_case_detail(int $id): void
    {
        $case = PCSC_DB::get_case($id);
        if (!$case) {
            _e('Case not found.', 'pc-stripe-caution');
            return;
        }
        $status = $case['status'];
    ?>
        <div class="pc-header">
            <div><a href="<?php echo esc_url(self::get_url()); ?>" style="text-decoration:none; color:#6b7280;"><?php _e('← Back', 'pc-stripe-caution'); ?></a>
                <h2 style="margin:5px 0 0;"><?php echo esc_html($case['booking_ref']); ?></h2>
            </div>
            <div><span class="pc-badge status-<?php echo $status; ?>"><?php echo esc_html(self::get_status_label($status)); ?></span></div>
        </div>

        <div class="pc-grid">
            <div>
                <div class="pc-card">
                    <h4><?php _e('Details', 'pc-stripe-caution'); ?></h4>
                    <p><strong><?php _e('Email:', 'pc-stripe-caution'); ?></strong> <?php echo esc_html($case['customer_email']); ?></p>
                    <p><strong><?php _e('Amount:', 'pc-stripe-caution'); ?></strong> <?php echo number_format($case['amount'] / 100, 2); ?> €</p>
                    <?php if ($case['last_error']): ?><div class="pc-alert pc-alert-error"><?php echo esc_html($case['last_error']); ?></div><?php endif; ?>
                </div>

                <div class="pc-card">
                    <h4><?php _e('Setup Link', 'pc-stripe-caution'); ?></h4>
                    <?php if ($case['stripe_setup_url']): ?>
                        <input type="text" readonly class="pc-input" value="<?php echo esc_attr($case['stripe_setup_url']); ?>">
                        <form method="post" style="margin-top:5px;">
                            <?php wp_nonce_field('pcsc_admin_action', 'pcsc_nonce'); ?>
                            <input type="hidden" name="pcsc_action" value="send_setup_link_email">
                            <input type="hidden" name="case_id" value="<?php echo $id; ?>">
                            <button type="submit" class="pc-btn" style="background:#183C3C; color:white; width:100%;"><?php _e('Send Email', 'pc-stripe-caution'); ?></button>
                        </form>
                    <?php else: ?>
                        <form method="post">
                            <?php wp_nonce_field('pcsc_admin_action', 'pcsc_nonce'); ?>
                            <input type="hidden" name="pcsc_action" value="create_setup_link">
                            <input type="hidden" name="case_id" value="<?php echo $id; ?>">
                            <button type="submit" class="pc-btn pc-btn-primary" style="width:100%;"><?php _e('Generate Link', 'pc-stripe-caution'); ?></button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="pc-card">
                    <h4><?php _e('Notes', 'pc-stripe-caution'); ?></h4>
                    <pre style="background:#fff; padding:10px; height:100px; overflow-y:auto;"><?php echo esc_html($case['internal_notes']); ?></pre>
                </div>
            </div>

            <div>
                <div class="pc-card" style="border-left: 4px solid #2563eb;">
                    <h3 style="margin-top:0; color:#1e40af;"><?php _e('1. Block Funds (Hold)', 'pc-stripe-caution'); ?></h3>
                    <?php if (in_array($status, ['setup_ok', 'setup_link_created', 'released'])): ?>
                        <form method="post">
                            <?php wp_nonce_field('pcsc_admin_action', 'pcsc_nonce'); ?>
                            <input type="hidden" name="pcsc_action" value="take_hold">
                            <input type="hidden" name="case_id" value="<?php echo $id; ?>">
                            <button type="submit" class="pc-btn pc-btn-primary" style="width:100%;"><?php _e('Take Deposit', 'pc-stripe-caution'); ?></button>
                        </form>
                    <?php elseif ($status === 'draft'): ?><p><?php _e('Waiting for card...', 'pc-stripe-caution'); ?></p><?php else: ?><p style="color:green;"><?php _e('Active.', 'pc-stripe-caution'); ?></p><?php endif; ?>
                </div>

                <?php if (in_array($status, ['authorized', 'rotated', 'rotation_failed'])): ?>
                    <div class="pc-card">
                        <h3><?php _e('Rotation', 'pc-stripe-caution'); ?></h3>
                        <form method="post">
                            <?php wp_nonce_field('pcsc_admin_action', 'pcsc_nonce'); ?>
                            <input type="hidden" name="pcsc_action" value="rotate">
                            <input type="hidden" name="case_id" value="<?php echo $id; ?>">
                            <button type="submit" class="pc-btn pc-btn-outline" style="width:100%;"><?php _e('Force Rotation', 'pc-stripe-caution'); ?></button>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if (in_array($status, ['authorized', 'rotated', 'capture_partial'])): ?>
                    <div class="pc-card" style="border-left: 4px solid #dc2626;">
                        <h3 style="color:#991b1b;"><?php _e('2. Charge', 'pc-stripe-caution'); ?></h3>
                        <form method="post">
                            <?php wp_nonce_field('pcsc_admin_action', 'pcsc_nonce'); ?>
                            <input type="hidden" name="pcsc_action" value="capture">
                            <input type="hidden" name="case_id" value="<?php echo $id; ?>">
                            <input type="text" name="capture_amount_eur" class="pc-input" placeholder="<?php _e('Amount €', 'pc-stripe-caution'); ?>" required>
                            <input type="text" name="capture_note" class="pc-input" placeholder="<?php _e('Reason', 'pc-stripe-caution'); ?>">
                            <button type="submit" class="pc-btn pc-btn-danger" style="width:100%; margin-top:5px;" onclick="return confirm('<?php _e('Confirm Charge?', 'pc-stripe-caution'); ?>');"><?php _e('Confirm Charge', 'pc-stripe-caution'); ?></button>
                        </form>
                    </div>

                    <div class="pc-card" style="border-left: 4px solid #059669;">
                        <h3 style="color:#065f46;"><?php _e('3. Release', 'pc-stripe-caution'); ?></h3>
                        <form method="post">
                            <?php wp_nonce_field('pcsc_admin_action', 'pcsc_nonce'); ?>
                            <input type="hidden" name="pcsc_action" value="release">
                            <input type="hidden" name="case_id" value="<?php echo $id; ?>">
                            <button type="submit" class="pc-btn pc-btn-success" style="width:100%;"><?php _e('Release Now', 'pc-stripe-caution'); ?></button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
<?php
    }
}
