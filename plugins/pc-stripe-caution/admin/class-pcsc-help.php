<?php
if (!defined('ABSPATH')) exit;

class PCSC_Help
{
    public static function init(): void
    {
        add_submenu_page(
            'pc-stripe-caution',
            __('Guide & Help', 'pc-stripe-caution'),
            __('Guide & Help', 'pc-stripe-caution'),
            'manage_options',
            'pc-stripe-help',
            [__CLASS__, 'render_page']
        );
    }

    public static function render_page(): void
    {
        $webhook_url = home_url('/wp-json/pcsc/v1/stripe/webhook');
?>
        <div class="wrap">
            <h1><?php _e('User Guide - PC Stripe Caution', 'pc-stripe-caution'); ?></h1>

            <div style="background: white; padding: 20px; border: 1px solid #ccd0d4; margin-top: 20px; max-width: 1000px;">

                <nav style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                    <a href="#setup" style="margin-right: 15px; font-weight: bold; text-decoration: none;">1. <?php _e('Stripe Configuration', 'pc-stripe-caution'); ?></a>
                    <a href="#workflow" style="margin-right: 15px; font-weight: bold; text-decoration: none;">2. <?php _e('Workflow', 'pc-stripe-caution'); ?></a>
                    <a href="#pages" style="margin-right: 15px; font-weight: bold; text-decoration: none;">3. <?php _e('Pages & Mobile', 'pc-stripe-caution'); ?></a>
                    <a href="#faq" style="font-weight: bold; text-decoration: none;">4. <?php _e('FAQ', 'pc-stripe-caution'); ?></a>
                </nav>

                <h2 id="setup" style="color: #2271b1;">1. <?php _e('Stripe Configuration (Required)', 'pc-stripe-caution'); ?></h2>
                <p><?php _e('To make the plugin work, you must connect your Stripe account. Go to <em>Settings > Deposit Settings</em>.', 'pc-stripe-caution'); ?></p>

                <table class="widefat striped" style="margin-bottom: 20px;">
                    <thead>
                        <tr>
                            <th><?php _e('Item', 'pc-stripe-caution'); ?></th>
                            <th><?php _e('Instructions', 'pc-stripe-caution'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><?php _e('Secret Key', 'pc-stripe-caution'); ?></strong></td>
                            <td><?php _e('Find it in your Stripe Dashboard under <em>Developers > API Keys</em>. It starts with <code>sk_live_...</code> (or <code>sk_test_...</code>).', 'pc-stripe-caution'); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Webhook Secret', 'pc-stripe-caution'); ?></strong></td>
                            <td>
                                <?php _e('Crucial for security and updates.', 'pc-stripe-caution'); ?><br>
                                1. <?php _e('Go to Stripe > <em>Developers > Webhooks</em>.', 'pc-stripe-caution'); ?><br>
                                2. <?php _e('Click "Add endpoint".', 'pc-stripe-caution'); ?><br>
                                3. <?php _e('Endpoint URL:', 'pc-stripe-caution'); ?> <code><?php echo esc_url($webhook_url); ?></code><br>
                                4. <?php _e('Events to listen for: select <code>checkout.session.completed</code>.', 'pc-stripe-caution'); ?><br>
                                5. <?php _e('Copy the Signing Secret (starts with <code>whsec_...</code>) and paste it into the plugin settings.', 'pc-stripe-caution'); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <hr>

                <h2 id="workflow" style="color: #2271b1;">2. <?php _e('Deposit Lifecycle', 'pc-stripe-caution'); ?></h2>
                <ol style="line-height: 1.6;">
                    <li>
                        <strong><?php _e('Case Creation:', 'pc-stripe-caution'); ?></strong>
                        <?php _e('Create a new deposit case by entering the Reference (e.g., Booking-123), Customer Email, and Amount.', 'pc-stripe-caution'); ?>
                        <br><em><?php _e('Status: Draft.', 'pc-stripe-caution'); ?></em>
                    </li>
                    <li>
                        <strong><?php _e('Send Link:', 'pc-stripe-caution'); ?></strong>
                        <?php _e('Click "Generate Link" then "Send Email". The customer receives a secure link to save their card.', 'pc-stripe-caution'); ?>
                        <br><em><?php _e('Status: Link Sent > Card Saved (once done by customer).', 'pc-stripe-caution'); ?></em>
                    </li>
                    <li>
                        <strong><?php _e('Take Deposit (Hold):', 'pc-stripe-caution'); ?></strong>
                        <?php _e('A few days before arrival (or on D-Day), go to the case and click <strong>"Take Deposit"</strong>.', 'pc-stripe-caution'); ?>
                        <br><?php _e('This blocks the funds on the customer card (without debiting).', 'pc-stripe-caution'); ?>
                        <br><em><?php _e('Status: Hold Active.', 'pc-stripe-caution'); ?></em>
                    </li>
                    <li>
                        <strong><?php _e('Release or Charge:', 'pc-stripe-caution'); ?></strong>
                        <ul>
                            <li><strong><?php _e('All good:', 'pc-stripe-caution'); ?></strong> <?php _e('Click "Release" after departure (or let the PRO auto-release do it).', 'pc-stripe-caution'); ?></li>
                            <li><strong><?php _e('Damages:', 'pc-stripe-caution'); ?></strong> <?php _e('Click "Charge", enter the amount to capture. The rest is released automatically.', 'pc-stripe-caution'); ?></li>
                        </ul>
                    </li>
                </ol>

                <hr>

                <h2 id="pages" style="color: #2271b1;">3. <?php _e('Pages & Mobile Dashboard', 'pc-stripe-caution'); ?></h2>
                <p><?php _e('Use these shortcodes to set up your specific pages.', 'pc-stripe-caution'); ?></p>

                <div style="background:#f0f6fc; border-left:4px solid #72aee6; padding:15px; margin-bottom:20px;">
                    <h3 style="margin-top:0;">📱 <?php _e('Mobile Dashboard (PRO)', 'pc-stripe-caution'); ?></h3>
                    <p><?php _e('Turn your website into a mobile app to manage deposits on the go.', 'pc-stripe-caution'); ?></p>
                    <ol>
                        <li>
                            <?php _e('Create a private WordPress Page and add this shortcode:', 'pc-stripe-caution'); ?><br>
                            <code>[pc_deposit_dashboard]</code>
                        </li>
                        <li>
                            <strong><?php _e('Create the App Shortcut:', 'pc-stripe-caution'); ?></strong><br>
                            - <em>iPhone (Safari):</em> <?php _e('Open the page, tap the "Share" button (square with arrow) > "Add to Home Screen".', 'pc-stripe-caution'); ?><br>
                            - <em>Android (Chrome):</em> <?php _e('Open the page, tap the menu (3 dots) > "Add to Home Screen".', 'pc-stripe-caution'); ?>
                        </li>
                        <li>
                            <strong><?php _e('Avoid Login screens:', 'pc-stripe-caution'); ?></strong><br>
                            <?php _e('When logging in, check the <strong>"Remember Me"</strong> box. Let your browser save your password so you can access the dashboard instantly next time.', 'pc-stripe-caution'); ?>
                        </li>
                    </ol>
                </div>

                <div style="background:#f0f6fc; border-left:4px solid #10b981; padding:15px; margin-bottom:20px;">
                    <h3 style="margin-top:0;">✨ <?php _e('Custom Thank You Page', 'pc-stripe-caution'); ?></h3>
                    <p><?php _e('By default, the plugin uses a basic success page. To use your own design:', 'pc-stripe-caution'); ?></p>
                    <ol>
                        <li><?php _e('Create a new Page (e.g., "Deposit Success").', 'pc-stripe-caution'); ?></li>
                        <li><?php _e('Add the confirmation message shortcode:', 'pc-stripe-caution'); ?> <code>[pc_caution_merci]</code></li>
                        <li>
                            <?php _e('Go to <em>Settings > Deposit Settings</em> and select this page in the <strong>"Success Page"</strong> option (PRO).', 'pc-stripe-caution'); ?>
                        </li>
                    </ol>
                </div>

                <hr>

                <h2 id="faq" style="color: #2271b1;">4. <?php _e('FAQ & Limitations', 'pc-stripe-caution'); ?></h2>

                <details style="margin-bottom: 10px; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">
                    <summary style="cursor: pointer; font-weight: bold;"><?php _e('How long does the hold last? (7-day limit)', 'pc-stripe-caution'); ?></summary>
                    <p style="margin-top: 10px;">
                        <?php _e('Stripe limits a hold (authorization) to <strong>7 days</strong>.', 'pc-stripe-caution'); ?> <br>
                        <strong><?php _e('Plugin Solution:', 'pc-stripe-caution'); ?></strong> <?php _e('The "Rotation" system (PRO) automatically renews the hold every 6 days for long stays, ensuring you remain secured.', 'pc-stripe-caution'); ?>
                    </p>
                </details>

                <details style="margin-bottom: 10px; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">
                    <summary style="cursor: pointer; font-weight: bold;"><?php _e('Is the money debited from the customer\'s account?', 'pc-stripe-caution'); ?></summary>
                    <p style="margin-top: 10px;">
                        <?php _e('No. When taking the imprint (Hold), the money is only "blocked" (impacts the bank ceiling). It is only debited if you click "Charge".', 'pc-stripe-caution'); ?>
                    </p>
                </details>

                <details style="margin-bottom: 10px; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">
                    <summary style="cursor: pointer; font-weight: bold;"><?php _e('Error "Invalid Signature"?', 'pc-stripe-caution'); ?></summary>
                    <p style="margin-top: 10px;">
                        <?php _e('Check that you have copied the Webhook Secret (<code>whsec_...</code>) and not the Public Key in the settings. Also verify that the Webhook URL in Stripe matches exactly the one provided above.', 'pc-stripe-caution'); ?>
                    </p>
                </details>

                <?php if (!defined('PCSC_IS_PRO') || !PCSC_IS_PRO): ?>
                    <div style="margin-top: 30px; background: linear-gradient(90deg, #4f46e5, #9333ea); color: white; padding: 15px; border-radius: 8px;">
                        <h3>🚀 <?php _e('Upgrade to PRO', 'pc-stripe-caution'); ?></h3>
                        <ul style="list-style: disc; margin-left: 20px;">
                            <li><?php _e('Unlimited deposits (vs 3/month)', 'pc-stripe-caution'); ?></li>
                            <li><?php _e('Automatic rotation (for stays > 7 days)', 'pc-stripe-caution'); ?></li>
                            <li><?php _e('Customizable emails', 'pc-stripe-caution'); ?></li>
                            <li><?php _e('Auto-release after departure', 'pc-stripe-caution'); ?></li>
                            <li><?php _e('Mobile Dashboard', 'pc-stripe-caution'); ?></li>
                        </ul>
                    </div>
                <?php endif; ?>

                <div style="margin-top: 40px; border-top: 1px solid #ddd; padding-top: 20px; text-align: center; color: #666; font-size: 13px;">
                    <p>
                        <?php _e('Plugin designed by', 'pc-stripe-caution'); ?> <strong>OS Web Solutions</strong>.<br>
                        <?php _e('For any specific questions or support, please contact us at:', 'pc-stripe-caution'); ?>
                        <a href="mailto:info@oswebsolutions.com" style="color:#2271b1; text-decoration:none;">info@oswebsolutions.com</a>
                    </p>
                </div>

            </div>
        </div>
<?php
    }
}
