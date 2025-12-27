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
            self::append_note($id, 'Libération automatique J+7 effectuée.');
        } else {
            self::update_case($id, ['last_error' => $res['error']]);
            self::append_note($id, 'Échec libération auto: ' . $res['error']);
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

            // Si déjà après départ+7 => la libération sera gérée par cron_release_single si planifié
            if ($now > ($depart_ts + 7 * DAY_IN_SECONDS)) continue;

            // Règle simple provisoire : rotation toutes les 6 jours (à partir de la dernière "validation")
            $updated_ts = strtotime($case['updated_at'] ?: $case['created_at']);
            if ($now < $updated_ts + 6 * DAY_IN_SECONDS) continue;

            self::rotate_silent($id, 'Rotation quotidienne automatique.');
        }
    }

    public static function rotate_silent(int $id, string $reason = ''): array
    {
        $case = self::get_case($id);
        if (!$case) return ['ok' => false, 'error' => 'Dossier introuvable'];

        if (empty($case['stripe_customer_id']) || empty($case['stripe_payment_method_id'])) {
            return ['ok' => false, 'error' => 'PaymentMethod non disponible (setup non terminé).'];
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
            self::append_note($id, 'Rotation: échec annulation PI précédent: ' . $cancel['error']);
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
            self::append_note($id, 'Rotation: nécessite action ou échec PI off-session: ' . $err);
            return ['ok' => false, 'error' => $err];
        }

        $new_pi = $create['payment_intent_id'];

        self::update_case($id, [
            'status' => 'rotated',
            'stripe_payment_intent_prev' => $old_pi,
            'stripe_payment_intent_id' => $new_pi,
            'last_error' => null,
        ]);

        self::append_note($id, trim('Rotation silencieuse OK. ' . $reason));
        return ['ok' => true, 'payment_intent_id' => $new_pi];
    }
}
