<?php
if (!defined('ABSPATH')) exit;

class PCSC_Shortcodes
{
    public static function init(): void
    {
        add_shortcode('pc_caution_admin', [__CLASS__, 'render_admin']);
        add_shortcode('pc_caution_merci', [__CLASS__, 'render_thankyou']);
        add_action('admin_menu', [__CLASS__, 'register_menu']);
    }

    private static function check_access(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Access denied. You must be an administrator.', 'pc-stripe-caution'));
        }
    }

    private static function prevent_cache(): void
    {
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        nocache_headers();
    }

    private static function get_url(array $params = []): string
    {
        if (is_admin() && isset($_GET['page']) && $_GET['page'] === 'pc-stripe-caution') {
            $base = admin_url('admin.php?page=pc-stripe-caution');
        } else {
            global $wp;
            $base = home_url($wp->request);
        }
        return add_query_arg($params, $base);
    }

    private static function safe_redirect(string $url): void
    {
        if (!headers_sent()) {
            wp_safe_redirect($url);
            exit;
        } else {
            echo "<script>window.location.href = '" . esc_url_raw($url) . "';</script>";
            echo "<div style='padding:20px; text-align:center;'>" . esc_html__('Redirecting...', 'pc-stripe-caution') . " <a href='" . esc_url($url) . "'>" . esc_html__('Click here', 'pc-stripe-caution') . "</a></div>";
            exit;
        }
    }

    private static function eur_to_cents($input): int
    {
        $clean = str_replace([',', ' '], ['.', ''], (string)$input);
        return (int) round((float)$clean * 100);
    }

    public static function render_admin($atts): string
    {
        self::check_access();
        self::prevent_cache();

        $message = '';
        $error = '';

        if (isset($_GET['msg'])) {
            if ($_GET['msg'] === 'done') $message = __('Action successful.', 'pc-stripe-caution');
            if ($_GET['msg'] === 'email_sent') $message = __('Email sent successfully to the customer.', 'pc-stripe-caution');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['pcsc_action'])) {
            if (!check_admin_referer('pcsc_admin_action', 'pcsc_nonce')) {
                $error = __('Security: Session expired. Please reload the page.', 'pc-stripe-caution');
            } else {
                try {
                    $action = sanitize_text_field($_POST['pcsc_action']);
                    $case_id = isset($_POST['case_id']) ? (int)$_POST['case_id'] : 0;

                    if ($action === 'delete_case') {
                        PCSC_DB::delete_case($case_id);
                        self::safe_redirect(self::get_url(['msg' => 'done']));
                    }

                    if ($action === 'create_case') {
                        $ref = sanitize_text_field($_POST['booking_ref']);
                        $email = sanitize_email($_POST['customer_email']);
                        $amount = self::eur_to_cents($_POST['amount_eur']);
                        $arr = sanitize_text_field($_POST['date_arrivee']);
                        $dep = sanitize_text_field($_POST['date_depart']);

                        if (!$ref || !$email || $amount <= 0) throw new Exception(__('Missing required fields.', 'pc-stripe-caution'));

                        $new_id = PCSC_DB::insert_case([
                            'booking_ref' => $ref,
                            'customer_email' => $email,
                            'amount' => $amount,
                            'date_arrivee' => $arr ?: null,
                            'date_depart' => $dep ?: null,
                        ]);
                        self::safe_redirect(self::get_url(['case_id' => $new_id]));
                    }

                    $case = PCSC_DB::get_case($case_id);
                    if (!$case) throw new Exception(__('Case not found.', 'pc-stripe-caution'));

                    if ($action === 'send_setup_link_email') {
                        if (empty($case['stripe_setup_url'])) throw new Exception(__('No generated link to send.', 'pc-stripe-caution'));

                        if (class_exists('PCSC_Mailer')) {
                            PCSC_Mailer::send_setup_link(
                                $case['customer_email'],
                                $case['booking_ref'],
                                (int)$case['amount'],
                                $case['stripe_setup_url']
                            );
                            PCSC_DB::append_note($case_id, "Setup link sent by email to " . $case['customer_email']);
                            self::safe_redirect(self::get_url(['case_id' => $case_id, 'msg' => 'email_sent']));
                        } else {
                            throw new Exception(__('Mailer module not active.', 'pc-stripe-caution'));
                        }
                    }

                    if ($action === 'create_setup_link') {
                        $stripe_cust_id = $case['stripe_customer_id'];
                        if (empty($stripe_cust_id)) {
                            $cust_res = PCSC_Stripe::create_customer($case['customer_email'], $case['booking_ref']);
                            if (!$cust_res['ok']) throw new Exception(__('Stripe customer creation error: ', 'pc-stripe-caution') . $cust_res['error']);
                            $stripe_cust_id = $cust_res['id'];
                            PCSC_DB::update_case($case_id, ['stripe_customer_id' => $stripe_cust_id]);
                        }

                        $fmt_amount = number_format($case['amount'] / 100, 2, ',', ' ');
                        $fmt_date = $case['date_depart'] ? date_i18n(get_option('date_format'), strtotime($case['date_depart'])) : __('undefined', 'pc-stripe-caution');

                        // Message affiché sur Stripe (Checkout)
                        $message_client = sprintf(__('Deposit ref. %s of %s %s. Releasable after %s.', 'pc-stripe-caution'), $case['booking_ref'], $fmt_amount, 'EUR', $fmt_date);

                        $res = PCSC_Stripe::create_checkout_setup_session([
                            'success_url' => home_url('/caution-merci/?case_id=' . $case_id), // Idéalement à changer par une option plus tard
                            'cancel_url'  => home_url('/caution-annulee/?case_id=' . $case_id),
                            'customer_email' => $case['customer_email'],
                            'customer_id' => $stripe_cust_id,
                            'metadata' => ['pc_case_id' => $case_id, 'booking_ref' => $case['booking_ref']],
                            'checkout_message' => $message_client
                        ]);

                        if (!$res['ok']) throw new Exception($res['error']);

                        PCSC_DB::update_case($case_id, [
                            'status' => 'setup_link_created',
                            'stripe_setup_session_id' => $res['session_id'],
                            'stripe_setup_url' => $res['url'],
                            'last_error' => null
                        ]);
                        PCSC_DB::append_note($case_id, "Link generated for customer ($stripe_cust_id).");
                        self::safe_redirect(self::get_url(['case_id' => $case_id, 'msg' => 'done']));
                    }

                    if ($action === 'take_hold') {
                        if (empty($case['stripe_customer_id']) || empty($case['stripe_payment_method_id'])) throw new Exception(__('Card not saved.', 'pc-stripe-caution'));

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
                            'currency' => 'eur',
                            'last_error' => null
                        ]);
                        PCSC_DB::append_note($case_id, 'Deposit taken (Hold active).');
                        PCSC_DB::schedule_release($case_id);

                        if (class_exists('PCSC_Mailer')) {
                            $d_txt = $case['date_depart'] ? date_i18n(get_option('date_format'), strtotime($case['date_depart']) + (7 * DAY_IN_SECONDS)) : __('D+7 after departure', 'pc-stripe-caution');
                            PCSC_Mailer::send_hold_confirmation($case['customer_email'], $case['booking_ref'], (int)$case['amount'], $d_txt);
                            PCSC_DB::append_note($case_id, 'Confirmation email sent to customer.');
                        }
                        self::safe_redirect(self::get_url(['case_id' => $case_id, 'msg' => 'done']));
                    }

                    if ($action === 'capture') {
                        $cap_amount = self::eur_to_cents($_POST['capture_amount_eur']);
                        $note = sanitize_textarea_field($_POST['capture_note']);
                        $pi_id = $case['stripe_payment_intent_id'];
                        if (empty($pi_id) || $cap_amount <= 0 || $cap_amount > (int)$case['amount']) throw new Exception(__('Capture error.', 'pc-stripe-caution'));

                        $res = PCSC_Stripe::capture_payment_intent($pi_id, $cap_amount);
                        if (!$res['ok']) throw new Exception($res['error']);

                        $status = ($cap_amount === (int)$case['amount']) ? 'captured' : 'capture_partial';
                        PCSC_DB::update_case($case_id, ['status' => $status, 'last_error' => null]);
                        PCSC_DB::append_note($case_id, "Capture success: " . ($cap_amount / 100) . "€. Note: $note");
                        if (class_exists('PCSC_Mailer')) {
                            PCSC_Mailer::send_capture_confirmation(
                                $case['customer_email'],
                                $case['booking_ref'],
                                $cap_amount,
                                $note
                            );
                            PCSC_DB::append_note($case_id, "Charge email sent to customer.");
                        }
                        self::safe_redirect(self::get_url(['case_id' => $case_id, 'msg' => 'done']));
                    }

                    if ($action === 'release') {
                        $res = PCSC_Stripe::cancel_payment_intent($case['stripe_payment_intent_id']);
                        if (!$res['ok']) throw new Exception($res['error']);
                        PCSC_DB::update_case($case_id, ['status' => 'released', 'last_error' => null]);
                        PCSC_DB::append_note($case_id, "Deposit released manually.");

                        if (class_exists('PCSC_Mailer')) {
                            PCSC_Mailer::send_release_confirmation($case['customer_email'], $case['booking_ref']);
                            PCSC_DB::append_note($case_id, 'Release email sent to customer.');
                        }
                        self::safe_redirect(self::get_url(['case_id' => $case_id, 'msg' => 'done']));
                    }

                    if ($action === 'rotate') {
                        $res = PCSC_DB::rotate_silent($case_id, "Manual rotation.");
                        if (!$res['ok']) throw new Exception($res['error']);
                        self::safe_redirect(self::get_url(['case_id' => $case_id, 'msg' => 'done']));
                    }
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            }
        }

        ob_start();
        $case_id_view = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;
?>
        <style>
            #pc-admin-root {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
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
            }

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

            #pc-admin-root .pc-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
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
                vertical-align: middle;
                background: transparent;
            }

            #pc-admin-root .pc-badge {
                padding: 4px 8px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                display: inline-block;
            }

            #pc-admin-root .status-draft {
                background: #e5e7eb;
                color: #374151;
            }

            #pc-admin-root .status-setup_ok {
                background: #ffedd5;
                color: #9a3412;
                border: 1px solid #fed7aa;
            }

            #pc-admin-root .status-authorized {
                background: #dbeafe;
                color: #1e40af;
                border: 1px solid #bfdbfe;
            }

            #pc-admin-root .status-released {
                background: #f3f4f6;
                color: #9ca3af;
                text-decoration: line-through;
            }

            #pc-admin-root .status-captured {
                background: #d1fae5;
                color: #065f46;
            }

            #pc-admin-root .status-setup_link_created {
                background: #fff7ed;
                color: #9a3412;
            }

            @media (max-width: 600px) {
                #pc-admin-root .pc-grid {
                    grid-template-columns: 1fr;
                }

                #pc-admin-root .pc-wrap {
                    padding: 15px;
                }

                #pc-admin-root table.pc-table,
                #pc-admin-root table.pc-table tbody,
                #pc-admin-root table.pc-table tr,
                #pc-admin-root table.pc-table td {
                    display: block;
                    width: 100%;
                    box-sizing: border-box;
                }

                #pc-admin-root table.pc-table thead {
                    display: none;
                }

                #pc-admin-root table.pc-table tr {
                    margin-bottom: 15px;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    background: #fff;
                    padding: 15px;
                    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
                }

                #pc-admin-root table.pc-table td {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    text-align: right;
                    padding: 8px 0;
                    border-bottom: 1px solid #f9fafb;
                    font-size: 14px;
                }

                #pc-admin-root table.pc-table td:last-child {
                    border-bottom: none;
                    display: block;
                    text-align: center;
                    margin-top: 10px;
                }

                #pc-admin-root table.pc-table td::before {
                    content: attr(data-label);
                    font-weight: 600;
                    color: #6b7280;
                    text-transform: uppercase;
                    font-size: 11px;
                    margin-right: 10px;
                }

                #pc-admin-root table.pc-table td[data-label="Actions"]::before {
                    display: none;
                }

                #pc-admin-root .pc-btn {
                    width: 100%;
                    margin-bottom: 5px;
                }
            }
        </style>

        <div id="pc-admin-root">
            <div class="pc-wrap">
                <?php if ($message): ?><div class="pc-alert pc-alert-success"><?php echo esc_html($message); ?></div><?php endif; ?>
                <?php if ($error): ?><div class="pc-alert pc-alert-error"><?php echo esc_html($error); ?></div><?php endif; ?>

                <?php if ($case_id_view): self::render_case_detail($case_id_view);
                else: self::render_dashboard();
                endif; ?>
            </div>
        </div>
    <?php
        return ob_get_clean();
    }

    private static function render_dashboard(): void
    {
        global $wpdb;
        $table = PCSC_DB::table();
        $cases = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT 50", ARRAY_A);
    ?>
        <div class="pc-header">
            <h2 style="margin:0;"><?php _e('Deposit Management', 'pc-stripe-caution'); ?></h2>
        </div>

        <div class="pc-card">
            <h3 style="margin-top:0;"><?php _e('New Case', 'pc-stripe-caution'); ?></h3>
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
                        <th><?php _e('Action', 'pc-stripe-caution'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cases as $c):
                        $amt = number_format($c['amount'] / 100, 2, ',', ' ');
                        $cls = 'status-' . $c['status'];
                    ?>
                        <tr>
                            <td data-label="<?php _e('Ref', 'pc-stripe-caution'); ?>"><b><?php echo esc_html($c['booking_ref']); ?></b></td>
                            <td data-label="<?php _e('Customer', 'pc-stripe-caution'); ?>"><?php echo esc_html($c['customer_email']); ?></td>
                            <td data-label="<?php _e('Amount', 'pc-stripe-caution'); ?>"><?php echo $amt; ?> €</td>
                            <td data-label="<?php _e('Status', 'pc-stripe-caution'); ?>"><span class="pc-badge <?php echo $cls; ?>"><?php echo esc_html($c['status']); ?></span></td>
                            <td data-label="<?php _e('Departure', 'pc-stripe-caution'); ?>"><?php echo $c['date_depart'] ? date('d/m', strtotime($c['date_depart'])) : '-'; ?></td>
                            <td data-label="<?php _e('Actions', 'pc-stripe-caution'); ?>" style="white-space: nowrap;">
                                <a href="<?php echo esc_url(self::get_url(['case_id' => $c['id']])); ?>" class="pc-btn pc-btn-outline" style="padding:6px 10px; font-size:12px;"><?php _e('Open', 'pc-stripe-caution'); ?></a>
                                <form method="post" style="display:inline-block; margin:0; margin-left:5px;" onsubmit="return confirm('<?php _e('Permanently delete this case?', 'pc-stripe-caution'); ?>');">
                                    <?php wp_nonce_field('pcsc_admin_action', 'pcsc_nonce'); ?>
                                    <input type="hidden" name="pcsc_action" value="delete_case">
                                    <input type="hidden" name="case_id" value="<?php echo $c['id']; ?>">
                                    <button type="submit" style="background:none; border:none; cursor:pointer; font-size:14px; padding:0;" title="<?php _e('Delete', 'pc-stripe-caution'); ?>">❌</button>
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
            echo '<p>' . __('Case not found.', 'pc-stripe-caution') . '</p> <a href="' . esc_url(self::get_url()) . '" class="pc-btn pc-btn-outline">' . __('Back', 'pc-stripe-caution') . '</a>';
            return;
        }
        $status = $case['status'];
    ?>
        <div class="pc-header">
            <div><a href="<?php echo esc_url(self::get_url()); ?>" style="text-decoration:none; color:#6b7280; font-size:14px;">← <?php _e('Back to list', 'pc-stripe-caution'); ?></a>
                <h2 style="margin:5px 0 0;"><?php echo esc_html($case['booking_ref']); ?></h2>
            </div>
            <div><span class="pc-badge status-<?php echo $status; ?>" style="font-size:14px; padding:6px 12px;"><?php echo esc_html($status); ?></span></div>
        </div>

        <div class="pc-grid">
            <div>
                <div class="pc-card">
                    <h4 style="margin-top:0;"><?php _e('Details', 'pc-stripe-caution'); ?></h4>
                    <p><strong><?php _e('Email:', 'pc-stripe-caution'); ?></strong> <?php echo esc_html($case['customer_email']); ?></p>
                    <p><strong><?php _e('Deposit:', 'pc-stripe-caution'); ?></strong> <?php echo number_format($case['amount'] / 100, 2, ',', ' '); ?> €</p>
                    <p><strong><?php _e('Departure:', 'pc-stripe-caution'); ?></strong> <?php echo esc_html($case['date_depart']); ?></p>
                    <?php if ($case['last_error']): ?><div class="pc-alert pc-alert-error" style="font-size:13px; margin-top:10px;"><strong><?php _e('Last Error:', 'pc-stripe-caution'); ?></strong><br><?php echo esc_html($case['last_error']); ?></div><?php endif; ?>
                </div>

                <div class="pc-card">
                    <h4 style="margin-top:0;"><?php _e('Customer Link (Setup)', 'pc-stripe-caution'); ?></h4>
                    <?php if ($case['stripe_setup_url']): ?>
                        <input type="text" readonly class="pc-input" value="<?php echo esc_attr($case['stripe_setup_url']); ?>" onclick="this.select()">
                        <form method="post" style="margin-top:10px;">
                            <?php wp_nonce_field('pcsc_admin_action', 'pcsc_nonce'); ?>
                            <input type="hidden" name="pcsc_action" value="send_setup_link_email">
                            <input type="hidden" name="case_id" value="<?php echo $id; ?>">
                            <button type="submit" class="pc-btn" style="background-color: #183C3C; color: white; width: 100%;"><?php _e('✉️ Send link by email', 'pc-stripe-caution'); ?></button>
                        </form>
                        <div style="margin-top:10px; border-top:1px solid #eee; padding-top:10px;">
                            <form method="post">
                                <?php wp_nonce_field('pcsc_admin_action', 'pcsc_nonce'); ?>
                                <input type="hidden" name="pcsc_action" value="create_setup_link">
                                <input type="hidden" name="case_id" value="<?php echo $id; ?>">
                                <button type="submit" class="pc-btn pc-btn-outline" style="font-size:12px; width:100%;" onclick="return confirm('<?php _e('Regenerate link?', 'pc-stripe-caution'); ?>');"><?php _e('↻ Regenerate', 'pc-stripe-caution'); ?></button>
                            </form>
                        </div>
                    <?php else: ?>
                        <form method="post">
                            <?php wp_nonce_field('pcsc_admin_action', 'pcsc_nonce'); ?>
                            <input type="hidden" name="pcsc_action" value="create_setup_link">
                            <input type="hidden" name="case_id" value="<?php echo $id; ?>">
                            <button type="submit" class="pc-btn pc-btn-primary" style="width:100%;"><?php _e('Generate Setup Link', 'pc-stripe-caution'); ?></button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="pc-card">
                    <h4 style="margin-top:0;"><?php _e('Internal Notes', 'pc-stripe-caution'); ?></h4>
                    <div style="background:#fff; border:1px solid #eee; padding:10px; height:150px; overflow-y:auto; font-size:13px; white-space:pre-wrap;"><?php echo esc_html($case['internal_notes']); ?></div>
                </div>
            </div>

            <div>
                <div class="pc-card" style="border-left: 4px solid #2563eb;">
                    <h3 style="margin-top:0; color:#1e40af;"><?php _e('1. Block Funds (Hold)', 'pc-stripe-caution'); ?></h3>
                    <p style="font-size:14px; color:#6b7280;"><?php _e('Performs an off-session authorization request.', 'pc-stripe-caution'); ?></p>
                    <?php if ($status === 'setup_ok'): ?><div class="pc-alert pc-alert-success" style="margin-bottom:10px; font-size:13px;"><?php _e('Card saved! Ready to take deposit.', 'pc-stripe-caution'); ?></div><?php endif; ?>
                    <?php if (in_array($status, ['setup_ok', 'setup_link_created', 'released'])): ?>
                        <form method="post">
                            <?php wp_nonce_field('pcsc_admin_action', 'pcsc_nonce'); ?>
                            <input type="hidden" name="pcsc_action" value="take_hold">
                            <input type="hidden" name="case_id" value="<?php echo $id; ?>">
                            <button type="submit" class="pc-btn pc-btn-primary" style="width:100%;"><?php _e('Take Deposit', 'pc-stripe-caution'); ?></button>
                        </form>
                    <?php elseif ($status === 'draft'): ?><p style="color:#b45309;"><?php _e('Waiting for generated link or saved card.', 'pc-stripe-caution'); ?></p><?php else: ?><p style="color:#059669;"><?php _e('Deposit already active.', 'pc-stripe-caution'); ?></p><?php endif; ?>
                </div>

                <?php if (in_array($status, ['authorized', 'rotated', 'rotation_failed'])): ?>
                    <div class="pc-card">
                        <h3 style="margin-top:0;"><?php _e('Rotation (Renewal)', 'pc-stripe-caution'); ?></h3>
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
                        <h3 style="margin-top:0; color:#991b1b;"><?php _e('2. Charge (Capture)', 'pc-stripe-caution'); ?></h3>
                        <form method="post">
                            <?php wp_nonce_field('pcsc_admin_action', 'pcsc_nonce'); ?>
                            <input type="hidden" name="pcsc_action" value="capture">
                            <input type="hidden" name="case_id" value="<?php echo $id; ?>">
                            <label class="pc-label"><?php _e('Amount to charge (€)', 'pc-stripe-caution'); ?></label>
                            <input type="text" inputmode="decimal" name="capture_amount_eur" class="pc-input" placeholder="ex: 250,00" required>
                            <label class="pc-label"><?php _e('Reason (Note)', 'pc-stripe-caution'); ?></label>
                            <input type="text" name="capture_note" class="pc-input" placeholder="<?php _e('ex: Extra cleaning', 'pc-stripe-caution'); ?>">
                            <button type="submit" class="pc-btn pc-btn-danger" style="width:100%; margin-top:10px;" onclick="return confirm('<?php _e('Are you sure you want to CHARGE the card?', 'pc-stripe-caution'); ?>');"><?php _e('CONFIRM CHARGE', 'pc-stripe-caution'); ?></button>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if (in_array($status, ['authorized', 'rotated', 'capture_partial'])): ?>
                    <div class="pc-card" style="border-left: 4px solid #059669;">
                        <h3 style="margin-top:0; color:#065f46;"><?php _e('3. Release Deposit', 'pc-stripe-caution'); ?></h3>
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

    public static function render_thankyou($atts): string
    {
        ob_start();
    ?>
        <style>
            #pc-thx-root .pc-thx-wrap {
                max-width: 600px;
                margin: 40px auto;
                padding: 40px;
                background: #ffffff !important;
                border-radius: 12px;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
                text-align: center;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                border: 1px solid #f0f0f0;
            }

            #pc-thx-root .pc-thx-icon {
                font-size: 50px;
                color: #10b981;
                margin-bottom: 20px;
                display: inline-block;
                animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            }

            #pc-thx-root .pc-thx-title {
                font-size: 24px;
                color: #111827;
                margin: 0 0 15px 0;
                font-weight: 700;
            }

            #pc-thx-root .pc-thx-text {
                font-size: 16px;
                color: #4b5563;
                line-height: 1.6;
                margin-bottom: 25px;
            }

            #pc-thx-root .pc-thx-note {
                font-size: 13px;
                color: #6b7280;
                background: #f9fafb;
                padding: 15px;
                border-radius: 8px;
                margin-bottom: 30px;
                border: 1px solid #e5e7eb;
            }

            #pc-thx-root .pc-thx-btn {
                display: inline-block;
                background: #2563eb !important;
                color: #ffffff !important;
                text-decoration: none;
                padding: 12px 25px;
                border-radius: 30px;
                font-weight: 600;
                transition: background 0.2s;
                box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2);
            }

            #pc-thx-root .pc-thx-btn:hover {
                background: #1d4ed8 !important;
                color: #ffffff !important;
                transform: translateY(-1px);
            }

            @keyframes popIn {
                from {
                    transform: scale(0);
                    opacity: 0;
                }

                to {
                    transform: scale(1);
                    opacity: 1;
                }
            }
        </style>

        <div id="pc-thx-root">
            <div class="pc-thx-wrap">
                <div class="pc-thx-icon">✅</div>
                <h1 class="pc-thx-title"><?php _e('Card saved successfully', 'pc-stripe-caution'); ?></h1>
                <p class="pc-thx-text">
                    <?php _e('Thank you! Your banking details have been securely saved for the deposit.', 'pc-stripe-caution'); ?><br>
                    <?php _e('You will receive a <strong>confirmation email</strong> as soon as the amount is officially blocked (Hold) before your arrival.', 'pc-stripe-caution'); ?>
                </p>
                <div class="pc-thx-note">
                    🔒 <strong><?php _e('Security & Privacy', 'pc-stripe-caution'); ?></strong><br>
                    <?php _e('Your information is encrypted by Stripe. No amount is debited immediately. The bank imprint will be automatically deleted after the deposit is released.', 'pc-stripe-caution'); ?>
                </div>
                <a href="<?php echo home_url('/'); ?>" class="pc-thx-btn"><?php _e('✨ Return to Home', 'pc-stripe-caution'); ?></a>
            </div>
        </div>
<?php
        return ob_get_clean();
    }

    public static function register_menu(): void
    {
        add_menu_page(
            __('Deposit Management', 'pc-stripe-caution'),
            __('Stripe Deposits', 'pc-stripe-caution'),
            'manage_options',
            'pc-stripe-caution',
            [__CLASS__, 'render_menu_page'],
            'dashicons-shield',
            56
        );
    }

    public static function render_menu_page(): void
    {
        echo self::render_admin([]);
    }
}
