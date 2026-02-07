<?php

/**
 * Template Name: PC Reservation App Shell
 * Description: Interface "Full Screen" pour l'Espace Propriétaire.
 */

if (!defined('ABSPATH')) exit;

// 1. GESTION DE LA SÉCURITÉ & LOGIN
$error = '';

// Si POST login soumis
if (isset($_POST['pc_app_login'], $_POST['pc_username'], $_POST['pc_password'])) {
    if (wp_verify_nonce($_POST['pc_app_login'], 'pc_app_login_action')) {
        $creds = [
            'user_login'    => sanitize_text_field($_POST['pc_username']),
            'user_password' => $_POST['pc_password'],
            'remember'      => true
        ];
        $user = wp_signon($creds, is_ssl());
        if (is_wp_error($user)) {
            $error = 'Identifiants incorrects.';
        } else {
            wp_safe_redirect($_SERVER['REQUEST_URI']);
            exit;
        }
    }
}

// Vérification accès
$is_logged = is_user_logged_in();
// TODO: Affiner les capabilities plus tard
$can_access = $is_logged && (current_user_can('administrator') || current_user_can('editor') || current_user_can('manage_options'));

// Déconnexion
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    wp_logout();
    wp_safe_redirect(remove_query_arg('action'));
    exit;
}

// 2. LOGIQUE LOGO
$logo_url = '';
if (function_exists('get_field')) {
    // Priorité 1 : Logo spécifique Dashboard
    $logo_url = get_field('pc_dashboard_logo', 'option');
    // Priorité 2 : Logo général du site
    if (empty($logo_url)) {
        $logo_url = get_field('pc_general_logo', 'option');
    }
}

// 3. CHARGEMENT DES ASSETS
do_action('pc_resa_app_enqueue_assets');

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Espace Propriétaire - <?php bloginfo('name'); ?></title>

    <?php wp_head(); ?>

    <style>
        /* RESET & VARIABLES */
        :root {
            --pc-sidebar-width: 260px;
            --pc-sidebar-width-collapsed: 80px;
            /* Largeur réduite */
            --pc-app-bg: #f3f4f6;
            --pc-primary: #4f46e5;
            --pc-text: #334155;
            --pc-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body,
        html {
            margin: 0;
            padding: 0;
            height: 100%;
            background-color: var(--pc-app-bg);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            overflow: hidden;
            /* Empêche le scroll global */
        }

        #wpadminbar {
            display: none !important;
        }

        html {
            margin-top: 0 !important;
        }

        header,
        footer,
        .site-header,
        .site-footer {
            display: none !important;
        }

        /* LOGIN SCREEN */
        .pc-login-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .pc-login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 3rem;
            border-radius: 24px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            text-align: center;
        }

        .pc-login-logo {
            max-height: 60px;
            margin-bottom: 2rem;
            object-fit: contain;
        }

        .pc-field-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }

        .pc-field-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #475569;
            font-size: 0.9rem;
        }

        .pc-input {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            box-sizing: border-box;
            transition: var(--pc-transition);
        }

        .pc-input:focus {
            border-color: var(--pc-primary);
            outline: none;
        }

        .pc-btn-login {
            width: 100%;
            padding: 0.9rem;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .pc-btn-login:hover {
            transform: translateY(-2px);
        }

        .pc-login-error {
            color: #dc2626;
            background: #fee2e2;
            padding: 0.8rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        /* DASHBOARD LAYOUT */
        .pc-app-container {
            display: flex;
            height: 100vh;
            width: 100vw;
        }

        /* SIDEBAR */
        .pc-app-sidebar {
            width: var(--pc-sidebar-width);
            background: #ffffff;
            border-right: 1px solid #e5e7eb;
            display: flex;
            flex-direction: column;
            z-index: 20;
            transition: width 0.3s ease;
            position: relative;
            flex-shrink: 0;
            /* Empêche l'écrasement */
        }

        /* Mode Rétracté (Collapsed) */
        .pc-app-sidebar.collapsed {
            width: var(--pc-sidebar-width-collapsed);
        }

        /* HEADER SIDEBAR */
        .pc-sidebar-header {
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            /* Centré pour le mode collapsed */
            padding: 0 1.5rem;
            border-bottom: 1px solid #f3f4f6;
            transition: var(--pc-transition);
        }

        .pc-sidebar-logo-img {
            max-height: 40px;
            max-width: 100%;
            transition: var(--pc-transition);
        }

        /* En mode collapsed, on réduit le logo ou on le cache si trop large */
        .pc-app-sidebar.collapsed .pc-sidebar-logo-img {
            max-height: 30px;
            max-width: 40px;
            /* Force un aspect icône */
            object-fit: contain;
        }

        /* NAVIGATION */
        .pc-sidebar-nav {
            padding: 1.5rem 1rem;
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .pc-nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0.9rem 1rem;
            margin-bottom: 0.5rem;
            color: #64748b;
            text-decoration: none;
            font-weight: 500;
            border-radius: 12px;
            transition: var(--pc-transition);
            cursor: pointer;
            white-space: nowrap;
            /* Important pour ne pas casser le texte */
        }

        .pc-nav-item:hover,
        .pc-nav-item.active {
            background: #e0e7ff;
            color: var(--pc-primary);
        }

        .pc-nav-icon {
            font-size: 1.4rem;
            min-width: 1.4rem;
            /* Fixe la largeur pour l'alignement */
            text-align: center;
        }

        /* Masquer le texte en mode collapsed */
        .pc-app-sidebar.collapsed .pc-nav-label {
            opacity: 0;
            width: 0;
            display: none;
        }

        /* Centrer les icônes en mode collapsed */
        .pc-app-sidebar.collapsed .pc-nav-item {
            justify-content: center;
            padding: 0.9rem 0;
        }

        /* FOOTER SIDEBAR */
        .pc-sidebar-footer {
            padding: 1.5rem;
            border-top: 1px solid #f3f4f6;
        }

        .pc-user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            color: #334155;
            overflow: hidden;
        }

        .pc-user-avatar {
            border-radius: 50%;
            width: 36px;
            height: 36px;
            flex-shrink: 0;
        }

        /* Conteneur pour le texte (Nom + Lien) */
        .pc-user-details {
            display: flex;
            flex-direction: column;
            white-space: nowrap;
            transition: opacity 0.2s;
        }

        /* Quand replié : on cache tout le bloc texte et on centre l'avatar */
        .pc-app-sidebar.collapsed .pc-user-details {
            display: none;
            opacity: 0;
        }

        .pc-app-sidebar.collapsed .pc-sidebar-footer {
            padding: 1.5rem 0;
            display: flex;
            justify-content: center;
        }

        /* BOUTON TOGGLE (Flèche) */
        .pc-sidebar-toggle-btn {
            position: absolute;
            bottom: 90px;
            /* ✅ Remonté pour ne pas chevaucher le footer */
            right: -12px;
            width: 24px;
            height: 24px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            z-index: 30;
            color: #64748b;
            font-size: 12px;
            transition: var(--pc-transition);
        }

        .pc-sidebar-toggle-btn:hover {
            color: var(--pc-primary);
            border-color: var(--pc-primary);
        }

        .pc-app-sidebar.collapsed .pc-sidebar-toggle-btn {
            transform: rotate(180deg);
        }

        /* MAIN CONTENT */
        .pc-app-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
            background: white;
            /* Fond contenu */
        }

        /* Zones de contenu */
        .pc-view-section {
            display: none;
            /* Caché par défaut */
            height: 100%;
            overflow-y: auto;
            /* Scroll interne */
            padding: 2rem;
            box-sizing: border-box;
            background: var(--pc-app-bg);
        }

        .pc-view-section.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Fix CSS spécifiques pour le mode app */
        .pc-resa-dashboard-wrapper {
            margin: 0 !important;
            max-width: 1400px;
            margin: 0 auto !important;
        }

        .pc-resa-filters {
            margin-top: 0 !important;
        }

        /* Mobile */
        .pc-mobile-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 100;
            background: white;
            padding: 0.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .pc-app-sidebar {
                position: fixed;
                transform: translateX(-100%);
                height: 100%;
                width: 260px !important;
            }

            /* Force width on mobile */
            .pc-app-sidebar.open {
                transform: translateX(0);
                box-shadow: 10px 0 30px rgba(0, 0, 0, 0.2);
            }

            .pc-mobile-toggle {
                display: block;
            }

            .pc-sidebar-toggle-btn {
                display: none;
            }

            /* Pas de collapse en mobile, juste open/close */
            .pc-view-section {
                padding: 1rem;
            }
        }

        /* === CORRECTIF Z-INDEX MODALES (IMPÉRATIF) === */

        /* 1. Modale Détail Réservation (Niveau 1) */
        /* On la force à 10 000 pour être au-dessus du dashboard */
        .pc-resa-modal-overlay,
        .pc-resa-modal,
        #pc-resa-modal-detail {
            z-index: 10000 !important;
        }

        /* 2. Modale Messagerie (Niveau 2 - SUPERIOR) */
        /* On la force à 9 millions pour être SÛR qu'elle passe au-dessus de tout */
        #pc-messaging-modal {
            z-index: 9999999 !important;
            position: fixed !important;
            /* Sécurité pour le positionnement */
        }

        /* On s'assure que le fond gris de la messagerie couvre bien tout */
        #pc-messaging-modal .pc-messaging-modal-backdrop {
            z-index: 1 !important;
        }

        #pc-messaging-modal .pc-messaging-modal-dialog {
            z-index: 10 !important;
        }
    </style>
</head>

<body>

    <?php if (!$can_access): ?>
        <div class="pc-login-wrapper">
            <div class="pc-login-card">
                <?php if ($logo_url): ?>
                    <img src="<?php echo esc_url($logo_url); ?>" class="pc-login-logo" alt="Logo">
                <?php else: ?>
                    <h2 style="color:#4f46e5; margin-bottom:2rem;">Espace Propriétaire</h2>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="pc-login-error"><?php echo esc_html($error); ?></div>
                <?php endif; ?>

                <form method="post">
                    <div class="pc-field-group">
                        <label>Identifiant / Email</label>
                        <input type="text" name="pc_username" class="pc-input" required autofocus>
                    </div>
                    <div class="pc-field-group">
                        <label>Mot de passe</label>
                        <input type="password" name="pc_password" class="pc-input" required>
                    </div>
                    <?php wp_nonce_field('pc_app_login_action', 'pc_app_login'); ?>
                    <button type="submit" class="pc-btn-login">Se connecter</button>
                </form>
                <p style="margin-top:1.5rem; font-size:0.85rem; color:#94a3b8;">
                    <a href="<?php echo esc_url(wp_lostpassword_url()); ?>" style="color:inherit;">Mot de passe oublié ?</a>
                </p>
            </div>
        </div>

    <?php else: ?>
        <div class="pc-app-container">

            <button class="pc-mobile-toggle" onclick="toggleMobileMenu()">☰</button>

            <aside class="pc-app-sidebar" id="pcSidebar">
                <button class="pc-sidebar-toggle-btn" onclick="toggleSidebar()" title="Réduire/Agrandir">❮</button>

                <div class="pc-sidebar-header">
                    <?php if ($logo_url): ?>
                        <img src="<?php echo esc_url($logo_url); ?>" class="pc-sidebar-logo-img" alt="Logo">
                    <?php else: ?>
                        <span class="pc-nav-icon" style="color:var(--pc-primary);">🏠</span>
                    <?php endif; ?>
                </div>

                <nav class="pc-sidebar-nav">
                    <a href="#dashboard" class="pc-nav-item active" onclick="switchTab('dashboard')">
                        <span class="pc-nav-icon">📊</span>
                        <span class="pc-nav-label">Tableau de bord</span>
                    </a>
                    <a href="#calendar" class="pc-nav-item" onclick="switchTab('calendar')">
                        <span class="pc-nav-icon">📅</span>
                        <span class="pc-nav-label">Calendrier</span>
                    </a>
                </nav>

                <div class="pc-sidebar-footer">
                    <div class="pc-user-info">
                        <?php echo get_avatar(get_current_user_id(), 36, '', '', ['class' => 'pc-user-avatar']); ?>

                        <div class="pc-user-details">
                            <span class="pc-user-name"><?php echo wp_get_current_user()->display_name; ?></span>
                            <a href="?action=logout" class="pc-logout-link" style="font-size:0.8rem; color:#94a3b8; text-decoration:none; display:block;">Déconnexion</a>
                        </div>
                    </div>
                </div>
            </aside>

            <main class="pc-app-main">
                <div id="view-dashboard" class="pc-view-section active">
                    <?php echo do_shortcode('[pc_resa_dashboard]'); ?>
                </div>

                <div id="view-calendar" class="pc-view-section">
                    <?php echo do_shortcode('[pc_dashboard_calendar]'); ?>
                </div>
            </main>
        </div>

        <script>
            // 1. GESTION DES ONGLETS
            function switchTab(tabId) {
                document.querySelectorAll('.pc-nav-item').forEach(el => el.classList.remove('active'));
                const activeLink = document.querySelector(`a[href="#${tabId}"]`);
                if (activeLink) activeLink.classList.add('active');

                document.querySelectorAll('.pc-view-section').forEach(el => el.classList.remove('active'));
                document.getElementById('view-' + tabId).classList.add('active');

                // Close sidebar on mobile
                document.getElementById('pcSidebar').classList.remove('open');

                // Important pour le rendu des calendriers (taille)
                setTimeout(() => {
                    window.dispatchEvent(new Event('resize'));
                }, 100);
            }

            // 2. GESTION DU MENU RÉTRACTABLE (Avec mémoire)
            function toggleSidebar() {
                const sidebar = document.getElementById('pcSidebar');
                sidebar.classList.toggle('collapsed');

                // Sauvegarde de l'état
                const isCollapsed = sidebar.classList.contains('collapsed');
                localStorage.setItem('pc_sidebar_collapsed', isCollapsed);

                // Déclencher resize pour adapter les graphiques/calendriers si besoin
                setTimeout(() => {
                    window.dispatchEvent(new Event('resize'));
                }, 300);
            }

            // 3. GESTION MOBILE
            function toggleMobileMenu() {
                document.getElementById('pcSidebar').classList.toggle('open');
            }

            // 4. INITIALISATION AU CHARGEMENT
            window.addEventListener('load', () => {
                // Restaurer l'état du sidebar
                const savedState = localStorage.getItem('pc_sidebar_collapsed');
                if (savedState === 'true') {
                    document.getElementById('pcSidebar').classList.add('collapsed');
                }

                // Restaurer l'onglet actif via le hash URL
                if (window.location.hash === '#calendar') {
                    switchTab('calendar');
                }
            });
        </script>
    <?php endif; ?>

    <?php wp_footer(); ?>
</body>

</html>