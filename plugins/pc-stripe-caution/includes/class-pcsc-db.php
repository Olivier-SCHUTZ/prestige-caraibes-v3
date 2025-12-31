<?php
if (!defined('ABSPATH')) exit;

class PCSC_DB
{
    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'pc_cautions';
    }

    public static function activate(): void
    {
        global $wpdb;
        $table = self::table();
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table} (
      id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      booking_ref VARCHAR(190) NOT NULL,
      customer_email VARCHAR(190) NOT NULL,
      amount INT NOT NULL,
      currency VARCHAR(10) NOT NULL DEFAULT 'eur',
      date_arrivee DATE NULL,
      date_depart DATE NULL,

      status VARCHAR(40) NOT NULL DEFAULT 'draft',

      stripe_setup_session_id VARCHAR(255) NULL,
      stripe_setup_url TEXT NULL,
      stripe_setup_intent_id VARCHAR(255) NULL,
      stripe_customer_id VARCHAR(255) NULL,
      stripe_payment_method_id VARCHAR(255) NULL,

      stripe_payment_intent_id VARCHAR(255) NULL,
      stripe_payment_intent_prev VARCHAR(255) NULL,

      last_error TEXT NULL,
      internal_notes TEXT NULL,

      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,

      PRIMARY KEY  (id),
      KEY booking_ref (booking_ref),
      KEY status (status)
    ) {$charset};";

        dbDelta($sql);
    }

    public static function now(): string
    {
        return current_time('mysql');
    }

    public static function insert_case(array $data): int
    {
        global $wpdb;
        $table = self::table();
        $defaults = [
            'booking_ref' => '',
            'customer_email' => '',
            'amount' => 0,
            'currency' => defined('PC_STRIPE_CURRENCY') ? PC_STRIPE_CURRENCY : 'eur',
            'date_arrivee' => null,
            'date_depart' => null,
            'status' => 'draft',
            'created_at' => self::now(),
            'updated_at' => self::now(),
        ];
        $row = array_merge($defaults, $data);

        $wpdb->insert($table, $row);
        return (int)$wpdb->insert_id;
    }

    public static function get_case(int $id): ?array
    {
        global $wpdb;
        $table = self::table();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $id), ARRAY_A);
        return $row ?: null;
    }

    public static function update_case(int $id, array $data): void
    {
        global $wpdb;
        $table = self::table();
        $data['updated_at'] = self::now();
        $wpdb->update($table, $data, ['id' => $id]);
    }

    // --- AJOUT : Suppression ---
    public static function delete_case(int $id): void
    {
        global $wpdb;
        $wpdb->delete(self::table(), ['id' => $id]);
    }

    public static function append_note(int $id, string $note): void
    {
        $case = self::get_case($id);
        if (!$case) return;
        $existing = (string)($case['internal_notes'] ?? '');
        $stamp = '[' . self::now() . '] ';
        $new = trim($existing . "\n" . $stamp . $note);
        self::update_case($id, ['internal_notes' => $new]);
    }

    public static function schedule_release(int $id): void
    {
        $case = self::get_case($id);
        if (!$case) return;
        if (empty($case['date_depart'])) return;

        $release_ts = strtotime($case['date_depart'] . ' 12:00:00') + (7 * DAY_IN_SECONDS);

        $hook = 'pcsc_cron_release';
        $args = [$id];

        // Évite doublons
        $next = wp_next_scheduled($hook, $args);
        if ($next) return;

        wp_schedule_single_event($release_ts, $hook, $args);
    }

    public static function cron_release_single(int $id): void
    {
        $case = self::get_case($id);
        if (!$case) return;

        // Si déjà encaissée, on ne libère pas
        if (in_array($case['status'], ['captured', 'capture_partial'], true)) return;
        if (empty($case['stripe_payment_intent_id'])) return;

        $pi = $case['stripe_payment_intent_id'];
        $res = PCSC_Stripe::cancel_payment_intent($pi);

        if ($res['ok']) {
            self::update_case($id, ['status' => 'released', 'last_error' => null]);

            // --- AJOUT EMAIL CLIENT ---
            if (class_exists('PCSC_Mailer')) {
                PCSC_Mailer::send_release_confirmation($case['customer_email'], $case['booking_ref']);
            }
        } else {
            self::update_case($id, ['last_error' => $res['error']]);
            // TRADUCTION ICI
            self::append_note($id, sprintf(__('Auto-release failed: %s', 'pc-stripe-caution'), $res['error']));
        }
    }

    public static function cron_daily(): void
    {
        // Rotation silencieuse : si séjour long et PI trop ancien, on tente une rotation.
        global $wpdb;
        $table = self::table();

        $rows = $wpdb->get_results(
            "SELECT * FROM {$table}
       WHERE status IN ('authorized','rotated','rotation_failed','rotation_requires_action')
       AND stripe_customer_id IS NOT NULL
       AND stripe_payment_method_id IS NOT NULL
       AND stripe_payment_intent_id IS NOT NULL",
            ARRAY_A
        );

        foreach ($rows as $case) {
            $id = (int)$case['id'];

            // Si pas de date_depart, on ne fait rien en auto
            if (empty($case['date_depart'])) continue;

            $depart_ts = strtotime($case['date_depart'] . ' 12:00:00');
            $now = time();
            if (class_exists('PCSC_Mailer')) {
                $days_since_depart = ($now - $depart_ts) / DAY_IN_SECONDS;
                // Entre 5.5 et 6.5 jours après le départ = le 6ème jour (la veille du 7ème)
                if ($days_since_depart >= 5.5 && $days_since_depart < 6.5) {
                    // Note: on vérifie une chaine partielle, donc on cherche "D-1 reminder" ou l'ancienne version française pour compatibilité
                    if (strpos($case['internal_notes'], 'Mail rappel J-1') === false && strpos($case['internal_notes'], 'D-1 reminder') === false) {
                        PCSC_Mailer::send_admin_reminder_release($case['booking_ref'], $case['date_depart']);
                        // TRADUCTION ICI
                        self::append_note($id, __('D-1 reminder email sent to admin.', 'pc-stripe-caution'));
                    }
                }
            }

            // Si déjà après départ+7 => la libération sera gérée par cron_release_single si planifié
            if ($now > ($depart_ts + 7 * DAY_IN_SECONDS)) continue;

            // Règle simple provisoire : rotation toutes les 6 jours (à partir de la dernière "validation")
            $updated_ts = strtotime($case['updated_at'] ?: $case['created_at']);
            if ($now < $updated_ts + 6 * DAY_IN_SECONDS) continue;

            // TRADUCTION ICI
            self::rotate_silent($id, __('Automatic daily rotation.', 'pc-stripe-caution'));
        }
    }

    public static function rotate_silent(int $id, string $reason = ''): array
    {
        $case = self::get_case($id);
        // TRADUCTION ICI
        if (!$case) return ['ok' => false, 'error' => __('Case not found', 'pc-stripe-caution')];

        if (empty($case['stripe_customer_id']) || empty($case['stripe_payment_method_id'])) {
            return ['ok' => false, 'error' => __('PaymentMethod not available (setup incomplete).', 'pc-stripe-caution')];
        }

        $old_pi = (string)$case['stripe_payment_intent_id'];
        $amount = (int)$case['amount'];
        $currency = !empty($case['currency'])
            ? strtolower($case['currency'])
            : (defined('PC_STRIPE_CURRENCY') ? strtolower(PC_STRIPE_CURRENCY) : 'eur');

        self::update_case($id, ['currency' => $currency]);

        // 1) Annule l’ancienne autorisation (si possible)
        $cancel = PCSC_Stripe::cancel_payment_intent($old_pi);
        if (!$cancel['ok']) {
            self::update_case($id, ['status' => 'rotation_failed', 'last_error' => $cancel['error']]);
            // TRADUCTION ICI
            self::append_note($id, sprintf(__('Rotation: Previous PI cancellation failed: %s', 'pc-stripe-caution'), $cancel['error']));
            return ['ok' => false, 'error' => $cancel['error']];
        }

        // 2) Crée une nouvelle autorisation off-session
        $create = PCSC_Stripe::create_manual_hold_off_session([
            'amount' => $amount,
            'currency' => $currency,
            'customer_id' => (string)$case['stripe_customer_id'],
            'payment_method_id' => (string)$case['stripe_payment_method_id'],
            'metadata' => [
                'pc_case_id' => (string)$id,
                'booking_ref' => (string)$case['booking_ref'],
                'purpose' => 'caution_rotation',
            ],
        ]);

        if (!$create['ok']) {
            $err = $create['error'];
            // Plan B : si nécessite action, on marquera requires_action et on générera un lien assisté ensuite (si tu veux)
            self::update_case($id, ['status' => 'rotation_requires_action', 'last_error' => $err]);
            // TRADUCTION ICI
            self::append_note($id, sprintf(__('Rotation: Requires action or off-session PI failed: %s', 'pc-stripe-caution'), $err));
            return ['ok' => false, 'error' => $err];
        }

        $new_pi = $create['payment_intent_id'];

        self::update_case($id, [
            'status' => 'rotated',
            'stripe_payment_intent_prev' => $old_pi,
            'stripe_payment_intent_id' => $new_pi,
            'last_error' => null,
        ]);

        // TRADUCTION ICI
        self::append_note($id, trim(__('Silent rotation OK.', 'pc-stripe-caution') . ' ' . $reason));
        return ['ok' => true, 'payment_intent_id' => $new_pi];
    }
}
