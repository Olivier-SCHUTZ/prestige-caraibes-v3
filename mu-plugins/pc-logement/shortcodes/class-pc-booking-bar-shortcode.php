<?php

/**
 * Composant Shortcode : Barre Sticky de Réservation [pc_booking_bar]
 * (Génère le FAB, la Bottom-Sheet et la Modale de contact)
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Booking_Bar_Shortcode extends PC_Shortcode_Base
{

    protected $tag = 'pc_booking_bar';

    /**
     * Rendu principal du shortcode
     */
    public function render($atts, $content = null)
    {
        if (!is_singular(['villa', 'appartement', 'logement']) || is_page(['reserver', 'demande-sejour'])) {
            return '';
        }

        $post_id = get_the_ID();
        if (!$post_id || !function_exists('get_field')) {
            return '';
        }

        // Récupération des données nécessaires
        $lodgify_embed = get_field('lodgify_widget_embed', $post_id);
        $has_lodgify   = !empty(trim((string)$lodgify_embed));
        $price         = get_field('base_price_from', $post_id);

        ob_start();

        // 1. Le bouton flottant (FAB)
        $this->render_fab($price);

        // 2. La Bottom-Sheet (tiroir)
        $this->render_bottom_sheet();

        // 3. La Modale de Contact (uniquement si pas de Lodgify)
        if (!$has_lodgify) {
            $this->render_contact_modal($post_id);
        }

        return ob_get_clean();
    }

    /**
     * Pas d'assets spécifiques à charger ici, gérés par le JS global
     */
    protected function enqueue_assets()
    {
        return;
    }

    /**
     * Helper : Affiche le Floating Action Button (Pilule)
     */
    private function render_fab($price)
    {
?>
        <button type="button" class="exp-booking-fab" id="logement-open-devis-sheet-btn">
            <span id="fab-logement-price-display">
                <?php if ($price): ?>
                    À partir de <?php echo esc_html(number_format_i18n($price, 0)) . '€'; ?>
                <?php else: ?>
                    Estimer le séjour
                <?php endif; ?>
            </span>
        </button>
    <?php
    }

    /**
     * Helper : Affiche la structure de la Bottom Sheet (Tiroir)
     */
    private function render_bottom_sheet()
    {
    ?>
        <div class="exp-devis-sheet" id="logement-devis-sheet" role="dialog" aria-modal="true" aria-labelledby="logement-devis-sheet-title" aria-hidden="true">
            <div class="exp-devis-sheet__overlay" data-close-devis-sheet></div>
            <div class="exp-devis-sheet__content" role="document">
                <div class="exp-devis-sheet__header">
                    <h3 class="exp-devis-sheet__title" id="logement-devis-sheet-title">Estimez votre séjour</h3>
                    <button class="exp-devis-sheet__close" aria-label="Fermer" data-close-devis-sheet>×</button>
                </div>
                <div class="exp-devis-sheet__body" id="logement-devis-sheet-body">
                </div>
            </div>
        </div>
    <?php
    }

    /**
     * Helper : Affiche la Modale de Contact avec son formulaire
     */
    private function render_contact_modal($post_id)
    {
    ?>
        <div class="exp-booking-modal is-hidden" id="logement-booking-modal" role="dialog" aria-modal="true">
            <div class="exp-booking-modal__overlay" data-close-modal></div>
            <div class="exp-booking-modal__content">
                <button class="exp-booking-modal__close" aria-label="Fermer" data-close-modal>×</button>
                <h3 class="exp-booking-modal__title">Réserver maintenant</h3>

                <form id="logement-booking-form" method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="logement_booking_request">
                    <input type="hidden" name="logement_id" value="<?php echo esc_attr($post_id); ?>">
                    <?php wp_nonce_field('logement_booking_request_nonce', 'nonce'); ?>

                    <p class="honeypot-field" style="display:none !important; visibility:hidden !important; opacity:0 !important; height:0 !important; width:0 !important; position:absolute !important; left:-9999px !important;" aria-hidden="true">
                        <label for="booking-reason-logement">Motif</label>
                        <input type="text" id="booking-reason-logement" name="booking_reason_logement" tabindex="-1" autocomplete="off">
                    </p>

                    <fieldset class="exp-booking-fieldset">
                        <legend class="visually-hidden">Votre simulation</legend>
                        <h3 class="exp-booking-fieldset-title">Votre simulation</h3>
                        <div id="modal-quote-summary-logement"></div>
                        <textarea name="quote_details" id="modal-quote-details-hidden-logement" style="display:none;"></textarea>
                    </fieldset>

                    <fieldset class="exp-booking-fieldset">
                        <legend class="visually-hidden">Vos coordonnées</legend>
                        <h3 class="exp-booking-fieldset-title">Vos coordonnées</h3>
                        <div class="exp-booking-form-grid">
                            <div class="exp-booking-field"><label for="booking-prenom-logement">Prénom*</label><input type="text" id="booking-prenom-logement" name="prenom" required></div>
                            <div class="exp-booking-field"><label for="booking-nom-logement">Nom*</label><input type="text" id="booking-nom-logement" name="nom" required></div>
                            <div class="exp-booking-field"><label for="booking-email-logement">Email*</label><input type="email" id="booking-email-logement" name="email" required></div>
                            <div class="exp-booking-field"><label for="booking-tel-logement">Téléphone*</label><input type="text" id="booking-tel-logement" name="tel" required></div>
                        </div>
                    </fieldset>

                    <fieldset class="exp-booking-fieldset">
                        <legend class="visually-hidden">Informations supplémentaires</legend>
                        <h3 class="exp-booking-fieldset-title">Informations supplémentaires</h3>
                        <div class="exp-booking-field">
                            <label for="booking-message-logement" class="visually-hidden">Votre message</label>
                            <textarea id="booking-message-logement" name="message" rows="3" placeholder="Avez-vous des questions ou des demandes particulières ?"></textarea>
                        </div>
                    </fieldset>

                    <div class="exp-booking-modal__actions">
                        <p class="exp-booking-disclaimer">Cette demande est sans engagement.</p>
                        <button type="submit" class="pc-btn pc-btn--primary">Envoyer la demande</button>
                    </div>
                </form>
            </div>
        </div>
<?php
    }
}
