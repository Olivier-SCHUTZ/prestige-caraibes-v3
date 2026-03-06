<?php

/**
 * Plugin Name: PC Maintenance Pro (MU)
 * Description: Maintenance 503 ultra-légère avec bypass intelligents, indicateur visuel dans l'admin et page d'attente professionnelle.
 * Author: Prestige Caraïbes
 * Version: 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

final class PC_Maintenance_MU
{
    private const FLAG_BASENAME = '.pc-maintenance-on';
    private const NONCE_ACTION  = 'pc_maintenance_toggle';
    private const CAPABILITY    = 'manage_options';

    public static function boot(): void
    {
        // Démarrage précoce pour intercepter le trafic
        $priority = defined('WP_INSTALLING') && WP_INSTALLING ? 1 : 0;
        add_action('init', [__CLASS__, 'maybe_serve_maintenance'], $priority);

        // Admin UX
        if (is_admin()) {
            add_action('admin_menu', [__CLASS__, 'register_admin_page']);
            add_action('admin_post_pc_maintenance_toggle', [__CLASS__, 'handle_toggle']);
            add_action('admin_notices', [__CLASS__, 'admin_notices']);
            add_action('admin_head', [__CLASS__, 'admin_css']);
        }
        // Barre d'admin front et back
        add_action('admin_bar_menu', [__CLASS__, 'admin_bar_indicator'], 100);
        // CSS barre d'admin front
        add_action('wp_head', [__CLASS__, 'admin_css']);
    }

    private static function flag_path(): string
    {
        // Utilisation de WP_CONTENT_DIR pour éviter les soucis de permissions à la racine
        return trailingslashit(WP_CONTENT_DIR) . self::FLAG_BASENAME;
    }

    public static function is_enabled(): bool
    {
        return file_exists(self::flag_path());
    }

    /**
     * C'est ici que le design de la page est généré.
     */
    public static function maybe_serve_maintenance(): void
    {
        if (!self::is_enabled()) return;

        // BYPASS : WP-CLI, Admins connectés, Login, AJAX, REST, CRON
        if (
            (defined('WP_CLI') && WP_CLI) ||
            current_user_can(self::CAPABILITY) ||
            is_admin() ||
            (defined('DOING_AJAX') && DOING_AJAX) ||
            (defined('DOING_CRON') && DOING_CRON) ||
            str_contains($_SERVER['REQUEST_URI'] ?? '', '/wp-json/') ||
            str_contains($_SERVER['REQUEST_URI'] ?? '', 'wp-login.php')
        ) {
            return;
        }

        // --- Headers SEO 503 ---
        status_header(503);
        header('Retry-After: 3600');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Expires: 0');

        // Icône SVG (engrenages) intégrée pour éviter une requête HTTP
        $svg_icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M20.944 12.979c-.489 4.509-4.306 8.021-8.944 8.021-2.698 0-5.112-1.194-6.763-3.075l1.245-1.633c1.283 1.645 3.276 2.708 5.518 2.708 3.526 0 6.444-2.624 6.923-6.021h-16.923c.479-3.397 3.397-6.021 6.923-6.021 1.814 0 3.462.688 4.697 1.812l1.674-1.231c-1.659-1.673-3.966-2.706-6.514-2.706-4.745 0-8.641 3.667-8.984 8.313l10.985.038.075 4.167h10.088z"/></svg>';

        // --- Page HTML Professionnelle ---
?>
        <!doctype html>
        <html lang="fr">

        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Site en maintenance</title>
            <meta name="robots" content="noindex, nofollow">
            <style>
                :root {
                    --bg-color: #f0f4f8;
                    --text-color: #2d3748;
                    --card-bg: #ffffff;
                    --accent-color: #3182ce;
                }

                body {
                    margin: 0;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                    background: var(--bg-color);
                    color: var(--text-color);
                    height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                    box-sizing: border-box;
                }

                .m-card {
                    background: var(--card-bg);
                    padding: 40px 32px;
                    border-radius: 16px;
                    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
                    text-align: center;
                    max-width: 480px;
                    width: 100%;
                }

                .m-icon {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 64px;
                    height: 64px;
                    background: #ebf8ff;
                    color: var(--accent-color);
                    border-radius: 50%;
                    margin-bottom: 24px;
                }

                .m-icon svg {
                    width: 32px;
                    height: 32px;
                    animation: spin 8s linear infinite;
                }

                h1 {
                    margin: 0 0 16px;
                    font-size: 24px;
                    font-weight: 700;
                    color: #1a202c;
                }

                p {
                    margin: 0 0 24px;
                    font-size: 16px;
                    line-height: 1.6;
                    color: #4a5568;
                }

                .m-footer {
                    font-size: 13px;
                    color: #718096;
                    border-top: 1px solid #e2e8f0;
                    padding-top: 20px;
                    margin-top: 30px;
                }

                @keyframes spin {
                    100% {
                        transform: rotate(360deg);
                    }
                }
            </style>
        </head>

        <body>
            <div class="m-card">
                <div class="m-icon"><?php echo $svg_icon; ?></div>
                <h1>Nous peaufinons les détails</h1>
                <p>Notre site fait actuellement l'objet d'une mise à jour technique pour améliorer votre expérience.</p>
                <p>Nous faisons au plus vite. Merci de votre patience, nous serons de retour très rapidement !</p>
                <div class="m-footer">
                    Service temporairement indisponible (HTTP 503)
                </div>
            </div>
        </body>

        </html>
    <?php
        exit;
    }

    // --- GESTION ADMIN ---

    public static function handle_toggle(): void
    {
        check_admin_referer(self::NONCE_ACTION);
        if (!current_user_can(self::CAPABILITY)) wp_die('Accès refusé.');

        $enabled = self::is_enabled();
        if ($enabled) {
            @unlink(self::flag_path());
            $status = 'off';
        } else {
            @file_put_contents(self::flag_path(), "on\n");
            $status = 'on';
        }
        wp_safe_redirect(add_query_arg('m-status', $status, admin_url('options-general.php?page=pc-maintenance')));
        exit;
    }

    public static function admin_notices(): void
    {
        if (isset($_GET['m-status'])) {
            $class = ($_GET['m-status'] === 'on') ? 'notice-warning' : 'notice-success';
            $msg   = ($_GET['m-status'] === 'on') ? 'Le site est en MAINTENANCE.' : 'Le site est de nouveau EN LIGNE.';
            echo "<div class='notice $class is-dismissible'><p><strong>$msg</strong></p></div>";
        }
    }

    public static function admin_css(): void
    {
        if (!self::is_enabled()) return;
        // Met le bouton de la barre d'admin en rouge vif si actif
        echo '<style>#wp-admin-bar-pc-maint-on .ab-item { background-color: #e53e3e !important; color: white !important; font-weight: 600; }</style>';
    }

    public static function admin_bar_indicator(\WP_Admin_Bar $wp_admin_bar): void
    {
        if (!current_user_can(self::CAPABILITY) || !self::is_enabled()) return;

        $wp_admin_bar->add_node([
            'id'    => 'pc-maint-on',
            'title' => '⚠️ MAINTENANCE ACTIVE',
            'href'  => admin_url('options-general.php?page=pc-maintenance'),
            'meta'  => ['title' => 'Le site est caché aux visiteurs. Cliquez pour gérer.'],
        ]);
    }

    public static function register_admin_page(): void
    {
        add_options_page('PC Maintenance', 'PC Maintenance', self::CAPABILITY, 'pc-maintenance', [__CLASS__, 'render_admin_page']);
    }

    public static function render_admin_page(): void
    {
        $enabled = self::is_enabled();
        $url = wp_nonce_url(admin_url('admin-post.php?action=pc_maintenance_toggle'), self::NONCE_ACTION);
    ?>
        <div class="wrap">
            <h1>PC Maintenance Pro</h1>
            <div class="card" style="max-width: 600px; margin-top: 20px; padding: 20px;">
                <h2 style="margin-top:0;">État actuel :
                    <?php echo $enabled ? '<span class="dashicons dashicons-warning" style="color:#e53e3e"></span> <strong style="color:#e53e3e">ACTIVÉE</strong>' : '<span class="dashicons dashicons-yes" style="color:#38a169"></span> <strong style="color:#38a169">DÉSACTIVÉE</strong>'; ?>
                </h2>
                <p class="description">
                    Lorsque la maintenance est active, les visiteurs voient une page d'attente professionnelle (Code 503).<br>
                    En tant qu'administrateur connecté, <strong>vous pouvez toujours voir le site normalement.</strong>
                </p>
                <p style="margin-top: 25px;">
                    <a href="<?php echo esc_url($url); ?>" class="button button-large <?php echo $enabled ? 'button-secondary' : 'button-primary dashicons-before dashicons-lock'; ?>">
                        <?php echo $enabled ? 'Désactiver la maintenance (Mettre en ligne)' : 'Activer le mode maintenance'; ?>
                    </a>
                </p>
                <?php if ($enabled): ?>
                    <hr>
                    <p><small>Pour vérifier ce que voient vos visiteurs, ouvrez votre site dans une fenêtre de navigation privée.</small></p>
                <?php endif; ?>
            </div>
        </div>
<?php
    }
}

PC_Maintenance_MU::boot();
