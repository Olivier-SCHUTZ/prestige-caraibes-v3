<?php
/*
Plugin Name: Système de Caution Premium Stripe
Description: Interface de caution pro avec [stripe_caution], alertes emails et suivi client.
Version: 1.2
*/

if (!defined('ABSPATH')) exit;

add_shortcode('stripe_caution', function () {
    // --- CONFIGURATION ---
    $stripe_secret_key = 'mk_1SYYqsCafDUEQpFHcnQqCCiF'; // VOTRE CLÉ PRIVÉE
    $stripe_public_key = 'pk_live_51MUbE1CafDUEQpFHkOI7lhhHnXOWI8G0IZbnAzyQ3olu0VY1NdO8SYtvkyEVz6gGBcRjkVMxUzGTl37I3HN8Q4IJ0009wIbKJ4'; // VOTRE CLÉ PUBLIQUE
    $admin_email = get_option('admin_email');
    // ---------------------

    // 1. ENVOI DE L'ALERTE EMAIL SI SUCCÈS
    if (isset($_GET['caution_status']) && $_GET['caution_status'] == 'success') {
        $amount_display = isset($_GET['amount']) ? $_GET['amount'] : 'Inconnu';
        $ref_display = isset($_GET['ref']) ? sanitize_text_field($_GET['ref']) : 'Non précisée';

        $subject = "🔒 Caution sécurisée : " . $amount_display . "€ (" . $ref_display . ")";
        $headers = array('Content-Type: text/html; charset=UTF-8');

        $message = "<h2>Nouvelle caution bloquée</h2>";
        $message .= "<p><strong>Client / Référence :</strong> " . $ref_display . "</p>";
        $message .= "<p><strong>Montant :</strong> " . $amount_display . " €</p>";
        $message .= "<p><strong>Statut :</strong> Autorisé (fonds bloqués 7 jours)</p>";
        $message .= "<hr><p>Connectez-vous à votre Dashboard Stripe pour gérer ce paiement.</p>";

        wp_mail($admin_email, $subject, $message, $headers);
    }

    // 2. PRÉPARATION DES DONNÉES
    $amount_val = isset($_GET['montant']) ? intval($_GET['montant']) : 100;
    $amount_cents = $amount_val * 100;

    $args = [
        'body' => [
            'amount' => $amount_cents,
            'currency' => 'eur',
            'capture_method' => 'manual',
        ],
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode($stripe_secret_key . ':'),
        ],
    ];

    $response = wp_remote_post('https://api.stripe.com/v1/payment_intents', $args);
    $body = json_decode(wp_remote_retrieve_body($response));

    if (isset($body->error)) return "<p style='color:red;'>Erreur de configuration Stripe.</p>";

    $client_secret = $body->client_secret;

    ob_start(); ?>

    <script src="https://js.stripe.com/v3/"></script>

    <div id="stripe-outer-container" style="display:flex; justify-content:center; padding: 20px;">
        <div id="caution-card" style="width:100%; max-width:480px; background:#ffffff; border-radius:16px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); overflow:hidden; border: 1px solid #f0f0f0;">

            <div style="background:#f8f9fa; padding:30px; border-bottom:1px solid #f0f0f0; text-align:center;">
                <div style="background:#6772e5; width:50px; height:50px; border-radius:12px; display:flex; align-items:center; justify-content:center; margin:0 auto 15px;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </div>
                <h2 style="margin:0; font-size:20px; color:#1a1f36;">Dépôt de garantie</h2>
                <p style="margin:10px 0 0; color:#4f566b; font-size:14px;">Montant à bloquer : <strong style="color:#1a1f36; font-size:18px;"><?php echo $amount_val; ?> €</strong></p>
            </div>

            <div style="padding:30px;">
                <form id="payment-form">
                    <div style="margin-bottom:20px;">
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:8px; color:#4f566b;">Référence ou Nom complet</label>
                        <input type="text" id="cust-ref" placeholder="Ex: Location Jean Dupont" required style="width:100%; padding:12px; border:1px solid #dcdfe3; border-radius:8px; font-size:15px;">
                    </div>

                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:8px; color:#4f566b;">Informations de paiement</label>
                    <div id="payment-element"></div>

                    <button id="submit" style="width:100%; background:#6772e5; color:#fff; border:0; padding:14px; border-radius:8px; font-size:16px; font-weight:600; cursor:pointer; margin-top:25px; transition: all 0.2s ease; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        Bloquer les <?php echo $amount_val; ?> €
                    </button>

                    <div id="error-message" style="color:#df1b41; margin-top:15px; font-size:14px; text-align:center;"></div>
                </form>

                <div id="success-message" style="display:none; text-align:center; padding:20px 0;">
                    <div style="color:#24b47e; font-size:48px; margin-bottom:10px;">✓</div>
                    <h3 style="margin:0; color:#1a1f36;">Caution validée</h3>
                    <p style="color:#4f566b; line-height:1.5;">Les fonds ont été sécurisés avec succès. Vous pouvez quitter cette page en toute sécurité.</p>
                </div>

                <p style="text-align:center; font-size:12px; color:#a3acb9; margin-top:25px;">
                    Paiement sécurisé par <strong>Stripe</strong>. Les fonds sont bloqués mais ne sont pas débités de votre compte à ce stade.
                </p>
            </div>
        </div>
    </div>

    <script>
        const stripe = Stripe('<?php echo $stripe_public_key; ?>');
        const elements = stripe.elements({
            clientSecret: '<?php echo $client_secret; ?>',
            appearance: {
                theme: 'flat'
            }
        });
        const paymentElement = elements.create('payment');
        paymentElement.mount('#payment-element');

        const form = document.getElementById('payment-form');
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const btn = document.getElementById('submit');
            const ref = document.getElementById('cust-ref').value;
            btn.disabled = true;
            btn.innerText = "Sécurisation...";

            const {
                error
            } = await stripe.confirmPayment({
                elements,
                confirmParams: {
                    return_url: window.location.origin + window.location.pathname + "?caution_status=success&amount=<?php echo $amount_val; ?>&ref=" + encodeURIComponent(ref),
                },
            });

            if (error) {
                document.getElementById('error-message').textContent = error.message;
                btn.disabled = false;
                btn.innerText = "Réessayer";
            }
        });
    </script>
<?php
    if (isset($_GET['caution_status']) && $_GET['caution_status'] == 'success') {
        echo "<script>document.getElementById('payment-form').style.display='none'; document.getElementById('success-message').style.display='block';</script>";
    }
    return ob_get_clean();
});
