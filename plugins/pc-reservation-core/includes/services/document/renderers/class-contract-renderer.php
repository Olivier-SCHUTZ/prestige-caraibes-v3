<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PCR_Contract_Renderer
 * * Gère le rendu HTML/PDF spécifique pour les Contrats de Location.
 */
class PCR_Contract_Renderer extends PCR_Base_Document_Renderer
{
    /**
     * Rendu du contrat de location.
     *
     * @param object $resa       L'objet réservation.
     * @param string $doc_number Le numéro de document.
     * @param array  $args       Doit contenir 'template_id'.
     * @return string            HTML complet.
     */
    public function render($resa, $doc_number, $args = [])
    {
        $template_id = $args['template_id'] ?? 0;
        $item_id = $resa->item_id;

        // --- RECUPERATION SOURCE (OPTION B) ---
        // On suppose que la colonne 'source' existe dans la table wp_pc_reservations
        // Valeurs possibles : 'direct', 'airbnb', 'booking', 'abritel'
        $source = !empty($resa->source) ? strtolower($resa->source) : 'direct';

        // Nom d'affichage de la plateforme
        $platform_name = ucfirst($source);
        if ($source === 'abritel') $platform_name = 'Abritel / Vrbo';

        // --- SECURISATION RÉCUPÉRATION ACF ---
        $infos = get_field('information_contrat_location', $item_id);
        if (!is_array($infos)) $infos = [];

        $get_val = function ($key) use ($infos, $item_id) {
            if (!empty($infos[$key])) return $infos[$key];
            return get_field($key, $item_id);
        };

        // Infos Propriétaire & Bien
        $proprio_nom  = $get_val('log_proprietaire_identite');
        if (empty($proprio_nom)) $proprio_nom = "LE PROPRIÉTAIRE";

        $proprio_addr = $get_val('proprietaire_adresse') ?: '';
        $desc_bien    = $get_val('description_contrat') ?: 'Logement meublé de tourisme.';
        $desc_equip   = $get_val('equipements_contrat') ?: 'Équipements standards.';
        $cap_max      = $get_val('personne_logement') ?: 'Non spécifiée';

        // Options
        $has_piscine  = $get_val('has_piscine');
        $has_jacuzzi  = $get_val('has_jacuzzi');
        $has_guide    = $get_val('has_guide_numerique');

        // Politique Annulation (Wysiwyg)
        $politique_annulation = get_field('politique_dannulation', $item_id) ?: 'Voir conditions sur le site.';

        // Règles de Paiement
        $rules_payment = get_field('regles_de_paiement', $item_id);
        if (!is_array($rules_payment)) $rules_payment = [];

        $pay_mode       = $rules_payment['pc_pay_mode'] ?? 'acompte_plus_solde';
        $deposit_val    = $rules_payment['pc_deposit_value'] ?? 30;
        $delay_days     = $rules_payment['pc_balance_delay_days'] ?? 30;
        $caution_type   = $rules_payment['pc_caution_type'] ?? 'aucune';
        $caution_amount = $rules_payment['pc_caution_amount'] ?? 0;

        // Agence & Design & Signature
        $logo_url = get_field('pc_pdf_logo', 'option');
        $logo = $this->get_image_base64($logo_url);

        $signature_url = get_site_url(null, '/wp-content/uploads/2025/12/Responsable.png');
        $signature_img = $this->get_image_base64($signature_url);

        $color = get_field('pc_pdf_primary_color', 'option') ?: '#000000';
        $agency = [
            'name'    => get_field('pc_legal_name', 'option'),
            'address' => get_field('pc_legal_address', 'option'),
            'email'   => get_field('pc_legal_email', 'option'),
            'phone'   => get_field('pc_legal_phone', 'option'),
            'siret'   => get_field('pc_legal_siret', 'option'),
        ];

        // 🎯 Appel de notre service financier
        $fin = PCR_Document_Financial_Calculator::get_instance()->calculate_for_reservation($resa);

        $date_resa = date_i18n('d/m/Y', strtotime($resa->date_creation));
        $ts_arrivee = strtotime($resa->date_arrivee);
        $ts_depart  = strtotime($resa->date_depart);
        $duree_nuits = ceil(($ts_depart - $ts_arrivee) / 86400);
        $date_solde_display = date_i18n('d/m/Y', strtotime($resa->date_arrivee . ' -' . (int)$delay_days . ' days'));

        // Titre Piscine/Jacuzzi
        $label_bassin = '';
        if ($has_piscine && $has_jacuzzi) $label_bassin = 'de la piscine et du jacuzzi';
        elseif ($has_piscine) $label_bassin = 'de la piscine';
        elseif ($has_jacuzzi) $label_bassin = 'du jacuzzi';

        ob_start();
?>
        <!DOCTYPE html>
        <html>

        <head>
            <meta charset="UTF-8">
            <?php echo $this->get_common_css($color); ?>
            <style>
                /* Styles spécifiques Contrat */
                .contract-parties {
                    width: 100%;
                    border-collapse: separate;
                    border-spacing: 15px 0;
                    margin-bottom: 20px;
                    font-size: 11px;
                }

                .party-box {
                    border: 1px solid #000;
                    padding: 15px;
                    vertical-align: top;
                }

                .party-title {
                    font-weight: bold;
                    text-decoration: underline;
                    margin-bottom: 10px;
                    display: block;
                }

                .contract-box {
                    border: 1px solid #000;
                    padding: 10px;
                    margin-bottom: 20px;
                    font-size: 11px;
                }

                .financial-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 10px;
                    font-size: 10px;
                }

                .financial-table th {
                    background: #eee;
                    border: 1px solid #000;
                    padding: 5px;
                }

                .financial-table td {
                    border: 1px solid #000;
                    padding: 5px;
                }

                .article-title {
                    font-weight: bold;
                    margin-top: 15px;
                    margin-bottom: 5px;
                    color: <?php echo $color; ?>;
                    text-transform: uppercase;
                    font-size: 11px;
                }

                p {
                    margin: 5px 0;
                    text-align: justify;
                    font-size: 10px;
                }

                li {
                    margin-bottom: 3px;
                }

                /* Numérotation des pages */
                .page-number {
                    position: fixed;
                    bottom: 20px;
                    right: 40px;
                    font-size: 9px;
                    color: #999;
                }

                .page-number:after {
                    content: counter(page);
                }
            </style>
        </head>

        <body>
            <div class="page-number"></div>

            <table style="width:100%; margin-bottom:30px;">
                <tr>
                    <td width="60%" style="vertical-align: top;">
                        <?php if ($logo): ?><img src="<?php echo $logo; ?>" style="max-height:65px; margin-bottom:5px;"><br><?php endif; ?>
                        <div style="font-size:10px; line-height:1.3;">
                            <strong><?php echo strtoupper($agency['name']); ?></strong><br>
                            <?php echo $agency['phone']; ?> - <?php echo $agency['email']; ?>
                        </div>
                    </td>
                    <td width="40%" style="text-align:right; vertical-align: top;">
                        <h1 style="font-size:16px; margin:0 0 5px 0; text-transform:uppercase;">Contrat de location saisonnière</h1>
                        <div style="font-size:11px;">
                            <strong>Réf : <?php echo $doc_number; ?></strong><br>
                            Date : <?php echo $date_resa; ?><br>
                            Source : <?php echo $platform_name; ?>
                        </div>
                    </td>
                </tr>
            </table>

            <table class="contract-parties">
                <tr>
                    <td width="50%" class="party-box" valign="top">
                        <span class="party-title">LE PRENEUR (LOCATAIRE)</span>
                        <strong><?php echo $resa->prenom . ' ' . strtoupper($resa->nom); ?></strong><br>
                        Email : <?php echo $resa->email; ?><br>
                        Tél : <?php echo $resa->telephone; ?><br><br>
                        Occupants : <?php echo $resa->adultes; ?> Adultes, <?php echo $resa->enfants; ?> Enfants
                    </td>
                    <td width="50%" class="party-box" valign="top">
                        <span class="party-title">LE BAILLEUR (POUR LE COMPTE DE)</span>
                        <strong><?php echo strtoupper($agency['name']); ?></strong><br>
                        SIRET : <?php echo $agency['siret']; ?><br>
                        <?php echo nl2br($agency['address']); ?><br><br>
                        <div style="background:#f0f0f0; padding:5px; border-radius:4px;">
                            <em>Pour le compte du propriétaire :</em><br>
                            <strong><?php echo strtoupper($proprio_nom); ?></strong><br>
                            <?php echo nl2br($proprio_addr); ?>
                        </div>
                    </td>
                </tr>
            </table>

            <div class="contract-box">
                <table style="width:100%">
                    <tr>
                        <td><strong>Arrivée :</strong><br><?php echo date_i18n('d/m/Y', $ts_arrivee); ?></td>
                        <td><strong>Départ :</strong><br><?php echo date_i18n('d/m/Y', $ts_depart); ?></td>
                        <td><strong>Durée :</strong><br><?php echo $duree_nuits; ?> nuits</td>
                        <td><strong>Logement :</strong><br><?php echo get_the_title($item_id); ?></td>
                    </tr>
                </table>
            </div>

            <h3 style="margin:0; font-size:12px; margin-top:10px;">DÉTAILS DU PRIX</h3>
            <table class="financial-table">
                <thead>
                    <tr>
                        <th>Détails</th>
                        <th width="20%" style="text-align:right;">Prix</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fin['lines'] as $line): ?>
                        <tr>
                            <td><?php echo $line['description']; ?></td>
                            <td style="text-align:right;"><?php echo number_format($line['total_ttc'], 2, ',', ' '); ?> €</td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="font-weight:bold; background:#f9f9f9;">
                        <td>TOTAL SÉJOUR</td>
                        <td style="text-align:right;"><?php echo number_format($fin['total_ttc'], 2, ',', ' '); ?> €</td>
                    </tr>
                </tbody>
            </table>

            <?php
            $is_platform_managed = ($source === 'airbnb'); // Seul Airbnb gère 100% le paiement

            // Affichage Caution
            $c_amount_display = ($caution_amount > 0) ? number_format((float)$caution_amount, 0, ',', ' ') . ' €' : 'Non définie';
            if ($is_platform_managed) {
                $c_amount_display = "Gérée par " . $platform_name;
            }
            ?>

            <div style="font-size:10px; border:1px dashed #ccc; padding:10px; margin-top:10px;">
                <strong>Conditions de Règlement :</strong><br>

                <?php if ($is_platform_managed): ?>
                    <p style="margin:5px 0; font-style:italic;">
                        Règlement intégralement géré et encaissé par la plateforme <strong><?php echo $platform_name; ?></strong>.
                    </p>
                    <strong>Reste à payer au Bailleur : 0,00 €</strong>
                <?php else: ?>
                    <?php
                    $payments_db = class_exists('PCR_Payment') ? PCR_Payment::get_for_reservation($resa->id) : [];
                    $acompte_row = null;
                    $solde_row   = null;
                    if (is_array($payments_db)) {
                        foreach ($payments_db as $p) {
                            if ($p->type_paiement === 'acompte') $acompte_row = $p;
                            elseif ($p->type_paiement === 'solde' || $p->type_paiement === 'total') $solde_row = $p;
                        }
                    }

                    if ($acompte_row) {
                        $mt_acompte = number_format((float)$acompte_row->montant, 2, ',', ' ');
                        $st_acompte = ($acompte_row->statut === 'paye') ? '<span style="color:green;">(RÉGLÉ)</span>' : '(À régler)';
                        echo "Acompte : {$mt_acompte} € {$st_acompte}<br>";
                    } else {
                        echo "Acompte : Aucun (Paiement total direct)<br>";
                    }

                    if ($solde_row) {
                        $mt_solde = number_format((float)$solde_row->montant, 2, ',', ' ');
                        $st_solde = ($solde_row->statut === 'paye') ? '<span style="color:green;">(RÉGLÉ)</span>' : 'à régler';
                        if ($solde_row->statut === 'paye') {
                            $d_regl = $solde_row->date_paiement ? date_i18n('d/m/Y', strtotime($solde_row->date_paiement)) : '';
                            echo "<strong>Solde : {$mt_solde} €</strong> <span style='color:green;'>RÉGLÉ le {$d_regl}</span>.<br>";
                        } else {
                            echo "<strong>Solde : {$mt_solde} €</strong> {$st_solde} au plus tard le <strong>{$date_solde_display}</strong>.<br>";
                        }
                    }
                    ?>
                <?php endif; ?>

                <br>
                <div style="margin-top:5px; border-top:1px dotted #ccc; padding-top:5px;">
                    <strong>Caution :</strong> <?php echo $c_amount_display; ?>
                    <?php if (!$is_platform_managed) echo "(Voir Article 4)"; ?>
                </div>
            </div>

            <div style="page-break-before: always;"></div>

            <div class="article-title">Article 1 : Objet du Contrat</div>
            <p>Le présent contrat a pour objet la location saisonnière d'un(e) <?php echo strtolower($desc_bien); ?></p>

            <div class="article-title">Article 2 : Description du Bien Loué</div>
            <p><strong>Adresse :</strong> <?php echo nl2br($proprio_addr); ?></p>
            <p><strong>Description :</strong> <?php echo $desc_bien; ?></p>
            <p><strong>Équipements spécifiques :</strong> <?php echo $desc_equip; ?></p>
            <p><strong>Capacité d'accueil maximale :</strong> <?php echo $cap_max; ?> personnes.</p>

            <div class="article-title">Article 3 : Modalités de Paiement</div>
            <?php if ($is_platform_managed): ?>
                <p>La présente réservation ayant été effectuée via la plateforme <strong><?php echo $platform_name; ?></strong>, le règlement du séjour ainsi que les modalités de paiement sont régis exclusivement par les conditions générales de ladite plateforme.</p>
            <?php else: ?>
                <?php if ($pay_mode === 'total_a_la_reservation'): ?>
                    <p>La totalité du montant du séjour est à régler le jour de la réservation. Le paiement intégral conditionne la validation définitive du séjour.</p>
                <?php else: ?>
                    <p><strong>Acompte :</strong> Un acompte de <?php echo $deposit_val; ?>% du loyer total, est dû à la signature du présent contrat pour valider la réservation.</p>
                    <p><strong>Solde :</strong> Le solde devra être réglé au plus tard <?php echo $delay_days; ?> jours avant l'arrivée.</p>
                    <p><strong>Moyens de paiement acceptés :</strong> Virement bancaire, carte bancaire.</p>
                <?php endif; ?>
            <?php endif; ?>

            <div class="article-title">Article 4 : Dépôt de Garantie (Caution)</div>
            <?php if ($is_platform_managed): ?>
                <p>La caution est gérée directement par <strong><?php echo $platform_name; ?></strong> (Garantie Hôte / AirCover). Aucune caution ne sera demandée directement par le Bailleur, sauf exception mentionnée dans le règlement intérieur.</p>
            <?php else: ?>
                <?php if ($caution_type === 'aucune'): ?>
                    <p>La caution est gérée avec le propriétaire ou son représentant lors de votre arrivée, elle peut être par chèque, liquide, ou empreinte bancaire (cela est défini par le propriétaire). Vous serez également informé des conditions de réservation le jour de votre arrivée. Prestige Caraïbes agit en tant qu'intermédiaire de location pour le propriétaire et ne gère pas cette partie du contrat.</p>
                <?php elseif ($caution_type === 'empreinte'): ?>
                    <p>Un dépôt de garantie d'un montant de <?php echo $c_amount_display; ?> est demandé au Preneur. Ce dépôt de garantie a pour but de couvrir les éventuels dommages causés au bien loué, aux mobiliers ou objets garnissant les lieux, ainsi que la perte de clés ou le non-respect des règles de la villa.</p>
                    <p>Ce dépôt de garantie sera versé par empreinte bancaire au plus tard le jour de l’arrivée. Le dépôt de garantie est restitué dans un délai de 7 jours après le départ du Preneur, pouvant aller jusqu’à 31 jours si des dégradations ont été constatées, déduction faite notamment, des indemnités retenues pour les éventuels dégâts occasionnés dans le logement. Si le montant des frais de réparation ou de remplacement excède le montant du dépôt de garantie, le Preneur s’engage à payer la différence au Bailleur.</p>
                <?php else: ?>
                    <p>Une caution de <?php echo $c_amount_display; ?> est demandée à l'arrivée (chèque ou espèces).</p>
                <?php endif; ?>
            <?php endif; ?>

            <div class="article-title">Article 5 : État des Lieux et Inventaire</div>
            <?php if ($has_guide): ?>
                <p>Le Preneur devra contrôler, à l’arrivée, l’état des lieux et l’inventaire que vous retrouverez dans votre guide numérique envoyé quelques semaines avant votre arrivée, ainsi que le bon fonctionnement des appareils ménagers et sanitaires.</p>
                <p>Les installations sont en état de marche et toute réclamation les concernant survenant plus de 24 heures après l’entrée en jouissance des lieux ne pourra être admise. À défaut, le logement sera réputé en bon état et l’état des lieux et l’inventaire conformes à la réalité. Le guide numérique mentionné ci-dessus sera envoyé au Preneur par e-mail quelques semaines avant la date d'arrivée.</p>
            <?php else: ?>
                <p>Le Preneur devra contrôler, à l'arrivée, l'état des lieux et l'inventaire, ainsi que le bon fonctionnement des appareils ménagers et sanitaires avec votre hôte qui vous accueillera.</p>
                <p>Les installations sont en état de marche et toute réclamation les concernant survenant plus de 24 heures après l’entrée en jouissance des lieux ne pourra être admise. À défaut, le logement sera réputé en bon état et l’état des lieux et l’inventaire conformes à la réalité.</p>
            <?php endif; ?>

            <div class="article-title">Article 6 : Conditions d'Annulation</div>
            <?php if ($source === 'direct'): ?>
                <p>Toute annulation doit être notifiée au Bailleur ou à son représentant, par lettre recommandée ou email avec accusé de réception.</p>
                <p>Les conditions de remboursement des règlements pré-payés sont les suivantes :</p>
                <div style="font-size:10px; background:#f9f9f9; padding:10px; margin:5px 0;">
                    <?php echo wpautop($politique_annulation); ?>
                </div>
                <p>Il est fortement recommandé au Preneur de souscrire une assurance annulation pour couvrir d'éventuels imprévus.</p>
            <?php else: ?>
                <p>Les conditions d'annulation, de modification et de remboursement applicables à ce séjour sont celles définies et validées par le Preneur sur la plateforme <strong><?php echo $platform_name; ?></strong> lors de la réservation.</p>
                <p>Toute demande d'annulation doit être effectuée directement via ladite plateforme.</p>
            <?php endif; ?>

            <?php $art_num = 7; ?>

            <div class="article-title">Article <?php echo $art_num++; ?> : Obligations du Preneur</div>
            <p>Le Preneur s'engage à :</p>
            <ul>
                <li>Utiliser les lieux loués en bon père de famille et les entretenir.</li>
                <li>Respecter le nombre maximum de personnes autorisées.</li>
                <li>Respecter le règlement intérieur de la villa.</li>
                <li>Ne pas sous-louer le bien.</li>
                <li>Signaler sans délai au Bailleur tout sinistre ou dégradation survenant dans les lieux loués.</li>
                <li>Laisser l'accès au Bailleur ou à son représentant pour l'entretien de la piscine et du jardin, après accord préalable.</li>
                <li>Procéder au rangement du logement avant son départ.</li>
                <li>Respecter le voisinage et éviter toute nuisance sonore, notamment entre 22h et 8h.</li>
            </ul>

            <div class="article-title">Article <?php echo $art_num++; ?> : Obligations du Bailleur</div>
            <p>Le Bailleur s'engage à :</p>
            <ul>
                <li>Mettre à disposition du Preneur un logement conforme à la description et en bon état de fonctionnement.</li>
                <li>Assurer l'entretien régulier de la piscine et du jardin.</li>
                <li>Fournir le linge de maison (draps, serviettes) sauf mention contraire.</li>
            </ul>

            <div class="article-title">Article <?php echo $art_num++; ?> : Assurances</div>
            <p>Le Preneur est informé qu'il est responsable de tous les dommages qu'il pourrait causer pendant la durée de la location. Il lui est conseillé de vérifier si son assurance responsabilité civile couvre les risques liés à la location saisonnière.</p>

            <?php if ($label_bassin): ?>
                <div class="article-title">Article <?php echo $art_num++; ?> : Utilisation <?php echo $label_bassin; ?> et Responsabilité</div>
                <p>L'utilisation <?php echo $label_bassin; ?> est soumise à un <strong>Règlement Intérieur</strong> ci-après (Annexe 1 du présent contrat) que le Preneur s'engage à respecter scrupuleusement.</p>
                <p>Le Preneur doit prendre toutes les précautions nécessaires pour l'usage <?php echo $label_bassin; ?>, en particulier s’il séjourne avec de jeunes enfants dont il doit impérativement assurer la surveillance. Le Preneur reconnaît dégager entièrement la responsabilité du Propriétaire en cas d'accident survenant à lui-même, sa famille ou ses invités en signant le jour de son arrivée le cahier de consignes de sécurité <?php echo $label_bassin; ?> avec le Bailleur.</p>
            <?php endif; ?>

            <div class="article-title">Article <?php echo $art_num++; ?> : Litiges</div>
            <p>Tout litige relatif à l'exécution ou à l'interprétation du présent contrat sera soumis aux juridictions compétentes.</p>

            <div class="article-title">Article <?php echo $art_num++; ?> : Élection de Domicile</div>
            <p>Pour l'exécution des présentes et de leurs suites, les parties font élection de domicile à l'adresse indiquée en tête des présentes.</p>

            <table style="width:100%; margin-top:50px; border-top:2px solid #000; padding-top:20px;">
                <tr>
                    <td width="50%" style="vertical-align:top; padding-right:20px;">
                        <strong>LE BAILLEUR</strong><br>
                        Le <?php echo date_i18n('d/m/Y'); ?><br><br>
                        <div style="height:100px; border:1px dashed #ccc; padding:5px; font-size:9px; color:#999; position:relative;">
                            Signature
                            <?php if ($signature_img): ?>
                                <img src="<?php echo $signature_img; ?>" style="position:absolute; top:5px; left:5px; max-height:90px; max-width:180px;">
                            <?php endif; ?>
                        </div>
                    </td>
                    <td width="50%" style="vertical-align:top; padding-left:20px; padding-right:60px; text-align:right;">
                        <strong>LE PRENEUR</strong><br>
                        Le &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<br><br>
                        <div style="height:100px; border:1px dashed #ccc; padding:5px; font-size:9px; color:#999; text-align:left;">
                            Signature (Lu et approuvé)
                        </div>
                    </td>
                </tr>
            </table>

            <?php if ($label_bassin): ?>
                <?php
                $txt_bassin = str_replace('de la ', 'de la/du ', $label_bassin);
                $txt_securite = "";
                if ($has_piscine && $has_jacuzzi) {
                    $txt_securite = "• Une alarme piscine ou une barrière est en place. Le jacuzzi est sécurisé par une bâche ou un capot en dur. Ces dispositifs ont été contrôlés le jour de l'arrivée. Tout dysfonctionnement doit être signalé immédiatement.";
                } elseif ($has_piscine) {
                    $txt_securite = "• Une alarme piscine ou une barrière est en place. Celle-ci a été contrôlée le jour de l'arrivée par le Bailleur. Tout dysfonctionnement doit être signalé au plus vite.";
                } elseif ($has_jacuzzi) {
                    $txt_securite = "• Le jacuzzi est sécurisé par une bâche ou un capot en dur qui doit être remis systématiquement après chaque utilisation pour prévenir tout risque de noyade.";
                }
                ?>
                <div style="page-break-before: always;"></div>

                <h3 style="text-align:center; border:1px solid #000; padding:10px; background:#eee; text-transform:uppercase;">
                    ANNEXE 1 : RÈGLEMENT INTÉRIEUR <?php echo strtoupper($label_bassin); ?>
                </h3>

                <p>Afin de garantir la sécurité et le bien-être de tous, l'utilisation <?php echo $label_bassin; ?> est soumise aux règles suivantes. La lecture et le respect de ce règlement sont <strong>obligatoires</strong> pour l'ensemble des occupants de la villa.</p>

                <div class="article-title">Sécurité et Surveillance</div>
                <ul>
                    <li><strong><?php echo ucfirst($label_bassin); ?> n'est/ne sont pas surveillée(s).</strong> Les enfants sont sous la <strong>responsabilité exclusive de leurs parents</strong> ou accompagnateurs légaux, et doivent être <strong>constamment surveillés</strong> lorsqu'ils se trouvent à proximité ou dans l'eau.</li>
                    <li>Le Preneur doit prendre toutes les précautions nécessaires pour l'usage <?php echo $label_bassin; ?>, en particulier s’il séjourne avec de jeunes enfants.</li>
                    <li>Le Preneur reconnaît dégager entièrement la responsabilité du bailleur et propriétaire en cas d'accident survenant à lui-même, sa famille ou ses invités.</li>
                    <li>Les <strong>mineurs non accompagnés</strong> d'un adulte sont strictement interdits dans l'enceinte <?php echo $label_bassin; ?>.</li>
                    <li><?php echo $txt_securite; ?></li>
                    <li><strong>Aucun objet en verre n'est autorisé</strong> sur la plage <?php echo $label_bassin; ?> ou dans l'eau afin de prévenir les accidents.</li>
                    <li>Il est <strong>interdit de courir</strong> autour <?php echo $label_bassin; ?> pour éviter les glissades et chutes.</li>
                    <?php if ($has_piscine): ?><li>Les <strong>plongeons sont interdits</strong>, sauf dans les zones spécifiquement désignées et sécurisées.</li><?php endif; ?>
                    <li>Assurez-vous que l'accès est <strong>correctement sécurisé</strong> (barrière, alarme, bâche) après chaque utilisation.</li>
                </ul>

                <div class="article-title">Hygiène et Propreté</div>
                <ul>
                    <li>Une <strong>douche préalable est obligatoire</strong> avant chaque baignade pour préserver la qualité de l'eau.</li>
                    <li>Il est <strong>interdit de cracher, uriner ou déféquer</strong> dans l'eau.</li>
                    <li>Les <strong>crèmes solaires, huiles</strong> et autres produits pouvant altérer la qualité de l'eau doivent être rincés au maximum avant la baignade.</li>
                    <li>Les <strong>animaux ne sont pas admis</strong> dans <?php echo $label_bassin; ?>.</li>
                </ul>

                <div class="article-title">Comportement</div>
                <ul>
                    <li><strong>Respectez le calme</strong> et la tranquillité du voisinage, en particulier après 22h. Les nuisances sonores excessives sont à proscrire.</li>
                    <li><strong>Ne laissez pas de déchets</strong> ou d'objets traîner autour <?php echo $label_bassin; ?>. Des poubelles sont à votre disposition.</li>
                    <li>Toute <strong>dégradation</strong> des équipements (liner, margelles, pompe, bâche, etc.) due à une mauvaise utilisation sera facturée au Preneur.</li>
                </ul>

                <div class="article-title">Dégagements de Responsabilité</div>
                <ul>
                    <li>Le Bailleur décline toute responsabilité en cas <strong>d'accident, de noyade, de blessure ou de dommage matériel</strong> survenant lors de l'utilisation <?php echo $label_bassin; ?>, si ces incidents sont la conséquence d'un <strong>non-respect du présent règlement</strong>, d'une imprudence ou d'une négligence de la part du Preneur ou de ses invités.</li>
                    <li>Le Bailleur s'engage à maintenir les équipements en bon état de fonctionnement et à en assurer l'entretien régulier. Cependant, il ne pourra être tenu responsable des incidents liés à des <strong>phénomènes naturels imprévisibles</strong> (ex: intempéries exceptionnelles).</li>
                </ul>
            <?php endif; ?>

        </body>

        </html>
<?php
        return ob_get_clean();
    }
}
