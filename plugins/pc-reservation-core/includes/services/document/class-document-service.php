<?php

if (!defined('ABSPATH')) {
    exit;
}

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Class PCR_Document_Service
 * * Orchestrateur du module de documents. Gère la logique métier complexe,
 * l'instanciation de Dompdf, la sauvegarde physique et l'aiguillage vers les Renderers.
 */
class PCR_Document_Service
{
    /**
     * @var PCR_Document_Service|null
     */
    private static $instance = null;

    /**
     * @return PCR_Document_Service
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Récupère le PDF existant ou le génère (Logique intelligente).
     *
     * @param string $doc_type
     * @param int    $reservation_id
     * @return array
     */
    public function generate_native($doc_type, $reservation_id)
    {
        $repo = PCR_Document_Repository::get_instance();
        $existing = $repo->get_latest_document_by_type($reservation_id, $doc_type);

        if ($existing) {
            $file_path = $existing->chemin_fichier;

            // Tentative de reconstruction du chemin si le fichier a bougé
            if (!file_exists($file_path)) {
                $upload_dir = wp_upload_dir();
                $rel_path = '/pc-reservation/documents/' . $reservation_id . '/' . $existing->nom_fichier;
                $file_path = $upload_dir['basedir'] . $rel_path;
            }

            if (file_exists($file_path)) {
                return [
                    'success' => true,
                    'path' => $file_path,
                    'url' => $existing->url_fichier,
                    'source' => 'existing'
                ];
            }
        }

        // SINON (Pas trouvé ou fichier supprimé) -> ON GÉNÈRE À LA VOLÉE
        $template_code = 'native_' . $doc_type;
        $result = self::generate($template_code, $reservation_id, true);

        if ($result['success']) {
            $upload_dir = wp_upload_dir();
            $generated_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $result['url']);

            return [
                'success' => true,
                'path' => $generated_path,
                'url' => $result['url'],
                'source' => 'generated'
            ];
        }

        return $result;
    }

    /**
     * Moteur hybride : Génère un document natif ou personnalisé.
     *
     * @param int|string $template_id_input
     * @param int        $reservation_id
     * @param bool       $force_regenerate
     * @return array
     */
    public function generate($template_id_input, $reservation_id, $force_regenerate = false)
    {
        if (!class_exists('\Dompdf\Dompdf')) {
            return ['success' => false, 'message' => 'Moteur Dompdf manquant.'];
        }

        $resa = PCR_Reservation::get_by_id($reservation_id);
        if (!$resa) {
            return ['success' => false, 'message' => 'Réservation introuvable.'];
        }

        $repo = PCR_Document_Repository::get_instance();

        // --- 1. DÉTECTION & NETTOYAGE INTELLIGENT ---
        $template_id = 0;
        $type_doc = 'document';

        if (is_string($template_id_input) && strpos($template_id_input, 'native_') === 0) {
            $type_doc = str_replace('native_', '', $template_id_input);
        } elseif (is_string($template_id_input) && strpos($template_id_input, 'template_') === 0) {
            $template_id = (int) str_replace('template_', '', $template_id_input);
        } else {
            $template_id = (int) $template_id_input;
            if ($template_id > 0) {
                $type_doc = get_field('pc_doc_type', $template_id) ?: 'document';
            }
        }

        // --- 2. RÈGLE MÉTIER : BLOCAGE FACTURE FINALE ---
        if ($type_doc === 'facture') {
            $acompte_prevu = (float) ($resa->montant_acompte ?? 0);
            if ($acompte_prevu > 0 && !$repo->has_deposit_invoice($reservation_id)) {
                return [
                    'success' => false,
                    'error_code' => 'missing_deposit',
                    'message' => 'BLOQUÉ : Vous devez générer la "Facture d\'Acompte" avant le Solde.'
                ];
            }
        }

        // --- 3. GESTION DE L'EXISTANT ---
        $existing = null;
        if ($type_doc !== 'document') {
            $existing = $repo->get_latest_document_by_type($reservation_id, $type_doc);
        }

        if ($existing && $force_regenerate && in_array($type_doc, ['facture', 'facture_acompte'])) {
            $this->generate_auto_credit_note($existing, $resa);
            $archived_type = $type_doc . '_archived_' . time();
            $repo->archive_document($existing->id, $archived_type);
            $existing = null;
        }

        if ($existing && !$force_regenerate && in_array($type_doc, ['facture', 'facture_acompte', 'avoir'])) {
            return ['success' => false, 'error_code' => 'document_exists', 'message' => 'Ce document existe déjà.'];
        }

        // --- 4. NUMÉROTATION ---
        if ($type_doc === 'contrat') {
            $doc_number = 'CONTRAT-RESA-' . $reservation_id;
        } elseif ($type_doc === 'voucher') {
            $doc_number = 'VOUCHER-RESA-' . $reservation_id;
        } elseif ($existing) {
            $doc_number = $existing->numero_doc;
        } elseif ($type_doc === 'document') {
            $doc_number = 'DOC-' . date('Y') . '-' . $reservation_id . '-' . ($template_id > 0 ? $template_id : time());
        } else {
            $doc_number = $this->generate_next_number($type_doc);
        }

        // --- 5. AIGUILLAGE VERS LES RENDERERS ---
        $html_content = '';
        if (in_array($type_doc, ['facture', 'devis', 'avoir'])) {
            $renderer = new PCR_Invoice_Renderer();
            $html_content = $renderer->render($resa, $doc_number, ['type_doc' => $type_doc, 'template_id' => $template_id]);
        } elseif ($type_doc === 'facture_acompte') {
            $renderer = new PCR_Deposit_Renderer();
            $html_content = $renderer->render($resa, $doc_number, ['template_id' => $template_id]);
        } elseif ($type_doc === 'voucher') {
            $renderer = new PCR_Voucher_Renderer();
            $html_content = $renderer->render($resa, $doc_number);
        } elseif ($type_doc === 'contrat') {
            $renderer = new PCR_Contract_Renderer();
            $html_content = $renderer->render($resa, $doc_number, ['template_id' => $template_id]);
        } else {
            $renderer = new PCR_Custom_Renderer();
            $html_content = $renderer->render($resa, $doc_number, ['template_id' => $template_id]);
        }

        // --- 6. GÉNÉRATION PDF (DOMPDF) ---
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html_content);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $output = $dompdf->output();

        // --- 7. SAUVEGARDE PHYSIQUE ---
        $upload_dir = wp_upload_dir();
        $rel_path   = '/pc-reservation/documents/' . $reservation_id;
        $abs_path   = $upload_dir['basedir'] . $rel_path;
        $url_path   = $upload_dir['baseurl'] . $rel_path;

        if (!file_exists($abs_path)) wp_mkdir_p($abs_path);

        if ($type_doc === 'contrat') {
            $nom_client = sanitize_file_name($resa->nom . ' ' . $resa->prenom);
            $nom_logement = sanitize_file_name(get_the_title($resa->item_id));
            $filename = "Contrat de location {$nom_client}, {$nom_logement}.pdf";
        } elseif ($type_doc === 'document') {
            $template_title = ($template_id > 0) ? get_the_title($template_id) : 'Document';
            $filename = sanitize_file_name($template_title . '-' . $resa->id) . '.pdf';
        } else {
            $filename = sanitize_file_name($doc_number) . '.pdf';
        }

        $file_full_path = $abs_path . '/' . $filename;
        $file_url       = $url_path . '/' . $filename;

        file_put_contents($file_full_path, $output);

        // --- 8. SAUVEGARDE EN BDD ---
        $repo->upsert_document(
            $reservation_id,
            $type_doc,
            $doc_number,
            $filename,
            $file_full_path,
            $file_url,
            get_current_user_id()
        );

        return ['success' => true, 'url' => $file_url, 'doc_number' => $doc_number];
    }

    /**
     * Génère une note de crédit automatique.
     */
    private function generate_auto_credit_note($old_doc, $resa)
    {
        $avoir_number = $this->generate_next_number('avoir');

        $renderer = new PCR_Invoice_Renderer();
        $html_content = $renderer->render_cancellation_credit_note($resa, $avoir_number, $old_doc->numero_doc);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html_content);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $output = $dompdf->output();

        $upload_dir = wp_upload_dir();
        $rel_path   = '/pc-reservation/documents/' . $resa->id;
        $abs_path   = $upload_dir['basedir'] . $rel_path;
        if (!file_exists($abs_path)) wp_mkdir_p($abs_path);

        $filename = sanitize_file_name($avoir_number) . '.pdf';
        $file_full_path = $abs_path . '/' . $filename;
        $file_url       = $upload_dir['baseurl'] . $rel_path . '/' . $filename;

        file_put_contents($file_full_path, $output);

        PCR_Document_Repository::get_instance()->insert_document([
            'reservation_id' => $resa->id,
            'type_doc'       => 'avoir_' . time(),
            'numero_doc'     => $avoir_number,
            'nom_fichier'    => $filename,
            'chemin_fichier' => $file_full_path,
            'url_fichier'    => $file_url,
            'user_id'        => get_current_user_id(),
            'date_creation'  => current_time('mysql')
        ]);
    }

    /**
     * Génère le numéro séquentiel suivant.
     */
    private function generate_next_number($type)
    {
        if ($type === 'devis') {
            $prefix = get_field('pc_quote_prefix', 'option') ?: 'DEV-' . date('Y') . '-';
            return $prefix . time();
        } elseif ($type === 'avoir') {
            $prefix = get_field('pc_credit_note_prefix', 'option') ?: 'AVOIR-' . date('Y') . '-';
            $next   = (int) get_field('pc_credit_note_next', 'option') ?: 1;
            update_field('pc_credit_note_next', $next + 1, 'option');
            return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
        } else {
            $prefix = get_field('pc_invoice_prefix', 'option') ?: 'FAC-' . date('Y') . '-';
            $next   = (int) get_field('pc_invoice_next', 'option') ?: 1;
            update_field('pc_invoice_next', $next + 1, 'option');
            return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
        }
    }

    /**
     * Aperçu d'un modèle (Preview).
     */
    public function preview($template_id)
    {
        $resa = new stdClass();
        $resa->id = 12345;
        $resa->type = 'location';
        $resa->item_id = 0;
        $resa->prenom = 'Jean';
        $resa->nom = 'PREVIEW';
        $resa->email = 'jean.preview@example.com';
        $resa->telephone = '06 90 00 00 00';
        $resa->adresse = '12 Rue des Cocotiers';
        $resa->code_postal = '97100';
        $resa->ville = 'Pointe-à-Pitre';
        $resa->date_arrivee = date('Y-m-d', strtotime('+10 days'));
        $resa->date_depart = date('Y-m-d', strtotime('+17 days'));
        $resa->date_creation = date('Y-m-d H:i:s');
        $resa->adultes = 2;
        $resa->enfants = 1;
        $resa->montant_total = 1650.00;
        $resa->montant_acompte = 495.00;
        $resa->detail_tarif = json_encode([
            ['label' => 'Hébergement (7 nuits)', 'amount' => 1400, 'price' => 1400],
            ['label' => 'Frais de ménage', 'amount' => 150, 'price' => 150],
            ['label' => 'Taxe de séjour', 'amount' => 100, 'price' => 100],
        ]);

        $type_doc = get_field('pc_doc_type', $template_id) ?: 'document';
        $doc_number = 'PREVIEW-' . date('Ymd');
        $html_content = '';

        if (in_array($type_doc, ['facture', 'devis', 'avoir'])) {
            $renderer = new PCR_Invoice_Renderer();
            $html_content = $renderer->render($resa, $doc_number, ['type_doc' => $type_doc, 'template_id' => $template_id]);
        } elseif ($type_doc === 'facture_acompte') {
            $renderer = new PCR_Deposit_Renderer();
            $html_content = $renderer->render($resa, $doc_number, ['template_id' => $template_id]);
        } elseif ($type_doc === 'voucher') {
            add_filter('the_title', function ($title, $id) use ($resa) {
                return ($id === $resa->item_id) ? 'Villa de Test (Vue Mer)' : $title;
            }, 10, 2);
            $renderer = new PCR_Voucher_Renderer();
            $html_content = $renderer->render($resa, $doc_number);
        } elseif ($type_doc === 'contrat') {
            $renderer = new PCR_Contract_Renderer();
            $html_content = $renderer->render($resa, $doc_number, ['template_id' => $template_id]);
        } else {
            $renderer = new PCR_Custom_Renderer();
            $html_content = $renderer->render($resa, $doc_number, ['template_id' => $template_id]);
        }

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html_content);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Nettoyage du buffer pour éviter la corruption du PDF streamé
        if (ob_get_length()) {
            ob_clean();
        }

        $dompdf->stream("apercu-document.pdf", ["Attachment" => 0]);
        wp_die(); // Utilisation de wp_die() au lieu de exit; pour une terminaison propre sous WordPress
    }
}
