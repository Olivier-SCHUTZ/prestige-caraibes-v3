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
        // Chargement de l'autoloader composer si nécessaire
        $autoload_path = dirname(dirname(__FILE__)) . '/vendor/autoload.php';
        if (file_exists($autoload_path)) {
            require_once $autoload_path;
        }

        add_action('init', [__CLASS__, 'register_cpt']);
        add_action('acf/init', [__CLASS__, 'register_acf_fields']); // Configuration spécifique au CPT PDF
        add_filter('acf/load_field/name=pc_linked_cgv', [__CLASS__, 'load_cgv_choices']);
        add_filter('acf/load_field/name=pc_cgv_default_location', [__CLASS__, 'load_cgv_choices']);
        add_filter('acf/load_field/name=pc_cgv_default_experience', [__CLASS__, 'load_cgv_choices']);
        add_action('admin_init', [__CLASS__, 'create_documents_table']);
        add_action('wp_ajax_pc_generate_document', [__CLASS__, 'ajax_generate_document']);
        add_action('wp_ajax_pc_get_documents_list', [__CLASS__, 'ajax_get_documents_list']);
    }

    // --- 1. CONFIGURATION CPT & BDD (Code standard conservé) ---

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

    public static function register_acf_fields()
    {
        if (!function_exists('acf_add_local_field_group')) return;

        // Configuration "Type de Document" sur le CPT
        acf_add_local_field_group([
            'key' => 'group_pc_pdf_config',
            'title' => 'Paramètres du Document',
            'fields' => [
                [
                    'key' => 'field_pc_doc_type',
                    'label' => 'Type de document',
                    'name' => 'pc_doc_type',
                    'type' => 'select',
                    'choices' => [
                        'devis'   => 'Devis',
                        'facture' => 'Facture',
                        'avoir'   => 'Avoir (Note de crédit)',
                        'contrat' => 'Contrat de Location',
                        'voucher' => 'Voucher / Bon d\'échange'
                    ],
                ],
                [
                    'key' => 'field_pc_linked_cgv',
                    'label' => 'Joindre les CGV ?',
                    'name' => 'pc_linked_cgv',
                    'type' => 'select', // <--- C'était 'post_object' avant
                    'instructions' => 'Sélectionnez une version définie dans PC Réservation > Documents & Légal.',
                    'choices' => [],    // <--- Sera rempli dynamiquement
                    'allow_null' => 1,
                    'ui' => 1,          // Joli menu déroulant avec recherche
                    'ajax' => 0,
                ],
            ],
            'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'pc_pdf_template']]]
        ]);
    }

    /**
     * Remplit le menu déroulant avec les titres des CGV créées dans les réglages
     */
    public static function load_cgv_choices($field)
    {
        $field['choices'] = [];

        // On vérifie si des CGV existent dans la page d'options
        if (have_rows('pc_pdf_cgv_library', 'option')) {
            while (have_rows('pc_pdf_cgv_library', 'option')) {
                the_row();
                $title = get_sub_field('cgv_title');
                // On utilise le Titre comme valeur et comme label
                if ($title) {
                    $field['choices'][$title] = $title;
                }
            }
        }
        return $field;
    }

    // --- 2. MOTEUR DE GÉNÉRATION (CONTROLLER) ---

    public static function generate($template_id, $reservation_id, $force_regenerate = false)
    {
        if (!class_exists('\Dompdf\Dompdf')) return ['success' => false, 'message' => 'Moteur Dompdf manquant.'];

        $resa = PCR_Reservation::get_by_id($reservation_id);
        if (!$resa) return ['success' => false, 'message' => 'Réservation introuvable.'];

        // Récupération du type
        $type_doc = get_field('pc_doc_type', $template_id) ?: 'document';

        // Gestion du Numéro de document (Incrémentation)
        global $wpdb;
        $table_name = $wpdb->prefix . 'pc_documents';

        // Vérif existence pour éviter doublon
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE reservation_id = %d AND type_doc = %s LIMIT 1",
            $reservation_id,
            $type_doc
        ));

        if ($existing && !$force_regenerate) {
            return ['success' => true, 'url' => $existing->url_fichier, 'doc_number' => $existing->numero_doc];
        }

        $doc_number = ($existing) ? $existing->numero_doc : self::generate_next_number($type_doc);

        // AIGUILLAGE DU RENDU (Routing)
        // On sépare la logique selon le type de doc et le type de flux (location/experience)
        $html_content = '';

        if (in_array($type_doc, ['facture', 'devis', 'avoir'])) {
            // Documents Financiers
            $html_content = self::render_financial_document($resa, $doc_number, $type_doc, $template_id);
        } elseif ($type_doc === 'voucher') {
            // Voucher
            $html_content = self::render_voucher($resa, $doc_number);
        } elseif ($type_doc === 'contrat') {
            // Contrat (Textuel)
            $html_content = self::render_contract($resa, $doc_number, $template_id);
        } else {
            // Défaut
            $html_content = "Type de document non supporté : " . $type_doc;
        }

        // Génération PDF
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html_content);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $output = $dompdf->output();

        // Sauvegarde
        $upload_dir = wp_upload_dir();
        $rel_path   = '/pc-reservation/documents/' . $reservation_id;
        $abs_path   = $upload_dir['basedir'] . $rel_path;
        $url_path   = $upload_dir['baseurl'] . $rel_path;

        if (!file_exists($abs_path)) mkdir($abs_path, 0755, true);

        $filename       = sanitize_file_name($doc_number) . '.pdf';
        $file_full_path = $abs_path . '/' . $filename;
        $file_url       = $url_path . '/' . $filename;

        file_put_contents($file_full_path, $output);

        // Enregistrement BDD
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table_name} (reservation_id, type_doc, numero_doc, nom_fichier, chemin_fichier, url_fichier, user_id, date_creation)
             VALUES (%d, %s, %s, %s, %s, %s, %d, NOW())
             ON DUPLICATE KEY UPDATE 
             url_fichier = VALUES(url_fichier), chemin_fichier = VALUES(chemin_fichier), date_creation = NOW()",
            $reservation_id,
            $type_doc,
            $doc_number,
            $filename,
            $file_full_path,
            $file_url,
            get_current_user_id()
        ));

        return ['success' => true, 'url' => $file_url, 'doc_number' => $doc_number];
    }

    // --- 3. HELPER : GESTION DES NUMÉROS (Compteurs ACF) ---
    private static function generate_next_number($type)
    {
        $prefix_key = ($type === 'devis') ? 'pc_quote_prefix' : 'pc_invoice_prefix';
        $next_key   = ($type === 'devis') ? 'pc_quote_next'   : 'pc_invoice_next'; // A créer en option si besoin, sinon on utilise invoice pour tout ce qui est fiscal

        // Pour simplifier ici, on utilise le compteur Facture pour (Facture/Avoir) et Devis pour Devis
        if ($type === 'devis') {
            $prefix = get_field('pc_quote_prefix', 'option') ?: 'DEV-' . date('Y') . '-';
            // Pas de compteur auto implémenté dans settings pour devis ? On génère un timestamp au pire
            return $prefix . time();
        } else {
            // FACTURE
            $prefix = get_field('pc_invoice_prefix', 'option') ?: 'FAC-' . date('Y') . '-';
            $next   = (int) get_field('pc_invoice_next', 'option') ?: 1;

            // Mise à jour du compteur
            update_field('pc_invoice_next', $next + 1, 'option');

            return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
        }
    }

    // --- 4. DATA PROVIDER : CALCULS FINANCIERS ---

    /**
     * Analyse le JSON detail_tarif, nettoie les prix et gère les quantités.
     */
    private static function get_financial_data($resa)
    {
        $lines = json_decode($resa->detail_tarif, true);
        if (!is_array($lines)) $lines = [];

        // Récupération des Taux
        $item_id = $resa->item_id;
        $tva_logement = (float) get_field('taux_tva', $item_id);
        $tva_menage   = 8.5; // (À remplacer par get_field si champ créé)
        $tva_plus_value = 8.5;

        $data = [
            'lines' => [],
            'total_ht' => 0,
            'total_tva' => 0,
            'total_ttc' => 0,
        ];

        foreach ($lines as $line) {
            $label_raw = $line['label'];

            // 1. Nettoyage du Prix (ex: "3 117 €" -> 3117.00)
            // On récupère la valeur brute, qu'elle s'appelle 'price' (ton cas) ou 'amount' (ancien code)
            $price_raw = isset($line['price']) ? $line['price'] : (isset($line['amount']) ? $line['amount'] : 0);

            // On enlève tout ce qui n'est pas chiffre, virgule ou point
            // Cela gère les espaces insécables, le symbole €, etc.
            $clean_price = preg_replace('/[^0-9,.]/', '', $price_raw);
            $clean_price = str_replace(',', '.', $clean_price); // 12,50 -> 12.50
            $total_line_ttc = (float) $clean_price;

            // 2. Détection de la Quantité dans le label (ex: "3 Adulte")
            $quantity = 1;
            $description = $label_raw;

            // Regex : Si commence par un nombre suivi d'un espace
            if (preg_match('/^(\d+)\s+(.*)/', $label_raw, $matches)) {
                $quantity = (int) $matches[1];
                $description = $matches[2]; // Le texte sans le nombre (ex: "Adulte")
            }

            // Calcul du prix unitaire TTC (si quantité > 1)
            $unit_ttc = ($quantity > 0) ? $total_line_ttc / $quantity : 0;

            // 3. Détection du Taux TVA
            $taux_applicable = $tva_logement;
            $label_lower = mb_strtolower($label_raw);

            if (strpos($label_lower, 'taxe de séjour') !== false) {
                $taux_applicable = 0;
            } elseif (strpos($label_lower, 'ménage') !== false || strpos($label_lower, 'menage') !== false) {
                $taux_applicable = $tva_menage;
            } elseif (strpos($label_lower, 'plus value') !== false || strpos($label_lower, 'plus-value') !== false) {
                $taux_applicable = $tva_plus_value;
            }

            // 4. Calcul HT / TVA (Méthode "Au rebours" depuis le TTC)
            // Formule : HT = TTC / (1 + taux/100)
            if ($taux_applicable > 0) {
                $total_line_ht = $total_line_ttc / (1 + ($taux_applicable / 100));
                $total_line_tva = $total_line_ttc - $total_line_ht;

                $unit_ht = $unit_ttc / (1 + ($taux_applicable / 100));
            } else {
                $total_line_ht = $total_line_ttc;
                $total_line_tva = 0;
                $unit_ht = $unit_ttc;
            }

            $data['lines'][] = [
                'description' => $description, // "Adulte" (propre)
                'quantity'    => $quantity,    // 3
                'unit_ht'     => $unit_ht,     // 135.00
                'taux_tva'    => $taux_applicable,
                'total_ht'    => $total_line_ht,
                'total_tva'   => $total_line_tva,
                'total_ttc'   => $total_line_ttc // 405.00
            ];

            $data['total_ht']  += $total_line_ht;
            $data['total_tva'] += $total_line_tva;
            $data['total_ttc'] += $total_line_ttc;
        }

        // Gestion des acomptes (inchangé)
        $data['deja_paye'] = 0;
        if (class_exists('PCR_Payment')) {
            $payments = PCR_Payment::get_for_reservation($resa->id);
            if ($payments) {
                foreach ($payments as $p) {
                    if ($p->statut === 'paye') {
                        $data['deja_paye'] += (float)$p->montant;
                    }
                }
            }
        }
        $data['reste_a_payer'] = max(0, $data['total_ttc'] - $data['deja_paye']);

        return $data;
    }

    // --- 5. MOTEUR DE RENDU VISUEL (PIXEL PERFECT) ---

    private static function get_common_css($color)
    {
        return "
            <style>
                @page { margin: 40px; }
                body { font-family: Helvetica, sans-serif; font-size: 11px; color: #333; line-height: 1.4; }
                h1, h2, h3, h4 { color: #000; margin: 0 0 10px 0; }
                
                /* Utils */
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
            </style>
        ";
    }

    // --- RENDU 1 : FACTURE / DEVIS (Structure Financière) ---
    private static function render_financial_document($resa, $doc_number, $type_doc, $template_id)
    {
        // 1. Récupération des réglages centralisés
        $logo_url = get_field('pc_pdf_logo', 'option');
        $logo = self::get_image_base64($logo_url);
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

        // 2. Calculs Financiers
        $fin = self::get_financial_data($resa);

        // 3. Labels
        $titles = ['facture' => 'Facture', 'devis' => 'Devis', 'avoir' => 'Avoir'];
        $doc_title = $titles[$type_doc] ?? 'Document';
        $date_label = ($type_doc === 'devis') ? "Date d'émission" : "Date de facture";

        ob_start();
?>
        <!DOCTYPE html>
        <html>

        <head>
            <meta charset="UTF-8">
            <?php echo self::get_common_css($color); ?>
        </head>

        <body>
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
            $cgv_selected_title = '';

            // 1. Logique Automatique pour Factures et Devis
            if (in_array($type_doc, ['facture', 'devis', 'avoir'])) {
                if ($resa->type === 'location') {
                    $cgv_selected_title = get_field('pc_cgv_default_location', 'option');
                } elseif ($resa->type === 'experience') {
                    $cgv_selected_title = get_field('pc_cgv_default_experience', 'option');
                }
            }

            // 2. Fallback : Si aucune règle auto (ou document "Libre"), on prend le choix manuel du modèle
            if (empty($cgv_selected_title)) {
                $cgv_selected_title = get_field('pc_linked_cgv', $template_id);
            }

            if ($cgv_selected_title && have_rows('pc_pdf_cgv_library', 'option')) {
                while (have_rows('pc_pdf_cgv_library', 'option')) {
                    the_row();
                    if (get_sub_field('cgv_title') === $cgv_selected_title) {
                        $cgv_content = get_sub_field('cgv_content');

                        // Saut de page
                        echo '<div style="page-break-before: always;"></div>';

                        // CORRECTION FINALE : padding-top mis à 0px pour supprimer l'espace blanc inutile
                        echo '<div style="position: relative; padding-top: 0px; padding-bottom: 50px;">';
                        echo '<h3 class="uppercase" style="border-bottom:1px solid #ccc; padding-bottom:5px; margin-bottom:15px;">Conditions Générales (' . $cgv_selected_title . ')</h3>';
                        echo '<div style="font-size:10px; text-align:justify; color:#444;">' . wpautop($cgv_content) . '</div>';
                        echo '</div>';

                        break;
                    }
                }
            }
            ?>
        </body>

        </html>
    <?php
        return ob_get_clean();
    }

    // --- RENDU 2 : VOUCHER ---
    private static function render_voucher($resa, $doc_number)
    {
        $logo_url = get_field('pc_pdf_logo', 'option');
        $logo = self::get_image_base64($logo_url);
        $color = get_field('pc_pdf_primary_color', 'option') ?: '#000000';

        // Adresse du logement (ACF sur le CPT Logement)
        $adresse_lieu = get_field('adresse_logement', $resa->item_id) ?: 'Adresse non communiquée';

        ob_start();
    ?>
        <!DOCTYPE html>
        <html>

        <head>
            <meta charset="UTF-8">
            <?php echo self::get_common_css($color); ?>
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
                        <td><?php echo $resa->prenom . ' ' . strtoupper($resa->nom); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Activité / Logement :</strong></td>
                        <td><?php echo get_the_title($resa->item_id); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Participants :</strong></td>
                        <td><?php echo $resa->adultes; ?> Adultes, <?php echo $resa->enfants; ?> Enfants</td>
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
                <?php echo nl2br($adresse_lieu); ?>
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

    // --- RENDU 3 : CONTRAT ---
    private static function render_contract($resa, $doc_number, $template_id)
    {
        $color = get_field('pc_pdf_primary_color', 'option') ?: '#000000';
        $content = get_post_field('post_content', $template_id); // Le texte brut du modèle

        // Variables de remplacement basiques (pour le contrat textuel)
        $vars = [
            '{nom_client}' => $resa->nom,
            '{prenom_client}' => $resa->prenom,
            '{date_arrivee}' => date_i18n('d/m/Y', strtotime($resa->date_arrivee)),
            '{date_depart}' => date_i18n('d/m/Y', strtotime($resa->date_depart)),
            '{montant_total}' => $resa->montant_total . ' €',
        ];
        $content = strtr($content, $vars);

        ob_start();
    ?>
        <!DOCTYPE html>
        <html>

        <head>
            <meta charset="UTF-8">
            <?php echo self::get_common_css($color); ?>
            <style>
                .contract-body {
                    text-align: justify;
                    font-size: 11px;
                    margin-top: 20px;
                }
            </style>
        </head>

        <body>
            <h1 class="text-center uppercase" style="color:<?php echo $color; ?>;">CONTRAT DE LOCATION</h1>
            <p class="text-center bold">Réf : <?php echo $doc_number; ?></p>

            <div class="contract-body">
                <?php echo wpautop($content); ?>
            </div>

            <div style="margin-top:60px; page-break-inside:avoid;">
                <div class="left" style="width:45%; border-top:1px solid #000; padding-top:10px;">
                    <strong>Le Bailleur (Signature)</strong><br><br><br>
                </div>
                <div class="right" style="width:45%; border-top:1px solid #000; padding-top:10px; text-align:left;">
                    <strong>Le Locataire (Signature) - Lu et approuvé</strong><br><br><br>
                </div>
                <div class="clear"></div>
            </div>

            <?php
            $cgv_selected_title = get_field('pc_linked_cgv', $template_id);

            if ($cgv_selected_title && have_rows('pc_pdf_cgv_library', 'option')) {
                while (have_rows('pc_pdf_cgv_library', 'option')) {
                    the_row();
                    if (get_sub_field('cgv_title') === $cgv_selected_title) {
                        $cgv_content = get_sub_field('cgv_content');

                        // Saut de page
                        echo '<div style="page-break-before: always;"></div>';

                        // CORRECTION ICI : padding-top mis à 0px pour remonter le texte tout en haut
                        echo '<div style="position: relative; padding-top: 0px; padding-bottom: 50px;">';
                        echo '<h3 class="uppercase" style="border-bottom:1px solid #ccc; padding-bottom:5px; margin-bottom:15px;">Conditions Générales (' . $cgv_selected_title . ')</h3>';
                        echo '<div style="font-size:10px; text-align:justify; color:#444;">' . wpautop($cgv_content) . '</div>';
                        echo '</div>';

                        break;
                    }
                }
            }
            ?>
        </body>

        </html>
<?php
        return ob_get_clean();
    }

    // --- AJAX ---
    public static function ajax_get_documents_list()
    {
        while (ob_get_level()) ob_end_clean();
        check_ajax_referer('pc_resa_manual_create', 'nonce');
        $resa_id = (int) ($_POST['reservation_id'] ?? 0);
        global $wpdb;
        $table = $wpdb->prefix . 'pc_documents';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) wp_send_json_success([]);
        $docs = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE reservation_id = %d ORDER BY date_creation DESC", $resa_id));
        wp_send_json_success($docs ?: []);
    }

    public static function ajax_generate_document()
    {
        while (ob_get_level()) ob_end_clean();
        $resa_id = (int) ($_POST['reservation_id'] ?? 0);
        $template_id = (int) ($_POST['template_id'] ?? 0);

        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => 'Non autorisé']);

        // Augmentation mémoire pour Dompdf
        if (function_exists('ini_set')) ini_set('memory_limit', '512M');
        set_time_limit(120);

        try {
            $res = self::generate($template_id, $resa_id, true); // Force regenerate via Ajax
            if ($res['success']) wp_send_json_success($res);
            else wp_send_json_error($res);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    /**
     * GÉNÉRATION DE L'APERÇU (Fake Data)
     * Affiche le PDF directement dans le navigateur sans l'enregistrer.
     */
    public static function preview($template_id)
    {
        // 1. Création d'une réservation fictive (Mock Object)
        $resa = new stdClass();
        $resa->id = 12345; // Faux numéro de résa
        $resa->type = 'location';
        $resa->item_id = 0; // Pas de vrai logement lié
        $resa->prenom = 'Jean';
        $resa->nom = 'PREVIEW';
        $resa->email = 'jean.preview@example.com';
        $resa->telephone = '06 90 00 00 00';
        $resa->date_arrivee = date('Y-m-d', strtotime('+10 days'));
        $resa->date_depart = date('Y-m-d', strtotime('+17 days'));
        $resa->adultes = 2;
        $resa->enfants = 1;
        $resa->montant_total = 1650.00;

        // Faux détail tarifaire pour tester le tableau financier
        $resa->detail_tarif = json_encode([
            ['label' => 'Hébergement (7 nuits)', 'amount' => 1400],
            ['label' => 'Frais de ménage', 'amount' => 150],      // Devrait déclencher la TVA ménage si configurée
            ['label' => 'Taxe de séjour', 'amount' => 100],       // Devrait être TVA 0%
        ]);

        // 2. Récupération du type de document depuis le modèle
        $type_doc = get_field('pc_doc_type', $template_id) ?: 'facture';
        $doc_number = 'PREVIEW-' . date('Ymd');

        // 3. Génération du HTML (On réutilise les fonctions existantes)
        $html_content = '';

        if (in_array($type_doc, ['facture', 'devis', 'avoir'])) {
            $html_content = self::render_financial_document($resa, $doc_number, $type_doc, $template_id);
        } elseif ($type_doc === 'voucher') {
            // Pour le voucher, il nous faut un titre de logement
            // On fait un petit hack pour afficher un titre fictif si item_id est 0
            add_filter('the_title', function ($title, $id) use ($resa) {
                return ($id === $resa->item_id) ? 'Villa de Test (Vue Mer)' : $title;
            }, 10, 2);
            $html_content = self::render_voucher($resa, $doc_number);
        } elseif ($type_doc === 'contrat') {
            $html_content = self::render_contract($resa, $doc_number, $template_id);
        }

        // 4. Rendu PDF et Stream (Affichage direct)
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html_content);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // stream() envoie le PDF au navigateur au lieu de le sauver
        $dompdf->stream("apercu-document.pdf", ["Attachment" => 0]); // 0 = Ouvrir dans l'onglet, 1 = Télécharger
        exit;
    }
    /**
     * Helper : Convertit l'image en Base64 (Solution Ultime pour Dompdf)
     * Cela contourne tous les problèmes de SSL, de chemins et de permissions.
     */
    private static function get_image_base64($url)
    {
        if (empty($url)) return '';

        // 1. On essaie de retrouver le chemin local (plus rapide et fiable)
        $upload_dir = wp_upload_dir();
        $path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);

        $data = '';

        // 2. Si le fichier existe localement, on le lit
        if (file_exists($path)) {
            $data = file_get_contents($path);
        } else {
            // Sinon, on tente de le télécharger (si allow_url_fopen est activé sur le serveur)
            $data = @file_get_contents($url);
        }

        if (!$data) return ''; // Échec lecture

        // 3. Détection du type (PNG ou JPG)
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = ($ext === 'png') ? 'image/png' : 'image/jpeg';

        // 4. Retourne la chaîne prête à l'emploi
        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }
}
