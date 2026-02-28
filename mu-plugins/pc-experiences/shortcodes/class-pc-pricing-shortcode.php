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
    protected function render(array $atts): void
    {
        if (!function_exists('have_rows') || !have_rows('exp_types_de_tarifs')) {
            return;
        }

        // --- DÉBUT DU RENDU ---
?>
        <div class="exp-pricing-grid">
            <?php while (have_rows('exp_types_de_tarifs')) : the_row();
                // Type & label
                $type_field  = get_sub_field_object('exp_type');
                $type_value  = is_array($type_field) ? ($type_field['value'] ?? '') : '';
                $choices     = is_array($type_field) ? ((array)($type_field['choices'] ?? [])) : [];
                $row_payload = ['exp_type' => $type_value, 'exp_type_custom' => get_sub_field('exp_type_custom')];

                // Utilisation de notre Helper créé à l'étape 1
                $type_label = class_exists('PC_Experience_Field_Helper')
                    ? PC_Experience_Field_Helper::resolve_pricing_type_label($row_payload, $choices)
                    : (isset($choices[$type_value]) ? $choices[$type_value] : ucfirst((string)$type_value));
            ?>
                <div class="exp-pricing-card">
                    <h3 class="exp-pricing-title"><?php echo esc_html($type_label); ?></h3>
                    <div class="exp-pricing-body">
                        <?php if ($type_value === 'sur-devis') : ?>
                            <div class="exp-pricing-row on-demand"><?php echo esc_html__('Sur devis', 'pc'); ?></div>
                            <?php else :
                            // LIGNES TARIFS (Standard)
                            $lines = (array) get_sub_field('exp_tarifs_lignes');
                            if (!empty($lines)) :
                                foreach ($lines as $ln) :
                                    $t     = $ln['type_ligne'] ?? 'personnalise';
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
                            $options_data = get_sub_field('exp_options_tarifaires');
                            $fees_data    = get_sub_field('exp-frais-fixes');

                            // On s'assure que ce sont bien des tableaux
                            if (!is_array($options_data)) $options_data = [];
                            if (!is_array($fees_data))    $fees_data    = [];

                            // S'il y a du contenu à afficher
                            if (!empty($options_data) || !empty($fees_data)) : ?>

                                <div class="exp-pricing-options">

                                    <?php // 1. OPTIONS 
                                    ?>
                                    <?php if (!empty($options_data)) : ?>
                                        <div class="exp-pricing-options-title"><?php echo esc_html__('Options', 'pc'); ?></div>
                                        <?php foreach ($options_data as $opt) :
                                            $opt_label = (string)($opt['exp_description_option'] ?? '');
                                            $opt_price = (float)($opt['exp_tarif_option'] ?? 0);
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
                                        // Espaceur si options au-dessus
                                        if (!empty($options_data)) {
                                            echo '<div style="height: 1rem;"></div>';
                                        }
                                    ?>
                                        <div class="exp-pricing-options-title"><?php echo esc_html__('Frais fixes', 'pc'); ?></div>
                                        <?php foreach ($fees_data as $fee) :
                                            $fee_label = (string)($fee['exp_description_frais_fixe'] ?? '');
                                            $fee_price = (float)($fee['exp_tarif_frais_fixe'] ?? 0);
                                            // On saute si vide ou gratuit
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
            <?php endwhile; ?>
        </div>
<?php
        // --- FIN DU RENDU ---
    }
}
