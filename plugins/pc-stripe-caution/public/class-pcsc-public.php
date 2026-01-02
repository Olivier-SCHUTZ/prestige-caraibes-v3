<?php
if (!defined('ABSPATH')) exit;

class PCSC_Public
{
    public static function init(): void
    {
        // On ne garde que le shortcode de la page de remerciement
        add_shortcode('pc_caution_merci', [__CLASS__, 'render_thankyou']);
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
}
