<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gestion des paiements liés aux réservations
 * - Génération automatique des lignes dans pc_payments
 * - Mise à jour des statuts de la réservation selon les règles ACF de la fiche
 */
class PCR_Payment
{
    /**
     * Génère les paiements pour une réservation donnée.
     * $resa_id = ID dans wp_pc_reservations
     */
    public static function generate_for_reservation($resa_id)
    {
        global $wpdb;

        $resa_id = (int) $resa_id;
        if ($resa_id <= 0) {
            return;
        }

        $table_res = $wpdb->prefix . 'pc_reservations';
        $table_pay = $wpdb->prefix . 'pc_payments';

        // 1) Récupération de la réservation
        $resa = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_res} WHERE id = %d", $resa_id),
            ARRAY_A
        );

        if (!$resa) {
            return;
        }

        $item_id      = (int) ($resa['item_id'] ?? 0);
        $total        = (float) ($resa['montant_total'] ?? 0);
        $date_arrivee = !empty($resa['date_arrivee']) ? $resa['date_arrivee'] : null;
        $date_exp     = !empty($resa['date_experience']) ? $resa['date_experience'] : null;

        // Si pas de total, on ne génère pas de paiements
        if ($total <= 0 || !$item_id) {
            return;
        }

        // 2) Lecture des règles ACF sur la fiche
        $mode_pay      = '';
        $deposit_type  = '';
        $deposit_value = '';
        $delay_days    = '';

        if (function_exists('get_field')) {
            // Group ACF : "regles_de_paiement"
            $rules = get_field('regles_de_paiement', $item_id);

            if (is_array($rules)) {
                $mode_pay      = $rules['pc_pay_mode'] ?? '';
                $deposit_type  = $rules['pc_deposit_type'] ?? '';
                $deposit_value = $rules['pc_deposit_value'] ?? '';
                $delay_days    = $rules['pc_balance_delay_days'] ?? '';
            }
        }

        // Valeurs par défaut si rien n'est configuré
        if (!$mode_pay) {
            $mode_pay = 'acompte_plus_solde'; // par défaut
        }

        $deposit_type  = $deposit_type ?: 'pourcentage';
        $deposit_value = $deposit_value !== '' ? (float) $deposit_value : 30.0; // 30% par défaut
        $delay_days    = $delay_days !== '' ? (int) $delay_days : 30;

        $now = current_time('mysql');

        // Helper pour ajouter un paiement
        $add_payment = function ($args) use ($wpdb, $table_pay, $resa_id, $now) {
            $defaults = [
                'type_paiement' => 'acompte',
                'methode'       => 'stripe',
                'montant'       => 0,
                'devise'        => 'EUR',
                'statut'        => 'en_attente',
                'date_creation' => $now,
                'date_echeance' => null,
                'date_paiement' => null,
                'date_annulation' => null,
                'gateway'       => null,
                'gateway_reference' => null,
                'gateway_status'    => null,
                'url_paiement'      => null,
                'raw_response'      => null,
                'note_interne'      => null,
                'user_id'           => null,
                'date_maj'          => $now,
            ];

            $row = array_merge($defaults, $args, [
                'reservation_id' => $resa_id,
            ]);

            $wpdb->insert($table_pay, $row);
        };

        $new_resa_status   = $resa['statut_reservation'] ?? 'en_attente_traitement';
        $new_payment_state = $resa['statut_paiement'] ?? 'non_paye';

        // 3) Logique selon le mode de paiement
        if ($mode_pay === 'sur_devis') {

            // Sur devis : aucune ligne de paiement pour l'instant
            $new_resa_status   = 'en_attente_validation';
            $new_payment_state = 'non_demande';
        } elseif ($mode_pay === 'total_a_la_reservation') {

            // 100% à la réservation
            $add_payment([
                'type_paiement' => 'total',
                'montant'       => $total,
                'statut'        => 'en_attente',
            ]);

            $new_resa_status   = 'en_attente_paiement';
            $new_payment_state = 'en_attente_paiement';
        } elseif ($mode_pay === 'acompte_plus_solde') {

            // Date de base pour le calcul (logement ou expérience)
            $base_date = $date_arrivee ?: $date_exp;

            // ========= Calcul standard de l’acompte / solde =========
            if ($deposit_type === 'montant_fixe') {
                $acompte = min($total, max(0, $deposit_value));
            } else {
                // pourcentage
                $acompte = max(0, $total * ($deposit_value / 100));
            }

            $solde = max(0, $total - $acompte);

            // ========= RÈGLE : si on est dans la période du solde (ex. 30 jours) → paiement TOTAL =========
            $force_total = false;

            if ($base_date && $delay_days > 0) {
                try {
                    $dt_arrivee = new DateTime($base_date);
                    $now_dt     = new DateTime(current_time('Y-m-d'));

                    $diff_days = (int) $now_dt->diff($dt_arrivee)->days;

                    if ($diff_days <= $delay_days) {
                        $force_total = true;
                    }
                } catch (Exception $e) {
                    // Si problème de date, on ignore et on reste en logique standard
                }
            }

            if ($force_total) {
                // Paiement TOTAL immédiat
                $add_payment([
                    'type_paiement' => 'total',
                    'montant'       => $total,
                    'statut'        => 'en_attente',
                ]);

                $new_resa_status   = 'en_attente_paiement';
                $new_payment_state = 'en_attente_paiement';
            } else {

                // ========= MODE NORMAL : acompte + solde =========

                // 1) acompte
                if ($acompte > 0) {
                    $add_payment([
                        'type_paiement' => 'acompte',
                        'montant'       => $acompte,
                        'statut'        => 'en_attente',
                    ]);
                }

                // 2) solde avec date d'échéance
                if ($solde > 0) {

                    $date_echeance = null;

                    if ($base_date && $delay_days > 0) {
                        $dt = new DateTime($base_date);
                        $dt->modify('-' . $delay_days . ' days');
                        $date_echeance = $dt->format('Y-m-d') . ' 00:00:00';
                    }

                    $add_payment([
                        'type_paiement' => 'solde',
                        'montant'       => $solde,
                        'statut'        => 'en_attente',
                        'date_echeance' => $date_echeance,
                    ]);
                }

                $new_resa_status   = 'en_attente_paiement';
                $new_payment_state = 'en_attente_paiement';
            }
        } elseif ($mode_pay === 'sur_place') {

            // Paiement sur place
            $add_payment([
                'type_paiement' => 'sur_place',
                'montant'       => $total,
                'statut'        => 'en_attente',
            ]);

            $new_resa_status   = 'en_attente';
            $new_payment_state = 'sur_place';
        }

        // 4) Mise à jour des statuts dans la réservation
        $wpdb->update(
            $table_res,
            [
                'statut_reservation' => $new_resa_status,
                'statut_paiement'    => $new_payment_state,
                'date_maj'           => $now,
            ],
            ['id' => $resa_id]
        );
    }

    /**
     * Retourne tous les paiements liés à une réservation.
     *
     * @param int $reservation_id
     * @return array Liste d'objets stdClass
     */
    public static function get_for_reservation($reservation_id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'pc_payments';

        $sql = "
            SELECT *
            FROM {$table}
            WHERE reservation_id = %d
            ORDER BY id ASC
        ";

        return $wpdb->get_results($wpdb->prepare($sql, (int) $reservation_id));
    }
}
