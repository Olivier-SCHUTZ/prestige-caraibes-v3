<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ancienne classe noyau des réservations (Facade / Proxy).
 * @deprecated Cette classe redirige désormais vers la nouvelle architecture (PCR_Reservation_Service).
 * Elle est conservée pour garantir la non-régression.
 */
class PCR_Reservation
{
    public static function init()
    {
        // Conservé pour la rétrocompatibilité (hooks, etc.)
    }

    public static function create(array $data)
    {
        return PCR_Reservation_Service::get_instance()->create($data);
    }

    public static function update($reservation_id, array $data)
    {
        return PCR_Reservation_Service::get_instance()->update($reservation_id, $data);
    }

    public static function get_list($args = [])
    {
        return PCR_Reservation_Service::get_instance()->get_list($args);
    }

    public static function get_by_id($reservation_id)
    {
        return PCR_Reservation_Service::get_instance()->get_by_id($reservation_id);
    }

    public static function get_count($type = '', $item_id = 0)
    {
        return PCR_Reservation_Service::get_instance()->get_count($type, $item_id);
    }

    // ... tes autres fonctions ...

    /**
     * CERVEAU CENTRAL DES PAIEMENTS
     * Calcule et met à jour le statut global de paiement d'une réservation.
     */
    public static function update_payment_status($resa_id)
    {
        global $wpdb;
        $table_res = $wpdb->prefix . 'pc_reservations';
        $table_pay = $wpdb->prefix . 'pc_payments';

        // 1. Récupérer le montant total de la réservation
        $resa = $wpdb->get_row($wpdb->prepare("SELECT montant_total FROM {$table_res} WHERE id = %d", $resa_id));
        if (!$resa) return false;

        $total = (float) $resa->montant_total;

        // 2. Calculer combien a été réellement payé (statut = 'paye')
        $paid_amount = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(montant) FROM {$table_pay} WHERE reservation_id = %d AND statut = 'paye'",
            $resa_id
        ));
        $paid = (float) $paid_amount;

        // 3. Vérifier s'il y a une ligne de paiement en attente prévue en liquide / sur place
        // NOUVEAU : On regarde "type_paiement" OU "methode = especes" pour être sûr de ne rien rater !
        $has_sur_place_attente = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(id) FROM {$table_pay} WHERE reservation_id = %d AND statut != 'paye' AND (type_paiement = 'sur_place' OR methode = 'especes')",
            $resa_id
        ));

        // 4. L'ARBRE DE DÉCISION (La logique métier)
        $new_status = 'non_paye';

        if ($paid >= ($total - 1) && $total > 0) {
            // Tout est payé (tolérance de 1€ pour les arrondis)
            $new_status = 'paye';
        } elseif ($paid > 0) {
            // Une partie est payée (ex: Acompte)... Mais reste-t-il du liquide à prévoir ?
            if ($has_sur_place_attente > 0) {
                // ALERTE MAXIMUM : Acompte payé + Solde en attente à l'arrivée
                $new_status = 'partiellement_paye_sur_place';
            } else {
                $new_status = 'partiellement_paye';
            }
        } elseif ($has_sur_place_attente > 0) {
            // Rien n'est payé, mais c'est prévu en liquide sur place
            $new_status = 'sur_place';
        } else {
            // Rien n'est payé, on attend un virement ou Stripe
            $new_status = 'en_attente_paiement';
        }

        // 5. Sauvegarder le nouveau statut dans la base de données
        $wpdb->update(
            $table_res,
            ['statut_paiement' => $new_status, 'date_maj' => current_time('mysql')],
            ['id' => $resa_id]
        );

        return $new_status;
    }
}
