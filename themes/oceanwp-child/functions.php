<?php

/**
 * OceanWP Child Theme - functions.php
 * Sécurité + chargement des styles parent & enfant + optimisations SEO de base.
 */

if (! defined('ABSPATH')) {
    exit; // Bloque l'accès direct
}

// Charger les styles du parent et de l'enfant proprement (sans @import)
add_action('wp_enqueue_scripts', 'oceanwp_child_enqueue_styles', 20);
function oceanwp_child_enqueue_styles()
{
    $parent_handle = 'oceanwp-style'; // handle du thème parent
    $parent_style  = get_template_directory_uri() . '/style.css';
    $theme  = wp_get_theme();
    $ver    = is_child_theme() ? $theme->parent()->get('Version') : $theme->get('Version');
    wp_enqueue_style($parent_handle, $parent_style, array(), $ver);
    wp_enqueue_style('oceanwp-child-style', get_stylesheet_uri(), array($parent_handle), $theme->get('Version'));
}

/**
 * Ajouter le script Iubenda pour la bannière de consentement dans le <head>.
 * Il est nécessaire de le placer ici pour s'assurer qu'il s'exécute en premier.
 */
function pc_add_iubenda_consent_banner()
{
    // Le script Iubenda, sans les balises PHP de début/fin
    echo '<script type="text/javascript" src="https://embeds.iubenda.com/widgets/6009e7fe-1ae3-4fc1-8bc0-08c908991e29.js"></script>' . "\n";
}
add_action('wp_head', 'pc_add_iubenda_consent_banner');

/**
 * =========================================
 * SEO & PERFORMANCE
 * =========================================
 */

/** 1) Nettoyage du <head> WordPress */
add_action('init', function () {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'wp_shortlink_wp_head');
    remove_action('wp_head', 'rest_output_link_wp_head');
});

/** 2) jQuery Migrate (désactiver si non requis) */
/*
add_filter('wp_default_scripts', function( $scripts ){
    if ( is_admin() ) return;
    if ( ! empty( $scripts->registered['jquery'] ) ) {
        $deps = $scripts->registered['jquery']->deps;
        $scripts->registered['jquery']->deps = array_diff( $deps, ['jquery-migrate'] );
    }
});
*/

/** 3) Preconnect / DNS Prefetch (Google Fonts) */
add_action('wp_head', function () {
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="dns-prefetch" href="//fonts.gstatic.com">' . "\n";
    echo '<link rel="dns-prefetch" href="//fonts.googleapis.com">' . "\n";
}, 1);

/** 4) ALT automatique si manquant */
add_filter('wp_get_attachment_image_attributes', function ($attr, $attachment) {
    if (empty($attr['alt'])) {
        $alt = get_post_meta($attachment->ID, '_wp_attachment_image_alt', true);
        if (! $alt) {
            $attr['alt'] = trim(wp_strip_all_tags(get_the_title($attachment->ID)));
        }
    }
    return $attr;
}, 10, 2);

/** 5) Lazy-load intelligent : pas sur l'image héro */
add_filter('wp_get_attachment_image_attributes', function ($attr) {
    static $first_image = true;
    // Sur la page d'accueil, on cible la première image (qui est le LCP)
    // Note: WP Rocket peut être plus agressif, des exclusions manuelles sont parfois nécessaires.
    if (is_front_page() || is_home()) {
        unset($attr['loading']);
        $attr['fetchpriority'] = 'high';
    }
    // Sur les autres pages, on cible la toute première image rencontrée
    elseif ($first_image) {
        unset($attr['loading']);
        $first_image = false;
    }
    return $attr;
}, 20);

/** 6) OceanWP : désactiver barre de titre de page */
add_filter('ocean_display_page_header', '__return_false');
