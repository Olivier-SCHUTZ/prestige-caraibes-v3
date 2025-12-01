<?php
if (! defined('ABSPATH')) {
    exit;
}
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
                    <option value="">Tous les logements</option> <?php foreach ($logement_options as $pid => $title) : ?>
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

        <?php
        // On affiche ce bloc seulement si un logement spécifique est sélectionné et que la classe d'export existe
        if ($item_id > 0 && array_key_exists($item_id, $logement_options) && class_exists('PCR_Ical_Export')) :
            $link_proprio = PCR_Ical_Export::get_export_url($item_id, 'simple');
            $link_ota     = PCR_Ical_Export::get_export_url($item_id, 'full');
        ?>
            <div class="pc-resa-ical-links" style="background:#f0f9ff; border:1px solid #bae6fd; padding:20px; border-radius:8px; margin-bottom:24px; box-shadow:0 2px 6px rgba(0,0,0,0.03);">
                <h4 style="color:#0369a1; font-size:1.1rem; margin:0 0 8px 0; display:flex; align-items:center; gap:8px;">
                    <span style="font-size:1.4em;">🔗</span> Synchronisation iCal : <?php echo esc_html($logement_options[$item_id]); ?>
                </h4>
                <p style="font-size:0.9rem; color:#64748b; margin-bottom:16px; margin-top:0;">
                    Copiez ces URL et collez-les dans les paramètres de synchronisation de vos calendriers externes (Airbnb, Booking, etc.).
                </p>

                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:20px;">

                    <div style="background:#fff; padding:12px; border:1px solid #e2e8f0; border-radius:6px;">
                        <label style="display:block; font-size:0.85rem; font-weight:700; color:#0369a1; margin-bottom:6px;">
                            📅 Lien Propriétaire (Mode Simple)
                        </label>
                        <p style="font-size:0.75rem; color:#64748b; margin:0 0 8px 0;">
                            Contient : <strong>Vos réservations internes + Blocages manuels</strong>.<br>
                            <em>Donnez ce lien à votre propriétaire.</em>
                        </p>
                        <div style="display:flex; gap:6px;">
                            <input type="text" value="<?php echo esc_url($link_proprio); ?>" readonly onclick="this.select()"
                                style="flex:1; padding:8px 12px; border:1px solid #cbd5e1; border-radius:4px; font-size:0.85rem; color:#334155; background:#f8fafc; font-family:monospace;">
                            <button type="button" class="pc-btn pc-btn--ghost"
                                onclick="navigator.clipboard.writeText('<?php echo esc_js($link_proprio); ?>'); alert('Lien copié !');"
                                style="padding:0 16px; font-size:0.85rem; border-color:#cbd5f5;">Copier</button>
                        </div>
                    </div>

                    <div style="background:#fff; padding:12px; border:1px solid #e2e8f0; border-radius:6px;">
                        <label style="display:block; font-size:0.85rem; font-weight:700; color:#be123c; margin-bottom:6px;">
                            🌍 Lien Airbnb / Booking (Mode Full)
                        </label>
                        <p style="font-size:0.75rem; color:#64748b; margin:0 0 8px 0;">
                            Contient : <strong>TOUT</strong> (Internes + Manuels + Imports externes).<br>
                            <em>Collez ce lien dans Airbnb/Booking pour tout bloquer.</em>
                        </p>
                        <div style="display:flex; gap:6px;">
                            <input type="text" value="<?php echo esc_url($link_ota); ?>" readonly onclick="this.select()"
                                style="flex:1; padding:8px 12px; border:1px solid #cbd5e1; border-radius:4px; font-size:0.85rem; color:#334155; background:#f8fafc; font-family:monospace;">
                            <button type="button" class="pc-btn pc-btn--ghost"
                                onclick="navigator.clipboard.writeText('<?php echo esc_js($link_ota); ?>'); alert('Lien copié !');"
                                style="padding:0 16px; font-size:0.85rem; border-color:#cbd5f5;">Copier</button>
                        </div>
                    </div>

                </div>
            </div>
        <?php endif; ?>
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

                        // On garde juste les attributs de données pour le JS
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
                                                class="pc-btn pc-btn--primary pc-resa-action pc-resa-edit-quote"
                                                data-action="edit_quote"
                                                data-prefill="<?php echo esc_attr($prefill_json); ?>"
                                                <?php echo $reservation_data_attrs; ?>>
                                                Modifier la réservation
                                            </button>

                                            <button type="button"
                                                class="pc-btn pc-btn--secondary pc-resa-action pc-resa-action-cancel-booking"
                                                data-action="cancel_booking"
                                                <?php echo $reservation_data_attrs; ?>>
                                                Annuler la réservation
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
                                                            class="pc-resa-actions-menu__link pc-resa-action pc-resa-action-send-quote"
                                                            role="menuitem"
                                                            data-action="send_quote"
                                                            data-prefill="<?php echo esc_attr($prefill_json); ?>"
                                                            <?php echo $reservation_data_attrs; ?>>
                                                            Envoyer le devis
                                                        </button>
                                                    </li>

                                                    <li>
                                                        <button type="button"
                                                            class="pc-resa-actions-menu__link pc-resa-action pc-resa-action-send-payment-link"
                                                            role="menuitem"
                                                            data-action="send_payment_link"
                                                            <?php echo $reservation_data_attrs; ?>>
                                                            Envoyer un lien de paiement
                                                        </button>
                                                    </li>

                                                    <li>
                                                        <button type="button"
                                                            class="pc-resa-actions-menu__link pc-resa-action pc-resa-action-add-message"
                                                            role="menuitem"
                                                            data-action="add_message"
                                                            <?php echo $reservation_data_attrs; ?>>
                                                            Envoyer un message
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
                                                                                class="pc-resa-payment-action pc-resa-payment-generate-link"
                                                                                style="color:#4f46e5; font-weight:600; margin-right:10px;"
                                                                                data-payment-id="<?php echo esc_attr($pay_id); ?>">
                                                                                🔗 Générer lien
                                                                            </button>

                                                                            <button type="button"
                                                                                class="pc-resa-payment-action pc-resa-payment-mark-paid"
                                                                                style="font-size:0.8em; color:#64748b;"
                                                                                data-action="mark_paid"
                                                                                data-payment-id="<?php echo esc_attr($pay_id); ?>">
                                                                                (Marquer payé manuel)
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
                                                    <h5>Caution (Empreinte Bancaire)</h5>
                                                    <?php
                                                    // On lit les données enregistrées en base (prioritaires)
                                                    $c_montant = (float) ($resa->caution_montant ?? 0);
                                                    $c_mode    = $resa->caution_mode ?? 'aucune';
                                                    $c_statut  = $resa->caution_statut ?? 'non_demande';

                                                    // Mapping labels statut
                                                    $status_labels = [
                                                        'non_demande' => 'Non demandée',
                                                        'demande_envoyee' => 'Demande envoyée',
                                                        'empreinte_validee' => 'Empreinte validée (Bloqué)',
                                                        'liberee' => 'Libérée',
                                                        'encaissee' => 'Encaissée'
                                                    ];
                                                    $statut_label = $status_labels[$c_statut] ?? $c_statut;
                                                    ?>

                                                    <?php if ($c_mode === 'empreinte' && $c_montant > 0) : ?>
                                                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                                            <div>
                                                                <span style="font-size:1.1em; font-weight:bold;"><?php echo number_format($c_montant, 2, ',', ' '); ?> €</span>
                                                                <br>
                                                                <span class="pc-resa-badge" style="font-size:0.75em; margin-top:4px; display:inline-block; background:#f3f4f6; color:#4b5563;">
                                                                    Statut : <?php echo esc_html($statut_label); ?>
                                                                </span>
                                                            </div>

                                                            <div>
                                                                <?php if ($c_statut === 'non_demande' || $c_statut === 'demande_envoyee') : ?>
                                                                    <button type="button"
                                                                        class="pc-resa-payment-action pc-resa-caution-generate"
                                                                        data-resa-id="<?php echo esc_attr($resa->id); ?>"
                                                                        style="color:#4f46e5; font-weight:600; cursor:pointer; border:none; background:none;">
                                                                        🔗 Lien caution
                                                                    </button>
                                                                <?php elseif ($c_statut === 'empreinte_validee') : ?>
                                                                    <div style="display:flex; gap:5px; align-items:center; flex-wrap:wrap;">
                                                                        <span style="color:#16a34a; font-weight:600; margin-right:5px;">✅ Validée</span>

                                                                        <button type="button" class="pc-resa-caution-rotate pc-btn--ghost"
                                                                            style="font-size:0.75rem; padding:4px 8px; border:1px solid #2563eb; color:#2563eb;"
                                                                            data-id="<?php echo $resa->id; ?>"
                                                                            data-ref="<?php echo esc_attr($resa->caution_reference); ?>"
                                                                            title="Prolonger de 7 jours (recrée une empreinte)">
                                                                            🔄 Renouveler
                                                                        </button>

                                                                        <button type="button" class="pc-resa-caution-action pc-btn--ghost"
                                                                            style="font-size:0.75rem; padding:4px 8px; border:1px solid #16a34a; color:#16a34a;"
                                                                            data-action="release"
                                                                            data-id="<?php echo $resa->id; ?>"
                                                                            data-ref="<?php echo esc_attr($resa->caution_reference); ?>">
                                                                            Libérer
                                                                        </button>

                                                                        <button type="button" class="pc-resa-caution-action pc-btn--ghost"
                                                                            style="font-size:0.75rem; padding:4px 8px; border:1px solid #dc2626; color:#dc2626;"
                                                                            data-action="capture"
                                                                            data-id="<?php echo $resa->id; ?>"
                                                                            data-ref="<?php echo esc_attr($resa->caution_reference); ?>"
                                                                            data-max="<?php echo esc_attr($c_montant); ?>">
                                                                            Encaisser
                                                                        </button>
                                                                    </div>
                                                                <?php elseif ($c_statut === 'liberee') : ?>
                                                                    <span style="color:#64748b;">🔓 Libérée</span>
                                                                <?php elseif ($c_statut === 'encaissee') : ?>
                                                                    <span style="color:#dc2626; font-weight:600;">💰 Encaissée</span>
                                                                    <?php
                                                                    // Recherche de la note d'encaissement dans l'historique
                                                                    if (!empty($resa->notes_internes)) {
                                                                        // On cherche la ligne qui commence par une date et contient "Encaissement Caution"
                                                                        if (preg_match('/^\d{2}\/\d{2}\/\d{4} - Encaissement Caution.*/m', $resa->notes_internes, $matches)) {
                                                                            echo '<div style="margin-top:4px; font-size:0.85em; color:#ef4444; background:#fef2f2; padding:4px 8px; border-radius:4px; border:1px solid #fee2e2;">' . esc_html($matches[0]) . '</div>';
                                                                        }
                                                                    }
                                                                    ?>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>

                                                        <?php if ($c_statut === 'empreinte_validee') : ?>
                                                            <div style="background:#f0f9ff; border-left:4px solid #3b82f6; padding:8px 12px; border-radius:4px; font-size:0.8rem; color:#1e3a8a; margin-bottom:12px;">
                                                                <strong>🤖 Gestion Automatique Active</strong>
                                                                <ul style="margin:4px 0 0 16px; padding:0; list-style-type:disc; color:#334155;">
                                                                    <li>Renouvellement auto tous les 6 jours (tant que le client est là).</li>
                                                                    <li>Libération auto 7 jours après la date de départ.</li>
                                                                </ul>
                                                                <em style="display:block; margin-top:4px; font-size:0.75rem; color:#64748b;">(Les boutons manuels ci-dessus restent prioritaires en cas de besoin)</em>
                                                            </div>
                                                        <?php endif; ?>

                                                    <?php elseif ($c_mode === 'encaissement') : ?>
                                                        <p>Caution à encaisser : <?php echo number_format($c_montant, 2, ',', ' '); ?> €</p>
                                                    <?php else : ?>
                                                        <p><em>Pas de gestion de caution (mode: <?php echo esc_html($c_mode); ?>).</em></p>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="pc-resa-card__section pc-resa-card__section--full" style="flex: 1 1 100%; max-width: 100%;">
                                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                                <h4 style="margin:0;">💬 Messagerie &amp; Historique</h4>
                                                <button type="button" class="pc-btn pc-btn--primary pc-resa-open-msg-modal"
                                                    style="font-size:0.8rem; padding:4px 10px;"
                                                    data-resa-id="<?php echo $resa->id; ?>"
                                                    data-client="<?php echo esc_attr($client_name); ?>">
                                                    ✉️ Envoyer un message
                                                </button>
                                            </div>

                                            <div class="pc-resa-messages-list">
                                                <?php
                                                $msgs = isset($messages_map[$resa->id]) ? $messages_map[$resa->id] : [];
                                                if (!empty($msgs)) :
                                                    foreach ($msgs as $msg) :
                                                        $is_out = ($msg->direction === 'sortant');
                                                        $bg = $is_out ? '#f1f5f9' : '#dbeafe'; // Gris = Nous, Bleu = Client (Futur)
                                                        $align = $is_out ? 'margin-left:auto; margin-right:0;' : 'margin-right:auto; margin-left:0;';
                                                        $icon = ($msg->canal === 'whatsapp') ? '📱' : '📧';
                                                        $status_icon = ($msg->statut_envoi === 'envoye') ? '✅' : '⏳';
                                                ?>
                                                        <div style="background:<?php echo $bg; ?>; padding:8px 12px; border-radius:8px; max-width:85%; margin-bottom:8px; <?php echo $align; ?> font-size:0.9rem;">
                                                            <div style="display:flex; justify-content:space-between; font-size:0.75rem; color:#64748b; margin-bottom:4px;">
                                                                <span><?php echo $icon; ?> <strong><?php echo esc_html($msg->sujet); ?></strong></span>
                                                                <span><?php echo date_i18n('d/m H:i', strtotime($msg->date_creation)); ?> <?php echo $status_icon; ?></span>
                                                            </div>
                                                            <div style="white-space: pre-wrap; line-height:1.4;"><?php echo wp_trim_words($msg->corps, 20, '...'); ?></div>
                                                            <?php if (strlen(strip_tags($msg->corps)) > 100): ?>
                                                                <button type="button" class="pc-msg-see-more"
                                                                    style="border:none; background:none; color:#3b82f6; font-size:0.75rem; padding:0; cursor:pointer;"
                                                                    data-action="view-full-message"
                                                                    data-content="<?php echo esc_attr($msg->corps); ?>">
                                                                    [Voir plus]
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach;
                                                else : ?>
                                                    <p style="font-style:italic; color:#94a3b8; text-align:center; padding:10px;">Aucun message échangé.</p>
                                                <?php endif; ?>
                                            </div>
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
        <?php if (isset($total_pages) && $total_pages > 1) : ?>
            <div class="pc-resa-pagination" style="margin-top: 1rem; display: flex; align-items: center; gap: 0.5rem; justify-content: flex-end;">

                <?php
                // Fonction simple pour garder les filtres actuels dans l'URL
                $get_page_link = function ($p) {
                    return add_query_arg('pc_resa_page', $p);
                };
                ?>

                <?php if ($page > 1) : ?>
                    <a href="<?php echo esc_url($get_page_link($page - 1)); ?>" class="pc-resa-page-btn">
                        &laquo; Précédent
                    </a>
                <?php else : ?>
                    <span class="pc-resa-page-btn" style="opacity:0.5; cursor:default;">&laquo; Précédent</span>
                <?php endif; ?>

                <span class="pc-resa-page-info" style="font-size: 0.9rem; color: #666; margin: 0 5px;">
                    Page <strong><?php echo intval($page); ?></strong> sur <?php echo intval($total_pages); ?>
                </span>

                <?php if ($page < $total_pages) : ?>
                    <a href="<?php echo esc_url($get_page_link($page + 1)); ?>" class="pc-resa-page-btn">
                        Suivant &raquo;
                    </a>
                <?php else : ?>
                    <span class="pc-resa-page-btn" style="opacity:0.5; cursor:default;">Suivant &raquo;</span>
                <?php endif; ?>

            </div>
        <?php endif; ?>
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
    
