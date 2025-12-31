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
    }

    public static function render_field_text(array $args): void
    {
        $options = get_option('pcsc_settings') ?: [];
        $val = isset($options[$args['label_for']]) ? $options[$args['label_for']] : ($args['default'] ?? '');
        echo '<input type="text" id="' . esc_attr($args['label_for']) . '" name="pcsc_settings[' . esc_attr($args['label_for']) . ']" value="' . esc_attr($val) . '" class="regular-text">';
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
}
