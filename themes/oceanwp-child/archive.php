<?php
/**
 * Ce modèle force l'affichage du contenu d'Elementor pour la page des archives (blog).
 */
get_header();

// Pour une page assignée comme "Page des articles", Elementor remplace l'appel à the_content().
the_content();

get_footer();