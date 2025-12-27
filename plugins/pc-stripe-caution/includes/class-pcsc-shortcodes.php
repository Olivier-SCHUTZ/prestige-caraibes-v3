<?php
if (!defined('ABSPATH')) exit;

class PCSC_Shortcodes
{

    public static function init(): void
    {
        add_shortcode('pc_caution_admin', [__CLASS__, 'shortcode_admin']);
    }

    private static function must_admin(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Accès refusé.');
        }
    }

    private static function admin_url_self(): string
    {
        return esc_url_raw(add_query_arg([]));
    }

    public static function shortcode_admin($atts): string
    {
        self::must_admin();
        // Empêche cache (sinon nonce peut expirer et les POST deviennent silencieux)
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        if (function_exists('nocache_headers')) nocache_headers();

        $msg = '';
        $err = '';

        // Handle POST
        if (!empty($_POST['pcsc_action'])) {
            if (!check_admin_referer('pcsc_admin_action', 'pcsc_nonce')) {
                $err = 'Nonce invalide/expiré. Page probablement cachée. Recharge la page (CTRL+F5) puis réessaie.';
            } else {
                $action = sanitize_text_field(wp_unslash($_POST['pcsc_action']));
                $case_id = isset($_POST['case_id']) ? (int)$_POST['case_id'] : 0;

                try {
                    if ($action === 'create_case') {
                        $booking_ref = sanitize_text_field(wp_unslash($_POST['booking_ref'] ?? ''));
                        $email = sanitize_email(wp_unslash($_POST['customer_email'] ?? ''));
                        $amount_eur = (float) str_replace(',', '.', (string) wp_unslash($_POST['amount_eur'] ?? '0'));
                        $amount = (int) round($amount_eur * 100);
                        $arr = sanitize_text_field(wp_unslash($_POST['date_arrivee'] ?? ''));
                        $dep = sanitize_text_field(wp_unslash($_POST['date_depart'] ?? ''));

                        if (!$booking_ref || !$email || $amount <= 0) {
                            throw new Exception('Champs requis: booking_ref, email, montant.');
                        }

                        $id = PCSC_DB::insert_case([
                            'booking_ref' => $booking_ref,
                            'customer_email' => $email,
                            'amount' => $amount,
                            'date_arrivee' => $arr ?: null,
                            'date_depart' => $dep ?: null,
                            'status' => 'draft',
                        ]);

                        // Redirige pour charger automatiquement le dossier et afficher les boutons
                        $url = add_query_arg(['case_id' => $id], self::admin_url_self());
                        wp_safe_redirect($url);
                        exit;
                    }

                    if ($action === 'create_setup_link') {
                        $case = PCSC_DB::get_case($case_id);
                        if (!$case) throw new Exception('Dossier introuvable.');

                        $success_url = home_url('/caution-merci/?case_id=' . $case_id);
                        $cancel_url  = home_url('/caution-annulee/?case_id=' . $case_id);

                        $res = PCSC_Stripe::create_checkout_setup_session([
                            'success_url' => $success_url,
                            'cancel_url'  => $cancel_url,
                            'customer_email' => (string)$case['customer_email'],
                            'metadata' => [
                                'pc_case_id' => (string)$case_id,
                                'booking_ref' => (string)$case['booking_ref'],
                                'purpose' => 'caution_setup',
                            ],
                        ]);

                        if (!$res['ok']) throw new Exception($res['error']);

                        PCSC_DB::update_case($case_id, [
                            'status' => 'setup_link_created',
                            'stripe_setup_session_id' => $res['session_id'],
                            'stripe_setup_url' => $res['url'],
                            'last_error' => null,
                        ]);

                        PCSC_DB::append_note($case_id, 'Lien setup Checkout généré.');
                        $msg = 'Lien setup généré.';
                    }

                    if ($action === 'take_hold_now') {
                        if (!$case) throw new Exception('Dossier introuvable.');

                        $currency = 'eur';

                        PCSC_DB::update_case($case_id, ['currency' => $currency]);
                        if (empty($case['stripe_customer_id']) || empty($case['stripe_payment_method_id'])) {
                            throw new Exception('Setup non terminé: customer/payment_method manquants.');
                        }

                        $res = PCSC_Stripe::create_manual_hold_off_session([
                            'amount' => (int)$case['amount'],
                            'currency' => $currency,
                            'customer_id' => (string)$case['stripe_customer_id'],
                            'payment_method_id' => (string)$case['stripe_payment_method_id'],
                            'metadata' => [
                                'pc_case_id' => (string)$case_id,
                                'booking_ref' => (string)$case['booking_ref'],
                                'purpose' => 'caution_hold',
                            ],
                        ]);

                        if (!$res['ok']) throw new Exception($res['error']);

                        PCSC_DB::update_case($case_id, [
                            'status' => 'authorized',
                            'stripe_payment_intent_id' => $res['payment_intent_id'],
                            'last_error' => null,
                        ]);

                        PCSC_DB::append_note($case_id, 'Caution prise (autorisation manual capture).');
                        PCSC_DB::schedule_release($case_id);
                        $msg = 'Caution prise (hold). Libération auto planifiée J+7.';
                    }

                    if ($action === 'release') {
                        $case = PCSC_DB::get_case($case_id);
                        if (!$case) throw new Exception('Dossier introuvable.');
                        if (empty($case['stripe_payment_intent_id'])) throw new Exception('Aucune caution active.');

                        $res = PCSC_Stripe::cancel_payment_intent((string)$case['stripe_payment_intent_id']);
                        if (!$res['ok']) throw new Exception($res['error']);

                        PCSC_DB::update_case($case_id, ['status' => 'released', 'last_error' => null]);
                        PCSC_DB::append_note($case_id, 'Libération manuelle effectuée.');
                        $msg = 'Caution libérée.';
                    }

                    if ($action === 'capture') {
                        $case = PCSC_DB::get_case($case_id);
                        if (!$case) throw new Exception('Dossier introuvable.');
                        if (empty($case['stripe_payment_intent_id'])) throw new Exception('Aucune caution active.');

                        $cap_eur = (float) str_replace(',', '.', (string) wp_unslash($_POST['capture_amount_eur'] ?? '0'));
                        $amount_to_capture = (int) round($cap_eur * 100);
                        $note = sanitize_textarea_field(wp_unslash($_POST['capture_note'] ?? ''));

                        if ($amount_to_capture <= 0 || $amount_to_capture > (int)$case['amount']) {
                            throw new Exception('Montant capture invalide.');
                        }

                        $res = PCSC_Stripe::capture_payment_intent((string)$case['stripe_payment_intent_id'], $amount_to_capture);
                        if (!$res['ok']) throw new Exception($res['error']);

                        $new_status = ($amount_to_capture === (int)$case['amount']) ? 'captured' : 'capture_partial';
                        PCSC_DB::update_case($case_id, ['status' => $new_status, 'last_error' => null]);
                        if ($note) PCSC_DB::append_note($case_id, 'NOTE CAPTURE: ' . $note);
                        PCSC_DB::append_note($case_id, 'Capture effectuée: ' . $amount_to_capture);

                        $msg = 'Capture effectuée.';
                    }

                    if ($action === 'rotate') {
                        $res = PCSC_DB::rotate_silent($case_id, 'Rotation manuelle.');
                        if (!$res['ok']) throw new Exception($res['error']);
                        $msg = 'Rotation OK. Nouveau PI: ' . $res['payment_intent_id'];
                    }
                } catch (Exception $e) {
                    $err = $e->getMessage();
                }
            }
        }
        // UI
        $case_id_get = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;
        $case_id = $case_id_get ?: (isset($case_id) ? (int)$case_id : 0);
        $case = $case_id ? PCSC_DB::get_case($case_id) : null;

        ob_start();
?>
        <div class="pcsc-wrap" style="max-width: 920px; margin: 20px auto; padding: 16px; border: 1px solid #e5e7eb; border-radius: 14px; background: #fff;">
            <h2 style="margin:0 0 8px;">Caution Stripe (provisoire)</h2>
            <p style="margin:0 0 16px; color:#374151;">Génère un setup Stripe (carte chez Stripe), puis prise/rotation silencieuse (manual capture).</p>

            <?php if ($msg): ?>
                <div style="padding:10px 12px; border:1px solid #bbf7d0; background:#f0fdf4; border-radius:10px; margin-bottom:12px;"><?php echo esc_html($msg); ?></div>
            <?php endif; ?>
            <?php if ($err): ?>
                <div style="padding:10px 12px; border:1px solid #fecaca; background:#fef2f2; border-radius:10px; margin-bottom:12px;"><?php echo esc_html($err); ?></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(self::admin_url_self()); ?>" style="display:grid; gap:10px; grid-template-columns: 1fr 1fr;">
                <?php wp_nonce_field('pcsc_admin_action', 'pcsc_nonce'); ?>
                <input type="hidden" name="pcsc_action" value="create_case">

                <div>
                    <label style="display:block; font-weight:600; margin-bottom:6px;">Référence réservation</label>
                    <input name="booking_ref" required style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px; font-size:16px;">
                </div>

                <div>
                    <label style="display:block; font-weight:600; margin-bottom:6px;">Email client</label>
                    <input type="email" name="customer_email" required style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px; font-size:16px;">
                </div>

                <div>
                    <label style="display:block; font-weight:600; margin-bottom:6px;">Montant (€)</label>
                    <input
                        type="text"
                        name="amount_eur"
                        inputmode="decimal"
                        required
                        placeholder="ex: 500"
                        style="width:100% !important; min-width:260px; padding:14px; border:1px solid #d1d5db; border-radius:10px; font-size:18px; box-sizing:border-box;">
                    <small style="display:block; margin-top:6px; color:#6b7280;">Conversion automatique en centimes côté Stripe.</small>
                </div>

                <div style="display:grid; gap:10px; grid-template-columns:1fr 1fr;">
                    <div>
                        <label style="display:block; font-weight:600; margin-bottom:6px;">Arrivée</label>
                        <input type="date" name="date_arrivee" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px; font-size:16px;">
                    </div>
                    <div>
                        <label style="display:block; font-weight:600; margin-bottom:6px;">Départ</label>
                        <input type="date" name="date_depart" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px; font-size:16px;">
                    </div>
                </div>

                <div style="grid-column:1 / -1;">
                    <button type="submit" style="padding:12px 14px; border-radius:12px; border:0; background:#1b3b5f; color:#fff; font-weight:700; cursor:pointer;">
                        Créer un dossier caution
                    </button>
                </div>
            </form>

            <?php
            global $wpdb;
            $table = PCSC_DB::table();
            $rows = $wpdb->get_results("SELECT id, booking_ref, customer_email, amount, status FROM {$table} ORDER BY id DESC LIMIT 25", ARRAY_A);
            ?>
            <div style="margin-top:16px; padding:12px; border:1px solid #e5e7eb; border-radius:12px;">
                <div style="font-weight:700; margin-bottom:10px;">Dossiers récents</div>

                <?php if (empty($rows)): ?>
                    <div style="color:#6b7280;">Aucun dossier.</div>
                <?php else: ?>
                    <div style="overflow:auto;">
                        <table style="width:100%; border-collapse:collapse; font-size:14px;">
                            <thead>
                                <tr>
                                    <th style="text-align:left; padding:8px; border-bottom:1px solid #e5e7eb;">ID</th>
                                    <th style="text-align:left; padding:8px; border-bottom:1px solid #e5e7eb;">Booking</th>
                                    <th style="text-align:left; padding:8px; border-bottom:1px solid #e5e7eb;">Montant</th>
                                    <th style="text-align:left; padding:8px; border-bottom:1px solid #e5e7eb;">Status</th>
                                    <th style="text-align:left; padding:8px; border-bottom:1px solid #e5e7eb;">Email</th>
                                    <th style="text-align:left; padding:8px; border-bottom:1px solid #e5e7eb;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td style="padding:8px; border-bottom:1px solid #f3f4f6;"><?php echo (int)$r['id']; ?></td>
                                        <td style="padding:8px; border-bottom:1px solid #f3f4f6;"><?php echo esc_html($r['booking_ref']); ?></td>
                                        <td style="padding:8px; border-bottom:1px solid #f3f4f6;"><?php echo esc_html(number_format(((int)$r['amount']) / 100, 0, ',', ' ')); ?> €</td>
                                        <td style="padding:8px; border-bottom:1px solid #f3f4f6;"><?php echo esc_html($r['status']); ?></td>
                                        <td style="padding:8px; border-bottom:1px solid #f3f4f6;"><?php echo esc_html($r['customer_email']); ?></td>
                                        <td style="padding:8px; border-bottom:1px solid #f3f4f6;">
                                            <a href="<?php echo esc_url(add_query_arg(['case_id' => (int)$r['id']], self::admin_url_self())); ?>" style="font-weight:700; color:#1b3b5f; text-decoration:none;">
                                                Ouvrir
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <hr style="border:0; border-top:1px solid #e5e7eb; margin:18px 0;">

            <div>
                <h3 style="margin:0 0 8px;">Dossier</h3>

                <?php if (!$case): ?>
                    <p style="margin:0; color:#6b7280;">Aucun dossier sélectionné. Crée un dossier ci-dessus, ou ajoute <code>?case_id=XX</code> à l’URL.</p>
                <?php else: ?>
                    <div style="display:grid; gap:10px; grid-template-columns: 1fr 1fr;">
                        <div><strong>ID:</strong> <?php echo (int)$case['id']; ?></div>
                        <div><strong>Status:</strong> <?php echo esc_html($case['status']); ?></div>
                        <div><strong>Booking:</strong> <?php echo esc_html($case['booking_ref']); ?></div>
                        <div><strong>Email:</strong> <?php echo esc_html($case['customer_email']); ?></div>
                        <div><strong>Montant:</strong> <?php echo esc_html(number_format(((int)$case['amount']) / 100, 0, ',', ' ')); ?> €</div>
                        <div><strong>Devise:</strong> <?php echo esc_html($case['currency']); ?></div>
                    </div>

                    <?php if (!empty($case['stripe_setup_url'])): ?>
                        <div style="margin-top:12px; padding:12px; border:1px solid #d1d5db; border-radius:12px;">
                            <div style="font-weight:700; margin-bottom:6px;">Lien Stripe (Setup carte)</div>
                            <input readonly value="<?php echo esc_attr($case['stripe_setup_url']); ?>" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px; font-size:16px;">
                            <p style="margin:8px 0 0; color:#6b7280;">Envoie ce lien au client. Il saisit sa carte sur Stripe.</p>
                        </div>
                    <?php endif; ?>

                    <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:14px;">
                        <?php echo self::action_btn('create_setup_link', $case_id, 'Générer lien setup'); ?>
                        <?php echo self::action_btn('take_hold_now', $case_id, 'Prendre la caution (hold)'); ?>
                        <?php echo self::action_btn('rotate', $case_id, 'Rotation'); ?>
                        <?php echo self::action_btn('release', $case_id, 'Libérer'); ?>
                    </div>

                    <div style="margin-top:14px; padding:12px; border:1px solid #e5e7eb; border-radius:12px;">
                        <div style="font-weight:700; margin-bottom:8px;">Capture (encaissement)</div>
                        <form method="post" action="<?php echo esc_url(self::admin_url_self()); ?>" style="display:grid; gap:10px; grid-template-columns: 1fr 2fr;">
                            <?php wp_nonce_field('pcsc_admin_action', 'pcsc_nonce'); ?>
                            <input type="hidden" name="pcsc_action" value="capture">
                            <input type="hidden" name="case_id" value="<?php echo (int)$case_id; ?>">

                            <input type="number" name="capture_amount_eur" min="1" step="1" required placeholder="Montant (€)" style="padding:14px; border:1px solid #d1d5db; border-radius:10px; font-size:18px; width:100%;">
                            <input name="capture_note" placeholder="Note interne (optionnel)" style="padding:10px; border:1px solid #d1d5db; border-radius:10px; font-size:16px;">

                            <div style="grid-column:1 / -1;">
                                <button type="submit" style="padding:12px 14px; border-radius:12px; border:0; background:#0f766e; color:#fff; font-weight:700; cursor:pointer;">
                                    Capturer
                                </button>
                            </div>
                        </form>
                    </div>

                    <?php if (!empty($case['internal_notes'])): ?>
                        <div style="margin-top:14px; padding:12px; border:1px solid #e5e7eb; border-radius:12px;">
                            <div style="font-weight:700; margin-bottom:8px;">Notes</div>
                            <pre style="white-space:pre-wrap; margin:0; color:#111827;"><?php echo esc_html($case['internal_notes']); ?></pre>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($case['last_error'])): ?>
                        <div style="margin-top:14px; padding:12px; border:1px solid #fecaca; background:#fef2f2; border-radius:12px;">
                            <div style="font-weight:700; margin-bottom:8px;">Dernière erreur</div>
                            <pre style="white-space:pre-wrap; margin:0;"><?php echo esc_html($case['last_error']); ?></pre>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>
            </div>

            <hr style="border:0; border-top:1px solid #e5e7eb; margin:18px 0;">
            <p style="margin:0; color:#6b7280;">
                Webhook à configurer dans Stripe:
                <code><?php echo esc_html(rest_url('pcsc/v1/stripe/webhook')); ?></code>
            </p>
        </div>
<?php

        return ob_get_clean();
    }

    private static function action_btn(string $action, int $case_id, string $label): string
    {
        $html  = '<form method="post" style="margin:0;" action="' . esc_url(self::admin_url_self()) . '">';
        $html .= wp_nonce_field('pcsc_admin_action', 'pcsc_nonce', true, false);
        $html .= '<input type="hidden" name="pcsc_action" value="' . esc_attr($action) . '">';
        $html .= '<input type="hidden" name="case_id" value="' . (int)$case_id . '">';
        $html .= '<button type="submit" style="padding:10px 12px; border-radius:12px; border:1px solid #1b3b5f; background:#1b3b5f; color:#fff; cursor:pointer; font-weight:700;">' . esc_html($label) . '</button>';
        $html .= '</form>';
        return $html;
    }
}
