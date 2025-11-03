<?php
/**
 * Ce modèle force l'affichage du contenu d'Elementor pour un article de blog.
 */
get_header();

while ( have_posts() ) :
    the_post();
    the_content();
endwhile;

get_footer();