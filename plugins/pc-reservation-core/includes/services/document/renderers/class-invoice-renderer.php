<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PCR_Invoice_Renderer
 * * Gère le rendu HTML/PDF pour les Factures, Devis et Avoirs.
 */
class PCR_Invoice_Renderer extends PCR_Base_Document_Renderer
{
    /**
     * Rendu principal pour Facture, Devis et Avoir standard.
     *
     * @param object $resa       L'objet réservation.
     * @param string $doc_number Le numéro de document.
     * @param array  $args       Doit contenir 'type_doc' et 'template_id'.
     * @return string            HTML complet.
     */
    public function render($resa, $doc_number, $args = [])
    {
        $type_doc    = $args['type_doc'] ?? 'facture';
        $template_id = $args['template_id'] ?? 0;

        $logo_url = get_field('pc_pdf_logo', 'option');
        $logo     = $this->get_image_base64($logo_url);
        $color    = get_field('pc_pdf_primary_color', 'option') ?: '#000000';
        $company  = [
            'name'    => get_field('pc_legal_name', 'option'),
            'address' => get_field('pc_legal_address', 'option'),
            'email'   => get_field('pc_legal_email', 'option'),
            'phone'   => get_field('pc_legal_phone', 'option'),
            'siret'   => get_field('pc_legal_siret', 'option'),
            'tva'     => get_field('pc_legal_tva', 'option'),
        ];
        $bank = [
            'name' => get_field('pc_bank_name', 'option'),
            'iban' => get_field('pc_bank_iban', 'option'),
            'bic'  => get_field('pc_bank_bic', 'option'),
        ];

        // 🎯 Appel de notre nouveau service de calcul financier
        $fin = PCR_Document_Financial_Calculator::get_instance()->calculate_for_reservation($resa);

        $titles = ['facture' => 'Facture', 'devis' => 'Devis', 'avoir' => 'Avoir'];
        $doc_title = $titles[$type_doc] ?? 'Document';
        $date_label = ($type_doc === 'devis') ? "Date d'émission" : "Date de facture";

        ob_start();
?>
        <!DOCTYPE html>
        <html>

        <head>
            <meta charset="UTF-8">
            <?php echo $this->get_common_css($color); ?>
        </head>

        <body>
            <?php
            if ($type_doc === 'facture' && $fin['deja_paye'] > 0) {
                if ($fin['reste_a_payer'] < 0.10) {
                    $date_stamp = $fin['date_dernier_paiement'] ? date_i18n('d/m/Y', strtotime($fin['date_dernier_paiement'])) : date('d/m/Y');
                    echo '<div class="stamp-box">RÉGLÉ LE ' . $date_stamp . '</div>';
                } else {
                    echo '<div class="stamp-box partial">PARTIELLEMENT RÉGLÉ</div>';
                }
            }
            ?>

            <div class="header">
                <div class="left">
                    <?php if ($logo): ?><img src="<?php echo $logo; ?>" class="logo"><?php else: ?><h2><?php echo $company['name']; ?></h2><?php endif; ?>
                </div>
                <div class="right doc-info">
                    <div class="doc-type"><?php echo $doc_title; ?></div>
                    <div class="doc-meta">
                        <strong>N° :</strong> <?php echo $doc_number; ?><br>
                        <strong><?php echo $date_label; ?> :</strong> <?php echo date_i18n('d/m/Y'); ?><br>
                        <strong>Réf. Résa :</strong> #<?php echo $resa->id; ?>
                    </div>
                </div>
                <div class="clear"></div>
            </div>

            <div class="addresses">
                <div class="addr-box">
                    <div class="addr-title">Émetteur</div>
                    <strong><?php echo $company['name']; ?></strong><br>
                    <?php echo nl2br($company['address']); ?><br><br>
                    Tél : <?php echo $company['phone']; ?><br>
                    Email : <?php echo $company['email']; ?>
                </div>
                <div class="addr-box client">
                    <div class="addr-title">Facturé à</div>
                    <strong><?php echo $resa->prenom . ' ' . strtoupper($resa->nom); ?></strong><br>
                    Email : <?php echo $resa->email; ?><br>
                    Tél : <?php echo $resa->telephone; ?>
                </div>
                <div class="clear"></div>
            </div>

            <div style="margin-bottom: 20px; padding: 10px; background: #eee; font-size: 11px;">
                <strong>Objet :</strong> <?php echo ($resa->type === 'location') ? 'Séjour Location' : 'Expérience'; ?>
                - <?php echo get_the_title($resa->item_id); ?><br>
                <strong>Dates :</strong> Du <?php echo date_i18n('d/m/Y', strtotime($resa->date_arrivee)); ?>
                au <?php echo date_i18n('d/m/Y', strtotime($resa->date_depart)); ?>
                (<?php echo $resa->adultes; ?> Adultes, <?php echo $resa->enfants; ?> Enfants)
            </div>

            <table>
                <thead>
                    <tr>
                        <th class="col-desc" width="40%">Description</th>
                        <th class="col-num" width="10%">Qté</th>
                        <th class="col-num" width="15%">P.U. HT</th>
                        <th class="col-num" width="10%">TVA</th>
                        <th class="col-num" width="25%">Total TTC</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fin['lines'] as $line): ?>
                        <tr>
                            <td>
                                <?php echo $line['description']; ?>
                                <?php if ($line['taux_tva'] == 0): ?><br><em style="font-size:9px;color:#666;">(Exonéré de TVA)</em><?php endif; ?>
                            </td>
                            <td class="col-num" style="text-align:center;"><?php echo $line['quantity']; ?></td>
                            <td class="col-num"><?php echo number_format($line['unit_ht'], 2, ',', ' '); ?> €</td>
                            <td class="col-num" style="text-align:center;">
                                <?php echo ($line['taux_tva'] > 0) ? $line['taux_tva'] . '%' : '-'; ?>
                            </td>
                            <td class="col-num bold"><?php echo number_format($line['total_ttc'], 2, ',', ' '); ?> €</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="totals-wrap">
                <table class="totals-table">
                    <tr>
                        <td>Total HT</td>
                        <td class="col-num"><?php echo number_format($fin['total_ht'], 2, ',', ' '); ?> €</td>
                    </tr>
                    <tr>
                        <td>Total TVA</td>
                        <td class="col-num"><?php echo number_format($fin['total_tva'], 2, ',', ' '); ?> €</td>
                    </tr>
                    <tr class="total-final">
                        <td style="border:none;">NET À PAYER (TTC)</td>
                        <td style="border:none;" class="col-num"><?php echo number_format($fin['total_ttc'], 2, ',', ' '); ?> €</td>
                    </tr>
                    <?php if ($type_doc === 'facture' && $fin['deja_paye'] > 0): ?>
                        <tr>
                            <td style="color:green;">Déjà réglé</td>
                            <td class="col-num" style="color:green;">- <?php echo number_format($fin['deja_paye'], 2, ',', ' '); ?> €</td>
                        </tr>
                        <tr>
                            <td class="bold">Reste à payer</td>
                            <td class="col-num bold"><?php echo number_format($fin['reste_a_payer'], 2, ',', ' '); ?> €</td>
                        </tr>
                    <?php endif; ?>
                </table>
                <div class="clear"></div>
            </div>

            <?php if ($type_doc === 'facture' && !empty($bank['iban'])): ?>
                <div class="bank-box">
                    <span class="bank-title">INFORMATIONS DE PAIEMENT (VIREMENT)</span>
                    <table style="width:100%; margin:0; border:none;">
                        <tr>
                            <td style="border:none; padding:2px;"><strong>Banque :</strong> <?php echo $bank['name']; ?></td>
                            <td style="border:none; padding:2px;"><strong>BIC/SWIFT :</strong> <?php echo $bank['bic']; ?></td>
                        </tr>
                        <tr>
                            <td colspan="2" style="border:none; padding:2px;"><strong>IBAN :</strong> <?php echo $bank['iban']; ?></td>
                        </tr>
                    </table>
                </div>
            <?php endif; ?>

            <div class="footer">
                <?php echo $company['name']; ?> - SIRET : <?php echo $company['siret']; ?> - TVA : <?php echo $company['tva']; ?><br>
                Document généré le <?php echo date('d/m/Y H:i'); ?>
            </div>

            <?php
            $cgv_content = '';

            // 1. Détection automatique selon le type de réservation
            if (isset($resa->type) && $resa->type === 'location') {
                $cgv_content = get_field('cgv_location', 'option');
            } elseif (isset($resa->type) && $resa->type === 'experience') {
                $cgv_content = get_field('cgv_experience', 'option');
            } else {
                $cgv_content = get_field('cgv_sejour', 'option');
            }

            // 2. Fallback : Si c'est un modèle personnalisé
            if (!empty($template_id) && is_numeric($template_id) && $template_id > 0) {
                $custom_cgv = get_field('pc_linked_cgv', $template_id);
                if (!empty($custom_cgv)) {
                    $cgv_content = $custom_cgv;
                }
            }

            // 3. Affichage du bloc CGV
            if (!empty($cgv_content)) {
                echo '<div style="page-break-before: always;"></div>';
                echo '<div style="position: relative; padding-top: 0px; padding-bottom: 50px;">';
                echo '<h3 class="uppercase" style="border-bottom:1px solid #ccc; padding-bottom:5px; margin-bottom:15px;">Conditions Générales de Vente</h3>';
                echo '<div style="font-size:10px; text-align:justify; color:#444;">' . wpautop($cgv_content) . '</div>';
                echo '</div>';
            }
            ?>
        </body>

        </html>
    <?php
        return ob_get_clean();
    }

    /**
     * Rendu spécifique pour la Note de Crédit (Avoir généré lors de l'annulation d'une facture).
     *
     * @param object $resa                L'objet réservation.
     * @param string $doc_number          Le numéro de l'avoir.
     * @param string $ref_facture_origine Numéro de la facture annulée.
     * @return string                     HTML complet.
     */
    public function render_cancellation_credit_note($resa, $doc_number, $ref_facture_origine)
    {
        $fin = PCR_Document_Financial_Calculator::get_instance()->calculate_for_reservation($resa);

        $logo_url = get_field('pc_pdf_logo', 'option');
        $logo = $this->get_image_base64($logo_url);
        $color = get_field('pc_pdf_primary_color', 'option') ?: '#000000';
        $company = [
            'name' => get_field('pc_legal_name', 'option'),
            'address' => get_field('pc_legal_address', 'option'),
            'siret' => get_field('pc_legal_siret', 'option'),
            'tva' => get_field('pc_legal_tva', 'option'),
        ];

        ob_start();
    ?>
        <!DOCTYPE html>
        <html>

        <head>
            <meta charset="UTF-8">
            <?php echo $this->get_common_css($color); ?>
        </head>

        <body>
            <div class="header">
                <div class="left">
                    <?php if ($logo): ?><img src="<?php echo $logo; ?>" class="logo"><?php endif; ?>
                </div>
                <div class="right doc-info">
                    <div class="doc-type" style="color: #cc0000;">AVOIR</div>
                    <div class="doc-meta">
                        <strong>N° :</strong> <?php echo $doc_number; ?><br>
                        <strong>Date :</strong> <?php echo date_i18n('d/m/Y'); ?><br>
                        <strong>Annule la facture :</strong> <?php echo $ref_facture_origine; ?>
                    </div>
                </div>
                <div class="clear"></div>
            </div>

            <div class="addresses">
                <div class="addr-box">
                    <div class="addr-title">Émetteur</div>
                    <strong><?php echo $company['name']; ?></strong><br>
                    <?php echo nl2br($company['address']); ?>
                </div>
                <div class="addr-box client">
                    <div class="addr-title">Avoir au profit de</div>
                    <strong><?php echo $resa->prenom . ' ' . strtoupper($resa->nom); ?></strong>
                </div>
                <div class="clear"></div>
            </div>

            <div style="margin-bottom: 20px; padding: 15px; border: 1px solid #cc0000; color: #cc0000; font-size: 11px; text-align: center;">
                <strong>NOTE DE CRÉDIT</strong><br>
                Ce document annule et remplace la facture n° <?php echo $ref_facture_origine; ?>.
            </div>

            <table>
                <thead>
                    <tr>
                        <th class="col-desc">Désignation</th>
                        <th class="col-num">Montant HT</th>
                        <th class="col-num">TVA</th>
                        <th class="col-num">Total TTC</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fin['lines'] as $line): ?>
                        <tr>
                            <td><?php echo $line['description']; ?></td>
                            <td class="col-num"><?php echo number_format($line['total_ht'], 2, ',', ' '); ?> €</td>
                            <td class="col-num"><?php echo ($line['taux_tva'] > 0) ? $line['taux_tva'] . '%' : '-'; ?></td>
                            <td class="col-num bold"><?php echo number_format($line['total_ttc'], 2, ',', ' '); ?> €</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="totals-wrap">
                <table class="totals-table">
                    <tr class="total-final" style="background-color: #cc0000;">
                        <td style="border:none;">TOTAL AVOIR (TTC)</td>
                        <td style="border:none;" class="col-num"><?php echo number_format($fin['total_ttc'], 2, ',', ' '); ?> €</td>
                    </tr>
                </table>
                <div class="clear"></div>
            </div>

            <div class="footer">
                <?php echo $company['name']; ?> - SIRET : <?php echo $company['siret']; ?>
            </div>
        </body>

        </html>
<?php
        return ob_get_clean();
    }
}
