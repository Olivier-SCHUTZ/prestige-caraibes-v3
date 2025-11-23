<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue les assets du calendrier dashboard.
 */
function pc_dashboard_calendar_enqueue_assets()
{
    $version = defined('PC_RES_CORE_VERSION') ? PC_RES_CORE_VERSION : '1.0.0';

    wp_register_style(
        'pc-calendar',
        PC_RES_CORE_URL . 'assets/css/pc-calendar.css',
        [],
        $version
    );

    wp_register_script(
        'pc-calendar',
        PC_RES_CORE_URL . 'assets/js/pc-calendar.js',
        [],
        $version,
        true
    );

    wp_enqueue_style('pc-calendar');
    wp_enqueue_script('pc-calendar');

    $localized = [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('pc_dashboard_calendar'),
        'i18n'    => [
            'title'       => __('Dashboard Calendrier', 'pc-reservation-core'),
            'subtitle'    => __('Mois courant + 15 jours, données iCal + réservations.', 'pc-reservation-core'),
            'error'       => __('Impossible de charger le calendrier. Merci de réessayer.', 'pc-reservation-core'),
            'empty'       => __('Aucun logement actif pour cette période.', 'pc-reservation-core'),
            'loading'     => __('Chargement...', 'pc-reservation-core'),
            'modalTitle'  => __('Calendrier du logement', 'pc-reservation-core'),
        ],
    ];

    wp_localize_script('pc-calendar', 'pcCalendarData', $localized);
}

/**
 * Shortcode : [pc_dashboard_calendar]
 */
function pc_dashboard_calendar_shortcode($atts = [])
{
    $capability = apply_filters('pc_resa_manual_creation_capability', 'manage_options');
    $can_manage = current_user_can($capability);
    if (!is_user_logged_in() || !$can_manage) {
        return '<p>' . esc_html__('Vous devez être connecté pour accéder au calendrier.', 'pc-reservation-core') . '</p>';
    }

    pc_dashboard_calendar_enqueue_assets();

    $month = (int) current_time('n');
    $year  = (int) current_time('Y');

    ob_start();
?>
    <div class="pc-cal-shell" data-pc-calendar data-initial-month="<?php echo esc_attr($month); ?>" data-initial-year="<?php echo esc_attr($year); ?>">
        <div class="pc-cal-heading">
            <div class="pc-cal-heading__text">
                <p class="pc-cal-eyebrow"><?php echo esc_html__('Dashboard', 'pc-reservation-core'); ?></p>
                <h2 class="pc-cal-title"><?php echo esc_html__('Dashboard Calendrier', 'pc-reservation-core'); ?></h2>
                <p class="pc-cal-subtitle"><?php echo esc_html__('Période affichée : mois courant + 15 jours.', 'pc-reservation-core'); ?></p>
                <div class="pc-cal-legend">
                    <span class="pc-cal-legend__item"><span class="pc-cal-dot pc-cal-dot--reservation"></span><?php echo esc_html__('Réservation confirmée', 'pc-reservation-core'); ?></span>
                    <span class="pc-cal-legend__item"><span class="pc-cal-dot pc-cal-dot--manual"></span><?php echo esc_html__('Blocage manuel', 'pc-reservation-core'); ?></span>
                    <span class="pc-cal-legend__item"><span class="pc-cal-dot pc-cal-dot--ical"></span><?php echo esc_html__('iCal / import', 'pc-reservation-core'); ?></span>
                </div>
            </div>
            <div class="pc-cal-actions">
                <div class="pc-cal-select-group">
                    <label class="pc-cal-select-label" for="pc-cal-month"><?php echo esc_html__('Mois', 'pc-reservation-core'); ?></label>
                    <select id="pc-cal-month" class="pc-cal-select" data-pc-cal-month></select>
                </div>
                <div class="pc-cal-select-group">
                    <label class="pc-cal-select-label" for="pc-cal-year"><?php echo esc_html__('Année', 'pc-reservation-core'); ?></label>
                    <select id="pc-cal-year" class="pc-cal-select" data-pc-cal-year></select>
                </div>
                <button type="button"
                    class="pc-cal-today-btn"
                    data-pc-cal-today>
                    <?php echo esc_html__('Aujourd’hui', 'pc-reservation-core'); ?>
                </button>
            </div>
        </div>

        <div class="pc-cal-error" data-pc-cal-error role="alert" hidden></div>

        <div class="pc-cal-scroll" data-pc-cal-scroll>
            <div class="pc-cal-grid" data-pc-cal-grid></div>
        </div>

        <div class="pc-cal-modal" data-pc-cal-modal hidden>
            <div class="pc-cal-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="pc-cal-modal-title">
                <div class="pc-cal-modal__header">
                    <div>
                        <p class="pc-cal-eyebrow"><?php echo esc_html__('Planning logement', 'pc-reservation-core'); ?></p>
                        <h3 class="pc-cal-modal__title" id="pc-cal-modal-title" data-pc-cal-modal-title></h3>
                        <p class="pc-cal-modal__subtitle" data-pc-cal-modal-subtitle></p>
                    </div>
                    <button type="button" class="pc-cal-modal__close" data-pc-cal-close aria-label="<?php echo esc_attr__('Fermer la modale', 'pc-reservation-core'); ?>">&times;</button>
                </div>

                <!-- AJOUT : sélecteurs mois / année / aujourd’hui dans la modale -->
                <div class="pc-cal-modal-actions">
                    <div class="pc-cal-select-group">
                        <label class="pc-cal-select-label" for="pc-cal-modal-month"><?php echo esc_html__('Mois', 'pc-reservation-core'); ?></label>
                        <select id="pc-cal-modal-month" class="pc-cal-select" data-pc-cal-modal-month></select>
                    </div>
                    <div class="pc-cal-select-group">
                        <label class="pc-cal-select-label" for="pc-cal-modal-year"><?php echo esc_html__('Année', 'pc-reservation-core'); ?></label>
                        <select id="pc-cal-modal-year" class="pc-cal-select" data-pc-cal-modal-year></select>
                    </div>
                    <button type="button"
                        class="pc-cal-today-btn"
                        data-pc-cal-modal-today>
                        <?php echo esc_html__('Aujourd’hui', 'pc-reservation-core'); ?>
                    </button>
                </div>

                <div class="pc-cal-modal__grid" data-pc-cal-modal-grid></div>
            </div>
        </div>
    </div>
<?php

    return ob_get_clean();
}

add_shortcode('pc_dashboard_calendar', 'pc_dashboard_calendar_shortcode');
