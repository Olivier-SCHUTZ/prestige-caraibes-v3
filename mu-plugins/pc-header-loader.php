<?php
/**
 * Plugin Name: PC - Header Assets Loader
 * Description: Charge pc-base.css (racine mu-plugins) puis pc-header.css (mu-plugins/assets) pour le header Elementor.
 * Version: 1.2.2
 * Author: Prestige Caraïbes
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('pc_header_enqueue_assets')) {
  function pc_header_enqueue_assets() {
    if (is_admin()) return;

    $dir_url  = plugin_dir_url(__FILE__);
    $dir_path = __DIR__;

    $base_rel   = 'pc-base.css';
    $header_rel = 'assets/pc-header.css';
    // NEW — chemin JS
    $smart_rel  = 'assets/pc-header-smart.js'; // NEW

    $base_path   = $dir_path . '/' . $base_rel;
    $header_path = $dir_path . '/' . $header_rel;
    // NEW
    $smart_path  = $dir_path . '/' . $smart_rel;   // NEW

    $base_url   = $dir_url . $base_rel;
    $header_url = $dir_url . $header_rel;
    // NEW
    $smart_url  = $dir_url . $smart_rel;          // NEW

    $base_ver   = file_exists($base_path)   ? filemtime($base_path)   : '1.0';
    $header_ver = file_exists($header_path) ? filemtime($header_path) : '1.0';
    // NEW
    $smart_ver  = file_exists($smart_path)  ? filemtime($smart_path)  : '1.0'; // NEW

    $deps = [];
    if (wp_style_is('elementor-frontend', 'registered') || wp_style_is('elementor-frontend', 'enqueued')) {
      $deps[] = 'elementor-frontend';
    }
    if (wp_style_is('oceanwp-style', 'enqueued')) {
      $deps[] = 'oceanwp-style';
    }

    if (file_exists($base_path)) {
      wp_enqueue_style('pc-base', $base_url, $deps, $base_ver);
    }

    $header_deps = array_merge($deps, file_exists($base_path) ? ['pc-base'] : []);
    if (file_exists($header_path)) {
      wp_enqueue_style('pc-header', $header_url, $header_deps, $header_ver);
      wp_add_inline_style('pc-header', '.pc-header{--pc-probe-loaded:' . esc_attr($header_ver) . ';}');
    }

    // NEW — Enqueue du JS smart header
    if (file_exists($smart_path)) {
      wp_enqueue_script(
        'pc-header-smart',
        $smart_url,
        [],                 // pas de dépendances
        $smart_ver,
        true                // footer
      );
      // paramètres ajustables sans modifier le JS
      wp_localize_script('pc-header-smart', 'PCHeader', [
        'solidY' => 24,     // px avant .pc-solid
      ]);
      // optionnel : defer
      wp_script_add_data('pc-header-smart', 'defer', true);
    }
  }
  add_action('wp_enqueue_scripts', 'pc_header_enqueue_assets', 99);
}