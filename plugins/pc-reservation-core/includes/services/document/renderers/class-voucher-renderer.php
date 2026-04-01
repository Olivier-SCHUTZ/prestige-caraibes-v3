<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PCR_Voucher_Renderer
 * * Gère le rendu HTML/PDF spécifique pour le Bon d'échange (Voucher).
 */
class PCR_Voucher_Renderer extends PCR_Base_Document_Renderer
{
    /**
     * Rendu du voucher.
     *
     * @param object $resa       L'objet réservation.
     * @param string $doc_number Le numéro de document.
     * @param array  $args       Arguments supplémentaires.
     * @return string            HTML complet.
     */
    public function render($resa, $doc_number, $args = [])
    {
        $logo_id  = PCR_Fields::get('pc_pdf_logo', 'option', '');
        $logo_url = is_numeric($logo_id) ? wp_get_attachment_url($logo_id) : $logo_id;
        $logo     = $this->get_image_base64($logo_url);

        $color = PCR_Fields::get('pc_pdf_primary_color', 'option', '#000000') ?: '#000000';

        $adresse_lieu = PCR_Fields::get('adresse_logement', $resa->item_id, 'Adresse non communiquée') ?: 'Adresse non communiquée';
        ob_start();
?>
        <!DOCTYPE html>
        <html>

        <head>
            <meta charset="UTF-8">
            <?php echo $this->get_common_css($color); ?>
            <style>
                .voucher-box {
                    border: 2px dashed #ccc;
                    padding: 20px;
                    background: #fdfdfd;
                    margin-bottom: 20px;
                }
            </style>
        </head>

        <body>
            <div class="text-center" style="margin-bottom:30px;">
                <?php if ($logo): ?><img src="<?php echo $logo; ?>" style="height:60px;"><?php endif; ?>
                <h1 style="color:<?php echo $color; ?>; margin-top:10px;">BON D'ÉCHANGE (VOUCHER)</h1>
                <p>N° <?php echo $doc_number; ?></p>
            </div>

            <div class="voucher-box">
                <h3 class="uppercase" style="border-bottom:1px solid #ddd; padding-bottom:5px;">Détails de la réservation</h3>
                <table style="margin-top:15px;">
                    <tr>
                        <td width="30%"><strong>Client :</strong></td>
                        <td><?php echo $this->escapeForPdf(($resa->prenom ?? '') . ' ' . strtoupper($resa->nom ?? '')); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Activité / Logement :</strong></td>
                        <td><?php echo $this->escapeForPdf(get_the_title($resa->item_id ?? 0)); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Participants :</strong></td>
                        <td><?php echo $resa->adultes ?? 0; ?> Adultes, <?php echo $resa->enfants ?? 0; ?> Enfants</td>
                    </tr>
                </table>
            </div>

            <div class="voucher-box" style="border-style:solid; border-color:<?php echo $color; ?>;">
                <h3 class="uppercase" style="color:<?php echo $color; ?>;">Votre arrivée</h3>
                <table style="margin-top:10px;">
                    <tr>
                        <td width="50%" style="border:none;">
                            <strong>DÉBUT (CHECK-IN)</strong><br>
                            <span style="font-size:14px;"><?php echo date_i18n('d/m/Y', strtotime($resa->date_arrivee)); ?></span><br>
                            À partir de 16h00
                        </td>
                        <td width="50%" style="border:none;">
                            <strong>FIN (CHECK-OUT)</strong><br>
                            <span style="font-size:14px;"><?php echo date_i18n('d/m/Y', strtotime($resa->date_depart)); ?></span><br>
                            Avant 11h00
                        </td>
                    </tr>
                </table>
                <hr style="border:0; border-top:1px dashed #ccc; margin:15px 0;">
                <strong>Lieu de rendez-vous / Adresse :</strong><br>
                <?php echo nl2br($this->escapeForPdf($adresse_lieu ?? '')); ?>
            </div>

            <div class="text-center" style="margin-top:50px;">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=RESA-<?php echo $resa->id; ?>" style="width:120px;">
                <p style="font-size:10px; color:#666;">Présentez ce code lors de votre arrivée.</p>
            </div>
        </body>

        </html>
<?php
        return ob_get_clean();
    }
}
