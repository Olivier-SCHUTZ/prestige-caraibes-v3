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

    $booked_dates = get_post_meta($post_id, '_booked_dates_cache', true);
    if (is_array($booked_dates) && !empty($booked_dates)) {
        $ics_disable = array_merge($ics_disable, pc_resa_dashboard_dates_to_ranges($booked_dates));
    }
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

    if (!empty($reservations)) {
        global $wpdb;

        $ids = array_filter(array_map(function ($resa) {
            return isset($resa->id) ? (int) $resa->id : 0;
        }, $reservations));

        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $table        = $wpdb->prefix . 'pc_reservations';
            $sql         = "SELECT id, detail_tarif FROM {$table} WHERE id IN ({$placeholders})";
            // $wpdb->prepare attend des arguments séparés, on unpacke le tableau d'ids
            $prepared    = $wpdb->prepare($sql, ...array_values($ids));
            $detail_rows = $wpdb->get_results($prepared, OBJECT_K);

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

    // Récupération des logements / expériences disponibles pour les sélecteurs
    $all_for_filters = PCR_Reservation::get_list([
        'limit'  => 9999,
        'offset' => 0,
    ]);

    $logement_options = [];
    $exp_options      = [];

    if (! empty($all_for_filters)) {
        foreach ($all_for_filters as $r) {
            $pid = isset($r->item_id) ? (int) $r->item_id : 0;
            if (! $pid) {
                continue;
            }
            $title = get_the_title($pid);
            if (! $title) {
                continue;
            }

            if ($r->type === 'location') {
                $logement_options[$pid] = $title;
            } elseif ($r->type === 'experience') {
                $exp_options[$pid] = $title;
            }
        }
    }

    if (! empty($logement_options)) {
        asort($logement_options);
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

    ob_start();
?>
    <div class="pc-resa-dashboard-wrapper">
        <div class="pc-resa-dashboard-header">
            <div class="pc-resa-dashboard-header__left">
                <h2 class="pc-resa-dashboard-title">Réservations</h2>
                <p class="pc-resa-dashboard-subtitle">Logements &amp; expériences</p>
            </div>
            <div class="pc-resa-dashboard-header__right">
                <button type="button" class="pc-resa-create-btn">
                    + Créer une réservation
                </button>
            </div>
        </div>

        <form method="get" class="pc-resa-filters">
            <span class="pc-resa-filters__label">Filtres :</span>

            <?php if ($type_filter) : ?>
                <input type="hidden" name="pc_resa_type" value="<?php echo esc_attr($type_filter); ?>">
            <?php endif; ?>

            <div class="pc-resa-filters__group">
                <span class="pc-resa-filters__group-label">Type</span>
                <button type="submit"
                    name="pc_resa_type"
                    value="experience"
                    class="pc-resa-filter-btn <?php echo ($type_filter === 'experience') ? 'is-active' : ''; ?>">
                    Expérience
                </button>
                <button type="submit"
                    name="pc_resa_type"
                    value="location"
                    class="pc-resa-filter-btn <?php echo ($type_filter === 'location') ? 'is-active' : ''; ?>">
                    Logement
                </button>
            </div>

            <div class="pc-resa-filters__group">
                <span class="pc-resa-filters__group-label">Logement</span>
                <select name="pc_resa_item_location" onchange="this.form.submit()">
                    <option value="">Tous</option>
                    <?php foreach ($logement_options as $pid => $title) : ?>
                        <option value="<?php echo esc_attr($pid); ?>"
                            <?php selected($item_id, $pid); ?>>
                            <?php echo esc_html($title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pc-resa-filters__group">
                <span class="pc-resa-filters__group-label">Expérience</span>
                <select name="pc_resa_item_experience" onchange="this.form.submit()">
                    <option value="">Toutes</option>
                    <?php foreach ($exp_options as $pid => $title) : ?>
                        <option value="<?php echo esc_attr($pid); ?>"
                            <?php selected($item_id, $pid); ?>>
                            <?php echo esc_html($title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit"
                name="pc_resa_reset"
                value="1"
                class="pc-resa-filter-reset">
                Réinitialiser
            </button>
        </form>

        <table class="pc-resa-dashboard-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Type</th>
                    <th>Logement / Expérience</th>
                    <th>Client</th>
                    <th>Dates</th>
                    <th>Montant</th>
                    <th>Statuts</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (! empty($reservations)) : ?>
                    <?php foreach ($reservations as $resa) : ?>
                        <?php
                        $type_label = ($resa->type === 'experience') ? 'Expérience' : 'Location';

                        // ID du logement / expérience (colonne item_id)
                        $post_id    = isset($resa->item_id) ? (int) $resa->item_id : 0;
                        $post_title = $post_id ? get_the_title($post_id) : '';
                        $post_link  = $post_id ? get_permalink($post_id) : '';

                        // Client (colonnes prenom, nom, email)
                        $client_name  = trim(($resa->prenom ?? '') . ' ' . ($resa->nom ?? ''));
                        $client_email = $resa->email ?? '';

                        // Dates : location = date_arrivee + date_depart, expérience = date_experience
                        $dates_label = '';
                        if ($resa->type === 'location' && ! empty($resa->date_arrivee) && ! empty($resa->date_depart)) {
                            $dates_label = sprintf(
                                'Du %s au %s',
                                date_i18n('d/m/Y', strtotime($resa->date_arrivee)),
                                date_i18n('d/m/Y', strtotime($resa->date_depart))
                            );
                        } elseif ($resa->type === 'experience' && ! empty($resa->date_experience)) {
                            $dates_label = sprintf(
                                'Le %s',
                                date_i18n('d/m/Y', strtotime($resa->date_experience))
                            );
                        }

                        // Montant total (colonne montant_total)
                        $total_amount = isset($resa->montant_total) ? (float) $resa->montant_total : 0;

                        $statut_resa     = ! empty($resa->statut_reservation) ? $resa->statut_reservation : '';
                        $statut_paiement = ! empty($resa->statut_paiement) ? $resa->statut_paiement : '';

                        // Labels lisibles
                        $statut_resa_label     = $statut_resa ? pc_resa_format_status_label($statut_resa) : '';
                        $statut_paiement_label = $statut_paiement ? pc_resa_format_status_label($statut_paiement) : '';

                        // Paiements liés
                        $payments = class_exists('PCR_Payment')
                            ? PCR_Payment::get_for_reservation($resa->id)
                            : [];

                        // Montants payé / dû pour cette réservation
                        $total_paid = 0;
                        if (! empty($payments)) {
                            foreach ($payments as $payment_line) {
                                $line_amount = isset($payment_line->montant) ? (float) $payment_line->montant : 0;
                                $line_status = isset($payment_line->statut) ? $payment_line->statut : '';

                                if ($line_status === 'paye') {
                                    $total_paid += $line_amount;
                                }
                            }
                        }

                        $total_due = max(0, $total_amount - $total_paid);

                        $prefill_payload = pc_resa_build_prefill_payload($resa);
                        $prefill_json    = $prefill_payload ? wp_json_encode($prefill_payload) : '';
                        $can_use_prefill = ! empty($prefill_json) && $resa->type === 'experience';

                        $primary_action_slug  = 'default';
                        $primary_action_label = 'Action principale';
                        if (! empty($resa->type_flux) && $resa->type_flux === 'devis') {
                            $primary_action_slug  = 'send_quote';
                            $primary_action_label = 'Envoyer le devis au client';
                        } elseif ($statut_resa === 'en_attente_paiement') {
                            $primary_action_slug  = 'record_payment';
                            $primary_action_label = 'Enregistrer un paiement';
                        } elseif ($statut_resa === 'en_attente_traitement') {
                            $primary_action_slug  = 'confirm_booking';
                            $primary_action_label = 'Confirmer la réservation';
                        }

                        $reservation_data_attrs = sprintf(
                            ' data-reservation-id="%1$s" data-reservation-type="%2$s" data-reservation-status="%3$s" data-reservation-payment-status="%4$s" data-type-flux="%5$s"',
                            esc_attr($resa->id),
                            esc_attr($resa->type),
                            esc_attr($statut_resa),
                            esc_attr($statut_paiement),
                            esc_attr($resa->type_flux ?? '')
                        );
                        ?>
                        <tr class="pc-resa-dashboard-row pc-resa-dashboard-row-toggle"
                            data-resa-id="<?php echo esc_attr($resa->id); ?>"
                            style="cursor:pointer;">
                            <td>#<?php echo esc_html($resa->id); ?></td>
                            <td><?php echo esc_html($type_label); ?></td>
                            <td>
                                <?php if ($post_link) : ?>
                                    <a href="<?php echo esc_url($post_link); ?>" target="_blank">
                                        <?php echo esc_html($post_title); ?>
                                    </a>
                                <?php else : ?>
                                    <?php echo esc_html($post_title); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html($client_name); ?><br>
                                <small><?php echo esc_html($client_email); ?></small>
                            </td>
                            <td><?php echo esc_html($dates_label); ?></td>
                            <td><?php echo esc_html(number_format($total_amount, 2, ',', ' ')); ?> €</td>
                            <td>
                                <?php if ($statut_resa && $statut_resa === $statut_paiement) : ?>
                                    <span class="pc-resa-badge pc-resa-badge--resa">
                                        <?php echo esc_html($statut_resa_label); ?>
                                    </span>
                                <?php else : ?>
                                    <?php if ($statut_resa) : ?>
                                        <span class="pc-resa-badge pc-resa-badge--resa">
                                            <?php echo esc_html($statut_resa_label); ?>
                                        </span><br>
                                    <?php endif; ?>
                                    <?php if ($statut_paiement) : ?>
                                        <span class="pc-resa-badge pc-resa-badge--pay">
                                            <?php echo esc_html($statut_paiement_label); ?>
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="pc-resa-view-link" type="button" data-resa-id="<?php echo esc_attr($resa->id); ?>">
                                    <svg class="pc-resa-view-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                        <polyline points="14 2 14 8 20 8"></polyline>
                                    </svg>
                                    Fiche
                                </button>
                            </td>
                        </tr>

                        <tr class="pc-resa-dashboard-row-detail"
                            data-resa-id="<?php echo esc_attr($resa->id); ?>"
                            style="display:none;">
                            <td colspan="7">
                                <div class="pc-resa-card">
                                    <div class="pc-resa-card__header">
                                        <div class="pc-resa-header-grid">
                                            <div class="pc-resa-header-col pc-resa-header-col--client">
                                                <h3>Réservation #<?php echo esc_html($resa->id); ?></h3>

                                                <p class="pc-resa-header-client-name">
                                                    <?php echo esc_html($client_name); ?>
                                                </p>

                                                <?php if (! empty($client_email)) : ?>
                                                    <p>
                                                        <a href="mailto:<?php echo esc_attr($client_email); ?>">
                                                            <?php echo esc_html($client_email); ?>
                                                        </a>
                                                    </p>
                                                <?php endif; ?>

                                                <?php if (! empty($resa->telephone)) : ?>
                                                    <p>
                                                        <a href="tel:<?php echo esc_attr($resa->telephone); ?>">
                                                            <?php echo esc_html($resa->telephone); ?>
                                                        </a>
                                                    </p>
                                                <?php endif; ?>

                                                <?php if (! empty($resa->langue)) : ?>
                                                    <p class="pc-resa-header-langue">
                                                        <strong>Langue :</strong> <?php echo esc_html($resa->langue); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>

                                            <div class="pc-resa-header-col pc-resa-header-col--meta">
                                                <p>
                                                    <strong>Type :</strong>
                                                    <?php echo esc_html($type_label); ?> – <?php echo esc_html($post_title); ?>
                                                </p>

                                                <p>
                                                    <strong>Statut :</strong>
                                                    <?php if ($statut_resa && $statut_resa === $statut_paiement) : ?>
                                                        <?php echo esc_html($statut_resa_label); ?>
                                                    <?php else : ?>
                                                        <?php if ($statut_resa) : ?>
                                                            <?php echo esc_html($statut_resa_label); ?>
                                                        <?php endif; ?>
                                                        <?php if ($statut_paiement) : ?>
                                                            &nbsp;/&nbsp;<?php echo esc_html($statut_paiement_label); ?>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </p>

                                                <?php
                                                $flux_label = (isset($resa->type_flux) && $resa->type_flux === 'devis') ? 'Devis' : 'Réservation';
                                                $origine    = $resa->origine ?? 'site';
                                                ?>
                                                <p>
                                                    <strong>Flux :</strong> <?php echo esc_html($flux_label); ?>
                                                    <?php if (! empty($resa->numero_devis)) : ?>
                                                        &nbsp;– <strong>Devis :</strong> <?php echo esc_html($resa->numero_devis); ?>
                                                    <?php endif; ?>
                                                </p>

                                                <p>
                                                    <strong>Origine :</strong> <?php echo esc_html($origine); ?><br>
                                                    <small>Créée le : <?php echo esc_html($resa->date_creation); ?></small>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="pc-resa-header-actions" <?php echo $reservation_data_attrs; ?>>
                                            <button type="button"
                                                class="pc-btn pc-btn--primary pc-resa-action-primary"
                                                data-primary-action="<?php echo esc_attr($primary_action_slug); ?>" <?php echo $reservation_data_attrs; ?>>
                                                <?php echo esc_html($primary_action_label); ?>
                                            </button>
                                            <div class="pc-resa-actions-more">
                                                <button type="button"
                                                    class="pc-btn pc-btn--line pc-resa-actions-toggle"
                                                    aria-haspopup="true"
                                                    aria-expanded="false">
                                                    Plus d'actions ▾
                                                </button>
                                                <ul class="pc-resa-actions-menu" role="menu">
                                                    <li>
                                                        <button type="button"
                                                            class="pc-resa-actions-menu__link pc-resa-action pc-resa-action-edit-quote pc-resa-edit-quote"
                                                            role="menuitem"
                                                            data-action="edit_quote" <?php echo $reservation_data_attrs; ?>
                                                            data-prefill="<?php echo esc_attr($prefill_json); ?>">
                                                            Modifier le devis
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button type="button"
                                                            class="pc-resa-actions-menu__link pc-resa-action pc-resa-action-send-quote"
                                                            role="menuitem"
                                                            data-action="send_quote" <?php echo $reservation_data_attrs; ?>
                                                            data-prefill="<?php echo esc_attr($prefill_json); ?>">
                                                            Envoyer le devis
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button type="button"
                                                            class="pc-resa-actions-menu__link pc-resa-action pc-resa-action-resend-quote pc-resa-resend-quote"
                                                            role="menuitem"
                                                            data-action="resend_quote" <?php echo $reservation_data_attrs; ?>
                                                            data-prefill="<?php echo esc_attr($prefill_json); ?>">
                                                            Renvoyer le devis
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button type="button"
                                                            class="pc-resa-actions-menu__link pc-resa-action pc-resa-action-confirm-booking"
                                                            role="menuitem"
                                                            data-action="confirm_booking" <?php echo $reservation_data_attrs; ?>>
                                                            Confirmer la réservation
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button type="button"
                                                            class="pc-resa-actions-menu__link pc-resa-action pc-resa-action-cancel-booking"
                                                            role="menuitem"
                                                            data-action="cancel_booking" <?php echo $reservation_data_attrs; ?>>
                                                            Annuler la réservation
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button type="button"
                                                            class="pc-resa-actions-menu__link pc-resa-action pc-resa-action-send-payment-link"
                                                            role="menuitem"
                                                            data-action="send_payment_link" <?php echo $reservation_data_attrs; ?>>
                                                            Envoyer un lien de paiement
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button type="button"
                                                            class="pc-resa-actions-menu__link pc-resa-action pc-resa-action-add-message"
                                                            role="menuitem"
                                                            data-action="add_message" <?php echo $reservation_data_attrs; ?>>
                                                            Ajouter un message
                                                        </button>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="pc-resa-card__body">
                                        <div class="pc-resa-card__section">
                                            <h4>Séjour</h4>

                                            <?php
                                            // Texte participants / occupants
                                            $guests_text = '';
                                            if (isset($guests_label) && $guests_label !== '') {
                                                $guests_text = $guests_label;
                                            } else {
                                                $parts = [];
                                                $parts[] = intval($resa->adultes) . ' adulte(s)';
                                                if (! empty($resa->enfants)) {
                                                    $parts[] = intval($resa->enfants) . ' enfant(s)';
                                                }
                                                if (! empty($resa->bebes)) {
                                                    $parts[] = intval($resa->bebes) . ' bébé(s)';
                                                }
                                                $guests_text = implode(' – ', $parts);
                                            }
                                            ?>

                                            <?php if ($resa->type === 'location') : ?>
                                                <p>
                                                    <strong>Logement :</strong>
                                                    <?php echo esc_html($post_title); ?>
                                                </p>

                                                <?php if (! empty($dates_label)) : ?>
                                                    <p><?php echo esc_html($dates_label); ?></p>
                                                <?php endif; ?>

                                                <p>
                                                    <strong>Occupants :</strong>
                                                    <?php echo esc_html($guests_text); ?>
                                                </p>
                                            <?php else : ?>
                                                <p>
                                                    <strong>Expérience :</strong>
                                                    <?php echo esc_html($post_title); ?>
                                                </p>

                                                <?php if (! empty($dates_label)) : ?>
                                                    <p><?php echo esc_html($dates_label); ?></p>
                                                <?php endif; ?>

                                                <p>
                                                    <strong>Participants :</strong>
                                                    <?php echo esc_html($guests_text); ?>
                                                </p>
                                            <?php endif; ?>

                                            <p>
                                                <strong>Montant total :</strong>
                                                <?php echo esc_html(number_format($total_amount, 2, ',', ' ')); ?> €
                                            </p>
                                            <p>
                                                <strong>Montant payé :</strong>
                                                <?php echo esc_html(number_format($total_paid, 2, ',', ' ')); ?> €
                                            </p>
                                            <p>
                                                <strong>Montant dû :</strong>
                                                <?php echo esc_html(number_format($total_due, 2, ',', ' ')); ?> €
                                            </p>
                                        </div>

                                        <div class="pc-resa-card__section">
                                            <h4><?php echo ($resa->type === 'location') ? 'Devis et Politique' : 'Devis'; ?></h4>

                                            <?php
                                            $quote_lines_raw = isset($resa->detail_tarif) ? $resa->detail_tarif : '';
                                            $quote_lines     = [];
                                            if (! empty($quote_lines_raw)) {
                                                $decoded_lines = json_decode($quote_lines_raw, true);
                                                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_lines)) {
                                                    $quote_lines = $decoded_lines;
                                                }
                                            }
                                            $quote_total_from_lines = 0;
                                            ?>

                                            <?php
                                            $known_adjustments = [];
                                            if (! empty($quote_lines)) {
                                                foreach ($quote_lines as $line_meta) {
                                                    if (! is_array($line_meta) || empty($line_meta['is_adjustment'])) {
                                                        continue;
                                                    }
                                                    $label_key = strtolower(trim($line_meta['label'] ?? ''));
                                                    if ($label_key === '') {
                                                        continue;
                                                    }
                                                    $amount_val = 0;
                                                    if (isset($line_meta['amount']) && $line_meta['amount'] != 0) {
                                                        $amount_val = (float) $line_meta['amount'];
                                                    } elseif (! empty($line_meta['price'])) {
                                                        $amount_val = pc_resa_dashboard_parse_amount($line_meta['price']);
                                                    }
                                                    $known_adjustments[$label_key] = $amount_val;
                                                }
                                            }
                                            ?>

                                            <?php if (! empty($quote_lines)) : ?>
                                                <ul class="pc-resa-devis-list">
                                                    <?php foreach ($quote_lines as $line) :
                                                        if (! is_array($line)) {
                                                            continue;
                                                        }
                                                        $is_separator = ! empty($line['is_separator']) || ! empty($line['isSeparator']) || (! empty($line['type']) && $line['type'] === 'separator');
                                                        $line_label   = isset($line['label']) ? trim((string) $line['label']) : '';
                                                        $line_price   = isset($line['price']) ? trim((string) $line['price']) : '';
                                                        $line_amount  = isset($line['amount']) ? (float) $line['amount'] : 0;
                                                        $normalized_label = strtolower($line_label);

                                                        $line_label = pc_resa_dashboard_normalize_quote_text($line_label);
                                                        $line_price = pc_resa_dashboard_normalize_quote_text($line_price);

                                                        if ($line_price === '' && $line_amount !== 0) {
                                                            $line_price = number_format($line_amount, 2, ',', ' ') . ' €';
                                                        } elseif ($line_price === '' && $line_amount === 0 && !$is_separator) {
                                                            // keep empty to avoid showing 0 if line explicitly gratuit without label
                                                            $line_price = '';
                                                        }

                                                        if (! $is_separator && empty($line['is_adjustment']) && $normalized_label !== '' && isset($known_adjustments[$normalized_label])) {
                                                            $line_amount_for_compare = $line_amount !== 0
                                                                ? $line_amount
                                                                : ($line_price !== '' ? pc_resa_dashboard_parse_amount($line_price) : 0);
                                                            if (abs($line_amount_for_compare - $known_adjustments[$normalized_label]) < 0.01) {
                                                                continue;
                                                            }
                                                        }

                                                        if (! $is_separator && isset($line['amount']) && is_numeric($line['amount'])) {
                                                            $quote_total_from_lines += (float) $line['amount'];
                                                        }
                                                    ?>
                                                        <?php if ($is_separator) : ?>
                                                            <li class="pc-resa-devis-line pc-resa-devis-separator">
                                                                <?php echo esc_html($line_label); ?>
                                                            </li>
                                                        <?php else : ?>
                                                            <li class="pc-resa-devis-line">
                                                                <span class="pc-resa-devis-line-label"><?php echo esc_html($line_label); ?></span>
                                                                <?php if ($line_price !== '') : ?>
                                                                    <span class="pc-resa-devis-line-price"><?php echo esc_html($line_price); ?></span>
                                                                <?php endif; ?>
                                                            </li>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </ul>
                                                <?php
                                                $quote_total_display = $total_amount > 0 ? (float) $total_amount : (float) $quote_total_from_lines;
                                                if ($quote_total_display > 0) :
                                                ?>
                                                    <div class="pc-resa-devis-total">
                                                        <span>Total</span>
                                                        <span><?php echo esc_html(number_format($quote_total_display, 2, ',', ' ')); ?> €</span>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else : ?>
                                                <?php if ($statut_paiement === 'sur_devis') : ?>
                                                    <p><em>Devis en attente (sur devis).</em></p>
                                                <?php else : ?>
                                                    <p><em>Aucun détail de devis enregistré.</em></p>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <?php if ($resa->type === 'location') : ?>
                                                <?php
                                                $politique = function_exists('get_field') ? get_field('politique_dannulation', $resa->item_id) : '';
                                                if (!empty($politique)) :
                                                ?>
                                                    <h5>Politique d’annulation appliquée :</h5>
                                                    <div class="pc-resa-politique">
                                                        <?php echo wp_kses_post($politique); ?>
                                                    </div>
                                                <?php else : ?>
                                                    <p><em>Aucune politique d’annulation renseignée pour ce logement.</em></p>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <?php if ($resa->type === 'location') : ?>
                                                <?php if (! empty($resa->snapshot_politique)) : ?>
                                                    <p>
                                                        <strong>Politique appliquée :</strong><br>
                                                        <?php echo nl2br(esc_html($resa->snapshot_politique)); ?>
                                                    </p>
                                                <?php else : ?>
                                                    <p>
                                                        <em>Aucune politique enregistrée au moment de la réservation.</em>
                                                    </p>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>

                                        <div class="pc-resa-card__section">
                                            <h4>Échéancier de paiements</h4>

                                            <?php if (! empty($payments)) : ?>
                                                <table class="pc-resa-payments-table">
                                                    <thead>
                                                        <tr>
                                                            <th>Type</th>
                                                            <th>Montant</th>
                                                            <th>Échéance</th>
                                                            <th>Statut</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($payments as $pay) : ?>
                                                            <?php
                                                            $pay_id      = isset($pay->id) ? (int) $pay->id : 0;
                                                            $pay_type    = isset($pay->type_paiement) ? $pay->type_paiement : '';
                                                            $pay_amount = isset($pay->montant) ? (float) $pay->montant : 0;
                                                            $due_label  = '';
                                                            if (! empty($pay->date_echeance)) {
                                                                $due_label = date_i18n('d/m/Y', strtotime($pay->date_echeance));
                                                            }

                                                            $pay_status_raw   = isset($pay->statut) ? $pay->statut : '';
                                                            $pay_status_label = $pay_status_raw ? pc_resa_format_status_label($pay_status_raw) : '';

                                                            $payment_row_attrs = sprintf(
                                                                ' data-payment-id="%1$s" data-payment-type="%2$s" data-payment-status="%3$s" data-reservation-id="%4$s"',
                                                                esc_attr($pay_id),
                                                                esc_attr($pay_type),
                                                                esc_attr($pay_status_raw),
                                                                esc_attr($resa->id)
                                                            );
                                                            ?>
                                                            <tr class="pc-resa-payment-row" <?php echo $payment_row_attrs; ?>>
                                                                <td><?php echo esc_html($pay_type); ?></td>
                                                                <td><?php echo esc_html(number_format($pay_amount, 2, ',', ' ')); ?> €</td>
                                                                <td><?php echo esc_html($due_label); ?></td>
                                                                <td><?php echo esc_html($pay_status_label); ?></td>
                                                                <td>
                                                                    <div class="pc-resa-payment-actions">
                                                                        <?php if ($pay_status_raw === 'en_attente') : ?>
                                                                            <button type="button"
                                                                                class="pc-resa-payment-action pc-resa-payment-mark-paid"
                                                                                data-action="mark_paid" <?php echo $reservation_data_attrs; ?>
                                                                                data-payment-id="<?php echo esc_attr($pay_id); ?>"
                                                                                data-payment-type="<?php echo esc_attr($pay_type); ?>"
                                                                                data-payment-status="<?php echo esc_attr($pay_status_raw); ?>">
                                                                                Marquer comme payé
                                                                            </button>
                                                                        <?php elseif ($pay_status_raw === 'paye') : ?>
                                                                            <button type="button"
                                                                                class="pc-resa-payment-action pc-resa-payment-mark-cancelled"
                                                                                data-action="mark_cancelled" <?php echo $reservation_data_attrs; ?>
                                                                                data-payment-id="<?php echo esc_attr($pay_id); ?>"
                                                                                data-payment-type="<?php echo esc_attr($pay_type); ?>"
                                                                                data-payment-status="<?php echo esc_attr($pay_status_raw); ?>"
                                                                                disabled>
                                                                                Corriger / annuler
                                                                            </button>
                                                                        <?php else : ?>
                                                                            <span class="pc-resa-payment-action-placeholder">
                                                                                Action disponible prochainement
                                                                            </span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            <?php else : ?>
                                                <p>Aucun paiement enregistré.</p>
                                            <?php endif; ?>

                                            <?php if ($resa->type === 'location') : ?>
                                                <div class="pc-resa-section-caution">
                                                    <h5>Caution (logement)</h5>
                                                    <?php
                                                    $caution_value = function_exists('get_field') ? get_field('caution', $resa->item_id) : '';
                                                    if ($caution_value !== '' && $caution_value !== null) :
                                                    ?>
                                                        <p><strong>Montant de la caution :</strong> <?php echo esc_html(number_format((float) $caution_value, 0, ',', ' ')); ?> €</p>
                                                    <?php else : ?>
                                                        <p><em>Aucune caution configurée pour ce logement.</em></p>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Bloc Infos client + Notes internes -->
                                    <div class="pc-resa-extra">
                                        <div class="pc-resa-extra-col">
                                            <h3>Infos client</h3>
                                            <p><strong>Civilité :</strong> <?php echo esc_html($resa->civilite); ?></p>
                                            <p><strong>Langue :</strong> <?php echo esc_html($resa->langue); ?></p>
                                            <p><strong>Email :</strong> <?php echo esc_html($resa->email); ?></p>
                                            <p><strong>Téléphone :</strong> <?php echo esc_html($resa->telephone); ?></p>

                                            <p><strong>Message du client :</strong><br>
                                                <?php
                                                $commentaire_client = isset($resa->commentaire_client) ? wp_unslash($resa->commentaire_client) : '';
                                                echo $commentaire_client !== ''
                                                    ? nl2br(esc_html($commentaire_client))
                                                    : '<em>Aucun message renseigné</em>';
                                                ?>
                                            </p>
                                        </div>

                                        <div class="pc-resa-extra-col">
                                            <h3>Notes internes</h3>
                                            <p>
                                                <?php
                                                $notes_internes = isset($resa->notes_internes) ? wp_unslash($resa->notes_internes) : '';
                                                echo $notes_internes !== ''
                                                    ? nl2br(esc_html($notes_internes))
                                                    : '<em>Aucune note interne</em>';
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="8">Aucune réservation trouvée.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Template création réservation -->
    <div id="pc-resa-create-template" style="display:none;">
        <div class="pc-resa-create">
            <h3 class="pc-resa-create-title">Créer une réservation</h3>
            <p class="pc-resa-create-intro">
                Créez manuellement un devis ou une réservation pour un logement ou une expérience.
            </p>
            <p class="pc-resa-create-hint">
                Choisissez le type correspondant : le moteur applique automatiquement les règles tarifaires existantes.
            </p>

            <form class="pc-resa-create-form">
                <input type="hidden" name="action" value="pc_manual_reservation_create">
                <div class="pc-resa-create-grid">
                    <div class="pc-resa-create-section">
                        <h4>Type &amp; flux</h4>

                        <label class="pc-resa-field">
                            <span class="pc-resa-field-label">Type de réservation</span>
                            <select name="type">
                                <option value="experience" selected>Expérience</option>
                                <option value="location">Logement</option>
                            </select>
                        </label>

                        <label class="pc-resa-field">
                            <span class="pc-resa-field-label">Flux</span>
                            <select name="type_flux">
                                <option value="devis">Devis</option>
                                <option value="reservation">Réservation confirmée</option>
                            </select>
                        </label>

                        <input type="hidden" name="mode_reservation" value="directe">

                        <label class="pc-resa-field" data-item-field>
                            <span class="pc-resa-field-label" data-item-label>Expérience</span>
                            <select name="item_id" data-item-select>
                                <option value="">Sélectionnez une expérience</option>
                                <?php foreach ($manual_experience_options as $pid => $title) : ?>
                                    <option value="<?php echo esc_attr($pid); ?>">
                                        <?php echo esc_html($title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="pc-resa-field-hint" data-type-hint="experience">
                                Choisissez une expérience pour afficher les options tarifaires disponibles.
                            </span>
                            <span class="pc-resa-field-hint" data-type-hint="location" style="display:none;">
                                Sélectionnez un logement pour activer le calendrier et le calcul automatique.
                            </span>
                        </label>

                        <label class="pc-resa-field" data-type-toggle="experience">
                            <span class="pc-resa-field-label">Type de tarif</span>
                            <select name="experience_tarif_type" data-tarif-select disabled required>
                                <option value="">Sélectionnez une expérience d'abord</option>
                            </select>
                            <span class="pc-resa-field-hint">
                                Choisissez une expérience pour afficher les options tarifaires disponibles.
                            </span>
                        </label>

                        <label class="pc-resa-field" data-type-toggle="location" data-logement-date-field style="display:none;">
                            <span class="pc-resa-field-label">Séjour logement</span>
                            <input type="text" name="logement_dates" data-logement-range placeholder="Arrivée – Départ" autocomplete="off" readonly>
                            <input type="hidden" name="date_arrivee">
                            <input type="hidden" name="date_depart">
                            <span class="pc-resa-field-hint" data-logement-availability>
                                Les périodes occupées sont grisées automatiquement (d'après les iCal).
                            </span>
                        </label>
                    </div>

                    <div class="pc-resa-create-section">
                        <h4>Client</h4>

                        <div class="pc-resa-create-grid pc-resa-create-grid--2">
                            <label class="pc-resa-field">
                                <span class="pc-resa-field-label">Prénom</span>
                                <input type="text" name="prenom">
                            </label>
                            <label class="pc-resa-field">
                                <span class="pc-resa-field-label">Nom</span>
                                <input type="text" name="nom">
                            </label>
                        </div>

                        <label class="pc-resa-field">
                            <span class="pc-resa-field-label">Email</span>
                            <input type="email" name="email">
                        </label>

                        <label class="pc-resa-field">
                            <span class="pc-resa-field-label">Téléphone</span>
                            <input type="text" name="telephone">
                        </label>
                    </div>
                </div>

                <div class="pc-resa-create-section">
                    <h4>Détails devis</h4>

                    <label class="pc-resa-field" data-type-toggle="experience">
                        <span class="pc-resa-field-label">Date de l'expérience</span>
                        <input type="date" name="date_experience">
                    </label>

                    <div class="pc-resa-create-grid pc-resa-create-grid--3" data-quote-counters>
                        <label class="pc-resa-field">
                            <span class="pc-resa-field-label">Adultes</span>
                            <input type="number" min="0" name="adultes" value="2" data-quote-counter>
                        </label>
                        <label class="pc-resa-field">
                            <span class="pc-resa-field-label">Enfants</span>
                            <input type="number" min="0" name="enfants" value="0" data-quote-counter>
                        </label>
                        <label class="pc-resa-field">
                            <span class="pc-resa-field-label">Bébés</span>
                            <input type="number" min="0" name="bebes" value="0" data-quote-counter>
                        </label>
                    </div>
                    <p class="pc-resa-field-hint" data-type-toggle="location" style="display:none;" data-capacity-warning>
                        Ces informations sont utilisées pour calculer le devis logement (taxe de séjour, capacité, etc.).
                    </p>

                    <div class="pc-resa-create-subsection pc-resa-create-section--custom" data-quote-custom-section data-type-toggle="experience" style="display:none;">
                        <h4>Quantités personnalisées</h4>
                        <p class="pc-resa-field-hint">
                            Ajustez ici les lignes forfaitaires qui nécessitent une quantité (ex : massages, transferts...).
                        </p>
                        <div class="pc-resa-customqty-list" data-quote-customqty></div>
                    </div>

                    <div class="pc-resa-create-subsection pc-resa-create-section--options" data-quote-options-section data-type-toggle="experience" style="display:none;">
                        <h4>Options</h4>
                        <p class="pc-resa-field-hint">
                            Activez les options complémentaires et précisez une quantité si nécessaire.
                        </p>
                        <div class="pc-resa-options-list" data-quote-options></div>
                    </div>

                    <label class="pc-resa-field">
                        <span class="pc-resa-field-label">Montant total / devis (€)</span>
                        <input type="number" min="0" step="0.01" name="montant_total" readonly>
                        <span class="pc-resa-field-hint">Calculé automatiquement d'après le tarif choisi.</span>
                    </label>
                </div>

                <div class="pc-resa-create-section" data-participants-section style="display:none;">
                    <h4>Participants (ne recalcule pas le devis)</h4>
                    <div class="pc-resa-create-grid pc-resa-create-grid--3">
                        <label class="pc-resa-field">
                            <span class="pc-resa-field-label">Adultes</span>
                            <input type="number" min="0" name="participants_adultes">
                        </label>
                        <label class="pc-resa-field">
                            <span class="pc-resa-field-label">Enfants</span>
                            <input type="number" min="0" name="participants_enfants">
                        </label>
                        <label class="pc-resa-field">
                            <span class="pc-resa-field-label">Bébés</span>
                            <input type="number" min="0" name="participants_bebes">
                        </label>
                    </div>
                    <p class="pc-resa-field-hint">
                        Ces champs mettent à jour les occupants/participants sans modifier le calcul du devis.
                    </p>
                </div>

                <div class="pc-resa-create-section">
                    <h4>Remise exceptionnelle</h4>
                    <div class="pc-resa-create-grid pc-resa-create-grid--2">
                        <label class="pc-resa-field">
                            <span class="pc-resa-field-label">Libellé remise</span>
                            <input type="text" name="remise_label" value="Remise exceptionnelle">
                        </label>
                        <label class="pc-resa-field">
                            <span class="pc-resa-field-label">Montant remise (€)</span>
                            <input type="number" step="0.01" name="remise_montant" placeholder="50">
                            <span class="pc-resa-field-hint">Entrez un montant positif pour réduire le total automatiquement.</span>
                        </label>
                    </div>
                    <div class="pc-resa-remise-actions">
                        <button type="button" class="pc-btn pc-btn--line pc-resa-remise-clear" disabled>
                            Supprimer la remise
                        </button>
                    </div>
                </div>

                <div class="pc-resa-create-section">
                    <h4>Plus-value</h4>
                    <div class="pc-resa-create-grid pc-resa-create-grid--2">
                        <label class="pc-resa-field">
                            <span class="pc-resa-field-label">Libellé plus-value</span>
                            <input type="text" name="plus_label" value="Plus-value">
                        </label>
                        <label class="pc-resa-field">
                            <span class="pc-resa-field-label">Montant plus-value (€)</span>
                            <input type="number" step="0.01" name="plus_montant" placeholder="50">
                            <span class="pc-resa-field-hint">Entrez un montant positif pour majorer le total automatiquement.</span>
                        </label>
                    </div>
                    <div class="pc-resa-plus-actions">
                        <button type="button" class="pc-btn pc-btn--line pc-resa-plus-clear" disabled>
                            Supprimer la plus-value
                        </button>
                    </div>
                </div>

                <div class="pc-resa-create-summary">
                    <h4>Résumé du devis</h4>
                    <div class="pc-resa-create-summary-body" data-quote-summary>
                        <p class="pc-resa-field-hint">Sélectionnez une expérience et un tarif pour afficher le calcul.</p>
                    </div>
                    <div class="pc-resa-create-summary-total">
                        <span>Total</span>
                        <span data-quote-total>—</span>
                    </div>
                </div>

                <div class="pc-resa-create-section">
                    <h4>Devis &amp; ajustements</h4>
                    <p class="pc-resa-field-hint">
                        Laissez vide pour un total simple ou collez le JSON généré par la fiche publique pour conserver le détail.
                    </p>
                    <label class="pc-resa-field">
                        <span class="pc-resa-field-label">Détail du devis (JSON)</span>
                        <textarea name="lines_json" rows="3" placeholder='[{"label":"Adultes × 2","price":"400 €"}]'></textarea>
                    </label>
                </div>

                <div class="pc-resa-create-section">
                    <h4>Infos complémentaires</h4>
                    <label class="pc-resa-field">
                        <span class="pc-resa-field-label">Commentaire client</span>
                        <textarea name="commentaire_client" rows="2"></textarea>
                    </label>
                    <label class="pc-resa-field">
                        <span class="pc-resa-field-label">Notes internes</span>
                        <textarea name="notes_internes" rows="2"></textarea>
                    </label>
                    <label class="pc-resa-field">
                        <span class="pc-resa-field-label">Numéro de devis</span>
                        <input type="text" name="numero_devis" placeholder="DEV-2024-001">
                    </label>
                </div>

                <div class="pc-resa-create-actions">
                    <button type="button" class="pc-resa-btn pc-resa-btn--ghost pc-resa-create-cancel">
                        Annuler
                    </button>
                    <div class="pc-resa-create-actions__right">
                        <button type="button" class="pc-resa-btn pc-resa-btn--secondary pc-resa-create-send">
                            Envoyer le devis
                        </button>
                        <button type="submit" class="pc-resa-btn pc-resa-btn--primary pc-resa-create-submit">
                            Enregistrer
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>
    <style>
        .pc-resa-dashboard-wrapper {
            margin: 2rem 0;
            font-family: var(--pc-font-body);
            font-size: var(--pc-text-size);
            line-height: var(--pc-text-lh);
            color: var(--pc-color-text);
        }

        .pc-resa-dashboard-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .pc-resa-dashboard-title {
            margin: 0;
            font-family: var(--pc-font-title);
            font-size: var(--pc-h3-size);
            font-weight: var(--pc-h3-weight);
            line-height: var(--pc-h3-lh);
        }

        .pc-resa-dashboard-subtitle {
            margin: 0.1rem 0 0;
            font-size: var(--pc-text-small-size);
            color: var(--pc-color-text-light);
        }

        .pc-resa-dashboard-header__right {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .pc-resa-create-btn {
            border: none;
            border-radius: 999px;
            padding: 0.55rem 1.4rem;
            font-size: var(--pc-text-small-size);
            font-weight: 600;
            font-family: var(--pc-font-body);

            /* Utilise les variables définies dans pc-base.css */
            background: var(--pc-color-primary);
            color: var(--pc-color-btn-text);

            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.12);
            transition: background 0.15s ease, box-shadow 0.15s ease, transform 0.1s ease;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        /* On force aussi toute éventuelle span interne à rester blanche */
        .pc-resa-create-btn span {
            color: #ffffff !important;
        }

        .pc-resa-create-btn:hover {
            background: var(--pc-color-primary-hover);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.18);
            transform: translateY(-1px);
        }

        .pc-resa-create-btn:active {
            transform: translateY(0);
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
        }

        @media (max-width: 768px) {
            .pc-resa-dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .pc-resa-dashboard-header__right {
                width: 100%;
            }

            .pc-resa-create-btn {
                width: 100%;
                justify-content: center;
            }
        }

        .pc-resa-filters {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .pc-resa-filters__label {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #666;
        }

        .pc-resa-filter-btn {
            display: inline-block;
            padding: 0.35rem 0.9rem;
            border-radius: 999px;
            border: 1px solid #d0d0d0;
            background: #f8f8f8;
            font-size: 0.8rem;
            text-decoration: none;
            color: #333;
            line-height: 1.1;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 600;
            box-shadow: inset 0 -2px 0 rgba(0, 0, 0, 0.06);
        }

        .pc-resa-filter-btn.is-active {
            background: var(--pc-color-primary, #4338ca);
            border-color: var(--pc-color-primary, #4338ca);
            color: var(--pc-color-btn-text, #fff);
            box-shadow: 0 4px 12px rgba(67, 56, 202, 0.2);
        }

        .pc-resa-filter-btn:hover {
            background: #e5e7ff;
        }

        .pc-resa-filters__group {
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .pc-resa-filters__group-label {
            font-size: 0.8rem;
            color: #666;
        }

        .pc-resa-filters select {
            padding: 0.3rem 0.6rem;
            border-radius: 999px;
            border: 1px solid #d0d0d0;
            font-size: 0.8rem;
            background: #ffffff;
            box-shadow: 0 3px 8px rgba(15, 23, 42, 0.06) inset, 0 1px 2px rgba(15, 23, 42, 0.04);
            appearance: none;
            -webkit-appearance: none;
        }

        .pc-resa-filter-reset {
            margin-left: auto;
            padding: 0.35rem 0.9rem;
            border-radius: 999px;
            border: 1px solid var(--pc-color-primary, #4338ca);
            background: transparent;
            font-size: 0.8rem;
            cursor: pointer;
            color: var(--pc-color-primary, #4338ca);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .pc-resa-filter-reset:hover {
            background: rgba(67, 56, 202, 0.08);
        }

        .pc-resa-dashboard-table {
            width: 100%;
            border-collapse: collapse;
        }

        .pc-resa-dashboard-table thead th {
            text-align: left;
            padding: 0.85rem 0.9rem;
            border-bottom: 2px solid #e0e0e0;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.06em;
            background: #fafafa;
        }

        .pc-resa-dashboard-table {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.04);
        }

        .pc-resa-dashboard-table thead th {
            text-align: left;
            padding: 0.9rem 1rem;
            border-bottom: 1px solid #e2e8f0;
            text-transform: uppercase;
            font-size: 0.78rem;
            letter-spacing: 0.08em;
            background: #f8fafc;
            color: #64748b;
        }

        .pc-resa-dashboard-table tbody td {
            padding: 0.8rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
            font-size: 0.9rem;
        }

        .pc-resa-dashboard-row-detail td {
            background: #f9fafb;
        }

        .pc-resa-dashboard-row-toggle:hover td {
            background: #eff6ff;
        }

        .pc-resa-card {
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid #e0e0e0;
            background: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        }

        .pc-resa-card__header {
            display: flex;
            justify-content: space-between;
            gap: 1.25rem;
            flex-wrap: wrap;
            align-items: flex-start;
        }

        .pc-resa-header-actions {
            display: flex;
            gap: 0.5rem;
            align-items: flex-start;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .pc-resa-header-actions .pc-btn {
            white-space: nowrap;
        }

        .pc-resa-actions-more {
            position: relative;
        }

        .pc-resa-actions-menu {
            position: absolute;
            top: calc(100% + 0.25rem);
            right: 0;
            min-width: 220px;
            padding: 0.25rem 0;
            margin: 0;
            list-style: none;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.15);
            display: none;
            z-index: 9;
        }

        .pc-resa-actions-more.is-open .pc-resa-actions-menu {
            display: block;
        }

        .pc-resa-actions-menu li {
            margin: 0;
        }

        .pc-resa-actions-menu__link {
            width: 100%;
            border: none;
            background: transparent;
            padding: 0.45rem 0.9rem;
            font-size: 0.9rem;
            text-align: left;
            cursor: pointer;
            color: #0f172a;
        }

        .pc-resa-actions-menu__link:hover,
        .pc-resa-actions-menu__link:focus-visible {
            background: #f8fafc;
            outline: none;
        }

        .pc-resa-actions-menu__link[disabled] {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pc-resa-card__header h3 {
            margin: 0 0 0.25rem;
            font-size: 1.2rem;
        }

        .pc-resa-card__header p {
            margin: 0.15rem 0;
        }

        .pc-resa-card__body {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .pc-resa-card__col {
            min-width: 220px;
            flex: 1;
        }

        .pc-resa-card__col h4 {
            margin-top: 0;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .pc-resa-payments-table {
            width: 100%;
            border-collapse: collapse;
            font-size: var(--pc-text-size);
        }

        .pc-resa-payments-table th,
        .pc-resa-payments-table td {
            padding: 0.4rem 0.4rem;
            border-bottom: 1px solid #eaeaea;
        }

        .pc-resa-payment-actions {
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
        }

        .pc-resa-payment-action {
            border: none;
            background: none;
            padding: 0;
            font-size: 0.85rem;
            color: var(--pc-color-primary, #0f172a);
            cursor: pointer;
            text-decoration: underline;
        }

        .pc-resa-payment-action[disabled] {
            color: #94a3b8;
            cursor: not-allowed;
            text-decoration: none;
        }

        .pc-resa-payment-action-placeholder {
            font-size: 0.8rem;
            color: #94a3b8;
        }

        .pc-resa-badge {
            display: inline-block;
            padding: 0.25rem 0.7rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 500;
            background: #e0e7ff;
            color: #1e293b;
            border: 1px solid #c7d2fe;
            white-space: nowrap;
        }

        .pc-resa-badge--resa {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #1d4ed8;
        }

        .pc-resa-badge--pay {
            background: #ecfdf5;
            border-color: #bbf7d0;
            color: #15803d;
        }

        .pc-resa-card__footer {
            margin-top: 1.25rem;
        }

        .pc-resa-extra {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .pc-resa-extra-col h3 {
            margin: 0 0 0.5rem;
            font-size: 1rem;
            font-weight: 600;
        }

        .pc-resa-extra-col p {
            margin: 0.1rem 0;
            font-size: 0.85rem;
            color: #4b5563;
        }

        .pc-resa-create-title {
            margin-top: 0;
            margin-bottom: 0.25rem;
            font-family: var(--pc-font-title);
            font-size: var(--pc-h3-size);
            font-weight: var(--pc-h3-weight);
            line-height: var(--pc-h3-lh);
        }

        .pc-resa-create-intro {
            margin: 0 0 1.25rem;
            font-size: var(--pc-text-small-size);
            color: var(--pc-color-text-light);
        }

        .pc-resa-create-form {
            margin-top: 0.5rem;
        }

        .pc-resa-create-grid {
            display: grid;
            gap: 1rem;
        }

        .pc-resa-create-grid--2 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .pc-resa-create-grid--3 {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .pc-resa-create-section {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
        }

        .pc-resa-create-section h4 {
            margin: 0 0 0.75rem;
            font-family: var(--pc-font-title);
            font-size: var(--pc-h4-size);
            font-weight: var(--pc-h4-weight);
            line-height: var(--pc-h4-lh);
        }

        .pc-resa-create-subsection {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
        }

        .pc-resa-customqty-list,
        .pc-resa-options-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-top: 0.75rem;
        }

        .pc-resa-field--inline {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
        }

        .pc-resa-field--inline input[type="number"] {
            max-width: 120px;
        }

        .pc-resa-options-list .pc-resa-option-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 0.6rem 0.8rem;
        }

        .pc-resa-option-item__meta {
            display: flex;
            flex-direction: column;
            row-gap: 0.15rem;
        }

        .pc-resa-option-item__price {
            font-weight: 600;
            color: var(--pc-color-primary, #4338ca);
        }

        .pc-resa-option-qty {
            margin-left: 2.2rem;
        }

        .pc-resa-option-qty input[type="number"] {
            width: 90px;
        }

        .pc-resa-field {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            font-size: var(--pc-text-small-size);
        }

        .pc-resa-field-label {
            font-weight: 500;
            color: var(--pc-color-text-light);
        }

        .pc-resa-field input,
        .pc-resa-field select,
        .pc-resa-field textarea {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 0.4rem 0.6rem;
            font-family: var(--pc-font-body);
            font-size: var(--pc-text-size);
            width: 100%;
        }

        .pc-resa-field textarea {
            min-height: 90px;
        }

        .pc-resa-create-hint,
        .pc-resa-field-hint {
            font-size: 0.8rem;
            color: var(--pc-color-text-light);
            margin: 0.2rem 0 0;
        }

        .pc-resa-create-summary {
            margin-top: 1.5rem;
            padding: 1.2rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            background: #f8fafc;
        }

        .pc-resa-create-summary h4 {
            margin: 0 0 0.8rem;
            font-family: var(--pc-font-title);
            font-size: var(--pc-h4-size);
        }

        .pc-resa-create-summary-body {
            border-radius: 0.5rem;
            background: #fff;
            padding: 0.75rem 1rem;
            box-shadow: inset 0 0 0 1px #e2e8f0;
        }

        .pc-resa-create-summary-body ul {
            list-style: none;
            padding: 0;
            margin: 0;
            font-size: 0.9rem;
        }

        .pc-resa-create-summary_body li {
            display: flex;
            justify-content: space-between;
            padding: 0.35rem 0;
            border-bottom: 1px solid #edf2f7;
        }

        .pc-resa-create-summary_body li.pc-resa-summary-sep {
            border-bottom: none;
            margin-top: 0.3rem;
            padding-top: 0.6rem;
            border-top: 1px solid #edf2f7;
            font-weight: 600;
        }

        .pc-resa-create-summary_body li:last-child {
            border-bottom: none;
        }

        .pc-resa-create-summary_body li.note {
            font-style: italic;
            color: #475569;
            justify-content: flex-start;
        }

        .pc-resa-create-summary-total {
            margin-top: 0.9rem;
            display: flex;
            justify-content: space-between;
            font-weight: 600;
            font-size: 1rem;
        }

        .pc-resa-create-actions {
            margin-top: 1.75rem;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        .pc-resa-create-actions__right {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        @media (max-width: 768px) {

            .pc-resa-create-grid--2,
            .pc-resa-create-grid--3 {
                grid-template-columns: 1fr;
            }
        }

        .pc-resa-view-link .pc-resa-view-icon {
            margin-right: 6px;
            display: inline-block;
            transform: translateY(1px);
        }

        @media (max-width: 768px) {
            .pc-resa-extra {
                grid-template-columns: 1fr;
            }
        }

        .pc-resa-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.45rem 1.1rem;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 600;
            border: 1px solid transparent;
            cursor: pointer;
            transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease,
                box-shadow 0.15s ease, transform 0.1s ease;
        }

        .pc-resa-btn--primary {
            background: var(--pc-color-primary);
            color: var(--pc-color-btn-text);
            border-color: var(--pc-color-primary);
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
        }

        .pc-resa-btn--primary:hover {
            background: var(--pc-color-primary-hover, #4338ca);
        }

        .pc-resa-btn--secondary {
            background: #f1f5f9;
            color: #1f2937;
            border-color: #cbd5f5;
        }

        .pc-resa-btn--secondary:hover {
            background: #e2e8f0;
        }

        .pc-resa-btn--ghost {
            background: transparent;
            border-color: #cbd5f5;
            color: #1f2937;
        }

        .pc-resa-btn--ghost:hover {
            background: #f8fafc;
        }

        .pc-resa-view-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #4338ca;
            background: #4338ca;
            padding: 0.35rem 0.9rem;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 500;
            color: #ffffff;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.15s ease, border-color 0.15s ease,
                transform 0.1s ease, box-shadow 0.15s ease;
        }

        .pc-resa-view-link:hover {
            background: #4f46e5;
            border-color: #4f46e5;
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.25);
            transform: translateY(-1px);
        }

        .pc-resa-view-link:active {
            transform: translateY(0);
            box-shadow: none;
        }

        .pc-resa-pagination {
            margin-top: 1rem;
            display: flex;
            gap: .4rem;
            align-items: center;
        }

        .pc-resa-page-btn {
            display: inline-block;
            padding: .35rem .75rem;
            border-radius: 6px;
            border: 1px solid #ddd;
            background: #fff;
            font-size: .85rem;
            text-decoration: none;
            color: #333;
        }

        .pc-resa-page-btn:hover {
            background: #f0f0ff;
        }

        .pc-resa-page-btn.is-active {
            background: #4338ca;
            border-color: #3730a3;
            color: white;
        }

        .pc-resa-fiche-header {
            display: flex;
            justify-content: space-between;
            gap: 1.5rem;
            margin-bottom: 1rem;
        }

        .pc-resa-fiche-header-left,
        .pc-resa-fiche-header-right {
            font-size: 0.9rem;
        }

        .pc-resa-fiche-client {
            margin: 0 0 0.25rem;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .pc-resa-fiche-contact {
            margin: 0;
            line-height: 1.4;
            color: #475569;
        }

        .pc-resa-fiche-meta {
            margin: 0;
            text-align: right;
            line-height: 1.4;
            color: #64748b;
            font-size: 0.85rem;
        }

        .pc-resa-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
            justify-content: flex-end;
            margin-bottom: 0.5rem;
        }

        .pc-resa-badge--typeflux {
            background: #0f766e;
            color: #ecfeff;
        }

        .pc-resa-badge--origine {
            background: #e2e8f0;
            color: #0f172a;
        }

        @media (max-width: 768px) {
            .pc-resa-fiche-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .pc-resa-fiche-meta {
                text-align: left;
            }
        }

        /* Popup simple */
        .pc-resa-modal {
            position: fixed;
            inset: 0;
            z-index: 9999;
        }

        .pc-resa-modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
        }

        .pc-resa-modal-dialog {
            position: relative;
            max-width: 900px;
            margin: 4rem auto;
            background: #fff;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
            max-height: 80vh;
            overflow-y: auto;
        }

        .pc-resa-modal-close {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            border: none;
            background: none;
            font-size: 1.7rem;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .pc-resa-card__body {
                flex-direction: column;
            }

            .pc-resa-dashboard-table thead {
                display: table-header-group;
            }
        }

        @media (max-width: 768px) {
            .pc-resa-card__body {
                flex-direction: column;
            }

            .pc-resa-dashboard-table thead {
                display: table-header-group;
            }
        }

        .pc-resa-header-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(0, 1.6fr);
            gap: 1.5rem;
            flex: 1 1 auto;
        }

        .pc-resa-header-client-name {
            font-weight: 600;
        }

        .pc-resa-header-col--client h3 {
            margin-bottom: 0.25rem;
        }

        .pc-resa-card__section {
            flex: 1 1 260px;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 0.75rem 0.9rem;
            background: #f9fafb;
        }

        .pc-resa-card__section h4 {
            margin-top: 0;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .pc-resa-card__section h4 {
            margin-top: 0;
            margin-bottom: 0.5rem;
            font-family: var(--pc-font-title);
            font-size: var(--pc-h4-size);
            font-weight: var(--pc-h4-weight);
            line-height: var(--pc-h4-lh);
        }

        .pc-resa-devis-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .pc-resa-devis-line {
            display: flex;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 0.35rem;
            font-size: 0.9rem;
        }

        .pc-resa-devis-line-label {
            font-weight: 500;
            color: #0f172a;
        }

        .pc-resa-devis-line-price {
            color: #0f172a;
        }

        .pc-resa-devis-separator {
            border-top: 1px dashed #cbd5e1;
            padding-top: 0.35rem;
            margin-top: 0.35rem;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            color: #64748b;
        }

        .pc-resa-devis-total {
            display: flex;
            justify-content: space-between;
            gap: 0.5rem;
            margin-top: 0.75rem;
            padding-top: 0.5rem;
            border-top: 1px solid #cbd5e1;
            font-size: 1rem;
            font-weight: 600;
            color: #0f172a;
        }

        .pc-resa-remise-actions,
        .pc-resa-plus-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 0.5rem;
        }

        .pc-resa-create-grid--3-static {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .pc-resa-section-caution {
            margin-top: 1rem;
            padding-top: 0.75rem;
            border-top: 1px dashed #cbd5e1;
            font-size: 0.85rem;
        }

        @media (max-width: 768px) {
            .pc-resa-header-grid {
                grid-template-columns: 1fr;
            }

            .pc-resa-header-actions {
                width: 100%;
                justify-content: flex-start;
            }
        }

        /* ============================
        POPUP ANNULATION RÉSERVATION
        ============================ */

        .pc-popup-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            /* dark + brand */
            z-index: 99999;
            /* toujours au-dessus de tout */
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .pc-popup-overlay[hidden] {
            display: none !important;
        }

        .pc-popup-box {
            background: #fff;
            border-radius: var(--pc-radius, 10px);
            max-width: 420px;
            width: 92%;
            padding: 24px 28px;
            box-shadow: var(--pc-shadow-soft, 0 12px 32px rgba(0, 0, 0, 0.12));
            text-align: center;
        }

        .pc-popup-title {
            font-family: var(--pc-font-title);
            color: var(--pc-color-heading);
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 14px;
        }

        .pc-popup-text {
            color: var(--pc-color-text);
            font-size: 1rem;
            line-height: 1.45;
            margin-bottom: 22px;
        }

        .pc-popup-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 12px;
        }
    </style>

    </div>

    <!-- Modal global utilisé pour afficher la fiche et le formulaire de création -->
    <div id="pc-resa-modal" class="pc-resa-modal" style="display:none;">
        <div class="pc-resa-modal-backdrop" id="pc-resa-modal-close"></div>
        <div class="pc-resa-modal-dialog" role="dialog" aria-modal="true">
            <button type="button" class="pc-resa-modal-close" id="pc-resa-modal-close-btn" aria-label="Fermer">×</button>
            <div id="pc-resa-modal-content"></div>
        </div>
    </div>

    <script>
        window.pcResaExperienceTarifs = <?php echo wp_json_encode($manual_experience_tarifs); ?>;
        window.pcResaExperienceOptions = <?php echo wp_json_encode($manual_experience_options); ?>;
        window.pcResaLogementOptions = <?php echo wp_json_encode($manual_logement_options); ?>;
        const pcResaAjaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
        const pcResaManualNonce = '<?php echo esc_attr($manual_nonce); ?>';

        document.addEventListener('DOMContentLoaded', function() {

            const experiencePricingData = window.pcResaExperienceTarifs || {};
            const experienceOptions = window.pcResaExperienceOptions || {};
            const logementOptions = window.pcResaLogementOptions || {};
            const logementQuote = window.PCLogementDevis || null;
            const logementConfigCache = {};
            const logementConfigPromises = {};
            const currencyFormatter = new Intl.NumberFormat('fr-FR', {
                style: 'currency',
                currency: 'EUR',
            });
            const createTemplate = document.getElementById('pc-resa-create-template');

            // Bouton "Créer une réservation" (ouverture manuelle du popup)
            const createBtn = document.querySelector('.pc-resa-create-btn');
            if (createBtn && createTemplate) {
                createBtn.addEventListener('click', function() {
                    openManualCreateModal();
                });
            }

            const closeAllActionMenus = () => {
                document.querySelectorAll('.pc-resa-actions-more').forEach((menu) => {
                    menu.classList.remove('is-open');
                    const toggle = menu.querySelector('.pc-resa-actions-toggle');
                    if (toggle) {
                        toggle.setAttribute('aria-expanded', 'false');
                    }
                });
            };

            document.addEventListener('click', (event) => {
                const toggle = event.target.closest('.pc-resa-actions-toggle');
                if (toggle) {
                    event.preventDefault();
                    const container = toggle.closest('.pc-resa-actions-more');
                    if (!container) {
                        return;
                    }
                    const wasOpen = container.classList.contains('is-open');
                    closeAllActionMenus();
                    if (!wasOpen) {
                        container.classList.add('is-open');
                        toggle.setAttribute('aria-expanded', 'true');
                    }
                    return;
                }

                if (event.target.closest('.pc-resa-actions-menu__link')) {
                    closeAllActionMenus();
                    return;
                }

                if (!event.target.closest('.pc-resa-actions-more')) {
                    closeAllActionMenus();
                }
            }, {
                capture: false
            });

            const formatPrice = (amount) => currencyFormatter.format(amount || 0);
            const escapeHtml = (value) => String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            const parseJSONSafe = (value) => {
                if (!value) {
                    return null;
                }
                try {
                    return JSON.parse(value);
                } catch (error) {
                    console.error('JSON parse error', error);
                    return null;
                }
            };

            const fetchLogementConfig = (logementId) => {
                if (!logementId) {
                    return Promise.reject(new Error('missing_logement_id'));
                }
                const cacheKey = String(logementId);
                if (logementConfigCache[cacheKey]) {
                    return Promise.resolve(logementConfigCache[cacheKey]);
                }
                if (logementConfigPromises[cacheKey]) {
                    return logementConfigPromises[cacheKey];
                }
                const formData = new FormData();
                formData.append('action', 'pc_manual_logement_config');
                formData.append('nonce', pcResaManualNonce);
                formData.append('logement_id', cacheKey);
                console.log('[pc-devis] Demande config logement :', cacheKey);
                const promise = fetch(pcResaAjaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                }).then(async (response) => {
                    const raw = await response.text();
                    let payload = null;
                    if (raw) {
                        try {
                            payload = JSON.parse(raw);
                        } catch (error) {
                            payload = null;
                        }
                    }
                    if (!response.ok) {
                        const message = payload && payload.data && payload.data.message ?
                            payload.data.message :
                            (raw || 'Erreur serveur');
                        const err = new Error(message);
                        err.payload = payload;
                        throw err;
                    }
                    if (!payload || !payload.success || !payload.data || !payload.data.config) {
                        const message = payload && payload.data && payload.data.message ?
                            payload.data.message :
                            'Config logement introuvable';
                        const err = new Error(message);
                        err.payload = payload;
                        throw err;
                    }
                    console.log('[pc-devis] Configuration reçue par le calendrier :', payload.data.config || {});
                    if (payload.data && payload.data.config) {
                        console.log('[pc-devis] Dates à désactiver :', payload.data.config.icsDisable || []);
                    }
                    logementConfigCache[cacheKey] = payload.data.config;
                    return payload.data.config;
                }).finally(() => {
                    delete logementConfigPromises[cacheKey];
                });
                logementConfigPromises[cacheKey] = promise;
                return promise;
            };

            const decodeText = (value) => {
                if (value == null) {
                    return '';
                }
                let str = String(value);
                str = str.replace(/\\u([0-9a-fA-F]{4})/g, (_m, g1) => {
                    try {
                        return JSON.parse('"\\u' + g1 + '"');
                    } catch (e) {
                        return _m;
                    }
                });
                str = str.replace(/u([0-9a-fA-F]{4})/g, (_m, g1) => {
                    try {
                        return JSON.parse('"\\u' + g1 + '"');
                    } catch (e) {
                        return _m;
                    }
                });
                str = str.replace(/\u00a0|\u202f/g, ' ');
                return str;
            };

            const renderStoredLinesSummary = (lines, summaryBody, summaryTotal, totalValue) => {
                if (!summaryBody || !Array.isArray(lines) || lines.length === 0) {
                    return;
                }
                let html = '<ul>';
                lines.forEach((line) => {
                    const rawLabel = decodeText(line.label || '');
                    const rawPrice = decodeText(line.price || '');

                    let formattedPrice = rawPrice;
                    const numericPrice = parseFloat(rawPrice.replace(/[^\d,\.-]/g, '').replace(',', '.'));
                    if (!Number.isNaN(numericPrice) && rawPrice !== '') {
                        formattedPrice = formatPrice(numericPrice);
                    }
                    const separator = formattedPrice ? ' \u2013 ' : '';
                    html += `<li><span>${rawLabel}</span><span>${separator}${formattedPrice}</span></li>`;
                });
                html += '</ul>';
                summaryBody.innerHTML = html;
                if (summaryTotal) {
                    const numericTotal = typeof totalValue === 'number' ?
                        totalValue :
                        parseFloat(totalValue || 0);
                    summaryTotal.textContent = formatPrice(numericTotal);
                }
            };

            const getTarifConfig = (expId, key) => {
                if (!expId || !experiencePricingData[expId]) {
                    return null;
                }
                return experiencePricingData[expId].find((tarif) => tarif.key === key) || null;
            };

            // Remplit les selects [data-tarif-select] pour une expérience donnée
            const populateTarifOptions = (expId, selectedKey = '') => {
                const selects = document.querySelectorAll('select[data-tarif-select]');
                selects.forEach((select) => {
                    // vide le select
                    select.innerHTML = '';

                    if (!expId || !experiencePricingData[expId] || experiencePricingData[expId].length === 0) {
                        const opt = document.createElement('option');
                        opt.value = '';
                        opt.textContent = "Sélectionnez une expérience d'abord";
                        select.appendChild(opt);
                        select.disabled = true;
                        select.required = true;
                        return;
                    }

                    // option par défaut
                    const defaultOpt = document.createElement('option');
                    defaultOpt.value = '';
                    defaultOpt.textContent = 'Sélectionnez un tarif';
                    select.appendChild(defaultOpt);

                    experiencePricingData[expId].forEach((tarif) => {
                        const opt = document.createElement('option');
                        opt.value = tarif.key || '';
                        opt.textContent = tarif.label || tarif.key || 'Tarif';
                        select.appendChild(opt);
                    });

                    select.disabled = false;
                    select.required = true;
                    if (selectedKey) {
                        select.value = selectedKey;
                    }
                });
            };

            const computeQuote = (config, counts, extras = {}) => {
                if (!config) {
                    return {
                        lines: [],
                        html: '',
                        total: 0,
                        isSurDevis: false
                    };
                }

                const pendingLabel = 'En attente de devis';
                const isSurDevis = config.code === 'sur-devis';
                const customQtyMap = extras.customQty || {};
                const selectedOptions = Array.isArray(extras.options) ? extras.options : [];
                let total = 0;
                let html = '<ul>';
                const lines = [];

                const appendLine = (label, amount, formatted) => {
                    const priceDisplay = formatted || (isSurDevis ? pendingLabel : formatPrice(amount));
                    html += `<li><span>${label}</span><span>${priceDisplay}</span></li>`;
                    lines.push({
                        label,
                        price: priceDisplay
                    });
                    if (!isSurDevis && amount) {
                        total += amount;
                    }
                };

                (config.lines || []).forEach((line, index) => {
                    const type = line.type || 'personnalise';
                    const unit = parseFloat(line.price) || 0;
                    let qty = 1;

                    if (type === 'adulte') qty = counts.adultes;
                    else if (type === 'enfant') qty = counts.enfants;
                    else if (type === 'bebe') qty = counts.bebes;
                    else if (line.enable_qty) {
                        const mapKey = line.uid || `line_${index}`;
                        if (typeof customQtyMap[mapKey] !== 'undefined') {
                            qty = parseInt(customQtyMap[mapKey], 10) || 0;
                        } else if (line.default_qty) {
                            qty = parseInt(line.default_qty, 10) || 0;
                        } else {
                            qty = 0;
                        }
                    }

                    if ((type === 'adulte' || type === 'enfant' || type === 'bebe') && qty <= 0) {
                        if (line.observation) {
                            html += `<li class="note">${line.observation}</li>`;
                        }
                        return;
                    }

                    if (line.enable_qty && qty <= 0) {
                        if (line.observation) {
                            html += `<li class="note">${line.observation}</li>`;
                        }
                        return;
                    }

                    if (qty <= 0) {
                        return;
                    }

                    const label = `${qty} ${line.label || ''}`.trim();
                    const amount = qty * unit;

                    if (type === 'bebe' && unit === 0 && !isSurDevis) {
                        html += `<li><span>${label}</span><span>Gratuit</span></li>`;
                        lines.push({
                            label,
                            price: 'Gratuit'
                        });
                        if (line.observation) {
                            html += `<li class="note">${line.observation}</li>`;
                        }
                        return;
                    }

                    appendLine(label, amount);

                    if (line.observation) {
                        html += `<li class="note">${line.observation}</li>`;
                    }
                });

                (config.fixed_fees || []).forEach((fee) => {
                    const label = fee.label || 'Frais fixes';
                    const amount = parseFloat(fee.price) || 0;
                    if (!label || amount === 0) {
                        return;
                    }
                    appendLine(label, amount);
                });

                if (selectedOptions.length) {
                    html += '<li class="pc-resa-summary-sep"><strong>Options</strong></li>';
                    selectedOptions.forEach((opt) => {
                        const optLabel = opt.label || 'Option';
                        const optQty = Math.max(1, parseInt(opt.qty, 10) || 1);
                        const label = optQty > 1 ? `${optLabel} × ${optQty}` : optLabel;
                        const amount = (parseFloat(opt.price) || 0) * optQty;
                        appendLine(label, amount);
                    });
                }

                html += '</ul>';

                return {
                    lines,
                    html,
                    total,
                    isSurDevis,
                    pendingLabel
                };
            };

            const applyQuoteToForm = (args) => {
                const {
                    result,
                    linesTextarea,
                    totalInput,
                    summaryBody,
                    summaryTotal,
                    remiseLabel,
                    remiseAmount,
                    plusLabel,
                    plusAmount,
                } = args;

                let summaryHtml = result.html;
                const linesJson = [...result.lines];
                const remiseValue = parseFloat(remiseAmount && remiseAmount.value ? remiseAmount.value : 0) || 0;

                if (remiseValue > 0) {
                    const label = remiseLabel && remiseLabel.value ? remiseLabel.value : 'Remise exceptionnelle';
                    const signed = -Math.abs(remiseValue);
                    const display = result.isSurDevis ? result.pendingLabel : formatPrice(signed);
                    summaryHtml = summaryHtml.replace('</ul>', `<li><span>${label}</span><span>${display}</span></li></ul>`);
                    if (!result.isSurDevis) {
                        result.total += signed;
                    }
                }

                const plusValue = parseFloat(plusAmount && plusAmount.value ? plusAmount.value : 0) || 0;
                if (plusValue > 0) {
                    const label = plusLabel && plusLabel.value ? plusLabel.value : 'Plus-value';
                    const display = result.isSurDevis ? result.pendingLabel : formatPrice(Math.abs(plusValue));
                    summaryHtml = summaryHtml.replace('</ul>', `<li><span>${label}</span><span>${display}</span></li></ul>`);
                    if (!result.isSurDevis) {
                        result.total += Math.abs(plusValue);
                    }
                }

                if (summaryBody) {
                    summaryBody.innerHTML = summaryHtml || '<p class="pc-resa-field-hint">Aucun calcul disponible.</p>';
                }
                if (summaryTotal) {
                    summaryTotal.textContent = result.isSurDevis ? result.pendingLabel : formatPrice(Math.max(result.total, 0));
                }
                if (totalInput) {
                    totalInput.value = result.isSurDevis ? '' : Math.max(result.total, 0).toFixed(2);
                }
                if (linesTextarea) {
                    linesTextarea.value = linesJson.length ? JSON.stringify(linesJson) : '';
                }
            };

            async function handleManualCreateSubmit(form, submitBtn) {
                const formData = new FormData(form);
                formData.set('action', formData.get('action') || 'pc_manual_reservation_create');
                formData.set('nonce', pcResaManualNonce);

                const participantsAdults = form.querySelector('input[name="participants_adultes"]');
                const participantsEnfants = form.querySelector('input[name="participants_enfants"]');
                const participantsBebes = form.querySelector('input[name="participants_bebes"]');
                const participantsEnabled = form.getAttribute('data-participants-enabled') === '1';
                if (participantsEnabled) {
                    if (participantsAdults && participantsAdults.value !== '') {
                        formData.set('adultes', parseInt(participantsAdults.value || '0', 10) || 0);
                    }
                    if (participantsEnfants && participantsEnfants.value !== '') {
                        formData.set('enfants', parseInt(participantsEnfants.value || '0', 10) || 0);
                    }
                    if (participantsBebes && participantsBebes.value !== '') {
                        formData.set('bebes', parseInt(participantsBebes.value || '0', 10) || 0);
                    }
                }

                const typeValue = formData.get('type') || 'experience';
                if (typeValue === 'experience') {
                    if (!formData.get('item_id')) {
                        alert('Sélectionnez une expérience.');
                        return;
                    }
                    if (!formData.get('experience_tarif_type')) {
                        alert('Sélectionnez un type de tarif.');
                        return;
                    }
                } else if (typeValue === 'location') {
                    if (!formData.get('item_id')) {
                        alert('Sélectionnez un logement.');
                        return;
                    }
                    if (!formData.get('date_arrivee') || !formData.get('date_depart')) {
                        alert('Choisissez les dates du séjour logement.');
                        return;
                    }
                    if (!formData.get('lines_json')) {
                        alert('Calculez le devis logement avant de continuer.');
                        return;
                    }
                } else {
                    alert('Type de réservation inconnu.');
                    return;
                }

                const originalText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = 'Création en cours...';

                try {
                    const response = await fetch(pcResaAjaxUrl, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                    });

                    const responseText = await response.text();

                    if (!response.ok) {
                        console.error('Manual creation HTTP error', response.status, responseText);
                        alert('Erreur serveur (' + response.status + ').');
                        submitBtn.textContent = originalText;
                        submitBtn.disabled = false;
                        return;
                    }







                    let result;
                    try {
                        result = JSON.parse(responseText);
                    } catch (parseError) {
                        const trimmed = responseText.trim();
                        let userMessage = 'Réponse inattendue du serveur.';
                        if (trimmed === '0') {
                            userMessage = 'Session expirée ou accès refusé. Merci de vous reconnecter à WordPress.';
                        }
                        console.error('Manual creation raw response:', responseText);
                        alert(userMessage);
                        submitBtn.textContent = originalText;
                        submitBtn.disabled = false;
                        return;
                    }

                    if (result.success) {
                        const successMsg = result.data && result.data.message ?
                            result.data.message :
                            'Réservation enregistrée';
                        submitBtn.textContent = successMsg;
                        setTimeout(function() {
                            window.location.reload();
                        }, 800);
                    } else {
                        const errorMsg = result.data && result.data.message ? result.data.message : 'Une erreur est survenue.';
                        alert(errorMsg);
                        submitBtn.textContent = originalText;
                        submitBtn.disabled = false;
                    }
                } catch (error) {
                    console.error('Manual creation error', error);
                    alert('Erreur technique pendant la création.');
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                }
            }

            function initManualCreateForm(container, prefillData = null, options = {}) {
                const form = container.querySelector('.pc-resa-create-form');
                if (!form) {
                    return null;
                }

                const submitBtn = form.querySelector('.pc-resa-create-submit');
                const sendBtn = form.querySelector('.pc-resa-create-send');
                const typeSelect = form.querySelector('select[name="type"]');
                const typeFluxSelect = form.querySelector('select[name="type_flux"]');
                const modeSelect = form.querySelector('select[name="mode_reservation"]');
                const itemSelect = form.querySelector('select[name="item_id"]');
                const tarifSelect = form.querySelector('select[name="experience_tarif_type"]');
                const linesTextarea = form.querySelector('textarea[name="lines_json"]');
                const totalInput = form.querySelector('input[name="montant_total"]');
                const summaryBody = container.querySelector('[data-quote-summary]');
                const summaryTotal = container.querySelector('[data-quote-total]');
                const remiseLabel = form.querySelector('input[name="remise_label"]');
                const remiseAmount = form.querySelector('input[name="remise_montant"]');
                const remiseClearBtn = form.querySelector('.pc-resa-remise-clear');
                const participantsAdultsField = form.querySelector('input[name="participants_adultes"]');
                const participantsEnfantsField = form.querySelector('input[name="participants_enfants"]');
                const participantsBebesField = form.querySelector('input[name="participants_bebes"]');
                const participantsSection = form.querySelector('[data-participants-section]');
                const plusLabel = form.querySelector('input[name="plus_label"]');
                const plusAmount = form.querySelector('input[name="plus_montant"]');
                const plusClearBtn = form.querySelector('.pc-resa-plus-clear');
                const counters = form.querySelectorAll('[data-quote-counter]');
                const countersWrapper = form.querySelector('[data-quote-counters]');
                const capacityWarning = form.querySelector('[data-capacity-warning]');
                const customSection = form.querySelector('[data-quote-custom-section]');
                const customList = form.querySelector('[data-quote-customqty]');
                const optionsSection = form.querySelector('[data-quote-options-section]');
                const optionsList = form.querySelector('[data-quote-options]');
                const dateExperienceInput = form.querySelector('input[name="date_experience"]');
                const prenomInput = form.querySelector('input[name="prenom"]');
                const nomInput = form.querySelector('input[name="nom"]');
                const emailInput = form.querySelector('input[name="email"]');
                const telephoneInput = form.querySelector('input[name="telephone"]');
                const commentaireField = form.querySelector('textarea[name="commentaire_client"]');
                const notesField = form.querySelector('textarea[name="notes_internes"]');
                const numeroDevisInput = form.querySelector('input[name="numero_devis"]');
                const adultField = form.querySelector('input[name="adultes"]');
                const childField = form.querySelector('input[name="enfants"]');
                const babyField = form.querySelector('input[name="bebes"]');
                const typeLabel = form.querySelector('[data-item-label]');
                const typeHints = container.querySelectorAll('[data-type-hint]');
                const typeToggleNodes = container.querySelectorAll('[data-type-toggle]');
                const logementRangeInput = form.querySelector('[data-logement-range]');
                const arrivalInput = form.querySelector('input[name="date_arrivee"]');
                const departInput = form.querySelector('input[name="date_depart"]');
                const logementAvailability = form.querySelector('[data-logement-availability]');
                if (form) {
                    form.setAttribute('data-participants-enabled', '0');
                }

                const prefill = prefillData || null;
                const opts = options || {};
                let logementCalendar = null;
                let pendingLogementRange = null;
                let currentLogementId = '';
                let currentLogementConfig = null;

                const formatYMD = (date) => {
                    if (!(date instanceof Date)) {
                        return '';
                    }
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    return `${year}-${month}-${day}`;
                };

                const setTypeLabel = (currentType) => {
                    if (!typeLabel) {
                        return;
                    }
                    typeLabel.textContent = currentType === 'location' ? 'Logement' : 'Expérience';
                };

                const toggleTypeHints = (currentType) => {
                    typeHints.forEach((hint) => {
                        const expected = hint.getAttribute('data-type-hint');
                        if (!expected) {
                            return;
                        }
                        hint.style.display = expected === currentType ? '' : 'none';
                    });
                };

                const ensureCountersVisible = () => {
                    if (countersWrapper) {
                        countersWrapper.style.display = '';
                    }
                };

                const toggleTypeSections = (currentType) => {
                    typeToggleNodes.forEach((node) => {
                        const expected = node.getAttribute('data-type-toggle');
                        if (!expected) {
                            return;
                        }
                        node.style.display = expected === currentType ? '' : 'none';
                    });
                    if (currentType === 'location') {
                        ensureCountersVisible();
                    }
                };

                const populateItemOptions = (currentType, selectedId) => {
                    if (!itemSelect) {
                        return;
                    }
                    const source = currentType === 'location' ? logementOptions : experienceOptions;
                    const placeholder = currentType === 'location' ?
                        'Sélectionnez un logement' :
                        'Sélectionnez une expérience';
                    let html = `<option value=\"\">${placeholder}</option>`;
                    Object.keys(source || {}).forEach((id) => {
                        const label = source[id] || '';
                        if (!label) {
                            return;
                        }
                        html += `<option value=\"${id}\">${escapeHtml(label)}</option>`;
                    });
                    itemSelect.innerHTML = html;
                    let targetValue = typeof selectedId === 'undefined' ? '' : selectedId;
                    if (!targetValue) {
                        targetValue = currentType === 'location' ? lastLocationId : lastExperienceId;
                    }
                    if (targetValue && source[String(targetValue)]) {
                        itemSelect.value = String(targetValue);
                    } else {
                        itemSelect.value = '';
                    }
                    if (currentType === 'location') {
                        lastLocationId = itemSelect.value || '';
                    } else {
                        lastExperienceId = itemSelect.value || '';
                    }
                    if (currentType !== 'experience' && tarifSelect) {
                        tarifSelect.value = '';
                        tarifSelect.disabled = true;
                    } else if (tarifSelect) {
                        tarifSelect.disabled = false;
                    }
                };

                const setLogementAvailabilityMessage = (message) => {
                    if (logementAvailability) {
                        logementAvailability.textContent = message || '';
                    }
                };

                const destroyLogementCalendar = () => {
                    if (logementCalendar && typeof logementCalendar.destroy === 'function') {
                        logementCalendar.destroy();
                    }
                    logementCalendar = null;
                };

                const initLogementCalendar = (config, rangeToApply = null, clearExisting = true) => {
                    if (!logementRangeInput) {
                        return;
                    }
                    destroyLogementCalendar();
                    if (clearExisting) {
                        logementRangeInput.value = '';
                        if (arrivalInput) {
                            arrivalInput.value = '';
                        }
                        if (departInput) {
                            departInput.value = '';
                        }
                    }
                    logementRangeInput.disabled = !config;
                    if (!config) {
                        return;
                    }
                    const disableRanges = Array.isArray(config.icsDisable) ?
                        config.icsDisable.filter((range) => range && range.from && range.to) : [];
                    const disableRules = disableRanges.length ? [
                        function(date) {
                            const s = formatYMD(date);
                            return disableRanges.some((range) => s >= range.from && s <= range.to);
                        },
                    ] : [];
                    const bootCalendar = () => {
                        if (typeof window.flatpickr !== 'function') {
                            setTimeout(bootCalendar, 150);
                            return;
                        }
                        if (window.flatpickr.l10ns && window.flatpickr.l10ns.fr) {
                            window.flatpickr.localize(window.flatpickr.l10ns.fr);
                        }
                        console.log('[pc-devis] INIT FLATPICKR DASHBOARD', logementRangeInput, {
                            mode: 'range',
                            dateFormat: 'd/m/Y',
                            disableRanges,
                        });
                        logementCalendar = window.flatpickr(logementRangeInput, {
                            mode: 'range',
                            dateFormat: 'd/m/Y',
                            altInput: false,
                            minDate: 'today',
                            disable: disableRules,
                            onChange(selectedDates) {
                                if (selectedDates.length === 2) {
                                    if (arrivalInput) {
                                        arrivalInput.value = formatYMD(selectedDates[0]);
                                    }
                                    if (departInput) {
                                        departInput.value = formatYMD(selectedDates[1]);
                                    }
                                } else {
                                    if (arrivalInput) {
                                        arrivalInput.value = '';
                                    }
                                    if (departInput) {
                                        departInput.value = '';
                                    }
                                }
                                updateQuote();
                            },
                        });
                        if (logementCalendar && logementCalendar.config) {
                            console.log('[pc-devis] FLATPICKR CONFIG FINALE', logementCalendar.config.disable);
                        }
                        if (rangeToApply && Array.isArray(rangeToApply) && rangeToApply.length === 2) {
                            logementCalendar.setDate(rangeToApply, true, 'Y-m-d');
                            if (arrivalInput) {
                                arrivalInput.value = rangeToApply[0];
                            }
                            if (departInput) {
                                departInput.value = rangeToApply[1];
                            }
                        }
                    };
                    bootCalendar();
                };

                const updateCapacityLimits = (config) => {
                    if (!capacityWarning) {
                        return;
                    }
                    if (!config || typeof config.cap === 'undefined') {
                        capacityWarning.style.display = 'none';
                        capacityWarning.removeAttribute('data-current-cap');
                        return;
                    }
                    const capValue = parseInt(config.cap, 10) || 0;
                    if (capValue <= 0) {
                        capacityWarning.style.display = 'none';
                        capacityWarning.removeAttribute('data-current-cap');
                        return;
                    }
                    capacityWarning.style.display = '';
                    capacityWarning.textContent = `Capacité maximale : ${capValue} personnes (adultes + enfants).`;
                    capacityWarning.setAttribute('data-current-cap', String(capValue));
                    enforceCapacity({
                        adultes: parseInt(adultField ? adultField.value : '0', 10) || 0,
                        enfants: parseInt(childField ? childField.value : '0', 10) || 0,
                        bebes: parseInt(babyField ? babyField.value : '0', 10) || 0,
                    });
                };

                const enforceCapacity = (counts) => {
                    if (!capacityWarning) {
                        return;
                    }
                    const capAttr = capacityWarning.getAttribute('data-current-cap');
                    if (!capAttr) {
                        capacityWarning.style.display = 'none';
                        return;
                    }
                    const capValue = parseInt(capAttr, 10) || 0;
                    if (capValue <= 0) {
                        capacityWarning.style.display = 'none';
                        return;
                    }
                    let adultesCount = counts.adultes || 0;
                    let enfantsCount = counts.enfants || 0;
                    let totalGuests = adultesCount + enfantsCount;
                    if (totalGuests > capValue) {
                        const overflow = totalGuests - capValue;
                        if (enfantsCount > 0) {
                            const newChildren = Math.max(0, enfantsCount - overflow);
                            enfantsCount = newChildren;
                            if (childField) {
                                childField.value = String(newChildren);
                            }
                        }
                        totalGuests = adultesCount + enfantsCount;
                        if (totalGuests > capValue && adultesCount > 0) {
                            adultesCount = Math.max(0, capValue - enfantsCount);
                            if (adultField) {
                                adultField.value = String(adultesCount);
                            }
                        }
                        capacityWarning.textContent = `Capacité max ${capValue} personnes atteinte.`;
                    } else {
                        capacityWarning.textContent = `Capacité maximale : ${capValue} personnes (adultes + enfants).`;
                    }
                    counts.adultes = adultesCount;
                    counts.enfants = enfantsCount;
                    capacityWarning.style.display = '';
                };

                const prepareLogementConfig = (logementId, options = {}) => {
                    currentLogementId = logementId || '';
                    currentLogementConfig = null;
                    initLogementCalendar(null, null, !options.range);
                    if (!logementId) {
                        setLogementAvailabilityMessage('Sélectionnez un logement pour afficher les disponibilités.');
                        updateCapacityLimits(null);
                        updateQuote();
                        return;
                    }
                    setLogementAvailabilityMessage('Chargement des disponibilités...');
                    fetchLogementConfig(logementId)
                        .then((config) => {
                            currentLogementConfig = config;
                            setLogementAvailabilityMessage('Les périodes grisées sont indisponibles.');
                            initLogementCalendar(config, options.range || null);
                            pendingLogementRange = null;
                            updateCapacityLimits(config);
                            updateQuote();
                        })
                        .catch((error) => {
                            console.error('Logement config error', error);
                            const msg = error && error.message ? error.message : 'Impossible de charger les disponibilités.';
                            setLogementAvailabilityMessage(msg);
                            updateCapacityLimits(null);
                            updateQuote();
                        });
                };

                const toggleAdjustmentButton = (input, btn) => {
                    if (!btn || !input) {
                        return;
                    }
                    const hasValue = input.value && parseFloat(input.value) > 0;
                    btn.disabled = !hasValue;
                };
                const refreshRemiseButton = () => toggleAdjustmentButton(remiseAmount, remiseClearBtn);
                const refreshPlusButton = () => toggleAdjustmentButton(plusAmount, plusClearBtn);
                const normalizeLabelKey = (value) => {
                    if (typeof value !== 'string') {
                        value = value == null ? '' : String(value);
                    }
                    return value.trim().toLowerCase().replace(/\s+/g, ' ');
                };
                const parseQtyFromLabel = (label) => {
                    if (!label) {
                        return 0;
                    }
                    const m = label.match(/^(\d+)\s*[x×]?\s*(.+)$/i);
                    if (m && m[1]) {
                        const qty = parseInt(m[1], 10);
                        return Number.isNaN(qty) ? 0 : qty;
                    }
                    return 0;
                };
                const deriveQtyMapFromLines = (lines) => {
                    const out = {};
                    if (!Array.isArray(lines)) {
                        return out;
                    }
                    lines.forEach((line) => {
                        const rawLabel = decodeText(line && line.label ? line.label : '');
                        const qty = parseQtyFromLabel(rawLabel);
                        if (qty > 0) {
                            const normalized = normalizeLabelKey(rawLabel.replace(/^(\d+)\s*[x×]?\s*/, ''));
                            if (normalized) {
                                out[normalized] = qty;
                            }
                        }
                    });
                    return out;
                };
                const normalizePrefillQtyMap = (map) => {
                    const out = {};
                    if (!map || typeof map !== 'object') {
                        return out;
                    }
                    Object.keys(map).forEach((key) => {
                        const qty = parseInt(map[key], 10);
                        if (!Number.isNaN(qty) && qty > 0) {
                            out[normalizeLabelKey(key)] = qty;
                        }
                    });
                    return out;
                };
                let prefillQtyMap = normalizePrefillQtyMap(prefill && prefill.lines_qty_map);
                const applyPrefillSelections = () => {
                    if (!prefillQtyMap || Object.keys(prefillQtyMap).length === 0) {
                        return;
                    }

                    if (customList) {
                        customList.querySelectorAll('input[data-custom-line]').forEach((input) => {
                            const labelEl = input.closest('.pc-resa-field');
                            const labelTextEl = labelEl ? labelEl.querySelector('.pc-resa-field-label') : null;
                            const labelText = labelTextEl ? labelTextEl.textContent : '';
                            const key = normalizeLabelKey(labelText);
                            const qty = prefillQtyMap[key];
                            if (qty && qty > 0) {
                                input.value = qty;
                                input.dispatchEvent(new Event('input'));
                            }
                        });
                    }

                    if (optionsList) {
                        optionsList.querySelectorAll('input[type="checkbox"][data-option-label]').forEach((checkbox) => {
                            const encodedLabel = checkbox.getAttribute('data-option-label') || '';
                            let labelDecoded = encodedLabel;
                            try {
                                labelDecoded = decodeURIComponent(encodedLabel);
                            } catch (error) {
                                // ignore decode errors
                            }
                            const key = normalizeLabelKey(labelDecoded);
                            const qty = prefillQtyMap[key];
                            if (!qty || qty <= 0) {
                                return;
                            }
                            checkbox.checked = true;
                            const optId = checkbox.getAttribute('data-option-id');
                            if (optId) {
                                const qtyInput = optionsList.querySelector(`[data-option-qty-for="${optId}"]`);
                                if (qtyInput) {
                                    qtyInput.disabled = false;
                                    qtyInput.value = qty;
                                }
                            }
                        });
                    }
                };

                const getCustomQtyValues = () => {
                    const map = {};
                    if (!customList) {
                        return map;
                    }
                    customList.querySelectorAll('input[data-custom-line]').forEach((input) => {
                        const key = input.getAttribute('data-custom-line');
                        if (!key) {
                            return;
                        }
                        const value = parseInt(input.value, 10);
                        if (!Number.isNaN(value)) {
                            map[key] = value;
                        }
                    });
                    return map;
                };

                const getSelectedOptions = () => {
                    const selected = [];
                    if (!optionsList) {
                        return selected;
                    }
                    optionsList.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
                        if (!checkbox.checked) {
                            return;
                        }
                        const optionId = checkbox.getAttribute('data-option-id') || '';
                        const encodedLabel = checkbox.getAttribute('data-option-label') || '';
                        let label = encodedLabel;
                        try {
                            label = decodeURIComponent(encodedLabel);
                        } catch (error) {
                            // ignore decode errors and keep encoded value
                        }
                        const price = parseFloat(checkbox.getAttribute('data-option-price') || '0') || 0;
                        let qty = 1;
                        if (checkbox.dataset.enableQty === '1') {
                            const qtyInput = optionsList.querySelector(`[data-option-qty-for="${optionId}"]`);
                            qty = parseInt(qtyInput && qtyInput.value, 10) || 1;
                        }
                        selected.push({
                            id: optionId,
                            label,
                            price,
                            qty,
                        });
                    });
                    return selected;
                };

                const toggleCountersVisibility = (config) => {
                    if (!countersWrapper) {
                        return;
                    }
                    const shouldDisplay = !!(config && config.has_counters);
                    countersWrapper.style.display = shouldDisplay ? '' : 'none';
                };

                const setParticipantsEnabled = (enabled) => {
                    if (!form) {
                        return;
                    }
                    form.setAttribute('data-participants-enabled', enabled ? '1' : '0');
                };

                const normalizeCode = (value) => {
                    if (typeof value !== 'string') {
                        value = value == null ? '' : String(value);
                    }
                    return value
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .trim()
                        .toLowerCase()
                        .replace(/[^a-z0-9]+/g, '-');
                };

                const isCustomExperienceConfig = (config) => {
                    if (!config) {
                        return false;
                    }
                    const code = normalizeCode(config.code || '');
                    const label = normalizeCode(config.label || '');
                    return ['custom', 'personnalise', 'personnalisee'].includes(code) || ['custom', 'personnalise', 'personnalisee'].includes(label);
                };

                const toggleParticipantsSection = (config) => {
                    if (!participantsSection) {
                        setParticipantsEnabled(false);
                        return;
                    }
                    const typeValue = typeSelect ? typeSelect.value : 'experience';
                    const shouldShow = (typeValue === 'experience') && isCustomExperienceConfig(config);
                    participantsSection.style.display = shouldShow ? '' : 'none';
                    setParticipantsEnabled(shouldShow);
                };

                function renderCustomQtyInputs(config) {
                    if (!customList) {
                        return;
                    }
                    if (!config || !Array.isArray(config.lines)) {
                        if (customSection) {
                            customSection.style.display = 'none';
                        }
                        return;
                    }
                    const linesWithQty = config.lines.filter((line) => line.enable_qty);
                    if (linesWithQty.length === 0) {
                        if (customSection) {
                            customSection.style.display = 'none';
                        }
                        return;
                    }
                    let html = '';
                    linesWithQty.forEach((line, index) => {
                        const inputKey = line.uid || `line_${index}`;
                        const defaultValue = line.default_qty && parseInt(line.default_qty, 10) > 0 ?
                            parseInt(line.default_qty, 10) :
                            0;
                        html += `
                            <label class="pc-resa-field pc-resa-field--inline">
                                <span class="pc-resa-field-label">${escapeHtml(line.label || 'Service')}</span>
                                <input type="number" min="0" value="${defaultValue}" data-custom-line="${inputKey}">
                            </label>
                        `;
                    });
                    customList.innerHTML = html;
                    if (customSection) {
                        customSection.style.display = '';
                    }
                    customList.querySelectorAll('input[data-custom-line]').forEach((input) => {
                        input.addEventListener('input', updateQuote);
                    });
                }

                function renderOptionsInputs(config) {
                    if (!optionsList) {
                        return;
                    }
                    optionsList.innerHTML = '';
                    if (!config || !Array.isArray(config.options) || config.options.length === 0) {
                        if (optionsSection) {
                            optionsSection.style.display = 'none';
                        }
                        return;
                    }
                    let html = '';
                    config.options.forEach((opt, index) => {
                        const optionId = opt.uid || `option_${index}`;
                        const safeLabel = escapeHtml(opt.label || 'Option');
                        const encodedLabel = encodeURIComponent(opt.label || '');
                        const amountDisplay = formatPrice(opt.price || 0);
                        html += `
                            <label class="pc-resa-option-item">
                                <input type="checkbox"
                                    data-option-id="${optionId}"
                                    data-option-label="${encodedLabel}"
                                    data-option-price="${parseFloat(opt.price) || 0}"
                                    data-enable-qty="${opt.enable_qty ? '1' : '0'}">
                                <span class="pc-resa-option-item__meta">
                                    <span>${safeLabel}</span>
                                    <span class="pc-resa-option-item__price">+ ${amountDisplay}</span>
                                </span>
                            </label>
                        `;
                        if (opt.enable_qty) {
                            const defaultQty = opt.default_qty && parseInt(opt.default_qty, 10) > 0 ?
                                parseInt(opt.default_qty, 10) :
                                1;
                            html += `
                                <div class="pc-resa-option-qty">
                                    <label class="pc-resa-field pc-resa-field--inline">
                                        <span class="pc-resa-field-label">Quantité</span>
                                        <input type="number" min="1" value="${defaultQty}" data-option-qty-for="${optionId}" disabled>
                                    </label>
                                </div>
                            `;
                        }
                    });
                    optionsList.innerHTML = html;
                    if (optionsSection) {
                        optionsSection.style.display = '';
                    }
                    optionsList.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
                        checkbox.addEventListener('change', function() {
                            const optId = this.getAttribute('data-option-id');
                            const qtyInput = optionsList.querySelector(`[data-option-qty-for="${optId}"]`);
                            if (qtyInput) {
                                qtyInput.disabled = !this.checked;
                            }
                            updateQuote();
                        });
                    });
                    optionsList.querySelectorAll('[data-option-qty-for]').forEach((input) => {
                        input.addEventListener('input', updateQuote);
                    });
                }

                const refreshDynamicSections = (config) => {
                    toggleCountersVisibility(config);
                    toggleParticipantsSection(config);
                    renderCustomQtyInputs(config);
                    renderOptionsInputs(config);
                };

                let currentType = typeSelect ? (typeSelect.value || 'experience') : 'experience';
                if (prefill && prefill.type) {
                    currentType = prefill.type;
                }
                if (typeSelect) {
                    typeSelect.value = currentType;
                }
                if (prefill && prefill.type === 'location' && prefill.date_arrivee && prefill.date_depart) {
                    pendingLogementRange = [prefill.date_arrivee, prefill.date_depart];
                    if (arrivalInput) {
                        arrivalInput.value = prefill.date_arrivee;
                    }
                    if (departInput) {
                        departInput.value = prefill.date_depart;
                    }
                }

                const initialItemId = prefill && prefill.item_id ? String(prefill.item_id) : '';
                let lastExperienceId = currentType === 'experience' ? initialItemId : '';
                let lastLocationId = currentType === 'location' ? initialItemId : '';
                populateItemOptions(currentType, initialItemId);
                setTypeLabel(currentType);
                toggleTypeHints(currentType);
                toggleTypeSections(currentType);

                // Ensure hidden "id" field exists so edits submit the reservation id instead of creating a new one
                let idInput = form.querySelector('input[name="id"]');
                if (!idInput) {
                    idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'id';
                    form.appendChild(idInput);
                }
                if (prefill && typeof prefill.id !== 'undefined' && prefill.id) {
                    idInput.value = String(prefill.id);
                } else {
                    idInput.value = '0';
                }

                // Si on ouvre en mode édition, changer l'action envoyée pour appeler le handler update côté serveur
                // Garder l'action "pc_manual_reservation_create" même en mode édition
                // Le handler serveur attend cette action et doit traiter id != 0 comme une mise à jour.
                const actionInput = form.querySelector('input[name="action"]');
                const desiredAction = 'pc_manual_reservation_create';
                if (actionInput) {
                    actionInput.value = desiredAction;
                } else {
                    const a = document.createElement('input');
                    a.type = 'hidden';
                    a.name = 'action';
                    a.value = desiredAction;
                    form.appendChild(a);
                }

                if (prefill) {
                    if (dateExperienceInput && prefill.date_experience) {
                        dateExperienceInput.value = prefill.date_experience;
                    }
                    if (adultField && typeof prefill.adultes !== 'undefined') {
                        adultField.value = prefill.adultes;
                    }
                    if (childField && typeof prefill.enfants !== 'undefined') {
                        childField.value = prefill.enfants;
                    }
                    if (babyField && typeof prefill.bebes !== 'undefined') {
                        babyField.value = prefill.bebes;
                    }
                    if (prenomInput) {
                        prenomInput.value = prefill.prenom || '';
                    }
                    if (nomInput) {
                        nomInput.value = prefill.nom || '';
                    }
                    if (emailInput) {
                        emailInput.value = prefill.email || '';
                    }
                    if (telephoneInput) {
                        telephoneInput.value = prefill.telephone || '';
                    }
                    if (commentaireField) {
                        commentaireField.value = prefill.commentaire_client || '';
                    }
                    if (notesField) {
                        notesField.value = prefill.notes_internes || '';
                    }
                    if (numeroDevisInput) {
                        numeroDevisInput.value = prefill.numero_devis || '';
                    }
                    if (remiseLabel && typeof prefill.remise_label !== 'undefined' && prefill.remise_label !== '') {
                        remiseLabel.value = prefill.remise_label;
                    }
                    if (remiseAmount && typeof prefill.remise_montant !== 'undefined' && prefill.remise_montant !== '') {
                        remiseAmount.value = prefill.remise_montant;
                    }
                    if (plusLabel && typeof prefill.plus_label !== 'undefined' && prefill.plus_label !== '') {
                        plusLabel.value = prefill.plus_label;
                    }
                    if (plusAmount && typeof prefill.plus_montant !== 'undefined' && prefill.plus_montant !== '') {
                        plusAmount.value = prefill.plus_montant;
                    }
                    if (participantsAdultsField) {
                        participantsAdultsField.value = prefill.participants && typeof prefill.participants.adultes !== 'undefined' ?
                            prefill.participants.adultes :
                            (prefill.adultes || 0);
                    }
                    if (participantsEnfantsField) {
                        participantsEnfantsField.value = prefill.participants && typeof prefill.participants.enfants !== 'undefined' ?
                            prefill.participants.enfants :
                            (prefill.enfants || 0);
                    }
                    if (participantsBebesField) {
                        participantsBebesField.value = prefill.participants && typeof prefill.participants.bebes !== 'undefined' ?
                            prefill.participants.bebes :
                            (prefill.bebes || 0);
                    }
                }

                if (!prefill) {
                    if (participantsAdultsField && adultField) {
                        participantsAdultsField.value = adultField.value || '';
                    }
                    if (participantsEnfantsField && childField) {
                        participantsEnfantsField.value = childField.value || '';
                    }
                    if (participantsBebesField && babyField) {
                        participantsBebesField.value = babyField.value || '';
                    }
                }

                refreshRemiseButton();
                refreshPlusButton();

                const initialTarifKey = prefill ? prefill.experience_tarif_type : '';
                if (currentType === 'experience') {
                    populateTarifOptions(initialItemId, initialTarifKey);
                    if (tarifSelect && initialTarifKey) {
                        tarifSelect.value = initialTarifKey;
                    }
                    const initialConfig = initialItemId && initialTarifKey ?
                        getTarifConfig(initialItemId, initialTarifKey) :
                        null;
                    refreshDynamicSections(initialConfig);
                    applyPrefillSelections();
                    updateCapacityLimits(null);
                } else {
                    if (customSection) {
                        customSection.style.display = 'none';
                    }
                    if (optionsSection) {
                        optionsSection.style.display = 'none';
                    }
                    toggleParticipantsSection(null);
                    prepareLogementConfig(initialItemId, {
                        range: pendingLogementRange,
                    });
                }

                let storedLines = null;
                if (prefill && prefill.lines_json) {
                    if (linesTextarea) {
                        linesTextarea.value = prefill.lines_json;
                    }
                    const parsedLines = parseJSONSafe(prefill.lines_json);
                    if (Array.isArray(parsedLines)) {
                        storedLines = parsedLines;
                    }
                }
                if ((!prefillQtyMap || Object.keys(prefillQtyMap).length === 0) && storedLines) {
                    prefillQtyMap = deriveQtyMapFromLines(storedLines);
                }

                if (storedLines && summaryBody && summaryTotal) {
                    renderStoredLinesSummary(storedLines, summaryBody, summaryTotal, prefill ? prefill.montant_total : 0);
                }

                if (totalInput && prefill && typeof prefill.montant_total !== 'undefined') {
                    totalInput.value = parseFloat(prefill.montant_total || 0).toFixed(2);
                }

                function updateQuote() {
                    if (!summaryBody || !summaryTotal) {
                        return;
                    }

                    const typeValue = typeSelect ? typeSelect.value : 'experience';
                    const counts = {
                        adultes: parseInt(adultField ? adultField.value : '0', 10) || 0,
                        enfants: parseInt(childField ? childField.value : '0', 10) || 0,
                        bebes: parseInt(babyField ? babyField.value : '0', 10) || 0,
                    };
                    if (typeValue !== 'location' && capacityWarning) {
                        capacityWarning.style.display = 'none';
                    }

                    if (typeValue === 'location') {
                        enforceCapacity(counts);
                        if (!logementQuote || typeof logementQuote.calculateQuote !== 'function') {
                            summaryBody.innerHTML = '<p class="pc-resa-field-hint">Le moteur logement n’est pas chargé.</p>';
                            summaryTotal.textContent = '—';
                            if (totalInput) totalInput.value = '';
                            if (linesTextarea) linesTextarea.value = '';
                            return;
                        }
                        const logementId = itemSelect ? itemSelect.value : '';
                        if (!logementId) {
                            summaryBody.innerHTML = '<p class="pc-resa-field-hint">Sélectionnez un logement.</p>';
                            summaryTotal.textContent = '—';
                            if (totalInput) totalInput.value = '';
                            if (linesTextarea) linesTextarea.value = '';
                            return;
                        }
                        const config = (logementId === currentLogementId && currentLogementConfig) ? currentLogementConfig : null;
                        if (!config) {
                            summaryBody.innerHTML = '<p class="pc-resa-field-hint">Chargement des données du logement…</p>';
                            summaryTotal.textContent = '—';
                            if (totalInput) totalInput.value = '';
                            if (linesTextarea) linesTextarea.value = '';
                            return;
                        }
                        const arrivalValue = arrivalInput ? arrivalInput.value : '';
                        const departValue = departInput ? departInput.value : '';
                        if (!arrivalValue || !departValue) {
                            summaryBody.innerHTML = '<p class="pc-resa-field-hint">Choisissez les dates du séjour.</p>';
                            summaryTotal.textContent = '—';
                            if (totalInput) totalInput.value = '';
                            if (linesTextarea) linesTextarea.value = '';
                            return;
                        }
                        const result = logementQuote.calculateQuote(config, {
                            date_arrivee: arrivalValue,
                            date_depart: departValue,
                            adults: counts.adultes,
                            children: counts.enfants,
                            infants: counts.bebes,
                        });
                        if (!result.success) {
                            summaryBody.innerHTML = `<p class="pc-resa-field-hint">${escapeHtml(result.message || 'Sélection invalide.')}</p>`;
                            summaryTotal.textContent = '—';
                            if (totalInput) totalInput.value = '';
                            if (linesTextarea) linesTextarea.value = '';
                            return;
                        }
                        applyQuoteToForm({
                            result,
                            linesTextarea,
                            totalInput,
                            summaryBody,
                            summaryTotal,
                            remiseLabel,
                            remiseAmount,
                            plusLabel,
                            plusAmount,
                        });
                        return;
                    }

                    const expId = itemSelect ? itemSelect.value : '';
                    const tarifKey = tarifSelect ? tarifSelect.value : '';

                    if (!expId || !tarifKey) {
                        summaryBody.innerHTML = '<p class="pc-resa-field-hint">Sélectionnez une expérience et un tarif.</p>';
                        summaryTotal.textContent = '—';
                        if (totalInput) totalInput.value = '';
                        if (linesTextarea) linesTextarea.value = '';
                        return;
                    }

                    const config = getTarifConfig(expId, tarifKey);
                    if (!config) {
                        summaryBody.innerHTML = '<p class="pc-resa-field-hint">Impossible de charger ce tarif (ACF).</p>';
                        summaryTotal.textContent = '—';
                        return;
                    }

                    const customQty = getCustomQtyValues();
                    const selectedOptions = getSelectedOptions();
                    const result = computeQuote(config, counts, {
                        customQty,
                        options: selectedOptions,
                    });
                    applyQuoteToForm({
                        result,
                        linesTextarea,
                        totalInput,
                        summaryBody,
                        summaryTotal,
                        remiseLabel,
                        remiseAmount,
                        plusLabel,
                        plusAmount,
                    });
                }

                if (typeSelect) {
                    typeSelect.addEventListener('change', function() {
                        const nextType = this.value || 'experience';
                        populateItemOptions(nextType);
                        setTypeLabel(nextType);
                        toggleTypeHints(nextType);
                        toggleTypeSections(nextType);
                        if (nextType === 'experience') {
                            const expId = itemSelect ? itemSelect.value : '';
                            populateTarifOptions(expId);
                            const cfg = expId && tarifSelect ? getTarifConfig(expId, tarifSelect.value) : null;
                            refreshDynamicSections(cfg);
                            currentLogementId = '';
                            currentLogementConfig = null;
                            setLogementAvailabilityMessage('');
                            initLogementCalendar(null);
                            updateCapacityLimits(null);
                        } else {
                            if (customSection) {
                                customSection.style.display = 'none';
                            }
                            if (optionsSection) {
                                optionsSection.style.display = 'none';
                            }
                            toggleParticipantsSection(null);
                            prepareLogementConfig(itemSelect ? itemSelect.value : '');
                        }
                        updateQuote();
                    });
                }

                if (itemSelect) {
                    itemSelect.addEventListener('change', function() {
                        const current = typeSelect ? typeSelect.value : 'experience';
                        if (current === 'experience') {
                            populateTarifOptions(this.value);
                            refreshDynamicSections(null);
                            updateCapacityLimits(null);
                        } else {
                            prepareLogementConfig(this.value);
                        }
                        console.log('[pc-devis] Sélection logement changée :', this.value || '(vide)');
                        if (current === 'experience') {
                            lastExperienceId = this.value || '';
                        } else {
                            lastLocationId = this.value || '';
                        }
                        updateQuote();
                    });
                }

                if (tarifSelect) {
                    tarifSelect.addEventListener('change', function() {
                        if (typeSelect && typeSelect.value !== 'experience') {
                            return;
                        }
                        const expId = itemSelect ? itemSelect.value : '';
                        const cfg = expId ? getTarifConfig(expId, this.value) : null;
                        refreshDynamicSections(cfg);
                        updateQuote();
                    });
                }

                counters.forEach((input) => {
                    input.addEventListener('input', updateQuote);
                });

                if (remiseLabel) {
                    remiseLabel.addEventListener('input', () => {
                        updateQuote();
                    });
                }
                if (remiseAmount) {
                    remiseAmount.addEventListener('input', () => {
                        refreshRemiseButton();
                        updateQuote();
                    });
                    refreshRemiseButton();
                }
                if (remiseClearBtn) {
                    remiseClearBtn.addEventListener('click', function() {
                        if (remiseLabel) {
                            remiseLabel.value = 'Remise exceptionnelle';
                        }
                        if (remiseAmount) {
                            remiseAmount.value = '';
                        }
                        refreshRemiseButton();
                        updateQuote();
                    });
                }

                [participantsAdultsField, participantsEnfantsField, participantsBebesField].forEach((field) => {
                    if (field) {
                        field.addEventListener('input', () => {
                            // pas de recalcul du devis
                        });
                    }
                });

                if (plusLabel) {
                    plusLabel.addEventListener('input', () => {
                        updateQuote();
                    });
                }
                if (plusAmount) {
                    plusAmount.addEventListener('input', () => {
                        refreshPlusButton();
                        updateQuote();
                    });
                    refreshPlusButton();
                }
                if (plusClearBtn) {
                    plusClearBtn.addEventListener('click', function() {
                        if (plusLabel) {
                            plusLabel.value = 'Plus-value';
                        }
                        if (plusAmount) {
                            plusAmount.value = '';
                        }
                        refreshPlusButton();
                        updateQuote();
                    });
                }

                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (!submitBtn) {
                        return;
                    }
                    handleManualCreateSubmit(form, submitBtn);
                });

                if (!storedLines) {
                    updateQuote();
                }

                if (sendBtn) {
                    sendBtn.addEventListener('click', function() {
                        if (typeFluxSelect) {
                            typeFluxSelect.value = 'devis';
                        }
                        handleManualCreateSubmit(form, sendBtn);
                    });
                }

                return {
                    form,
                    submitBtn,
                    sendBtn,
                    typeFluxSelect,
                };
            }

            const openManualCreateModal = (prefillData = null, options = {}) => {
                if (!createTemplate) {
                    return null;
                }

                openResaModal(createTemplate.innerHTML);

                const modalContent = document.getElementById('pc-resa-modal-content');
                if (!modalContent) {
                    return null;
                }

                const refs = initManualCreateForm(modalContent, prefillData, options);

                const cancelBtn = modalContent.querySelector('.pc-resa-create-cancel');
                if (cancelBtn) {
                    cancelBtn.addEventListener('click', function() {
                        closeResaModal();
                    });
                }

                return refs;
            };

            function openResaModal(html) {
                const modal = document.getElementById('pc-resa-modal');
                const modalContent = document.getElementById('pc-resa-modal-content');

                if (modalContent) {
                    modalContent.innerHTML = html;
                }
                if (modal) {
                    modal.style.display = 'block';
                }
            }

            function closeResaModal() {
                const modal = document.getElementById('pc-resa-modal');
                const modalContent = document.getElementById('pc-resa-modal-content');

                if (modalContent) {
                    modalContent.innerHTML = '';
                }
                if (modal) {
                    modal.style.display = 'none';
                }
            }

            const modalCloseBtn = document.getElementById('pc-resa-modal-close-btn');
            const modalCloseBackdrop = document.getElementById('pc-resa-modal-close');
            if (modalCloseBtn) {
                modalCloseBtn.addEventListener('click', closeResaModal);
            }
            if (modalCloseBackdrop) {
                modalCloseBackdrop.addEventListener('click', closeResaModal);
            }

            // Boutons "Voir la fiche"
            document.querySelectorAll('.pc-resa-view-link').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();

                    const id = this.getAttribute('data-resa-id');
                    const detailRow = document.querySelector(
                        '.pc-resa-dashboard-row-detail[data-resa-id="' + id + '"]'
                    );

                    if (!detailRow) return;

                    const card = detailRow.querySelector('.pc-resa-card');
                    if (!card) return;

                    openResaModal(card.innerHTML);
                    // Ré-attache les handlers (boutons Modifier / Renvoyer) présents dans le HTML injecté
                    if (typeof attachQuoteButtons === 'function') {
                        attachQuoteButtons();
                    }
                });
            });

            // Ouverture automatique du popup si on arrive depuis le calendrier
            if (typeof window !== "undefined" && window.sessionStorage) {
                const key = "pc_resa_from_calendar";
                const raw = window.sessionStorage.getItem(key);

                if (raw) {
                    try {
                        const parsed = JSON.parse(raw);
                        window.sessionStorage.removeItem(key);

                        if (parsed && typeof parsed === "object") {
                            const prefill = {
                                type: "location",
                                item_id: parsed.logementId || "",
                                date_arrivee: parsed.start || "",
                                date_depart: parsed.end || "",
                            };

                            openManualCreateModal(prefill, {
                                context: "from_calendar",
                            });
                        }
                    } catch (error) {
                        // eslint-disable-next-line no-console
                        console.error(
                            "[pc-reservations] erreur prefill depuis calendrier",
                            error
                        );
                        window.sessionStorage.removeItem(key);
                    }
                }
            }

            const attachQuoteButtons = () => {
                document.querySelectorAll('.pc-resa-edit-quote').forEach((btn) => {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const rawData = this.getAttribute('data-prefill');
                        const payload = parseJSONSafe(rawData);
                        if (!payload) {
                            alert('Impossible de charger les données du devis.');
                            return;
                        }
                        openManualCreateModal(payload, {
                            context: 'edit'
                        });
                    });
                });

                document.querySelectorAll('.pc-resa-resend-quote').forEach((btn) => {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const reservationId = this.getAttribute('data-resa-id') || '';
                        console.log('[pc-reservations] Renvoyer devis TODO', reservationId);
                    });
                });
            };

            attachQuoteButtons();
        });
    </script>

    <script>
        document.addEventListener("click", function(event) {

            /* --- OUVERTURE DU POPUP --- */
            const cancelBtn = event.target.closest(".pc-resa-action-cancel-booking");
            if (cancelBtn) {
                event.preventDefault();
                const id = cancelBtn.dataset.reservationId;

                const popup = document.getElementById("pc-cancel-reservation-popup");
                popup.dataset.resaId = id;
                popup.hidden = false;
                return;
            }

            /* --- FERMETURE DU POPUP --- */
            if (event.target.matches("[data-pc-popup-close]")) {
                document.getElementById("pc-cancel-reservation-popup").hidden = true;
                return;
            }

            /* --- CONFIRMATION ANNULATION --- */
            if (event.target.matches("[data-pc-popup-confirm]")) {

                const popup = document.getElementById("pc-cancel-reservation-popup");
                const id = popup.dataset.resaId;

                const body = new URLSearchParams();
                body.append("action", "pc_cancel_reservation");
                body.append("nonce", pcResaManualNonce);
                body.append("reservation_id", id);

                fetch(pcResaAjaxUrl, {
                        method: "POST",
                        credentials: "same-origin",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded"
                        },
                        body: body.toString(),
                    })
                    .then(r => r.json())
                    .then(json => {

                        if (!json || !json.success) {
                            console.error(json && json.data && json.data.message ?
                                json.data.message :
                                "Erreur lors de l’annulation.");
                            return;
                        }

                        // Ferme le popup
                        popup.hidden = true;

                        // Refresh calendrier
                        document.dispatchEvent(new CustomEvent("pc:calendar:refresh"));

                        // Recharger la page pour mettre à jour la fiche (statut = annulée)
                        window.location.reload();

                    })
                    .catch(() => {
                        console.error("Erreur réseau lors de l'annulation.");
                    });
            }
            /* --- CONFIRMATION RÉSERVATION --- */
            const confirmBtn = event.target.closest(".pc-resa-action-confirm-booking");
            if (confirmBtn) {
                event.preventDefault();
                const id = confirmBtn.dataset.reservationId;

                if (!confirm('Confirmer cette réservation ? Elle apparaîtra dans le calendrier.')) {
                    return;
                }

                const originalText = confirmBtn.textContent;
                confirmBtn.textContent = '...';
                confirmBtn.disabled = true;

                const body = new URLSearchParams();
                body.append("action", "pc_confirm_reservation");
                body.append("nonce", pcResaManualNonce);
                body.append("reservation_id", id);

                fetch(pcResaAjaxUrl, {
                        method: "POST",
                        credentials: "same-origin",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded"
                        },
                        body: body.toString(),
                    })
                    .then(r => r.json())
                    .then(json => {
                        if (json && json.success) {
                            // Rafraîchir calendrier si présent
                            document.dispatchEvent(new CustomEvent("pc:calendar:refresh"));
                            // Recharger la page pour mettre à jour les statuts et boutons
                            window.location.reload();
                        } else {
                            alert(json.data && json.data.message ? json.data.message : 'Erreur.');
                            confirmBtn.textContent = originalText;
                            confirmBtn.disabled = false;
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('Erreur réseau.');
                        confirmBtn.textContent = originalText;
                        confirmBtn.disabled = false;
                    });
            }
        });
    </script>
    <!-- Popup d’annulation Prestige Caraïbes -->
    <div id="pc-cancel-reservation-popup" class="pc-popup-overlay" hidden>
        <div class="pc-popup-box">
            <h3 class="pc-popup-title">Annuler cette réservation ?</h3>

            <p class="pc-popup-text">
                Êtes-vous sûr de vouloir annuler cette réservation ?<br>
                <small>Si votre client a déjà fait des règlements, pensez au remboursement suivant vos conditions générales.</small>
            </p>

            <div class="pc-popup-actions">
                <button class="pc-btn pc-btn--primary" data-pc-popup-confirm>
                    J’annule cette réservation
                </button>
                <button class="pc-btn pc-btn--line" data-pc-popup-close>
                    Revenir
                </button>
            </div>
        </div>
    </div>
<?php

    return ob_get_clean();
}
add_shortcode('pc_resa_dashboard', 'pc_resa_dashboard_shortcode');
