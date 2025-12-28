<?php
if (!defined('ABSPATH')) exit;

class PCSC_Mailer
{
    private static function get_header(): string
    {
        return '<div style="font-family: Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 10px; background-color: #ffffff;">
                <div style="text-align: center; border-bottom: 2px solid #2563eb; padding-bottom: 20px; margin-bottom: 20px;">
                    <h2 style="color: #2563eb; margin: 0;">Prestige Caraïbes</h2>
                </div>';
    }

    private static function get_footer(): string
    {
        return '<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #888; text-align: center;">
                    <p>Ceci est un message automatique concernant votre caution de location.</p>
                </div>
            </div>';
    }

    private static function send(string $to, string $subject, string $body): void
    {
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $content = self::get_header() . $body . self::get_footer();
        wp_mail($to, $subject, $content, $headers);
    }

    // --- EMAILS CLIENTS ---

    public static function send_hold_confirmation(string $email, string $ref, int $amount_cents, string $date_release_txt): void
    {
        $amount = number_format($amount_cents / 100, 2, ',', ' ');
        $body = "<p>Bonjour,</p>
                 <p>Nous vous confirmons que l'empreinte bancaire pour la caution de votre séjour (Réf: <strong>$ref</strong>) a été effectuée avec succès.</p>
                 <div style='background: #f0f9ff; border-left: 4px solid #2563eb; padding: 15px; margin: 20px 0;'>
                    <p style='margin: 0;'><strong>Montant bloqué :</strong> $amount €</p>
                    <p style='margin: 5px 0 0;'><strong>Date de libération prévue :</strong> $date_release_txt</p>
                 </div>
                 <p>Aucun montant n'a été débité. Cette somme est simplement bloquée temporairement sur votre compte.</p>
                 <p>Nous vous souhaitons un excellent séjour !</p>";

        self::send($email, "Confirmation de caution - Réf $ref", $body);
    }

    public static function send_release_confirmation(string $email, string $ref): void
    {
        $body = "<p>Bonjour,</p>
                 <p>Bonne nouvelle ! Votre caution pour le séjour <strong>$ref</strong> a été libérée.</p>
                 <div style='background: #dcfce7; border-left: 4px solid #16a34a; padding: 15px; margin: 20px 0;'>
                    <strong style='color: #166534;'>Caution relâchée avec succès.</strong>
                 </div>
                 <p>L'empreinte bancaire a été annulée. Selon votre banque, cela peut prendre quelques jours pour disparaître totalement de vos relevés.</p>
                 <p>À bientôt chez Prestige Caraïbes !</p>";

        self::send($email, "Caution libérée - Réf $ref", $body);
    }

    public static function send_capture_confirmation(string $email, string $ref, int $amount_cents, string $reason): void
    {
        $amount = number_format($amount_cents / 100, 2, ',', ' ');
        // Si aucun motif n'est donné, on met un texte par défaut
        $motif = $reason ?: "Frais de remise en état / Manquements constatés";

        $body = "<p>Bonjour,</p>
                 <p>Nous vous informons de la clôture de votre caution pour le séjour <strong>$ref</strong>.</p>
                 
                 <div style='background: #fff7ed; border-left: 4px solid #ea580c; padding: 15px; margin: 20px 0;'>
                    <p style='margin: 0; color: #9a3412; font-size: 16px;'><strong>Une retenue a été appliquée.</strong></p>
                    <ul style='margin: 10px 0 0 20px; color: #431407;'>
                        <li style='margin-bottom: 5px;'><strong>Montant débité :</strong> $amount €</li>
                        <li><strong>Motif :</strong> $motif</li>
                    </ul>
                 </div>
                 
                 <p>Le reste de votre empreinte bancaire (si applicable) a été libéré automatiquement.</p>
                 <p>Pour toute question concernant cette opération, n'hésitez pas à nous contacter.</p>
                 <p>Cordialement,<br>L'équipe Prestige Caraïbes</p>";

        self::send($email, "Clôture caution (Retenue appliquée) - Réf $ref", $body);
    }

    // --- EMAILS ADMIN ---

    public static function send_admin_card_saved(string $ref, string $email): void
    {
        $admin_email = get_option('admin_email');
        $link = admin_url('admin.php?page=pc-stripe-caution'); // Lien approximatif vers l'admin

        $body = "<h3 style='color: #d97706;'>Action requise : Carte enregistrée</h3>
                 <p>Le client ($email) a enregistré sa carte pour le dossier <strong>$ref</strong>.</p>
                 <p>Vous pouvez maintenant aller dans l'administration pour <strong>Prendre la Caution</strong>.</p>
                 <p><a href='$link' style='background: #2563eb; color: #fff; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>Accéder à l'admin</a></p>";

        self::send($admin_email, "[Admin] Carte enregistrée - $ref", $body);
    }

    public static function send_admin_reminder_release(string $ref, string $date_depart): void
    {
        $admin_email = get_option('admin_email');
        $body = "<h3 style='color: #16a34a;'>Rappel : Libération demain</h3>
                 <p>La caution du dossier <strong>$ref</strong> (Départ : $date_depart) doit être libérée demain (J+7).</p>
                 <p>Si vous ne faites rien, le système tentera de la libérer automatiquement.</p>
                 <p>Si vous devez faire une retenue (encaissement), c'est le moment !</p>";

        self::send($admin_email, "[Admin] Libération J-1 - $ref", $body);
    }
}
