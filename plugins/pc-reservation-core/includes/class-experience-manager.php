<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCR Experience Manager - Façade de Gestion des Expériences (Refactoring v2)
 * Cette classe agit désormais comme un simple proxy vers la nouvelle architecture 
 * (Repository / Service) pour garantir la non-régression (Pattern Strangler).
 * * @since 0.2.0 (Création)
 * @since 2.0.0 (Refactoring en Façade)
 */
class PCR_Experience_Manager
{
    /**
     * Initialisation des hooks.
     */
    public static function init()
    {
        // Hook d'initialisation redirigé si besoin plus tard
    }

    /**
     * Retourne une liste légère des expériences pour le tableau dashboard.
     * * @param array $args Arguments de requête (pagination, filtres)
     * @return array
     */
    public static function get_experience_list($args = [])
    {
        return PCR_Experience_Repository::get_instance()->get_experience_list($args);
    }

    /**
     * Retourne les détails complets d'une expérience avec tous les champs mappés.
     * * @param int $post_id ID du post
     * @return array|false
     */
    public static function get_experience_details($post_id)
    {
        return PCR_Experience_Repository::get_instance()->get_experience_details($post_id);
    }

    /**
     * Met à jour une expérience avec les données fournies.
     * Accepte ID = 0 pour créer une nouvelle expérience.
     * * @param int $post_id ID du post (0 pour création)
     * @param array $data Données à mettre à jour
     * @return array
     */
    public static function update_experience($post_id, $data)
    {
        return PCR_Experience_Service::get_instance()->update_experience($post_id, $data);
    }

    /**
     * Supprime définitivement une expérience et toutes ses données associées.
     * * @param int $post_id ID du post à supprimer
     * @return array
     */
    public static function delete_experience($post_id)
    {
        return PCR_Experience_Repository::get_instance()->delete_experience($post_id);
    }
}
