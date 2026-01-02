<?php
if (!defined('ABSPATH')) exit;

class PCSC_Pro_Loader
{
    public static function init()
    {
        require_once PCSC_PLUGIN_DIR . 'pro/class-pcsc-pro-cron.php';
        PCSC_Pro_Cron::init();

        // --- AJOUT : Chargement du Dashboard Mobile ---
        require_once PCSC_PLUGIN_DIR . 'pro/class-pcsc-pro-dashboard.php';
        PCSC_Pro_Dashboard::init();
        // ----------------------------------------------
    }
}
