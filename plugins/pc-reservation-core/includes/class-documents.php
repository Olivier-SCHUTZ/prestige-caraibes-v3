<?php
if (!defined('ABSPATH')) {
    exit;
}

use Dompdf\Dompdf;
use Dompdf\Options;

class PCR_Documents
{
    public static function init()
    {
        $autoload_path = dirname(dirname(__FILE__)) . '/vendor/autoload.php';
        if (file_exists($autoload_path)) {
            require_once $autoload_path;
        }

        add_action('init', [__CLASS__, 'register_cpt']);
        add_action('init', [__CLASS__, 'register_options_page']);
        add_action('acf/init', [__CLASS__, 'register_acf_fields']);
        add_action('admin_init', [__CLASS__, 'create_documents_table']);
        add_action('wp_ajax_pc_generate_document', [__CLASS__, 'ajax_generate_document']);
        add_action('wp_ajax_pc_get_documents_list', [__CLASS__, 'ajax_get_documents_list']);
    }

    /* ... (Gardez les méthodes create_documents_table, register_cpt, register_options_page, register_acf_fields comme avant) ... */
    /* ... (Elles ne changent pas, sauf si vous voulez ajouter des champs spécifiques) ... */

    // --- JE REMETS LES METHODES D'INIT RAPIDEMENT POUR QUE LE FICHIER SOIT COMPLET SI COPIÉ-COLLÉ ---
    public static function create_documents_table()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pc_documents';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                reservation_id bigint(20) UNSIGNED NOT NULL,
                type_doc varchar(20) NOT NULL,
                numero_doc varchar(50) DEFAULT NULL,
                nom_fichier varchar(191) NOT NULL,
                chemin_fichier text NOT NULL,
                url_fichier text NOT NULL,
                date_creation datetime DEFAULT CURRENT_TIMESTAMP,
                user_id bigint(20) UNSIGNED DEFAULT 0,
                PRIMARY KEY  (id),
                UNIQUE KEY uniq_doc (reservation_id, type_doc),
                KEY reservation_id (reservation_id)
            ) $charset_collate;";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
    public static function register_cpt()
    {
        register_post_type('pc_pdf_template', [
            'labels' => ['name' => 'Modèles PDF', 'singular_name' => 'Modèle PDF'],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'pc-reservation-settings',
            'supports' => ['title', 'editor'],
            'menu_icon' => 'dashicons-media-document',
        ]);
    }
    public static function register_options_page()
    {
        if (function_exists('acf_add_options_sub_page')) {
            acf_add_options_sub_page(['page_title' => 'Configuration PDF', 'menu_title' => 'Réglages PDF', 'parent_slug' => 'pc-reservation-settings']);
        }
    }
    public static function register_acf_fields()
    {
        if (!function_exists('acf_add_local_field_group')) return;
        acf_add_local_field_group([
            'key' => 'group_pc_pdf_config',
            'title' => 'Type de Document',
            'fields' => [
                [
                    'key' => 'field_pc_doc_type',
                    'label' => 'Type de document',
                    'name' => 'pc_doc_type',
                    'type' => 'select',
                    'choices' => [
                        'devis' => 'Devis',
                        'facture' => 'Facture',
                        'contrat' => 'Contrat',
                        'voucher' => 'Voucher'
                    ],
                ],
                // [AJOUT] Sélecteur de CGV (Relation vers une Page WordPress ou un CPT 'CGV')
                [
                    'key' => 'field_pc_linked_cgv',
                    'label' => 'Joindre les CGV (Page ou Contenu)',
                    'name' => 'pc_linked_cgv',
                    'type' => 'post_object', // Permet de choisir une Page WordPress existante
                    'post_type' => ['page'], // Ou un CPT dédié si vous en avez un
                    'return_format' => 'id',
                    'allow_null' => 1,
                    'multiple' => 0,
                    'ui' => 1,
                ],
            ],
            'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'pc_pdf_template']]]
        ]);
        // Note: Ajoutez ici vos champs globaux (Logo, Siret, etc.) comme dans la version précédente
    }
    // -------------------------------------------------------------------------------------------

    /**
     * MOTEUR DE GÉNÉRATION (Version "Pare-Balles" - Ne plante jamais)
     */
    public static function generate($template_id, $reservation_id, $force_regenerate = false)
    {
        // 1. Vérifications basiques
        if (!class_exists('\Dompdf\Dompdf')) {
            return ['success' => false, 'message' => 'Moteur PDF absent (Dompdf).'];
        }
        $resa = PCR_Reservation::get_by_id($reservation_id);
        if (!$resa) {
            return ['success' => false, 'message' => 'Réservation introuvable.'];
        }

        // 2. On s'assure que la table existe (Méthode interne existante)
        self::create_documents_table();

        // 3. Récupération du Type (Avec toutes les roues de secours possibles)
        $type_doc = '';

        // Tentative 1 : Via ACF
        if (function_exists('get_field')) {
            $type_doc = get_field('pc_doc_type', $template_id);
        }

        // Tentative 2 : Via WordPress natif (si ACF échoue)
        if (empty($type_doc)) {
            $type_doc = get_post_meta($template_id, 'pc_doc_type', true);
        }

        // Tentative 3 : FORCE BRUTE (Si tout est vide, on met une valeur par défaut pour ne pas planter SQL)
        if (empty($type_doc)) {
            $type_doc = 'document'; // Valeur de secours
        }

        global $wpdb;
        $table_name   = $wpdb->prefix . 'pc_documents';
        $existing   = null;
        $doc_number = '';

        // 4. Vérification existence (pour éviter les doublons inutiles)
        // On vérifie si la table existe vraiment avant de requêter pour éviter un crash rare
        $table_exists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $table_name)
        );

        if ($table_exists === $table_name) {
            // On récupère le dernier document enregistré pour cette réservation + ce type
            $sql = "
                SELECT *
                FROM {$table_name}
                WHERE reservation_id = %d
                  AND type_doc = %s
                ORDER BY date_creation DESC, id DESC
                LIMIT 1
            ";

            $existing = $wpdb->get_row(
                $wpdb->prepare($sql, $reservation_id, $type_doc)
            );

            // Si on a déjà un doc et qu'on ne force pas la régénération : on le réutilise
            if ($existing && !$force_regenerate) {
                return [
                    'success'    => true,
                    'url'        => $existing->url_fichier,
                    'doc_number' => $existing->numero_doc,
                ];
            }

            // Sinon : même numéro si déjà existant, ou nouveau numéro
            if ($existing && !empty($existing->numero_doc)) {
                $doc_number = $existing->numero_doc;
            } else {
                $doc_number = self::generate_doc_number($type_doc);
            }
        } else {
            // Si la table n'est toujours pas là malgré le create, on génère quand même le PDF sans BDD
            $doc_number = self::generate_doc_number($type_doc);
        }

        // 5. Construction HTML & PDF
        $html_content = self::build_structured_html($template_id, $resa, $doc_number, $type_doc);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html_content);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $output = $dompdf->output();

        // 6. Sauvegarde Fichier
        $upload_dir = wp_upload_dir();
        $rel_path   = '/pc-reservation/documents/' . $reservation_id;
        $abs_path   = $upload_dir['basedir'] . $rel_path;
        $url_path   = $upload_dir['baseurl'] . $rel_path;

        if (!file_exists($abs_path)) {
            mkdir($abs_path, 0755, true);
        }

        $filename       = sanitize_file_name($doc_number) . '.pdf';
        $file_full_path = $abs_path . '/' . $filename;
        $file_url       = $url_path . '/' . $filename;

        file_put_contents($file_full_path, $output);

        // 7. Enregistrement BDD (Si la table existe)
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name) {
            $user_id = get_current_user_id();

            // INSERT ... ON DUPLICATE KEY : une seule ligne par (reservation_id, type_doc)
            $sql = $wpdb->prepare(
                "
                INSERT INTO {$table_name}
                    (reservation_id, type_doc, numero_doc, nom_fichier, chemin_fichier, url_fichier, user_id)
                VALUES
                    (%d, %s, %s, %s, %s, %s, %d)
                ON DUPLICATE KEY UPDATE
                    nom_fichier    = VALUES(nom_fichier),
                    chemin_fichier = VALUES(chemin_fichier),
                    url_fichier    = VALUES(url_fichier),
                    user_id        = VALUES(user_id),
                    date_creation  = NOW()
                ",
                $reservation_id,
                $type_doc,
                $doc_number,
                $filename,
                $file_full_path,
                $file_url,
                $user_id
            );

            $wpdb->query($sql);
        }

        return [
            'success'    => true,
            'url'        => $file_url,
            'doc_number' => $doc_number,
        ];
    }

    private static function generate_doc_number($type)
    {
        // Logique simple pour l'exemple. À connecter à vos compteurs ACF Options
        $prefix = strtoupper(substr($type, 0, 3)); // DEV, FAC
        $year = date('Y');
        // Ici il faudrait récupérer le compteur en option, l'incrémenter et sauvegarder
        // $count = get_field('compteur_'.$type, 'option'); ...
        $unique = time(); // Temporaire pour éviter doublon en dev
        return $prefix . '-' . $year . '-' . substr($unique, -4);
    }

    /**
     * CONSTRUCTEUR DE LAYOUT "IMPOSÉ"
     */
    private static function build_structured_html($template_id, $resa, $doc_number, $type_doc)
    {
        // Données Globales
        $logo = get_field('pc_legal_logo', 'option');
        $color = get_field('pc_legal_color', 'option') ?: '#000000'; // Noir par défaut si pas de couleur

        // Infos Société (Vendeur)
        $company = [
            'name' => get_field('pc_legal_name', 'option'),
            'address' => get_field('pc_legal_address', 'option'), // Doit contenir rue, cp, ville
            'email' => 'guadeloupe@prestigecaraibes.com', // À mettre en option aussi idéalement
            'siret' => get_field('pc_legal_siret', 'option'),
            'tva'   => get_field('pc_legal_tva', 'option'),
        ];

        // Infos Client (Acheteur)
        $client = [
            'name' => $resa->prenom . ' ' . strtoupper($resa->nom),
            'address' => 'Adresse non communiquée', // Si tu ajoutes le champ adresse client plus tard
            'email' => $resa->email,
            'tel' => $resa->telephone
        ];

        // Dates
        $date_emission = date_i18n('d/m/Y');
        $date_expiration = date_i18n('d/m/Y', strtotime('+1 month')); // Validité 1 mois pour devis

        // Titre document
        $titres = [
            'devis' => 'DEVIS',
            'facture' => 'FACTURE',
            'contrat' => 'CONTRAT DE LOCATION',
            'voucher' => 'BON D\'ÉCHANGE (VOUCHER)'
        ];
        $titre_doc = $titres[$type_doc] ?? 'DOCUMENT';

        // CSS "Pixel Perfect" inspiré de ton PDF
        $css = "
            <style>
                @page { margin: 40px 40px; }
                body { font-family: 'Helvetica', sans-serif; font-size: 11px; color: #333; line-height: 1.4; }
                
                /* Layout Helper */
                .clear { clear: both; }
                .half { width: 48%; float: left; }
                .right { float: right; text-align: right; }
                .left { float: left; text-align: left; }
                .bold { font-weight: bold; }
                .uppercase { text-transform: uppercase; }
                .text-color { color: $color; }
                
                /* Header */
                .header-top { margin-bottom: 40px; height: 100px; }
                .logo { max-height: 80px; max-width: 200px; }
                
                /* Meta Box (Top Right) */
                .meta-box { width: 250px; float: right; }
                .meta-row { border-bottom: 1px solid #ddd; padding: 5px 0; margin-bottom: 2px; }
                .meta-label { float: left; font-weight: bold; color: #555; }
                .meta-val { float: right; text-align: right; }
                .doc-title { font-size: 24px; font-weight: bold; margin-bottom: 10px; color: #000; text-align: right; }

                /* Addresses Grid */
                .address-section { margin-bottom: 40px; display: block; height: 160px; }
                .addr-box { width: 45%; float: left; font-size: 12px; }
                .addr-box.client { float: right; }
                .addr-title { font-weight: bold; text-transform: uppercase; color: #999; font-size: 10px; margin-bottom: 5px; border-bottom: 1px solid #eee; padding-bottom: 2px; }

                /* Subject Line */
                .subject-line { background: #f9f9f9; padding: 10px; border-radius: 4px; border-left: 4px solid $color; margin-bottom: 30px; font-size: 12px; }

                /* Tables (Structure imposée) */
                table.items { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
                table.items th { text-align: left; border-bottom: 2px solid #000; padding: 8px 5px; font-weight: bold; font-size: 11px; text-transform: uppercase; color: #000; }
                table.items td { border-bottom: 1px solid #eee; padding: 10px 5px; vertical-align: top; }
                table.items td.num { text-align: right; }
                
                /* Totals Box */
                .totals-box { width: 40%; float: right; }
                .total-row { padding: 5px 0; border-bottom: 1px solid #eee; }
                .total-row.final { border-bottom: 2px solid #000; border-top: 2px solid #000; font-size: 14px; font-weight: bold; margin-top: 5px; padding: 10px 0; }
                .total-label { float: left; }
                .total-val { float: right; }

                /* Footer */
                .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #ddd; padding-top: 10px; }
                
                /* Specific Contract/Text Content */
                .contract-body { text-align: justify; font-size: 11px; margin-top: 20px; }
                .contract-body h2 { font-size: 14px; border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-top: 20px; }
            </style>
        ";

        // Début HTML
        ob_start();
?>
        <!DOCTYPE html>
        <html>

        <head>
            <meta charset="UTF-8"><?php echo $css; ?>
        </head>

        <body>
            <div class="header-top">
                <div class="left">
                    <?php if ($logo): ?><img src="<?php echo $logo; ?>" class="logo"><?php endif; ?>
                </div>
                <div class="meta-box">
                    <div class="doc-title"><?php echo $titre_doc; ?></div>
                    <div class="meta-row">
                        <span class="meta-label">Numéro</span>
                        <span class="meta-val"><?php echo $doc_number; ?></span>
                        <div class="clear"></div>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Date d'émission</span>
                        <span class="meta-val"><?php echo $date_emission; ?></span>
                        <div class="clear"></div>
                    </div>
                    <?php if ($type_doc === 'devis'): ?>
                        <div class="meta-row">
                            <span class="meta-label">Expiration</span>
                            <span class="meta-val"><?php echo $date_expiration; ?></span>
                            <div class="clear"></div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="clear"></div>
            </div>

            <div class="address-section">
                <div class="addr-box">
                    <div class="addr-title">Émetteur</div>
                    <strong><?php echo $company['name']; ?></strong><br>
                    <?php echo nl2br($company['address']); ?><br>
                    Email: <?php echo $company['email']; ?><br><br>
                    Siret: <?php echo $company['siret']; ?><br>
                    TVA: <?php echo $company['tva']; ?>
                </div>
                <div class="addr-box client">
                    <div class="addr-title">Adressé à</div>
                    <strong><?php echo $client['name']; ?></strong><br>
                    <?php echo $client['address']; ?><br>
                    <?php echo $client['email']; ?><br>
                    <?php echo $client['tel']; ?>
                </div>
                <div class="clear"></div>
            </div>

            <div class="subject-line">
                <strong>Objet :</strong> Votre séjour en Guadeloupe du
                <?php echo date_i18n('d/m/Y', strtotime($resa->date_arrivee)); ?> au
                <?php echo date_i18n('d/m/Y', strtotime($resa->date_depart)); ?>
                dans <?php echo get_the_title($resa->item_id); ?>.
            </div>

            <?php if (in_array($type_doc, ['devis', 'facture'])): ?>
                <?php echo self::render_financial_table_pdf($resa); ?>
            <?php elseif ($type_doc === 'voucher'): ?>
                <?php echo self::render_voucher_body($resa); ?>
            <?php elseif ($type_doc === 'contrat'): ?>
                <div class="contract-body">
                    <?php echo wpautop(get_post_field('post_content', $template_id)); ?>
                </div>
                <div style="margin-top:50px;">
                    <div class="half"><strong>Le Bailleur</strong><br>Signature</div>
                    <div class="half right"><strong>Le Locataire</strong><br>Signature</div>
                </div>
            <?php endif; ?>

            <?php
            // On récupère l'ID de la page CGV sélectionnée dans le modèle
            $cgv_id = get_field('pc_linked_cgv', $template_id);
            if ($cgv_id) {
                $cgv_post = get_post($cgv_id);
                if ($cgv_post && !empty($cgv_post->post_content)) {
            ?>
                    <div style="page-break-before: always;"></div>

                    <div class="cgv-section">
                        <h3 class="uppercase" style="border-bottom:1px solid #ccc; padding-bottom:5px; margin-bottom:15px;">
                            Conditions Générales de Vente
                        </h3>
                        <div class="cgv-content" style="font-size: 10px; text-align: justify; color: #444;">
                            <?php echo wpautop($cgv_post->post_content); ?>
                        </div>
                    </div>
            <?php
                }
            }
            ?>

            <div class="footer">
                <?php echo $company['name']; ?> - SIRET: <?php echo $company['siret']; ?> - TVA: <?php echo $company['tva']; ?><br>
                Document généré automatiquement par PC Reservation.
            </div>

        </body>

        </html>
    <?php
        return ob_get_clean();
    }

    /**
     * RENDU DU TABLEAU FINANCIER (Strictement comme le PDF : HT | TVA | Total HT)
     */
    private static function render_financial_table_pdf($resa)
    {
        $lines = json_decode($resa->detail_tarif, true);
        if (!is_array($lines)) return '';

        // Récupération TVA depuis le logement (ACF)
        $taux_tva = (float) get_field('taux_tva', $resa->item_id);

        $html = '<table class="items">
            <thead>
                <tr>
                    <th width="45%">Description</th>
                    <th width="15%" class="num">Prix Unitaire</th>
                    <th width="15%" class="num">TVA %</th>
                    <th width="20%" class="num">Total HT</th>
                </tr>
            </thead>
            <tbody>';

        $total_ht_global = 0;
        $total_tva_global = 0;

        foreach ($lines as $line) {
            $label = $line['label'];
            // Dans ta BDD, le prix est souvent stocké en TTC global pour la ligne (amount)
            $montant_ligne_ttc = isset($line['amount']) ? (float)$line['amount'] : 0;

            // Calcul inversé pour retrouver le HT
            // HT = TTC / (1 + taux/100)
            if ($taux_tva > 0) {
                $montant_ligne_ht = $montant_ligne_ttc / (1 + ($taux_tva / 100));
                $montant_tva = $montant_ligne_ttc - $montant_ligne_ht;
            } else {
                $montant_ligne_ht = $montant_ligne_ttc;
                $montant_tva = 0;
            }

            // Prix unitaire (pour l'affichage, on prend le HT global de la ligne ici, car Qté souvent incluse dans le libellé)
            // Si tu as des Qté séparées, divise ici.

            $total_ht_global += $montant_ligne_ht;
            $total_tva_global += $montant_tva;

            $html .= '<tr>
                <td>' . $label . '</td>
                <td class="num">' . number_format($montant_ligne_ht, 2, ',', ' ') . ' €</td>
                <td class="num">' . ($taux_tva > 0 ? $taux_tva . '%' : '0%') . '</td>
                <td class="num">' . number_format($montant_ligne_ht, 2, ',', ' ') . ' €</td>
            </tr>';
        }

        $html .= '</tbody></table>';

        // BLOC TOTAUX
        $html .= '<div class="totals-box">
            <div class="total-row">
                <span class="total-label">Total HT</span>
                <span class="total-val">' . number_format($total_ht_global, 2, ',', ' ') . ' €</span>
                <div class="clear"></div>
            </div>
            <div class="total-row">
                <span class="total-label">TVA (' . $taux_tva . '%)</span>
                <span class="total-val">' . number_format($total_tva_global, 2, ',', ' ') . ' €</span>
                <div class="clear"></div>
            </div>
            <div class="total-row final">
                <span class="total-label">NET À PAYER (TTC)</span>
                <span class="total-val">' . number_format($resa->montant_total, 2, ',', ' ') . ' €</span>
                <div class="clear"></div>
            </div>
        </div>
        <div class="clear"></div>';

        // NOTE DE BAS DE PAGE (contenu CPT optionnel)
        // Ici on pourrait injecter le texte libre si besoin pour les conditions de paiement
        $html .= '<div style="margin-top:30px; font-size:10px; color:#666;">
            Conditions de paiement : Acompte de 30% à la réservation, solde 30 jours avant l\'arrivée.
        </div>';

        return $html;
    }

    /**
     * RENDU VOUCHER (Spécifique)
     */
    private static function render_voucher_body($resa)
    {
        $logement_adresse = get_field('adresse_logement', $resa->item_id); // Champ ACF à créer si inexistant

        ob_start();
    ?>
        <div style="border: 2px dashed #ccc; padding: 20px; background: #fdfdfd; margin-bottom: 20px;">
            <h3 style="margin-top:0;">INFORMATIONS D'ARRIVÉE</h3>
            <table width="100%">
                <tr>
                    <td width="50%">
                        <strong>CHECK-IN (Arrivée)</strong><br>
                        Date : <?php echo date_i18n('d/m/Y', strtotime($resa->date_arrivee)); ?><br>
                        Heure : À partir de 16h00
                    </td>
                    <td width="50%">
                        <strong>CHECK-OUT (Départ)</strong><br>
                        Date : <?php echo date_i18n('d/m/Y', strtotime($resa->date_depart)); ?><br>
                        Heure : Avant 11h00
                    </td>
                </tr>
            </table>
        </div>

        <div style="margin-bottom: 20px;">
            <h4>ADRESSE DU LOGEMENT</h4>
            <p><?php echo $logement_adresse ? $logement_adresse : 'Adresse communiquée par WhatsApp.'; ?></p>
            <p><strong>Google Maps :</strong> <a href="#">Ouvrir la localisation</a></p>
        </div>

        <div style="text-align: center; margin-top: 40px;">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=RESA-<?php echo $resa->id; ?>" style="width:100px;">
            <p style="font-size:10px;">Présentez ce code à l'arrivée</p>
        </div>
<?php
        return ob_get_clean();
    }

    // AJOUT AJAX POUR LA LISTE (MANQUANT DANS LE CODE PRECEDENT)
    public static function ajax_get_documents_list()
    {
        // 1. Sécurité : même nonce que dans le dashboard (pcResaManualNonce)
        check_ajax_referer('pc_resa_manual_create', 'nonce');

        $resa_id = isset($_POST['reservation_id']) ? (int) $_POST['reservation_id'] : 0;
        if ($resa_id <= 0) {
            wp_send_json_success([]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'pc_documents';

        // 2. Lecture simple de tous les documents liés à cette réservation
        $sql = $wpdb->prepare(
            "
            SELECT
                id,
                reservation_id,
                type_doc,
                numero_doc,
                nom_fichier,
                url_fichier,
                date_creation
            FROM {$table}
            WHERE reservation_id = %d
            ORDER BY date_creation DESC, id DESC
            ",
            $resa_id
        );

        $docs = $wpdb->get_results($sql);
        if (!is_array($docs)) {
            $docs = [];
        }

        wp_send_json_success($docs);
    }

    /**
     * AJAX : Génération du PDF (Version Blindée & Nettoyée)
     */
    public static function ajax_generate_document()
    {
        // 1. NETTOYAGE DU BUFFER (CRUCIAL)
        // On supprime tout ce qui a pu être affiché avant (notices PHP, erreurs ACF, espaces blancs)
        // Cela garantit que la réponse sera un JSON pur et valide.
        while (ob_get_level()) {
            ob_end_clean();
        }

        // 2. Récupération & Sécurisation
        $resa_id = isset($_POST['reservation_id']) ? (int) $_POST['reservation_id'] : 0;
        $template_id = isset($_POST['template_id']) ? (int) $_POST['template_id'] : 0;
        $force = isset($_POST['force']) && $_POST['force'] === 'true';

        try {
            // Vérification des droits
            if (!current_user_can('edit_posts')) {
                throw new Exception("Permissions insuffisantes.");
            }

            if (!$resa_id || !$template_id) {
                throw new Exception("Données manquantes (ID Réservation ou Modèle).");
            }

            // Augmentation des ressources pour Dompdf (mémoire et temps)
            if (function_exists('set_time_limit')) set_time_limit(120);
            if (function_exists('ini_set')) ini_set('memory_limit', '512M');

            // 3. Appel du moteur de génération
            $result = self::generate($template_id, $resa_id, $force);

            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        } catch (Exception $e) {
            // Capture des erreurs logiques
            wp_send_json_error([
                'message' => 'Erreur : ' . $e->getMessage()
            ]);
        } catch (Error $e) {
            // Capture des erreurs fatales PHP (ex: librairie manquante)
            wp_send_json_error([
                'message' => 'Erreur Fatale : ' . $e->getMessage()
            ]);
        }

        // 4. Arrêt strict de WordPress
        wp_die();
    }
}
