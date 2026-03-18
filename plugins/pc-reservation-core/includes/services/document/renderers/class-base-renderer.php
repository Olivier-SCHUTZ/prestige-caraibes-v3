<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PCR_Base_Document_Renderer
 * * Classe abstraite parente pour tous les rendus de documents PDF.
 * Fournit le CSS partagé et les utilitaires d'encodage d'images pour Dompdf.
 */
abstract class PCR_Base_Document_Renderer
{
    /**
     * Méthode principale que chaque classe enfant devra obligatoirement implémenter.
     *
     * @param object $resa       L'objet réservation.
     * @param string $doc_number Le numéro généré du document.
     * @param array  $args       Arguments supplémentaires (template_id, type_doc, etc.).
     * @return string            Le code HTML complet prêt à être envoyé à Dompdf.
     */
    abstract public function render($resa, $doc_number, $args = []);

    /**
     * Fournit le socle CSS commun à tous les documents PDF.
     *
     * @param string $color La couleur principale définie dans les réglages.
     * @return string Code HTML <style>
     */
    protected function get_common_css($color)
    {
        return "
            <style>
                @page { margin: 40px; }
                body { font-family: Helvetica, sans-serif; font-size: 11px; color: #333; line-height: 1.4; }
                h1, h2, h3, h4 { color: #000; margin: 0 0 10px 0; }
                
                img { image-rendering: auto; }
                .clear { clear: both; }
                .left { float: left; }
                .right { float: right; text-align: right; }
                .text-center { text-align: center; }
                .bold { font-weight: bold; }
                .uppercase { text-transform: uppercase; }
                .primary-color { color: $color; }
                .bg-primary { background-color: $color; color: #fff; }

                /* Header */
                .header { margin-bottom: 30px; border-bottom: 2px solid $color; padding-bottom: 20px; }
                .logo { max-height: 70px; max-width: 200px; }
                .doc-info { text-align: right; }
                .doc-type { font-size: 24px; font-weight: bold; text-transform: uppercase; color: $color; }
                .doc-meta { font-size: 12px; margin-top: 5px; }

                /* Addresses */
                .addresses { width: 100%; margin-bottom: 40px; }
                .addr-box { width: 45%; float: left; }
                .addr-box.client { float: right; background: #f9f9f9; padding: 15px; border-radius: 5px; }
                .addr-title { font-weight: bold; text-transform: uppercase; font-size: 10px; color: #777; margin-bottom: 5px; border-bottom: 1px solid #ddd; padding-bottom: 3px; }

                /* Tables */
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th { background: #eee; text-align: left; padding: 8px; font-size: 10px; text-transform: uppercase; border-bottom: 2px solid #ccc; font-weight: bold; }
                td { padding: 8px; border-bottom: 1px solid #eee; font-size: 11px; }
                .col-num { text-align: right; }
                .col-desc { width: 50%; }

                /* Totals */
                .totals-wrap { width: 100%; margin-top: 10px; }
                .totals-table { width: 40%; float: right; border: 1px solid #eee; }
                .totals-table td { padding: 5px 10px; border-bottom: 1px solid #eee; }
                .total-final { background: $color; color: #fff; font-weight: bold; font-size: 13px; }

                /* Bank Box */
                .bank-box { border: 1px solid #ccc; padding: 10px; margin-top: 30px; font-size: 10px; page-break-inside: avoid; }
                .bank-title { font-weight: bold; border-bottom: 1px solid #ccc; margin-bottom: 5px; display: block; }

                /* Footer */
                .footer { position: fixed; bottom: -30px; left: 0; right: 0; height: 30px; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #eee; padding-top: 5px; }
                
                /* TAMPON RÉGLÉ */
                .stamp-box {
                    position: absolute;
                    top: 250px; left: 40%;
                    transform: rotate(-15deg);
                    border: 3px solid #22c55e;
                    color: #22c55e;
                    font-size: 20px; font-weight: bold; text-transform: uppercase;
                    padding: 10px 20px; border-radius: 10px;
                    opacity: 0.8; z-index: 999;
                }
                .stamp-box.partial { border-color: #eab308; color: #eab308; }
            </style>
        ";
    }

    /**
     * Convertit une URL d'image en flux Base64 pour contourner les blocages de sécurité de Dompdf.
     *
     * @param string $url L'URL publique de l'image.
     * @return string     La chaîne Base64 formatée pour la balise <img src="...">.
     */
    protected function get_image_base64($url)
    {
        if (empty($url)) {
            return '';
        }

        $upload_dir = wp_upload_dir();
        $path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);
        $data = '';

        if (file_exists($path)) {
            $data = file_get_contents($path);
        } else {
            // Utilisation de @ pour éviter les warnings PHP si l'URL externe échoue
            $data = @file_get_contents($url);
        }

        if (!$data) {
            return '';
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = ($ext === 'png') ? 'image/png' : 'image/jpeg';

        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }

    /**
     * Échappe les données dynamiques avant de les injecter dans le HTML pour DomPDF.
     * Empêche les caractères spéciaux (ex: &) ou le code malveillant de casser le rendu PDF.
     *
     * @param string|null $data La chaîne à sécuriser.
     * @return string           La chaîne sécurisée.
     */
    protected function escapeForPdf($data)
    {
        if (empty($data)) {
            return '';
        }
        // ENT_QUOTES | ENT_HTML5 convertit les guillemets et utilise les entités HTML5
        return htmlspecialchars((string) $data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
