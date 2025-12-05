<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Transforme un statut technique en label lisible.
 *
 * @param string $status
 * @return string
 */
function pc_resa_format_status_label($status)
{
    $map = [
        // --- Statuts de Réservation ---
        'brouillon'             => 'Brouillon',
        'en_attente_traitement' => 'En attente de traitement', // Demande entrante
        'devis_envoye'          => 'Devis envoyé',
        'reservee'              => 'Réservée', // Le seul qui bloque le calendrier
        'annule'                => 'Annulée',
        'annulee'               => 'Annulée',

        // --- Statuts de Paiement ---
        'sur_devis'             => 'Sur devis',
        'en_attente_paiement'   => 'En attente de paiement',
        'non_paye'              => 'Non payé',
        'partiellement_paye'    => 'Partiellement payé',
        'paye'                  => 'Payé',
        'sur_place'             => 'À régler sur place',

        // Lignes de paiement spécifiques
        'acompte_paye'          => 'Acompte réglé',
        'solde_paye'            => 'Solde réglé',
    ];

    $status = (string) $status;
    if (isset($map[$status])) {
        return $map[$status];
    }

    // fallback générique : en_attente_test -> En attente test
    $status = str_replace('_', ' ', $status);
    return ucfirst($status);
}

/**
 * Prépare les données pour pré-remplir le formulaire de devis.
 *
 * @param object $resa
 * @return array
 */
function pc_resa_build_prefill_payload($resa)
{
    if (empty($resa) || !is_object($resa)) {
        return [];
    }

    $payload = [
        'id'                    => isset($resa->id) ? (int) $resa->id : 0,
        'type'                  => isset($resa->type) ? $resa->type : '',
        'type_flux'             => isset($resa->type_flux) ? $resa->type_flux : '',
        'mode_reservation'      => isset($resa->mode_reservation) ? $resa->mode_reservation : '',
        'item_id'               => isset($resa->item_id) ? (int) $resa->item_id : 0,
        'experience_tarif_type' => isset($resa->experience_tarif_type) ? $resa->experience_tarif_type : '',
        'date_experience'       => isset($resa->date_experience) ? $resa->date_experience : '',
        'date_arrivee'          => isset($resa->date_arrivee) ? $resa->date_arrivee : '',
        'date_depart'           => isset($resa->date_depart) ? $resa->date_depart : '',
        'adultes'               => isset($resa->adultes) ? (int) $resa->adultes : 0,
        'enfants'               => isset($resa->enfants) ? (int) $resa->enfants : 0,
        'bebes'                 => isset($resa->bebes) ? (int) $resa->bebes : 0,
        'montant_total'         => isset($resa->montant_total) ? (float) $resa->montant_total : 0,
        'lines_json'            => isset($resa->detail_tarif) ? $resa->detail_tarif : '',
        'prenom'                => isset($resa->prenom) ? $resa->prenom : '',
        'nom'                   => isset($resa->nom) ? $resa->nom : '',
        'email'                 => isset($resa->email) ? $resa->email : '',
        'telephone'             => isset($resa->telephone) ? $resa->telephone : '',
        'commentaire_client'    => isset($resa->commentaire_client) ? $resa->commentaire_client : '',
        'notes_internes'        => isset($resa->notes_internes) ? $resa->notes_internes : '',
        'numero_devis'          => isset($resa->numero_devis) ? $resa->numero_devis : '',
        'remise_label'          => '',
        'remise_montant'        => '',
        'plus_label'            => '',
        'plus_montant'          => '',
        'participants'          => [
            'adultes' => isset($resa->adultes) ? (int) $resa->adultes : 0,
            'enfants' => isset($resa->enfants) ? (int) $resa->enfants : 0,
            'bebes'   => isset($resa->bebes) ? (int) $resa->bebes : 0,
        ],
        'lines_qty_map'         => [],
    ];

    if (!empty($resa->detail_tarif)) {
        $detail_lines = json_decode($resa->detail_tarif, true);
        if (is_array($detail_lines)) {
            $qty_map = [];
            $normalize_label = function ($value) {
                $value = (string) $value;
                $value = strtolower(trim($value));
                $value = preg_replace('/\s+/', ' ', $value);
                return $value;
            };

            foreach ($detail_lines as $line) {
                $label = isset($line['label']) ? (string) $line['label'] : '';
                $amount = 0;
                if (isset($line['amount']) && $line['amount'] != 0) {
                    $amount = (float) $line['amount'];
                } elseif (!empty($line['price'])) {
                    $amount = pc_resa_dashboard_parse_amount($line['price']);
                }

                if (!empty($line['is_adjustment'])) {
                    if ($amount < 0 && $payload['remise_montant'] === '') {
                        $payload['remise_label'] = $label;
                        $payload['remise_montant'] = abs($amount);
                    } elseif ($amount > 0 && $payload['plus_montant'] === '') {
                        $payload['plus_label'] = $label;
                        $payload['plus_montant'] = abs($amount);
                    }
                    continue;
                }

                $normalized_label = $normalize_label($label);
                if ($normalized_label === '') {
                    continue;
                }

                $qty_from_label = 0;
                if (preg_match('/^(\d+)\s*[x×]?\s*(.+)$/u', $label, $m)) {
                    $qty_from_label = (int) $m[1];
                    $normalized_label = $normalize_label($m[2]);
                }
                if ($qty_from_label <= 0 && $amount !== 0) {
                    $qty_from_label = 1;
                }
                if ($qty_from_label > 0) {
                    $qty_map[$normalized_label] = $qty_from_label;
                }
            }

            if (!empty($qty_map)) {
                $payload['lines_qty_map'] = $qty_map;
            }
        }
    }

    return $payload;
}

function pc_resa_get_logement_pricing_config($post_id)
{
    if (! function_exists('get_field')) {
        return null;
    }

    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return null;
    }

    $base_price   = (float) get_field('base_price_from', $post_id);
    $unit         = (string) get_field('unite_de_prix',   $post_id);
    $min_nights   = (int)    get_field('min_nights',      $post_id);
    $max_nights   = (int)    get_field('max_nights',      $post_id);
    $cap          = (int)    get_field('capacite',        $post_id);
    if ($cap <= 0) {
        $cap = 1;
    }
    $extra_fee    = (float)  get_field('extra_guest_fee',  $post_id);
    $extra_from   = (int)    get_field('extra_guest_from', $post_id);
    $cleaning     = (float)  get_field('frais_menage',      $post_id);
    $other_fee    = (float)  get_field('autres_frais',      $post_id);
    $other_label  = (string) get_field('autres_frais_type', $post_id);
    $taxe_choices = (array)  get_field('taxe_sejour',       $post_id);
    $unit_is_week = (stripos($unit, 'semaine') !== false);
    $seasons_raw  = (array) get_field('pc_season_blocks', $post_id);
    $manual_quote = (bool) get_field('pc_manual_quote', $post_id);

    $seasons = [];
    foreach ($seasons_raw as $s) {
        $price = isset($s['season_price']) ? (float) $s['season_price'] : 0.0;
        if ($unit_is_week && $price > 0) {
            $price = $price / 7.0;
        }
        if (! is_array($s)) {
            $s = [];
        }
        $seasons[] = [
            'name'        => trim((string) ($s['season_name'] ?? 'Saison')),
            'min_nights'  => (int) ($s['season_min_nights'] ?? 0),
            'extra_fee'   => ($s['season_extra_guest_fee'] ?? '') !== '' ? (float) $s['season_extra_guest_fee'] : $extra_fee,
            'extra_from'  => ($s['season_extra_guest_from'] ?? '') !== '' ? (int) $s['season_extra_guest_from'] : $extra_from,
            'price'       => ($price > 0 ? $price : ($unit_is_week ? ($base_price / 7.0) : $base_price)),
            'periods'     => array_values(array_map(function ($p) {
                return [
                    'from' => (string) ($p['date_from'] ?? ''),
                    'to'   => (string) ($p['date_to'] ?? ''),
                ];
            }, (array) ($s['season_periods'] ?? []))),
        ];
    }

    $ics_disable = [];
    $ical_url    = (string) get_field('ical_url', $post_id);
    if ($ical_url && function_exists('pc_parse_ics_ranges')) {
        $cache_key = 'pc_ics_body_' . md5($ical_url);
        $ics_body  = get_transient($cache_key);
        if ($ics_body === false) {
            $resp = wp_remote_get($ical_url, ['timeout' => 10]);
            if (! is_wp_error($resp) && 200 === wp_remote_retrieve_response_code($resp)) {
                $ics_body = (string) wp_remote_retrieve_body($resp);
                if ($ics_body !== '') {
                    set_transient($cache_key, $ics_body, 2 * HOUR_IN_SECONDS);
                }
            } else {
                $ics_body = '';
            }
        }
        if ($ics_body !== '') {
            $ics_disable = pc_parse_ics_ranges($ics_body);
        }
    }

    // 1. Cache iCal Externe
    $booked_dates = get_post_meta($post_id, '_booked_dates_cache', true);
    if (is_array($booked_dates) && !empty($booked_dates)) {
        $ics_disable = array_merge($ics_disable, pc_resa_dashboard_dates_to_ranges($booked_dates));
    }

    // 2. [AJOUT] Réservations Internes + Blocages Manuels
    // Pour que le calendrier du dashboard grise aussi ce qu'on a déjà créé en interne.
    global $wpdb;
    $today_sql = current_time('Y-m-d');

    // A. Réservations internes (statut 'reservee')
    $table_res = $wpdb->prefix . 'pc_reservations';
    $internal_res = $wpdb->get_results($wpdb->prepare(
        "SELECT date_arrivee, date_depart FROM {$table_res} 
         WHERE item_id = %d AND statut_reservation = 'reservee' AND date_depart >= %s",
        $post_id,
        $today_sql
    ));
    foreach ($internal_res as $r) {
        // Logique chassé-croisé : on libère le jour du départ
        $end_date = date('Y-m-d', strtotime($r->date_depart . ' -1 day'));
        if ($end_date >= $r->date_arrivee) {
            $ics_disable[] = ['from' => $r->date_arrivee, 'to' => $end_date];
        }
    }

    // B. Blocages manuels
    $table_unv = $wpdb->prefix . 'pc_unavailabilities';
    $manual_blocks = $wpdb->get_results($wpdb->prepare(
        "SELECT date_debut, date_fin FROM {$table_unv} 
         WHERE item_id = %d AND date_fin >= %s",
        $post_id,
        $today_sql
    ));
    foreach ($manual_blocks as $b) {
        $ics_disable[] = ['from' => $b->date_debut, 'to' => $b->date_fin];
    }

    // Fusion et nettoyage final
    if (!empty($ics_disable)) {
        $ics_disable = pc_resa_dashboard_merge_ranges($ics_disable);
    }

    return [
        'title'       => get_the_title($post_id),
        'basePrice'   => $unit_is_week ? ($base_price / 7.0) : $base_price,
        'cap'         => $cap,
        'minNights'   => max(1, $min_nights ?: 1),
        'maxNights'   => max(1, $max_nights ?: 365),
        'extraFee'    => $extra_fee,
        'extraFrom'   => max(0, $extra_from),
        'cleaning'    => $cleaning,
        'otherFee'    => $other_fee,
        'otherLabel'  => $other_label ?: 'Autres frais',
        'taxe_sejour' => $taxe_choices,
        'seasons'     => $seasons,
        'icsDisable'  => $ics_disable,
        'manualQuote' => $manual_quote,
    ];
}

function pc_resa_dashboard_dates_to_ranges($dates)
{
    if (!is_array($dates) || empty($dates)) {
        return [];
    }
    $normalized = [];
    foreach ($dates as $date) {
        $value = trim((string) $date);
        if ($value === '') {
            continue;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $timestamp = strtotime($value);
            if ($timestamp === false) {
                continue;
            }
            $value = date('Y-m-d', $timestamp);
        }
        $normalized[] = $value;
    }
    if (empty($normalized)) {
        return [];
    }
    sort($normalized);
    $ranges = [];
    $currentStart = $normalized[0];
    $currentEnd = $normalized[0];
    for ($i = 1, $max = count($normalized); $i < $max; $i++) {
        $date = $normalized[$i];
        $prevNext = date('Y-m-d', strtotime($currentEnd . ' +1 day'));
        if ($date === $prevNext) {
            $currentEnd = $date;
            continue;
        }
        $ranges[] = ['from' => $currentStart, 'to' => $currentEnd];
        $currentStart = $date;
        $currentEnd = $date;
    }
    $ranges[] = ['from' => $currentStart, 'to' => $currentEnd];
    return $ranges;
}

function pc_resa_dashboard_merge_ranges($ranges)
{
    $valid = [];
    foreach ($ranges as $range) {
        if (empty($range['from']) || empty($range['to'])) {
            continue;
        }
        $from = substr((string) $range['from'], 0, 10);
        $to   = substr((string) $range['to'], 0, 10);
        if (!$from || !$to) {
            continue;
        }
        if ($to < $from) {
            continue;
        }
        $valid[] = [
            'from' => $from,
            'to'   => $to,
        ];
    }
    if (empty($valid)) {
        return [];
    }
    usort($valid, function ($a, $b) {
        return strcmp($a['from'], $b['from']);
    });
    $merged = [];
    $current = array_shift($valid);
    foreach ($valid as $range) {
        $currentEndPlusOne = date('Y-m-d', strtotime($current['to'] . ' +1 day'));
        if ($range['from'] <= $currentEndPlusOne) {
            if ($range['to'] > $current['to']) {
                $current['to'] = $range['to'];
            }
        } else {
            $merged[] = $current;
            $current = $range;
        }
    }
    $merged[] = $current;
    return $merged;
}

/**
 * Corrige les textes de devis qui contiennent des séquences unicode type u00e9.
 *
 * @param string $text
 * @return string
 */
function pc_resa_dashboard_normalize_quote_text($text)
{
    if (!is_string($text) || $text === '') {
        return $text;
    }

    if (strpos($text, 'u') === false) {
        return $text;
    }

    return preg_replace_callback('/u([0-9a-fA-F]{4})/', function ($matches) {
        $decoded = json_decode('"\\u' . $matches[1] . '"');
        return $decoded !== null ? $decoded : $matches[0];
    }, $text);
}

function pc_resa_dashboard_parse_amount($value)
{
    if (is_numeric($value)) {
        return (float) $value;
    }

    $value = html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8');
    $value = str_replace(["\xC2\xA0", ' '], '', $value);
    $value = preg_replace('/[^0-9,\.\-]/', '', $value);

    if ($value === '' || $value === '-' || $value === '--') {
        return 0.0;
    }

    $comma_pos = strrpos($value, ',');
    $dot_pos   = strrpos($value, '.');

    if ($comma_pos !== false && $dot_pos !== false) {
        if ($comma_pos > $dot_pos) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }
    } elseif ($comma_pos !== false && $dot_pos === false) {
        $value = str_replace(',', '.', $value);
    }

    return (float) $value;
}

/**
 * Shortcode [pc_resa_dashboard]
 */
function pc_resa_dashboard_shortcode($atts)
{
    if (! class_exists('PCR_Reservation')) {
        return '<p>Module réservation non disponible.</p>';
    }

    $plugin_file = dirname(__DIR__) . '/pc-reservation-core.php';
    $plugin_url  = plugin_dir_url($plugin_file);
    $plugin_path = plugin_dir_path($plugin_file);

    wp_enqueue_style(
        'pc-flatpickr',
        'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
        [],
        '4.6.13'
    );
    wp_enqueue_script(
        'pc-flatpickr',
        'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js',
        [],
        '4.6.13',
        true
    );
    wp_enqueue_script(
        'pc-flatpickr-fr',
        'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js',
        ['pc-flatpickr'],
        '4.6.13',
        true
    );

    $pc_devis_js_path = WP_CONTENT_DIR . '/mu-plugins/assets/pc-devis.js';
    if (file_exists($pc_devis_js_path)) {
        wp_enqueue_script(
            'pc-logement-devis',
            content_url('mu-plugins/assets/pc-devis.js'),
            ['pc-flatpickr-fr'],
            filemtime($pc_devis_js_path),
            true
        );
    }

    $dashboard_css = $plugin_path . 'assets/css/dashboard-style.css';
    wp_enqueue_style(
        'pc-resa-dashboard',
        $plugin_url . 'assets/css/dashboard-style.css',
        [],
        file_exists($dashboard_css) ? filemtime($dashboard_css) : null
    );

    $dashboard_js = $plugin_path . 'assets/js/dashboard-core.js';
    wp_enqueue_script(
        'pc-resa-dashboard',
        $plugin_url . 'assets/js/dashboard-core.js',
        ['pc-flatpickr-fr'],
        file_exists($dashboard_js) ? filemtime($dashboard_js) : null,
        true
    );

    // Réinitialisation : si bouton reset, on ignore tous les filtres
    $is_reset = isset($_GET['pc_resa_reset']);

    $type_filter = '';
    $item_id     = 0;

    if (! $is_reset) {
        // Filtre type : ?pc_resa_type=experience ou ?pc_resa_type=location
        if (isset($_GET['pc_resa_type']) && in_array($_GET['pc_resa_type'], ['experience', 'location'], true)) {
            $type_filter = $_GET['pc_resa_type'];
        }

        // Filtre par logement / expérience via sélecteurs
        if (! empty($_GET['pc_resa_item_location'])) {
            $item_id = (int) $_GET['pc_resa_item_location'];
        } elseif (! empty($_GET['pc_resa_item_experience'])) {
            $item_id = (int) $_GET['pc_resa_item_experience'];
        }
    }

    // Pagination — 25 lignes par page
    $per_page = 25;
    $page     = isset($_GET['pc_resa_page']) ? max(1, intval($_GET['pc_resa_page'])) : 1;
    $offset   = ($page - 1) * $per_page;

    $list_args = [
        'limit'  => $per_page,
        'offset' => $offset,
    ];

    if ($type_filter) {
        $list_args['type'] = $type_filter;
    }
    if ($item_id > 0) {
        $list_args['item_id'] = $item_id;
    }

    $reservations = PCR_Reservation::get_list($list_args);

    // --- CHARGEMENT DES MESSAGES & TEMPLATES ---
    $messages_map = [];
    $templates_list = [];

    // 1. Récupérer les templates disponibles (pour le formulaire d'envoi)
    $templates_posts = get_posts([
        'post_type' => 'pc_template',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC'
    ]);
    foreach ($templates_posts as $tpl) {
        $templates_list[] = ['id' => $tpl->ID, 'title' => $tpl->post_title];
    }

    if (!empty($reservations)) {
        global $wpdb;

        $ids = array_filter(array_map(function ($resa) {
            return isset($resa->id) ? (int) $resa->id : 0;
        }, $reservations));

        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));

            // A. Détails tarifs (existant)
            $table_res    = $wpdb->prefix . 'pc_reservations';
            $sql_res      = "SELECT id, detail_tarif FROM {$table_res} WHERE id IN ({$placeholders})";
            $prepared_res = $wpdb->prepare($sql_res, ...array_values($ids));
            $detail_rows  = $wpdb->get_results($prepared_res, OBJECT_K);

            // B. Messages (NOUVEAU)
            $table_msg    = $wpdb->prefix . 'pc_messages';
            $sql_msg      = "SELECT * FROM {$table_msg} WHERE reservation_id IN ({$placeholders}) ORDER BY date_creation DESC";
            $prepared_msg = $wpdb->prepare($sql_msg, ...array_values($ids));
            $raw_msgs     = $wpdb->get_results($prepared_msg);

            foreach ($raw_msgs as $msg) {
                $messages_map[$msg->reservation_id][] = $msg;
            }

            foreach ($reservations as $resa) {
                $resa_id = isset($resa->id) ? (int) $resa->id : 0;
                if ($resa_id && isset($detail_rows[$resa_id])) {
                    $resa->detail_tarif = isset($detail_rows[$resa_id]->detail_tarif) ? $detail_rows[$resa_id]->detail_tarif : '';
                } elseif (!property_exists($resa, 'detail_tarif')) {
                    $resa->detail_tarif = '';
                }
            }
        } else {
            foreach ($reservations as $resa) {
                if (!property_exists($resa, 'detail_tarif')) {
                    $resa->detail_tarif = '';
                }
            }
        }
    }

    // On récupère aussi le **nombre total** de réservations pour les filtres actifs
    $total_rows = PCR_Reservation::get_count($type_filter, $item_id);
    $total_pages = ceil($total_rows / $per_page);

    // --- MODIFICATION : Récupération de TOUS les logements (pour export iCal même sans résa) ---
    $logement_options = [];
    $all_logements_posts = get_posts([
        'post_type'      => ['logement', 'villa', 'appartement'],
        'post_status'    => ['publish', 'pending'],
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'fields'         => 'ids',
    ]);

    if (!empty($all_logements_posts)) {
        foreach ($all_logements_posts as $pid) {
            $logement_options[$pid] = get_the_title($pid);
        }
    }

    // Pour les expériences, on garde la logique existante basée sur les réservations (ou on pourrait faire pareil)
    $exp_options = [];
    $all_reservations_raw = PCR_Reservation::get_list(['limit' => 9999, 'type' => 'experience']);
    if (!empty($all_reservations_raw)) {
        foreach ($all_reservations_raw as $r) {
            $pid = (int) $r->item_id;
            if ($pid) $exp_options[$pid] = get_the_title($pid);
        }
    }
    if (! empty($exp_options)) {
        asort($exp_options);
    }

    $manual_experience_options = [];
    $manual_experience_tarifs  = [];
    $manual_experiences = get_posts([
        'post_type'      => 'experience',
        'post_status'    => ['publish', 'pending'],
        'posts_per_page' => 200,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'fields'         => 'ids',
    ]);

    if (! empty($manual_experiences)) {
        foreach ($manual_experiences as $exp_id) {
            $manual_experience_options[$exp_id] = get_the_title($exp_id);

            if (function_exists('get_field')) {

                $tarifs_rows = get_field('exp_types_de_tarifs', $exp_id);
                if (!empty($tarifs_rows) && is_array($tarifs_rows)) {
                    $tarif_options = [];

                    foreach ($tarifs_rows as $idx => $row) {
                        $code = isset($row['exp_type']) ? (string) $row['exp_type'] : '';
                        $key  = $code !== '' ? $code . '_' . $idx : 'tarif_' . $idx;

                        if (function_exists('pc_exp_type_label')) {
                            $label = pc_exp_type_label($row);
                        } else {
                            $label = isset($row['exp_type_custom']) && $code === 'custom'
                                ? trim((string) $row['exp_type_custom'])
                                : ($code ?: 'Tarif');
                        }

                        if ($label === '') {
                            $label = sprintf(__('Tarif %d', 'pc'), $idx + 1);
                        }

                        $lines_raw = isset($row['exp_tarifs_lignes']) ? (array) $row['exp_tarifs_lignes'] : [];
                        $lines = [];
                        $has_counters = false;

                        foreach ($lines_raw as $line_index => $ln) {
                            $type_ligne = isset($ln['type_ligne']) ? (string) $ln['type_ligne'] : 'personnalise';
                            $entry = [
                                'type'        => $type_ligne,
                                'price'       => isset($ln['tarif_valeur']) ? (float) $ln['tarif_valeur'] : 0,
                                'label'       => '',
                                'observation' => trim((string) ($ln['tarif_observation'] ?? '')),
                                'enable_qty'  => !empty($ln['tarif_enable_qty']),
                                'default_qty' => isset($ln['tarif_qty_default']) ? (int) $ln['tarif_qty_default'] : 0,
                                'uid'         => sprintf('%s_line_%d', $key, $line_index),
                            ];

                            if ($type_ligne === 'adulte') {
                                $entry['label'] = __('Adulte', 'pc');
                                $has_counters = true;
                            } elseif ($type_ligne === 'enfant') {
                                $precision = trim((string) ($ln['precision_age_enfant'] ?? ''));
                                $entry['label'] = $precision ? sprintf(__('Enfant (%s)', 'pc'), $precision) : __('Enfant', 'pc');
                                $has_counters = true;
                            } elseif ($type_ligne === 'bebe') {
                                $precision = trim((string) ($ln['precision_age_bebe'] ?? ''));
                                $entry['label'] = $precision ? sprintf(__('Bébé (%s)', 'pc'), $precision) : __('Bébé', 'pc');
                                $has_counters = true;
                            } else {
                                $entry['label'] = trim((string) ($ln['tarif_nom_perso'] ?? '')) ?: __('Forfait', 'pc');
                            }

                            $lines[] = $entry;
                        }

                        $fixed_fees = [];
                        if (!empty($row['exp-frais-fixes'])) {
                            foreach ((array) $row['exp-frais-fixes'] as $fee_row) {
                                $fee_label = trim((string) ($fee_row['exp_description_frais_fixe'] ?? ''));
                                $fee_price = isset($fee_row['exp_tarif_frais_fixe']) ? (float) $fee_row['exp_tarif_frais_fixe'] : 0;
                                if ($fee_label !== '' && $fee_price != 0) {
                                    $fixed_fees[] = [
                                        'label' => $fee_label,
                                        'price' => $fee_price,
                                    ];
                                }
                            }
                        }

                        $options = [];
                        if (!empty($row['exp_options_tarifaires']) && is_array($row['exp_options_tarifaires'])) {
                            foreach ($row['exp_options_tarifaires'] as $opt_idx => $opt_row) {
                                $opt_label = trim((string) ($opt_row['exp_description_option'] ?? ''));
                                $opt_price = isset($opt_row['exp_tarif_option']) ? (float) $opt_row['exp_tarif_option'] : 0;
                                if ($opt_label === '') {
                                    continue;
                                }
                                $options[] = [
                                    'label'       => $opt_label,
                                    'price'       => $opt_price,
                                    'enable_qty'  => !empty($opt_row['option_enable_qty']),
                                    'default_qty' => isset($opt_row['option_qty_default']) ? (int) $opt_row['option_qty_default'] : 1,
                                    'uid'         => sprintf('%s_opt_%d', $key, $opt_idx),
                                ];
                            }
                        }

                        $tarif_options[] = [
                            'key'        => $key,
                            'label'      => $label,
                            'code'       => $code,
                            'lines'      => $lines,
                            'fixed_fees' => $fixed_fees,
                            'options'    => $options,
                            'has_counters' => $has_counters,
                        ];
                    }

                    if (!empty($tarif_options)) {
                        $manual_experience_tarifs[$exp_id] = $tarif_options;
                    }
                }
            }
        }
    }

    $manual_nonce = wp_create_nonce('pc_resa_manual_create');

    $manual_logement_options = [];
    $manual_logements = get_posts([
        'post_type'      => ['logement', 'villa', 'appartement'],
        'post_status'    => ['publish', 'pending'],
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'fields'         => 'ids',
        // FILTRE : On masque les logements gérés par channel externe
        'meta_query'     => [
            'relation' => 'OR',
            [
                'key'     => 'mode_reservation',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key'     => 'mode_reservation',
                'value'   => 'log_channel',
                'compare' => '!=',
            ],
        ],
    ]);

    if (! empty($manual_logements)) {
        foreach ($manual_logements as $logement_id) {
            $title = get_the_title($logement_id);
            if (! $title) {
                continue;
            }
            $manual_logement_options[$logement_id] = $title;
        }
    }
    if (! empty($manual_logement_options)) {
        asort($manual_logement_options);
    }

    wp_localize_script(
        'pc-resa-dashboard',
        'pcResaParams',
        [
            'ajaxUrl'           => admin_url('admin-ajax.php'),
            'manualNonce'       => $manual_nonce,
            'experienceTarifs'  => $manual_experience_tarifs,
            'experienceOptions' => $manual_experience_options,
            'logementOptions'   => $manual_logement_options,
            'logementQuote'     => null,
            'translations'      => [
                'genericError' => __('Erreur technique', 'pc'),
                'loading'      => __('Chargement...', 'pc'),
            ],
        ]
    );

    ob_start();

    $template_base = trailingslashit($plugin_path . 'templates/dashboard');

    include $template_base . 'list.php';
    include $template_base . 'modal-detail.php';
    include $template_base . 'popups.php';

    return ob_get_clean();
}
add_shortcode('pc_resa_dashboard', 'pc_resa_dashboard_shortcode');
