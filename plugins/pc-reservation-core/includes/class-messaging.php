<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ancienne classe Messagerie (Facade / Proxy).
 * @deprecated Cette classe redirige désormais vers la nouvelle architecture (Services).
 * Conservée pour garantir le "zéro régression".
 */
class PCR_Messaging
{
    public static function init()
    {
        // Les hooks liés à WP sont désormais gérés par le Template Manager
        PCR_Template_Manager::get_instance()->init_hooks();
    }

    public static function send_message($template_identifier, $reservation_id, $force_send = false, $message_type = 'automatique', $custom_args = [])
    {
        return PCR_Messaging_Service::get_instance()->send_message($template_identifier, $reservation_id, $force_send, $message_type, $custom_args);
    }

    public static function get_conversation($reservation_id)
    {
        return PCR_Messaging_Service::get_instance()->get_conversation($reservation_id);
    }

    public static function receive_external_message($reservation_id, $content, $channel = 'email', $metadata = [])
    {
        return PCR_Messaging_Service::get_instance()->receive_external_message($reservation_id, $content, $channel, $metadata);
    }

    public static function mark_as_read($message_ids)
    {
        return PCR_Messaging_Service::get_instance()->mark_as_read($message_ids);
    }

    public static function find_active_reservation_by_email($email)
    {
        return PCR_Messaging_Repository::get_instance()->find_active_reservation_by_email($email);
    }

    public static function get_external_messages_stats($days = 30)
    {
        return PCR_Messaging_Repository::get_instance()->get_external_messages_stats($days);
    }

    public static function get_quick_replies()
    {
        return PCR_Template_Manager::get_instance()->get_quick_replies();
    }

    public static function get_quick_reply_with_vars($template_id, $reservation_id = null)
    {
        return PCR_Template_Manager::get_instance()->get_quick_reply_with_vars($template_id, $reservation_id);
    }

    public static function process_auto_messages()
    {
        return PCR_Messaging_Service::get_instance()->process_auto_messages();
    }
}
