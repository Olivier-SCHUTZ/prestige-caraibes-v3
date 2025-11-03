<?php
/**
 * Plugin Name: Prestige Caraïbes — Types de Contenu (CPT)
 * Description: Déclare les CPT Logements (Villa/Appartement) et la taxonomie Catégories.
 * Author: Prestige Caraïbes
 * Version: 3.3
 */

if ( ! defined('ABSPATH') ) exit;

/**
 * ==================================================================
 * 1) CPT & Taxonomie
 * ==================================================================
 */
add_action('init', function () {

    /* -----------------------------
     * Taxonomie : Catégories logement
     * ----------------------------- */
    $tax_labels = [
        'name'              => 'Catégories de Logements',
        'singular_name'     => 'Catégorie de Logement',
        'search_items'      => 'Rechercher une catégorie',
        'all_items'         => 'Toutes les catégories',
        'edit_item'         => 'Modifier la catégorie',
        'update_item'       => 'Mettre à jour la catégorie',
        'add_new_item'      => 'Ajouter une catégorie',
        'new_item_name'     => 'Nouvelle catégorie',
        'menu_name'         => 'Catégories',
    ];
    register_taxonomy('categorie_logement', ['villa','appartement'], [
        'hierarchical'      => true,
        'labels'            => $tax_labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => ['slug' => 'categorie-logement'],
        'show_in_rest'      => true,
    ]);

    /* -----------------------------
     * CPT : Villa (menu parent "Logements")
     * ----------------------------- */
    $villa_labels = [
        'name'               => 'Logements',
        'singular_name'      => 'Villa',
        'add_new'            => 'Ajouter',
        'add_new_item'       => 'Ajouter une villa',
        'edit_item'          => 'Modifier la villa',
        'new_item'           => 'Nouvelle villa',
        'view_item'          => 'Voir la villa',
        'search_items'       => 'Rechercher une villa',
        'not_found'          => 'Aucune villa',
        'not_found_in_trash' => 'Aucune villa dans la corbeille',
        'menu_name'          => 'Logements',
    ];
    $villa_args = [
        'labels'             => $villa_labels,
        'public'             => true,
        'show_ui'            => true,
        'show_in_menu'       => true, // menu principal
        'menu_position'      => 25,
        'menu_icon'          => 'dashicons-admin-multisite',
        'supports'           => ['title','editor','thumbnail','custom-fields','revisions'],
        'has_archive'        => false,
        'rewrite'            => ['slug' => 'location-villa'],
        'taxonomies'         => ['categorie_logement'],
        'publicly_queryable'  => true,
        'show_in_rest'       => true,
        'map_meta_cap'       => true,
    ];
    register_post_type('villa', $villa_args);

    /* -----------------------------
     * CPT : Appartement (sous-menu de "Logements")
     * ----------------------------- */
    $appartement_labels = [
        'name'               => 'Appartements',
        'singular_name'      => 'Appartement',
        'add_new'            => 'Ajouter',
        'add_new_item'       => 'Ajouter un appartement',
        'edit_item'          => "Modifier l'appartement",
        'new_item'           => 'Nouvel appartement',
        'view_item'          => "Voir l'appartement",
        'search_items'       => 'Rechercher un appartement',
        'not_found'          => 'Aucun appartement',
        'not_found_in_trash' => 'Aucun appartement dans la corbeille',
        'menu_name'          => 'Appartements',
    ];
    $appartement_args = [
        'labels'             => $appartement_labels,
        'public'             => true,
        'show_ui'            => true,
        // ➜ Sous le menu "Logements" (CPT villa)
        'show_in_menu'       => 'edit.php?post_type=villa',
        'supports'           => ['title','editor','thumbnail','custom-fields','revisions'],
        'has_archive'        => false,
        'rewrite'            => ['slug' => 'location-appartement'],
        'taxonomies'         => ['categorie_logement'],
        'publicly_queryable'  => true,
        'show_in_rest'       => true,
        'map_meta_cap'       => true,
    ];
    register_post_type('appartement', $appartement_args);
    /* -----------------------------
     * CPT : Expérience (menu principal)
     * ----------------------------- */
    $experience_labels = [
        'name'               => 'Expériences',
        'singular_name'      => 'Expérience',
        'add_new'            => 'Ajouter',
        'add_new_item'       => 'Ajouter une expérience',
        'edit_item'          => 'Modifier l\'expérience',
        'new_item'           => 'Nouvelle expérience',
        'view_item'          => 'Voir l\'expérience',
        'search_items'       => 'Rechercher une expérience',
        'not_found'          => 'Aucune expérience trouvée',
        'not_found_in_trash' => 'Aucune expérience dans la corbeille',
        'menu_name'          => 'Expériences',
    ];
    $experience_args = [
        'labels'             => $experience_labels,
        'public'             => true,
        'show_ui'            => true,
        'show_in_menu'       => true, // Menu principal indépendant
        'menu_position'      => 26,   // Juste après "Logements"
        'menu_icon'          => 'dashicons-palmtree',
        'supports'           => ['title','editor','thumbnail','custom-fields','revisions'],
        'has_archive'        => true, // Utile pour une page listant toutes les expériences
        'rewrite'            => ['slug' => 'experience'],
        'taxonomies'         => ['categorie_experience'],
        'publicly_queryable'  => true,
        'show_in_rest'       => true,
    ];
    register_post_type('experience', $experience_args);

    /* -----------------------------
     * Taxonomie : Catégories d'expérience
     * ----------------------------- */
    $tax_exp_labels = [
        'name'              => 'Catégories d\'expérience',
        'singular_name'     => 'Catégorie d\'expérience',
        'search_items'      => 'Rechercher une catégorie',
        'all_items'         => 'Toutes les catégories',
        'edit_item'         => 'Modifier la catégorie',
        'update_item'       => 'Mettre à jour la catégorie',
        'add_new_item'      => 'Ajouter une catégorie',
        'new_item_name'     => 'Nouvelle catégorie',
        'menu_name'         => 'Catégories',
    ];
    register_taxonomy('categorie_experience', ['experience'], [
        'hierarchical'      => true,
        'labels'            => $tax_exp_labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => ['slug' => 'categorie-experience'],
        'show_in_rest'      => true,
    ]);

}, 0);

// === CPT: destination (Ville) — AJOUTER sans supprimer le reste
add_action('init', function(){
  if (post_type_exists('destination')) return;
  register_post_type('destination', [
    'label' => 'Destinations',
    'labels' => [
      'singular_name' => 'Destination',
      'add_new' => 'Ajouter',
      'add_new_item' => 'Ajouter une destination',
      'edit_item' => 'Modifier la destination',
      'new_item' => 'Nouvelle destination',
      'view_item' => 'Voir la destination',
      'search_items' => 'Rechercher des destinations',
      'not_found' => 'Aucune destination',
      'not_found_in_trash' => 'Aucune destination dans la corbeille',
      'all_items' => 'Toutes les destinations',
      'menu_name' => 'Destinations',
    ],
    'public' => true,
    'has_archive' => false, // Option A validée
    'rewrite' => ['slug' => 'destinations', 'with_front' => false],
    'show_in_rest' => true,
    'supports' => ['title','editor','thumbnail','excerpt','revisions'],
    'menu_position' => 20,
    'menu_icon' => 'dashicons-location',
  ]);
}, 9);


/**
 * ==================================================================
 * 2) Organisation du menu d’administration
 * ==================================================================
 */
add_action('admin_menu', function () {
    // Sous-menu "Catégories" sous "Logements" (taxonomie des logements)
    add_submenu_page(
        'edit.php?post_type=villa',
        'Catégories de Logements',
        'Catégories',
        'manage_categories',
        'edit-tags.php?taxonomy=categorie_logement&post_type=villa'
    );
}, 99);
