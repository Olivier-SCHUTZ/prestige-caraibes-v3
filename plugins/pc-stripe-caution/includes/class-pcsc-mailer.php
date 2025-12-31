<?php
if (!defined('ABSPATH')) exit;

class PCSC_Mailer
{
    /**
     * Récupère le nom de l'entreprise depuis les réglages ou utilise une valeur par défaut.
     */
    private static function get_company_name(): string
    {
        if (class_exists('PCSC_Settings')) {
            $name = PCSC_Settings::get_option('company_name');
            if (!empty($name)) return $name;
        }
        return get_bloginfo('name'); // Fallback sur le nom du site
    }

    private static function get_header(): string
    {
        $company = esc_html(self::get_company_name());
        return '<div style="font-family: Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 10px; background-color: #ffffff;">
                <div style="text-align: center; border-bottom: 2px solid #2563eb; padding-bottom: 20px; margin-bottom: 20px;">
                    <h2 style="color: #2563eb; margin: 0;">' . $company . '</h2>
                </div>';
    }

    private static function get_footer(): string
    {
        $text = __('This is an automated message regarding your rental deposit.', 'pc-stripe-caution');
        return '<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #888; text-align: center;">
                    <p>' . esc_html($text) . '</p>
                </div>
            </div>';
    }

    private static function send(string $to, string $subject, string $body): void
    {
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $content = self::get_header() . $body . self::get_footer();
        wp_mail($to, $subject, $content, $headers);
    }

    // --- EMAILS CLIENTS ---

    public static function send_setup_link(string $email, string $ref, int $amount_cents, string $url): void
    {
        $amount = number_format($amount_cents / 100, 2, ',', ' ');
        $btn_color = '#183C3C'; // Pourrait être mis en option plus tard

        // Traductions
        $subject = sprintf(__('Secure Deposit Link - Ref %s', 'pc-stripe-caution'), $ref);

        $p1 = __('Hello,', 'pc-stripe-caution');
        $p2 = sprintf(
            __('To finalize your booking (Ref: <strong>%s</strong>), please proceed to secure your deposit of <strong>%s €</strong>.', 'pc-stripe-caution'),
            $ref,
            $amount
        );
        $p3 = __('Please click the button below to secure the bank imprint via our partner Stripe (no immediate debit is made):', 'pc-stripe-caution');
        $btn_text = __('Deposit my caution online', 'pc-stripe-caution');
        $p4 = __('If the button does not work, you can copy this link into your browser:', 'pc-stripe-caution');
        $p5 = __('We remain at your disposal for any questions.', 'pc-stripe-caution');
        $closing = sprintf(__('Sincerely,<br>The %s Team', 'pc-stripe-caution'), self::get_company_name());

        $body = "<p>$p1</p>
                 <p>$p2</p>
                 <p>$p3</p>
                 
                 <div style='text-align: center; margin: 30px 0;'>
                    <a href='" . esc_url($url) . "' style='background-color: $btn_color; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 30px; font-weight: bold; font-size: 16px; display: inline-block; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>$btn_text</a>
                 </div>

                 <p style='font-size: 12px; color: #999; text-align: center; margin-top: 20px;'>
                    $p4<br>
                    <a href='" . esc_url($url) . "' style='color: #666; text-decoration: underline; word-break: break-all;'>" . esc_url($url) . "</a>
                 </p>
                 
                 <p>$p5</p>
                 <p>$closing</p>";

        self::send($email, $subject, $body);
    }

    public static function send_hold_confirmation(string $email, string $ref, int $amount_cents, string $date_release_txt): void
    {
        $amount = number_format($amount_cents / 100, 2, ',', ' ');

        $subject = sprintf(__('Deposit Confirmation - Ref %s', 'pc-stripe-caution'), $ref);

        $p1 = __('Hello,', 'pc-stripe-caution');
        $p2 = sprintf(__('We confirm that the bank imprint for your deposit (Ref: <strong>%s</strong>) has been successfully secured.', 'pc-stripe-caution'), $ref);
        $label_amount = __('Blocked amount:', 'pc-stripe-caution');
        $label_date = __('Expected release date:', 'pc-stripe-caution');
        $p3 = __('No amount has been debited. This sum is simply temporarily blocked on your account.', 'pc-stripe-caution');
        $p4 = __('We wish you an excellent stay!', 'pc-stripe-caution');

        $body = "<p>$p1</p>
                 <p>$p2</p>
                 <div style='background: #f0f9ff; border-left: 4px solid #2563eb; padding: 15px; margin: 20px 0;'>
                    <p style='margin: 0;'><strong>$label_amount</strong> $amount €</p>
                    <p style='margin: 5px 0 0;'><strong>$label_date</strong> $date_release_txt</p>
                 </div>
                 <p>$p3</p>
                 <p>$p4</p>";

        self::send($email, $subject, $body);
    }

    public static function send_release_confirmation(string $email, string $ref): void
    {
        $subject = sprintf(__('Deposit Released - Ref %s', 'pc-stripe-caution'), $ref);
        $company = self::get_company_name();

        $p1 = __('Hello,', 'pc-stripe-caution');
        $p2 = sprintf(__('Good news! Your deposit for booking <strong>%s</strong> has been released.', 'pc-stripe-caution'), $ref);
        $msg_success = __('Deposit successfully released.', 'pc-stripe-caution');
        $p3 = __('The bank imprint has been cancelled. Depending on your bank, it may take a few days to completely disappear from your statements.', 'pc-stripe-caution');
        $p4 = sprintf(__('See you soon at %s!', 'pc-stripe-caution'), $company);

        $body = "<p>$p1</p>
                 <p>$p2</p>
                 <div style='background: #dcfce7; border-left: 4px solid #16a34a; padding: 15px; margin: 20px 0;'>
                    <strong style='color: #166534;'>$msg_success</strong>
                 </div>
                 <p>$p3</p>
                 <p>$p4</p>";

        self::send($email, $subject, $body);
    }

    public static function send_capture_confirmation(string $email, string $ref, int $amount_cents, string $reason): void
    {
        $amount = number_format($amount_cents / 100, 2, ',', ' ');
        $motif = $reason ?: __('Damage fees / Reported issues', 'pc-stripe-caution');

        $subject = sprintf(__('Deposit Closed (Charge Applied) - Ref %s', 'pc-stripe-caution'), $ref);

        $p1 = __('Hello,', 'pc-stripe-caution');
        $p2 = sprintf(__('We are informing you of the closure of your deposit for booking <strong>%s</strong>.', 'pc-stripe-caution'), $ref);
        $msg_warn = __('A charge has been applied.', 'pc-stripe-caution');
        $label_debit = __('Debited amount:', 'pc-stripe-caution');
        $label_reason = __('Reason:', 'pc-stripe-caution');
        $p3 = __('The rest of your bank imprint (if applicable) has been automatically released.', 'pc-stripe-caution');
        $p4 = __('For any questions regarding this operation, please contact us.', 'pc-stripe-caution');
        $closing = sprintf(__('Sincerely,<br>The %s Team', 'pc-stripe-caution'), self::get_company_name());

        $body = "<p>$p1</p>
                 <p>$p2</p>
                 
                 <div style='background: #fff7ed; border-left: 4px solid #ea580c; padding: 15px; margin: 20px 0;'>
                    <p style='margin: 0; color: #9a3412; font-size: 16px;'><strong>$msg_warn</strong></p>
                    <ul style='margin: 10px 0 0 20px; color: #431407;'>
                        <li style='margin-bottom: 5px;'><strong>$label_debit</strong> $amount €</li>
                        <li><strong>$label_reason</strong> " . esc_html($motif) . "</li>
                    </ul>
                 </div>
                 
                 <p>$p3</p>
                 <p>$p4</p>
                 <p>$closing</p>";

        self::send($email, $subject, $body);
    }

    // --- EMAILS ADMIN ---

    public static function send_admin_card_saved(string $ref, string $email): void
    {
        $admin_email = get_option('admin_email');
        $link = admin_url('admin.php?page=pc-stripe-caution');

        $subject = sprintf(__('[Admin] Card saved - %s', 'pc-stripe-caution'), $ref);
        $title = __('Action required: Card saved', 'pc-stripe-caution');
        $p1 = sprintf(__('The customer (%s) has saved their card for booking <strong>%s</strong>.', 'pc-stripe-caution'), $email, $ref);
        $p2 = __('You can now go to the administration to <strong>Take the Deposit</strong> (Hold).', 'pc-stripe-caution');
        $btn_text = __('Go to Admin', 'pc-stripe-caution');

        $body = "<h3 style='color: #d97706;'>$title</h3>
                 <p>$p1</p>
                 <p>$p2</p>
                 <p><a href='$link' style='background: #2563eb; color: #fff; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>$btn_text</a></p>";

        self::send($admin_email, $subject, $body);
    }

    public static function send_admin_reminder_release(string $ref, string $date_depart): void
    {
        $admin_email = get_option('admin_email');

        $subject = sprintf(__('[Admin] Release Reminder - %s', 'pc-stripe-caution'), $ref);
        $title = __('Reminder: Release Tomorrow', 'pc-stripe-caution');
        $p1 = sprintf(__('The deposit for booking <strong>%s</strong> (Departure: %s) must be released tomorrow (D+7).', 'pc-stripe-caution'), $ref, $date_depart);
        $p2 = __('If you do nothing, the system will attempt to release it automatically.', 'pc-stripe-caution');
        $p3 = __('If you need to make a charge, now is the time!', 'pc-stripe-caution');

        $body = "<h3 style='color: #16a34a;'>$title</h3>
                 <p>$p1</p>
                 <p>$p2</p>
                 <p>$p3</p>";

        self::send($admin_email, $subject, $body);
    }
}
