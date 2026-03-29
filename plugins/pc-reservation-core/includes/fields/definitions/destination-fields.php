<?php

/**
 * Définition des champs natifs pour les Destinations
 * Remplace définitivement les groupes ACF associés aux destinations.
 */

if (!defined('ABSPATH')) {
    exit;
}

$manager = PCR_Field_Manager::init();
$post_types = ['destination'];

// 1. GÉNÉRAL & SEO
$manager->register_field_group('destination_seo_general', [
    'title'      => 'SEO & Visibilité',
    'post_types' => $post_types,
    'fields'     => [
        'dest_featured'         => ['type' => 'checkbox', 'label' => 'Mettre en avant cette destination'],
        'dest_order'            => ['type' => 'number', 'label' => 'Ordre d\'affichage'],
        'dest_h1'               => ['type' => 'text', 'label' => 'Titre H1 Personnalisé'],
        'dest_meta_title'       => ['type' => 'text', 'label' => 'Meta Titre'],
        'dest_meta_description' => ['type' => 'textarea', 'label' => 'Meta Description'],
        'dest_meta_canonical'   => ['type' => 'text', 'label' => 'URL Canonique'],
        'dest_meta_robots'      => ['type' => 'select', 'label' => 'Meta Robots'],
        'dest_exclude_sitemap'  => ['type' => 'checkbox', 'label' => 'Exclure du Sitemap'],
        'dest_http_410'         => ['type' => 'checkbox', 'label' => 'Forcer en erreur 410'],
    ]
]);

// 2. MÉDIAS & IMAGES
$manager->register_field_group('destination_media', [
    'title'      => 'Médias & Images',
    'post_types' => $post_types,
    'fields'     => [
        'dest_hero_desktop' => ['type' => 'image', 'label' => 'Hero Desktop (ID)'],
        'dest_hero_mobile'  => ['type' => 'image', 'label' => 'Hero Mobile (ID)'],
    ]
]);

// 3. GÉOGRAPHIE & STATISTIQUES
$manager->register_field_group('destination_geo_stats', [
    'title'      => 'Géographie & Statistiques',
    'post_types' => $post_types,
    'fields'     => [
        'dest_region'              => ['type' => 'select', 'label' => 'Région'],
        'dest_sea_side'            => ['type' => 'radio', 'label' => 'Type de côte'],
        'dest_geo_lat'             => ['type' => 'number', 'label' => 'Latitude (GPS)'],
        'dest_geo_lng'             => ['type' => 'number', 'label' => 'Longitude (GPS)'],
        'dest_population'          => ['type' => 'number', 'label' => 'Population'],
        'dest_surface_km2'         => ['type' => 'number', 'label' => 'Surface en km²'],
        'dest_airport_distance_km' => ['type' => 'number', 'label' => 'Distance Aéroport (km)'],
    ]
]);

// 4. CONTENUS TEXTUELS & RELATIONS
$manager->register_field_group('destination_content', [
    'title'      => 'Textes & Relations',
    'post_types' => $post_types,
    'fields'     => [
        'dest_slogan'                => ['type' => 'text', 'label' => 'Slogan (vignette)'],
        'dest_intro'                 => ['type' => 'textarea', 'label' => 'Introduction (Description complète)'],
        'dest_exp_featured'          => ['type' => 'array', 'label' => 'Expériences mises en avant (IDs)'],
        'dest_logements_recommandes' => ['type' => 'array', 'label' => 'Logements recommandés (IDs)'],
    ]
]);

// 5. INFOS PRATIQUES & FAQ (JSON)
$manager->register_field_group('destination_repeaters', [
    'title'      => 'Infos Pratiques & FAQ',
    'post_types' => $post_types,
    'fields'     => [
        'dest_infos' => ['type' => 'array', 'label' => 'Informations pratiques (JSON/Repeater)'],
        'dest_faq'   => ['type' => 'array', 'label' => 'FAQ (JSON/Repeater)'],
    ]
]);
