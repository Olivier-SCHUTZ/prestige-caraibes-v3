<?php

/**
 * PC Context Helper
 * Détecte le contexte de la page en cours pour optimiser le chargement conditionnel
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Context_Helper
{

    /**
     * Détermine si la page actuelle affiche une carte (Leaflet/OSM)
     * @return bool
     */
    public static function uses_map()
    {
        if (is_admin() || is_feed()) return false;

        // Pages de recherche
        if (is_page()) {
            $slug = str_replace(trailingslashit(home_url()), '/', trailingslashit(get_permalink()));
            if (strpos($slug, '/recherche-de-logements/') !== false || strpos($slug, '/recherche-dexperiences/') !== false) {
                return true;
            }
        }

        // Fiches individuelles avec carte (Logement, Villa, Appartement, Expérience)
        if (is_singular(['logement', 'villa', 'appartement', 'experience'])) {
            return true;
        }

        return false;
    }
}
