<?php

/**
 * Interface/Classe abstraite commune pour tous les moteurs de recherche
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class PC_Search_Engine_Base
{
    /**
     * Exécute la recherche principale avec les filtres donnés
     * Doit retourner un tableau standardisé avec 'vignettes', 'map_data' et 'pagination'
     * * @param array $filters
     * @return array
     */
    abstract public function search(array $filters): array;

    /**
     * Retourne la liste des filtres disponibles pour ce moteur spécifique
     * * @return array
     */
    abstract public function get_available_filters(): array;

    /**
     * Formate un tableau de posts (WP_Post) en données spécifiques pour les vignettes
     * * @param array $posts
     * @return array
     */
    abstract protected function format_items(array $posts): array;

    /**
     * Formate un tableau de posts (WP_Post) en données pour la carte (coordonnées, prix, etc.)
     * * @param array $posts
     * @return array
     */
    abstract protected function format_map_data(array $posts): array;

    /**
     * Gère la logique commune de pagination (découpage du tableau PHP)
     * Très utile car votre système actuel récupère tous les posts (-1) puis filtre en PHP (villes, dates).
     * * @param array $final_posts Tableau des posts validés après tous les filtres
     * @param int $paged Page courante
     * @param int $posts_per_page Nombre de résultats par page (défaut 9 comme dans l'ancien code)
     * @return array
     */
    protected function paginate_and_format_results(array $final_posts, int $paged, int $posts_per_page = 9): array
    {
        $total_results = count($final_posts);
        $total_pages   = ceil($total_results / $posts_per_page);

        // Découpage du tableau pour ne garder que les résultats de la page courante
        $offset = ($paged - 1) * $posts_per_page;
        $posts_for_current_page = array_slice($final_posts, $offset, $posts_per_page);

        // On formate les vignettes uniquement pour la page courante
        $vignettes = $this->format_items($posts_for_current_page);

        // Historiquement (dans pc-ajax-search.php), map_data prend TOUS les posts filtrés, 
        // pas seulement ceux de la page courante, pour afficher toutes les épingles sur la carte.
        $map_data = $this->format_map_data($final_posts);

        return [
            'vignettes'  => $vignettes,
            'map_data'   => $map_data,
            'pagination' => [
                'current_page' => $paged,
                'total_pages'  => $total_pages,
                'total_items'  => $total_results
            ]
        ];
    }
}
