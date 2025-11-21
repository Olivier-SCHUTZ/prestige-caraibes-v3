<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode : [pc_dashboard_calendar]
 * Affichage uniquement (planning global + modale logement).
 */
function pc_resa_register_dashboard_calendar_shortcode()
{
    add_shortcode('pc_dashboard_calendar', 'pc_resa_render_dashboard_calendar');
}
add_action('init', 'pc_resa_register_dashboard_calendar_shortcode');

/**
 * Rend le conteneur HTML du planning + modale.
 *
 * @return string
 */
function pc_resa_render_dashboard_calendar()
{
    wp_enqueue_script(
        'pc-dashboard-calendar',
        PC_RES_CORE_URL . 'assets/js/pc-calendar.js',
        [],
        PC_RES_CORE_VERSION,
        true
    );

    wp_localize_script('pc-dashboard-calendar', 'pcDashboardCalendar', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('pc_dashboard_calendar'),
        'i18n'    => [
            'loading'      => __('Chargement du planning...', 'pc'),
            'noLogement'   => __('Aucun logement actif pour le moment.', 'pc'),
            'calendarName' => __('Calendrier', 'pc'),
        ],
    ]);

    $current_month = (int) current_time('n');
    $current_year  = (int) current_time('Y');

    ob_start();
    ?>
    <div class="pc-container pc-dashboard-calendar"
        data-month="<?php echo esc_attr($current_month); ?>"
        data-year="<?php echo esc_attr($current_year); ?>">
        <div class="pc-dashboard-calendar__header">
            <div class="pc-dashboard-calendar__title">
                <p class="pc-dashboard-calendar__eyebrow">Planning multi-logements</p>
                <h2 class="pc-h2">Dashboard Calendrier</h2>
                <p class="pc-dashboard-calendar__subtitle">
                    Visualisez d&rsquo;un coup d&rsquo;œil les réservations confirmées et les indisponibilités iCal.
                </p>
            </div>
            <div class="pc-dashboard-calendar__actions">
                <button type="button"
                    class="pc-btn pc-btn--ghost"
                    data-pc-calendar-nav="prev">
                    &#8249; Mois précédent
                </button>
                <button type="button"
                    class="pc-btn"
                    data-pc-calendar-nav="next">
                    Mois suivant &#8250;
                </button>
            </div>
        </div>

        <div class="pc-dashboard-calendar__legend">
            <span class="pc-dashboard-calendar__badge pc-dashboard-calendar__badge--primary"></span>
            <span class="pc-dashboard-calendar__legend-label">Réservations confirmées</span>
            <span class="pc-dashboard-calendar__badge pc-dashboard-calendar__badge--secondary"></span>
            <span class="pc-dashboard-calendar__legend-label">Indispos iCal / blocages</span>
        </div>

        <div class="pc-dashboard-calendar__global" data-pc-calendar-grid>
            <div class="pc-dashboard-calendar__loader" data-pc-calendar-loader>
                <?php echo esc_html__('Chargement du planning...', 'pc'); ?>
            </div>
        </div>

        <div class="pc-dashboard-calendar__modal" data-pc-calendar-modal hidden>
            <div class="pc-dashboard-calendar__modal-overlay" data-pc-calendar-close></div>
            <div class="pc-dashboard-calendar__modal-dialog">
                <div class="pc-dashboard-calendar__modal-header">
                    <div>
                        <p class="pc-dashboard-calendar__eyebrow"><?php echo esc_html__('Calendrier logement', 'pc'); ?></p>
                        <h3 class="pc-dashboard-calendar__modal-title" data-pc-calendar-modal-title></h3>
                    </div>
                    <button type="button" class="pc-calendar-close" data-pc-calendar-close aria-label="<?php echo esc_attr__('Fermer', 'pc'); ?>">
                        &times;
                    </button>
                </div>
                <div class="pc-dashboard-calendar__modal-actions">
                    <button type="button"
                        class="pc-btn pc-btn--ghost"
                        data-pc-calendar-modal-nav="prev">
                        &#8249; Mois précédent
                    </button>
                    <button type="button"
                        class="pc-btn"
                        data-pc-calendar-modal-nav="next">
                        Mois suivant &#8250;
                    </button>
                </div>
                <div class="pc-dashboard-calendar__modal-body">
                    <div class="pc-calendar-single" data-pc-calendar-single></div>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
