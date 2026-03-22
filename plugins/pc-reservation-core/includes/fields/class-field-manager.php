<?php

/**
 * Core Field Manager pour PC Reservation.
 * Remplace progressivement ACF Pro pour la gestion des métadonnées.
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class PCR_Field_Manager
{

    /**
     * Instance unique (Singleton)
     */
    private static $instance = null;

    /**
     * Registre des groupes de champs
     */
    private $field_groups = [];

    /**
     * Initialisation du Singleton
     */
    public static function init()
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->setup_hooks();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Constructeur privé pour forcer le Singleton
    }

    /**
     * Déclaration des hooks WordPress
     */
    private function setup_hooks()
    {
        // Nous ajouterons ici les hooks pour la sauvegarde (save_post) 
        // et l'injection dans l'API REST plus tard.
    }

    /**
     * Enregistrer un groupe de champs (ex: Logements, Expériences)
     */
    public function register_field_group($group_id, $config)
    {
        $this->field_groups[$group_id] = $config;
    }

    /**
     * Récupérer tous les groupes enregistrés
     */
    public function get_field_groups()
    {
        return $this->field_groups;
    }

    /**
     * Récupérer une valeur brute depuis la base de données (wp_postmeta)
     */
    public function get_native_field($key, $post_id)
    {
        // On récupère la meta native de WordPress
        $value = get_post_meta($post_id, $key, true);

        // Si la meta n'existe pas, get_post_meta retourne une chaîne vide.
        // On la convertit en null pour faciliter notre logique de "fallback" vers ACF.
        return ($value === '') ? null : $value;
    }

    /**
     * Sauvegarder une valeur nativement
     */
    public function save_native_field($key, $post_id, $value)
    {
        return update_post_meta($post_id, $key, $value);
    }
}
