<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCR Housing Manager - Façade de Gestion des Logements (Refactoring v2)
 * * Cette classe agit désormais comme un simple proxy vers la nouvelle architecture 
 * (Repository / Service) pour garantir la non-régression (Pattern Strangler).
 * * @since 0.1.4 (Création)
 * @since 2.0.0 (Refactoring en Façade)
 */
class PCR_Housing_Manager
{
    /**
     * Initialisation des hooks (si nécessaire).
     */
    public static function init()
    {
        // Hook d'initialisation redirigé si besoin plus tard
    }

    /**
     * Retourne une liste légère des logements pour le tableau dashboard.
     * * @param array $args Arguments de requête (pagination, filtres)
     * @return array
     */
    public static function get_housing_list($args = [])
    {
        return PCR_Housing_Repository::get_instance()->get_housing_list($args);
    }

    /**
     * Retourne les détails complets d'un logement avec tous ses champs.
     * * @param int $post_id ID du post
     * @return array|false
     */
    public static function get_housing_details($post_id)
    {
        return PCR_Housing_Repository::get_instance()->get_housing_details($post_id);
    }

    /**
     * Met à jour un logement avec les données fournies.
     * Accepte ID = 0 pour créer un nouveau logement.
     * * @param int $post_id ID du post (0 pour création)
     * @param array $data Données à mettre à jour
     * @return array
     */
    public static function update_housing($post_id, $data)
    {
        return PCR_Housing_Service::get_instance()->update_housing($post_id, $data);
    }

    /**
     * Supprime définitivement un logement et toutes ses données associées.
     * * @param int $post_id ID du post à supprimer
     * @return array
     */
    public static function delete_housing($post_id)
    {
        return PCR_Housing_Repository::get_instance()->delete_housing($post_id);
    }
}
