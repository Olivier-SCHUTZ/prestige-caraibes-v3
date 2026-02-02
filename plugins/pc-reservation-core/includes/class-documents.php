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
        add_action('acf/init', [__CLASS__, 'register_acf_fields']);
        add_filter('acf/load_field/name=pc_linked_cgv', [__CLASS__, 'load_cgv_choices']);
        add_filter('acf/load_field/name=pc_cgv_default_location', [__CLASS__, 'load_cgv_choices']);
        add_filter('acf/load_field/name=pc_cgv_default_experience', [__CLASS__, 'load_cgv_choices']);
        add_action('admin_init', [__CLASS__, 'create_documents_table']);
        add_action('wp_ajax_pc_generate_document', [__CLASS__, 'ajax_generate_document']);
        add_action('wp_ajax_pc_get_documents_list', [__CLASS__, 'ajax_get_documents_list']);
    }

    public static function register_cpt()
    {
        register_post_type('pc_pdf_template', [
            'labels' => [
                'name' => 'Modèles PDF',
                'singular_name' => 'Modèle PDF',
                'menu_name' => 'Modèles PDF',
                'add_new_item' => 'Nouveau Modèle PDF',
                'edit_item' => 'Modifier le Modèle PDF',
            ],
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

        acf_add_local_field_group([
            'key' => 'group_pc_pdf_config',
            'title' => 'Paramètres du Modèle',
            'fields' => [
                // SUPPRESSION DU CHAMP "TYPE DE DOCUMENT" (Inutile car géré en natif)

                [
                    'key' => 'field_pc_model_context',
                    'label' => 'Contexte d\'affichage',
                    'name' => 'pc_model_context',
                    'type' => 'select',
                    'instructions' => 'Définit pour quel type de réservation ce modèle sera proposé.',
                    'choices' => [
                        'global' => 'Afficher pour tout (Défaut)',
                        'location' => 'Réservations de type \'logement\' uniquement',
                        'experience' => 'Réservations de type \'experience\' uniquement',
                    ],
                    'default_value' => 'global',
                    'ui' => 1,
                ],
                [
                    'key' => 'field_pc_linked_cgv',
                    'label' => 'Joindre les CGV ? (OPTIONNEL)',
                    'name' => 'pc_linked_cgv',
                    // On passe en WYSIWYG pour permettre une édition directe si besoin, 
                    // ou on garde Select si tu veux lier aux CGV globales. 
                    // Vu ta demande précédente, restons simple :
                    'type' => 'message',
                    'message' => 'Ce document est un modèle libre. Les CGV automatiques (Location/Expérience) ne seront pas ajoutées automatiquement, sauf si vous les copiez-collez dans l\'éditeur principal ci-dessus.',
                    // Si tu préfères garder la possibilité de forcer une CGV spécifique :
                    /*
                    'type' => 'select',
                    'choices' => [
                        'cgv_location' => 'CGV Location/Logement',
                        'cgv_experience' => 'CGV Expériences/Activités',
                        'cgv_sejour' => 'CGV Organisation Séjour',
                    ],
                    'allow_null' => 1,
                    */
                ],
            ],
            'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'pc_pdf_template']]]
        ]);
    }

    public static function load_cgv_choices($field)
    {
        $field['choices'] = [];
        if (have_rows('pc_pdf_cgv_library', 'option')) {
            while (have_rows('pc_pdf_cgv_library', 'option')) {
                the_row();
                $title = get_sub_field('cgv_title');
                if ($title) {
                    $field['choices'][$title] = $title;
                }
            }
        }
        return $field;
    }

    // --- MOTEUR DE GÉNÉRATION ---

    /**
     * ✨ **NOUVEAU MOTEUR HYBRIDE** : Génère un document (Natif ou Personnalisé)
     * 
     * @param int|string $template_id - ID du template personnalisé OU type natif (ex: 'native_devis')
     * @param int $reservation_id - ID de la réservation
     * @param bool $force_regenerate - Forcer la régénération
     */
    /**
     * ✨ MOTEUR HYBRIDE CORRIGÉ : Gère native_xxx, template_xxx et les IDs simples.
     */
    public static function generate($template_id_input, $reservation_id, $force_regenerate = false)
    {
        if (!class_exists('\Dompdf\Dompdf')) return ['success' => false, 'message' => 'Moteur Dompdf manquant.'];

        $resa = PCR_Reservation::get_by_id($reservation_id);
        if (!$resa) return ['success' => false, 'message' => 'Réservation introuvable.'];

        // --- 1. DÉTECTION & NETTOYAGE INTELLIGENT ---
        $template_id = 0;       // L'ID numérique (pour get_post)
        $type_doc = 'document'; // Le type (pour le routing)

        if (is_string($template_id_input) && strpos($template_id_input, 'native_') === 0) {
            // CAS 1 : Document NATIF (ex: "native_devis")
            $type_doc = str_replace('native_', '', $template_id_input);
            $template_id = 0;
        } elseif (is_string($template_id_input) && strpos($template_id_input, 'template_') === 0) {
            // CAS 2 : Document CUSTOM (ex: "template_7170")
            // C'est ici que ça bloquait : on nettoie le préfixe pour récupérer l'ID entier.
            $template_id = (int) str_replace('template_', '', $template_id_input);
            $type_doc = 'document'; // Par défaut pour les customs
        } else {
            // CAS 3 : ID Direct (Legacy)
            $template_id = (int) $template_id_input;
            if ($template_id > 0) {
                // On garde l'ancien comportement si un type était défini via ACF
                $type_doc = get_field('pc_doc_type', $template_id) ?: 'document';
            }
        }

        // =================================================================
        // RÈGLE : BLOCAGE FACTURE FINALE SI ACOMPTE NON GÉNÉRÉ
        // =================================================================
        if ($type_doc === 'facture') {
            $acompte_prevu = (float) ($resa->montant_acompte ?? 0);
            if ($acompte_prevu > 0) {
                global $wpdb;
                $table_doc = $wpdb->prefix . 'pc_documents';
                $has_acompte = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$table_doc} WHERE reservation_id = %d AND type_doc = 'facture_acompte'",
                    $reservation_id
                ));

                if (!$has_acompte) {
                    return [
                        'success' => false,
                        'error_code' => 'missing_deposit',
                        'message' => 'BLOQUÉ : Vous devez générer la "Facture d\'Acompte" avant le Solde.'
                    ];
                }
            }
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'pc_documents';

        // Vérification existence (sauf pour les docs customs qui peuvent être multiples)
        $existing = null;
        if ($type_doc !== 'document') {
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE reservation_id = %d AND type_doc = %s LIMIT 1",
                $reservation_id,
                $type_doc
            ));
        }

        // SMART REGEN (Archivage)
        if ($existing && $force_regenerate && in_array($type_doc, ['facture', 'facture_acompte'])) {
            self::generate_auto_credit_note($existing, $resa);
            $archived_type = $type_doc . '_archived_' . time();
            $wpdb->update($table_name, ['type_doc' => $archived_type], ['id' => $existing->id]);
            $existing = null;
        }

        // Check doublon
        if ($existing && !$force_regenerate && in_array($type_doc, ['facture', 'facture_acompte', 'avoir'])) {
            return [
                'success' => false,
                'error_code' => 'document_exists',
                'message' => 'Ce document existe déjà.'
            ];
        }

        // --- GESTION DES NUMÉROS ---
        if ($type_doc === 'contrat') {
            $doc_number = 'CONTRAT-RESA-' . $reservation_id;
        } elseif ($type_doc === 'voucher') {
            $doc_number = 'VOUCHER-RESA-' . $reservation_id;
        } elseif ($existing) {
            $doc_number = $existing->numero_doc;
        } elseif ($type_doc === 'document') {
            // Numérotation pour les docs libres : DOC-202X-ID
            $doc_number = 'DOC-' . date('Y') . '-' . $reservation_id . '-' . ($template_id > 0 ? $template_id : time());
        } else {
            $doc_number = self::generate_next_number($type_doc);
        }

        // --- ROUTING (Aiguillage) ---
        $html_content = '';

        if (in_array($type_doc, ['facture', 'devis', 'avoir'])) {
            $html_content = self::render_financial_document($resa, $doc_number, $type_doc, $template_id);
        } elseif ($type_doc === 'facture_acompte') {
            $html_content = self::render_deposit_invoice($resa, $doc_number, $template_id);
        } elseif ($type_doc === 'voucher') {
            $html_content = self::render_voucher($resa, $doc_number);
        } elseif ($type_doc === 'contrat') {
            $html_content = self::render_contract($resa, $doc_number, $template_id);
        } else {
            // DOC PERSONNALISÉ (Le cas qui plantait avant)
            // Maintenant $template_id est bien un entier (ex: 7170)
            $html_content = self::render_custom_document($resa, $doc_number, $template_id);
        }

        // --- GÉNÉRATION PDF ---
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html_content);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $output = $dompdf->output();

        // --- SAUVEGARDE ---
        $upload_dir = wp_upload_dir();
        $rel_path   = '/pc-reservation/documents/' . $reservation_id;
        $abs_path   = $upload_dir['basedir'] . $rel_path;
        $url_path   = $upload_dir['baseurl'] . $rel_path;

        if (!file_exists($abs_path)) mkdir($abs_path, 0755, true);

        if ($type_doc === 'contrat') {
            $nom_client = sanitize_file_name($resa->nom . ' ' . $resa->prenom);
            $nom_logement = sanitize_file_name(get_the_title($resa->item_id));
            $filename = "Contrat de location {$nom_client}, {$nom_logement}.pdf";
        } elseif ($type_doc === 'document') {
            // Nommage propre pour les modèles perso
            $template_title = ($template_id > 0) ? get_the_title($template_id) : 'Document';
            $filename = sanitize_file_name($template_title . '-' . $resa->id) . '.pdf';
        } else {
            $filename = sanitize_file_name($doc_number) . '.pdf';
        }

        $file_full_path = $abs_path . '/' . $filename;
        $file_url       = $url_path . '/' . $filename;

        file_put_contents($file_full_path, $output);

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

    private static function generate_next_number($type)
    {
        if ($type === 'devis') {
            $prefix = get_field('pc_quote_prefix', 'option') ?: 'DEV-' . date('Y') . '-';
            // Pas de compteur auto pour devis dans cette version, on garde le timestamp ou on ajoute un compteur si besoin
            return $prefix . time(); // Ou logique compteur si vous l'ajoutez
        } elseif ($type === 'avoir') {
            // [NOUVEAU] Logique spécifique Avoir
            $prefix = get_field('pc_credit_note_prefix', 'option') ?: 'AVOIR-' . date('Y') . '-';
            $next   = (int) get_field('pc_credit_note_next', 'option') ?: 1;

            update_field('pc_credit_note_next', $next + 1, 'option');
            return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
        } else {
            // Par défaut (facture, facture_acompte) -> Compteur Facture
            $prefix = get_field('pc_invoice_prefix', 'option') ?: 'FAC-' . date('Y') . '-';
            $next   = (int) get_field('pc_invoice_next', 'option') ?: 1;

            update_field('pc_invoice_next', $next + 1, 'option');
            return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
        }
    }

    /**
     * Analyse le JSON detail_tarif, nettoie les prix et gère les quantités.
     * VERSION CORRIGÉE : Nettoyage prix renforcé et calculs précis.
     */
    private static function get_financial_data($resa)
    {
        $lines = json_decode($resa->detail_tarif, true);
        if (!is_array($lines)) $lines = [];

        // Récupération des Taux
        $item_id = $resa->item_id;
        $tva_logement = (float) get_field('taux_tva', $item_id);
        $tva_menage_val = get_field('taux_tva_menage', $item_id);
        $tva_menage = ($tva_menage_val !== '' && $tva_menage_val !== null) ? (float)$tva_menage_val : 8.5;
        $tva_plus_value = 8.5;

        $data = [
            'lines' => [],
            'total_ht' => 0,
            'total_tva' => 0,
            'total_ttc' => 0,
        ];

        foreach ($lines as $line) {
            $label_raw = $line['label'];

            // --- 1. NETTOYAGE ROBUSTE DU PRIX ---
            // On récupère la valeur brute
            $price_raw = isset($line['price']) ? $line['price'] : (isset($line['amount']) ? $line['amount'] : 0);

            // Conversion en chaîne pour traitement
            $str = (string)$price_raw;
            // Décodage entités HTML (ex: &nbsp;)
            $str = html_entity_decode($str);
            // Suppression des espaces insécables (code ASCII 160 et UTF-8 C2A0)
            $str = str_replace(["\xc2\xa0", "\xa0", " "], "", $str);
            // On ne garde que chiffres, point, virgule, et le signe moins
            $clean_price = preg_replace('/[^0-9,.-]/', '', $str);
            // Remplacement virgule par point pour le float
            $clean_price = str_replace(',', '.', $clean_price);

            $total_line_ttc = (float) $clean_price;

            // --- 2. LOGIQUE QUANTITÉ ---
            $quantity = 1;
            $description = $label_raw;

            // Détection "3 Adulte"
            if (preg_match('/^(\d+)\s+(.*)/', $label_raw, $matches)) {
                $quantity = (int) $matches[1];
                $description = $matches[2];
            }

            $unit_ttc = ($quantity > 0) ? $total_line_ttc / $quantity : 0;

            // --- 3. DÉTECTION TAUX TVA ---
            $taux_applicable = $tva_logement;
            $label_lower = mb_strtolower($label_raw);

            if (strpos($label_lower, 'taxe de séjour') !== false) {
                $taux_applicable = 0;
            } elseif (strpos($label_lower, 'ménage') !== false || strpos($label_lower, 'menage') !== false) {
                $taux_applicable = $tva_menage;
            } elseif (strpos($label_lower, 'plus value') !== false || strpos($label_lower, 'plus-value') !== false) {
                $taux_applicable = $tva_plus_value;
            } elseif (strpos($label_lower, 'remise') !== false) {
                // Pour une remise, on garde le taux du logement (pour réduire la TVA proportionnellement)
                // Le montant sera négatif, le calcul restera juste.
                $taux_applicable = $tva_logement;
            }

            // --- 4. CALCUL HT / TVA ---
            if ($taux_applicable > 0) {
                $total_line_ht = $total_line_ttc / (1 + ($taux_applicable / 100));
                $total_line_tva = $total_line_ttc - $total_line_ht;
                $unit_ht = $unit_ttc / (1 + ($taux_applicable / 100));
            } else {
                $total_line_ht = $total_line_ttc;
                $total_line_tva = 0;
                $unit_ht = $unit_ttc;
            }

            // Ajout à la liste
            $data['lines'][] = [
                'description' => $description,
                'quantity'    => $quantity,
                'unit_ht'     => $unit_ht,
                'taux_tva'    => $taux_applicable,
                'total_ht'    => $total_line_ht,
                'total_tva'   => $total_line_tva,
                'total_ttc'   => $total_line_ttc
            ];

            // --- 5. CUMUL DES TOTAUX ---
            $data['total_ht']  += $total_line_ht;
            $data['total_tva'] += $total_line_tva;
            $data['total_ttc'] += $total_line_ttc;
        }

        // --- 6. GESTION DES PAIEMENTS ---
        $data['deja_paye'] = 0;
        $data['date_dernier_paiement'] = null;

        if (class_exists('PCR_Payment')) {
            $payments = PCR_Payment::get_for_reservation($resa->id);
            if ($payments) {
                foreach ($payments as $p) {
                    if ($p->statut === 'paye') {
                        $data['deja_paye'] += (float)$p->montant;
                        $data['date_dernier_paiement'] = $p->date_paiement;
                    }
                }
            }
        }

        // Reste à payer (sécurité anti-négatif)
        $data['reste_a_payer'] = max(0, $data['total_ttc'] - $data['deja_paye']);

        return $data;
    }

    // --- VISUEL ---

    private static function get_common_css($color)
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

    // --- RENDU : DOCUMENT PERSONNALISÉ (Contenu libre + Variables Simples) ---
    private static function render_custom_document($resa, $doc_number, $template_id)
    {
        // 1. Récupération du contenu
        $post = get_post($template_id);
        if (!$post) return "Erreur : Modèle introuvable (ID $template_id)";

        // 2. Calculs Financiers & Durée
        $fin = self::get_financial_data($resa);

        $ts_arr = strtotime($resa->date_arrivee);
        $ts_dep = strtotime($resa->date_depart);
        $duree  = ceil(($ts_dep - $ts_arr) / 86400);

        // Construction adresse
        $adresse_client = trim(($resa->adresse ?? '') . ' ' . ($resa->code_postal ?? '') . ' ' . ($resa->ville ?? ''));
        if (empty($adresse_client)) $adresse_client = "Adresse non renseignée";

        // 3. Définition des Variables
        $variables = [
            // Client
            '{prenom_client}' => $resa->prenom,
            '{nom_client}'    => strtoupper($resa->nom),
            '{email_client}'  => $resa->email,
            '{telephone}'     => $resa->telephone,
            '{adresse_client}' => $adresse_client,

            // Séjour
            '{date_arrivee}'  => date_i18n('d/m/Y', $ts_arr),
            '{date_depart}'   => date_i18n('d/m/Y', $ts_dep),
            '{duree_sejour}'  => $duree . ' nuit(s)',
            '{logement}'      => get_the_title($resa->item_id),
            '{numero_resa}'   => $resa->id,

            // Finances (Montants seuls)
            '{montant_total}' => number_format($fin['total_ttc'], 2, ',', ' ') . ' €',
            '{acompte_paye}'  => number_format($fin['deja_paye'], 2, ',', ' ') . ' €',
            '{solde_restant}' => number_format($fin['reste_a_payer'], 2, ',', ' ') . ' €',
        ];

        // 4. Remplacement
        $content = wpautop($post->post_content);
        $content = str_replace(array_keys($variables), array_values($variables), $content);

        // 5. Branding
        $logo_url = get_field('pc_pdf_logo', 'option');
        $logo = self::get_image_base64($logo_url);
        $color = get_field('pc_pdf_primary_color', 'option') ?: '#000000';
        $company = [
            'name'    => get_field('pc_legal_name', 'option'),
            'siret'   => get_field('pc_legal_siret', 'option'),
            'address' => get_field('pc_legal_address', 'option'),
            'email'   => get_field('pc_legal_email', 'option'),
        ];

        ob_start();
?>
        <!DOCTYPE html>
        <html>

        <head>
            <meta charset="UTF-8">
            <?php echo self::get_common_css($color); ?>
            <style>
                .custom-content {
                    font-size: 12px;
                    line-height: 1.6;
                    color: #333;
                    margin-top: 20px;
                }

                .custom-content h1,
                .custom-content h2 {
                    color: <?php echo $color; ?>;
                    border-bottom: 1px solid #eee;
                    padding-bottom: 5px;
                    margin-top: 20px;
                }
            </style>
        </head>

        <body>
            <div class="header">
                <div class="left">
                    <?php if ($logo): ?><img src="<?php echo $logo; ?>" class="logo"><?php else: ?><h2><?php echo $company['name']; ?></h2><?php endif; ?>
                </div>
                <div class="right doc-info">
                    <div class="doc-type"><?php echo strtoupper($post->post_title); ?></div>
                    <div class="doc-meta">
                        <strong>N° :</strong> <?php echo $doc_number; ?><br>
                        <strong>Date :</strong> <?php echo date_i18n('d/m/Y'); ?><br>
                        <strong>Réf :</strong> #<?php echo $resa->id; ?>
                    </div>
                </div>
                <div class="clear"></div>
            </div>

            <div class="addresses">
                <div class="addr-box">
                    <strong><?php echo $company['name']; ?></strong><br>
                    <?php echo nl2br($company['address']); ?>
                </div>
                <div class="addr-box client">
                    <strong>À l'attention de :</strong><br>
                    <?php echo $resa->prenom . ' ' . strtoupper($resa->nom); ?><br>
                    <?php if (!empty($adresse_client) && $adresse_client !== "Adresse non renseignée") echo $adresse_client . '<br>'; ?>
                    <?php echo $resa->email; ?><br>
                    <?php echo $resa->telephone; ?>
                </div>
                <div class="clear"></div>
            </div>

            <div class="custom-content">
                <?php echo $content; ?>
            </div>

            <div class="footer">
                <?php echo $company['name']; ?> - SIRET : <?php echo $company['siret']; ?> - <?php echo $company['email']; ?>
            </div>

            <?php
            // INJECTION CGV (SI LIÉES DANS L'ADMIN)
            $custom_cgv_content = '';
            if (!empty($template_id) && is_numeric($template_id)) {
                $custom_cgv_content = get_field('pc_linked_cgv', $template_id);
            }
            if (!empty($custom_cgv_content)) {
                echo '<div style="page-break-before: always;"></div>';
                echo '<h3 class="uppercase" style="border-bottom:1px solid #ccc; padding-bottom:5px; margin-bottom:15px;">Conditions Générales</h3>';
                echo '<div style="font-size:10px; text-align:justify; color:#444;">' . wpautop($custom_cgv_content) . '</div>';
            }
            ?>
        </body>

        </html>
    <?php
        return ob_get_clean();
    }

    // --- RENDUS ---

    private static function render_financial_document($resa, $doc_number, $type_doc, $template_id)
    {
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

        $fin = self::get_financial_data($resa);
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
            // On cible les documents financiers (Devis, Factures, Avoirs)
            if (in_array($type_doc, ['facture', 'devis', 'avoir', 'facture_acompte'])) {
                if (isset($resa->type) && $resa->type === 'location') {
                    $cgv_content = get_field('cgv_location', 'option');
                } elseif (isset($resa->type) && $resa->type === 'experience') {
                    $cgv_content = get_field('cgv_experience', 'option');
                } else {
                    // Cas "Mixte" ou "Organisation de séjour"
                    $cgv_content = get_field('cgv_sejour', 'option');
                }
            }

            // 2. Fallback : Si c'est un modèle personnalisé (ID numérique) qui force une CGV spécifique
            // On vérifie que ce n'est pas un template natif (ex: 'native_devis')
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

    // --- RENDU 4 : FACTURE D'ACOMPTE (Spécifique) ---
    private static function render_deposit_invoice($resa, $doc_number, $template_id)
    {
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
                        $deja_paye += (float)$p->montant;
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
        $logo = self::get_image_base64($logo_url);
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
            <?php echo self::get_common_css($color); ?>
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
                    <strong><?php echo $resa->prenom . ' ' . strtoupper($resa->nom); ?></strong><br>
                    Email : <?php echo $resa->email; ?>
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
                            <strong><?php echo $description_ligne; ?></strong><br>
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

    private static function render_voucher($resa, $doc_number)
    {
        $logo_url = get_field('pc_pdf_logo', 'option');
        $logo = self::get_image_base64($logo_url);
        $color = get_field('pc_pdf_primary_color', 'option') ?: '#000000';
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

    // --- RENDU 3 : CONTRAT DE LOCATION (Version Multi-Plateformes) ---
    private static function render_contract($resa, $doc_number, $template_id)
    {
        // 1. Chargement des Données
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
        $logo = self::get_image_base64($logo_url);

        $signature_url = get_site_url(null, '/wp-content/uploads/2025/12/Responsable.png');
        $signature_img = self::get_image_base64($signature_url);

        $color = get_field('pc_pdf_primary_color', 'option') ?: '#000000';
        $agency = [
            'name'    => get_field('pc_legal_name', 'option'),
            'address' => get_field('pc_legal_address', 'option'),
            'email'   => get_field('pc_legal_email', 'option'),
            'phone'   => get_field('pc_legal_phone', 'option'),
            'siret'   => get_field('pc_legal_siret', 'option'),
        ];

        // Données Financières & Dates
        $fin = self::get_financial_data($resa);
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
            <?php echo self::get_common_css($color); ?>
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

    // --- AUTRES HELPERS (Credit Note, Base64, Preview, Ajax) ---
    // (J'ai inclus les méthodes nécessaires, assurez-vous de conserver generate_auto_credit_note, preview, etc. si vous utilisez le fichier entier)

    private static function generate_auto_credit_note($old_doc, $resa)
    {
        // 1. On génère un VRAI numéro d'avoir (ex: AVOIR-2025-001)
        $avoir_number = self::generate_next_number('avoir');

        // 2. Rendu HTML (On passe l'ancien numéro $old_doc->numero_doc pour l'écrire DANS le PDF)
        $html_content = self::render_credit_note($resa, $avoir_number, $old_doc->numero_doc, $old_doc->type_doc);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html_content);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $output = $dompdf->output();

        // 3. Sauvegarde
        $upload_dir = wp_upload_dir();
        $rel_path   = '/pc-reservation/documents/' . $resa->id;
        if (!file_exists($upload_dir['basedir'] . $rel_path)) mkdir($upload_dir['basedir'] . $rel_path, 0755, true);

        // 4. Nom du fichier : On utilise le numéro de l'AVOIR pour l'unicité
        // Ex: AVOIR-2025-001.pdf
        $filename = sanitize_file_name($avoir_number) . '.pdf';
        $file_full_path = $upload_dir['basedir'] . $rel_path . '/' . $filename;
        $file_url       = $upload_dir['baseurl'] . $rel_path . '/' . $filename;

        file_put_contents($file_full_path, $output);

        global $wpdb;
        $table_name = $wpdb->prefix . 'pc_documents';

        // 5. Insertion BDD
        $wpdb->insert($table_name, [
            'reservation_id' => $resa->id,
            'type_doc'       => 'avoir_' . time(), // Type unique pour l'historique
            'numero_doc'     => $avoir_number,     // Le numéro séquentiel AVOIR
            'nom_fichier'    => $filename,
            'chemin_fichier' => $file_full_path,
            'url_fichier'    => $file_url,
            'user_id'        => get_current_user_id(),
            'date_creation'  => current_time('mysql')
        ]);
    }

    private static function render_credit_note($resa, $doc_number, $ref_facture_origine, $type_origine)
    {
        $fin = self::get_financial_data($resa);
        $logo_url = get_field('pc_pdf_logo', 'option');
        $logo = self::get_image_base64($logo_url);
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
            <?php echo self::get_common_css($color); ?>
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

    public static function preview($template_id)
    {
        // 1. Création d'une "Fausse Réservation" complète pour tester toutes les variables
        $resa = new stdClass();
        $resa->id = 12345;
        $resa->type = 'location'; // ou 'experience'
        $resa->item_id = 0; // ID factice
        $resa->prenom = 'Jean';
        $resa->nom = 'PREVIEW';
        $resa->email = 'jean.preview@example.com';
        $resa->telephone = '06 90 00 00 00';
        $resa->adresse = '12 Rue des Cocotiers';
        $resa->code_postal = '97100';
        $resa->ville = 'Pointe-à-Pitre';

        // Dates (J+10 à J+17)
        $resa->date_arrivee = date('Y-m-d', strtotime('+10 days'));
        $resa->date_depart = date('Y-m-d', strtotime('+17 days'));
        $resa->date_creation = date('Y-m-d H:i:s');

        $resa->adultes = 2;
        $resa->enfants = 1;
        $resa->montant_total = 1650.00;
        $resa->montant_acompte = 495.00; // 30%

        // JSON Tarifaire factice pour tester les tableaux
        $resa->detail_tarif = json_encode([
            ['label' => 'Hébergement (7 nuits)', 'amount' => 1400, 'price' => 1400],
            ['label' => 'Frais de ménage', 'amount' => 150, 'price' => 150],
            ['label' => 'Taxe de séjour', 'amount' => 100, 'price' => 100],
        ]);

        // 2. Détermination du Type de Document
        // Si le champ ACF 'pc_doc_type' est vide (ce qui est le cas pour les nouveaux modèles libres), on met 'document'
        $type_doc = get_field('pc_doc_type', $template_id) ?: 'document';

        $doc_number = 'PREVIEW-' . date('Ymd');
        $html_content = '';

        // 3. Aiguillage (Exactement comme la génération réelle)
        if (in_array($type_doc, ['facture', 'devis', 'avoir'])) {
            $html_content = self::render_financial_document($resa, $doc_number, $type_doc, $template_id);
        } elseif ($type_doc === 'facture_acompte') {
            $html_content = self::render_deposit_invoice($resa, $doc_number, $template_id);
        } elseif ($type_doc === 'voucher') {
            // Mock pour le titre du logement dans le voucher
            add_filter('the_title', function ($title, $id) use ($resa) {
                return ($id === $resa->item_id) ? 'Villa de Test (Vue Mer)' : $title;
            }, 10, 2);
            $html_content = self::render_voucher($resa, $doc_number);
        } elseif ($type_doc === 'contrat') {
            $html_content = self::render_contract($resa, $doc_number, $template_id);
        } else {
            // ✨ C'EST ICI : Appel du rendu personnalisé pour les modèles libres
            $html_content = self::render_custom_document($resa, $doc_number, $template_id);
        }

        // 4. Génération
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html_content);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream("apercu-document.pdf", ["Attachment" => 0]); // Affichage dans le navigateur
        exit;
    }

    public static function ajax_get_documents_list()
    {
        check_ajax_referer('pc_resa_manual_create', 'nonce');

        $resa_id = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;
        if (!$resa_id) wp_send_json_error(['message' => 'ID manquant']);

        global $wpdb;
        $table_name = $wpdb->prefix . 'pc_documents';

        // La requête est CORRECTE par rapport à votre schéma CREATE TABLE ligne 47
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT type_doc, nom_fichier, url_fichier, date_creation FROM $table_name WHERE reservation_id = %d ORDER BY date_creation DESC",
            $resa_id
        ));

        $documents = [];
        foreach ($results as $row) {
            $documents[] = [
                'type_doc'      => ucfirst(str_replace('_', ' ', $row->type_doc)), // ex: facture_acompte -> Facture acompte
                'nom_fichier'   => $row->nom_fichier,
                'date_creation' => date('d/m/Y H:i', strtotime($row->date_creation)),
                'url_fichier'   => $row->url_fichier
            ];
        }

        wp_send_json_success($documents);
    }

    public static function ajax_generate_document()
    {
        while (ob_get_level()) ob_end_clean();
        check_ajax_referer('pc_resa_manual_create', 'nonce');

        $template_id = isset($_POST['template_id']) ? sanitize_text_field($_POST['template_id']) : '';

        $resa_id = (int) ($_POST['reservation_id'] ?? 0);
        $force_regen = isset($_POST['force']) && $_POST['force'] === 'true';

        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => 'Non autorisé']);

        if (function_exists('ini_set')) ini_set('memory_limit', '512M');
        set_time_limit(120);

        try {
            // On passe force_regen qui vient du JS
            $res = self::generate($template_id, $resa_id, $force_regen);
            if ($res['success']) wp_send_json_success($res);
            else wp_send_json_error($res);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    private static function get_image_base64($url)
    {
        if (empty($url)) return '';
        $upload_dir = wp_upload_dir();
        $path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);
        $data = '';
        if (file_exists($path)) {
            $data = file_get_contents($path);
        } else {
            $data = @file_get_contents($url);
        }
        if (!$data) return '';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = ($ext === 'png') ? 'image/png' : 'image/jpeg';
        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }
}
