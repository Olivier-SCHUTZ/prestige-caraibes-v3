<?php

/**
 * Composant Shortcode : Calculateur de Devis [pc_devis]
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Devis_Shortcode extends PC_Shortcode_Base
{

    protected $tag = 'pc_devis';

    /**
     * Rendu principal du shortcode
     */
    public function render($atts, $content = null)
    {
        $post_id = get_the_ID();
        if (!$post_id || !function_exists('get_field')) {
            return '';
        }

        // 1. Détection de Lodgify
        $lodgify_embed = get_field('lodgify_widget_embed', $post_id);
        $has_lodgify   = !empty(trim($lodgify_embed));
        $manual_quote  = (bool) get_field('pc_manual_quote', $post_id);

        // 2. Récupération des données complexes structurées
        $company_info = $this->get_company_info();
        $devis_config = $this->get_devis_config($post_id, $manual_quote);

        // 3. Identifiant unique
        $id = 'pc-devis-' . $post_id;
        $data_json = esc_attr(wp_json_encode($devis_config));

        // 4. Affichage HTML
        ob_start(); ?>
        <section id="<?php echo esc_attr($id); ?>" class="pc-devis-section" data-pc-devis="<?php echo $data_json; ?>" data-manual-quote="<?php echo $manual_quote ? '1' : '0'; ?>">
            <div class="exp-devis-wrap">
                <h3 class="exp-devis-title">Estimez le coût de votre séjour</h3>
                <div class="exp-devis-form">

                    <div class="exp-devis-field" style="grid-column: 1 / -1;">
                        <label for="<?php echo esc_attr($id); ?>-dates">Vos dates</label>
                        <input type="text" id="<?php echo esc_attr($id); ?>-dates" name="dates" class="pcq-input" placeholder="Arrivée – Départ" readonly>
                    </div>

                    <div class="exp-devis-field">
                        <label for="<?php echo esc_attr($id); ?>-adults">Adultes</label>
                        <div class="exp-stepper">
                            <button type="button" class="exp-stepper-btn" data-step="minus" aria-label="Retirer un adulte">-</button>
                            <input type="number" id="<?php echo esc_attr($id); ?>-adults" name="devis_adults" class="pcq-input" min="1" value="1">
                            <button type="button" class="exp-stepper-btn" data-step="plus" aria-label="Ajouter un adulte">+</button>
                        </div>
                    </div>

                    <div class="exp-devis-field">
                        <label for="<?php echo esc_attr($id); ?>-children">Enfants</label>
                        <div class="exp-stepper">
                            <button type="button" class="exp-stepper-btn" data-step="minus" aria-label="Retirer un enfant">-</button>
                            <input type="number" id="<?php echo esc_attr($id); ?>-children" name="devis_children" class="pcq-input" min="0" placeholder="0">
                            <button type="button" class="exp-stepper-btn" data-step="plus" aria-label="Ajouter un enfant">+</button>
                        </div>
                    </div>

                    <div class="exp-devis-field">
                        <label for="<?php echo esc_attr($id); ?>-infants">Bébés</label>
                        <div class="exp-stepper">
                            <button type="button" class="exp-stepper-btn" data-step="minus" aria-label="Retirer un bébé">-</button>
                            <input type="number" id="<?php echo esc_attr($id); ?>-infants" name="devis_infants" class="pcq-input" min="0" placeholder="0">
                            <button type="button" class="exp-stepper-btn" data-step="plus" aria-label="Ajouter un bébé">+</button>
                        </div>
                    </div>

                    <div id="<?php echo esc_attr($id); ?>-msg" class="pcq-msg" role="status" aria-live="polite"></div>

                    <div class="exp-devis-result" id="<?php echo esc_attr($id); ?>-result" hidden>
                        <h4 class="exp-result-title">Résumé de l'estimation</h4>
                        <ul id="<?php echo esc_attr($id); ?>-lines" class="pcq-lines"></ul>
                        <div class="exp-result-total">
                            <span>Total</span>
                            <strong id="<?php echo esc_attr($id); ?>-total">—</strong>
                        </div>
                    </div>

                    <div class="exp-devis-error" id="logement-devis-error-msg"></div>

                    <div class="exp-devis-actions">
                        <button type="button" id="<?php echo esc_attr($id); ?>-pdf-btn" class="pc-btn pc-btn--secondary">
                            <i class="fas fa-file-pdf"></i> Télécharger l'estimation
                        </button>
                        <?php if ($has_lodgify) : ?>
                            <button type="button" id="logement-lodgify-reserve-btn" class="pc-btn pc-btn--primary">
                                Réserver maintenant
                            </button>
                        <?php else : ?>
                            <button type="button" id="logement-open-modal-btn-local" class="pc-btn pc-btn--primary">
                                Réserver maintenant
                            </button>
                        <?php endif; ?>
                    </div>

                </div>

                <div class="exp-devis-company-info" style="display:none;"><?php echo wp_json_encode($company_info); ?></div>
                <div class="exp-devis-experience-title" style="display:none;"><?php echo esc_html(get_the_title($post_id)); ?></div>
            </div>
        </section>
<?php return ob_get_clean();
    }

    protected function enqueue_assets()
    {
        return; // Le script `pc-devis.js` est toujours géré globalement pour l'instant
    }

    /**
     * Helper : Construit la configuration complète du devis pour le JS
     */
    private function get_devis_config($post_id, $manual)
    {
        $base_price   = (float) get_field('base_price_from', $post_id);
        $unit         = (string) get_field('unite_de_prix',   $post_id);
        $min_nights   = (int)    get_field('min_nights',      $post_id);
        $max_nights   = (int)    get_field('max_nights',      $post_id);
        $cap          = (int)    get_field('capacite',        $post_id);

        if ($cap <= 0) $cap = 1;

        $extra_fee    = (float)  get_field('extra_guest_fee',  $post_id);
        $extra_from   = (int)    get_field('extra_guest_from', $post_id);
        $cleaning     = (float)  get_field('frais_menage',     $post_id);
        $other_fee    = (float)  get_field('autres_frais',     $post_id);
        $other_label  = (string) get_field('autres_frais_type', $post_id);
        $taxe_choices = (array)  get_field('taxe_sejour',      $post_id);

        $unit_is_week = (stripos($unit, 'semaine') !== false);

        // Récupération des modes
        $mode_raw = get_field('mode_reservation', $post_id);
        if (is_array($mode_raw)) $mode_raw = $mode_raw['value'] ?? $mode_raw[0] ?? '';
        $booking_mode = ($mode_raw === 'log_directe' || $mode_raw === 'log_direct') ? 'directe' : 'demande';

        return [
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
            'seasons'     => $this->get_formatted_seasons($post_id, $base_price, $extra_fee, $extra_from, $unit_is_week),
            'icsDisable'  => $this->get_disabled_dates($post_id),
            'payment'     => $this->get_payment_rules($post_id),
            'bookingMode' => $booking_mode,
            'lodgifyId'   => get_field('identifiant_lodgify', $post_id) ?: '',
            'lodgifyAccount' => 'marine-schutz-431222',
            'manualQuote' => $manual,
        ];
    }

    /**
     * Helper : Formate les saisons ACF pour le JS
     */
    private function get_formatted_seasons($post_id, $base_price, $extra_fee, $extra_from, $unit_is_week)
    {
        $seasons_raw = (array) get_field('pc_season_blocks', $post_id);
        $seasons = [];

        foreach ($seasons_raw as $s) {
            if (!is_array($s)) continue;

            $price = isset($s['season_price']) ? (float)$s['season_price'] : 0.0;
            if ($unit_is_week && $price > 0) $price = $price / 7.0;

            $seasons[] = [
                'name'        => trim((string)($s['season_name'] ?? 'Saison')),
                'min_nights'  => (int)($s['season_min_nights'] ?? 0),
                'extra_fee'   => ($s['season_extra_guest_fee'] ?? '') !== '' ? (float)$s['season_extra_guest_fee'] : $extra_fee,
                'extra_from'  => ($s['season_extra_guest_from'] ?? '') !== '' ? (int)$s['season_extra_guest_from'] : $extra_from,
                'price'       => ($price > 0 ? $price : ($unit_is_week ? ($base_price / 7.0) : $base_price)),
                'periods'     => array_values(array_map(function ($p) {
                    return ['from' => (string)($p['date_from'] ?? ''), 'to' => (string)($p['date_to'] ?? '')];
                }, (array)($s['season_periods'] ?? [])))
            ];
        }
        return $seasons;
    }

    /**
     * Helper : Récupère les dates désactivées formatées pour le JS
     */
    private function get_disabled_dates($post_id)
    {
        $combined_json = PC_Availability_Helper::get_combined_availability($post_id);
        $raw_ranges = json_decode($combined_json, true);
        $ics_disable = [];

        if (is_array($raw_ranges)) {
            foreach ($raw_ranges as $range) {
                if (isset($range[0], $range[1])) {
                    $ics_disable[] = [
                        'from' => $range[0],
                        'to'   => $range[1]
                    ];
                }
            }
        }
        return $ics_disable;
    }

    /**
     * Helper : Récupère les règles de paiement
     */
    private function get_payment_rules($post_id)
    {
        $pay_rules = get_field('regles_de_paiement', $post_id);
        return [
            'mode'         => $pay_rules['pc_pay_mode'] ?? 'acompte_plus_solde',
            'deposit_type' => $pay_rules['pc_deposit_type'] ?? 'pourcentage',
            'deposit_val'  => floatval($pay_rules['pc_deposit_value'] ?? 30),
            'delay_days'   => intval($pay_rules['pc_balance_delay_days'] ?? 30),
        ];
    }

    /**
     * Helper : Construit les infos de l'entreprise et gère le logo Base64 pour le PDF
     */
    private function get_company_info()
    {
        $info = [
            'name'    => get_field('pc_org_name', 'option') ?: get_bloginfo('name'),
            'legal'   => get_field('pc_org_legal_name', 'option'),
            'address' => get_field('pc_org_address_street', 'option'),
            'city'    => get_field('pc_org_address_postal', 'option') . ' ' . get_field('pc_org_address_locality', 'option'),
            'phone'   => get_field('pc_org_phone', 'option'),
            'email'   => get_field('pc_org_email', 'option'),
            'vat'     => get_field('pc_org_vat_id', 'option'),
            'logo'    => get_field('pc_org_logo', 'option'),
        ];

        // CGV
        $terms_raw = get_field('cgv_location', 'option');
        $info['cgv_location'] = $terms_raw ? trim(wp_strip_all_tags(wp_kses_post($terms_raw))) : '';

        // Conversion du logo en Base64 (fond blanc forcé pour jsPDF)
        $uploads  = wp_get_upload_dir();
        $rel_path = '2025/06/Logo-Prestige-Caraibes-bleu.png';
        $abs_path = trailingslashit($uploads['basedir']) . $rel_path;

        if (is_readable($abs_path)) {
            $src = imagecreatefrompng($abs_path);
            if ($src) {
                $w = imagesx($src);
                $h = imagesy($src);
                $dst = imagecreatetruecolor($w, $h);

                imagealphablending($dst, true);
                imagesavealpha($dst, false);
                $white = imagecolorallocate($dst, 255, 255, 255);
                imagefilledrectangle($dst, 0, 0, $w, $h, $white);
                imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);

                ob_start();
                imagepng($dst, null, 9);
                $png = ob_get_clean();

                $info['logo_data'] = 'data:image/png;base64,' . base64_encode($png);

                imagedestroy($dst);
                imagedestroy($src);
            }
        }

        return $info;
    }
}
