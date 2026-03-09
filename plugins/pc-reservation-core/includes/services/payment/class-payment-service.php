<?php
if (!defined('ABSPATH')) {
    exit; // Sécurité : Empêche l'accès direct au fichier
}

/**
 * Couche Métier (Service) pour les Paiements.
 * Orchestre la génération des lignes de paiements selon les règles ACF (acomptes, soldes).
 * Pattern Singleton pour garantir une instance unique.
 */
class PCR_Payment_Service
{
    /**
     * @var PCR_Payment_Service Instance unique
     */
    private static $instance = null;

    /**
     * Constructeur privé (Singleton)
     */
    private function __construct() {}

    /**
     * Empêche le clonage
     */
    private function __clone() {}

    /**
     * Récupère l'instance unique du Service.
     *
     * @return PCR_Payment_Service
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Génère les paiements pour une réservation donnée.
     * $resa_id = ID dans wp_pc_reservations
     */
    public function generate_for_reservation($resa_id)
    {
        $resa_id = (int) $resa_id;
        if ($resa_id <= 0) {
            return;
        }

        $repository = PCR_Payment_Repository::get_instance();

        // 1) Récupération de la réservation depuis le Repository
        $resa = $repository->get_reservation_data($resa_id);

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

        // Helper pour ajouter un paiement via le Repository
        $add_payment = function ($args) use ($repository, $resa_id, $now) {
            $defaults = [
                'type_paiement'   => 'acompte',
                'methode'         => 'stripe',
                'montant'         => 0,
                'devise'          => 'EUR',
                'statut'          => 'en_attente',
                'date_creation'   => $now,
                'date_echeance'   => null,
                'date_paiement'   => null,
                'date_annulation' => null,
                'gateway'         => null,
                'gateway_reference' => null,
                'gateway_status'  => null,
                'url_paiement'    => null,
                'raw_response'    => null,
                'note_interne'    => null,
                'user_id'         => null,
                'date_maj'        => $now,
            ];

            $row = array_merge($defaults, $args, [
                'reservation_id' => $resa_id,
            ]);

            $repository->insert($row);
        };

        $new_payment_state = 'non_paye'; // Par défaut

        // 3) Logique selon le mode de paiement
        if ($mode_pay === 'sur_devis') {
            $new_payment_state = 'non_demande';
        } elseif ($mode_pay === 'total_a_la_reservation') {
            // 100% à la réservation
            $add_payment([
                'type_paiement' => 'total',
                'montant'       => $total,
                'statut'        => 'en_attente',
            ]);
            $new_payment_state = 'en_attente_paiement';
        } elseif ($mode_pay === 'acompte_plus_solde') {
            // Date de base pour le calcul (logement ou expérience)
            $base_date = $date_arrivee ?: $date_exp;

            // Calcul standard de l’acompte / solde
            if ($deposit_type === 'montant_fixe') {
                $acompte = min($total, max(0, $deposit_value));
            } else {
                $acompte = max(0, $total * ($deposit_value / 100));
            }

            $solde = max(0, $total - $acompte);
            $force_total = false;

            if ($base_date && $delay_days > 0) {
                try {
                    $dt_arrivee = new DateTime($base_date);
                    $now_dt     = new DateTime(current_time('Y-m-d'));
                    $diff_days  = (int) $now_dt->diff($dt_arrivee)->days;

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
                $new_payment_state = 'en_attente_paiement';
            } else {
                // MODE NORMAL : acompte + solde
                if ($acompte > 0) {
                    $add_payment([
                        'type_paiement' => 'acompte',
                        'montant'       => $acompte,
                        'statut'        => 'en_attente',
                    ]);
                }

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
                $new_payment_state = 'en_attente_paiement';
            }
        } elseif ($mode_pay === 'sur_place') {
            // Paiement sur place
            $add_payment([
                'type_paiement' => 'sur_place',
                'montant'       => $total,
                'statut'        => 'en_attente',
            ]);
            $new_payment_state = 'sur_place';
        }

        // 4) Mise à jour des statuts
        $is_draft = (isset($resa['type_flux']) && $resa['type_flux'] === 'devis')
            || (isset($resa['statut_reservation']) && $resa['statut_reservation'] === 'brouillon');

        if ($is_draft) {
            $new_payment_state = 'sur_devis';
        }

        $current_resa_status = $resa['statut_reservation'] ?? 'en_attente_traitement';

        // Mise à jour finale en base via le Repository
        $repository->update_reservation_statuses($resa_id, $new_payment_state, $current_resa_status);
    }

    /**
     * Retourne tous les paiements liés à une réservation.
     */
    public function get_for_reservation($reservation_id)
    {
        return PCR_Payment_Repository::get_instance()->get_for_reservation($reservation_id);
    }

    /**
     * Supprime toutes les lignes de paiement pour une réservation donnée.
     */
    public function delete_for_reservation($reservation_id)
    {
        return PCR_Payment_Repository::get_instance()->delete_for_reservation($reservation_id);
    }

    /**
     * Supprime puis régénère les paiements liés à une réservation.
     */
    public function regenerate_for_reservation($reservation_id, array $args = [])
    {
        $reservation_id = (int) $reservation_id;
        if ($reservation_id <= 0) {
            return;
        }

        $preserve_statuses = !empty($args['preserve_statuses']);
        $original_resa_status = isset($args['statut_reservation']) ? $args['statut_reservation'] : '';
        $original_pay_status  = isset($args['statut_paiement']) ? $args['statut_paiement'] : '';

        $this->delete_for_reservation($reservation_id);
        $this->generate_for_reservation($reservation_id);

        if ($preserve_statuses && ($original_resa_status || $original_pay_status)) {
            $repo = PCR_Payment_Repository::get_instance();
            $current = $repo->get_reservation_data($reservation_id);

            $s_pay = $original_pay_status ? $original_pay_status : $current['statut_paiement'];
            $s_res = $original_resa_status ? $original_resa_status : $current['statut_reservation'];

            $repo->update_reservation_statuses($reservation_id, $s_pay, $s_res);
        }
    }
}
