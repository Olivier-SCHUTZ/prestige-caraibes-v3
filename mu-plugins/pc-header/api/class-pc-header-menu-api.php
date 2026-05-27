<?php

if (!defined('ABSPATH')) {
    exit;
}

class PC_Header_Menu_API
{
    public function register()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes()
    {
        register_rest_route('pc/v1', '/menus', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handle_request'],
            'permission_callback' => '__return_true', // public
        ]);
    }

    public function handle_request(\WP_REST_Request $req): \WP_REST_Response
    {
        // On réutilise la brillante architecture existante
        $cfg = PC_Header_Config::get();

        $main_items = PC_Header_Menu_Helper::get_items($cfg['menu_name']);
        $services_items = PC_Header_Menu_Helper::get_items($cfg['menu_services_name']);

        $response = [
            'mainTree'     => PC_Header_Menu_Helper::build_tree($main_items),
            'servicesTree' => PC_Header_Menu_Helper::build_tree($services_items)
        ];

        return new \WP_REST_Response($response, 200);
    }
}
