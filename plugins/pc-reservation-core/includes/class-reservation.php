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
}
