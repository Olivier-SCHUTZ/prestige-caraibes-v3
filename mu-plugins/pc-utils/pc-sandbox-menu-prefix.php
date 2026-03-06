<?php
/**
 * PC — Prefix /sandbox aux liens de menu en staging.
 * Chargé uniquement quand PC_ENV=staging (via pc-loader.php).
 */
add_filter('nav_menu_link_attributes', function($atts, $item, $args){
    if (!isset($atts['href']) || $atts['href'] === '') return $atts;

    $href = $atts['href'];

    // 1) Lien root-relatif → ajouter /sandbox au début si absent
    if (strpos($href, '/') === 0 && strpos($href, '/sandbox/') !== 0) {
        $atts['href'] = '/sandbox' . $href;
        return $atts;
    }

    // 2) Lien absolu vers domaine prod → injecter /sandbox/
    $site_sandbox = home_url('/');                         // https://prestigecaraibes.com/sandbox/
    $site_prod    = str_replace('/sandbox/', '/', $site_sandbox);
    $host         = parse_url($site_prod, PHP_URL_HOST);

    if (stripos($href, "https://{$host}/") === 0 && stripos($href, '/sandbox/') === false) {
        $atts['href'] = str_replace("https://{$host}/", $site_sandbox, $href);
    } elseif (stripos($href, "http://{$host}/") === 0 && stripos($href, '/sandbox/') === false) {
        $atts['href'] = str_replace("http://{$host}/", $site_sandbox, $href);
    }

    return $atts;
}, 10, 3);
