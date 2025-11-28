<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gère la page d'options du plugin via ACF Pro.
 * Crée un menu principal "PC Réservation" dans l'admin.
 */
class PCR_Settings
{
    public static function init()
    {
        // On crée une PAGE PRINCIPALE car il n'y a pas encore de menu parent
        if (function_exists('acf_add_options_page')) {
            acf_add_options_page([
                'page_title'  => 'Configuration PC Réservation',
                'menu_title'  => 'PC Réservation', // Le nom dans le menu noir à gauche
                'menu_slug'   => 'pc-reservation-settings',
                'capability'  => 'manage_options',
                'icon_url'    => 'dashicons-calendar-alt', // Une jolie icône calendrier
                'redirect'    => false,
            ]);
        }

        // Enregistrement des champs ACF (Clés API Stripe)
        add_action('acf/init', [__CLASS__, 'register_fields']);
    }

    public static function register_fields()
    {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group([
            'key' => 'group_pc_stripe_settings',
            'title' => 'Configuration Stripe & Paiements',
            'fields' => [
                [
                    'key' => 'field_pc_stripe_mode',
                    'label' => 'Mode',
                    'name' => 'pc_stripe_mode',
                    'type' => 'button_group',
                    'choices' => [
                        'test' => 'Test (Sandbox)',
                        'live' => 'Live (Production)',
                    ],
                    'default_value' => 'test',
                    'layout' => 'horizontal',
                ],
                // --- CLÉS DE TEST ---
                [
                    'key' => 'field_pc_stripe_test_pk',
                    'label' => 'Clé Publique (Test)',
                    'name' => 'pc_stripe_test_pk',
                    'type' => 'text',
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_pc_stripe_mode',
                                'operator' => '==',
                                'value' => 'test',
                            ],
                        ],
                    ],
                ],
                [
                    'key' => 'field_pc_stripe_test_sk',
                    'label' => 'Clé Secrète (Test)',
                    'name' => 'pc_stripe_test_sk',
                    'type' => 'password',
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_pc_stripe_mode',
                                'operator' => '==',
                                'value' => 'test',
                            ],
                        ],
                    ],
                ],
                // --- CLÉS DE PROD ---
                [
                    'key' => 'field_pc_stripe_live_pk',
                    'label' => 'Clé Publique (Live)',
                    'name' => 'pc_stripe_live_pk',
                    'type' => 'text',
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_pc_stripe_mode',
                                'operator' => '==',
                                'value' => 'live',
                            ],
                        ],
                    ],
                ],
                [
                    'key' => 'field_pc_stripe_live_sk',
                    'label' => 'Clé Secrète (Live)',
                    'name' => 'pc_stripe_live_sk',
                    'type' => 'password',
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_pc_stripe_mode',
                                'operator' => '==',
                                'value' => 'live',
                            ],
                        ],
                    ],
                ],
                [
                    'key' => 'field_pc_stripe_webhook_secret',
                    'label' => 'Secret Webhook (Signature)',
                    'name' => 'pc_stripe_webhook_secret',
                    'type' => 'text',
                    'instructions' => 'Nécessaire pour valider les paiements automatiquement.',
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'options_page',
                        'operator' => '==',
                        'value' => 'pc-reservation-settings',
                    ],
                ],
            ],
        ]);
    }
}
