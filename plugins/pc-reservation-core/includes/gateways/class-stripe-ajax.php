<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gère les requêtes AJAX pour Stripe (Génération de liens).
 */
class PCR_Stripe_Ajax extends PCR_Base_Ajax_Controller
{
    public static function init()
    {
        // Action pour l'admin connecté
        add_action('wp_ajax_pc_stripe_get_link', [__CLASS__, 'handle_get_link']);
        // --- NOUVEAU : Paiement Libre ---
        add_action('wp_ajax_pc_stripe_create_custom_payment', [__CLASS__, 'handle_create_custom_payment']);

        // --- NOUVEAU : Caution ---
        add_action('wp_ajax_pc_stripe_get_caution_link', [__CLASS__, 'handle_get_caution_link']);
        add_action('wp_ajax_pc_stripe_release_caution', [__CLASS__, 'handle_release_caution']);
        add_action('wp_ajax_pc_stripe_capture_caution', [__CLASS__, 'handle_capture_caution']);
        add_action('wp_ajax_pc_stripe_rotate_caution', [__CLASS__, 'handle_rotate_caution']);
        // Pas de nopriv ici : c'est l'admin qui génère le lien manuellement.
        // Pour le front, c'est le contrôleur de formulaire qui s'en chargera directement.
    }

    public static function handle_get_link()
    {
        // 1. Sécurité centralisée (Logs automatiques + Nonce + Droits)
        parent::verify_access('pc_resa_manual_create', 'nonce', 'manage_options');

        // 2. Récupération des données
        $payment_id = isset($_POST['payment_id']) ? (int) $_POST['payment_id'] : 0;

        if ($payment_id <= 0) {
            wp_send_json_error(['message' => 'ID de paiement manquant.']);
        }

        // 3. Lecture du paiement en base
        global $wpdb;
        $table_pay = $wpdb->prefix . 'pc_payments';
        $payment = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_pay} WHERE id = %d", $payment_id));

        if (!$payment) {
            wp_send_json_error(['message' => 'Ligne de paiement introuvable.']);
        }

        if ($payment->statut === 'paye') {
            wp_send_json_error(['message' => 'Ce montant est déjà payé.']);
        }

        $amount = (float) $payment->montant;
        if ($amount <= 0) {
            wp_send_json_error(['message' => 'Montant nul, pas de paiement nécessaire.']);
        }

        // 4. Appel au Manager Stripe
        if (!class_exists('PCR_Stripe_Manager')) {
            wp_send_json_error(['message' => 'Classe Stripe Manager absente.']);
        }

        // On passe l'ID de la RÉSERVATION, pas du paiement, car le Manager a besoin des infos client
        // Mais on pourra passer l'ID du paiement en métadonnée si on améliore le Manager plus tard.
        // Pour l'instant, le manager attend ($reservation_id, $amount, $type).

        $result = PCR_Stripe_Manager::create_payment_link(
            $payment->reservation_id,
            $amount,
            $payment->type_paiement,
            $payment->id // <-- AJOUT DE L'ID DU PAIEMENT
        );

        if (!$result['success']) {
            wp_send_json_error(['message' => $result['message']]);
        }

        // --- NOUVEAU : Sauvegarde du lien en base de données ---
        $wpdb->update(
            $table_pay,
            [
                'url_paiement'      => $result['url'],
                'gateway_reference' => $result['id'], // ID de la session Stripe
                'date_maj'          => current_time('mysql')
            ],
            ['id' => $payment_id]
        );

        // 5. Succès : on renvoie l'URL
        wp_send_json_success([
            'url' => $result['url'],
            'id'  => $result['id'] // ID session Stripe
        ]);
    }

    public static function handle_get_caution_link()
    {
        parent::verify_access('pc_resa_manual_create', 'nonce', 'manage_options');

        $resa_id = isset($_POST['reservation_id']) ? (int) $_POST['reservation_id'] : 0;
        if ($resa_id <= 0) wp_send_json_error(['message' => 'ID manquant.']);

        if (!class_exists('PCR_Stripe_Manager')) {
            wp_send_json_error(['message' => 'Stripe Manager absent.']);
        }

        // Appel au Manager
        $result = PCR_Stripe_Manager::create_caution_link($resa_id);

        if (!$result['success']) {
            wp_send_json_error(['message' => $result['message']]);
        }

        // Mise à jour date demande
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'pc_reservations',
            ['caution_statut' => 'demande_envoyee', 'caution_date_demande' => current_time('mysql')],
            ['id' => $resa_id]
        );

        wp_send_json_success(['url' => $result['url']]);
    }

    public static function handle_release_caution()
    {
        parent::verify_access('pc_resa_manual_create', 'nonce', 'manage_options');

        $resa_id = (int) $_POST['reservation_id'];
        $ref     = sanitize_text_field($_POST['ref']);

        if (!$resa_id || !$ref) wp_send_json_error(['message' => 'Données manquantes.']);

        if (!class_exists('PCR_Stripe_Manager')) wp_send_json_error(['message' => 'Manager absent.']);

        $res = PCR_Stripe_Manager::release_caution($ref);

        if (!$res['success']) {
            wp_send_json_error(['message' => $res['message']]);
        }

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'pc_reservations',
            ['caution_statut' => 'liberee', 'caution_date_liberation' => current_time('mysql')],
            ['id' => $resa_id]
        );

        wp_send_json_success(['message' => 'Caution libérée avec succès.']);
    }

    public static function handle_capture_caution()
    {
        parent::verify_access('pc_resa_manual_create', 'nonce', 'manage_options');

        $resa_id = (int) $_POST['reservation_id'];
        $ref     = sanitize_text_field($_POST['ref']);
        $amount  = (float) $_POST['amount'];
        $note    = isset($_POST['note']) ? sanitize_textarea_field($_POST['note']) : '';

        if (!$resa_id || !$ref || $amount <= 0) wp_send_json_error(['message' => 'Données invalides.']);

        $res = PCR_Stripe_Manager::capture_caution($ref, $amount, $note);

        if (!$res['success']) {
            wp_send_json_error(['message' => $res['message']]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'pc_reservations';

        // 1. Mise à jour statut
        $data_update = ['caution_statut' => 'encaissee'];

        // 2. Ajout dans les notes internes si motif fourni
        if (!empty($note)) {
            $old_notes = $wpdb->get_var($wpdb->prepare("SELECT notes_internes FROM {$table} WHERE id = %d", $resa_id));
            $new_line  = date('d/m/Y') . ' - Encaissement Caution (' . $amount . '€) : ' . $note . "\n";
            $data_update['notes_internes'] = $new_line . $old_notes;
        }

        $wpdb->update($table, $data_update, ['id' => $resa_id]);

        wp_send_json_success(['message' => 'Montant prélevé avec succès.']);
    }

    public static function handle_rotate_caution()
    {
        parent::verify_access('pc_resa_manual_create', 'nonce', 'manage_options');

        $resa_id = (int) $_POST['reservation_id'];
        $old_ref = sanitize_text_field($_POST['old_ref']); // L'ancienne réf Stripe

        if (!$resa_id || !$old_ref) wp_send_json_error(['message' => 'Données manquantes.']);

        // Récupérer le montant actuel de la caution défini dans la résa
        // (On repart sur le montant théorique, au cas où on voudrait changer le montant, ici on garde le même)
        global $wpdb;
        $table = $wpdb->prefix . 'pc_reservations';
        $resa = $wpdb->get_row($wpdb->prepare("SELECT caution_montant, notes_internes FROM {$table} WHERE id = %d", $resa_id));

        if (!$resa) wp_send_json_error(['message' => 'Réservation introuvable.']);
        $amount = (float) $resa->caution_montant;

        if (!class_exists('PCR_Stripe_Manager')) wp_send_json_error(['message' => 'Manager absent.']);

        // Appel de la logique métier
        $res = PCR_Stripe_Manager::rotate_caution($old_ref, $amount, $resa_id);

        if (!$res['success']) {
            wp_send_json_error(['message' => $res['message']]);
        }

        // Succès : Mise à jour BDD
        $new_ref = sanitize_text_field($res['new_ref']);

        // On loggue l'action dans les notes internes
        $date_now = date('d/m/Y H:i');
        $new_note = "$date_now - Rotation Caution OK.\nAncienne : $old_ref (Libérée)\nNouvelle : $new_ref (Validée)\n----------------\n" . $resa->notes_internes;

        $wpdb->update(
            $table,
            [
                'caution_reference'       => $new_ref,
                'caution_date_validation' => current_time('mysql'), // On rafraîchit la date
                'notes_internes'          => $new_note,
                'date_maj'                => current_time('mysql')
            ],
            ['id' => $resa_id]
        );

        wp_send_json_success([
            'message' => 'Caution renouvelée avec succès !',
            'new_ref' => $new_ref
        ]);
    }

    /**
     * NOUVEAU : Crée un appel de fond manuel (Stripe, Virement, ou Sur Place)
     */
    public static function handle_create_custom_payment()
    {
        parent::verify_access('pc_resa_manual_create', 'nonce', 'manage_options');

        $resa_id = isset($_POST['reservation_id']) ? (int) $_POST['reservation_id'] : 0;
        $amount  = isset($_POST['amount']) ? (float) $_POST['amount'] : 0;
        $action_type = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : 'stripe';

        if ($resa_id <= 0 || $amount <= 0) {
            wp_send_json_error(['message' => 'Données invalides. Le montant doit être supérieur à 0.']);
        }

        global $wpdb;
        $table_resa = $wpdb->prefix . 'pc_reservations';
        $table_pay  = $wpdb->prefix . 'pc_payments';

        // 1. Contrôle du reste à payer
        $resa = $wpdb->get_row($wpdb->prepare("SELECT montant_total FROM {$table_resa} WHERE id = %d", $resa_id));
        if (!$resa) wp_send_json_error(['message' => 'Réservation introuvable.']);

        $total_paid = $wpdb->get_var($wpdb->prepare("SELECT SUM(montant) FROM {$table_pay} WHERE reservation_id = %d AND statut = 'paye'", $resa_id));
        $reste_a_payer = (float) $resa->montant_total - (float) $total_paid;

        if ($amount > $reste_a_payer) {
            wp_send_json_error(['message' => sprintf('Le montant (%.2f€) dépasse le reste à payer (%.2f€).', $amount, $reste_a_payer)]);
        }

        // 2. Détermination du statut et de la méthode selon l'action demandée
        $statut = 'en_attente';
        $methode = 'stripe';

        if ($action_type === 'virement') {
            $statut = 'paye';
            $methode = 'virement';
        } elseif ($action_type === 'sur_place') {
            $statut = 'sur_place';
            $methode = 'especes';
        }

        // 3. Création de la ligne
        $wpdb->insert(
            $table_pay,
            [
                'reservation_id' => $resa_id,
                'montant'        => $amount,
                'type_paiement'  => 'manuel',
                'statut'         => $statut,
                'methode'        => $methode,
                'date_creation'  => current_time('mysql'),
                'date_maj'       => current_time('mysql')
            ]
        );
        $new_payment_id = $wpdb->insert_id;
        if (!$new_payment_id) wp_send_json_error(['message' => 'Erreur lors de la création du paiement.']);

        $url_paiement = ''; // On prépare la variable

        // 4. Si c'est Stripe, on génère le lien
        if ($action_type === 'stripe') {
            if (!class_exists('PCR_Stripe_Manager')) wp_send_json_error(['message' => 'Stripe Manager absent.']);
            $result = PCR_Stripe_Manager::create_payment_link($resa_id, $amount, 'manuel', $new_payment_id);

            if (!$result['success']) {
                $wpdb->delete($table_pay, ['id' => $new_payment_id]);
                wp_send_json_error(['message' => 'Stripe : ' . $result['message']]);
            }

            $url_paiement = $result['url'];
            $wpdb->update($table_pay, ['url_paiement' => $url_paiement, 'gateway_reference' => $result['id']], ['id' => $new_payment_id]);
        }

        // 5. NOUVEAU : On renvoie TOUT l'objet pour que Vue l'affiche comme les autres
        wp_send_json_success([
            'message' => 'Action réussie.',
            'payment' => [
                'id'            => $new_payment_id,
                'reservation_id' => $resa_id,
                'montant'       => $amount,
                'type_paiement' => 'manuel',
                'statut'        => $statut,
                'methode'       => $methode,
                'url_paiement'  => $url_paiement
            ]
        ]);
    }
}
