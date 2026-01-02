<?php
if (!defined('ABSPATH')) exit;

class PCSC_Settings
{
    public static function init(): void
    {
        // Priorité 20 pour s'assurer que le menu parent (créé en priorité 10) existe déjà
        add_action('admin_menu', [__CLASS__, 'add_admin_menu'], 20);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    public static function add_admin_menu(): void
    {
        add_submenu_page(
            'pc-stripe-caution',
            __('Deposit Settings', 'pc-stripe-caution'),
            __('Settings', 'pc-stripe-caution'),
            'manage_options',
            'pc-stripe-settings',
            [__CLASS__, 'render_settings_page']
        );
    }

    public static function register_settings(): void
    {
        register_setting('pcsc_options_group', 'pcsc_settings');

        // --- SECTION 1 : CONFIGURATION GÉNÉRALE (LITE) ---
        add_settings_section(
            'pcsc_section_general',
            __('General & Stripe Configuration', 'pc-stripe-caution'),
            null,
            'pc-stripe-settings'
        );

        add_settings_field(
            'company_name',
            __('Company Name', 'pc-stripe-caution'),
            [__CLASS__, 'render_field_text'],
            'pc-stripe-settings',
            'pcsc_section_general',
            ['label_for' => 'company_name', 'default' => 'Prestige Caraïbes']
        );

        add_settings_field(
            'stripe_secret_key',
            __('Stripe Secret Key', 'pc-stripe-caution'),
            [__CLASS__, 'render_field_password'],
            'pc-stripe-settings',
            'pcsc_section_general',
            ['label_for' => 'stripe_secret_key']
        );

        add_settings_field(
            'stripe_webhook_secret',
            __('Webhook Secret (Signature)', 'pc-stripe-caution'),
            [__CLASS__, 'render_field_password'],
            'pc-stripe-settings',
            'pcsc_section_general',
            ['label_for' => 'stripe_webhook_secret', 'desc' => __('Starts with whsec_... (See Stripe > Developers > Webhooks)', 'pc-stripe-caution')]
        );

        add_settings_field(
            'stripe_currency',
            __('Currency', 'pc-stripe-caution'),
            [__CLASS__, 'render_field_text'],
            'pc-stripe-settings',
            'pcsc_section_general',
            ['label_for' => 'stripe_currency', 'default' => 'EUR', 'desc' => 'Ex: EUR, USD, CAD']
        );

        // --- SECTION 2 : OPTIONS AVANCÉES (PRO) ---
        // C'est ici qu'on ajoute la séparation visuelle
        add_settings_section(
            'pcsc_section_pro_options',
            '', // Pas de titre standard WP, on utilise le callback pour faire joli
            [__CLASS__, 'render_section_pro_banner'], // <-- Callback de la bannière
            'pc-stripe-settings'
        );

        // Note : On change le 5ème argument de 'pcsc_section_general' à 'pcsc_section_pro_options'
        add_settings_field(
            'release_delay_days',
            __('Auto Release Delay', 'pc-stripe-caution'),
            [__CLASS__, 'render_field_release_delay'],
            'pc-stripe-settings',
            'pcsc_section_pro_options', // <-- Nouvelle section
            ['label_for' => 'release_delay_days', 'is_pro' => true]
        );

        add_settings_field(
            'success_page_id',
            __('Success Page (Redirection)', 'pc-stripe-caution'),
            [__CLASS__, 'render_field_page_selector'],
            'pc-stripe-settings',
            'pcsc_section_pro_options', // <-- Nouvelle section
            ['label_for' => 'success_page_id', 'is_pro' => true]
        );

        // --- SECTION 3 : EMAILS (PRO) ---
        add_settings_section(
            'pcsc_section_emails',
            __('Email Customization', 'pc-stripe-caution') . ' <span class="pcsc-pro-badge">PRO</span>',
            [__CLASS__, 'render_section_emails_desc'],
            'pc-stripe-settings'
        );

        self::add_email_fields('email_setup', __('Link Sent to Customer', 'pc-stripe-caution'), true);
        self::add_email_fields('email_hold', __('Hold Confirmation', 'pc-stripe-caution'), true);
        self::add_email_fields('email_release', __('Release Confirmation', 'pc-stripe-caution'), true);
        self::add_email_fields('email_capture', __('Charge Notification', 'pc-stripe-caution'), true);

        // --- DIAGNOSTIC (Si PRO activé uniquement) ---
        if (defined('PCSC_IS_PRO') && PCSC_IS_PRO) {
            add_settings_section('pcsc_section_diag', __('Diagnostic & Tools (PRO)', 'pc-stripe-caution'), null, 'pc-stripe-settings');
            add_settings_field('diag_status', __('System Status', 'pc-stripe-caution'), [__CLASS__, 'render_diag_status'], 'pc-stripe-settings', 'pcsc_section_diag');
        }
    }

    public static function render_field_text(array $args): void
    {
        $options = get_option('pcsc_settings') ?: [];
        $val = isset($options[$args['label_for']]) ? $options[$args['label_for']] : ($args['default'] ?? '');

        $locked = self::is_locked($args);
        $disabled = $locked ? 'disabled' : '';
        $css_class = 'regular-text ' . ($locked ? 'pcsc-pro-lock' : '');

        echo '<input type="text" id="' . esc_attr($args['label_for']) . '" name="pcsc_settings[' . esc_attr($args['label_for']) . ']" value="' . esc_attr($val) . '" class="' . $css_class . '" ' . $disabled . '>';

        if ($locked) echo ' <span class="pcsc-lock-icon">🔒</span>';
        if (!empty($args['desc'])) echo '<p class="description">' . esc_html($args['desc']) . '</p>';
    }

    public static function render_field_password(array $args): void
    {
        $options = get_option('pcsc_settings') ?: [];
        $key = $args['label_for'];
        $val = isset($options[$key]) ? $options[$key] : '';

        $placeholder = '';
        if ($key === 'stripe_secret_key' && defined('PC_STRIPE_SECRET_KEY')) {
            $placeholder = __('Defined in wp-config.php (PC_STRIPE_SECRET_KEY)', 'pc-stripe-caution');
        } elseif ($key === 'stripe_webhook_secret' && defined('PC_STRIPE_WEBHOOK_SECRET')) {
            $placeholder = __('Defined in wp-config.php (PC_STRIPE_WEBHOOK_SECRET)', 'pc-stripe-caution');
        }

        echo '<input type="password" id="' . esc_attr($key) . '" name="pcsc_settings[' . esc_attr($key) . ']" value="' . esc_attr($val) . '" class="regular-text" placeholder="' . esc_attr($placeholder) . '">';
        if (!empty($args['desc'])) echo '<p class="description">' . esc_html($args['desc']) . '</p>';
    }

    public static function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) return;
?>
        <style>
            .pcsc-pro-lock {
                opacity: 0.6;
                pointer-events: none;
                /* Empêche le clic */
                background-color: #f9f9f9;
                position: relative;
            }

            .pcsc-lock-icon {
                font-size: 16px;
                margin-left: 5px;
                cursor: help;
            }

            .pcsc-pro-badge {
                background: #e5e7eb;
                color: #374151;
                font-size: 10px;
                padding: 2px 6px;
                border-radius: 4px;
                text-transform: uppercase;
                margin-left: 5px;
                vertical-align: middle;
                display: inline-block;
            }

            /* Bannière de séparation PRO */
            .pcsc-pro-header {
                background: linear-gradient(90deg, #4f46e5 0%, #9333ea 100%);
                color: #fff;
                padding: 12px 20px;
                border-radius: 8px;
                margin: 35px 0 20px 0;
                display: flex;
                align-items: center;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            }

            .pcsc-pro-header h3 {
                margin: 0 !important;
                color: #fff !important;
                font-size: 1.1em;
                display: flex;
                align-items: center;
            }

            .pcsc-pro-header .dashicons {
                margin-right: 10px;
                font-size: 20px;
                width: 20px;
                height: 20px;
            }
        </style>
        <div class="wrap">
            <h1><?php _e('Stripe Deposit Settings', 'pc-stripe-caution'); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('pcsc_options_group');
                do_settings_sections('pc-stripe-settings');
                submit_button(__('Save Changes', 'pc-stripe-caution'));
                ?>
            </form>
        </div>
<?php
    }

    public static function get_option(string $key, string $default = '')
    {
        $opts = get_option('pcsc_settings');
        return $opts[$key] ?? $default;
    }

    public static function render_field_page_selector(array $args): void
    {
        $options = get_option('pcsc_settings') ?: [];
        $val = isset($options[$args['label_for']]) ? $options[$args['label_for']] : 0;

        $locked = self::is_locked($args);
        // wp_dropdown_pages n'accepte pas directement 'disabled', on ruse avec du HTML pur si bloqué ou on l'enveloppe

        if ($locked) echo '<fieldset disabled class="pcsc-pro-lock" style="display:inline-block;">';

        wp_dropdown_pages([
            'name' => 'pcsc_settings[' . esc_attr($args['label_for']) . ']',
            'selected' => $val,
            'show_option_none' => __('Default (Plugin Page)', 'pc-stripe-caution'),
            'option_none_value' => 0
        ]);

        if ($locked) echo '</fieldset> <span class="pcsc-lock-icon" title="Version PRO requise">🔒</span>';

        echo '<p class="description">' . __('Select the page where the customer is redirected after a successful deposit.', 'pc-stripe-caution') . '</p>';
    }

    public static function render_diag_status(): void
    {
        $sk = self::get_option('stripe_secret_key');
        $wh = self::get_option('stripe_webhook_secret');
        $mail = get_option('admin_email');

        // Check API
        $api_ok = !empty($sk) && strpos($sk, 'sk_') === 0;
        $icon_api = $api_ok ? '✅' : '❌';

        // Check Webhook
        $wh_ok = !empty($wh) && strpos($wh, 'whsec_') === 0;
        $icon_wh = $wh_ok ? '✅' : '❌';

        echo '<div style="background:#fff; padding:15px; border:1px solid #ccc; max-width:600px;">';

        // Traduction des statuts API
        $status_api = $api_ok ? __('Key detected', 'pc-stripe-caution') : __('Missing/Invalid SK Key', 'pc-stripe-caution');
        echo '<p><strong>API Stripe :</strong> ' . $icon_api . ' ' . $status_api . '</p>';

        // Traduction des statuts Webhook
        $status_wh = $wh_ok ? __('Secret detected', 'pc-stripe-caution') : __('Missing Secret', 'pc-stripe-caution');
        echo '<p><strong>Webhook Secret :</strong> ' . $icon_wh . ' ' . $status_wh . '</p>';

        // Traduction du label Email
        echo '<p><strong>' . __('Admin Email :', 'pc-stripe-caution') . '</strong> ' . esc_html($mail) . '</p>';

        // Bouton et alerte JS traduits
        $alert_msg = esc_js(__('Diagnostic tool ready.', 'pc-stripe-caution'));
        $btn_text = __('Run Full Diagnostic', 'pc-stripe-caution');

        echo '<hr><button type="button" class="button" onclick="alert(\'' . $alert_msg . '\')">' . $btn_text . '</button>';
        echo '</div>';
    }

    public static function render_field_release_delay(array $args): void
    {
        $options = get_option('pcsc_settings') ?: [];
        $val = isset($options[$args['label_for']]) ? (int)$options[$args['label_for']] : 7;

        $locked = self::is_locked($args);
        $disabled = $locked ? 'disabled' : '';
        $style = $locked ? 'class="pcsc-pro-lock"' : '';

        echo '<select name="pcsc_settings[' . esc_attr($args['label_for']) . ']" id="' . esc_attr($args['label_for']) . '" ' . $disabled . ' ' . $style . '>';
        foreach ([1, 2, 3, 4, 5, 7, 10, 14] as $day) {
            $sel = ($val === $day) ? 'selected' : '';
            echo "<option value='$day' $sel>" . sprintf(__('D+%d after departure', 'pc-stripe-caution'), $day) . "</option>";
        }
        echo '</select>';

        if ($locked) echo ' <span class="pcsc-lock-icon" title="Version PRO requise">🔒</span>';
        echo '<p class="description">' . __('Automatically release the hold X days after departure date.', 'pc-stripe-caution') . '</p>';
    }

    public static function render_section_pro_banner(): void
    {
        echo '
        <div class="pcsc-pro-header">
            <h3><span class="dashicons dashicons-star-filled"></span> ' . __('Advanced Features (PRO)', 'pc-stripe-caution') . '</h3>
        </div>
        <p>' . __('Unlock automatic management and advanced customization.', 'pc-stripe-caution') . '</p>
        ';
    }

    public static function render_section_emails_desc(): void
    {
        echo '<p>' . __('Use the following placeholders in your emails: <code>{ref}</code>, <code>{amount}</code>, <code>{customer_name}</code> (if set), <code>{link}</code> (for setup), <code>{date}</code>, <code>{company}</code>.', 'pc-stripe-caution') . '</p>';
    }

    private static function add_email_fields(string $prefix, string $title, bool $is_pro = false): void
    {
        add_settings_field(
            $prefix . '_subject',
            $title . ' - ' . __('Subject', 'pc-stripe-caution'),
            [__CLASS__, 'render_field_text'], // On réutilise le champ texte standard
            'pc-stripe-settings',
            'pcsc_section_emails',
            ['label_for' => $prefix . '_subject', 'class' => 'pc-full-width', 'is_pro' => $is_pro]
        );

        add_settings_field(
            $prefix . '_body',
            $title . ' - ' . __('Content', 'pc-stripe-caution'),
            [__CLASS__, 'render_field_wysiwyg'],
            'pc-stripe-settings',
            'pcsc_section_emails',
            ['label_for' => $prefix . '_body', 'is_pro' => $is_pro]
        );
    }

    public static function render_field_wysiwyg(array $args): void
    {
        $options = get_option('pcsc_settings') ?: [];
        $key = $args['label_for'];
        $val = isset($options[$key]) ? $options[$key] : '';

        $locked = self::is_locked($args);

        if ($locked) {
            // Version Grisée LITE : Simple Textarea désactivé
            echo '<textarea disabled class="large-text code pcsc-pro-lock" rows="5" placeholder="Contenu HTML (Visible en PRO)...">' . esc_textarea($val) . '</textarea>';
            echo ' <span class="pcsc-lock-icon">🔒</span>';
        } else {
            // Version PRO : Éditeur complet
            $editor_args = [
                'textarea_name' => 'pcsc_settings[' . $key . ']',
                'textarea_rows' => 10,
                'media_buttons' => false,
                'teeny'         => true,
            ];
            wp_editor($val, $key . '_editor', $editor_args);
        }
    }

    private static function is_locked(array $args): bool
    {
        return !empty($args['is_pro']) && (!defined('PCSC_IS_PRO') || !PCSC_IS_PRO);
    }
}
