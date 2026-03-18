<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PCR_Deposit_Renderer
 * * Gère le rendu HTML/PDF spécifique pour les Factures d'Acompte.
 */
class PCR_Deposit_Renderer extends PCR_Base_Document_Renderer
{
    /**
     * Rendu de la facture d'acompte.
     *
     * @param object $resa       L'objet réservation.
     * @param string $doc_number Le numéro de document.
     * @param array  $args       Doit contenir 'template_id'.
     * @return string            HTML complet.
     */
    public function render($resa, $doc_number, $args = [])
    {
        $template_id = $args['template_id'] ?? 0;

        // 1. Récupération des Règles de Paiement du Logement
        $rules = get_field('regles_de_paiement', $resa->item_id);

        $deposit_type = $rules['pc_deposit_type'] ?? 'pourcentage';
        $deposit_val  = (float) ($rules['pc_deposit_value'] ?? 30);

        // 2. Calcul du Montant de l'Acompte
        $total_sejour = (float) $resa->montant_total;
        $montant_acompte_ttc = 0;
        $label_calcul = "";

        if ($deposit_type === 'montant_fixe') {
            $montant_acompte_ttc = $deposit_val;
            $label_calcul = "Forfait fixe";
        } else {
            // Pourcentage
            $montant_acompte_ttc = $total_sejour * ($deposit_val / 100);
            $label_calcul = "{$deposit_val}% du total";
        }

        // 3. Calcul HT / TVA (On applique le taux du logement sur l'acompte)
        $tva_logement = (float) get_field('taux_tva', $resa->item_id);

        $montant_acompte_ht = $montant_acompte_ttc;
        $montant_acompte_tva = 0;

        if ($tva_logement > 0) {
            $montant_acompte_ht = $montant_acompte_ttc / (1 + ($tva_logement / 100));
            $montant_acompte_tva = $montant_acompte_ttc - $montant_acompte_ht;
        }

        // 4. Calcul des Paiements (Pour le Tampon "RÉGLÉ")
        $deja_paye = 0;
        $date_dernier_paiement = null;
        if (class_exists('PCR_Payment')) {
            $payments = PCR_Payment::get_for_reservation($resa->id);
            if ($payments) {
                foreach ($payments as $p) {
                    if ($p->statut === 'paye') {
                        $deja_paye += (float) $p->montant;
                        $date_dernier_paiement = $p->date_paiement;
                    }
                }
            }
        }
        // On considère réglé si le total payé couvre le montant de l'acompte (marge 0.10€)
        $is_paid = ($deja_paye >= ($montant_acompte_ttc - 0.10));

        // 5. Construction de l'Observation
        $nom_logement = get_the_title($resa->item_id);
        $dates = "du " . date_i18n('d/m/Y', strtotime($resa->date_arrivee)) . " au " . date_i18n('d/m/Y', strtotime($resa->date_depart));
        $description_ligne = "Acompte ({$label_calcul}) pour la réservation : {$nom_logement} ({$dates})";

        // 6. Récupération Données Visuelles & Légales
        $logo_url = get_field('pc_pdf_logo', 'option');
        $logo = $this->get_image_base64($logo_url);
        $color = get_field('pc_pdf_primary_color', 'option') ?: '#000000';
        $company = [
            'name' => get_field('pc_legal_name', 'option'),
            'address' => get_field('pc_legal_address', 'option'),
            'siret' => get_field('pc_legal_siret', 'option'),
            'tva' => get_field('pc_legal_tva', 'option'),
        ];
        $bank = [
            'name' => get_field('pc_bank_name', 'option'),
            'iban' => get_field('pc_bank_iban', 'option'),
            'bic'  => get_field('pc_bank_bic', 'option'),
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
            <?php
            if ($is_paid) {
                $date_stamp = $date_dernier_paiement ? date_i18n('d/m/Y', strtotime($date_dernier_paiement)) : date('d/m/Y');
                echo '<div class="stamp-box">RÉGLÉ LE ' . $date_stamp . '</div>';
            }
            ?>

            <div class="header">
                <div class="left">
                    <?php if ($logo): ?><img src="<?php echo $logo; ?>" class="logo"><?php endif; ?>
                </div>
                <div class="right doc-info">
                    <div class="doc-type" style="font-size:18px;">FACTURE D'ACOMPTE</div>
                    <div class="doc-meta">
                        <strong>N° :</strong> <?php echo $doc_number; ?><br>
                        <strong>Date :</strong> <?php echo date_i18n('d/m/Y'); ?><br>
                        <strong>Réf. Résa :</strong> #<?php echo $resa->id; ?>
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
                    <div class="addr-title">Facturé à</div>
                    <strong><?php echo $this->escapeForPdf(($resa->prenom ?? '') . ' ' . strtoupper($resa->nom ?? '')); ?></strong><br>
                    Email : <?php echo $this->escapeForPdf($resa->email ?? ''); ?>
                </div>
                <div class="clear"></div>
            </div>

            <table style="margin-top: 50px;">
                <thead>
                    <tr>
                        <th class="col-desc" width="60%">Désignation</th>
                        <th class="col-num">Base HT</th>
                        <th class="col-num">TVA (<?php echo $tva_logement; ?>%)</th>
                        <th class="col-num">Total TTC</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding: 15px 10px;">
                            <strong><?php echo $this->escapeForPdf($description_ligne); ?></strong><br>
                            <em style="font-size: 10px; color: #666;">Conformément à vos conditions de réservation.</em>
                        </td>
                        <td class="col-num"><?php echo number_format($montant_acompte_ht, 2, ',', ' '); ?> €</td>
                        <td class="col-num"><?php echo number_format($montant_acompte_tva, 2, ',', ' '); ?> €</td>
                        <td class="col-num bold"><?php echo number_format($montant_acompte_ttc, 2, ',', ' '); ?> €</td>
                    </tr>
                </tbody>
            </table>

            <div class="totals-wrap">
                <table class="totals-table">
                    <tr class="total-final">
                        <td style="border:none;">NET À PAYER</td>
                        <td style="border:none;" class="col-num"><?php echo number_format($montant_acompte_ttc, 2, ',', ' '); ?> €</td>
                    </tr>
                </table>
                <div class="clear"></div>
            </div>

            <?php if (!empty($bank['iban'])): ?>
                <div class="bank-box" style="margin-top: 40px; border-top: 1px solid #ccc; padding-top: 10px; border:none;">
                    <span class="bank-title" style="border:none; text-decoration:underline;">INFORMATIONS DE PAIEMENT (VIREMENT)</span>
                    <table style="width:100%; margin:0; border:none; margin-top:5px;">
                        <tr>
                            <td style="border:none; padding:2px;"><strong>Banque :</strong> <?php echo $bank['name']; ?></td>
                            <td style="border:none; padding:2px;"><strong>BIC :</strong> <?php echo $bank['bic']; ?></td>
                        </tr>
                        <tr>
                            <td colspan="2" style="border:none; padding:2px;"><strong>IBAN :</strong> <?php echo $bank['iban']; ?></td>
                        </tr>
                    </table>
                </div>
            <?php endif; ?>

            <div class="footer">
                <?php echo $company['name']; ?> - SIRET : <?php echo $company['siret']; ?> - TVA : <?php echo $company['tva']; ?>
            </div>

            <?php
            $cgv_content = '';

            // 1. Détection automatique selon le type de réservation
            if (isset($resa->type) && $resa->type === 'location') {
                $cgv_content = get_field('cgv_location', 'option');
            } elseif (isset($resa->type) && $resa->type === 'experience') {
                $cgv_content = get_field('cgv_experience', 'option');
            } else {
                // Cas "Mixte" ou "Organisation de séjour"
                $cgv_content = get_field('cgv_sejour', 'option');
            }

            // 2. Fallback : Si c'est un modèle personnalisé (ID numérique) qui force une CGV spécifique
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
}
