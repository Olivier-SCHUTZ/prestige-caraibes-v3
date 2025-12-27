<?php
if (!defined('ABSPATH')) exit;

class PCSC_Shortcodes
{
    public static function init(): void
    {
        add_shortcode('pc_caution_admin', [__CLASS__, 'render_admin']);
    }

    private static function check_access(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Accès refusé. Vous devez être administrateur.');
        }
    }

    private static function prevent_cache(): void
    {
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        nocache_headers();
    }

    private static function get_url(array $params = []): string
    {
        $current_url = remove_query_arg(array_keys($_GET));
        return add_query_arg($params, $current_url);
    }

    private static function eur_to_cents($input): int
    {
        $clean = str_replace([',', ' '], ['.', ''], (string)$input);
        $float = (float)$clean;
        return (int) round($float * 100);
    }

    public static function render_admin($atts): string
    {
        self::check_access();
        self::prevent_cache();

        $message = '';
        $error = '';

        // Gestion message après redirection
        if (isset($_GET['msg']) && $_GET['msg'] === 'done') {
            $message = "Action effectuée avec succès.";
        }

        // --- GESTION DES ACTIONS POST ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['pcsc_action'])) {
            if (!check_admin_referer('pcsc_admin_action', 'pcsc_nonce')) {
                $error = 'Sécurité: Session expirée. Veuillez recharger la page.';
            } else {
                try {
                    $action = sanitize_text_field($_POST['pcsc_action']);
                    $case_id = isset($_POST['case_id']) ? (int)$_POST['case_id'] : 0;

                    // 1. CRÉATION
                    if ($action === 'create_case') {
                        $ref = sanitize_text_field($_POST['booking_ref']);
                        $email = sanitize_email($_POST['customer_email']);
                        $amount = self::eur_to_cents($_POST['amount_eur']);
                        $arr = sanitize_text_field($_POST['date_arrivee']);
                        $dep = sanitize_text_field($_POST['date_depart']);

                        if (!$ref || !$email || $amount <= 0) {
                            throw new Exception("Champs obligatoires manquants ou montant invalide.");
                        }

                        $new_id = PCSC_DB::insert_case([
                            'booking_ref' => $ref,
                            'customer_email' => $email,
                            'amount' => $amount,
                            'date_arrivee' => $arr ?: null,
                            'date_depart' => $dep ?: null,
                        ]);

                        wp_safe_redirect(self::get_url(['case_id' => $new_id]));
                        exit;
                    }

                    $case = PCSC_DB::get_case($case_id);
                    if (!$case) throw new Exception("Dossier introuvable.");

                    // 2. GÉNÉRER LIEN (C'est ici qu'on ajoute la correction CLIENT)
                    if ($action === 'create_setup_link') {

                        // --- CORRECTION DÉBUT : On crée le client Stripe MAINTENANT ---
                        $stripe_cust_id = $case['stripe_customer_id'];

                        if (empty($stripe_cust_id)) {
                            // Appel à la nouvelle fonction dans class-pcsc-stripe.php
                            $cust_res = PCSC_Stripe::create_customer($case['customer_email'], $case['booking_ref']);

                            if (!$cust_res['ok']) {
                                throw new Exception("Erreur lors de la création du client Stripe : " . $cust_res['error']);
                            }

                            $stripe_cust_id = $cust_res['id'];
                            // On sauvegarde immédiatement l'ID Client
                            PCSC_DB::update_case($case_id, ['stripe_customer_id' => $stripe_cust_id]);
                        }
                        // --- CORRECTION FIN ---

                        // --- PRÉPARATION DU MESSAGE CLIENT ---
                        $fmt_amount = number_format($case['amount'] / 100, 2, ',', ' ');
                        $fmt_date = $case['date_depart'] ? date('d/m/Y', strtotime($case['date_depart'])) : 'non définie';
                        $message_client = "Caution réf. " . $case['booking_ref'] . " de " . $fmt_amount . " €. Libérable après le " . $fmt_date . ".";

                        $res = PCSC_Stripe::create_checkout_setup_session([
                            'success_url' => home_url('/caution-merci/?case_id=' . $case_id),
                            'cancel_url'  => home_url('/caution-annulee/?case_id=' . $case_id),
                            'customer_email' => $case['customer_email'], // Fallback
                            'customer_id' => $stripe_cust_id,            // ON LIE LE LIEN AU CLIENT
                            'metadata' => [
                                'pc_case_id' => $case_id,
                                'booking_ref' => $case['booking_ref'],
                            ],
                            // AJOUT : On envoie le message à notre fonction Stripe
                            'checkout_message' => $message_client
                        ]);

                        if (!$res['ok']) throw new Exception($res['error']);

                        PCSC_DB::update_case($case_id, [
                            'status' => 'setup_link_created',
                            'stripe_setup_session_id' => $res['session_id'],
                            'stripe_setup_url' => $res['url'],
                            'last_error' => null
                        ]);

                        PCSC_DB::append_note($case_id, "Client Stripe ($stripe_cust_id) vérifié/créé. Lien généré.");

                        wp_safe_redirect(self::get_url(['case_id' => $case_id, 'msg' => 'done']));
                        exit;
                    }

                    // 3. HOLD
                    if ($action === 'take_hold') {
                        if (empty($case['stripe_customer_id']) || empty($case['stripe_payment_method_id'])) {
                            throw new Exception("Le client n'a pas encore enregistré sa carte (Setup incomplet).");
                        }

                        $res = PCSC_Stripe::create_manual_hold_off_session([
                            'amount' => (int)$case['amount'],
                            'customer_id' => $case['stripe_customer_id'],
                            'payment_method_id' => $case['stripe_payment_method_id'],
                            'metadata' => [
                                'pc_case_id' => $case_id,
                                'booking_ref' => $case['booking_ref']
                            ]
                        ]);

                        if (!$res['ok']) throw new Exception($res['error']);

                        PCSC_DB::update_case($case_id, [
                            'status' => 'authorized',
                            'stripe_payment_intent_id' => $res['payment_intent_id'],
                            'currency' => 'eur',
                            'last_error' => null
                        ]);
                        PCSC_DB::append_note($case_id, 'Caution prise (Hold actif).');
                        PCSC_DB::schedule_release($case_id);

                        wp_safe_redirect(self::get_url(['case_id' => $case_id, 'msg' => 'done']));
                        exit;
                    }

                    // 4. CAPTURE
                    if ($action === 'capture') {
                        $cap_amount = self::eur_to_cents($_POST['capture_amount_eur']);
                        $note = sanitize_textarea_field($_POST['capture_note']);
                        $pi_id = $case['stripe_payment_intent_id'];

                        if (empty($pi_id)) throw new Exception("Aucune caution active à encaisser.");
                        if ($cap_amount <= 0 || $cap_amount > (int)$case['amount']) throw new Exception("Montant invalide.");

                        $res = PCSC_Stripe::capture_payment_intent($pi_id, $cap_amount);
                        if (!$res['ok']) throw new Exception($res['error']);

                        $status = ($cap_amount === (int)$case['amount']) ? 'captured' : 'capture_partial';
                        PCSC_DB::update_case($case_id, ['status' => $status, 'last_error' => null]);
                        PCSC_DB::append_note($case_id, "Encaissement effectué: " . ($cap_amount / 100) . "€. Note: $note");

                        wp_safe_redirect(self::get_url(['case_id' => $case_id, 'msg' => 'done']));
                        exit;
                    }

                    // 5. RELEASE
                    if ($action === 'release') {
                        $pi_id = $case['stripe_payment_intent_id'];
                        if (empty($pi_id)) throw new Exception("Rien à libérer.");

                        $res = PCSC_Stripe::cancel_payment_intent($pi_id);
                        if (!$res['ok']) throw new Exception($res['error']);

                        PCSC_DB::update_case($case_id, ['status' => 'released', 'last_error' => null]);
                        PCSC_DB::append_note($case_id, "Caution libérée manuellement.");

                        wp_safe_redirect(self::get_url(['case_id' => $case_id, 'msg' => 'done']));
                        exit;
                    }

                    // 6. ROTATE
                    if ($action === 'rotate') {
                        $res = PCSC_DB::rotate_silent($case_id, "Rotation manuelle demandée.");
                        if (!$res['ok']) throw new Exception($res['error']);

                        wp_safe_redirect(self::get_url(['case_id' => $case_id, 'msg' => 'done']));
                        exit;
                    }
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            }
        }

        // --- AFFICHAGE (UI DÉTAILLÉE) ---
        ob_start();
        $case_id_view = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;
?>

        <style>
            .pc-wrap {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                max-width: 800px;
                margin: 20px auto;
                background: #fff;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            }

            .pc-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                border-bottom: 2px solid #f3f4f6;
                padding-bottom: 15px;
            }

            .pc-btn {
                display: inline-block;
                padding: 10px 16px;
                border-radius: 6px;
                font-weight: 600;
                text-decoration: none;
                border: none;
                cursor: pointer;
                font-size: 14px;
            }

            .pc-btn-primary {
                background: #2563eb;
                color: white;
            }

            .pc-btn-success {
                background: #059669;
                color: white;
            }

            .pc-btn-danger {
                background: #dc2626;
                color: white;
            }

            .pc-btn-outline {
                background: transparent;
                border: 1px solid #d1d5db;
                color: #374151;
            }

            .pc-alert {
                padding: 12px;
                border-radius: 6px;
                margin-bottom: 20px;
                font-weight: 500;
            }

            .pc-alert-success {
                background: #d1fae5;
                color: #065f46;
                border: 1px solid #a7f3d0;
            }

            .pc-alert-error {
                background: #fee2e2;
                color: #991b1b;
                border: 1px solid #fecaca;
            }

            .pc-input {
                width: 100%;
                padding: 12px;
                border: 1px solid #d1d5db;
                border-radius: 6px;
                font-size: 16px;
                box-sizing: border-box;
                margin-bottom: 10px;
            }

            .pc-label {
                display: block;
                font-weight: 600;
                margin-bottom: 5px;
                color: #374151;
            }

            .pc-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }

            .pc-card {
                background: #f9fafb;
                padding: 15px;
                border-radius: 6px;
                border: 1px solid #e5e7eb;
                margin-bottom: 15px;
            }

            .pc-table {
                width: 100%;
                border-collapse: collapse;
            }

            .pc-table th {
                text-align: left;
                padding: 10px;
                border-bottom: 2px solid #e5e7eb;
                color: #6b7280;
                font-size: 12px;
                text-transform: uppercase;
            }

            .pc-table td {
                padding: 12px 10px;
                border-bottom: 1px solid #f3f4f6;
            }

            .pc-badge {
                padding: 4px 8px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: 600;
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
        </style>

        <div class="pc-wrap">
            <?php if ($message): ?><div class="pc-alert pc-alert-success"><?php echo esc_html($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="pc-alert pc-alert-error"><?php echo esc_html($error); ?></div><?php endif; ?>

            <?php if ($case_id_view): ?>
                <?php self::render_case_detail($case_id_view); ?>
            <?php else: ?>
                <?php self::render_dashboard(); ?>
            <?php endif; ?>
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
            <h2 style="margin:0;">Tableau des cautions</h2>
        </div>

        <div class="pc-card">
            <h3 style="margin-top:0;">Nouveau dossier</h3>
            <form method="post" class="pc-grid">
                <?php wp_nonce_field('pcsc_admin_action', 'pcsc_nonce'); ?>
                <input type="hidden" name="pcsc_action" value="create_case">

                <div>
                    <label class="pc-label">Référence Réservation</label>
                    <input type="text" name="booking_ref" class="pc-input" required placeholder="ex: 2024-ABC">
                </div>
                <div>
                    <label class="pc-label">Email Client</label>
                    <input type="email" name="customer_email" class="pc-input" required placeholder="client@email.com">
                </div>
                <div>
                    <label class="pc-label">Montant Caution (€)</label>
                    <input type="text" inputmode="decimal" name="amount_eur" class="pc-input" required placeholder="ex: 500">
                </div>
                <div>
                    <div class="pc-grid" style="gap:5px;">
                        <div>
                            <label class="pc-label">Arrivée</label>
                            <input type="date" name="date_arrivee" class="pc-input">
                        </div>
                        <div>
                            <label class="pc-label">Départ</label>
                            <input type="date" name="date_depart" class="pc-input">
                        </div>
                    </div>
                </div>
                <div style="grid-column: 1 / -1;">
                    <button type="submit" class="pc-btn pc-btn-primary" style="width:100%;">Créer le dossier</button>
                </div>
            </form>
        </div>

        <div style="overflow-x:auto;">
            <table class="pc-table">
                <thead>
                    <tr>
                        <th>Réf</th>
                        <th>Client</th>
                        <th>Montant</th>
                        <th>Statut</th>
                        <th>Départ</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cases as $c):
                        $amt = number_format($c['amount'] / 100, 2, ',', ' ');
                        $cls = 'status-' . $c['status'];
                    ?>
                        <tr>
                            <td><b><?php echo esc_html($c['booking_ref']); ?></b></td>
                            <td><?php echo esc_html($c['customer_email']); ?></td>
                            <td><?php echo $amt; ?> €</td>
                            <td><span class="pc-badge <?php echo $cls; ?>"><?php echo esc_html($c['status']); ?></span></td>
                            <td><?php echo $c['date_depart'] ? date('d/m', strtotime($c['date_depart'])) : '-'; ?></td>
                            <td>
                                <a href="<?php echo esc_url(self::get_url(['case_id' => $c['id']])); ?>" class="pc-btn pc-btn-outline" style="padding:6px 10px; font-size:12px;">Ouvrir</a>
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
            echo '<p>Dossier introuvable.</p> <a href="' . esc_url(self::get_url()) . '" class="pc-btn pc-btn-outline">Retour</a>';
            return;
        }
        $status = $case['status'];
    ?>
        <div class="pc-header">
            <div>
                <a href="<?php echo esc_url(self::get_url()); ?>" style="text-decoration:none; color:#6b7280; font-size:14px;">← Retour liste</a>
                <h2 style="margin:5px 0 0;"><?php echo esc_html($case['booking_ref']); ?></h2>
            </div>
            <div>
                <span class="pc-badge status-<?php echo $status; ?>" style="font-size:14px; padding:6px 12px;"><?php echo esc_html($status); ?></span>
            </div>
        </div>

        <div class="pc-grid">
            <div>
                <div class="pc-card">
                    <h4 style="margin-top:0;">Détails</h4>
                    <p><strong>Email:</strong> <?php echo esc_html($case['customer_email']); ?></p>
                    <p><strong>Caution:</strong> <?php echo number_format($case['amount'] / 100, 2, ',', ' '); ?> €</p>
                    <p><strong>Départ:</strong> <?php echo esc_html($case['date_depart']); ?></p>
                    <?php if ($case['last_error']): ?>
                        <div class="pc-alert pc-alert-error" style="font-size:13px; margin-top:10px;">
                            <strong>Dernière erreur :</strong><br>
                            <?php echo esc_html($case['last_error']); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="pc-card">
                    <h4 style="margin-top:0;">Lien Client (Setup)</h4>
                    <?php if ($case['stripe_setup_url']): ?>
                        <input type="text" readonly class="pc-input" value="<?php echo esc_attr($case['stripe_setup_url']); ?>" onclick="this.select()">
                        <small style="color:#6b7280;">Envoyez ce lien au client pour qu'il enregistre sa carte.</small>

                        <div style="margin-top:10px; border-top:1px solid #eee; padding-top:10px;">
                            <form method="post">
                                <?php wp_nonce_field('pcsc_admin_action', 'pcsc_nonce'); ?>
                                <input type="hidden" name="pcsc_action" value="create_setup_link">
                                <input type="hidden" name="case_id" value="<?php echo $id; ?>">
                                <button type="submit" class="pc-btn pc-btn-outline" style="font-size:12px; width:100%;" onclick="return confirm('Attention: cela va créer un NOUVEAU lien. Continuer ?');">
                                    ↻ Regénérer un nouveau lien
                                </button>
                            </form>
                        </div>

                    <?php else: ?>
                        <p style="color:#6b7280; font-style:italic;">Lien non généré.</p>
                        <form method="post">
                            <?php wp_nonce_field('pcsc_admin_action', 'pcsc_nonce'); ?>
                            <input type="hidden" name="pcsc_action" value="create_setup_link">
                            <input type="hidden" name="case_id" value="<?php echo $id; ?>">
                            <button type="submit" class="pc-btn pc-btn-primary" style="width:100%;">Générer le lien Setup</button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="pc-card">
                    <h4 style="margin-top:0;">Notes internes</h4>
                    <div style="background:#fff; border:1px solid #eee; padding:10px; height:150px; overflow-y:auto; font-size:13px; white-space:pre-wrap;"><?php echo esc_html($case['internal_notes']); ?></div>
                </div>
            </div>

            <div>
                <div class="pc-card" style="border-left: 4px solid #2563eb;">
                    <h3 style="margin-top:0; color:#1e40af;">1. Bloquer les fonds (Hold)</h3>
                    <p style="font-size:14px; color:#6b7280;">Effectue une demande d'autorisation off-session.</p>

                    <?php if ($status === 'setup_ok'): ?>
                        <div class="pc-alert pc-alert-success" style="margin-bottom:10px; font-size:13px;">
                            Carte enregistrée ! Prêt à prendre la caution.
                        </div>
                    <?php endif; ?>

                    <?php if (in_array($status, ['setup_ok', 'setup_link_created', 'released'])): ?>
                        <form method="post">
                            <?php wp_nonce_field('pcsc_admin_action', 'pcsc_nonce'); ?>
                            <input type="hidden" name="pcsc_action" value="take_hold">
                            <input type="hidden" name="case_id" value="<?php echo $id; ?>">
                            <button type="submit" class="pc-btn pc-btn-primary" style="width:100%;">Prendre la caution</button>
                        </form>
                    <?php elseif ($status === 'draft'): ?>
                        <p style="color:#b45309;">Attente lien généré ou carte enregistrée.</p>
                    <?php else: ?>
                        <p style="color:#059669;">Caution déjà active.</p>
                    <?php endif; ?>
                </div>

                <?php if (in_array($status, ['authorized', 'rotated', 'rotation_failed'])): ?>
                    <div class="pc-card">
                        <h3 style="margin-top:0;">Rotation (Renouvellement)</h3>
                        <form method="post">
                            <?php wp_nonce_field('pcsc_admin_action', 'pcsc_nonce'); ?>
                            <input type="hidden" name="pcsc_action" value="rotate">
                            <input type="hidden" name="case_id" value="<?php echo $id; ?>">
                            <button type="submit" class="pc-btn pc-btn-outline" style="width:100%;">Forcer une rotation</button>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if (in_array($status, ['authorized', 'rotated', 'capture_partial'])): ?>
                    <div class="pc-card" style="border-left: 4px solid #dc2626;">
                        <h3 style="margin-top:0; color:#991b1b;">2. Encaisser (Capture)</h3>
                        <p style="font-size:14px; color:#6b7280;">Débit réel de la carte. Irréversible.</p>
                        <form method="post">
                            <?php wp_nonce_field('pcsc_admin_action', 'pcsc_nonce'); ?>
                            <input type="hidden" name="pcsc_action" value="capture">
                            <input type="hidden" name="case_id" value="<?php echo $id; ?>">

                            <label class="pc-label">Montant à encaisser (€)</label>
                            <input type="text" inputmode="decimal" name="capture_amount_eur" class="pc-input" placeholder="ex: 250,00" required>

                            <label class="pc-label">Motif (Note)</label>
                            <input type="text" name="capture_note" class="pc-input" placeholder="ex: Nettoyage sup.">

                            <button type="submit" class="pc-btn pc-btn-danger" style="width:100%; margin-top:10px;" onclick="return confirm('Êtes-vous sûr de vouloir DEBITER la carte ?');">CONFIRMER LE DEBIT</button>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if (in_array($status, ['authorized', 'rotated', 'capture_partial'])): ?>
                    <div class="pc-card" style="border-left: 4px solid #059669;">
                        <h3 style="margin-top:0; color:#065f46;">3. Libérer la caution</h3>
                        <p style="font-size:14px; color:#6b7280;">Annule l'empreinte bancaire.</p>
                        <form method="post">
                            <?php wp_nonce_field('pcsc_admin_action', 'pcsc_nonce'); ?>
                            <input type="hidden" name="pcsc_action" value="release">
                            <input type="hidden" name="case_id" value="<?php echo $id; ?>">
                            <button type="submit" class="pc-btn pc-btn-success" style="width:100%;">Libérer maintenant</button>
                        </form>
                    </div>
                <?php endif; ?>

            </div>
        </div>
<?php
    }
}
