<?php

/**
 * Shortcode : [experience_pricing]
 * Affiche la grille des tarifs (standard, options, frais fixes ou sur devis).
 *
 * @package PC_Experiences
 */

if (!defined('ABSPATH')) {
    exit; // Sécurité
}

class PC_Experience_Pricing_Shortcode extends PC_Experience_Shortcode_Base
{

    /**
     * Nom du shortcode.
     *
     * @var string
     */
    protected $shortcode_name = 'experience_pricing';

    /**
     * Rendu HTML du shortcode.
     *
     * @param array $atts Attributs.
     */
    protected function render(array $atts = []): void
    {
        $post_id = $this->get_experience_id();
        if (!$post_id) return;

        // 1. Décodeur Universel pour le Répéteur JSON de Tarifs
        $raw_tarifs = PCR_Fields::get('exp_types_de_tarifs', $post_id);
        $tarifs = is_string($raw_tarifs) ? json_decode($raw_tarifs, true) : $raw_tarifs;

        // Si vide, on arrête l'affichage
        if (empty($tarifs) || !is_array($tarifs)) {
            return;
        }

        // Dictionnaire de fallback pour les labels de types de tarifs
        $type_labels = [
            'adulte_enfant' => 'Tarif Adulte / Enfant',
            'forfait'       => 'Forfait Privatisé',
            'sur-devis'     => 'Sur Devis',
            'personnalise'  => 'Tarif Spécial'
        ];

        // --- DÉBUT DU RENDU ---
        ob_start(); ?>

        <div class="exp-pricing-grid">
            <?php foreach ($tarifs as $tarif) :
                // Type & Label
                $type_raw = $tarif['exp_type'] ?? '';
                $type_value = is_array($type_raw) ? ($type_raw['value'] ?? reset($type_raw)) : $type_raw;
                $custom_type = trim((string)($tarif['exp_type_custom'] ?? ''));

                $type_label = $custom_type ?: ($type_labels[$type_value] ?? ucfirst(str_replace('_', ' ', $type_value)));
                if (empty($type_label)) $type_label = 'Tarif';
            ?>
                <div class="exp-pricing-card">
                    <h3 class="exp-pricing-title"><?php echo esc_html($type_label); ?></h3>
                    <div class="exp-pricing-body">

                        <?php if ($type_value === 'sur-devis') : ?>
                            <div class="exp-pricing-row on-demand"><?php echo esc_html__('Sur devis', 'pc'); ?></div>
                            <?php else :

                            // LIGNES TARIFS (Standard)
                            $lines = $tarif['exp_tarifs_lignes'] ?? [];
                            if (is_string($lines)) $lines = json_decode($lines, true);
                            if (!is_array($lines)) $lines = [];

                            if (!empty($lines)) :
                                foreach ($lines as $ln) :
                                    $t_raw = $ln['type_ligne'] ?? 'personnalise';
                                    $t = is_array($t_raw) ? ($t_raw['value'] ?? reset($t_raw)) : $t_raw;

                                    $price = (float)($ln['tarif_valeur'] ?? 0);
                                    $obs   = trim((string)($ln['tarif_observation'] ?? ''));

                                    if ($t === 'adulte') {
                                        $label = __('Adulte', 'pc');
                                    } elseif ($t === 'enfant') {
                                        $p = trim((string)($ln['precision_age_enfant'] ?? ''));
                                        $label = $p ? sprintf(__('Enfant (%s)', 'pc'), $p) : __('Enfant', 'pc');
                                    } elseif ($t === 'bebe') {
                                        $p = trim((string)($ln['precision_age_bebe'] ?? ''));
                                        $label = $p ? sprintf(__('Bébé (%s)', 'pc'), $p) : __('Bébé', 'pc');
                                    } else {
                                        $label = trim((string)($ln['tarif_nom_perso'] ?? '')) ?: __('Forfait', 'pc');
                                    }

                                    $price_html = ($price == 0 && $t === 'bebe') ? __('Gratuit', 'pc') : esc_html(number_format((float)$price, 2, ',', ' ')) . ' €';
                            ?>
                                    <div class="exp-pricing-wrapper">
                                        <div class="exp-pricing-row">
                                            <span class="exp-pricing-label"><?php echo esc_html($label); ?></span>
                                            <span class="exp-pricing-price"><?php echo $price_html; ?></span>
                                        </div>
                                        <?php if ($obs !== '') : ?>
                                            <div class="exp-pricing-note"><?php echo esc_html($obs); ?></div>
                                        <?php endif; ?>
                                    </div>

                            <?php endforeach;
                            endif; ?>

                            <?php
                            // --- FUSION OPTIONS & FRAIS FIXES ---
                            $options_data = $tarif['exp_options_tarifaires'] ?? [];
                            if (is_string($options_data)) $options_data = json_decode($options_data, true);
                            if (!is_array($options_data)) $options_data = [];

                            // Supporte les deux syntaxes (tiret et underscore)
                            $fees_data = $tarif['exp-frais-fixes'] ?? $tarif['exp_frais_fixes'] ?? [];
                            if (is_string($fees_data)) $fees_data = json_decode($fees_data, true);
                            if (!is_array($fees_data)) $fees_data = [];

                            if (!empty($options_data) || !empty($fees_data)) : ?>

                                <div class="exp-pricing-options">

                                    <?php // 1. OPTIONS 
                                    ?>
                                    <?php if (!empty($options_data)) : ?>
                                        <div class="exp-pricing-options-title"><?php echo esc_html__('Options', 'pc'); ?></div>
                                        <?php foreach ($options_data as $opt) :
                                            $opt_label = trim((string)($opt['exp_description_option'] ?? ''));
                                            $opt_price = (float)($opt['exp_tarif_option'] ?? 0);
                                            if ($opt_label === '') continue;
                                        ?>
                                            <div class="exp-pricing-row option">
                                                <span class="exp-pricing-label"><?php echo esc_html($opt_label); ?></span>
                                                <span class="exp-pricing-price">+ <?php echo esc_html(number_format($opt_price, 2, ',', ' ')); ?> €</span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>

                                    <?php // 2. FRAIS FIXES 
                                    ?>
                                    <?php if (!empty($fees_data)) :
                                        if (!empty($options_data)) {
                                            echo '<div style="height: 1rem;"></div>';
                                        }
                                    ?>
                                        <div class="exp-pricing-options-title"><?php echo esc_html__('Frais fixes', 'pc'); ?></div>
                                        <?php foreach ($fees_data as $fee) :
                                            $fee_label = trim((string)($fee['exp_description_frais_fixe'] ?? ''));
                                            $fee_price = (float)($fee['exp_tarif_frais_fixe'] ?? 0);
                                            if ($fee_label === '' || $fee_price == 0) continue;
                                        ?>
                                            <div class="exp-pricing-row option">
                                                <span class="exp-pricing-label"><?php echo esc_html($fee_label); ?></span>
                                                <span class="exp-pricing-price"><?php echo esc_html(number_format($fee_price, 2, ',', ' ')); ?> €</span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>

                                </div>
                            <?php endif; ?>

                        <?php endif; // fin else sur-devis 
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <style>
            /* CSS Embarqué avec les Tokens Globaux (Prestige Caraïbes V3) */
            .exp-pricing-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
                gap: 2rem;
                padding: 2rem 0;
            }

            .exp-pricing-card {
                background: #ffffff;
                border: 1px solid #e0e0e0;
                border-radius: var(--pc-radius, 10px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
                display: flex;
                flex-direction: column;
                overflow: hidden;
                transition: transform 0.3s ease, box-shadow 0.3s ease;
            }

            .exp-pricing-card:hover {
                transform: translateY(-5px);
                box-shadow: var(--pc-shadow-soft, 0 10px 25px rgba(0, 0, 0, 0.1));
            }

            .exp-pricing-title {
                font-family: var(--pc-font-heading, system-ui);
                background: #fff;
                padding: 1.5rem 1.5rem 0.5rem 1.5rem;
                margin: 0;
                font-size: 1.3rem;
                font-weight: 700;
                color: var(--pc-color-heading, #1b3b5f);
                text-align: center;
            }

            .exp-pricing-body {
                padding: 1.5rem;
                flex-grow: 1;
            }

            .exp-pricing-wrapper {
                padding-bottom: 0.75rem;
                margin-bottom: 0.75rem;
                border-bottom: 1px dashed #e0e0e0;
            }

            .exp-pricing-wrapper:last-of-type {
                border-bottom: none;
                margin-bottom: 0;
            }

            .exp-pricing-row {
                display: flex;
                justify-content: space-between;
                align-items: baseline;
                gap: 1rem;
            }

            .exp-pricing-body .exp-pricing-row {
                border-bottom: none;
                padding: 0;
                margin-bottom: 2px;
            }

            .exp-pricing-row .exp-pricing-label {
                font-family: var(--pc-font-heading, system-ui);
                font-size: 1.1rem;
                font-weight: 600;
                color: var(--pc-color-heading, #1b3b5f);
                flex: 1;
            }

            .exp-pricing-row .exp-pricing-price {
                font-family: var(--pc-font-heading, system-ui);
                font-size: 1.1rem;
                font-weight: 700;
                color: var(--pc-color-primary, #007a92);
                white-space: nowrap;
            }

            .exp-pricing-note {
                font-family: var(--pc-font-body, serif);
                font-size: 0.85rem;
                color: var(--pc-color-muted, #6f6f6f);
                font-style: italic;
                margin-top: 2px;
                line-height: 1.4;
            }

            .exp-pricing-options {
                background: #f8f9fa;
                margin: 1.5rem -1.5rem -1.5rem -1.5rem;
                padding: 1.5rem;
                border-top: 1px solid #e0e0e0;
            }

            .exp-pricing-options-title {
                margin: 0 0 0.75rem 0;
                font-family: var(--pc-font-body, serif);
                font-weight: 700;
                font-size: 0.85rem;
                text-transform: uppercase;
                letter-spacing: 1px;
                color: var(--pc-color-muted, #999);
            }

            .exp-pricing-row.option {
                padding: 0.5rem 0;
                border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            }

            .exp-pricing-row.option:last-child {
                border-bottom: none;
            }

            .exp-pricing-row.option .exp-pricing-label {
                font-size: 0.95rem;
                color: var(--pc-color-text, #333);
                font-weight: 400;
                font-family: var(--pc-font-body, serif);
            }

            .exp-pricing-row.option .exp-pricing-price {
                font-size: 1rem;
                font-weight: 600;
                color: var(--pc-color-muted, #666);
                font-family: var(--pc-font-body, serif);
            }

            .exp-pricing-row.on-demand {
                justify-content: center;
                font-style: italic;
                font-family: var(--pc-font-body, serif);
                font-size: 1.1rem;
                padding: 1rem 0;
                color: var(--pc-color-muted, #888);
            }
        </style>

<?php echo ob_get_clean();
    }
}
