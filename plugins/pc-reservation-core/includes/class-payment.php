<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ancienne classe de gestion des paiements liés aux réservations (Facade / Proxy).
 * @deprecated Cette classe redirige désormais vers la nouvelle architecture (PCR_Payment_Service).
 * Elle est conservée pour garantir la non-régression.
 */
class PCR_Payment
{
    /**
     * Génère les paiements pour une réservation donnée.
     */
    public static function generate_for_reservation($resa_id)
    {
        PCR_Payment_Service::get_instance()->generate_for_reservation($resa_id);
    }

    /**
     * Retourne tous les paiements liés à une réservation.
     */
    public static function get_for_reservation($reservation_id)
    {
        return PCR_Payment_Service::get_instance()->get_for_reservation($reservation_id);
    }

    /**
     * Supprime toutes les lignes de paiement pour une réservation donnée.
     */
    public static function delete_for_reservation($reservation_id)
    {
        PCR_Payment_Service::get_instance()->delete_for_reservation($reservation_id);
    }

    /**
     * Supprime puis régénère les paiements liés à une réservation.
     */
    public static function regenerate_for_reservation($reservation_id, array $args = [])
    {
        PCR_Payment_Service::get_instance()->regenerate_for_reservation($reservation_id, $args);
    }
}
