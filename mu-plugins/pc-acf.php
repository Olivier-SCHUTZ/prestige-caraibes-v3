<?php
/**
 * Plugin Name: PC – ACF (MU) : Logements, Catégories, FAQ & Avis
 * Description: Regroupe tous les groupes ACF en un seul endroit (Local JSON) + évite les doublons.
 * Author: Prestige Caraïbes
 */

if ( ! defined('ABSPATH') ) exit;

/** 0) Vérif ACF */
add_action('plugins_loaded', function(){
  if ( ! function_exists('acf_add_local_field_group') ) {
    error_log('PC-ACF (MU) : ACF est requis pour charger les groupes.');
  }
});

/** 1) Local JSON
 *  - SAVE: enregistre dans /mu-plugins/pc-acf-json
 *  - LOAD: **uniquement** depuis ce dossier (on n’ajoute PAS le chemin du thème) pour éviter les doublons.
 */
add_filter('acf/settings/save_json', function($path){
  return WP_CONTENT_DIR . '/mu-plugins/pc-acf-json';
});
add_filter('acf/settings/load_json', function($paths){
  return [ WP_CONTENT_DIR . '/mu-plugins/pc-acf-json' ];
});







