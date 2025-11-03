<?php
/* Template 410 — Cette page n'existe plus */
status_header(410); // par sécurité, on renvoie bien 410
get_header();
?>
<main id="content" class="site-content">
  <div class="container" style="max-width: 860px; margin: 64px auto;">
    <h1 style="margin:0 0 .5em">Cette page n’existe plus</h1>
    <p style="color:#555;margin:.5em 0 1.2em">
      <?php
      // Récupère le message passé par le hook si dispo, sinon message par défaut
      $msg = get_query_var('pcseo_410_message');
      echo esc_html( $msg ?: "Le contenu a été retiré ou n’est plus disponible." );
      ?>
    </p>
    <p><a class="button" href="<?php echo esc_url( home_url('/') ); ?>">← Retour à l’accueil</a></p>
  </div>
</main>
<?php get_footer(); ?>
