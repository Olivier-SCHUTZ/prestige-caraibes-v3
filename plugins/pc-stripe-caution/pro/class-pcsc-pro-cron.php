<?php
if (!defined('ABSPATH')) exit;

class PCSC_Pro_Cron
{
    public static function init(): void
    {
        add_action('pcsc_cron_daily', [__CLASS__, 'run_daily_rotation']);
        add_action('pcsc_cron_release', [__CLASS__, 'run_scheduled_release'], 10, 1);
    }

    public static function run_scheduled_release(int $id): void
    {
        $case = PCSC_DB::get_case($id);
        if (!$case) return;

        // Si déjà encaissée, on ne libère pas
        if (in_array($case['status'], ['captured', 'capture_partial'], true)) return;
        if (empty($case['stripe_payment_intent_id'])) return;

        $pi = $case['stripe_payment_intent_id'];
        $res = PCSC_Stripe::cancel_payment_intent($pi);

        if ($res['ok']) {
            PCSC_DB::update_case($id, ['status' => 'released', 'last_error' => null]);
            if (class_exists('PCSC_Mailer')) {
                PCSC_Mailer::send_release_confirmation($case['customer_email'], $case['booking_ref']);
            }
        } else {
            PCSC_DB::update_case($id, ['last_error' => $res['error']]);
            PCSC_DB::append_note($id, sprintf(__('Auto-release failed: %s', 'pc-stripe-caution'), $res['error']));
        }
    }

    public static function run_daily_rotation(): void
    {
        global $wpdb;
        $table = PCSC_DB::table();

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
            if (empty($case['date_depart'])) continue;

            $depart_ts = strtotime($case['date_depart'] . ' 12:00:00');
            $now = time();

            // Email Rappel J-1 Admin
            if (class_exists('PCSC_Mailer')) {
                $days_since_depart = ($now - $depart_ts) / DAY_IN_SECONDS;
                if ($days_since_depart >= 5.5 && $days_since_depart < 6.5) {
                    if (strpos($case['internal_notes'], 'Mail rappel J-1') === false && strpos($case['internal_notes'], 'D-1 reminder') === false) {
                        PCSC_Mailer::send_admin_reminder_release($case['booking_ref'], $case['date_depart']);
                        PCSC_DB::append_note($id, __('D-1 reminder email sent to admin.', 'pc-stripe-caution'));
                    }
                }
            }

            // Si on a dépassé la date de libération prévue, on ne rotate plus (le cron release s'en chargera)
            if ($now > ($depart_ts + 7 * DAY_IN_SECONDS)) continue;

            // Rotation tous les 6 jours
            $updated_ts = strtotime($case['updated_at'] ?: $case['created_at']);
            if ($now < $updated_ts + 6 * DAY_IN_SECONDS) continue;

            PCSC_DB::rotate_silent($id, __('Automatic daily rotation.', 'pc-stripe-caution'));
        }
    }
}
