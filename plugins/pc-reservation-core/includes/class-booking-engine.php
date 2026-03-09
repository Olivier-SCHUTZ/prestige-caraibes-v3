<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ancienne classe du moteur de réservation (Facade / Proxy).
 * @deprecated Cette classe redirige désormais vers les nouveaux services de la couche Application.
 * Conservée pour garantir le "zéro régression".
 */
class PCR_Booking_Engine
{
    /**
     * Redirige vers PCR_Booking_Orchestrator
     */
    public static function create(array $payload)
    {
        return PCR_Booking_Orchestrator::get_instance()->create($payload);
    }

    /**
     * Redirige vers PCR_Booking_Orchestrator
     */
    public static function update($reservation_id, array $payload)
    {
        return PCR_Booking_Orchestrator::get_instance()->update($reservation_id, $payload);
    }

    /**
     * Redirige vers PCR_Booking_Orchestrator
     */
    public static function cancel($reservation_id)
    {
        return PCR_Booking_Orchestrator::get_instance()->cancel($reservation_id);
    }

    /**
     * Redirige vers PCR_Booking_Payload_Normalizer (au cas où d'autres plugins l'utilisent directement)
     */
    public static function normalize_payload(array $payload)
    {
        return PCR_Booking_Payload_Normalizer::get_instance()->normalize($payload);
    }
}
