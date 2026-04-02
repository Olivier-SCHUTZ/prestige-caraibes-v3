<?php

/**
 * Définition des champs natifs pour les Logements (Villas & Appartements)
 * Remplace définitivement les groupes ACF correspondants.
 */

if (!defined('ABSPATH')) {
    exit;
}

$manager = PCR_Field_Manager::init();
$post_types = ['villa', 'appartement'];

// 1. MÉDIA & GALERIE
$manager->register_field_group('housing_media', [
    'title'      => 'Média & Galerie',
    'post_types' => $post_types,
    'fields'     => [
        'hero_desktop_url'  => ['type' => 'image', 'label' => 'Hero Desktop'],
        'hero_mobile_url'   => ['type' => 'image', 'label' => 'Hero Mobile'],
        'gallery_urls'      => ['type' => 'gallery', 'label' => 'Galerie principale'],
        'video_urls'        => ['type' => 'text', 'label' => 'Vidéos'],
        'groupes_images'    => ['type' => 'text', 'label' => 'Groupes d\'images'],
    ]
]);

// 2. CONTENU SEO & HIGHLIGHTS
$manager->register_field_group('housing_seo', [
    'title'      => 'Contenu & SEO',
    'post_types' => $post_types,
    'fields'     => [
        'contenu_seo_titre_h1'              => ['type' => 'text'],
        'seo_long_html'                     => ['type' => 'textarea'],
        'highlights'                        => ['type' => 'text'],
        'highlights_custom'                 => ['type' => 'text'],
        'logement_experiences_recommandees' => ['type' => 'text'],
        'logement_faq'                      => ['type' => 'array', 'label' => 'FAQ du Logement (JSON)'],
        'meta_titre'                        => ['type' => 'text'],
        'meta_description'                  => ['type' => 'textarea'],
        'url_canonique'                     => ['type' => 'text'],
        'log_exclude_sitemap'               => ['type' => 'checkbox'],
        'log_http_410'                      => ['type' => 'checkbox'],
        'log_meta_robots'                   => ['type' => 'text'],
        'seo_gallery_urls'                  => ['type' => 'gallery'],
    ]
]);

// 3. DÉTAILS DU LOGEMENT
$manager->register_field_group('housing_details', [
    'title'      => 'Détails & Capacités',
    'post_types' => $post_types,
    'fields'     => [
        'identifiant_lodgify' => ['type' => 'text'],
        'capacite'            => ['type' => 'number'],
        'superficie'          => ['type' => 'number'],
        'nombre_de_chambres'  => ['type' => 'number'],
        'nombre_sdb'          => ['type' => 'number'],
        'nombre_lits'         => ['type' => 'number'],
    ]
]);

// 4. ÉQUIPEMENTS (Stockés sous forme de tableaux/JSON par Vue)
$manager->register_field_group('housing_equipments', [
    'title'      => 'Équipements',
    'post_types' => $post_types,
    'fields'     => [
        'eq_piscine_spa'                  => ['type' => 'array'],
        'eq_parking_installations'        => ['type' => 'array'],
        'eq_politiques'                   => ['type' => 'array'],
        'eq_divertissements'              => ['type' => 'array'],
        'eq_cuisine_salle_a_manger'       => ['type' => 'array'],
        'eq_caracteristiques_emplacement' => ['type' => 'array'],
        'eq_salle_de_bain_blanchisserie'  => ['type' => 'array'],
        'eq_chauffage_climatisation'      => ['type' => 'array'],
        'eq_internet_bureautique'         => ['type' => 'array'],
        'eq_securite_maison'              => ['type' => 'array'],
    ]
]);

// 5. EMPLACEMENT & PROXIMITÉS
$manager->register_field_group('housing_location', [
    'title'      => 'Emplacement',
    'post_types' => $post_types,
    'fields'     => [
        'adresse_rue'     => ['type' => 'text'],
        'ville'           => ['type' => 'text'],
        'code_postal'     => ['type' => 'text'],
        'latitude'        => ['type' => 'text'],
        'longitude'       => ['type' => 'text'],
        'geo_coords'      => ['type' => 'text'],
        'geo_radius_m'    => ['type' => 'number'],
        'prox_airport_km' => ['type' => 'number'],
        'prox_bus_km'     => ['type' => 'number'],
        'prox_port_km'    => ['type' => 'number'],
        'prox_beach_km'   => ['type' => 'number'],
    ]
]);

// 6. RÉSERVATION & RÈGLES
$manager->register_field_group('housing_booking', [
    'title'      => 'Réservation & Règles',
    'post_types' => $post_types,
    'fields'     => [
        'mode_reservation'      => ['type' => 'select'],
        'ical_export_token'     => ['type' => 'text'],
        'politique_dannulation' => ['type' => 'textarea'],
        'regles_maison'         => ['type' => 'textarea'],
        'horaire_arrivee'       => ['type' => 'text'],
        'horaire_depart'        => ['type' => 'text'],
        'icals_sync'            => ['type' => 'array', 'label' => 'Flux iCal de synchronisation'],
        'lodgify_widget_embed'  => ['type' => 'textarea'],
        'pc_manual_quote'       => ['type' => 'checkbox'],
    ]
]);

// 7. TARIFICATION DE BASE & FRAIS
$manager->register_field_group('housing_pricing', [
    'title'      => 'Tarification de base',
    'post_types' => $post_types,
    'fields'     => [
        'base_price_from'   => ['type' => 'number'],
        'unite_de_prix'     => ['type' => 'select'],
        'pc_promo_log'      => ['type' => 'checkbox'],
        'min_nights'        => ['type' => 'number'],
        'max_nights'        => ['type' => 'number'],
        'extra_guest_fee'   => ['type' => 'number'],
        'extra_guest_from'  => ['type' => 'number'],
        'caution'           => ['type' => 'number'],
        'frais_menage'      => ['type' => 'number'],
        'taux_tva'          => ['type' => 'number'],
        'taux_tva_menage'   => ['type' => 'number'],
        'autres_frais'      => ['type' => 'number'],
        'autres_frais_type' => ['type' => 'text'],
        'taxe_sejour'       => ['type' => 'array'],
    ]
]);

// 8. HÔTE & CONTRAT PROPRIÉTAIRE
$manager->register_field_group('housing_host_contract', [
    'title'      => 'Hôte & Contrat',
    'post_types' => $post_types,
    'fields'     => [
        'hote_nom'                  => ['type' => 'text'],
        'hote_description'          => ['type' => 'textarea'],
        'hote_photo'                => ['type' => 'image', 'label' => 'Photo de l\'hôte'],
        'log_proprietaire_identite' => ['type' => 'text'],
        'personne_logement'         => ['type' => 'text'],
        'proprietaire_adresse'      => ['type' => 'textarea'],
        'description_contrat'       => ['type' => 'textarea'],
        'equipements_contrat'       => ['type' => 'textarea'],
        'has_piscine'               => ['type' => 'checkbox'],
        'has_jacuzzi'               => ['type' => 'checkbox'],
        'has_guide_numerique'       => ['type' => 'checkbox'],
    ]
]);

// 9. RÈGLES DE PAIEMENT (CHAMPS INDIVIDUELS)
$manager->register_field_group('housing_payment_rules', [
    'title'      => 'Règles de Paiement',
    'post_types' => $post_types,
    'fields'     => [
        'pc_pay_mode'           => ['type' => 'select'],
        'pc_deposit_type'       => ['type' => 'select'],
        'pc_deposit_value'      => ['type' => 'number'],
        'pc_balance_delay_days' => ['type' => 'number'],
        'pc_caution_amount'     => ['type' => 'number'],
        'pc_caution_type'       => ['type' => 'select'],
    ]
]);

// Note : Les répéteurs (pc_season_blocks, pc_promo_blocks) et l'objet JSON (payment_rules) 
// sont gérés spécifiquement dans le contrôleur de sauvegarde et le repository.