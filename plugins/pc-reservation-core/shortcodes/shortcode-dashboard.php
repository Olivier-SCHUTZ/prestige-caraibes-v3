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
        // Statuts réservation
        'brouillon'             => 'Brouillon',
        'en_attente_traitement' => 'En attente de traitement',
        'devis_envoye'          => 'Devis envoyé',
        'en_attente_validation' => 'En attente de validation',
        'en_attente_paiement'   => 'En attente de paiement',
        'reservee'              => 'Réservée',
        'annule'                => 'Annulé',
        'annulee'               => 'Annulée',

        // Compat anciens codes
        'en_attente'            => 'En attente de paiement',
        'non_demande'           => 'Non payé',

        // Statuts paiement global
        'non_paye'              => 'Non payé',
        'partiellement_paye'    => 'Partiellement payé',
        'paye'                  => 'Payé',
        'sur_devis'             => 'Sur devis',
        'sur_place'             => 'À régler sur place',

        // Statuts lignes de paiement
        'acompte_paye'          => 'Acompte payé',
        'solde_paye'            => 'Solde payé',
        'en_retard'             => 'En retard',
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
    ];

    return $payload;
}

/**
 * Shortcode [pc_resa_dashboard]
 */
function pc_resa_dashboard_shortcode($atts)
{
    if (! class_exists('PCR_Reservation')) {
        return '<p>Module réservation non disponible.</p>';
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

                        foreach ($lines_raw as $ln) {
                            $type_ligne = isset($ln['type_ligne']) ? (string) $ln['type_ligne'] : 'personnalise';
                            $entry = [
                                'type'        => $type_ligne,
                                'price'       => isset($ln['tarif_valeur']) ? (float) $ln['tarif_valeur'] : 0,
                                'label'       => '',
                                'observation' => trim((string) ($ln['tarif_observation'] ?? '')),
                                'enable_qty'  => !empty($ln['tarif_enable_qty']),
                                'default_qty' => isset($ln['tarif_qty_default']) ? (int) $ln['tarif_qty_default'] : 0,
                            ];

                            if ($type_ligne === 'adulte') {
                                $entry['label'] = __('Adulte', 'pc');
                            } elseif ($type_ligne === 'enfant') {
                                $precision = trim((string) ($ln['precision_age_enfant'] ?? ''));
                                $entry['label'] = $precision ? sprintf(__('Enfant (%s)', 'pc'), $precision) : __('Enfant', 'pc');
                            } elseif ($type_ligne === 'bebe') {
                                $precision = trim((string) ($ln['precision_age_bebe'] ?? ''));
                                $entry['label'] = $precision ? sprintf(__('Bébé (%s)', 'pc'), $precision) : __('Bébé', 'pc');
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

                        $tarif_options[] = [
                            'key'        => $key,
                            'label'      => $label,
                            'code'       => $code,
                            'lines'      => $lines,
                            'fixed_fees' => $fixed_fees,
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
                                            <h4>Devis &amp; politique</h4>

                                            <p class="pc-resa-hint">
                                                Le détail du devis (lines_json) sera intégré ici dans une prochaine étape.
                                            </p>

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
                                            <?php else : ?>
                                                <p>
                                                    <em>Pas de politique spécifique pour les expériences.</em>
                                                </p>
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
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($payments as $pay) : ?>
                                                            <?php
                                                            $pay_amount = isset($pay->montant) ? (float) $pay->montant : 0;
                                                            $due_label  = '';
                                                            if (! empty($pay->date_echeance)) {
                                                                $due_label = date_i18n('d/m/Y', strtotime($pay->date_echeance));
                                                            }

                                                            $pay_status_raw   = isset($pay->statut) ? $pay->statut : '';
                                                            $pay_status_label = $pay_status_raw ? pc_resa_format_status_label($pay_status_raw) : '';
                                                            ?>
                                                            <tr>
                                                                <td><?php echo esc_html($pay->type_paiement); ?></td>
                                                                <td><?php echo esc_html(number_format($pay_amount, 2, ',', ' ')); ?> €</td>
                                                                <td><?php echo esc_html($due_label); ?></td>
                                                                <td><?php echo esc_html($pay_status_label); ?></td>
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
                                                    <p>
                                                        <em>Les informations de caution seront ajoutées ici lors de la prochaine
                                                            étape d’intégration Stripe / ACF.</em>
                                                    </p>
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
                                                echo ! empty($resa->commentaire_client)
                                                    ? nl2br(esc_html($resa->commentaire_client))
                                                    : '<em>Aucun message renseigné</em>';
                                                ?>
                                            </p>
                                        </div>

                                        <div class="pc-resa-extra-col">
                                            <h3>Notes internes</h3>
                                            <p>
                                                <?php
                                                echo ! empty($resa->notes_internes)
                                                    ? nl2br(esc_html($resa->notes_internes))
                                                    : '<em>Aucune note interne</em>';
                                                ?>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="pc-resa-card__footer">
                                        <?php if ($resa->type === 'experience') :
                                            $prefill_payload = pc_resa_build_prefill_payload($resa);
                                            $prefill_json    = $prefill_payload ? wp_json_encode($prefill_payload) : '';
                                            $prefill_attr    = esc_attr($prefill_json);
                                        ?>
                                            <button type="button"
                                                class="pc-resa-btn pc-resa-btn--secondary pc-resa-edit-quote"
                                                data-prefill="<?php echo $prefill_attr; ?>">
                                                Modifier le devis
                                            </button>
                                            <button type="button"
                                                class="pc-resa-btn pc-resa-btn--primary pc-resa-resend-quote"
                                                data-prefill="<?php echo $prefill_attr; ?>">
                                                Renvoyer le devis
                                            </button>
                                        <?php else : ?>
                                            <em>Actions rapides disponibles prochainement.</em>
                                        <?php endif; ?>
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
                Pour l'instant, la création manuelle est disponible pour les expériences uniquement.
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
                                <option value="location">Logement (bientôt)</option>
                            </select>
                        </label>

                        <label class="pc-resa-field">
                            <span class="pc-resa-field-label">Flux</span>
                            <select name="type_flux">
                                <option value="devis">Devis</option>
                                <option value="reservation">Réservation directe</option>
                            </select>
                        </label>

                        <label class="pc-resa-field">
                            <span class="pc-resa-field-label">Mode</span>
                            <select name="mode_reservation">
                                <option value="demande">Demande</option>
                                <option value="directe">Directe</option>
                            </select>
                        </label>

                        <label class="pc-resa-field">
                            <span class="pc-resa-field-label">Expérience</span>
                            <select name="item_id">
                                <option value="">Sélectionnez une expérience</option>
                                <?php foreach ($manual_experience_options as $pid => $title) : ?>
                                    <option value="<?php echo esc_attr($pid); ?>">
                                        <?php echo esc_html($title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="pc-resa-field-hint">
                                Les logements seront ajoutés après validation du moteur logement.
                            </span>
                        </label>

                        <label class="pc-resa-field">
                            <span class="pc-resa-field-label">Type de tarif</span>
                            <select name="experience_tarif_type" data-tarif-select disabled required>
                                <option value="">Sélectionnez une expérience d'abord</option>
                            </select>
                            <span class="pc-resa-field-hint">
                                Choisissez une expérience pour afficher les options tarifaires disponibles.
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

                    <label class="pc-resa-field">
                        <span class="pc-resa-field-label">Date de l'expérience</span>
                        <input type="date" name="date_experience">
                    </label>

                    <div class="pc-resa-create-grid pc-resa-create-grid--3">
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

                    <label class="pc-resa-field">
                        <span class="pc-resa-field-label">Montant total / devis (€)</span>
                        <input type="number" min="0" step="0.01" name="montant_total" readonly>
                        <span class="pc-resa-field-hint">Calculé automatiquement d'après le tarif choisi.</span>
                    </label>
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

        .pc-resa-create-summary-body li {
            display: flex;
            justify-content: space-between;
            padding: 0.35rem 0;
            border-bottom: 1px solid #edf2f7;
        }

        .pc-resa-create-summary-body li:last-child {
            border-bottom: none;
        }

        .pc-resa-create-summary-body li.note {
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

        .pc-resa-section-caution {
            margin-top: 1rem;
            padding-top: 0.75rem;
            border-top: 1px dashed #cbd5e1;
            font-size: 0.85rem;
        }

        .pc-resa-hint {
            font-size: 0.82rem;
            font-style: italic;
            color: #64748b;
        }

        @media (max-width: 768px) {
            .pc-resa-header-grid {
                grid-template-columns: 1fr;
            }
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
        const pcResaAjaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
        const pcResaManualNonce = '<?php echo esc_attr($manual_nonce); ?>';

        document.addEventListener('DOMContentLoaded', function() {

            const experiencePricingData = window.pcResaExperienceTarifs || {};
            const currencyFormatter = new Intl.NumberFormat('fr-FR', {
                style: 'currency',
                currency: 'EUR',
            });
            const createTemplate = document.getElementById('pc-resa-create-template');

            const formatPrice = (amount) => currencyFormatter.format(amount || 0);

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

            const renderStoredLinesSummary = (lines, summaryBody, summaryTotal, totalValue) => {
                if (!summaryBody || !Array.isArray(lines) || lines.length === 0) {
                    return;
                }
                let html = '<ul>';
                lines.forEach((line) => {
                    const label = line.label || '';
                    const price = line.price || '';
                    html += `<li><span>${label}</span><span>${price}</span></li>`;
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

            const computeQuote = (config, counts) => {
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

                (config.lines || []).forEach((line) => {
                    const type = line.type || 'personnalise';
                    const unit = parseFloat(line.price) || 0;
                    let qty = 1;

                    if (type === 'adulte') qty = counts.adultes;
                    else if (type === 'enfant') qty = counts.enfants;
                    else if (type === 'bebe') qty = counts.bebes;
                    else if (line.enable_qty) qty = line.default_qty ? parseInt(line.default_qty, 10) || 0 : 1;

                    if ((type === 'adulte' || type === 'enfant' || type === 'bebe') && qty <= 0) {
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
                } = args;

                let summaryHtml = result.html;
                const linesJson = [...result.lines];
                const remiseValue = parseFloat(remiseAmount && remiseAmount.value ? remiseAmount.value : 0) || 0;

                if (remiseValue > 0) {
                    const label = remiseLabel && remiseLabel.value ? remiseLabel.value : 'Remise exceptionnelle';
                    const signed = -Math.abs(remiseValue);
                    const display = result.isSurDevis ? result.pendingLabel : formatPrice(signed);
                    summaryHtml = summaryHtml.replace('</ul>', `<li><span>${label}</span><span>${display}</span></li></ul>`);
                    linesJson.push({
                        label,
                        price: display
                    });
                    if (!result.isSurDevis) {
                        result.total += signed;
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

                const typeValue = formData.get('type');
                if (typeValue !== 'experience') {
                    alert('La création manuelle est disponible uniquement pour les expériences.');
                    return;
                }

                if (!formData.get('item_id')) {
                    alert('Sélectionnez une expérience.');
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
                        const successMsg = result.data && result.data.message
                            ? result.data.message
                            : 'Réservation enregistrée';
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
                const experienceSelect = form.querySelector('select[name="item_id"]');
                const tarifSelect = form.querySelector('select[name="experience_tarif_type"]');
                const linesTextarea = form.querySelector('textarea[name="lines_json"]');
                const totalInput = form.querySelector('input[name="montant_total"]');
                const summaryBody = container.querySelector('[data-quote-summary]');
                const summaryTotal = container.querySelector('[data-quote-total]');
                const remiseLabel = form.querySelector('input[name="remise_label"]');
                const remiseAmount = form.querySelector('input[name="remise_montant"]');
                const counters = form.querySelectorAll('[data-quote-counter]');
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

                const prefill = prefillData || null;
                const opts = options || {};

                // Applique les valeurs pré-remplies aux selects avant l'initialisation
                // pour que populateTarifOptions et updateQuote utilisent les bonnes valeurs.
                if (prefill) {
                    if (typeof prefill.type !== 'undefined' && prefill.type && form) {
                        const tmpType = form.querySelector('select[name="type"]');
                        if (tmpType) {
                            tmpType.value = prefill.type;
                        }
                    }
                    if (typeof prefill.item_id !== 'undefined' && prefill.item_id && form) {
                        const tmpItem = form.querySelector('select[name="item_id"]');
                        if (tmpItem) {
                            tmpItem.value = String(prefill.item_id);
                        }
                    }
                }

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
                }

                const initialExperienceValue = experienceSelect ? experienceSelect.value : '';
                const initialTarifKey = prefill ? prefill.experience_tarif_type : '';
                populateTarifOptions(initialExperienceValue, initialTarifKey);
                // si un tarif pré-rempli existe, appliquer la valeur au select tarif
                if (tarifSelect && initialTarifKey) {
                    tarifSelect.value = initialTarifKey;
                }

                // Assurer l'état du bouton submit selon le type actuel
                if (typeSelect && submitBtn) {
                    submitBtn.disabled = typeSelect.value !== 'experience';
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

                if (storedLines && summaryBody && summaryTotal) {
                    renderStoredLinesSummary(storedLines, summaryBody, summaryTotal, prefill ? prefill.montant_total : 0);
                }

                if (totalInput && prefill && typeof prefill.montant_total !== 'undefined') {
                    totalInput.value = parseFloat(prefill.montant_total || 0).toFixed(2);
                }

                const updateQuote = () => {
                    if (!summaryBody || !summaryTotal) {
                        return;
                    }

                    const typeValue = typeSelect ? typeSelect.value : 'experience';
                    if (typeValue !== 'experience') {
                        summaryBody.innerHTML = '<p class="pc-resa-field-hint">Le calcul de devis est disponible uniquement pour les expériences.</p>';
                        summaryTotal.textContent = '—';
                        if (totalInput) totalInput.value = '';
                        if (linesTextarea) linesTextarea.value = '';
                        return;
                    }

                    const expId = experienceSelect ? experienceSelect.value : '';
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

                    const counts = {
                        adultes: parseInt(adultField ? adultField.value : '0', 10) || 0,
                        enfants: parseInt(childField ? childField.value : '0', 10) || 0,
                        bebes: parseInt(babyField ? babyField.value : '0', 10) || 0,
                    };

                    const result = computeQuote(config, counts);
                    applyQuoteToForm({
                        result,
                        linesTextarea,
                        totalInput,
                        summaryBody,
                        summaryTotal,
                        remiseLabel,
                        remiseAmount,
                    });
                };

                if (typeSelect) {
                    typeSelect.addEventListener('change', function() {
                        if (submitBtn) {
                            submitBtn.disabled = this.value !== 'experience';
                        }
                        updateQuote();
                    });
                    if (submitBtn) {
                        submitBtn.disabled = typeSelect.value !== 'experience';
                    }
                }

                if (experienceSelect) {
                    experienceSelect.addEventListener('change', function() {
                        populateTarifOptions(this.value);
                        updateQuote();
                    });
                }

                if (tarifSelect) {
                    tarifSelect.addEventListener('change', updateQuote);
                }

                counters.forEach((input) => {
                    input.addEventListener('input', updateQuote);
                });

                if (remiseLabel) {
                    remiseLabel.addEventListener('input', updateQuote);
                }
                if (remiseAmount) {
                    remiseAmount.addEventListener('input', updateQuote);
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

            // Bouton "Créer une réservation"
            const createBtn = document.querySelector('.pc-resa-create-btn');

            if (createBtn && createTemplate) {
                createBtn.addEventListener('click', function() {
                    openManualCreateModal();
                });
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
                        const rawData = this.getAttribute('data-prefill');
                        const payload = parseJSONSafe(rawData);
                        if (!payload) {
                            alert('Impossible de charger les données du devis.');
                            return;
                        }
                        const refs = openManualCreateModal(payload, {
                            context: 'resend',
                            forceTypeFlux: 'devis'
                        });
                        if (!refs) {
                            return;
                        }
                        const {
                            form,
                            submitBtn,
                            sendBtn,
                            typeFluxSelect
                        } = refs;
                        if (typeFluxSelect) {
                            typeFluxSelect.value = 'devis';
                        }
                        setTimeout(() => {
                            if (confirm('Envoyer ce devis à nouveau ?')) {
                                handleManualCreateSubmit(form, sendBtn || submitBtn);
                            }
                        }, 400);
                    });
                });
            };

            attachQuoteButtons();
        });
    </script>
<?php

    return ob_get_clean();
}
add_shortcode('pc_resa_dashboard', 'pc_resa_dashboard_shortcode');
