<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Contrôleur AJAX pour la gestion des Documents (Templates et Fichiers joints).
 */
class PCR_Document_Ajax_Controller extends PCR_Base_Ajax_Controller
{
    /**
     * Valide de manière stricte le chemin d'un document pour éviter le Path Traversal.
     */
    private static function validate_secure_path($filename, $reservation_id)
    {
        if (strpos($filename, '..') !== false) {
            return false;
        }

        $upload_dir = wp_upload_dir();
        $expected_dir = $upload_dir['basedir'] . '/pc-reservation/documents/' . $reservation_id;
        $file_path = $expected_dir . '/' . basename($filename);

        // realpath() résout les liens symboliques et retourne false si le fichier n'existe pas
        $real_path = realpath($file_path);

        // Standardisation des séparateurs de dossiers pour compatibilité Windows/Linux
        $real_path = wp_normalize_path($real_path);
        $expected_dir = wp_normalize_path($expected_dir);

        // On vérifie que le fichier existe ET qu'il est bien dans le dossier attendu
        if (!$real_path || strpos($real_path, $expected_dir) !== 0) {
            return false;
        }

        return $real_path;
    }

    /**
     * Récupère la liste des documents disponibles (Natifs + Personnalisés).
     */
    public static function ajax_get_documents_templates()
    {
        // Sécurité centralisée
        parent::verify_access('pc_resa_manual_create', 'nonce');

        // Récupération données réservation
        $reservation_id = isset($_POST['reservation_id']) ? (int) $_POST['reservation_id'] : 0;
        if ($reservation_id <= 0) {
            wp_send_json_error(['message' => 'ID Réservation manquant.']);
        }

        if (!class_exists('PCR_Reservation')) {
            wp_send_json_error(['message' => 'Core Réservation manquant.']);
        }

        $resa = PCR_Reservation::get_by_id($reservation_id);
        if (!$resa) {
            wp_send_json_error(['message' => 'Réservation introuvable.']);
        }

        $reservation_type = $resa->type ?? 'location';

        // GROUPE A : Documents Natifs
        $documents_natifs = [];

        $documents_natifs[] = [
            'id' => 'native_devis',
            'type' => 'devis',
            'label' => '📄 Devis commercial',
            'description' => 'Document natif - Devis pour la réservation',
            'group' => 'native'
        ];

        $documents_natifs[] = [
            'id' => 'native_facture',
            'type' => 'facture',
            'label' => '🧾 Facture (Solde/Totale)',
            'description' => 'Document natif - Facture principale',
            'group' => 'native'
        ];

        $documents_natifs[] = [
            'id' => 'native_facture_acompte',
            'type' => 'facture_acompte',
            'label' => '💰 Facture d\'Acompte',
            'description' => 'Document natif - Facture d\'acompte',
            'group' => 'native'
        ];

        if (in_array($reservation_type, ['location', 'mixte'])) {
            $documents_natifs[] = [
                'id' => 'native_contrat',
                'type' => 'contrat',
                'label' => '📋 Contrat de Location',
                'description' => 'Document natif - Contrat pour logements',
                'group' => 'native'
            ];
        }

        if (in_array($reservation_type, ['experience', 'mixte'])) {
            $documents_natifs[] = [
                'id' => 'native_voucher',
                'type' => 'voucher',
                'label' => '🎫 Voucher / Bon d\'échange',
                'description' => 'Document natif - Voucher pour expériences',
                'group' => 'native'
            ];
        }

        // GROUPE B : Documents Personnalisés
        $documents_personnalises = [];

        $templates_args = [
            'post_type' => 'pc_pdf_template',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ];

        $templates = get_posts($templates_args);

        foreach ($templates as $template) {
            // Remplacement pour le champ pc_model_context
            $model_context = (class_exists('PCR_Fields')
                ? PCR_Fields::get('pc_model_context', $template->ID)
                : (function_exists('get_field') ? get_field('pc_model_context', $template->ID) : '')) ?: 'global';

            $show_template = false;
            if ($model_context === 'global') {
                $show_template = true;
            } elseif ($model_context === 'location' && $reservation_type === 'location') {
                $show_template = true;
            } elseif ($model_context === 'experience' && $reservation_type === 'experience') {
                $show_template = true;
            }

            if ($show_template) {
                // Remplacement pour le champ pc_doc_type
                $doc_type = (class_exists('PCR_Fields')
                    ? PCR_Fields::get('pc_doc_type', $template->ID)
                    : (function_exists('get_field') ? get_field('pc_doc_type', $template->ID) : '')) ?: 'document';

                $icon = '📄';
                switch ($doc_type) {
                    case 'devis':
                        $icon = '📄';
                        break;
                    case 'facture':
                        $icon = '🧾';
                        break;
                    case 'facture_acompte':
                        $icon = '💰';
                        break;
                    case 'avoir':
                        $icon = '↩️';
                        break;
                    case 'contrat':
                        $icon = '📋';
                        break;
                    case 'voucher':
                        $icon = '🎫';
                        break;
                    default:
                        $icon = '📄';
                        break;
                }

                $documents_personnalises[] = [
                    'id' => 'template_' . $template->ID,
                    'template_id' => $template->ID,
                    'type' => $doc_type,
                    'label' => $icon . ' ' . $template->post_title,
                    'description' => 'Modèle personnalisé - ' . ($template->post_excerpt ?: 'Document personnalisé'),
                    'group' => 'custom',
                    'context' => $model_context
                ];
            }
        }

        wp_send_json_success([
            'reservation_id' => $reservation_id,
            'reservation_type' => $reservation_type,
            'documents' => [
                'native' => [
                    'label' => '🏠 Documents Natifs',
                    'description' => 'Documents intégrés au système, toujours disponibles',
                    'items' => $documents_natifs
                ],
                'custom' => [
                    'label' => '🎨 Modèles Personnalisés',
                    'description' => 'Documents créés dans PC Réservation > Modèles PDF',
                    'items' => $documents_personnalises
                ]
            ],
            'total_count' => count($documents_natifs) + count($documents_personnalises)
        ]);
    }

    /**
     * Récupère les documents PDF générés pour une réservation.
     */
    public static function ajax_get_reservation_files()
    {
        // Sécurité centralisée
        parent::verify_access('pc_resa_manual_create', 'nonce');

        $reservation_id = isset($_REQUEST['reservation_id']) ? (int) $_REQUEST['reservation_id'] : 0;
        if ($reservation_id <= 0) {
            wp_send_json_error(['message' => 'ID réservation manquant.']);
        }

        if (!class_exists('PCR_Documents')) {
            wp_send_json_error(['message' => 'Module Documents indisponible.']);
        }

        if (!class_exists('PCR_Reservation')) {
            wp_send_json_error(['message' => 'Module Réservation indisponible.']);
        }

        $resa = PCR_Reservation::get_by_id($reservation_id);
        if (!$resa) {
            wp_send_json_error(['message' => 'Réservation introuvable.']);
        }

        $upload_dir = wp_upload_dir();
        $resa_folder = $upload_dir['basedir'] . '/pc-reservation/documents/' . $reservation_id;

        $files = [];

        if (is_dir($resa_folder)) {
            $file_list = scandir($resa_folder);

            foreach ($file_list as $filename) {
                if ($filename === '.' || $filename === '..') {
                    continue;
                }

                $file_path = $resa_folder . '/' . $filename;
                if (!is_file($file_path)) {
                    continue;
                }

                if (pathinfo($filename, PATHINFO_EXTENSION) !== 'pdf') {
                    continue;
                }

                $doc_type = 'document';
                $display_name = $filename;

                if (strpos($filename, 'devis') !== false) {
                    $doc_type = 'devis';
                    $display_name = 'Devis commercial';
                } elseif (strpos($filename, 'facture') !== false) {
                    if (strpos($filename, 'acompte') !== false) {
                        $doc_type = 'facture_acompte';
                        $display_name = 'Facture d\'acompte';
                    } else {
                        $doc_type = 'facture';
                        $display_name = 'Facture';
                    }
                } elseif (strpos($filename, 'contrat') !== false) {
                    $doc_type = 'contrat';
                    $display_name = 'Contrat de location';
                } elseif (strpos($filename, 'voucher') !== false) {
                    $doc_type = 'voucher';
                    $display_name = 'Voucher / Bon d\'échange';
                } elseif (strpos($filename, 'avoir') !== false) {
                    $doc_type = 'avoir';
                    $display_name = 'Avoir';
                }

                $icon = '📄';
                switch ($doc_type) {
                    case 'devis':
                        $icon = '📄';
                        break;
                    case 'facture':
                        $icon = '🧾';
                        break;
                    case 'facture_acompte':
                        $icon = '💰';
                        break;
                    case 'avoir':
                        $icon = '↩️';
                        break;
                    case 'contrat':
                        $icon = '📋';
                        break;
                    case 'voucher':
                        $icon = '🎫';
                        break;
                    default:
                        $icon = '📄';
                        break;
                }

                $file_size = filesize($file_path);
                $file_size_formatted = '';
                if ($file_size < 1024) {
                    $file_size_formatted = $file_size . ' B';
                } elseif ($file_size < 1024 * 1024) {
                    $file_size_formatted = round($file_size / 1024, 1) . ' KB';
                } else {
                    $file_size_formatted = round($file_size / (1024 * 1024), 1) . ' MB';
                }

                // Construction de l'URL sécurisée au lieu de l'URL directe du dossier uploads
                $nonce = wp_create_nonce('pc_document_download');
                $secure_url = admin_url('admin-ajax.php') . '?action=pc_secure_download&filename=' . urlencode($filename) . '&reservation_id=' . $reservation_id . '&nonce=' . $nonce;

                $files[] = [
                    'id' => $filename, // Utilisation du nom comme ID temporaire
                    'name' => $display_name,
                    'filename' => $filename,
                    'path' => $file_path,
                    'url' => $secure_url, // L'ancienne propriété 'url' devient sécurisée
                    'secure_download_url' => $secure_url,
                    'type' => $doc_type,
                    'icon' => $icon,
                    'size' => $file_size,
                    'size_formatted' => $file_size_formatted,
                    'created' => date('Y-m-d H:i:s', filemtime($file_path))
                ];
            }
        }

        usort($files, function ($a, $b) {
            $priority = [
                'devis' => 1,
                'facture_acompte' => 2,
                'facture' => 3,
                'contrat' => 4,
                'voucher' => 5,
                'avoir' => 6,
                'document' => 7
            ];
            $a_priority = $priority[$a['type']] ?? 99;
            $b_priority = $priority[$b['type']] ?? 99;

            if ($a_priority === $b_priority) {
                return strcmp($a['name'], $b['name']);
            }
            return $a_priority <=> $b_priority;
        });

        wp_send_json_success([
            'reservation_id' => $reservation_id,
            'files' => $files,
            'total_count' => count($files),
            'folder_path' => $resa_folder,
            'message' => count($files) > 0
                ? sprintf('%d document(s) disponible(s) pour cette réservation.', count($files))
                : 'Aucun document généré pour cette réservation.'
        ]);
    }

    /**
     * (NOUVEAU) Télécharge un document de manière sécurisée via PHP Stream.
     * Accessible en GET (sans vérifier PCR_Base_Ajax_Controller) car c'est un lien direct cliqué,
     * MAIS on vérifie un nonce spécifique.
     */
    public static function ajax_secure_download()
    {
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'pc_document_download')) {
            wp_die('Lien expiré ou accès non autorisé.', 'Accès refusé', ['response' => 403]);
        }

        if (!current_user_can('edit_posts')) {
            wp_die('Privilèges insuffisants.', 'Accès refusé', ['response' => 403]);
        }

        $reservation_id = isset($_GET['reservation_id']) ? (int) $_GET['reservation_id'] : 0;
        $filename = isset($_GET['filename']) ? sanitize_file_name($_GET['filename']) : '';

        if (!$reservation_id || empty($filename)) {
            wp_die('Paramètres manquants.', 'Erreur', ['response' => 400]);
        }

        $real_path = self::validate_secure_path($filename, $reservation_id);

        if (!$real_path || !file_exists($real_path)) {
            wp_die('Fichier introuvable ou accès interdit sur le serveur.', 'Erreur 404', ['response' => 404]);
        }

        // Audit Log
        error_log(sprintf('[PC-DOCUMENTS] Download - User:%d - Resa:%d - File:%s', get_current_user_id(), $reservation_id, $filename));

        // Nettoyage agressif du buffer pour éviter toute corruption (caractères invisibles)
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Gestion du mode de disposition
        $disposition = (isset($_GET['download']) && $_GET['download'] === '1') ? 'attachment' : 'inline';

        // En-têtes complets pour forcer le lecteur PDF natif du navigateur
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . $disposition . '; filename="' . basename($real_path) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . filesize($real_path));
        header('Accept-Ranges: bytes'); // Indispensable pour certains navigateurs (Chrome/Safari)
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        flush();
        readfile($real_path);
        exit;
    }

    /**
     * (NOUVEAU) Supprime physiquement un document généré.
     */
    public static function ajax_delete_document()
    {
        // Sécurité centralisée
        parent::verify_access('pc_resa_manual_create', 'nonce');

        $reservation_id = isset($_POST['reservation_id']) ? (int) $_POST['reservation_id'] : 0;
        $filename = isset($_POST['document_id']) ? sanitize_file_name($_POST['document_id']) : '';

        if (!$reservation_id || empty($filename)) {
            wp_send_json_error(['message' => 'Paramètres invalides.']);
        }

        $real_path = self::validate_secure_path($filename, $reservation_id);

        if (!$real_path) {
            wp_send_json_error(['message' => 'Fichier introuvable.']);
        }

        if (unlink($real_path)) {
            // Audit Log
            error_log(sprintf('[PC-DOCUMENTS] Delete - User:%d - Resa:%d - File:%s', get_current_user_id(), $reservation_id, $filename));
            wp_send_json_success(['message' => 'Document supprimé avec succès.']);
        } else {
            wp_send_json_error(['message' => 'Impossible de supprimer le fichier.']);
        }
    }
}

// FORCE WordPress à écouter cette action spécifique même si le Routeur AJAX global l'ignore
add_action('wp_ajax_pc_secure_download', ['PCR_Document_Ajax_Controller', 'ajax_secure_download']);
