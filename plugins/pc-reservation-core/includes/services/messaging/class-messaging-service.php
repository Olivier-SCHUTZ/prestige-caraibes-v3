<?php
if (!defined('ABSPATH')) {
    exit; // Sécurité : Empêche l'accès direct au fichier
}

/**
 * Couche Métier (Service) pour la Messagerie.
 * Orchestre l'envoi, la réception, et le formatage des conversations.
 * Pattern Singleton pour garantir une instance unique.
 */
class PCR_Messaging_Service
{
    private static $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * MOTEUR D'ENVOI CHANNEL MANAGER
     */
    public function send_message($template_identifier, $reservation_id, $force_send = false, $message_type = 'automatique', $custom_args = [])
    {
        $reservation_id = (int) $reservation_id;
        if (!$reservation_id) return ['success' => false, 'message' => 'ID réservation manquant.'];

        $subject = '';
        $body    = '';
        $template_code = '';
        $attachment_pdf_id = 0;

        $channel_source = $custom_args['channel_source'] ?? 'email';
        $sender_type = $custom_args['sender_type'] ?? 'host';
        $external_id = $custom_args['external_id'] ?? null;
        $metadata = $custom_args['metadata'] ?? [];

        // 1. DÉTERMINER LE CONTENU
        if ($template_identifier === 'custom' || $template_identifier === 0) {
            if (empty($custom_args['sujet']) || empty($custom_args['corps'])) {
                return ['success' => false, 'message' => 'Sujet ou message manquant pour l\'envoi manuel.'];
            }
            $subject = sanitize_text_field($custom_args['sujet']);

            // Échappement HTML renforcé et restrictif
            $body = wp_kses($custom_args['corps'], [
                'p'      => [],
                'br'     => [],
                'strong' => [],
                'em'     => [],
                'a'      => ['href' => [], 'title' => [], 'target' => []]
            ]);

            $template_code = 'manuel_custom';
        } else {
            $template_post = null;
            if (is_numeric($template_identifier)) {
                $template_post = get_post($template_identifier);
            } else {
                $posts = get_posts([
                    'post_type' => 'pc_message',
                    'meta_key' => 'pc_msg_trigger',
                    'meta_value' => $template_identifier,
                    'numberposts' => 1
                ]);
                if (!empty($posts)) $template_post = $posts[0];
            }

            if (!$template_post) {
                return ['success' => false, 'message' => "Modèle introuvable ($template_identifier)."];
            }

            $pcr_exists = class_exists('PCR_Fields');
            $has_acf    = function_exists('get_field');

            $raw_subject = $pcr_exists ? PCR_Fields::get('pc_msg_subject', $template_post->ID) : ($has_acf ? get_field('pc_msg_subject', $template_post->ID) : '');
            $subject = $raw_subject ?: $template_post->post_title;

            $body    = $template_post->post_content;
            $template_code = $template_post->post_name;

            $attachment_pdf_id = $pcr_exists ? PCR_Fields::get('pc_msg_attachment', $template_post->ID) : ($has_acf ? get_field('pc_msg_attachment', $template_post->ID) : 0);
        }

        // 2. RÉCUPÉRATION DONNÉES RÉSERVATION
        if (!class_exists('PCR_Reservation')) return ['success' => false, 'message' => 'Core manquant.'];
        $resa = PCR_Reservation::get_by_id($reservation_id);
        if (!$resa) return ['success' => false, 'message' => 'Réservation introuvable.'];

        // 3. PRÉPARATION DES VARIABLES
        $repo = PCR_Messaging_Repository::get_instance();
        $item_title = get_the_title($resa->item_id);
        $paid_amount = $repo->get_paid_amount($reservation_id);
        $solde = (float)($resa->montant_total ?? 0) - $paid_amount;

        $ts_arr = strtotime($resa->date_arrivee);
        $ts_dep = strtotime($resa->date_depart);
        $duree  = ($ts_arr && $ts_dep) ? ceil(($ts_dep - $ts_arr) / 86400) : 0;

        $vars = [
            '{id}'              => $resa->id,
            '{prenom_client}'   => ucfirst($resa->prenom),
            '{nom_client}'      => ucfirst($resa->prenom) . ' ' . strtoupper($resa->nom),
            '{email_client}'    => $resa->email,
            '{telephone}'       => $resa->telephone,
            '{logement}'        => $item_title,
            '{date_arrivee}'    => date_i18n('d/m/Y', $ts_arr),
            '{date_depart}'     => date_i18n('d/m/Y', $ts_dep),
            '{duree_sejour}'    => $duree . ' nuit(s)',
            '{numero_resa}'     => '#' . $resa->id,
            '{numero_devis}'    => $resa->numero_devis,
            '{montant_total}'   => number_format((float)$resa->montant_total, 2, ',', ' ') . ' €',
            '{acompte_paye}'    => number_format($paid_amount, 2, ',', ' ') . ' €',
            '{solde_restant}'   => number_format($solde, 2, ',', ' ') . ' €',
            '{lien_paiement}'         => home_url('/paiement/?resa=' . $resa->id),
            '{lien_paiement_acompte}' => $this->get_smart_link($resa->id, 'acompte'),
            '{lien_paiement_solde}'   => $this->get_smart_link($resa->id, 'solde'),
            '{lien_paiement_caution}' => $this->get_smart_link($resa->id, 'caution'),
        ];

        // 4. REMPLACEMENT
        $subject = strtr($subject, $vars);
        $body    = strtr(wpautop($body), $vars);

        if ($channel_source === 'email') {
            $pcr_exists = class_exists('PCR_Fields');
            $has_acf    = function_exists('get_field');

            // Sécurité maximale : DB native -> PCR_Fields -> Fallback ACF
            $signature = PCR_Fields::get('pc_email_signature', 'option', '');

            if (!empty($signature)) {
                $body .= "<br><br><div class='pc-signature'>" . wpautop($signature) . "</div>";
            }
        }

        // SÉCURITÉ
        $prefix = "[#{$resa->id}]";
        if (strpos($subject, $prefix) === false) {
            $subject = $prefix . ' ' . $subject;
        }
        $watermark = "<div style='color:#ffffff; font-size:1px; opacity:0;'>Ref: #{$resa->id}</div>";
        $body .= $watermark;

        // 5. PIÈCES JOINTES
        $attachments = [];

        // --- NOUVEAU MOTEUR DE DOCUMENTS (Vue 3) ---
        $doc_service_exists = class_exists('PCR_Document_Service');

        // A. Documents attachés depuis un Template d'email automatique
        if (!empty($attachment_pdf_id) && $doc_service_exists) {
            $doc_service = PCR_Document_Service::get_instance();
            if (is_string($attachment_pdf_id) && strpos($attachment_pdf_id, 'native_') === 0) {
                $native_mappings = [
                    'native_devis' => 'devis',
                    'native_facture' => 'facture',
                    'native_facture_acompte' => 'facture_acompte',
                    'native_contrat' => 'contrat',
                    'native_voucher' => 'voucher',
                ];
                $doc_type = $native_mappings[$attachment_pdf_id] ?? null;
                if ($doc_type) {
                    $gen = $doc_service->generate_native($doc_type, $resa->id);
                    if ($gen['success'] && !empty($gen['path'])) $attachments[] = $gen['path'];
                }
            } elseif (is_string($attachment_pdf_id) && strpos($attachment_pdf_id, 'template_') === 0) {
                $template_id = (int) str_replace('template_', '', $attachment_pdf_id);
                $gen = $doc_service->generate($template_id, $resa->id, true);
                if ($gen['success'] && !empty($gen['url'])) {
                    $upload_dir = wp_upload_dir();
                    $local_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $gen['url']);
                    if (file_exists($local_path)) $attachments[] = $local_path;
                }
            }
        }

        // B. Documents multiples attachés manuellement depuis l'interface Vue 3
        if (!empty($custom_args['document_ids']) && is_array($custom_args['document_ids']) && $doc_service_exists) {
            $doc_service = PCR_Document_Service::get_instance();
            foreach ($custom_args['document_ids'] as $doc_id) {
                if (strpos($doc_id, 'native_') === 0) {
                    $doc_type = str_replace('native_', '', $doc_id);
                    $gen = $doc_service->generate_native($doc_type, $resa->id);
                    if ($gen['success'] && !empty($gen['path'])) $attachments[] = $gen['path'];
                } elseif (strpos($doc_id, 'template_') === 0) {
                    $tpl_id = (int) str_replace('template_', '', $doc_id);
                    $gen = $doc_service->generate($tpl_id, $resa->id, false); // Utilise le cache PDF si existant
                    if ($gen['success'] && !empty($gen['url'])) {
                        $upload_dir = wp_upload_dir();
                        $local_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $gen['url']);
                        if (file_exists($local_path)) $attachments[] = $local_path;
                    }
                }
            }
        }

        // 6. ENVOI
        $delivery_success = false;
        if ($channel_source === 'email') {
            $dispatcher = PCR_Notification_Dispatcher::get_instance();
            $delivery_success = $dispatcher->send_email($resa->email, $subject, $body, $attachments, $resa->id);
        } else {
            $delivery_success = true;
        }

        // 7. INSERTION BDD
        $conversation_id = $repo->get_conversation_id($reservation_id);
        $metadata = is_array($metadata) ? $metadata : [];

        if (!empty($attachments)) {
            $meta_attachments = [];
            foreach ($attachments as $att_path) {
                $meta_attachments[] = ['name' => basename($att_path), 'type' => 'file'];
            }
            $metadata['attachments'] = $meta_attachments;
        }

        $message_data = [
            'reservation_id'  => $reservation_id,
            'conversation_id' => $conversation_id,
            'canal'           => $channel_source,
            'channel_source'  => $channel_source,
            'direction'       => 'sortant',
            'sender_type'     => $sender_type,
            'type'            => $message_type,
            'template_code'   => $template_code,
            'sujet'           => $subject,
            'corps'           => $body,
            'dest_email'      => $resa->email,
            'statut_envoi'    => $delivery_success ? 'envoye' : 'echec',
            'date_creation'   => current_time('mysql'),
            'date_envoi'      => $delivery_success ? current_time('mysql') : null,
            'delivered_at'    => $delivery_success ? current_time('mysql') : null,
            'date_maj'        => current_time('mysql'),
            'user_id'         => get_current_user_id() ?: 0,
            'external_id'     => $external_id,
            'metadata'        => !empty($metadata) ? json_encode($metadata) : null,
        ];

        $repo->insert_message($message_data);

        return ['success' => $delivery_success, 'message' => $delivery_success ? 'Message envoyé' : 'Erreur technique'];
    }

    /**
     * HISTORIQUE & CONVERSATIONS
     */

    public function get_conversation_cached($reservation_id)
    {
        $cache_key = "pcr_conversation_{$reservation_id}";
        $conversation = wp_cache_get($cache_key);

        if ($conversation === false) {
            $conversation = $this->get_conversation($reservation_id);
            if ($conversation['success']) {
                wp_cache_set($cache_key, $conversation, '', 300); // Cache de 5 minutes
            }
        }

        return $conversation;
    }

    public function get_conversation($reservation_id)
    {
        $reservation_id = (int) $reservation_id;
        if (!$reservation_id) return ['success' => false, 'messages' => []];

        $repo = PCR_Messaging_Repository::get_instance();
        $messages = $repo->get_messages_by_reservation($reservation_id);

        if (!$messages) {
            return ['success' => true, 'messages' => [], 'conversation_id' => $reservation_id];
        }

        $conversation_data = [];
        foreach ($messages as $msg) {
            $design_data = $this->compute_message_design_data($msg);

            $conversation_data[] = [
                'id' => (int) $msg['id'],
                'conversation_id' => (int) $msg['conversation_id'],
                'canal' => $msg['canal'],
                'channel_source' => $msg['channel_source'],
                'direction' => $msg['direction'],
                'sender_type' => $msg['sender_type'],
                'type' => $msg['type'],
                'sujet' => $msg['sujet'],
                'corps' => $msg['corps'],
                'template_code' => $msg['template_code'],
                'statut_envoi' => $msg['statut_envoi'],
                'is_read' => !empty($msg['read_at']),
                'is_delivered' => !empty($msg['delivered_at']),
                'date_creation' => $msg['date_creation'],
                'date_envoi' => $msg['date_envoi'],
                'date_relative' => $this->format_relative_time($msg['date_creation']),
                'external_id' => $msg['external_id'],
                'metadata' => $msg['metadata'] ? json_decode($msg['metadata'], true) : null,
                'user_id' => (int) $msg['user_id'],
                'sender_avatar' => $design_data['avatar'],
                'sender_name' => $design_data['sender_name'],
                'css_classes' => $design_data['css_classes'],
                'channel_icon' => $design_data['channel_icon'],
                'status_badge' => $design_data['status_badge'],
                'formatted_date' => $design_data['formatted_date'],
                'truncated_preview' => $design_data['truncated_preview'],
                'needs_see_more' => $design_data['needs_see_more'],
                'bubble_style' => $design_data['bubble_style'],
            ];
        }

        return [
            'success' => true,
            'conversation_id' => (int) $messages[0]['conversation_id'],
            'total_messages' => count($conversation_data),
            'unread_count' => count(array_filter($conversation_data, function ($msg) {
                return !$msg['is_read'] && $msg['direction'] === 'entrant';
            })),
            'messages' => $conversation_data
        ];
    }

    /**
     * RÉCEPTION WEBHOOK (Messages externes)
     */
    public function receive_external_message($reservation_id, $content, $channel = 'email', $metadata = [])
    {
        $reservation_id = (int) $reservation_id;
        if (!$reservation_id || empty($content)) return ['success' => false, 'message' => 'Paramètres manquants.'];

        $repo = PCR_Messaging_Repository::get_instance();
        $conversation_id = $repo->get_conversation_id($reservation_id);

        $message_data = [
            'reservation_id'  => $reservation_id,
            'conversation_id' => $conversation_id,
            'canal'           => $channel,
            'channel_source'  => $channel,
            'direction'       => 'entrant',
            'sender_type'     => 'guest',
            'type'            => 'externe',
            'template_code'   => null,
            'sujet'           => $metadata['original_subject'] ?? "Message via " . ucfirst($channel),
            'corps'           => wp_kses_post($content),
            'dest_email'      => null,
            'exp_email'       => $metadata['sender_email'] ?? null,
            'statut_envoi'    => 'recu',
            'date_creation'   => current_time('mysql'),
            'date_envoi'      => null,
            'delivered_at'    => current_time('mysql'),
            'read_at'         => null,
            'date_maj'        => current_time('mysql'),
            'user_id'         => 0,
            'external_id'     => $metadata['external_id'] ?? null,
            'metadata'        => !empty($metadata) ? json_encode($metadata) : null,
        ];

        $message_id = $repo->insert_message($message_data);

        return ['success' => ($message_id !== false), 'message_id' => $message_id];
    }

    public function mark_as_read($message_ids)
    {
        if (!is_array($message_ids)) $message_ids = [(int) $message_ids];
        $updated = PCR_Messaging_Repository::get_instance()->mark_messages_read($message_ids);
        return ['success' => true, 'updated_count' => $updated];
    }

    /**
     * NOUVEAU : Récupère le résumé des conversations pour le dashboard.
     */
    public function get_conversations_summary($limit = 50)
    {
        $repo = PCR_Messaging_Repository::get_instance();
        return $repo->get_recent_conversations_with_stats($limit);
    }

    /**
     * NOUVEAU : Recherche full-text dans l'historique.
     */
    public function search_messages($query, $reservation_id = null, $filters = [])
    {
        $repo = PCR_Messaging_Repository::get_instance();
        return $repo->search_full_text($query, $reservation_id, $filters);
    }

    // --- Helpers Privés de Design ---

    private function compute_message_design_data($msg)
    {
        $sender_type = $msg['sender_type'] ?? 'host';
        $channel_source = $msg['channel_source'] ?? 'email';

        $css_classes = ['pc-msg-bubble'];
        if ($sender_type === 'guest') {
            $css_classes[] = 'pc-msg--guest';
            $css_classes[] = 'pc-msg--incoming';
        } else {
            $css_classes[] = 'pc-msg--host';
            $css_classes[] = 'pc-msg--outgoing';
        }
        $css_classes[] = 'pc-msg--' . $channel_source;

        $channel_icons = ['email' => '✉️', 'airbnb' => '🏠', 'booking' => '🏨', 'sms' => '📱', 'whatsapp' => '💬', 'system' => '🤖'];

        $plain_text = strip_tags($msg['corps'] ?? '');
        $needs_see_more = strlen($plain_text) > 100;

        $sender_name = $this->get_sender_name($sender_type, $msg['user_id'] ?? 0);
        if ($channel_source !== 'email') $sender_name .= " via " . ucfirst($channel_source);

        return [
            'avatar' => $this->get_sender_avatar($sender_type, $msg['user_id'] ?? 0),
            'sender_name' => $sender_name,
            'css_classes' => implode(' ', $css_classes),
            'channel_icon' => $channel_icons[$channel_source] ?? '✉️',
            'status_badge' => $this->compute_status_badge($msg),
            'formatted_date' => $this->format_display_date($msg['date_creation'] ?? ''),
            'truncated_preview' => $needs_see_more ? substr($plain_text, 0, 100) . '...' : $plain_text,
            'needs_see_more' => $needs_see_more,
            'bubble_style' => $this->compute_bubble_gradient($channel_source, $sender_type),
        ];
    }

    private function compute_status_badge($msg)
    {
        $statut = $msg['statut_envoi'] ?? 'brouillon';
        $badges = [
            'envoye' => ['text' => 'Envoyé', 'class' => 'pc-badge--success', 'icon' => '✅'],
            'echec' => ['text' => 'Échec', 'class' => 'pc-badge--error', 'icon' => '❌'],
            'brouillon' => ['text' => 'Brouillon', 'class' => 'pc-badge--draft', 'icon' => '📝'],
            'recu' => ['text' => 'Reçu', 'class' => 'pc-badge--info', 'icon' => '📥']
        ];
        $badge = $badges[$statut] ?? $badges['brouillon'];
        if (!empty($msg['delivered_at']) && !empty($msg['read_at'])) {
            $badge['text'] .= ' · Lu';
            $badge['icon'] .= '👁️';
        }
        return $badge;
    }

    private function format_display_date($datetime)
    {
        if (!$datetime) return '';
        $ts = strtotime($datetime);
        $diff = current_time('timestamp') - $ts;
        if ($diff < 3600) return sprintf('Il y a %dm', floor($diff / 60));
        if ($diff < 86400) return sprintf('Il y a %dh', floor($diff / 3600));
        if ($diff < 604800) return date_i18n('D j M', $ts);
        return date_i18n('j M Y', $ts);
    }

    private function format_relative_time($datetime)
    {
        if (!$datetime) return '';
        $diff = current_time('timestamp') - strtotime($datetime);
        if ($diff < 60) return 'À l\'instant';
        return $this->format_display_date($datetime);
    }

    private function compute_bubble_gradient($channel, $sender)
    {
        $gradients = [
            'email' => 'linear-gradient(135deg, #6366f1 0%, #4f46e5 100%)',
            'airbnb' => 'linear-gradient(135deg, #ff385c 0%, #e31c5f 100%)',
            'booking' => 'linear-gradient(135deg, #003580 0%, #0071c2 100%)',
            'sms' => 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
            'whatsapp' => 'linear-gradient(135deg, #25d366 0%, #128c7e 100%)',
            'system' => 'linear-gradient(135deg, #64748b 0%, #475569 100%)',
        ];
        $grad = $gradients[$channel] ?? $gradients['email'];
        return $sender === 'guest' ? $grad . '; opacity: 0.9;' : $grad;
    }

    private function get_sender_avatar($sender, $uid = 0)
    {
        if ($sender === 'guest') return '👤';
        if ($sender === 'system') return '🤖';
        return ($uid > 0 && get_avatar_url($uid)) ? get_avatar_url($uid, ['size' => 32]) : '🏠';
    }

    private function get_sender_name($sender, $uid = 0)
    {
        if ($sender === 'guest') return 'Client';
        if ($sender === 'system') return 'Système';
        return ($uid > 0 && ($u = get_userdata($uid))) ? $u->display_name : 'Équipe';
    }

    private function get_smart_link($reservation_id, $type)
    {
        global $wpdb;
        $url = '';
        if ($type === 'caution') {
            if (class_exists('PCR_Stripe_Manager')) {
                $result = PCR_Stripe_Manager::create_caution_link($reservation_id);
                if ($result['success']) {
                    $wpdb->update($wpdb->prefix . 'pc_reservations', ['caution_statut' => 'demande_envoyee', 'caution_date_demande' => current_time('mysql')], ['id' => $reservation_id]);
                    $url = $result['url'];
                }
            }
        } else {
            $table = $wpdb->prefix . 'pc_payments';
            $row = $wpdb->get_row($wpdb->prepare("SELECT id, montant, statut, url_paiement FROM {$table} WHERE reservation_id = %d AND type_paiement = %s LIMIT 1", $reservation_id, $type));
            if (!$row && $type === 'solde') {
                $row = $wpdb->get_row($wpdb->prepare("SELECT id, montant, statut, url_paiement FROM {$table} WHERE reservation_id = %d AND type_paiement = 'total' LIMIT 1", $reservation_id));
            }
            if ($row && $row->statut !== 'paye') {
                if (!empty($row->url_paiement)) $url = $row->url_paiement;
                elseif (class_exists('PCR_Stripe_Manager')) {
                    $result = PCR_Stripe_Manager::create_payment_link($reservation_id, (float)$row->montant, $type);
                    if ($result['success']) {
                        $wpdb->update($table, ['url_paiement' => $result['url'], 'gateway_reference' => $result['id']], ['id' => $row->id]);
                        $url = $result['url'];
                    }
                }
            }
        }
        if (empty($url)) return '';
        $label = $type === 'acompte' ? "Régler l'acompte" : ($type === 'solde' ? "Régler le solde" : "Déposer la caution");
        $color = $type === 'caution' ? '#059669' : '#6366f1';
        $esc = esc_url($url);
        return '<div style="margin:20px 0;padding:15px;border:1px solid #eee;border-radius:8px;background:#f9f9f9;text-align:center;"><a href="' . $esc . '" style="display:inline-block;background:' . $color . ';color:#fff;padding:12px 25px;border-radius:6px;font-weight:bold;text-decoration:none;">' . $label . '</a><div style="font-size:12px;color:#666;margin-top:10px;">Lien direct : <a href="' . $esc . '" style="color:' . $color . ';">' . $esc . '</a></div></div>';
    }

    public function process_auto_messages() {}
}
