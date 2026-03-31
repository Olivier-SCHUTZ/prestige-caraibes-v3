<?php

/**
 * Gestionnaire : Traitement des formulaires de réservation (Modale) et retours de paiement
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Booking_Handler
{

    /**
     * Enregistre les hooks (actions) WordPress
     */
    public function register()
    {
        // Hooks pour la soumission du formulaire de la modale
        add_action('admin_post_nopriv_logement_booking_request', [$this, 'handle_booking_request']);
        add_action('admin_post_logement_booking_request',        [$this, 'handle_booking_request']);

        // Hook pour afficher le popup de succès Stripe sur toutes les pages si nécessaire
        add_action('wp_footer', [$this, 'render_success_popup']);
    }

    /**
     * Traite la soumission de la modale de réservation
     */
    public function handle_booking_request()
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'logement_booking_request_nonce')) {
            wp_send_json_error(['message' => 'La vérification de sécurité a échoué.']);
            return;
        }

        // === VÉRIFICATION ANTI-BOT ===
        if (!empty($_POST['booking_reason_logement'])) {
            wp_send_json_success(['message' => 'Votre demande a bien été envoyée !']);
            return;
        }

        $quote_details_raw = $_POST['quote_details'] ?? '';
        if (empty($quote_details_raw) || strpos($quote_details_raw, 'Aucune simulation') !== false) {
            wp_send_json_success(['message' => 'Votre demande a bien été envoyée !']);
            return;
        }

        // === RÉCUPÉRATION DES DONNÉES ===
        $logement_id = isset($_POST['logement_id']) ? absint($_POST['logement_id']) : 0;
        $prenom      = sanitize_text_field($_POST['prenom'] ?? '');
        $nom         = sanitize_text_field($_POST['nom'] ?? '');
        $email       = sanitize_email($_POST['email'] ?? '');
        $tel         = sanitize_text_field($_POST['tel'] ?? '');
        $adultes     = isset($_POST['adultes']) ? absint($_POST['adultes']) : 0;
        $enfants     = isset($_POST['enfants']) ? absint($_POST['enfants']) : 0;
        $bebes       = isset($_POST['bebes']) ? absint($_POST['bebes']) : 0;
        $message     = sanitize_textarea_field($_POST['message'] ?? '');
        $quote_details = sanitize_textarea_field($quote_details_raw);

        if (!$logement_id || empty($prenom) || empty($nom) || !is_email($email)) {
            wp_send_json_error(['message' => 'Veuillez remplir tous les champs obligatoires.']);
            return;
        }

        // === DÉTECTION DU MODE DE RÉSERVATION ===
        $mode_reservation = 'demande';
        if (class_exists('PCR_Fields')) {
            $setting = PCR_Fields::get('mode_reservation', $logement_id);
            if (is_array($setting)) $setting = $setting['value'] ?? ($setting[0] ?? '');
            if ($setting === 'log_directe' || $setting === 'log_direct') {
                $mode_reservation = 'directe';
            }
        }

        // === PRÉPARATION CAUTION ===
        $caution_montant = 0;
        $caution_mode    = 'aucune';

        if (class_exists('PCR_Fields')) {
            $rules = PCR_Fields::get('regles_de_paiement', $logement_id);
            if (is_array($rules)) {
                $caution_montant = isset($rules['pc_caution_amount']) ? (float) $rules['pc_caution_amount'] : 0;
                $raw_mode        = isset($rules['pc_caution_type'])   ? $rules['pc_caution_type']   : '';
                if (!empty($raw_mode)) {
                    $caution_mode = (string) $raw_mode;
                }
            }
        }

        if ($caution_montant == 0) {
            $meta_amount = get_post_meta($logement_id, 'regles_de_paiement_pc_caution_amount', true);
            if (is_numeric($meta_amount)) $caution_montant = (float) $meta_amount;
        }
        if ($caution_mode === 'aucune') {
            $meta_mode = get_post_meta($logement_id, 'regles_de_paiement_pc_caution_type', true);
            if (!empty($meta_mode)) $caution_mode = (string) $meta_mode;
        }

        // === CRÉATION DE LA RÉSERVATION ===
        $payment_url = '';
        if (class_exists('PCR_Reservation')) {
            $resa_data = [
                'type'             => 'location',
                'item_id'          => $logement_id,
                'mode_reservation' => $mode_reservation,
                'origine'          => 'site',
                'date_arrivee'     => isset($_POST['arrival']) ? sanitize_text_field($_POST['arrival']) : null,
                'date_depart'      => isset($_POST['departure']) ? sanitize_text_field($_POST['departure']) : null,
                'adultes' => $adultes,
                'enfants' => $enfants,
                'bebes'   => $bebes,
                'prenom'  => $prenom,
                'nom'     => $nom,
                'email'   => $email,
                'telephone' => $tel,
                'commentaire_client' => $message,
                'devise'           => 'EUR',
                'montant_total'    => isset($_POST['total']) ? (float) $_POST['total'] : 0,
                'detail_tarif'     => isset($_POST['lines_json']) ? wp_kses_post(wp_unslash($_POST['lines_json'])) : null,
                'caution_montant' => $caution_montant,
                'caution_mode'    => $caution_mode,
                'caution_statut'  => 'non_demande',
                'statut_reservation' => ($mode_reservation === 'directe') ? 'reservee' : 'en_attente_traitement',
                'statut_paiement'    => ($mode_reservation === 'directe') ? 'en_attente_paiement' : 'non_paye',
                'date_creation' => current_time('mysql'),
                'date_maj'      => current_time('mysql'),
            ];

            $resa_id = PCR_Reservation::create($resa_data);

            if ($resa_id && class_exists('PCR_Payment')) {
                PCR_Payment::generate_for_reservation($resa_id);

                if ($mode_reservation === 'directe' && class_exists('PCR_Stripe_Manager')) {
                    global $wpdb;
                    $table_pay = $wpdb->prefix . 'pc_payments';
                    $payment_row = $wpdb->get_row($wpdb->prepare(
                        "SELECT id, montant, type_paiement FROM {$table_pay} WHERE reservation_id = %d AND statut = 'en_attente' ORDER BY id ASC LIMIT 1",
                        $resa_id
                    ));

                    if ($payment_row && $payment_row->montant > 0) {
                        $stripe = PCR_Stripe_Manager::create_payment_link(
                            $resa_id,
                            (float)$payment_row->montant,
                            $payment_row->type_paiement
                        );
                        if ($stripe['success']) {
                            $payment_url = $stripe['url'];
                        }
                    }
                }
            }
        }

        // === NOTIFICATIONS EMAIL ===
        $logement_title = get_the_title($logement_id);

        // --- 1. Admin (Texte brut - pour l'agence) ---
        $admin_headers = ['Content-Type: text/plain; charset=UTF-8'];
        $admin_body  = "Nouvelle " . ($mode_reservation === 'directe' ? "RÉSERVATION DIRECTE" : "demande") . ".\n\n";
        $admin_body .= "Logement : " . $logement_title . "\n";
        $admin_body .= "Client : " . $prenom . " " . $nom . "\n";
        $admin_body .= "E-mail : " . $email . "\n";
        $admin_body .= "Téléphone : " . $tel . "\n\n";
        if (!empty($message)) {
            $admin_body .= "Message du client :\n" . $message . "\n\n";
        }
        $admin_body .= "Simulation :\n" . $quote_details;
        wp_mail('guadeloupe@prestigecaraibes.com', 'Nouvelle demande - ' . $logement_title, $admin_body, $admin_headers);

        // --- 2. Client (Format HTML avec belle signature) ---
        $client_headers = ['Content-Type: text/html; charset=UTF-8'];
        $client_subject = ($mode_reservation === 'directe') ? "Confirmation de réservation - Prestige Caraïbes" : "Confirmation de votre demande - Prestige Caraïbes";

        // Statut du message selon le mode
        $statut_msg = ($mode_reservation === 'directe')
            ? "Votre réservation est pré-enregistrée."
            : "Nous avons bien reçu votre demande et nous revenons vers vous très vite.";

        // Signature HTML
        $signature_html = <<<HTML
<table cellpadding="0" cellspacing="0" border="0" class="table__StyledTable-sc-1avdl6r-0 lmHSv" style="vertical-align: -webkit-baseline-middle; font-size: medium; font-family: Arial;"><tbody><tr><td><table cellpadding="0" cellspacing="0" border="0" class="table__StyledTable-sc-1avdl6r-0 lmHSv" style="vertical-align: -webkit-baseline-middle; font-size: medium; font-family: Arial;"><tbody><tr><td style="vertical-align: middle;"><h2 color="#000000" class="name__NameContainer-sc-1m457h3-0 csBPEs" style="margin: 0px; font-size: 18px; color: rgb(0, 0, 0); font-weight: 600;"><span>Marine</span><span>&nbsp;</span><span>SCHUTZ</span></h2><p color="#000000" font-size="medium" class="job-title__Container-sc-1hmtp73-0 KjlXr" style="margin: 0px; color: rgb(0, 0, 0); font-size: 14px; line-height: 22px;"><span>Gérante</span></p><p color="#000000" font-size="medium" class="company-details__CompanyContainer-sc-j5pyy8-0 cSOAsl" style="margin: 0px; font-weight: 500; color: rgb(0, 0, 0); font-size: 14px; line-height: 22px;"><span>Prestige Caraïbes</span></p></td><td width="30"><div style="width: 30px;"></div></td><td color="#B78C47" direction="vertical" width="1" height="auto" class="color-divider__Divider-sc-1h38qjv-0 bofWVx" style="width: 1px; border-bottom-width: medium; border-bottom-style: none; border-bottom-color: currentcolor; border-left-width: 1px; border-left-style: solid; border-left-color: rgb(183, 140, 71);"></td><td width="30"><div style="width: 30px;"></div></td><td style="vertical-align: middle;"><table cellpadding="0" cellspacing="0" border="0" class="table__StyledTable-sc-1avdl6r-0 lmHSv" style="vertical-align: -webkit-baseline-middle; font-size: medium; font-family: Arial;"><tbody><tr height="25" style="vertical-align: middle;"><td width="30" style="vertical-align: middle;"><table cellpadding="0" cellspacing="0" border="0" class="table__StyledTable-sc-1avdl6r-0 lmHSv" style="vertical-align: -webkit-baseline-middle; font-size: medium; font-family: Arial;"><tbody><tr><td style="vertical-align: bottom;"><span color="#B78C47" width="11" class="contact-info__IconWrapper-sc-mmkjr6-1 ldYaqt" style="display: inline-block; background-color: rgb(183, 140, 71);"><img src="https://cdn2.hubspot.net/hubfs/53/tools/email-signature-generator/icons/phone-icon-2x.png" color="#B78C47" alt="mobilePhone" width="13" class="contact-info__ContactLabelIcon-sc-mmkjr6-0 gxFfYp" style="display: block; background-color: rgb(183, 140, 71);"></span></td></tr></tbody></table></td><td style="padding: 0px; color: rgb(0, 0, 0);"><a href="tel:+590 690 63 11 81" color="#000000" class="contact-info__ExternalLink-sc-mmkjr6-2 jOTYAn" style="text-decoration: none; color: rgb(0, 0, 0); font-size: 12px;"><span>+590 690 63 11 81</span></a></td></tr><tr height="25" style="vertical-align: middle;"><td width="30" style="vertical-align: middle;"><table cellpadding="0" cellspacing="0" border="0" class="table__StyledTable-sc-1avdl6r-0 lmHSv" style="vertical-align: -webkit-baseline-middle; font-size: medium; font-family: Arial;"><tbody><tr><td style="vertical-align: bottom;"><span color="#B78C47" width="11" class="contact-info__IconWrapper-sc-mmkjr6-1 ldYaqt" style="display: inline-block; background-color: rgb(183, 140, 71);"><img src="https://cdn2.hubspot.net/hubfs/53/tools/email-signature-generator/icons/email-icon-2x.png" color="#B78C47" alt="emailAddress" width="13" class="contact-info__ContactLabelIcon-sc-mmkjr6-0 gxFfYp" style="display: block; background-color: rgb(183, 140, 71);"></span></td></tr></tbody></table></td><td style="padding: 0px;"><a href="mailto:guadeloupe@prestigecaraibes.com" color="#000000" class="contact-info__ExternalLink-sc-mmkjr6-2 jOTYAn" style="text-decoration: none; color: rgb(0, 0, 0); font-size: 12px;"><span>guadeloupe@prestigecaraibes.com</span></a></td></tr><tr height="25" style="vertical-align: middle;"><td width="30" style="vertical-align: middle;"><table cellpadding="0" cellspacing="0" border="0" class="table__StyledTable-sc-1avdl6r-0 lmHSv" style="vertical-align: -webkit-baseline-middle; font-size: medium; font-family: Arial;"><tbody><tr><td style="vertical-align: bottom;"><span color="#B78C47" width="11" class="contact-info__IconWrapper-sc-mmkjr6-1 ldYaqt" style="display: inline-block; background-color: rgb(183, 140, 71);"><img src="https://cdn2.hubspot.net/hubfs/53/tools/email-signature-generator/icons/link-icon-2x.png" color="#B78C47" alt="website" width="13" class="contact-info__ContactLabelIcon-sc-mmkjr6-0 gxFfYp" style="display: block; background-color: rgb(183, 140, 71);"></span></td></tr></tbody></table></td><td style="padding: 0px;"><a href="https://prestigecaraibes.com" color="#000000" class="contact-info__ExternalLink-sc-mmkjr6-2 jOTYAn" style="text-decoration: none; color: rgb(0, 0, 0); font-size: 12px;"><span>prestigecaraibes.com</span></a></td></tr></tbody></table></td></tr></tbody></table></td></tr><tr><td><table cellpadding="0" cellspacing="0" border="0" class="table__StyledTable-sc-1avdl6r-0 lmHSv" style="width: 100%; vertical-align: -webkit-baseline-middle; font-size: medium; font-family: Arial;"><tbody><tr><td height="30"></td></tr><tr><td color="#B78C47" direction="horizontal" width="auto" height="1" class="color-divider__Divider-sc-1h38qjv-0 bofWVx" style="width: 100%; border-bottom-width: 1px; border-bottom-style: solid; border-bottom-color: rgb(183, 140, 71); border-left-width: medium; border-left-style: none; border-left-color: currentcolor; display: block;"></td></tr><tr><td height="30"></td></tr></tbody></table></td></tr><tr><td><table cellpadding="0" cellspacing="0" border="0" class="table__StyledTable-sc-1avdl6r-0 lmHSv" style="width: 100%; vertical-align: -webkit-baseline-middle; font-size: medium; font-family: Arial;"><tbody><tr><td style="vertical-align: top;"><img src="https://prestigecaraibes.com/wp-content/uploads/2025/06/Logo-Prestige-Caraibes-bleu.png" alt="Logo Prestige Caraibes" role="presentation" width="230" class="image__StyledImage-sc-hupvqm-0 fOIYAq" style="display: inline-block; max-width: 230px;"></td><td style="text-align: right; vertical-align: top;"><table cellpadding="0" cellspacing="0" border="0" class="table__StyledTable-sc-1avdl6r-0 lmHSv" style="display: inline-block; vertical-align: -webkit-baseline-middle; font-size: medium; font-family: Arial;"><tbody><tr style="text-align: right;"><td><a href="https://www.facebook.com/PrestigeCaraibes/" color="#7075db" class="social-links__LinkAnchor-sc-py8uhj-2 iWFMIm" style="display: inline-block; padding: 0px; background-color: rgb(112, 117, 219);"><img src="https://cdn2.hubspot.net/hubfs/53/tools/email-signature-generator/icons/facebook-icon-2x.png" alt="facebook" color="#7075db" width="24" class="social-links__LinkImage-sc-py8uhj-1 cgBQhD" style="background-color: rgb(112, 117, 219); max-width: 135px; display: block;"></a></td><td width="5"><div></div></td><td><a href="https://www.instagram.com/PrestigeCaraibes/" color="#C32AA3" class="social-links__LinkAnchor-sc-py8uhj-2 iWFMIm" style="display: inline-block; padding: 0px; background-color: rgb(112, 117, 219);"><img src="https://cdn2.hubspot.net/hubfs/53/tools/email-signature-generator/icons/instagram-icon-2x.png" alt="instagram" color="#7075db" width="24" class="social-links__LinkImage-sc-py8uhj-1 cgBQhD" style="background-color: rgb(112, 117, 219); max-width: 135px; display: block;"></a></td><td width="5"><div></div></td></tr></tbody></table></td></tr></tbody></table></td></tr><tr><td><table cellpadding="0" cellspacing="0" border="0" class="table__StyledTable-sc-1avdl6r-0 lmHSv" style="width: 100%; vertical-align: -webkit-baseline-middle; font-size: medium; font-family: Arial;"><tbody><tr><td height="15"></td></tr><tr><td><a textcolor="#000000" href="https://prestigecaraibes.com" target="_blank" rel="noopener noreferrer" class="viral-link__Anchor-sc-1kv0kjx-0 fGduiD" style="font-size: 12px; display: block; color: rgb(0, 0, 0);"></a></td><td style="text-align: right;"><span style="display: block; text-align: right;"><a target="_blank" rel="noopener noreferrer" href="https://api.whatsapp.com/send?phone=590690631181" isimage="" color="#183C3C" textcolor="#ffffff" alignment="right" class="cta__CtaButton-sc-sq0d6i-0 hpjtwR" style="border-width: 6px 12px; border-style: solid; border-color: rgb(24, 60, 60); display: inline-block; background-color: rgb(24, 60, 60); color: rgb(255, 255, 255); font-weight: 700; text-decoration: none; text-align: center; line-height: 40px; font-size: 12px; border-radius: 3px;">Nous contacter sur WhatsApp</a></span></td></tr></tbody></table></td></tr></tbody></table>
HTML;

        // Corps complet de l'e-mail client en HTML
        $client_body = <<<HTML
<html>
<head></head>
<body style="font-family: Arial, sans-serif; color: #333333; line-height: 1.6; max-width: 650px; margin: 0 auto; padding: 20px;">
    <p>Bonjour <strong>{$prenom}</strong>,</p>
    <p>Merci pour votre demande concernant le logement <strong>{$logement_title}</strong>.</p>
    <p>{$statut_msg}</p>
    <br>
    <p>L'équipe Prestige Caraïbes reste à votre entière disposition.</p>
    <br>
    {$signature_html}
</body>
</html>
HTML;

        if ($email) {
            wp_mail($email, $client_subject, $client_body, $client_headers);
        }

        // === RÉPONSE JSON ===
        $response = ['message' => 'Votre demande a bien été envoyée ! Un email de confirmation vous a été adressé.'];
        if (!empty($payment_url)) {
            $response['payment_url'] = $payment_url;
            $response['message'] = 'Redirection vers le paiement...';
        }

        wp_send_json_success($response);
        exit;
    }

    /**
     * Affiche le popup de remerciement après un retour de paiement Stripe
     */
    public function render_success_popup()
    {
        if (!isset($_GET['pc_payment_return']) || $_GET['pc_payment_return'] !== 'success') {
            return;
        }

        $link_experiences = home_url('/recherche-dexperiences/');
?>
        <div id="pc-success-modal" class="pc-success-overlay">
            <div class="pc-success-box">
                <div class="pc-success-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 6L9 17l-5-5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
                <h3>Merci pour votre commande !</h3>
                <p>
                    Votre paiement a bien été validé et votre réservation est confirmée.<br>
                    Vous allez recevoir un email récapitulatif dans quelques instants.
                </p>
                <div class="pc-success-actions">
                    <a href="<?php echo esc_url($link_experiences); ?>" class="pc-btn pc-btn--primary">
                        Visitez nos expériences
                    </a>
                    <button type="button" class="pc-btn pc-btn--ghost" id="pc-success-close">
                        Continuer la visite
                    </button>
                </div>
            </div>
        </div>

        <style>
            .pc-success-overlay {
                position: fixed;
                inset: 0;
                background-color: rgba(14, 43, 92, 0.7);
                backdrop-filter: blur(5px);
                z-index: 999999;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                animation: pcFadeIn 0.4s ease-out;
            }

            .pc-success-box {
                background: #fff;
                padding: 40px 30px;
                border-radius: 16px;
                max-width: 480px;
                width: 100%;
                text-align: center;
                box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
                animation: pcSlideUp 0.5s cubic-bezier(0.16, 1, 0.3, 1);
            }

            .pc-success-icon {
                width: 80px;
                height: 80px;
                background: #dcfce7;
                color: #16a34a;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 24px;
            }

            .pc-success-icon svg {
                width: 40px;
                height: 40px;
            }

            .pc-success-box h3 {
                margin: 0 0 16px;
                font-family: inherit;
                font-size: 1.6rem;
                color: #0f172a;
                font-weight: 700;
            }

            .pc-success-box p {
                color: #64748b;
                line-height: 1.6;
                margin-bottom: 32px;
                font-size: 1.05rem;
            }

            .pc-success-actions {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            .pc-success-box .pc-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 100%;
                padding: 14px 24px;
                font-size: 1rem;
                font-weight: 600;
                border-radius: 99px;
                text-decoration: none;
                cursor: pointer;
                transition: all 0.2s;
                box-sizing: border-box;
            }

            .pc-success-box .pc-btn--primary {
                background-color: #0e2b5c;
                color: #ffffff !important;
                border: 1px solid #0e2b5c;
            }

            .pc-success-box .pc-btn--primary:hover {
                background-color: #1a3c75;
                transform: translateY(-1px);
            }

            .pc-success-box .pc-btn--ghost {
                background-color: transparent;
                border: 1px solid #cbd5e1;
                color: #475569 !important;
            }

            .pc-success-box .pc-btn--ghost:hover {
                background-color: #f8fafc;
                border-color: #94a3b8;
                color: #0f172a !important;
            }

            @keyframes pcFadeIn {
                from {
                    opacity: 0;
                }

                to {
                    opacity: 1;
                }
            }

            @keyframes pcSlideUp {
                from {
                    transform: translateY(30px);
                    opacity: 0;
                }

                to {
                    transform: translateY(0);
                    opacity: 1;
                }
            }
        </style>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var modal = document.getElementById('pc-success-modal');
                var closeBtn = document.getElementById('pc-success-close');
                if (closeBtn && modal) {
                    closeBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        modal.style.opacity = '0';
                        setTimeout(function() {
                            modal.style.display = 'none';
                        }, 300);
                        if (window.history && window.history.pushState) {
                            var cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                            window.history.pushState({
                                path: cleanUrl
                            }, '', cleanUrl);
                        }
                    });
                }
            });
        </script>
<?php
    }
}
