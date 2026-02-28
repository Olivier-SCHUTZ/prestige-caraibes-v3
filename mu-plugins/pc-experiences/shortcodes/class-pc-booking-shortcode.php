<?php

/**
 * Shortcode : [experience_booking_bar]
 * Affiche la barre de réservation, le calculateur de devis (bottom-sheet) et la modale.
 * Gère également le shortcode legacy [experience_devis].
 *
 * @package PC_Experiences
 */

if (!defined('ABSPATH')) {
    exit; // Sécurité
}

class PC_Experience_Booking_Shortcode extends PC_Experience_Shortcode_Base
{

    /**
     * Nom du shortcode.
     *
     * @var string
     */
    protected $shortcode_name = 'experience_booking_bar';

    /**
     * Surcharge du constructeur pour gérer le shortcode legacy.
     */
    public function __construct()
    {
        parent::__construct();
        // Maintien de l'ancien shortcode pour ne pas casser les designs Elementor existants
        add_shortcode('experience_devis', '__return_empty_string');
    }

    /**
     * Rendu HTML du shortcode.
     *
     * @param array $atts Attributs.
     */
    protected function render(array $atts): void
    {
        $experience_id = $this->get_experience_id();

        if (!have_rows('exp_types_de_tarifs')) {
            return;
        }

        // --- 1. Informations de l'entreprise (pour le devis PDF) ---
        $company_info = [
            'name'    => get_field('pc_org_name', 'option') ?: get_bloginfo('name'),
            'legal'   => get_field('pc_org_legal_name', 'option'),
            'address' => get_field('pc_org_address_street', 'option'),
            'city'    => get_field('pc_org_address_postal', 'option') . ' ' . get_field('pc_org_address_locality', 'option'),
            'phone'   => get_field('pc_org_phone', 'option'),
            'email'   => get_field('pc_org_email', 'option'),
            'vat'     => get_field('pc_org_vat_id', 'option'),
        ];

        // Logo PNG "bleu" (URL publique + base64 OPAQUE pour jsPDF)
        $uploads  = wp_get_upload_dir();
        $rel_path = '2025/06/Logo-Prestige-Caraibes-bleu.png';
        $company_info['logo'] = trailingslashit($uploads['baseurl']) . $rel_path;
        $company_info['logo_data'] = '';
        $abs_path = trailingslashit($uploads['basedir']) . $rel_path;

        if (is_readable($abs_path)) {
            $src = @imagecreatefrompng($abs_path);
            if ($src !== false) {
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
                $png_data = ob_get_clean();

                if ($png_data !== false) {
                    $company_info['logo_data'] = 'data:image/png;base64,' . base64_encode($png_data);
                }
                imagedestroy($dst);
                imagedestroy($src);
            }
        }

        // CGV
        $terms_raw  = get_field('cgv_experience', 'option');
        $terms_text = $terms_raw ? trim(wp_strip_all_tags(wp_kses_post($terms_raw))) : '';
        $company_info['conditions_generales'] = $terms_text;

        // --- 2. Données de tarification pour le calculateur JS ---
        $pricing_data  = [];
        $field_object  = get_field_object('exp_types_de_tarifs');
        $exp_type_choices = [];

        if (!empty($field_object['sub_fields'])) {
            foreach ($field_object['sub_fields'] as $sf) {
                if (!empty($sf['name']) && $sf['name'] === 'exp_type' && !empty($sf['choices'])) {
                    $exp_type_choices = (array)$sf['choices'];
                    break;
                }
            }
        }

        foreach ((array)$field_object['value'] as $index => $row) {
            $type_value = $row['exp_type'] ?? '';
            if (!$type_value) continue;

            // Utilisation de notre Helper PHP propre
            $type_label = class_exists('PC_Experience_Field_Helper')
                ? PC_Experience_Field_Helper::resolve_pricing_type_label($row, $exp_type_choices)
                : $type_value;

            $options = [];
            if (!empty($row['exp_options_tarifaires'])) {
                foreach ($row['exp_options_tarifaires'] as $opt) {
                    $options[] = [
                        'label'       => (string)($opt['exp_description_option'] ?? ''),
                        'price'       => (float)($opt['exp_tarif_option'] ?? 0),
                        'enable_qty'  => !empty($opt['option_enable_qty']),
                    ];
                }
            }

            $lines_raw = isset($row['exp_tarifs_lignes']) ? (array)$row['exp_tarifs_lignes'] : [];
            $lines = [];
            $has_counters = false;

            foreach ($lines_raw as $ln) {
                $t = $ln['type_ligne'] ?? 'personnalise';
                $entry = [
                    'type'        => $t,
                    'price'       => (float)($ln['tarif_valeur'] ?? 0),
                    'label'       => '',
                    'observation' => trim((string)($ln['tarif_observation'] ?? '')),
                    'precision'   => '',
                    'enable_qty'  => false,
                ];

                if ($t === 'adulte') {
                    $entry['label'] = __('Adulte', 'pc');
                    $has_counters = true;
                } elseif ($t === 'enfant') {
                    $p = trim((string)($ln['precision_age_enfant'] ?? ''));
                    $entry['label'] = $p ? sprintf(__('Enfant (%s)', 'pc'), $p) : __('Enfant', 'pc');
                    $entry['precision'] = $p;
                    $has_counters = true;
                } elseif ($t === 'bebe') {
                    $p = trim((string)($ln['precision_age_bebe'] ?? ''));
                    $entry['label'] = $p ? sprintf(__('Bébé (%s)', 'pc'), $p) : __('Bébé', 'pc');
                    $entry['precision'] = $p;
                    $has_counters = true;
                } else {
                    $entry['label'] = trim((string)($ln['tarif_nom_perso'] ?? '')) ?: __('Forfait', 'pc');
                    $entry['enable_qty'] = !empty($ln['tarif_enable_qty']);
                }
                $lines[] = $entry;
            }

            $fixed_fees = [];
            if (!empty($row['exp-frais-fixes'])) {
                foreach ((array)$row['exp-frais-fixes'] as $fee_row) {
                    $fee_label = trim((string)($fee_row['exp_description_frais_fixe'] ?? ''));
                    $fee_price = (float)($fee_row['exp_tarif_frais_fixe'] ?? 0);
                    if ($fee_label !== '' && $fee_price != 0) {
                        $fixed_fees[] = [
                            'label' => $fee_label,
                            'price' => $fee_price,
                        ];
                    }
                }
            }

            $pricing_key = $type_value . '_' . $index;
            $pricing_data[$pricing_key] = [
                'code'         => $type_value,
                'label'        => $type_label,
                'options'      => $options,
                'lines'        => $lines,
                'fixed_fees'   => $fixed_fees,
                'has_counters' => $has_counters,
            ];
        }

        $devis_id = 'exp-devis-' . $experience_id;

        // --- 3. DÉBUT DU RENDU HTML ---
?>
        <button type="button" class="exp-booking-fab" id="exp-open-devis-sheet-btn">
            <span id="fab-price-display">Simuler un devis</span>
        </button>

        <div class="exp-devis-sheet" id="exp-devis-sheet" role="dialog" aria-modal="true" aria-labelledby="devis-sheet-title" aria-hidden="true">
            <div class="exp-devis-sheet__overlay" data-close-devis-sheet></div>

            <div class="exp-devis-sheet__content" role="document">
                <div class="exp-devis-sheet__header">
                    <h3 class="exp-devis-sheet__title" id="devis-sheet-title">Obtenir un devis</h3>
                    <button class="exp-devis-sheet__close" aria-label="Fermer" data-close-devis-sheet>×</button>
                </div>

                <div class="exp-devis-sheet__body">

                    <div id="<?php echo esc_attr($devis_id); ?>" class="exp-devis-wrap" data-exp-devis='<?php echo esc_attr(wp_json_encode($pricing_data)); ?>' data-label-pending="En attente de devis">

                        <div class="exp-devis-section-type">
                            <label for="<?php echo esc_attr($devis_id); ?>-type">Type de prestation</label>
                            <div class="exp-select-wrapper">
                                <select id="<?php echo esc_attr($devis_id); ?>-type" name="devis_type">
                                    <?php foreach ($pricing_data as $value => $data): ?>
                                        <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($data['label']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="exp-list-container">
                            <h4 class="exp-list-title">Participants</h4>

                            <div id="<?php echo esc_attr($devis_id); ?>-dynamic-lines" class="exp-dynamic-lines"></div>

                            <input type="hidden" name="devis_adults" id="<?php echo esc_attr($devis_id); ?>-adults" value="0">
                            <input type="hidden" name="devis_children" id="<?php echo esc_attr($devis_id); ?>-children" value="0">
                            <input type="hidden" name="devis_bebes" id="<?php echo esc_attr($devis_id); ?>-bebes" value="0">
                        </div>

                        <div id="<?php echo esc_attr($devis_id); ?>-options" class="exp-options-container"></div>

                        <div class="exp-devis-result" id="<?php echo esc_attr($devis_id); ?>-result"></div>

                        <div class="exp-devis-error" id="exp-devis-error-msg"></div>

                        <div class="exp-devis-actions">
                            <button type="button" id="<?php echo esc_attr($devis_id); ?>-pdf-btn" class="pc-btn pc-btn--secondary">
                                <i class="fas fa-file-pdf"></i> Télécharger le devis
                            </button>
                            <button type="button" id="exp-open-modal-btn-local" class="pc-btn pc-btn--primary">
                                Faire une demande de réservation
                            </button>
                        </div>

                        <div class="exp-devis-company-info" style="display:none;"><?php echo wp_json_encode($company_info); ?></div>
                        <div class="exp-devis-experience-title" style="display:none;"><?php echo esc_html(get_the_title()); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="exp-booking-modal is-hidden" id="exp-booking-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
            <div class="exp-booking-modal__overlay" data-close-modal></div>
            <div class="exp-booking-modal__content">
                <button class="exp-booking-modal__close" aria-label="Fermer" data-close-modal>×</button>
                <h3 class="exp-booking-modal__title" id="modal-title">Demande de réservation</h3>

                <form id="experience-booking-form" method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="experience_booking_request">
                    <input type="hidden" name="experience_id" value="<?php echo esc_attr($experience_id); ?>">
                    <?php wp_nonce_field('experience_booking_request_nonce', 'nonce'); ?>

                    <p class="honeypot-field" style="display:none !important; visibility:hidden !important; opacity:0 !important; height:0 !important; width:0 !important; position:absolute !important; left:-9999px !important;" aria-hidden="true">
                        <label for="booking-reason">Motif</label>
                        <input type="text" id="booking-reason" name="booking_reason" tabindex="-1" autocomplete="off">
                    </p>

                    <fieldset class="exp-booking-fieldset">
                        <legend>Votre simulation</legend>
                        <div id="modal-quote-summary">
                            <p>Veuillez d'abord faire une simulation avec le calculateur.</p>
                        </div>
                        <textarea name="quote_details" id="modal-quote-details-hidden" style="display:none;"></textarea>
                    </fieldset>

                    <fieldset class="exp-booking-fieldset">
                        <legend>Vos coordonnées</legend>
                        <div class="exp-booking-form-grid">
                            <div class="exp-booking-field">
                                <label for="booking-prenom">Prénom*</label>
                                <input type="text" id="booking-prenom" name="prenom" required>
                            </div>
                            <div class="exp-booking-field">
                                <label for="booking-nom">Nom*</label>
                                <input type="text" id="booking-nom" name="nom" required>
                            </div>
                            <div class="exp-booking-field">
                                <label for="booking-email">Email*</label>
                                <input type="email" id="booking-email" name="email" required>
                            </div>
                            <div class="exp-booking-field">
                                <label for="booking-tel">Téléphone*</label>
                                <input type="tel" id="booking-tel" name="tel" required>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="exp-booking-fieldset">
                        <legend>Pouvons-nous vous joindre par WhatsApp ?</legend>
                        <div class="exp-booking-radio-group">
                            <label><input type="radio" name="whatsapp" value="Oui" checked> Oui</label>
                            <label><input type="radio" name="whatsapp" value="Non"> Non</label>
                        </div>
                    </fieldset>

                    <fieldset class="exp-booking-fieldset">
                        <legend>Observations ou demandes particulières</legend>
                        <div class="exp-booking-field">
                            <label for="booking-message-experience" class="visually-hidden">Votre message</label>
                            <textarea id="booking-message-experience" name="message" rows="3" placeholder="Avez-vous des questions ou des demandes particulières ?"></textarea>
                        </div>
                    </fieldset>

                    <div class="exp-booking-modal__actions">
                        <p class="exp-booking-disclaimer">Cette demande est sans engagement. Nous vous recontacterons pour confirmer la disponibilité.</p>
                        <button type="submit" class="pc-btn pc-btn--primary">Envoyer la demande</button>
                    </div>
                </form>
            </div>
        </div>
<?php
        // --- FIN DU RENDU ---
    }
}
