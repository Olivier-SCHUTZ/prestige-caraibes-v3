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
        // 1. Chargement de l'autoloader Composer (Moteur PDF)
        $autoload_path = plugin_dir_path(dirname(__FILE__)) . 'vendor/autoload.php';
        if (file_exists($autoload_path)) {
            require_once $autoload_path;
        }

        // 2. Enregistrement CPT & Options
        add_action('init', [__CLASS__, 'register_cpt']);
        add_action('init', [__CLASS__, 'register_options_page']);
        add_action('acf/init', [__CLASS__, 'register_acf_fields']);

        // 3. Création Table SQL (à l'activation normalement, mais check ici pour dev)
        add_action('admin_init', [__CLASS__, 'create_documents_table']);

        // 4. AJAX Génération
        add_action('wp_ajax_pc_generate_document', [__CLASS__, 'ajax_generate_document']);
    }

    /**
     * Crée la table SQL pour l'historique des documents générés
     */
    public static function create_documents_table()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pc_documents';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                reservation_id bigint(20) UNSIGNED NOT NULL,
                type_doc varchar(20) NOT NULL, /* facture, devis, contrat */
                numero_doc varchar(50) DEFAULT NULL, /* FAC-2025-001 */
                nom_fichier varchar(191) NOT NULL,
                chemin_fichier text NOT NULL,
                url_fichier text NOT NULL,
                date_creation datetime DEFAULT CURRENT_TIMESTAMP,
                user_id bigint(20) UNSIGNED DEFAULT 0,
                PRIMARY KEY  (id),
                KEY reservation_id (reservation_id)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    public static function register_cpt()
    {
        register_post_type('pc_pdf_template', [
            'labels' => [
                'name' => 'Modèles PDF',
                'singular_name' => 'Modèle PDF',
                'menu_name' => 'Documents PDF',
                'add_new_item' => 'Ajouter un modèle',
                'edit_item' => 'Modifier le modèle',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'pc-reservation-settings', // Sous le menu principal
            'supports' => ['title', 'editor'], // Editor = HTML du PDF
            'menu_icon' => 'dashicons-media-document',
        ]);
    }

    public static function register_options_page()
    {
        if (function_exists('acf_add_options_sub_page')) {
            acf_add_options_sub_page([
                'page_title'  => 'Configuration PDF (Légal & Design)',
                'menu_title'  => 'Réglages PDF',
                'parent_slug' => 'pc-reservation-settings',
            ]);
        }
    }

    public static function register_acf_fields()
    {
        if (!function_exists('acf_add_local_field_group')) return;

        // A. CHAMPS POUR LE MODÈLE PDF (CPT)
        acf_add_local_field_group([
            'key' => 'group_pc_pdf_config',
            'title' => 'Type de Document',
            'fields' => [
                [
                    'key' => 'field_pc_doc_type',
                    'label' => 'Ce modèle sert à générer :',
                    'name' => 'pc_doc_type',
                    'type' => 'select',
                    'choices' => [
                        'devis' => 'Devis',
                        'facture' => 'Facture (Incrémente compteur)',
                        'contrat' => 'Contrat de Location',
                        'libre' => 'Document Libre',
                    ],
                ],
            ],
            'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'pc_pdf_template']]],
            'position' => 'side',
        ]);

        // B. RÉGLAGES GLOBAUX (Page Options)
        acf_add_local_field_group([
            'key' => 'group_pc_pdf_globals',
            'title' => 'Identité & Mentions Légales',
            'fields' => [
                // Identité
                ['key' => 'field_legal_logo', 'label' => 'Logo', 'name' => 'pc_legal_logo', 'type' => 'image', 'return_format' => 'url'],
                ['key' => 'field_legal_color', 'label' => 'Couleur Principale', 'name' => 'pc_legal_color', 'type' => 'color_picker', 'default_value' => '#4338ca'],

                // Société
                ['key' => 'field_legal_name', 'label' => 'Raison Sociale', 'name' => 'pc_legal_name', 'type' => 'text'],
                ['key' => 'field_legal_address', 'label' => 'Adresse Siège', 'name' => 'pc_legal_address', 'type' => 'textarea', 'rows' => 2],
                ['key' => 'field_legal_siret', 'label' => 'SIRET', 'name' => 'pc_legal_siret', 'type' => 'text'],
                ['key' => 'field_legal_tva', 'label' => 'N° TVA Intracom', 'name' => 'pc_legal_tva', 'type' => 'text'],
                ['key' => 'field_legal_rcs', 'label' => 'RCS / Ville', 'name' => 'pc_legal_rcs', 'type' => 'text'],
                ['key' => 'field_legal_capital', 'label' => 'Capital Social', 'name' => 'pc_legal_capital', 'type' => 'text'],

                // Compteurs
                ['key' => 'field_invoice_prefix', 'label' => 'Préfixe Facture', 'name' => 'pc_invoice_prefix', 'type' => 'text', 'default_value' => 'FAC-2025-'],
                ['key' => 'field_invoice_count', 'label' => 'Prochain N° Facture', 'name' => 'pc_invoice_count', 'type' => 'number', 'default_value' => 1],
                ['key' => 'field_quote_prefix', 'label' => 'Préfixe Devis', 'name' => 'pc_quote_prefix', 'type' => 'text', 'default_value' => 'DEV-2025-'],
                ['key' => 'field_quote_count', 'label' => 'Prochain N° Devis', 'name' => 'pc_quote_count', 'type' => 'number', 'default_value' => 1],
            ],
            'location' => [[['param' => 'options_page', 'operator' => '==', 'value' => 'acf-options-reglages-pdf']]],
        ]);
    }

    /**
     * MOTEUR PRINCIPAL : Génère le PDF et le sauvegarde
     */
    public static function generate($template_id, $reservation_id, $force_regenerate = false)
    {
        if (!class_exists('\Dompdf\Dompdf')) return ['success' => false, 'message' => 'Moteur PDF non installé (Composer).'];

        $resa = PCR_Reservation::get_by_id($reservation_id);
        if (!$resa) return ['success' => false, 'message' => 'Réservation introuvable.'];

        $type_doc = get_field('pc_doc_type', $template_id);

        // 1. Gestion du Numéro de Document (Chrono)
        $doc_number = '';
        $is_official = in_array($type_doc, ['facture', 'devis']);

        // Vérifier si un document de ce type existe déjà pour cette résa
        global $wpdb;
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}pc_documents WHERE reservation_id = %d AND type_doc = %s",
            $reservation_id,
            $type_doc
        ));

        if ($existing && !$force_regenerate) {
            return ['success' => true, 'url' => $existing->url_fichier, 'message' => 'Document existant récupéré.'];
        }

        // Attribution du numéro
        if ($existing) {
            $doc_number = $existing->numero_doc; // On garde le même numéro si on régénère
        } elseif ($is_official) {
            // Incrémentation du compteur global
            $field_name = ($type_doc === 'facture') ? 'pc_invoice_count' : 'pc_quote_count';
            $prefix_name = ($type_doc === 'facture') ? 'pc_invoice_prefix' : 'pc_quote_prefix';

            $count = (int) get_field($field_name, 'option');
            $prefix = get_field($prefix_name, 'option');

            $doc_number = $prefix . str_pad($count, 4, '0', STR_PAD_LEFT);

            // Mise à jour du compteur (+1)
            update_field($field_name, $count + 1, 'option');
        } else {
            $doc_number = 'DOC-' . $reservation_id;
        }

        // 2. Construction du HTML
        $html_content = self::build_html($template_id, $resa, $doc_number);

        // 3. Génération PDF (Dompdf)
        $options = new Options();
        $options->set('isRemoteEnabled', true); // Pour charger les images (logos)

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html_content);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $output = $dompdf->output();

        // 4. Sauvegarde sur le serveur
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/pc-documents/' . $reservation_id;
        $base_url = $upload_dir['baseurl'] . '/pc-documents/' . $reservation_id;

        if (!file_exists($base_dir)) {
            mkdir($base_dir, 0755, true);
            // Sécurité : index.php vide pour empêcher le listing
            file_put_contents($base_dir . '/index.php', '<?php // Silence is golden');
        }

        $filename = sanitize_file_name($type_doc . '-' . $doc_number) . '.pdf';
        $file_path = $base_dir . '/' . $filename;
        $file_url = $base_url . '/' . $filename;

        file_put_contents($file_path, $output);

        // 5. Enregistrement en BDD
        if ($existing) {
            $wpdb->update(
                $wpdb->prefix . 'pc_documents',
                ['nom_fichier' => $filename, 'date_creation' => current_time('mysql')],
                ['id' => $existing->id]
            );
        } else {
            $wpdb->insert($wpdb->prefix . 'pc_documents', [
                'reservation_id' => $reservation_id,
                'type_doc'       => $type_doc,
                'numero_doc'     => $doc_number,
                'nom_fichier'    => $filename,
                'chemin_fichier' => $file_path,
                'url_fichier'    => $file_url,
                'user_id'        => get_current_user_id()
            ]);
        }

        return ['success' => true, 'url' => $file_url, 'doc_number' => $doc_number];
    }

    /**
     * Construit le HTML complet en remplaçant les variables
     */
    private static function build_html($template_id, $resa, $doc_number)
    {
        $template_content = get_post_field('post_content', $template_id);

        // Remplacement du Shortcode [tableau_financier]
        if (strpos($template_content, '[tableau_financier]') !== false) {
            $table_html = self::generate_financial_table($resa);
            $template_content = str_replace('[tableau_financier]', $table_html, $template_content);
        }

        // Variables Globales
        $logo = get_field('pc_legal_logo', 'option');
        $color = get_field('pc_legal_color', 'option');

        // Variables Mentions Légales
        $footer_legal = sprintf(
            "%s - %s<br>SIRET: %s - TVA: %s - RCS: %s - Capital: %s",
            get_field('pc_legal_name', 'option'),
            get_field('pc_legal_address', 'option'),
            get_field('pc_legal_siret', 'option'),
            get_field('pc_legal_tva', 'option'),
            get_field('pc_legal_rcs', 'option'),
            get_field('pc_legal_capital', 'option')
        );

        // Remplacement des variables simples
        $vars = [
            '{numero_doc}'    => $doc_number,
            '{date_jour}'     => date_i18n('d/m/Y'),
            '{prenom_client}' => $resa->prenom,
            '{nom_client}'    => strtoupper($resa->nom),
            '{adresse_client}' => 'Adresse client ici...', // À ajouter en champ résa si besoin
            '{logement}'      => get_the_title($resa->item_id),
            '{date_arrivee}'  => date_i18n('d/m/Y', strtotime($resa->date_arrivee)),
            '{date_depart}'   => date_i18n('d/m/Y', strtotime($resa->date_depart)),
        ];

        $content = strtr($template_content, $vars);

        // Structure HTML globale du PDF
        ob_start();
?>
        <!DOCTYPE html>
        <html>

        <head>
            <meta charset="UTF-8">
            <style>
                body {
                    font-family: sans-serif;
                    font-size: 12px;
                    color: #333;
                }

                .header {
                    width: 100%;
                    border-bottom: 2px solid <?php echo $color; ?>;
                    padding-bottom: 20px;
                    margin-bottom: 30px;
                }

                .logo {
                    max-width: 200px;
                    max-height: 80px;
                }

                .doc-title {
                    font-size: 24px;
                    color: <?php echo $color; ?>;
                    text-transform: uppercase;
                    font-weight: bold;
                }

                .footer {
                    position: fixed;
                    bottom: 0;
                    left: 0;
                    right: 0;
                    height: 50px;
                    text-align: center;
                    font-size: 9px;
                    color: #777;
                    border-top: 1px solid #ddd;
                    padding-top: 10px;
                }

                /* Style du tableau financier injecté */
                .financial-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 20px;
                }

                .financial-table th {
                    background-color: #f3f4f6;
                    color: #333;
                    font-weight: bold;
                    padding: 10px;
                    text-align: left;
                    border-bottom: 2px solid #ddd;
                }

                .financial-table td {
                    padding: 10px;
                    border-bottom: 1px solid #eee;
                }

                .financial-table .col-price {
                    text-align: right;
                }

                .financial-table .total-row td {
                    font-weight: bold;
                    font-size: 14px;
                    border-top: 2px solid #333;
                }
            </style>
        </head>

        <body>
            <div class="header">
                <table width="100%">
                    <tr>
                        <td width="50%"><img src="<?php echo $logo; ?>" class="logo"></td>
                        <td width="50%" align="right">
                            <div class="doc-title"><?php echo $doc_number; ?></div>
                            Date : <?php echo date_i18n('d/m/Y'); ?>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="content">
                <?php echo $content; ?>
            </div>

            <div class="footer">
                <?php echo $footer_legal; ?>
            </div>
        </body>

        </html>
<?php
        return ob_get_clean();
    }

    /**
     * Génère le tableau financier HTML
     */
    private static function generate_financial_table($resa)
    {
        $lines = json_decode($resa->detail_tarif, true);
        if (!is_array($lines)) return '<p>Détail tarifaire indisponible.</p>';

        $html = '<table class="financial-table">';
        $html .= '<thead><tr><th>Désignation</th><th class="col-price">Prix</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($lines as $line) {
            $label = $line['label'] ?? '';
            $price = $line['price'] ?? '';
            // Si c'est un montant numérique brut, on le formate
            if (isset($line['amount']) && is_numeric($line['amount'])) {
                $price = number_format($line['amount'], 2, ',', ' ') . ' €';
            }
            $html .= "<tr><td>{$label}</td><td class='col-price'>{$price}</td></tr>";
        }

        // Totaux
        $html .= '<tr class="total-row"><td>TOTAL TTC</td><td class="col-price">' . number_format($resa->montant_total, 2, ',', ' ') . ' €</td></tr>';

        // Acomptes déjà payés
        // (Logique à affiner selon votre gestion des paiements)

        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * Point d'entrée AJAX
     */
    public static function ajax_generate_document()
    {
        // Sécurité
        // ... (Ajoutez check_ajax_referer ici quand vous aurez mis le nonce dans le JS)

        $resa_id = (int) $_POST['reservation_id'];
        $template_id = (int) $_POST['template_id'];

        $result = self::generate($template_id, $resa_id, true); // True = Force regeneration pour l'instant

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}
