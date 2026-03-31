<?php
if (!defined('ABSPATH')) {
    exit; // Sécurité : Empêche l'accès direct au fichier
}

/**
 * Couche Expédition (Dispatcher) pour la Messagerie.
 * Gère l'envoi physique des messages (ex: wp_mail) et l'habillage HTML/CSS des emails.
 * Pattern Singleton pour garantir une instance unique.
 */
class PCR_Notification_Dispatcher
{
    /**
     * @var PCR_Notification_Dispatcher Instance unique
     */
    private static $instance = null;

    /**
     * Constructeur privé (Singleton)
     */
    private function __construct() {}

    /**
     * Empêche le clonage
     */
    private function __clone() {}

    /**
     * Récupère l'instance unique du Dispatcher.
     *
     * @return PCR_Notification_Dispatcher
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Envoie un email avec le template HTML global.
     * * @param string $to Email du destinataire
     * @param string $subject Sujet technique (avec préfixe)
     * @param string $body Corps du message brut (HTML/Texte)
     * @param array  $attachments Liste des chemins absolus vers les PJ
     * @param int    $reservation_id ID de la réservation pour nettoyer le sujet
     * @return bool Succès ou échec de l'envoi
     */
    public function send_email($to, $subject, $body, $attachments = [], $reservation_id = 0)
    {
        if (!is_email($to)) {
            return false;
        }

        // Nettoyage du sujet pour l'affichage visuel (comme dans l'existant)
        $clean_subject_display = $subject;
        if ($reservation_id > 0) {
            $clean_subject_display = trim(str_replace("[#{$reservation_id}]", '', $subject));
        }

        // Génération du HTML complet avec le template
        $html_email = $this->wrap_email_html($clean_subject_display, $body);

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        // Envoi physique via WordPress
        $delivery_success = wp_mail($to, $subject, $html_email, $headers, $attachments);

        if (!$delivery_success) {
            error_log("❌ Echec envoi mail à {$to} (Sujet: {$subject})");
        }

        return $delivery_success;
    }

    /**
     * ✨ DESIGN : Habillage HTML des emails (Wrapper)
     * Récupéré à l'identique depuis l'ancienne classe pour garantir le design.
     * * @param string $subject
     * @param string $content
     * @return string Code HTML complet
     */
    private function wrap_email_html($subject, $content)
    {
        // 1. Récupération du branding (Logique en cascade avec Règle B)
        $pcr_exists = class_exists('PCR_Fields');
        $has_acf    = function_exists('get_field');

        // A. Nouveau champ général
        $logo_url = get_option('options_pc_general_logo') ?: get_option('pc_general_logo') ?: ($pcr_exists ? PCR_Fields::get('pc_general_logo', 'option') : ($has_acf ? get_field('pc_general_logo', 'option') : ''));

        // B. Fallback : Champ PDF
        if (empty($logo_url)) {
            $logo_url = get_option('options_pc_pdf_logo') ?: get_option('pc_pdf_logo') ?: ($pcr_exists ? PCR_Fields::get('pc_pdf_logo', 'option') : ($has_acf ? get_field('pc_pdf_logo', 'option') : ''));
        }

        // C. Fallback : Fichier physique spécifique (Hardcodé)
        if (empty($logo_url)) {
            $upload_dir = wp_upload_dir();
            // On vérifie si le fichier existe pour éviter une image brisée
            $fallback_path = '/wp-content/uploads/2025/03/Logo-blanc.svg';
            if (file_exists($upload_dir['basedir'] . $fallback_path)) {
                $logo_url = $upload_dir['baseurl'] . $fallback_path;
            }
        }

        $raw_primary_color = get_option('options_pc_pdf_primary_color') ?: get_option('pc_pdf_primary_color') ?: ($pcr_exists ? PCR_Fields::get('pc_pdf_primary_color', 'option') : ($has_acf ? get_field('pc_pdf_primary_color', 'option') : ''));
        $primary_color = $raw_primary_color ?: '#6366f1'; // Violet par défaut

        $bg_color      = '#f3f4f6'; // Gris très clair

        $raw_legal_name = get_option('options_pc_legal_name') ?: get_option('pc_legal_name') ?: ($pcr_exists ? PCR_Fields::get('pc_legal_name', 'option') : ($has_acf ? get_field('pc_legal_name', 'option') : ''));
        $legal_name    = $raw_legal_name ?: get_bloginfo('name');

        // 2. Construction du Template Email
        ob_start();
?>
        <!DOCTYPE html>
        <html>

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body {
                    margin: 0;
                    padding: 0;
                    font-family: 'Helvetica', 'Arial', sans-serif;
                    background-color: <?php echo $bg_color; ?>;
                    color: #374151;
                }

                .wrapper {
                    width: 100%;
                    table-layout: fixed;
                    background-color: <?php echo $bg_color; ?>;
                    padding-bottom: 40px;
                }

                .main {
                    background-color: #ffffff;
                    margin: 0 auto;
                    width: 100%;
                    max-width: 600px;
                    border-radius: 8px;
                    overflow: hidden;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
                }

                .header {
                    background-color: <?php echo $primary_color; ?>;
                    padding: 20px;
                    text-align: center;
                }

                .header img {
                    max-height: 50px;
                    width: auto;
                }

                .header h1 {
                    color: #ffffff;
                    margin: 0;
                    font-size: 20px;
                    font-weight: normal;
                }

                .content {
                    padding: 30px;
                    line-height: 1.6;
                    font-size: 15px;
                }

                .content h2 {
                    color: <?php echo $primary_color; ?>;
                    margin-top: 0;
                    font-size: 20px;
                }

                .content a {
                    color: <?php echo $primary_color; ?>;
                    text-decoration: none;
                    font-weight: bold;
                }

                .footer {
                    text-align: center;
                    padding: 20px;
                    font-size: 12px;
                    color: #9ca3af;
                }

                /* Boutons natifs dans le contenu */
                .pc-btn {
                    display: inline-block;
                    background-color: <?php echo $primary_color; ?>;
                    color: #ffffff !important;
                    padding: 12px 24px;
                    border-radius: 6px;
                    text-decoration: none;
                    margin: 10px 0;
                    font-weight: bold;
                }
            </style>
        </head>

        <body>
            <div class="wrapper">
                <div style="height: 30px;"></div>
                <div class="main">
                    <div class="header">
                        <?php if ($logo_url): ?>
                            <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($legal_name); ?>">
                        <?php else: ?>
                            <h1><?php echo esc_html($legal_name); ?></h1>
                        <?php endif; ?>
                    </div>

                    <div class="content">
                        <h2><?php echo esc_html($subject); ?></h2>

                        <?php echo $content; ?>
                    </div>
                </div>

                <div class="footer">
                    <p>&copy; <?php echo date('Y'); ?> <?php echo esc_html($legal_name); ?>. Tous droits réservés.</p>
                    <p>Ceci est un message automatique lié à votre réservation.</p>
                </div>
            </div>
        </body>

        </html>
<?php
        return ob_get_clean();
    }
}
