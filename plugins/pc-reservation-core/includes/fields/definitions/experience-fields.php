<?php

/**
 * Définition des champs natifs pour les Expériences
 * Remplace définitivement les groupes ACF associés aux expériences.
 */

if (!defined('ABSPATH')) {
    exit;
}

$manager = PCR_Field_Manager::init();
$post_types = ['experience'];

// 1. GÉNÉRAL & SEO
$manager->register_field_group('experience_seo_general', [
    'title'      => 'Général & SEO',
    'post_types' => $post_types,
    'fields'     => [
        'exp_availability'          => ['type' => 'checkbox', 'label' => 'Expérience disponible'],
        'exp_h1_custom'             => ['type' => 'text', 'label' => 'Titre H1 Personnalisé'],
        'exp_meta_titre'            => ['type' => 'text', 'label' => 'Meta Titre'],
        'exp_meta_description'      => ['type' => 'textarea', 'label' => 'Meta Description'],
        'exp_meta_canonical'        => ['type' => 'text', 'label' => 'URL Canonique'],
        'exp_meta_robots'           => ['type' => 'text', 'label' => 'Meta Robots'],
        'exp_exclude_sitemap'       => ['type' => 'checkbox', 'label' => 'Exclure du Sitemap'],
        'exp_http_410'              => ['type' => 'checkbox', 'label' => 'Forcer en erreur 410'],
        'exp_logements_recommandes' => ['type' => 'array', 'label' => 'Logements recommandés associés'],
    ]
]);

// 2. MÉDIAS & GALERIE (Détails Principaux & Galerie)
$manager->register_field_group('experience_media', [
    'title'      => 'Médias & Galerie',
    'post_types' => $post_types,
    'fields'     => [
        'exp_hero_desktop'  => ['type' => 'image', 'label' => 'Hero Desktop'],
        'exp_hero_mobile'   => ['type' => 'image', 'label' => 'Hero Mobile'],
        'photos_experience' => ['type' => 'gallery', 'label' => 'Galerie photos de l\'expérience'],
    ]
]);

// 3. DÉTAILS SORTIES
$manager->register_field_group('experience_details', [
    'title'      => 'Détails des Sorties',
    'post_types' => $post_types,
    'fields'     => [
        'exp_duree'                 => ['type' => 'text', 'label' => 'Durée'],
        'exp_capacite'              => ['type' => 'number', 'label' => 'Capacité max'],
        'exp_age_minimum'           => ['type' => 'number', 'label' => 'Âge minimum'],
        'exp_accessibilite'         => ['type' => 'array', 'label' => 'Accessibilité'],
        'exp_periode'               => ['type' => 'array', 'label' => 'Périodes d\'ouverture'],
        'exp_jour'                  => ['type' => 'array', 'label' => 'Jours d\'ouverture'],
        'exp_periodes_fermeture'    => ['type' => 'array', 'label' => 'Périodes de fermeture (JSON/Repeater)'],
        'exp_lieux_horaires_depart' => ['type' => 'array', 'label' => 'Lieux et horaires de départ (JSON/Repeater)'],
    ]
]);

// 4. INCLUSIONS & PRÉ-REQUIS
$manager->register_field_group('experience_inclusions', [
    'title'      => 'Inclusions & Pré-requis',
    'post_types' => $post_types,
    'fields'     => [
        'exp_prix_comprend'        => ['type' => 'textarea', 'label' => 'Le prix comprend'],
        'exp_prix_ne_comprend_pas' => ['type' => 'textarea', 'label' => 'Le prix ne comprend pas'],
        'exp_a_prevoir'            => ['type' => 'array', 'label' => 'À prévoir (Liste)'],
    ]
]);

// 5. SERVICES ADDITIONNELS
$manager->register_field_group('experience_services', [
    'title'      => 'Services Additionnels',
    'post_types' => $post_types,
    'fields'     => [
        'exp_delai_de_reservation'     => ['type' => 'text', 'label' => 'Délai de réservation'],
        'exp_zone_intervention'        => ['type' => 'array', 'label' => 'Zone d\'intervention'],
        'exp_type_de_prestation'       => ['type' => 'text', 'label' => 'Type de prestation'],
        'exp_heure_limite_de_commande' => ['type' => 'text', 'label' => 'Heure limite de commande'],
        'exp_le_service_comprend'      => ['type' => 'textarea', 'label' => 'Le service comprend'],
        'exp_service_a_prevoir'        => ['type' => 'textarea', 'label' => 'Service à prévoir'],
    ]
]);

// 6. RÈGLES DE PAIEMENT & TARIFS (Tarifs complexes gérés via JSON)
$manager->register_field_group('experience_pricing_rules', [
    'title'      => 'Règles de Paiement & Tarifs',
    'post_types' => $post_types,
    'fields'     => [
        'taux_tva'              => ['type' => 'number', 'label' => 'Taux de TVA'],
        'pc_pay_mode'           => ['type' => 'select', 'label' => 'Mode de paiement'],
        'pc_deposit_type'       => ['type' => 'select', 'label' => 'Type d\'acompte'],
        'pc_deposit_value'      => ['type' => 'number', 'label' => 'Valeur de l\'acompte'],
        'pc_balance_delay_days' => ['type' => 'number', 'label' => 'Délai paiement solde (jours)'],
        'pc_caution_amount'     => ['type' => 'number', 'label' => 'Montant de la caution'],
        'pc_caution_mode'       => ['type' => 'select', 'label' => 'Mode de caution'],
        'exp_types_de_tarifs'   => ['type' => 'array', 'label' => 'Grille tarifaire complexe (JSON/Repeater)'],
    ]
]);

// 7. FAQ
$manager->register_field_group('experience_faq', [
    'title'      => 'Foire aux Questions',
    'post_types' => $post_types,
    'fields'     => [
        'exp_faq' => ['type' => 'array', 'label' => 'FAQ (JSON/Repeater)'],
    ]
]);
