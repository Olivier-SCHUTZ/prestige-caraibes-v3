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

        // 🚀 CORRECTION 1 : Plus de blocage lié à ACF
        if (!$post_id) {
            return '';
        }

        // 1. Détection de Lodgify
        $lodgify_embed = PCR_Fields::get('lodgify_widget_embed', $post_id);
        $has_lodgify   = !empty(trim($lodgify_embed));
        $manual_quote  = (bool) PCR_Fields::get('pc_manual_quote', $post_id);

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
        $base_price   = (float) PCR_Fields::get('base_price_from', $post_id);
        $unit         = (string) PCR_Fields::get('unite_de_prix',   $post_id);
        $min_nights   = (int)    PCR_Fields::get('min_nights',      $post_id);
        $max_nights   = (int)    PCR_Fields::get('max_nights',      $post_id);
        $cap          = (int)    PCR_Fields::get('capacite',        $post_id);

        if ($cap <= 0) $cap = 1;

        $extra_fee    = (float)  PCR_Fields::get('extra_guest_fee',  $post_id);
        $extra_from   = (int)    PCR_Fields::get('extra_guest_from', $post_id);
        $cleaning     = (float)  PCR_Fields::get('frais_menage',     $post_id);
        $other_fee    = (float)  PCR_Fields::get('autres_frais',     $post_id);
        $other_label  = (string) PCR_Fields::get('autres_frais_type', $post_id);

        // 🚀 CORRECTION : Aspirateur Universel pour la Taxe de Séjour
        $taxe_raw = PCR_Fields::get('taxe_sejour', $post_id);

        // 1. Détection ancien format Répéteur ACF (un chiffre)
        if (is_numeric($taxe_raw) && $taxe_raw > 0 && $taxe_raw < 20) {
            $reconstructed = [];
            for ($i = 0; $i < intval($taxe_raw); $i++) {
                $val = PCR_Fields::get("taxe_sejour_{$i}_value", $post_id)
                    ?: PCR_Fields::get("taxe_sejour_{$i}_type", $post_id)
                    ?: PCR_Fields::get("taxe_sejour_{$i}_taux", $post_id);
                if ($val) $reconstructed[] = $val;
            }
            $taxe_raw = $reconstructed;
        }
        // 2. Décodeur JSON / Sérialisé
        elseif (is_string($taxe_raw)) {
            $decoded = json_decode($taxe_raw, true);
            $taxe_raw = (json_last_error() === JSON_ERROR_NONE) ? $decoded : maybe_unserialize($taxe_raw);
        }

        // 3. Extraction agressive (Aspirateur à données multi-niveaux)
        $taxe_choices = [];
        if (is_array($taxe_raw)) {
            array_walk_recursive($taxe_raw, function ($value, $key) use (&$taxe_choices) {
                // Si Vue a sauvegardé les choix en tant que clés (ex: {"5%": "Label..."})
                if (is_string($key) && !is_numeric($key)) {
                    $taxe_choices[] = $key;
                }
                if (is_string($value) && !empty(trim($value))) {
                    $taxe_choices[] = trim($value);
                }
            });
        } elseif (is_string($taxe_raw) && !empty(trim($taxe_raw))) {
            $taxe_choices[] = trim($taxe_raw);
        }

        $unit_is_week = (stripos($unit, 'semaine') !== false);

        // Récupération des modes
        $mode_raw = PCR_Fields::get('mode_reservation', $post_id);
        if (is_string($mode_raw)) {
            $decoded_mode = json_decode($mode_raw, true);
            if (json_last_error() === JSON_ERROR_NONE) $mode_raw = $decoded_mode;
            else $mode_raw = maybe_unserialize($mode_raw);
        }
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
            'taxe_sejour' => array_values(array_unique(array_filter($taxe_choices))),
            'seasons'     => $this->get_formatted_seasons($post_id, $base_price, $extra_fee, $extra_from, $unit_is_week),
            'icsDisable'  => $this->get_disabled_dates($post_id),
            'payment'     => $this->get_payment_rules($post_id),
            'bookingMode' => $booking_mode,
            'lodgifyId'   => PCR_Fields::get('identifiant_lodgify', $post_id) ?: '',
            'lodgifyAccount' => 'marine-schutz-431222',
            'manualQuote' => $manual,
        ];
    }

    /**
     * Helper : Formate les saisons pour le JS (Compatible ancien et nouveau format)
     */
    private function get_formatted_seasons($post_id, $base_price, $extra_fee, $extra_from, $unit_is_week)
    {
        $raw_seasons = PCR_Fields::get('pc_season_blocks', $post_id);

        // --- 🚀 CORRECTION 2 : RECONSTRUCTEUR ACF ---
        if (is_numeric($raw_seasons) && $raw_seasons > 0) {
            $reconstructed = [];
            for ($i = 0; $i < intval($raw_seasons); $i++) {
                $prefix = "pc_season_blocks_{$i}_";

                $periods_count = PCR_Fields::get($prefix . 'season_periods', $post_id);
                $periods = [];
                if (is_numeric($periods_count) && $periods_count > 0) {
                    for ($j = 0; $j < intval($periods_count); $j++) {
                        $p_prefix = $prefix . "season_periods_{$j}_";
                        $periods[] = [
                            'date_from' => PCR_Fields::get($p_prefix . 'date_from', $post_id),
                            'date_to'   => PCR_Fields::get($p_prefix . 'date_to', $post_id),
                        ];
                    }
                }

                $reconstructed[] = [
                    'season_name'             => PCR_Fields::get($prefix . 'season_name', $post_id),
                    'season_price'            => PCR_Fields::get($prefix . 'season_price', $post_id),
                    'season_min_nights'       => PCR_Fields::get($prefix . 'season_min_nights', $post_id),
                    'season_extra_guest_fee'  => PCR_Fields::get($prefix . 'season_extra_guest_fee', $post_id),
                    'season_extra_guest_from' => PCR_Fields::get($prefix . 'season_extra_guest_from', $post_id),
                    'season_periods'          => $periods
                ];
            }
            $raw_seasons = $reconstructed;
        }
        // --- DÉCODEUR JSON/SÉRIALISÉ ---
        elseif (is_string($raw_seasons)) {
            $decoded = json_decode($raw_seasons, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $raw_seasons = $decoded;
            } else {
                $raw_seasons = maybe_unserialize($raw_seasons);
            }
        }

        if (!is_array($raw_seasons)) return [];

        $seasons = [];
        foreach ($raw_seasons as $s) {
            if (!is_array($s)) continue;

            $price = isset($s['season_price']) ? (float)$s['season_price'] : 0.0;
            if ($unit_is_week && $price > 0) $price = $price / 7.0;

            // Gestion de la sous-liste de périodes
            $periods = $s['season_periods'] ?? [];
            if (is_string($periods)) {
                $decoded_p = json_decode($periods, true);
                $periods = (json_last_error() === JSON_ERROR_NONE) ? $decoded_p : maybe_unserialize($periods);
            }
            if (!is_array($periods)) $periods = [];

            $seasons[] = [
                'name'        => trim((string)($s['season_name'] ?? 'Saison')),
                'min_nights'  => (int)($s['season_min_nights'] ?? 0),
                'extra_fee'   => ($s['season_extra_guest_fee'] ?? '') !== '' ? (float)$s['season_extra_guest_fee'] : $extra_fee,
                'extra_from'  => ($s['season_extra_guest_from'] ?? '') !== '' ? (int)$s['season_extra_guest_from'] : $extra_from,
                'price'       => ($price > 0 ? $price : ($unit_is_week ? ($base_price / 7.0) : $base_price)),
                'periods'     => array_values(array_map(function ($p) {
                    return ['from' => (string)($p['date_from'] ?? ''), 'to' => (string)($p['date_to'] ?? '')];
                }, $periods))
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
     * Helper : Récupère les règles de paiement (Sécurisé sans ACF)
     */
    private function get_payment_rules($post_id)
    {
        $pay_rules = PCR_Fields::get('regles_de_paiement', $post_id);
        if (is_string($pay_rules)) $pay_rules = json_decode($pay_rules, true) ?: [];

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
        // 🚀 CORRECTION 3 : Sécurité Options sans ACF
        $info = [
            'name'    => get_option('options_pc_org_name') ?: get_bloginfo('name'),
            'legal'   => get_option('options_pc_org_legal_name'),
            'address' => get_option('options_pc_org_address_street'),
            'city'    => get_option('options_pc_org_address_postal') . ' ' . get_option('options_pc_org_address_locality'),
            'phone'   => get_option('options_pc_org_phone'),
            'email'   => get_option('options_pc_org_email'),
            'vat'     => get_option('options_pc_org_vat_id'),
            'logo'    => get_option('options_pc_org_logo'),
        ];

        // CGV
        $terms_raw = get_option('options_cgv_location');
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
